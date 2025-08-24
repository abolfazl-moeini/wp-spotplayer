<?php
namespace spotplayer\inc\EDD;

defined('ABSPATH') || exit;

final class AdminDownload {
    public function register(): void {
        if (!function_exists('edd_price_field')) return;
        add_action('edd_price_field', [$this, 'field']);
        add_action('edd_save_download', [$this, 'save'], 10, 2);
    }

    public function field(): void {
        echo '<p><label for="_spot_course">' . esc_html__('شناسه دوره‌ها (اسپات پلیر)', 'spotplayer') . '</label>';
        printf('<input type="text" class="regular-text ltr" name="_spot_course" value="%s" />', esc_attr(get_post_meta(get_the_ID(), '_spot_course', true)));
        echo '</p>';
    }

    public function save(int $download_id, $post): void {
        $val = isset($_POST['_spot_course']) ? sanitize_text_field(wp_unslash($_POST['_spot_course'])) : '';
        update_post_meta($download_id, '_spot_course', $val);
    }
}
