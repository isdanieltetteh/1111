<?php
/**
 * Helper functions for managing advertisement settings stored in the ad_settings table.
 */

/**
 * Retrieve an advertisement setting by key.
 *
 * @param PDO    $db       Database connection.
 * @param string $key      Setting key to retrieve.
 * @param mixed  $default  Default value if the key is not found.
 *
 * @return mixed The stored setting value or the provided default.
 */
function get_ad_setting(PDO $db, string $key, $default = null) {
    $query = "SELECT setting_value FROM ad_settings WHERE setting_key = :key LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':key', $key);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result !== false) {
        return $result['setting_value'];
    }

    return $default;
}

/**
 * Persist a setting value in the ad_settings table.
 *
 * @param PDO    $db    Database connection.
 * @param string $key   Setting key to update.
 * @param mixed  $value Value to store. It will be converted to string.
 *
 * @return bool True on success, false on failure.
 */
function set_ad_setting(PDO $db, string $key, $value): bool {
    $query = "INSERT INTO ad_settings (setting_key, setting_value)
              VALUES (:key, :value)
              ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':key', $key);
    $stmt->bindValue(':value', (string) $value);

    return $stmt->execute();
}

/**
 * Retrieve multiple settings at once.
 *
 * @param PDO  $db      Database connection.
 * @param array $keys   List of setting keys.
 * @param mixed $default Default value for missing keys.
 *
 * @return array Associative array of key => value.
 */
function get_ad_settings(PDO $db, array $keys, $default = null): array {
    if (empty($keys)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $query = "SELECT setting_key, setting_value FROM ad_settings WHERE setting_key IN ($placeholders)";
    $stmt = $db->prepare($query);
    $stmt->execute(array_values($keys));

    $settings = array_fill_keys($keys, $default);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    return $settings;
}

/**
 * Format a bid rate for display using up to four decimal places while trimming
 * trailing zeros. Used when presenting CPC/CPM floors or advertiser-entered
 * bids so that fractional cent precision is preserved without visual noise.
 */
function format_ad_rate($value, int $precision = 4): string {
    $numeric = is_numeric($value) ? (float) $value : 0.0;
    if ($numeric <= 0) {
        return '0';
    }

    $formatted = number_format($numeric, $precision, '.', '');
    $trimmed = rtrim(rtrim($formatted, '0'), '.');

    return $trimmed === '' ? '0' : $trimmed;
}
