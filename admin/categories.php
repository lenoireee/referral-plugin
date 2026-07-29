<?php
/**
 * Admin Categories View
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap srp-admin">
    <h1><?php _e('Referral Categories', 'smart-referral-pro'); ?></h1>

    <button type="button" class="button button-primary" id="srp-add-category">
        <?php _e('Add New Category', 'smart-referral-pro'); ?>
    </button>

    <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
        <thead>
            <tr>
                <th><?php _e('Name', 'smart-referral-pro'); ?></th>
                <th><?php _e('Referrer %', 'smart-referral-pro'); ?></th>
                <th><?php _e('Referee Discount %', 'smart-referral-pro'); ?></th>
                <th><?php _e('Min Purchase', 'smart-referral-pro'); ?></th>
                <th><?php _e('Withdrawal', 'smart-referral-pro'); ?></th>
                <th><?php _e('Status', 'smart-referral-pro'); ?></th>
                <th><?php _e('Actions', 'smart-referral-pro'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($categories)) : ?>
                <?php foreach ($categories as $cat) : ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($cat->name); ?></strong>
                        <?php if (!empty($cat->description)) : ?>
                            <br><small><?php echo esc_html($cat->description); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($cat->referrer_percentage); ?>%</td>
                    <td><?php echo esc_html($cat->referee_discount); ?>%</td>
                    <td><?php echo wc_price(floatval($cat->min_purchase_amount)); ?></td>
                    <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $cat->withdrawal_method))); ?></td>
                    <td><span class="srp-badge srp-badge-<?php echo esc_attr($cat->status); ?>"><?php echo esc_html(ucfirst($cat->status)); ?></span></td>
                    <td>
                        <button type="button" class="button srp-edit-category" data-id="<?php echo esc_attr($cat->id); ?>"><?php _e('Edit', 'smart-referral-pro'); ?></button>
                        <?php if ($cat->slug !== 'default') : ?>
                        <button type="button" class="button srp-delete-category" data-id="<?php echo esc_attr($cat->id); ?>"><?php _e('Delete', 'smart-referral-pro'); ?></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="7" style="text-align:center;padding:20px;"><?php _e('No categories found.', 'smart-referral-pro'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Modal -->
    <div id="srp-category-modal" class="srp-modal" style="display:none;">
        <div class="srp-modal-content">
            <h2 id="srp-modal-title"><?php _e('Add Category', 'smart-referral-pro'); ?></h2>
            <form id="srp-category-form">
                <input type="hidden" name="id" id="srp-cat-id" value="">
                <?php wp_nonce_field('srp_admin_nonce', 'srp_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="srp-cat-name"><?php _e('Name', 'smart-referral-pro'); ?></label></th>
                        <td><input type="text" name="name" id="srp-cat-name" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="srp-cat-description"><?php _e('Description', 'smart-referral-pro'); ?></label></th>
                        <td><textarea name="description" id="srp-cat-description" class="large-text" rows="3"></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="srp-cat-referrer-percentage"><?php _e('Referrer Percentage', 'smart-referral-pro'); ?></label></th>
                        <td><input type="number" name="referrer_percentage" id="srp-cat-referrer-percentage" step="0.01" min="0" max="100" value="10" required> %</td>
                    </tr>
                    <tr>
                        <th><label for="srp-cat-referee-discount"><?php _e('Referee Discount', 'smart-referral-pro'); ?></label></th>
                        <td><input type="number" name="referee_discount" id="srp-cat-referee-discount" step="0.01" min="0" max="100" value="5" required> %</td>
                    </tr>
                    <tr>
                        <th><label for="srp-cat-min-purchase"><?php _e('Minimum Purchase', 'smart-referral-pro'); ?></label></th>
                        <td><input type="number" name="min_purchase" id="srp-cat-min-purchase" step="0.01" min="0" value="0"></td>
                    </tr>
                    <tr>
                        <th><label for="srp-cat-max-earnings"><?php _e('Max Earnings (optional)', 'smart-referral-pro'); ?></label></th>
                        <td><input type="number" name="max_earnings" id="srp-cat-max-earnings" step="0.01" min="0"></td>
                    </tr>
                    <tr>
                        <th><label for="srp-cat-withdrawal-method"><?php _e('Withdrawal Method', 'smart-referral-pro'); ?></label></th>
                        <td>
                            <select name="withdrawal_method" id="srp-cat-withdrawal-method">
                                <option value="both"><?php _e('Both Coupon & Withdrawal', 'smart-referral-pro'); ?></option>
                                <option value="coupon"><?php _e('Coupon Only', 'smart-referral-pro'); ?></option>
                                <option value="withdrawal"><?php _e('Withdrawal Only', 'smart-referral-pro'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="srp-cat-min-withdrawal"><?php _e('Minimum Withdrawal', 'smart-referral-pro'); ?></label></th>
                        <td><input type="number" name="min_withdrawal" id="srp-cat-min-withdrawal" step="0.01" min="0" value="10"></td>
                    </tr>
                    <tr>
                        <th><label for="srp-cat-status"><?php _e('Status', 'smart-referral-pro'); ?></label></th>
                        <td>
                            <select name="status" id="srp-cat-status">
                                <option value="active"><?php _e('Active', 'smart-referral-pro'); ?></option>
                                <option value="inactive"><?php _e('Inactive', 'smart-referral-pro'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Save Category', 'smart-referral-pro'); ?></button>
                    <button type="button" class="button srp-modal-close"><?php _e('Cancel', 'smart-referral-pro'); ?></button>
                </p>
            </form>
        </div>
    </div>
</div>
