
<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ARK Account UI — Customizer.
 */
function ark_account_ui_customizer($wp_customize) {
    $wp_customize->add_section('ark_account_ui', array(
        'title'       => __('ARK Account Page', 'ark-material-commerce'),
        'description' => __('Customize the mobile-app style customer account.', 'ark-material-commerce'),
        'priority'    => 34,
    ));

    $settings = array(
        'ark_account_hide_header' => array(
            'label' => 'Hide normal website header on account pages',
            'type' => 'checkbox',
            'default' => 1,
        ),
        'ark_account_header_name' => array(
            'label' => 'Account header website name',
            'type' => 'text',
            'default' => get_theme_mod('ark_material_brand_name', 'ARK Mart'),
        ),
        'ark_account_show_orders' => array(
            'label' => 'Show My Orders card',
            'type' => 'checkbox',
            'default' => 1,
        ),
        'ark_account_show_wishlist' => array(
            'label' => 'Show Wishlist card',
            'type' => 'checkbox',
            'default' => 1,
        ),
        'ark_account_show_addresses' => array(
            'label' => 'Show Addresses menu',
            'type' => 'checkbox',
            'default' => 1,
        ),
        'ark_account_show_profile' => array(
            'label' => 'Show Edit Profile menu',
            'type' => 'checkbox',
            'default' => 1,
        ),
        'ark_account_show_downloads' => array(
            'label' => 'Show Downloads menu',
            'type' => 'checkbox',
            'default' => 1,
        ),
        'ark_account_show_help' => array(
            'label' => 'Show Help & Support menu',
            'type' => 'checkbox',
            'default' => 1,
        ),
        'ark_account_show_logout' => array(
            'label' => 'Show Logout menu',
            'type' => 'checkbox',
            'default' => 1,
        ),
        'ark_account_wishlist_url' => array(
            'label' => 'Wishlist page URL',
            'type' => 'url',
            'default' => home_url('/wishlist/'),
        ),
        'ark_account_help_url' => array(
            'label' => 'Help & Support page URL',
            'type' => 'url',
            'default' => home_url('/contact-us/'),
        ),
        'ark_account_title' => array(
            'label' => 'Account welcome title',
            'type' => 'text',
            'default' => 'Welcome back!',
        ),
        'ark_account_custom_menu' => array(
            'label' => 'Custom account menu items (JSON)',
            'type' => 'textarea',
            'default' => wp_json_encode(array(
                array(
                    'title' => 'Special Offers',
                    'description' => 'Explore current offers',
                    'url' => home_url('/'),
                    'icon' => 'local_offer',
                ),
            ), JSON_PRETTY_PRINT),
        ),
    );

    foreach ($settings as $id => $field) {
        $sanitize = 'sanitize_text_field';

        if ($field['type'] === 'checkbox') {
            $sanitize = 'absint';
        } elseif ($field['type'] === 'url') {
            $sanitize = 'esc_url_raw';
        } elseif ($field['type'] === 'textarea') {
            $sanitize = 'ark_account_sanitize_menu_json';
        }

        $wp_customize->add_setting($id, array(
            'default'           => $field['default'],
            'sanitize_callback' => $sanitize,
            'transport'         => 'refresh',
        ));

        $wp_customize->add_control($id, array(
            'label'       => __($field['label'], 'ark-material-commerce'),
            'description' => ($id === 'ark_account_custom_menu')
                ? __('Add or remove menu entries by editing the JSON list. Each entry needs title, description, url and icon.', 'ark-material-commerce')
                : '',
            'section'     => 'ark_account_ui',
            'type'        => $field['type'],
        ));
    }
}
add_action('customize_register', 'ark_account_ui_customizer');


/**
 * Validate custom menu JSON.
 */
function ark_account_sanitize_menu_json($value) {
    $value = wp_unslash((string) $value);
    $items = json_decode($value, true);

    if (!is_array($items)) {
        return '[]';
    }

    $clean = array();

    foreach (array_slice($items, 0, 20) as $item) {
        if (!is_array($item) || empty($item['title']) || empty($item['url'])) {
            continue;
        }

        $url = esc_url_raw($item['url']);

        if (!$url) {
            continue;
        }

        $clean[] = array(
            'title'       => sanitize_text_field($item['title']),
            'description' => sanitize_text_field($item['description'] ?? ''),
            'url'         => $url,
            'icon'        => sanitize_html_class($item['icon'] ?? 'chevron_right'),
        );
    }

    return wp_json_encode($clean, JSON_PRETTY_PRINT);
}


/**
 * Add account-specific body class.
 */
function ark_account_ui_body_class($classes) {
    if (function_exists('is_account_page') && is_account_page()) {
        $classes[] = 'ark-account-page';

        if (get_theme_mod('ark_account_hide_header', 1)) {
            $classes[] = 'ark-account-hide-header';
        }
    }

    return $classes;
}
add_filter('body_class', 'ark_account_ui_body_class');


/**
 * App-style header on all logged-in WooCommerce account endpoints.
 */
function ark_account_ui_app_header() {
    if (!is_user_logged_in() || !function_exists('wc_get_page_permalink')) {
        return;
    }

    $account_url = wc_get_page_permalink('myaccount');
    $site_name = get_theme_mod('ark_account_header_name', 'ARK Mart');

    if (empty($site_name)) {
        $site_name = get_bloginfo('name');
    }

    $back_url = wp_get_referer();

    // Never use the account page itself as the back destination.
    if (!$back_url || untrailingslashit($back_url) === untrailingslashit(home_url(wp_unslash($_SERVER['REQUEST_URI'] ?? '/')))) {
        $back_url = home_url('/');
    }
    ?>
    <header class="ark-account-app-header">
        <a class="ark-account-back"
           href="<?php echo esc_url($back_url); ?>"
           aria-label="<?php esc_attr_e('Go back', 'ark-material-commerce'); ?>">
            <span aria-hidden="true">←</span>
        </a>

        <a class="ark-account-site-name"
           href="<?php echo esc_url($account_url); ?>">
            <?php echo esc_html($site_name); ?>
        </a>

        <span class="ark-account-header-spacer" aria-hidden="true"></span>
    </header>
    <?php
}
add_action('woocommerce_before_account_navigation', 'ark_account_ui_app_header', 1);


/**
 * Custom account dashboard.
 */
function ark_account_ui_dashboard() {
    if (!is_user_logged_in()) {
        return;
    }

    $user = wp_get_current_user();
    $name = $user->first_name ?: $user->display_name;

    $orders_url = wc_get_account_endpoint_url('orders');
    $addresses_url = wc_get_account_endpoint_url('edit-address');
    $profile_url = wc_get_account_endpoint_url('edit-account');
    $downloads_url = wc_get_account_endpoint_url('downloads');
    $logout_url = wc_logout_url(wc_get_page_permalink('myaccount'));

    $wishlist_url = get_theme_mod('ark_account_wishlist_url', home_url('/wishlist/'));
    $help_url = get_theme_mod('ark_account_help_url', home_url('/contact-us/'));
    $title = get_theme_mod('ark_account_title', 'Welcome back!');

    ?>
    <section class="ark-account-dashboard">

        <div class="ark-account-welcome">
            <div class="ark-account-avatar" aria-hidden="true">
                <?php echo esc_html(strtoupper(substr($name, 0, 1))); ?>
            </div>

            <div class="ark-account-welcome-copy">
                <p><?php echo esc_html($title); ?></p>
                <h2><?php echo esc_html($name); ?></h2>
                <span><?php esc_html_e('Manage your account', 'ark-material-commerce'); ?></span>
            </div>
        </div>

        <div class="ark-account-shortcuts">
            <?php if (get_theme_mod('ark_account_show_orders', 1)) : ?>
                <a class="ark-account-shortcut ark-orders-shortcut"
                   href="<?php echo esc_url($orders_url); ?>">
                    <span class="material-symbols-rounded" aria-hidden="true">package_2</span>
                    <strong>My Orders</strong>
                    <small>Track and view purchases</small>
                </a>
            <?php endif; ?>

            <?php if (get_theme_mod('ark_account_show_wishlist', 1)) : ?>
                <a class="ark-account-shortcut ark-wishlist-shortcut"
                   href="<?php echo esc_url($wishlist_url); ?>">
                    <span class="material-symbols-rounded" aria-hidden="true">favorite</span>
                    <strong>Wishlist</strong>
                    <small>Your saved products</small>
                </a>
            <?php endif; ?>
        </div>

        <div class="ark-account-menu">
            <h3>Account Settings</h3>

            <?php
            $standard_items = array();

            if (get_theme_mod('ark_account_show_addresses', 1)) {
                $standard_items[] = array(
                    'title' => 'Addresses',
                    'description' => 'Manage billing and delivery addresses',
                    'url' => $addresses_url,
                    'icon' => 'location_on',
                );
            }

            if (get_theme_mod('ark_account_show_profile', 1)) {
                $standard_items[] = array(
                    'title' => 'Edit Profile',
                    'description' => 'Update your name and email',
                    'url' => $profile_url,
                    'icon' => 'manage_accounts',
                );
            }

            if (get_theme_mod('ark_account_show_downloads', 1)) {
                $standard_items[] = array(
                    'title' => 'Downloads',
                    'description' => 'Access your available downloads',
                    'url' => $downloads_url,
                    'icon' => 'download',
                );
            }

            if (get_theme_mod('ark_account_show_help', 1)) {
                $standard_items[] = array(
                    'title' => 'Help & Support',
                    'description' => 'Get help with your order',
                    'url' => $help_url,
                    'icon' => 'support_agent',
                );
            }

            if (get_theme_mod('ark_account_show_logout', 1)) {
                $standard_items[] = array(
                    'title' => 'Logout',
                    'description' => 'Sign out of your account',
                    'url' => $logout_url,
                    'icon' => 'logout',
                    'class' => 'ark-account-logout',
                );
            }

            foreach ($standard_items as $item) :
            ?>
                <a class="ark-account-menu-item <?php echo esc_attr($item['class'] ?? ''); ?>"
                   href="<?php echo esc_url($item['url']); ?>">
                    <span class="ark-account-menu-icon material-symbols-rounded" aria-hidden="true"><?php echo esc_html($item['icon']); ?></span>
                    <span class="ark-account-menu-copy">
                        <strong><?php echo esc_html($item['title']); ?></strong>
                        <small><?php echo esc_html($item['description']); ?></small>
                    </span>
                    <span class="material-symbols-rounded ark-account-chevron" aria-hidden="true">chevron_right</span>
                </a>
            <?php endforeach; ?>

            <?php
            $custom_json = get_theme_mod('ark_account_custom_menu', '[]');
            $custom_items = json_decode($custom_json, true);

            if (is_array($custom_items)) :
                foreach ($custom_items as $item) :
                    if (empty($item['title']) || empty($item['url'])) {
                        continue;
                    }
            ?>
                <a class="ark-account-menu-item"
                   href="<?php echo esc_url($item['url']); ?>">
                    <span class="ark-account-menu-icon material-symbols-rounded" aria-hidden="true"><?php echo esc_html($item['icon'] ?? 'chevron_right'); ?></span>
                    <span class="ark-account-menu-copy">
                        <strong><?php echo esc_html($item['title']); ?></strong>
                        <small><?php echo esc_html($item['description'] ?? ''); ?></small>
                    </span>
                    <span class="material-symbols-rounded ark-account-chevron" aria-hidden="true">chevron_right</span>
                </a>
            <?php
                endforeach;
            endif;
            ?>
        </div>
    </section>
    <?php
}
add_action('woocommerce_account_dashboard', 'ark_account_ui_dashboard', 1);


/**
 * Hide the default WooCommerce greeting and description.
 * The custom dashboard section is not affected.
 */
function ark_account_ui_hide_default_text_css() {
    if (!function_exists('is_account_page') || !is_account_page()) {
        return;
    }
    ?>
    <style id="ark-account-hide-default-greeting">
        body.ark-account-page .woocommerce-MyAccount-content > p {
            display: none !important;
        }

        .ark-account-app-header {
            display: flex;
            align-items: center;
            gap: 14px;
            width: 100%;
            box-sizing: border-box;
            padding: 14px 20px;
            margin: 0 0 20px;
            background: #fff;
            border-bottom: 1px solid #e9e6f1;
            box-shadow: 0 3px 14px rgba(28, 24, 45, .06);
        }

        .ark-account-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 14px;
            background: #f0eaff;
            color: #6546b8;
            text-decoration: none;
            font-size: 28px;
            line-height: 1;
        }

        .ark-account-site-name {
            color: #211e2b;
            font-size: 18px;
            font-weight: 750;
            text-decoration: none;
        }

        .ark-account-header-spacer {
            flex: 1;
        }

        @media (max-width: 600px) {
            .ark-account-app-header {
                padding: 12px 14px;
                margin-bottom: 14px;
            }

            .ark-account-site-name {
                font-size: 17px;
            }
        }
    </style>
    <?php
}
add_action('wp_head', 'ark_account_ui_hide_default_text_css', 99);
