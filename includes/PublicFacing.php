<?php
namespace SRP;

class PublicFacing {

    /**
     * Enqueue public assets
     */
    public static function enqueue_assets() {
        if (!is_account_page() && !is_checkout()) {
            return;
        }

        wp_enqueue_style('srp-public-css', SRP_PLUGIN_URL . 'assets/css/public.css', [], SRP_VERSION);
        wp_enqueue_script('srp-public-js', SRP_PLUGIN_URL . 'assets/js/public.js', ['jquery'], SRP_VERSION, true);
        wp_localize_script('srp-public-js', 'srpPublic', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('srp_public_nonce')
        ]);
    }

    /**
     * Add account endpoint
     */
    public static function add_endpoint() {
        add_rewrite_endpoint('referrals', EP_ROOT | EP_PAGES);
    }

    /**
     * Add account menu item
     */
    public static function add_account_tab($items) {
        $items['referrals'] = __('My Referrals', 'smart-referral-pro');
        return $items;
    }

    /**
     * Render account page
     */
    public static function render_account_page() {
        $user_id = get_current_user_id();

        // Ensure user has a code
        $code_data = ReferralCode::get_user_code($user_id);
        if (!$code_data) {
            ReferralCode::generate_user_code($user_id);
            $code_data = ReferralCode::get_user_code($user_id);
        }

        $stats = ReferralCode::get_stats($user_id);
        $balance = Wallet::get_balance($user_id);
        $pending_balance = Wallet::get_pending_balance($user_id);
        $transactions = Wallet::get_transactions($user_id, 20);
        $coupons = Wallet::get_coupons($user_id);

        // Get category rules for withdrawal options
        $category = null;
        if ($code_data && $code_data->category_id) {
            global $wpdb;
            $category = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}srp_categories WHERE id = %d",
                $code_data->category_id
            ));
        }

        include SRP_PLUGIN_DIR . 'public/account-referrals.php';
    }

    /**
     * Handle public AJAX
     */
    public static function handle_ajax() {
        check_ajax_referer('srp_public_nonce', 'nonce');

        $action = sanitize_text_field(wp_unslash($_POST['srp_action'] ?? ''));

        switch ($action) {
            case 'convert_to_coupon':
                self::ajax_convert_to_coupon();
                break;
            case 'request_withdrawal':
                self::ajax_request_withdrawal();
                break;
            case 'get_referral_link':
                self::ajax_get_referral_link();
                break;
            case 'apply_referral':
                Checkout::ajax_apply_referral();
                break;
            default:
                wp_send_json_error(['message' => 'Unknown action']);
        }
    }

    /**
     * Handle non-logged in AJAX
     */
    public static function handle_nopriv_ajax() {
        $action = sanitize_text_field(wp_unslash($_POST['srp_action'] ?? ''));

        if ($action === 'apply_referral') {
            Checkout::ajax_apply_referral();
            return;
        }

        wp_send_json_error(['message' => 'Please log in to perform this action.']);
    }

    /**
     * AJAX: Convert to coupon
     */
    private static function ajax_convert_to_coupon() {
        $user_id = get_current_user_id();
        $amount = Security::sanitize_amount($_POST['amount'] ?? 0);

        if ($amount <= 0) {
            wp_send_json_error(['message' => 'Invalid amount']);
            return;
        }

        $coupon_code = Wallet::convert_to_coupon($user_id, $amount);

        if ($coupon_code) {
            wp_send_json_success([
                'message' => sprintf('Coupon %s created successfully!', $coupon_code),
                'coupon' => $coupon_code,
                'balance' => Wallet::get_balance($user_id)
            ]);
        }
    }

    /**
     * AJAX: Request withdrawal
     */
    private static function ajax_request_withdrawal() {
        $user_id = get_current_user_id();
        $amount = Security::sanitize_amount($_POST['amount'] ?? 0);
        $method = sanitize_text_field(wp_unslash($_POST['method'] ?? 'paypal'));

        $payment_details = [];
        if ($method === 'paypal') {
            $payment_details['email'] = sanitize_email(wp_unslash($_POST['paypal_email'] ?? ''));
        } elseif ($method === 'bank_transfer') {
            $payment_details['account_name'] = sanitize_text_field(wp_unslash($_POST['account_name'] ?? ''));
            $payment_details['account_number'] = sanitize_text_field(wp_unslash($_POST['account_number'] ?? ''));
            $payment_details['bank_name'] = sanitize_text_field(wp_unslash($_POST['bank_name'] ?? ''));
            $payment_details['routing_number'] = sanitize_text_field(wp_unslash($_POST['routing_number'] ?? ''));
        } elseif ($method === 'stripe') {
            $payment_details['stripe_account'] = sanitize_text_field(wp_unslash($_POST['stripe_account'] ?? ''));
        }

        if ($amount <= 0) {
            wp_send_json_error(['message' => 'Invalid amount']);
            return;
        }

        $withdrawal_id = Wallet::request_withdrawal($user_id, $amount, $method, $payment_details);

        if ($withdrawal_id) {
            wp_send_json_success([
                'message' => 'Withdrawal request submitted successfully!',
                'withdrawal_id' => $withdrawal_id,
                'balance' => Wallet::get_balance($user_id)
            ]);
        }
    }

    /**
     * AJAX: Get referral link with stats
     */
    private static function ajax_get_referral_link() {
        $user_id = get_current_user_id();
        $stats = ReferralCode::get_stats($user_id);

        if (!$stats) {
            wp_send_json_error(['message' => 'No referral code found']);
            return;
        }

        wp_send_json_success([
            'code' => $stats->code,
            'url' => $stats->referral_url,
            'total_referrals' => $stats->total_referrals,
            'total_earnings' => $stats->total_earnings,
            'total_visits' => $stats->total_visits,
            'conversion_rate' => $stats->total_visits > 0 ? round(($stats->converted_visits / $stats->total_visits) * 100, 2) : 0
        ]);
    }
}
