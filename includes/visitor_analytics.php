<?php
/**
 * Visitor analytics helper responsible for logging page views
 * and aggregating statistics for the admin dashboard.
 */
class VisitorAnalytics
{
    private static bool $trackedThisRequest = false;

    /**
     * Persist a visitor log entry for the current request.
     */
    public static function track(PDO $connection): void
    {
        if (self::$trackedThisRequest) {
            return;
        }

        if (PHP_SAPI === 'cli') {
            return;
        }

        if (empty($_SERVER['REQUEST_URI'])) {
            return;
        }

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return; // Skip ajax calls to avoid noise.
        }

        $path = parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
        if ($path && preg_match('/\.(?:css|js|png|jpe?g|gif|svg|ico|woff2?|ttf|map)$/i', $path)) {
            return;
        }

        try {
            $ip = self::resolveIp();
            $country = self::resolveCountry();
            $referrer = isset($_SERVER['HTTP_REFERER']) ? trim((string) $_SERVER['HTTP_REFERER']) : null;
            $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? trim((string) $_SERVER['HTTP_USER_AGENT']) : null;

            $insert = $connection->prepare(
                'INSERT INTO visitor_logs (ip_address, country, page_url, referrer, user_agent, visit_time)
                 VALUES (:ip, :country, :page, :referrer, :user_agent, NOW())'
            );

            $insert->execute([
                ':ip' => $ip,
                ':country' => $country,
                ':page' => self::normalise((string) $_SERVER['REQUEST_URI'], 512),
                ':referrer' => $referrer ? self::normalise($referrer, 512) : null,
                ':user_agent' => $userAgent ? self::normalise($userAgent, 512) : null,
            ]);

            self::$trackedThisRequest = true;
        } catch (Throwable $exception) {
            error_log('[VisitorAnalytics] Failed to record visit: ' . $exception->getMessage());
        }
    }

    /**
     * Build analytics summary (visits, unique visitors, page views, returning visitors).
     */
    public static function fetchSummary(PDO $connection, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        try {
            $query = $connection->prepare(
                'SELECT
                    (SELECT COUNT(*) FROM visitor_logs WHERE visit_time BETWEEN :start AND :end) AS page_views,
                    (SELECT COUNT(DISTINCT ip_address) FROM visitor_logs WHERE visit_time BETWEEN :start AND :end) AS unique_visitors,
                    (SELECT COUNT(DISTINCT CONCAT(ip_address, DATE(visit_time))) FROM visitor_logs WHERE visit_time BETWEEN :start AND :end) AS total_visits,
                    (SELECT COUNT(*) FROM (
                        SELECT ip_address
                        FROM visitor_logs
                        WHERE visit_time BETWEEN :start AND :end
                        GROUP BY ip_address
                        HAVING COUNT(*) > 1
                    ) AS returning) AS returning_visitors'
            );

            $query->execute([
                ':start' => $start->format('Y-m-d H:i:s'),
                ':end' => $end->format('Y-m-d H:i:s'),
            ]);

            $row = $query->fetch(PDO::FETCH_ASSOC) ?: [];

            return [
                'page_views' => (int) ($row['page_views'] ?? 0),
                'unique_visitors' => (int) ($row['unique_visitors'] ?? 0),
                'total_visits' => (int) ($row['total_visits'] ?? 0),
                'returning_visitors' => (int) ($row['returning_visitors'] ?? 0),
            ];
        } catch (Throwable $exception) {
            error_log('[VisitorAnalytics] Summary query failed: ' . $exception->getMessage());
            return [
                'page_views' => 0,
                'unique_visitors' => 0,
                'total_visits' => 0,
                'returning_visitors' => 0,
            ];
        }
    }

    public static function fetchTimeSeries(PDO $connection, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $series = [];

        try {
            $query = $connection->prepare(
                "SELECT DATE(visit_time) AS bucket,
                        COUNT(*) AS views,
                        COUNT(DISTINCT ip_address) AS visitors
                 FROM visitor_logs
                 WHERE visit_time BETWEEN :start AND :end
                 GROUP BY bucket
                 ORDER BY bucket ASC"
            );

            $query->execute([
                ':start' => $start->format('Y-m-d H:i:s'),
                ':end' => $end->format('Y-m-d H:i:s'),
            ]);

            $rows = $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $exception) {
            error_log('[VisitorAnalytics] Time series query failed: ' . $exception->getMessage());
            $rows = [];
        }
        $cursor = $start;
        while ($cursor <= $end) {
            $key = $cursor->format('Y-m-d');
            $series[$key] = ['views' => 0, 'visitors' => 0];
            $cursor = $cursor->modify('+1 day');
        }

        foreach ($rows as $row) {
            $key = $row['bucket'];
            if (isset($series[$key])) {
                $series[$key]['views'] = (int) $row['views'];
                $series[$key]['visitors'] = (int) $row['visitors'];
            }
        }

        return $series;
    }

    public static function fetchTopPages(PDO $connection, DateTimeImmutable $start, DateTimeImmutable $end, int $limit = 10): array
    {
        try {
            $query = $connection->prepare(
                'SELECT page_url, COUNT(*) AS views
                 FROM visitor_logs
                 WHERE visit_time BETWEEN :start AND :end
                 GROUP BY page_url
                 ORDER BY views DESC
                 LIMIT :limit'
            );

            $query->bindValue(':start', $start->format('Y-m-d H:i:s'));
            $query->bindValue(':end', $end->format('Y-m-d H:i:s'));
            $query->bindValue(':limit', $limit, PDO::PARAM_INT);
            $query->execute();

            return $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $exception) {
            error_log('[VisitorAnalytics] Top pages query failed: ' . $exception->getMessage());
            return [];
        }
    }

    public static function fetchBreakdown(PDO $connection, string $field, DateTimeImmutable $start, DateTimeImmutable $end, int $limit = 10): array
    {
        if (!in_array($field, ['country', 'referrer'], true)) {
            return [];
        }

        $column = $field === 'country' ? 'country' : 'COALESCE(NULLIF(referrer, \'\'), \"Direct\")';

        try {
            $query = $connection->prepare(
                "SELECT {$column} AS label, COUNT(*) AS views
                 FROM visitor_logs
                 WHERE visit_time BETWEEN :start AND :end
                 GROUP BY label
                 ORDER BY views DESC
                 LIMIT :limit"
            );

            $query->bindValue(':start', $start->format('Y-m-d H:i:s'));
            $query->bindValue(':end', $end->format('Y-m-d H:i:s'));
            $query->bindValue(':limit', $limit, PDO::PARAM_INT);
            $query->execute();

            return $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $exception) {
            error_log('[VisitorAnalytics] Breakdown query failed: ' . $exception->getMessage());
            return [];
        }
    }

    public static function fetchLogFilters(PDO $connection): array
    {
        try {
            $countries = $connection->query('SELECT DISTINCT country FROM visitor_logs ORDER BY country ASC')->fetchAll(PDO::FETCH_COLUMN);
            $referrers = $connection->query('SELECT DISTINCT COALESCE(NULLIF(referrer, \'\'), "Direct") FROM visitor_logs ORDER BY 1 ASC')->fetchAll(PDO::FETCH_COLUMN);

            return [
                'countries' => array_filter(array_map('strval', $countries)),
                'referrers' => array_filter(array_map('strval', $referrers)),
            ];
        } catch (Throwable $exception) {
            error_log('[VisitorAnalytics] Filter query failed: ' . $exception->getMessage());
            return ['countries' => [], 'referrers' => []];
        }
    }

    public static function fetchLogs(PDO $connection, array $filters, int $limit = 100): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['start'])) {
            $conditions[] = 'visit_time >= :start';
            $params[':start'] = $filters['start'];
        }

        if (!empty($filters['end'])) {
            $conditions[] = 'visit_time <= :end';
            $params[':end'] = $filters['end'];
        }

        if (!empty($filters['country'])) {
            $conditions[] = 'country = :country';
            $params[':country'] = $filters['country'];
        }

        if (!empty($filters['referrer'])) {
            if ($filters['referrer'] === 'Direct') {
                $conditions[] = '(referrer IS NULL OR referrer = \"\")';
            } else {
                $conditions[] = 'referrer = :referrer';
                $params[':referrer'] = $filters['referrer'];
            }
        }

        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

        try {
            $query = $connection->prepare(
                "SELECT ip_address, country, page_url, COALESCE(NULLIF(referrer, ''), 'Direct') AS referrer,
                        user_agent, visit_time
                 FROM visitor_logs
                 {$where}
                 ORDER BY visit_time DESC
                 LIMIT :limit"
            );

            foreach ($params as $key => $value) {
                $query->bindValue($key, $value);
            }

            $query->bindValue(':limit', $limit, PDO::PARAM_INT);
            $query->execute();

            return $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $exception) {
            error_log('[VisitorAnalytics] Log fetch failed: ' . $exception->getMessage());
            return [];
        }
    }

    private static function resolveIp(): string
    {
        $keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $raw = explode(',', (string) $_SERVER[$key]);
                foreach ($raw as $candidate) {
                    $candidate = trim($candidate);
                    if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                        return $candidate;
                    }
                }
            }
        }

        return '0.0.0.0';
    }

    private static function resolveCountry(): string
    {
        $sources = [
            'HTTP_CF_IPCOUNTRY',
            'HTTP_CF_IPCOUNTRY',
            'HTTP_X_COUNTRY_CODE',
            'GEOIP_COUNTRY_CODE',
        ];

        foreach ($sources as $key) {
            if (!empty($_SERVER[$key])) {
                $value = strtoupper(substr((string) $_SERVER[$key], 0, 2));
                if (preg_match('/^[A-Z]{2}$/', $value)) {
                    return $value;
                }
            }
        }

        return 'Unknown';
    }

    private static function normalise(string $value, int $maxLength): string
    {
        $value = trim($value);
        if (strlen($value) > $maxLength) {
            $value = substr($value, 0, $maxLength);
        }
        return $value;
    }
}
