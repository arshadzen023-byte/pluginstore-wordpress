<?php
/**
 * Plugin Name: ARK App Designer
 * Description: Server-driven visual app designer for ARK Native / GlowHit Flutter apps, including live layout, navigation, theme and in-app splash configuration.
 * Version: 1.17.4
 * Author: ARK
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

final class ARK_App_Designer {
    const VERSION = '1.17.4';
    const PUBLISHED = 'ark_app_designer_published';
    const DRAFT = 'ark_app_designer_draft';
    const REVISIONS = 'ark_app_designer_revisions';
    const GITHUB_SETTINGS = 'ark_app_designer_github_settings';
    const REST_NAMESPACE = 'ark-designer/v1';

    public function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('init', [$this, 'maybe_upgrade_schema'], 5);
        if (get_option(self::PUBLISHED, null) === null) {
            update_option(self::PUBLISHED, self::default_config(), false);
        }
    }

    public static function activate() {
        if (get_option(self::PUBLISHED, null) === null) {
            update_option(self::PUBLISHED, self::default_config(), false);
        }
        if (get_option(self::DRAFT, null) === null) {
            update_option(self::DRAFT, self::default_config(), false);
        }
        self::create_live_slider_tables();
        update_option('ark_app_designer_live_slider_schema', 1, false);
    }

    public static function default_config() {
        return [
            'schema_version' => 3,
            'version' => 1,
            'updated_at' => gmdate('c'),
            'app' => ['name' => 'GlowHit', 'tagline' => 'Shop smarter', 'icon_url' => '', 'logo_url' => '', 'show_icon' => true, 'show_name' => true, 'show_refresh' => true, 'show_notifications' => true, 'show_cart' => true, 'header_icon_animation' => 'none', 'splash_image_url' => '', 'splash_enabled' => true, 'splash_duration_ms' => 1800, 'splash_fit' => 'contain', 'splash_background' => '#F5F7FB'],
            'default_heading' => [
                'enabled' => true, 'visible_pages' => ['home','categories','wishlist','account'],
                'show_app_logo' => true, 'show_app_name' => true, 'show_icon' => false, 'icon_name' => 'storefront',
                'show_title' => true, 'title' => '', 'show_button' => false, 'button_label' => 'Explore',
                'button_icon' => 'arrow_forward', 'button_link_type' => 'in_app', 'button_url' => 'page:categories',
                'background_color' => '#FFFFFF', 'background_image_url' => '', 'show_background_image' => false,
                'background_image_fit' => 'cover', 'background_image_opacity' => 100, 'title_color' => '#202733', 'icon_color' => '#202733',
                'button_background' => '#2874F0', 'button_text_color' => '#FFFFFF', 'height' => 64, 'animation' => 'none', 'background_effect' => 'none', 'background_image_id' => 0, 'position_index' => -1,
                'show_mini_button' => false, 'mini_button_label' => 'More', 'mini_button_icon' => 'chevron_right', 'mini_button_link_type' => 'in_app', 'mini_button_url' => '', 'mini_button_background' => '#FFFFFF', 'mini_button_text_color' => '#2874F0',
            ],
            
            'theme' => [
                'primary' => '#2874F0',
                'accent' => '#FFD814',
                'background' => '#F5F7FB',
                'surface' => '#FFFFFF',
                'text' => '#202733',
                'muted' => '#727985',
                'header_background' => '#FFFFFF',
                'header_text' => '#202733',
                'header_animation' => 'none',
                'navigation_background' => '#F0EFF5',
                'navigation_indicator' => '#DDE4FF',
                'cart_button_background' => '#FFFFFF',
                'cart_button_text' => '#496292',
                'buy_button_background' => '#FFD814',
                'buy_button_text' => '#202733',
                'buy_button_price' => '#202733',
                'radius' => 18,
                'font_scale' => 1.0,
            ],
            'splash' => [
                'enabled' => true,
                'image_url' => '',
                'duration_ms' => 1800,
                'fit' => 'contain',
                'background' => '#F5F7FB',
            ],
            'loading' => [
                'enabled' => true,
                'animation_url' => '',
                'duration_ms' => 900,
                'size' => 58,
                'pages' => ['home','categories','product','account'],
            ],
            'celebration' => [
                'enabled' => false,
                'duration_ms' => 4200,
                'pages' => ['home'],
            ],
            'navigation' => [
                'visible' => true,
                'items' => [
                    ['key' => 'home', 'label' => 'Home', 'icon' => 'home'],
                    ['key' => 'categories', 'label' => 'Categories', 'icon' => 'category'],
                    ['key' => 'wishlist', 'label' => 'Wishlist', 'icon' => 'favorite'],
                    ['key' => 'account', 'label' => 'Account', 'icon' => 'person'],
                ],
            ],
            'pages' => [
                'home' => ['title' => 'Home', 'sections' => [
                    ['id' => 'home-search', 'type' => 'search', 'visible' => true, 'title' => 'Search products', 'placeholder' => 'Search products...', 'open_search_page' => true, 'show_voice_search' => true, 'show_camera_search' => true],
                    ['id' => 'home-hero', 'type' => 'ark_live_slider', 'slider_key' => 'home-hero', 'visible' => true, 'title' => 'Featured'],
                    ['id' => 'home-cats', 'type' => 'category_grid', 'visible' => true, 'title' => 'Shop by category', 'limit' => 8, 'columns' => 4, 'style' => 'rounded'],
                    ['id' => 'home-featured', 'type' => 'product_carousel', 'visible' => true, 'title' => 'Featured products', 'source' => 'featured', 'limit' => 10],
                    ['id' => 'home-offer', 'type' => 'offer_banner', 'visible' => true, 'title' => 'Special offers', 'subtitle' => 'Explore today’s best deals', 'button_label' => 'Shop now', 'button_url' => ''],
                    ['id' => 'home-recent', 'type' => 'recently_viewed', 'visible' => true, 'title' => 'Recently viewed', 'limit' => 8],
                    ['id' => 'home-products', 'type' => 'product_grid', 'visible' => true, 'title' => 'Shop products', 'source' => 'latest', 'limit' => 12, 'columns' => 2],
                ]],
                'search' => ['title' => 'Search', 'search_settings' => ['placeholder' => 'Search products...', 'show_voice_search' => true, 'show_camera_search' => true, 'show_cart' => true, 'voice_locale' => 'en_IN', 'top_bar_background' => '#D7E6FF', 'search_bar_background' => '#FFFFFF', 'suggestion_limit' => 8], 'sections' => [
                    ['id' => 'search-recent', 'type' => 'recent_searches', 'visible' => true, 'title' => 'Recent Searches', 'limit' => 8],
                    ['id' => 'search-recommended', 'type' => 'product_carousel', 'visible' => true, 'title' => 'Recommended Stores', 'source' => 'featured', 'limit' => 8],
                    ['id' => 'search-trending', 'type' => 'product_carousel', 'visible' => true, 'title' => 'Trending Today', 'source' => 'latest', 'limit' => 12],
                ]],
                'categories' => ['title' => 'Categories', 'sections' => [
                    ['id' => 'categories-grid', 'type' => 'category_grid', 'visible' => true, 'title' => 'All categories', 'limit' => 100, 'columns' => 2],
                ]],
                'product' => ['title' => 'Product', 'sections' => [
                    ['id' => 'product-gallery', 'type' => 'product_gallery', 'visible' => true, 'title' => 'Product images'],
                    ['id' => 'product-title', 'type' => 'product_title', 'visible' => true, 'title' => 'Product title'],
                    ['id' => 'product-price', 'type' => 'product_price', 'visible' => true, 'title' => 'Price and offers'],
                    ['id' => 'product-delivery', 'type' => 'product_delivery', 'visible' => true, 'title' => 'Delivery information'],
                    ['id' => 'product-description', 'type' => 'product_description', 'visible' => true, 'title' => 'Description'],
                    ['id' => 'product-specs', 'type' => 'product_specs', 'visible' => true, 'title' => 'Specifications'],
                    ['id' => 'product-reviews', 'type' => 'product_reviews', 'visible' => true, 'title' => 'Ratings and reviews'],
                    ['id' => 'product-offers', 'type' => 'product_offers', 'visible' => true, 'title' => 'Offers'],
                    ['id' => 'product-recommended', 'type' => 'product_recommended', 'visible' => true, 'title' => 'Recommended offers'],
                    ['id' => 'product-similar', 'type' => 'product_carousel', 'visible' => true, 'title' => 'Similar products', 'source' => 'related', 'limit' => 8],
                ]],
                'account' => ['title' => 'Account', 'sections' => [
                    ['id' => 'account-profile', 'type' => 'account_profile', 'visible' => true, 'title' => 'Your account'],
                    ['id' => 'account-orders', 'type' => 'account_menu', 'visible' => true, 'title' => 'Account options'],
                ]],
                'wishlist' => ['title' => 'Wishlist', 'sections' => [
                    ['id' => 'wishlist-products', 'type' => 'product_grid', 'visible' => true, 'title' => 'Saved products', 'source' => 'wishlist', 'limit' => 50, 'columns' => 2],
                ]],
            ],
        ];
    }

    public function admin_menu() {
        add_menu_page(
            'ARK App Designer',
            'ARK App Designer',
            'manage_options',
            'ark-app-designer',
            [$this, 'render_admin'],
            'dashicons-smartphone',
            57
        );
    }

    public function admin_assets($hook) {
        if ($hook !== 'toplevel_page_ark-app-designer') return;
        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-sortable');
        $css_path = plugin_dir_path(__FILE__) . 'assets/admin.css';
        $js_path = plugin_dir_path(__FILE__) . 'assets/admin.js';
        $css_version = file_exists($css_path) ? self::VERSION . '.' . filemtime($css_path) : self::VERSION;
        $js_version = file_exists($js_path) ? self::VERSION . '.' . filemtime($js_path) : self::VERSION;
        wp_enqueue_style('ark-app-designer-admin', plugin_dir_url(__FILE__) . 'assets/admin.css', [], $css_version);
        wp_enqueue_script('ark-app-designer-admin', plugin_dir_url(__FILE__) . 'assets/admin.js', ['jquery'], $js_version, true);
        $config = $this->current_editor_config();
        wp_localize_script('ark-app-designer-admin', 'ARKDesigner', [
            'restUrl' => esc_url_raw(rest_url(self::REST_NAMESPACE . '/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'config' => $config,
            'components' => self::component_catalog(),
        ]);
    }

    public static function component_catalog() {
        return [
            'heading' => ['label' => 'Heading', 'icon' => 'H', 'description' => 'Section title and subtitle'],
            'search' => ['label' => 'Search bar', 'icon' => '⌕', 'description' => 'Native search page with live product suggestions, voice and camera OCR'],
            'recent_searches' => ['label' => 'Recent searches', 'icon' => '◷', 'description' => 'Recent search history'],
            'search_suggestions' => ['label' => 'Search suggestions', 'icon' => '⌕', 'description' => 'Popular searches'],
            'notice_bar' => ['label' => 'Announcement strip', 'icon' => '▰', 'description' => 'Compact store message with optional link'],
            'icon_grid' => ['label' => 'Icon shortcuts', 'icon' => '⊞', 'description' => 'Grid of icon links and quick actions'],
            'countdown' => ['label' => 'Countdown card', 'icon' => '◷', 'description' => 'Live countdown to a selected deadline'],
            'ark_live_slider' => ['label' => 'ARK Live Slider', 'icon' => '▧', 'description' => 'SQL-managed image campaigns with native links and scheduled slides'],
            'banner_grid' => ['label' => 'Banner Grid', 'icon' => '▦', 'description' => 'SQL-backed banner images in a responsive grid'],
            'banner_list' => ['label' => 'Banner List', 'icon' => '☷', 'description' => 'SQL-backed banner images in a vertical list'],
            'banner_slider' => ['label' => 'Banner Slider', 'icon' => '▤', 'description' => 'SQL-backed auto-sliding banner images'],
            'banner_scroll' => ['label' => 'Banner Scroll', 'icon' => '⇆', 'description' => 'SQL-backed horizontal banner rail'],
            'category_grid' => ['label' => 'Category Grid', 'icon' => '▦', 'description' => 'Live WooCommerce category cards'],
            'category_list' => ['label' => 'Category List', 'icon' => '☷', 'description' => 'Live WooCommerce categories in a vertical list'],
            'category_list_tile' => ['label' => 'Category List Tile', 'icon' => '▤', 'description' => 'Live category rows with image, title and count'],
            'category_scroll' => ['label' => 'Category Scroll', 'icon' => '⇆', 'description' => 'Live horizontally scrolling categories'],
            'category_slider' => ['label' => 'Category Slider', 'icon' => '▥', 'description' => 'Live swipeable category carousel'],
            'product_carousel' => ['label' => 'Product Slider', 'icon' => '▤', 'description' => 'Featured, latest, sale or category products'],
            'product_grid' => ['label' => 'Product Grid', 'icon' => '▦', 'description' => 'Responsive WooCommerce product cards'],
            'product_list' => ['label' => 'Product List', 'icon' => '☷', 'description' => 'WooCommerce products in compact horizontal list rows'],
            'product_scroll' => ['label' => 'Product Scroll', 'icon' => '⇆', 'description' => 'Swipeable WooCommerce product card rail'],
            'offer_banner' => ['label' => 'Offer card', 'icon' => '％', 'description' => 'Campaign card with button'],
            'recently_viewed' => ['label' => 'Recently viewed', 'icon' => '◷', 'description' => 'Device-local product history'],
            'image' => ['label' => 'Image', 'icon' => '▧', 'description' => 'Image with optional link'],
            'text' => ['label' => 'Text block', 'icon' => 'T', 'description' => 'Rich text content (safe plain text)'],
            'button' => ['label' => 'Button', 'icon' => '↗', 'description' => 'Action button or deep link'],
            'spacer' => ['label' => 'Spacing', 'icon' => '↕', 'description' => 'Vertical spacing'],
            'divider' => ['label' => 'Divider', 'icon' => '—', 'description' => 'Thin section separator'],
            'trust_badges' => ['label' => 'Trust badges', 'icon' => '✓', 'description' => 'Editable delivery, payment and support badges'],
            'faq' => ['label' => 'FAQ accordion', 'icon' => '?', 'description' => 'Expandable question and answer list'],
            'coupon_banner' => ['label' => 'Coupon card', 'icon' => '％', 'description' => 'Coupon code with copy action'],
            'spacer' => ['label' => 'Spacing', 'icon' => '↕', 'description' => 'Vertical spacing'],
            'product_gallery' => ['label' => 'Product gallery', 'icon' => '▧', 'description' => 'Auto-sliding product images with full-screen zoom and floating actions'],
            'product_title' => ['label' => 'Product title', 'icon' => 'T', 'description' => 'Native WooCommerce product title'],
            'product_price' => ['label' => 'Price card', 'icon' => '₹', 'description' => 'Native product price and savings'],
            'product_delivery' => ['label' => 'Delivery card', 'icon' => '⌖', 'description' => 'Native delivery information'],
            'product_description' => ['label' => 'Description', 'icon' => '≡', 'description' => 'Native expandable description'],
            'product_specs' => ['label' => 'Specifications', 'icon' => '☷', 'description' => 'Native product attributes'],
            'product_reviews' => ['label' => 'Reviews', 'icon' => '★', 'description' => 'Native reviews area'],
            'product_offers' => ['label' => 'Product offers', 'icon' => '%', 'description' => 'Native sale and savings card'],
            'product_recommended' => ['label' => 'Recommended offers', 'icon' => '✦', 'description' => 'Compact auto-sliding WooCommerce promotions with sponsored badge'],
            'account_profile' => ['label' => 'Profile card', 'icon' => '☺', 'description' => 'Native signed-in account details'],
            'account_menu' => ['label' => 'Account menu', 'icon' => '☰', 'description' => 'Native account actions'],
            'address_selector' => ['label' => 'Delivery address selector', 'icon' => '⌖', 'description' => 'Show the saved delivery address with an edit action'],
        ];
    }


    private static function live_slider_tables() {
        global $wpdb;
        return [
            $wpdb->prefix . 'ark_live_sliders',
            $wpdb->prefix . 'ark_live_slider_items',
        ];
    }

    private static function create_live_slider_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        [$sliders, $items] = self::live_slider_tables();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$sliders} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            slider_key varchar(64) NOT NULL,
            title varchar(191) NOT NULL DEFAULT '',
            enabled tinyint(1) NOT NULL DEFAULT 1,
            autoplay tinyint(1) NOT NULL DEFAULT 1,
            interval_ms int(10) unsigned NOT NULL DEFAULT 3500,
            loop_slides tinyint(1) NOT NULL DEFAULT 1,
            transition varchar(12) NOT NULL DEFAULT 'slide',
            height_px smallint(5) unsigned NOT NULL DEFAULT 178,
            image_fit varchar(12) NOT NULL DEFAULT 'cover',
            card_width decimal(4,2) NOT NULL DEFAULT 1.00,
            slide_gap smallint(5) unsigned NOT NULL DEFAULT 8,
            show_arrows tinyint(1) NOT NULL DEFAULT 0,
            pagination varchar(12) NOT NULL DEFAULT 'dots',
            pause_on_interaction tinyint(1) NOT NULL DEFAULT 1,
            radius smallint(5) unsigned NOT NULL DEFAULT 16,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slider_key (slider_key)
        ) {$charset};");
        dbDelta("CREATE TABLE {$items} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            slider_key varchar(64) NOT NULL,
            attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
            image_url text NOT NULL,
            image_srcset text NULL,
            title varchar(191) NOT NULL DEFAULT '',
            subtitle text NULL,
            alt_text varchar(191) NOT NULL DEFAULT '',
            button_label varchar(80) NOT NULL DEFAULT '',
            link_type varchar(16) NOT NULL DEFAULT 'in_app',
            link_value text NULL,
            active tinyint(1) NOT NULL DEFAULT 1,
            sort_order int(10) unsigned NOT NULL DEFAULT 0,
            starts_at datetime NULL DEFAULT NULL,
            ends_at datetime NULL DEFAULT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY slider_order (slider_key, sort_order),
            KEY slider_active (slider_key, active)
        ) {$charset};");
    }

    public function maybe_upgrade_schema() {
        $live_slider_schema = absint(get_option('ark_app_designer_live_slider_schema', 0));
        if ($live_slider_schema < 1) {
            self::create_live_slider_tables();
            $this->migrate_legacy_banner_sliders();
            update_option('ark_app_designer_live_slider_schema', 1, false);
        } elseif ($live_slider_schema < 2) {
            // Re-run the safe Children-media migration for existing 1.17.x installs.
            self::create_live_slider_tables();
            $this->migrate_legacy_banner_sliders();
            update_option('ark_app_designer_live_slider_schema', 2, false);
        }

        // Upgrade existing Draft and Published documents without resetting any
        // user-created sections, ordering, images, links or styling.
        $config_schema = absint(get_option('ark_app_designer_config_schema', 0));
        if ($config_schema < 3) {
            $defaults = self::default_config();
            foreach ([self::PUBLISHED, self::DRAFT] as $option_name) {
                $stored = get_option($option_name, null);
                if (!is_array($stored)) continue;
                $merged = self::merge_config_defaults($defaults, $stored);
                $merged['schema_version'] = 3;
                if ($merged !== $stored) update_option($option_name, $merged, false);
            }
            update_option('ark_app_designer_config_schema', 3, false);
        }
    }

    /** Recursively fill missing associative config keys; preserve all saved lists. */
    private static function merge_config_defaults($defaults, $saved) {
        if (!is_array($defaults) || !is_array($saved)) return $saved;
        $is_list = function($value) {
            if (!$value) return true;
            return array_keys($value) === range(0, count($value) - 1);
        };
        if ($is_list($defaults) || ($saved && $is_list($saved))) return $saved;
        foreach ($defaults as $key => $value) {
            if (!array_key_exists($key, $saved)) {
                $saved[$key] = $value;
            } elseif (is_array($value) && is_array($saved[$key])) {
                $saved[$key] = self::merge_config_defaults($value, $saved[$key]);
            }
        }
        return $saved;
    }

    private function live_slider_defaults() {
        return [
            'enabled' => 1, 'autoplay' => 1, 'interval_ms' => 3500,
            'loop_slides' => 1, 'transition' => 'slide', 'height_px' => 178,
            'image_fit' => 'cover', 'card_width' => 1.00, 'slide_gap' => 8,
            'show_arrows' => 0, 'pagination' => 'dots',
            'pause_on_interaction' => 1, 'radius' => 16,
        ];
    }

    private function normalize_slider_datetime($value) {
        $value = trim((string) $value);
        if ($value === '') return null;
        try {
            $date = new DateTimeImmutable($value, wp_timezone());
            return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Exception $error) {
            return null;
        }
    }

    private function normalize_slider_link($type, $value) {
        $type = in_array($type, ['in_app','deeplink','chrome'], true) ? $type : 'in_app';
        $value = trim((string) $value);
        if ($type === 'chrome') {
            if ($value !== '' && !preg_match('/^https?:\\/\\//i', $value)) $value = 'https://' . $value;
            return [$type, $this->clean_url($value)];
        }
        if ($type === 'in_app') {
            if (preg_match('/^(page:(home|categories|account|wishlist|cart|orders|notifications|custom_[a-z0-9_]{1,40})|product:[0-9]+|category:[0-9]+)$/', $value)) {
                return [$type, $value];
            }
            return [$type, ''];
        }
        return [$type, sanitize_text_field($value)];
    }

    private function save_live_slider_payload($slider_key, $settings, $slides) {
        global $wpdb;
        [$sliders, $items] = self::live_slider_tables();
        $slider_key = sanitize_key($slider_key);
        if ($slider_key === '' || strlen($slider_key) > 64) {
            return new WP_Error('ark_slider_key', 'Slider ID is invalid.', ['status' => 400]);
        }
        if (!is_array($settings)) $settings = [];
        if (!is_array($slides)) $slides = [];
        if (count($slides) > 40) {
            return new WP_Error('ark_slider_limit', 'Maximum 40 images per slider.', ['status' => 400]);
        }

        // Validate every selected image before deleting existing SQL rows.
        // A stale Media Library attachment must never turn a valid slider into an empty one.
        foreach ($slides as $slide_index => $slide) {
            if (!is_array($slide)) {
                return new WP_Error('ark_slider_image_invalid', 'Slide ' . ($slide_index + 1) . ' has invalid image data.', ['status' => 400]);
            }
            $attachment_id = absint($slide['attachment_id'] ?? 0);
            $image_url = '';
            if ($attachment_id > 0 && wp_attachment_is_image($attachment_id)) {
                $image_url = wp_get_attachment_image_url($attachment_id, 'large') ?: wp_get_attachment_url($attachment_id);
            } else {
                $image_url = $this->clean_url($slide['image_url'] ?? '');
            }
            if (!$image_url) {
                return new WP_Error('ark_slider_image_invalid', 'Slide ' . ($slide_index + 1) . ' has no valid image. Existing saved images were kept.', ['status' => 400]);
            }
        }

        $defaults = $this->live_slider_defaults();
        $row = [
            'title' => sanitize_text_field($settings['title'] ?? 'Live Slider'),
            'enabled' => !array_key_exists('enabled', $settings) || !empty($settings['enabled']) ? 1 : 0,
            'autoplay' => !array_key_exists('autoplay', $settings) || !empty($settings['autoplay']) ? 1 : 0,
            'interval_ms' => min(20000, max(1000, absint($settings['interval_ms'] ?? $defaults['interval_ms']))),
            'loop_slides' => !array_key_exists('loop_slides', $settings) || !empty($settings['loop_slides']) ? 1 : 0,
            'transition' => in_array(($settings['transition'] ?? 'slide'), ['slide','fade','zoom'], true) ? ($settings['transition'] ?? 'slide') : 'slide',
            'height_px' => min(600, max(80, absint($settings['height_px'] ?? $defaults['height_px']))),
            'image_fit' => in_array(($settings['image_fit'] ?? 'cover'), ['cover','contain','fill'], true) ? ($settings['image_fit'] ?? 'cover') : 'cover',
            'card_width' => min(1.00, max(0.45, (float) ($settings['card_width'] ?? $defaults['card_width']))),
            'slide_gap' => min(32, max(0, absint($settings['slide_gap'] ?? $defaults['slide_gap']))),
            'show_arrows' => !empty($settings['show_arrows']) ? 1 : 0,
            'pagination' => in_array(($settings['pagination'] ?? 'dots'), ['dots','progress','none'], true) ? ($settings['pagination'] ?? 'dots') : 'dots',
            'pause_on_interaction' => !array_key_exists('pause_on_interaction', $settings) || !empty($settings['pause_on_interaction']) ? 1 : 0,
            'radius' => min(60, max(0, absint($settings['radius'] ?? $defaults['radius']))),
            'updated_at' => current_time('mysql', true),
        ];

        $wpdb->query('START TRANSACTION');
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$sliders} WHERE slider_key = %s", $slider_key));
        if ($exists) {
            $saved = $wpdb->update($sliders, $row, ['slider_key' => $slider_key]);
        } else {
            $row['slider_key'] = $slider_key;
            $saved = $wpdb->insert($sliders, $row);
        }
        if ($saved === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('ark_slider_save', 'Slider settings could not be saved.', ['status' => 500]);
        }

        $wpdb->delete($items, ['slider_key' => $slider_key], ['%s']);
        $saved_slide_count = 0;
        foreach (array_values($slides) as $index => $slide) {
            $attachment_id = absint($slide['attachment_id'] ?? 0);
            $image_url = '';
            $srcset = '';
            if ($attachment_id > 0 && wp_attachment_is_image($attachment_id)) {
                $image_url = wp_get_attachment_image_url($attachment_id, 'large') ?: wp_get_attachment_url($attachment_id);
                $srcset = wp_get_attachment_image_srcset($attachment_id, 'large') ?: '';
            } else {
                $image_url = $this->clean_url($slide['image_url'] ?? '');
            }
            if (!$image_url) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('ark_slider_image_invalid', 'A selected image became unavailable during save. Existing saved images were restored.', ['status' => 500]);
            }
            [$link_type, $link_value] = $this->normalize_slider_link(
                $slide['link_type'] ?? 'in_app',
                $slide['link_value'] ?? $slide['link'] ?? ''
            );
            $start = $this->normalize_slider_datetime($slide['starts_at'] ?? '');
            $end = $this->normalize_slider_datetime($slide['ends_at'] ?? '');
            $alt = sanitize_text_field($slide['alt_text'] ?? '');
            if ($alt === '' && $attachment_id > 0) {
                $alt = sanitize_text_field(get_post_meta($attachment_id, '_wp_attachment_image_alt', true));
            }
            $inserted = $wpdb->insert($items, [
                'slider_key' => $slider_key,
                'attachment_id' => $attachment_id,
                'image_url' => esc_url_raw($image_url),
                'image_srcset' => $srcset,
                'title' => sanitize_text_field($slide['title'] ?? ''),
                'subtitle' => sanitize_textarea_field($slide['subtitle'] ?? ''),
                'alt_text' => $alt,
                'button_label' => sanitize_text_field($slide['button_label'] ?? ''),
                'link_type' => $link_type,
                'link_value' => $link_value,
                'active' => !array_key_exists('active', $slide) || !empty($slide['active']) ? 1 : 0,
                'sort_order' => $index,
                'starts_at' => $start,
                'ends_at' => $end,
                'updated_at' => current_time('mysql', true),
            ]);
            if ($inserted === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('ark_slider_save', 'A slider image could not be saved.', ['status' => 500]);
            }
            $saved_slide_count++;
        }
        $wpdb->query('COMMIT');
        return ['slider_key' => $slider_key, 'saved_slides' => $saved_slide_count];
    }

    private function get_live_slider_payload($slider_key, $public = false) {
        global $wpdb;
        [$sliders, $items] = self::live_slider_tables();
        $slider_key = sanitize_key($slider_key);
        $saved = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$sliders} WHERE slider_key = %s", $slider_key), ARRAY_A);
        $settings = $saved ?: array_merge(['title' => 'Live Slider'], $this->live_slider_defaults());
        $where = "slider_key = %s";
        if ($public) {
            $where .= " AND active = 1 AND (starts_at IS NULL OR starts_at <= UTC_TIMESTAMP()) AND (ends_at IS NULL OR ends_at >= UTC_TIMESTAMP())";
        }
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$items} WHERE {$where} ORDER BY sort_order ASC, id ASC", $slider_key), ARRAY_A);
        $slides = [];
        foreach ($rows as $item) {
            $slides[] = [
                'id' => (int) $item['id'],
                'attachment_id' => (int) $item['attachment_id'],
                'image_url' => esc_url_raw($item['image_url']),
                'image_srcset' => (string) $item['image_srcset'],
                'title' => (string) $item['title'],
                'subtitle' => (string) $item['subtitle'],
                'alt_text' => (string) $item['alt_text'],
                'button_label' => (string) $item['button_label'],
                'link_type' => (string) $item['link_type'],
                'link_value' => (string) $item['link_value'],
                'active' => (bool) $item['active'],
                'starts_at' => $item['starts_at'],
                'ends_at' => $item['ends_at'],
            ];
        }
        return [
            'slider_key' => $slider_key,
            'settings' => [
                'title' => (string) ($settings['title'] ?? 'Live Slider'),
                'enabled' => (bool) ($settings['enabled'] ?? 1),
                'autoplay' => (bool) ($settings['autoplay'] ?? 1),
                'interval_ms' => (int) ($settings['interval_ms'] ?? 3500),
                'loop_slides' => (bool) ($settings['loop_slides'] ?? 1),
                'transition' => (string) ($settings['transition'] ?? 'slide'),
                'height_px' => (int) ($settings['height_px'] ?? 178),
                'image_fit' => (string) ($settings['image_fit'] ?? 'cover'),
                'card_width' => (float) ($settings['card_width'] ?? 1),
                'slide_gap' => (int) ($settings['slide_gap'] ?? 8),
                'show_arrows' => (bool) ($settings['show_arrows'] ?? 0),
                'pagination' => (string) ($settings['pagination'] ?? 'dots'),
                'pause_on_interaction' => (bool) ($settings['pause_on_interaction'] ?? 1),
                'radius' => (int) ($settings['radius'] ?? 16),
                'updated_at' => (string) ($settings['updated_at'] ?? ''),
            ],
            'slides' => $slides,
        ];
    }

    private function migrate_legacy_banner_sliders() {
        $banner_layouts = ['grid','list','slider','scroll'];
        foreach ([self::PUBLISHED, self::DRAFT] as $option) {
            $config = get_option($option, []);
            if (!is_array($config) || !is_array($config['pages'] ?? null)) continue;
            $changed = false;
            foreach ($config['pages'] as $page_key => &$page) {
                if (!is_array($page) || !is_array($page['sections'] ?? null)) continue;
                foreach ($page['sections'] as &$section) {
                    if (!is_array($section)) continue;
                    $original_type = sanitize_key($section['type'] ?? '');
                    $raw_variant = sanitize_key($section['layout_variant'] ?? $section['banner_layout'] ?? '');
                    $legacy_image_banner = $original_type === 'image' && in_array($raw_variant, $banner_layouts, true);
                    $legacy_banner_type = in_array($original_type, ['banner_slider','banner_grid','banner_list','banner_scroll'], true);
                    if (!$legacy_image_banner && !$legacy_banner_type) continue;

                    $variant = $legacy_image_banner
                        ? $raw_variant
                        : (['banner_grid'=>'grid','banner_list'=>'list','banner_scroll'=>'scroll','banner_slider'=>'slider'][$original_type] ?? 'slider');
                    $slider_key = sanitize_key($section['slider_key'] ?? $section['id'] ?? ('legacy-' . $page_key));
                    if ($slider_key === '') $slider_key = sanitize_key('legacy-' . $page_key . '-' . wp_generate_uuid4());

                    // Preserve the original Designer component identity and every layout property.
                    $section['designer_component_type'] = $section['designer_component_type'] ?? $original_type;
                    $section['layout_variant'] = $variant;
                    $section['slider_key'] = $slider_key;

                    if ($option === self::PUBLISHED) {
                        // Media can have been stored as Children, items, slides, images or image_url.
                        $legacy_slides = $section['children'] ?? $section['items'] ?? $section['slides'] ?? $section['images'] ?? [];
                        if (!is_array($legacy_slides)) $legacy_slides = [];
                        if (!$legacy_slides && !empty($section['image_url'])) {
                            $legacy_slides = [[
                                'attachment_id' => absint($section['image_id'] ?? 0),
                                'image_url' => $section['image_url'],
                                'title' => $section['title'] ?? '',
                                'alt_text' => $section['alt_text'] ?? '',
                                'link_type' => $section['button_link_type'] ?? 'in_app',
                                'link_value' => $section['button_url'] ?? '',
                            ]];
                        }
                        $legacy_slides = array_values(array_filter(array_map(function($child) {
                            if (is_string($child)) $child = ['image_url' => $child];
                            if (!is_array($child)) return null;
                            $attachment_id = absint($child['attachment_id'] ?? $child['media_id'] ?? $child['id'] ?? 0);
                            $image_url = $child['image_url'] ?? $child['url'] ?? $child['src'] ?? $child['image'] ?? '';
                            if (is_array($image_url)) $image_url = $image_url['url'] ?? $image_url['src'] ?? '';
                            if ($attachment_id > 0 && wp_attachment_is_image($attachment_id)) {
                                $image_url = wp_get_attachment_image_url($attachment_id, 'large') ?: wp_get_attachment_url($attachment_id);
                            }
                            return [
                                'attachment_id' => $attachment_id,
                                'image_url' => esc_url_raw((string) $image_url),
                                'title' => sanitize_text_field($child['title'] ?? ''),
                                'subtitle' => sanitize_textarea_field($child['subtitle'] ?? ''),
                                'alt_text' => sanitize_text_field($child['alt_text'] ?? $child['alt'] ?? ''),
                                'button_label' => sanitize_text_field($child['button_label'] ?? ''),
                                'link_type' => $child['link_type'] ?? 'in_app',
                                'link_value' => $child['link_value'] ?? $child['link'] ?? '',
                                'active' => !array_key_exists('active', $child) || !empty($child['active']),
                                'starts_at' => $child['starts_at'] ?? '',
                                'ends_at' => $child['ends_at'] ?? '',
                            ];
                        }, $legacy_slides), function($slide) {
                            return is_array($slide) && ($slide['attachment_id'] > 0 || $slide['image_url'] !== '');
                        }));
                        $legacy_settings = [
                            'title' => $section['title'] ?? 'Live Slider',
                            'height_px' => $section['height_px'] ?? $section['height'] ?? 178,
                            'interval_ms' => $section['interval_ms'] ?? 3500,
                            'autoplay' => $section['autoplay'] ?? true,
                            'loop_slides' => $section['loop_slides'] ?? true,
                            'transition' => $section['transition'] ?? 'slide',
                            'image_fit' => $section['image_fit'] ?? $section['fit'] ?? 'cover',
                            'card_width' => $section['card_width'] ?? 1,
                            'slide_gap' => $section['slide_gap'] ?? $section['item_gap'] ?? 8,
                            'show_arrows' => $section['show_arrows'] ?? false,
                            'pagination' => $section['pagination'] ?? (!empty($section['show_dots']) ? 'dots' : 'none'),
                            'pause_on_interaction' => $section['pause_on_interaction'] ?? true,
                            'radius' => $section['radius'] ?? $section['corner_radius'] ?? 16,
                        ];
                        // An empty legacy Children list must never delete existing SQL campaigns.
                        if (!empty($legacy_slides)) {
                            $saved = $this->save_live_slider_payload($slider_key, $legacy_settings, $legacy_slides);
                            if (is_wp_error($saved)) continue;
                        }
                    }
                    $section['type'] = 'ark_live_slider';
                    unset($section['items'], $section['slides'], $section['images'], $section['children'], $section['banner_layout']);
                    $changed = true;
                }
                unset($section);
            }
            unset($page);
            if ($changed) update_option($option, $config, false);
        }
    }

    public function api_studio_live_slider($request) {
        return rest_ensure_response($this->get_live_slider_payload($request['slider_key'], false));
    }

    public function api_save_live_slider($request) {
        $body = $request->get_json_params();
        if (!is_array($body)) return new WP_Error('ark_slider_payload', 'Slider data is invalid.', ['status' => 400]);
        $saved = $this->save_live_slider_payload(
            $request['slider_key'],
            $body['settings'] ?? [],
            $body['slides'] ?? []
        );
        if (is_wp_error($saved)) return $saved;
        return rest_ensure_response(['success' => true] + $saved);
    }

    public function api_public_live_slider($request) {
        $payload = $this->get_live_slider_payload($request['slider_key'], true);
        if (!$payload['settings']['enabled']) $payload['slides'] = [];
        $response = rest_ensure_response($payload);
        $response->header('Cache-Control', 'public, max-age=30, stale-while-revalidate=120');
        return $response;
    }

    public function register_routes() {
        register_rest_route(self::REST_NAMESPACE, '/sliders/(?P<slider_key>[a-zA-Z0-9_-]+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'api_public_live_slider'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::REST_NAMESPACE, '/studio/sliders/(?P<slider_key>[a-zA-Z0-9_-]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'api_studio_live_slider'],
                'permission_callback' => [$this, 'can_design'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'api_save_live_slider'],
                'permission_callback' => [$this, 'can_design'],
            ],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/config', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'api_config'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::REST_NAMESPACE, '/studio/config', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'api_studio_config'],
            'permission_callback' => [$this, 'can_design'],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/studio/draft', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'api_save_draft'],
            'permission_callback' => [$this, 'can_design'],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/studio/publish', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'api_publish'],
            'permission_callback' => [$this, 'can_design'],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/studio/components', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => function() { return rest_ensure_response(self::component_catalog()); },
            'permission_callback' => [$this, 'can_design'],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/studio/media', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'api_studio_media'],
            'permission_callback' => [$this, 'can_design'],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/studio/targets', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'api_studio_targets'],
            'permission_callback' => [$this, 'can_design'],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/studio/github/tree', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'api_github_tree'],
            'permission_callback' => [$this, 'can_design'],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/studio/github/file', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'api_github_read_file'],
                'permission_callback' => [$this, 'can_design'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'api_github_save_file'],
                'permission_callback' => [$this, 'can_design'],
            ],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/studio/github/settings', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'api_github_save_settings'],
            'permission_callback' => [$this, 'can_design'],
        ]);
    }

    public function can_design() {
        return current_user_can('manage_options');
    }

    public function api_config() {
        $config = get_option(self::PUBLISHED, self::default_config());
        return rest_ensure_response(self::merge_config_defaults(self::default_config(), is_array($config) ? $config : self::default_config()));
    }

    public function api_studio_config() {
        return rest_ensure_response($this->current_editor_config());
    }

    /** Return real native page and WooCommerce product destinations for slider links. */
    /** Paginated image-only Media Library endpoint for the native Designer. */
    public function api_studio_media($request) {
        $page = max(1, absint($request->get_param('page') ?: 1));
        $per_page = min(60, max(1, absint($request->get_param('per_page') ?: 30)));
        $search = sanitize_text_field((string) $request->get_param('search'));
        $query = new WP_Query([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_mime_type' => 'image',
            'posts_per_page' => $per_page,
            'paged' => $page,
            's' => $search,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => false,
        ]);
        $items = [];
        foreach ($query->posts as $attachment) {
            $id = (int) $attachment->ID;
            $source = wp_get_attachment_url($id);
            if (!$source) continue;
            $thumb = wp_get_attachment_image_url($id, 'medium');
            $items[] = [
                'id' => $id,
                'source_url' => esc_url_raw($source),
                'thumbnail_url' => esc_url_raw($thumb ?: $source),
                'title' => sanitize_text_field(get_the_title($id)),
                'alt_text' => sanitize_text_field(get_post_meta($id, '_wp_attachment_image_alt', true)),
                'mime_type' => get_post_mime_type($id),
            ];
        }
        return rest_ensure_response([
            'items' => $items,
            'page' => $page,
            'per_page' => $per_page,
            'total' => (int) $query->found_posts,
            'total_pages' => (int) $query->max_num_pages,
        ]);
    }

    public function api_studio_targets($request) {
        $products = [];
        $categories = [];
        $search = sanitize_text_field((string) $request->get_param('search'));
        // Keep the initial target picker fast; product search loads a small
        // matching page instead of transferring hundreds of full product objects.
        if (function_exists('wc_get_products')) {
            $query = [
                'status' => 'publish', 'limit' => 50, 'orderby' => 'title',
                'order' => 'ASC', 'return' => 'objects',
            ];
            if ($search !== '') $query['search'] = $search;
            $found = wc_get_products($query);
            foreach ($found as $product) {
                // The native Designer preview needs real WooCommerce media,
                // not only product IDs/names. Prefer the featured image and
                // fall back to the first gallery image when no thumbnail is set.
                $image_ids = [];
                $featured_id = (int) $product->get_image_id();
                if ($featured_id > 0) $image_ids[] = $featured_id;
                if (method_exists($product, 'get_gallery_image_ids')) {
                    foreach ((array) $product->get_gallery_image_ids() as $gallery_id) {
                        $gallery_id = (int) $gallery_id;
                        if ($gallery_id > 0 && !in_array($gallery_id, $image_ids, true)) $image_ids[] = $gallery_id;
                    }
                }
                $images = [];
                foreach (array_slice($image_ids, 0, 8) as $image_id) {
                    $image_url = wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail')
                        ?: wp_get_attachment_url($image_id);
                    if (!$image_url) continue;
                    $images[] = [
                        'id' => $image_id,
                        'src' => esc_url_raw($image_url),
                        'thumbnail' => esc_url_raw(wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail') ?: $image_url),
                        'alt' => sanitize_text_field(get_post_meta($image_id, '_wp_attachment_image_alt', true)),
                    ];
                }
                $products[] = [
                    'id' => (int) $product->get_id(),
                    'name' => wp_strip_all_tags($product->get_name()),
                    'image_url' => $images[0]['src'] ?? '',
                    'images' => $images,
                    'price' => wp_strip_all_tags((string) $product->get_price()),
                    'regular_price' => wp_strip_all_tags((string) $product->get_regular_price()),
                    'sale_price' => wp_strip_all_tags((string) $product->get_sale_price()),
                ];
            }
        }
        if (function_exists('get_terms')) {
            $term_query = ['taxonomy' => 'product_cat', 'hide_empty' => false, 'number' => 50];
            if ($search !== '') $term_query['search'] = $search;
            $terms = get_terms($term_query);
            if (!is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $thumbnail_id = (int) get_term_meta($term->term_id, 'thumbnail_id', true);
                    $image_url = $thumbnail_id > 0
                        ? (wp_get_attachment_image_url($thumbnail_id, 'woocommerce_thumbnail') ?: wp_get_attachment_url($thumbnail_id))
                        : '';
                    $categories[] = [
                        'id' => (int) $term->term_id,
                        'name' => $term->name,
                        'image_url' => $image_url ? esc_url_raw($image_url) : '',
                        'thumbnail' => $image_url ? esc_url_raw($image_url) : '',
                    ];
                }
            }
        }
        $pages = $this->current_editor_config()['pages'] ?? [];
        return rest_ensure_response([
            'pages' => [
                ['value' => 'page:home', 'label' => 'Home page'],
                ['value' => 'page:categories', 'label' => 'Categories page'],
                ['value' => 'page:account', 'label' => 'Account page'],
                ['value' => 'page:wishlist', 'label' => 'Wishlist page'],
                ['value' => 'page:cart', 'label' => 'Cart page'],
            ],
            'custom_pages' => array_values(array_map(function($key) use ($pages) {
                return ['value' => 'page:' . $key, 'label' => (string) ($pages[$key]['title'] ?? $key)];
            }, array_filter(array_keys($pages), function($key) use ($pages) {
                return strpos((string) $key, 'custom_') === 0 && is_array($pages[$key]);
            }))),
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    private function clean_url($value) {
        $value = trim((string) $value);
        if ($value === '') return '';
        $url = esc_url_raw($value, ['http', 'https']);
        return $url ?: '';
    }

    private function clean_value($value, $depth = 0) {
        if ($depth > 6) return '';
        if (is_bool($value) || is_numeric($value) || $value === null) return $value;
        if (is_string($value)) {
            $value = wp_check_invalid_utf8($value);
            return sanitize_textarea_field($value);
        }
        if (!is_array($value)) return '';
        $clean = [];
        foreach (array_slice($value, 0, 100) as $key => $item) {
            $key = is_int($key) ? $key : sanitize_key($key);
            if ($key === '') continue;
            $clean[$key] = $this->clean_value($item, $depth + 1);
        }
        return $clean;
    }

    private function sanitize_heading_settings($raw, $defaults, $allowed_pages = []) {
        $value = is_array($raw) ? self::merge_config_defaults($defaults, $raw) : $defaults;
        foreach (['background_color','title_color','icon_color','button_background','button_text_color'] as $key) {
            $value[$key] = sanitize_hex_color($value[$key] ?? '') ?: ($defaults[$key] ?? '#FFFFFF');
        }
        $image_id = absint($value['background_image_id'] ?? 0);
        $image_url = $image_id > 0 && function_exists('wp_get_attachment_url') ? wp_get_attachment_url($image_id) : '';
        $value['background_image_id'] = $image_id;
        $value['background_image_url'] = $this->clean_url($image_url ?: ($value['background_image_url'] ?? ''));
        $value['show_background_image'] = !empty($value['show_background_image']) && $value['background_image_url'] !== '';
        $value['background_image_fit'] = in_array(($value['background_image_fit'] ?? 'cover'), ['cover','contain','fill'], true) ? $value['background_image_fit'] : 'cover';
        $value['background_image_opacity'] = min(100, max(0, absint($value['background_image_opacity'] ?? 100)));
        $value['animation'] = in_array(($value['animation'] ?? 'none'), ['none','pulse','gradient','slide','shimmer','float'], true) ? $value['animation'] : 'none';
        $value['background_effect'] = in_array(($value['background_effect'] ?? 'none'), ['none','glass','blur','shimmer','glow','gradient','fireworks'], true) ? $value['background_effect'] : 'none';
        $value['height'] = min(100, max(48, absint($value['height'] ?? 64)));
        $value['position_index'] = min(50, max(-1, (int) ($value['position_index'] ?? -1)));
        $value['visible_pages'] = array_values(array_intersect($allowed_pages, array_map('sanitize_key', (array) ($value['visible_pages'] ?? $defaults['visible_pages'] ?? []))));
        $value['title'] = sanitize_text_field($value['title'] ?? '');
        $value['icon_name'] = sanitize_key($value['icon_name'] ?? 'storefront');
        $value['button_label'] = sanitize_text_field($value['button_label'] ?? 'Explore');
        $value['button_icon'] = sanitize_key($value['button_icon'] ?? 'arrow_forward');
        $value['show_mini_button'] = !empty($value['show_mini_button']);
        $value['mini_button_label'] = sanitize_text_field($value['mini_button_label'] ?? 'More');
        $value['mini_button_icon'] = sanitize_key($value['mini_button_icon'] ?? 'chevron_right');
        $mini_mode = in_array(($value['mini_button_link_type'] ?? 'in_app'), ['in_app','deeplink','chrome'], true) ? ($value['mini_button_link_type'] ?? 'in_app') : 'in_app';
        $value['mini_button_link_type'] = $mini_mode;
        $mini_target = (string) ($value['mini_button_url'] ?? '');
        $value['mini_button_url'] = $mini_mode === 'chrome' ? $this->clean_url($mini_target) : sanitize_text_field($mini_target);
        $value['mini_button_background'] = sanitize_hex_color($value['mini_button_background'] ?? '') ?: '#FFFFFF';
        $value['mini_button_text_color'] = sanitize_hex_color($value['mini_button_text_color'] ?? '') ?: '#2874F0';
        $mode = in_array(($value['button_link_type'] ?? 'in_app'), ['in_app','deeplink','chrome'], true) ? ($value['button_link_type'] ?? 'in_app') : 'in_app';
        $value['button_link_type'] = $mode;
        $target = (string) ($value['button_url'] ?? '');
        $value['button_url'] = $mode === 'chrome' ? $this->clean_url($target) : sanitize_text_field($target);
        foreach (['enabled','show_app_logo','show_app_name','show_icon','show_title','show_button'] as $key) {
            $value[$key] = !array_key_exists($key, $value) || (bool) $value[$key];
        }
        return $value;
    }

    private function current_editor_config() {
        $published = get_option(self::PUBLISHED, self::default_config());
        $draft = get_option(self::DRAFT, []);
        if (!is_array($published)) $published = self::default_config();
        if (!is_array($draft)) $draft = [];
        $published_at = strtotime((string) ($published['updated_at'] ?? '')) ?: 0;
        $draft_at = strtotime((string) ($draft['updated_at'] ?? '')) ?: 0;
        $source = $draft_at >= $published_at && !empty($draft) ? $draft : $published;
        return self::merge_config_defaults(self::default_config(), $source);
    }

    private function sanitize_config($raw, $baseline = null) {
        if (!is_array($raw)) return new WP_Error('ark_designer_invalid', 'Design configuration must be an object.', ['status' => 400]);
        $base = self::default_config();
        if (!is_array($baseline)) $baseline = $this->current_editor_config();
        // A cached/older Designer tab must not erase settings that were added by
        // a newer plugin. Fill only missing values from the last saved document.
        $raw = self::merge_config_defaults(is_array($baseline) ? $baseline : $base, $raw);
        $clean = $this->clean_value($raw);
        if (!is_array($clean)) return new WP_Error('ark_designer_invalid', 'Invalid design configuration.', ['status' => 400]);
        $clean = self::merge_config_defaults($base, $clean);

        $clean['schema_version'] = 3;
        $clean['version'] = max(1, absint($raw['version'] ?? 1));
        $clean['updated_at'] = gmdate('c');

        $app = is_array($clean['app'] ?? null) ? $clean['app'] : $base['app'];
        $icon_visibility = is_array($app['icon_visibility'] ?? null) ? $app['icon_visibility'] : [];
        $page_keys_for_visibility = array_keys(is_array($clean['pages'] ?? null) ? $clean['pages'] : []);
        $custom_visibility_pages = array_values(array_filter($page_keys_for_visibility, function($key) {
            return preg_match('/^custom_[a-z0-9_]{1,40}$/', (string) $key) === 1;
        }));
        $visibility_pages = array_values(array_unique(array_merge(['home','search','categories','product','wishlist','account'], $custom_visibility_pages)));
        $visibility_icons = ['app_icon','app_name','page_title','refresh','notifications','cart','wishlist','share','add_to_cart','buy_now'];
        foreach ($visibility_pages as $visibility_page) {
            $page_visibility = is_array($icon_visibility[$visibility_page] ?? null) ? $icon_visibility[$visibility_page] : [];
            foreach ($visibility_icons as $visibility_icon) {
                $page_visibility[$visibility_icon] = !array_key_exists($visibility_icon, $page_visibility) || (bool) $page_visibility[$visibility_icon];
            }
            $icon_visibility[$visibility_page] = $page_visibility;
        }
        $app['icon_visibility'] = $icon_visibility;
        $clean['app'] = $app;

        $theme = is_array($clean['theme'] ?? null) ? $clean['theme'] : [];
        foreach (['primary','accent','background','surface','text','muted'] as $color) {
            $candidate = sanitize_hex_color($theme[$color] ?? '');
            $theme[$color] = $candidate ?: $base['theme'][$color];
        }
        foreach (['header_background','header_text','navigation_background','navigation_indicator','cart_button_background','cart_button_text','buy_button_background','buy_button_text','buy_button_price'] as $color) {
            $candidate = sanitize_hex_color($theme[$color] ?? '');
            $theme[$color] = $candidate ?: ($base['theme'][$color] ?? '#FFFFFF');
        }
        $theme['header_animation'] = in_array(($theme['header_animation'] ?? 'none'), ['none','gradient','pulse'], true)
            ? ($theme['header_animation'] ?? 'none') : 'none';
        $theme['radius'] = min(32, max(0, absint($theme['radius'] ?? 18)));
        $theme['font_scale'] = min(1.3, max(0.85, (float) ($theme['font_scale'] ?? 1)));
        $clean['theme'] = $theme;

        $allowed_page_keys = array_values(array_unique(array_merge(['home','search','categories','product','account','wishlist'], $custom_visibility_pages)));
        $heading = $this->sanitize_heading_settings($clean['default_heading'] ?? $base['default_heading'], $base['default_heading'], $allowed_page_keys);
        $clean['default_heading'] = $heading;

        $splash = is_array($clean['splash'] ?? null) ? $clean['splash'] : [];
        $splash['enabled'] = !empty($splash['enabled']);
        $splash['image_url'] = $this->clean_url($splash['image_url'] ?? '');
        $splash['duration_ms'] = min(8000, max(500, absint($splash['duration_ms'] ?? 1800)));
        $splash['fit'] = in_array(($splash['fit'] ?? 'contain'), ['contain','cover','fill'], true) ? $splash['fit'] : 'contain';
        $splash['background'] = sanitize_hex_color($splash['background'] ?? '') ?: $base['splash']['background']; 
        unset($splash['logo_url'], $splash['title'], $splash['subtitle'], $splash['animation']);
        $clean['splash'] = $splash;
        $loading = is_array($clean['loading'] ?? null) ? self::merge_config_defaults($base['loading'], $clean['loading']) : $base['loading'];
        $loading['enabled'] = !empty($loading['enabled']);
        $loading['animation_url'] = $this->clean_url($loading['animation_url'] ?? '');
        $loading['duration_ms'] = min(5000, max(250, absint($loading['duration_ms'] ?? 900)));
        $loading['size'] = min(140, max(24, absint($loading['size'] ?? 58)));
        $loading_pages = is_array($loading['pages'] ?? null) ? $loading['pages'] : $base['loading']['pages'];
        $loading['pages'] = array_values(array_intersect($allowed_page_keys, array_map('sanitize_key', $loading_pages)));
        $clean['loading'] = $loading;

        $celebration = is_array($clean['celebration'] ?? null) ? $clean['celebration'] : $base['celebration'];
        $celebration['enabled'] = !empty($celebration['enabled']);
        $celebration['duration_ms'] = min(10000, max(1000, absint($celebration['duration_ms'] ?? 4200)));
        $celebration['pages'] = ['home'];
        $clean['celebration'] = $celebration;

        $pages = is_array($clean['pages'] ?? null) ? $clean['pages'] : [];
        $allowed_pages = ['home','search','categories','product','account','wishlist'];
        foreach (array_keys($pages) as $candidate_key) {
            if (preg_match('/^custom_[a-z0-9_]{1,40}$/', (string) $candidate_key) === 1 && is_array($pages[$candidate_key])) {
                $allowed_pages[] = $candidate_key;
            }
        }
        $allowed_pages = array_values(array_unique($allowed_pages));
        foreach ($allowed_pages as $page_key) {
            $page_defaults = $base['pages'][$page_key] ?? [
                'title' => 'New Page', 'custom_page' => true, 'sections' => [],
                'heading_settings' => $base['default_heading'],
            ];
            $page = is_array($pages[$page_key] ?? null)
                ? self::merge_config_defaults($page_defaults, $pages[$page_key])
                : $page_defaults;
            if (strpos($page_key, 'custom_') === 0) {
                $page['custom_page'] = true;
                $page['heading_settings'] = $this->sanitize_heading_settings(
                    $page['heading_settings'] ?? $base['default_heading'],
                    $base['default_heading'],
                    $allowed_page_keys
                );
            }
            if ($page_key === 'search') {
                $search_settings = is_array($page['search_settings'] ?? null) ? $page['search_settings'] : ($base['pages']['search']['search_settings'] ?? []);
                $search_settings['placeholder'] = sanitize_text_field($search_settings['placeholder'] ?? 'Search products...');
                $search_settings['show_voice_search'] = !array_key_exists('show_voice_search', $search_settings) || !empty($search_settings['show_voice_search']);
                $search_settings['show_camera_search'] = !array_key_exists('show_camera_search', $search_settings) || !empty($search_settings['show_camera_search']);
                $search_settings['show_cart'] = !array_key_exists('show_cart', $search_settings) || !empty($search_settings['show_cart']);
                $search_settings['voice_locale'] = in_array(($search_settings['voice_locale'] ?? 'en_IN'), ['en_IN','hi_IN'], true) ? ($search_settings['voice_locale'] ?? 'en_IN') : 'en_IN';
                $search_settings['top_bar_background'] = sanitize_hex_color($search_settings['top_bar_background'] ?? '') ?: '#D7E6FF';
                $search_settings['search_bar_background'] = sanitize_hex_color($search_settings['search_bar_background'] ?? '') ?: '#FFFFFF';
                $search_settings['suggestion_limit'] = min(20, max(1, absint($search_settings['suggestion_limit'] ?? 8)));
                $page['search_settings'] = $search_settings;
            }
            $sections = is_array($page['sections'] ?? null) ? array_slice($page['sections'], 0, 50) : [];
            $page['sections'] = [];
            foreach ($sections as $section) {
                if (!is_array($section)) continue;
                $type = sanitize_key($section['type'] ?? '');
                $designer_component_type = $type;
                // All banner layout variants use the same SQL-backed slide store.
                // The Designer's current image picker writes selected media to children.
                $legacy_banner_slider = in_array($type, ['banner_slider','banner_grid','banner_list','banner_scroll'], true)
                    && (!empty($section['items']) || !empty($section['slides']) || !empty($section['images']) || !empty($section['children']));
                $layout_aliases = [
                    'banner_grid' => ['ark_live_slider','grid'],
                    'banner_list' => ['ark_live_slider','list'],
                    'banner_slider' => ['ark_live_slider','slider'],
                    'banner_scroll' => ['ark_live_slider','scroll'],
                    'category_list' => ['category_grid','list'],
                    'category_list_tile' => ['category_grid','list_tile'],
                    'category_scroll' => ['category_grid','scroll'],
                    'category_slider' => ['category_grid','slider'],
                    'product_list' => ['product_grid','list'],
                    'product_scroll' => ['product_carousel','scroll'],
                ];
                $layout_variant = '';
                if (isset($layout_aliases[$type])) {
                    [$type, $layout_variant] = $layout_aliases[$type];
                } elseif ($type === 'banner_slider') {
                    $type = 'ark_live_slider';
                    $layout_variant = 'slider';
                } elseif ($type === 'product_grid') {
                    $layout_variant = sanitize_key($section['layout_variant'] ?? 'grid');
                } elseif ($type === 'product_carousel') {
                    $layout_variant = sanitize_key($section['layout_variant'] ?? 'slider');
                } elseif ($type === 'category_grid') {
                    $layout_variant = sanitize_key($section['layout_variant'] ?? ($section['category_layout'] ?? 'grid'));
                } elseif ($type === 'ark_live_slider') {
                    $layout_variant = sanitize_key($section['layout_variant'] ?? 'slider');
                }
                if (!isset(self::component_catalog()[$designer_component_type]) && !isset(self::component_catalog()[$type])) continue;
                $item = $this->clean_value($section);
                $item['id'] = sanitize_key($section['id'] ?? wp_generate_uuid4());
                $item['type'] = $type;
                $item['designer_component_type'] = $designer_component_type;
                $item['layout_variant'] = $layout_variant !== '' ? $layout_variant : ($item['layout_variant'] ?? '');
                $item['visible'] = !array_key_exists('visible', $section) || (bool) $section['visible'];
                if (isset($section['image_url'])) $item['image_url'] = $this->clean_url($section['image_url']);
                if (isset($section['background_image_url'])) $item['background_image_url'] = $this->clean_url($section['background_image_url']);
                if (isset($section['visible_pages']) && is_array($section['visible_pages'])) $item['visible_pages'] = array_values(array_intersect($allowed_page_keys, array_map('sanitize_key', $section['visible_pages'])));
                $item['show_icon'] = !array_key_exists('show_icon', $section) || (bool) $section['show_icon'];
                $item['show_title'] = !array_key_exists('show_title', $section) || (bool) $section['show_title'];
                $item['heading_animation'] = in_array(($section['heading_animation'] ?? 'none'), ['none','fade','slide','pulse','gradient'], true) ? ($section['heading_animation'] ?? 'none') : 'none';
                $item['heading_icon_animation'] = in_array(($section['heading_icon_animation'] ?? 'none'), ['none','pulse','rotate','bounce'], true) ? ($section['heading_icon_animation'] ?? 'none') : 'none';
                $tap_mode = in_array(($section['tap_link_type'] ?? 'in_app'), ['in_app','deeplink','chrome'], true)
                    ? ($section['tap_link_type'] ?? 'in_app') : 'in_app';
                $item['tap_link_type'] = $tap_mode;
                $tap_value = (string) ($section['tap_url'] ?? '');
                $item['tap_url'] = $tap_mode === 'chrome'
                    ? $this->clean_url($tap_value) : sanitize_text_field($tap_value);
                if ($type === 'ark_live_slider') {
                    $slider_key = sanitize_key($section['slider_key'] ?? $section['id'] ?? wp_generate_uuid4());
                    $item['slider_key'] = $slider_key;
                    if ($legacy_banner_slider) {
                        $slides = $section['items'] ?? $section['slides'] ?? $section['images'] ?? $section['children'] ?? [];
                        if (!is_array($slides)) $slides = [];
                        $is_list = ($slides === []) || (array_keys($slides) === range(0, count($slides) - 1));
                        if (!$is_list && (isset($slides['image_url']) || isset($slides['image']) || isset($slides['src']) || isset($slides['url']) || isset($slides['attachment_id']))) {
                            $slides = [$slides];
                        } elseif (!$is_list && isset($slides['items']) && is_array($slides['items'])) {
                            $slides = $slides['items'];
                        } elseif (!$is_list && isset($slides['slides']) && is_array($slides['slides'])) {
                            $slides = $slides['slides'];
                        }
                        $legacy_settings = [
                            'title' => $section['title'] ?? 'Live Slider',
                            'height_px' => $section['height'] ?? 178,
                            'interval_ms' => $section['interval_ms'] ?? 3500,
                            'autoplay' => $section['autoplay'] ?? true,
                            'loop_slides' => true,
                            'transition' => 'slide',
                            'image_fit' => $section['fit'] ?? 'cover',
                            'card_width' => $section['card_width'] ?? 0.92,
                            'slide_gap' => $section['slide_gap'] ?? 8,
                            'show_arrows' => $section['show_arrows'] ?? false,
                            'pagination' => !empty($section['show_dots']) ? 'dots' : 'none',
                            'pause_on_interaction' => true,
                        ];
                        $saved_slider = $this->save_live_slider_payload($slider_key, $legacy_settings, $slides);
                        if (is_wp_error($saved_slider)) return $saved_slider;
                    }
                    unset($item['items'], $item['slides'], $item['images'], $item['image_url'], $item['button_url']);
                }
                foreach (['width_pct' => [10, 100], 'height_px' => [0, 2000], 'min_height_px' => [0, 2000], 'max_width_px' => [0, 2000], 'padding_top' => [0, 120], 'padding_right' => [0, 120], 'padding_bottom' => [0, 120], 'padding_left' => [0, 120], 'margin_top' => [0, 120], 'margin_right' => [0, 120], 'margin_bottom' => [0, 120], 'margin_left' => [0, 120], 'corner_radius' => [0, 60]] as $layout_key => $range) {
                    if (isset($section[$layout_key])) $item[$layout_key] = min($range[1], max($range[0], (float) $section[$layout_key]));
                }
                $item['alignment'] = in_array(($section['alignment'] ?? 'stretch'), ['stretch','left','center','right'], true) ? ($section['alignment'] ?? 'stretch') : 'stretch';
                $item['section_background'] = sanitize_hex_color($section['section_background'] ?? '') ?: 'transparent';
                $item['text_color'] = sanitize_hex_color($section['text_color'] ?? '') ?: '#202733';
                $item['card_background'] = sanitize_hex_color($section['card_background'] ?? '') ?: '#FFFFFF';
                $item['item_gap'] = min(40, max(0, absint($section['item_gap'] ?? 8)));
                $entrance_animation = sanitize_key($section['entrance_animation'] ?? 'none');
                $entrance_aliases = [
                    'fade_in' => 'fade',
                    'slide_in' => 'slide',
                    'slide_up' => 'slide',
                    'zoom_in' => 'zoom',
                    'bounce_in' => 'bounce',
                    'rotate_in' => 'rotate',
                    'flip_3d' => 'flip',
                ];
                $entrance_animation = $entrance_aliases[$entrance_animation] ?? $entrance_animation;
                $item['entrance_animation'] = in_array($entrance_animation, ['none','fade','slide','zoom','bounce','rotate','flip'], true)
                    ? $entrance_animation : 'none';
                $item['entrance_duration_ms'] = min(1600, max(250, absint($section['entrance_duration_ms'] ?? 520)));
                $item['card_animation'] = in_array(($section['card_animation'] ?? 'none'), ['none','tilt_3d','glow','fade','scale','slide','pulse'], true)
                    ? ($section['card_animation'] ?? 'none') : 'none';
                $item['background_motion'] = in_array(($section['background_motion'] ?? 'none'), ['none','pulse','gradient','shimmer','glow','fireworks'], true) ? ($section['background_motion'] ?? 'none') : 'none';
                if (isset($section['card_design'])) {
                    $item['card_design'] = in_array($section['card_design'], ['flipkart','classic','elevated','glass','compact','minimal','3d','3d_glass'], true)
                        ? $section['card_design'] : 'flipkart';
                }
                $item['card_radius'] = min(40, max(0, absint($section['card_radius'] ?? 14)));
                $item['card_depth'] = min(30, max(0, absint($section['card_depth'] ?? 12)));
                $item['card_aspect_ratio'] = min(1.20, max(0.48, (float) ($section['card_aspect_ratio'] ?? 0.66)));
                $item['card_image_fit'] = in_array(($section['card_image_fit'] ?? 'contain'), ['contain','cover'], true)
                    ? ($section['card_image_fit'] ?? 'contain') : 'contain';
                if (in_array($type, ['product_grid','product_carousel'], true) || in_array($designer_component_type, ['product_grid','product_carousel','product_list','product_scroll'], true)) {
                    $allowed_sources = ['latest','featured','sale','category','top_rated','wishlist','recent'];
                    $item['source'] = in_array(($section['source'] ?? 'latest'), $allowed_sources, true)
                        ? ($section['source'] ?? 'latest') : 'latest';
                    $item['category_id'] = absint($section['category_id'] ?? 0);
                    $item['tap_action_type'] = in_array(($section['tap_action_type'] ?? 'product_detail'), ['product_detail','in_app','deeplink','website'], true)
                        ? ($section['tap_action_type'] ?? 'product_detail') : 'product_detail';
                    $item['card_width'] = min(0.82, max(0.32, (float) ($section['card_width'] ?? 0.46)));
                }
                if ($type === 'category_grid') {
                    $category_layout = $section['category_layout'] ?? ($section['layout_variant'] ?? 'grid');
                    $item['category_layout'] = in_array($category_layout, ['grid','slider','scroll','list','list_tile'], true) ? $category_layout : 'grid';
                    $item['category_card_style'] = in_array(($section['category_card_style'] ?? 'image'), ['image','elevated','minimal','circle'], true)
                        ? ($section['category_card_style'] ?? 'image') : 'image';
                    $item['show_category_count'] = !empty($section['show_category_count']);
                }
                if ($type === 'product_delivery') {
                    $item['delivery_style'] = in_array(($section['delivery_style'] ?? 'standard'), ['compact','standard'], true)
                        ? ($section['delivery_style'] ?? 'standard') : 'standard';
                    $item['show_delivery_details'] = !array_key_exists('show_delivery_details', $section) || (bool) $section['show_delivery_details'];
                    $item['show_pin_badge'] = !array_key_exists('show_pin_badge', $section) || (bool) $section['show_pin_badge'];
                    $item['delivery_title'] = sanitize_text_field($section['delivery_title'] ?? 'Delivery information');
                    $item['delivery_subtitle'] = sanitize_text_field($section['delivery_subtitle'] ?? 'Check delivery availability at your address.');
                    $item['delivery_background'] = sanitize_hex_color($section['delivery_background'] ?? '') ?: '#F8FAFD';
                }
                if ($type === 'product_description') {
                    $item['expanded'] = !empty($section['expanded']);
                    $item['preview_words'] = 80;
                }
                if ($type === 'address_selector') {
                    $item['show_phone'] = !array_key_exists('show_phone', $section) || (bool) $section['show_phone'];
                    $item['show_city_pin'] = !array_key_exists('show_city_pin', $section) || (bool) $section['show_city_pin'];
                    $item['allow_address_switch'] = !array_key_exists('allow_address_switch', $section) || (bool) $section['allow_address_switch'];
                    $item['compact_on_mobile'] = !empty($section['compact_on_mobile']);
                    $item['address_style'] = in_array(($section['address_style'] ?? 'flipkart'), ['flipkart','outlined','compact'], true) ? ($section['address_style'] ?? 'flipkart') : 'flipkart';
                    $item['show_edit'] = !array_key_exists('show_edit', $section) || (bool) $section['show_edit'];
                }
                if ($type === 'account_menu' && is_array($section['items'] ?? null)) {
                    $allowed_actions = ['orders','downloads','address','profile','wishlist','cart','home','categories','support','custom','logout'];
                    $item['items'] = [];
                    foreach (array_slice($section['items'], 0, 30) as $menu_item) {
                        if (!is_array($menu_item)) continue;
                        $menu_action = in_array(($menu_item['action'] ?? 'custom'), $allowed_actions, true) ? ($menu_item['action'] ?? 'custom') : 'custom';
                        $menu_mode = in_array(($menu_item['link_type'] ?? 'chrome'), ['in_app','deeplink','chrome'], true) ? ($menu_item['link_type'] ?? 'chrome') : 'chrome';
                        $menu_link = $menu_item['link'] ?? '';
                        $item['items'][] = [
                            'title' => sanitize_text_field($menu_item['title'] ?? 'Menu item'),
                            'subtitle' => sanitize_text_field($menu_item['subtitle'] ?? ''),
                            'icon' => sanitize_key($menu_item['icon'] ?? 'widgets'),
                            'action' => $menu_action,
                            'link_type' => $menu_mode,
                            'link' => $menu_mode === 'chrome' ? $this->clean_url($menu_link) : sanitize_text_field($menu_link),
                        ];
                    }
                }
                if (isset($section['button_url'])) {
                    $button_mode = in_array(($section['button_link_type'] ?? 'chrome'), ['in_app','deeplink','chrome'], true) ? ($section['button_link_type'] ?? 'chrome') : 'chrome';
                    $item['button_link_type'] = $button_mode;
                    $item['button_url'] = $button_mode === 'chrome' ? $this->clean_url($section['button_url']) : sanitize_text_field($section['button_url']);
                }
                if (isset($section['destination_url'])) {
                    $dest_mode = in_array(($section['destination_link_type'] ?? 'chrome'), ['in_app','deeplink','chrome'], true) ? ($section['destination_link_type'] ?? 'chrome') : 'chrome';
                    $item['destination_link_type'] = $dest_mode;
                    $item['destination_url'] = $dest_mode === 'chrome' ? $this->clean_url($section['destination_url']) : sanitize_text_field($section['destination_url']);
                }
                if ($type === 'product_gallery') {
                    $item['gallery_height'] = min(600, max(220, absint($section['gallery_height'] ?? 390)));
                    $item['gallery_fit'] = in_array(($section['gallery_fit'] ?? 'contain'), ['contain','cover'], true) ? ($section['gallery_fit'] ?? 'contain') : 'contain';
                    $item['auto_slide'] = !array_key_exists('auto_slide', $section) || (bool) $section['auto_slide'];
                    $item['auto_slide_interval_ms'] = min(15000, max(1800, absint($section['auto_slide_interval_ms'] ?? 4200)));
                    $item['show_dots'] = !array_key_exists('show_dots', $section) || (bool) $section['show_dots'];
                    $item['allow_zoom'] = !array_key_exists('allow_zoom', $section) || (bool) $section['allow_zoom'];
                    $item['show_wishlist'] = !array_key_exists('show_wishlist', $section) || (bool) $section['show_wishlist'];
                    $item['show_share'] = !array_key_exists('show_share', $section) || (bool) $section['show_share'];
                    $item['action_layout'] = in_array(($section['action_layout'] ?? 'vertical'), ['vertical','horizontal'], true) ? ($section['action_layout'] ?? 'vertical') : 'vertical';
                    foreach (['action_button_size' => [36,64,44], 'action_icon_size' => [16,30,21], 'action_gap' => [4,24,9], 'action_offset_top' => [4,80,14], 'action_offset_right' => [4,32,14]] as $key => $range) {
                        $item[$key] = min($range[1], max($range[0], (int) ($section[$key] ?? $range[2])));
                    }
                    foreach (['action_background','action_icon_color'] as $key) {
                        $item[$key] = sanitize_hex_color($section[$key] ?? '') ?: ($key === 'action_background' ? '#FFFFFF' : '#263248');
                    }
                }
                if ($type === 'product_recommended') {
                    $item['promo_autoplay'] = !array_key_exists('promo_autoplay', $section) || (bool) $section['promo_autoplay'];
                    $item['promo_interval_ms'] = min(15000, max(1800, absint($section['promo_interval_ms'] ?? 4000)));
                    $item['promo_card_height'] = min(150, max(88, absint($section['promo_card_height'] ?? 112)));
                    $item['promo_card_width'] = min(320, max(180, absint($section['promo_card_width'] ?? 238)));
                    $item['promo_image_size'] = min(100, max(48, absint($section['promo_image_size'] ?? 72)));
                    $item['show_ad_badge'] = !array_key_exists('show_ad_badge', $section) || (bool) $section['show_ad_badge'];
                    $item['ad_label'] = sanitize_text_field($section['ad_label'] ?? 'AD');
                    $item['show_discount'] = !array_key_exists('show_discount', $section) || (bool) $section['show_discount'];
                    $item['show_original_price'] = !array_key_exists('show_original_price', $section) || (bool) $section['show_original_price'];
                }
                $page['sections'][] = $item;
            }
            $page['title'] = sanitize_text_field($page['title'] ?? ucfirst($page_key));
            $pages[$page_key] = $page;
        }
        $clean['pages'] = array_intersect_key($pages, array_flip($allowed_pages));
        $navigation = is_array($clean['navigation'] ?? null) ? $clean['navigation'] : $base['navigation'];
        $navigation['visible'] = !empty($navigation['visible']);
        $navigation['style'] = in_array(($navigation['style'] ?? 'classic'), ['classic','pill','floating','minimal'], true)
            ? ($navigation['style'] ?? 'classic') : 'classic';
        $navigation['items'] = array_values(array_map(function($item) {
            if (!is_array($item)) return null;
            $item['key'] = sanitize_key($item['key'] ?? 'custom');
            $item['label'] = sanitize_text_field($item['label'] ?? 'Menu');
            $item['icon'] = sanitize_text_field($item['icon'] ?? 'widgets');
            $item['link_type'] = in_array(($item['link_type'] ?? 'in_app'), ['in_app','deeplink','chrome'], true) ? $item['link_type'] : 'in_app';
            $item['target'] = sanitize_text_field($item['target'] ?? $item['key']);
            if ($item['link_type'] === 'chrome') $item['url'] = $this->clean_url($item['url'] ?? '');
            else $item['url'] = sanitize_text_field($item['url'] ?? '');
            $item['animation'] = in_array(($item['animation'] ?? 'none'), ['none','fade','scale','slide'], true) ? $item['animation'] : 'none';
            return $item;
        }, array_slice(array_filter((array) ($navigation['items'] ?? []), 'is_array'), 0, 5)));

        $clean['navigation'] = $navigation;
        // Preserve every App Identity field. Previously this whitelist kept only
        // name/tagline/icon_url, silently discarding logo and splash settings on publish.
        $app = is_array($clean['app'] ?? null) ? $clean['app'] : [];
        $splash_image_url = $this->clean_url($app['splash_image_url'] ?? ($splash['image_url'] ?? ''));
        $splash_background = sanitize_hex_color($app['splash_background'] ?? ($splash['background'] ?? '')) ?: $base['splash']['background'];
        $splash_fit = in_array(($app['splash_fit'] ?? ($splash['fit'] ?? 'contain')), ['contain','cover','fill'], true)
            ? ($app['splash_fit'] ?? ($splash['fit'] ?? 'contain')) : 'contain';
        $splash_duration = min(8000, max(500, absint($app['splash_duration_ms'] ?? ($splash['duration_ms'] ?? 1800))));
        $splash_enabled = array_key_exists('splash_enabled', $app)
            ? (bool) $app['splash_enabled'] : (bool) ($splash['enabled'] ?? true);
        $clean['app'] = [
            'name' => sanitize_text_field($app['name'] ?? 'GlowHit'),
            'tagline' => sanitize_text_field($app['tagline'] ?? ''),
            'icon_url' => $this->clean_url($app['icon_url'] ?? ''),
            'logo_url' => $this->clean_url($app['logo_url'] ?? ''),
            'show_icon' => !array_key_exists('show_icon', $app) || (bool) $app['show_icon'],
            'show_name' => !array_key_exists('show_name', $app) || (bool) $app['show_name'],
            'show_refresh' => !array_key_exists('show_refresh', $app) || (bool) $app['show_refresh'],
            'show_notifications' => !array_key_exists('show_notifications', $app) || (bool) $app['show_notifications'],
            'show_cart' => !array_key_exists('show_cart', $app) || (bool) $app['show_cart'],
            'header_icon_animation' => in_array(($app['header_icon_animation'] ?? 'none'), ['none','pulse','rotate','bounce'], true) ? ($app['header_icon_animation'] ?? 'none') : 'none',
            'icon_visibility' => is_array($app['icon_visibility'] ?? null) ? $app['icon_visibility'] : [],
            'splash_image_url' => $splash_image_url,
            'splash_enabled' => $splash_enabled,
            'splash_duration_ms' => $splash_duration,
            'splash_fit' => $splash_fit,
            'splash_background' => $splash_background,
        ];
        // Keep legacy splash fields in sync for older app builds and clients.
        $clean['splash']['image_url'] = $splash_image_url;
        $clean['splash']['enabled'] = $splash_enabled;
        $clean['splash']['duration_ms'] = $splash_duration;
        $clean['splash']['fit'] = $splash_fit;
        $clean['splash']['background'] = $splash_background;
        return $clean;
    }

    public function api_save_draft($request) {
        $baseline = $this->current_editor_config();
        $clean = $this->sanitize_config($request->get_json_params(), is_array($baseline) ? $baseline : $this->current_editor_config());
        if (is_wp_error($clean)) return $clean;
        update_option(self::DRAFT, $clean, false);
        return rest_ensure_response(['success' => true, 'version' => $clean['version']]);
    }

    public function api_publish($request) {
        $baseline = $this->current_editor_config();
        $clean = $this->sanitize_config($request->get_json_params(), is_array($baseline) ? $baseline : $this->current_editor_config());
        if (is_wp_error($clean)) return $clean;
        $current = get_option(self::PUBLISHED, self::default_config());
        $clean['version'] = max(1, absint($current['version'] ?? 0) + 1);
        $clean['updated_at'] = gmdate('c');

        $history = get_option(self::REVISIONS, []);
        if (!is_array($history)) $history = [];
        array_unshift($history, [
            'version' => absint($current['version'] ?? 1),
            'published_at' => $current['updated_at'] ?? gmdate('c'),
            'config' => $current,
        ]);
        update_option(self::REVISIONS, array_slice($history, 0, 20), false);
        update_option(self::PUBLISHED, $clean, false);
        update_option(self::DRAFT, $clean, false);
        return rest_ensure_response(['success' => true, 'version' => $clean['version'], 'updated_at' => $clean['updated_at']]);
    }

    private function github_settings() {
        $settings = get_option(self::GITHUB_SETTINGS, []);
        if (!is_array($settings)) $settings = [];
        return [
            'owner' => sanitize_user($settings['owner'] ?? 'arshadzen023-byte', true),
            'repo' => sanitize_title($settings['repo'] ?? 'ark-native-app'),
            'branch' => sanitize_text_field($settings['branch'] ?? 'main'),
            'token' => (string) ($settings['token'] ?? ''),
        ];
    }

    public function api_github_save_settings($request) {
        $input = $request->get_json_params();
        if (!is_array($input)) return new WP_Error('ark_github_settings', 'Invalid GitHub settings.', ['status' => 400]);
        $current = $this->github_settings();
        $owner = sanitize_user($input['owner'] ?? $current['owner'], true);
        $repo = sanitize_title($input['repo'] ?? $current['repo']);
        $branch = sanitize_text_field($input['branch'] ?? $current['branch']);
        $token = trim((string) ($input['token'] ?? ''));
        if ($token === '') $token = $current['token'];
        if ($owner === '' || $repo === '' || $branch === '' || strlen($token) < 20) {
            return new WP_Error('ark_github_settings', 'Enter repository owner, name, branch and a valid fine-grained token.', ['status' => 400]);
        }
        update_option(self::GITHUB_SETTINGS, [
            'owner' => $owner,
            'repo' => $repo,
            'branch' => $branch,
            'token' => sanitize_text_field($token),
        ], false);
        return rest_ensure_response(['success' => true, 'owner' => $owner, 'repo' => $repo, 'branch' => $branch, 'token_saved' => true]);
    }

    private function github_api($method, $endpoint, $body = null) {
        $settings = $this->github_settings();
        if ($settings['token'] === '') {
            return new WP_Error('ark_github_token_missing', 'Save a GitHub fine-grained token in ARK App Designer settings first.', ['status' => 400]);
        }
        $url = 'https://api.github.com/repos/' . rawurlencode($settings['owner']) . '/' . rawurlencode($settings['repo']) . '/' . ltrim($endpoint, '/');
        $args = [
            'method' => strtoupper($method),
            'timeout' => 25,
            'headers' => [
                'Accept' => 'application/vnd.github+json',
                'Authorization' => 'Bearer ' . $settings['token'],
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'ARK-App-Designer',
            ],
        ];
        if ($body !== null) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = wp_json_encode($body);
        }
        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) return $response;
        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300) {
            $message = is_array($data) && !empty($data['message']) ? sanitize_text_field($data['message']) : 'GitHub API request failed.';
            return new WP_Error('ark_github_api', $message, ['status' => $code >= 400 && $code < 600 ? $code : 502]);
        }
        return is_array($data) ? $data : [];
    }

    private function allowed_source_path($path) {
        $path = trim(str_replace('\\', '/', (string) $path), '/');
        if ($path === '' || strpos($path, '..') !== false || strpos($path, ':') !== false) return false;
        $roots = ['flutter-app/', 'studio-app/', 'wordpress-addon/', '.github/workflows/', 'docs/'];
        $inside = false;
        foreach ($roots as $root) {
            if (strpos($path, $root) === 0) { $inside = true; break; }
        }
        if (!$inside && !in_array($path, ['README.md', '.gitignore'], true)) return false;
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $text_extensions = ['dart','yaml','yml','xml','gradle','kts','properties','json','md','txt','php','css','js','kt','java','sh','gitignore','arb','plist','strings','xcconfig','entitlements','toml','lock','html','scss','svg','sql','bat','ps1'];
        return in_array($extension, $text_extensions, true) || in_array($path, ['README.md', '.gitignore'], true);
    }

    public function api_github_tree($request) {
        $settings = $this->github_settings();
        // GitHub's Git Trees API needs a tree SHA, not a branch name.
        // Resolve branch -> commit SHA -> root tree SHA before listing files.
        $ref = $this->github_api('GET', 'git/ref/heads/' . rawurlencode($settings['branch']));
        if (is_wp_error($ref)) return $ref;
        $commit_sha = sanitize_text_field($ref['object']['sha'] ?? '');
        if ($commit_sha === '') return new WP_Error('ark_github_ref', 'GitHub did not return the selected branch commit.', ['status' => 502]);

        $commit = $this->github_api('GET', 'git/commits/' . rawurlencode($commit_sha));
        if (is_wp_error($commit)) return $commit;
        $tree_sha = sanitize_text_field($commit['tree']['sha'] ?? '');
        if ($tree_sha === '') return new WP_Error('ark_github_tree', 'GitHub did not return the selected commit tree.', ['status' => 502]);

        $tree = $this->github_api('GET', 'git/trees/' . rawurlencode($tree_sha) . '?recursive=1');
        if (is_wp_error($tree)) return $tree;
        $rows = [];
        foreach ((array) ($tree['tree'] ?? []) as $node) {
            if (($node['type'] ?? '') !== 'blob') continue;
            $path = (string) ($node['path'] ?? '');
            if (!$this->allowed_source_path($path)) continue;
            $rows[] = [
                'path' => $path,
                'name' => basename($path),
                'size' => absint($node['size'] ?? 0),
                'sha' => sanitize_text_field($node['sha'] ?? ''),
            ];
        }
        return rest_ensure_response([
            'branch' => $settings['branch'],
            'files' => array_slice($rows, 0, 500),
        ]);
    }

    public function api_github_read_file($request) {
        $path = sanitize_text_field($request->get_param('path') ?? '');
        if (!$this->allowed_source_path($path)) return new WP_Error('ark_github_path', 'This file path is outside the editable app source directories.', ['status' => 403]);
        $settings = $this->github_settings();
        $file = $this->github_api('GET', 'contents/' . implode('/', array_map('rawurlencode', explode('/', $path))) . '?ref=' . rawurlencode($settings['branch']));
        if (is_wp_error($file)) return $file;
        if (($file['type'] ?? '') !== 'file' || empty($file['content'])) return new WP_Error('ark_github_file', 'GitHub did not return a text file.', ['status' => 404]);
        $content = base64_decode(str_replace(["\n", "\r"], '', (string) $file['content']), true);
        if ($content === false || strlen($content) > 1048576) return new WP_Error('ark_github_file_size', 'Only text files up to 1 MB can be edited here.', ['status' => 413]);
        return rest_ensure_response([
            'path' => $path,
            'sha' => sanitize_text_field($file['sha'] ?? ''),
            'content' => $content,
            'size' => strlen($content),
            'branch' => $settings['branch'],
        ]);
    }

    public function api_github_save_file($request) {
        $data = $request->get_json_params();
        $path = sanitize_text_field($data['path'] ?? '');
        $content = (string) ($data['content'] ?? '');
        $sha = sanitize_text_field($data['sha'] ?? '');
        $message = sanitize_text_field($data['message'] ?? '');
        if (!$this->allowed_source_path($path)) return new WP_Error('ark_github_path', 'This file path is outside the editable app source directories.', ['status' => 403]);
        if (strlen($content) > 1048576) return new WP_Error('ark_github_file_size', 'File exceeds the 1 MB editor limit.', ['status' => 413]);
        if ($message === '') $message = 'Update ' . basename($path) . ' from ARK App Designer';
        $settings = $this->github_settings();
        $payload = [
            'message' => $message,
            'content' => base64_encode($content),
            'branch' => $settings['branch'],
        ];
        if ($sha !== '') $payload['sha'] = $sha;
        $saved = $this->github_api('PUT', 'contents/' . implode('/', array_map('rawurlencode', explode('/', $path))), $payload);
        if (is_wp_error($saved)) return $saved;
        return rest_ensure_response([
            'success' => true,
            'path' => $path,
            'sha' => sanitize_text_field($saved['content']['sha'] ?? ''),
            'commit' => sanitize_text_field($saved['commit']['sha'] ?? ''),
            'branch' => $settings['branch'],
        ]);
    }

    public function render_admin() {
        if (!current_user_can('manage_options')) return;
        echo '<div class="wrap ark-designer-wrap"><div class="ark-designer-app">';
        echo '<header class="ark-topbar"><div class="ark-brand"><span class="ark-brand-mark">A</span><div><strong>ARK App Designer</strong><small>Visual studio for GlowHit</small></div></div>';
        echo '<div class="ark-top-actions"><span class="ark-status"><i></i> Connected to WordPress</span><button class="ark-btn ark-btn-ghost" id="ark-save-draft">Save draft</button><button class="ark-btn ark-btn-publish" id="ark-publish">Publish changes ↗</button></div></header>';
        echo '<div class="ark-release-stamp" data-ark-designer-version="1.17.2" data-ark-source-revision="1.17.2"><strong>ARK App Designer 1.17.2</strong><span>Layout Studio</span></div>';
        echo '<main class="ark-workspace"><aside class="ark-sidebar"><button type="button" class="ark-panel-toggle" id="ark-toggle-left" aria-label="Collapse components panel">☰ Components</button><div class="ark-sidebar-title">BUILD YOUR APP</div><div class="ark-page-list" id="ark-pages"></div><div class="ark-sidebar-divider"></div><button type="button" class="ark-quick-link" data-view="default_heading">▤ Default heading bar</button><button type="button" class="ark-quick-link" id="ark-add-page">＋ Add New Page</button><button type="button" class="ark-quick-link" id="ark-page-settings" data-view="page_settings" style="display:none">⚙ Page Settings</button><div class="ark-sidebar-divider"></div><div class="ark-sidebar-title">ADD COMPONENT</div><div class="ark-component-list" id="ark-components"></div><div class="ark-sidebar-divider"></div><button class="ark-quick-link" data-view="app">✎ App name & identity</button><button class="ark-quick-link" data-view="theme">◈ Theme & colours</button><button class="ark-quick-link" data-view="celebration">✧ Crackers animation</button><button class="ark-quick-link" data-view="loading">◌ Loading animation</button><button class="ark-quick-link" data-view="navigation">☷ Navigation</button><button class="ark-quick-link" data-view="icon_visibility">◉ Icon visibility</button><button class="ark-quick-link" data-view="source">⌘ App source files</button></aside>';
        echo '<section class="ark-canvas-area"><div class="ark-canvas-toolbar"><div><strong id="ark-canvas-title">Home page</strong><span id="ark-canvas-subtitle">Drag sections or use the arrows to change order</span></div><div class="ark-canvas-tools"><span class="ark-live-pill">● LIVE PREVIEW</span><button class="ark-icon-btn" id="ark-preview-refresh" title="Refresh preview">↻</button></div></div>';
        echo '<div class="ark-canvas-scroll"><div class="ark-phone"><div class="ark-phone-status"><span>9:41</span><span>● ● ▰</span></div><div class="ark-phone-screen" id="ark-preview"></div><div class="ark-phone-nav"><span>⌂</span><span>▦</span><span>♡</span><span>♙</span></div></div></div></section>';
        echo '<aside class="ark-inspector"><div class="ark-inspector-head"><button type="button" class="ark-panel-toggle" id="ark-toggle-right" aria-label="Collapse properties panel">⚙ Properties</button><span>PROPERTIES</span><strong id="ark-inspector-title">Select a section</strong></div><div id="ark-inspector-content" class="ark-inspector-content"><div class="ark-empty-inspector">Choose a component from the canvas to edit its settings.</div></div></aside></main>';
        echo '<nav class="ark-layout-dock" id="ark-layout-dock" aria-label="Layout Designer"><div class="ark-layout-dock-heading"><span class="ark-layout-dock-mark">A</span><div><strong>Layout Designer</strong><small>Live app studio · edits sync to the same published config</small></div><span class="ark-layout-dock-hint">SELECT · EDIT · PUBLISH</span></div><div class="ark-layout-dock-tabs">';
        $layout_tabs = [
            'components' => ['▦','Components'],
            'properties' => ['⚙','Properties'],
            'reorder' => ['↕','Reorder'],
            'templates' => ['▣','My templates'],
            'pages' => ['▤','Pages'],
            'slide_menu' => ['☷','Slide menu'],
            'menu_edit' => ['✎','Menu edit'],
            'navigation' => ['⌑','Navigation'],
            'header' => ['▱','Top header'],
            'search' => ['⌕','Search'],
            'animations' => ['✧','Animations'],
            'account' => ['♙','Account page'],
        ];
        foreach ($layout_tabs as $key => $tab) {
            echo '<button type="button" class="ark-layout-tab" data-layout-tab="' . esc_attr($key) . '"><span>' . esc_html($tab[0]) . '</span><small>' . esc_html($tab[1]) . '</small></button>';
        }
        echo '</div><div class="ark-layout-dock-panel" id="ark-layout-dock-panel" hidden></div></nav>';
        echo '<footer class="ark-footer"><span>ARK App Designer · Live Layout Studio</span><span id="ark-version-label">Draft</span><span>Save draft or Publish to sync the supported native app.</span></footer></div></div>';
    }
}

register_activation_hook(__FILE__, ['ARK_App_Designer', 'activate']);
new ARK_App_Designer();
