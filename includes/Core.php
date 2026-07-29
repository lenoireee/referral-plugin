<?php
namespace SRP;

class Core {

    private static $instance = null;

    public static function init() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->setup_hooks();
    }

    private function load_dependencies() {
        require_once SRP_PLUGIN_DIR . 'includes/Security.php';
        require_once SRP_PLUGIN_DIR . 'includes/ReferralCode.php';
        require_once SRP_PLUGIN_DIR . 'includes/Wallet.php';
        require_once SRP_PLUGIN_DIR . 'includes/Checkout.php';
        require_once SRP_PLUGIN_DIR . 'includes/Admin.php';
        require_once SRP_PLUGIN_DIR . 'includes/PublicFacing.php';
        require_once SRP_PLUGIN_DIR . 'includes/Notifications.php';
    }

    private function setup_hooks() {
        // Admin
        add_action('admin_menu', [Admin::class, 'add_menu']);
        add_action('admin_enqueue_scripts', [Admin::class, 'enqueue_assets']);
        add_action('wp_ajax_srp_admin_action', [Admin::class, 'handle_ajax']);

        // Public
        add_action('wp_enqueue_scripts', [PublicFacing::class, 'enqueue_assets']);
        add_action('wp_ajax_srp_public_action', [PublicFacing::class, 'handle_ajax']);
        add_action('wp_ajax_nopriv_srp_public_action', [PublicFacing::class, 'handle_nopriv_ajax']);

        // WooCommerce Integration
        add_action('woocommerce_checkout_order_processed', [Checkout::class, 'process_referral'], 10, 3);
        add_action('woocommerce_order_status_completed', [Checkout::class, 'complete_referral']);
        add_action('woocommerce_order_status_cancelled', [Checkout::class, 'cancel_referral']);
        add_action('woocommerce_order_status_refunded', [Checkout::class, 'refund_referral']);

        // Account page tabs
        add_action('init', [PublicFacing::class, 'add_endpoint']);
        add_filter('woocommerce_account_menu_items', [PublicFacing::class, 'add_account_tab']);
        add_action('woocommerce_account_referrals_endpoint', [PublicFacing::class, 'render_account_page']);

        // Checkout coupon field
        add_action('woocommerce_before_checkout_form', [Checkout::class, 'render_referral_field'], 5);
        add_action('woocommerce_cart_calculate_fees', [Checkout::class, 'apply_referral_discount']);
        add_action('woocommerce_checkout_update_order_review', [Checkout::class, 'ajax_apply_referral']);

        // Query variable handling
        add_action('template_redirect', [ReferralCode::class, 'capture_referral_url']);

        // User registration
        add_action('user_register', [ReferralCode::class, 'generate_user_code']);

        // Cron
        add_action('srp_process_pending_withdrawals', [Wallet::class, 'process_scheduled_withdrawals']);

        // Notifications
        add_action('srp_referral_completed', [Notifications::class, 'send_referral_notification'], 10, 2);
        add_action('srp_withdrawal_processed', [Notifications::class, 'send_withdrawal_notification'], 10, 2);
    }
}
