<?php
/**
 * Plugin Name: ARK App Push Notifications
 * Description: Send image notifications and timed sale alerts to registered ARK Native Android app installs, with delivery and click analytics.
 * Version: 1.3.2
 * Author: ARK
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

final class ARK_App_Push_Notifications {
    const VERSION = '1.3.2';
    const OPTION = 'ark_app_push_settings';
    const CRON_HOOK = 'ark_app_push_send_batch';
    const SCHEDULE_HOOK = 'ark_app_push_send_scheduled';

    private $devices;
    private $messages;
    private $clicks;
    private $dismissed;

    public function __construct() {
        global $wpdb;
        $this->devices = $wpdb->prefix . 'ark_push_devices';
        $this->messages = $wpdb->prefix . 'ark_push_messages';
        $this->clicks = $wpdb->prefix . 'ark_push_clicks';
        $this->dismissed = $wpdb->prefix . 'ark_push_dismissed';

        // Upgrade database columns once after plugin update.
        if (get_option('ark_app_push_db_version') !== self::VERSION) {
            self::activate();
        }

        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('admin_post_ark_push_save_settings', [$this, 'save_settings']);
        add_action('admin_post_ark_push_send_campaign', [$this, 'send_campaign']);
        add_action('admin_post_ark_push_cancel_schedule', [$this, 'cancel_scheduled_message']);
        add_action('admin_post_ark_push_send_test', [$this, 'send_test_notification']);
        add_action('admin_post_ark_push_deactivate_device', [$this, 'deactivate_device']);
        add_action('wp_ajax_ark_push_search_products', [$this, 'ajax_search_products']);
        add_action(self::CRON_HOOK, [$this, 'send_batch'], 10, 1);
        add_action(self::SCHEDULE_HOOK, [$this, 'run_scheduled_message'], 10, 1);
        add_action('init', [$this, 'ensure_scheduled_messages'], 30);

        // Customer-specific WooCommerce order notifications.
        add_action('woocommerce_new_order', [$this, 'on_order_created'], 30, 1);
        add_action('woocommerce_update_order', [$this, 'on_order_updated'], 30, 1);
        add_action('woocommerce_order_status_changed', [$this, 'on_order_status_changed'], 30, 4);

        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $devices = $wpdb->prefix . 'ark_push_devices';
        $messages = $wpdb->prefix . 'ark_push_messages';
        $clicks = $wpdb->prefix . 'ark_push_clicks';
        $dismissed = $wpdb->prefix . 'ark_push_dismissed';

        dbDelta("CREATE TABLE {$devices} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            token_hash char(64) NOT NULL,
            token longtext NOT NULL,
            platform varchar(20) NOT NULL DEFAULT 'android',
            app_version varchar(40) NOT NULL DEFAULT '',
            installation_id varchar(64) NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            user_email varchar(190) NOT NULL DEFAULT '',
            account_linked_at datetime NULL,
            active tinyint(1) NOT NULL DEFAULT 1,
            last_seen datetime NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY token_hash (token_hash),
            UNIQUE KEY installation_id (installation_id),
            KEY active_id (active,id),
            KEY user_active (user_id,active)
        ) {$charset};");

        dbDelta("CREATE TABLE {$messages} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(150) NOT NULL,
            body text NOT NULL,
            image_url text NULL,
            click_url text NULL,
            target_type varchar(20) NOT NULL DEFAULT 'none',
            target_id bigint(20) unsigned NOT NULL DEFAULT 0,
            message_type varchar(20) NOT NULL DEFAULT 'campaign',
            recipient_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            countdown_end datetime NULL,
            schedule_type varchar(20) NOT NULL DEFAULT 'now',
            scheduled_at datetime NULL,
            schedule_day tinyint(3) unsigned NOT NULL DEFAULT 0,
            schedule_series_id bigint(20) unsigned NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'queued',
            target_count int(10) unsigned NOT NULL DEFAULT 0,
            success_count int(10) unsigned NOT NULL DEFAULT 0,
            failure_count int(10) unsigned NOT NULL DEFAULT 0,
            click_count int(10) unsigned NOT NULL DEFAULT 0,
            cursor_id bigint(20) unsigned NOT NULL DEFAULT 0,
            last_error text NULL,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            sent_at datetime NULL,
            PRIMARY KEY  (id),
            KEY status_created (status,created_at),
            KEY scheduled_status (schedule_type,status,scheduled_at),
            KEY schedule_series_status (schedule_series_id,status),
            KEY recipient_status (recipient_user_id,status,created_at)
        ) {$charset};");

        dbDelta("CREATE TABLE {$clicks} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            message_id bigint(20) unsigned NOT NULL,
            token_hash char(64) NOT NULL,
            clicked_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY message_device (message_id,token_hash),
            KEY message_id (message_id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$dismissed} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            message_id bigint(20) unsigned NOT NULL,
            token_hash char(64) NOT NULL,
            dismissed_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY message_device (message_id,token_hash),
            KEY token_hash (token_hash)
        ) {$charset};");

        update_option('ark_app_push_db_version', self::VERSION, false);
    }

    private function settings() {
        $value = get_option(self::OPTION, []);
        if (!is_array($value)) return [];

        // Fix the single-character API key typo used by the first GlowHit APK
        // and copied into the original plugin settings. Source of truth:
        // the uploaded google-services.json for com.ark.glowhit.
        $old_glowhit_key = 'AIzaSyDucrtTl8qEBbHCuh-M1s98c7MSnYS4kY';
        $correct_glowhit_key = 'AIzaSyDucrtTl8qEBbHCuh-M1s98c7MSnYS4skY';
        if (
            ($value['project_id'] ?? '') === 'glowhit-10a3c'
            && ($value['api_key'] ?? '') === $old_glowhit_key
        ) {
            $value['api_key'] = $correct_glowhit_key;
            update_option(self::OPTION, $value, false);
        }
        return $value;
    }

    private function firebase_ready($settings = null) {
        $settings = is_array($settings) ? $settings : $this->settings();
        $service = json_decode((string) ($settings['service_account_json'] ?? ''), true);
        $project_id = sanitize_text_field($settings['project_id'] ?? '');
        return !empty($settings['api_key'])
            && !empty($settings['app_id'])
            && !empty($settings['messaging_sender_id'])
            && $project_id !== ''
            && is_array($service)
            && !empty($service['client_email'])
            && !empty($service['private_key'])
            && !empty($service['project_id'])
            && hash_equals($project_id, sanitize_text_field($service['project_id']));
    }

    public function admin_menu() {
        add_menu_page(
            'ARK App Notifications',
            'ARK Notifications',
            'manage_options',
            'ark-app-notifications',
            [$this, 'render_admin'],
            'dashicons-megaphone',
            58
        );
    }

    public function admin_assets($hook) {
        if ($hook !== 'toplevel_page_ark-app-notifications') return;
        wp_enqueue_media();
        $script = <<<'JS'
jQuery(function($) {
    $('#ark-push-pick-image').on('click', function(e) {
        e.preventDefault();
        var frame = wp.media({ title: 'Choose notification image', button: { text: 'Use image' }, multiple: false });
        frame.on('select', function() {
            var item = frame.state().get('selection').first().toJSON();
            $('#ark-push-image-url').val(item.url);
            $('#ark-push-image-preview').attr('src', item.url).show();
        });
        frame.open();
    });
    var type = $('#ark-push-target-type');
    var targetId = $('#ark-push-target-id');
    var productSearch = $('#ark-push-product-search');
    var productResults = $('#ark-push-product-results');
    var selectedProduct = $('#ark-push-selected-product');
    var timer = null;
    function updateTargetFields() {
        var value = type.val();
        $('.ark-push-target-panel').hide();
        if (value === 'product') $('#ark-push-product-panel').show();
        if (value === 'page') $('#ark-push-page-panel').show();
        if (value === 'category') $('#ark-push-category-panel').show();
        if (value === 'external') $('#ark-push-external-panel').show();
        targetId.val('');
        selectedProduct.text('');
    }
    type.on('change', updateTargetFields);
    updateTargetFields();
    $('#ark-push-page-select, #ark-push-category-select').on('change', function() {
        targetId.val($(this).val() || '');
    });
    function escapeHtml(value) { return $('<div>').text(value || '').html(); }
    productSearch.on('input', function() {
        var query = $.trim($(this).val());
        clearTimeout(timer);
        if (query.length < 2) { productResults.empty().hide(); return; }
        timer = setTimeout(function() {
            productResults.html('<p>Searching products...</p>').show();
            $.post(ARKPushAdmin.ajaxUrl, {
                action: 'ark_push_search_products',
                nonce: ARKPushAdmin.nonce,
                term: query
            }).done(function(response) {
                productResults.empty();
                if (!response || !response.success || !response.data.length) {
                    productResults.html('<p>No published products found.</p>');
                    return;
                }
                response.data.forEach(function(item) {
                    var button = $('<button type="button" class="button ark-push-product-option" style="display:block;width:100%;text-align:left;margin:4px 0"></button>');
                    button.attr('data-id', item.id).attr('data-title', item.title);
                    button.text('#' + item.id + ' — ' + item.title);
                    productResults.append(button);
                });
            }).fail(function() {
                productResults.html('<p>Product search failed. Refresh the page and try again.</p>');
            });
        }, 250);
    });
    productResults.on('click', '.ark-push-product-option', function() {
        var id = $(this).attr('data-id');
        var title = $(this).attr('data-title');
        targetId.val(id);
        selectedProduct.text('Selected: #' + id + ' — ' + title);
        productSearch.val(title);
        productResults.hide().empty();
    });

    var scheduleMode = $('#ark-push-schedule-mode');
    var scheduleDate = $('#ark-push-schedule-date');
    var scheduleDay = $('#ark-push-schedule-day');
    function updateScheduleFields() {
        var mode = scheduleMode.val() || 'now';
        var show = mode !== 'now';
        $('.ark-push-schedule-row').toggle(show);
        scheduleDate.prop('required', show);
        $('#ark-push-schedule-time').prop('required', show);
        $('#ark-push-submit').text(show ? 'Save scheduled notification' : 'Send notification now');
        if (mode === 'weekly') {
            $('#ark-push-schedule-help').text('Repeats every week on the weekday selected by the date, at the selected time. You can stop it from notification history.');
        } else {
            $('#ark-push-schedule-help').text('Sends once on the selected date and time.');
        }
    }
    function updateScheduleDay() {
        var value = scheduleDate.val();
        if (!value) {
            scheduleDay.text('Choose a date to see the weekday.');
            return;
        }
        var parts = value.split('-');
        var date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        scheduleDay.text(date.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }));
    }
    scheduleMode.on('change', updateScheduleFields);
    scheduleDate.on('change', updateScheduleDay);
    updateScheduleFields();
    updateScheduleDay();
});
JS;
        $script = str_replace(
            ['ARKPushAdmin.ajaxUrl', 'ARKPushAdmin.nonce'],
            [wp_json_encode(admin_url('admin-ajax.php')), wp_json_encode(wp_create_nonce('ark_push_search_products'))],
            $script
        );
        wp_add_inline_script('jquery-core', $script);
    }

    public function save_settings() {
        if (!current_user_can('manage_options')) wp_die('You are not allowed to change these settings.');
        check_admin_referer('ark_push_save_settings');

        $current = $this->settings();
        $input = isset($_POST['firebase']) && is_array($_POST['firebase']) ? wp_unslash($_POST['firebase']) : [];
        $settings = [
            'api_key' => sanitize_text_field($input['api_key'] ?? ''),
            'app_id' => sanitize_text_field($input['app_id'] ?? ''),
            'messaging_sender_id' => sanitize_text_field($input['messaging_sender_id'] ?? ''),
            'project_id' => sanitize_text_field($input['project_id'] ?? ''),
            'storage_bucket' => sanitize_text_field($input['storage_bucket'] ?? ''),
            'service_account_json' => $current['service_account_json'] ?? '',
        ];

        $service_json = isset($_POST['service_account_json']) ? trim(wp_unslash($_POST['service_account_json'])) : '';
        if ($service_json !== '') {
            $decoded = json_decode($service_json, true);
            if (!is_array($decoded) || empty($decoded['client_email']) || empty($decoded['private_key']) || empty($decoded['project_id'])) {
                wp_safe_redirect(add_query_arg(['page' => 'ark-app-notifications', 'ark_error' => 'service'], admin_url('admin.php')));
                exit;
            }
            $settings['service_account_json'] = wp_json_encode($decoded);
        }

        update_option(self::OPTION, $settings, false);
        wp_safe_redirect(add_query_arg(['page' => 'ark-app-notifications', 'ark_saved' => '1'], admin_url('admin.php')));
        exit;
    }

    private function clean_link($value) {
        $value = trim((string) $value);
        if ($value === '') return '';
        $parts = wp_parse_url($value);
        if (!$parts || empty($parts['scheme'])) return '';
        $scheme = strtolower($parts['scheme']);
        $allowed = ['https', 'http', 'ark', 'glowhit', 'whatsapp', 'tel', 'mailto', 'upi'];
        if (!in_array($scheme, $allowed, true)) return '';
        if (in_array($scheme, ['http', 'https'], true) && empty($parts['host'])) return '';
        return esc_url_raw($value, $allowed);
    }

    public function send_campaign() {
        if (!current_user_can('manage_options')) wp_die('You are not allowed to send notifications.');
        check_admin_referer('ark_push_send_campaign');

        if (!$this->firebase_ready()) {
            wp_safe_redirect(add_query_arg(['page' => 'ark-app-notifications', 'ark_error' => 'firebase'], admin_url('admin.php')));
            exit;
        }

        $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
        $body = sanitize_textarea_field(wp_unslash($_POST['body'] ?? ''));
        $image = esc_url_raw(wp_unslash($_POST['image_url'] ?? ''));
        $target_type = sanitize_key(wp_unslash($_POST['target_type'] ?? 'none'));
        $target_id = absint($_POST['target_id'] ?? 0);
        $external_link = $this->clean_link(wp_unslash($_POST['click_url'] ?? ''));
        $schedule_mode = sanitize_key(wp_unslash($_POST['schedule_mode'] ?? 'now'));
        if (!in_array($schedule_mode, ['now', 'once', 'weekly'], true)) $schedule_mode = 'now';
        $link = '';

        if (!in_array($target_type, ['none', 'product', 'page', 'category', 'external'], true)) {
            $target_type = 'none';
        }
        if ($target_type === 'product') {
            $product = function_exists('wc_get_product') ? wc_get_product($target_id) : false;
            if (!$product || $product->get_status() !== 'publish') {
                wp_safe_redirect(add_query_arg(['page' => 'ark-app-notifications', 'ark_error' => 'target'], admin_url('admin.php')));
                exit;
            }
            $link = 'ark://product/' . $target_id;
        } elseif ($target_type === 'page') {
            $page = get_post($target_id);
            if (!$page || $page->post_type !== 'page' || $page->post_status !== 'publish') {
                wp_safe_redirect(add_query_arg(['page' => 'ark-app-notifications', 'ark_error' => 'target'], admin_url('admin.php')));
                exit;
            }
            $link = 'ark://page/' . $target_id;
        } elseif ($target_type === 'category') {
            $term = get_term($target_id, 'product_cat');
            if (!$term || is_wp_error($term)) {
                wp_safe_redirect(add_query_arg(['page' => 'ark-app-notifications', 'ark_error' => 'target'], admin_url('admin.php')));
                exit;
            }
            $link = 'ark://category/' . $target_id;
        } elseif ($target_type === 'external') {
            $link = $external_link;
            if ($link === '') {
                wp_safe_redirect(add_query_arg(['page' => 'ark-app-notifications', 'ark_error' => 'target'], admin_url('admin.php')));
                exit;
            }
        }

        if ($title === '' || $body === '') {
            wp_safe_redirect(add_query_arg(['page' => 'ark-app-notifications', 'ark_error' => 'fields'], admin_url('admin.php')));
            exit;
        }

        // Date/time values are interpreted in the WordPress site's timezone,
        // then persisted as UTC for WP-Cron and cross-timezone consistency.
        $scheduled_local = null;
        $scheduled_utc = null;
        $schedule_day = 0;
        $effective_send_time = time();
        if ($schedule_mode !== 'now') {
            $date_input = sanitize_text_field(wp_unslash($_POST['schedule_date'] ?? ''));
            $time_input = sanitize_text_field(wp_unslash($_POST['schedule_time'] ?? ''));
            if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date_input)
                || !preg_match('/^\\d{2}:\\d{2}$/', $time_input)) {
                wp_safe_redirect(add_query_arg(['page' => 'ark-app-notifications', 'ark_error' => 'schedule'], admin_url('admin.php')));
                exit;
            }
            $scheduled_local = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date_input . ' ' . $time_input, wp_timezone());
            $date_errors = DateTimeImmutable::getLastErrors();
            $date_valid = $scheduled_local
                && (!$date_errors || ((int) $date_errors['warning_count'] === 0 && (int) $date_errors['error_count'] === 0))
                && $scheduled_local->format('Y-m-d H:i') === $date_input . ' ' . $time_input;
            if (!$date_valid || $scheduled_local->getTimestamp() <= time() + 30) {
                wp_safe_redirect(add_query_arg(['page' => 'ark-app-notifications', 'ark_error' => 'schedule'], admin_url('admin.php')));
                exit;
            }
            $effective_send_time = $scheduled_local->getTimestamp();
            $scheduled_utc = $scheduled_local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $schedule_day = (int) $scheduled_local->format('w'); // 0=Sunday
        }

        $countdown = trim(sanitize_text_field(wp_unslash($_POST['countdown_end'] ?? '')));
        $countdown_utc = null;
        if ($countdown !== '') {
            try {
                $date = new DateTimeImmutable($countdown, wp_timezone());
                if ($date->getTimestamp() <= $effective_send_time) {
                    wp_safe_redirect(add_query_arg(['page' => 'ark-app-notifications', 'ark_error' => 'countdown'], admin_url('admin.php')));
                    exit;
                }
                $countdown_utc = $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                wp_safe_redirect(add_query_arg(['page' => 'ark-app-notifications', 'ark_error' => 'countdown'], admin_url('admin.php')));
                exit;
            }
        }

        global $wpdb;
        $target_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->devices} WHERE active = 1");
        if ($schedule_mode === 'now' && $target_count < 1) {
            wp_safe_redirect(add_query_arg(['page' => 'ark-app-notifications', 'ark_error' => 'devices'], admin_url('admin.php')));
            exit;
        }

        $schedule_type = $schedule_mode === 'now' ? 'now' : $schedule_mode;
        $initial_status = $schedule_mode === 'now' ? 'queued' : 'scheduled';
        $inserted = $wpdb->insert($this->messages, [
            'title' => $title,
            'body' => $body,
            'image_url' => $image,
            'click_url' => $link,
            'target_type' => $target_type,
            'target_id' => $target_id,
            'countdown_end' => $countdown_utc,
            'schedule_type' => $schedule_type,
            'scheduled_at' => $scheduled_utc,
            'schedule_day' => $schedule_day,
            'status' => $initial_status,
            'target_count' => $target_count,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql', true),
        ], ['%s','%s','%s','%s','%s','%d','%s','%s','%s','%d','%s','%d','%d','%s']);

        if (!$inserted) {
            wp_safe_redirect(add_query_arg(['page' => 'ark-app-notifications', 'ark_error' => 'database'], admin_url('admin.php')));
            exit;
        }

        $message_id = (int) $wpdb->insert_id;
        if ($schedule_mode === 'weekly') {
            $wpdb->update($this->messages, ['schedule_series_id' => $message_id], ['id' => $message_id], ['%d'], ['%d']);
        }
        if ($schedule_mode === 'now') {
            // Deliver the first batch immediately; larger lists continue on WP-Cron.
            $this->send_batch($message_id);
        } else {
            $event = wp_schedule_single_event(
                $scheduled_local->getTimestamp(),
                self::SCHEDULE_HOOK,
                [$message_id],
                true
            );
            if (is_wp_error($event) || $event === false) {
                $wpdb->update($this->messages, [
                    'status' => 'failed',
                    'last_error' => is_wp_error($event) ? $event->get_error_message() : 'WordPress could not register the scheduled event.',
                ], ['id' => $message_id], ['%s','%s'], ['%d']);
                wp_safe_redirect(add_query_arg(['page' => 'ark-app-notifications', 'ark_error' => 'schedule_save'], admin_url('admin.php')));
                exit;
            }
        }

        $delivery = $wpdb->get_row($wpdb->prepare(
            "SELECT status FROM {$this->messages} WHERE id = %d",
            $message_id
        ));
        $status = $delivery ? sanitize_key($delivery->status) : $initial_status;
        wp_safe_redirect(add_query_arg([
            'page' => 'ark-app-notifications',
            'ark_processed' => $message_id,
            'ark_delivery' => $status,
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Re-register missed scheduled events after plugin updates or when a site
     * has WP-Cron disabled and later resumes. A real server cron is preferred
     * for time-sensitive delivery.
     */
    public function ensure_scheduled_messages() {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT id,scheduled_at FROM {$this->messages}
             WHERE status = 'scheduled'
               AND schedule_type IN ('once','weekly')
               AND scheduled_at IS NOT NULL
             ORDER BY scheduled_at ASC LIMIT 100"
        );
        foreach ((array) $rows as $row) {
            $id = (int) $row->id;
            if (!$id || wp_next_scheduled(self::SCHEDULE_HOOK, [$id])) continue;
            $timestamp = strtotime((string) $row->scheduled_at . ' UTC');
            if (!$timestamp || $timestamp <= time()) $timestamp = time() + 1;
            wp_schedule_single_event($timestamp, self::SCHEDULE_HOOK, [$id]);
        }
    }

    /**
     * Dispatch a saved one-time or weekly message. Weekly events schedule their
     * next occurrence before sending, so a temporary FCM failure does not
     * silently stop the recurring schedule.
     */
    public function run_scheduled_message($message_id) {
        $message_id = absint($message_id);
        if (!$message_id) return;
        $lock = 'ark_push_schedule_lock_' . $message_id;
        if (get_transient($lock)) return;
        set_transient($lock, 1, 60);

        global $wpdb;
        $message = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->messages} WHERE id = %d",
            $message_id
        ));
        if (!$message) {
            delete_transient($lock);
            return;
        }

        $type = sanitize_key((string) $message->schedule_type);
        $status = sanitize_key((string) $message->status);
        if ($status !== 'scheduled' || !in_array($type, ['once','weekly'], true) || empty($message->scheduled_at)) {
            delete_transient($lock);
            return;
        }

        $scheduled_timestamp = strtotime((string) $message->scheduled_at . ' UTC');
        if (!$scheduled_timestamp) {
            $wpdb->update($this->messages, [
                'status' => 'failed',
                'last_error' => 'The saved schedule date is invalid.',
            ], ['id' => $message_id], ['%s','%s'], ['%d']);
            delete_transient($lock);
            return;
        }
        if ($scheduled_timestamp > time() + 5) {
            if (!wp_next_scheduled(self::SCHEDULE_HOOK, [$message_id])) {
                wp_schedule_single_event($scheduled_timestamp, self::SCHEDULE_HOOK, [$message_id]);
            }
            delete_transient($lock);
            return;
        }

        if ($type === 'weekly') {
            $repeat_ok = false;
            try {
                $next_local = (new DateTimeImmutable((string) $message->scheduled_at, new DateTimeZone('UTC')))
                    ->setTimezone(wp_timezone())
                    ->modify('+7 days');
                // Skip missed weekly slots after downtime; do not send a backlog
                // of old offers in a burst when WP-Cron starts running again.
                while ($next_local->getTimestamp() <= time() + 30) {
                    $next_local = $next_local->modify('+7 days');
                }

                $series_id = (int) ($message->schedule_series_id ?? 0);
                if (!$series_id) $series_id = $message_id;
                $next_utc = $next_local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                $next_data = [
                    'title' => (string) $message->title,
                    'body' => (string) $message->body,
                    'image_url' => (string) $message->image_url,
                    'click_url' => (string) $message->click_url,
                    'target_type' => (string) $message->target_type,
                    'target_id' => (int) $message->target_id,
                    'message_type' => (string) $message->message_type,
                    'recipient_user_id' => (int) $message->recipient_user_id,
                    'countdown_end' => $message->countdown_end,
                    'schedule_type' => 'weekly',
                    'scheduled_at' => $next_utc,
                    'schedule_day' => (int) $next_local->format('w'),
                    'schedule_series_id' => $series_id,
                    'status' => 'scheduled',
                    'target_count' => 0,
                    'success_count' => 0,
                    'failure_count' => 0,
                    'click_count' => 0,
                    'cursor_id' => 0,
                    'last_error' => '',
                    'created_by' => (int) $message->created_by,
                    'created_at' => current_time('mysql', true),
                    'sent_at' => null,
                ];
                $next_formats = [
                    '%s','%s','%s','%s','%s','%d','%s','%d','%s','%s','%s','%d',
                    '%d','%s','%d','%d','%d','%d','%d','%s','%d','%s','%s'
                ];
                $next_saved = $wpdb->insert($this->messages, $next_data, $next_formats);
                if ($next_saved) {
                    $next_id = (int) $wpdb->insert_id;
                    $next_event = wp_schedule_single_event(
                        $next_local->getTimestamp(),
                        self::SCHEDULE_HOOK,
                        [$next_id],
                        true
                    );
                    if (is_wp_error($next_event) || $next_event === false) {
                        $wpdb->update($this->messages, [
                            'status' => 'failed',
                            'schedule_type' => 'stopped',
                            'scheduled_at' => null,
                            'last_error' => is_wp_error($next_event) ? $next_event->get_error_message() : 'Could not register the next weekly occurrence.',
                        ], ['id' => $next_id], ['%s','%s','%s','%s'], ['%d']);
                    } else {
                        $repeat_ok = true;
                    }
                }
                // This occurrence now has its own unique notification ID. The
                // next week's message is a separate row and can be read/deleted
                // independently in each app installation.
                if ($repeat_ok) {
                    $wpdb->update($this->messages, [
                        'schedule_type' => 'weekly_sent',
                        'scheduled_at' => null,
                    ], ['id' => $message_id], ['%s','%s'], ['%d']);
                } else {
                    $wpdb->update($this->messages, [
                        'schedule_type' => 'stopped',
                        'scheduled_at' => null,
                    ], ['id' => $message_id], ['%s','%s'], ['%d']);
                }
            } catch (Exception $e) {
                $wpdb->update($this->messages, [
                    'schedule_type' => 'stopped',
                    'last_error' => 'Weekly schedule stopped: ' . $e->getMessage(),
                ], ['id' => $message_id], ['%s','%s'], ['%d']);
            }
        }

        $target_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->devices} WHERE active = 1");
        $wpdb->update($this->messages, [
            'status' => 'queued',
            'target_count' => $target_count,
            'success_count' => 0,
            'failure_count' => 0,
            'cursor_id' => 0,
            'last_error' => '',
            'sent_at' => null,
        ], ['id' => $message_id], ['%s','%d','%d','%d','%d','%s','%s'], ['%d']);

        delete_transient($lock);
        $this->send_batch($message_id);
    }

    public function cancel_scheduled_message() {
        if (!current_user_can('manage_options')) wp_die('You are not allowed to manage notification schedules.');
        $message_id = absint($_POST['message_id'] ?? 0);
        check_admin_referer('ark_push_cancel_schedule_' . $message_id);
        if (!$message_id) {
            wp_safe_redirect(add_query_arg(['page' => 'ark-app-notifications', 'ark_error' => 'schedule_cancel'], admin_url('admin.php')));
            exit;
        }

        global $wpdb;
        $message = $wpdb->get_row($wpdb->prepare(
            "SELECT id,status,schedule_type,schedule_series_id FROM {$this->messages} WHERE id = %d",
            $message_id
        ));
        if ($message && ($message->status === 'scheduled' || in_array($message->schedule_type, ['weekly','weekly_sent'], true))) {
            $series_id = (int) ($message->schedule_series_id ?? 0);
            if ($message->schedule_type === 'weekly' || $message->schedule_type === 'weekly_sent') {
                if (!$series_id) $series_id = $message_id;
                $pending_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT id FROM {$this->messages} WHERE schedule_series_id = %d AND status = 'scheduled'",
                    $series_id
                ));
                foreach ((array) $pending_ids as $pending_id) {
                    wp_clear_scheduled_hook(self::SCHEDULE_HOOK, [(int) $pending_id]);
                }
                $wpdb->update($this->messages, [
                    'status' => 'cancelled',
                    'schedule_type' => 'cancelled',
                    'scheduled_at' => null,
                ], ['schedule_series_id' => $series_id, 'status' => 'scheduled'], ['%s','%s','%s'], ['%d','%s']);
                $wpdb->update($this->messages, [
                    'schedule_type' => 'stopped',
                    'scheduled_at' => null,
                ], ['schedule_series_id' => $series_id, 'schedule_type' => 'weekly_sent'], ['%s','%s'], ['%d','%s']);
            } else {
                wp_clear_scheduled_hook(self::SCHEDULE_HOOK, [$message_id]);
                $wpdb->update($this->messages, [
                    'status' => 'cancelled',
                    'schedule_type' => 'cancelled',
                    'scheduled_at' => null,
                ], ['id' => $message_id], ['%s','%s','%s'], ['%d']);
            }
        }

        wp_safe_redirect(add_query_arg(['page' => 'ark-app-notifications', 'ark_schedule_cancelled' => $message_id], admin_url('admin.php')));
        exit;
    }

    /**
     * Find the WooCommerce account that owns an order. App checkouts require
     * the billing email to match the signed-in customer, so a guest order can
     * be safely attached when WooCommerce did not persist its customer id.
     */
    private function order_customer_user_id($order) {
        if (!($order instanceof WC_Order)) return 0;

        $customer_id = (int) $order->get_customer_id();
        if ($customer_id > 0) {
            return get_user_by('id', $customer_id) ? $customer_id : 0;
        }

        $email = sanitize_email((string) $order->get_billing_email());
        if (!$email || !is_email($email)) return 0;
        $user = get_user_by('email', $email);
        if (!$user) return 0;

        // The billing email is verified against a registered WordPress
        // account. Do not save here: save() fires woocommerce_update_order
        // while the order-created notification is still being processed.
        return (int) $user->ID;
    }

    private function queue_order_notification($order, $user_id, $message_type, $title, $body) {
        global $wpdb;
        $user_id = absint($user_id);
        $order_id = $order instanceof WC_Order ? (int) $order->get_id() : 0;
        if (!$user_id || !$order_id || !$this->messages) return false;

        $target_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->devices} WHERE active = 1 AND user_id = %d",
            $user_id
        ));
        $now = current_time('mysql', true);
        // Keep the order event until a customer device is linked. This avoids
        // marking it as sent (and hiding it from the inbox) when the customer
        // signs in or grants notification permission a little later.
        $status = $target_count > 0 ? 'queued' : 'waiting_device';

        $inserted = $wpdb->insert($this->messages, [
            'title' => sanitize_text_field($title),
            'body' => sanitize_textarea_field($body),
            'image_url' => '',
            'click_url' => 'ark://order/' . $order_id,
            'target_type' => 'order',
            'target_id' => $order_id,
            'message_type' => sanitize_key($message_type),
            'recipient_user_id' => $user_id,
            'countdown_end' => null,
            'status' => $status,
            'target_count' => $target_count,
            'success_count' => 0,
            'failure_count' => 0,
            'created_by' => 0,
            'created_at' => $now,
            'sent_at' => null,
        ], [
            '%s','%s','%s','%s','%s','%d','%s','%d','%s','%s','%d','%d','%d','%d','%s','%s'
        ]);

        if (!$inserted) return false;
        $message_id = (int) $wpdb->insert_id;
        if ($target_count > 0) {
            // Do not leave a single-customer order alert waiting for WP-Cron.
            $this->send_batch($message_id);
        }
        return true;
    }

    /**
     * Resume a small number of order alerts that were created before this
     * customer's FCM token was linked (e.g. permission/account setup lag).
     */
    private function resume_waiting_order_notifications($user_id) {
        global $wpdb;
        $user_id = absint($user_id);
        if (!$user_id) return;

        $device_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->devices} WHERE active = 1 AND user_id = %d",
            $user_id
        ));
        if ($device_count < 1) return;

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$this->messages}
             WHERE recipient_user_id = %d
               AND message_type IN ('order_placed','order_status')
               AND status = 'waiting_device'
             ORDER BY id DESC LIMIT 5",
            $user_id
        ));
        foreach ((array) $ids as $message_id) {
            $wpdb->update($this->messages, [
                'status' => 'queued',
                'target_count' => $device_count,
                'cursor_id' => 0,
                'last_error' => '',
                'sent_at' => null,
            ], ['id' => (int) $message_id], ['%s','%d','%d','%s','%s'], ['%d']);
            $this->send_batch((int) $message_id);
        }
    }

    public function on_order_created($order_id) {
        $order = function_exists('wc_get_order') ? wc_get_order(absint($order_id)) : false;
        if (!$order || $order->get_meta('_ark_push_order_placed_notified', true) === 'yes') return;

        $user_id = $this->order_customer_user_id($order);
        if (!$user_id) return;

        $number = $order->get_order_number();
        $sent = $this->queue_order_notification(
            $order,
            $user_id,
            'order_placed',
            sprintf('Order #%s placed', $number),
            'Thank you! Your order has been received. We will notify you when its status changes.'
        );
        if ($sent) {
            $order->update_meta_data('_ark_push_order_placed_notified', 'yes');
            $order->save();
        }
    }

    public function on_order_updated($order_id) {
        // Covers app/guest orders that receive their customer id after creation
        // through the authenticated /orders/attach endpoint.
        $this->on_order_created($order_id);
    }

    public function on_order_status_changed($order_id, $old_status, $new_status, $order = null) {
        $old_status = sanitize_key($old_status);
        $new_status = sanitize_key($new_status);
        if (
            $old_status === ''
            || in_array($old_status, ['new', 'auto-draft'], true)
            || $new_status === ''
            || $old_status === $new_status
        ) return;

        if (!($order instanceof WC_Order)) {
            $order = function_exists('wc_get_order') ? wc_get_order(absint($order_id)) : false;
        }
        if (!$order) return;

        // If customer linking happened after the initial WooCommerce hook,
        // recover the order-placed event before sending the new status event.
        if ($order->get_meta('_ark_push_order_placed_notified', true) !== 'yes') {
            $this->on_order_created($order_id);
        }

        $user_id = $this->order_customer_user_id($order);
        if (!$user_id) return;

        $modified = $order->get_date_modified();
        $signature = $old_status . '>' . $new_status . ':' . ($modified ? $modified->getTimestamp() : time());
        $previous = (string) $order->get_meta('_ark_push_last_status_event', true);
        if ($previous !== '' && hash_equals($previous, $signature)) return;

        $status_label = function_exists('wc_get_order_status_name')
            ? wc_get_order_status_name($new_status)
            : ucfirst(str_replace('-', ' ', $new_status));
        $number = $order->get_order_number();
        $sent = $this->queue_order_notification(
            $order,
            $user_id,
            'order_status',
            sprintf('Order #%s update', $number),
            sprintf('Your order status is now %s. Tap to view your order details.', $status_label)
        );
        if ($sent) {
            $order->update_meta_data('_ark_push_last_status_event', $signature);
            $order->save();
        }
    }

    private function b64url($value) {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function fcm_access_token() {
        $cached = get_transient('ark_push_fcm_access_token');
        if (is_array($cached) && !empty($cached['token'])) return $cached['token'];

        $settings = $this->settings();
        $service = json_decode((string) ($settings['service_account_json'] ?? ''), true);
        if (!is_array($service) || empty($service['client_email']) || empty($service['private_key'])) {
            return new WP_Error('ark_push_service_account', 'Firebase service account JSON is not configured.');
        }

        $now = time();
        $header = $this->b64url(wp_json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $this->b64url(wp_json_encode([
            'iss' => $service['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));
        $unsigned = $header . '.' . $claims;
        $private_key = openssl_pkey_get_private($service['private_key']);
        if (!$private_key) return new WP_Error('ark_push_private_key', 'Firebase service account private key could not be read.');
        $signed = openssl_sign($unsigned, $signature, $private_key, OPENSSL_ALGO_SHA256);
        if (!$signed) return new WP_Error('ark_push_jwt', 'Could not sign Firebase authentication request.');
        $assertion = $unsigned . '.' . $this->b64url($signature);

        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'timeout' => 20,
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ],
        ]);
        if (is_wp_error($response)) return $response;

        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($code !== 200 || empty($data['access_token'])) {
            $error = is_array($data) ? ($data['error_description'] ?? $data['error'] ?? 'OAuth token request failed.') : 'OAuth token request failed.';
            return new WP_Error('ark_push_oauth', sanitize_text_field($error));
        }

        $token = sanitize_text_field($data['access_token']);
        set_transient('ark_push_fcm_access_token', ['token' => $token], max(60, min(3500, (int) ($data['expires_in'] ?? 3600) - 120)));
        return $token;
    }

    private function send_to_device($device, $message) {
        $settings = $this->settings();
        $service = json_decode((string) ($settings['service_account_json'] ?? ''), true);
        $project = sanitize_text_field($settings['project_id'] ?? ($service['project_id'] ?? ''));
        $access_token = $this->fcm_access_token();
        if (is_wp_error($access_token)) return ['ok' => false, 'invalid' => false, 'error' => $access_token->get_error_message()];
        if ($project === '') return ['ok' => false, 'invalid' => false, 'error' => 'Firebase project ID is missing.'];

        $data = [
            'message_id' => (string) $message->id,
            'click_url' => (string) ($message->click_url ?? ''),
            'target_type' => (string) ($message->target_type ?? 'none'),
            'target_id' => (string) ((int) ($message->target_id ?? 0)),
            // FCM reserves the exact key "message_type"; use a namespaced key.
            'ark_message_type' => (string) ($message->message_type ?? 'campaign'),
            'countdown_end' => $message->countdown_end ? gmdate(DATE_ATOM, strtotime($message->countdown_end . ' UTC')) : '',
            'image_url' => (string) ($message->image_url ?? ''),
        ];

        // Guard against regressions: FCM rejects reserved data keys such
        // as "from", "message_type", and google./gcm.* prefixes.
        foreach (array_keys($data) as $data_key) {
            if (preg_match('/^(from|message_type|google\\.|gcm\\.)/i', (string) $data_key)) {
                return [
                    'ok' => false,
                    'invalid' => false,
                    'error' => 'Internal FCM payload validation rejected reserved key: ' . sanitize_key($data_key),
                ];
            }
        }

        $notification = [
            'title' => (string) $message->title,
            'body' => (string) $message->body,
        ];
        if (!empty($message->image_url)) $notification['image'] = (string) $message->image_url;

        $android_notification = [
            'channel_id' => 'ark_promotions',
            'sound' => 'default',
        ];
        if (!empty($message->image_url)) $android_notification['image'] = (string) $message->image_url;

        $payload = [
            'message' => [
                'token' => (string) $device->token,
                'notification' => $notification,
                'data' => $data,
                'android' => [
                    'priority' => 'HIGH',
                    'notification' => $android_notification,
                ],
            ],
        ];

        $response = wp_remote_post(
            'https://fcm.googleapis.com/v1/projects/' . rawurlencode($project) . '/messages:send',
            [
                'timeout' => 20,
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json; charset=utf-8',
                ],
                'body' => wp_json_encode($payload),
            ]
        );

        if (is_wp_error($response)) return ['ok' => false, 'invalid' => false, 'error' => $response->get_error_message()];
        $code = (int) wp_remote_retrieve_response_code($response);
        $result = json_decode(wp_remote_retrieve_body($response), true);
        if ($code >= 200 && $code < 300 && !empty($result['name'])) {
            return ['ok' => true, 'invalid' => false, 'error' => ''];
        }

        $error_message = is_array($result) ? ($result['error']['message'] ?? 'Firebase rejected the message.') : 'Firebase rejected the message.';
        $invalid = false;
        if (isset($result['error']['details']) && is_array($result['error']['details'])) {
            foreach ($result['error']['details'] as $detail) {
                $reason = $detail['errorCode'] ?? '';
                if (in_array($reason, ['UNREGISTERED', 'SENDER_ID_MISMATCH'], true)) $invalid = true;
            }
        }
        if ($code === 404) $invalid = true;

        return ['ok' => false, 'invalid' => $invalid, 'error' => sanitize_text_field($error_message)];
    }

    public function send_batch($message_id) {
        $message_id = absint($message_id);
        if (!$message_id) return;

        $lock = 'ark_push_batch_lock_' . $message_id;
        if (get_transient($lock)) {
            wp_schedule_single_event(time() + 10, self::CRON_HOOK, [$message_id]);
            return;
        }
        set_transient($lock, 1, 55);

        global $wpdb;
        $message = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->messages} WHERE id = %d", $message_id));
        if (!$message || in_array($message->status, ['sent', 'failed'], true)) {
            delete_transient($lock);
            return;
        }

        $access = $this->fcm_access_token();
        if (is_wp_error($access)) {
            $wpdb->update($this->messages, ['status' => 'failed', 'last_error' => $access->get_error_message()], ['id' => $message_id], ['%s','%s'], ['%d']);
            delete_transient($lock);
            return;
        }

        $recipient_user_id = (int) ($message->recipient_user_id ?? 0);
        $devices = $wpdb->get_results($wpdb->prepare(
            "SELECT id, token, token_hash
             FROM {$this->devices}
             WHERE active = 1
               AND id > %d
               AND (%d = 0 OR user_id = %d)
             ORDER BY id ASC
             LIMIT 10",
            (int) $message->cursor_id,
            $recipient_user_id,
            $recipient_user_id
        ));

        if (!$devices) {
            $status = $recipient_user_id > 0
                ? 'waiting_device'
                : (((int) $message->success_count > 0) ? 'sent' : 'failed');
            $wpdb->update($this->messages, [
                'status' => $status,
                'sent_at' => $status === 'sent' || $status === 'failed' ? current_time('mysql', true) : null,
                'last_error' => $status === 'waiting_device'
                    ? 'No active GlowHit device is currently linked to this customer account.'
                    : (string) $message->last_error,
            ], ['id' => $message_id], ['%s','%s','%s'], ['%d']);
            delete_transient($lock);
            return;
        }

        $success = (int) $message->success_count;
        $failure = (int) $message->failure_count;
        $cursor = (int) $message->cursor_id;
        $last_error = (string) $message->last_error;

        foreach ($devices as $device) {
            $result = $this->send_to_device($device, $message);
            $cursor = (int) $device->id;
            if ($result['ok']) {
                $success++;
            } else {
                $failure++;
                $last_error = $result['error'];
                if ($result['invalid']) {
                    $wpdb->update($this->devices, ['active' => 0, 'updated_at' => current_time('mysql', true)], ['id' => (int) $device->id], ['%d','%s'], ['%d']);
                }
            }
        }

        $wpdb->update($this->messages, [
            'status' => 'sending',
            'success_count' => $success,
            'failure_count' => $failure,
            'cursor_id' => $cursor,
            'last_error' => $last_error,
        ], ['id' => $message_id], ['%s','%d','%d','%d','%s'], ['%d']);

        delete_transient($lock);

        if (count($devices) >= 10) {
            wp_schedule_single_event(time() + 5, self::CRON_HOOK, [$message_id]);
        } else {
            $wpdb->update($this->messages, [
                'status' => ($success > 0) ? 'sent' : 'failed',
                'sent_at' => current_time('mysql', true),
            ], ['id' => $message_id], ['%s','%s'], ['%d']);
        }
    }

    private function device_from_request($request) {
        $token = trim((string) $request->get_header('x-ark-push-token'));
        if ($token === '') $token = trim((string) $request->get_param('token'));
        if (strlen($token) < 50 || strlen($token) > 5000) return null;

        global $wpdb;
        $hash = hash('sha256', $token);
        $device = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->devices} WHERE token_hash = %s AND active = 1 LIMIT 1",
            $hash
        ));
        return $device ?: null;
    }

    public function register_routes() {
        register_rest_route('ark-push/v1', '/config', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'api_config'],
        ]);
        register_rest_route('ark-push/v1', '/register', [
            'methods' => WP_REST_Server::CREATABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'api_register'],
        ]);
        register_rest_route('ark-push/v1', '/messages', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'api_messages'],
        ]);
        register_rest_route('ark-push/v1', '/messages/(?P<id>\\d+)', [
            'methods' => WP_REST_Server::DELETABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'api_delete_message'],
            'args' => ['id' => ['validate_callback' => function ($value) { return absint($value) > 0; }]],
        ]);
        register_rest_route('ark-push/v1', '/diagnostics', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'api_diagnostics'],
        ]);
        register_rest_route('ark-push/v1', '/click', [
            'methods' => WP_REST_Server::CREATABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'api_click'],
        ]);
        register_rest_route('ark-push/v1', '/unregister', [
            'methods' => WP_REST_Server::CREATABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'api_unregister'],
        ]);
        register_rest_route('ark-push/v1', '/resolve-link', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'api_resolve_link'],
        ]);
        register_rest_route('ark-push/v1', '/target', [
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => [$this, 'api_target'],
        ]);
    }

    private function same_store_host($url) {
        $url_host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        $site_host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
        $url_host = preg_replace('/^www\./', '', $url_host);
        $site_host = preg_replace('/^www\./', '', $site_host);
        return $url_host !== '' && $site_host !== '' && hash_equals($site_host, $url_host);
    }

    private function public_target_response($type, $id) {
        $id = absint($id);
        if ($id < 1) {
            return new WP_Error('ark_push_target_invalid', 'A valid destination is required.', ['status' => 400]);
        }

        if ($type === 'product' || $type === 'page') {
            $post = get_post($id);
            $expected_type = $type === 'product' ? 'product' : 'page';
            if (!$post || $post->post_type !== $expected_type || $post->post_status !== 'publish') {
                return new WP_Error('ark_push_target_missing', 'The selected destination is not published.', ['status' => 404]);
            }
            return rest_ensure_response([
                'type' => $type,
                'id' => $id,
                'title' => get_the_title($id),
                'url' => get_permalink($id),
            ]);
        }

        if ($type === 'category') {
            $term = get_term($id, 'product_cat');
            if (!$term || is_wp_error($term)) {
                return new WP_Error('ark_push_target_missing', 'The selected product category was not found.', ['status' => 404]);
            }
            return rest_ensure_response([
                'type' => $type,
                'id' => $id,
                'title' => $term->name,
                'url' => get_term_link($term),
            ]);
        }

        return new WP_Error('ark_push_target_type', 'Unsupported in-app destination type.', ['status' => 400]);
    }

    public function api_target($request) {
        return $this->public_target_response(
            sanitize_key($request->get_param('type')),
            absint($request->get_param('id'))
        );
    }

    public function api_resolve_link($request) {
        $url = esc_url_raw((string) $request->get_param('url'));
        if ($url === '' || !$this->same_store_host($url)) {
            return new WP_Error('ark_push_resolve_host', 'Only links from this store can be opened as in-app destinations.', ['status' => 400]);
        }

        $post_id = url_to_postid($url);
        if ($post_id > 0) {
            $post = get_post($post_id);
            if ($post && $post->post_status === 'publish' && in_array($post->post_type, ['product', 'page'], true)) {
                return $this->public_target_response($post->post_type === 'product' ? 'product' : 'page', $post_id);
            }
        }

        $path = trim((string) wp_parse_url($url, PHP_URL_PATH), '/');
        $parts = array_values(array_filter(explode('/', $path)));
        $slug = $parts ? sanitize_title(end($parts)) : '';
        if ($slug !== '') {
            foreach (['product', 'page'] as $post_type) {
                $post = get_page_by_path($slug, OBJECT, $post_type);
                if ($post && $post->post_status === 'publish') {
                    return $this->public_target_response($post_type, $post->ID);
                }
            }
            $term = get_term_by('slug', $slug, 'product_cat');
            if ($term && !is_wp_error($term)) {
                return $this->public_target_response('category', $term->term_id);
            }
        }

        return new WP_Error('ark_push_resolve_missing', 'This store link could not be matched to a published product, page or category.', ['status' => 404]);
    }

    public function ajax_search_products() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'You are not allowed to search products.'], 403);
        }
        check_ajax_referer('ark_push_search_products', 'nonce');
        if (!function_exists('wc_get_products')) wp_send_json_success([]);

        $term = sanitize_text_field(wp_unslash($_POST['term'] ?? ''));
        if (strlen($term) < 2) wp_send_json_success([]);

        $products = wc_get_products([
            'status' => 'publish',
            'limit' => 20,
            'search' => $term,
            'orderby' => 'title',
            'order' => 'ASC',
            'return' => 'objects',
        ]);
        $items = [];
        foreach ($products as $product) {
            $items[] = [
                'id' => $product->get_id(),
                'title' => $product->get_name(),
            ];
        }
        wp_send_json_success($items);
    }

    public function api_config($request) {
        $s = $this->settings();
        $service = json_decode((string) ($s['service_account_json'] ?? ''), true);
        return rest_ensure_response([
            'enabled' => $this->firebase_ready($s),
            'firebase' => [
                'api_key' => sanitize_text_field($s['api_key'] ?? ''),
                'app_id' => sanitize_text_field($s['app_id'] ?? ''),
                'messaging_sender_id' => sanitize_text_field($s['messaging_sender_id'] ?? ''),
                'project_id' => sanitize_text_field($s['project_id'] ?? ($service['project_id'] ?? '')),
                'storage_bucket' => sanitize_text_field($s['storage_bucket'] ?? ''),
            ],
        ]);
    }

    /**
     * Resolve the signed-in customer from the ARK Native API JWT.
     * The user id is never accepted from the request body.
     */
    private function app_user_id_from_request($request) {
        $authorization = (string) $request->get_header('authorization');
        if (!preg_match('/Bearer\s+(.+)/i', $authorization, $match)) return 0;

        $parts = explode('.', trim($match[1]));
        if (count($parts) !== 3) return 0;

        $expected = $this->b64url(hash_hmac('sha256', $parts[0] . '.' . $parts[1], wp_salt('auth'), true));
        if (!hash_equals($expected, $parts[2])) return 0;

        $payload_json = base64_decode(strtr($parts[1], '-_', '+/'));
        $payload = json_decode((string) $payload_json, true);
        if (!is_array($payload)) return 0;

        $user_id = absint($payload['sub'] ?? 0);
        $expires = absint($payload['exp'] ?? 0);
        if (!$user_id || !$expires || $expires < time()) return 0;
        if (($payload['iss'] ?? '') !== home_url('/')) return 0;

        return get_user_by('id', $user_id) ? $user_id : 0;
    }

    public function api_register($request) {
        $token = trim((string) $request->get_param('token'));
        if (strlen($token) < 50 || strlen($token) > 5000) {
            return new WP_Error('ark_push_token_invalid', 'A valid Firebase device token is required.', ['status' => 400]);
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
        $rate_key = 'ark_push_register_' . hash('sha256', $ip);
        $attempts = (int) get_transient($rate_key);
        if ($attempts >= 60) return new WP_Error('ark_push_rate_limit', 'Too many device registration requests. Try later.', ['status' => 429]);
        set_transient($rate_key, $attempts + 1, HOUR_IN_SECONDS);

        global $wpdb;
        $hash = hash('sha256', $token);
        $platform = sanitize_key($request->get_param('platform') ?: 'android');
        if (!in_array($platform, ['android', 'ios'], true)) $platform = 'android';
        $version = sanitize_text_field($request->get_param('app_version') ?: '');
        $installation_id = sanitize_text_field($request->get_param('installation_id') ?: '');
        if ($installation_id !== '' && !preg_match('/^[a-f0-9-]{16,64}$/i', $installation_id)) {
            return new WP_Error('ark_push_installation_invalid', 'The app installation identifier is invalid.', ['status' => 400]);
        }

        $user_id = $this->app_user_id_from_request($request);
        $user = $user_id ? get_user_by('id', $user_id) : false;
        $user_email = $user ? sanitize_email($user->user_email) : '';
        $now = current_time('mysql', true);

        // Prefer the stable per-installation id so token rotations update
        // one device record instead of creating duplicate active rows.
        $existing_id = 0;
        if ($installation_id !== '') {
            $existing_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->devices} WHERE installation_id = %s LIMIT 1",
                $installation_id
            ));
        }
        if (!$existing_id) {
            $existing_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->devices} WHERE token_hash = %s LIMIT 1",
                $hash
            ));
        }

        // Older app builds do not send installation_id. Preserve an
        // already-known ID during their token/account refresh rather than
        // clearing the stable identity from the device row.
        if ($installation_id === '' && $existing_id > 0) {
            $installation_id = (string) $wpdb->get_var($wpdb->prepare(
                "SELECT installation_id FROM {$this->devices} WHERE id = %d",
                $existing_id
            ));
        }

        $existing_row = $existing_id > 0
            ? $wpdb->get_row($wpdb->prepare("SELECT user_id,account_linked_at,created_at FROM {$this->devices} WHERE id = %d", $existing_id))
            : null;
        $previous_user_id = (int) ($existing_row->user_id ?? 0);
        $account_linked_at = $existing_row->account_linked_at ?? null;
        if ($user_id > 0 && $user_id !== $previous_user_id) {
            // Start a clean campaign inbox whenever a different customer signs
            // in on the same phone. This prevents prior users' sale messages.
            $account_linked_at = $now;
        } elseif ($user_id === 0) {
            $account_linked_at = null;
        } elseif ($account_linked_at === null) {
            $account_linked_at = $now;
        }

        $device_data = [
            'token_hash' => $hash,
            'token' => $token,
            'platform' => $platform,
            'app_version' => $version,
            'installation_id' => $installation_id !== '' ? $installation_id : null,
            'user_id' => $user_id,
            'user_email' => $user_email,
            'account_linked_at' => $account_linked_at,
            'active' => 1,
            'last_seen' => $now,
            'updated_at' => $now,
        ];

        if ($existing_id > 0) {
            $conflict_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->devices} WHERE token_hash = %s AND id <> %d LIMIT 1",
                $hash,
                $existing_id
            ));
            if ($conflict_id > 0) {
                $wpdb->update(
                    $this->devices,
                    [
                        'token_hash' => hash('sha256', 'inactive-' . $conflict_id . '-' . wp_generate_uuid4()),
                        'token' => '',
                        'active' => 0,
                        'updated_at' => $now,
                    ],
                    ['id' => $conflict_id],
                    ['%s','%s','%d','%s'],
                    ['%d']
                );
            }
            $registered = $wpdb->update($this->devices, $device_data, ['id' => $existing_id]);
        } else {
            $device_data['created_at'] = $now;
            $registered = $wpdb->insert($this->devices, $device_data);
        }
        if ($registered === false) {
            return new WP_Error('ark_push_register_database', 'The device registration could not be saved. Check the ARK Push device table and database permissions.', ['status' => 500]);
        }

        if ($user_id > 0) {
            $this->resume_waiting_order_notifications($user_id);
        }

        return rest_ensure_response([
            'success' => true,
            'account_linked' => ($user_id > 0),
            'user_id' => $user_id,
            'user_email' => $user_email,
            'app_version' => $version,
            'installation_id' => $installation_id,
            'message' => $user_id > 0
                ? 'This app installation is registered and linked to your customer account.'
                : 'This app installation is registered for general sale alerts. Sign in to link it to your order notifications.',
        ]);
    }

    public function api_messages($request) {
        $device = $this->device_from_request($request);
        if (!$device) {
            return new WP_Error('ark_push_device_required', 'This device is not registered. Enable notifications and retry.', ['status' => 401]);
        }

        global $wpdb;
        $user_id = (int) ($device->user_id ?? 0);
        $installed_at = (string) ($device->created_at ?? '');
        $inbox_since = (string) (($device->account_linked_at ?? '') ?: $installed_at);
        $token_hash = hash('sha256', (string) $device->token);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT m.id,m.title,m.body,m.image_url,m.click_url,m.target_type,m.target_id,m.message_type,m.status,m.countdown_end,m.success_count,m.click_count,m.sent_at
             FROM {$this->messages} AS m
             LEFT JOIN {$this->dismissed} AS d ON d.message_id = m.id AND d.token_hash = %s
             WHERE d.id IS NULL AND (
                 (m.recipient_user_id = 0 AND m.status = 'sent' AND m.sent_at IS NOT NULL AND m.sent_at >= %s)
                 OR (
                     m.recipient_user_id = %d
                     AND m.message_type IN ('order_placed','order_status')
                     AND (
                         (m.status = 'sent' AND m.sent_at IS NOT NULL AND m.sent_at >= %s)
                         OR (m.status IN ('queued','sending','waiting_device','failed') AND m.created_at >= %s)
                     )
                 )
             )
             ORDER BY m.id DESC LIMIT 6",
            $token_hash,
            $inbox_since,
            $user_id,
            $installed_at,
            $installed_at
        ), ARRAY_A);
        $items = [];
        foreach ((array) $rows as $row) {
            $row['id'] = (int) $row['id'];
            $row['success_count'] = (int) $row['success_count'];
            $row['click_count'] = (int) $row['click_count'];
            $row['countdown_end'] = $row['countdown_end'] ? gmdate(DATE_ATOM, strtotime($row['countdown_end'] . ' UTC')) : '';
            $items[] = $row;
        }
        return rest_ensure_response(['messages' => $items]);
    }

    public function api_delete_message($request) {
        $device = $this->device_from_request($request);
        if (!$device) {
            return new WP_Error('ark_push_device_required', 'This device is not registered.', ['status' => 401]);
        }

        $message_id = absint($request->get_param('id'));
        if (!$message_id) {
            return new WP_Error('ark_push_message_invalid', 'A valid notification ID is required.', ['status' => 400]);
        }

        global $wpdb;
        $message = $wpdb->get_row($wpdb->prepare(
            "SELECT id,recipient_user_id FROM {$this->messages} WHERE id = %d LIMIT 1",
            $message_id
        ));
        if (!$message) {
            return new WP_Error('ark_push_message_missing', 'Notification not found.', ['status' => 404]);
        }
        $recipient = (int) ($message->recipient_user_id ?? 0);
        $device_user = (int) ($device->user_id ?? 0);
        if ($recipient > 0 && $recipient !== $device_user) {
            return new WP_Error('ark_push_message_forbidden', 'You cannot delete another customer notification.', ['status' => 403]);
        }

        $saved = $wpdb->replace($this->dismissed, [
            'message_id' => $message_id,
            'token_hash' => hash('sha256', (string) $device->token),
            'dismissed_at' => current_time('mysql', true),
        ], ['%d','%s','%s']);
        if ($saved === false) {
            return new WP_Error('ark_push_delete_failed', 'Notification could not be deleted.', ['status' => 500]);
        }
        return rest_ensure_response(['success' => true, 'deleted_id' => $message_id]);
    }

    public function api_diagnostics($request) {
        $device = $this->device_from_request($request);
        if (!$device) {
            return new WP_Error('ark_push_device_required', 'This device token is not registered. Open GlowHit and retry registration.', ['status' => 401]);
        }

        global $wpdb;
        $user_id = (int) ($device->user_id ?? 0);
        $active_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->devices} WHERE active = 1");
        $active_linked = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->devices} WHERE active = 1 AND user_id > 0");
        $active_unlinked = max(0, $active_total - $active_linked);

        $installed_at = (string) ($device->created_at ?? '');
        $inbox_since = (string) (($device->account_linked_at ?? '') ?: $installed_at);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id,title,message_type,status,target_count,success_count,failure_count,last_error,created_at,sent_at
             FROM {$this->messages}
             WHERE (
                 (recipient_user_id = 0 AND (created_at >= %s OR sent_at >= %s))
                 OR (recipient_user_id = %d AND (created_at >= %s OR sent_at >= %s))
             )
             ORDER BY id DESC LIMIT 8",
            $inbox_since,
            $inbox_since,
            $user_id,
            $inbox_since,
            $inbox_since
        ), ARRAY_A);

        $messages = [];
        foreach ((array) $rows as $row) {
            $messages[] = [
                'id' => (int) $row['id'],
                'title' => (string) $row['title'],
                'type' => (string) $row['message_type'],
                'status' => (string) $row['status'],
                'target_count' => (int) $row['target_count'],
                'success_count' => (int) $row['success_count'],
                'failure_count' => (int) $row['failure_count'],
                'last_error' => (string) $row['last_error'],
                'created_at' => (string) $row['created_at'],
                'sent_at' => (string) $row['sent_at'],
            ];
        }

        $next_cron = wp_next_scheduled(self::CRON_HOOK);
        return rest_ensure_response([
            'success' => true,
            'device' => [
                'id' => (int) $device->id,
                'installation_id' => (string) ($device->installation_id ?? ''),
                'platform' => (string) $device->platform,
                'app_version' => (string) $device->app_version,
                'last_seen' => (string) $device->last_seen,
                'account_linked' => ($user_id > 0),
                'user_id' => $user_id,
                'user_email' => (string) ($device->user_email ?? ''),
            ],
            'server' => [
                'firebase_configured' => $this->firebase_ready(),
                'wp_cron_disabled' => (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON),
                'next_push_cron' => $next_cron ? gmdate(DATE_ATOM, $next_cron) : '',
            ],
            'devices' => [
                'active_total' => $active_total,
                'active_account_linked' => $active_linked,
                'active_unlinked' => $active_unlinked,
            ],
            'recent_messages' => $messages,
        ]);
    }

    public function api_click($request) {
        $device = $this->device_from_request($request);
        if (!$device) return new WP_Error('ark_push_device_required', 'This device is not registered.', ['status' => 401]);

        $message_id = absint($request->get_param('message_id'));
        if (!$message_id) return new WP_Error('ark_push_message_required', 'Notification ID is required.', ['status' => 400]);

        global $wpdb;
        $message = $wpdb->get_row($wpdb->prepare(
            "SELECT id,click_url,recipient_user_id FROM {$this->messages} WHERE id = %d AND status IN ('sent','sending')",
            $message_id
        ));
        if (!$message) return new WP_Error('ark_push_message_missing', 'Notification was not found.', ['status' => 404]);
        if ((int) $message->recipient_user_id > 0
            && (int) $message->recipient_user_id !== (int) ($device->user_id ?? 0)) {
            return new WP_Error('ark_push_message_forbidden', 'This notification belongs to a different customer account.', ['status' => 403]);
        }

        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$this->clicks} (message_id,token_hash,clicked_at) VALUES (%d,%s,%s)",
            $message_id, $device->token_hash, current_time('mysql', true)
        ));
        if ($inserted === 1) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->messages} SET click_count = click_count + 1 WHERE id = %d",
                $message_id
            ));
        }

        return rest_ensure_response([
            'success' => true,
            'click_recorded' => ($inserted === 1),
            'click_url' => (string) $message->click_url,
        ]);
    }

    public function api_unregister($request) {
        $device = $this->device_from_request($request);
        if (!$device) return rest_ensure_response(['success' => true]);
        global $wpdb;
        $wpdb->update($this->devices, ['active' => 0, 'updated_at' => current_time('mysql', true)], ['id' => (int) $device->id], ['%d','%s'], ['%d']);
        return rest_ensure_response(['success' => true]);
    }

    public function send_test_notification() {
        if (!current_user_can('manage_options')) wp_die('You are not allowed to send test notifications.');
        check_admin_referer('ark_push_send_test');

        if (!$this->firebase_ready()) {
            wp_safe_redirect(add_query_arg(['page' => 'ark-app-notifications', 'ark_error' => 'firebase'], admin_url('admin.php')));
            exit;
        }

        global $wpdb;
        $target_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->devices} WHERE active = 1");
        if ($target_count < 1) {
            wp_safe_redirect(add_query_arg(['page' => 'ark-app-notifications', 'ark_error' => 'devices'], admin_url('admin.php')));
            exit;
        }

        $inserted = $wpdb->insert($this->messages, [
            'title' => 'GlowHit test notification',
            'body' => 'Your phone is connected to GlowHit push notifications. This is a test alert.',
            'image_url' => '',
            'click_url' => 'ark://notifications',
            'target_type' => 'none',
            'target_id' => 0,
            'message_type' => 'test',
            'recipient_user_id' => 0,
            'countdown_end' => null,
            'status' => 'queued',
            'target_count' => $target_count,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql', true),
        ], ['%s','%s','%s','%s','%s','%d','%s','%d','%s','%s','%d','%d','%s']);

        if (!$inserted) {
            wp_safe_redirect(add_query_arg(['page' => 'ark-app-notifications', 'ark_error' => 'database'], admin_url('admin.php')));
            exit;
        }

        $message_id = (int) $wpdb->insert_id;
        // Send immediately so the test also works when WP-Cron is disabled.
        $this->send_batch($message_id);
        $delivery = $wpdb->get_row($wpdb->prepare(
            "SELECT status FROM {$this->messages} WHERE id = %d",
            $message_id
        ));
        wp_safe_redirect(add_query_arg([
            'page' => 'ark-app-notifications',
            'ark_test' => $message_id,
            'ark_delivery' => $delivery ? sanitize_key($delivery->status) : 'queued',
        ], admin_url('admin.php')));
        exit;
    }

    public function deactivate_device() {
        if (!current_user_can('manage_options')) wp_die('You are not allowed to manage app devices.');
        $device_id = absint($_POST['device_id'] ?? 0);
        if (!$device_id) wp_die('Invalid app device.');
        check_admin_referer('ark_push_deactivate_device_' . $device_id);

        global $wpdb;
        $wpdb->update(
            $this->devices,
            ['active' => 0, 'updated_at' => current_time('mysql', true)],
            ['id' => $device_id],
            ['%d','%s'],
            ['%d']
        );
        wp_safe_redirect(add_query_arg([
            'page' => 'ark-app-notifications',
            'ark_device_removed' => $device_id,
        ], admin_url('admin.php')));
        exit;
    }

    private function format_date($value) {
        if (!$value) return '—';
        $timestamp = strtotime($value . ' UTC');
        if (!$timestamp) return $value;
        return wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
    }

    public function render_admin() {
        if (!current_user_can('manage_options')) return;
        global $wpdb;
        $s = $this->settings();
        $service_saved = !empty($s['service_account_json']);
        $firebase_ready = $this->firebase_ready($s);
        $device_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->devices} WHERE active = 1");
        $linked_device_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->devices} WHERE active = 1 AND user_id > 0");
        $devices = $wpdb->get_results(
            "SELECT id,token_hash,platform,app_version,user_id,user_email,last_seen
             FROM {$this->devices} WHERE active = 1 ORDER BY last_seen DESC,id DESC LIMIT 50"
        );
        $messages = $wpdb->get_results("SELECT * FROM {$this->messages} ORDER BY id DESC LIMIT 5");

        echo '<div class="wrap"><h1>ARK App Push Notifications</h1>';
        if (isset($_GET['ark_saved'])) echo '<div class="notice notice-success"><p>Firebase settings saved.</p></div>';
        if (isset($_GET['ark_processed'])) {
            $delivery = sanitize_key(wp_unslash($_GET['ark_delivery'] ?? 'queued'));
            if ($delivery === 'scheduled') {
                echo '<div class="notice notice-success"><p>Notification scheduled successfully. WordPress will send it at the selected site-local date and time.</p></div>';
            } elseif ($delivery === 'sent') {
                echo '<div class="notice notice-success"><p>FCM accepted the first delivery batch. Check the history row below for device counts and any partial failures.</p></div>';
            } elseif ($delivery === 'failed') {
                echo '<div class="notice notice-error"><p>The push failed. Read the Last error column in notification history below; it contains Firebase\'s response.</p></div>';
            } else {
                echo '<div class="notice notice-warning"><p>The notification is still queued. Check WP-Cron and the Last error column below.</p></div>';
            }
        }
        if (isset($_GET['ark_test'])) {
            $delivery = sanitize_key(wp_unslash($_GET['ark_delivery'] ?? 'queued'));
            $notice_class = $delivery === 'sent' ? 'notice-success' : ($delivery === 'failed' ? 'notice-error' : 'notice-warning');
            echo '<div class="notice ' . esc_attr($notice_class) . '"><p>Test notification #' . esc_html((string) absint($_GET['ark_test'])) . ' status: <strong>' . esc_html($delivery) . '</strong>. Check the phone notification shade and in-app Notifications. If failed, read Last error below.</p></div>';
        }
        if (isset($_GET['ark_device_removed'])) echo '<div class="notice notice-success"><p>Device registration deactivated. It will no longer receive notifications.</p></div>';
        if (isset($_GET['ark_schedule_cancelled'])) echo '<div class="notice notice-success"><p>Notification schedule cancelled. No future messages from this schedule will be sent.</p></div>';
        if (isset($_GET['ark_error'])) {
            $errors = [
                'service' => 'Service account JSON is invalid. Paste the full Firebase service-account JSON.',
                'firebase' => 'Complete Firebase configuration and service account settings before sending.',
                'fields' => 'Notification title and message are required.',
                'countdown' => 'Countdown end must be a valid future date and time.',
                'devices' => 'No app installations are registered yet. Install the latest GlowHit APK (1.0.14 or newer), open it once with internet enabled, allow notifications if Android asks, then refresh this page. If permission was already allowed, the app will register its FCM token silently.',
                'database' => 'The notification campaign could not be saved.',
                'target' => 'Choose a valid published product, page, category or external link destination.',
                'schedule' => 'Choose a valid future date and time. The schedule uses your WordPress site timezone.',
                'schedule_save' => 'WordPress could not save this scheduled event. Check that WP-Cron is enabled.',
                'schedule_cancel' => 'The notification schedule could not be cancelled.',
            ];
            $key = sanitize_key(wp_unslash($_GET['ark_error']));
            echo '<div class="notice notice-error"><p>' . esc_html($errors[$key] ?? 'An error occurred.') . '</p></div>';
        }

        echo '<p>Send sale alerts to registered GlowHit / ARK Native Android app installations. This sends to your app users only; it cannot send notifications to unrelated apps installed on a phone.</p>';
        echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:16px 0">';
        echo '<div class="card" style="padding:14px;min-width:180px"><strong>Active app devices</strong><h2>' . esc_html((string) $device_count) . '</h2></div>';
        echo '<div class="card" style="padding:14px;min-width:180px"><strong>Linked to customer account</strong><h2>' . esc_html((string) $linked_device_count) . '</h2></div>';
        echo '<div class="card" style="padding:14px;min-width:180px"><strong>Firebase connection</strong><h2 style="color:' . ($firebase_ready ? '#16834d' : '#b7791f') . '">' . ($firebase_ready ? 'Configured' : 'Setup required') . '</h2></div>';
        echo '</div>';

        echo '<h2>Registered devices</h2>';
        echo '<p class="description">Each row is one unique Firebase installation token. A phone may create a new record after app data is cleared or Firebase rotates its token. Check app version and last seen before deactivating an old installation.</p>';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Platform / App version</th><th>Customer account link</th><th>Last seen</th><th>Token fingerprint</th><th>Action</th></tr></thead><tbody>';
        if (!$devices) {
            echo '<tr><td colspan="6">No active app device registrations.</td></tr>';
        } else {
            foreach ($devices as $device) {
                $device_id = (int) $device->id;
                $account = (int) $device->user_id > 0
                    ? (string) $device->user_email . ' (#' . (int) $device->user_id . ')'
                    : 'Not linked — general campaigns only';
                echo '<tr>';
                echo '<td>#' . esc_html((string) $device_id) . '</td>';
                echo '<td>' . esc_html((string) $device->platform) . ' / ' . esc_html((string) $device->app_version) . '</td>';
                echo '<td>' . esc_html($account) . '</td>';
                echo '<td>' . esc_html($this->format_date($device->last_seen)) . '</td>';
                echo '<td><code>…' . esc_html(substr((string) $device->token_hash, -10)) . '</code></td>';
                echo '<td><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'Stop sending notifications to this installation?\')">';
                echo '<input type="hidden" name="action" value="ark_push_deactivate_device"><input type="hidden" name="device_id" value="' . esc_attr((string) $device_id) . '">';
                wp_nonce_field('ark_push_deactivate_device_' . $device_id);
                echo '<button class="button button-small">Deactivate</button></form></td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';

        if ($device_count < 1) {
            echo '<div class="notice notice-warning inline"><p><strong>No app device is registered yet.</strong> Install and open GlowHit APK 1.0.14 or newer on the phone, keep internet enabled, and allow notifications when Android prompts. The app sends its FCM token to this site automatically. Refresh this page after opening the app. If Android notifications are already ON, no new permission popup will appear, but token registration should still run.</p></div>';
        }

        echo '<h2>1. Firebase connection</h2>';
        echo '<p>Create an Android app in Firebase Console using package name <code>com.ark.glowhit</code>. Enable Firebase Cloud Messaging and create a service account key in Google Cloud IAM. The app uses the public Firebase app configuration below; the private service-account JSON stays on this WordPress server.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('ark_push_save_settings');
        echo '<input type="hidden" name="action" value="ark_push_save_settings">';
        echo '<table class="form-table"><tbody>';
        $fields = [
            'api_key' => ['Firebase Web API Key', 'text'],
            'app_id' => ['Firebase App ID (Android app)', 'text'],
            'messaging_sender_id' => ['Firebase Messaging Sender ID', 'text'],
            'project_id' => ['Firebase Project ID', 'text'],
            'storage_bucket' => ['Storage Bucket (optional)', 'text'],
        ];
        foreach ($fields as $key => $field) {
            echo '<tr><th><label for="ark-firebase-' . esc_attr($key) . '">' . esc_html($field[0]) . '</label></th><td><input class="regular-text" id="ark-firebase-' . esc_attr($key) . '" name="firebase[' . esc_attr($key) . ']" type="text" value="' . esc_attr($s[$key] ?? '') . '"></td></tr>';
        }
        echo '<tr><th><label for="ark-service-account">Firebase Service Account JSON</label></th><td><textarea class="large-text code" rows="7" id="ark-service-account" name="service_account_json" placeholder="' . ($service_saved ? 'Saved securely. Leave blank to keep the existing key.' : 'Paste the complete service account JSON here.') . '"></textarea><p class="description">' . ($service_saved ? 'A service account key is saved on this server. Leave this blank to retain it.' : 'Download a private JSON key from Google Cloud → IAM & Admin → Service Accounts. Never paste this key into Flutter or publish it in GitHub.') . '</p></td></tr>';
        echo '</tbody></table><p><button class="button button-primary">Save Firebase Settings</button></p></form>';

        echo '<hr><h2>2. Delivery test</h2>';
        echo '<p>Send a real FCM test alert immediately to all active app devices. This creates a normal inbox record and also tests the Android notification shade.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('ark_push_send_test');
        echo '<input type="hidden" name="action" value="ark_push_send_test">';
        echo '<button class="button button-secondary" ' . (!$firebase_ready || $device_count < 1 ? 'disabled' : '') . '>Send test notification to ' . esc_html((string) $device_count) . ' devices</button>';
        echo '</form>';

        echo '<hr><h2>3. Create sale notification</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('ark_push_send_campaign');
        echo '<input type="hidden" name="action" value="ark_push_send_campaign">';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="ark-push-title">Notification title</label></th><td><input required maxlength="150" class="regular-text" id="ark-push-title" name="title" type="text" placeholder="Big Sale is Live!"></td></tr>';
        echo '<tr><th><label for="ark-push-body">Message</label></th><td><textarea required maxlength="500" rows="3" class="large-text" id="ark-push-body" name="body" placeholder="Extra savings on selected products. Tap to shop."></textarea></td></tr>';
        echo '<tr><th><label for="ark-push-image-url">Sale image</label></th><td><input class="large-text" id="ark-push-image-url" name="image_url" type="url" placeholder="https://..."><button class="button" id="ark-push-pick-image" style="margin-top:8px">Choose from Media Library</button><br><img id="ark-push-image-preview" alt="" style="display:none;max-width:260px;max-height:150px;margin-top:10px;object-fit:contain"></td></tr>';
        $pages = get_pages(['post_status' => 'publish', 'number' => 300, 'sort_column' => 'post_title']);
        $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'number' => 500]);
        echo '<tr><th><label for="ark-push-target-type">Open destination in GlowHit</label></th><td>';
        echo '<select id="ark-push-target-type" name="target_type">';
        echo '<option value="none">No destination</option><option value="product">WooCommerce product</option>';
        echo '<option value="page">WordPress page</option><option value="category">Product category</option>';
        echo '<option value="external">External website / app link</option></select>';
        echo '<input type="hidden" id="ark-push-target-id" name="target_id" value="">';
        echo '<p class="description">Select a product, page or category to open inside the GlowHit app. External links open outside the app.</p></td></tr>';
        echo '<tr id="ark-push-product-panel" class="ark-push-target-panel"><th><label for="ark-push-product-search">Select product</label></th><td>';
        echo '<input type="search" class="regular-text" id="ark-push-product-search" placeholder="Type at least 2 letters of a product name..." autocomplete="off">';
        echo '<div id="ark-push-product-results" style="display:none;max-width:520px;max-height:220px;overflow:auto;background:#fff;border:1px solid #dcdcde;padding:8px;margin-top:6px"></div>';
        echo '<p id="ark-push-selected-product" class="description"></p></td></tr>';
        echo '<tr id="ark-push-page-panel" class="ark-push-target-panel"><th><label for="ark-push-page-select">Select page</label></th><td>';
        echo '<select id="ark-push-page-select"><option value="">Choose a published page...</option>';
        foreach ($pages as $page) echo '<option value="' . esc_attr((string) $page->ID) . '">' . esc_html($page->post_title) . ' (#' . esc_html((string) $page->ID) . ')</option>';
        echo '</select></td></tr>';
        echo '<tr id="ark-push-category-panel" class="ark-push-target-panel"><th><label for="ark-push-category-select">Select product category</label></th><td>';
        echo '<select id="ark-push-category-select"><option value="">Choose a category...</option>';
        if (!is_wp_error($categories)) {
            foreach ($categories as $category) echo '<option value="' . esc_attr((string) $category->term_id) . '">' . esc_html($category->name) . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr id="ark-push-external-panel" class="ark-push-target-panel"><th><label for="ark-push-click-url">External link</label></th><td>';
        echo '<input class="large-text" id="ark-push-click-url" name="click_url" type="text" placeholder="https://example.com or whatsapp://...">';
        echo '<p class="description">Use only for an external website/app. Store products, pages and categories should be selected above.</p></td></tr>';
        echo '<tr><th><label for="ark-push-countdown">Offer countdown (optional)</label></th><td><input id="ark-push-countdown" name="countdown_end" type="datetime-local"><p class="description">Leave blank for no countdown. When selected, the app notification inbox shows the time remaining until this deadline.</p></td></tr>';
        echo '<tr><th><label for="ark-push-schedule-mode">Delivery schedule</label></th><td><select id="ark-push-schedule-mode" name="schedule_mode"><option value="now">Send now</option><option value="once">Schedule once</option><option value="weekly">Repeat weekly</option></select><p class="description">Choose an exact future date and time, or set a weekly message. Weekly schedules repeat until you cancel them from history.</p></td></tr>';
        echo '<tr class="ark-push-schedule-row" style="display:none"><th><label for="ark-push-schedule-date">Schedule date</label></th><td><input id="ark-push-schedule-date" name="schedule_date" type="date"><p id="ark-push-schedule-day" class="description">Choose a date to see the weekday.</p></td></tr>';
        echo '<tr class="ark-push-schedule-row" style="display:none"><th><label for="ark-push-schedule-time">Schedule time</label></th><td><input id="ark-push-schedule-time" name="schedule_time" type="time"><p class="description">Site timezone: <strong>' . esc_html(wp_timezone()->getName()) . '</strong>. Weekly sends repeat on the weekday selected above.</p><p id="ark-push-schedule-help" class="description">Sends once on the selected date and time.</p></td></tr>';
        echo '</tbody></table>';
        echo '<p><button id="ark-push-submit" class="button button-primary button-hero" ' . (!$firebase_ready ? 'disabled' : '') . '>Send notification now</button></p>';
        if ($device_count < 1) echo '<p class="description">No devices are active now. A scheduled notification will target active devices at its delivery time.</p>';
        echo '<p class="description">Automatic delivery uses WordPress Cron. For accurate delivery at the selected time, configure a server cron to run wp-cron.php every 1–5 minutes.</p>';
        if (!$firebase_ready) echo '<p><strong>Complete Firebase setup above to enable sending.</strong></p>';
        echo '</form>';

        echo '<hr><h2>4. Notification history and click reports</h2>';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Notification</th><th>Destination</th><th>Status</th><th>Schedule</th><th>Sent / Target</th><th>Failed</th><th>Clicks</th><th>Click rate</th><th>Last error</th><th>Created</th><th>Action</th></tr></thead><tbody>';
        if (!$messages) {
            echo '<tr><td colspan="12">No notification history yet.</td></tr>';
        } else {
            foreach ($messages as $m) {
                $rate = ((int) $m->success_count > 0) ? round(((int) $m->click_count / (int) $m->success_count) * 100, 1) : 0;
                echo '<tr>';
                echo '<td>#' . esc_html((string) $m->id) . '</td>';
                echo '<td><strong>' . esc_html($m->title) . '</strong><br><span style="color:#646970">' . esc_html(wp_trim_words($m->body, 12)) . '</span></td>';
                echo '<td>' . esc_html(ucfirst((string) ($m->target_type ?? 'none'))) . ($m->target_id ? ' #' . esc_html((string) $m->target_id) : '') . '</td>';
                echo '<td>' . esc_html(ucwords(str_replace('_', ' ', (string) $m->status))) . '</td>';
                if ($m->schedule_type === 'weekly' && !empty($m->scheduled_at)) {
                    $day_names = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                    $day_name = $day_names[(int) $m->schedule_day] ?? 'Weekly';
                    echo '<td>Every ' . esc_html($day_name) . '<br><small>Next: ' . esc_html($this->format_date($m->scheduled_at)) . '</small></td>';
                } elseif ($m->schedule_type === 'weekly_sent') {
                    echo '<td>Weekly • sent ' . esc_html($this->format_date($m->sent_at)) . '</td>';
                } elseif ($m->schedule_type === 'once' && !empty($m->scheduled_at)) {
                    echo '<td>Once<br><small>' . esc_html($this->format_date($m->scheduled_at)) . '</small></td>';
                } else {
                    echo '<td>' . ($m->schedule_type === 'stopped' ? 'Weekly • stopped' : ($m->schedule_type === 'cancelled' ? 'Cancelled' : 'Immediate')) . '</td>';
                }
                echo '<td>' . esc_html((string) $m->success_count) . ' / ' . esc_html((string) $m->target_count) . '</td>';
                echo '<td>' . esc_html((string) $m->failure_count) . '</td>';
                echo '<td><strong>' . esc_html((string) $m->click_count) . '</strong></td>';
                echo '<td>' . esc_html((string) $rate) . '%</td>';
                echo '<td>' . (!empty($m->last_error) ? '<span style="color:#b32d2e">' . esc_html($m->last_error) . '</span>' : '—') . '</td>';
                echo '<td>' . esc_html($this->format_date($m->created_at)) . '</td>';
                echo '<td>';
                if ($m->status === 'scheduled' || in_array($m->schedule_type, ['weekly','weekly_sent'], true)) {
                    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'Cancel this notification schedule?\')">';
                    echo '<input type="hidden" name="action" value="ark_push_cancel_schedule"><input type="hidden" name="message_id" value="' . esc_attr((string) $m->id) . '">';
                    wp_nonce_field('ark_push_cancel_schedule_' . (int) $m->id);
                    echo '<button class="button button-small">Cancel schedule</button></form>';
                } else {
                    echo '—';
                }
                echo '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        echo '<p class="description">Showing the 5 latest records. Click counts are unique per notification and registered device. Delivery counts are the FCM send responses returned by Google, not proof that a person viewed the notification.</p>';
        echo '</div>';
    }
}

register_activation_hook(__FILE__, ['ARK_App_Push_Notifications', 'activate']);
new ARK_App_Push_Notifications();
