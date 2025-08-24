<?php
namespace spotplayer\inc\Shortcodes;

defined('ABSPATH') || exit;

final class Courses {
    public function register(): void {
        add_shortcode('spotplayer_courses', [$this, 'render']);
    }

    public function render($atts = [], $content = ''): string {
        $out = '<div id="sp" class="spotplayer-courses">';
        $out .= '<h3>' . esc_html__('دوره‌های من (اسپات پلیر)', 'spotplayer') . '</h3>';
        $out .= '<p class="description">' . esc_html__('این بخش در نسخه بازنویسی‌شده ساده‌سازی شده است. در صورت نیاز به جزئیات بیشتر، می‌توان آن را به API پنل متصل کرد.', 'spotplayer') . '</p>';
        $out .= do_shortcode($content);
        $out .= '</div>';
        return $out;
    }
}
