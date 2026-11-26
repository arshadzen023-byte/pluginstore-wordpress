<?php
/**
 * ARK App Designer persistence regression checks.
 * These checks intentionally cover only this WordPress repository.
 * Flutter renderer checks run in the companion ark-native-app repository.
 */
$root = dirname(__DIR__);
$php = file_get_contents($root . '/ark-app-designer.php');
$js = file_get_contents($root . '/assets/admin.js');
$css = file_get_contents($root . '/assets/admin.css');

$checks = [
    'plugin source is readable' => is_string($php) && strlen($php) > 50000,
    'admin editor source is readable' => is_string($js) && strlen($js) > 50000,
    'plugin header and class versions match' => preg_match('/^\\s*\\* Version:\\s*([0-9.]+)/m', $php, $m) && strpos($php, "const VERSION = '" . $m[1] . "'") !== false,
    'SQL slider and slide tables are created' => strpos($php, 'ark_live_sliders') !== false && strpos($php, 'ark_live_slider_items') !== false && strpos($php, 'dbDelta(') !== false,
    'public app and authenticated Studio slider routes exist' => strpos($php, '/sliders/(?P<slider_key>') !== false && strpos($php, '/studio/sliders/(?P<slider_key>') !== false,
    'legacy migration includes all banner variants' => strpos($php, "['banner_slider','banner_grid','banner_list','banner_scroll']") !== false,
    'legacy migration reads Children media' => strpos($php, "['children'] ?? []") !== false,
    'legacy media migration normalizes attachment IDs and URLs' => strpos($php, "['media_id']") !== false && strpos($php, "['src']") !== false,
    'SQL save validates all images before replacing stored rows' => strpos($php, 'Validate every selected image before deleting existing SQL rows') !== false,
    'SQL save uses transaction rollback on failure' => strpos($php, 'START TRANSACTION') !== false && strpos($php, 'ROLLBACK') !== false && strpos($php, 'COMMIT') !== false,
    'SQL payload retains image metadata and destinations' => strpos($php, 'attachment_id') !== false && strpos($php, 'image_srcset') !== false && strpos($php, 'link_value') !== false,
    'SQL payload retains slide scheduling' => strpos($php, 'starts_at') !== false && strpos($php, 'ends_at') !== false,
    'Designer exposes SQL-backed Children image editor' => strpos($js, 'CHILDREN · SQL SAVED SLIDES') !== false && strpos($js, 'Save slider to SQL') !== false,
    'Designer supports multi-image Media Library selection' => strpos($js, 'multiple:true,library:{type:') !== false,
    'Designer previews grid list and scroll distinctly' => strpos($js, "variant==='grid'") !== false && strpos($js, "variant==='list'") !== false && strpos($js, "variant==='scroll'") !== false,
    'shared dimension margin and padding controls remain available' => strpos($js, '2. DIMENSIONS') !== false && strpos($js, '3. PADDING') !== false && strpos($js, '4. MARGIN') !== false,
    'save and publish verify SQL slide counts before completion' => strpos($js, 'Image save verification failed') !== false && strpos($js, 'Publish was stopped to protect your previous images') !== false,
    'slider editor CSS is present' => strpos($css, '.ark-live-slide-item') !== false,
];

$checks['native Navigation Bar JSON defaults include all five app destinations'] =
    strpos($php, "'navigation_bar' => [") !== false &&
    strpos($php, "'nav-home'") !== false &&
    strpos($php, "'nav-categories'") !== false &&
    strpos($php, "'nav-wishlist'") !== false &&
    strpos($php, "'nav-cart'") !== false &&
    strpos($php, "'nav-account'") !== false;

$checks['native Navigation Bar sanitizer preserves fixed shifting, icon packs, links and colors'] =
    strpos($php, "['fixed','shifting']") !== false &&
    strpos($php, "['Material','Cupertino','Font Awesome','Remix Icons','FluentUI']") !== false &&
    strpos($php, '$allowed_navigation_links') !== false &&
    strpos($php, "'active_icon_family'") !== false &&
    strpos($php, "'active_background_color'") !== false &&
    strpos($php, 'sanitize_key($page_key)') !== false &&
    strpos($php, 'in_array($item[\'link\'], [\'webview\',\'posts\',\'bookings\',\'contact_form_7\'], true)') !== false;

$checks['published Designer response returns exact sanitized config for native parity verification'] =
    strpos($php, "'published_config' => $clean") !== false &&
    strpos($php, "'config' => $clean") !== false;

$checks['Native Designer Media Library REST endpoint is registered and paginated'] =
    strpos($php, "'/studio/media'") !== false &&
    strpos($php, 'api_studio_media') !== false &&
    strpos($php, "'thumbnail_url' => esc_url_raw") !== false &&
    strpos($php, "'total_pages' =>") !== false;

$failed = [];
foreach ($checks as $label => $passed) {
    if (!$passed) $failed[] = $label;
}
if ($failed) {
    fwrite(STDERR, "ARK App Designer regression checks failed: " . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
echo "ARK App Designer persistence checks passed (" . count($checks) . ")." . PHP_EOL;
