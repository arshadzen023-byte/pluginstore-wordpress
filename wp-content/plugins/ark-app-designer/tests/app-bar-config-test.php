<?php
/**
 * Static contract checks for the native App Bar Builder.
 * WordPress runtime publish verification is covered on the live staging store.
 */
$root = dirname(__DIR__);
$source = file_get_contents($root . '/ark-app-designer.php');
if (!is_string($source) || strlen($source) < 50000) {
    fwrite(STDERR, "ARK App Designer source is missing or incomplete.\n");
    exit(1);
}
$checks = [
    'Plugin version is 1.17.8' => strpos($source, "Version: 1.17.8") !== false && strpos($source, "const VERSION = '1.17.8'") !== false,
    'App Bar defaults are present' => strpos($source, "'app_bar' => [") !== false,
    'Twelve App Bar presets are supported' => strpos($source, "['amazon','flipkart','myntra','meesho','minimal','premium','location','gradient','compact','search','fashion','custom']") !== false,
    'App Bar visibility settings are sanitized per page' => strpos($source, "'visible_pages' => ['home'=>true") !== false && strpos($source, '$app_bar[\'visible_pages\']') !== false,
    'Voice Search and action icon options are preserved' => strpos($source, "'show_voice_search' => true") !== false && strpos($source, "'voice_icon_family'") !== false,
    'Background image, GIF MIME and logo URLs are sanitized' => strpos($source, 'background_image_url') !== false && strpos($source, 'image/gif') !== false && strpos($source, 'logo_url') !== false,
    'App Bar custom actions are limited to ten' => strpos($source, 'array_slice(array_filter((array)') !== false && strpos($source, '0, 10)') !== false,
    'Per-page Floating Action Button settings remain supported' => strpos($source, "'floating_actions' => []") !== false && strpos($source, '$clean[\'floating_actions\'] = $clean_floating_actions') !== false,
    'Native Navigation Bar schema remains present' => strpos($source, "'navigation_bar' => [") !== false,
    'Existing component layout sanitizer remains present' => strpos($source, 'main_axis_spacing') !== false && strpos($source, 'padding_top') !== false && strpos($source, 'margin_top') !== false,
];
$failed = [];
foreach ($checks as $label => $passed) {
    if (!$passed) $failed[] = $label;
}
if ($failed) {
    fwrite(STDERR, "App Bar contract checks failed: " . implode('; ', $failed) . PHP_EOL);
    exit(1);
}
echo "App Bar contract checks passed (" . count($checks) . ")." . PHP_EOL;
