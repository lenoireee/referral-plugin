<?php
/**
 * Admin Settings View
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap srp-admin">
    <h1><?php _e('Referral Settings', 'smart-referral-pro'); ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field('srp_settings'); ?>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="enable_notifications"><?php _e('Enable Email Notifications', 'smart-referral-pro'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="enable_notifications" id="enable_notifications" value="1" <?php checked($settings['enable_notifications'] ?? 'yes', 'yes'); ?>>
                        <?php _e('Send email notifications for referral completions and withdrawals', 'smart-referral-pro'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="notification_email"><?php _e('Notification Email', 'smart-referral-pro'); ?></label></th>
                <td>
                    <input type="email" name="notification_email" id="notification_email" class="regular-text" value="<?php echo esc_attr($settings['notification_email'] ?? get_option('admin_email')); ?>">
                    <p class="description"><?php _e('Email address to receive admin notifications', 'smart-referral-pro'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="cookie_duration"><?php _e('Referral Cookie Duration', 'smart-referral-pro'); ?></label></th>
                <td>
                    <input type="number" name="cookie_duration" id="cookie_duration" class="small-text" value="<?php echo esc_attr($settings['cookie_duration'] ?? 30); ?>" min="1" max="365">
                    <?php _e('days', 'smart-referral-pro'); ?>
                    <p class="description"><?php _e('How long referral cookies last before expiring', 'smart-referral-pro'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="minimum_order"><?php _e('Minimum Order Amount', 'smart-referral-pro'); ?></label></th>
                <td>
                    <input type="number" name="minimum_order" id="minimum_order" class="small-text" value="<?php echo esc_attr($settings['minimum_order'] ?? 0); ?>" step="0.01" min="0">
                    <p class="description"><?php _e('Minimum order total required for referral to count (0 = no minimum)', 'smart-referral-pro'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="auto_approve_withdrawals"><?php _e('Auto-Approve Withdrawals', 'smart-referral-pro'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="auto_approve_withdrawals" id="auto_approve_withdrawals" value="1" <?php checked($settings['auto_approve_withdrawals'] ?? 'no', 'yes'); ?>>
                        <?php _e('Automatically approve withdrawal requests (use with caution)', 'smart-referral-pro'); ?>
                    </label>
                </td>
            </tr>
        </table>

        <?php submit_button('Save Settings'); ?>
    </form>
</div>
