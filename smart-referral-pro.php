<?php
/**
 * Plugin Name: Smart Referral Pro
 * Description: Advanced referral system with wallet, coupons, and admin dashboard
 * Version: 1.0.0
 * Author: Smart Referral Pro
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SRP_VERSION', '1.0.0');
define('SRP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SRP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'SRP\\';
    $base_dir = SRP_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Activation hook
register_activation_hook(__FILE__, function () {
    require_once SRP_PLUGIN_DIR . 'includes/Database.php';
    SRP\Database::activate();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('srp_process_pending_withdrawals');
});

// Initialize
add_action('plugins_loaded', function () {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="error"><p>Smart Referral Pro requires WooCommerce to be installed and active.</p></div>';
        });
        return;
    }

    SRP\Core::init();
});
