<?php
/**
 * Isolated test for the Application Password server-header compatibility bridge.
 * Run: php tests/application-password-bridge-test.php
 */
define('ABSPATH', __DIR__);
$GLOBALS['test_headers'] = [];
function add_action() {}
function add_filter() {}
function register_activation_hook() {}
function get_option($name, $default = null) { return true; }
function getallheaders() { return $GLOBALS['test_headers']; }
require dirname(__DIR__) . '/ark-app-designer.php';

$plugin = new ARK_App_Designer();
$input = false;

// Simulate a CGI/FastCGI host where Authorization is available via getallheaders().
$expectedUser = 'ark-test-user';
$expectedPass = 'test-app-password-1234';
$GLOBALS['test_headers'] = ['aUtHoRiZaTiOn' => 'Basic ' . base64_encode($expectedUser . ':' . $expectedPass)];
unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'], $_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION'], $_SERVER['HTTP_X_AUTHORIZATION']);
$plugin->populate_application_password_from_server_headers($input);
if (($_SERVER['PHP_AUTH_USER'] ?? null) !== $expectedUser || ($_SERVER['PHP_AUTH_PW'] ?? null) !== $expectedPass) {
    fwrite(STDERR, "Authorization header was not bridged from getallheaders().\n");
    exit(1);
}

// Simulate a proxy that exposes the Basic header via REMOTE_USER.
unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
$GLOBALS['test_headers'] = [];
$_SERVER['REMOTE_USER'] = 'Basic ' . base64_encode($expectedUser . ':' . $expectedPass);
$plugin->populate_application_password_from_server_headers($input);
if (($_SERVER['PHP_AUTH_USER'] ?? null) !== $expectedUser || ($_SERVER['PHP_AUTH_PW'] ?? null) !== $expectedPass) {
    fwrite(STDERR, "Authorization header was not bridged from REMOTE_USER.\n");
    exit(1);
}

echo "Application Password compatibility bridge tests passed.\n";
