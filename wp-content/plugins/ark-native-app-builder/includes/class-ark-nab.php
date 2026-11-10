<?php
if (!defined('ABSPATH')) exit;
final class ARK_NAB {
 private static $instance;
 private $key='ark_nab_settings';
 static function instance(){ return self::$instance ?: (self::$instance=new self()); }
 static function activate(){
  if(!get_option('ark_nab_build_token')) add_option('ark_nab_build_token',wp_generate_password(48,false,false), '', false);
  if(!get_option('ark_nab_settings')) add_option('ark_nab_settings',['app_name'=>get_bloginfo('name'),'package'=>'com.ark.glowhit','version'=>'1.0.0','version_code'=>'1','owner'=>'','repo'=>'','workflow'=>'build-android.yml','github_token'=>'','api_base'=>home_url('/wp-json/')]);
 }
 private function __construct(){ add_action('admin_menu',[$this,'menu']); add_action('admin_init',[$this,'settings']); add_action('rest_api_init',[$this,'routes']); }
 private function opts(){ return wp_parse_args(get_option($this->key,[]),['app_name'=>'GlowHit','package'=>'com.ark.glowhit','version'=>'1.0.0','version_code'=>'1','owner'=>'','repo'=>'','workflow'=>'build-android.yml','github_token'=>'','api_base'=>home_url('/wp-json/')]); }
 function menu(){ add_menu_page('ARK Native App Builder','ARK App Builder','manage_options','ark-native-app-builder',[$this,'page'],'dashicons-smartphone',58); }
 function settings(){ register_setting('ark_nab_group',$this->key,[$this,'sanitize']); }
 function sanitize($in){
  $old=$this->opts(); $o=[];
  foreach(['app_name','version'] as $k) $o[$k]=sanitize_text_field($in[$k]??'');
  $o['package']=preg_replace('/[^A-Za-z0-9_.]/','',$in['package']??'com.ark.app');
  $o['version_code']=(string)max(1,absint($in['version_code']??1));
  $o['owner']=sanitize_text_field($in['owner']??''); $o['repo']=sanitize_text_field($in['repo']??'');
  $o['workflow']=sanitize_file_name($in['workflow']??'build-android.yml');
  $o['api_base']=esc_url_raw($in['api_base']??home_url('/wp-json/'));
  $o['github_token']=!empty($in['github_token'])?sanitize_text_field($in['github_token']):$old['github_token'];
  return $o;
 }
 function page(){
  if(!current_user_can('manage_options')) return; $o=$this->opts();
  if(isset($_POST['ark_nab_build']) && check_admin_referer('ark_nab_build')){
   $r=$this->build();
   if(is_wp_error($r)) echo '<div class="notice notice-error"><p>'.esc_html($r->get_error_message()).'</p></div>';
   else echo '<div class="notice notice-success"><p>Build request sent. <a href="'.esc_url($r).'" target="_blank" rel="noopener">Open GitHub Actions</a></p></div>';
  }
  ?>
  <div class="wrap"><h1>ARK Native App Builder</h1><p>Native Flutter Android app — no WebView.</p>
  <form method="post" action="options.php"><?php settings_fields('ark_nab_group'); ?>
  <table class="form-table">
  <?php foreach(['app_name'=>'App Name','package'=>'Package Name','version'=>'Version Name','version_code'=>'Version Code','api_base'=>'WordPress REST API','owner'=>'GitHub Owner','repo'=>'GitHub Repository','workflow'=>'Workflow file'] as $k=>$label): ?>
  <tr><th><?php echo esc_html($label); ?></th><td><input class="regular-text" name="<?php echo esc_attr($this->key.'['.$k.']'); ?>" value="<?php echo esc_attr($o[$k]); ?>"></td></tr>
  <?php endforeach; ?>
  <tr><th>GitHub Fine-grained Token</th><td><input type="password" class="regular-text" name="<?php echo esc_attr($this->key); ?>[github_token]" placeholder="<?php echo $o['github_token']?'Saved — enter to replace':'github_pat_…'; ?>"><p class="description">Repository permissions: Actions read/write, Contents read.</p></td></tr>
  </table><?php submit_button('Save Settings'); ?></form>
  <hr><h2>Generate APK</h2><form method="post"><?php wp_nonce_field('ark_nab_build'); ?><button class="button button-primary" name="ark_nab_build" value="1">Generate Android APK</button></form>
  <hr><h2>GitHub Actions secrets</h2><p><code>ARK_WORDPRESS_URL</code> = <?php echo esc_html(home_url('/')); ?></p><p><code>ARK_BUILD_TOKEN</code> = <code><?php echo esc_html(get_option('ark_nab_build_token')); ?></code></p>
  <?php $last=get_option('ark_nab_last_build',[]); if(!empty($last['url'])) echo '<p><a class="button button-secondary" href="'.esc_url($last['url']).'">Download latest APK</a></p>'; ?>
  <p><strong>Starter milestone:</strong> Product catalog and product detail are native. Cart, customer login, checkout/payment, Firebase push registration and Elementor widget mapping are subsequent milestones.</p></div>
  <?php
 }
 private function build() {
    $o = $this->opts();

    if (!$o['owner'] || !$o['repo'] || !$o['github_token']) {
        return new WP_Error(
            'config',
            'Set GitHub owner, repository and token, then save.'
        );
    }

    $url = sprintf(
        'https://api.github.com/repos/%s/%s/actions/workflows/%s/dispatches',
        rawurlencode($o['owner']),
        rawurlencode($o['repo']),
        rawurlencode($o['workflow'])
    );

    $body = [
        'ref' => 'main',
        'inputs' => [
            'app_name' => $o['app_name'],
            'package_name' => $o['package'],
            'version_name' => $o['version'],
            'version_code' => $o['version_code'],
            'store_url' => home_url('/'),
            'build_id' => (string) time(),
        ],
    ];

    $response = wp_remote_post($url, [
        'timeout' => 30,
        'headers' => [
            'Accept' => 'application/vnd.github+json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $o['github_token'],
            'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent' => 'ARK-Native-App-Builder',
        ],
        'body' => wp_json_encode($body),
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code($response);

    if ($code !== 204) {
        return new WP_Error(
            'github_error',
            'GitHub HTTP ' . $code . ': ' .
            wp_remote_retrieve_body($response)
        );
    }

    $link = 'https://github.com/' .
        $o['owner'] . '/' . $o['repo'] . '/actions';

    update_option('ark_nab_last_build', [
        'url' => $link,
        'built_at' => current_time('mysql'),
    ], false);

    return $link;
}
 function routes(){
  register_rest_route('ark-native/v1','/config',['methods'=>'GET','callback'=>function(){ $o=$this->opts(); return ['app_name'=>$o['app_name'],'store_url'=>home_url('/'),'currency'=>function_exists('get_woocommerce_currency')?get_woocommerce_currency():'INR','sync_mode'=>'live','api_version'=>1]; },'permission_callback'=>'__return_true']);
  register_rest_route('ark-native/v1','/build-artifact',['methods'=>'POST','callback'=>[$this,'artifact'],'permission_callback'=>'__return_true']);
 }
 
function artifact($req) {

    // 1. Verify build token
    $token = (string) $req->get_header('x-ark-build-token');

    if ($token === '') {
        $token = (string) $req->get_param('build_token');
    }

    $expected = (string) get_option('ark_nab_build_token');

    if (!$expected || !hash_equals($expected, $token)) {
        return new WP_Error(
            'forbidden',
            'Invalid ARK build token.',
            ['status' => 403]
        );
    }

    // 2. Read uploaded file through WordPress REST API
    $files = $req->get_file_params();
    $file = $files['apk'] ?? null;

    // 3. Detect PHP POST size limit
    if (!$file) {
        $content_length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        $post_limit = wp_convert_hr_to_bytes(ini_get('post_max_size'));

        if ($post_limit > 0 && $content_length > $post_limit) {
            return new WP_Error(
                'ark_post_limit',
                'APK exceeds PHP post_max_size. Increase post_max_size in cPanel.',
                ['status' => 413]
            );
        }

        return new WP_Error(
            'ark_missing_apk',
            'APK missing. Check PHP upload_max_filesize and post_max_size.',
            ['status' => 400]
        );
    }

    // 4. Check PHP upload errors
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $error = $file['error'] ?? -1;

        return new WP_Error(
            'ark_upload_error',
            'PHP upload error code: ' . $error .
            '. Check cPanel upload limits.',
            ['status' => 400]
        );
    }

    // 5. Validate APK
    if (
        empty($file['tmp_name']) ||
        !is_uploaded_file($file['tmp_name']) ||
        strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'apk' ||
        $file['size'] > 200 * 1024 * 1024
    ) {
        return new WP_Error(
            'ark_invalid_apk',
            'Invalid APK file or file size.',
            ['status' => 400]
        );
    }

    // 6. Create storage directory
    $upload = wp_upload_dir();
    $dir = trailingslashit($upload['basedir']) . 'ark-native-builds';

    if (!wp_mkdir_p($dir)) {
        return new WP_Error(
            'ark_storage_error',
            'Could not create APK storage folder.',
            ['status' => 500]
        );
    }

    // 7. Move APK into WordPress uploads
    $name = wp_unique_filename(
        $dir,
        'ark-app-' . gmdate('Ymd-His') . '.apk'
    );

    $destination = trailingslashit($dir) . $name;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return new WP_Error(
            'ark_move_error',
            'Could not save APK on server.',
            ['status' => 500]
        );
    }

    // 8. Save latest APK link
    $url = trailingslashit($upload['baseurl']) .
        'ark-native-builds/' . rawurlencode($name);

    update_option('ark_nab_last_build', [
        'url' => $url,
        'built_at' => current_time('mysql'),
        'file' => $name
    ], false);

    return rest_ensure_response([
        'success' => true,
        'message' => 'APK uploaded successfully.',
        'download_url' => $url
    ]);
}

}
