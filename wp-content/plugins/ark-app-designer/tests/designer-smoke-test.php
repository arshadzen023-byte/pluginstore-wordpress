<?php
/**
 * Source regression checks for ARK App Designer 1.14.0.
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
    'SQL-backed banner slider and legacy live slider have dedicated catalog entries' => strpos($source, "'ark_live_slider' =>") !== false && strpos($source, "'banner_slider' =>") !== false,
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
    'Banner Slider alias is available in the Designer component editor' => strpos($js, 'banner_slider') !== false && strpos($js, 'Banner Slider') !== false,
    'live slider editor CSS exists' => strpos($css, '.ark-live-slide-item') !== false,
    '3D product card designs are permitted' => strpos($source, "'3d_glass'") !== false,
    'card animation is persisted rather than discarded' => strpos($source, "'card_animation']") !== false && strpos($source, 'unset($item[\'card_animation\'])') === false,
    'card depth, radius and image ratio are persisted' => strpos($source, "'card_depth'") !== false && strpos($source, "'card_radius'") !== false && strpos($source, "'card_aspect_ratio'") !== false,
    'Designer exposes 3D card controls' => strpos($js, 'Interactive 3D tilt') !== false && strpos($js, '3D glass card') !== false,
    'Product cards expose working tap actions and source filters' => strpos($js, 'Tap action type') !== false && strpos($source, "'tap_action_type'") !== false && strpos($source, "'top_rated'") !== false,
    'Product card animation and background motion options are persisted' => strpos($js, 'Background animation') !== false && strpos($source, "'scale','slide','pulse'") !== false,
    'Category grid supports slider and alternate card layouts' => strpos($js, 'Horizontal slider') !== false && strpos($source, "'category_layout'") !== false && strpos($source, "'category_card_style'") !== false,
    'Layout Designer bottom dock exposes live editing tabs' => strpos($source, 'id="ark-layout-dock"') !== false && strpos($js, 'layoutDockShowPanel') !== false && strpos($css, '.ark-layout-dock') !== false,
    'Layout Designer component panel exposes banner category and product variants' => strpos($js, 'Banner Grid') !== false && strpos($js, 'Category List Tile') !== false && strpos($js, 'Product Scroll') !== false,
    'Layout Designer shared spacing and dimension properties are available' => strpos($js, 'Padding top (px)') !== false && strpos($js, 'Margin left (px)') !== false && strpos($js, 'Fixed height (px, 0 = auto)') !== false,
    'Layout templates can be saved and inserted' => strpos($js, 'arkDesignerMyTemplatesV1') !== false && strpos($js, 'Save selected component') !== false && strpos($js, 'Template inserted') !== false,
    'Banner category and product component aliases normalize to native renderer types' => strpos($source, "'banner_grid' => ['ark_live_slider','grid']") !== false && strpos($source, "'category_list_tile' => ['category_grid','list_tile']") !== false && strpos($source, "'product_list' => ['product_grid','list']") !== false,
    'Designer component alias is restored while native layout variant is preserved' => strpos($source, "'designer_component_type'") !== false && strpos($js, 'designer_component_type') !== false,
    'Banner SQL images support grid list and scroll variants in Flutter' => strpos(file_get_contents($root . '/../../flutter-app/lib/features/designer/dynamic_page_renderer.dart'), "variant == 'grid'") !== false && strpos(file_get_contents($root . '/../../flutter-app/lib/features/designer/dynamic_page_renderer.dart'), "variant == 'scroll'") !== false,
    'Navigation has four styles and persists the selected style' => strpos($js, 'Floating rounded bar') !== false && strpos($source, "navigation['style']") !== false,
    'Config migration fills missing defaults without replacing saved arrays' => strpos($source, 'merge_config_defaults') !== false && strpos($source, 'ark_app_designer_config_schema') !== false,
    'Admin assets use file modification cache-busters' => strpos($source, 'filemtime($js_path)') !== false && strpos($source, 'filemtime($css_path)') !== false,
    'Default Heading Bar has native animation controls' => strpos($js, "Heading bar animation") !== false && strpos($source, "'animation' => 'none'") !== false,
    'Default Heading animation and background effects are validated and persisted' => strpos($source, "['none','pulse','gradient','slide','shimmer','float']") !== false && strpos($source, "['none','glass','blur','shimmer','glow','gradient','fireworks']") !== false && strpos($source, "\$value['background_effect'] = in_array") !== false,
    'Per-page icon visibility survives publish sanitization' => strpos($source, "'icon_visibility' => is_array") !== false,
    'Media Library background image attachment IDs are persisted and restored' => strpos($source, 'background_image_id') !== false && strpos($source, 'wp_get_attachment_url($image_id)') !== false,
    'Draft editor selects the newest saved Draft or Published config' => strpos($source, 'current_editor_config()') !== false && strpos($source, '$draft_at >= $published_at') !== false,
    'Stale editor requests preserve missing fields from the latest configuration' => strpos($source, '$raw = self::merge_config_defaults(is_array($baseline) ? $baseline : $base, $raw)') !== false,
    'Custom pages survive config sanitization and are returned in link targets' => strpos($source, "preg_match('/^custom_[a-z0-9_]{1,40}$/") !== false && strpos($source, "'custom_pages' =>") !== false,
    'Loading animation supports size, page visibility and bundled asset fallback' => strpos($source, "'size' => 58") !== false && strpos($source, '$loading[\'size\']') !== false,
    'Designer has Add New Page and Page Settings menu items' => strpos($source, 'id="ark-add-page"') !== false && strpos($source, 'id="ark-page-settings"') !== false,
    'Loading animation controls are available in Designer' => strpos($source, 'data-view="loading"') !== false && strpos($js, "state.view==='loading'") !== false,
    'Heading preview is draggable and persists its placement' => strpos($js, "data-id','__default_heading__'") !== false && strpos($js, 'heading.position_index=') !== false,
    'Custom page modal and page link picker are available' => strpos($js, 'function openAddPageDialog') !== false && strpos($js, 'Custom pages') !== false,

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
