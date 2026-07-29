<?php
namespace SRP;

class Database {

    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Referral Categories
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}srp_categories (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            slug varchar(100) NOT NULL,
            description text,
            referrer_percentage decimal(5,2) NOT NULL DEFAULT 10.00,
            referee_discount decimal(5,2) NOT NULL DEFAULT 5.00,
            min_purchase_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            max_earnings decimal(10,2) DEFAULT NULL,
            withdrawal_method enum('coupon','withdrawal','both') NOT NULL DEFAULT 'both',
            min_withdrawal decimal(10,2) NOT NULL DEFAULT 10.00,
            status enum('active','inactive') NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";

        // Referral Codes
        $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}srp_referral_codes (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            code varchar(50) NOT NULL,
            category_id bigint(20) unsigned DEFAULT NULL,
            total_referrals int(11) NOT NULL DEFAULT 0,
            total_earnings decimal(12,2) NOT NULL DEFAULT 0.00,
            total_withdrawn decimal(12,2) NOT NULL DEFAULT 0.00,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Referral Records
        $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}srp_referrals (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            referrer_id bigint(20) unsigned NOT NULL,
            referee_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned NOT NULL,
            category_id bigint(20) unsigned NOT NULL,
            order_total decimal(10,2) NOT NULL,
            referrer_earnings decimal(10,2) NOT NULL,
            referee_discount decimal(10,2) NOT NULL,
            status enum('pending','completed','cancelled','refunded') NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_referee (referee_id),
            KEY referrer_id (referrer_id),
            KEY order_id (order_id)
        ) $charset_collate;";

        // Wallet/Earnings
        $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}srp_wallet (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            referral_id bigint(20) unsigned DEFAULT NULL,
            amount decimal(10,2) NOT NULL,
            type enum('earning','withdrawal','coupon_conversion','adjustment') NOT NULL,
            status enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            description text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime DEFAULT NULL,
            processed_by bigint(20) unsigned DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";

        // Coupons generated from earnings
        $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}srp_coupons (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            wallet_id bigint(20) unsigned NOT NULL,
            coupon_code varchar(50) NOT NULL,
            amount decimal(10,2) NOT NULL,
            wc_coupon_id bigint(20) unsigned NOT NULL,
            status enum('active','used','expired') NOT NULL DEFAULT 'active',
            used_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY coupon_code (coupon_code),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Withdrawal Requests
        $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}srp_withdrawals (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            wallet_id bigint(20) unsigned NOT NULL,
            amount decimal(10,2) NOT NULL,
            method enum('bank_transfer','paypal','stripe') NOT NULL,
            payment_details longtext,
            status enum('pending','processing','completed','rejected') NOT NULL DEFAULT 'pending',
            admin_notes text,
            requested_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime DEFAULT NULL,
            processed_by bigint(20) unsigned DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";

        // Referral Visits (tracking)
        $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}srp_visits (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            referral_code varchar(50) NOT NULL,
            visitor_ip varchar(45) NOT NULL,
            user_agent text,
            converted tinyint(1) NOT NULL DEFAULT 0,
            converted_user_id bigint(20) unsigned DEFAULT NULL,
            visited_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY referral_code (referral_code),
            KEY visitor_ip (visitor_ip)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Create default category
        $default_exists = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}srp_categories WHERE slug = 'default'");
        if (!$default_exists) {
            $wpdb->insert($wpdb->prefix . 'srp_categories', [
                'name' => 'Default Referral',
                'slug' => 'default',
                'description' => 'Default referral category',
                'referrer_percentage' => 10.00,
                'referee_discount' => 5.00,
                'withdrawal_method' => 'both',
                'min_withdrawal' => 10.00,
                'status' => 'active'
            ]);
        }

        // Schedule cron for processing pending withdrawals
        if (!wp_next_scheduled('srp_process_pending_withdrawals')) {
            wp_schedule_event(time(), 'daily', 'srp_process_pending_withdrawals');
        }
    }
}
