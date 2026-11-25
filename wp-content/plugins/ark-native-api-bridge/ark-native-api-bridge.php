<?php
/**
 * Plugin Name: ARK GlowHit Native Commerce Bridge
 * Description: Unified secure native commerce API for GlowHit Flutter: customer auth, addresses, orders, wishlist, payment configuration and UPI proof submission.
 * Version: 2.1.0
 * Author: ARK
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce
 */
defined('ABSPATH') || exit;

final class ARK_Native_API_Bridge {
    const NS = 'ark-native/v1';
    const WISHLIST_META = '_ark_cw_ids';
    const TOKEN_TTL = 1209600; // 14 days

    public function __construct() {
        add_action('rest_api_init', [$this, 'routes']);
    }

    public function routes() {
        // Public diagnostic endpoint: confirms that this exact API bridge is loaded.
        register_rest_route(self::NS, '/health', [
            'methods' => 'GET',
            'callback' => [$this, 'health'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::NS, '/auth/login', [
            'methods' => 'POST',
            'callback' => [$this, 'login'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::NS, '/auth/register', [
            'methods' => 'POST',
            'callback' => [$this, 'register'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::NS, '/auth/me', [
            'methods' => 'GET',
            'callback' => [$this, 'me'],
            'permission_callback' => [$this, 'authorize'],
        ]);
        register_rest_route(self::NS, '/auth/profile', [
            'methods' => 'POST',
            'callback' => [$this, 'update_profile'],
            'permission_callback' => [$this, 'authorize'],
        ]);

        // Compatibility routes used by the current GlowHit Android client.
        // They share this bridge's JWT authentication; the retired customer API
        // plugin is not required.
        register_rest_route(self::NS, '/customer/login', [
            'methods' => 'POST', 'callback' => [$this, 'customer_login_compat'], 'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::NS, '/customer/logout', [
            'methods' => 'POST', 'callback' => [$this, 'customer_logout_compat'], 'permission_callback' => [$this, 'authorize'],
        ]);
        register_rest_route(self::NS, '/customer/me', [
            ['methods' => 'GET', 'callback' => [$this, 'customer_profile_compat'], 'permission_callback' => [$this, 'authorize']],
            ['methods' => 'PUT', 'callback' => [$this, 'customer_profile_compat'], 'permission_callback' => [$this, 'authorize']],
        ]);
        register_rest_route(self::NS, '/customer/orders', [
            'methods' => 'GET', 'callback' => [$this, 'customer_orders_compat'], 'permission_callback' => [$this, 'authorize'],
        ]);
        register_rest_route(self::NS, '/customer/orders/(?P<id>\\d+)', [
            'methods' => 'GET', 'callback' => [$this, 'customer_order_detail_compat'], 'permission_callback' => [$this, 'authorize'],
        ]);
        register_rest_route(self::NS, '/customer/orders/(?P<id>\\d+)/cancel', [
            'methods' => 'POST', 'callback' => [$this, 'cancel_order'], 'permission_callback' => [$this, 'authorize'],
        ]);
        register_rest_route(self::NS, '/customer/orders/(?P<id>\\d+)/return', [
            'methods' => 'POST', 'callback' => [$this, 'customer_return_request'], 'permission_callback' => [$this, 'authorize'],
        ]);
        register_rest_route(self::NS, '/customer/downloads', [
            'methods' => 'GET', 'callback' => [$this, 'customer_downloads_compat'], 'permission_callback' => [$this, 'authorize'],
        ]);
        register_rest_route(self::NS, '/customer/addresses', [
            ['methods' => 'GET', 'callback' => [$this, 'customer_addresses_compat'], 'permission_callback' => [$this, 'authorize']],
            ['methods' => 'POST', 'callback' => [$this, 'customer_addresses_compat'], 'permission_callback' => [$this, 'authorize']],
            ['methods' => 'DELETE', 'callback' => [$this, 'customer_addresses_compat'], 'permission_callback' => [$this, 'authorize']],
        ]);
        register_rest_route(self::NS, '/account/addresses', [
            'methods' => 'GET',
            'callback' => [$this, 'account_addresses'],
            'permission_callback' => [$this, 'authorize'],
        ]);
        register_rest_route(self::NS, '/account/addresses', [
            'methods' => 'POST',
            'callback' => [$this, 'save_account_addresses'],
            'permission_callback' => [$this, 'authorize'],
        ]);
        register_rest_route(self::NS, '/account/downloads', [
            'methods' => 'GET',
            'callback' => [$this, 'account_downloads'],
            'permission_callback' => [$this, 'authorize'],
        ]);
        register_rest_route(self::NS, '/product-data/(?P<id>\\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'native_product_data'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::NS, '/orders', [
            'methods' => 'GET',
            'callback' => [$this, 'orders'],
            'permission_callback' => [$this, 'authorize'],
        ]);
        register_rest_route(self::NS, '/orders/attach', [
            'methods' => 'POST',
            'callback' => [$this, 'attach_order'],
            'permission_callback' => [$this, 'authorize'],
        ]);

        // Backward-compatible alias for earlier GlowHit app builds.
        register_rest_route(self::NS, '/customer/orders/attach', [
            'methods' => 'POST',
            'callback' => [$this, 'attach_order'],
            'permission_callback' => [$this, 'authorize'],
        ]);
        register_rest_route(self::NS, '/orders/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'order_detail'],
            'permission_callback' => [$this, 'authorize'],
        ]);
        register_rest_route(self::NS, '/orders/(?P<id>\d+)/cancel', [
            'methods' => 'POST',
            'callback' => [$this, 'cancel_order'],
            'permission_callback' => [$this, 'authorize'],
        ]);
        register_rest_route(self::NS, '/wishlist', [
            'methods' => 'GET',
            'callback' => [$this, 'wishlist'],
            'permission_callback' => [$this, 'authorize'],
        ]);
        register_rest_route(self::NS, '/wishlist/toggle', [
            'methods' => 'POST',
            'callback' => [$this, 'wishlist_toggle'],
            'permission_callback' => [$this, 'authorize'],
        ]);

        register_rest_route(self::NS, '/payment/config', [
            'methods' => 'GET',
            'callback' => [$this, 'payment_config'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::NS, '/payment/upi-submit', [
            'methods' => 'POST',
            'callback' => [$this, 'submit_upi_payment'],
            'permission_callback' => '__return_true',
        ]);

        // Native Designer routes use the same signed customer token as the
        // commerce API, but require a WordPress administrator capability.
        register_rest_route(self::NS, '/designer/config', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'designer_config'],
                'permission_callback' => [$this, 'authorize_admin'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'save_designer_config'],
                'permission_callback' => [$this, 'authorize_admin'],
            ],
        ]);
        register_rest_route(self::NS, '/designer/media', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'designer_media'],
            'permission_callback' => [$this, 'authorize_admin'],
        ]);
    }

    public function health($request) {
        $woocommerce_active = class_exists('WooCommerce') && function_exists('WC') && WC();
        return rest_ensure_response([
            'success' => true,
            'plugin' => 'ARK GlowHit Native Commerce Bridge',
            'version' => '2.1.0',
            'woocommerce_active' => (bool) $woocommerce_active,
            'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '',
            'routes' => [
                'auth_login' => rest_url(self::NS . '/auth/login'),
                'customer_login_compat' => rest_url(self::NS . '/customer/login'),
                'customer_addresses' => rest_url(self::NS . '/customer/addresses'),
                'customer_orders' => rest_url(self::NS . '/customer/orders'),
                'order_attach' => rest_url(self::NS . '/orders/attach'),
                'payment_config' => rest_url(self::NS . '/payment/config'),
                'upi_submit' => rest_url(self::NS . '/payment/upi-submit'),
            ],
        ]);
    }

    private function b64url($value) {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function issue_token($user_id) {
        $now = time();
        $header = $this->b64url(wp_json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->b64url(wp_json_encode([
            'sub' => (int) $user_id,
            'iat' => $now,
            'exp' => $now + self::TOKEN_TTL,
            'iss' => home_url('/'),
        ]));
        $signature = $this->b64url(hash_hmac('sha256', $header . '.' . $payload, wp_salt('auth'), true));
        return $header . '.' . $payload . '.' . $signature;
    }

    private function token_user_id($request) {
        $authorization = (string) $request->get_header('authorization');
        if (!preg_match('/Bearer\s+(.+)/i', $authorization, $match)) {
            return new WP_Error('ark_auth_required', 'Please sign in to continue.', ['status' => 401]);
        }

        $parts = explode('.', trim($match[1]));
        if (count($parts) !== 3) {
            return new WP_Error('ark_invalid_token', 'Invalid login session. Please sign in again.', ['status' => 401]);
        }

        $expected = $this->b64url(hash_hmac('sha256', $parts[0] . '.' . $parts[1], wp_salt('auth'), true));
        if (!hash_equals($expected, $parts[2])) {
            return new WP_Error('ark_invalid_token', 'Invalid login session. Please sign in again.', ['status' => 401]);
        }

        $payload_json = base64_decode(strtr($parts[1], '-_', '+/'));
        $payload = json_decode((string) $payload_json, true);
        $user_id = absint($payload['sub'] ?? 0);
        $expires = absint($payload['exp'] ?? 0);

        if (!$user_id || !$expires || $expires < time()) {
            return new WP_Error('ark_expired_token', 'Your login session expired. Please sign in again.', ['status' => 401]);
        }

        if (($payload['iss'] ?? '') !== home_url('/')) {
            return new WP_Error('ark_invalid_issuer', 'Invalid login session.', ['status' => 401]);
        }

        return $user_id;
    }

    public function authorize($request) {
        $user_id = $this->token_user_id($request);
        if (is_wp_error($user_id)) return $user_id;
        $user = get_user_by('id', $user_id);
        if (!$user) return new WP_Error('ark_user_missing', 'Account not found.', ['status' => 401]);
        wp_set_current_user($user_id);
        return true;
    }

    public function authorize_admin($request) {
        $authorized = $this->authorize($request);
        if (is_wp_error($authorized)) return $authorized;
        if (!current_user_can('manage_options')) {
            return new WP_Error('ark_designer_admin_required', 'ARK Designer requires a WordPress administrator account.', ['status' => 403]);
        }
        return true;
    }

    private function designer_instance() {
        if (!class_exists('ARK_App_Designer')) {
            return new WP_Error('ark_designer_inactive', 'ARK App Designer plugin is not active.', ['status' => 503]);
        }
        try {
            $reflection = new ReflectionClass('ARK_App_Designer');
            return $reflection->newInstanceWithoutConstructor();
        } catch (Throwable $error) {
            return new WP_Error('ark_designer_unavailable', 'ARK App Designer could not be initialized.', ['status' => 503]);
        }
    }

    public function designer_config($request) {
        $designer = $this->designer_instance();
        if (is_wp_error($designer)) return $designer;
        return $designer->api_studio_config();
    }

    public function save_designer_config($request) {
        $body = $request->get_json_params();
        if (!is_array($body) || !is_array($body['config'] ?? null)) {
            return new WP_Error('ark_designer_payload', 'Designer configuration is missing or invalid.', ['status' => 400]);
        }
        $designer = $this->designer_instance();
        if (is_wp_error($designer)) return $designer;
        $inner = new WP_REST_Request('POST');
        $inner->set_header('Content-Type', 'application/json');
        $inner->set_body(wp_json_encode($body['config']));
        $publish = ($body['status'] ?? 'draft') === 'published';
        return $publish ? $designer->api_publish($inner) : $designer->api_save_draft($inner);
    }

    public function designer_media($request) {
        $designer = $this->designer_instance();
        if (is_wp_error($designer)) return $designer;
        $inner = new WP_REST_Request('GET');
        $inner->set_query_params($request->get_query_params());
        return $designer->api_studio_media($inner);
    }

    private function user_payload($user) {
        return [
            'id' => (int) $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'first_name' => get_user_meta($user->ID, 'first_name', true),
            'last_name' => get_user_meta($user->ID, 'last_name', true),
            'phone' => get_user_meta($user->ID, 'billing_phone', true),
        ];
    }

    public function login($request) {
        $username = sanitize_text_field((string) $request->get_param('username'));
        $password = (string) $request->get_param('password');

        if ($username === '' || $password === '') {
            return new WP_Error('ark_login_fields', 'Enter your email/username and password.', ['status' => 400]);
        }

        if (is_email($username)) {
            $user_by_email = get_user_by('email', $username);
            if ($user_by_email) $username = $user_by_email->user_login;
        }

        $user = wp_authenticate($username, $password);
        if (is_wp_error($user)) {
            return new WP_Error('ark_login_failed', 'Incorrect username/email or password.', ['status' => 401]);
        }

        return rest_ensure_response([
            'success' => true,
            'token' => $this->issue_token($user->ID),
            'user' => $this->user_payload($user),
        ]);
    }

    public function register($request) {
        if (!function_exists('wc_create_new_customer')) {
            return new WP_Error('ark_wc_missing', 'WooCommerce is required.', ['status' => 503]);
        }

        if (get_option('woocommerce_enable_myaccount_registration') !== 'yes') {
            return new WP_Error('ark_registration_disabled', 'Customer registration is disabled in WooCommerce settings.', ['status' => 403]);
        }

        $email = sanitize_email((string) $request->get_param('email'));
        $password = (string) $request->get_param('password');
        $first = sanitize_text_field((string) $request->get_param('first_name'));
        $last = sanitize_text_field((string) $request->get_param('last_name'));

        if (!is_email($email)) return new WP_Error('ark_email_invalid', 'Enter a valid email address.', ['status' => 400]);
        if (strlen($password) < 8) return new WP_Error('ark_password_short', 'Password must contain at least 8 characters.', ['status' => 400]);
        if (email_exists($email)) return new WP_Error('ark_email_exists', 'An account already exists with this email.', ['status' => 409]);

        $username = sanitize_user(current(explode('@', $email)), true);
        if ($username === '') $username = 'customer';
        if (username_exists($username)) $username .= wp_rand(100, 9999);

        $customer_id = wc_create_new_customer($email, $username, $password, [
            'first_name' => $first,
            'last_name' => $last,
            'display_name' => trim($first . ' ' . $last),
        ]);

        if (is_wp_error($customer_id)) return $customer_id;

        $user = get_user_by('id', $customer_id);
        return rest_ensure_response([
            'success' => true,
            'token' => $this->issue_token($customer_id),
            'user' => $this->user_payload($user),
        ]);
    }

    public function me($request) {
        $user = wp_get_current_user();
        return rest_ensure_response(['user' => $this->user_payload($user)]);
    }

    public function update_profile($request) {
        $user_id = get_current_user_id();
        $first = sanitize_text_field((string) $request->get_param('first_name'));
        $last = sanitize_text_field((string) $request->get_param('last_name'));
        $email = sanitize_email((string) $request->get_param('email'));
        $phone = sanitize_text_field((string) $request->get_param('phone'));
        $display = trim($first . ' ' . $last);

        $user = get_user_by('id', $user_id);
        if (!$user) return new WP_Error('ark_account_missing', 'Account was not found.', ['status' => 404]);

        $update = ['ID' => $user_id];
        if ($display !== '') $update['display_name'] = $display;
        if ($email !== '' && strtolower($email) !== strtolower($user->user_email)) {
            if (!is_email($email)) return new WP_Error('ark_email_invalid', 'Enter a valid email address.', ['status' => 400]);
            $existing = email_exists($email);
            if ($existing && (int) $existing !== $user_id) {
                return new WP_Error('ark_email_exists', 'This email is already used by another account.', ['status' => 409]);
            }
            $update['user_email'] = $email;
        }

        $result = wp_update_user($update);
        if (is_wp_error($result)) return $result;

        update_user_meta($user_id, 'first_name', $first);
        update_user_meta($user_id, 'last_name', $last);
        if ($phone !== '') update_user_meta($user_id, 'billing_phone', $phone);
        if ($email !== '') update_user_meta($user_id, 'billing_email', $email);

        return rest_ensure_response(['success' => true, 'user' => $this->user_payload(get_user_by('id', $user_id))]);
    }

    private function account_address_payload($user_id, $prefix) {
        $fields = ['first_name','last_name','company','address_1','address_2','city','state','postcode','country','phone','email'];
        $out = [];
        foreach ($fields as $field) {
            $out[$field] = (string) get_user_meta($user_id, $prefix . '_' . $field, true);
        }
        return $out;
    }

    /**
     * Return recent orders for this account and safely claim guest orders whose
     * billing email exactly matches the signed-in account email.
     */
    private function account_orders_for_user($user_id, $limit = 50) {
        $orders_by_id = [];
        $limit = min(50, max(1, absint($limit)));

        $customer_orders = wc_get_orders([
            'customer_id' => $user_id,
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects',
        ]);
        foreach ((array) $customer_orders as $order) {
            if ($order instanceof WC_Order) {
                $orders_by_id[$order->get_id()] = $order;
            }
        }

        $user = get_user_by('id', $user_id);
        if ($user && is_email($user->user_email)) {
            $email_orders = wc_get_orders([
                'billing_email' => $user->user_email,
                'limit' => $limit,
                'orderby' => 'date',
                'order' => 'DESC',
                'return' => 'objects',
            ]);
            foreach ((array) $email_orders as $order) {
                if (!($order instanceof WC_Order)) continue;
                $owner = (int) $order->get_customer_id();
                if ($owner !== 0 && $owner !== (int) $user_id) continue;
                if ($owner === 0) {
                    $order->set_customer_id((int) $user_id);
                    $order->save();
                }
                $orders_by_id[$order->get_id()] = $order;
            }
        }

        $orders = array_values($orders_by_id);
        usort($orders, function ($a, $b) {
            $a_date = $a->get_date_created();
            $b_date = $b->get_date_created();
            return ($b_date ? $b_date->getTimestamp() : 0) <=> ($a_date ? $a_date->getTimestamp() : 0);
        });

        return array_slice($orders, 0, $limit);
    }

    public function account_addresses($request) {
        $user_id = get_current_user_id();

        // Some existing customer accounts have addresses saved only on their
        // previous orders. Restore missing profile fields from the newest
        // eligible order without overwriting any address the customer saved.
        if (function_exists('wc_update_new_customer_past_orders')) {
            // WooCommerce also rebuilds downloadable-file permissions when it
            // links an old guest order to its matching customer account.
            wc_update_new_customer_past_orders($user_id);
        }
        if (function_exists('wc_get_orders')) {
            foreach ($this->account_orders_for_user($user_id, 30) as $order) {
                foreach (['billing', 'shipping'] as $prefix) {
                    $saved = $this->account_address_payload($user_id, $prefix);
                    $order_address = $order->get_address($prefix);
                    foreach (array_keys($saved) as $field) {
                        if (trim((string) $saved[$field]) !== '') continue;
                        $value = sanitize_text_field((string) ($order_address[$field] ?? ''));
                        if ($value !== '') {
                            update_user_meta($user_id, $prefix . '_' . $field, $value);
                        }
                    }
                }
            }
        }

        return rest_ensure_response([
            'billing' => $this->account_address_payload($user_id, 'billing'),
            'shipping' => $this->account_address_payload($user_id, 'shipping'),
        ]);
    }

    public function save_account_addresses($request) {
        $user_id = get_current_user_id();
        $fields = ['first_name','last_name','company','address_1','address_2','city','state','postcode','country','phone','email'];
        $billing = $request->get_param('billing');
        $shipping = $request->get_param('shipping');
        $billing = is_array($billing) ? $billing : [];
        $shipping = is_array($shipping) ? $shipping : [];

        foreach (['billing' => $billing, 'shipping' => $shipping] as $prefix => $values) {
            foreach ($fields as $field) {
                if (!array_key_exists($field, $values)) continue;
                $raw = (string) $values[$field];
                $clean = in_array($field, ['email'], true) ? sanitize_email($raw) : sanitize_text_field($raw);
                update_user_meta($user_id, $prefix . '_' . $field, $clean);
            }
        }

        return rest_ensure_response([
            'success' => true,
            'billing' => $this->account_address_payload($user_id, 'billing'),
            'shipping' => $this->account_address_payload($user_id, 'shipping'),
        ]);
    }

    public function account_downloads($request) {
        if (!function_exists('wc_get_customer_available_downloads')) {
            return rest_ensure_response(['downloads' => []]);
        }

        $user_id = get_current_user_id();

        // Include older guest purchases made with this account email. The
        // matching, unowned orders are linked before WooCommerce builds the
        // customer's available-download permissions list.
        if (function_exists('wc_update_new_customer_past_orders')) {
            // This WooCommerce helper links guest orders by exact account email
            // and regenerates their user_id-scoped download permissions.
            wc_update_new_customer_past_orders($user_id);
        } elseif (function_exists('wc_get_orders')) {
            $this->account_orders_for_user($user_id, 50);
        }

        $available = wc_get_customer_available_downloads($user_id);
        $downloads = [];

        foreach ((array) $available as $download) {
            $get = function ($method, $fallback = '') use ($download) {
                if (is_object($download) && method_exists($download, $method)) return $download->{$method}();
                if (is_array($download)) {
                    $key = preg_replace('/^get_/', '', $method);
                    return $download[$key] ?? $fallback;
                }
                return $fallback;
            };

            $product_id = absint($get('get_product_id', 0));
            $product = $product_id ? wc_get_product($product_id) : false;
            $url = (string) $get('get_download_url', '');
            $name = (string) $get('get_download_name', $product ? $product->get_name() : 'Download');
            if ($url === '') continue;

            $expiry = $get('get_access_expires', '');
            if ($expiry instanceof DateTimeInterface) $expiry = $expiry->format(DATE_ATOM);
            $file_info = $get('get_file', []);
            $file_name = is_array($file_info) ? sanitize_file_name((string) ($file_info['name'] ?? '')) : '';

            $downloads[] = [
                'name' => wp_strip_all_tags($name),
                'product_name' => $product ? wp_strip_all_tags($product->get_name()) : wp_strip_all_tags($name),
                'product_id' => $product_id,
                'order_id' => absint($get('get_order_id', 0)),
                'download_url' => esc_url_raw($url),
                'file_name' => $file_name,
                'downloads_remaining' => (string) $get('get_downloads_remaining', ''),
                'access_expires' => (string) $expiry,
            ];
        }

        return rest_ensure_response(['downloads' => $downloads]);
    }

    public function native_product_data($request) {
        if (!function_exists('wc_get_product')) {
            return new WP_Error('ark_wc_missing', 'WooCommerce is required.', ['status' => 503]);
        }

        $product_id = absint($request['id']);
        $product = wc_get_product($product_id);
        if (!$product || $product->get_status() !== 'publish') {
            return new WP_Error('ark_product_not_found', 'Product was not found.', ['status' => 404]);
        }

        $terms = wp_get_post_terms($product_id, 'product_cat');
        $categories = [];
        $category_ids = [];
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $category_ids[] = (int) $term->term_id;
                $categories[] = ['id' => (int) $term->term_id, 'name' => $term->name, 'slug' => $term->slug];
            }
        }

        $campaigns = get_posts([
            'post_type' => 'ark_promo_campaign',
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'orderby' => ['menu_order' => 'ASC', 'date' => 'DESC'],
            'no_found_rows' => true,
            'meta_query' => [['key' => '_ark_promo_enabled', 'value' => '1']],
        ]);

        $promos = [];
        $now = current_datetime();
        foreach ($campaigns as $campaign) {
            $start = (string) get_post_meta($campaign->ID, '_ark_promo_start', true);
            $end = (string) get_post_meta($campaign->ID, '_ark_promo_end', true);
            if ($start !== '') {
                $start_date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $start, wp_timezone());
                if ($start_date && $now < $start_date) continue;
            }
            if ($end !== '') {
                $end_date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $end, wp_timezone());
                if ($end_date && $now >= $end_date->modify('+1 minute')) continue;
            }

            $target_products = array_map('absint', (array) get_post_meta($campaign->ID, '_ark_promo_target_products', true));
            $target_categories = array_map('absint', (array) get_post_meta($campaign->ID, '_ark_promo_target_categories', true));
            $ids = array_map('absint', (array) get_post_meta($campaign->ID, '_ark_promo_products', true));
            $apply_categories = get_post_meta($campaign->ID, '_ark_promo_apply_categories', true) === '1';

            // Page targeting is deliberately restrictive by default:
            // 1) Explicit target product pages are an exact allow-list.
            // 2) Category targeting is broad only after the admin opts in.
            // 3) Otherwise, the promoted products themselves are the page allow-list.
            if (!empty($target_products)) {
                $matches = in_array($product_id, $target_products, true);
            } elseif ($apply_categories && !empty($target_categories)) {
                $matches = (bool) array_intersect($category_ids, $target_categories);
            } else {
                $matches = in_array($product_id, $ids, true);
            }
            if (!$matches) continue;
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
                    'id' => $promo->get_id(),
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
                $promos[] = [
                    'campaign_id' => (int) $campaign->ID,
                    'heading' => sanitize_text_field(get_post_meta($campaign->ID, '_ark_promo_heading', true) ?: 'Recommended offers'),
                    'speed' => max(0, min(15000, absint(get_post_meta($campaign->ID, '_ark_promo_speed', true) ?: 0))),
                    'products' => $cards,
                ];
            }
        }

        // Prefer the promo plugin's canonical native payload when it is active.
        // The in-bridge campaign query remains as a backward-compatible fallback.
        $promos = apply_filters('ark_native_product_promos', $promos, $product_id);

        return rest_ensure_response([
            'id' => $product_id,
            'sku' => $product->get_sku(),
            'categories' => $categories,
            'promos' => $promos,
        ]);
    }

    public function attach_order($request) {
        if (!function_exists('wc_get_order')) {
            return new WP_Error('ark_wc_missing', 'WooCommerce is required.', ['status' => 503]);
        }

        $user_id = get_current_user_id();
        $user = get_user_by('id', $user_id);
        $order_id = absint($request->get_param('order_id'));
        $order_key = sanitize_text_field((string) $request->get_param('order_key'));

        if (!$user || !$order_id || $order_key === '') {
            return new WP_Error('ark_order_attach_fields', 'Order details are incomplete.', ['status' => 400]);
        }

        $order = wc_get_order($order_id);
        if (!$order || !hash_equals((string) $order->get_order_key(), $order_key)) {
            return new WP_Error('ark_order_attach_invalid', 'Order could not be verified.', ['status' => 404]);
        }

        $account_email = strtolower(trim((string) $user->user_email));
        $billing_email = strtolower(trim((string) $order->get_billing_email()));
        if ($account_email === '' || !hash_equals($account_email, $billing_email)) {
            return new WP_Error('ark_order_attach_email', 'Checkout email must match your signed-in account email so this order appears in My Orders.', ['status' => 403]);
        }

        $existing_customer = (int) $order->get_customer_id();
        if ($existing_customer !== 0 && $existing_customer !== $user_id) {
            return new WP_Error('ark_order_attach_owner', 'This order is already linked to another customer account.', ['status' => 403]);
        }

        if ($existing_customer === 0) {
            $order->set_customer_id($user_id);
            $order->save();
        }

        return rest_ensure_response([
            'success' => true,
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'customer_id' => $user_id,
        ]);
    }

    private function order_money($amount, $currency) {
        $formatted = wc_price((float) $amount, ['currency' => $currency]);
        return trim(html_entity_decode(wp_strip_all_tags($formatted), ENT_QUOTES, 'UTF-8'));
    }

    private function order_payload($order, $include_details = false) {
        $items = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $items[] = [
                'product_id' => $product ? $product->get_id() : 0,
                'name' => $item->get_name(),
                'quantity' => (int) $item->get_quantity(),
                'sku' => $product ? $product->get_sku() : '',
                'unit_price' => $this->order_money($item->get_subtotal() / max(1, $item->get_quantity()), $order->get_currency()),
                'line_total' => $this->order_money($item->get_total(), $order->get_currency()),
                'total' => (float) $item->get_total(),
                'image' => $product ? wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail') : '',
            ];
        }

        $date = $order->get_date_created();
        $modified = $order->get_date_modified();
        $status = $order->get_status();
        // Customer cancellation remains available until fulfilment is shipped.
        // Paid orders can be cancelled before shipment, but any captured payment
        // is flagged for manual store refund review (no automatic refund is sent).
        $can_cancel = in_array($status, ['pending', 'on-hold', 'processing'], true)
            && !in_array($status, ['shipped', 'completed', 'cancelled', 'refunded', 'failed'], true);

        $payload = [
            'id' => $order->get_id(),
            'number' => $order->get_order_number(),
            'status' => wc_get_order_status_name($status),
            'status_label' => wc_get_order_status_name($status),
            'status_key' => $status,
            'date' => $date ? $date->date(DATE_ATOM) : '',
            'date_created' => $date ? $date->date(DATE_ATOM) : '',
            'date_modified' => $modified ? $modified->date(DATE_ATOM) : '',
            'payment_status' => $order->is_paid() ? 'Paid' : (in_array($status, ['pending', 'on-hold'], true) ? 'Awaiting verification' : ($status === 'refunded' ? 'Refunded' : 'Unpaid')),
            'return_request_status' => (string) $order->get_meta('_ark_return_request_status', true),
            'refund_review_required' => $order->get_meta('_ark_refund_review_required', true) === 'yes',
            'total' => $this->order_money($order->get_total(), $order->get_currency()),
            'total_value' => (float) $order->get_total(),
            'subtotal' => $this->order_money($order->get_subtotal(), $order->get_currency()),
            'shipping_total' => $this->order_money($order->get_shipping_total(), $order->get_currency()),
            'discount_total' => $this->order_money($order->get_discount_total(), $order->get_currency()),
            'tax_total' => $this->order_money($order->get_total_tax(), $order->get_currency()),
            'currency' => $order->get_currency(),
            'payment_method' => $order->get_payment_method_title(),
            'can_cancel' => $can_cancel,
            'items' => $items,
        ];

        if ($include_details) {
            $billing = $order->get_address('billing');
            $shipping = $order->get_address('shipping');
            $payload['billing'] = [
                'name' => trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? '')),
                'phone' => $billing['phone'] ?? '',
                'email' => $billing['email'] ?? '',
                'address' => implode(', ', array_filter([
                    $billing['address_1'] ?? '',
                    $billing['address_2'] ?? '',
                    $billing['city'] ?? '',
                    $billing['state'] ?? '',
                    $billing['postcode'] ?? '',
                    $billing['country'] ?? '',
                ])),
            ];
            $payload['shipping'] = [
                'name' => trim(($shipping['first_name'] ?? '') . ' ' . ($shipping['last_name'] ?? '')),
                'first_name' => $shipping['first_name'] ?? '',
                'last_name' => $shipping['last_name'] ?? '',
                'address_1' => $shipping['address_1'] ?? '',
                'address_2' => $shipping['address_2'] ?? '',
                'city' => $shipping['city'] ?? '',
                'state' => $shipping['state'] ?? '',
                'postcode' => $shipping['postcode'] ?? '',
                'country' => $shipping['country'] ?? '',
                'phone' => $shipping['phone'] ?? '',
                'address' => implode(', ', array_filter([
                    $shipping['address_1'] ?? '',
                    $shipping['address_2'] ?? '',
                    $shipping['city'] ?? '',
                    $shipping['state'] ?? '',
                    $shipping['postcode'] ?? '',
                    $shipping['country'] ?? '',
                ])),
            ];
            $payload['customer_note'] = $order->get_customer_note();
            // Include custom WooCommerce fulfilment statuses in the same ordered
            // customer timeline used by the native app.
            $confirmed_done = in_array($status, ['confirmed', 'processing', 'shipped', 'out-of-delivery', 'completed'], true);
            $processing_done = in_array($status, ['processing', 'shipped', 'out-of-delivery', 'completed'], true);
            $shipped_done = in_array($status, ['shipped', 'out-of-delivery', 'completed'], true);
            $out_for_delivery_done = in_array($status, ['out-of-delivery', 'completed'], true);
            $status_times = [];
            if (function_exists('wc_get_order_notes')) {
                $notes = wc_get_order_notes(['order_id' => $order->get_id(), 'type' => 'internal', 'limit' => 100]);
                foreach ($notes as $note) {
                    $content = wp_strip_all_tags((string) ($note->content ?? ''));
                    if (!preg_match('/status changed from .+? to (.+?)(?:[.]|$)/i', $content, $matches)) {
                        continue;
                    }
                    $slug = sanitize_title(trim($matches[1]));
                    $key = [
                        'pending-payment' => 'payment',
                        'on-hold' => 'payment',
                        'confirmed' => 'confirmed',
                        'processing' => 'processing',
                        'shipped' => 'shipped',
                        'out-of-delivery' => 'out-for-delivery',
                        'out-for-delivery' => 'out-for-delivery',
                        'completed' => 'delivered',
                        'cancelled' => 'cancelled',
                        'canceled' => 'cancelled',
                        'failed' => 'failed',
                        'refunded' => 'refunded',
                    ][$slug] ?? '';
                    if ($key !== '') {
                        $note_date = $note->date_created ?? '';
                        if (is_object($note_date) && method_exists($note_date, 'date')) {
                            $note_date = $note_date->date(DATE_ATOM);
                        }
                        if (is_string($note_date) && $note_date !== '') {
                            $status_times[$key] = $note_date;
                        }
                    }
                }
            }
            $created_at = $date ? $date->date(DATE_ATOM) : '';
            $modified_at = $modified ? $modified->date(DATE_ATOM) : '';
            $payload['timeline'] = [
                ['key' => 'placed', 'label' => 'Order placed', 'done' => true, 'active' => false, 'date' => $created_at],
                ['key' => 'confirmed', 'label' => 'Confirmed', 'done' => $confirmed_done, 'active' => $status === 'confirmed', 'date' => $status_times['confirmed'] ?? ''],
                ['key' => 'processing', 'label' => 'Processing', 'done' => $processing_done, 'active' => $status === 'processing', 'date' => $status_times['processing'] ?? ''],
                ['key' => 'shipped', 'label' => 'Shipped', 'done' => $shipped_done, 'active' => $status === 'shipped', 'date' => $status_times['shipped'] ?? ''],
                ['key' => 'out-for-delivery', 'label' => 'Out for delivery', 'done' => $out_for_delivery_done, 'active' => $status === 'out-of-delivery', 'date' => $status_times['out-for-delivery'] ?? ($status === 'out-of-delivery' ? $modified_at : '')],
                ['key' => 'delivered', 'label' => 'Delivered', 'done' => $status === 'completed', 'active' => false, 'date' => $status_times['delivered'] ?? ($status === 'completed' ? $modified_at : '')],
            ];
            if ($status === 'cancelled' || $status === 'refunded' || $status === 'failed') {
                $terminal_key = $status === 'cancelled' ? 'cancelled' : $status;
                $payload['timeline'] = [
                    ['key' => 'placed', 'label' => 'Order placed', 'done' => true, 'active' => false, 'date' => $created_at],
                    ['key' => $terminal_key, 'label' => wc_get_order_status_name($status), 'done' => true, 'active' => true, 'date' => $status_times[$terminal_key] ?? $modified_at],
                ];
            }
        }

        return $payload;
    }

    public function orders($request) {
        if (!function_exists('wc_get_orders')) {
            return new WP_Error('ark_wc_missing', 'WooCommerce is required.', ['status' => 503]);
        }

        $user_id = get_current_user_id();
        $user = get_user_by('id', $user_id);
        $limit = min(50, max(1, absint($request->get_param('per_page') ?: 30)));
        $orders_by_id = [];

        $customer_orders = wc_get_orders([
            'customer_id' => $user_id,
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects',
        ]);
        foreach ($customer_orders as $order) {
            $orders_by_id[$order->get_id()] = $order;
        }

        if ($user && is_email($user->user_email)) {
            try {
                $email_orders = wc_get_orders([
                    'billing_email' => $user->user_email,
                    'limit' => $limit,
                    'orderby' => 'date',
                    'order' => 'DESC',
                    'return' => 'objects',
                ]);
                foreach ($email_orders as $order) {
                    $owner = (int) $order->get_customer_id();
                    if ($owner !== 0 && $owner !== $user_id) continue;
                    if ($owner === 0) {
                        $order->set_customer_id($user_id);
                        $order->save();
                    }
                    $orders_by_id[$order->get_id()] = $order;
                }
            } catch (Throwable $e) {
                // Orders already linked to the signed-in user remain available.
            }
        }

        $orders = array_values($orders_by_id);
        usort($orders, function ($a, $b) {
            $a_date = $a->get_date_created();
            $b_date = $b->get_date_created();
            return ($b_date ? $b_date->getTimestamp() : 0) <=> ($a_date ? $a_date->getTimestamp() : 0);
        });
        $orders = array_slice($orders, 0, $limit);

        $result = [];
        foreach ($orders as $order) {
            $result[] = $this->order_payload($order, false);
        }

        return rest_ensure_response(['orders' => $result]);
    }

    public function order_detail($request) {
        $order = wc_get_order(absint($request['id']));
        if (!$order) {
            return new WP_Error('ark_order_not_found', 'Order was not found.', ['status' => 404]);
        }

        $user_id = get_current_user_id();
        if ((int) $order->get_customer_id() !== $user_id) {
            $user = get_user_by('id', $user_id);
            $billing_email = strtolower(trim((string) $order->get_billing_email()));
            $account_email = strtolower(trim((string) ($user ? $user->user_email : '')));
            if (!$user || $billing_email === '' || !hash_equals($account_email, $billing_email)) {
                return new WP_Error('ark_order_forbidden', 'You cannot view this order.', ['status' => 403]);
            }
            if ((int) $order->get_customer_id() === 0) {
                $order->set_customer_id($user_id);
                $order->save();
            } elseif ((int) $order->get_customer_id() !== $user_id) {
                return new WP_Error('ark_order_forbidden', 'You cannot view this order.', ['status' => 403]);
            }
        }

        return rest_ensure_response(['order' => $this->order_payload($order, true)]);
    }

    public function cancel_order($request) {
        $order = wc_get_order(absint($request['id']));
        if (!$order) {
            return new WP_Error('ark_order_not_found', 'Order was not found.', ['status' => 404]);
        }

        $user_id = get_current_user_id();
        if ((int) $order->get_customer_id() !== $user_id) {
            return new WP_Error('ark_order_forbidden', 'You cannot cancel this order.', ['status' => 403]);
        }

        $status = $order->get_status();
        $reason = sanitize_textarea_field((string) $request->get_param('reason'));
        if (!in_array($status, ['pending', 'on-hold', 'processing'], true)
            || in_array($status, ['shipped', 'completed', 'cancelled', 'refunded', 'failed'], true)) {
            return new WP_Error(
                'ark_order_cancel_blocked',
                'This order can no longer be cancelled from the app because it has shipped or reached a final status.',
                ['status' => 409]
            );
        }

        $refund_review_required = $order->is_paid();
        $note = $refund_review_required
            ? 'Customer cancelled before shipment from the ARK Native App. Payment was captured; the store must review and process any refund manually.'
            : 'Cancelled by customer from the ARK Native App before shipment.';
        if ($reason !== '') {
            $order->update_meta_data('_ark_customer_cancel_reason', $reason);
            $note .= ' Customer reason: ' . $reason;
        }
        if ($refund_review_required) {
            $order->update_meta_data('_ark_refund_review_required', 'yes');
            $order->save();
        }
        $order->update_status('cancelled', $note);
        return rest_ensure_response([
            'success' => true,
            'order' => $this->order_payload($order, true),
            'refund_review_required' => $refund_review_required,
            'message' => $refund_review_required
                ? 'Your order was cancelled. The store must review the captured payment and process any refund separately.'
                : 'Your order has been cancelled.',
        ]);
    }


    public function customer_login_compat($request) {
        $data = $request->get_json_params();
        $login = sanitize_text_field((string) ($data['email_or_username'] ?? $data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        if ($login === '' || $password === '') {
            return new WP_Error('ark_login_fields', 'Enter your email or username and password.', ['status' => 400]);
        }
        if (is_email($login)) {
            $found = get_user_by('email', $login);
            if ($found) $login = $found->user_login;
        }
        $user = wp_authenticate($login, $password);
        if (is_wp_error($user)) {
            return new WP_Error('ark_login_failed', 'Incorrect username/email or password.', ['status' => 401]);
        }
        $token = $this->issue_token($user->ID);
        return rest_ensure_response([
            'success' => true,
            'session' => ['token' => $token, 'expires_in' => self::TOKEN_TTL],
            'customer' => ['id' => (int) $user->ID, 'email' => $user->user_email, 'name' => $user->display_name],
            'token' => $token,
            'user' => $this->user_payload($user),
        ]);
    }

    public function customer_logout_compat($request) {
        // JWTs are short-lived and stateless; client removes its local token.
        return rest_ensure_response(['logged_out' => true]);
    }

    public function customer_profile_compat($request) {
        $user_id = get_current_user_id();
        if ($request->get_method() === 'PUT') {
            $data = $request->get_json_params();
            $user_data = ['ID' => $user_id];
            if (isset($data['display_name'])) $user_data['display_name'] = sanitize_text_field($data['display_name']);
            if (isset($data['email'])) {
                $email = sanitize_email($data['email']);
                if (!is_email($email)) return new WP_Error('ark_invalid_email', 'Enter a valid email address.', ['status' => 400]);
                $existing = email_exists($email);
                if ($existing && (int) $existing !== $user_id) return new WP_Error('ark_email_exists', 'This email is already used by another account.', ['status' => 409]);
                $user_data['user_email'] = $email;
            }
            $updated = wp_update_user($user_data);
            if (is_wp_error($updated)) return $updated;
            foreach (['first_name' => 'first_name', 'last_name' => 'last_name', 'billing_phone' => 'billing_phone'] as $key => $meta) {
                if (isset($data[$key])) update_user_meta($user_id, $meta, sanitize_text_field($data[$key]));
            }
        }
        $user = get_user_by('id', $user_id);
        $customer = function_exists('WC') && WC() ? new WC_Customer($user_id) : null;
        return rest_ensure_response([
            'id' => $user_id,
            'email' => $user ? $user->user_email : '',
            'display_name' => $user ? $user->display_name : '',
            'first_name' => get_user_meta($user_id, 'first_name', true),
            'last_name' => get_user_meta($user_id, 'last_name', true),
            'billing' => $customer ? $customer->get_billing() : [],
            'shipping' => $customer ? $customer->get_shipping() : [],
        ]);
    }

    public function customer_orders_compat($request) {
        $response = $this->orders($request);
        if (is_wp_error($response)) return $response;
        $data = $response instanceof WP_REST_Response ? $response->get_data() : $response;
        return rest_ensure_response(is_array($data) ? ($data['orders'] ?? []) : []);
    }

    public function customer_order_detail_compat($request) {
        $response = $this->order_detail($request);
        if (is_wp_error($response)) return $response;
        $data = $response instanceof WP_REST_Response ? $response->get_data() : $response;
        return rest_ensure_response(is_array($data) ? ($data['order'] ?? $data) : []);
    }

    public function customer_downloads_compat($request) {
        $response = $this->account_downloads($request);
        if (is_wp_error($response)) return $response;
        $data = $response instanceof WP_REST_Response ? $response->get_data() : $response;
        return rest_ensure_response(is_array($data) ? ($data['downloads'] ?? []) : []);
    }

    public function customer_addresses_compat($request) {
        $user_id = get_current_user_id();
        $addresses = get_user_meta($user_id, '_ark_native_saved_addresses', true);
        $addresses = is_array($addresses) ? array_values($addresses) : [];
        $method = $request->get_method();

        if ($method === 'POST') {
            $data = $request->get_json_params();
            $data = is_array($data) ? $data : [];
            $address = [];
            foreach (['first_name','last_name','company','address_1','address_2','city','state','postcode','country','email','phone'] as $key) {
                $address[$key] = sanitize_text_field((string) ($data[$key] ?? ''));
            }
            $address['label'] = sanitize_text_field((string) ($data['label'] ?? 'Home'));
            if ($address['address_1'] === '' || $address['city'] === '' || $address['postcode'] === '' || $address['country'] === '') {
                return new WP_Error('ark_address_incomplete', 'Enter street, city, PIN code and country.', ['status' => 400]);
            }
            $id = sanitize_text_field((string) ($data['id'] ?? ''));
            $matched = false;
            foreach ($addresses as &$existing) {
                if (($id !== '' && ($existing['id'] ?? '') === $id) ||
                    (($existing['address_1'] ?? '') === $address['address_1'] && ($existing['postcode'] ?? '') === $address['postcode'] && ($existing['first_name'] ?? '') === $address['first_name'])) {
                    $address['id'] = $existing['id'] ?? wp_generate_uuid4();
                    $existing = $address;
                    $matched = true;
                    break;
                }
            }
            unset($existing);
            if (!$matched) {
                // Preserve the legacy WooCommerce default-address identity when
                // a customer edits the address that was hydrated from their profile.
                $address['id'] = ($id === 'default') ? 'default' : wp_generate_uuid4();
                $addresses[] = $address;
            }
            update_user_meta($user_id, '_ark_native_saved_addresses', $addresses);

            // Keep WooCommerce's default billing/shipping profile in sync with
            // the most recently saved address for subsequent native checkouts.
            if (function_exists('WC') && WC()) {
                $customer = new WC_Customer($user_id);
                foreach ($address as $key => $value) {
                    if (in_array($key, ['label','id'], true)) continue;
                    if (method_exists($customer, 'set_shipping_' . $key)) $customer->{'set_shipping_' . $key}($value);
                    if (in_array($key, ['first_name','last_name','company','address_1','address_2','city','state','postcode','country','email','phone'], true)
                        && method_exists($customer, 'set_billing_' . $key)) $customer->{'set_billing_' . $key}($value);
                }
                $customer->save();
            }
            return rest_ensure_response($addresses);
        }

        if ($method === 'DELETE') {
            $id = sanitize_text_field((string) $request->get_param('id'));
            if ($id === '') {
                return new WP_Error('ark_address_id_required', 'Address ID is required.', ['status' => 400]);
            }

            $addresses = array_values(array_filter($addresses, function ($item) use ($id) {
                return (string) ($item['id'] ?? '') !== $id;
            }));

            // Persist an empty array too. Without this, deleting the final
            // saved address would immediately re-add the WooCommerce profile
            // address on the next GET, making Delete appear broken in the app.
            update_user_meta($user_id, '_ark_native_saved_addresses', $addresses);

            // Keep the WooCommerce shipping profile aligned with the saved
            // address list after a deletion. Billing remains intact for invoices.
            if (function_exists('WC') && WC()) {
                $customer = new WC_Customer($user_id);
                $replacement = !empty($addresses) ? end($addresses) : null;
                foreach (['first_name','last_name','company','address_1','address_2','city','state','postcode','country','phone'] as $field) {
                    $value = is_array($replacement) ? (string) ($replacement[$field] ?? '') : '';
                    $setter = 'set_shipping_' . $field;
                    if (method_exists($customer, $setter)) {
                        $customer->{$setter}($value);
                    }
                }
                $customer->save();
            }
        }

        $has_saved_address_meta = metadata_exists('user', $user_id, '_ark_native_saved_addresses');
        if (!$addresses && !$has_saved_address_meta && function_exists('WC') && WC()) {
            $customer = new WC_Customer($user_id);
            $shipping = $customer->get_shipping();
            if (!empty($shipping['address_1'])) {
                $shipping['label'] = 'Default';
                $shipping['id'] = 'default';
                $addresses[] = $shipping;
            }
        }
        return rest_ensure_response($addresses);
    }

    public function customer_return_request($request) {
        $order = wc_get_order(absint($request['id']));
        if (!$order) return new WP_Error('ark_order_not_found', 'Order was not found.', ['status' => 404]);
        if ((int) $order->get_customer_id() !== get_current_user_id()) {
            return new WP_Error('ark_order_forbidden', 'You cannot request a return for this order.', ['status' => 403]);
        }
        if ($order->get_status() !== 'completed') {
            return new WP_Error('ark_return_not_eligible', 'A return request is available after the order is delivered.', ['status' => 409]);
        }
        if ($order->get_meta('_ark_return_request_status', true) === 'pending') {
            return new WP_Error('ark_return_duplicate', 'A return request is already awaiting review.', ['status' => 409]);
        }
        $reason = sanitize_textarea_field((string) $request->get_param('reason'));
        if ((function_exists('mb_strlen') ? mb_strlen($reason) : strlen($reason)) < 5) return new WP_Error('ark_return_reason', 'Please enter a return reason (at least 5 characters).', ['status' => 400]);
        $order->update_meta_data('_ark_return_request_status', 'pending');
        $order->update_meta_data('_ark_return_request_reason', $reason);
        $order->add_order_note('Customer requested a return: ' . $reason, false);
        $order->save();
        return rest_ensure_response([
            'success' => true,
            'return_request_status' => 'pending',
            'message' => 'Return request sent to the store for review. The store will confirm eligibility and refund options.',
        ]);
    }

    private function wishlist_ids($user_id) {
        $ids = (array) get_user_meta($user_id, self::WISHLIST_META, true);
        $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
        $ids = array_values(array_filter($ids, function ($id) {
            return get_post_type($id) === 'product' && get_post_status($id) === 'publish';
        }));
        return $ids;
    }

    public function wishlist($request) {
        return rest_ensure_response(['ids' => $this->wishlist_ids(get_current_user_id())]);
    }

    public function wishlist_toggle($request) {
        $product_id = absint($request->get_param('product_id'));
        if (!$product_id || get_post_type($product_id) !== 'product' || get_post_status($product_id) !== 'publish') {
            return new WP_Error('ark_product_invalid', 'Product was not found.', ['status' => 404]);
        }

        $user_id = get_current_user_id();
        $ids = $this->wishlist_ids($user_id);
        $added = !in_array($product_id, $ids, true);
        $ids = $added ? array_merge([$product_id], $ids) : array_values(array_diff($ids, [$product_id]));
        update_user_meta($user_id, self::WISHLIST_META, $ids);

        return rest_ensure_response(['ids' => $ids, 'added' => $added]);
    }

    /**
     * Public checkout configuration. UPI VPA is a merchant payment address,
     * not a password; never expose API keys or private gateway settings here.
     */
    public function payment_config($request) {
        if (!function_exists('WC') || !WC()) {
            return new WP_Error('ark_wc_missing', 'WooCommerce is not available.', ['status' => 503]);
        }

        $settings = get_option('woocommerce_wc-upi_settings', []);
        $settings = is_array($settings) ? $settings : [];
        $gateways = [];
        $available = WC()->payment_gateways->get_available_payment_gateways();

        foreach ($available as $id => $gateway) {
            $gateways[] = [
                'id' => (string) $id,
                'title' => (string) $gateway->get_title(),
                'description' => wp_strip_all_tags((string) $gateway->get_description()),
            ];
        }

        // COD can be omitted by get_available_payment_gateways() on a fresh
        // REST request before WooCommerce has hydrated the customer/session.
        // Use the saved gateway enable flag as a native selector fallback;
        // checkout still validates actual gateway availability when the order is placed.
        $cod_settings = get_option('woocommerce_cod_settings', []);
        $cod_settings = is_array($cod_settings) ? $cod_settings : [];
        $cod_enabled = ($cod_settings['enabled'] ?? 'no') === 'yes';
        $cod_available = false;
        foreach ($gateways as $gateway) {
            if ($gateway['id'] === 'cod') $cod_available = true;
        }
        if ($cod_enabled && !$cod_available) {
            $gateways[] = [
                'id' => 'cod',
                'title' => sanitize_text_field($cod_settings['title'] ?? 'Cash on Delivery'),
                'description' => wp_strip_all_tags((string) ($cod_settings['description'] ?? 'Pay when your order is delivered.')),
            ];
        }

        $upi_enabled = ($settings['enabled'] ?? 'no') === 'yes' && !empty($settings['vpa']) && ($settings['hide_on_mobile'] ?? 'no') !== 'yes' && get_woocommerce_currency() === 'INR';
        $upi_available = false;
        foreach ($gateways as $gateway) {
            if ($gateway['id'] === 'wc-upi') $upi_available = true;
        }
        // On a fresh REST request there may be no hydrated Woo session; the
        // gateway settings are authoritative for this app's payment selector.
        if ($upi_enabled && !$upi_available) {
            $gateways[] = [
                'id' => 'wc-upi',
                'title' => sanitize_text_field($settings['title'] ?? 'Pay with UPI QR Code'),
                'description' => wp_strip_all_tags((string) ($settings['description'] ?? '')),
            ];
            $upi_available = true;
        }

        return rest_ensure_response([
            'gateways' => $gateways,
            'upi' => [
                'available' => $upi_available && !empty($settings['vpa']),
                'id' => 'wc-upi',
                'title' => sanitize_text_field($settings['title'] ?? 'Pay with UPI QR Code'),
                'description' => sanitize_text_field($settings['description'] ?? ''),
                'merchant_vpa' => sanitize_text_field($settings['vpa'] ?? ''),
                'merchant_name' => sanitize_text_field($settings['name'] ?? get_bloginfo('name')),
                'upi_address_mode' => sanitize_key($settings['upi_address'] ?? 'show_handle'),
                'require_upi' => ($settings['require_upi'] ?? 'yes') === 'yes',
                'transaction_id_mode' => sanitize_key($settings['transaction_id'] ?? 'show_require'),
                'transaction_image_mode' => sanitize_key($settings['transaction_image'] ?? 'show_require'),
                'instructions' => sanitize_textarea_field($settings['instructions_mobile'] ?? $settings['instructions'] ?? ''),
                'payment_status' => sanitize_key($settings['payment_status'] ?? 'on-hold'),
                'upi_handles' => ['oksbi','okaxis','okhdfcbank','okicici','ybl','ibl','paytm','axl','upi','apl','airtel','barodampay','federal','hdfcbank','icici','sbi','yesbank','axisbank','kotak','indus'],
            ],
        ]);
    }

    /**
     * Submit the UTR and private screenshot for an order created using the
     * WooCommerce Store API UPI Blocks payment method. Order key is verified.
     */
    public function submit_upi_payment($request) {
        if (!function_exists('wc_get_order')) {
            return new WP_Error('ark_wc_missing', 'WooCommerce is not available.', ['status' => 503]);
        }

        $order_id = absint($request->get_param('order_id'));
        $order_key = sanitize_text_field((string) $request->get_param('order_key'));
        $utr = preg_replace('/\D+/', '', (string) $request->get_param('transaction_id'));

        if (!$order_id || $order_key === '') {
            return new WP_Error('ark_upi_fields', 'Order and order key are required.', ['status' => 400]);
        }

        $settings = get_option('woocommerce_wc-upi_settings', []);
        $settings = is_array($settings) ? $settings : [];
        $utr_mode = $settings['transaction_id'] ?? 'show_require';
        if ($utr_mode === 'show_require' && !preg_match('/^\d{12}$/', $utr)) {
            return new WP_Error('ark_upi_utr', 'Enter the 12-digit UPI Transaction / UTR ID.', ['status' => 400]);
        }
        if ($utr_mode !== 'hide' && $utr !== '' && !preg_match('/^\d{12}$/', $utr)) {
            return new WP_Error('ark_upi_utr', 'UPI Transaction / UTR ID must contain 12 digits.', ['status' => 400]);
        }

        $order = wc_get_order($order_id);
        if (!$order || !hash_equals((string) $order->get_order_key(), $order_key)) {
            return new WP_Error('ark_upi_order', 'Order could not be verified.', ['status' => 404]);
        }
        if ($order->get_payment_method() !== 'wc-upi') {
            return new WP_Error('ark_upi_method', 'This order does not use the UPI QR payment method.', ['status' => 400]);
        }
        // UPI gateways commonly move a newly placed QR order to On Hold before
        // the customer uploads proof. needs_payment() is false for On Hold, so
        // validate the actual order state and paid flag instead.
        if ($order->is_paid() || !in_array($order->get_status(), ['pending', 'on-hold'], true)) {
            return new WP_Error('ark_upi_status', 'This UPI order is already paid or is no longer awaiting payment proof.', ['status' => 409]);
        }

        if ($utr !== '') {
            $existing = wc_get_orders([
                'transaction_id' => $utr,
                'limit' => 1,
                'return' => 'ids',
            ]);
            if (!empty($existing) && (int) $existing[0] !== $order_id) {
                return new WP_Error('ark_upi_duplicate', 'This UTR has already been submitted for another order.', ['status' => 409]);
            }
        }

        $image_mode = $settings['transaction_image'] ?? 'show_require';
        $image_required = $image_mode === 'show_require';
        $files = $request->get_file_params();
        $screenshot = $files['screenshot'] ?? null;

        if ($image_required && (!$screenshot || (int) ($screenshot['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)) {
            return new WP_Error('ark_upi_screenshot_required', 'Payment screenshot is required by the store. Upload a JPG or PNG screenshot.', ['status' => 400]);
        }

        if ($screenshot && (int) ($screenshot['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            if (!class_exists('UPIWC_Private_Screenshots') ||
                !method_exists('UPIWC_Private_Screenshots', 'handle_payment_screenshot')) {
                return new WP_Error('ark_upi_screenshot_service', 'The UPI plugin private screenshot service is not available. Update the UPI QR Code Payment Gateway plugin.', ['status' => 503]);
            }

            // The UPI plugin's own validator stores screenshots outside Media Library.
            $original_files = $_FILES;
            $_FILES['upiwc_file'] = $screenshot;
            UPIWC_Private_Screenshots::handle_payment_screenshot($order);
            $_FILES = $original_files;

            if ($image_required && !$order->get_meta(UPIWC_Private_Screenshots::META_FILE, true)) {
                return new WP_Error('ark_upi_screenshot_failed', 'Screenshot could not be stored securely. Please upload a JPG or PNG image up to 5 MB.', ['status' => 400]);
            }
        }

        if ($utr !== '') {
            $order->set_transaction_id($utr);
        }
        // A customer-entered UTR is only a claim, not proof of a settled payment.
        // Keep the gateway's paid flag explicitly false until an administrator
        // verifies the transaction in WooCommerce.
        $order->update_meta_data('_upiwc_order_paid', 'no');
        $order->update_meta_data('_ark_upi_verification_status', 'pending');
        $order->add_order_note(
            'UPI UTR submitted from the ARK native app: ' . $utr . '. Payment is awaiting manual store-owner verification.',
            false
        );
        // Never honor the gateway's optional Processing/Completed setting here:
        // the native submission endpoint cannot independently verify bank payment.
        if (!$order->has_status(['pending', 'on-hold'])) {
            $order->update_status('on-hold');
        } elseif ($order->has_status('pending')) {
            $order->update_status('on-hold');
        }
        $order->save();

        return rest_ensure_response([
            'success' => true,
            'order_id' => $order_id,
            'order_number' => $order->get_order_number(),
            'status' => $order->get_status(),
            'status_label' => wc_get_order_status_name($order->get_status()),
            'message' => 'UPI details submitted. Payment is awaiting store-owner verification.',
        ]);
    }
}

add_action('plugins_loaded', function () {
    if (class_exists('WooCommerce')) {
        new ARK_Native_API_Bridge();
    }
});