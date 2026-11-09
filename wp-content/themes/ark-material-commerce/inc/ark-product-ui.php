
<?php
/**
 * ARK Material Commerce - Product page UI.
 * Compatible with WooCommerce and Elementor Pro Single Product templates.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Product UI Customizer settings.
 */
function ark_product_ui_customizer($wp_customize) {
    $wp_customize->add_section('ark_product_ui', array(
        'title'       => __('ARK Product Page UI', 'ark-material-commerce'),
        'description' => __('Settings for the mobile-app-style product page.', 'ark-material-commerce'),
        'priority'    => 33,
    ));

    $wp_customize->add_setting('ark_product_ui_enabled', array(
        'default'           => 1,
        'sanitize_callback' => 'absint',
    ));

    $wp_customize->add_control('ark_product_ui_enabled', array(
        'label'   => __('Enable Product Page UI', 'ark-material-commerce'),
        'section' => 'ark_product_ui',
        'type'    => 'checkbox',
    ));

    $wp_customize->add_setting('ark_product_wishlist_url', array(
        'default'           => home_url('/wishlist/'),
        'sanitize_callback' => 'esc_url_raw',
    ));

    $wp_customize->add_control('ark_product_wishlist_url', array(
        'label'       => __('Wishlist Page URL', 'ark-material-commerce'),
        'description' => __('Enter the URL of your active wishlist page/plugin.', 'ark-material-commerce'),
        'section'     => 'ark_product_ui',
        'type'        => 'url',
    ));
    
    
    // Product page visibility controls.
    $ark_product_options = array(
        'ark_product_show_share' => array(
            'label'   => __('Show Share Button on Product Image', 'ark-material-commerce'),
            'default' => 1,
        ),
        'ark_product_show_wishlist' => array(
            'label'   => __('Show Wishlist Button on Product Image', 'ark-material-commerce'),
            'default' => 1,
        ),
        'ark_product_show_category' => array(
            'label'   => __('Show Product Category', 'ark-material-commerce'),
            'default' => 1,
        ),
        'ark_product_show_description' => array(
            'label'   => __('Show Product Description Tab', 'ark-material-commerce'),
            'default' => 1,
        ),
    );

    foreach ($ark_product_options as $setting_id => $option) {
        $wp_customize->add_setting($setting_id, array(
            'default'           => $option['default'],
            'sanitize_callback' => 'absint',
        ));

        $wp_customize->add_control($setting_id, array(
            'label'   => $option['label'],
            'section' => 'ark_product_ui',
            'type'    => 'checkbox',
        ));
    }

    // Sticky button labels.
    $ark_product_texts = array(
        'ark_product_cart_label' => array(
            'label'   => __('Sticky Add to Cart Text', 'ark-material-commerce'),
            'default' => 'Add to Cart',
        ),
        'ark_product_buy_label' => array(
            'label'   => __('Sticky Buy Now Text', 'ark-material-commerce'),
            'default' => 'Buy Now',
        ),
    );

    foreach ($ark_product_texts as $setting_id => $option) {
        $wp_customize->add_setting($setting_id, array(
            'default'           => $option['default'],
            'sanitize_callback' => 'sanitize_text_field',
        ));

        $wp_customize->add_control($setting_id, array(
            'label'   => $option['label'],
            'section' => 'ark_product_ui',
            'type'    => 'text',
        ));
    }

}
add_action('customize_register', 'ark_product_ui_customizer');


/**
 * Return whether the custom product UI should run.
 */
function ark_product_ui_is_enabled() {
    return function_exists('is_product')
        && is_product()
        && (bool) get_theme_mod('ark_product_ui_enabled', 1);
}


/**
 * Minimal search + back + cart header for single product pages.
 * The standard theme header is hidden with product-page CSS.
 */
function ark_product_ui_topbar() {
    if (!ark_product_ui_is_enabled()) {
        return;
    }

    $back_url = wp_get_referer();

    if (!$back_url || strpos($back_url, home_url()) !== 0) {
        $back_url = function_exists('wc_get_page_permalink')
            ? wc_get_page_permalink('shop')
            : home_url('/');
    }

    $cart_url = function_exists('wc_get_cart_url')
        ? wc_get_cart_url()
        : home_url('/');

    ?>
    <div class="ark-product-topbar">
        <a class="ark-product-back"
           href="<?php echo esc_url($back_url); ?>"
           aria-label="<?php esc_attr_e('Go back', 'ark-material-commerce'); ?>">
            <span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>
        </a>

        <form class="ark-product-search" role="search"
              method="get" action="<?php echo esc_url(home_url('/')); ?>">
            <span class="material-symbols-rounded" aria-hidden="true">search</span>
            <input type="search"
                   name="s"
                   value="<?php echo esc_attr(get_search_query()); ?>"
                   placeholder="<?php esc_attr_e('Search for products', 'ark-material-commerce'); ?>"
                   aria-label="<?php esc_attr_e('Search products', 'ark-material-commerce'); ?>">
            <?php if (class_exists('WooCommerce')) : ?>
                <input type="hidden" name="post_type" value="product">
            <?php endif; ?>
        </form>

        <a class="ark-product-cart"
           href="<?php echo esc_url($cart_url); ?>"
           aria-label="<?php esc_attr_e('Open cart', 'ark-material-commerce'); ?>">
            <span class="material-symbols-rounded" aria-hidden="true">shopping_cart</span>
            <?php
            if (function_exists('ark_material_cart_count') && ark_material_cart_count() > 0) :
            ?>
                <span class="ark-product-cart-count">
                    <?php echo esc_html(ark_material_cart_count()); ?>
                </span>
            <?php endif; ?>
        </a>
    </div>
    <?php
}
add_action('wp_body_open', 'ark_product_ui_topbar', 5);


/**
 * Add a Buy Now flag inside the normal WooCommerce product form.
 * This keeps WooCommerce's regular quantity/variation validation.
 */
function ark_product_ui_buy_now_field() {
    if (!ark_product_ui_is_enabled()) {
        return;
    }

    echo '<input type="hidden" name="ark_buy_now" value="0" class="ark-buy-now-flag">';
}

add_action('woocommerce_before_add_to_cart_button', 'ark_product_ui_buy_now_field', 5);

/**
 * Show a clear Saver Deal label immediately above the product price.
 */
function ark_product_ui_saver_deal_badge() {
    if (!ark_product_ui_is_enabled()) {
        return;
    }

    global $product;
    if (!$product instanceof WC_Product || !$product->is_on_sale()) {
        return;
    }

    echo '<div class="ark-product-saver-deal">' . esc_html__('Saver Deal', 'ark-material-commerce') . '</div>';
}



/**
 * Redirect to Checkout only when Buy Now was clicked.
 */

/**
 * Redirect to Checkout only when Buy Now was used.
 */
function ark_product_ui_buy_now_redirect($url) {
    if (
        isset($_REQUEST['ark_buy_now'])
        && (string) wp_unslash($_REQUEST['ark_buy_now']) === '1'
        && function_exists('wc_get_checkout_url')
    ) {
        return wc_get_checkout_url();
    }

    return $url;
}
add_filter('woocommerce_add_to_cart_redirect', 'ark_product_ui_buy_now_redirect', 999);



/**
 * Fixed purchase bar. The buttons operate the real WooCommerce form.
 */

function ark_product_ui_sticky_bar() {
    if (!ark_product_ui_is_enabled()) {
        return;
    }
    ?>
    <div class="ark-product-sticky-bar"
         aria-label="<?php esc_attr_e('Product purchase actions', 'ark-material-commerce'); ?>">

        <button type="button" class="ark-product-sticky-cart">
            <?php echo esc_html(get_theme_mod('ark_product_cart_label', 'Add to Cart')); ?>
        </button>

        <button type="button" class="ark-product-sticky-buy">
            <span class="ark-product-sticky-buy-label"><?php echo esc_html(get_theme_mod('ark_product_buy_label', 'Buy Now')); ?></span>
            <span class="ark-product-sticky-buy-price">
                <?php
                global $product;
                if ($product instanceof WC_Product) {
                    $sticky_price = $product->get_price();
                    if ($product->is_type('variable')) {
                        $sticky_price = $product->get_variation_price('min', true);
                    }
                    if ($sticky_price !== '' && is_numeric($sticky_price)) {
                        echo wp_kses_post(wc_price((float) $sticky_price));
                    }
                }
                ?>
            </span>
        </button>

    </div>
    <?php
}

add_action('wp_footer', 'ark_product_ui_sticky_bar', 30);

/**
 * Product page Customizer visibility settings.
 */
add_filter('body_class', function ($classes) {

    if (function_exists('is_product') && is_product()) {

        if (!get_theme_mod('ark_product_show_category', 1)) {
            $classes[] = 'ark-hide-product-category';
        }

        if (!get_theme_mod('ark_product_show_description', 1)) {
            $classes[] = 'ark-hide-product-description';
        }

        if (!get_theme_mod('ark_product_show_share', 1)) {
            $classes[] = 'ark-hide-product-share';
        }

        if (!get_theme_mod('ark_product_show_wishlist', 1)) {
            $classes[] = 'ark-hide-product-wishlist';
        }
    }

    return $classes;
});


/**
 * ARK Product UI: replace the classic WooCommerce tabs with a clean
 * description section. Elementor's own Product Data Tabs widget is
 * managed separately in the Elementor template.
 */
function ark_product_ui_replace_default_tabs() {
    if (!function_exists('is_product') || !is_product()) {
        return;
    }

// Hide default SKU and Category from the product summary.
remove_action(
    'woocommerce_single_product_summary',
    'woocommerce_template_single_meta',
    40
);


    remove_action(
        'woocommerce_after_single_product_summary',
        'woocommerce_output_product_data_tabs',
        10
    );

    add_action(
        'woocommerce_after_single_product_summary',
        'ark_product_ui_description_section',
        10
    );
}

add_action('wp', 'ark_product_ui_replace_default_tabs', 99);

/**
 * Customer delivery address card above the product description.
 * Uses the saved WooCommerce shipping address, then billing address.
 */
function ark_product_ui_customer_address_card() {
    if (!ark_product_ui_is_enabled() || !function_exists('wc_get_page_permalink')) {
        return;
    }

    $user_id = get_current_user_id();
    $address = '';
    $address_url = wc_get_page_permalink('myaccount');

    if ($user_id) {
        $shipping_fields = array(
            'shipping_address_1', 'shipping_address_2', 'shipping_city',
            'shipping_state', 'shipping_postcode', 'shipping_country'
        );
        $billing_fields = array(
            'billing_address_1', 'billing_address_2', 'billing_city',
            'billing_state', 'billing_postcode', 'billing_country'
        );

        $shipping_parts = array();
        foreach ($shipping_fields as $field) {
            $value = trim((string) get_user_meta($user_id, $field, true));
            if ($value !== '') {
                $shipping_parts[] = $value;
            }
        }

        $fields = !empty($shipping_parts) ? $shipping_fields : $billing_fields;
        $parts = !empty($shipping_parts) ? $shipping_parts : array();

        if (empty($parts)) {
            foreach ($fields as $field) {
                $value = trim((string) get_user_meta($user_id, $field, true));
                if ($value !== '') {
                    $parts[] = $value;
                }
            }
        }

        $address = implode(', ', $parts);
        $address_url = function_exists('wc_get_account_endpoint_url')
            ? wc_get_account_endpoint_url('edit-address')
            : $address_url;
    }
    ?>
    <section class="ark-product-address-card" aria-label="<?php esc_attr_e('Delivery address', 'ark-material-commerce'); ?>">
        <div class="ark-product-address-icon">
            <span class="material-symbols-rounded" aria-hidden="true">location_on</span>
        </div>
        <div class="ark-product-address-copy">
            <strong><?php esc_html_e('Deliver to', 'ark-material-commerce'); ?></strong>
            <span>
                <?php
                if ($address !== '') {
                    echo esc_html($address);
                } elseif ($user_id) {
                    esc_html_e('Add your delivery address', 'ark-material-commerce');
                } else {
                    esc_html_e('Login to add your delivery address', 'ark-material-commerce');
                }
                ?>
            </span>
        </div>
        <a class="ark-product-address-action" href="<?php echo esc_url($address_url); ?>">
            <?php echo esc_html($user_id ? __('Change', 'ark-material-commerce') : __('Add address', 'ark-material-commerce')); ?>
            <span class="material-symbols-rounded" aria-hidden="true">chevron_right</span>
        </a>
        
        
<?php
global $product;

if ($product instanceof WC_Product) :
    $ark_sku = $product->get_sku();
    $ark_categories = wc_get_product_category_list(
        $product->get_id(),
        ', '
    );
?>
    <div class="ark-delivery-product-meta">

        <?php if ($ark_sku !== '') : ?>
            <div class="ark-delivery-meta-item">
                <span class="material-symbols-rounded" aria-hidden="true">
                    inventory_2
                </span>
                <div>
                    <small>Product SKU</small>
                    <strong><?php echo esc_html($ark_sku); ?></strong>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($ark_categories)) : ?>
            <div class="ark-delivery-meta-item">
                <span class="material-symbols-rounded" aria-hidden="true">
                    category
                </span>
                <div>
                    <small>Category</small>
                    <strong><?php echo wp_kses_post($ark_categories); ?></strong>
                </div>
            </div>
        <?php endif; ?>

    </div>
<?php endif; ?>

        
    </section>
    <?php
}
add_action('woocommerce_after_single_product_summary', 'ark_product_ui_customer_address_card', 5);



function ark_product_ui_description_section() {
    global $product;

    if (!$product instanceof WC_Product) {
        return;
    }

    $description = (string) $product->get_description();
    $short_description = (string) $product->get_short_description();

    $preview_source = trim(wp_strip_all_tags($short_description));
    if ($preview_source === '') {
        $preview_source = trim(wp_strip_all_tags($description));
    }

    $preview = $preview_source !== '' ? wp_html_excerpt($preview_source, 180, '…') : '';
    $has_full_description = trim(wp_strip_all_tags($description)) !== '';
    ?>
    <section class="ark-product-details-card" id="ark-product-details">
        <div class="ark-product-details-heading">
            <span class="material-symbols-rounded" aria-hidden="true">description</span>
            <h2><?php esc_html_e('Product Details', 'ark-material-commerce'); ?></h2>
        </div>

        <?php if ($preview !== '') : ?>
            <div class="ark-product-preview"><?php echo esc_html($preview); ?></div>
        <?php else : ?>
            <p class="ark-product-description-empty"><?php esc_html_e('Product details will be added soon.', 'ark-material-commerce'); ?></p>
        <?php endif; ?>

        <?php if ($has_full_description) : ?>
            <details class="ark-product-description-accordion">
                <summary>
                    <span><?php esc_html_e('Read full description', 'ark-material-commerce'); ?></span>
                    <span class="ark-description-arrow material-symbols-rounded" aria-hidden="true">keyboard_arrow_down</span>
                </summary>
                <div class="ark-product-full-description">
                    <?php echo apply_filters('the_content', $description); ?>
                </div>
            </details>
        <?php endif; ?>
    </section>
    <?php
}

/**
 * Show a real sale discount badge beside the WooCommerce price.
 */
function ark_product_ui_add_discount_badge($price_html, $product) {
    if (
        is_admin() ||
        !function_exists('is_product') ||
        !is_product() ||
        !$product instanceof WC_Product ||
        !$product->is_on_sale()
    ) {
        return $price_html;
    }

    // Keep the badge on the main product price, not related/upsell products.
    if ((int) $product->get_id() !== (int) get_queried_object_id()) {
        return $price_html;
    }

    $regular_price = (float) $product->get_regular_price();
    $sale_price    = (float) $product->get_sale_price();

    if ($product->is_type('variable')) {
        $regular_price = (float) $product->get_variation_regular_price('min', true);
        $sale_price    = (float) $product->get_variation_sale_price('min', true);
    }

    if ($regular_price <= 0 || $sale_price <= 0 || $sale_price >= $regular_price) {
        return $price_html;
    }

    $discount = (int) round((($regular_price - $sale_price) / $regular_price) * 100);
    if ($discount <= 0) {
        return $price_html;
    }

    $badge = sprintf(
    '<span class="ark-product-discount-badge"><span aria-hidden="true">↓</span> %d%% %s</span> <span class="ark-product-saver-inline">Saver Deal</span>',
    $discount,
    esc_html__('OFF', 'ark-material-commerce')
);

    return $price_html . ' ' . $badge;
}
add_filter('woocommerce_get_price_html', 'ark_product_ui_add_discount_badge', 20, 2);


/**
 * Keep the product search bar visible at the top of the page only.
 * Also update the sticky Buy Now price when a variable product variation changes.
 */
function ark_product_ui_product_behaviour_script() {
    if (!ark_product_ui_is_enabled()) {
        return;
    }
    ?>
    <script>
    (function () {
        
document.addEventListener('click', function (event) {
    var buyButton = event.target.closest('.ark-product-sticky-buy');

    if (!buyButton) {
        return;
    }

    var form = document.querySelector(
        'body.single-product form.cart'
    );

    if (!form) {
        return;
    }

    var flag = form.querySelector('input[name="ark_buy_now"]');
    var addButton = form.querySelector('.single_add_to_cart_button');

    if (!flag || !addButton) {
        return;
    }

    // Mark this action as Buy Now, not Add to Cart.
    flag.value = '1';

    // Trigger WooCommerce's normal product/variation validation.
    addButton.click();
}, true);

        var summary = document.querySelector(
    'body.single-product .woocommerce div.product div.summary'
);

if (summary) {
    var price = summary.querySelector(':scope > p.price, :scope > span.price');

    var wishlist = summary.querySelector(
        '.yith-wcwl-add-to-wishlist'
    );

    if (price && wishlist && !summary.querySelector('.ark-wishlist-price-row')) {
        var row = document.createElement('div');
        row.className = 'ark-wishlist-price-row';

        wishlist.parentNode.insertBefore(row, wishlist);

        row.appendChild(wishlist);
        row.appendChild(price);
    }
}
        updateTopbar();
        window.addEventListener('scroll', updateTopbar, { passive: true });
        window.addEventListener('pageshow', updateTopbar);

        if (window.jQuery) {
            jQuery(function ($) {
                var $buyPrice = $('.ark-product-sticky-buy-price');
                var defaultPrice = $buyPrice.html();

                $('form.variations_form')
                    .on('found_variation', function (event, variation) {
                        if (variation && variation.price_html) {
                            var holder = document.createElement('div');
                            holder.innerHTML = variation.price_html;
                            var amount = holder.querySelector('.amount');
                            if (amount && $buyPrice.length) {
                                $buyPrice.html(amount.outerHTML);
                            }
                        } else if (variation && typeof variation.display_price !== 'undefined' && $buyPrice.length) {
                            $buyPrice.text(new Intl.NumberFormat(undefined, {
                                style: 'currency',
                                currency: (window.arkWooCurrency || 'INR'),
                                maximumFractionDigits: 2
                            }).format(variation.display_price));
                        }
                    })
                    .on('reset_data', function () {
                        $buyPrice.html(defaultPrice);
                    });
            });
        }
    })();
    </script>
    <?php
}
add_action('wp_footer', 'ark_product_ui_product_behaviour_script', 40);

