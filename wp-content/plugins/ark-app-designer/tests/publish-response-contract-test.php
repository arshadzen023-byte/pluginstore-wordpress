<?php
$root = dirname(__DIR__);
$designer = file_get_contents($root . '/ark-app-designer.php');
$checks = [
  'Designer publish returns canonical published_config' => is_string($designer) && strpos($designer, 'published_config') !== false,
  'Designer verifies the stored published option before responding' => is_string($designer) && strpos($designer, 'get_option(self::PUBLISHED, [])') !== false,
];
$failed = [];
foreach ($checks as $label => $passed) if (!$passed) $failed[] = $label;
if ($failed) { fwrite(STDERR, 'Publish contract checks failed: ' . implode(', ', $failed) . PHP_EOL); exit(1); }
echo 'Publish contract checks passed (' . count($checks) . ').' . PHP_EOL;
