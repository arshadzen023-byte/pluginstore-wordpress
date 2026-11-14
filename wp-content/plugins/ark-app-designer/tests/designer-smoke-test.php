<?php
/**
 * Source regression checks for ARK App Designer 1.10.0.
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
    'Search page is a configurable Designer page' => strpos($source, "'search' => ['title' => 'Search'") !== false,
    'Default Heading Bar is a dedicated Designer menu, not a page component' => strpos($source, 'data-view="default_heading"') !== false && strpos($js, "state.view==='default_heading'") !== false,
    'Default Heading Bar supports background image and page visibility' => strpos($js, 'background_image_url') !== false && strpos($js, 'visible_pages') !== false,
    'Heading is not offered as an addable page component' => strpos($js, "if(type==='heading')return") !== false,
    'Home search component has voice and camera toggles' => strpos($js, 'Show Voice Search button') !== false && strpos($js, 'Show Camera Search button') !== false,
    'Heading action button controls are available' => strpos($js, 'Show action button') !== false && strpos($js, 'button_link_type') !== false,
    'Search voice and camera controls are available' => strpos($js, 'Enable voice search') !== false && strpos($js, 'Enable camera / image text search') !== false,
    'Recent searches and suggestions components are available' => strpos($source, "'recent_searches' =>") !== false && strpos($source, "'search_suggestions' =>") !== false,
    'new slider replaces legacy component catalog entry' => strpos($source, "'ark_live_slider' =>") !== false && strpos($source, "'banner_slider' =>") === false,
    'slider settings SQL table exists' => strpos($source, 'ark_live_sliders') !== false,
    'slide items SQL table exists' => strpos($source, 'ark_live_slider_items') !== false,
    'database migration uses dbDelta' => strpos($source, 'dbDelta(') !== false,
    'legacy slider data migration exists' => strpos($source, 'migrate_legacy_banner_sliders') !== false,
    'unsaved legacy slides are copied to SQL before removing inline items' => strpos($source, '$this->save_live_slider_payload($slider_key, $legacy_settings, $slides)') !== false,
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
    '3D product card designs are permitted' => strpos($source, "'3d_glass'") !== false,
    'card animation is persisted rather than discarded' => strpos($source, "'card_animation']") !== false && strpos($source, 'unset($item[\'card_animation\'])') === false,
    'card depth, radius and image ratio are persisted' => strpos($source, "'card_depth'") !== false && strpos($source, "'card_radius'") !== false && strpos($source, "'card_aspect_ratio'") !== false,
    'Designer exposes 3D card controls' => strpos($js, 'Interactive 3D tilt') !== false && strpos($js, '3D glass card') !== false,
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
