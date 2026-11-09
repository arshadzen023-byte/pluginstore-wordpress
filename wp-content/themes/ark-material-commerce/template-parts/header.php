
<?php
if (!defined('ABSPATH')) {
    exit;
}

$brand_name = get_theme_mod(
    'ark_brand_name',
    get_bloginfo('name') ?: 'ARK Mart'
);

$show_ark_card = (bool) get_theme_mod('ark_show_ark_card', 1);
$show_plugin_card = (bool) get_theme_mod('ark_show_plugin_card', 1);
$plugin_label = get_theme_mod('ark_plugin_card_label', 'Plugin');
$plugin_url = get_theme_mod('ark_plugin_card_url', home_url('/'));
$show_search = (bool) get_theme_mod('ark_header_search', 1);
$show_fireworks = (bool) get_theme_mod('ark_fireworks_enabled', 1);
?>

<header class="ark-site-header <?php echo $show_fireworks ? 'ark-fireworks-on' : ''; ?>">

    <?php if ($show_fireworks) : ?>
        <div class="ark-fireworks-layer" aria-hidden="true"></div>
    <?php endif; ?>

    <div class="ark-container ark-header-inner">

        <!-- TOP ROW: WEBSITE CARDS -->
        <div class="ark-header-cards">

            <?php if ($show_ark_card) : ?>
                <a class="ark-top-card ark-top-card-primary"
                   href="<?php echo esc_url(home_url('/')); ?>"
                   aria-label="<?php esc_attr_e('Open ARK Mart home', 'ark-material-commerce'); ?>">

                    <span class="ark-top-card-logo">
                        <?php if (has_custom_logo()) : ?>
                            <?php echo wp_kses_post(get_custom_logo()); ?>
                        <?php else : ?>
                            <?php echo ark_material_icon('shopping_bag'); ?>
                        <?php endif; ?>
                    </span>

                    <span class="ark-top-card-title">
                        <?php echo esc_html($brand_name); ?>
                    </span>
                </a>
            <?php endif; ?>

            <?php if ($show_plugin_card && $plugin_label !== '') : ?>
                <a class="ark-top-card ark-top-card-plugin"
                   href="<?php echo esc_url($plugin_url); ?>">
                    <span class="ark-plugin-card-icon">
                        <?php echo ark_material_icon('extension'); ?>
                    </span>
                    <span class="ark-top-card-title">
                        <?php echo esc_html($plugin_label); ?>
                    </span>
                </a>
            <?php endif; ?>

        </div>

        <!-- SECOND ROW: SEARCH + CART -->
        <div class="ark-header-search-row">

            <?php if ($show_search) : ?>
                <form class="ark-search" role="search"
                      method="get"
                      action="<?php echo esc_url(home_url('/')); ?>">

                    <?php echo ark_material_icon('search'); ?>

                    <label class="screen-reader-text" for="ark-header-search">
                        <?php esc_html_e('Search products', 'ark-material-commerce'); ?>
                    </label>

                    <input
                        id="ark-header-search"
                        type="search"
                        name="s"
                        placeholder="<?php esc_attr_e('Search products…', 'ark-material-commerce'); ?>"
                        value="<?php echo esc_attr(get_search_query()); ?>"
                    >

                    <?php if (class_exists('WooCommerce')) : ?>
                        <input type="hidden" name="post_type" value="product">
                    <?php endif; ?>
                </form>
            <?php endif; ?>

            <div class="ark-header-actions">

                <a class="ark-icon-link ark-cart-action"
                   href="<?php echo esc_url(function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/')); ?>"
                   aria-label="<?php esc_attr_e('Shopping cart', 'ark-material-commerce'); ?>">

                    <?php echo ark_material_icon('shopping_cart'); ?>

                    <?php if (function_exists('ark_material_cart_count') && ark_material_cart_count() > 0) : ?>
                        <span class="ark-cart-count">
                            <?php echo esc_html(ark_material_cart_count()); ?>
                        </span>
                    <?php endif; ?>
                </a>

                <a class="ark-icon-link ark-account-action"
                   href="<?php echo esc_url(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url()); ?>"
                   aria-label="<?php esc_attr_e('My account', 'ark-material-commerce'); ?>">
                    <?php echo ark_material_icon('person'); ?>
                </a>

            </div>
        </div>

    </div>
</header>
