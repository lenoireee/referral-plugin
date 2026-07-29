<?php
/**
 * Admin Referral Records View
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap srp-admin">
    <h1><?php _e('Referral Records', 'smart-referral-pro'); ?></h1>

    <div class="srp-filters">
        <form method="get">
            <input type="hidden" name="page" value="srp-records">
            <select name="status" class="postform">
                <option value=""><?php _e('All Statuses', 'smart-referral-pro'); ?></option>
                <option value="pending" <?php selected($status_filter ?? '', 'pending'); ?>><?php _e('Pending', 'smart-referral-pro'); ?></option>
                <option value="completed" <?php selected($status_filter ?? '', 'completed'); ?>><?php _e('Completed', 'smart-referral-pro'); ?></option>
                <option value="cancelled" <?php selected($status_filter ?? '', 'cancelled'); ?>><?php _e('Cancelled', 'smart-referral-pro'); ?></option>
                <option value="refunded" <?php selected($status_filter ?? '', 'refunded'); ?>><?php _e('Refunded', 'smart-referral-pro'); ?></option>
            </select>
            <button type="submit" class="button"><?php _e('Filter', 'smart-referral-pro'); ?></button>
        </form>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('ID', 'smart-referral-pro'); ?></th>
                <th><?php _e('Referrer', 'smart-referral-pro'); ?></th>
                <th><?php _e('Referee', 'smart-referral-pro'); ?></th>
                <th><?php _e('Order', 'smart-referral-pro'); ?></th>
                <th><?php _e('Order Total', 'smart-referral-pro'); ?></th>
                <th><?php _e('Earnings', 'smart-referral-pro'); ?></th>
                <th><?php _e('Discount', 'smart-referral-pro'); ?></th>
                <th><?php _e('Status', 'smart-referral-pro'); ?></th>
                <th><?php _e('Date', 'smart-referral-pro'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($records)) : ?>
                <?php foreach ($records as $record) : ?>
                <tr>
                    <td><?php echo esc_html($record->id); ?></td>
                    <td><?php echo esc_html($record->referrer_name ?? '-'); ?></td>
                    <td><?php echo esc_html($record->referee_name ?? '-'); ?></td>
                    <td><a href="<?php echo admin_url('post.php?post=' . intval($record->order_id) . '&action=edit'); ?>">#<?php echo esc_html($record->order_id); ?></a></td>
                    <td><?php echo wc_price(floatval($record->order_total)); ?></td>
                    <td><?php echo wc_price(floatval($record->referrer_earnings)); ?></td>
                    <td><?php echo wc_price(floatval($record->referee_discount)); ?></td>
                    <td><span class="srp-badge srp-badge-<?php echo esc_attr($record->status); ?>"><?php echo esc_html(ucfirst($record->status)); ?></span></td>
                    <td><?php echo esc_html(date('M j, Y g:i a', strtotime($record->created_at))); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="9" style="text-align:center;padding:20px;"><?php _e('No referral records found.', 'smart-referral-pro'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1) : ?>
    <div class="srp-pagination">
        <div class="tablenav">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo number_format(intval($total)); ?> <?php _e('items', 'smart-referral-pro'); ?></span>
                <span class="pagination-links">
                    <?php
                    echo paginate_links([
                        'base'      => add_query_arg('paged', '%#%'),
                        'format'    => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total'     => intval($total_pages),
                        'current'   => intval($current_page),
                    ]);
                    ?>
                </span>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
