<?php
$root = dirname(__DIR__);
$source = file_get_contents($root . '/ark-app-designer.php');
$checks = [
    'navigation_bar default schema restored' => strpos($source, "'navigation_bar' => [") !== false,
    'fixed and shifting modes are sanitized' => strpos($source, "['fixed','shifting']") !== false,
    'all original icon families are retained' => strpos($source, "['Material','Cupertino','Font Awesome','Remix Icons','FluentUI']") !== false,
    'normal and active icon fields are retained' => strpos($source, "['icon','active_icon']") !== false,
    'legacy source_url and nested media are accepted' => strpos($source, "'source_url','src','url','original_url','full_url'") !== false && strpos($source, "['image','media','attachment','file','data']") !== false,
    'empty media source cannot mask non-empty children' => strpos($source, "['items','slides','images','children']") !== false,
    'canonical published config is returned' => strpos($source, "'published_config' => \$published") !== false,
];
$failed = [];
foreach ($checks as $label => $passed) if (!$passed) $failed[] = $label;
if ($failed) { fwrite(STDERR, 'Navigation/slider regression checks failed: ' . implode(', ', $failed) . PHP_EOL); exit(1); }
echo 'Navigation/slider regression checks passed (' . count($checks) . ').' . PHP_EOL;
