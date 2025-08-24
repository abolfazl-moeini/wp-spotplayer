<?php
namespace spotplayer\inc\WooCommerce;

use spotplayer\inc\Api\Client as ApiClient;

defined('ABSPATH') || exit;

final class AdminOrder {
    public function register(): void {
        add_action('add_meta_boxes', [$this, 'add_metabox'], 0);
        add_action('woocommerce_process_shop_order_meta', [$this, 'save']);
    }

    public function add_metabox(): void {
        if (!function_exists('wc_get_order')) return;
        $order = wc_get_order();
        if (!$order) return;
        add_meta_box('sp-order', __('اسپات پلیر', 'spotplayer'), [$this, 'render_box'], null, 'normal', 'high');
    }

    public function render_box(): void {
        $order = wc_get_order();
        if (!$order) return;
        $data = $this->get_license_data($order) ?: ['name' => '', 'watermark' => ['texts' => []]];
        wp_nonce_field('spotplayer_order_meta', 'spotplayer_nonce');
        ?>
        <table class="widefat" style="border:none">
            <tr>
                <td><?php esc_html_e('شناسه لایسنس', 'spotplayer'); ?>:</td>
                <td><input type="text" class="ltr" name="spot-id" value="<?php echo esc_attr($data['_id'] ?? ''); ?>" /></td>
            </tr>
            <tr>
                <td><?php esc_html_e('نام', 'spotplayer'); ?>:</td>
                <td><input type="text" name="spot-name" value="<?php echo esc_attr($data['name'] ?? ''); ?>" /></td>
            </tr>
            <tr>
                <td><?php esc_html_e('واترمارک', 'spotplayer'); ?>:</td>
                <td>
                    <?php
                    $texts = $data['watermark']['texts'] ?? [];
                    for ($i=0; $i<3; $i++):
                        $val = $texts[$i]['text'] ?? '';
                        printf('<input type="text" class="regular-text ltr" name="spot-text[%1$d]" value="%2$s" placeholder="%3$s" /><br/>',
                            $i, esc_attr($val), esc_attr(__('متن واترمارک', 'spotplayer')));
                    endfor; ?>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <button class="button button-primary" type="submit" name="spot-create" value="1"><?php esc_html_e('ایجاد/به‌روزرسانی لایسنس', 'spotplayer'); ?></button>
                    <button class="button" type="submit" name="spot-retrieve" value="1"><?php esc_html_e('دریافت اطلاعات لایسنس', 'spotplayer'); ?></button>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save(int $order_id): void {
        if (empty($_POST['spotplayer_nonce']) || !wp_verify_nonce($_POST['spotplayer_nonce'], 'spotplayer_order_meta')) return;
        $order = wc_get_order($order_id);
        if (!$order) return;

        $data = $order->get_meta('_spotplayer_data') ?: [];
        $api = new ApiClient();

        if (!empty($_POST['spot-retrieve']) && !empty($_POST['spot-id'])) {
            try {
                $id = sanitize_text_field(wp_unslash($_POST['spot-id']));
                $rep = $api->license_get($id);
                if (!empty($rep['_id'])) {
                    $data = array_merge($data, $rep);
                    $order->update_meta_data('_spotplayer_data', $data);
                    $order->save_meta_data();
                    $order->add_order_note(sprintf(__('اطلاعات لایسنس %s دریافت شد.', 'spotplayer'), esc_html($id)));
                }
            } catch (\Exception $e) {
                $order->add_order_note(sprintf(__('خطا در دریافت لایسنس: %s', 'spotplayer'), esc_html($e->getMessage())));
            }
            return;
        }

        if (!empty($_POST['spot-create'])) {
            $name = isset($_POST['spot-name']) ? sanitize_text_field(wp_unslash($_POST['spot-name'])) : '';
            $texts = isset($_POST['spot-text']) && is_array($_POST['spot-text']) ? array_values(array_filter(array_map('sanitize_text_field', wp_unslash($_POST['spot-text'])), function($t){return strlen($t) > 3;})) : [];
            if ($name && $texts) {
                $payload = array_merge($data, [
                    'name' => $name,
                    'watermark' => ['texts' => array_map(fn($t)=>['text'=>$t], $texts)]
                ]);
                try {
                    $rep = $api->license_put($payload);
                    $data = array_merge($data, $rep);
                    $order->update_meta_data('_spotplayer_data', $data);
                    $order->save_meta_data();
                    if (!empty($rep['_id'])) {
                        $order->add_order_note(sprintf(__('لایسنس با شناسه %s ایجاد/بروزرسانی شد.', 'spotplayer'), esc_html($rep['_id'])));
                    }
                } catch (\Exception $e) {
                    $order->add_order_note(sprintf(__('خطا در ساخت لایسنس: %s', 'spotplayer'), esc_html($e->getMessage())));
                }
            }
        }
    }

    private function get_license_data(\WC_Order $order): array {
        $data = $order->get_meta('_spotplayer_data') ?: [];
        if (in_array($order->get_status(), ['auto-draft','draft'], true)) {
            return $data;
        }
        if (!empty($data)) return $data;
        // Fallback to legacy eval template (kept for compatibility)
        return $this->eval_license_template($order) ?: [];
    }

    private function eval_license_template(?\WC_Order $order): ?array {
        if (!$order) return null;
        $user = $order->get_user();
        $code = get_option(SPOTPLAYER_OPTION)['code'] ?? '';
        if (!$code) return null;
        // Evaluate with extreme caution; this mirrors legacy behavior.
        try {
            return eval("return " . $code . ";");
        } catch (\Throwable $e) {
            return null;
        }
    }
}
