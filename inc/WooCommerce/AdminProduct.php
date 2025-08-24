<?php
namespace spotplayer\inc\WooCommerce;

defined('ABSPATH') || exit;

final class AdminProduct {
    public function register(): void {
        add_filter('woocommerce_product_data_tabs', [$this, 'product_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'product_panel']);
        add_action('woocommerce_admin_process_product_object', [$this, 'save_product']);
        add_action('woocommerce_product_after_variable_attributes', [$this, 'variation_panel'], 10, 2);
        add_action('woocommerce_admin_process_variation_object', [$this, 'save_variation'], 10, 2);
    }

    public function product_tab(array $tabs): array {
        $tabs['spotplayer'] = [
            'label'    => __('اسپات پلیر', 'spotplayer'),
            'target'   => 'spotplayer-product',
            'class'    => [],
            'priority' => 90,
        ];
        return $tabs;
    }

    public function product_panel(): void { ?>
        <div id="spotplayer-product" class="panel woocommerce_options_panel">
            <?php woocommerce_wp_textarea_input([
                'id'          => '_spotplayer_course',
                'name'        => '_spotplayer_course',
                'label'       => __('شناسه دوره‌ها', 'spotplayer'),
                'class'       => 'ltr',
                'desc_tip'    => true,
                'description' => __('شناسه دوره‌ها را با , جدا کنید.', 'spotplayer'),
            ]); ?>
        </div>
    <?php }

    public function save_product(\WC_Product $product): void {
        if (!current_user_can('administrator')) return;
        $course = isset($_POST['_spotplayer_course']) ? sanitize_text_field(wp_unslash($_POST['_spotplayer_course'])) : '';
        if (preg_match('/^[0-9a-f]{24}(,[0-9a-f]{24})*$/i', $course)) {
            $product->update_meta_data('_spotplayer_course', $course);
            $product->set_virtual(true);
            $product->set_sold_individually(true);
        } else {
            $product->update_meta_data('_spotplayer_course', '');
        }
    }

    public function variation_panel(\WC_Product_Variation $variation, int $i): void { ?>
        <div class="options_group">
            <?php woocommerce_wp_text_input([
                'id'            => "spotplayer_course[{$i}]",
                'label'         => __('شناسه دوره‌ها', 'spotplayer'),
                'value'         => $variation->get_meta('_spotplayer_course'),
                'wrapper_class' => 'form-row form-row-full',
                'class'         => 'short ltr',
                'desc_tip'      => true,
                'description'   => __('شناسه دوره‌های این ورییشن', 'spotplayer'),
            ]); ?>
        </div>
    <?php }

    public function save_variation(\WC_Product_Variation $variation, int $i): void {
        $val = isset($_POST['spotplayer_course'][$i]) ? sanitize_text_field(wp_unslash($_POST['spotplayer_course'][$i])) : '';
        $this->save_product($variation);
        if ($val) $variation->update_meta_data('_spotplayer_course', $val);
    }
}
