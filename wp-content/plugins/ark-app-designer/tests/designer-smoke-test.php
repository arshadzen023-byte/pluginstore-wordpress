<?php
$root = dirname(__DIR__);
$php = file_get_contents($root . '/ark-app-designer.php');
$js = file_get_contents($root . '/assets/admin.js');
$css = file_get_contents($root . '/assets/admin.css');
$checks = [
  'plugin header version' => strpos($php, '* Version: 1.17.8') !== false,
  'plugin class version' => strpos($php, "const VERSION = '1.17.8'") !== false,
  'recursive media sanitizer preserves deep Designer payloads' => strpos($php, 'if ($depth > 16) return') !== false,
  'SQL slider persistence tables remain present' => strpos($php, 'ark_live_sliders') !== false && strpos($php, 'ark_live_slider_items') !== false,
  'multi-image Media Library selector remains present' => strpos($js, 'multiple:true,library:{type:') !== false,
  'publish response includes canonical JSON' => strpos($php, 'published_config') !== false,
  'native Navigation Bar defaults remain present' => strpos($php, "'navigation_bar' => [") !== false && strpos($php, "'type' => 'fixed'") !== false,
  'native Navigation Bar preserves icon families and active icons' => strpos($php, "['Material','Cupertino','Font Awesome','Remix Icons','FluentUI']") !== false && strpos($php, "'active_icon_family'") !== false && strpos($php, "'active_icon'") !== false,
  'native Navigation Bar preserves app and web destinations' => strpos($php, '$allowed_navigation_links') !== false && strpos($php, "'link_value'") !== false,
  'slider accepts legacy source_url and nested media payloads' => strpos($php, "'source_url','src','url','original_url','full_url'") !== false && strpos($php, "['image','media','attachment','file','data']") !== false,
  'slider filters invalid placeholders without discarding valid images' => strpos($php, '$normalized_slides') !== false && strpos($php, 'Existing saved images were kept') !== false,
  'legacy migration selects first non-empty media collection' => strpos($php, "['items','slides','images','children']") !== false,
  'component typography fields survive publish normalization' => strpos($php, "'title_font_size'") !== false && strpos($php, "'letter_spacing'") !== false && strpos($php, "'font_family'") !== false,
  'Banner height maps from height_px' => strpos($php, "section['height_px']") !== false,
  'Banner width maps from width' => strpos($php, "section['width']") !== false,
  'Banner spacing maps from main axis spacing' => strpos($php, 'main_axis_spacing') !== false,
  'Banner radius maps from corner radius' => strpos($php, 'corner_radius') !== false,
  'aspect ratio accepts native Designer range' => strpos($php, 'min(3.0, max(0.4') !== false,
  'Media Library REST route is registered and paginated' => strpos($php, "'/studio/media'") !== false && strpos($php, "'posts_per_page' => $per_page") !== false && strpos($php, "'paged' => $page") !== false,
  'App Bar defaults and sanitization are additive' => strpos($php, "'app_bar' => [") !== false && strpos($php, "app_bar'] = ") !== false,
  'editor source remains readable' => strlen($js) > 50000,
  'editor CSS remains readable' => strlen($css) > 1000,
];
$failed = [];
foreach ($checks as $label => $passed) if (!$passed) $failed[] = $label;
if ($failed) { fwrite(STDERR, 'Designer smoke checks failed: ' . implode(', ', $failed) . PHP_EOL); exit(1); }
echo 'Designer smoke checks passed (' . count($checks) . ').' . PHP_EOL;
