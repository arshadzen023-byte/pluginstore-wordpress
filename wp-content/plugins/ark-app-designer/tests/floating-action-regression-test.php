<?php
$root = dirname(__DIR__);
$source = file_get_contents($root . '/ark-app-designer.php');
$checks = [
    'per-page Floating Action Button defaults are additive' => strpos($source, "'floating_actions' => []") !== false,
    'floating actions are sanitized separately from page dimensions' => strpos($source, '$clean_floating_actions') !== false,
    'floating action supports all five existing icon families' => strpos($source, "['Material','Cupertino','Font Awesome','Remix Icons','FluentUI']") !== false,
    'floating action page keys include core and custom pages' => strpos($source, "'home','search','categories','product','account','wishlist','cart','orders','notifications'") !== false && strpos($source, "custom_[a-z0-9_]{1,40}") !== false,
    'floating action supports in-app, web and deep links' => strpos($source, "['in_app','chrome','deeplink']") !== false,
    'floating action size and offsets are bounded' => strpos($source, "'size' => min(76, max(40") !== false && strpos($source, "'margin_bottom' => min(120, max(0") !== false,
    'existing layout spacing sanitizer remains intact' => strpos($source, "'padding_top' => [0, 120]") !== false && strpos($source, "'margin_top' => [0, 120]") !== false,
    'existing app identity defaults remain GlowHit' => strpos($source, "'name' => 'GlowHit'") !== false,
];
$failed = [];
foreach ($checks as $label => $passed) if (!$passed) $failed[] = $label;
if ($failed) { fwrite(STDERR, 'Floating action regression checks failed: ' . implode(', ', $failed) . PHP_EOL); exit(1); }
echo 'Floating action regression checks passed (' . count($checks) . ').' . PHP_EOL;
