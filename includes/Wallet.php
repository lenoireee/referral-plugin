<?php
namespace SRP;

class Wallet {

    /**
     * Get user's current balance
     */
    public static function get_balance($user_id) {
        global $wpdb;

        $earnings = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}srp_wallet 
             WHERE user_id = %d AND type IN ('earning', 'adjustment') AND status = 'approved'",
            $user_id
        ));

        $withdrawals = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}srp_wallet 
             WHERE user_id = %d AND type IN ('withdrawal', 'coupon_conversion') AND status = 'approved'",
            $user_id
        ));

        return round(floatval($earnings) - floatval($withdrawals), 2);
    }

    /**
     * Get pending balance
     */
    public static function get_pending_balance($user_id) {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}srp_wallet 
             WHERE user_id = %d AND type = 'earning' AND status = 'pending'",
            $user_id
        ));
    }

    /**
     * Add earning to wallet
     */
    public static function add_earning($user_id, $amount, $referral_id, $description = '') {
        global $wpdb;

        $amount = Security::sanitize_amount($amount);

        if ($amount <= 0) {
            return false;
        }

        $result = $wpdb->insert(
            $wpdb->prefix . 'srp_wallet',
            [
                'user_id' => $user_id,
                'referral_id' => $referral_id,
                'amount' => $amount,
                'type' => 'earning',
                'status' => 'pending', // Pending until order is completed
                'description' => $description,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%f', '%s', '%s', '%s', '%s']
        );

        if ($result) {
            do_action('srp_earning_added', $user_id, $amount, $referral_id);
        }

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Approve earning (called when order is completed)
     */
    public static function approve_earning($referral_id) {
        global $wpdb;

        $result = $wpdb->update(
            $wpdb->prefix . 'srp_wallet',
            [
                'status' => 'approved',
                'processed_at' => current_time('mysql')
            ],
            ['referral_id' => $referral_id, 'type' => 'earning'],
            ['%s', '%s'],
            ['%d', '%s']
        );

        if ($result) {
            $wallet = $wpdb->get_row($wpdb->prepare(
                "SELECT user_id, amount FROM {$wpdb->prefix}srp_wallet WHERE referral_id = %d",
                $referral_id
            ));

            if ($wallet) {
                do_action('srp_earning_approved', $wallet->user_id, $wallet->amount, $referral_id);
            }
        }

        return $result;
    }

    /**
     * Convert earnings to coupon
     */
    public static function convert_to_coupon($user_id, $amount) {
        global $wpdb;

        Security::check_capability('read'); // At least subscriber

        if (get_current_user_id() !== $user_id && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return false;
        }

        $amount = Security::sanitize_amount($amount);
        $balance = self::get_balance($user_id);

        if ($amount > $balance) {
            wp_send_json_error(['message' => 'Insufficient balance']);
            return false;
        }

        // Check category rules
        $code = ReferralCode::get_user_code($user_id);
        $category = self::get_category_rules($code->category_id ?? null);

        if ($category && $category->withdrawal_method === 'withdrawal') {
            wp_send_json_error(['message' => 'Coupon conversion not allowed for this category']);
            return false;
        }

        // Create WooCommerce coupon
        $coupon_code = 'REF' . strtoupper(wp_generate_password(8, false));

        $coupon = new \WC_Coupon();
        $coupon->set_code($coupon_code);
        $coupon->set_discount_type('fixed_cart');
        $coupon->set_amount($amount);
        $coupon->set_individual_use(true);
        $coupon->set_usage_limit(1);
        $coupon->set_usage_limit_per_user(1);
        $coupon->set_date_expires(date('Y-m-d', strtotime('+1 year')));
        $coupon->set_description("Generated from referral earnings for user #$user_id");
        $coupon->save();

        // Deduct from wallet
        $wallet_id = $wpdb->insert(
            $wpdb->prefix . 'srp_wallet',
            [
                'user_id' => $user_id,
                'amount' => $amount,
                'type' => 'coupon_conversion',
                'status' => 'approved',
                'description' => "Converted to coupon: $coupon_code",
                'created_at' => current_time('mysql'),
                'processed_at' => current_time('mysql')
            ]
        );

        // Store coupon reference
        $wpdb->insert(
            $wpdb->prefix . 'srp_coupons',
            [
                'user_id' => $user_id,
                'wallet_id' => $wallet_id,
                'coupon_code' => $coupon_code,
                'amount' => $amount,
                'wc_coupon_id' => $coupon->get_id(),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+1 year')),
                'created_at' => current_time('mysql')
            ]
        );

        do_action('srp_coupon_created', $user_id, $amount, $coupon_code);

        return $coupon_code;
    }

    /**
     * Request withdrawal
     */
    public static function request_withdrawal($user_id, $amount, $method, $payment_details) {
        global $wpdb;

        Security::check_capability('read');

        if (get_current_user_id() !== $user_id && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return false;
        }

        $amount = Security::sanitize_amount($amount);
        $balance = self::get_balance($user_id);

        if ($amount > $balance) {
            wp_send_json_error(['message' => 'Insufficient balance']);
            return false;
        }

        // Check category rules
        $code = ReferralCode::get_user_code($user_id);
        $category = self::get_category_rules($code->category_id ?? null);

        if ($category) {
            if ($category->withdrawal_method === 'coupon') {
                wp_send_json_error(['message' => 'Withdrawal not allowed for this category']);
                return false;
            }

            if ($amount < $category->min_withdrawal) {
                wp_send_json_error([
                    'message' => sprintf('Minimum withdrawal amount is %s', wc_price($category->min_withdrawal))
                ]);
                return false;
            }
        }

        // Encrypt payment details
        $encrypted_details = Security::encrypt_data(wp_json_encode($payment_details));

        // Create wallet entry
        $wallet_id = $wpdb->insert(
            $wpdb->prefix . 'srp_wallet',
            [
                'user_id' => $user_id,
                'amount' => $amount,
                'type' => 'withdrawal',
                'status' => 'pending',
                'description' => "Withdrawal request via $method",
                'created_at' => current_time('mysql')
            ]
        );

        // Create withdrawal request
        $wpdb->insert(
            $wpdb->prefix . 'srp_withdrawals',
            [
                'user_id' => $user_id,
                'wallet_id' => $wallet_id,
                'amount' => $amount,
                'method' => $method,
                'payment_details' => $encrypted_details,
                'status' => 'pending',
                'requested_at' => current_time('mysql')
            ]
        );

        do_action('srp_withdrawal_requested', $user_id, $amount, $method);

        return $wpdb->insert_id;
    }

    /**
     * Process withdrawal (admin)
     */
    public static function process_withdrawal($withdrawal_id, $status, $admin_notes = '') {
        global $wpdb;

        Security::check_capability('manage_options');

        $withdrawal = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}srp_withdrawals WHERE id = %d",
            $withdrawal_id
        ));

        if (!$withdrawal) {
            return false;
        }

        $wpdb->update(
            $wpdb->prefix . 'srp_withdrawals',
            [
                'status' => $status,
                'admin_notes' => $admin_notes,
                'processed_at' => current_time('mysql'),
                'processed_by' => get_current_user_id()
            ],
            ['id' => $withdrawal_id]
        );

        // Update wallet entry
        $wpdb->update(
            $wpdb->prefix . 'srp_wallet',
            [
                'status' => $status === 'completed' ? 'approved' : 'rejected',
                'processed_at' => current_time('mysql'),
                'processed_by' => get_current_user_id()
            ],
            ['id' => $withdrawal->wallet_id]
        );

        do_action('srp_withdrawal_processed', $withdrawal->user_id, $withdrawal->amount, $status);

        return true;
    }

    /**
     * Get user's transaction history
     */
    public static function get_transactions($user_id, $limit = 50, $offset = 0) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}srp_wallet 
             WHERE user_id = %d 
             ORDER BY created_at DESC 
             LIMIT %d OFFSET %d",
            $user_id, $limit, $offset
        ));
    }

    /**
     * Get user's coupons
     */
    public static function get_coupons($user_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}srp_coupons 
             WHERE user_id = %d 
             ORDER BY created_at DESC",
            $user_id
        ));
    }

    /**
     * Get category rules
     */
    private static function get_category_rules($category_id) {
        global $wpdb;

        if (!$category_id) {
            return $wpdb->get_row("SELECT * FROM {$wpdb->prefix}srp_categories WHERE slug = 'default'");
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}srp_categories WHERE id = %d",
            $category_id
        ));
    }

    /**
     * Cron: Process scheduled withdrawals
     */
    public static function process_scheduled_withdrawals() {
        global $wpdb;

        // Auto-reject pending withdrawals older than 30 days
        $wpdb->query(
            "UPDATE {$wpdb->prefix}srp_withdrawals 
             SET status = 'rejected', 
                 admin_notes = 'Auto-rejected: exceeded processing time',
                 processed_at = NOW()
             WHERE status = 'pending' 
             AND requested_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        // Update corresponding wallet entries
        $wpdb->query(
            "UPDATE {$wpdb->prefix}srp_wallet w
             JOIN {$wpdb->prefix}srp_withdrawals wd ON w.id = wd.wallet_id
             SET w.status = 'rejected', w.processed_at = NOW()
             WHERE wd.status = 'rejected' AND w.status = 'pending'"
        );
    }
}
