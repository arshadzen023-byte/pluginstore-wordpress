<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ARK Mobile Multi-Step WooCommerce Checkout
 * Shortcode: [ark_custom_checkout]
 * Keeps WooCommerce responsible for checkout validation, order creation and payment gateway processing.
 */

/* Checkout appearance settings */
add_action('customize_register', function ($wp_customize) {
    $wp_customize->add_section('ark_checkout_design', [
        'title'       => 'ARK Checkout Design',
        'description' => 'Customize ARK checkout appearance.',
        'priority'    => 35,
    ]);

    $settings = [
        'ark_checkout_title'   => ['label' => 'Checkout Header Title', 'default' => 'Secure Checkout', 'type' => 'text'],
        'ark_checkout_primary' => ['label' => 'Checkout Primary Color', 'default' => '#2874f0', 'type' => 'color'],
        'ark_checkout_accent'  => ['label' => 'Checkout Accent Color', 'default' => '#ffcc00', 'type' => 'color'],
        'ark_checkout_bg'      => ['label' => 'Checkout Background', 'default' => '#f1f4f9', 'type' => 'color'],
    ];

    foreach ($settings as $id => $field) {
        $wp_customize->add_setting($id, [
            'default'           => $field['default'],
            'sanitize_callback' => $field['type'] === 'color' ? 'sanitize_hex_color' : 'sanitize_text_field',
        ]);

        if ($field['type'] === 'color') {
            $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, $id, [
                'label'   => $field['label'],
                'section' => 'ark_checkout_design',
            ]));
        } else {
            $wp_customize->add_control($id, [
                'label'   => $field['label'],
                'section' => 'ark_checkout_design',
                'type'    => 'text',
            ]);
        }
    }
});

/* Product thumbnail in the checkout order review */
add_filter('woocommerce_cart_item_name', function ($name, $cart_item, $cart_item_key) {
    if (!function_exists('is_checkout') || !is_checkout() || is_order_received_page()) {
        return $name;
    }

    if (empty($cart_item['data']) || !($cart_item['data'] instanceof WC_Product)) {
        return $name;
    }

    $image = $cart_item['data']->get_image('woocommerce_thumbnail', [
        'class'   => 'ark-checkout-product-image',
        'loading' => 'lazy',
        'alt'     => esc_attr(wp_strip_all_tags($cart_item['data']->get_name())),
    ]);

    return '<span class="ark-checkout-product">' . $image .
        '<span class="ark-checkout-product-name">' . wp_kses_post($name) . '</span></span>';
}, 20, 3);

/* Quantity controls in checkout. Actual cart updates happen through the AJAX handler below. */
add_filter('woocommerce_checkout_cart_item_quantity', function ($quantity_html, $cart_item, $cart_item_key) {
    if (!function_exists('is_checkout') || !is_checkout() || is_order_received_page()) {
        return $quantity_html;
    }

    $qty = isset($cart_item['quantity']) ? (int) $cart_item['quantity'] : 1;
    $min = 1;
    $max = isset($cart_item['data']) && $cart_item['data'] instanceof WC_Product
        ? (int) $cart_item['data']->get_max_purchase_quantity()
        : 0;

    return '<span class="ark-qty-control" data-cart-key="' . esc_attr($cart_item_key) . '">' .
        '<button type="button" class="ark-qty-minus" aria-label="Decrease quantity"><span class="material-symbols-outlined" aria-hidden="true">remove</span></button>' .
        '<span class="ark-qty-number">' . esc_html($qty) . '</span>' .
        '<button type="button" class="ark-qty-plus" aria-label="Increase quantity"' .
        ($max > 0 && $qty >= $max ? ' disabled' : '') . '><span class="material-symbols-outlined" aria-hidden="true">add</span></button>' .
        '<button type="button" class="ark-remove-item">Remove</button>' .
        '</span>';
}, 20, 3);

/* Secure AJAX cart update/remove */
function ark_checkout_update_cart_item_ajax() {
    if (!function_exists('WC') || !WC()->cart) {
        wp_send_json_error(['message' => 'Cart is unavailable. Please refresh and try again.'], 400);
    }

    check_ajax_referer('ark_checkout_cart', 'nonce');

    $cart_key = isset($_POST['cart_key']) ? wc_clean(wp_unslash($_POST['cart_key'])) : '';
    $action   = isset($_POST['cart_action']) ? sanitize_key(wp_unslash($_POST['cart_action'])) : '';
    $qty      = isset($_POST['quantity']) ? absint($_POST['quantity']) : 1;

    $cart = WC()->cart->get_cart();

    if (!$cart_key || !isset($cart[$cart_key])) {
        wp_send_json_error(['message' => 'This product is no longer in your cart. Refresh the page.'], 404);
    }

    if ($action === 'remove') {
        WC()->cart->remove_cart_item($cart_key);
    } elseif ($action === 'quantity') {
        $product = $cart[$cart_key]['data'];
        if (!$product || !$product->exists()) {
            wp_send_json_error(['message' => 'This product is unavailable.'], 400);
        }

        $qty = max(1, $qty);
        $max = (int) $product->get_max_purchase_quantity();
        if ($max > 0) {
            $qty = min($qty, $max);
        }

        $passed = apply_filters('woocommerce_update_cart_validation', true, $cart_key, $cart[$cart_key], $qty);
        if (!$passed) {
            wp_send_json_error(['message' => 'This quantity is not available.'], 400);
        }

        WC()->cart->set_quantity($cart_key, $qty, true);
    } else {
        wp_send_json_error(['message' => 'Invalid cart action.'], 400);
    }

    WC()->cart->calculate_totals();
    wp_send_json_success([
        'cart_empty' => WC()->cart->is_empty(),
        'cart_hash'  => WC()->cart->get_cart_hash(),
    ]);
}
add_action('wp_ajax_ark_checkout_update_cart', 'ark_checkout_update_cart_item_ajax');
add_action('wp_ajax_nopriv_ark_checkout_update_cart', 'ark_checkout_update_cart_item_ajax');

/* Custom checkout shortcode */
add_shortcode('ark_custom_checkout', function () {
    if (!class_exists('WooCommerce')) {
        return '<p>WooCommerce is required.</p>';
    }

    // WooCommerce renders its own order-received content here. Do not wrap it
    // in the checkout header/progress steps (which caused raw icon names).
    if (function_exists('is_order_received_page') && is_order_received_page()) {
        return do_shortcode('[woocommerce_checkout]');
    }

    if (!WC()->cart || (WC()->cart->is_empty() && !is_wc_endpoint_url('order-pay'))) {
        return '<div class="ark-checkout-empty"><h2>Your cart is empty</h2><a class="ark-empty-shop" href="' .
            esc_url(wc_get_page_permalink('shop')) . '">Continue shopping</a></div>';
    }

    ob_start();
    ?>
    <div class="ark-checkout-app">
        <header class="ark-checkout-mini-header">
            <a class="ark-checkout-back-cart" href="<?php echo esc_url(wc_get_cart_url()); ?>" aria-label="Back to cart"><span class="material-symbols-outlined" aria-hidden="true">arrow_back</span></a>
            <strong><?php echo esc_html(get_theme_mod('ark_checkout_title', 'Secure Checkout')); ?></strong>
            <span class="ark-checkout-secure"><span class="material-symbols-outlined" aria-hidden="true">lock</span><span>Secure</span></span>
        </header>

        <div class="ark-checkout-container">
            <div class="ark-checkout-progress" aria-label="Checkout progress">
                <div class="ark-checkout-progress-step active" data-step="1"><span>1</span><small>Address</small></div>
                <i></i>
                <div class="ark-checkout-progress-step" data-step="2"><span>2</span><small>Confirm details</small></div>
                <i></i>
                <div class="ark-checkout-progress-step" data-step="3"><span>3</span><small>Payment</small></div>
            </div>
            <div class="ark-checkout-heading">
                <h1>Complete your order</h1>
                <p>Confirm your delivery details and order.</p>
            </div>
            <?php echo do_shortcode('[woocommerce_checkout]'); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
});

/* Checkout CSS */
add_action('wp_head', function () {
    if (!function_exists('is_checkout') || !is_checkout() || is_order_received_page()) {
        return;
    }

    $primary = sanitize_hex_color(get_theme_mod('ark_checkout_primary', '#2874f0')) ?: '#2874f0';
    $accent  = sanitize_hex_color(get_theme_mod('ark_checkout_accent', '#ffcc00')) ?: '#ffcc00';
    $bg      = sanitize_hex_color(get_theme_mod('ark_checkout_bg', '#f1f4f9')) ?: '#f1f4f9';
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0" rel="stylesheet">
    <style id="ark-checkout-mobile-app">
        .ark-checkout-app .material-symbols-outlined { font-family:'Material Symbols Outlined'; font-weight:normal; font-style:normal; font-size:22px; line-height:1; letter-spacing:normal; text-transform:none; display:inline-flex; align-items:center; justify-content:center; white-space:nowrap; direction:ltr; -webkit-font-feature-settings:'liga'; -webkit-font-smoothing:antialiased; font-feature-settings:'liga'; }
        body.woocommerce-checkout { background: <?php echo esc_html($bg); ?>; }
        .ark-checkout-app { --ark-primary: <?php echo esc_html($primary); ?>; --ark-accent: <?php echo esc_html($accent); ?>; color:#172337; font-family:inherit; }
        .ark-checkout-app * { box-sizing:border-box; }
        .ark-checkout-mini-header { display:flex; align-items:center; gap:14px; max-width:1240px; margin:18px auto 0; padding:12px 18px; background:#fff; border:1px solid #e5eaf2; border-radius:16px; }
        .ark-checkout-mini-header a { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; text-decoration:none; color:var(--ark-primary); }
        .ark-checkout-mini-header strong { flex:1; font-size:17px; }
        .ark-checkout-secure { display:inline-flex; align-items:center; justify-content:center; gap:4px; color:#16804a; font-size:13px; font-weight:700; }
        /* Hide theme header's extra share/cart actions only on checkout; keep checkout payment controls intact. */
        body.woocommerce-checkout header.site-header .header-cart,
        body.woocommerce-checkout header.site-header .header-share,
        body.woocommerce-checkout header.site-header .share-button,
        body.woocommerce-checkout header.site-header .cart-contents,
        body.woocommerce-checkout header.site-header .elementor-widget-wc-cart,
        body.woocommerce-checkout header.site-header .elementor-widget-share-buttons,
        body.woocommerce-checkout header.site-header .ast-header-woo-cart,
        body.woocommerce-checkout header.site-header .header-account-share,
        body.woocommerce-checkout .site-header .header-cart,
        body.woocommerce-checkout .site-header .header-share,
        body.woocommerce-checkout .site-header .share-button,
        body.woocommerce-checkout .site-header .cart-contents,
        body.woocommerce-checkout .site-header .elementor-widget-wc-cart,
        body.woocommerce-checkout .site-header .elementor-widget-share-buttons,
        body.woocommerce-checkout .site-header .ast-header-woo-cart,
        body.woocommerce-checkout .site-header .header-account-share { display:none !important; }

        /* Hide the store-wide header (logo/search/cart/account/share) on checkout only.
           Keep the compact Secure Checkout bar below. */
       /* Hide complete ARK store header on checkout and order confirmation */
body.woocommerce-checkout .ark-site-header,
body.woocommerce-order-received .ark-site-header,

body.woocommerce-checkout .elementor-location-header,
body.woocommerce-order-received .elementor-location-header,

body.woocommerce-checkout header#masthead,
body.woocommerce-order-received header#masthead,

body.woocommerce-checkout header.site-header,
body.woocommerce-order-received header.site-header,

body.woocommerce-checkout .site-header,
body.woocommerce-order-received .site-header,

body.woocommerce-checkout .wp-site-blocks > header,
body.woocommerce-order-received .wp-site-blocks > header {
    display: none !important;
}

        /* Reliable desktop layout for the Address step and WooCommerce billing fields. */
        .ark-checkout-app,
        .ark-checkout-app .ark-checkout-container,
        .ark-checkout-app form.checkout,
        .ark-checkout-app #customer_details,
        .ark-checkout-app #customer_details .col2-set,
        .ark-checkout-app #customer_details .col-1,
        .ark-checkout-app #customer_details .col-2 {
            min-width:0;
            max-width:100%;
        }
        .ark-checkout-app form.checkout.ark-step-1 #customer_details {
            grid-column:1 / -1 !important;
            grid-row:auto !important;
            width:100% !important;
        }
        .ark-checkout-app #customer_details .col2-set,
        .ark-checkout-app #customer_details .col2-set .col-1,
        .ark-checkout-app #customer_details .col2-set .col-2 {
            float:none !important;
            width:100% !important;
        }
        .ark-checkout-app #customer_details .form-row-first,
        .ark-checkout-app #customer_details .form-row-last,
        .ark-checkout-app #customer_details .form-row-wide {
            float:none !important;
            clear:both !important;
            width:100% !important;
        }
        .ark-checkout-app .ark-qty-control button {
            display:inline-flex;
            align-items:center;
            justify-content:center;
            padding:0;
            line-height:1;
        }
        .ark-checkout-app .ark-qty-control .material-symbols-outlined {
            font-size:19px;
            line-height:1;
        }
        .ark-checkout-app .ark-qty-control button:disabled {
            opacity:.4;
            cursor:not-allowed;
        }
        .ark-checkout-container { width:min(1240px, calc(100% - 32px)); margin:22px auto 48px; }
        .ark-checkout-progress { display:flex; align-items:center; justify-content:center; gap:12px; padding:18px; margin-bottom:18px; background:#fff; border:1px solid #e4eaf3; border-radius:18px; box-shadow:0 5px 20px rgba(25,45,75,.04); }
        .ark-checkout-progress-step { display:flex; align-items:center; gap:9px; color:#8a95a6; font-size:14px; font-weight:650; }
        .ark-checkout-progress-step span { display:grid; place-items:center; width:34px; height:34px; border:2px solid #d6deea; border-radius:50%; background:#fff; font-weight:800; }
        .ark-checkout-progress-step.active { color:var(--ark-primary); }
        .ark-checkout-progress-step.active span,.ark-checkout-progress-step.done span { border-color:var(--ark-primary); background:var(--ark-primary); color:#fff; }
        .ark-checkout-progress-step.done { color:#16804a; }
        .ark-checkout-progress > i { display:block; width:clamp(20px,10vw,110px); height:2px; background:#e1e7f0; }
        .ark-checkout-heading { margin:0 0 18px; }
        .ark-checkout-heading h1 { margin:0 0 5px; font-size:clamp(23px,3vw,30px); line-height:1.25; }
        .ark-checkout-heading p { margin:0; color:#69778b; font-size:14px; }
        .ark-checkout-app form.checkout { display:grid; grid-template-columns:minmax(0,1.15fr) minmax(340px,.85fr); gap:20px; align-items:start; }
        .ark-checkout-app #customer_details,.ark-checkout-app #order_review_heading,.ark-checkout-app #order_review { min-width:0; margin:0; padding:22px; border:1px solid #e3eaf3; border-radius:18px; background:#fff; box-shadow:0 5px 22px rgba(25,45,75,.045); }
        .ark-checkout-app #customer_details { grid-column:1; grid-row:1 / span 2; }
        .ark-checkout-app #order_review_heading { grid-column:2; grid-row:1; padding-bottom:0; border-bottom:0; border-radius:18px 18px 0 0; font-size:20px; }
        .ark-checkout-app #order_review { grid-column:2; grid-row:2; margin-top:-20px; border-top:0; border-radius:0 0 18px 18px; }
        .ark-checkout-app .col2-set,.ark-checkout-app .col2-set .col-1,.ark-checkout-app .col2-set .col-2 { width:100%; float:none; }
        .ark-checkout-app .col2-set .col-2 { margin-top:18px; }
        .ark-checkout-app .form-row { width:100%; float:none; margin-bottom:14px; padding:0; }
        .ark-checkout-app input.input-text,.ark-checkout-app textarea,.ark-checkout-app select { min-height:46px; width:100%; padding:11px 12px; border:1px solid #d9e1ed; border-radius:10px; background:#fff; box-shadow:none; }
        .ark-checkout-app label { margin-bottom:6px; color:#29364a; font-size:13px; font-weight:650; }
        .ark-checkout-app #order_review .shop_table { width:100%; margin:0; border:0; border-collapse:collapse; }
        .ark-checkout-app #order_review .shop_table th,.ark-checkout-app #order_review .shop_table td { padding:13px 5px; border-bottom:1px solid #edf0f5; font-size:13px; vertical-align:middle; }
        .ark-checkout-app #order_review .shop_table th:first-child,.ark-checkout-app #order_review .shop_table td:first-child { text-align:left; }
        .ark-checkout-app #order_review .shop_table th:last-child,.ark-checkout-app #order_review .shop_table td:last-child { text-align:right; }
        .ark-checkout-product { display:flex; align-items:center; gap:11px; min-width:0; }
        .ark-checkout-product-image { width:62px!important; height:62px!important; flex:0 0 62px; margin:0!important; object-fit:contain; border-radius:10px; background:#f6f8fb; }
        .ark-checkout-product-name { min-width:0; overflow-wrap:anywhere; }
        .ark-qty-control { display:flex; flex-wrap:wrap; align-items:center; gap:7px; margin-top:9px; }
        .ark-qty-control button { min-width:29px; height:29px; border:1px solid #d9e1ed; border-radius:8px; background:#fff; color:#26364d; font-weight:800; cursor:pointer; }
        .ark-qty-control .ark-qty-number { min-width:18px; text-align:center; font-weight:750; }
        .ark-qty-control .ark-remove-item { width:auto; height:auto; margin-left:4px; padding:4px 7px; border:0; background:transparent; color:#d43b45; font-size:12px; font-weight:650; }
        .ark-checkout-app #payment { margin-top:18px; padding:15px; border:1px solid #e2e8f0; border-radius:14px; background:#fff; }
        .ark-checkout-app #payment ul.payment_methods { padding:0 0 12px; border-bottom:1px solid #e8edf4; }
        .ark-checkout-app #payment .payment_box { border-radius:10px; }
        .ark-checkout-app #place_order { width:100%; min-height:52px; padding:13px 18px; border:0; border-radius:13px; background:var(--ark-primary); color:#fff; font-size:15px; font-weight:800; }
        .ark-checkout-app .ark-checkout-step-actions { display:flex; gap:10px; margin-top:20px; }
        .ark-checkout-app .ark-checkout-step-actions button { display:inline-flex; align-items:center; justify-content:center; gap:8px; flex:1; min-height:49px; padding:11px 14px; border:0; border-radius:12px; font-weight:800; cursor:pointer; }
        .ark-checkout-app .ark-checkout-next { background:var(--ark-primary); color:#fff; }
        .ark-checkout-app .ark-checkout-back { background:#edf3ff; color:#1d55a6; }
        .ark-checkout-app .ark-checkout-address-card { display:flex; align-items:flex-start; gap:12px; margin-bottom:16px; padding:15px; border:1px solid #dfe8f5; border-radius:14px; background:#f7faff; }
        .ark-checkout-app .ark-address-copy { flex:1; min-width:0; overflow-wrap:anywhere; line-height:1.6; }
        .ark-checkout-app .ark-address-copy strong { display:block; margin-bottom:5px; }
        .ark-checkout-app .ark-address-edit { flex-shrink:0; padding:8px 11px; border:1px solid #cfddf5; border-radius:9px; background:#fff; color:var(--ark-primary); font-weight:750; cursor:pointer; }
        .ark-checkout-app form.checkout.ark-step-1 #order_review_heading,.ark-checkout-app form.checkout.ark-step-1 #order_review { display:none!important; }
        .ark-checkout-app form.checkout.ark-step-1 #customer_details { grid-column:1/-1; grid-row:auto; }
        .ark-checkout-app form.checkout.ark-step-2 #customer_details { display:none!important; }
        .ark-checkout-app form.checkout.ark-step-2 #order_review_heading { display:block!important; grid-column:1/-1; grid-row:auto; }
        .ark-checkout-app form.checkout.ark-step-2 #order_review { display:block!important; grid-column:1/-1; grid-row:auto; margin-top:-20px; }
        .ark-checkout-app form.checkout.ark-step-2 #payment { display:none!important; }
        .ark-checkout-app form.checkout.ark-step-3 #customer_details { display:none!important; }
        .ark-checkout-app form.checkout.ark-step-3 #order_review_heading { display:none!important; }
        .ark-checkout-app form.checkout.ark-step-3 #order_review { display:block!important; grid-column:1/-1; grid-row:auto; margin-top:0; border-top:1px solid #e3eaf3; border-radius:18px; }
        .ark-checkout-app form.checkout.ark-step-3 #order_review .shop_table { display:none!important; }
        .ark-checkout-app form.checkout.ark-step-3 #payment { display:block!important; margin-top:0; }
        .ark-checkout-app form.checkout.ark-step-3 .ark-checkout-summary-actions { display:none!important; }
        .ark-checkout-empty { max-width:600px; margin:35px auto; padding:28px; border:1px solid #e5eaf2; border-radius:18px; background:#fff; text-align:center; }
        .ark-empty-shop { display:inline-block; margin-top:10px; padding:12px 18px; border-radius:10px; background:<?php echo esc_html($primary); ?>; color:#fff; text-decoration:none; }
        .ark-checkout-app .woocommerce-info,.ark-checkout-app .woocommerce-error,.ark-checkout-app .woocommerce-message { border-radius:12px; }
        .ark-checkout-app .woocommerce-info:before { color:var(--ark-primary); }
        .ark-checkout-app .blockOverlay { border-radius:16px; }
        @media(max-width:800px) {
            .ark-checkout-mini-header { width:calc(100% - 20px); margin:10px auto 0; padding:10px 13px; border-radius:13px; }
            .ark-checkout-container { width:calc(100% - 20px); margin:13px auto 28px; }
            .ark-checkout-progress { position:sticky; top:0; z-index:30; gap:5px; justify-content:space-between; padding:11px 8px; margin-bottom:14px; border-radius:14px; box-shadow:0 4px 15px rgba(20,40,70,.07); }
            .ark-checkout-progress-step { flex:1; flex-direction:column; gap:4px; font-size:11px; text-align:center; }
            .ark-checkout-progress-step span { width:29px; height:29px; }
            .ark-checkout-progress > i { flex:1; width:auto; margin-top:-17px; }
            .ark-checkout-heading { margin-bottom:13px; }
            .ark-checkout-heading h1 { font-size:23px; }
            .ark-checkout-app form.checkout { display:block!important; }
            .ark-checkout-app #customer_details,.ark-checkout-app #order_review_heading,.ark-checkout-app #order_review { width:100%; margin:0 0 12px; padding:15px; border:1px solid #e3eaf3; border-radius:16px; }
            .ark-checkout-app #order_review_heading { margin-bottom:-12px; padding-bottom:0; }
            .ark-checkout-app #order_review { margin-top:0; }
            .ark-checkout-app form.checkout.ark-step-2 #order_review { margin-top:0; }
            .ark-checkout-app #order_review .shop_table th,.ark-checkout-app #order_review .shop_table td { padding:10px 3px; font-size:12px; }
            .ark-checkout-product-image { width:48px!important; height:48px!important; flex-basis:48px; }
            .ark-checkout-app .ark-checkout-step-actions { position:sticky; bottom:8px; z-index:60; padding:9px; margin:13px 0 0; border:1px solid #e2e8f0; border-radius:15px; background:rgba(255,255,255,.97); box-shadow:0 5px 22px rgba(20,30,50,.14); }
            .ark-checkout-app .ark-checkout-step-actions button { min-height:47px; padding:10px 8px; font-size:13px; }
            .ark-checkout-app #payment { padding:12px; }
        }
        @media(max-width:380px) {
            .ark-checkout-progress { gap:3px; padding:9px 5px; }
            .ark-checkout-progress-step { font-size:10px; }
            .ark-checkout-progress > i { min-width:9px; }
            .ark-checkout-app #order_review .shop_table th,.ark-checkout-app #order_review .shop_table td { font-size:11px; }
        }
    </style>
    <?php
}, 99);

/* Multi-step checkout navigation and AJAX quantity/remove */
add_action('wp_footer', function () {
    if (!function_exists('is_checkout') || !is_checkout() || is_order_received_page()) {
        return;
    }
    $ajax_url = admin_url('admin-ajax.php');
    $nonce    = wp_create_nonce('ark_checkout_cart');
    ?>
    <script id="ark-checkout-app-script">
    (function () {
        'use strict';

        var ajaxUrl = <?php echo wp_json_encode($ajax_url); ?>;
        var nonce = <?php echo wp_json_encode($nonce); ?>;
        var busy = false;

        function initArkCheckout() {
            var app = document.querySelector('.ark-checkout-app');
            var form = app && app.querySelector('form.checkout');
            if (!app || !form || form.dataset.arkAppInitialized === '1') return;

            var customer = form.querySelector('#customer_details');
            var review = form.querySelector('#order_review');
            var heading = form.querySelector('#order_review_heading');
            var progress = app.querySelector('.ark-checkout-progress');
            if (!customer || !review || !heading) return;

            form.dataset.arkAppInitialized = '1';

            // Confirm details is the default checkout screen. Quantity changes reload the page,
            // so they return here instead of sending the customer back to Address.
            var step = 2;
            var addressCard = document.createElement('section');
            addressCard.className = 'ark-checkout-address-card';
            addressCard.innerHTML = '<div class="ark-address-copy"><strong>Delivering to</strong><div class="ark-address-value">Please enter your address.</div></div><button type="button" class="ark-address-edit">Change</button>';
            form.insertBefore(addressCard, heading);

            var addressActions = document.createElement('div');
            addressActions.className = 'ark-checkout-step-actions ark-checkout-address-actions';
            addressActions.innerHTML = '<button type="button" class="ark-checkout-next"><span class="material-symbols-outlined" aria-hidden="true">check_circle</span><span>Continue to order details</span></button>';
            customer.appendChild(addressActions);

            var summaryActions = document.createElement('div');
            summaryActions.className = 'ark-checkout-step-actions ark-checkout-summary-actions';
            summaryActions.innerHTML = '<button type="button" class="ark-checkout-back"><span class="material-symbols-outlined" aria-hidden="true">arrow_back</span><span>Address</span></button><button type="button" class="ark-checkout-next"><span>Continue to payment</span><span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></button>';
            review.appendChild(summaryActions);

            var payment = form.querySelector('#payment');
            var paymentActions = document.createElement('div');
            paymentActions.className = 'ark-checkout-step-actions ark-checkout-payment-actions';
            paymentActions.innerHTML = '<button type="button" class="ark-checkout-back"><span class="material-symbols-outlined" aria-hidden="true">arrow_back</span><span>Order details</span></button>';
            if (payment) payment.appendChild(paymentActions);

            function getValue(name) {
                var field = form.querySelector('[name="' + name + '"]');
                return field && !field.disabled ? field.value.trim() : '';
            }

            function updateAddress() {
                var name = [getValue('billing_first_name'), getValue('billing_last_name')].filter(Boolean).join(' ');
                var address = [
                    getValue('billing_address_1'), getValue('billing_address_2'),
                    getValue('billing_city'), getValue('billing_state'),
                    getValue('billing_postcode'), getValue('billing_phone')
                ].filter(Boolean).join(', ');
                var target = addressCard.querySelector('.ark-address-value');
                target.textContent = [name, address].filter(Boolean).join(' · ') || 'Please enter your address.';
            }

            function validateAddress() {
                var fields = customer.querySelectorAll('input[required],select[required],textarea[required]');
                for (var i = 0; i < fields.length; i++) {
                    var field = fields[i];
                    if (field.disabled || !field.offsetParent) continue;
                    if (!field.checkValidity()) {
                        field.reportValidity();
                        field.focus();
                        return false;
                    }
                }
                return true;
            }

            function showStep(nextStep, scroll) {
                step = Math.max(1, Math.min(3, nextStep));
                form.classList.remove('ark-step-1', 'ark-step-2', 'ark-step-3');
                form.classList.add('ark-step-' + step);
                updateAddress();

                if (progress) {
                    progress.querySelectorAll('.ark-checkout-progress-step').forEach(function (item) {
                        var number = parseInt(item.dataset.step, 10);
                        item.classList.toggle('active', number === step);
                        item.classList.toggle('done', number < step);
                    });
                }

                addressCard.style.display = step === 1 ? 'none' : 'flex';
                addressActions.style.display = step === 1 ? 'flex' : 'none';
                summaryActions.style.display = step === 2 ? 'flex' : 'none';
                paymentActions.style.display = step === 3 ? 'flex' : 'none';

                if (scroll !== false) {
                    app.scrollIntoView({behavior:'smooth', block:'start'});
                }
            }

            addressActions.querySelector('.ark-checkout-next').addEventListener('click', function () {
                if (validateAddress()) showStep(2);
            });
            summaryActions.querySelector('.ark-checkout-back').addEventListener('click', function () { showStep(1); });
            summaryActions.querySelector('.ark-checkout-next').addEventListener('click', function () { showStep(3); });
            paymentActions.querySelector('.ark-checkout-back').addEventListener('click', function () { showStep(2); });
            addressCard.querySelector('.ark-address-edit').addEventListener('click', function () { showStep(1); });
            form.addEventListener('input', updateAddress);
            form.addEventListener('change', updateAddress);

            // Always open Confirm details first. Address remains available via Change/Address button.
            showStep(2, false);
        }

        function updateCart(control, action, quantity) {
            if (busy) return;
            var key = control.getAttribute('data-cart-key') || (control.closest('.ark-qty-control') && control.closest('.ark-qty-control').getAttribute('data-cart-key'));
            if (!key) return;

            busy = true;
            document.body.classList.add('ark-cart-updating');

            var data = new URLSearchParams();
            data.set('action', 'ark_checkout_update_cart');
            data.set('nonce', nonce);
            data.set('cart_key', key);
            data.set('cart_action', action);
            data.set('quantity', String(quantity || 1));

            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
                body: data.toString()
            }).then(function (response) {
                return response.json();
            }).then(function (result) {
                if (!result || !result.success) {
                    var message = result && result.data && result.data.message ? result.data.message : 'Could not update cart. Please try again.';
                    window.alert(message);
                    return;
                }
                if (result.data.cart_empty) {
                    window.location.reload();
                    return;
                }
                if (window.jQuery) {
                    window.jQuery(document.body).trigger('update_checkout');
                }
                window.location.reload();
            }).catch(function () {
                window.alert('Network error while updating cart. Please try again.');
            }).finally(function () {
                busy = false;
                document.body.classList.remove('ark-cart-updating');
            });
        }

        document.addEventListener('click', function (event) {
            var minus = event.target.closest('.ark-qty-minus');
            var plus = event.target.closest('.ark-qty-plus');
            var remove = event.target.closest('.ark-remove-item');
            if (!minus && !plus && !remove) return;

            event.preventDefault();
            var control = event.target.closest('.ark-qty-control');
            if (!control) return;

            var number = control.querySelector('.ark-qty-number');
            var current = parseInt(number ? number.textContent : '1', 10) || 1;

            if (remove) {
                if (window.confirm('Remove this product from your cart?')) updateCart(control, 'remove', 0);
                return;
            }
            if (minus && current <= 1) return;
            updateCart(control, 'quantity', current + (plus ? 1 : -1));
        });

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initArkCheckout);
        } else {
            initArkCheckout();
        }

        if (window.jQuery) {
            window.jQuery(document.body).on('updated_checkout', function () {
                // WooCommerce may replace order-review markup. Reload only if controls are gone.
                var form = document.querySelector('.ark-checkout-app form.checkout');
                if (form && !form.querySelector('.ark-checkout-summary-actions')) {
                    form.dataset.arkAppInitialized = '';
                    initArkCheckout();
                }
            });
        }
    })();
    </script>
    <style>
        body.ark-cart-updating { cursor: progress; }
        body.ark-cart-updating .ark-checkout-app { opacity:.65; pointer-events:none; }
    </style>
    <?php
}, 99);

/* Preserve existing order-received celebration and styling */
add_action('wp_footer', function () {
    if (
        !function_exists('is_order_received_page') ||
        !is_order_received_page()
    ) {
        return;
    }
    ?>
    <script id="ark-order-celebration">
    (function () {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        function celebrate() {
            if (document.querySelector('.ark-order-confetti')) {
                return;
            }

            var layer = document.createElement('div');
            layer.className = 'ark-order-confetti';
            layer.setAttribute('aria-hidden', 'true');

            layer.style.cssText =
                'position:fixed;inset:0;pointer-events:none;' +
                'overflow:hidden;z-index:99999;';

            var colors = ['#2874f0', '#16a765', '#ffcc00', '#ff6b81'];

            for (var i = 0; i < 24; i++) {
                var piece = document.createElement('i');

                piece.style.cssText =
                    'position:absolute;top:-15px;left:' +
                    Math.round(Math.random() * 100) + '%;' +
                    'width:7px;height:11px;border-radius:2px;' +
                    'background:' + colors[i % colors.length] + ';' +
                    'opacity:.9;animation:arkConfettiFall ' +
                    (1.8 + Math.random() * 1.4) + 's ease-in forwards;' +
                    'animation-delay:' + (Math.random() * .5) + 's;';

                layer.appendChild(piece);
            }

            document.body.appendChild(layer);

            window.setTimeout(function () {
                layer.remove();
            }, 4200);
        }

        var style = document.createElement('style');
        style.textContent =
            '@keyframes arkConfettiFall{' +
            'to{transform:translateY(110vh) rotate(600deg);opacity:0}' +
            '}';

        document.head.appendChild(style);

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', celebrate, {once:true});
        } else {
            celebrate();
        }
    })();
    </script>
    <?php
}, 99);



/* 5. ARK Order Confirmation — lightweight app-style UI */
add_action('wp_head', function () {
    if (
        !function_exists('is_order_received_page') ||
        !is_order_received_page()
    ) {
        return;
    }
    ?>
    <style id="ark-order-success-ui">
        body.woocommerce-order-received {
            background: #f1f4f9 !important;
            color: #172337;
        }

        /* Order confirmation should not show the store-wide GlowHit/Plugin/search/cart header. */
        body.woocommerce-order-received .elementor-location-header,
        body.woocommerce-order-received header#masthead,
        body.woocommerce-order-received header.site-header,
        body.woocommerce-order-received .site-header,
        body.woocommerce-order-received .wp-site-blocks > header {
            display: none !important;
        }

        body.woocommerce-order-received .woocommerce-order {
            width: min(1100px, calc(100% - 32px));
            margin: 30px auto 70px;
        }

        body.woocommerce-order-received .woocommerce-notice--success {
            position: relative;
            display: flex;
            align-items: center;
            gap: 16px;
            margin: 0 0 22px;
            padding: 26px 24px 26px 88px;
            border: 1px solid #cdeedb;
            border-radius: 20px;
            background: linear-gradient(135deg, #ffffff, #f0fff6);
            color: #12683b;
            font-size: clamp(18px, 2.5vw, 25px);
            font-weight: 800;
            line-height: 1.4;
            box-shadow: 0 8px 26px rgba(20, 60, 40, .06);
            animation: arkSuccessIn .45s ease-out both;
        }

        body.woocommerce-order-received .woocommerce-notice--success::before {
            content: "✓";
            position: absolute;
            left: 24px;
            top: 50%;
            display: grid;
            place-items: center;
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: #16a765;
            color: #fff;
            font-size: 28px;
            transform: translateY(-50%) scale(.7);
            animation: arkCheckPop .45s .15s ease-out forwards;
        }

        body.woocommerce-order-received .woocommerce-order-overview {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 12px;
            margin: 0 0 24px;
            padding: 0;
            list-style: none;
        }

        body.woocommerce-order-received .woocommerce-order-overview li {
            float: none !important;
            min-width: 0;
            margin: 0 !important;
            padding: 17px;
            border: 1px solid #e4eaf3;
            border-radius: 15px;
            background: #fff;
            box-shadow: 0 4px 18px rgba(23, 35, 55, .035);
            font-size: 13px;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        body.woocommerce-order-received .woocommerce-order-overview strong {
            display: block;
            margin-top: 5px;
            color: #172337;
            font-size: 15px;
        }

        body.woocommerce-order-received .woocommerce-order-details,
        body.woocommerce-order-received .woocommerce-customer-details {
            margin-top: 20px;
            padding: 22px;
            border: 1px solid #e4eaf3;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 5px 22px rgba(23, 35, 55, .04);
            animation: arkSuccessIn .4s ease-out both;
        }

        body.woocommerce-order-received .woocommerce-order-details h2,
        body.woocommerce-order-received .woocommerce-customer-details h2,
        body.woocommerce-order-received .woocommerce-column__title {
            margin: 0 0 18px;
            color: #172337;
            font-size: 21px;
            font-weight: 800;
        }

        body.woocommerce-order-received .woocommerce-table {
            width: 100%;
            border-collapse: collapse;
        }

        body.woocommerce-order-received .woocommerce-table th,
        body.woocommerce-order-received .woocommerce-table td {
            padding: 14px 10px;
            border-bottom: 1px solid #edf0f5;
            text-align: left;
            overflow-wrap: anywhere;
        }

        body.woocommerce-order-received .woocommerce-table tfoot tr:last-child {
            color: #172337;
            font-weight: 800;
        }

        body.woocommerce-order-received .woocommerce-order-details a {
            color: #1769f5;
            font-weight: 700;
        }

        @keyframes arkSuccessIn {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes arkCheckPop {
            to { transform: translateY(-50%) scale(1); }
        }

        @media (max-width: 760px) {
            body.woocommerce-order-received .woocommerce-order {
                width: calc(100% - 20px);
                margin: 16px auto 40px;
            }

            body.woocommerce-order-received .woocommerce-notice--success {
                padding: 72px 17px 18px;
                border-radius: 17px;
                font-size: 20px;
            }

            body.woocommerce-order-received .woocommerce-notice--success::before {
                top: 15px;
                left: 17px;
                width: 42px;
                height: 42px;
                transform: scale(.7);
                animation-name: arkMobileCheckPop;
            }

            @keyframes arkMobileCheckPop {
                to { transform: scale(1); }
            }

            body.woocommerce-order-received .woocommerce-order-overview {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 9px;
            }

            body.woocommerce-order-received .woocommerce-order-overview li {
                padding: 13px;
                font-size: 12px;
            }

            body.woocommerce-order-received .woocommerce-order-details,
            body.woocommerce-order-received .woocommerce-customer-details {
                padding: 15px;
                border-radius: 15px;
            }

            body.woocommerce-order-received .woocommerce-table th,
            body.woocommerce-order-received .woocommerce-table td {
                padding: 11px 5px;
                font-size: 13px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            body.woocommerce-order-received *,
            body.woocommerce-order-received *::before,
            body.woocommerce-order-received *::after {
                animation: none !important;
                transition: none !important;
                scroll-behavior: auto !important;
            }
        }
    </style>
    <?php
}, 99);



/* ARK: Simplify order confirmation and add My Orders button */
add_action('wp_head', function () {
    if (
        !function_exists('is_order_received_page') ||
        !is_order_received_page()
    ) {
        return;
    }
    ?>
    <style id="ark-order-address-hide">
        /* Confirmation page se dono address sections hide */
        body.woocommerce-order-received .woocommerce-customer-details {
            display: none !important;
        }

        /* My Orders button wrapper */
        .ark-order-my-orders-wrap {
            display: flex;
            justify-content: center;
            width: 100%;
            margin: 28px auto 12px;
        }

        .ark-order-my-orders-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: min(100%, 340px);
            min-height: 54px;
            padding: 14px 24px;
            border: 0;
            border-radius: 14px;
            background: #2874f0;
            color: #fff !important;
            font-size: 16px;
            font-weight: 800;
            text-decoration: none !important;
            box-shadow: 0 6px 16px rgba(40, 116, 240, .22);
            transition: background .2s ease, transform .2s ease;
        }

        .ark-order-my-orders-btn:hover {
            background: #155bd7;
            color: #fff !important;
            transform: translateY(-1px);
        }

        .ark-order-my-orders-btn:focus-visible {
            outline: 3px solid #9fc1ff;
            outline-offset: 3px;
        }

        @media (max-width: 600px) {
            .ark-order-my-orders-wrap {
                margin: 22px auto 10px;
                padding: 0 4px;
                box-sizing: border-box;
            }

            .ark-order-my-orders-btn {
                width: 100%;
                min-height: 52px;
                border-radius: 13px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .ark-order-my-orders-btn {
                transition: none;
            }
        }
        
        

/* Hide only the default page title on order confirmation */
body.woocommerce-order-received h1,
body.woocommerce-order-received .entry-header,
body.woocommerce-order-received .entry-title,
body.woocommerce-order-received .page-title,
body.woocommerce-order-received .wp-block-post-title {
    display: none !important;
}


    
body.woocommerce-order-received .ark-order-my-orders-wrap {
    display: flex;
    justify-content: center;
    width: 100%;
    margin: 24px auto 40px;
    padding: 0 16px;
    box-sizing: border-box;
}

body.woocommerce-order-received .ark-order-my-orders-btn {
    width: min(100%, 360px);
    min-height: 52px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #2874f0;
    color: #fff !important;
    font-size: 15px;
    font-weight: 700;
    text-decoration: none !important;
    box-shadow: 0 5px 16px rgba(40,116,240,.2);
}

    
body.woocommerce-order-received .woocommerce-notice--success {
    font-size: clamp(15px, 1.5vw, 19px) !important;
    line-height: 1.55 !important;
    overflow-wrap: anywhere;
}

body.woocommerce-order-received .ark-order-top-success h2 {
    font-size: clamp(22px, 3vw, 30px) !important;
    line-height: 1.3 !important;
}

body.woocommerce-order-received .ark-order-top-success p {
    font-size: 14px !important;
    line-height: 1.5 !important;
}

        
    </style>
    <?php
}, 100);



/* ARK: Add Lottie container on WooCommerce thank-you page */
add_filter('woocommerce_thankyou_order_received_text', function ($text, $order) {

    return '<div id="ark-order-lottie" aria-hidden="true"></div>' . $text;

}, 10, 2);
/* ARK: Lottie animation on order confirmation page */
add_action('wp_enqueue_scripts', function () {
    if (
        !function_exists('is_order_received_page') ||
        !is_order_received_page()
    ) {
        return;
    }

    // Load Lottie only on the order confirmation page.
    wp_enqueue_script(
        'ark-lottie-player',
        'https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js',
        [],
        '5.12.2',
        true
    );

    $animation_url = get_template_directory_uri()
    . '/assets/animations/order-success.json';

    $animation_url_js = wp_json_encode(esc_url($animation_url));

    $js = "
(function () {

    function startArkOrderAnimation() {

        var box = document.getElementById('ark-order-lottie');

        if (!box || box.dataset.started === '1') {
            return;
        }

        if (typeof window.lottie === 'undefined') {
            console.error('ARK Lottie library not loaded.');
            return;
        }

        box.dataset.started = '1';

        var animation = window.lottie.loadAnimation({
            container: box,
            renderer: 'svg',
            loop: false,
            autoplay: true,
            path: {$animation_url_js}
        });

        animation.addEventListener('DOMLoaded', function () {
            box.style.display = 'flex';
            console.log('ARK Order animation loaded.');
        });

        animation.addEventListener('data_failed', function () {
            box.dataset.started = '0';
            console.error('ARK animation JSON could not be loaded.');
        });
    }

    function init() {
        startArkOrderAnimation();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }

})();
";

    wp_add_inline_script('ark-lottie-player', $js, 'after');
}, 20);


/* Animation appearance: desktop and mobile */
add_action('wp_head', function () {
    if (
        !function_exists('is_order_received_page') ||
        !is_order_received_page()
    ) {
        return;
    }
    ?>
    <style id="ark-order-lottie-style">
        body.woocommerce-order-received #ark-order-lottie {
            width: 190px;
            height: 190px;
            max-width: 100%;
            margin: 0 auto 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        body.woocommerce-order-received #ark-order-lottie svg {
            width: 100% !important;
            height: 100% !important;
        }

        @media (max-width: 600px) {
            body.woocommerce-order-received #ark-order-lottie {
                width: 150px;
                height: 150px;
                margin-bottom: 8px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            body.woocommerce-order-received #ark-order-lottie {
                animation: none !important;
            }
        }
        
        body.woocommerce-order-received #ark-order-lottie {
    width: 190px;
    height: 190px;
    max-width: 100%;
    margin: 0 auto 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

body.woocommerce-order-received #ark-order-lottie svg {
    width: 100% !important;
    height: 100% !important;
}

@media (max-width: 600px) {
    body.woocommerce-order-received #ark-order-lottie {
        width: 150px;
        height: 150px;
    }
}
        
    </style>
    <?php
}, 101);



/* ARK: Order confirmation animation and thank-you message — no header */


/* Keep the thank-you page's My Orders button */
add_action('woocommerce_thankyou', function ($order_id) {
    if (!$order_id || !function_exists('wc_get_order')) return;
    $order = wc_get_order($order_id);
    if (!$order) return;

    $orders_url = function_exists('wc_get_account_endpoint_url')
        ? wc_get_account_endpoint_url('orders')
        : wc_get_page_permalink('myaccount');

    if (!$orders_url) return;

    echo '<div class="ark-order-my-orders-wrap"><a class="ark-order-my-orders-btn" href="' .
        esc_url($orders_url) . '">← MY ORDERS</a></div>';
}, 20);

/* ARK: Hide complete store header on checkout and order-received pages */
add_action('wp_head', function () {

    if (!function_exists('is_checkout') || !is_checkout()) {
        return;
    }

    ?>
    <style id="ark-hide-store-header-checkout">

        /* Hide GlowHit, Plugin cards, search, cart and account header */
        header.ark-site-header,
        .ark-site-header,
        .elementor-location-header,
        header#masthead,
        header.site-header {
            display: none !important;
            visibility: hidden !important;
            height: 0 !important;
            min-height: 0 !important;
            overflow: hidden !important;
            margin: 0 !important;
            padding: 0 !important;
        }

    </style>
    <?php

}, 9999);
