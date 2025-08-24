<?php
namespace spotplayer\inc\Admin;

defined('ABSPATH') || exit;

final class SettingsPage {
    public function register(): void {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'settings']);
    }

    public function menu(): void {
        add_menu_page(
            __('اسپات پلیر', 'spotplayer'),
            __('اسپات پلیر', 'spotplayer'),
            'manage_options',
            'spotplayer',
            [$this, 'render'],
            'dashicons-media-code',
            56
        );
    }

    public function settings(): void {
        register_setting('spotplayer', SPOTPLAYER_OPTION, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize'],
            'default' => [],
        ]);
        add_settings_section('spotplayer_main', __('تنظیمات اصلی', 'spotplayer'), '__return_false', 'spotplayer');

        add_settings_field('api', __('کلید API', 'spotplayer'), [$this, 'field_api'], 'spotplayer', 'spotplayer_main');
        add_settings_field('code', __('کد ساخت لایسنس', 'spotplayer'), [$this, 'field_code'], 'spotplayer', 'spotplayer_main');
        add_settings_field('color', __('رنگ برند', 'spotplayer'), [$this, 'field_color'], 'spotplayer', 'spotplayer_main');
        add_settings_field('test', __('حالت تست', 'spotplayer'), [$this, 'field_test'], 'spotplayer', 'spotplayer_main');
        add_settings_field('time', __('عدم ساخت برای سفارشات قدیمی', 'spotplayer'), [$this, 'field_time'], 'spotplayer', 'spotplayer_main');
    }

    public function sanitize($input) {
        $out = is_array($input) ? $input : [];
        $out['api'] = isset($out['api']) ? sanitize_text_field($out['api']) : '';
        $out['code'] = isset($out['code']) ? wp_kses_post($out['code']) : '';
        $out['color'] = (isset($out['color']) && preg_match('/^#[0-9A-F]{6}$/i', $out['color'])) ? $out['color'] : '#6611DD';
        $out['test'] = empty($out['test']) ? 0 : 1;
        $out['time'] = empty($out['time']) ? 0 : absint($out['time']);

        // Preserve extra optional flags from legacy UI if posted
        foreach (['wccrs','studiare','lifterlms'] as $flag) {
            if (isset($out[$flag])) $out[$flag] = $out[$flag] ? 1 : 0;
        }
        return $out;
    }

    public function render(): void {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('اسپات پلیر', 'spotplayer'); ?></h1>
            <form method="post" action="options.php" novalidate>
                <?php
                settings_fields('spotplayer');
                do_settings_sections('spotplayer');
                submit_button(__('ذخیره تنظیمات', 'spotplayer'));
                ?>
            </form>
            <p class="description">
                <?php esc_html_e('کلید API و الگوی ساخت لایسنس را از پنل اسپات پلیر دریافت کنید.', 'spotplayer'); ?>
            </p>
        </div>
        <?php
    }

    private function getopt(): array {
        return get_option(SPOTPLAYER_OPTION, []);
    }

    public function field_api(): void {
        $v = $this->getopt()['api'] ?? '';
        printf('<input type="text" class="regular-text ltr" name="%1$s[api]" value="%2$s" pattern="^(?:[A-Za-z0-9+/]{{4}})*(?:[A-Za-z0-9+/]{{2}}==|[A-Za-z0-9+/]{{3}}=)?$" required />',
            esc_attr(SPOTPLAYER_OPTION),
            esc_attr($v)
        );
        echo '<p class="description">' . esc_html__('کلید API که در داشبورد اسپات پلیر در دسترس است.', 'spotplayer') . '</p>';
    }

    public function field_code(): void {
        $v = $this->getopt()['code'] ?? '';
        printf('<textarea class="large-text code" rows="10" name="%1$s[code]">%2$s</textarea>', esc_attr(SPOTPLAYER_OPTION), esc_textarea($v));
        echo '<p class="description">' . esc_html__('کد PHP تولید لایسنس (استفاده با احتیاط).', 'spotplayer') . '</p>';
    }

    public function field_color(): void {
        $v = $this->getopt()['color'] ?? '#6611DD';
        printf('<input type="text" class="regular-text ltr" name="%1$s[color]" value="%2$s" />', esc_attr(SPOTPLAYER_OPTION), esc_attr($v));
    }

    public function field_test(): void {
        $v = !empty($this->getopt()['test']);
        printf('<label><input type="checkbox" name="%1$s[test]" value="1" %2$s/> %3$s</label>',
            esc_attr(SPOTPLAYER_OPTION),
            checked($v, true, false),
            esc_html__('فعال‌سازی حالت تست (لایسنس‌ها جایگزین می‌شوند)', 'spotplayer')
        );
    }

    public function field_time(): void {
        $opt = $this->getopt();
        $value = !empty($opt['time']) ? intval($opt['time']) : 0;
        $checked = $value ? 'checked="checked"' : '';
        $now = time();
        if (!$value) $value = $now;
        printf('<label><input type="checkbox" name="%1$s[time]" value="%2$d" %3$s/> %4$s</label>',
            esc_attr(SPOTPLAYER_OPTION),
            intval($value),
            $checked,
            esc_html__('عدم ایجاد لایسنس برای سفارشات قدیمی', 'spotplayer')
        );
    }
}
