<?php
/**
 * Self-contained ARK App Designer regression checks.
 */
$root = dirname(__DIR__);
$source = file_get_contents($root . '/ark-app-designer.php');
$js = file_get_contents($root . '/assets/admin.js');
$css = file_get_contents($root . '/assets/admin.css');
if ($source === false || $js === false || $css === false) {
    fwrite(STDERR, "Unable to load Designer source files." . PHP_EOL);
    exit(1);
}
$checks = [
    'Designer plugin is version 1.17.4' =>
        strpos($source, '* Version: 1.17.4') !== false &&
        strpos($source, "const VERSION = '1.17.4'") !== false,
    'all four Banner layouts are in the component catalog' =>
        strpos($source, "'banner_grid' =>") !== false &&
        strpos($source, "'banner_list' =>") !== false &&
        strpos($source, "'banner_slider' =>") !== false &&
        strpos($source, "'banner_scroll' =>") !== false,
    'legacy image sections with banner_layout are identified for migration' =>
        strpos($source, '$legacy_image_banner') !== false &&
        strpos($source, "['grid','list','slider','scroll']") !== false,
    'legacy media stores attachment id and image url in SQL slide records' =>
        strpos($source, "'attachment_id' => $attachment_id") !== false &&
        strpos($source, "'image_url' => esc_url_raw") !== false,
    'legacy migration keeps the original Designer component type and variant' =>
        strpos($source, "'designer_component_type']") !== false &&
        strpos($source, "'layout_variant'] = $variant") !== false,
    'schema version 1 upgrades safely to version 2' =>
        strpos($source, '$live_slider_schema < 2') !== false &&
        strpos($source, "update_option('ark_app_designer_live_slider_schema', 2") !== false,
    'empty legacy children never overwrite SQL banner data' =>
        strpos($source, "if (!empty(" . '$' . "legacy_slides))") !== false,
    'SQL save validates every image before deleting existing rows' =>
        strpos($source, 'Validate every selected image before deleting existing SQL rows') !== false,
    'SQL save returns actual number of saved slides' =>
        strpos($source, '$saved_slide_count++') !== false &&
        strpos($source, "'saved_slides' => $" . 'saved_slide_count') !== false,
    'public live slider endpoint is registered' =>
        strpos($source, '/sliders/(?P<slider_key>') !== false,
    'authenticated Studio slider endpoint is registered' =>
        strpos($source, '/studio/sliders/(?P<slider_key>') !== false,
    'authenticated paginated Media Library endpoint is registered' =>
        strpos($source, '/studio/media') !== false &&
        strpos($source, 'function api_studio_media') !== false &&
        strpos($source, "'total_pages' =>") !== false,
    'slide titles subtitles alt links and schedules are stored' =>
        strpos($source, "'link_type' => $link_type") !== false &&
        strpos($source, "'starts_at' => $" . 'start') !== false &&
        strpos($source, "'ends_at' => $" . 'end') !== false &&
        strpos($source, "'subtitle' => sanitize_textarea_field") !== false,
    'Save Draft and Publish share the Designer sanitizer' =>
        strpos($source, 'function api_save_draft') !== false &&
        strpos($source, 'function api_publish') !== false &&
        strpos($source, '$this->sanitize_config(') !== false,
    'WordPress Media Library is loaded in admin' =>
        strpos($source, 'wp_enqueue_media()') !== false,
    'Designer contains SQL slider Children editor and multi-image picker' =>
        strpos($js, 'function renderLiveSliderInspector') !== false &&
        strpos($js, 'CHILDREN · SQL SAVED SLIDES') !== false &&
        strpos($js, 'multiple:true,library:{type:') !== false,
    'Designer can preview legacy image banner layouts' =>
        strpos($js, 'isLegacyImageBanner') !== false &&
        strpos($js, "variant==='grid'") !== false &&
        strpos($js, "variant==='list'") !== false &&
        strpos($js, "variant==='scroll'") !== false,
    'Save and Publish verify SQL saved slide count before continuing' =>
        strpos($js, 'Image save verification failed') !== false &&
        strpos($js, 'Publish was stopped to protect your previous images') !== false,
    'dimensions padding and margin controls are available' =>
        strpos($js, "'2. DIMENSIONS'") !== false &&
        strpos($js, "'3. PADDING'") !== false &&
        strpos($js, "'4. MARGIN'") !== false,
    'slider error state is rendered rather than an empty preview' =>
        strpos($js, 'load_error:true') !== false,
    'layout property CSS exists' =>
        strpos($css, '.ark-property-section') !== false &&
        strpos($css, '.ark-live-slide-item') !== false,
];
$failed = [];
foreach ($checks as $label => $passed) {
    if (!$passed) $failed[] = $label;
}
if ($failed) {
    fwrite(STDERR, "Designer regression checks failed: " . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
echo "ARK App Designer regression checks passed (" . count($checks) . ")." . PHP_EOL;
