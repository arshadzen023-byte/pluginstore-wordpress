<?php
/**
 * Source regression checks for ARK App Designer 1.7.4.
 * Run: php tests/designer-smoke-test.php
 */
$root = dirname(__DIR__);
$php = file_get_contents($root . '/ark-app-designer.php');
$js = file_get_contents($root . '/assets/admin.js');
$checks = [
    'tap mode is persisted' => strpos($php, "tap_link_type'] =") !== false,
    'slide attachment IDs are saved' => strpos($php, "attachment_id' =>") !== false,
    'slider uses canonical items list' => strpos($php, "items'] = [];") !== false,
    'in-app destination selector exists' => strpos($js, 'function inAppTargetField') !== false,
    'single media attachment ID is saved' => strpos($js, 'item.attachment_id=Number(media.id)||0') !== false,
    'multiple media attachment IDs are saved' => strpos($js, 'attachment_id:Number(media.id)||0') !== false,
    'empty slider can be published' => strpos($js, 'Add at least one image to every Image Slider') === false,
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
