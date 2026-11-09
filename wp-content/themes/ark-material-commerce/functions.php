<?php
if (!defined('ABSPATH')) exit;
define('ARK_MATERIAL_VERSION', '1.0.0');
require_once get_template_directory() . '/inc/ark-otp-login.php';
require_once get_template_directory() . '/inc/ark-account-ui.php';
require_once get_template_directory() . '/inc/ark-product-ui.php';
require_once get_template_directory() . '/inc/ark-checkout-ui.php';
function ark_material_setup() {
    load_theme_textdomain('ark-material-commerce', get_template_directory() . '/languages');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('responsive-embeds');
    add_theme_support('align-wide');
    add_theme_support('wp-block-styles');
    add_theme_support('editor-styles');
    add_theme_support('custom-logo', array('height'=>100,'width'=>300,'flex-height'=>true,'flex-width'=>true));
    add_theme_support('html5', array('search-form','comment-form','comment-list','gallery','caption','style','script'));
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
    register_nav_menus(array(
        'primary' => __('Primary Menu', 'ark-material-commerce'),
        'footer' => __('Footer Menu', 'ark-material-commerce'),
    ));
}
add_action('after_setup_theme', 'ark_material_setup');


function ark_material_assets() {
    $css_file = get_stylesheet_directory() . '/style.css';
    $js_file  = get_template_directory() . '/assets/js/theme.js';

    $css_version = file_exists($css_file)
        ? (string) filemtime($css_file)
        : ARK_MATERIAL_VERSION;

    $js_version = file_exists($js_file)
        ? (string) filemtime($js_file)
        : ARK_MATERIAL_VERSION;

    wp_enqueue_style(
        'ark-material-symbols',
        'https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400,0,0&display=block',
        array(),
        null
    );

    wp_enqueue_style(
        'ark-material-style',
        get_stylesheet_uri(),
        array('ark-material-symbols'),
        $css_version
    );



    // Apply Footer Customizer settings
    $footer_css = '';

    if (!get_theme_mod('ark_footer_enabled', 1)) {
        $footer_css .= '.ark-site-footer { display: none !important; }';
    } else {
        if (!get_theme_mod('ark_footer_brand', 1)) {
            $footer_css .= '.ark-footer-grid > div:first-child { display: none !important; }';
        }

        if (!get_theme_mod('ark_footer_explore', 1)) {
            $footer_css .= '.ark-footer-grid > div:nth-child(2) { display: none !important; }';
        }

        if (!get_theme_mod('ark_footer_customer', 1)) {
            $footer_css .= '.ark-footer-grid > div:nth-child(3) { display: none !important; }';
        }

        if (!get_theme_mod('ark_footer_mobile', 1)) {
            $footer_css .= '@media (max-width: 640px) {
                .ark-site-footer { display: none !important; }
            }';
        }
    }

    wp_add_inline_style('ark-material-style', $footer_css);


    // Apply selected theme colour
    $theme_primary = get_theme_mod('ark_theme_primary', '#2874f0');

    if (!is_string($theme_primary) || !sanitize_hex_color($theme_primary)) {
        $theme_primary = '#2874f0';
    }

    $theme_primary_dark = $theme_primary;

    $theme_css = ':root {
        --ark-primary: ' . $theme_primary . ';
        --ark-primary-dark: ' . $theme_primary_dark . ';
    }';

    wp_add_inline_style('ark-material-style', $theme_css);


    wp_enqueue_script(
        'ark-material-main',
        get_template_directory_uri() . '/assets/js/theme.js',
        array(),
        $js_version,
        true
    );
}
add_action('wp_enqueue_scripts', 'ark_material_assets');



function ark_material_customize($wp_customize) {

    // Header settings
    $wp_customize->add_section('ark_material_header', array(
        'title'    => __('ARK Header Settings', 'ark-material-commerce'),
        'priority' => 30,
    ));

    $wp_customize->add_setting('ark_brand_name', array(
        'default'           => 'ARK Mart',
        'sanitize_callback' => 'sanitize_text_field',
    ));

    $wp_customize->add_control('ark_brand_name', array(
        'label'   => __('Brand Name', 'ark-material-commerce'),
        'section' => 'ark_material_header',
        'type'    => 'text',
    ));

    $wp_customize->add_setting('ark_header_search', array(
        'default'           => 1,
        'sanitize_callback' => 'absint',
    ));

    $wp_customize->add_control('ark_header_search', array(
        'label'   => __('Show Header Search', 'ark-material-commerce'),
        'section' => 'ark_material_header',
        'type'    => 'checkbox',
    ));

    $wp_customize->add_setting('ark_mobile_nav', array(
        'default'           => 1,
        'sanitize_callback' => 'absint',
    ));

    $wp_customize->add_control('ark_mobile_nav', array(
        'label'   => __('Show Mobile Bottom Navigation', 'ark-material-commerce'),
        'section' => 'ark_material_header',
        'type'    => 'checkbox',
    ));
    
        // ==============================
    // Mobile Bottom Navigation Settings
    // ==============================

    $wp_customize->add_section('ark_bottom_nav_settings', array(
        'title'       => __('Bottom Navigation Settings', 'ark-material-commerce'),
        'description' => __('Edit navigation buttons and choose where they appear.', 'ark-material-commerce'),
        'priority'    => 32,
    ));

    // Individual navigation buttons.
    $ark_nav_items = array(
        'home' => array(
            'label' => 'Home',
            'icon'  => 'home',
            'url'   => home_url('/'),
        ),
        'shop' => array(
            'label' => 'Shop',
            'icon'  => 'storefront',
            'url'   => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/'),
        ),
        'search' => array(
            'label' => 'Search',
            'icon'  => 'search',
            'url'   => home_url('/?s='),
        ),
        'account' => array(
            'label' => 'Account',
            'icon'  => 'person',
            'url'   => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url(),
        ),
        'cart' => array(
            'label' => 'Cart',
            'icon'  => 'shopping_bag',
            'url'   => function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/'),
        ),
    );

    foreach ($ark_nav_items as $key => $item) {

        $wp_customize->add_setting('ark_nav_' . $key . '_enabled', array(
            'default'           => 1,
            'sanitize_callback' => 'absint',
        ));

        $wp_customize->add_control('ark_nav_' . $key . '_enabled', array(
            'label'   => sprintf(__('Show %s Button', 'ark-material-commerce'), $item['label']),
            'section' => 'ark_bottom_nav_settings',
            'type'    => 'checkbox',
        ));

        $wp_customize->add_setting('ark_nav_' . $key . '_label', array(
            'default'           => $item['label'],
            'sanitize_callback' => 'sanitize_text_field',
        ));

        $wp_customize->add_control('ark_nav_' . $key . '_label', array(
            'label'   => sprintf(__('%s Button Name', 'ark-material-commerce'), $item['label']),
            'section' => 'ark_bottom_nav_settings',
            'type'    => 'text',
        ));

        $wp_customize->add_setting('ark_nav_' . $key . '_icon', array(
            'default'           => $item['icon'],
            'sanitize_callback' => 'sanitize_text_field',
        ));

        $wp_customize->add_control('ark_nav_' . $key . '_icon', array(
            'label'       => sprintf(__('%s Icon Name (Material Symbols)', 'ark-material-commerce'), $item['label']),
            'description' => __('Example: home, storefront, search, person, shopping_bag.', 'ark-material-commerce'),
            'section'     => 'ark_bottom_nav_settings',
            'type'        => 'text',
        ));

        $wp_customize->add_setting('ark_nav_' . $key . '_url', array(
            'default'           => $item['url'],
            'sanitize_callback' => 'esc_url_raw',
        ));

        $wp_customize->add_control('ark_nav_' . $key . '_url', array(
            'label'   => sprintf(__('%s Button Link', 'ark-material-commerce'), $item['label']),
            'section' => 'ark_bottom_nav_settings',
            'type'    => 'url',
        ));
    }

    // Hide the bottom navigation on selected page types.
    $ark_nav_hide_options = array(
        'home'    => __('Hide on Homepage', 'ark-material-commerce'),
        'shop'    => __('Hide on Shop Page', 'ark-material-commerce'),
        'product' => __('Hide on Product Pages', 'ark-material-commerce'),
        'cart'    => __('Hide on Cart Page', 'ark-material-commerce'),
        'checkout'=> __('Hide on Checkout Page', 'ark-material-commerce'),
        'account' => __('Hide on My Account Page', 'ark-material-commerce'),
        'blog'    => __('Hide on Blog / Posts', 'ark-material-commerce'),
    );

    foreach ($ark_nav_hide_options as $key => $label) {
        $wp_customize->add_setting('ark_nav_hide_' . $key, array(
            'default'           => 0,
            'sanitize_callback' => 'absint',
        ));

        $wp_customize->add_control('ark_nav_hide_' . $key, array(
            'label'   => $label,
            'section' => 'ark_bottom_nav_settings',
            'type'    => 'checkbox',
        ));
    }
    
    // ARK shopping header cards
    $wp_customize->add_setting('ark_show_ark_card', array(
        'default' => 1,
        'sanitize_callback' => 'absint',
    ));

    $wp_customize->add_control('ark_show_ark_card', array(
        'label' => __('Show ARK Mart Card', 'ark-material-commerce'),
        'section' => 'ark_material_header',
        'type' => 'checkbox',
    ));

    $wp_customize->add_setting('ark_show_plugin_card', array(
        'default' => 1,
        'sanitize_callback' => 'absint',
    ));

    $wp_customize->add_control('ark_show_plugin_card', array(
        'label' => __('Show Plugin Card', 'ark-material-commerce'),
        'section' => 'ark_material_header',
        'type' => 'checkbox',
    ));

    $wp_customize->add_setting('ark_plugin_card_label', array(
        'default' => 'Plugin',
        'sanitize_callback' => 'sanitize_text_field',
    ));

    $wp_customize->add_control('ark_plugin_card_label', array(
        'label' => __('Plugin Card Name', 'ark-material-commerce'),
        'section' => 'ark_material_header',
        'type' => 'text',
    ));

    $wp_customize->add_setting('ark_plugin_card_url', array(
        'default' => home_url('/'),
        'sanitize_callback' => 'esc_url_raw',
    ));

    $wp_customize->add_control('ark_plugin_card_url', array(
        'label' => __('Plugin Card Link', 'ark-material-commerce'),
        'section' => 'ark_material_header',
        'type' => 'url',
    ));

    // Festive sparkle animation
    $wp_customize->add_setting('ark_fireworks_enabled', array(
        'default' => 1,
        'sanitize_callback' => 'absint',
    ));

    $wp_customize->add_control('ark_fireworks_enabled', array(
        'label' => __('Enable Festive Sparkle Animation', 'ark-material-commerce'),
        'section' => 'ark_material_header',
        'type' => 'checkbox',
    ));

    // Theme primary colour
    $wp_customize->add_setting('ark_theme_primary', array(
        'default' => '#2874f0',
        'sanitize_callback' => 'sanitize_hex_color',
    ));

    $wp_customize->add_control(new WP_Customize_Color_Control(
        $wp_customize,
        'ark_theme_primary',
        array(
            'label' => __('Website Theme Colour', 'ark-material-commerce'),
            'section' => 'ark_material_header',
        )
    ));


    // Footer settings
    $wp_customize->add_section('ark_material_footer', array(
        'title'       => __('Footer Settings', 'ark-material-commerce'),
        'description' => __('Customize footer visibility and sections.', 'ark-material-commerce'),
        'priority'    => 31,
    ));

    $footer_options = array(
        'ark_footer_enabled' => array(
            'label'   => __('Enable Website Footer', 'ark-material-commerce'),
            'default' => 1,
        ),
        'ark_footer_mobile' => array(
            'label'   => __('Show Footer on Mobile', 'ark-material-commerce'),
            'default' => 1,
        ),
        'ark_footer_brand' => array(
            'label'   => __('Show Footer Logo & Description', 'ark-material-commerce'),
            'default' => 1,
        ),
        'ark_footer_explore' => array(
            'label'   => __('Show Explore Links', 'ark-material-commerce'),
            'default' => 1,
        ),
        'ark_footer_customer' => array(
            'label'   => __('Show Customer Care Links', 'ark-material-commerce'),
            'default' => 1,
        ),
    );

    foreach ($footer_options as $setting_id => $option) {
        $wp_customize->add_setting($setting_id, array(
            'default'           => $option['default'],
            'sanitize_callback' => 'absint',
        ));

        $wp_customize->add_control($setting_id, array(
            'label'   => $option['label'],
            'section' => 'ark_material_footer',
            'type'    => 'checkbox',
        ));
    }

    $wp_customize->add_setting('ark_footer_note', array(
        'default'           => '© {year} ARK Mart. All rights reserved.',
        'sanitize_callback' => 'sanitize_text_field',
    ));

    $wp_customize->add_control('ark_footer_note', array(
        'label'   => __('Footer Copyright Text', 'ark-material-commerce'),
        'section' => 'ark_material_footer',
        'type'    => 'text',
    ));
    
   
    // OTP Login Settings
    $wp_customize->add_section('ark_otp_settings', array(
        'title'       => __('OTP Login Settings', 'ark-material-commerce'),
        'description' => __('Configure your SMS provider for mobile login.', 'ark-material-commerce'),
        'priority'    => 32,
    ));

$otp_fields = array(
    'ark_otp_enabled' => array(
        'label'   => __('Enable Mobile OTP Login', 'ark-material'),
        'type'    => 'checkbox',
        'default' => 0,
    ),
    'ark_otp_api_url' => array(
        'label'   => __('Request URL (IndiaHost OTP API)', 'ark-material'),
        'type'    => 'url',
        'default' => 'https://otp.indiahost.org/send_otp.php',
    ),
    'ark_otp_api_key' => array(
        'label'   => __('IndiaHost API Key', 'ark-material'),
        'type'    => 'password',
        'default' => '',
    ),
    'ark_otp_sender_id' => array(
        'label'   => __('IndiaHost Account Email (User)', 'ark-material'),
        'type'    => 'text',
        'default' => '',
    ),
);


    foreach ($otp_fields as $id => $field) {
        $wp_customize->add_setting($id, array(
            'default'           => $field['default'],
            'sanitize_callback' => (
                $field['type'] === 'url'
                    ? 'esc_url_raw'
                    : ($field['type'] === 'checkbox'
                        ? 'absint'
                        : 'sanitize_text_field')
            ),
        ));

        $wp_customize->add_control($id, array(
            'label'   => $field['label'],
            'section' => 'ark_otp_settings',
            'type'    => $field['type'],
        ));
    }
 
    
}
add_action('customize_register', 'ark_material_customize');


function ark_material_elementor_locations($elementor_theme_manager) {
    if (method_exists($elementor_theme_manager, 'register_all_core_location')) {
        $elementor_theme_manager->register_all_core_location();
    }
}
add_action('elementor/theme/register_locations', 'ark_material_elementor_locations');

function ark_material_cart_count() {
    if (function_exists('WC') && WC()->cart) return WC()->cart->get_cart_contents_count();
    return 0;
}
function ark_material_icon($name) {
    return '<span class="material-symbols-rounded" aria-hidden="true">' . esc_html($name) . '</span>';
}
function ark_material_header() {
    if (function_exists('elementor_theme_do_location') && elementor_theme_do_location('header')) return;
    get_template_part('template-parts/header');
}
function ark_material_footer() {
    if (function_exists('elementor_theme_do_location') && elementor_theme_do_location('footer')) return;
    get_template_part('template-parts/footer');
}
function ark_material_body_classes($classes) {
    if (is_front_page()) $classes[] = 'ark-home';
    return $classes;
}
add_filter('body_class', 'ark_material_body_classes');

function ark_material_elementor_support() {
    add_post_type_support('page', 'elementor');
}
add_action('init', 'ark_material_elementor_support');



/**
 * ARK Visual Customizer: full-site colors and celebration effects.
 * Add this block at the END of functions.php.
 */
function ark_visual_customizer($wp_customize) {
    $wp_customize->add_section('ark_visual_design', array(
        'title'       => __('ARK Visual Design', 'ark-material-commerce'),
        'description' => __('Customize desktop/mobile colors, page backgrounds and celebration animations.', 'ark-material-commerce'),
        'priority'    => 28,
    ));

    $colors = array(
        'ark_color_primary' => array(
            'label'   => __('Primary / Button Color', 'ark-material-commerce'),
            'default' => '#2874f0',
        ),
        'ark_color_accent' => array(
            'label'   => __('Accent Color', 'ark-material-commerce'),
            'default' => '#ffb000',
        ),
        'ark_color_page_bg' => array(
            'label'   => __('Page Background Color', 'ark-material-commerce'),
            'default' => '#f1f3f6',
        ),
        'ark_color_surface' => array(
            'label'   => __('Cards / Content Background', 'ark-material-commerce'),
            'default' => '#ffffff',
        ),
        'ark_color_header_bg' => array(
            'label'   => __('Header Background Color', 'ark-material-commerce'),
            'default' => '#ffffff',
        ),
        'ark_color_header_text' => array(
            'label'   => __('Header Text / Icon Color', 'ark-material-commerce'),
            'default' => '#172337',
        ),
        'ark_color_body_text' => array(
            'label'   => __('Website Text Color', 'ark-material-commerce'),
            'default' => '#202124',
        ),
        'ark_color_footer_bg' => array(
            'label'   => __('Footer Background Color', 'ark-material-commerce'),
            'default' => '#172337',
        ),
    );

    foreach ($colors as $id => $setting) {
        $wp_customize->add_setting($id, array(
            'default'           => $setting['default'],
            'sanitize_callback' => 'sanitize_hex_color',
            'transport'         => 'refresh',
        ));

        $wp_customize->add_control(
            new WP_Customize_Color_Control(
                $wp_customize,
                $id,
                array(
                    'label'   => $setting['label'],
                    'section' => 'ark_visual_design',
                )
            )
        );
    }

    $wp_customize->add_setting('ark_visual_content_width', array(
        'default'           => 1240,
        'sanitize_callback' => function($value) {
            return max(960, min(1600, absint($value)));
        },
    ));

    $wp_customize->add_control('ark_visual_content_width', array(
        'label'       => __('Desktop Content Width (px)', 'ark-material-commerce'),
        'description' => __('Controls the maximum width of the main layout.', 'ark-material-commerce'),
        'section'     => 'ark_visual_design',
        'type'        => 'number',
        'input_attrs' => array(
            'min'  => 960,
            'max'  => 1600,
            'step' => 20,
        ),
    ));

    $wp_customize->add_setting('ark_visual_crackers', array(
        'default'           => 'mixed',
        'sanitize_callback' => function($value) {
            $allowed = array('off', 'fireworks', 'confetti', 'sparkles', 'mixed');
            return in_array($value, $allowed, true) ? $value : 'mixed';
        },
    ));

    $wp_customize->add_control('ark_visual_crackers', array(
        'label'   => __('Celebration Animation', 'ark-material-commerce'),
        'section' => 'ark_visual_design',
        'type'    => 'select',
        'choices' => array(
            'off'       => __('Off', 'ark-material-commerce'),
            'fireworks' => __('Fireworks / Crackers', 'ark-material-commerce'),
            'confetti'  => __('Colorful Confetti', 'ark-material-commerce'),
            'sparkles'  => __('Golden Sparkles', 'ark-material-commerce'),
            'mixed'     => __('Mixed Celebration Effects', 'ark-material-commerce'),
        ),
    ));

    $wp_customize->add_setting('ark_visual_cracker_density', array(
        'default'           => 35,
        'sanitize_callback' => function($value) {
            return max(10, min(80, absint($value)));
        },
    ));

    $wp_customize->add_control('ark_visual_cracker_density', array(
        'label'       => __('Animation Density', 'ark-material-commerce'),
        'description' => __('10 = subtle, 80 = more effects. Keep it moderate for mobile performance.', 'ark-material-commerce'),
        'section'     => 'ark_visual_design',
        'type'        => 'range',
        'input_attrs' => array(
            'min'  => 10,
            'max'  => 80,
            'step' => 5,
        ),
    ));

    $wp_customize->add_setting('ark_visual_cracker_speed', array(
        'default'           => 'normal',
        'sanitize_callback' => function($value) {
            $allowed = array('slow', 'normal', 'fast');
            return in_array($value, $allowed, true) ? $value : 'normal';
        },
    ));

    $wp_customize->add_control('ark_visual_cracker_speed', array(
        'label'   => __('Animation Speed', 'ark-material-commerce'),
        'section' => 'ark_visual_design',
        'type'    => 'select',
        'choices' => array(
            'slow'   => __('Slow', 'ark-material-commerce'),
            'normal' => __('Normal', 'ark-material-commerce'),
            'fast'   => __('Fast', 'ark-material-commerce'),
        ),
    ));
}
add_action('customize_register', 'ark_visual_customizer', 20);


/**
 * Apply selected colors globally using CSS variables.
 */
function ark_visual_customizer_css() {
    $primary    = get_theme_mod('ark_color_primary', '#2874f0');
    $accent     = get_theme_mod('ark_color_accent', '#ffb000');
    $page_bg    = get_theme_mod('ark_color_page_bg', '#f1f3f6');
    $surface    = get_theme_mod('ark_color_surface', '#ffffff');
    $header_bg  = get_theme_mod('ark_color_header_bg', '#ffffff');
    $header_txt = get_theme_mod('ark_color_header_text', '#172337');
    $body_txt   = get_theme_mod('ark_color_body_text', '#202124');
    $footer_bg  = get_theme_mod('ark_color_footer_bg', '#172337');
    $width      = absint(get_theme_mod('ark_visual_content_width', 1240));

    $css = '
    :root {
        --ark-primary: ' . sanitize_hex_color($primary) . ';
        --ark-primary-dark: ' . sanitize_hex_color($primary) . ';
        --ark-accent: ' . sanitize_hex_color($accent) . ';
        --ark-bg: ' . sanitize_hex_color($page_bg) . ';
        --ark-surface: ' . sanitize_hex_color($surface) . ';
        --ark-header-bg: ' . sanitize_hex_color($header_bg) . ';
        --ark-header-text: ' . sanitize_hex_color($header_txt) . ';
        --ark-text: ' . sanitize_hex_color($body_txt) . ';
        --ark-footer-bg: ' . sanitize_hex_color($footer_bg) . ';
        --ark-content: ' . max(960, min(1600, $width)) . 'px;
    }

    body {
        background: var(--ark-bg);
        color: var(--ark-text);
    }

    .ark-site-header {
        background: var(--ark-header-bg);
        color: var(--ark-header-text);
        border-bottom: 1px solid color-mix(in srgb, var(--ark-header-text) 12%, transparent);
    }

    .ark-site-header a,
    .ark-site-header .ark-icon-link {
        color: var(--ark-header-text);
    }

    .ark-brand-mark,
    button,
    .button,
    .wp-element-button,
    .woocommerce a.button,
    .woocommerce button.button,
    .woocommerce input.button {
        background: var(--ark-primary);
    }

    .ark-search input {
        background: var(--ark-surface);
    }

    .ark-card,
    .widget-area,
    .woocommerce ul.products li.product,
    .woocommerce-message,
    .woocommerce-info,
    .woocommerce-error,
    .ark-no-results {
        background-color: var(--ark-surface);
    }

    .ark-site-footer {
        background: var(--ark-footer-bg);
    }

    .ark-site-footer a {
        color: var(--ark-accent);
    }

    .woocommerce ul.products li.product .price,
    .woocommerce div.product p.price,
    .woocommerce div.product span.price {
        color: var(--ark-primary);
    }

    .ark-site-header,
    .ark-main,
    .ark-site-footer {
        position: relative;
        z-index: 1;
    }

    @media (min-width: 901px) {
        .ark-main {
            padding-top: 34px;
            padding-bottom: 72px;
        }

        body.ark-home .ark-main > .ark-container {
            width: min(var(--ark-content), calc(100% - 64px));
        }

        .woocommerce ul.products li.product {
            padding: 18px !important;
            border-radius: 20px;
        }

        .ark-site-header .ark-header-inner {
            min-height: 82px;
        }
    }
    ';

    echo '<style id="ark-visual-customizer-css">' . wp_strip_all_tags($css) . '</style>';
}
add_action('wp_head', 'ark_visual_customizer_css', 30);


/**
 * Add an accessible, non-interactive animation canvas.
 */

/**
 * Show celebration animation ONLY on the front page.
 */
function ark_visual_crackers_canvas() {

    // Do not display crackers on Shop, Product, Cart, Account or other pages.
    if (is_admin() || !is_front_page()) {
        return;
    }

    $effect = get_theme_mod('ark_visual_crackers', 'mixed');

    if ($effect === 'off') {
        return;
    }

    $density = absint(get_theme_mod('ark_visual_cracker_density', 35));
    $speed   = get_theme_mod('ark_visual_cracker_speed', 'normal');

    ?>
    <canvas
        id="ark-celebration-canvas"
        aria-hidden="true"
        data-effect="<?php echo esc_attr($effect); ?>"
        data-density="<?php echo esc_attr(max(10, min(80, $density))); ?>"
        data-speed="<?php echo esc_attr($speed); ?>"
    ></canvas>
    <?php
}
add_action('wp_footer', 'ark_visual_crackers_canvas', 5);




/* ARK Delivery Address Selector */

function ark_delivery_address_box() {
    if (!function_exists('WC') || !is_front_page()) {
        return;
    }

    $user_id = get_current_user_id();
    $address = '';

    if ($user_id) {
        // Prefer shipping address; fall back to billing.
        $type = get_user_meta($user_id, 'shipping_address_1', true)
            ? 'shipping'
            : 'billing';

        $fields = array(
            $type . '_address_1',
            $type . '_address_2',
            $type . '_city',
            $type . '_state',
            $type . '_postcode',
            $type . '_country',
        );

        $parts = array();

        foreach ($fields as $field) {
            $value = get_user_meta($user_id, $field, true);

            if ($field === $type . '_state' && $value && function_exists('WC')) {
                $states = WC()->countries->get_states(
                    get_user_meta($user_id, $type . '_country', true)
                );

                if (is_array($states) && isset($states[$value])) {
                    $value = $states[$value];
                }
            }

            if ($field === $type . '_country' && $value && function_exists('WC')) {
                $countries = WC()->countries->get_countries();

                if (isset($countries[$value])) {
                    $value = $countries[$value];
                }
            }

            if ($value !== '') {
                $parts[] = $value;
            }
        }

        $address = implode(', ', $parts);
    }

    if ($user_id) {
        $address_url = wc_get_account_endpoint_url('edit-address');
    } else {
        $address_url = wc_get_page_permalink('myaccount');
    }
    ?>
    <section class="ark-delivery-box">
        <div class="ark-delivery-inner">
            <span class="material-symbols-rounded ark-location-icon">
                location_on
            </span>

            <div class="ark-delivery-info">
                <strong>Deliver to</strong>

                <div>
                    <?php
                    if ($address !== '') {
                        echo esc_html($address);
                    } elseif ($user_id) {
                        esc_html_e(
                            'Add your delivery address',
                            'ark-material-commerce'
                        );
                    } else {
                        esc_html_e(
                            'Login to select your address',
                            'ark-material-commerce'
                        );
                    }
                    ?>
                </div>
            </div>

            <a class="ark-address-arrow"
               href="<?php echo esc_url($address_url); ?>"
               aria-label="<?php esc_attr_e(
                   'Open address page',
                   'ark-material-commerce'
               ); ?>">
                <span class="material-symbols-rounded">chevron_right</span>
            </a>
        </div>
    </section>
    <?php
}
add_action('wp', function () {
    if (
        function_exists('is_account_page') &&
        is_account_page()
    ) {
        remove_action(
            'woocommerce_account_dashboard',
            'woocommerce_account_dashboard',
            10
        );
    }
});