<?php
namespace SRP;

class Security {

    /**
     * Verify nonce with proper error handling
     */
    public static function verify_nonce($nonce, $action = 'srp_nonce_action') {
        if (!wp_verify_nonce($nonce, $action)) {
            wp_send_json_error(['message' => 'Security check failed. Please refresh the page and try again.']);
            exit;
        }
        return true;
    }

    /**
     * Check user capabilities
     */
    public static function check_capability($capability = 'manage_options') {
        if (!current_user_can($capability)) {
            wp_send_json_error(['message' => 'You do not have permission to perform this action.']);
            exit;
        }
        return true;
    }

    /**
     * Sanitize referral code
     */
    public static function sanitize_code($code) {
        $code = sanitize_text_field($code);
        $code = preg_replace('/[^a-zA-Z0-9_-]/', '', $code);
        return strtoupper(substr($code, 0, 50));
    }

    /**
     * Validate and sanitize decimal amount
     */
    public static function sanitize_amount($amount) {
        $amount = floatval($amount);
        return round(max(0, $amount), 2);
    }

    /**
     * Rate limiting for API endpoints
     */
    public static function check_rate_limit($identifier, $max_attempts = 10, $window = 60) {
        $transient_key = 'srp_rate_' . md5($identifier);
        $attempts = get_transient($transient_key);

        if ($attempts === false) {
            set_transient($transient_key, 1, $window);
            return true;
        }

        if ($attempts >= $max_attempts) {
            wp_send_json_error(['message' => 'Too many requests. Please try again later.']);
            exit;
        }

        set_transient($transient_key, $attempts + 1, $window);
        return true;
    }

    /**
     * Log security events
     */
    public static function log_event($event, $data = []) {
        $log_data = [
            'timestamp' => current_time('mysql'),
            'event' => sanitize_text_field($event),
            'user_id' => get_current_user_id(),
            'ip' => self::get_client_ip(),
            'data' => $data
        ];

        do_action('srp_security_log', $log_data);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SRP Security: ' . json_encode($log_data));
        }
    }

    /**
     * Get client IP safely
     */
    public static function get_client_ip() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = sanitize_text_field(wp_unslash($_SERVER[$key]));
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    /**
     * SQL injection protection wrapper
     */
    public static function esc_like($string) {
        global $wpdb;
        return $wpdb->esc_like($string);
    }

    /**
     * Hash sensitive data
     */
    public static function hash_data($data) {
        return hash_hmac('sha256', $data, wp_salt('auth'));
    }

    /**
     * Encrypt sensitive payment details
     */
    public static function encrypt_data($data) {
        if (!extension_loaded('openssl')) {
            return base64_encode($data); // Fallback - not recommended for production
        }
        $key = wp_salt('auth');
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt sensitive payment details
     */
    public static function decrypt_data($data) {
        if (!extension_loaded('openssl')) {
            return base64_decode($data);
        }
        $key = wp_salt('auth');
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
}
