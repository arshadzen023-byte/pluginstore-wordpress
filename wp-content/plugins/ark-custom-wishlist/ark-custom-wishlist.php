<?php
/**
 * Plugin Name: ARK Custom Wishlist for WooCommerce
 * Description: Responsive wishlist, product-image wishlist/share buttons, and wishlist page.
 * Version: 1.1.0
 * Author: ARK
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 */

defined('ABSPATH') || exit;

if (!class_exists('ARK_Custom_Wishlist')) {
final class ARK_Custom_Wishlist {
    const OPT = 'ark_cw_settings';
    const META = '_ark_cw_ids';
    const NONCE = 'ark_cw_nonce';

    public function __construct() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'register']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_action('woocommerce_before_single_product_summary', [$this, 'image_button'], 1);
        add_action('woocommerce_before_add_to_cart_button', [$this, 'single_button'], 5);
        add_action('woocommerce_after_add_to_cart_button', [$this, 'single_button'], 25);
        add_action('woocommerce_single_product_summary', [$this, 'summary_button'], 35);
        add_action('woocommerce_before_shop_loop_item_title', [$this, 'loop_button'], 9);
        add_shortcode('ark_wishlist', [$this, 'page_shortcode']);
        add_shortcode('ark_wishlist_button', [$this, 'button_shortcode']);

        foreach (['get', 'toggle', 'remove', 'sync'] as $action) {
            add_action('wp_ajax_ark_cw_' . $action, [$this, 'ajax_' . $action]);
            add_action('wp_ajax_nopriv_ark_cw_' . $action, [$this, 'ajax_' . $action]);
        }
    }

    public static function defaults() {
        return [
            'page' => 0, 'single' => 1, 'loop' => 1, 'position' => 'image',
            'text' => 'Add to wishlist', 'saved' => 'Saved', 'icon' => 'heart',
            'style' => 'icon', 'size' => 'small', 'bg' => '#2874f0', 'fg' => '#ffffff',
            'count' => 0, 'share' => 1, 'share_bg' => '#ffffff', 'share_fg' => '#2874f0',
            'heading' => 'My Wishlist', 'empty' => 'Your wishlist is empty.', 'columns' => 3,
        ];
    }

    private function settings() {
        return wp_parse_args(get_option(self::OPT, []), self::defaults());
    }

    public function menu() {
        add_submenu_page('woocommerce', 'ARK Wishlist', 'ARK Wishlist', 'manage_woocommerce', 'ark-cw', [$this, 'settings_page']);
    }

    public function register() {
        register_setting('ark_cw_group', self::OPT, [$this, 'sanitize']);
    }

    public function sanitize($input) {
        $input = is_array($input) ? $input : [];
        $defaults = self::defaults();
        $out = [];
        $out['page'] = absint($input['page'] ?? 0);
        foreach (['single', 'loop', 'count', 'share'] as $key) {
            $out[$key] = empty($input[$key]) ? 0 : 1;
        }
        $choices = [
            'position' => ['after', 'before', 'summary', 'image'],
            'icon' => ['heart', 'star', 'bookmark'],
            'style' => ['pill', 'rounded', 'outline', 'icon'],
            'size' => ['small', 'medium', 'large'],
        ];
        foreach ($choices as $key => $allowed) {
            $value = isset($input[$key]) ? sanitize_key($input[$key]) : $defaults[$key];
            $out[$key] = in_array($value, $allowed, true) ? $value : $defaults[$key];
        }
        foreach (['text' => 50, 'saved' => 50, 'heading' => 80, 'empty' => 120] as $key => $length) {
            $out[$key] = substr(sanitize_text_field($input[$key] ?? $defaults[$key]), 0, $length);
        }
        foreach (['bg', 'fg', 'share_bg', 'share_fg'] as $key) {
            $out[$key] = sanitize_hex_color($input[$key] ?? '') ?: $defaults[$key];
        }
        $out['columns'] = max(1, min(4, absint($input['columns'] ?? $defaults['columns'])));
        return $out;
    }

    public function settings_page() {
        if (!current_user_can('manage_woocommerce')) return;
        $s = $this->settings();
        ?>
        <div class="wrap">
            <h1>ARK Custom Wishlist</h1>
            <p>Wishlist page shortcode: <code>[ark_wishlist]</code></p>
            <form method="post" action="options.php">
                <?php settings_fields('ark_cw_group'); ?>
                <table class="form-table" role="presentation">
                    <tr><th scope="row">Wishlist page</th><td><select name="<?php echo esc_attr(self::OPT); ?>[page]"><option value="0">Select page</option><?php foreach (get_pages() as $page) : ?><option value="<?php echo esc_attr($page->ID); ?>" <?php selected($s['page'], $page->ID); ?>><?php echo esc_html($page->post_title); ?></option><?php endforeach; ?></select></td></tr>
                    <tr><th scope="row">Button visibility</th><td><label><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[single]" value="1" <?php checked($s['single']); ?>> Product page</label><br><label><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[loop]" value="1" <?php checked($s['loop']); ?>> Shop/category cards</label></td></tr>
                    <tr><th scope="row">Product button location</th><td><select name="<?php echo esc_attr(self::OPT); ?>[position]"><?php foreach (['image' => 'On product image (floating buttons)', 'after' => 'Below Add to Cart', 'before' => 'Before Add to Cart', 'summary' => 'Product summary end'] as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($s['position'], $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></td></tr>
                    <tr><th scope="row">Wishlist button labels</th><td>Default <input type="text" name="<?php echo esc_attr(self::OPT); ?>[text]" value="<?php echo esc_attr($s['text']); ?>"> Saved <input type="text" name="<?php echo esc_attr(self::OPT); ?>[saved]" value="<?php echo esc_attr($s['saved']); ?>"></td></tr>
                    <tr><th scope="row">Wishlist button design</th><td>Icon <select name="<?php echo esc_attr(self::OPT); ?>[icon]"><?php foreach (['heart' => 'Heart', 'star' => 'Star', 'bookmark' => 'Bookmark'] as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($s['icon'], $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select> Style <select name="<?php echo esc_attr(self::OPT); ?>[style]"><?php foreach (['pill' => 'Pill', 'rounded' => 'Rounded', 'outline' => 'Outline', 'icon' => 'Icon only'] as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($s['style'], $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select> Size <select name="<?php echo esc_attr(self::OPT); ?>[size]"><?php foreach (['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large'] as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($s['size'], $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select><br><br>Background <input type="color" name="<?php echo esc_attr(self::OPT); ?>[bg]" value="<?php echo esc_attr($s['bg']); ?>"> Icon/text <input type="color" name="<?php echo esc_attr(self::OPT); ?>[fg]" value="<?php echo esc_attr($s['fg']); ?>"><br><label><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[count]" value="1" <?php checked($s['count']); ?>> Show count</label></td></tr>
                    <tr><th scope="row">Product Share Button</th><td><label><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[share]" value="1" <?php checked($s['share']); ?>> Show Share icon on product image</label><p>Share background <input type="color" name="<?php echo esc_attr(self::OPT); ?>[share_bg]" value="<?php echo esc_attr($s['share_bg']); ?>"></p><p>Share icon color <input type="color" name="<?php echo esc_attr(self::OPT); ?>[share_fg]" value="<?php echo esc_attr($s['share_fg']); ?>"></p></td></tr>
                    <tr><th scope="row">Wishlist page UI</th><td>Heading <input type="text" name="<?php echo esc_attr(self::OPT); ?>[heading]" value="<?php echo esc_attr($s['heading']); ?>"><br><br>Empty message <input type="text" name="<?php echo esc_attr(self::OPT); ?>[empty]" value="<?php echo esc_attr($s['empty']); ?>"><br><br>Desktop columns <select name="<?php echo esc_attr(self::OPT); ?>[columns]"><?php for ($i = 1; $i <= 4; $i++) : ?><option value="<?php echo esc_attr($i); ?>" <?php selected($s['columns'], $i); ?>><?php echo esc_html($i); ?></option><?php endfor; ?></select></td></tr>
                </table>
                <?php submit_button('Save Wishlist Settings'); ?>
            </form>
            <p>Shortcodes: <code>[ark_wishlist]</code> and <code>[ark_wishlist_button product_id="123"]</code></p>
        </div>
        <?php
    }

    public function assets() {
        if (!class_exists('WooCommerce')) return;
        $s = $this->settings();
        $base = plugin_dir_url(__FILE__);
       
wp_enqueue_style(
    'ark-cw',
    $base . 'assets/wishlist.css',
    [],
    '1.1.2'
);

wp_enqueue_script(
    'ark-cw',
    $base . 'assets/wishlist.js',
    [],
    '1.0.4',
    true
);

        wp_localize_script('ark-cw', 'ARKCW', [
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE),
            'logged' => is_user_logged_in(),
            'text' => $s['text'], 'saved' => $s['saved'],
            'page' => !empty($s['page']) ? get_permalink($s['page']) : '',
            'productApi' => rest_url('wc/store/v1/products/'),
            'empty' => $s['empty'], 'remove' => __('Remove', 'ark-custom-wishlist'),
            'error' => __('Could not update wishlist. Please try again.', 'ark-custom-wishlist'),
        ]);

        $css = '
        .ark-cw-image-actions{position:absolute!important;top:12px!important;right:12px!important;left:auto!important;z-index:100!important;display:flex!important;flex-direction:column!important;align-items:center;gap:9px!important}
        body.single-product .woocommerce-product-gallery,body.single-product .woocommerce div.product div.images{position:relative}
        .ark-cw-image-actions .ark-cw-single,.ark-cw-image-actions .ark-cw-share-image{display:inline-flex!important;align-items:center;justify-content:center;width:40px;height:40px;min-width:40px;padding:8px!important;border-radius:50%!important;box-shadow:0 3px 12px rgba(20,30,50,.14);cursor:pointer}
        .ark-cw-image-actions .ark-cw-single{background:var(--ark-cw-bg,#2874f0)!important;color:var(--ark-cw-fg,#fff)!important;border:1px solid var(--ark-cw-bg,#2874f0)!important}
        .ark-cw-image-actions .ark-cw-single .ark-cw-label,.ark-cw-image-actions .ark-cw-single .ark-cw-count{display:none!important}
        .ark-cw-image-actions .ark-cw-single .ark-cw-icon{width:21px;height:21px;flex:0 0 21px}
        .ark-cw-image-actions .ark-cw-single[aria-pressed=true]{background:#fff0f2!important;color:#e11d48!important;border-color:#fecdd3!important}
        .ark-cw-image-actions .ark-cw-share-image{background:var(--ark-cw-share-bg,#fff)!important;color:var(--ark-cw-share-fg,#2874f0)!important;border:1px solid #e4eaf2!important}
        .ark-cw-image-actions .ark-cw-share-image svg{width:21px;height:21px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
        .ark-cw-image-actions .ark-cw-share-image:hover{transform:translateY(-2px)}
        @media(max-width:640px){.ark-cw-image-actions{top:10px!important;right:9px!important;gap:7px!important}.ark-cw-image-actions .ark-cw-single,.ark-cw-image-actions .ark-cw-share-image{width:36px;height:36px;min-width:36px;padding:7px!important}}
        .ark-cw-page{width:100%;max-width:1200px;margin:0 auto;padding:18px;border:1px solid #e8edf4;border-radius:20px;background:#fff;box-shadow:0 5px 24px rgba(20,35,60,.06)}
        .ark-cw-page>header{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px}
        .ark-cw-page>header h2{margin:0;color:#172337;font-size:clamp(22px,4vw,30px);font-weight:800;line-height:1.2}
        .ark-cw-total{flex:0 0 auto;padding:5px 10px;border-radius:999px;background:#edf4ff;color:#1769f5;font-size:12px;font-weight:700}
        .ark-cw-grid{display:grid;grid-template-columns:repeat(var(--ark-cw-columns,3),minmax(0,1fr));gap:14px}.ark-cw-grid>*{min-width:0}
        .ark-cw-page img{max-width:100%;height:auto;object-fit:contain}.ark-cw-page button,.ark-cw-page a{overflow-wrap:anywhere}.ark-cw-notice:empty{display:none}
        .ark-cw-item{min-width:0}.ark-cw-actions{display:flex;gap:8px}
        @media(max-width:760px){.ark-cw-page{padding:12px;border-radius:15px}.ark-cw-grid{grid-template-columns:minmax(0,1fr)!important;gap:10px}}
        ';
        wp_add_inline_style('ark-cw', $css);

        // The wishlist and share buttons are emitted together by PHP; keep the wrapper anchored to the gallery.
        
$move_js = <<<'JS'
(function () {
    'use strict';

    function moveActions() {
        if (!document.body.classList.contains('single-product')) {
            return;
        }

        var actions = document.querySelector(
            'body.single-product .ark-cw-image-actions'
        );

        var gallery = document.querySelector(
            'body.single-product .woocommerce-product-gallery'
        ) || document.querySelector(
            'body.single-product .woocommerce div.product div.images'
        );

        if (!actions || !gallery) {
            return;
        }

        if (actions.parentElement !== gallery) {
            gallery.prepend(actions);
        }

        gallery.style.position = 'relative';
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', moveActions, {
            once: true
        });
    } else {
        moveActions();
    }
})();
JS;

wp_add_inline_script('ark-cw', $move_js, 'after');
    }


    private function icon_svg() {
        $s = $this->settings();
        $paths = [
            'heart' => 'M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.6-8.6a5.5 5.5 0 0 0 .2-7.8Z',
            'star' => 'm12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9L12 3Z',
            'bookmark' => 'M6 4h12v17l-6-4-6 4Z',
        ];
        $path = $paths[$s['icon']] ?? $paths['heart'];
        return '<svg class="ark-cw-icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><path d="' . esc_attr($path) . '"/></svg>';
    }

    public function button($product_id, $context = 'single') {
        $product_id = absint($product_id);
        if (!$product_id || get_post_type($product_id) !== 'product' || get_post_status($product_id) !== 'publish') return '';
        $s = $this->settings();
        $label = $s['style'] === 'icon' ? '' : '<span class="ark-cw-label">' . esc_html($s['text']) . '</span>';
        $style = '--ark-cw-bg:' . esc_attr($s['bg']) . ';--ark-cw-fg:' . esc_attr($s['fg']) . ';';
        return sprintf('<button type="button" class="ark-cw-button ark-cw-%1$s ark-cw-style-%2$s ark-cw-%3$s" data-id="%4$d" aria-pressed="false" aria-label="%5$s" style="%6$s">%7$s%8$s%9$s</button>', esc_attr($context), esc_attr($s['style']), esc_attr($s['size']), $product_id, esc_attr($s['text']), esc_attr($style), $this->icon_svg(), $label, !empty($s['count']) ? '<span class="ark-cw-count">0</span>' : '');
    }

    public function image_button() {
        $s = $this->settings();
        if (empty($s['single']) || $s['position'] !== 'image' || !function_exists('is_product') || !is_product()) return;
        global $product;
        if (!$product instanceof WC_Product) return;
        echo '<div class="ark-cw-image-actions" aria-label="' . esc_attr__('Product actions', 'ark-custom-wishlist') . '">';
        echo $this->button($product->get_id(), 'single');
        if (!empty($s['share'])) {
            $share_style = '--ark-cw-share-bg:' . esc_attr($s['share_bg']) . ';--ark-cw-share-fg:' . esc_attr($s['share_fg']) . ';';
            echo sprintf('<button type="button" class="ark-cw-share-image" data-share-url="%1$s" data-share-title="%2$s" aria-label="%3$s" title="%3$s" style="%4$s"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><path d="M8.7 10.7 15.3 6.3 M8.7 13.3 15.3 17.7"></path></svg></button>', esc_url(get_permalink($product->get_id())), esc_attr(get_the_title($product->get_id())), esc_attr__('Share product', 'ark-custom-wishlist'), esc_attr($share_style));
        }
        echo '</div>';
    }

    public function single_button() {
        $s = $this->settings();
        if (empty($s['single']) || !function_exists('is_product') || !is_product() || $s['position'] === 'image') return;
        $hook = current_filter();
        if ($s['position'] === 'before' && $hook !== 'woocommerce_before_add_to_cart_button') return;
        if ($s['position'] === 'after' && $hook !== 'woocommerce_after_add_to_cart_button') return;
        if ($s['position'] === 'summary') return;
        global $product;
        if ($product instanceof WC_Product) echo $this->button($product->get_id());
    }

    public function summary_button() {
        $s = $this->settings();
        if (empty($s['single']) || $s['position'] !== 'summary' || !function_exists('is_product') || !is_product()) return;
        global $product;
        if ($product instanceof WC_Product) echo $this->button($product->get_id());
    }

    public function loop_button() {
        $s = $this->settings();
        if (empty($s['loop']) || (function_exists('is_product') && is_product())) return;
        global $product;
        if ($product instanceof WC_Product) echo $this->button($product->get_id(), 'loop');
    }

    public function button_shortcode($atts) {
        $atts = shortcode_atts(['product_id' => 0], $atts, 'ark_wishlist_button');
        $id = absint($atts['product_id']);
        if (!$id) { global $product; $id = $product instanceof WC_Product ? $product->get_id() : get_queried_object_id(); }
        return $this->button($id, 'shortcode');
    }

    public function page_shortcode() {
        $s = $this->settings();
        return '<div class="ark-cw-page" data-columns="' . esc_attr($s['columns']) . '"><header><h2>' . esc_html($s['heading']) . '</h2><span class="ark-cw-total">0 items</span></header><p class="ark-cw-notice" role="status"></p><div class="ark-cw-grid"><p>Loading wishlist…</p></div></div>';
    }

    private function ids() {
        if (!is_user_logged_in()) return [];
        return array_values(array_unique(array_filter(array_map('absint', (array) get_user_meta(get_current_user_id(), self::META, true)))));
    }

    private function valid_ids($ids) {
        $valid = [];
        foreach (array_slice(array_unique(array_map('absint', (array) $ids)), 0, 500) as $id) {
            if ($id && get_post_type($id) === 'product' && get_post_status($id) === 'publish') $valid[] = $id;
        }
        return $valid;
    }

    private function verify() { check_ajax_referer(self::NONCE, 'nonce'); }
    public function ajax_get() { $this->verify(); wp_send_json_success(['ids' => $this->ids()]); }

    public function ajax_toggle() {
        $this->verify();
        $id = absint($_POST['id'] ?? 0);
        if (!$id || get_post_type($id) !== 'product' || get_post_status($id) !== 'publish') wp_send_json_error([], 400);
        if (!is_user_logged_in()) wp_send_json_success(['guest' => 1]);
        $ids = $this->ids();
        $added = !in_array($id, $ids, true);
        $ids = $added ? array_merge([$id], $ids) : array_values(array_diff($ids, [$id]));
        $ids = $this->valid_ids($ids);
        update_user_meta(get_current_user_id(), self::META, $ids);
        wp_send_json_success(['ids' => $ids, 'added' => $added]);
    }

    public function ajax_remove() {
        $this->verify();
        $id = absint($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error([], 400);
        if (!is_user_logged_in()) wp_send_json_success(['guest' => 1]);
        $ids = array_values(array_diff($this->ids(), [$id]));
        update_user_meta(get_current_user_id(), self::META, $ids);
        wp_send_json_success(['ids' => $ids]);
    }

    public function ajax_sync() {
        $this->verify();
        if (!is_user_logged_in()) wp_send_json_error([], 401);
        $guest = isset($_POST['ids']) ? (array) wp_unslash($_POST['ids']) : [];
        $ids = $this->valid_ids(array_merge(array_map('absint', $guest), $this->ids()));
        update_user_meta(get_current_user_id(), self::META, $ids);
        wp_send_json_success(['ids' => $ids]);
    }

    public static function activate() {
        $settings = get_option(self::OPT, []);
        if (empty($settings['page'])) {
            $page = get_page_by_path('wishlist');
            if (!$page) {
                $id = wp_insert_post(['post_title' => 'Wishlist', 'post_name' => 'wishlist', 'post_status' => 'publish', 'post_type' => 'page', 'post_content' => '[ark_wishlist]']);
                if (!is_wp_error($id)) $settings['page'] = $id;
            } else {
                $settings['page'] = $page->ID;
            }
        }
        update_option(self::OPT, wp_parse_args($settings, self::defaults()));
    }
}
}

register_activation_hook(__FILE__, ['ARK_Custom_Wishlist', 'activate']);
add_action('plugins_loaded', function () {
    if (class_exists('WooCommerce')) {
        new ARK_Custom_Wishlist();
    } elseif (is_admin() && current_user_can('activate_plugins')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-warning"><p>' . esc_html__('ARK Custom Wishlist requires WooCommerce.', 'ark-custom-wishlist') . '</p></div>';
        });
    }
});
