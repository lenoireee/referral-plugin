<?php
/**
 * Admin Accounts View
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap srp-admin">
    <h1><?php _e('All Referral Accounts', 'smart-referral-pro'); ?></h1>

    <div class="srp-filters">
        <form method="get">
            <input type="hidden" name="page" value="srp-accounts">
            <input type="search" name="s" class="regular-text" value="<?php echo esc_attr($search ?? ''); ?>" placeholder="<?php _e('Search by name, email, or code...', 'smart-referral-pro'); ?>">
            <button type="submit" class="button"><?php _e('Search', 'smart-referral-pro'); ?></button>
            <?php if (!empty($search)) : ?>
                <a href="<?php echo admin_url('admin.php?page=srp-accounts'); ?>" class="button"><?php _e('Clear', 'smart-referral-pro'); ?></a>
            <?php endif; ?>
        </form>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('User', 'smart-referral-pro'); ?></th>
                <th><?php _e('Referral Code', 'smart-referral-pro'); ?></th>
                <th><?php _e('Total Referrals', 'smart-referral-pro'); ?></th>
                <th><?php _e('Total Earnings', 'smart-referral-pro'); ?></th>
                <th><?php _e('Balance', 'smart-referral-pro'); ?></th>
                <th><?php _e('Registered', 'smart-referral-pro'); ?></th>
                <th><?php _e('Actions', 'smart-referral-pro'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($accounts)) : ?>
                <?php foreach ($accounts as $account) : ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($account->display_name ?? '-'); ?></strong>
                        <br><small><?php echo esc_html($account->user_email ?? ''); ?></small>
                    </td>
                    <td><code><?php echo esc_html($account->code); ?></code></td>
                    <td><?php echo number_format(intval($account->total_referrals)); ?></td>
                    <td><?php echo wc_price(floatval($account->total_earnings)); ?></td>
                    <td><?php echo wc_price(floatval($account->balance ?? 0)); ?></td>
                    <td><?php echo esc_html(date('M j, Y', strtotime($account->user_registered))); ?></td>
                    <td>
                        <a href="<?php echo admin_url('user-edit.php?user_id=' . intval($account->user_id)); ?>" class="button button-small"><?php _e('View User', 'smart-referral-pro'); ?></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="7" style="text-align:center;padding:20px;"><?php _e('No accounts found.', 'smart-referral-pro'); ?></td>
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
