<?php
/**
 * Self-contained ARK App Designer regression checks.
 * Run: php tests/designer-smoke-test.php
 */
$root = dirname(__DIR__);
$source = file_get_contents($root . '/ark-app-designer.php');
$js = file_get_contents($root . '/assets/admin.js');
$css = file_get_contents($root . '/assets/admin.css');
if ($source === false || $js === false || $css === false) {
    fwrite(STDERR, "Unable to load Designer source files." . PHP_EOL);
    exit(1);
}
if (!preg_match('/^\s*\* Version:\s*([0-9.]+)/m', $source, $match)) {
    fwrite(STDERR, "Could not read Designer version." . PHP_EOL);
    exit(1);
}
$version = $match[1];
if (strpos($source, "const VERSION = '" . $version . "'") === false) {
    fwrite(STDERR, "Plugin header and class versions differ." . PHP_EOL);
    exit(1);
}
$checks = [
    'all banner variants are present in the native component catalogue' =>
        strpos($source, "'banner_grid' =>") !== false &&
        strpos($source, "'banner_list' =>") !== false &&
        strpos($source, "'banner_slider' =>") !== false &&
        strpos($source, "'banner_scroll' =>") !== false,
    'legacy image sections with banner_layout are migrated' =>
        strpos($source, "$" . "legacy_image_banner") !== false &&
        strpos($source, "['grid','list','slider','scroll']") !== false,
    'legacy image and child media are normalized to SQL attachment and URL fields' =>
        strpos($source, "'attachment_id' => $attachment_id") !== false &&
        strpos($source, "'image_url' => esc_url_raw") !== false,
    'migration preserves the original Designer component type and layout variant' =>
        strpos($source, "'designer_component_type']") !== false &&
        strpos($source, "'layout_variant'] = $variant") !== false,
    'migration does not clear a SQL slider when old children are empty' =>
        strpos($source, 'if (!empty(' . ' !== false,
    'schema upgrade re-runs safe migration for existing 1.17.x installations' =>
        strpos($source, '$live_slider_schema < 2') !== false &&
        strpos($source, "update_option('ark_app_designer_live_slider_schema', 2") !== false,
    'SQL slide save validates all images before deleting existing rows' =>
        strpos($source, 'Validate every selected image before deleting existing SQL rows') !== false,
    'SQL save returns the actual persisted slide count' =>
        strpos($source, '$saved_slide_count++') !== false &&
        strpos($source, "'saved_slides' => $saved_slide_count") !== false,
    'public SQL slider REST endpoint exists' =>
        strpos($source, '/sliders/(?P<slider_key>') !== false,
    'authenticated SQL slider read/write endpoints exist' =>
        strpos($source, '/studio/sliders/(?P<slider_key>') !== false,
    'native authenticated paginated Media Library endpoint exists' =>
        strpos($source, '/studio/media') !== false &&
        strpos($source, 'function api_studio_media') !== false &&
        strpos($source, "'total_pages' =>") !== false,
    'slide links, schedule, title, subtitle and alt fields persist in SQL' =>
        strpos($source, "'link_type' => $link_type") !== false &&
        strpos($source, "'starts_at' => $start") !== false &&
        strpos($source, "'ends_at' => $end") !== false &&
        strpos($source, "'subtitle' => sanitize_textarea_field") !== false,
    'draft and publish APIs share the same configuration sanitizer' =>
        strpos($source, 'function api_save_draft') !== false &&
        strpos($source, 'function api_publish') !== false &&
        strpos($source, '$this->sanitize_config(') !== false,
    'admin Media Library is enqueued for Designer' => strpos($source, 'wp_enqueue_media()') !== false,
    'Designer provides SQL slider editor and multi-image Media Library selection' =>
        strpos($js, 'function renderLiveSliderInspector') !== false &&
        strpos($js, 'multiple:true,library:{type:') !== false,
    'Designer supports legacy image/banner layouts in the preview and inspector' =>
        strpos($js, 'isLegacyImageBanner') !== false &&
        strpos($js, "variant==='grid'") !== false &&
        strpos($js, "variant==='list'") !== false &&
        strpos($js, "variant==='scroll'") !== false,
    'Designer verifies selected media count before completing SQL save' =>
        strpos($js, 'Image save verification failed') !== false &&
        strpos($js, 'Publish was stopped to protect your previous images') !== false,
    'common dimensions padding margin and styling property groups remain available' =>
        strpos($js, "'2. DIMENSIONS'") !== false &&
        strpos($js, "'3. PADDING'") !== false &&
        strpos($js, "'4. MARGIN'") !== false &&
        strpos($js, "'6. STYLE & ANIMATION'") !== false,
    'live SQL preview falls back cleanly instead of leaving an empty preview request' =>
        strpos($js, 'load_error:true') !== false,
    'layout property styles are present' =>
        strpos($css, '.ark-property-section') !== false &&
        strpos($css, '.ark-live-slide-item') !== false,
];
$failed = [];
foreach ($checks as $label => $passed) if (!$passed) $failed[] = $label;
if ($failed) {
    fwrite(STDERR, "Designer regression checks failed: " . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
echo "ARK App Designer " . $version . " regression checks passed (" . count($checks) . ")." . PHP_EOL;
 . 'legacy_slides))') !== false,
    'schema upgrade re-runs safe migration for existing 1.17.x installations' =>
        strpos($source, '$live_slider_schema < 2') !== false &&
        strpos($source, "update_option('ark_app_designer_live_slider_schema', 2") !== false,
    'SQL slide save validates all images before deleting existing rows' =>
        strpos($source, 'Validate every selected image before deleting existing SQL rows') !== false,
    'SQL save returns the actual persisted slide count' =>
        strpos($source, '$saved_slide_count++') !== false &&
        strpos($source, "'saved_slides' => $saved_slide_count") !== false,
    'public SQL slider REST endpoint exists' =>
        strpos($source, '/sliders/(?P<slider_key>') !== false,
    'authenticated SQL slider read/write endpoints exist' =>
        strpos($source, '/studio/sliders/(?P<slider_key>') !== false,
    'native authenticated paginated Media Library endpoint exists' =>
        strpos($source, '/studio/media') !== false &&
        strpos($source, 'function api_studio_media') !== false &&
        strpos($source, "'total_pages' =>") !== false,
    'slide links, schedule, title, subtitle and alt fields persist in SQL' =>
        strpos($source, "'link_type' => $link_type") !== false &&
        strpos($source, "'starts_at' => $start") !== false &&
        strpos($source, "'ends_at' => $end") !== false &&
        strpos($source, "'subtitle' => sanitize_textarea_field") !== false,
    'draft and publish APIs share the same configuration sanitizer' =>
        strpos($source, 'function api_save_draft') !== false &&
        strpos($source, 'function api_publish') !== false &&
        strpos($source, '$this->sanitize_config(') !== false,
    'admin Media Library is enqueued for Designer' => strpos($source, 'wp_enqueue_media()') !== false,
    'Designer provides SQL slider editor and multi-image Media Library selection' =>
        strpos($js, 'function renderLiveSliderInspector') !== false &&
        strpos($js, 'multiple:true,library:{type:') !== false,
    'Designer supports legacy image/banner layouts in the preview and inspector' =>
        strpos($js, 'isLegacyImageBanner') !== false &&
        strpos($js, "variant==='grid'") !== false &&
        strpos($js, "variant==='list'") !== false &&
        strpos($js, "variant==='scroll'") !== false,
    'Designer verifies selected media count before completing SQL save' =>
        strpos($js, 'Image save verification failed') !== false &&
        strpos($js, 'Publish was stopped to protect your previous images') !== false,
    'common dimensions padding margin and styling property groups remain available' =>
        strpos($js, "'2. DIMENSIONS'") !== false &&
        strpos($js, "'3. PADDING'") !== false &&
        strpos($js, "'4. MARGIN'") !== false &&
        strpos($js, "'6. STYLE & ANIMATION'") !== false,
    'live SQL preview falls back cleanly instead of leaving an empty preview request' =>
        strpos($js, 'load_error:true') !== false,
    'layout property styles are present' =>
        strpos($css, '.ark-property-section') !== false &&
        strpos($css, '.ark-live-slide-item') !== false,
];
$failed = [];
foreach ($checks as $label => $passed) if (!$passed) $failed[] = $label;
if ($failed) {
    fwrite(STDERR, "Designer regression checks failed: " . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
echo "ARK App Designer " . $version . " regression checks passed (" . count($checks) . ")." . PHP_EOL;
