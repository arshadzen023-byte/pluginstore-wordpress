
<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Normalize Indian mobile numbers to 10 digits.
 */
function ark_otp_normalize_phone($phone) {
    $digits = preg_replace('/\D+/', '', (string) $phone);

    if (strlen($digits) === 12 && substr($digits, 0, 2) === '91') {
        $digits = substr($digits, 2);
    }

    if (strlen($digits) !== 10 || $digits[0] < '6' || $digits[0] > '9') {
        return false;
    }

    return $digits;
}

function ark_otp_transient_key($phone) {
    return 'ark_otp_' . hash_hmac('sha256', $phone, wp_salt('auth'));
}

/**
 * Send OTP through the configured IndiaHost Request URL.
 */
function ark_otp_send_ajax() {
    check_ajax_referer('ark_otp_login', 'nonce');

    if (!get_theme_mod('ark_otp_enabled', 0)) {
        wp_send_json_error(array(
            'message' => 'Mobile OTP login is currently disabled.'
        ), 403);
    }

    $phone = ark_otp_normalize_phone(
        isset($_POST['phone']) ? wp_unslash($_POST['phone']) : ''
    );

    if (!$phone) {
        wp_send_json_error(array(
            'message' => 'Please enter a valid 10-digit Indian mobile number.'
        ), 400);
    }

    $key = ark_otp_transient_key($phone);

    // One request per phone every 60 seconds.
    if (get_transient($key . '_cooldown')) {
        wp_send_json_error(array(
            'message' => 'Please wait 60 seconds before requesting another OTP.'
        ), 429);
    }

    $url   = trim((string) get_theme_mod(
        'ark_otp_api_url',
        'https://otp.indiahost.org/send_otp.php'
    ));
    $api_key = trim((string) get_theme_mod('ark_otp_api_key', ''));
    $email   = trim((string) get_theme_mod('ark_otp_sender_id', ''));

    if (
        !$url ||
        !wp_http_validate_url($url) ||
        strtolower((string) wp_parse_url($url, PHP_URL_SCHEME)) !== 'https' ||
        !$api_key ||
        !is_email($email)
    ) {
        wp_send_json_error(array(
            'message' => 'Please check the Request URL, API Key and IndiaHost account email in Customizer.'
        ), 500);
    }

    $otp = (string) random_int(100000, 999999);

    // Temporarily block repeated requests while contacting the provider.
    set_transient($key . '_cooldown', 1, 60);

    $request_url = add_query_arg(
    array(
        'mobile' => '+91' . $phone,
        'otp'    => $otp,
        'user'   => $email,
        'key'    => $api_key,
    ),
    $url
);

$response = wp_safe_remote_get($request_url, array(
    'timeout'     => 15,
    'redirection' => 0,
    'sslverify'   => true,
    'headers'     => array(
        'Accept' => 'application/json, text/plain',
    ),
));

    if (is_wp_error($response)) {
        delete_transient($key . '_cooldown');

        wp_send_json_error(array(
            'message' => 'OTP provider could not be reached. Please try again later.'
        ), 502);
    }

    $status = wp_remote_retrieve_response_code($response);
    $body   = trim(wp_remote_retrieve_body($response));

    // IndiaHost may return plain text or JSON.
    $json = json_decode($body, true);
    $provider_message = is_array($json)
        ? implode(' ', array_map('strval', $json))
        : $body;

    $success = (
        $status >= 200 &&
        $status < 300 &&
        preg_match('/\bSUCCESS(?:FULLY)?\b/i', $provider_message) &&
        !preg_match('/\b(FAIL|ERROR|INVALID|LOW BALANCE)\b/i', $provider_message)
    );

    if (!$success) {
        delete_transient($key . '_cooldown');

        // Do not expose provider credentials or the raw provider response.
        wp_send_json_error(array(
            'message' => 'IndiaHost did not confirm OTP delivery. Check your API settings and account balance.'
        ), 502);
    }

    set_transient($key, array(
        'phone'    => $phone,
        'otp_hash' => wp_hash_password($otp),
        'attempts' => 0,
    ), 5 * MINUTE_IN_SECONDS);

    wp_send_json_success(array(
        'message' => 'OTP request sent. Please check your mobile phone.'
    ));
}
add_action('wp_ajax_nopriv_ark_send_otp', 'ark_otp_send_ajax');
add_action('wp_ajax_ark_send_otp', 'ark_otp_send_ajax');


/**
 * Find a WooCommerce customer using their saved billing phone.
 */
function ark_otp_find_customer($phone) {
    $variants = array(
        $phone,
        '+91' . $phone,
        '91' . $phone,
    );

    $ids = get_users(array(
        'role__in'   => array('customer', 'subscriber'),
        'number'     => 1,
        'fields'     => 'ID',
        'meta_query' => array(
            array(
                'key'     => 'billing_phone',
                'value'   => $variants,
                'compare' => 'IN',
            ),
        ),
    ));

    if (!empty($ids)) {
        return (int) $ids[0];
    }

    // Also support accounts created with the phone as their username.
    $user = get_user_by('login', 'customer' . $phone);

    return $user ? (int) $user->ID : 0;
}

/**
 * Verify OTP and sign in or create a WooCommerce customer.
 */
function ark_otp_verify_ajax() {
    check_ajax_referer('ark_otp_login', 'nonce');

    if (!get_theme_mod('ark_otp_enabled', 0)) {
        wp_send_json_error(array(
            'message' => 'Mobile OTP login is currently disabled.'
        ), 403);
    }

    $phone = ark_otp_normalize_phone(
        isset($_POST['phone']) ? wp_unslash($_POST['phone']) : ''
    );

    $otp = isset($_POST['otp'])
        ? preg_replace('/\D+/', '', (string) wp_unslash($_POST['otp']))
        : '';

    if (!$phone || !preg_match('/^\d{6}$/', $otp)) {
        wp_send_json_error(array(
            'message' => 'Enter your valid mobile number and 6-digit OTP.'
        ), 400);
    }

    $key  = ark_otp_transient_key($phone);
    $data = get_transient($key);

    if (
        !is_array($data) ||
        empty($data['otp_hash']) ||
        ($data['phone'] ?? '') !== $phone
    ) {
        wp_send_json_error(array(
            'message' => 'OTP expired or not found. Please request a new OTP.'
        ), 400);
    }

    $attempts = (int) ($data['attempts'] ?? 0);

    if ($attempts >= 5) {
        delete_transient($key);

        wp_send_json_error(array(
            'message' => 'Too many incorrect attempts. Please request a new OTP.'
        ), 429);
    }

    if (!wp_check_password($otp, $data['otp_hash'])) {
        $data['attempts'] = $attempts + 1;
        set_transient($key, $data, 5 * MINUTE_IN_SECONDS);

        wp_send_json_error(array(
            'message' => 'Incorrect OTP. Please try again.'
        ), 400);
    }

    // Invalidate the OTP immediately after successful verification.
    delete_transient($key);
    delete_transient($key . '_cooldown');

    if (!function_exists('wc_get_page_permalink')) {
        wp_send_json_error(array(
            'message' => 'WooCommerce is not active.'
        ), 500);
    }

    
    // Read the email separately from the verified mobile number.
    $email = isset($_POST['email'])
        ? sanitize_email(wp_unslash($_POST['email']))
        : '';

    if (!$email || !is_email($email)) {
        wp_send_json_error(array(
            'message' => 'Please enter a valid email address.'
        ), 400);
    }

    $user_id = ark_otp_find_customer($phone);

    if (!$user_id) {
        // Do not allow the same email to belong to two accounts.
        if (email_exists($email)) {
            wp_send_json_error(array(
                'message' => 'This email is already linked to another account. Please use your registered email.'
            ), 409);
        }

        $username = 'customer' . $phone;

        // Avoid a username collision.
        if (username_exists($username)) {
            $username .= wp_generate_password(4, false, false);
        }

        $user_id = wp_insert_user(array(
            'user_login' => $username,
            'user_pass'  => wp_generate_password(32, true, true),
            'user_email' => $email,
            'role'       => 'customer',
        ));

        if (is_wp_error($user_id)) {
            wp_send_json_error(array(
                'message' => 'Could not create the customer account. Please check the email and try again.'
            ), 500);
        }
    } else {
        // Update only the old auto-generated placeholder email.
        // Do not silently replace a customer's existing real email.
        $user = get_user_by('id', $user_id);

        if (
            $user &&
            strpos(
                strtolower($user->user_email),
                strtolower('phone' . $phone . '@')
            ) === 0
        ) {
            $email_owner = email_exists($email);

            if ($email_owner && (int) $email_owner !== (int) $user_id) {
                wp_send_json_error(array(
                    'message' => 'This email is already linked to another account.'
                ), 409);
            }

            $updated = wp_update_user(array(
                'ID'         => $user_id,
                'user_email' => $email,
            ));

            if (is_wp_error($updated)) {
                wp_send_json_error(array(
                    'message' => 'Could not update the customer email.'
                ), 500);
            }
        }
    }


    $saved_phone = '+91' . $phone;

    update_user_meta($user_id, 'billing_phone', $saved_phone);

    // Log the verified customer into WordPress/WooCommerce.
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true, is_ssl());

    $user = get_user_by('id', $user_id);

    if ($user) {
        do_action('wp_login', $user->user_login, $user);
    }

    wp_send_json_success(array(
        'message'  => 'Login successful.',
        'redirect' => wc_get_page_permalink('myaccount'),
    ));
}
add_action('wp_ajax_nopriv_ark_verify_otp', 'ark_otp_verify_ajax');
add_action('wp_ajax_ark_verify_otp', 'ark_otp_verify_ajax');


/**
 * Display phone numbers in the WordPress Users table.
 */
function ark_otp_user_columns($columns) {
    $columns['ark_customer_phone'] = 'Customer Phone';
    return $columns;
}
add_filter('manage_users_columns', 'ark_otp_user_columns');

function ark_otp_user_column_content($value, $column_name, $user_id) {
    if ($column_name === 'ark_customer_phone') {
        return esc_html(
            (string) get_user_meta($user_id, 'billing_phone', true)
        );
    }

    return $value;
}
add_filter('manage_users_custom_column', 'ark_otp_user_column_content', 10, 3);
