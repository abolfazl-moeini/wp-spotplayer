<?php
namespace spotplayer\inc;

use spotplayer\inc\Admin\SettingsPage;
use spotplayer\inc\Assets\Assets;
use spotplayer\inc\EDD\AdminDownload;
use spotplayer\inc\EDD\AdminPayment;
use spotplayer\inc\Shop\MyAccount;
use spotplayer\inc\Shortcodes\Courses;
use spotplayer\inc\WooCommerce\AdminOrder;
use spotplayer\inc\WooCommerce\AdminProduct;

defined('ABSPATH') || exit;

final class Plugin {
    private static $instance;

    public static function instance(): self {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    public function boot(): void {
        add_action('plugins_loaded', [$this, 'init']);
        register_activation_hook(SPOTPLAYER_FILE, [$this, 'activate']);
    }

    public function activate(): void {
        // Initialize default options if not present
        $opt = get_option(SPOTPLAYER_OPTION, []);
        if (empty($opt)) {
            $opt = [
                'color' => '#6611DD',
                'api'   => '',
                'code'  => '',
                'test'  => 0,
                'time'  => 0,
            ];
            add_option(SPOTPLAYER_OPTION, $opt);
        }
    }

    public function init(): void {
        // Admin
        if (is_admin()) {
            (new SettingsPage())->register();
            add_filter('plugin_action_links', [$this, 'plugin_action_links'], 10, 2);
        }

        // Assets
        (new Assets())->register();

        // Shortcodes
        (new Courses())->register();

        // WooCommerce (if active)
        if (class_exists('WooCommerce')) {
            (new AdminProduct())->register();
            (new AdminOrder())->register();
            (new MyAccount())->register();
        }

        // Easy Digital Downloads (if active)
        if (class_exists('Easy_Digital_Downloads') || function_exists('EDD')) {
            (new AdminDownload())->register();
            (new AdminPayment())->register();
        }

        // URL handler (kept for backward-compat with /spotx & /spdeb)
        add_action('parse_request', [$this, 'url_handler']);
    }

    public function plugin_action_links(array $links, string $file): array {
        if (strpos($file, 'wp-spotplayer.php') !== false) {
            array_unshift(
                $links,
                '<a href="' . esc_url(admin_url('admin.php?page=spotplayer')) . '">' . esc_html__('تنظیمات', 'spotplayer') . '</a>'
            );
        }
        return $links;
    }

    public function url_handler(): void {
        $home_path = parse_url(get_home_url(), PHP_URL_PATH) ?: '';
        $path = str_replace($home_path, '', $_SERVER['REQUEST_URI'] ?? '');
        $prefix = substr($path, 0, 6);
        if ($prefix === '/spotx') {
            do_action('spotplayer/spotx');
            exit;
        }
        if ($prefix === '/spdeb') {
            do_action('spotplayer/spdeb');
            exit;
        }
    }
}
