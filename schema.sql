-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 02, 2025 at 08:40 PM
-- Server version: 11.4.8-MariaDB
-- PHP Version: 8.4.11

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mwqnrvfg_faucetguardpop`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_action_logs`
--

CREATE TABLE `admin_action_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `target_type` varchar(50) NOT NULL,
  `target_id` int(11) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_action_logs`
--

INSERT INTO `admin_action_logs` (`id`, `admin_id`, `action`, `target_type`, `target_id`, `details`, `ip_address`, `created_at`) VALUES
(1, 28, 'sponsored_site', 'site', 66, '{\"sponsored\":1,\"until\":\"2025-11-01 04:51:35\"}', '102.176.75.94', '2025-10-02 04:51:35'),
(2, 28, 'sponsored_site', 'site', 63, '{\"sponsored\":1,\"until\":\"2025-11-01 04:51:42\"}', '102.176.75.94', '2025-10-02 04:51:42'),
(3, 28, 'boosted_site', 'site', 70, '{\"boosted\":1,\"until\":\"2025-11-01 07:47:11\"}', '102.176.75.94', '2025-10-02 07:47:11');

-- --------------------------------------------------------

--
-- Table structure for table `ad_clicks`
--

CREATE TABLE `ad_clicks` (
  `id` bigint(20) NOT NULL,
  `ad_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `referrer_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ad_impressions`
--

CREATE TABLE `ad_impressions` (
  `id` bigint(20) NOT NULL,
  `ad_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `page_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ad_pricing`
--

CREATE TABLE `ad_pricing` (
  `id` int(11) NOT NULL,
  `ad_type` enum('banner','text') NOT NULL,
  `duration_days` int(11) NOT NULL,
  `base_price` decimal(10,4) NOT NULL,
  `premium_multiplier` decimal(3,2) DEFAULT 2.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ad_pricing`
--

INSERT INTO `ad_pricing` (`id`, `ad_type`, `duration_days`, `base_price`, `premium_multiplier`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'banner', 1, 5.0000, 2.00, 1, '2025-10-02 11:19:37', '2025-10-02 11:19:37'),
(2, 'banner', 3, 12.0000, 2.00, 1, '2025-10-02 11:19:37', '2025-10-02 11:19:37'),
(3, 'banner', 7, 25.0000, 2.00, 1, '2025-10-02 11:19:37', '2025-10-02 11:19:37'),
(4, 'banner', 10, 32.0000, 2.00, 1, '2025-10-02 11:19:37', '2025-10-02 11:19:37'),
(5, 'banner', 30, 80.0000, 2.00, 1, '2025-10-02 11:19:37', '2025-10-02 11:19:37'),
(6, 'text', 1, 3.0000, 2.00, 1, '2025-10-02 11:19:37', '2025-10-02 11:19:37'),
(7, 'text', 3, 7.5000, 2.00, 1, '2025-10-02 11:19:37', '2025-10-02 11:19:37'),
(8, 'text', 7, 15.0000, 2.00, 1, '2025-10-02 11:19:37', '2025-10-02 11:19:37'),
(9, 'text', 10, 20.0000, 2.00, 1, '2025-10-02 11:19:37', '2025-10-02 11:19:37'),
(10, 'text', 30, 50.0000, 2.00, 1, '2025-10-02 11:19:37', '2025-10-02 11:19:37');

-- --------------------------------------------------------

--
-- Table structure for table `ad_settings`
--

CREATE TABLE `ad_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ad_settings`
--

INSERT INTO `ad_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'max_active_ads_per_user', '5', '2025-10-02 11:19:37'),
(2, 'auto_approve_ads', '0', '2025-10-02 11:19:37'),
(3, 'require_admin_approval', '1', '2025-10-02 11:19:37'),
(4, 'banner_max_file_size', '2097152', '2025-10-02 11:19:37'),
(5, 'allowed_image_types', 'image/jpeg,image/png,image/gif', '2025-10-02 11:19:37'),
(6, 'rotation_algorithm', 'fair', '2025-10-02 11:19:37'),
(7, 'min_credit_balance', '1.00', '2025-10-02 11:19:37');

-- --------------------------------------------------------

--
-- Table structure for table `ad_spaces`
--

CREATE TABLE `ad_spaces` (
  `id` int(11) NOT NULL,
  `space_id` varchar(100) NOT NULL COMMENT 'Unique identifier like index_top_banner',
  `space_name` varchar(255) NOT NULL COMMENT 'Human-readable name',
  `page_location` varchar(100) NOT NULL COMMENT 'Page where ad appears',
  `position` varchar(100) NOT NULL COMMENT 'Position on page',
  `ad_type` enum('banner','text','both') NOT NULL DEFAULT 'both',
  `width` int(11) DEFAULT NULL COMMENT 'Recommended width in pixels',
  `height` int(11) DEFAULT NULL COMMENT 'Recommended height in pixels',
  `base_price_multiplier` decimal(3,2) DEFAULT 1.00 COMMENT 'Price multiplier for this space',
  `is_enabled` tinyint(1) DEFAULT 1,
  `is_premium_only` tinyint(1) DEFAULT 0 COMMENT 'Only premium ads can use this space',
  `display_order` int(11) DEFAULT 0,
  `max_ads_rotation` int(11) DEFAULT 5 COMMENT 'Max ads in rotation pool',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ad_spaces`
--

INSERT INTO `ad_spaces` (`id`, `space_id`, `space_name`, `page_location`, `position`, `ad_type`, `width`, `height`, `base_price_multiplier`, `is_enabled`, `is_premium_only`, `display_order`, `max_ads_rotation`, `created_at`, `updated_at`) VALUES
(1, 'index_top_banner', 'Homepage Top Banner', 'index', 'top', 'banner', 728, 90, 2.00, 1, 0, 1, 5, '2025-10-02 20:09:57', '2025-10-02 20:09:57'),
(2, 'index_sidebar_1', 'Homepage Sidebar #1', 'index', 'sidebar', 'both', 300, 250, 1.50, 1, 0, 2, 5, '2025-10-02 20:09:57', '2025-10-02 20:09:57'),
(3, 'index_middle_banner', 'Homepage Middle Banner', 'index', 'middle', 'banner', 728, 90, 1.80, 1, 0, 3, 5, '2025-10-02 20:09:57', '2025-10-02 20:09:57'),
(4, 'index_sidebar_2', 'Homepage Sidebar #2', 'index', 'sidebar', 'both', 300, 250, 1.50, 1, 0, 4, 5, '2025-10-02 20:09:57', '2025-10-02 20:09:57'),
(5, 'index_bottom_banner', 'Homepage Bottom Banner', 'index', 'bottom', 'banner', 728, 90, 1.50, 1, 0, 5, 5, '2025-10-02 20:09:57', '2025-10-02 20:09:57'),
(6, 'sites_top_banner', 'Sites Listing Top Banner', 'sites', 'top', 'banner', 728, 90, 1.80, 1, 0, 1, 5, '2025-10-02 20:09:57', '2025-10-02 20:09:57'),
(7, 'sites_sidebar_1', 'Sites Listing Sidebar #1', 'sites', 'sidebar', 'both', 300, 250, 1.50, 1, 0, 2, 5, '2025-10-02 20:09:57', '2025-10-02 20:09:57'),
(8, 'sites_between_results_1', 'Sites Between Results #1', 'sites', 'between_results', 'both', 468, 60, 1.30, 1, 0, 3, 5, '2025-10-02 20:09:57', '2025-10-02 20:09:57'),
(9, 'sites_between_results_2', 'Sites Between Results #2', 'sites', 'between_results', 'both', 468, 60, 1.30, 1, 0, 4, 5, '2025-10-02 20:09:57', '2025-10-02 20:09:57'),
(10, 'sites_bottom_banner', 'Sites Listing Bottom Banner', 'sites', 'bottom', 'banner', 728, 90, 1.40, 1, 0, 5, 5, '2025-10-02 20:09:57', '2025-10-02 20:09:57'),
(11, 'review_top_banner', 'Review Page Top Banner', 'review', 'top', 'banner', 728, 90, 1.70, 1, 0, 1, 5, '2025-10-02 20:09:57', '2025-10-02 20:09:57'),
(12, 'review_sidebar_1', 'Review Page Sidebar #1', 'review', 'sidebar', 'both', 300, 250, 1.60, 1, 0, 2, 5, '2025-10-02 20:09:57', '2025-10-02 20:09:57'),
(13, 'review_sidebar_2', 'Review Page Sidebar #2', 'review', 'sidebar', 'both', 300, 600, 1.60, 1, 0, 3, 5, '2025-10-02 20:09:57', '2025-10-02 20:09:57'),
(14, 'review_bottom_banner', 'Review Page Bottom Banner', 'review', 'bottom', 'banner', 728, 90, 1.50, 1, 0, 4, 5, '2025-10-02 20:09:57', '2025-10-02 20:09:57'),
(15, 'rankings_top_banner', 'Rankings Top Banner', 'rankings', 'top', 'banner', 728, 90, 1.90, 1, 0, 1, 5, '2025-10-02 20:09:57', '2025-10-02 20:09:57'),
(16, 'rankings_sidebar', 'Rankings Sidebar', 'rankings', 'sidebar', 'both', 300, 250, 1.60, 1, 0, 2, 5, '2025-10-02 20:09:57', '2025-10-02 20:09:57'),
(17, 'rankings_between_ranks', 'Rankings Between Ranks', 'rankings', 'between_results', 'both', 468, 60, 1.40, 1, 0, 3, 5, '2025-10-02 20:09:57', '2025-10-02 20:09:57'),
(18, 'rankings_bottom_banner', 'Rankings Bottom Banner', 'rankings', 'bottom', 'banner', 728, 90, 1.50, 1, 0, 4, 5, '2025-10-02 20:09:57', '2025-10-02 20:09:57'),
(19, 'dashboard_top_banner', 'Dashboard Top Banner', 'dashboard', 'top', 'banner', 728, 90, 1.20, 1, 0, 1, 5, '2025-10-02 20:09:57', '2025-10-02 20:09:57'),
(20, 'dashboard_sidebar', 'Dashboard Sidebar', 'dashboard', 'sidebar', 'both', 300, 250, 1.10, 1, 0, 2, 5, '2025-10-02 20:09:57', '2025-10-02 20:09:57'),
(21, 'dashboard_bottom_text', 'Dashboard Bottom Text Ad', 'dashboard', 'bottom', 'text', NULL, NULL, 1.00, 1, 0, 3, 5, '2025-10-02 20:09:57', '2025-10-02 20:09:57'),
(22, 'redirect_top_banner', 'Redirect Page Top Banner', 'redirect', 'top', 'banner', 728, 90, 2.50, 1, 1, 1, 5, '2025-10-02 20:09:57', '2025-10-02 20:09:57'),
(23, 'redirect_middle_banner', 'Redirect Page Middle Banner', 'redirect', 'middle', 'banner', 468, 60, 2.20, 1, 0, 2, 5, '2025-10-02 20:09:57', '2025-10-02 20:09:57'),
(24, 'redirect_sidebar', 'Redirect Page Sidebar', 'redirect', 'sidebar', 'both', 300, 250, 2.00, 1, 0, 3, 5, '2025-10-02 20:09:57', '2025-10-02 20:09:57');

-- --------------------------------------------------------

--
-- Table structure for table `ad_space_assignments`
--

CREATE TABLE `ad_space_assignments` (
  `id` int(11) NOT NULL,
  `ad_id` int(11) NOT NULL,
  `space_id` varchar(100) NOT NULL,
  `is_cross_pool` tinyint(1) DEFAULT 0 COMMENT 'If 1, ad rotates across multiple spaces',
  `priority` int(11) DEFAULT 0 COMMENT 'Higher priority shows more often',
  `last_shown` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ad_transactions`
--

CREATE TABLE `ad_transactions` (
  `id` int(11) NOT NULL,
  `ad_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,4) NOT NULL,
  `transaction_type` enum('purchase','refund') NOT NULL DEFAULT 'purchase',
  `description` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `automated_notifications`
--

CREATE TABLE `automated_notifications` (
  `id` int(11) NOT NULL,
  `trigger_event` varchar(100) NOT NULL,
  `recipient_type` enum('user','admin','submitter','all') NOT NULL,
  `subject_template` varchar(255) NOT NULL,
  `message_template` longtext NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `delay_minutes` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `backlink_tracking`
--

CREATE TABLE `backlink_tracking` (
  `id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `backlink_url` varchar(500) NOT NULL,
  `status` enum('pending','verified','failed','removed') NOT NULL DEFAULT 'pending',
  `last_checked` timestamp NULL DEFAULT NULL,
  `check_count` int(11) DEFAULT 0,
  `failure_count` int(11) DEFAULT 0,
  `first_verified` timestamp NULL DEFAULT NULL,
  `last_verified` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `backlink_tracking`
--

INSERT INTO `backlink_tracking` (`id`, `site_id`, `backlink_url`, `status`, `last_checked`, `check_count`, `failure_count`, `first_verified`, `last_verified`, `notes`, `created_at`, `updated_at`) VALUES
(3, 65, 'https://faucetguard.live/', 'pending', '2025-10-02 04:30:41', 0, 0, NULL, NULL, NULL, '2025-10-02 04:30:41', '2025-10-02 04:30:41');

-- --------------------------------------------------------

--
-- Table structure for table `blocked_ips`
--

CREATE TABLE `blocked_ips` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `blocked_by` int(11) DEFAULT NULL,
  `blocked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_permanent` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `coupon_type` enum('deposit_bonus','points_bonus','credits_bonus','percentage_bonus') NOT NULL DEFAULT 'deposit_bonus',
  `value` decimal(10,4) NOT NULL,
  `minimum_deposit` decimal(10,4) DEFAULT 0.0000,
  `usage_limit` int(11) DEFAULT NULL,
  `usage_count` int(11) DEFAULT 0,
  `user_limit_per_account` int(11) DEFAULT 1,
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `coupon_redemptions`
--

CREATE TABLE `coupon_redemptions` (
  `id` int(11) NOT NULL,
  `coupon_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `redemption_value` decimal(10,4) NOT NULL,
  `deposit_transaction_id` int(11) DEFAULT NULL,
  `security_hash` varchar(64) NOT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `verification_token` varchar(64) DEFAULT NULL,
  `redeemed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `coupon_security_logs`
--

CREATE TABLE `coupon_security_logs` (
  `id` int(11) NOT NULL,
  `coupon_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `risk_level` enum('low','medium','high','critical') DEFAULT 'low',
  `is_suspicious` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `credit_transactions`
--

CREATE TABLE `credit_transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `type` enum('deposit','withdrawal','spent','refund') NOT NULL,
  `description` varchar(255) NOT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `status` enum('pending','completed','failed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deposit_transactions`
--

CREATE TABLE `deposit_transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,4) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `payment_method` enum('faucetpay','bitpay','coupon') NOT NULL,
  `faucetpay_id` varchar(255) DEFAULT NULL,
  `bitpay_invoice_id` varchar(255) DEFAULT NULL,
  `coupon_redemption_id` int(11) DEFAULT NULL,
  `status` enum('pending','completed','failed') DEFAULT 'pending',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `credits_amount` decimal(10,4) DEFAULT 0.0000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_campaigns`
--

CREATE TABLE `email_campaigns` (
  `id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `target_audience` varchar(255) NOT NULL,
  `recipient_count` int(11) NOT NULL,
  `sent_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_queue`
--

CREATE TABLE `email_queue` (
  `id` int(11) NOT NULL,
  `campaign_id` int(11) DEFAULT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `error_message` text DEFAULT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `visitor_logs`
--

CREATE TABLE `visitor_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `country` varchar(64) DEFAULT 'Unknown',
  `page_url` varchar(512) NOT NULL,
  `referrer` varchar(512) DEFAULT NULL,
  `user_agent` varchar(512) DEFAULT NULL,
  `visit_time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_visit_time` (`visit_time`),
  KEY `idx_country` (`country`),
  KEY `idx_referrer` (`referrer`(191)),
  KEY `idx_page_url` (`page_url`(191)),
  KEY `idx_ip_time` (`ip_address`,`visit_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feature_pricing`
--

CREATE TABLE `feature_pricing` (
  `id` int(11) NOT NULL,
  `feature_type` enum('referral_link','skip_backlink','priority_review') NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `feature_pricing`
--

INSERT INTO `feature_pricing` (`id`, `feature_type`, `price`, `description`, `is_active`, `created_at`) VALUES
(1, 'referral_link', 15.00, 'Use your referral link instead of the original URL', 1, '2025-09-09 01:00:27'),
(2, 'skip_backlink', 1.00, 'Skip the backlink requirement for site submission', 1, '2025-09-09 01:00:27'),
(3, 'priority_review', 1.00, 'Get your site reviewed within 24 hours', 1, '2025-09-09 01:00:27');

-- --------------------------------------------------------

--
-- Table structure for table `ip_registrations`
--

CREATE TABLE `ip_registrations` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `levels`
--

CREATE TABLE `levels` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `min_reputation` int(11) NOT NULL,
  `badge_icon` varchar(255) DEFAULT NULL,
  `badge_color` varchar(7) NOT NULL DEFAULT '#3b82f6',
  `difficulty` enum('newcomer','easy','medium','hard','extreme','special') DEFAULT 'easy',
  `description` text DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `levels`
--

INSERT INTO `levels` (`id`, `name`, `min_reputation`, `badge_icon`, `badge_color`, `difficulty`, `description`, `requirements`, `created_at`) VALUES
(1, 'Newcomer', 0, '🆕', '#6b7280', 'newcomer', 'Welcome to the community!', 'Join the platform', '2025-09-09 22:34:21'),
(2, 'Explorer', 25, '🔍', '#10b981', 'easy', 'Starting to explore crypto sites', '25+ reputation points', '2025-09-09 22:34:21'),
(3, 'Reviewer', 75, '📝', '#3b82f6', 'easy', 'Active in writing reviews', '75+ reputation, 5+ reviews', '2025-09-09 22:34:21'),
(4, 'Contributor', 150, '⭐', '#f59e0b', 'easy', 'Regular community contributor', '150+ reputation, 10+ reviews', '2025-09-09 22:34:21'),
(5, 'Trusted Member', 300, '🏅', '#8b5cf6', 'medium', 'Trusted by the community', '300+ reputation, 20+ reviews, 50+ upvotes', '2025-09-09 22:34:21'),
(6, 'Site Hunter', 500, '🎯', '#ef4444', 'medium', 'Expert at finding quality sites', '500+ reputation, 5+ approved submissions', '2025-09-09 22:34:21'),
(7, 'Crypto Expert', 750, '💎', '#06b6d4', 'medium', 'Deep crypto knowledge', '750+ reputation, 30+ reviews, 100+ upvotes', '2025-09-09 22:34:21'),
(8, 'Community Leader', 1200, '👑', '#f59e0b', 'hard', 'Leading the community', '1200+ reputation, 50+ reviews, 200+ upvotes', '2025-09-09 22:34:21'),
(9, 'Master Reviewer', 2000, '🏆', '#10b981', 'hard', 'Master of site reviews', '2000+ reputation, 100+ reviews, 500+ upvotes', '2025-09-09 22:34:21'),
(10, 'Crypto Legend', 3500, '⚡', '#8b5cf6', 'extreme', 'Legendary status achieved', '3500+ reputation, 150+ reviews, 1000+ upvotes', '2025-09-09 22:34:21'),
(11, 'Hall of Fame', 5000, '🌟', '#ef4444', 'extreme', 'Elite contributor status', '5000+ reputation, 200+ reviews, 2000+ upvotes', '2025-09-09 22:34:21'),
(12, 'Scam Hunter', 100, '🛡️', '#ef4444', 'special', 'Protects community from scams', 'Report 5+ confirmed scam sites', '2025-09-09 22:34:21'),
(13, 'Moderator', 999999, '🔰', '#3b82f6', 'special', 'Community moderator', 'Appointed by administration', '2025-09-09 22:34:21'),
(14, 'Administrator', 999999, '👨‍💼', '#ef4444', 'special', 'Site administrator', 'Administrative privileges', '2025-09-09 22:34:21');

-- --------------------------------------------------------

--
-- Table structure for table `newsletter_subscriptions`
--

CREATE TABLE `newsletter_subscriptions` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '{}' CHECK (json_valid(`preferences`)),
  `is_active` tinyint(1) DEFAULT 1,
  `verification_token` varchar(64) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_queue`
--

CREATE TABLE `notification_queue` (
  `id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` longtext NOT NULL,
  `trigger_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`trigger_data`)),
  `scheduled_for` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `attempts` int(11) DEFAULT 0,
  `last_attempt` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `points_transactions`
--

CREATE TABLE `points_transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `points` int(11) NOT NULL,
  `type` enum('earned','spent','redeemed','referral_bonus','admin_adjustment') NOT NULL,
  `description` varchar(255) NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `points_transactions`
--

INSERT INTO `points_transactions` (`id`, `user_id`, `points`, `type`, `description`, `reference_id`, `reference_type`, `created_at`) VALUES
(65, 28, 25, 'earned', 'Site approved by admin', 65, 'submission', '2025-10-02 04:31:50'),
(66, 28, 1, 'earned', 'Voted on site', 63, 'vote', '2025-10-02 04:38:26'),
(67, 28, 1, 'earned', 'Voted on site', 70, 'vote', '2025-10-02 07:03:09'),
(68, 28, 1, 'earned', 'Voted on site', 73, 'vote', '2025-10-02 07:45:23'),
(69, 29, 5, 'earned', 'Review posted', 63, 'review', '2025-10-02 07:59:34'),
(70, 29, 1, 'earned', 'Voted on site', 63, 'vote', '2025-10-02 07:59:38'),
(71, 28, 3, 'earned', 'Reply to review', 6, 'reply', '2025-10-02 08:01:01'),
(72, 28, 3, 'earned', 'Reply to review', 6, 'reply', '2025-10-02 08:14:10'),
(73, 28, 1, 'earned', 'Voted on review', 6, 'vote', '2025-10-02 08:22:20'),
(74, 28, 1, 'earned', 'Voted on review', 6, 'vote', '2025-10-02 09:55:09');

-- --------------------------------------------------------

--
-- Table structure for table `promotion_pricing`
--

CREATE TABLE `promotion_pricing` (
  `id` int(11) NOT NULL,
  `promotion_type` enum('sponsored','boosted') NOT NULL,
  `duration_days` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `redirect_ads`
--

CREATE TABLE `redirect_ads` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `type` enum('image','html') NOT NULL DEFAULT 'image',
  `image_url` varchar(500) DEFAULT NULL,
  `link_url` varchar(500) DEFAULT NULL,
  `html_content` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `redirect_settings`
--

CREATE TABLE `redirect_settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `countdown_seconds` int(11) DEFAULT 10,
  `redirect_message` varchar(500) DEFAULT 'You will be redirected to the site in {seconds} seconds...',
  `show_site_preview` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `redirect_settings`
--

INSERT INTO `redirect_settings` (`id`, `countdown_seconds`, `redirect_message`, `show_site_preview`, `created_at`, `updated_at`) VALUES
(1, 10, 'You will be redirected to the site in {seconds} seconds...', 1, '2025-09-09 01:00:27', '2025-09-09 01:00:27');

-- --------------------------------------------------------

--
-- Table structure for table `referral_analytics`
--

CREATE TABLE `referral_analytics` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `clicks` int(11) DEFAULT 0,
  `signups` int(11) DEFAULT 0,
  `points_earned` int(11) DEFAULT 0,
  `conversion_rate` decimal(5,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `referral_clicks`
--

CREATE TABLE `referral_clicks` (
  `id` int(11) NOT NULL,
  `referral_code` varchar(50) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `clicked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `click_count` int(11) DEFAULT 1,
  `last_clicked` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `referral_contests`
--

CREATE TABLE `referral_contests` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` timestamp NOT NULL,
  `end_date` timestamp NOT NULL,
  `prize_pool` decimal(10,2) DEFAULT 0.00,
  `min_referrals` int(11) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `referral_milestones`
--

CREATE TABLE `referral_milestones` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `milestone_type` enum('referrals','points','tier') NOT NULL,
  `milestone_value` int(11) NOT NULL,
  `reward_points` int(11) DEFAULT 0,
  `reward_credits` decimal(10,2) DEFAULT 0.00,
  `achieved_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_claimed` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `referral_tiers`
--

CREATE TABLE `referral_tiers` (
  `id` int(11) NOT NULL,
  `tier_name` varchar(50) NOT NULL,
  `min_referrals` int(11) NOT NULL,
  `bonus_multiplier` decimal(3,2) DEFAULT 1.00,
  `tier_color` varchar(7) DEFAULT '#3b82f6',
  `tier_icon` varchar(50) DEFAULT 'fas fa-star',
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `referral_tiers`
--

INSERT INTO `referral_tiers` (`id`, `tier_name`, `min_referrals`, `bonus_multiplier`, `tier_color`, `tier_icon`, `is_active`) VALUES
(1, 'Bronze', 0, 1.00, '#cd7f32', 'fas fa-medal', 1),
(2, 'Silver', 5, 1.10, '#c0c0c0', 'fas fa-medal', 1),
(3, 'Gold', 10, 1.20, '#ffd700', 'fas fa-crown', 1),
(4, 'Platinum', 25, 1.30, '#e5e4e2', 'fas fa-gem', 1),
(5, 'Diamond', 50, 1.50, '#b9f2ff', 'fas fa-diamond', 1);

-- --------------------------------------------------------

--
-- Table structure for table `remember_tokens`
--

CREATE TABLE `remember_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(1) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comment` text NOT NULL,
  `proof_url` varchar(500) DEFAULT NULL,
  `proof_image` varchar(255) DEFAULT NULL,
  `is_scam_report` tinyint(1) DEFAULT 0,
  `is_highlighted` tinyint(1) DEFAULT 0,
  `upvotes` int(11) DEFAULT 0,
  `downvotes` int(11) DEFAULT 0,
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `site_id`, `user_id`, `rating`, `comment`, `proof_url`, `proof_image`, `is_scam_report`, `is_highlighted`, `upvotes`, `downvotes`, `is_deleted`, `created_at`, `updated_at`) VALUES
(6, 63, 29, 5, 'The best faucet to earn BTC, always receive my withdrawal on time', '', NULL, 0, 0, 1, 0, 0, '2025-10-02 07:59:34', '2025-10-02 09:55:09');

-- --------------------------------------------------------

--
-- Table structure for table `review_replies`
--

CREATE TABLE `review_replies` (
  `id` int(11) NOT NULL,
  `review_id` int(11) NOT NULL COMMENT 'Foreign key to reviews table',
  `user_id` int(11) NOT NULL COMMENT 'Foreign key to users table - who wrote the reply',
  `parent_reply_id` int(11) DEFAULT NULL COMMENT 'For nested replies - references another reply',
  `content` text NOT NULL COMMENT 'The reply content/message',
  `upvotes` int(11) DEFAULT 0 COMMENT 'Number of upvotes for this reply',
  `downvotes` int(11) DEFAULT 0 COMMENT 'Number of downvotes for this reply',
  `is_deleted` tinyint(1) DEFAULT 0 COMMENT 'Soft delete flag',
  `is_highlighted` tinyint(1) DEFAULT 0 COMMENT 'Admin can highlight important replies',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores user replies to reviews with threading support';

--
-- Dumping data for table `review_replies`
--

INSERT INTO `review_replies` (`id`, `review_id`, `user_id`, `parent_reply_id`, `content`, `upvotes`, `downvotes`, `is_deleted`, `is_highlighted`, `created_at`, `updated_at`) VALUES
(5, 6, 28, NULL, 'Am trying it because of your review', 0, 0, 0, 0, '2025-10-02 08:01:01', '2025-10-02 08:01:01');

-- --------------------------------------------------------

--
-- Stand-in structure for view `review_reply_counts`
-- (See below for the actual view)
--
CREATE TABLE `review_reply_counts` (
`review_id` int(11)
,`reply_count` bigint(21)
,`last_reply_at` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `review_reply_votes`
--

CREATE TABLE `review_reply_votes` (
  `id` int(11) NOT NULL,
  `reply_id` int(11) NOT NULL COMMENT 'Foreign key to review_replies table',
  `user_id` int(11) NOT NULL COMMENT 'Foreign key to users table - who voted',
  `vote_type` enum('upvote','downvote') NOT NULL COMMENT 'Type of vote',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores votes on review replies';

-- --------------------------------------------------------

--
-- Table structure for table `scam_reports_log`
--

CREATE TABLE `scam_reports_log` (
  `id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `old_status` varchar(50) NOT NULL,
  `new_status` varchar(50) NOT NULL,
  `reason` text DEFAULT NULL,
  `changed_by` varchar(100) DEFAULT 'system',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `secure_visit_tokens`
--

CREATE TABLE `secure_visit_tokens` (
  `id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `secure_visit_tokens`
--

INSERT INTO `secure_visit_tokens` (`id`, `site_id`, `token`, `user_id`, `ip_address`, `expires_at`, `used_at`, `created_at`) VALUES
(67, 63, 'cd14ece40c98e9ada0d3754907e618d9f1bae9ba8ce1a9e3ef60ad5e7c85751f', NULL, '102.176.65.85', '2025-10-02 03:01:57', '2025-10-02 02:56:58', '2025-10-02 02:56:57'),
(68, 64, '93bfb86382223cc6c9e78268f91e0600ca537d0aae832fcdc1635b1f23734a92', NULL, '102.176.75.94', '2025-10-02 04:11:57', '2025-10-02 04:06:57', '2025-10-02 04:06:57'),
(69, 64, 'cf7aa8dcc58b57ced44a204874d29b2dcd0155810fa944acdc06923883e6fdfe', NULL, '102.176.75.94', '2025-10-02 04:12:40', '2025-10-02 04:07:40', '2025-10-02 04:07:40'),
(70, 64, '8139f0b90dd4fa0ff30c6e1a8504859b367eb4f82b6b909634976848f92e415f', NULL, '102.176.75.94', '2025-10-02 04:18:51', NULL, '2025-10-02 04:13:51'),
(71, 64, 'c580960e73ac23b2ea9834425154e701232dbdfab064be44a4b2286a7efb8b64', NULL, '157.55.39.13', '2025-10-02 04:22:15', '2025-10-02 04:17:25', '2025-10-02 04:17:15'),
(72, 64, '0796ff70a32d0ec1f60a22d474dc2ef4dbb64b6673c695fe7b46121bce90bbf0', NULL, '102.176.75.94', '2025-10-02 04:22:28', '2025-10-02 04:17:28', '2025-10-02 04:17:28'),
(73, 64, '44d76fd0cb621cc8b3e9bdeae10a4c6a55b5155e218dee8340b1473f8c0a584c', NULL, '102.176.75.94', '2025-10-02 04:28:11', '2025-10-02 04:23:11', '2025-10-02 04:23:11'),
(74, 64, 'aa5994d0cec93984cccf3c052198a15a207b79639e1320c6cd17d1091ed091ed', NULL, '102.176.75.94', '2025-10-02 04:28:30', '2025-10-02 04:23:30', '2025-10-02 04:23:30'),
(75, 65, '12bc85151edc4cbd3c3f5e3e0c15c56fbbb719c9e66dfdee24d8a99cdd7d73db', NULL, '102.176.75.94', '2025-10-02 04:37:21', '2025-10-02 04:32:21', '2025-10-02 04:32:21'),
(76, 63, 'a1a4f8bc15f3b006ac78e95158ad68dbfe5550c2208eeb2e827f041f13cca55f', NULL, '102.176.75.94', '2025-10-02 10:49:36', '2025-10-02 10:44:36', '2025-10-02 10:44:36'),
(77, 66, '1c3dcdd1043031227e1196e1ff842703071d1c32202653c0a0d33ced4dadf6bd', NULL, '102.176.75.94', '2025-10-02 11:42:58', '2025-10-02 11:37:58', '2025-10-02 11:37:58');

-- --------------------------------------------------------

--
-- Table structure for table `security_logs`
--

CREATE TABLE `security_logs` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `risk_level` enum('low','medium','high','critical') DEFAULT 'low',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `security_logs`
--

INSERT INTO `security_logs` (`id`, `ip_address`, `user_id`, `action`, `details`, `risk_level`, `created_at`) VALUES
(86, '102.176.65.85', 28, 'user_registered', '{\"user_id\":\"28\",\"username\":\"CoinHunter\",\"has_referrer\":false}', 'low', '2025-10-02 02:39:53'),
(87, '102.176.65.85', 28, 'login_success', '{\"user_id\":\"28\",\"username\":\"CoinHunter\"}', 'low', '2025-10-02 02:40:17'),
(88, '102.176.75.94', 28, 'login_success', '{\"user_id\":\"28\",\"username\":\"CoinHunter\"}', 'low', '2025-10-02 05:41:56'),
(89, '102.176.75.94', 29, 'user_registered', '{\"user_id\":\"29\",\"username\":\"Hexa\",\"has_referrer\":false}', 'low', '2025-10-02 07:55:39'),
(90, '102.176.75.94', 29, 'login_success', '{\"user_id\":\"29\",\"username\":\"Hexa\"}', 'low', '2025-10-02 07:56:22'),
(91, '102.176.75.94', 28, 'login_success', '{\"user_id\":\"28\",\"username\":\"CoinHunter\"}', 'low', '2025-10-02 19:32:20');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `sid` varchar(255) NOT NULL,
  `session` text NOT NULL,
  `expires` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sites`
--

CREATE TABLE `sites` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `url` varchar(500) NOT NULL,
  `referral_link` varchar(500) DEFAULT NULL,
  `description` text NOT NULL,
  `category` enum('faucet','url_shortener') NOT NULL,
  `supported_coins` text DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `submitted_by` int(11) NOT NULL,
  `is_approved` tinyint(1) DEFAULT 0,
  `quality_score` int(11) DEFAULT 50,
  `submission_notes` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `status` enum('paying','not_paying','scam_reported','scam') DEFAULT 'paying',
  `is_featured` tinyint(1) DEFAULT 0,
  `is_sponsored` tinyint(1) DEFAULT 0,
  `sponsored_until` timestamp NULL DEFAULT NULL,
  `sponsored_last_shown` timestamp NULL DEFAULT NULL,
  `is_boosted` tinyint(1) DEFAULT 0,
  `boosted_until` timestamp NULL DEFAULT NULL,
  `boosted_last_shown` timestamp NULL DEFAULT NULL,
  `promotion_rotation_order` int(11) DEFAULT 0,
  `backlink_url` varchar(500) DEFAULT NULL,
  `backlink_verified` tinyint(1) DEFAULT 0,
  `views` int(11) DEFAULT 0,
  `clicks` int(11) DEFAULT 0,
  `total_upvotes` int(11) DEFAULT 0,
  `total_downvotes` int(11) DEFAULT 0,
  `total_reviews` int(11) DEFAULT 0,
  `average_rating` decimal(3,2) DEFAULT 0.00,
  `scam_reports_count` int(11) DEFAULT 0,
  `total_reviews_for_scam` int(11) DEFAULT 0,
  `admin_scam_decision` tinyint(1) DEFAULT 0,
  `is_dead` tinyint(1) DEFAULT 0,
  `admin_approved_dead` tinyint(1) DEFAULT 0,
  `consecutive_failures` int(11) DEFAULT 0,
  `last_health_check` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sites`
--

INSERT INTO `sites` (`id`, `name`, `url`, `referral_link`, `description`, `category`, `supported_coins`, `logo`, `submitted_by`, `is_approved`, `quality_score`, `submission_notes`, `approved_by`, `status`, `is_featured`, `is_sponsored`, `sponsored_until`, `sponsored_last_shown`, `is_boosted`, `boosted_until`, `boosted_last_shown`, `promotion_rotation_order`, `backlink_url`, `backlink_verified`, `views`, `clicks`, `total_upvotes`, `total_downvotes`, `total_reviews`, `average_rating`, `scam_reports_count`, `total_reviews_for_scam`, `admin_scam_decision`, `is_dead`, `admin_approved_dead`, `consecutive_failures`, `last_health_check`, `created_at`, `updated_at`) VALUES
(63, 'adBTC', 'https://r.adbtc.top/1121925', '', 'Earn money for watching websites', 'faucet', 'BTC, USDT', '/assets/images/logos/adbtc.ico', 28, 1, 50, NULL, NULL, 'paying', 0, 1, '2025-11-01 04:51:42', '2025-10-02 20:39:16', 0, NULL, NULL, 0, '', 0, 48, 2, 2, 0, 0, 0.00, 0, 0, 0, 0, 0, 0, NULL, '2025-10-02 02:50:43', '2025-10-02 20:39:16'),
(64, 'Fc.lc', 'https://fc.lc/ref/100047732308306534495', '', 'With Fc.lc you can shorten your URls and earn money while you’re at it. Create and share your short URLs with our generator and start earning money from your home!', 'url_shortener', 'BTC, USDT', 'assets/images/logos/logo_1759376345_68ddf3d9894b2.png', 28, 1, 50, NULL, NULL, 'paying', 0, 0, NULL, NULL, 0, NULL, NULL, 0, '', 0, 12, 6, 0, 0, 0, 0.00, 0, 0, 0, 0, 0, 0, NULL, '2025-10-02 03:39:05', '2025-10-02 16:30:08'),
(65, 'Exe io', 'https://exe.io/ref/Kingzabbu', 'https://exe.io/ref/Kingzabbu', 'exe.io makes you shorten and track your links. In addition, it rewards you for every single link at the same time.', 'url_shortener', 'BTC, USDT', 'assets/images/logos/logo_1759379441_68ddfff11d0b2.ico', 28, 1, 50, NULL, 28, 'paying', 0, 0, NULL, NULL, 0, NULL, NULL, 0, 'https://faucetguard.live/', 0, 7, 1, 0, 0, 0, 0.00, 0, 0, 0, 0, 0, 0, NULL, '2025-10-02 04:30:41', '2025-10-02 07:55:03'),
(66, 'Vie Faucet', 'https://viefaucet.com?r=62d5d5aef6ee210e76650e53', '', 'Vie Faucet is a website where you can claim free cryptocurrencies like Bitcoin, Litecoin, PEPE, Dogecoin, Shiba, Solana with faucet, shortlinks, offers, ptc.', 'faucet', 'BTC, USDT, LTC, BNB, LTC, ETH', 'assets/images/logos/logo_1759380680_68de04c8ad19d.ico', 28, 1, 50, NULL, NULL, 'paying', 0, 1, '2025-11-01 04:51:35', '2025-10-02 20:39:16', 0, NULL, NULL, 0, '', 0, 8, 1, 0, 0, 0, 0.00, 0, 0, 0, 0, 0, 0, NULL, '2025-10-02 04:51:20', '2025-10-02 20:39:16'),
(67, 'Coinpayu', 'https://www.coinpayu.com/?r=WinUrge', '', 'Earn rewards effortlessly', 'faucet', 'BTC, LTC, BNB, DOGE, SOL', 'assets/images/logos/logo_1759383926_68de11763c1d9.ico', 28, 1, 50, NULL, NULL, 'paying', 0, 0, NULL, NULL, 0, NULL, NULL, 0, '', 0, 0, 0, 0, 0, 0, 0.00, 0, 0, 0, 0, 0, 0, NULL, '2025-10-02 05:45:26', '2025-10-02 05:45:26'),
(68, 'Fire Faucet', 'https://firefaucet.win/ref/247425', '', 'The Best Auto Faucet, With 7+ years of experience & over a million users, Fire Faucet is your trusted choice for free crypto & gift cards. Auto-claim, complete tasks, and get paid!', 'faucet', 'BTC, LTC, BNB, DOGE, TRL, ZEC, DGB, NANA, ZMR, ADA, ETH', 'assets/images/logos/logo_1759385448_68de176874326.png', 28, 1, 50, NULL, NULL, 'paying', 0, 0, NULL, NULL, 0, NULL, NULL, 0, '', 0, 0, 0, 0, 0, 0, 0.00, 0, 0, 0, 0, 0, 0, NULL, '2025-10-02 06:10:48', '2025-10-02 06:10:48'),
(69, 'BTCadSpace', 'https://btcadspace.com/ref/Kingzabbu', '', 'Earn crypto coins with multiple ways. Cheap website promotion with real crypto users. Create your ad and target crypto audience.', 'faucet', 'BTC', 'assets/images/logos/logo_1759386727_68de1c67dc0f8.ico', 28, 1, 50, NULL, NULL, 'paying', 0, 0, NULL, NULL, 0, NULL, NULL, 0, '', 0, 0, 0, 0, 0, 0, 0.00, 0, 0, 0, 0, 0, 0, NULL, '2025-10-02 06:32:07', '2025-10-02 06:32:34'),
(70, 'Dutch Corp', 'https://autofaucet.dutchycorp.space/?r=Kingzabbu', '', 'Start earning cryptocurrency on the best autofaucet out there by doing tasks, offers and surveys, staking, faucet, shortlinks, PTC, mining & more.', 'faucet', 'BTC, LTC, BNB, DOGE, TRL, ZEC, DGB, NANA, ZMR, ADA, ETH', 'assets/images/logos/logo_1759387659_68de200b53b99.png', 28, 1, 50, NULL, NULL, 'paying', 0, 0, NULL, NULL, 1, '2025-11-01 07:47:11', '2025-10-02 20:39:16', 0, '', 0, 6, 0, 1, 0, 0, 0.00, 0, 0, 0, 0, 0, 0, NULL, '2025-10-02 06:43:50', '2025-10-02 20:39:16'),
(71, 'ClaimClicks', 'https://claimclicks.com/btc/?r=godwin853', '', 'Instant Earn free crypto rewards by completing a few simple steps and receive instant payouts on FaucetPay Microwallet Platform', 'faucet', 'BTC, LTC, BNB, DOGE, XLM, ZEC, DGB, ADA, ETH, XMR', 'assets/images/logos/logo_1759388201_68de2229ebe92.ico', 28, 1, 50, NULL, NULL, 'paying', 0, 0, NULL, NULL, 0, NULL, NULL, 0, '', 0, 0, 0, 0, 0, 0, 0.00, 0, 0, 0, 0, 0, 0, NULL, '2025-10-02 06:56:41', '2025-10-02 06:56:41'),
(72, 'AutoFaucet', 'https://autofaucet.org/r/Kingzabbu', '', 'One of the oldest faucet websites for earning money online from anywhere. Offers instant withdrawals including Bitcoin, Ethereum, Dogecoin, and more.', 'faucet', 'BTC, LTC, BNB, DOGE, XLM, ZEC, DGB, ADA, ETH, XMR', 'assets/images/logos/logo_1759388706_68de242291d23.png', 28, 1, 50, NULL, NULL, 'paying', 0, 0, NULL, NULL, 0, NULL, NULL, 0, '', 0, 0, 0, 0, 0, 0, 0.00, 0, 0, 0, 0, 0, 0, NULL, '2025-10-02 07:05:06', '2025-10-02 07:05:06'),
(73, 'Cointiply', 'https://cointiply.com/r/DpKyA', '', 'Earn free Bitcoin & crypto from the best Bitcoin faucet & crypto rewards platform. Complete offers & surveys, watch videos & play games to earn free Bitcoin.', 'faucet', 'BTC, LTC, DOGE, DASH', 'assets/images/logos/logo_1759390292_68de2a546a142.png', 28, 1, 50, NULL, NULL, 'paying', 0, 0, NULL, NULL, 0, NULL, NULL, 0, '', 0, 5, 0, 1, 0, 0, 0.00, 0, 0, 0, 0, 0, 0, NULL, '2025-10-02 07:31:32', '2025-10-02 07:55:11'),
(74, 'FreeTrump', 'https://freetrump.in?ref=ZUqYTbTYOC', '', 'FreeTrump.in is free TRUMP Faucet. Earn free Official TRUMP token every hour and multiply your TRUMP up to 4,850x by playing provably fair HI-LO game.', 'faucet', 'TRUMP', 'assets/images/logos/logo_1759390789_68de2c45cd28b.ico', 28, 1, 50, NULL, NULL, 'paying', 0, 0, NULL, NULL, 0, NULL, NULL, 0, '', 0, 0, 0, 0, 0, 0, 0.00, 0, 0, 0, 0, 0, 0, NULL, '2025-10-02 07:39:49', '2025-10-02 07:39:49'),
(75, 'FREESHIB', 'https://freeshib.in?ref=_M3LDEhRBA', '', 'FreeShib.in is free Shiba Faucet. Earn free Shiba Inu token every hour and multiply your Shiba token up to 4,750x by playing provably fair HI-LO game.', 'faucet', 'SHIB', 'assets/images/logos/logo_1759391077_68de2d65a5940.ico', 28, 1, 50, NULL, NULL, 'paying', 0, 0, NULL, NULL, 0, NULL, NULL, 0, '', 0, 0, 0, 0, 0, 0, 0.00, 0, 0, 0, 0, 0, 0, NULL, '2025-10-02 07:44:37', '2025-10-02 07:44:37');

-- --------------------------------------------------------

--
-- Table structure for table `site_ads`
--

CREATE TABLE `site_ads` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `type` enum('banner','sidebar','popup','inline') NOT NULL DEFAULT 'banner',
  `position` varchar(50) NOT NULL,
  `content` longtext NOT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `link_url` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `start_date` timestamp NULL DEFAULT NULL,
  `end_date` timestamp NULL DEFAULT NULL,
  `click_count` int(11) DEFAULT 0,
  `impression_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `site_categories`
--

CREATE TABLE `site_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(10) DEFAULT NULL,
  `color` varchar(7) DEFAULT '#3b82f6',
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `site_categories`
--

INSERT INTO `site_categories` (`id`, `name`, `slug`, `description`, `icon`, `color`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Crypto Faucets', 'faucet', 'Free cryptocurrency earning sites that reward users with small amounts of crypto', '🚰', '#10b981', 1, 1, '2025-09-11 09:48:47', '2025-09-11 09:48:47'),
(2, 'URL Shorteners', 'url_shortener', 'Earn cryptocurrency by shortening and sharing links', '🔗', '#3b82f6', 2, 1, '2025-09-11 09:48:47', '2025-09-11 09:48:47');

-- --------------------------------------------------------

--
-- Table structure for table `site_clicks`
--

CREATE TABLE `site_clicks` (
  `id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `clicked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `site_clicks`
--

INSERT INTO `site_clicks` (`id`, `site_id`, `ip_address`, `user_agent`, `clicked_at`) VALUES
(63, 63, '102.176.65.85', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-10-02 02:56:58'),
(64, 64, '102.176.75.94', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-10-02 04:06:57'),
(65, 64, '102.176.75.94', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-10-02 04:07:40'),
(66, 64, '157.55.39.13', 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm) Chrome/116.0.1938.76 Safari/537.36', '2025-10-02 04:17:25'),
(67, 64, '102.176.75.94', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-10-02 04:17:28'),
(68, 64, '102.176.75.94', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-10-02 04:23:11'),
(69, 64, '102.176.75.94', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-10-02 04:23:30'),
(70, 65, '102.176.75.94', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-10-02 04:32:21'),
(71, 63, '102.176.75.94', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-10-02 10:44:36'),
(72, 66, '102.176.75.94', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-10-02 11:37:58');

-- --------------------------------------------------------

--
-- Table structure for table `site_faqs`
--

CREATE TABLE `site_faqs` (
  `id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `question` varchar(500) NOT NULL,
  `answer` text NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `site_features`
--

CREATE TABLE `site_features` (
  `id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `feature_type` enum('referral_link','skip_backlink','priority_review') NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `site_features`
--

INSERT INTO `site_features` (`id`, `site_id`, `feature_type`, `is_active`, `expires_at`, `created_at`) VALUES
(6, 65, 'referral_link', 1, NULL, '2025-10-02 04:30:41');

-- --------------------------------------------------------

--
-- Table structure for table `site_health_checks`
--

CREATE TABLE `site_health_checks` (
  `id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `url_checked` varchar(500) NOT NULL,
  `status_code` int(11) DEFAULT NULL,
  `response_time` decimal(8,3) DEFAULT NULL,
  `is_accessible` tinyint(1) DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `consecutive_failures` int(11) DEFAULT 0,
  `first_failure_at` timestamp NULL DEFAULT NULL,
  `admin_approved_dead` tinyint(1) DEFAULT 0,
  `admin_notes` text DEFAULT NULL,
  `last_checked` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `site_promotions`
--

CREATE TABLE `site_promotions` (
  `id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `promotion_type` enum('sponsored','boosted') NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `duration_days` int(11) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `payment_status` enum('pending','completed','failed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `site_upload_settings`
--

CREATE TABLE `site_upload_settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `max_file_size` int(11) DEFAULT 2097152,
  `allowed_types` text DEFAULT 'image/jpeg,image/png,image/gif',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `site_upload_settings`
--

INSERT INTO `site_upload_settings` (`id`, `max_file_size`, `allowed_types`, `created_at`, `updated_at`) VALUES
(1, 2097152, 'image/jpeg,image/png,image/gif', '2025-09-11 08:07:38', '2025-09-11 08:07:38');

-- --------------------------------------------------------

--
-- Table structure for table `support_replies`
--

CREATE TABLE `support_replies` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `sender_type` enum('admin','user','system') DEFAULT 'admin',
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `status` enum('open','replied','closed') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `temp_email_domains`
--

CREATE TABLE `temp_email_domains` (
  `id` int(11) NOT NULL,
  `domain` varchar(255) NOT NULL,
  `is_blocked` tinyint(1) DEFAULT 1,
  `detection_method` enum('manual','api','pattern') DEFAULT 'manual',
  `added_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `temp_email_domains`
--

INSERT INTO `temp_email_domains` (`id`, `domain`, `is_blocked`, `detection_method`, `added_by`, `created_at`) VALUES
(1, '10minutemail.com', 1, 'manual', NULL, '2025-09-09 01:00:27'),
(2, 'guerrillamail.com', 1, 'manual', NULL, '2025-09-09 01:00:27'),
(3, 'mailinator.com', 1, 'manual', NULL, '2025-09-09 01:00:27'),
(4, 'tempmail.org', 1, 'manual', NULL, '2025-09-09 01:00:27'),
(5, 'temp-mail.org', 1, 'manual', NULL, '2025-09-09 01:00:27'),
(6, 'throwaway.email', 1, 'manual', NULL, '2025-09-09 01:00:27'),
(7, 'maildrop.cc', 1, 'manual', NULL, '2025-09-09 01:00:27'),
(8, 'yopmail.com', 1, 'manual', NULL, '2025-09-09 01:00:27'),
(9, 'sharklasers.com', 1, 'manual', NULL, '2025-09-09 01:00:27'),
(10, 'guerrillamailblock.com', 1, 'manual', NULL, '2025-09-09 01:00:27'),
(11, 'pokemail.net', 1, 'manual', NULL, '2025-09-09 01:00:27'),
(12, 'spam4.me', 1, 'manual', NULL, '2025-09-09 01:00:27'),
(13, 'tempail.com', 1, 'manual', NULL, '2025-09-09 01:00:27'),
(14, 'tempemail.com', 1, 'manual', NULL, '2025-09-09 01:00:27'),
(15, 'tempinbox.com', 1, 'manual', NULL, '2025-09-09 01:00:27'),
(16, 'emailondeck.com', 1, 'manual', NULL, '2025-09-09 01:00:27'),
(17, 'fakeinbox.com', 1, 'manual', NULL, '2025-09-09 01:00:27'),
(18, 'spamgourmet.com', 1, 'manual', NULL, '2025-09-09 01:00:27'),
(19, 'mailnesia.com', 1, 'manual', NULL, '2025-09-09 01:00:27'),
(20, 'trashmail.com', 1, 'manual', NULL, '2025-09-09 01:00:27'),
(21, 'dispostable.com', 1, 'manual', NULL, '2025-09-09 01:00:27'),
(22, 'tempmailo.com', 1, 'manual', NULL, '2025-09-09 01:00:27'),
(23, 'mohmal.com', 1, 'manual', NULL, '2025-09-09 01:00:27'),
(24, 'emailfake.com', 1, 'manual', NULL, '2025-09-09 01:00:27'),
(25, 'mytemp.email', 1, 'manual', NULL, '2025-09-09 01:00:27'),
(26, 'temp-mail.io', 1, 'manual', NULL, '2025-09-09 01:00:27'),
(27, 'disposablemail.com', 1, 'manual', NULL, '2025-09-09 01:00:27'),
(28, 'getnada.com', 1, 'manual', NULL, '2025-09-09 01:00:27');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT 'assets/images/default-avatar.png',
  `reputation_points` int(11) DEFAULT 0,
  `level_id` int(11) DEFAULT NULL,
  `active_badge_id` int(11) DEFAULT NULL,
  `is_admin` tinyint(1) DEFAULT 0,
  `is_moderator` tinyint(1) DEFAULT 0,
  `is_banned` tinyint(1) DEFAULT 0,
  `ban_reason` varchar(255) DEFAULT NULL,
  `email_notifications` tinyint(1) DEFAULT 1,
  `last_active` timestamp NULL DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL,
  `total_reviews` int(11) DEFAULT 0,
  `total_upvotes` int(11) DEFAULT 0,
  `total_submissions` int(11) DEFAULT 0,
  `credits` decimal(10,2) DEFAULT 0.00,
  `referred_by` int(11) DEFAULT NULL,
  `referral_code` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `avatar`, `reputation_points`, `level_id`, `active_badge_id`, `is_admin`, `is_moderator`, `is_banned`, `ban_reason`, `email_notifications`, `last_active`, `last_login`, `last_ip`, `total_reviews`, `total_upvotes`, `total_submissions`, `credits`, `referred_by`, `referral_code`, `created_at`, `updated_at`) VALUES
(28, 'CoinHunter', 'kingzabbu@gmail.com', '$2y$10$jdo1q7NSnsrvLzUjKhzD6.rX9whVy0Xj3qXeq4A9xui6zoFmFNf5m', 'assets/images/default-avatar.png', 802, NULL, 5, 1, 0, 0, NULL, 1, '2025-10-02 20:39:09', '2025-10-02 19:32:20', '102.176.75.94', 0, 0, 0, 485.00, NULL, 'coinhunter', '2025-10-02 02:39:53', '2025-10-02 20:39:09'),
(29, 'Hexa', 'k.ingzabbu@gmail.com', '$2y$10$P695jDMxTSznwDGQ8yc3mOvX3Wq9oiknWcD6Y7X1uVRePTFrK9olW', 'assets/images/default-avatar.png', 12, NULL, NULL, 0, 0, 0, NULL, 1, '2025-10-02 07:56:22', '2025-10-02 07:56:22', '102.176.75.94', 0, 0, 0, 0.00, NULL, 'hexa', '2025-10-02 07:55:39', '2025-10-02 07:59:38');

-- --------------------------------------------------------

--
-- Table structure for table `user_actions`
--

CREATE TABLE `user_actions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_advertisements`
--

CREATE TABLE `user_advertisements` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `ad_type` enum('banner','text') NOT NULL DEFAULT 'banner',
  `visibility_level` enum('standard','premium') NOT NULL DEFAULT 'standard',
  `duration_days` int(11) NOT NULL,
  `status` enum('pending','active','paused','expired','rejected') NOT NULL DEFAULT 'pending',
  `banner_image` varchar(500) DEFAULT NULL,
  `banner_alt_text` varchar(255) DEFAULT NULL,
  `text_title` varchar(100) DEFAULT NULL,
  `text_description` varchar(255) DEFAULT NULL,
  `target_url` varchar(500) NOT NULL,
  `target_space_id` varchar(100) DEFAULT NULL COMMENT 'Specific ad space if targeted',
  `is_cross_pool` tinyint(1) DEFAULT 0 COMMENT 'If 1, rotates across multiple spaces',
  `click_count` int(11) DEFAULT 0,
  `impression_count` int(11) DEFAULT 0,
  `cost_paid` decimal(10,4) NOT NULL,
  `premium_cost` decimal(10,4) DEFAULT 0.0000,
  `start_date` timestamp NULL DEFAULT NULL,
  `end_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_badges`
--

CREATE TABLE `user_badges` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `badge_id` int(11) NOT NULL,
  `earned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_badges`
--

INSERT INTO `user_badges` (`id`, `user_id`, `badge_id`, `earned_at`) VALUES
(31, 28, 1, '2025-10-02 02:40:37'),
(32, 28, 2, '2025-10-02 08:53:34'),
(33, 28, 3, '2025-10-02 08:55:17'),
(34, 28, 12, '2025-10-02 08:55:17'),
(35, 28, 4, '2025-10-02 08:55:17'),
(36, 28, 5, '2025-10-02 08:55:17'),
(37, 28, 6, '2025-10-02 08:55:17');

-- --------------------------------------------------------

--
-- Table structure for table `user_referrals`
--

CREATE TABLE `user_referrals` (
  `id` int(11) NOT NULL,
  `referrer_id` int(11) NOT NULL,
  `referred_id` int(11) NOT NULL,
  `referral_code` varchar(50) NOT NULL,
  `points_earned` int(11) DEFAULT 0,
  `activities` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_wallets`
--

CREATE TABLE `user_wallets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `deposit_balance` decimal(10,4) DEFAULT 0.0000,
  `points_balance` int(11) DEFAULT 0,
  `total_deposited` decimal(10,4) DEFAULT 0.0000,
  `total_earned_points` int(11) DEFAULT 0,
  `total_redeemed_points` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_wallets`
--

INSERT INTO `user_wallets` (`id`, `user_id`, `deposit_balance`, `points_balance`, `total_deposited`, `total_earned_points`, `total_redeemed_points`, `created_at`, `updated_at`) VALUES
(34, 28, 0.0000, 36, 0.0000, 36, 0, '2025-10-02 02:39:53', '2025-10-02 09:55:09'),
(35, 29, 0.0000, 6, 0.0000, 6, 0, '2025-10-02 07:55:39', '2025-10-02 07:59:38');

-- --------------------------------------------------------

--
-- Table structure for table `votes`
--

CREATE TABLE `votes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `site_id` int(11) DEFAULT NULL,
  `review_id` int(11) DEFAULT NULL,
  `vote_type` enum('upvote','downvote') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `votes`
--

INSERT INTO `votes` (`id`, `user_id`, `site_id`, `review_id`, `vote_type`, `created_at`) VALUES
(66, 28, 63, NULL, 'upvote', '2025-10-02 04:38:26'),
(67, 28, 70, NULL, 'upvote', '2025-10-02 07:03:09'),
(68, 28, 73, NULL, 'upvote', '2025-10-02 07:45:23'),
(69, 29, 63, NULL, 'upvote', '2025-10-02 07:59:38'),
(71, 28, NULL, 6, 'upvote', '2025-10-02 09:55:09');

-- --------------------------------------------------------

--
-- Table structure for table `wallet_settings`
--

CREATE TABLE `wallet_settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `min_deposit` decimal(10,4) DEFAULT 0.0001,
  `min_withdrawal` decimal(10,4) DEFAULT 0.0010,
  `min_points_withdrawal` int(11) DEFAULT 1000,
  `min_faucetpay_points_withdrawal` int(11) DEFAULT 500,
  `points_to_usd_rate` decimal(10,6) DEFAULT 0.000100,
  `referral_percentage` decimal(5,2) DEFAULT 10.00,
  `withdrawal_fee_percentage` decimal(5,2) DEFAULT 2.00,
  `faucetpay_fee_percentage` decimal(5,2) DEFAULT 1.00,
  `faucetpay_merchant_id` varchar(255) DEFAULT NULL,
  `faucetpay_api_key` varchar(255) DEFAULT NULL,
  `bitpay_api_token` varchar(255) DEFAULT NULL,
  `bitpay_environment` enum('test','prod') DEFAULT 'test',
  `bitpay_webhook_secret` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wallet_settings`
--

INSERT INTO `wallet_settings` (`id`, `sort_order`, `min_deposit`, `min_withdrawal`, `min_points_withdrawal`, `min_faucetpay_points_withdrawal`, `points_to_usd_rate`, `referral_percentage`, `withdrawal_fee_percentage`, `faucetpay_fee_percentage`, `faucetpay_merchant_id`, `faucetpay_api_key`, `bitpay_api_token`, `bitpay_environment`, `bitpay_webhook_secret`, `created_at`, `updated_at`) VALUES
(1, 0, 1.0000, 1.0000, 1000, 500, 0.000100, 10.00, 2.00, 1.00, 'godwin853', '1052b4763b5830b1a082942e39f673f4c0e64c9b12e3c155f5ea46e7fad0baa9', '', NULL, '', '2025-09-09 01:00:27', '2025-09-11 22:17:16');

-- --------------------------------------------------------

--
-- Table structure for table `withdrawal_currencies`
--

CREATE TABLE `withdrawal_currencies` (
  `id` int(11) NOT NULL,
  `currency_name` varchar(100) NOT NULL,
  `currency_code` varchar(10) NOT NULL,
  `withdrawal_method` enum('faucetpay','direct_wallet','both') NOT NULL DEFAULT 'both',
  `min_amount` decimal(10,4) DEFAULT 0.0001,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `fee_percentage` decimal(5,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `withdrawal_currencies`
--

INSERT INTO `withdrawal_currencies` (`id`, `currency_name`, `currency_code`, `withdrawal_method`, `min_amount`, `is_active`, `created_at`, `fee_percentage`) VALUES
(1, 'Bitcoin', 'BTC', 'both', 0.0001, 1, '2025-09-09 01:00:27', 0.00),
(2, 'Ethereum', 'ETH', 'both', 0.0010, 1, '2025-09-09 01:00:27', 0.00),
(3, 'Litecoin', 'LTC', 'both', 0.0100, 1, '2025-09-09 01:00:27', 0.00),
(4, 'Dogecoin', 'DOGE', 'faucetpay', 1.0000, 1, '2025-09-09 01:00:27', 0.00),
(5, 'Bitcoin Cash', 'BCH', 'both', 0.0010, 1, '2025-09-09 01:00:27', 0.00),
(6, 'Tether USDT', 'USDT', 'faucetpay', 1.0000, 1, '2025-09-09 01:00:27', 0.00),
(7, 'Dash', 'DASH', 'faucetpay', 0.0100, 1, '2025-09-09 01:00:27', 0.00),
(8, 'DigiByte', 'DGB', 'faucetpay', 10.0000, 1, '2025-09-09 01:00:27', 0.00),
(9, 'Tron', 'TRX', 'faucetpay', 10.0000, 1, '2025-09-09 01:00:27', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `withdrawal_requests`
--

CREATE TABLE `withdrawal_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,4) NOT NULL,
  `points_redeemed` int(11) NOT NULL,
  `withdrawal_method` enum('faucetpay','direct_wallet') NOT NULL,
  `wallet_address` varchar(255) DEFAULT NULL,
  `currency` varchar(10) NOT NULL,
  `faucetpay_email` varchar(255) DEFAULT NULL,
  `withdrawal_fee` decimal(10,4) DEFAULT 0.0000,
  `net_amount` decimal(10,4) NOT NULL,
  `status` enum('pending','completed','rejected') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_action_logs`
--
ALTER TABLE `admin_action_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_logs_admin_id` (`admin_id`),
  ADD KEY `idx_admin_logs_action` (`action`),
  ADD KEY `idx_admin_logs_target` (`target_type`,`target_id`),
  ADD KEY `idx_admin_logs_created` (`created_at`);

--
-- Indexes for table `ad_clicks`
--
ALTER TABLE `ad_clicks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ad_id` (`ad_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `ad_impressions`
--
ALTER TABLE `ad_impressions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ad_id` (`ad_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `ad_pricing`
--
ALTER TABLE `ad_pricing`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_pricing` (`ad_type`,`duration_days`);

--
-- Indexes for table `ad_settings`
--
ALTER TABLE `ad_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `ad_spaces`
--
ALTER TABLE `ad_spaces`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_space_id` (`space_id`),
  ADD KEY `idx_page_location` (`page_location`),
  ADD KEY `idx_is_enabled` (`is_enabled`);

--
-- Indexes for table `ad_space_assignments`
--
ALTER TABLE `ad_space_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ad_id` (`ad_id`),
  ADD KEY `idx_space_id` (`space_id`),
  ADD KEY `idx_is_cross_pool` (`is_cross_pool`);

--
-- Indexes for table `ad_transactions`
--
ALTER TABLE `ad_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ad_id` (`ad_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `automated_notifications`
--
ALTER TABLE `automated_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `trigger_event` (`trigger_event`),
  ADD KEY `is_active` (`is_active`);

--
-- Indexes for table `backlink_tracking`
--
ALTER TABLE `backlink_tracking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_site_id` (`site_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_last_checked` (`last_checked`),
  ADD KEY `idx_backlink_status_checked` (`status`,`last_checked`),
  ADD KEY `idx_backlink_site_status` (`site_id`,`status`);

--
-- Indexes for table `blocked_ips`
--
ALTER TABLE `blocked_ips`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ip_address` (`ip_address`),
  ADD KEY `idx_expires` (`expires_at`),
  ADD KEY `idx_blocked_by` (`blocked_by`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_expires` (`expires_at`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `coupon_redemptions`
--
ALTER TABLE `coupon_redemptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_coupon` (`coupon_id`,`user_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_coupon` (`coupon_id`),
  ADD KEY `idx_ip` (`ip_address`),
  ADD KEY `idx_verified` (`is_verified`),
  ADD KEY `fk_coupon_redemptions_transaction` (`deposit_transaction_id`);

--
-- Indexes for table `coupon_security_logs`
--
ALTER TABLE `coupon_security_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_risk` (`risk_level`),
  ADD KEY `idx_suspicious` (`is_suspicious`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_coupon` (`coupon_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `credit_transactions`
--
ALTER TABLE `credit_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `deposit_transactions`
--
ALTER TABLE `deposit_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_method` (`payment_method`);

--
-- Indexes for table `email_campaigns`
--
ALTER TABLE `email_campaigns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sent_by` (`sent_by`);

--
-- Indexes for table `email_queue`
--
ALTER TABLE `email_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_campaign` (`campaign_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `feature_pricing`
--
ALTER TABLE `feature_pricing`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `feature_type` (`feature_type`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `ip_registrations`
--
ALTER TABLE `ip_registrations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip` (`ip_address`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `levels`
--
ALTER TABLE `levels`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reputation` (`min_reputation`);

--
-- Indexes for table `newsletter_subscriptions`
--
ALTER TABLE `newsletter_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_read` (`is_read`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `notification_queue`
--
ALTER TABLE `notification_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notification_id` (`notification_id`),
  ADD KEY `status` (`status`),
  ADD KEY `scheduled_for` (`scheduled_for`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_expires` (`expires_at`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `points_transactions`
--
ALTER TABLE `points_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `promotion_pricing`
--
ALTER TABLE `promotion_pricing`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_promotion_duration` (`promotion_type`,`duration_days`),
  ADD KEY `idx_type` (`promotion_type`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `redirect_ads`
--
ALTER TABLE `redirect_ads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_sort` (`sort_order`);

--
-- Indexes for table `redirect_settings`
--
ALTER TABLE `redirect_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `referral_analytics`
--
ALTER TABLE `referral_analytics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_date` (`user_id`,`date`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `date` (`date`);

--
-- Indexes for table `referral_clicks`
--
ALTER TABLE `referral_clicks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_click` (`referral_code`,`ip_address`),
  ADD KEY `referral_code` (`referral_code`),
  ADD KEY `clicked_at` (`clicked_at`);

--
-- Indexes for table `referral_contests`
--
ALTER TABLE `referral_contests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `is_active` (`is_active`),
  ADD KEY `date_range` (`start_date`,`end_date`);

--
-- Indexes for table `referral_milestones`
--
ALTER TABLE `referral_milestones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `milestone_type` (`milestone_type`),
  ADD KEY `is_claimed` (`is_claimed`);

--
-- Indexes for table `referral_tiers`
--
ALTER TABLE `referral_tiers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `min_referrals` (`min_referrals`);

--
-- Indexes for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_site` (`site_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_rating` (`rating`),
  ADD KEY `idx_scam_report` (`is_scam_report`),
  ADD KEY `idx_highlighted` (`is_highlighted`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_reviews_site_id` (`site_id`),
  ADD KEY `idx_reviews_user_id` (`user_id`),
  ADD KEY `idx_reviews_created` (`created_at`),
  ADD KEY `idx_reviews_rating` (`rating`),
  ADD KEY `idx_reviews_active` (`is_deleted`,`site_id`),
  ADD KEY `idx_reviews_performance` (`site_id`,`is_deleted`,`created_at`);

--
-- Indexes for table `review_replies`
--
ALTER TABLE `review_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_review_replies_review_id` (`review_id`),
  ADD KEY `idx_review_replies_user_id` (`user_id`),
  ADD KEY `idx_review_replies_parent` (`parent_reply_id`),
  ADD KEY `idx_review_replies_created` (`created_at`),
  ADD KEY `idx_review_replies_active` (`is_deleted`,`review_id`);

--
-- Indexes for table `review_reply_votes`
--
ALTER TABLE `review_reply_votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_reply_vote` (`reply_id`,`user_id`),
  ADD KEY `idx_reply_votes_reply_id` (`reply_id`),
  ADD KEY `idx_reply_votes_user_id` (`user_id`),
  ADD KEY `idx_reply_votes_type` (`vote_type`);

--
-- Indexes for table `scam_reports_log`
--
ALTER TABLE `scam_reports_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_site` (`site_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `secure_visit_tokens`
--
ALTER TABLE `secure_visit_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_expires` (`expires_at`),
  ADD KEY `idx_site` (`site_id`),
  ADD KEY `fk_secure_visit_tokens_user` (`user_id`);

--
-- Indexes for table `security_logs`
--
ALTER TABLE `security_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip` (`ip_address`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_risk` (`risk_level`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`sid`);

--
-- Indexes for table `sites`
--
ALTER TABLE `sites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `url` (`url`),
  ADD KEY `idx_approved` (`is_approved`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_featured` (`is_featured`),
  ADD KEY `idx_sponsored` (`is_sponsored`),
  ADD KEY `idx_boosted` (`is_boosted`),
  ADD KEY `idx_upvotes` (`total_upvotes`),
  ADD KEY `idx_rating` (`average_rating`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `fk_sites_submitted_by` (`submitted_by`),
  ADD KEY `fk_sites_approved_by` (`approved_by`),
  ADD KEY `idx_sites_approved` (`is_approved`,`is_dead`,`admin_approved_dead`),
  ADD KEY `idx_sites_status` (`status`),
  ADD KEY `idx_sites_category` (`category`),
  ADD KEY `idx_sites_promotion` (`is_sponsored`,`sponsored_until`),
  ADD KEY `idx_sites_boost` (`is_boosted`,`boosted_until`),
  ADD KEY `idx_sites_health` (`is_dead`,`admin_approved_dead`),
  ADD KEY `idx_sites_last_check` (`last_health_check`),
  ADD KEY `idx_promotion_rotation` (`promotion_rotation_order`),
  ADD KEY `idx_sponsored_active` (`is_sponsored`,`sponsored_until`),
  ADD KEY `idx_boosted_active` (`is_boosted`,`boosted_until`),
  ADD KEY `idx_community_ranking` (`is_approved`,`is_dead`,`admin_approved_dead`,`total_upvotes`,`total_downvotes`),
  ADD KEY `idx_sites_performance` (`is_approved`,`is_dead`,`admin_approved_dead`,`status`,`created_at`);

--
-- Indexes for table `site_ads`
--
ALTER TABLE `site_ads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `position` (`position`),
  ADD KEY `is_active` (`is_active`);

--
-- Indexes for table `site_categories`
--
ALTER TABLE `site_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `site_clicks`
--
ALTER TABLE `site_clicks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_site` (`site_id`),
  ADD KEY `idx_clicked` (`clicked_at`);

--
-- Indexes for table `site_faqs`
--
ALTER TABLE `site_faqs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_site` (`site_id`),
  ADD KEY `idx_sort` (`sort_order`);

--
-- Indexes for table `site_features`
--
ALTER TABLE `site_features`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_site` (`site_id`),
  ADD KEY `idx_feature` (`feature_type`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `site_health_checks`
--
ALTER TABLE `site_health_checks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_site` (`site_id`),
  ADD KEY `idx_accessible` (`is_accessible`),
  ADD KEY `idx_checked` (`last_checked`);

--
-- Indexes for table `site_promotions`
--
ALTER TABLE `site_promotions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_site` (`site_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_type` (`promotion_type`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `site_upload_settings`
--
ALTER TABLE `site_upload_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `support_replies`
--
ALTER TABLE `support_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ticket` (`ticket_id`),
  ADD KEY `idx_admin` (`admin_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`);

--
-- Indexes for table `temp_email_domains`
--
ALTER TABLE `temp_email_domains`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `domain` (`domain`),
  ADD KEY `idx_blocked` (`is_blocked`),
  ADD KEY `fk_temp_email_domains_added_by` (`added_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `referral_code` (`referral_code`),
  ADD KEY `idx_reputation` (`reputation_points`),
  ADD KEY `idx_active` (`last_active`),
  ADD KEY `idx_banned` (`is_banned`),
  ADD KEY `idx_referral` (`referral_code`),
  ADD KEY `idx_users_referral_code` (`referral_code`),
  ADD KEY `idx_users_last_ip` (`last_ip`),
  ADD KEY `idx_last_active` (`last_active`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `user_actions`
--
ALTER TABLE `user_actions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_actions_user_id` (`user_id`),
  ADD KEY `idx_user_actions_action` (`action`),
  ADD KEY `idx_user_actions_created` (`created_at`);

--
-- Indexes for table `user_advertisements`
--
ALTER TABLE `user_advertisements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`),
  ADD KEY `ad_type` (`ad_type`),
  ADD KEY `visibility_level` (`visibility_level`),
  ADD KEY `idx_target_space_id` (`target_space_id`),
  ADD KEY `idx_is_cross_pool` (`is_cross_pool`),
  ADD KEY `idx_status_dates` (`status`,`start_date`,`end_date`);

--
-- Indexes for table `user_badges`
--
ALTER TABLE `user_badges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_badge` (`user_id`,`badge_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_badge` (`badge_id`);

--
-- Indexes for table `user_referrals`
--
ALTER TABLE `user_referrals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_referral` (`referrer_id`,`referred_id`),
  ADD KEY `idx_referrer` (`referrer_id`),
  ADD KEY `idx_referred` (`referred_id`),
  ADD KEY `idx_code` (`referral_code`),
  ADD KEY `referrer_created` (`referrer_id`,`created_at`),
  ADD KEY `points_earned` (`points_earned`);

--
-- Indexes for table `user_wallets`
--
ALTER TABLE `user_wallets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `votes`
--
ALTER TABLE `votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_site_vote` (`user_id`,`site_id`),
  ADD UNIQUE KEY `unique_review_vote` (`user_id`,`review_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_site` (`site_id`),
  ADD KEY `idx_review` (`review_id`),
  ADD KEY `idx_votes_site_id` (`site_id`),
  ADD KEY `idx_votes_review_id` (`review_id`),
  ADD KEY `idx_votes_user_id` (`user_id`),
  ADD KEY `idx_votes_type` (`vote_type`),
  ADD KEY `idx_votes_performance` (`site_id`,`vote_type`,`created_at`);

--
-- Indexes for table `wallet_settings`
--
ALTER TABLE `wallet_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `withdrawal_currencies`
--
ALTER TABLE `withdrawal_currencies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `currency_code` (`currency_code`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `withdrawal_requests`
--
ALTER TABLE `withdrawal_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_method` (`withdrawal_method`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_action_logs`
--
ALTER TABLE `admin_action_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `ad_clicks`
--
ALTER TABLE `ad_clicks`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ad_impressions`
--
ALTER TABLE `ad_impressions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ad_pricing`
--
ALTER TABLE `ad_pricing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `ad_settings`
--
ALTER TABLE `ad_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `ad_spaces`
--
ALTER TABLE `ad_spaces`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `ad_space_assignments`
--
ALTER TABLE `ad_space_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ad_transactions`
--
ALTER TABLE `ad_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `automated_notifications`
--
ALTER TABLE `automated_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `backlink_tracking`
--
ALTER TABLE `backlink_tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `blocked_ips`
--
ALTER TABLE `blocked_ips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `coupon_redemptions`
--
ALTER TABLE `coupon_redemptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `coupon_security_logs`
--
ALTER TABLE `coupon_security_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `credit_transactions`
--
ALTER TABLE `credit_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `deposit_transactions`
--
ALTER TABLE `deposit_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `email_campaigns`
--
ALTER TABLE `email_campaigns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_queue`
--
ALTER TABLE `email_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feature_pricing`
--
ALTER TABLE `feature_pricing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `ip_registrations`
--
ALTER TABLE `ip_registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `levels`
--
ALTER TABLE `levels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `newsletter_subscriptions`
--
ALTER TABLE `newsletter_subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `notification_queue`
--
ALTER TABLE `notification_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `points_transactions`
--
ALTER TABLE `points_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `promotion_pricing`
--
ALTER TABLE `promotion_pricing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `redirect_ads`
--
ALTER TABLE `redirect_ads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `referral_analytics`
--
ALTER TABLE `referral_analytics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `referral_clicks`
--
ALTER TABLE `referral_clicks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `referral_contests`
--
ALTER TABLE `referral_contests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `referral_milestones`
--
ALTER TABLE `referral_milestones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `referral_tiers`
--
ALTER TABLE `referral_tiers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `review_replies`
--
ALTER TABLE `review_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `review_reply_votes`
--
ALTER TABLE `review_reply_votes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scam_reports_log`
--
ALTER TABLE `scam_reports_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `secure_visit_tokens`
--
ALTER TABLE `secure_visit_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;

--
-- AUTO_INCREMENT for table `security_logs`
--
ALTER TABLE `security_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=92;

--
-- AUTO_INCREMENT for table `sites`
--
ALTER TABLE `sites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT for table `site_ads`
--
ALTER TABLE `site_ads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `site_categories`
--
ALTER TABLE `site_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `site_clicks`
--
ALTER TABLE `site_clicks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `site_faqs`
--
ALTER TABLE `site_faqs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `site_features`
--
ALTER TABLE `site_features`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `site_health_checks`
--
ALTER TABLE `site_health_checks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `site_promotions`
--
ALTER TABLE `site_promotions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `support_replies`
--
ALTER TABLE `support_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `temp_email_domains`
--
ALTER TABLE `temp_email_domains`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `user_actions`
--
ALTER TABLE `user_actions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_advertisements`
--
ALTER TABLE `user_advertisements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_badges`
--
ALTER TABLE `user_badges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `user_referrals`
--
ALTER TABLE `user_referrals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_wallets`
--
ALTER TABLE `user_wallets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `votes`
--
ALTER TABLE `votes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `withdrawal_currencies`
--
ALTER TABLE `withdrawal_currencies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `withdrawal_requests`
--
ALTER TABLE `withdrawal_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Structure for view `review_reply_counts`
--
DROP TABLE IF EXISTS `review_reply_counts`;

CREATE ALGORITHM=UNDEFINED DEFINER=`cpses_mwge22tp9k`@`localhost` SQL SECURITY DEFINER VIEW `review_reply_counts`  AS SELECT `r`.`id` AS `review_id`, count(`rr`.`id`) AS `reply_count`, max(`rr`.`created_at`) AS `last_reply_at` FROM (`reviews` `r` left join `review_replies` `rr` on(`r`.`id` = `rr`.`review_id` and `rr`.`is_deleted` = 0)) GROUP BY `r`.`id` ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_action_logs`
--
ALTER TABLE `admin_action_logs`
  ADD CONSTRAINT `admin_action_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ad_clicks`
--
ALTER TABLE `ad_clicks`
  ADD CONSTRAINT `ad_clicks_ibfk_1` FOREIGN KEY (`ad_id`) REFERENCES `user_advertisements` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ad_impressions`
--
ALTER TABLE `ad_impressions`
  ADD CONSTRAINT `ad_impressions_ibfk_1` FOREIGN KEY (`ad_id`) REFERENCES `user_advertisements` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ad_space_assignments`
--
ALTER TABLE `ad_space_assignments`
  ADD CONSTRAINT `ad_space_assignments_ibfk_1` FOREIGN KEY (`ad_id`) REFERENCES `user_advertisements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ad_space_assignments_ibfk_2` FOREIGN KEY (`space_id`) REFERENCES `ad_spaces` (`space_id`) ON DELETE CASCADE;

--
-- Constraints for table `ad_transactions`
--
ALTER TABLE `ad_transactions`
  ADD CONSTRAINT `ad_transactions_ibfk_1` FOREIGN KEY (`ad_id`) REFERENCES `user_advertisements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ad_transactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `backlink_tracking`
--
ALTER TABLE `backlink_tracking`
  ADD CONSTRAINT `fk_backlink_tracking_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `blocked_ips`
--
ALTER TABLE `blocked_ips`
  ADD CONSTRAINT `fk_blocked_ips_blocked_by` FOREIGN KEY (`blocked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `coupons`
--
ALTER TABLE `coupons`
  ADD CONSTRAINT `fk_coupons_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `coupon_redemptions`
--
ALTER TABLE `coupon_redemptions`
  ADD CONSTRAINT `fk_coupon_redemptions_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_coupon_redemptions_transaction` FOREIGN KEY (`deposit_transaction_id`) REFERENCES `deposit_transactions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_coupon_redemptions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `coupon_security_logs`
--
ALTER TABLE `coupon_security_logs`
  ADD CONSTRAINT `fk_coupon_security_logs_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_coupon_security_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `deposit_transactions`
--
ALTER TABLE `deposit_transactions`
  ADD CONSTRAINT `fk_deposit_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `email_campaigns`
--
ALTER TABLE `email_campaigns`
  ADD CONSTRAINT `fk_email_campaigns_sent_by` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `email_queue`
--
ALTER TABLE `email_queue`
  ADD CONSTRAINT `fk_email_queue_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `email_campaigns` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ip_registrations`
--
ALTER TABLE `ip_registrations`
  ADD CONSTRAINT `fk_ip_registrations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `newsletter_subscriptions`
--
ALTER TABLE `newsletter_subscriptions`
  ADD CONSTRAINT `fk_newsletter_subscriptions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_queue`
--
ALTER TABLE `notification_queue`
  ADD CONSTRAINT `notification_queue_ibfk_1` FOREIGN KEY (`notification_id`) REFERENCES `automated_notifications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `fk_password_reset_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `points_transactions`
--
ALTER TABLE `points_transactions`
  ADD CONSTRAINT `fk_points_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `referral_analytics`
--
ALTER TABLE `referral_analytics`
  ADD CONSTRAINT `referral_analytics_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `referral_milestones`
--
ALTER TABLE `referral_milestones`
  ADD CONSTRAINT `referral_milestones_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD CONSTRAINT `remember_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `fk_reviews_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reviews_site_id` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reviews_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `review_replies`
--
ALTER TABLE `review_replies`
  ADD CONSTRAINT `fk_review_replies_parent` FOREIGN KEY (`parent_reply_id`) REFERENCES `review_replies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_review_replies_review` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_review_replies_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `review_reply_votes`
--
ALTER TABLE `review_reply_votes`
  ADD CONSTRAINT `fk_reply_votes_reply` FOREIGN KEY (`reply_id`) REFERENCES `review_replies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reply_votes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `secure_visit_tokens`
--
ALTER TABLE `secure_visit_tokens`
  ADD CONSTRAINT `fk_secure_visit_tokens_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_secure_visit_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `security_logs`
--
ALTER TABLE `security_logs`
  ADD CONSTRAINT `fk_security_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sites`
--
ALTER TABLE `sites`
  ADD CONSTRAINT `fk_sites_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sites_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `site_clicks`
--
ALTER TABLE `site_clicks`
  ADD CONSTRAINT `fk_site_clicks_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `site_faqs`
--
ALTER TABLE `site_faqs`
  ADD CONSTRAINT `fk_site_faqs_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `site_features`
--
ALTER TABLE `site_features`
  ADD CONSTRAINT `fk_site_features_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `site_health_checks`
--
ALTER TABLE `site_health_checks`
  ADD CONSTRAINT `fk_site_health_checks_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `site_promotions`
--
ALTER TABLE `site_promotions`
  ADD CONSTRAINT `fk_site_promotions_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_site_promotions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `support_replies`
--
ALTER TABLE `support_replies`
  ADD CONSTRAINT `fk_support_replies_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_support_replies_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_support_replies_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD CONSTRAINT `fk_support_tickets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `temp_email_domains`
--
ALTER TABLE `temp_email_domains`
  ADD CONSTRAINT `fk_temp_email_domains_added_by` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_actions`
--
ALTER TABLE `user_actions`
  ADD CONSTRAINT `user_actions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_advertisements`
--
ALTER TABLE `user_advertisements`
  ADD CONSTRAINT `user_advertisements_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_badges`
--
ALTER TABLE `user_badges`
  ADD CONSTRAINT `fk_user_badges_badge` FOREIGN KEY (`badge_id`) REFERENCES `levels` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_badges_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_referrals`
--
ALTER TABLE `user_referrals`
  ADD CONSTRAINT `fk_user_referrals_referred` FOREIGN KEY (`referred_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_referrals_referrer` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_wallets`
--
ALTER TABLE `user_wallets`
  ADD CONSTRAINT `fk_user_wallets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `votes`
--
ALTER TABLE `votes`
  ADD CONSTRAINT `fk_votes_review` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_votes_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_votes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `withdrawal_requests`
--
ALTER TABLE `withdrawal_requests`
  ADD CONSTRAINT `fk_withdrawal_requests_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
