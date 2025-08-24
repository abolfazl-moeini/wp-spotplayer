<?php
namespace spotplayer\inc\Shop;

defined('ABSPATH') || exit;

final class MyAccount {
    public function register(): void {
        add_filter('woocommerce_account_menu_items', [$this, 'menu']);
        add_action('init', [$this, 'endpoint']);
        add_action('woocommerce_account_licenses_endpoint', [$this, 'content']);
    }

    public function endpoint(): void {
        add_rewrite_endpoint('licenses', EP_PAGES);
    }

    public function menu(array $items): array {
        $opt = get_option(SPOTPLAYER_OPTION, []);
        if (!empty($opt['wccrs'])) {
            $items = array_slice($items, 0, 1, true) + ['licenses' => __('لایسنس‌های من', 'spotplayer')] + array_slice($items, 1, null, true);
        }
        return $items;
    }

    public function content(): void {
        echo do_shortcode('[spotplayer_courses]');
    }
}
