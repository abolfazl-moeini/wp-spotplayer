<?php
namespace spotplayer\inc\Assets;

defined('ABSPATH') || exit;

final class Assets {
    public function register(): void {
        add_action('wp_enqueue_scripts', [$this, 'frontend']);
        add_action('admin_enqueue_scripts', [$this, 'admin']);
    }

    public function frontend(): void {
        wp_register_style('spot-shop', plugins_url('assets/public.css', SPOTPLAYER_FILE), [], SPOTPLAYER_VERSION);
        wp_enqueue_style('spot-shop');

        $opt = get_option(SPOTPLAYER_OPTION, []);
        $color = $opt['color'] ?? '#6611DD';
        if (!preg_match('/^#[0-9A-F]{6}$/i', $color)) $color = '#6611DD';
        $rgba = $this->hex2rgba($color, 0.05);

        $css = "#sp_license > BUTTON {background: {$color}} #sp B {color: {$color}} #sp_players > DIV {background: {$rgba}}";
        wp_add_inline_style('spot-shop', $css);
    }

    public function admin(): void {
        wp_register_style('spot-admin', plugins_url('assets/admin.css', SPOTPLAYER_FILE), [], SPOTPLAYER_VERSION);
        wp_enqueue_style('spot-admin');
    }

    private function hex2rgba(string $hex, float $opacity = 1): string {
        $h = substr($hex, 1);
        $rgb = [hexdec($h[0].$h[1]), hexdec($h[2].$h[3]), hexdec($h[4].$h[5])];
        $op = min(max($opacity, 0), 1);
        return 'rgba(' . implode(',', $rgb) . ',' . $op . ')';
    }
}
