<?php
class AdManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Get a random active ad for a specific space
     */
    public function getRandomAd($ad_type = 'banner', $prefer_premium = true, $space_id = null) {
        try {
            $query = "SELECT ua.*
                      FROM user_advertisements ua
                      WHERE ua.status = 'active'
                        AND ua.ad_type = :ad_type
                        AND (ua.campaign_type = 'standard' OR ua.budget_remaining > 0)
                        AND (ua.start_date IS NULL OR ua.start_date <= NOW())
                        AND (ua.end_date IS NULL OR ua.end_date >= NOW() OR ua.campaign_type IN ('cpc','cpm'))";

            if ($space_id) {
                $query .= " AND ua.placement_type = 'targeted' AND ua.target_space_id = :space_id";
            }

            $query .= $this->buildOrderingClause($prefer_premium) . ' LIMIT 1';

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':ad_type', $ad_type);
            if ($space_id) {
                $stmt->bindParam(':space_id', $space_id);
            }
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get ad for specific space by space_id string
     */
    public function getAdForSpace($space_id, $space = null) {
        try {
            if ($space === null) {
                $space_stmt = $this->db->prepare("SELECT * FROM ad_spaces WHERE space_id = :space_id LIMIT 1");
                $space_stmt->bindParam(':space_id', $space_id);
                $space_stmt->execute();
                $space = $space_stmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$space) {
                return null;
            }

            $ad = $this->fetchAdForSpace($space, false);

            if (!$ad) {
                $ad = $this->fetchAdForSpace($space, true);
            }

            return $ad;
        } catch (Exception $e) {
            return null;
        }
    }

    private function fetchAdForSpace(array $space, bool $allowGeneral = false) {
        $adTypes = [];
        if ($space['ad_type'] === 'both') {
            $adTypes = ['banner', 'text'];
        } else {
            $adTypes = [$space['ad_type']];
        }

        $query = "SELECT ua.*
                  FROM user_advertisements ua
                  WHERE ua.status = 'active'
                    AND ua.ad_type IN (" . implode(',', array_fill(0, count($adTypes), '?')) . ")
                    AND (ua.campaign_type = 'standard' OR ua.budget_remaining > 0)
                    AND (ua.start_date IS NULL OR ua.start_date <= NOW())
                    AND (ua.end_date IS NULL OR ua.end_date >= NOW() OR ua.campaign_type IN ('cpc','cpm'))";

        $params = $adTypes;

        if ($allowGeneral) {
            $query .= " AND ua.placement_type = 'general'";

            if (!empty($space['width']) && !empty($space['height'])) {
                $query .= " AND (ua.target_width IS NULL OR ua.target_width = ?)";
                $query .= " AND (ua.target_height IS NULL OR ua.target_height = ?)";
                $params[] = (int) $space['width'];
                $params[] = (int) $space['height'];
            } else {
                $query .= " AND ua.target_width IS NULL AND ua.target_height IS NULL";
            }
        } else {
            $query .= " AND ua.placement_type = 'targeted' AND ua.target_space_id = ?";
            $params[] = $space['space_id'];
        }

        $query .= $this->buildOrderingClause(true) . ' LIMIT 1';

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Get multiple ads for display
     */
    public function getAds($ad_type = 'banner', $limit = 3) {
        try {
            $query = "SELECT ua.* FROM user_advertisements ua
                      WHERE ua.status = 'active'
                        AND ua.ad_type = :ad_type
                        AND (ua.campaign_type = 'standard' OR ua.budget_remaining > 0)
                        AND (ua.start_date IS NULL OR ua.start_date <= NOW())
                        AND (ua.end_date IS NULL OR ua.end_date >= NOW() OR ua.campaign_type IN ('cpc','cpm'))";

            $query .= $this->buildOrderingClause(true) . ' LIMIT :limit';

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':ad_type', $ad_type);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Track ad impression
     */
    public function trackImpression($ad_id, $user_id = null, $page_url = null) {
        try {
            $ad = $this->getAdById($ad_id);

            if (!$ad || ($ad['campaign_type'] !== 'standard' && $ad['budget_remaining'] <= 0)) {
                return false;
            }

            $update_query = "UPDATE user_advertisements
                             SET impression_count = impression_count + 1,
                                 updated_at = NOW()
                             WHERE id = :ad_id";
            $update_stmt = $this->db->prepare($update_query);
            $update_stmt->bindParam(':ad_id', $ad_id);
            $update_stmt->execute();

            if ($ad['campaign_type'] === 'cpm' && $ad['cpm_rate'] > 0) {
                $this->applySpend($ad, $ad['cpm_rate'] / 1000, 'impression');
            }

            $log_query = "INSERT INTO ad_impressions
                          (ad_id, user_id, ip_address, user_agent, page_url)
                          VALUES (:ad_id, :user_id, :ip_address, :user_agent, :page_url)";
            $log_stmt = $this->db->prepare($log_query);
            $log_stmt->bindParam(':ad_id', $ad_id);
            $log_stmt->bindParam(':user_id', $user_id);

            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            $log_stmt->bindParam(':ip_address', $ip_address);
            $log_stmt->bindParam(':user_agent', $user_agent);
            $log_stmt->bindParam(':page_url', $page_url);
            $log_stmt->execute();

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Track ad click
     */
    public function trackClick($ad_id, $user_id = null, $referrer_url = null) {
        try {
            $ad = $this->getAdById($ad_id);

            if (!$ad || ($ad['campaign_type'] !== 'standard' && $ad['budget_remaining'] <= 0)) {
                return false;
            }

            $update_query = "UPDATE user_advertisements
                             SET click_count = click_count + 1,
                                 updated_at = NOW()
                             WHERE id = :ad_id";
            $update_stmt = $this->db->prepare($update_query);
            $update_stmt->bindParam(':ad_id', $ad_id);
            $update_stmt->execute();

            if ($ad['campaign_type'] === 'cpc' && $ad['cpc_rate'] > 0) {
                $this->applySpend($ad, $ad['cpc_rate'], 'click');
            }

            $log_query = "INSERT INTO ad_clicks
                          (ad_id, user_id, ip_address, user_agent, referrer_url)
                          VALUES (:ad_id, :user_id, :ip_address, :user_agent, :referrer_url)";
            $log_stmt = $this->db->prepare($log_query);
            $log_stmt->bindParam(':ad_id', $ad_id);
            $log_stmt->bindParam(':user_id', $user_id);

            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            $log_stmt->bindParam(':ip_address', $ip_address);
            $log_stmt->bindParam(':user_agent', $user_agent);
            $log_stmt->bindParam(':referrer_url', $referrer_url);
            $log_stmt->execute();

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Render banner ad HTML
     */
    public function renderBannerAd($ad, $track_impression = true, $space_width = null, $space_height = null) {
        if (!$ad) {
            return '';
        }
        
        if ($track_impression) {
            $user_id = $_SESSION['user_id'] ?? null;
            $page_url = $_SERVER['REQUEST_URI'] ?? null;
            $this->trackImpression($ad['id'], $user_id, $page_url);
        }
        
        $randomClass = 'content-' . uniqid();
        $html = '<div class="' . $randomClass . '" data-ad-id="' . $ad['id'] . '">';
        $html .= '<a href="ad-click.php?id=' . $ad['id'] . '" target="_blank" rel="noopener">';
        
        if ($ad['banner_image']) {
            $html .= '<img src="' . htmlspecialchars($ad['banner_image']) . '" ';
            $html .= 'alt="' . htmlspecialchars($ad['banner_alt_text']) . '" ';
            $html .= 'style="';
            $html .= 'max-width: 100%; ';
            if ($space_width) $html .= 'max-width: ' . $space_width . 'px; ';
            if ($space_height) $html .= 'max-height: ' . $space_height . 'px; ';
            $html .= 'width: auto; height: auto; border-radius: 0.5rem;"';
        }
        
        $html .= '</a>';
        $html .= '<small style="display: block; text-align: center; color: #64748b; font-size: 0.75rem; margin-top: 0.5rem;">Featured</small>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Render text ad HTML
     */
    public function renderTextAd($ad, $track_impression = true, $space_width = null, $space_height = null) {
        if (!$ad) {
            return '';
        }
        
        if ($track_impression) {
            $user_id = $_SESSION['user_id'] ?? null;
            $page_url = $_SERVER['REQUEST_URI'] ?? null;
            $this->trackImpression($ad['id'], $user_id, $page_url);
        }
        
        $randomClass = 'content-' . uniqid();
        $html = '<div class="' . $randomClass . '" data-ad-id="' . $ad['id'] . '" style="';
        $html .= 'background: rgba(59, 130, 246, 0.1); ';
        $html .= 'border: 1px solid rgba(59, 130, 246, 0.2); ';
        $html .= 'border-radius: 0.75rem; ';
        $html .= 'padding: 1.5rem; ';
        $html .= 'margin: 1rem 0;';
        
        if ($space_width) {
            $html .= 'max-width: ' . $space_width . 'px; ';
        }
        if ($space_height) {
            $html .= 'min-height: ' . max($space_height, 100) . 'px; ';
        }
        
        $html .= '">';
        
        $html .= '<a href="ad-click.php?id=' . $ad['id'] . '" target="_blank" style="text-decoration: none; color: inherit;">';
        $html .= '<h4 style="color: #3b82f6; font-weight: 700; margin-bottom: 0.5rem;">';
        $html .= htmlspecialchars($ad['text_title']);
        $html .= '</h4>';
        $html .= '<p style="color: #94a3b8; font-size: 0.875rem; margin-bottom: 0;">';
        $html .= htmlspecialchars($ad['text_description']);
        $html .= '</p>';
        $html .= '</a>';
        
        $html .= '<small style="display: block; color: #64748b; font-size: 0.75rem; margin-top: 0.75rem;">Featured</small>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Check and expire old ads
     */
    public function expireOldAds() {
        try {
            $query = "UPDATE user_advertisements
                      SET status = CASE
                            WHEN campaign_type IN ('cpc','cpm') AND budget_remaining <= 0 THEN 'completed'
                            ELSE 'expired'
                      END
                      WHERE status = 'active'
                        AND campaign_type = 'standard'
                        AND end_date <= NOW()";
            $stmt = $this->db->prepare($query);
            return $stmt->execute();
        } catch (Exception $e) {
            return false;
        }
    }

    private function buildOrderingClause($includeVisibilityPriority = true) {
        $clauses = [];

        if ($includeVisibilityPriority) {
            $clauses[] = "CASE ua.visibility_level WHEN 'premium' THEN 1 ELSE 2 END";
        }

        $clauses[] = "CASE ua.campaign_type WHEN 'cpc' THEN 1 WHEN 'cpm' THEN 2 ELSE 3 END";
        $clauses[] = "CASE WHEN ua.campaign_type = 'cpc' THEN ua.cpc_rate WHEN ua.campaign_type = 'cpm' THEN ua.cpm_rate ELSE 0 END DESC";
        $clauses[] = "ua.updated_at DESC";
        $clauses[] = "RAND()";

        return ' ORDER BY ' . implode(', ', $clauses);
    }

    private function getAdById($ad_id) {
        $stmt = $this->db->prepare("SELECT * FROM user_advertisements WHERE id = :ad_id LIMIT 1");
        $stmt->bindParam(':ad_id', $ad_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function applySpend(array $ad, float $amount, string $trigger) {
        if ($amount <= 0) {
            return;
        }

        $update_query = "UPDATE user_advertisements
                         SET budget_remaining = GREATEST(budget_remaining - :amount, 0),
                             total_spent = total_spent + :amount,
                             status = CASE
                                 WHEN campaign_type IN ('cpc','cpm') AND (budget_remaining - :amount) <= 0 THEN 'completed'
                                 ELSE status
                             END,
                             updated_at = NOW()
                         WHERE id = :ad_id";
        $update_stmt = $this->db->prepare($update_query);
        $update_stmt->bindParam(':amount', $amount);
        $update_stmt->bindParam(':ad_id', $ad['id']);
        $update_stmt->execute();

        $description = sprintf('Automatic %s spend for campaign #%d', $trigger, $ad['id']);
        $log_query = "INSERT INTO ad_transactions (ad_id, user_id, amount, transaction_type, description)
                      VALUES (:ad_id, :user_id, :amount, 'spend', :description)";
        $log_stmt = $this->db->prepare($log_query);
        $log_amount = -abs($amount);
        $log_stmt->bindParam(':ad_id', $ad['id']);
        $log_stmt->bindParam(':user_id', $ad['user_id']);
        $log_stmt->bindParam(':amount', $log_amount);
        $log_stmt->bindParam(':description', $description);
        $log_stmt->execute();
    }
}
?>
