<?php
namespace SRP;

class Admin {

    /**
     * Add admin menu
     */
    public static function add_menu() {
        add_menu_page(
            __('Referral System', 'smart-referral-pro'),
            __('Referrals', 'smart-referral-pro'),
            'manage_options',
            'srp-dashboard',
            [self::class, 'render_dashboard'],
            'dashicons-networking',
            30
        );

        add_submenu_page(
            'srp-dashboard',
            __('Dashboard', 'smart-referral-pro'),
            __('Dashboard', 'smart-referral-pro'),
            'manage_options',
            'srp-dashboard',
            [self::class, 'render_dashboard']
        );

        add_submenu_page(
            'srp-dashboard',
            __('Categories', 'smart-referral-pro'),
            __('Categories', 'smart-referral-pro'),
            'manage_options',
            'srp-categories',
            [self::class, 'render_categories']
        );

        add_submenu_page(
            'srp-dashboard',
            __('All Accounts', 'smart-referral-pro'),
            __('All Accounts', 'smart-referral-pro'),
            'manage_options',
            'srp-accounts',
            [self::class, 'render_accounts']
        );

        add_submenu_page(
            'srp-dashboard',
            __('Referral Records', 'smart-referral-pro'),
            __('Referral Records', 'smart-referral-pro'),
            'manage_options',
            'srp-records',
            [self::class, 'render_records']
        );

        add_submenu_page(
            'srp-dashboard',
            __('Withdrawals', 'smart-referral-pro'),
            __('Withdrawals', 'smart-referral-pro'),
            'manage_options',
            'srp-withdrawals',
            [self::class, 'render_withdrawals']
        );

        add_submenu_page(
            'srp-dashboard',
            __('Settings', 'smart-referral-pro'),
            __('Settings', 'smart-referral-pro'),
            'manage_options',
            'srp-settings',
            [self::class, 'render_settings']
        );
    }

    /**
     * Enqueue admin assets
     */
    public static function enqueue_assets($hook) {
        if (strpos($hook, 'srp-') === false) {
            return;
        }

        wp_enqueue_style('srp-admin-css', SRP_PLUGIN_URL . 'assets/css/admin.css', [], SRP_VERSION);
        wp_enqueue_script('srp-admin-js', SRP_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], SRP_VERSION, true);
        wp_localize_script('srp-admin-js', 'srpAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('srp_admin_nonce')
        ]);
    }

    /**
     * Handle admin AJAX
     */
    public static function handle_ajax() {
        // Check if this is actually an AJAX request
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            wp_send_json_error(['message' => 'Invalid request']);
            wp_die();
        }

        // Log for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SRP Admin AJAX POST: ' . print_r($_POST, true));
        }

        // Verify nonce - check both 'nonce' and 'srp_nonce' field names
        $nonce = '';
        if (isset($_POST['nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));
        } elseif (isset($_POST['srp_nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_POST['srp_nonce']));
        }

        if (empty($nonce) || !wp_verify_nonce($nonce, 'srp_admin_nonce')) {
            wp_send_json_error(['message' => 'Security check failed. Please refresh the page.']);
            wp_die();
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'You do not have permission to perform this action.']);
            wp_die();
        }

        $action = isset($_POST['srp_action']) ? sanitize_text_field(wp_unslash($_POST['srp_action'])) : '';

        if (empty($action)) {
            wp_send_json_error(['message' => 'No action specified']);
            wp_die();
        }

        switch ($action) {
            case 'save_category':
                self::ajax_save_category();
                break;
            case 'delete_category':
                self::ajax_delete_category();
                break;
            case 'get_category':
                self::ajax_get_category();
                break;
            case 'process_withdrawal':
                self::ajax_process_withdrawal();
                break;
            case 'get_stats':
                self::ajax_get_stats();
                break;
            case 'export_data':
                self::ajax_export_data();
                break;
            default:
                wp_send_json_error(['message' => 'Unknown action: ' . $action]);
                wp_die();
        }
    }

    /**
     * Save category
     */
    private static function ajax_save_category() {
        global $wpdb;

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $slug = sanitize_title($name);
        $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
        $referrer_percentage = isset($_POST['referrer_percentage']) ? floatval($_POST['referrer_percentage']) : 10;
        $referee_discount = isset($_POST['referee_discount']) ? floatval($_POST['referee_discount']) : 5;
        $min_purchase = isset($_POST['min_purchase']) ? floatval($_POST['min_purchase']) : 0;
        $max_earnings = (!empty($_POST['max_earnings']) && floatval($_POST['max_earnings']) > 0) ? floatval($_POST['max_earnings']) : null;
        $withdrawal_method = isset($_POST['withdrawal_method']) ? sanitize_text_field(wp_unslash($_POST['withdrawal_method'])) : 'both';
        $min_withdrawal = isset($_POST['min_withdrawal']) ? floatval($_POST['min_withdrawal']) : 10;
        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'active';

        if (empty($name)) {
            wp_send_json_error(['message' => 'Category name is required']);
            wp_die();
        }

        $data = [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'referrer_percentage' => $referrer_percentage,
            'referee_discount' => $referee_discount,
            'min_purchase_amount' => $min_purchase,
            'withdrawal_method' => $withdrawal_method,
            'min_withdrawal' => $min_withdrawal,
            'status' => $status
        ];

        if ($max_earnings !== null) {
            $data['max_earnings'] = $max_earnings;
        }

        if ($id) {
            $result = $wpdb->update($wpdb->prefix . 'srp_categories', $data, ['id' => $id]);
        } else {
            $result = $wpdb->insert($wpdb->prefix . 'srp_categories', $data);
            $id = $wpdb->insert_id;
        }

        wp_send_json_success(['id' => $id, 'message' => 'Category saved successfully']);
        wp_die();
    }

    /**
     * Delete category
     */
    private static function ajax_delete_category() {
        global $wpdb;

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        if (!$id) {
            wp_send_json_error(['message' => 'Invalid category ID']);
            wp_die();
        }

        // Check if category is in use
        $in_use = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}srp_referral_codes WHERE category_id = %d",
            $id
        ));

        if ($in_use > 0) {
            wp_send_json_error(['message' => 'Cannot delete category that is in use. Please reassign users first.']);
            wp_die();
        }

        $wpdb->delete($wpdb->prefix . 'srp_categories', ['id' => $id]);
        wp_send_json_success(['message' => 'Category deleted']);
        wp_die();
    }

    /**
     * Get category data
     */
    private static function ajax_get_category() {
        global $wpdb;

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $category = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}srp_categories WHERE id = %d",
            $id
        ));

        if (!$category) {
            wp_send_json_error(['message' => 'Category not found']);
            wp_die();
        }

        wp_send_json_success($category);
        wp_die();
    }

    /**
     * Process withdrawal
     */
    private static function ajax_process_withdrawal() {
        $withdrawal_id = isset($_POST['withdrawal_id']) ? intval($_POST['withdrawal_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
        $notes = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '';

        if (!in_array($status, ['completed', 'rejected'])) {
            wp_send_json_error(['message' => 'Invalid status']);
            wp_die();
        }

        $result = Wallet::process_withdrawal($withdrawal_id, $status, $notes);

        if ($result) {
            wp_send_json_success(['message' => 'Withdrawal ' . $status]);
        } else {
            wp_send_json_error(['message' => 'Failed to process withdrawal']);
        }
        wp_die();
    }

    /**
     * Get dashboard stats
     */
    private static function ajax_get_stats() {
        global $wpdb;

        $period = sanitize_text_field(wp_unslash($_POST['period'] ?? '30_days'));

        $date_condition = '';
        switch ($period) {
            case '7_days':
                $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case '30_days':
                $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case '90_days':
                $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
                break;
            case 'year':
                $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                break;
        }

        $stats = [
            'total_referrals' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}srp_referrals WHERE 1=1 $date_condition"),
            'completed_referrals' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}srp_referrals WHERE status = 'completed' $date_condition"),
            'total_earnings' => $wpdb->get_var("SELECT COALESCE(SUM(referrer_earnings), 0) FROM {$wpdb->prefix}srp_referrals WHERE status = 'completed' $date_condition"),
            'total_withdrawals' => $wpdb->get_var("SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}srp_withdrawals WHERE status = 'completed' $date_condition"),
            'pending_withdrawals' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}srp_withdrawals WHERE status = 'pending'"),
            'active_referrers' => $wpdb->get_var("SELECT COUNT(DISTINCT referrer_id) FROM {$wpdb->prefix}srp_referrals WHERE status = 'completed' $date_condition"),
            'conversion_rate' => 0
        ];

        $total_visits = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}srp_visits WHERE 1=1 $date_condition");
        if ($total_visits > 0) {
            $stats['conversion_rate'] = round(($stats['completed_referrals'] / $total_visits) * 100, 2);
        }

        wp_send_json_success($stats);
    }

    /**
     * Export data
     */
    private static function ajax_export_data() {
        $type = sanitize_text_field(wp_unslash($_POST['export_type'] ?? 'referrals'));

        global $wpdb;

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="srp-export-' . $type . '-' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        switch ($type) {
            case 'referrals':
                fputcsv($output, ['ID', 'Referrer', 'Referee', 'Order ID', 'Order Total', 'Earnings', 'Discount', 'Status', 'Date']);
                $results = $wpdb->get_results("SELECT r.*, u1.display_name as referrer_name, u2.display_name as referee_name 
                    FROM {$wpdb->prefix}srp_referrals r
                    LEFT JOIN {$wpdb->users} u1 ON r.referrer_id = u1.ID
                    LEFT JOIN {$wpdb->users} u2 ON r.referee_id = u2.ID");
                foreach ($results as $row) {
                    fputcsv($output, [
                        $row->id,
                        $row->referrer_name,
                        $row->referee_name,
                        $row->order_id,
                        $row->order_total,
                        $row->referrer_earnings,
                        $row->referee_discount,
                        $row->status,
                        $row->created_at
                    ]);
                }
                break;

            case 'withdrawals':
                fputcsv($output, ['ID', 'User', 'Amount', 'Method', 'Status', 'Requested', 'Processed']);
                $results = $wpdb->get_results("SELECT w.*, u.display_name FROM {$wpdb->prefix}srp_withdrawals w
                    LEFT JOIN {$wpdb->users} u ON w.user_id = u.ID");
                foreach ($results as $row) {
                    fputcsv($output, [
                        $row->id,
                        $row->display_name,
                        $row->amount,
                        $row->method,
                        $row->status,
                        $row->requested_at,
                        $row->processed_at
                    ]);
                }
                break;
        }

        fclose($output);
        exit;
    }

    /**
     * Render Dashboard
     */
    public static function render_dashboard() {
        global $wpdb;

        // Get overview stats
        $total_users = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}srp_referral_codes");
        $total_referrals = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}srp_referrals");
        $total_earnings = $wpdb->get_var("SELECT COALESCE(SUM(referrer_earnings), 0) FROM {$wpdb->prefix}srp_referrals WHERE status = 'completed'");
        $pending_withdrawals = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}srp_withdrawals WHERE status = 'pending'");

        // Recent referrals
        $recent_referrals = $wpdb->get_results(
            "SELECT r.*, u1.display_name as referrer_name, u2.display_name as referee_name
             FROM {$wpdb->prefix}srp_referrals r
             LEFT JOIN {$wpdb->users} u1 ON r.referrer_id = u1.ID
             LEFT JOIN {$wpdb->users} u2 ON r.referee_id = u2.ID
             ORDER BY r.created_at DESC LIMIT 10"
        );

        // Top referrers
        $top_referrers = $wpdb->get_results(
            "SELECT rc.user_id, rc.code, rc.total_referrals, rc.total_earnings, u.display_name
             FROM {$wpdb->prefix}srp_referral_codes rc
             LEFT JOIN {$wpdb->users} u ON rc.user_id = u.ID
             ORDER BY rc.total_earnings DESC LIMIT 10"
        );

        include SRP_PLUGIN_DIR . 'admin/dashboard.php';
    }

    /**
     * Render Categories
     */
    public static function render_categories() {
        global $wpdb;
        $categories = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}srp_categories ORDER BY created_at DESC");
        include SRP_PLUGIN_DIR . 'admin/categories.php';
    }

    /**
     * Render Accounts
     */
    public static function render_accounts() {
        global $wpdb;

        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

        $where = '';
        if ($search) {
            $where = $wpdb->prepare(" WHERE u.display_name LIKE %s OR u.user_email LIKE %s OR rc.code LIKE %s",
                '%' . Security::esc_like($search) . '%',
                '%' . Security::esc_like($search) . '%',
                '%' . Security::esc_like($search) . '%'
            );
        }

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}srp_referral_codes rc 
            LEFT JOIN {$wpdb->users} u ON rc.user_id = u.ID $where");

        $accounts = $wpdb->get_results("SELECT rc.*, u.display_name, u.user_email, u.user_registered,
            (SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}srp_wallet WHERE user_id = rc.user_id AND status = 'approved') as balance
            FROM {$wpdb->prefix}srp_referral_codes rc
            LEFT JOIN {$wpdb->users} u ON rc.user_id = u.ID
            $where
            ORDER BY rc.total_earnings DESC
            LIMIT $offset, $per_page");

        $total_pages = ceil($total / $per_page);

        include SRP_PLUGIN_DIR . 'admin/accounts.php';
    }

    /**
     * Render Records
     */
    public static function render_records() {
        global $wpdb;

        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        $status_filter = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';

        $where = 'WHERE 1=1';
        if ($status_filter) {
            $where .= $wpdb->prepare(" AND r.status = %s", $status_filter);
        }

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}srp_referrals r $where");

        $records = $wpdb->get_results("SELECT r.*, u1.display_name as referrer_name, u2.display_name as referee_name
            FROM {$wpdb->prefix}srp_referrals r
            LEFT JOIN {$wpdb->users} u1 ON r.referrer_id = u1.ID
            LEFT JOIN {$wpdb->users} u2 ON r.referee_id = u2.ID
            $where
            ORDER BY r.created_at DESC
            LIMIT $offset, $per_page");

        $total_pages = ceil($total / $per_page);

        include SRP_PLUGIN_DIR . 'admin/records.php';
    }

    /**
     * Render Withdrawals
     */
    public static function render_withdrawals() {
        global $wpdb;

        $status_filter = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : 'pending';

        $withdrawals = $wpdb->get_results($wpdb->prepare(
            "SELECT w.*, u.display_name, u.user_email
             FROM {$wpdb->prefix}srp_withdrawals w
             LEFT JOIN {$wpdb->users} u ON w.user_id = u.ID
             WHERE w.status = %s
             ORDER BY w.requested_at DESC",
            $status_filter
        ));

        include SRP_PLUGIN_DIR . 'admin/withdrawals.php';
    }

    /**
     * Render Settings
     */
    public static function render_settings() {
        if (isset($_POST['srp_save_settings'])) {
            check_admin_referer('srp_settings');

            update_option('srp_enable_notifications', isset($_POST['enable_notifications']) ? 'yes' : 'no');
            update_option('srp_notification_email', sanitize_email(wp_unslash($_POST['notification_email'] ?? '')));
            update_option('srp_cookie_duration', intval($_POST['cookie_duration'] ?? 30));
            update_option('srp_minimum_order', Security::sanitize_amount($_POST['minimum_order'] ?? 0));
            update_option('srp_auto_approve_withdrawals', isset($_POST['auto_approve_withdrawals']) ? 'yes' : 'no');

            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }

        $settings = [
            'enable_notifications' => get_option('srp_enable_notifications', 'yes'),
            'notification_email' => get_option('srp_notification_email', get_option('admin_email')),
            'cookie_duration' => get_option('srp_cookie_duration', 30),
            'minimum_order' => get_option('srp_minimum_order', 0),
            'auto_approve_withdrawals' => get_option('srp_auto_approve_withdrawals', 'no')
        ];

        include SRP_PLUGIN_DIR . 'admin/settings.php';
    }
}

        add_submenu_page(
            'srp-dashboard',
            __('All Accounts', 'smart-referral-pro'),
            __('All Accounts', 'smart-referral-pro'),
            'manage_options',
            'srp-accounts',
            [self::class, 'render_accounts']
        );

        add_submenu_page(
            'srp-dashboard',
            __('Referral Records', 'smart-referral-pro'),
            __('Referral Records', 'smart-referral-pro'),
            'manage_options',
            'srp-records',
            [self::class, 'render_records']
        );

        add_submenu_page(
            'srp-dashboard',
            __('Withdrawals', 'smart-referral-pro'),
            __('Withdrawals', 'smart-referral-pro'),
            'manage_options',
            'srp-withdrawals',
            [self::class, 'render_withdrawals']
        );

        add_submenu_page(
            'srp-dashboard',
            __('Settings', 'smart-referral-pro'),
            __('Settings', 'smart-referral-pro'),
            'manage_options',
            'srp-settings',
            [self::class, 'render_settings']
        );
    }

    /**
     * Enqueue admin assets
     */
    public static function enqueue_assets($hook) {
        if (strpos($hook, 'srp-') === false) {
            return;
        }

        wp_enqueue_style('srp-admin-css', SRP_PLUGIN_URL . 'assets/css/admin.css', [], SRP_VERSION);
        wp_enqueue_script('srp-admin-js', SRP_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], SRP_VERSION, true);
        wp_localize_script('srp-admin-js', 'srpAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('srp_admin_nonce')
        ]);
    }

    /**
     * Handle admin AJAX
     */
    public static function handle_ajax() {
        Security::verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'srp_admin_nonce');
        Security::check_capability('manage_options');

        $action = sanitize_text_field(wp_unslash($_POST['srp_action'] ?? ''));

        switch ($action) {
            case 'save_category':
                self::ajax_save_category();
                break;
            case 'delete_category':
                self::ajax_delete_category();
                break;
            case 'get_category':
                self::ajax_get_category();
                break;
            case 'process_withdrawal':
                self::ajax_process_withdrawal();
                break;
            case 'get_stats':
                self::ajax_get_stats();
                break;
            case 'export_data':
                self::ajax_export_data();
                break;
            default:
                wp_send_json_error(['message' => 'Unknown action']);
        }
    }

    /**
     * Save category
     */
    private static function ajax_save_category() {
        global $wpdb;

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $slug = sanitize_title($name);
        $description = sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''));
        $referrer_percentage = Security::sanitize_amount($_POST['referrer_percentage'] ?? 10);
        $referee_discount = Security::sanitize_amount($_POST['referee_discount'] ?? 5);
        $min_purchase = Security::sanitize_amount($_POST['min_purchase'] ?? 0);
        $max_earnings = !empty($_POST['max_earnings']) ? Security::sanitize_amount($_POST['max_earnings']) : null;
        $withdrawal_method = sanitize_text_field(wp_unslash($_POST['withdrawal_method'] ?? 'both'));
        $min_withdrawal = Security::sanitize_amount($_POST['min_withdrawal'] ?? 10);
        $status = sanitize_text_field(wp_unslash($_POST['status'] ?? 'active'));

        if (empty($name)) {
            wp_send_json_error(['message' => 'Category name is required']);
            return;
        }

        $data = [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'referrer_percentage' => $referrer_percentage,
            'referee_discount' => $referee_discount,
            'min_purchase_amount' => $min_purchase,
            'withdrawal_method' => $withdrawal_method,
            'min_withdrawal' => $min_withdrawal,
            'status' => $status
        ];

        if ($max_earnings !== null) {
            $data['max_earnings'] = $max_earnings;
        }

        if ($id) {
            $wpdb->update($wpdb->prefix . 'srp_categories', $data, ['id' => $id]);
        } else {
            $wpdb->insert($wpdb->prefix . 'srp_categories', $data);
            $id = $wpdb->insert_id;
        }

        wp_send_json_success(['id' => $id, 'message' => 'Category saved successfully']);
    }

    /**
     * Delete category
     */
    private static function ajax_delete_category() {
        global $wpdb;

        $id = intval($_POST['id'] ?? 0);

        if (!$id) {
            wp_send_json_error(['message' => 'Invalid category ID']);
            return;
        }

        // Check if category is in use
        $in_use = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}srp_referral_codes WHERE category_id = %d",
            $id
        ));

        if ($in_use > 0) {
            wp_send_json_error(['message' => 'Cannot delete category that is in use. Please reassign users first.']);
            return;
        }

        $wpdb->delete($wpdb->prefix . 'srp_categories', ['id' => $id]);
        wp_send_json_success(['message' => 'Category deleted']);
    }

    /**
     * Get category data
     */
    private static function ajax_get_category() {
        global $wpdb;

        $id = intval($_POST['id'] ?? 0);
        $category = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}srp_categories WHERE id = %d",
            $id
        ));

        if (!$category) {
            wp_send_json_error(['message' => 'Category not found']);
            return;
        }

        wp_send_json_success($category);
    }

    /**
     * Process withdrawal
     */
    private static function ajax_process_withdrawal() {
        $withdrawal_id = intval($_POST['withdrawal_id'] ?? 0);
        $status = sanitize_text_field(wp_unslash($_POST['status'] ?? ''));
        $notes = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));

        if (!in_array($status, ['completed', 'rejected'])) {
            wp_send_json_error(['message' => 'Invalid status']);
            return;
        }

        $result = Wallet::process_withdrawal($withdrawal_id, $status, $notes);

        if ($result) {
            wp_send_json_success(['message' => 'Withdrawal ' . $status]);
        } else {
            wp_send_json_error(['message' => 'Failed to process withdrawal']);
        }
    }

    /**
     * Get dashboard stats
     */
    private static function ajax_get_stats() {
        global $wpdb;

        $period = sanitize_text_field(wp_unslash($_POST['period'] ?? '30_days'));

        $date_condition = '';
        switch ($period) {
            case '7_days':
                $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case '30_days':
                $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case '90_days':
                $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
                break;
            case 'year':
                $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                break;
        }

        $stats = [
            'total_referrals' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}srp_referrals WHERE 1=1 $date_condition"),
            'completed_referrals' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}srp_referrals WHERE status = 'completed' $date_condition"),
            'total_earnings' => $wpdb->get_var("SELECT COALESCE(SUM(referrer_earnings), 0) FROM {$wpdb->prefix}srp_referrals WHERE status = 'completed' $date_condition"),
            'total_withdrawals' => $wpdb->get_var("SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}srp_withdrawals WHERE status = 'completed' $date_condition"),
            'pending_withdrawals' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}srp_withdrawals WHERE status = 'pending'"),
            'active_referrers' => $wpdb->get_var("SELECT COUNT(DISTINCT referrer_id) FROM {$wpdb->prefix}srp_referrals WHERE status = 'completed' $date_condition"),
            'conversion_rate' => 0
        ];

        $total_visits = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}srp_visits WHERE 1=1 $date_condition");
        if ($total_visits > 0) {
            $stats['conversion_rate'] = round(($stats['completed_referrals'] / $total_visits) * 100, 2);
        }

        wp_send_json_success($stats);
    }

    /**
     * Export data
     */
    private static function ajax_export_data() {
        $type = sanitize_text_field(wp_unslash($_POST['export_type'] ?? 'referrals'));

        global $wpdb;

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="srp-export-' . $type . '-' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        switch ($type) {
            case 'referrals':
                fputcsv($output, ['ID', 'Referrer', 'Referee', 'Order ID', 'Order Total', 'Earnings', 'Discount', 'Status', 'Date']);
                $results = $wpdb->get_results("SELECT r.*, u1.display_name as referrer_name, u2.display_name as referee_name 
                    FROM {$wpdb->prefix}srp_referrals r
                    LEFT JOIN {$wpdb->users} u1 ON r.referrer_id = u1.ID
                    LEFT JOIN {$wpdb->users} u2 ON r.referee_id = u2.ID");
                foreach ($results as $row) {
                    fputcsv($output, [
                        $row->id,
                        $row->referrer_name,
                        $row->referee_name,
                        $row->order_id,
                        $row->order_total,
                        $row->referrer_earnings,
                        $row->referee_discount,
                        $row->status,
                        $row->created_at
                    ]);
                }
                break;

            case 'withdrawals':
                fputcsv($output, ['ID', 'User', 'Amount', 'Method', 'Status', 'Requested', 'Processed']);
                $results = $wpdb->get_results("SELECT w.*, u.display_name FROM {$wpdb->prefix}srp_withdrawals w
                    LEFT JOIN {$wpdb->users} u ON w.user_id = u.ID");
                foreach ($results as $row) {
                    fputcsv($output, [
                        $row->id,
                        $row->display_name,
                        $row->amount,
                        $row->method,
                        $row->status,
                        $row->requested_at,
                        $row->processed_at
                    ]);
                }
                break;
        }

        fclose($output);
        exit;
    }

    /**
     * Render Dashboard
     */
    public static function render_dashboard() {
        global $wpdb;

        // Get overview stats
        $total_users = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}srp_referral_codes");
        $total_referrals = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}srp_referrals");
        $total_earnings = $wpdb->get_var("SELECT COALESCE(SUM(referrer_earnings), 0) FROM {$wpdb->prefix}srp_referrals WHERE status = 'completed'");
        $pending_withdrawals = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}srp_withdrawals WHERE status = 'pending'");

        // Recent referrals
        $recent_referrals = $wpdb->get_results(
            "SELECT r.*, u1.display_name as referrer_name, u2.display_name as referee_name
             FROM {$wpdb->prefix}srp_referrals r
             LEFT JOIN {$wpdb->users} u1 ON r.referrer_id = u1.ID
             LEFT JOIN {$wpdb->users} u2 ON r.referee_id = u2.ID
             ORDER BY r.created_at DESC LIMIT 10"
        );

        // Top referrers
        $top_referrers = $wpdb->get_results(
            "SELECT rc.user_id, rc.code, rc.total_referrals, rc.total_earnings, u.display_name
             FROM {$wpdb->prefix}srp_referral_codes rc
             LEFT JOIN {$wpdb->users} u ON rc.user_id = u.ID
             ORDER BY rc.total_earnings DESC LIMIT 10"
        );

        include SRP_PLUGIN_DIR . 'admin/dashboard.php';
    }

    /**
     * Render Categories
     */
    public static function render_categories() {
        global $wpdb;
        $categories = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}srp_categories ORDER BY created_at DESC");
        include SRP_PLUGIN_DIR . 'admin/categories.php';
    }

    /**
     * Render Accounts
     */
    public static function render_accounts() {
        global $wpdb;

        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

        $where = '';
        if ($search) {
            $where = $wpdb->prepare(" WHERE u.display_name LIKE %s OR u.user_email LIKE %s OR rc.code LIKE %s",
                '%' . Security::esc_like($search) . '%',
                '%' . Security::esc_like($search) . '%',
                '%' . Security::esc_like($search) . '%'
            );
        }

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}srp_referral_codes rc 
            LEFT JOIN {$wpdb->users} u ON rc.user_id = u.ID $where");

        $accounts = $wpdb->get_results("SELECT rc.*, u.display_name, u.user_email, u.user_registered,
            (SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}srp_wallet WHERE user_id = rc.user_id AND status = 'approved') as balance
            FROM {$wpdb->prefix}srp_referral_codes rc
            LEFT JOIN {$wpdb->users} u ON rc.user_id = u.ID
            $where
            ORDER BY rc.total_earnings DESC
            LIMIT $offset, $per_page");

        $total_pages = ceil($total / $per_page);

        include SRP_PLUGIN_DIR . 'admin/accounts.php';
    }

    /**
     * Render Records
     */
    public static function render_records() {
        global $wpdb;

        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        $status_filter = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';

        $where = 'WHERE 1=1';
        if ($status_filter) {
            $where .= $wpdb->prepare(" AND r.status = %s", $status_filter);
        }

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}srp_referrals r $where");

        $records = $wpdb->get_results("SELECT r.*, u1.display_name as referrer_name, u2.display_name as referee_name
            FROM {$wpdb->prefix}srp_referrals r
            LEFT JOIN {$wpdb->users} u1 ON r.referrer_id = u1.ID
            LEFT JOIN {$wpdb->users} u2 ON r.referee_id = u2.ID
            $where
            ORDER BY r.created_at DESC
            LIMIT $offset, $per_page");

        $total_pages = ceil($total / $per_page);

        include SRP_PLUGIN_DIR . 'admin/records.php';
    }

    /**
     * Render Withdrawals
     */
    public static function render_withdrawals() {
        global $wpdb;

        $status_filter = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : 'pending';

        $withdrawals = $wpdb->get_results($wpdb->prepare(
            "SELECT w.*, u.display_name, u.user_email
             FROM {$wpdb->prefix}srp_withdrawals w
             LEFT JOIN {$wpdb->users} u ON w.user_id = u.ID
             WHERE w.status = %s
             ORDER BY w.requested_at DESC",
            $status_filter
        ));

        include SRP_PLUGIN_DIR . 'admin/withdrawals.php';
    }

    /**
     * Render Settings
     */
    public static function render_settings() {
        if (isset($_POST['srp_save_settings'])) {
            check_admin_referer('srp_settings');

            update_option('srp_enable_notifications', isset($_POST['enable_notifications']) ? 'yes' : 'no');
            update_option('srp_notification_email', sanitize_email(wp_unslash($_POST['notification_email'] ?? '')));
            update_option('srp_cookie_duration', intval($_POST['cookie_duration'] ?? 30));
            update_option('srp_minimum_order', Security::sanitize_amount($_POST['minimum_order'] ?? 0));
            update_option('srp_auto_approve_withdrawals', isset($_POST['auto_approve_withdrawals']) ? 'yes' : 'no');

            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }

        $settings = [
            'enable_notifications' => get_option('srp_enable_notifications', 'yes'),
            'notification_email' => get_option('srp_notification_email', get_option('admin_email')),
            'cookie_duration' => get_option('srp_cookie_duration', 30),
            'minimum_order' => get_option('srp_minimum_order', 0),
            'auto_approve_withdrawals' => get_option('srp_auto_approve_withdrawals', 'no')
        ];

        include SRP_PLUGIN_DIR . 'admin/settings.php';
    }
}
