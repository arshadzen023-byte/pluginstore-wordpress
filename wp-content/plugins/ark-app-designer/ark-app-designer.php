<?php
/**
 * Plugin Name: ARK App Designer
 * Description: Server-driven visual app designer for ARK Native / GlowHit Flutter apps, including live layout, navigation, theme and in-app splash configuration.
 * Version: 1.7.0
 * Author: ARK
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

final class ARK_App_Designer {
    const VERSION = '1.7.0';
    const PUBLISHED = 'ark_app_designer_published';
    const DRAFT = 'ark_app_designer_draft';
    const REVISIONS = 'ark_app_designer_revisions';
    const GITHUB_SETTINGS = 'ark_app_designer_github_settings';
    const REST_NAMESPACE = 'ark-designer/v1';

    public function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('rest_api_init', [$this, 'register_routes']);
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
    }

    public static function default_config() {
        return [
            'schema_version' => 1,
            'version' => 1,
            'updated_at' => gmdate('c'),
            'app' => ['name' => 'GlowHit', 'tagline' => 'Shop smarter', 'icon_url' => '', 'logo_url' => '', 'splash_image_url' => '', 'splash_enabled' => true, 'splash_duration_ms' => 1800, 'splash_fit' => 'contain', 'splash_background' => '#F5F7FB'],
            
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
                'animation_url' => 'https://pluginstore.shop/wp-content/themes/ark-material-commerce/assets/animations/loading.json',
                'duration_ms' => 900,
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
                    ['id' => 'home-search', 'type' => 'search', 'visible' => true, 'title' => 'Search products', 'placeholder' => 'Search products...'],
                    ['id' => 'home-hero', 'type' => 'banner_slider', 'visible' => true, 'title' => 'Featured', 'height' => 178, 'items' => []],
                    ['id' => 'home-cats', 'type' => 'category_grid', 'visible' => true, 'title' => 'Shop by category', 'limit' => 8, 'columns' => 4, 'style' => 'rounded'],
                    ['id' => 'home-featured', 'type' => 'product_carousel', 'visible' => true, 'title' => 'Featured products', 'source' => 'featured', 'limit' => 10],
                    ['id' => 'home-offer', 'type' => 'offer_banner', 'visible' => true, 'title' => 'Special offers', 'subtitle' => 'Explore today’s best deals', 'button_label' => 'Shop now', 'button_url' => ''],
                    ['id' => 'home-recent', 'type' => 'recently_viewed', 'visible' => true, 'title' => 'Recently viewed', 'limit' => 8],
                    ['id' => 'home-products', 'type' => 'product_grid', 'visible' => true, 'title' => 'Shop products', 'source' => 'latest', 'limit' => 12, 'columns' => 2],
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
        wp_enqueue_style('ark-app-designer-admin', plugin_dir_url(__FILE__) . 'assets/admin.css', [], self::VERSION);
        wp_enqueue_script('ark-app-designer-admin', plugin_dir_url(__FILE__) . 'assets/admin.js', ['jquery'], self::VERSION, true);
        $config = get_option(self::DRAFT, get_option(self::PUBLISHED, self::default_config()));
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
            'search' => ['label' => 'Search bar', 'icon' => '⌕', 'description' => 'Native WooCommerce product search'],
            'notice_bar' => ['label' => 'Announcement strip', 'icon' => '▰', 'description' => 'Compact store message with optional link'],
            'icon_grid' => ['label' => 'Icon shortcuts', 'icon' => '⊞', 'description' => 'Grid of icon links and quick actions'],
            'countdown' => ['label' => 'Countdown card', 'icon' => '◷', 'description' => 'Live countdown to a selected deadline'],
            'banner_slider' => ['label' => 'Image slider', 'icon' => '▧', 'description' => 'Clickable promotional image carousel'],
            'category_grid' => ['label' => 'Category grid', 'icon' => '▦', 'description' => 'Live WooCommerce categories'],
            'product_carousel' => ['label' => 'Product slider', 'icon' => '▤', 'description' => 'Featured, latest, sale or category products'],
            'product_grid' => ['label' => 'Product grid', 'icon' => '▦', 'description' => 'Responsive product cards'],
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
            'product_gallery' => ['label' => 'Product gallery', 'icon' => '▧', 'description' => 'Native product image gallery'],
            'product_title' => ['label' => 'Product title', 'icon' => 'T', 'description' => 'Native WooCommerce product title'],
            'product_price' => ['label' => 'Price card', 'icon' => '₹', 'description' => 'Native product price and savings'],
            'product_delivery' => ['label' => 'Delivery card', 'icon' => '⌖', 'description' => 'Native delivery information'],
            'product_description' => ['label' => 'Description', 'icon' => '≡', 'description' => 'Native expandable description'],
            'product_specs' => ['label' => 'Specifications', 'icon' => '☷', 'description' => 'Native product attributes'],
            'product_reviews' => ['label' => 'Reviews', 'icon' => '★', 'description' => 'Native reviews area'],
            'product_offers' => ['label' => 'Product offers', 'icon' => '%', 'description' => 'Native sale and savings card'],
            'product_recommended' => ['label' => 'Recommended offers', 'icon' => '✦', 'description' => 'Promoted products configured in WooCommerce'],
            'account_profile' => ['label' => 'Profile card', 'icon' => '☺', 'description' => 'Native signed-in account details'],
            'account_menu' => ['label' => 'Account menu', 'icon' => '☰', 'description' => 'Native account actions'],
            'address_selector' => ['label' => 'Delivery address selector', 'icon' => '⌖', 'description' => 'Show the saved delivery address with an edit action'],
        ];
    }

    public function register_routes() {
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
        return rest_ensure_response($config);
    }

    public function api_studio_config() {
        return rest_ensure_response(get_option(self::DRAFT, get_option(self::PUBLISHED, self::default_config())));
    }

    /** Return real native page and WooCommerce product destinations for slider links. */
    public function api_studio_targets($request) {
        $products = [];
        if (function_exists('wc_get_products')) {
            $found = wc_get_products([
                'status' => 'publish', 'limit' => 500, 'orderby' => 'title',
                'order' => 'ASC', 'return' => 'objects',
            ]);
            foreach ($found as $product) {
                $products[] = [
                    'id' => (int) $product->get_id(),
                    'name' => wp_strip_all_tags($product->get_name()),
                ];
            }
        }
        return rest_ensure_response([
            'pages' => [
                ['value' => 'page:home', 'label' => 'Home page'],
                ['value' => 'page:categories', 'label' => 'Categories page'],
                ['value' => 'page:product', 'label' => 'Product page'],
                ['value' => 'page:account', 'label' => 'Account page'],
                ['value' => 'page:wishlist', 'label' => 'Wishlist page'],
                ['value' => 'page:cart', 'label' => 'Cart page'],
            ],
            'products' => $products,
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

    private function sanitize_config($raw) {
        if (!is_array($raw)) return new WP_Error('ark_designer_invalid', 'Design configuration must be an object.', ['status' => 400]);
        $base = self::default_config();
        $clean = $this->clean_value($raw);
        if (!is_array($clean)) return new WP_Error('ark_designer_invalid', 'Invalid design configuration.', ['status' => 400]);

        $clean['schema_version'] = 1;
        $clean['version'] = max(1, absint($raw['version'] ?? 1));
        $clean['updated_at'] = gmdate('c');

        $theme = is_array($clean['theme'] ?? null) ? $clean['theme'] : [];
        foreach (['primary','accent','background','surface','text','muted'] as $color) {
            $candidate = sanitize_hex_color($theme[$color] ?? '');
            $theme[$color] = $candidate ?: $base['theme'][$color];
        }
        foreach (['header_background','header_text','navigation_background','navigation_indicator'] as $color) {
            $candidate = sanitize_hex_color($theme[$color] ?? '');
            $theme[$color] = $candidate ?: ($base['theme'][$color] ?? '#FFFFFF');
        }
        $theme['header_animation'] = in_array(($theme['header_animation'] ?? 'none'), ['none','gradient','pulse'], true)
            ? ($theme['header_animation'] ?? 'none') : 'none';
        $theme['radius'] = min(32, max(0, absint($theme['radius'] ?? 18)));
        $theme['font_scale'] = min(1.3, max(0.85, (float) ($theme['font_scale'] ?? 1)));
        $clean['theme'] = $theme;

        $splash = is_array($clean['splash'] ?? null) ? $clean['splash'] : [];
        $splash['enabled'] = !empty($splash['enabled']);
        $splash['image_url'] = $this->clean_url($splash['image_url'] ?? '');
        $splash['duration_ms'] = min(8000, max(500, absint($splash['duration_ms'] ?? 1800)));
        $splash['fit'] = in_array(($splash['fit'] ?? 'contain'), ['contain','cover','fill'], true) ? $splash['fit'] : 'contain';
        $splash['background'] = sanitize_hex_color($splash['background'] ?? '') ?: $base['splash']['background']; 
        unset($splash['logo_url'], $splash['title'], $splash['subtitle'], $splash['animation']);
        $clean['splash'] = $splash;
        $loading = is_array($clean['loading'] ?? null) ? $clean['loading'] : $base['loading'];
        $loading['enabled'] = !empty($loading['enabled']);
        $loading['animation_url'] = $this->clean_url($loading['animation_url'] ?? '') ?: $base['loading']['animation_url'];
        $loading['duration_ms'] = min(5000, max(250, absint($loading['duration_ms'] ?? 900)));
        $allowed_loading_pages = ['home','categories','product','account'];
        $loading_pages = is_array($loading['pages'] ?? null) ? $loading['pages'] : $allowed_loading_pages;
        $loading['pages'] = array_values(array_intersect($allowed_loading_pages, array_map('sanitize_key', $loading_pages)));
        $clean['loading'] = $loading;

        $celebration = is_array($clean['celebration'] ?? null) ? $clean['celebration'] : $base['celebration'];
        $celebration['enabled'] = !empty($celebration['enabled']);
        $celebration['duration_ms'] = min(10000, max(1000, absint($celebration['duration_ms'] ?? 4200)));
        $celebration['pages'] = ['home'];
        $clean['celebration'] = $celebration;

        $pages = is_array($clean['pages'] ?? null) ? $clean['pages'] : [];
        $allowed_pages = ['home','categories','product','account','wishlist'];
        foreach ($allowed_pages as $page_key) {
            $page = is_array($pages[$page_key] ?? null) ? $pages[$page_key] : $base['pages'][$page_key];
            $sections = is_array($page['sections'] ?? null) ? array_slice($page['sections'], 0, 50) : [];
            $page['sections'] = [];
            foreach ($sections as $section) {
                if (!is_array($section)) continue;
                $type = sanitize_key($section['type'] ?? '');
                if (!isset(self::component_catalog()[$type])) continue;
                $item = $this->clean_value($section);
                $item['id'] = sanitize_key($section['id'] ?? wp_generate_uuid4());
                $item['type'] = $type;
                $item['visible'] = !array_key_exists('visible', $section) || (bool) $section['visible'];
                if (isset($section['image_url'])) $item['image_url'] = $this->clean_url($section['image_url']);
                if (isset($section['tap_url'])) {
                    $tap_mode = in_array(($section['tap_link_type'] ?? 'in_app'), ['in_app','deeplink','chrome'], true) ? ($section['tap_link_type'] ?? 'in_app') : 'in_app';
                    $item['tap_link_type'] = $tap_mode;
                    $item['tap_url'] = $tap_mode === 'chrome' ? $this->clean_url($section['tap_url']) : sanitize_text_field($section['tap_url']);
                }
                foreach (['width_pct' => [10, 100], 'height_px' => [0, 2000], 'min_height_px' => [0, 2000], 'max_width_px' => [0, 2000], 'padding_top' => [0, 120], 'padding_right' => [0, 120], 'padding_bottom' => [0, 120], 'padding_left' => [0, 120], 'margin_top' => [0, 120], 'margin_right' => [0, 120], 'margin_bottom' => [0, 120], 'margin_left' => [0, 120], 'corner_radius' => [0, 60]] as $layout_key => $range) {
                    if (isset($section[$layout_key])) $item[$layout_key] = min($range[1], max($range[0], (float) $section[$layout_key]));
                }
                $item['alignment'] = in_array(($section['alignment'] ?? 'stretch'), ['stretch','left','center','right'], true) ? ($section['alignment'] ?? 'stretch') : 'stretch';
                unset($item['entrance_animation'], $item['card_animation']);
                $item['background_motion'] = in_array(($section['background_motion'] ?? 'none'), ['none','pulse','gradient','shimmer','glow'], true) ? ($section['background_motion'] ?? 'none') : 'none';
                if (isset($section['card_design'])) $item['card_design'] = in_array($section['card_design'], ['flipkart','classic','elevated','glass','compact','minimal'], true) ? $section['card_design'] : 'flipkart';
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
                // Normalize legacy and current slider payloads to image_url.
                $slides = $section['items'] ?? $section['slides'] ?? $section['images'] ?? [];
                // Some editor/API versions serialize a single slide as an object,
                // while others send a list. Normalize both without losing slides.
                if (is_array($slides)) {
                    $is_list = array_keys($slides) === range(0, count($slides) - 1);
                    if (!$is_list && (isset($slides['image_url']) || isset($slides['image']) || isset($slides['src']) || isset($slides['url']) || isset($slides['media_url']))) {
                        $slides = [$slides];
                    } elseif (!$is_list && isset($slides['items']) && is_array($slides['items'])) {
                        $slides = $slides['items'];
                    } elseif (!$is_list && isset($slides['slides']) && is_array($slides['slides'])) {
                        $slides = $slides['slides'];
                    }
                    $item['items'] = [];
                    foreach (array_slice($slides, 0, 20) as $slide) {
                        if (is_string($slide)) $slide = ['image_url' => $slide];
                        if (!is_array($slide)) continue;
                        $image_value = $slide['image_url'] ?? $slide['image'] ?? $slide['src'] ?? $slide['url'] ?? $slide['media_url'] ?? $slide['attachment_url'] ?? $slide['source_url'] ?? '';
                        if (is_array($image_value)) $image_value = $image_value['url'] ?? $image_value['src'] ?? $image_value['source_url'] ?? '';
                        $image_url = $this->clean_url($image_value);
                        if ($image_url === '') continue;
                        $mode = in_array(($slide['link_type'] ?? 'chrome'), ['in_app','deeplink','chrome'], true) ? ($slide['link_type'] ?? 'chrome') : 'chrome';
                        $link_value = $slide['link'] ?? $slide['button_url'] ?? '';
                        $item['items'][] = [
                            'image_url' => $image_url,
                            'title' => sanitize_text_field($slide['title'] ?? ''),
                            'link_type' => $mode,
                            'link' => $mode === 'chrome' ? $this->clean_url($link_value) : sanitize_text_field($link_value),
                        ];
                    }
                }
                $page['sections'][] = $item;
            }
            $page['title'] = sanitize_text_field($page['title'] ?? ucfirst($page_key));
            $pages[$page_key] = $page;
        }
        $clean['pages'] = array_intersect_key($pages, array_flip($allowed_pages));
        $navigation = is_array($clean['navigation'] ?? null) ? $clean['navigation'] : $base['navigation'];
        $navigation['visible'] = !empty($navigation['visible']);
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
        }, array_filter((array) ($navigation['items'] ?? []), 'is_array')));

        $clean['navigation'] = $navigation;
        $app = is_array($clean['app'] ?? null) ? $clean['app'] : [];
        $clean['app'] = ['name' => sanitize_text_field($app['name'] ?? 'GlowHit'), 'tagline' => sanitize_text_field($app['tagline'] ?? ''), 'icon_url' => $this->clean_url($app['icon_url'] ?? '')];
        return $clean;
    }

    public function api_save_draft($request) {
        $clean = $this->sanitize_config($request->get_json_params());
        if (is_wp_error($clean)) return $clean;
        update_option(self::DRAFT, $clean, false);
        return rest_ensure_response(['success' => true, 'version' => $clean['version']]);
    }

    public function api_publish($request) {
        $clean = $this->sanitize_config($request->get_json_params());
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
        echo '<main class="ark-workspace"><aside class="ark-sidebar"><button type="button" class="ark-panel-toggle" id="ark-toggle-left" aria-label="Collapse components panel">☰ Components</button><div class="ark-sidebar-title">BUILD YOUR APP</div><div class="ark-page-list" id="ark-pages"></div><div class="ark-sidebar-divider"></div><div class="ark-sidebar-title">ADD COMPONENT</div><div class="ark-component-list" id="ark-components"></div><div class="ark-sidebar-divider"></div><button class="ark-quick-link" data-view="app">✎ App name & identity</button><button class="ark-quick-link" data-view="theme">◈ Theme & colours</button><button class="ark-quick-link" data-view="splash">✦ Splash screen</button><button class="ark-quick-link" data-view="celebration">✧ Crackers animation</button><button class="ark-quick-link" data-view="navigation">☷ Navigation</button><button class="ark-quick-link" data-view="source">⌘ App source files</button></aside>';
        echo '<section class="ark-canvas-area"><div class="ark-canvas-toolbar"><div><strong id="ark-canvas-title">Home page</strong><span id="ark-canvas-subtitle">Drag sections or use the arrows to change order</span></div><div class="ark-canvas-tools"><span class="ark-live-pill">● LIVE PREVIEW</span><button class="ark-icon-btn" id="ark-preview-refresh" title="Refresh preview">↻</button></div></div>';
        echo '<div class="ark-canvas-scroll"><div class="ark-phone"><div class="ark-phone-status"><span>9:41</span><span>● ● ▰</span></div><div class="ark-phone-screen" id="ark-preview"></div><div class="ark-phone-nav"><span>⌂</span><span>▦</span><span>♡</span><span>♙</span></div></div></div></section>';
        echo '<aside class="ark-inspector"><div class="ark-inspector-head"><button type="button" class="ark-panel-toggle" id="ark-toggle-right" aria-label="Collapse properties panel">⚙ Properties</button><span>PROPERTIES</span><strong id="ark-inspector-title">Select a section</strong></div><div id="ark-inspector-content" class="ark-inspector-content"><div class="ark-empty-inspector">Choose a component from the canvas to edit its settings.</div></div></aside></main>';
        echo '<footer class="ark-footer"><span>ARK App Designer · Server-driven UI</span><span id="ark-version-label">Draft</span><span>Changes apply to supported app components after publish and sync.</span></footer></div></div>';
    }
}

register_activation_hook(__FILE__, ['ARK_App_Designer', 'activate']);
new ARK_App_Designer();
