<?php
/**
 * Source regression checks for ARK App Designer 1.8.0.
 * Run: php tests/designer-smoke-test.php
 */
$root = dirname(__DIR__);
$source = file_get_contents($root . '/ark-app-designer.php');
$js = file_get_contents($root . '/assets/admin.js');
$css = file_get_contents($root . '/assets/admin.css');

if (!preg_match('/^\\s*\\* Version:\\s*([0-9.]+)/m', $source, $match)) {
    fwrite(STDERR, "Could not read Designer version." . PHP_EOL);
    exit(1);
}
$version = $match[1];
if (strpos($source, "const VERSION = '" . $version . "'") === false) {
    fwrite(STDERR, "Plugin header and class versions differ." . PHP_EOL);
    exit(1);
}
$checks = [
    'new slider replaces legacy component catalog entry' => strpos($source, "'ark_live_slider' =>") !== false && strpos($source, "'banner_slider' =>") === false,
    'slider settings SQL table exists' => strpos($source, 'ark_live_sliders') !== false,
    'slide items SQL table exists' => strpos($source, 'ark_live_slider_items') !== false,
    'database migration uses dbDelta' => strpos($source, 'dbDelta(') !== false,
    'legacy slider data migration exists' => strpos($source, 'migrate_legacy_banner_sliders') !== false,
    'public app slider endpoint exists' => strpos($source, '/sliders/(?P<slider_key>') !== false,
    'authenticated studio slider endpoint exists' => strpos($source, '/studio/sliders/(?P<slider_key>') !== false,
    'scheduled slides are filtered by time' => strpos($source, 'starts_at <= UTC_TIMESTAMP()') !== false,
    'Media Library attachment and srcset are saved' => strpos($source, 'image_srcset') !== false && strpos($source, "'attachment_id' =>") !== false,
    'in-app product and category destinations are validated' => strpos($source, 'category:[0-9]+') !== false && strpos($source, 'product:[0-9]+') !== false,
    'live slider SQL editor exists' => strpos($js, 'function renderLiveSliderInspector') !== false,
    'SQL save action exists' => strpos($js, 'Save slider to SQL') !== false,
    'multiple Media Library uploads exist' => strpos($js, 'multiple:true,library:{type:') !== false,
    'per-slide date scheduling fields exist' => strpos($js, 'Start showing (optional)') !== false && strpos($js, 'Stop showing (optional)') !== false,
    'WooCommerce category link picker exists' => strpos($js, 'WooCommerce categories') !== false,
    'legacy editor removed from Designer JS' => strpos($js, 'banner_slider') === false,
    'live slider editor CSS exists' => strpos($css, '.ark-live-slide-item') !== false,
];
$failed = [];
foreach ($checks as $label => $passed) {
    if (!$passed) $failed[] = $label;
}
if ($failed) {
    fwrite(STDERR, "Designer regression checks failed: " . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
echo "ARK App Designer " . $version . " regression checks passed (" . count($checks) . ")." . PHP_EOL;
