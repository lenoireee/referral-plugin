<?php
namespace SRP;

class Notifications {

    /**
     * Send referral completion notification
     */
    public static function send_referral_notification($user_id, $referral) {
        if (get_option('srp_enable_notifications', 'yes') !== 'yes') {
            return;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        $subject = sprintf('[%s] You earned %s from a referral!', get_bloginfo('name'), wc_price($referral->referrer_earnings));

        $message = sprintf(
            "Hi %s,

Great news! Someone used your referral code and completed a purchase.

" .
            "Order Total: %s
" .
            "Your Earnings: %s
" .
            "Order ID: #%d

" .
            "Your current balance: %s

" .
            "Keep sharing your referral link: %s

" .
            "Best regards,
%s",
            $user->display_name,
            wc_price($referral->order_total),
            wc_price($referral->referrer_earnings),
            $referral->order_id,
            wc_price(Wallet::get_balance($user_id)),
            home_url('?ref=' . ReferralCode::get_user_code($user_id)->code),
            get_bloginfo('name')
        );

        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        wp_mail($user->user_email, $subject, $message, $headers);

        // Also notify admin
        $admin_email = get_option('srp_notification_email', get_option('admin_email'));
        $admin_subject = sprintf('[%s] New Referral Completed', get_bloginfo('name'));
        $admin_message = sprintf(
            "A new referral has been completed.

" .
            "Referrer: %s (ID: %d)
" .
            "Referee: %s (ID: %d)
" .
            "Earnings: %s
" .
            "Order: #%d",
            $user->display_name,
            $user_id,
            get_userdata($referral->referee_id)->display_name,
            $referral->referee_id,
            wc_price($referral->referrer_earnings),
            $referral->order_id
        );
        wp_mail($admin_email, $admin_subject, $admin_message, $headers);
    }

    /**
     * Send withdrawal notification
     */
    public static function send_withdrawal_notification($user_id, $amount, $status) {
        if (get_option('srp_enable_notifications', 'yes') !== 'yes') {
            return;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        $subject = sprintf('[%s] Withdrawal %s', get_bloginfo('name'), ucfirst($status));

        if ($status === 'completed') {
            $message = sprintf(
                "Hi %s,

Your withdrawal request of %s has been processed and completed.

" .
                "Your current balance: %s

Best regards,
%s",
                $user->display_name,
                wc_price($amount),
                wc_price(Wallet::get_balance($user_id)),
                get_bloginfo('name')
            );
        } else {
            $message = sprintf(
                "Hi %s,

Your withdrawal request of %s has been rejected.

" .
                "Please contact support for more information.

" .
                "Your current balance: %s

Best regards,
%s",
                $user->display_name,
                wc_price($amount),
                wc_price(Wallet::get_balance($user_id)),
                get_bloginfo('name')
            );
        }

        wp_mail($user->user_email, $subject, $message);
    }
}
