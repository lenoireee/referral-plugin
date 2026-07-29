<?php
/**
 * Admin Dashboard View
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap srp-admin">
    <h1><?php _e('Referral Dashboard', 'smart-referral-pro'); ?></h1>

    <div class="srp-stats-grid">
        <div class="srp-stat-card">
            <h3><?php _e('Total Referrers', 'smart-referral-pro'); ?></h3>
            <div class="srp-stat-number"><?php echo number_format(intval($total_users)); ?></div>
        </div>
        <div class="srp-stat-card">
            <h3><?php _e('Total Referrals', 'smart-referral-pro'); ?></h3>
            <div class="srp-stat-number"><?php echo number_format(intval($total_referrals)); ?></div>
        </div>
        <div class="srp-stat-card">
            <h3><?php _e('Total Earnings', 'smart-referral-pro'); ?></h3>
            <div class="srp-stat-number"><?php echo wc_price(floatval($total_earnings)); ?></div>
        </div>
        <div class="srp-stat-card srp-stat-warning">
            <h3><?php _e('Pending Withdrawals', 'smart-referral-pro'); ?></h3>
            <div class="srp-stat-number"><?php echo number_format(intval($pending_withdrawals)); ?></div>
        </div>
    </div>

    <div class="srp-sections">
        <div class="srp-section">
            <h2><?php _e('Recent Referrals', 'smart-referral-pro'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Referrer', 'smart-referral-pro'); ?></th>
                        <th><?php _e('Referee', 'smart-referral-pro'); ?></th>
                        <th><?php _e('Order', 'smart-referral-pro'); ?></th>
                        <th><?php _e('Earnings', 'smart-referral-pro'); ?></th>
                        <th><?php _e('Status', 'smart-referral-pro'); ?></th>
                        <th><?php _e('Date', 'smart-referral-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recent_referrals)) : ?>
                        <?php foreach ($recent_referrals as $ref) : ?>
                        <tr>
                            <td><?php echo esc_html($ref->referrer_name ?? '-'); ?></td>
                            <td><?php echo esc_html($ref->referee_name ?? '-'); ?></td>
                            <td>#<?php echo esc_html($ref->order_id); ?></td>
                            <td><?php echo wc_price(floatval($ref->referrer_earnings)); ?></td>
                            <td><span class="srp-badge srp-badge-<?php echo esc_attr($ref->status); ?>"><?php echo esc_html(ucfirst($ref->status)); ?></span></td>
                            <td><?php echo esc_html(human_time_diff(strtotime($ref->created_at), current_time('timestamp'))); ?> ago</td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="6" style="text-align:center;padding:20px;"><?php _e('No referrals yet.', 'smart-referral-pro'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="srp-section">
            <h2><?php _e('Top Referrers', 'smart-referral-pro'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Rank', 'smart-referral-pro'); ?></th>
                        <th><?php _e('User', 'smart-referral-pro'); ?></th>
                        <th><?php _e('Code', 'smart-referral-pro'); ?></th>
                        <th><?php _e('Referrals', 'smart-referral-pro'); ?></th>
                        <th><?php _e('Total Earnings', 'smart-referral-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($top_referrers)) : ?>
                        <?php $rank = 1; foreach ($top_referrers as $ref) : ?>
                        <tr>
                            <td>#<?php echo $rank++; ?></td>
                            <td><?php echo esc_html($ref->display_name ?? '-'); ?></td>
                            <td><code><?php echo esc_html($ref->code); ?></code></td>
                            <td><?php echo number_format(intval($ref->total_referrals)); ?></td>
                            <td><?php echo wc_price(floatval($ref->total_earnings)); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5" style="text-align:center;padding:20px;"><?php _e('No referrers yet.', 'smart-referral-pro'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
