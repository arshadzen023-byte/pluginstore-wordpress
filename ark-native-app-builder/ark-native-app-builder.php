<?php
/**
 * Plugin Name: ARK Native App Builder
 * Description: Configure a Flutter native Android app and trigger APK builds with GitHub Actions.
 * Version: 0.2.0
 * Requires PHP: 8.0
 */
if (!defined('ABSPATH')) exit;
define('ARK_NAB_DIR', plugin_dir_path(__FILE__));
require_once ARK_NAB_DIR . 'includes/class-ark-nab.php';
register_activation_hook(__FILE__, ['ARK_NAB', 'activate']);
add_action('plugins_loaded', ['ARK_NAB', 'instance']);
