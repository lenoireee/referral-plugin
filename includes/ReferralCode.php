<?php
namespace SRP;

class ReferralCode {

    /**
     * Generate a unique, memorable referral code from user data
     */
    public static function generate_code($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        // Try to create something memorable from user's name
        $first_name = sanitize_title($user->first_name);
        $last_name = sanitize_title($user->last_name);
        $display_name = sanitize_title($user->display_name);
        $user_login = sanitize_title($user->user_login);

        $base = '';

        // Priority: first name + last initial, then display name, then username
        if (!empty($first_name)) {
            $base = $first_name;
            if (!empty($last_name)) {
                $base .= substr($last_name, 0, 1);
            }
        } elseif (!empty($display_name) && $display_name !== $user_login) {
            $base = $display_name;
        } else {
            $base = $user_login;
        }

        // Clean and format
        $base = preg_replace('/[^a-zA-Z0-9]/', '', $base);
        $base = substr($base, 0, 12);
        $base = strtoupper($base);

        // Ensure uniqueness
        $code = $base;
        $counter = 1;

        global $wpdb;
        while ($wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}srp_referral_codes WHERE code = %s",
            $code
        ))) {
            $suffix = self::generate_suffix(3);
            $code = substr($base, 0, 9) . $suffix;
            $counter++;

            if ($counter > 100) {
                // Fallback to random code
                $code = 'REF' . self::generate_suffix(8);
                break;
            }
        }

        return $code;
    }

    /**
     * Generate random suffix
     */
    private static function generate_suffix($length = 4) {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Removed confusing chars
        $suffix = '';
        for ($i = 0; $i < $length; $i++) {
            $suffix .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $suffix;
    }

    /**
     * Create referral code for user
     */
    public static function create_code($user_id, $category_id = null) {
        global $wpdb;

        // Check if user already has a code
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}srp_referral_codes WHERE user_id = %d",
            $user_id
        ));

        if ($existing) {
            return false; // User already has a code
        }

        $code = self::generate_code($user_id);

        $result = $wpdb->insert(
            $wpdb->prefix . 'srp_referral_codes',
            [
                'user_id' => $user_id,
                'code' => $code,
                'category_id' => $category_id,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%d', '%s']
        );

        if ($result) {
            do_action('srp_code_created', $user_id, $code);
            return $code;
        }

        return false;
    }

    /**
     * Auto-generate on user registration
     */
    public static function generate_user_code($user_id) {
        // Get default category
        global $wpdb;
        $default_category = $wpdb->get_var(
            "SELECT id FROM {$wpdb->prefix}srp_categories WHERE slug = 'default' LIMIT 1"
        );

        self::create_code($user_id, $default_category);
    }

    /**
     * Get user's referral code
     */
    public static function get_user_code($user_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}srp_referral_codes WHERE user_id = %d",
            $user_id
        ));
    }

    /**
     * Get code by string
     */
    public static function get_by_code($code) {
        global $wpdb;
        $code = Security::sanitize_code($code);
        return $wpdb->get_row($wpdb->prepare(
            "SELECT rc.*, c.referrer_percentage, c.referee_discount, c.min_purchase_amount, 
                    c.withdrawal_method, c.min_withdrawal, c.name as category_name
             FROM {$wpdb->prefix}srp_referral_codes rc
             LEFT JOIN {$wpdb->prefix}srp_categories c ON rc.category_id = c.id
             WHERE rc.code = %s AND c.status = 'active'",
            $code
        ));
    }

    /**
     * Capture referral from URL query variable
     */
    public static function capture_referral_url() {
        if (!isset($_GET['ref'])) {
            return;
        }

        $code = Security::sanitize_code(sanitize_text_field(wp_unslash($_GET['ref'])));

        if (empty($code)) {
            return;
        }

        // Validate code exists
        $referral = self::get_by_code($code);
        if (!$referral) {
            return;
        }

        // Don't allow self-referral
        if (is_user_logged_in() && get_current_user_id() == $referral->user_id) {
            return;
        }

        // Set cookie (30 days)
        setcookie('srp_referral_code', $code, [
            'expires' => time() + 30 * DAY_IN_SECONDS,
            'path' => '/',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        // Log visit
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'srp_visits',
            [
                'referral_code' => $code,
                'visitor_ip' => Security::get_client_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
                'visited_at' => current_time('mysql')
            ]
        );

        // Redirect to remove query parameter (clean URL)
        $current_url = remove_query_arg('ref');
        wp_safe_redirect($current_url);
        exit;
    }

    /**
     * Get active referral code from cookie/session
     */
    public static function get_active_referral() {
        if (isset($_COOKIE['srp_referral_code'])) {
            $code = Security::sanitize_code(sanitize_text_field(wp_unslash($_COOKIE['srp_referral_code'])));
            return self::get_by_code($code);
        }
        return false;
    }

    /**
     * Clear referral cookie
     */
    public static function clear_referral_cookie() {
        setcookie('srp_referral_code', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    /**
     * Check if user has been referred before (one-time only rule)
     */
    public static function has_been_referred($user_id) {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}srp_referrals WHERE referee_id = %d",
            $user_id
        ));
    }

    /**
     * Get referral statistics
     */
    public static function get_stats($user_id) {
        global $wpdb;

        $code = self::get_user_code($user_id);
        if (!$code) {
            return false;
        }

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(DISTINCT r.id) as total_referrals,
                SUM(CASE WHEN r.status = 'completed' THEN r.referrer_earnings ELSE 0 END) as total_earnings,
                SUM(CASE WHEN r.status = 'pending' THEN r.referrer_earnings ELSE 0 END) as pending_earnings,
                COUNT(DISTINCT v.id) as total_visits,
                COUNT(DISTINCT CASE WHEN v.converted = 1 THEN v.id END) as converted_visits
             FROM {$wpdb->prefix}srp_referral_codes rc
             LEFT JOIN {$wpdb->prefix}srp_referrals r ON rc.user_id = r.referrer_id
             LEFT JOIN {$wpdb->prefix}srp_visits v ON rc.code = v.referral_code
             WHERE rc.user_id = %d",
            $user_id
        ));

        $stats->code = $code->code;
        $stats->referral_url = home_url('?ref=' . $code->code);

        return $stats;
    }
}
