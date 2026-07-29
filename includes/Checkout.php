<?php
namespace SRP;

class Checkout {

    private static $applied_referral = null;
    private static $referral_discount = 0;

    /**
     * Render referral input field on checkout
     */
    public static function render_referral_field() {
        // Check if user is logged in and hasn't been referred before
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();

        // Check if user has already been referred (one-time rule)
        if (ReferralCode::has_been_referred($user_id)) {
            return;
        }

        // Check for active referral from cookie
        $active_referral = ReferralCode::get_active_referral();
        $code_value = '';

        if ($active_referral) {
            $code_value = esc_attr($active_referral->code);
        }

        // Check if referral is already applied
        $applied_code = WC()->session->get('srp_applied_referral');

        ?>
        <div class="srp-referral-section woocommerce-info">
            <h4><?php _e('Have a referral code?', 'smart-referral-pro'); ?></h4>
            <div class="srp-referral-input-wrapper" style="display: <?php echo $applied_code ? 'none' : 'flex'; ?>; gap: 10px; align-items: center; margin-top: 10px;">
                <input type="text" 
                       id="srp_referral_code" 
                       class="input-text" 
                       placeholder="<?php _e('Enter referral code', 'smart-referral-pro'); ?>"
                       value="<?php echo $code_value; ?>"
                       style="text-transform: uppercase;"
                       maxlength="50">
                <button type="button" class="button" id="srp_apply_referral">
                    <?php _e('Apply', 'smart-referral-pro'); ?>
                </button>
            </div>
            <div id="srp_referral_message" style="margin-top: 10px;"></div>
            <?php if ($applied_code): ?>
            <div class="srp-referral-applied" style="margin-top: 10px;">
                <strong><?php printf(__('Referral code %s applied! You will receive a discount.', 'smart-referral-pro'), '<code>' . esc_html($applied_code) . '</code>'); ?></strong>
                <button type="button" class="button button-small" id="srp_remove_referral" style="margin-left: 10px;">
                    <?php _e('Remove', 'smart-referral-pro'); ?>
                </button>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * AJAX: Apply referral code
     */
    public static function ajax_apply_referral() {
        check_ajax_referer('srp_public_nonce', 'nonce');

        $code = isset($_POST['code']) ? Security::sanitize_code(sanitize_text_field(wp_unslash($_POST['code']))) : '';
        $remove = isset($_POST['remove']) ? true : false;

        if ($remove) {
            WC()->session->__unset('srp_applied_referral');
            WC()->session->__unset('srp_referral_discount');
            WC()->session->__unset('srp_referral_data');

            wp_send_json_success([
                'message' => __('Referral code removed.', 'smart-referral-pro'),
                'refresh' => true
            ]);
            return;
        }

        if (empty($code)) {
            wp_send_json_error(['message' => __('Please enter a referral code.', 'smart-referral-pro')]);
            return;
        }

        // Validate code
        $referral = ReferralCode::get_by_code($code);

        if (!$referral) {
            wp_send_json_error(['message' => __('Invalid referral code.', 'smart-referral-pro')]);
            return;
        }

        // Prevent self-referral
        if (is_user_logged_in() && get_current_user_id() == $referral->user_id) {
            wp_send_json_error(['message' => __('You cannot use your own referral code.', 'smart-referral-pro')]);
            return;
        }

        // Check if user has already been referred
        if (is_user_logged_in() && ReferralCode::has_been_referred(get_current_user_id())) {
            wp_send_json_error(['message' => __('You have already been referred by someone else.', 'smart-referral-pro')]);
            return;
        }

        // Check minimum purchase
        $cart_total = WC()->cart->get_subtotal();
        if ($cart_total < $referral->min_purchase_amount) {
            wp_send_json_error([
                'message' => sprintf(
                    __('Minimum purchase of %s required for this referral code.', 'smart-referral-pro'),
                    wc_price($referral->min_purchase_amount)
                )
            ]);
            return;
        }

        // Calculate discount
        $discount = round($cart_total * ($referral->referee_discount / 100), 2);

        // Store in session
        WC()->session->set('srp_applied_referral', $code);
        WC()->session->set('srp_referral_discount', $discount);
        WC()->session->set('srp_referral_data', $referral);

        wp_send_json_success([
            'message' => sprintf(
                __('Referral code applied! You will receive %s discount.', 'smart-referral-pro'),
                wc_price($discount)
            ),
            'discount' => $discount,
            'refresh' => true
        ]);
    }

    /**
     * Apply referral discount to cart
     */
    public static function apply_referral_discount($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        if (!WC()->session) {
            return;
        }

        $discount = WC()->session->get('srp_referral_discount');
        $code = WC()->session->get('srp_applied_referral');

        if (!$discount || !$code) {
            return;
        }

        $cart->add_fee(
            sprintf(__('Referral Discount (%s)', 'smart-referral-pro'), esc_html($code)),
            -$discount,
            false
        );
    }

    /**
     * Process referral when order is created
     */
    public static function process_referral($order_id, $posted_data, $order) {
        if (!WC()->session) {
            return;
        }

        $code = WC()->session->get('srp_applied_referral');
        $referral_data = WC()->session->get('srp_referral_data');

        if (!$code || !$referral_data) {
            return;
        }

        $user_id = get_current_user_id();

        // Double-check: user hasn't been referred before
        if (ReferralCode::has_been_referred($user_id)) {
            return;
        }

        // Check if this is user's first purchase
        $previous_orders = wc_get_orders([
            'customer_id' => $user_id,
            'status' => ['completed', 'processing'],
            'limit' => 1,
            'exclude' => [$order_id]
        ]);

        if (!empty($previous_orders)) {
            // Not first purchase, clear session but don't process
            self::clear_session();
            return;
        }

        global $wpdb;

        $order_total = $order->get_total();
        $referrer_earnings = round($order_total * ($referral_data->referrer_percentage / 100), 2);
        $referee_discount = WC()->session->get('srp_referral_discount');

        // Create referral record
        $wpdb->insert(
            $wpdb->prefix . 'srp_referrals',
            [
                'referrer_id' => $referral_data->user_id,
                'referee_id' => $user_id,
                'order_id' => $order_id,
                'category_id' => $referral_data->category_id,
                'order_total' => $order_total,
                'referrer_earnings' => $referrer_earnings,
                'referee_discount' => $referee_discount,
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ]
        );

        $referral_id = $wpdb->insert_id;

        // Add to referrer's wallet (pending until order completion)
        Wallet::add_earning(
            $referral_data->user_id,
            $referrer_earnings,
            $referral_id,
            sprintf('Referral earnings from order #%d', $order_id)
        );

        // Update referral code stats
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}srp_referral_codes 
             SET total_referrals = total_referrals + 1,
                 total_earnings = total_earnings + %f
             WHERE id = %d",
            $referrer_earnings,
            $referral_data->id
        ));

        // Mark visit as converted
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}srp_visits 
             SET converted = 1, converted_user_id = %d 
             WHERE referral_code = %s AND converted = 0 
             ORDER BY visited_at DESC LIMIT 1",
            $user_id,
            $code
        ));

        // Store referral info in order meta
        $order->update_meta_data('_srp_referral_id', $referral_id);
        $order->update_meta_data('_srp_referral_code', $code);
        $order->update_meta_data('_srp_referrer_id', $referral_data->user_id);
        $order->save();

        // Clear session
        self::clear_session();

        // Clear cookie
        ReferralCode::clear_referral_cookie();
    }

    /**
     * Complete referral when order is completed
     */
    public static function complete_referral($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $referral_id = $order->get_meta('_srp_referral_id');
        if (!$referral_id) {
            return;
        }

        global $wpdb;

        // Update referral status
        $wpdb->update(
            $wpdb->prefix . 'srp_referrals',
            [
                'status' => 'completed',
                'completed_at' => current_time('mysql')
            ],
            ['id' => $referral_id]
        );

        // Approve wallet entry
        Wallet::approve_earning($referral_id);

        // Trigger notification
        $referral = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}srp_referrals WHERE id = %d",
            $referral_id
        ));

        if ($referral) {
            do_action('srp_referral_completed', $referral->referrer_id, $referral);
        }
    }

    /**
     * Cancel referral when order is cancelled
     */
    public static function cancel_referral($order_id) {
        self::update_referral_status($order_id, 'cancelled');
    }

    /**
     * Refund referral when order is refunded
     */
    public static function refund_referral($order_id) {
        self::update_referral_status($order_id, 'refunded');
    }

    /**
     * Update referral status helper
     */
    private static function update_referral_status($order_id, $status) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $referral_id = $order->get_meta('_srp_referral_id');
        if (!$referral_id) {
            return;
        }

        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'srp_referrals',
            ['status' => $status],
            ['id' => $referral_id]
        );

        // Reverse wallet entry if it was approved
        $wallet = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}srp_wallet WHERE referral_id = %d",
            $referral_id
        ));

        if ($wallet && $wallet->status === 'approved') {
            // Create reversal entry
            $wpdb->insert(
                $wpdb->prefix . 'srp_wallet',
                [
                    'user_id' => $wallet->user_id,
                    'referral_id' => $referral_id,
                    'amount' => -$wallet->amount,
                    'type' => 'adjustment',
                    'status' => 'approved',
                    'description' => "Reversed due to order $status (Order #$order_id)",
                    'created_at' => current_time('mysql'),
                    'processed_at' => current_time('mysql')
                ]
            );
        } else {
            // Just reject the pending entry
            $wpdb->update(
                $wpdb->prefix . 'srp_wallet',
                ['status' => 'rejected'],
                ['referral_id' => $referral_id, 'type' => 'earning']
            );
        }
    }

    /**
     * Clear session data
     */
    private static function clear_session() {
        WC()->session->__unset('srp_applied_referral');
        WC()->session->__unset('srp_referral_discount');
        WC()->session->__unset('srp_referral_data');
    }
}
