<?php
/**
 * Admin Withdrawals View
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap srp-admin">
    <h1><?php _e('Withdrawal Requests', 'smart-referral-pro'); ?></h1>

    <div class="srp-filters">
        <a href="<?php echo admin_url('admin.php?page=srp-withdrawals&status=pending'); ?>" class="button <?php echo ($status_filter ?? '') === 'pending' ? 'button-primary' : ''; ?>"><?php _e('Pending', 'smart-referral-pro'); ?></a>
        <a href="<?php echo admin_url('admin.php?page=srp-withdrawals&status=processing'); ?>" class="button <?php echo ($status_filter ?? '') === 'processing' ? 'button-primary' : ''; ?>"><?php _e('Processing', 'smart-referral-pro'); ?></a>
        <a href="<?php echo admin_url('admin.php?page=srp-withdrawals&status=completed'); ?>" class="button <?php echo ($status_filter ?? '') === 'completed' ? 'button-primary' : ''; ?>"><?php _e('Completed', 'smart-referral-pro'); ?></a>
        <a href="<?php echo admin_url('admin.php?page=srp-withdrawals&status=rejected'); ?>" class="button <?php echo ($status_filter ?? '') === 'rejected' ? 'button-primary' : ''; ?>"><?php _e('Rejected', 'smart-referral-pro'); ?></a>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('ID', 'smart-referral-pro'); ?></th>
                <th><?php _e('User', 'smart-referral-pro'); ?></th>
                <th><?php _e('Amount', 'smart-referral-pro'); ?></th>
                <th><?php _e('Method', 'smart-referral-pro'); ?></th>
                <th><?php _e('Status', 'smart-referral-pro'); ?></th>
                <th><?php _e('Requested', 'smart-referral-pro'); ?></th>
                <th><?php _e('Actions', 'smart-referral-pro'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($withdrawals)) : ?>
                <?php foreach ($withdrawals as $wd) : ?>
                <tr>
                    <td><?php echo esc_html($wd->id); ?></td>
                    <td>
                        <strong><?php echo esc_html($wd->display_name ?? '-'); ?></strong>
                        <br><small><?php echo esc_html($wd->user_email ?? ''); ?></small>
                    </td>
                    <td><?php echo wc_price(floatval($wd->amount)); ?></td>
                    <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $wd->method))); ?></td>
                    <td><span class="srp-badge srp-badge-<?php echo esc_attr($wd->status); ?>"><?php echo esc_html(ucfirst($wd->status)); ?></span></td>
                    <td><?php echo esc_html(date('M j, Y g:i a', strtotime($wd->requested_at))); ?></td>
                    <td>
                        <?php if ($wd->status === 'pending') : ?>
                            <button type="button" class="button button-primary srp-process-withdrawal" data-id="<?php echo esc_attr($wd->id); ?>" data-status="completed"><?php _e('Approve', 'smart-referral-pro'); ?></button>
                            <button type="button" class="button srp-process-withdrawal" data-id="<?php echo esc_attr($wd->id); ?>" data-status="rejected"><?php _e('Reject', 'smart-referral-pro'); ?></button>
                        <?php else : ?>
                            <?php if (!empty($wd->admin_notes)) : ?>
                                <small><?php echo esc_html($wd->admin_notes); ?></small>
                            <?php else : ?>
                                <em><?php _e('No notes', 'smart-referral-pro'); ?></em>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="7" style="text-align:center;padding:20px;"><?php _e('No withdrawal requests found.', 'smart-referral-pro'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
