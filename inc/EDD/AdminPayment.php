<?php
namespace spotplayer\inc\EDD;

defined('ABSPATH') || exit;

final class AdminPayment {
    public function register(): void {
        if (!function_exists('edd_view_order_details_main_before')) return;
        add_action('edd_view_order_details_main_before', [$this, 'box']);
        add_action('edd_updated_edited_purchase', [$this, 'save']);
    }

    public function box($payment_id = null): void {
        echo '<div class="postbox"><h2 class="hndle"><span>' . esc_html__('اسپات پلیر', 'spotplayer') . '</span></h2><div class="inside">';
        echo '<p class="description">' . esc_html__('پنل لایسنس EDD (نسخه بازنویسی‌شده).', 'spotplayer') . '</p>';
        echo '</div></div>';
    }

    public function save($payment_id = null): void {
        // Reserved for future parity with WooCommerce meta box
    }
}
