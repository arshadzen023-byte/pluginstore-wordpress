<?php
/**
 * Source regression checks for ARK App Designer 1.17.2.
 * Run: php tests/designer-smoke-test.php
 */
$root = dirname(__DIR__);
$source = file_get_contents($root . '/ark-app-designer.php');
$js = file_get_contents($root . '/assets/admin.js');
$css = file_get_contents($root . '/assets/admin.css');
$flutter = file_get_contents($root . '/../../flutter-app/lib/features/designer/dynamic_page_renderer.dart');
$home = file_get_contents($root . '/../../flutter-app/lib/features/home/home_screen.dart');
$shell = file_get_contents($root . '/../../flutter-app/lib/core/app_page_shell.dart');
$dynamic = file_get_contents($root . '/../../flutter-app/lib/features/designer/dynamic_page_renderer.dart');
$page_shell = file_get_contents($root . '/../../flutter-app/lib/core/app_page_shell.dart');
$app_theme = file_get_contents($root . '/../../flutter-app/lib/core/app_theme.dart');
$app = file_get_contents($root . '/../../flutter-app/lib/app.dart');

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
    'Studio target API includes WooCommerce product featured/gallery images and prices' => strpos($source, "'image_url' => $images[0]['src'] ?? ''") !== false && strpos($source, "'images' => $images") !== false && strpos($source, "'price' => wp_strip_all_tags((string) $product->get_price())") !== false,
    'Studio target API includes WooCommerce category thumbnail images' => strpos($source, "'thumbnail' => $image_url ? esc_url_raw($image_url) : ''") !== false,
    'Legacy Banner Slider migrates selected Children images into SQL slides' => strpos($source, "$"."section['children'] ?? []") !== false,
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
    'Layout Designer shared spacing and dimension properties are available' => strpos($js, "'3. PADDING'") !== false && strpos($js, "'4. MARGIN'") !== false && strpos($js, "'2. DIMENSIONS'") !== false && strpos($js, "'Top (px)','padding_top'") !== false && strpos($js, "'Left (px)','margin_left'") !== false && strpos($js, 'Fixed height (px, 0 = auto)') !== false,
    'Layout templates can be saved and inserted' => strpos($js, 'arkDesignerMyTemplatesV1') !== false && strpos($js, 'Save selected component') !== false && strpos($js, 'Template inserted') !== false,
    'Banner category and product component aliases normalize to native renderer types' => strpos($source, "'banner_grid' => ['ark_live_slider','grid']") !== false && strpos($source, "'category_list_tile' => ['category_grid','list_tile']") !== false && strpos($source, "'product_list' => ['product_grid','list']") !== false,
    'Designer component alias is restored while native layout variant is preserved' => strpos($source, "'designer_component_type'") !== false && strpos($js, 'designer_component_type') !== false,
    'Banner SQL images support grid list and scroll variants in Flutter' => strpos(file_get_contents($root . '/../../flutter-app/lib/features/designer/dynamic_page_renderer.dart'), "variant == 'grid'") !== false && strpos(file_get_contents($root . '/../../flutter-app/lib/features/designer/dynamic_page_renderer.dart'), "variant == 'scroll'") !== false,
    'Navigation has four styles and persists the selected style' => strpos($js, 'Floating rounded bar') !== false && strpos($source, "navigation['style']") !== false,
    'In-app wishlist categories and account open their real HomeScreen tabs' => strpos($dynamic, 'HomeScreen(initialTab: 1)') !== false && strpos($dynamic, 'HomeScreen(initialTab: 2)') !== false && strpos($dynamic, 'HomeScreen(initialTab: 3)') !== false && strpos($home, 'widget.initialTab') !== false,
    'Standalone page shell includes a real heading and system-bar style' => strpos($page_shell, 'AnnotatedRegion<SystemUiOverlayStyle>') !== false && strpos($page_shell, "title: Text(") !== false && strpos($page_shell, 'appSystemUiOverlayStyle') !== false,
    'Published global theme applies readable foreground on dark backgrounds' => strpos($app, 'appThemeForDesign') !== false && strpos($app_theme, 'onSurface: foreground') !== false && strpos($app_theme, '_contrastForeground') !== false,
    'Status bar and navigation bar follow published page colours' => strpos($app, 'AnnotatedRegion<SystemUiOverlayStyle>') !== false && strpos($app_theme, 'statusBarIconBrightness') !== false && strpos($app_theme, 'systemNavigationBarIconBrightness') !== false,
    'Native entrance animations support bounce rotate and 3D flip' => strpos($dynamic, "case 'bounce'") !== false && strpos($dynamic, "case 'rotate'") !== false && strpos($dynamic, "case 'flip'") !== false,
    'Entrance animations replay for each published design version' => strpos($dynamic, 'AppDesignStore.instance.version') !== false && strpos($dynamic, 'entrance_duration_ms') !== false,
    'Designer exposes new entrance animation choices and duration' => strpos($js, "['bounce','Bounce']") !== false && strpos($js, "['flip','3D flip']") !== false && strpos($js, 'Entrance duration (ms)') !== false,
    'Published sections validate entrance animation options and duration' => strpos($source, "'bounce','rotate','flip'") !== false && strpos($source, "'entrance_duration_ms'") !== false,
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
    'Custom page links survive SQL link normalization' => strpos($source, 'custom_[a-z0-9_]{1,40}') !== false,
    'Website URLs without a scheme are normalized to HTTPS' => strpos($source, "'https://' . \$value") !== false,
    'Destination lookup supports fast server-side product search' => strpos($source, "'limit' => 50") !== false && strpos($source, 'get_param') !== false,
    'Destination picker provides searchable products and categories' => strpos($js, 'Search products or categories') !== false && strpos($js, 'studio/targets?search=') !== false,
    'Numeric layout properties have linked range sliders' => strpos($js, "type==='number'") !== false && strpos($js, 'ark-field-range') !== false,
    'Layout properties are grouped by colour dimensions padding margin settings and style' => strpos($js, '1. COLOUR & TITLE') !== false && strpos($js, '2. DIMENSIONS') !== false && strpos($js, '3. PADDING') !== false && strpos($js, '4. MARGIN') !== false && strpos($js, '5. SETTINGS') !== false && strpos($js, '6. STYLE & ANIMATION') !== false,
    'Designer property groups and range sliders have responsive styling' => strpos($css, '.ark-property-section') !== false && strpos($css, '.ark-field-range') !== false,
    'Product destination opens ProductScreen when no parent callback is supplied' => strpos($flutter, 'widget.onProductTap != null') !== false && strpos($flutter, 'ProductScreen(id: id)') !== false,
    'Custom pages use configured app background and an empty-page fallback' => strpos($flutter, 'backgroundColor: pageBackground') !== false && strpos($flutter, 'This page is ready. Add components in ARK App Designer.') !== false,
    'Home website and deep links are normalized before opening' => strpos($home, 'https://') !== false && strpos($home, 'LaunchMode.externalApplication') !== false,
    'In-app links normalize legacy page keys and use full native destination pages' => strpos($flutter, 'nativePageKeys') !== false && strpos($flutter, "case 'wishlist':") !== false && strpos($flutter, "case 'account':") !== false && strpos($flutter, "case 'categories':") !== false && strpos($flutter, 'HomeScreen(initialTab: 1)') !== false && strpos($flutter, 'HomeScreen(initialTab: 2)') !== false && strpos($flutter, 'HomeScreen(initialTab: 3)') !== false && strpos($flutter, "lowerTarget.startsWith('page:')") !== false,
    'Legacy entrance animation aliases are accepted and mapped before rendering' => strpos($source, "'fade_in' => 'fade'") !== false && strpos($flutter, "'slide_up': 'slide'") !== false,
    'Standalone native page shell synchronizes Designer header and system status bar' => strpos($shell, 'statusBarColor: header') !== false && strpos($shell, 'heading[\'background_color\']') !== false && strpos($shell, 'SizedBox.expand(child: child)') !== false,

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
