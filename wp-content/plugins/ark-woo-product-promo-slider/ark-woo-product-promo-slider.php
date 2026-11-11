<?php
/**
 * Plugin Name: ARK Woo Product Promo Slider
 * Description: Responsive, product/category-targeted sponsored product slider for WooCommerce product pages.
 * Version: 1.1.2
 * Author: ARK
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * Text Domain: ark-woo-promo-slider
 */

defined('ABSPATH') || exit;

if (!class_exists('ARK_Woo_Product_Promo_Slider')) {
    final class ARK_Woo_Product_Promo_Slider {
        const VERSION = '1.1.2';
        const CPT = 'ark_promo_campaign';
        const NONCE = 'ark_promo_save_campaign';

        public function __construct() {
            add_action('init', [$this, 'register_campaign_type']);
            add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
            add_action('save_post_' . self::CPT, [$this, 'save_campaign'], 10, 2);
            add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
            add_action('wp_enqueue_scripts', [$this, 'frontend_assets']);
            add_action('wp_footer', [$this, 'render_slider'], 2);
            add_filter('ark_native_product_promos', [$this, 'native_campaigns_for_product'], 10, 2);
            add_action('wp_ajax_ark_promo_search_products', [$this, 'ajax_search_products']);
        }

        public function register_campaign_type() {
            register_post_type(self::CPT, [
                'labels' => [
                    'name' => __('Product Promo Sliders', 'ark-woo-promo-slider'),
                    'singular_name' => __('Promo Campaign', 'ark-woo-promo-slider'),
                    'add_new_item' => __('Add Promo Campaign', 'ark-woo-promo-slider'),
                    'edit_item' => __('Edit Promo Campaign', 'ark-woo-promo-slider'),
                    'menu_name' => __('Product Promo Slider', 'ark-woo-promo-slider'),
                ],
                'public' => false,
                'show_ui' => true,
                'show_in_menu' => true,
                'menu_icon' => 'dashicons-images-alt2',
                'supports' => ['title'],
                'capability_type' => 'post',
                'map_meta_cap' => true,
            ]);
        }

        public function add_meta_boxes() {
            add_meta_box(
                'ark_promo_rules',
                __('Slider settings', 'ark-woo-promo-slider'),
                [$this, 'render_meta_box'],
                self::CPT,
                'normal',
                'high'
            );
        }

        private function meta($post_id, $key, $default = '') {
            $value = get_post_meta($post_id, $key, true);
            return $value === '' ? $default : $value;
        }

        public function render_meta_box($post) {
            if (!current_user_can('edit_post', $post->ID)) {
                return;
            }

            wp_nonce_field(self::NONCE, '_ark_promo_nonce');

            $enabled = $this->meta($post->ID, '_ark_promo_enabled', '1');
            $target_categories = array_map('absint', (array) $this->meta($post->ID, '_ark_promo_target_categories', []));
            $target_products = array_map('absint', (array) $this->meta($post->ID, '_ark_promo_target_products', []));
            $promo_products = array_map('absint', (array) $this->meta($post->ID, '_ark_promo_products', []));
            $apply_categories = $this->meta($post->ID, '_ark_promo_apply_categories', '0');
            $start = $this->meta($post->ID, '_ark_promo_start', '');
            $end = $this->meta($post->ID, '_ark_promo_end', '');
            $heading = $this->meta($post->ID, '_ark_promo_heading', 'Recommended offers');
            $speed = absint($this->meta($post->ID, '_ark_promo_speed', 3500));
            $limit = absint($this->meta($post->ID, '_ark_promo_limit', 8));

            $cats = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
            if (is_wp_error($cats)) {
                $cats = [];
            }

            $target_products_data = $this->get_products_by_ids($target_products);
            $promo_products_data = $this->get_products_by_ids($promo_products);
            ?>
            <style>
                .ark-promo-field{margin:16px 0}
                .ark-promo-field>label{display:block;font-weight:600;margin-bottom:6px}
                .ark-promo-field input[type=text],.ark-promo-field input[type=number],.ark-promo-field input[type=datetime-local]{max-width:520px;width:100%}
                .ark-promo-multi{width:100%;max-width:620px;min-height:125px}
                .ark-promo-help{color:#646970;font-size:12px}
                .ark-promo-product-search{max-width:620px;width:100%;padding:8px}
                .ark-promo-chips{display:flex;flex-wrap:wrap;gap:6px;margin:8px 0}
                .ark-promo-chip{background:#eef4ff;border:1px solid #c9dcff;border-radius:14px;padding:4px 8px}
                .ark-promo-chip button{border:0;background:transparent;color:#a00;cursor:pointer}
            </style>

            <div class="ark-promo-field">
                <label><input type="checkbox" name="ark_promo_enabled" value="1" <?php checked($enabled, '1'); ?>> <?php esc_html_e('Enable this campaign', 'ark-woo-promo-slider'); ?></label>
            </div>

            <div class="ark-promo-field">
                <label for="ark_promo_heading"><?php esc_html_e('Slider heading', 'ark-woo-promo-slider'); ?></label>
                <input id="ark_promo_heading" type="text" name="ark_promo_heading" value="<?php echo esc_attr($heading); ?>" maxlength="90">
            </div>

            <hr>
            <h3><?php esc_html_e('Where should this campaign appear?', 'ark-woo-promo-slider'); ?></h3>
            <p class="ark-promo-help"><?php esc_html_e('Individual product pages are an exact allow-list. When none are selected, the promoted products below become the default pages. Category-wide display is opt-in.', 'ark-woo-promo-slider'); ?></p>

            <div class="ark-promo-field">
                <label for="ark_promo_target_categories"><?php esc_html_e('Target product categories', 'ark-woo-promo-slider'); ?></label>
                <select class="ark-promo-multi" multiple id="ark_promo_target_categories" name="ark_promo_target_categories[]">
                    <?php foreach ($cats as $cat) : ?>
                        <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected(in_array((int) $cat->term_id, $target_categories, true)); ?>><?php echo esc_html($cat->name); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="ark-promo-help"><?php esc_html_e('Use Ctrl (Windows) or Cmd (Mac) to select multiple categories.', 'ark-woo-promo-slider'); ?></p>
            </div>

            <div class="ark-promo-field">
                <label><?php esc_html_e('Target individual product pages (optional)', 'ark-woo-promo-slider'); ?></label>
                <input type="search" class="ark-promo-product-search" data-target="target" placeholder="<?php esc_attr_e('Search products by name…', 'ark-woo-promo-slider'); ?>">
                <div class="ark-promo-results" data-results="target"></div>
                <div class="ark-promo-chips" data-chips="target">
                    <?php foreach ($target_products_data as $p) : ?>
                        <span class="ark-promo-chip" data-id="<?php echo esc_attr($p->get_id()); ?>">
                            <?php echo esc_html($p->get_name()); ?>
                            <button type="button" aria-label="<?php esc_attr_e('Remove product', 'ark-woo-promo-slider'); ?>">×</button>
                            <input type="hidden" name="ark_promo_target_products[]" value="<?php echo esc_attr($p->get_id()); ?>">
                        </span>
                    <?php endforeach; ?>
                </div>
                <p class="ark-promo-help"><?php esc_html_e('Select the exact product pages where this campaign may appear. If this list is empty, only the promoted products below will be used as page targets.', 'ark-woo-promo-slider'); ?></p>
            </div>

            <div class="ark-promo-field">
                <label>
                    <input type="checkbox" name="ark_promo_apply_categories" value="1" <?php checked($apply_categories, '1'); ?>>
                    <?php esc_html_e('Also show on ALL products in the selected categories (category-wide campaign)', 'ark-woo-promo-slider'); ?>
                </label>
                <p class="ark-promo-help"><?php esc_html_e('Leave unchecked to prevent ads from appearing on unrelated products in the same category.', 'ark-woo-promo-slider'); ?></p>
            </div>

            <hr>
            <h3><?php esc_html_e('Which products should slide as ads?', 'ark-woo-promo-slider'); ?></h3>
            <div class="ark-promo-field">
                <label><?php esc_html_e('Promoted products', 'ark-woo-promo-slider'); ?></label>
                <input type="search" class="ark-promo-product-search" data-target="promo" placeholder="<?php esc_attr_e('Search products to add to slider…', 'ark-woo-promo-slider'); ?>">
                <div class="ark-promo-results" data-results="promo"></div>
                <div class="ark-promo-chips" data-chips="promo">
                    <?php foreach ($promo_products_data as $p) : ?>
                        <span class="ark-promo-chip" data-id="<?php echo esc_attr($p->get_id()); ?>">
                            <?php echo esc_html($p->get_name()); ?>
                            <button type="button" aria-label="<?php esc_attr_e('Remove product', 'ark-woo-promo-slider'); ?>">×</button>
                            <input type="hidden" name="ark_promo_products[]" value="<?php echo esc_attr($p->get_id()); ?>">
                        </span>
                    <?php endforeach; ?>
                </div>
                <p class="ark-promo-help"><?php esc_html_e('Published products only. The current product is automatically excluded.', 'ark-woo-promo-slider'); ?></p>
            </div>

            <div class="ark-promo-field">
                <label for="ark_promo_limit"><?php esc_html_e('Maximum cards in slider', 'ark-woo-promo-slider'); ?></label>
                <input id="ark_promo_limit" type="number" name="ark_promo_limit" min="1" max="20" value="<?php echo esc_attr(max(1, min(20, $limit))); ?>">
            </div>

            <div class="ark-promo-field">
                <label for="ark_promo_speed"><?php esc_html_e('Auto-slide interval (milliseconds)', 'ark-woo-promo-slider'); ?></label>
                <input id="ark_promo_speed" type="number" name="ark_promo_speed" min="0" max="15000" step="500" value="<?php echo esc_attr(max(0, min(15000, $speed))); ?>">
                <p class="ark-promo-help"><?php esc_html_e('0 disables auto-slide. Recommended: 3500–5000 ms.', 'ark-woo-promo-slider'); ?></p>
            </div>

            <div class="ark-promo-field">
                <label for="ark_promo_start"><?php esc_html_e('Campaign starts (optional)', 'ark-woo-promo-slider'); ?></label>
                <input id="ark_promo_start" type="datetime-local" name="ark_promo_start" value="<?php echo esc_attr($start); ?>">
            </div>

            <div class="ark-promo-field">
                <label for="ark_promo_end"><?php esc_html_e('Campaign ends (optional)', 'ark-woo-promo-slider'); ?></label>
                <input id="ark_promo_end" type="datetime-local" name="ark_promo_end" value="<?php echo esc_attr($end); ?>">
            </div>
            <?php
        }

        public function save_campaign($post_id, $post) {
            if (!isset($_POST['_ark_promo_nonce'])) {
                return;
            }

            $nonce = sanitize_text_field(wp_unslash($_POST['_ark_promo_nonce']));
            if (!wp_verify_nonce($nonce, self::NONCE)) {
                return;
            }

            if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id) || !$post || $post->post_type !== self::CPT) {
                return;
            }

            if (!current_user_can('edit_post', $post_id)) {
                return;
            }

            update_post_meta($post_id, '_ark_promo_enabled', isset($_POST['ark_promo_enabled']) ? '1' : '0');

            $heading = isset($_POST['ark_promo_heading'])
                ? sanitize_text_field(wp_unslash($_POST['ark_promo_heading']))
                : 'Recommended offers';
            if (function_exists('mb_substr')) {
                $heading = mb_substr($heading, 0, 90);
            } else {
                $heading = substr($heading, 0, 90);
            }
            update_post_meta($post_id, '_ark_promo_heading', $heading);

            $categories = isset($_POST['ark_promo_target_categories'])
                ? array_values(array_unique(array_filter(array_map('absint', (array) wp_unslash($_POST['ark_promo_target_categories'])))))
                : [];
            $valid_categories = [];
            foreach ($categories as $cat_id) {
                if ($cat_id && term_exists($cat_id, 'product_cat')) {
                    $valid_categories[] = $cat_id;
                }
            }
            update_post_meta($post_id, '_ark_promo_target_categories', $valid_categories);

            foreach (['_ark_promo_target_products' => 'ark_promo_target_products', '_ark_promo_products' => 'ark_promo_products'] as $meta_key => $field) {
                $ids = isset($_POST[$field])
                    ? array_values(array_unique(array_filter(array_map('absint', (array) wp_unslash($_POST[$field])))))
                    : [];
                $valid = [];
                foreach ($ids as $id) {
                    if ($id && get_post_type($id) === 'product' && get_post_status($id) === 'publish') {
                        $valid[] = $id;
                    }
                }
                update_post_meta($post_id, $meta_key, $valid);
            }
            update_post_meta($post_id, '_ark_promo_apply_categories', isset($_POST['ark_promo_apply_categories']) ? '1' : '0');

            update_post_meta($post_id, '_ark_promo_limit', max(1, min(20, isset($_POST['ark_promo_limit']) ? absint($_POST['ark_promo_limit']) : 8)));
            update_post_meta($post_id, '_ark_promo_speed', max(0, min(15000, isset($_POST['ark_promo_speed']) ? absint($_POST['ark_promo_speed']) : 3500)));

            foreach (['_ark_promo_start' => 'ark_promo_start', '_ark_promo_end' => 'ark_promo_end'] as $key => $field) {
                $value = isset($_POST[$field]) ? sanitize_text_field(wp_unslash($_POST[$field])) : '';
                if ($value !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)) {
                    $value = '';
                }
                update_post_meta($post_id, $key, $value);
            }
        }

        public function admin_assets($hook) {
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            if (!$screen || $screen->post_type !== self::CPT) {
                return;
            }

            wp_enqueue_script(
                'ark-promo-admin',
                plugin_dir_url(__FILE__) . 'assets/admin.js',
                ['jquery'],
                self::VERSION,
                true
            );

            wp_localize_script('ark-promo-admin', 'ARKPromoAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ark_promo_search_products'),
                'searching' => __('Searching…', 'ark-woo-promo-slider'),
                'noResults' => __('No products found.', 'ark-woo-promo-slider'),
                'error' => __('Search failed. Please try again.', 'ark-woo-promo-slider'),
                'add' => __('Add', 'ark-woo-promo-slider'),
            ]);
        }

        public function ajax_search_products() {
            if (!current_user_can('edit_products')) {
                wp_send_json_error(['message' => 'Forbidden'], 403);
            }

            check_ajax_referer('ark_promo_search_products', 'nonce');

            if (!function_exists('wc_get_products')) {
                wp_send_json_error(['message' => 'WooCommerce is not active'], 400);
            }

            $term = isset($_GET['term']) ? sanitize_text_field(wp_unslash($_GET['term'])) : '';
            if (function_exists('mb_strlen') ? mb_strlen($term) < 2 : strlen($term) < 2) {
                wp_send_json_success([]);
            }

            $products = wc_get_products([
                'status' => 'publish',
                'limit' => 15,
                's' => $term,
                'orderby' => 'title',
                'order' => 'ASC',
                'return' => 'objects',
            ]);

            $out = [];
            foreach ($products as $product) {
                if (!$product instanceof WC_Product) {
                    continue;
                }
                $out[] = [
                    'id' => $product->get_id(),
                    'name' => wp_strip_all_tags($product->get_name()),
                ];
            }

            wp_send_json_success($out);
        }

        private function get_products_by_ids($ids) {
            if (!function_exists('wc_get_product')) {
                return [];
            }

            $out = [];
            $ids = array_slice(array_unique(array_map('absint', (array) $ids)), 0, 100);

            foreach ($ids as $id) {
                $product = wc_get_product($id);
                if ($product && $product->get_status() === 'publish') {
                    $out[] = $product;
                }
            }
            return $out;
        }

        private function campaign_matches($campaign_id, $product_id) {
            if (get_post_meta($campaign_id, '_ark_promo_enabled', true) !== '1' || get_post_status($campaign_id) !== 'publish') {
                return false;
            }

            $timezone = wp_timezone();
            $now = current_datetime();
            $start = get_post_meta($campaign_id, '_ark_promo_start', true);
            $end = get_post_meta($campaign_id, '_ark_promo_end', true);

            if ($start !== '') {
                $start_date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $start, $timezone);
                if ($start_date && $now < $start_date) {
                    return false;
                }
            }

            if ($end !== '') {
                $end_date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $end, $timezone);
                if ($end_date && $now >= $end_date->modify('+1 minute')) {
                    return false;
                }
            }

            $target_products = array_map('absint', (array) get_post_meta($campaign_id, '_ark_promo_target_products', true));
            $target_categories = array_map('absint', (array) get_post_meta($campaign_id, '_ark_promo_target_categories', true));
            $promo_products = array_map('absint', (array) get_post_meta($campaign_id, '_ark_promo_products', true));
            $apply_categories = get_post_meta($campaign_id, '_ark_promo_apply_categories', true) === '1';

            // Page targeting must never broaden accidentally:
            // 1) Explicit target products are an exact allow-list.
            // 2) Category targeting is broad only after the admin opts in.
            // 3) Otherwise, the promoted products themselves are the page allow-list.
            if (!empty($target_products)) {
                return in_array((int) $product_id, $target_products, true);
            }

            if ($apply_categories && !empty($target_categories)) {
                $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
                return !is_wp_error($product_categories)
                    && (bool) array_intersect($target_categories, array_map('absint', $product_categories));
            }

            return in_array((int) $product_id, $promo_products, true);
        }

        /**
         * Expose the same configured campaigns to the ARK Native App API bridge.
         * The bridge applies this filter to its product-data response, so the
         * app and the WooCommerce product page use one campaign targeting rule.
         */
        public function native_campaigns_for_product($existing, $product_id) {
            if (!function_exists('wc_get_product')) return is_array($existing) ? $existing : [];
            $product_id = absint($product_id);
            $product = wc_get_product($product_id);
            if (!$product || $product->get_status() !== 'publish') return [];

            $campaigns = get_posts([
                'post_type' => self::CPT,
                'post_status' => 'publish',
                'posts_per_page' => 20,
                'orderby' => ['menu_order' => 'ASC', 'date' => 'DESC'],
                'no_found_rows' => true,
                'meta_query' => [['key' => '_ark_promo_enabled', 'value' => '1']],
            ]);
            $result = [];
            foreach ($campaigns as $campaign) {
                if (!$this->campaign_matches($campaign->ID, $product_id)) continue;
                $ids = array_map('absint', (array) get_post_meta($campaign->ID, '_ark_promo_products', true));
                $limit = max(1, min(20, absint(get_post_meta($campaign->ID, '_ark_promo_limit', true) ?: 8)));
                $cards = [];
                foreach (array_slice(array_unique($ids), 0, 100) as $promo_id) {
                    if ($promo_id === $product_id) continue;
                    $promo = wc_get_product($promo_id);
                    if (!$promo || $promo->get_status() !== 'publish' || (method_exists($promo, 'is_visible') && !$promo->is_visible())) continue;
                    $regular = (float) $promo->get_regular_price();
                    $sale = (float) $promo->get_price();
                    $discount = ($regular > 0 && $sale > 0 && $sale < $regular)
                        ? (int) round((($regular - $sale) / $regular) * 100) : 0;
                    $cards[] = [
                        'id' => (int) $promo->get_id(),
                        'name' => wp_strip_all_tags($promo->get_name()),
                        'image' => wp_get_attachment_image_url($promo->get_image_id(), 'woocommerce_thumbnail') ?: '',
                        'permalink' => get_permalink($promo->get_id()),
                        'regular_price' => (string) $regular,
                        'sale_price' => (string) $sale,
                        'price_html' => trim(html_entity_decode(wp_strip_all_tags($promo->get_price_html()), ENT_QUOTES, 'UTF-8')),
                        'discount_percent' => $discount,
                    ];
                    if (count($cards) >= $limit) break;
                }
                if ($cards) {
                    $result[] = [
                        'campaign_id' => (int) $campaign->ID,
                        'heading' => sanitize_text_field(get_post_meta($campaign->ID, '_ark_promo_heading', true) ?: 'Recommended offers'),
                        'speed' => max(0, min(15000, absint(get_post_meta($campaign->ID, '_ark_promo_speed', true) ?: 0))),
                        'products' => $cards,
                    ];
                }
            }
            return $result;
        }

        public function frontend_assets() {
            if (!function_exists('is_product') || !is_product()) {
                return;
            }

            $base_url = plugin_dir_url(__FILE__);
            wp_enqueue_style('ark-woo-promo-slider', $base_url . 'assets/frontend.css', [], self::VERSION);
            wp_enqueue_script('ark-woo-promo-slider', $base_url . 'assets/frontend.js', [], self::VERSION, true);
        }

        public function render_slider() {
            if (!function_exists('is_product') || !is_product() || !function_exists('wc_get_product')) {
                return;
            }

            $current_id = absint(get_queried_object_id());
            if (!$current_id) {
                return;
            }

            $campaigns = get_posts([
                'post_type' => self::CPT,
                'post_status' => 'publish',
                'posts_per_page' => 20,
                'orderby' => ['menu_order' => 'ASC', 'date' => 'DESC'],
                'no_found_rows' => true,
                'meta_query' => [
                    ['key' => '_ark_promo_enabled', 'value' => '1'],
                ],
            ]);

            foreach ($campaigns as $campaign) {
                if (!$this->campaign_matches($campaign->ID, $current_id)) {
                    continue;
                }

                $ids = array_map('absint', (array) get_post_meta($campaign->ID, '_ark_promo_products', true));
                $limit = max(1, min(20, absint(get_post_meta($campaign->ID, '_ark_promo_limit', true) ?: 8)));
                $speed = max(0, min(15000, absint(get_post_meta($campaign->ID, '_ark_promo_speed', true) ?: 0)));
                $products = [];

                foreach (array_slice(array_unique($ids), 0, 100) as $id) {
                    if ($id === $current_id) {
                        continue;
                    }

                    $product = wc_get_product($id);
                    if (!$product || $product->get_status() !== 'publish') {
                        continue;
                    }

                    $products[] = $product;
                    if (count($products) >= $limit) {
                        break;
                    }
                }

                if (!$products) {
                    continue;
                }

                $heading = get_post_meta($campaign->ID, '_ark_promo_heading', true);
                if (!$heading) {
                    $heading = __('Recommended offers', 'ark-woo-promo-slider');
                }

                $instance_id = 'ark-promo-slider-' . absint($campaign->ID) . '-' . $current_id;
                ?>
                <section class="ark-promo-slider" id="<?php echo esc_attr($instance_id); ?>" data-speed="<?php echo esc_attr($speed); ?>" aria-label="<?php echo esc_attr($heading); ?>">
                    <div class="ark-promo-slider-head">
                        <h2><?php echo esc_html($heading); ?></h2>
                        <span class="ark-promo-ad-label" aria-label="<?php esc_attr_e('Advertisement', 'ark-woo-promo-slider'); ?>">Ad</span>
                        <div class="ark-promo-controls">
                            <button type="button" class="ark-promo-prev" aria-label="<?php esc_attr_e('Previous products', 'ark-woo-promo-slider'); ?>">‹</button>
                            <button type="button" class="ark-promo-next" aria-label="<?php esc_attr_e('Next products', 'ark-woo-promo-slider'); ?>">›</button>
                        </div>
                    </div>

                    <div class="ark-promo-track" tabindex="0">
                        <?php foreach ($products as $product) :
                            $product_id = $product->get_id();
                            $link = get_permalink($product_id);
                            $image = $product->get_image('woocommerce_thumbnail', [
                                'loading' => 'lazy',
                                'alt' => $product->get_name(),
                            ]);

                            $regular_price = (float) $product->get_regular_price();
                            $sale_price = (float) $product->get_price();
                            $discount = 0;

                            if ($regular_price > 0 && $sale_price > 0 && $sale_price < $regular_price) {
                                $discount = (int) round((($regular_price - $sale_price) / $regular_price) * 100);
                            }
                            ?>
                            <a class="ark-promo-card" href="<?php echo esc_url($link); ?>">
                                <span class="ark-promo-image">
                                    <?php echo wp_kses_post($image); ?>
                                </span>

                                <span class="ark-promo-info">
                                    <span class="ark-promo-name"><?php echo esc_html($product->get_name()); ?></span>
                                    <span class="ark-promo-details">
                                        <span class="ark-promo-price"><?php echo wp_kses_post($product->get_price_html()); ?></span>
                                        <?php if ($discount > 0) : ?>
                                            <span class="ark-promo-discount">↓ <?php echo esc_html($discount); ?>% OFF</span>
                                        <?php endif; ?>
                                    </span>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php
            }
        }
    }
}

add_action('plugins_loaded', function () {
    if (class_exists('WooCommerce')) {
        new ARK_Woo_Product_Promo_Slider();
        return;
    }

    add_action('admin_notices', function () {
        if (current_user_can('activate_plugins')) {
            echo '<div class="notice notice-warning"><p>' .
                esc_html__('ARK Woo Product Promo Slider requires WooCommerce to be active.', 'ark-woo-promo-slider') .
                '</p></div>';
        }
    });
});
