
<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

</div>
</main>

<?php
// Website footer.
if (function_exists('ark_material_footer')) {
    ark_material_footer();
}

/**
 * Decide whether the bottom navigation is hidden on this page.
 */
$ark_nav_hidden = false;

if (is_front_page() && get_theme_mod('ark_nav_hide_home', 0)) {
    $ark_nav_hidden = true;
}

if (
    function_exists('is_shop') &&
    (is_shop() || is_product_category() || is_product_tag()) &&
    get_theme_mod('ark_nav_hide_shop', 0)
) {
    $ark_nav_hidden = true;
}

if (
    function_exists('is_product') &&
    is_product() &&
    get_theme_mod('ark_nav_hide_product', 0)
) {
    $ark_nav_hidden = true;
}

if (
    function_exists('is_cart') &&
    is_cart() &&
    get_theme_mod('ark_nav_hide_cart', 0)
) {
    $ark_nav_hidden = true;
}

if (
    function_exists('is_checkout') &&
    is_checkout() &&
    get_theme_mod('ark_nav_hide_checkout', 0)
) {
    $ark_nav_hidden = true;
}

if (
    function_exists('is_account_page') &&
    is_account_page() &&
    get_theme_mod('ark_nav_hide_account', 0)
) {
    $ark_nav_hidden = true;
}

if (
    (is_home() || is_single() || is_archive()) &&
    get_theme_mod('ark_nav_hide_blog', 0)
) {
    $ark_nav_hidden = true;
}

/**
 * Default navigation buttons.
 */
$ark_nav_items = array(
    'home' => array(
        'label' => 'Home',
        'icon'  => 'home',
        'url'   => home_url('/'),
    ),
    'shop' => array(
        'label' => 'Shop',
        'icon'  => 'storefront',
        'url'   => function_exists('wc_get_page_permalink')
            ? wc_get_page_permalink('shop')
            : home_url('/shop/'),
    ),
    'search' => array(
        'label' => 'Search',
        'icon'  => 'search',
        'url'   => home_url('/?s='),
    ),
    'account' => array(
        'label' => 'Account',
        'icon'  => 'person',
        'url'   => function_exists('wc_get_page_permalink')
            ? wc_get_page_permalink('myaccount')
            : wp_login_url(),
    ),
    'cart' => array(
        'label' => 'Cart',
        'icon'  => 'shopping_bag',
        'url'   => function_exists('wc_get_cart_url')
            ? wc_get_cart_url()
            : home_url('/'),
    ),
);

/**
 * Apply Customizer values.
 */
foreach ($ark_nav_items as $key => $item) {
    $ark_nav_items[$key]['enabled'] = (bool) get_theme_mod(
        'ark_nav_' . $key . '_enabled',
        1
    );

    $ark_nav_items[$key]['label'] = sanitize_text_field(
        get_theme_mod('ark_nav_' . $key . '_label', $item['label'])
    );

    $ark_nav_items[$key]['icon'] = sanitize_text_field(
        get_theme_mod('ark_nav_' . $key . '_icon', $item['icon'])
    );

    $ark_nav_items[$key]['url'] = esc_url(
        get_theme_mod('ark_nav_' . $key . '_url', $item['url'])
    );
}

/**
 * Count enabled buttons.
 */
$ark_nav_visible_count = 0;

foreach ($ark_nav_items as $item) {
    if (
        !empty($item['enabled']) &&
        !empty($item['label']) &&
        !empty($item['url'])
    ) {
        $ark_nav_visible_count++;
    }
}

/**
 * Display bottom navigation.
 */
if (
    get_theme_mod('ark_mobile_nav', 1) &&
    !$ark_nav_hidden &&
    $ark_nav_visible_count > 0
) :
?>
<nav
    class="ark-mobile-nav"
    aria-label="<?php esc_attr_e('Mobile navigation', 'ark-material-commerce'); ?>"
    style="--ark-nav-columns: <?php echo esc_attr((string) $ark_nav_visible_count); ?>;"
>
    <?php foreach ($ark_nav_items as $key => $item) : ?>

        <?php
        if (
            empty($item['enabled']) ||
            empty($item['label']) ||
            empty($item['url'])
        ) {
            continue;
        }

        $is_active = false;

        if ($key === 'home' && is_front_page()) {
            $is_active = true;
        } elseif (
            $key === 'shop' &&
            function_exists('is_shop') &&
            (is_shop() || is_product_category() || is_product_tag())
        ) {
            $is_active = true;
        } elseif (
            $key === 'cart' &&
            function_exists('is_cart') &&
            is_cart()
        ) {
            $is_active = true;
        } elseif (
            $key === 'account' &&
            function_exists('is_account_page') &&
            is_account_page()
        ) {
            $is_active = true;
        }
        ?>

        <a
            class="<?php echo $is_active ? 'is-active' : ''; ?>"
            href="<?php echo esc_url($item['url']); ?>"
            <?php echo $is_active ? 'aria-current="page"' : ''; ?>
        >
            <?php
            if (function_exists('ark_material_icon')) {
                echo ark_material_icon($item['icon']); // Theme icon renderer.
            } else {
                echo '<span class="material-symbols-rounded" aria-hidden="true">' .
                    esc_html($item['icon']) .
                    '</span>';
            }
            ?>

            <span class="ark-mobile-nav-label">
                <?php echo esc_html($item['label']); ?>
            </span>

            <?php if ($key === 'cart' && function_exists('ark_material_cart_count')) : ?>
                <?php $ark_cart_count = (int) ark_material_cart_count(); ?>

                <?php if ($ark_cart_count > 0) : ?>
                    <span class="ark-mobile-nav-count">
                        <?php echo esc_html((string) $ark_cart_count); ?>
                    </span>
                <?php endif; ?>
            <?php endif; ?>
        </a>

    <?php endforeach; ?>
</nav>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
