<?php
/**
 * Plugin Name: ARK WordPress GitHub Bridge
 * Description: Safely deploys approved WordPress theme and plugin files from a private GitHub repository using GitHub API polling and signed webhooks. No cPanel Terminal required.
 * Version: 1.1.0
 * Author: ARK
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) { exit; }

final class ARK_WordPress_GitHub_Bridge {
    const VERSION = '1.1.0';
    const OPTION = 'ark_wpgb_settings';
    const STATUS = 'ark_wpgb_status';
    const CRON_HOOK = 'ark_wpgb_poll_github';
    const REST_NS = 'ark-github-bridge/v1';
    const MAX_FILE_BYTES = 5242880; // 5 MiB
    const MAX_FILES_PER_RUN = 40;

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action(self::CRON_HOOK, array(__CLASS__, 'scheduled_sync'));
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
        add_action('admin_post_ark_wpgb_sync', array(__CLASS__, 'handle_manual_sync'));
        add_action('admin_post_ark_wpgb_export', array(__CLASS__, 'handle_export'));
        add_action('admin_post_ark_wpgb_test', array(__CLASS__, 'handle_test_connection'));
    }

    public static function activate() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 120, 'ark_wpgb_five_minutes', self::CRON_HOOK);
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    public static function cron_schedules($schedules) {
        $schedules['ark_wpgb_five_minutes'] = array('interval' => 300, 'display' => 'Every five minutes (ARK GitHub Bridge)');
        return $schedules;
    }

    private static function defaults() {
        return array(
            'owner' => '', 'repo' => '', 'branch' => 'main', 'token' => '', 'webhook_secret' => '',
            'enabled' => 0, 'auto_sync' => 1,
        );
    }

    private static function settings() {
        return wp_parse_args(get_option(self::OPTION, array()), self::defaults());
    }

    public static function register_settings() {
        register_setting('ark_wpgb_group', self::OPTION, array(__CLASS__, 'sanitize_settings'));
    }

    public static function sanitize_settings($input) {
        $old = self::settings();
        $out = self::defaults();
        $out['owner'] = isset($input['owner']) ? sanitize_user($input['owner'], true) : '';
        $out['repo'] = isset($input['repo']) ? preg_replace('/[^A-Za-z0-9_.-]/', '', $input['repo']) : '';
        $out['branch'] = isset($input['branch']) ? preg_replace('/[^A-Za-z0-9_\.\/-]/', '', $input['branch']) : 'main';
        $out['token'] = !empty($input['token']) ? trim(sanitize_text_field($input['token'])) : $old['token'];
        $out['webhook_secret'] = !empty($input['webhook_secret']) ? trim(sanitize_text_field($input['webhook_secret'])) : $old['webhook_secret'];
        $out['enabled'] = !empty($input['enabled']) ? 1 : 0;
        $out['auto_sync'] = !empty($input['auto_sync']) ? 1 : 0;
        return $out;
    }

    public static function admin_menu() {
        add_menu_page('ARK GitHub Bridge', 'ARK GitHub Bridge', 'manage_options', 'ark-github-bridge', array(__CLASS__, 'settings_page'), 'dashicons-github', 58);
    }

    public static function settings_page() {
        if (!current_user_can('manage_options')) { return; }
        $s = self::settings();
        $status = get_option(self::STATUS, array());
        $webhook = rest_url(self::REST_NS . '/webhook');
        ?>
        <div class="wrap" style="max-width:1050px">
            <h1>ARK WordPress GitHub Bridge</h1>
            <p>Private GitHub repository se approved Theme/Plugin code ko is WordPress hosting ki real files mein deploy karein. cPanel Terminal ki zaroorat nahi.</p>
            <div style="background:#fff;border:1px solid #dcdcde;border-left:4px solid #2271b1;padding:14px 18px;margin:18px 0">
                <strong>Safety:</strong> Pehle backup banta hai. Uploads, wp-config.php, database, customer/order data aur secret files sync nahi hote. Remote file deletion v1 mein disabled hai.
            </div>
            <h2>1. GitHub Connection</h2>
            <form method="post" action="options.php">
                <?php settings_fields('ark_wpgb_group'); ?>
                <table class="form-table" role="presentation">
                    <tr><th><label for="ark-owner">GitHub Owner</label></th><td><input id="ark-owner" class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[owner]" value="<?php echo esc_attr($s['owner']); ?>" placeholder="arshadzen023-byte" required></td></tr>
                    <tr><th><label for="ark-repo">Repository</label></th><td><input id="ark-repo" class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[repo]" value="<?php echo esc_attr($s['repo']); ?>" placeholder="pluginstore-wordpress" required></td></tr>
                    <tr><th><label for="ark-branch">Branch</label></th><td><input id="ark-branch" class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[branch]" value="<?php echo esc_attr($s['branch']); ?>" required><p class="description">Aapki repository ka default branch, aam taur par main.</p></td></tr>
                    <tr><th><label for="ark-token">GitHub Fine-grained Token</label></th><td><input id="ark-token" type="password" autocomplete="new-password" class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[token]" value="" placeholder="<?php echo $s['token'] ? 'Token saved — blank rakhein to same rahega' : 'github_pat_...'; ?>"><p class="description">Import/export ke liye Contents: Read and write aur Metadata: Read-only permission dein. Token WordPress database mein admin-only setting ke roop mein save hota hai. Isse chat mein share na karein.</p></td></tr>
                    <tr><th><label for="ark-secret">Webhook Secret</label></th><td><input id="ark-secret" type="password" autocomplete="new-password" class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[webhook_secret]" value="" placeholder="<?php echo $s['webhook_secret'] ? 'Secret saved — blank rakhein to same rahega' : 'Long random secret'; ?>"><p class="description">GitHub webhook signature verify karne ke liye. Polling bhi available hai.</p></td></tr>
                    <tr><th>Deployment</th><td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[enabled]" value="1" <?php checked($s['enabled'], 1); ?>> Enable GitHub deployment</label><br><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[auto_sync]" value="1" <?php checked($s['auto_sync'], 1); ?>> Automatic check every 5 minutes (WP-Cron; traffic-dependent)</label></td></tr>
                </table>
                <?php submit_button('Save GitHub Settings'); ?>
            </form>
            <hr><h2>2. GitHub Webhook (recommended for faster updates)</h2>
            <p><strong>Payload URL:</strong> <code><?php echo esc_html($webhook); ?></code></p>
            <p>GitHub Repository → Settings → Webhooks → Add webhook. Content type <code>application/json</code>, Secret wahi jo upar set kiya, event <strong>Just the push event</strong>. Save karne ke baad GitHub push par deploy trigger karega.</p>
            <hr><h2>3. GitHub Connection Test</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('ark_wpgb_test'); ?><input type="hidden" name="action" value="ark_wpgb_test">
                <?php submit_button('Test GitHub Connection', 'secondary', 'submit', false); ?>
            </form>
            <hr><h2>4. Export Live Files to GitHub</h2>
            <p>One folder path per line. Use paths relative to WordPress content, for example <code>themes/ark-material-commerce</code> or <code>plugins/my-custom-plugin</code>. Export is manual and creates GitHub commits. Review the paths carefully; do not select third-party plugins unless you intend to version their source.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('ark_wpgb_export'); ?><input type="hidden" name="action" value="ark_wpgb_export">
                <textarea name="paths" rows="5" class="large-text code" placeholder="themes/your-theme&#10;plugins/your-custom-plugin&#10;mu-plugins"></textarea>
                <p class="description">Safety limits: selected folders only; no uploads, cache, secrets, database files or deletions; maximum 100 files per export and 5 MiB per file.</p>
                <?php submit_button('Export Selected Live Files to GitHub', 'primary', 'submit', false); ?>
            </form>
            <hr><h2>5. Manual Deploy from GitHub / Status</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('ark_wpgb_sync'); ?><input type="hidden" name="action" value="ark_wpgb_sync">
                <?php submit_button('Run Sync Now', 'primary', 'submit', false); ?>
            </form>
            <p><strong>Last result:</strong> <?php echo esc_html(isset($status['message']) ? $status['message'] : 'Not run yet'); ?></p>
            <p><strong>Last check:</strong> <?php echo esc_html(isset($status['time']) ? $status['time'] : '—'); ?></p>
            <p><strong>Files updated:</strong> <?php echo esc_html(isset($status['updated']) ? (string) $status['updated'] : '0'); ?></p>
            <p><strong>Backup directory:</strong> <code><?php echo esc_html(self::backup_dir()); ?></code></p>
            <hr><h2>6. Repository folder structure</h2>
            <p>Repository ke andar files is tarah rakhein. <code>wp-content/</code> ke andar sirf selected themes, plugins aur mu-plugins sync honge.</p>
            <pre>wp-content/themes/your-theme/...
wp-content/plugins/your-custom-plugin/...
wp-content/mu-plugins/...</pre>
            <p><strong>Important:</strong> Pehle staging par test karein. Is plugin ko production par enable karne se pehle full hosting backup lein. GitHub commit trusted hone par hi deploy karein.</p>
        </div>
        <?php
    }

    public static function register_routes() {
        register_rest_route(self::REST_NS, '/webhook', array('methods' => 'POST', 'callback' => array(__CLASS__, 'webhook'), 'permission_callback' => '__return_true'));
    }

    public static function webhook($request) {
        $s = self::settings();
        if (empty($s['webhook_secret'])) { return new WP_REST_Response(array('ok' => false, 'message' => 'Webhook secret is not configured'), 403); }
        $body = $request->get_body();
        $signature = $request->get_header('x-hub-signature-256');
        $expected = 'sha256=' . hash_hmac('sha256', $body, $s['webhook_secret']);
        if (!$signature || !hash_equals($expected, $signature)) { return new WP_REST_Response(array('ok' => false, 'message' => 'Invalid signature'), 403); }
        $event = $request->get_header('x-github-event');
        if ($event !== 'push') { return new WP_REST_Response(array('ok' => true, 'ignored' => true), 200); }
        if (!wp_next_scheduled(self::CRON_HOOK)) { wp_schedule_single_event(time() + 5, self::CRON_HOOK); }
        return new WP_REST_Response(array('ok' => true, 'queued' => true), 202);
    }

    public static function handle_manual_sync() {
        if (!current_user_can('manage_options')) { wp_die('Permission denied'); }
        check_admin_referer('ark_wpgb_sync');
        self::sync_from_github();
        wp_safe_redirect(add_query_arg('page', 'ark-github-bridge', admin_url('admin.php')));
        exit;
    }

    public static function handle_test_connection() {
        if (!current_user_can('manage_options')) { wp_die('Permission denied'); }
        check_admin_referer('ark_wpgb_test');
        $s = self::settings();
        $result = self::api_get('/repos/' . rawurlencode($s['owner']) . '/' . rawurlencode($s['repo']));
        $message = is_wp_error($result) ? 'GitHub connection failed: ' . $result->get_error_message() : 'GitHub connection successful. Repository: ' . $result['full_name'];
        self::save_status($message, 0);
        wp_safe_redirect(add_query_arg('page', 'ark-github-bridge', admin_url('admin.php')));
        exit;
    }

    public static function handle_export() {
        if (!current_user_can('manage_options')) { wp_die('Permission denied'); }
        check_admin_referer('ark_wpgb_export');
        $s = self::settings();
        if (empty($s['owner']) || empty($s['repo']) || empty($s['branch']) || empty($s['token'])) {
            self::save_status('Export failed: GitHub connection settings are incomplete.', 0);
        } else {
            $raw = isset($_POST['paths']) ? sanitize_textarea_field(wp_unslash($_POST['paths'])) : '';
            $paths = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw)));
            $count = 0; $errors = array();
            foreach ($paths as $path) {
                $path = trim(str_replace('\\', '/', $path), '/');
                if (strpos($path, '../') !== false || (strpos($path, 'themes/') !== 0 && strpos($path, 'plugins/') !== 0 && strpos($path, 'mu-plugins') !== 0)) { $errors[] = 'Blocked path: ' . $path; continue; }
                $base = wp_normalize_path(WP_CONTENT_DIR);
                $dir = wp_normalize_path($base . '/' . $path);
                $real_base = realpath($base); $real_dir = realpath($dir);
                if (!$real_base || !$real_dir || strpos(wp_normalize_path($real_dir), trailingslashit(wp_normalize_path($real_base))) !== 0 || !is_dir($real_dir)) { $errors[] = 'Folder not found or outside wp-content: ' . $path; continue; }
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real_dir, FilesystemIterator::SKIP_DOTS));
                foreach ($iterator as $file) {
                    if (!$file->isFile() || $file->isLink()) { continue; }
                    $local = wp_normalize_path($file->getPathname());
                    $rel = ltrim(substr($local, strlen(wp_normalize_path($base))), '/');
                    $repo_path = 'wp-content/' . $rel;
                    if (!self::allowed_repo_path($repo_path)) { continue; }
                    if ($file->getSize() > self::MAX_FILE_BYTES) { $errors[] = $rel . ': exceeds 5 MiB'; continue; }
                    if ($count >= 100) { $errors[] = 'Export stopped at 100 files. Run again for remaining folders/files.'; break 2; }
                    $content = @file_get_contents($local);
                    if ($content === false) { $errors[] = $rel . ': cannot read'; continue; }
                    $put = self::github_put_file($repo_path, $content, 'Export live file: ' . $rel);
                    if (is_wp_error($put)) { $errors[] = $rel . ': ' . $put->get_error_message(); continue; }
                    $count++;
                }
            }
            $msg = 'Live export finished. Files committed: ' . $count . '; errors: ' . count($errors);
            if ($errors) { $msg .= ' | ' . implode(' | ', array_slice($errors, 0, 8)); }
            self::save_status($msg, $count);
        }
        wp_safe_redirect(add_query_arg('page', 'ark-github-bridge', admin_url('admin.php')));
        exit;
    }

    private static function github_put_file($path, $content, $message) {
        $s = self::settings();
        $endpoint = '/repos/' . rawurlencode($s['owner']) . '/' . rawurlencode($s['repo']) . '/contents/' . implode('/', array_map('rawurlencode', explode('/', $path)));
        $existing = self::api_get($endpoint);
        $body = array('message' => $message, 'content' => base64_encode($content), 'branch' => $s['branch']);
        if (!is_wp_error($existing) && !empty($existing['sha'])) { $body['sha'] = $existing['sha']; }
        elseif (is_wp_error($existing)) {
            $data = $existing->get_error_data();
            if (is_array($data) && isset($data['status']) && (int) $data['status'] !== 404) { return $existing; }
        }
        $url = 'https://api.github.com' . $endpoint;
        $response = wp_remote_request($url, array('method' => 'PUT', 'timeout' => 30, 'redirection' => 0, 'headers' => array(
            'Accept' => 'application/vnd.github+json', 'Authorization' => 'Bearer ' . $s['token'],
            'X-GitHub-Api-Version' => '2022-11-28', 'User-Agent' => 'ARK-WordPress-GitHub-Bridge/' . self::VERSION,
            'Content-Type' => 'application/json'), 'body' => wp_json_encode($body)));
        if (is_wp_error($response)) { return $response; }
        $code = wp_remote_retrieve_response_code($response); $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300) { return new WP_Error('ark_wpgb_put_error', !empty($data['message']) ? $data['message'] : 'GitHub write HTTP ' . $code, array('status' => $code)); }
        return $data;
    }

    private static function api_get($endpoint) {
        $s = self::settings();
        if (empty($s['token'])) { return new WP_Error('ark_wpgb_no_token', 'GitHub token is not configured.'); }
        $url = 'https://api.github.com' . $endpoint;
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'redirection' => 0,
            'headers' => array(
                'Accept' => 'application/vnd.github+json',
                'Authorization' => 'Bearer ' . $s['token'],
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'ARK-WordPress-GitHub-Bridge/' . self::VERSION,
            ),
        ));
        if (is_wp_error($response)) { return $response; }
        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300) {
            $message = is_array($data) && !empty($data['message']) ? $data['message'] : 'GitHub API HTTP ' . $code;
            return new WP_Error('ark_wpgb_api_error', $message, array('status' => $code));
        }
        return $data;
    }

    private static function allowed_repo_path($path) {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (strpos($path, '../') !== false || strpos($path, "\0") !== false) { return false; }
        if (strpos($path, 'wp-content/themes/') !== 0 && strpos($path, 'wp-content/plugins/') !== 0 && strpos($path, 'wp-content/mu-plugins/') !== 0) { return false; }
        $parts = explode('/', strtolower($path));
        $blocked = array('uploads', 'cache', 'upgrade', 'backups', 'backup', '.git', 'node_modules');
        foreach ($parts as $part) { if (in_array($part, $blocked, true)) { return false; } }
        $name = strtolower(basename($path));
        $blocked_files = array('wp-config.php', '.env', '.htaccess', 'php.ini', 'error_log', 'debug.log', 'composer.lock');
        if (in_array($name, $blocked_files, true)) { return false; }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $allowed_ext = array('php','css','js','json','xml','txt','md','html','htm','scss','svg','png','jpg','jpeg','gif','webp','ico','woff','woff2','ttf','otf','eot','map','yml','yaml');
        return in_array($ext, $allowed_ext, true);
    }

    private static function local_path_for_repo_path($repo_path) {
        $prefix = 'wp-content/';
        if (strpos($repo_path, $prefix) !== 0) { return false; }
        $relative = substr($repo_path, strlen($prefix));
        $base = wp_normalize_path(WP_CONTENT_DIR);
        $target = wp_normalize_path($base . '/' . $relative);
        if (strpos($target, trailingslashit($base)) !== 0) { return false; }
        return $target;
    }

    private static function backup_dir() {
        return trailingslashit(dirname(rtrim(ABSPATH, '/\\'))) . 'ark-github-bridge-backups';
    }

    private static function backup_file($file, $relative) {
        $dir = self::backup_dir();
        if (!is_dir($dir) && !wp_mkdir_p($dir)) { return new WP_Error('ark_wpgb_backup_dir', 'Could not create private backup directory.'); }
        if (!file_exists($dir . '/.htaccess')) { @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n"); }
        if (!file_exists($dir . '/index.php')) { @file_put_contents($dir . '/index.php', "<?php // Silence is golden.\n"); }
        $safe_relative = preg_replace('/[^A-Za-z0-9._\/-]/', '_', $relative);
        $dest = trailingslashit($dir) . gmdate('Ymd-His') . '/' . $safe_relative;
        $parent = dirname($dest);
        if (!is_dir($parent) && !wp_mkdir_p($parent)) { return new WP_Error('ark_wpgb_backup_path', 'Could not create backup subdirectory.'); }
        if (!@copy($file, $dest)) { return new WP_Error('ark_wpgb_backup_copy', 'Could not back up ' . $relative); }
        return true;
    }

    private static function fetch_blob($sha) {
        $blob = self::api_get('/repos/' . rawurlencode(self::settings()['owner']) . '/' . rawurlencode(self::settings()['repo']) . '/git/blobs/' . rawurlencode($sha));
        if (is_wp_error($blob)) { return $blob; }
        if (empty($blob['content']) || empty($blob['encoding']) || $blob['encoding'] !== 'base64') { return new WP_Error('ark_wpgb_blob_format', 'Unsupported GitHub blob encoding.'); }
        $content = base64_decode(preg_replace('/\s+/', '', $blob['content']), true);
        if ($content === false) { return new WP_Error('ark_wpgb_blob_decode', 'Could not decode GitHub file content.'); }
        if (strlen($content) > self::MAX_FILE_BYTES) { return new WP_Error('ark_wpgb_file_too_large', 'File exceeds 5 MiB limit.'); }
        return $content;
    }

    public static function scheduled_sync() {
        $s = self::settings();
        if (empty($s['auto_sync'])) { return; }
        self::sync_from_github();
    }

    public static function sync_from_github() {
        $s = self::settings();
        if (empty($s['enabled']) || empty($s['owner']) || empty($s['repo']) || empty($s['branch']) || empty($s['token'])) {
            self::save_status('Not configured or deployment disabled.', 0);
            return;
        }
        $owner = rawurlencode($s['owner']);
        $repo = rawurlencode($s['repo']);
        $branch = rawurlencode($s['branch']);
        $tree = self::api_get('/repos/' . $owner . '/' . $repo . '/git/trees/' . $branch . '?recursive=1');
        if (is_wp_error($tree)) { self::save_status('GitHub error: ' . $tree->get_error_message(), 0); return; }
        if (empty($tree['tree']) || !is_array($tree['tree'])) { self::save_status('Repository tree is empty or unavailable.', 0); return; }

        $updated = 0; $skipped = 0; $errors = array(); $processed = 0;
        foreach ($tree['tree'] as $item) {
            if (empty($item['type']) || $item['type'] !== 'blob' || empty($item['path']) || empty($item['sha'])) { continue; }
            $repo_path = $item['path'];
            if (!self::allowed_repo_path($repo_path)) { $skipped++; continue; }
            if (!empty($item['size']) && (int) $item['size'] > self::MAX_FILE_BYTES) { $skipped++; continue; }
            $target = self::local_path_for_repo_path($repo_path);
            if (!$target) { $skipped++; continue; }
            $parent = dirname($target);
            if (!is_dir($parent) && !wp_mkdir_p($parent)) { $errors[] = $repo_path . ': cannot create directory'; continue; }
            // Reject symlinks anywhere under wp-content to prevent path escape.
            $base = wp_normalize_path(WP_CONTENT_DIR);
            $relative_parent = ltrim(substr(wp_normalize_path($parent), strlen($base)), '/');
            $walk = $base;
            foreach (array_filter(explode('/', $relative_parent)) as $segment) {
                $walk .= '/' . $segment;
                if (is_link($walk)) { $errors[] = $repo_path . ': symlink path blocked'; continue 2; }
            }
            if (is_link($target)) { $errors[] = $repo_path . ': symlink file blocked'; continue; }
            if (file_exists($target)) {
                $local = @file_get_contents($target);
                if ($local !== false) {
                    $local_sha = sha1("blob " . strlen($local) . "\0" . $local);
                    if (hash_equals($item['sha'], $local_sha)) { continue; }
                }
            }
            if ($processed >= self::MAX_FILES_PER_RUN) { $errors[] = 'Run limit reached; remaining files will be picked up on next scheduled sync.'; break; }
            $content = self::fetch_blob($item['sha']);
            if (is_wp_error($content)) { $errors[] = $repo_path . ': ' . $content->get_error_message(); continue; }
            if (file_exists($target)) {
                $backup = self::backup_file($target, substr($repo_path, strlen('wp-content/')));
                if (is_wp_error($backup)) { $errors[] = $repo_path . ': backup failed — ' . $backup->get_error_message(); continue; }
            }
            $tmp = $target . '.arktmp-' . wp_generate_password(6, false, false);
            $written = @file_put_contents($tmp, $content, LOCK_EX);
            if ($written === false || $written !== strlen($content)) { @unlink($tmp); $errors[] = $repo_path . ': write failed'; continue; }
            if (file_exists($target)) { @chmod($tmp, fileperms($target) & 0777); } else { @chmod($tmp, 0644); }
            if (!@rename($tmp, $target)) { @unlink($tmp); $errors[] = $repo_path . ': atomic replace failed'; continue; }
            $updated++; $processed++;
        }
        $message = 'Sync complete. Updated: ' . $updated . '; skipped: ' . $skipped . '; errors: ' . count($errors);
        if (!empty($errors)) { $message .= ' | ' . implode(' | ', array_slice($errors, 0, 5)); }
        self::save_status($message, $updated);
    }

    private static function save_status($message, $updated) {
        update_option(self::STATUS, array('message' => $message, 'updated' => (int) $updated, 'time' => current_time('mysql')), false);
    }
}

add_filter('cron_schedules', array('ARK_WordPress_GitHub_Bridge', 'cron_schedules'));
ARK_WordPress_GitHub_Bridge::init();
register_activation_hook(__FILE__, array('ARK_WordPress_GitHub_Bridge', 'activate'));
register_deactivation_hook(__FILE__, array('ARK_WordPress_GitHub_Bridge', 'deactivate'));
