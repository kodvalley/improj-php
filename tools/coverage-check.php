<?php

declare(strict_types=1);

// Fails when line coverage in a Clover report is below a minimum.
// Usage: php tools/coverage-check.php build/clover.xml 90

$file = $argv[1] ?? 'build/clover.xml';
$minimum = (float) ($argv[2] ?? 90);

if (!is_file($file)) {
    fwrite(\STDERR, "Coverage report not found: {$file}\n");
    exit(2);
}
$xml = simplexml_load_file($file);
if ($xml === false || !isset($xml->project->metrics)) {
    fwrite(\STDERR, "Not a Clover report: {$file}\n");
    exit(2);
}
$metrics = $xml->project->metrics;
$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$percent = $statements === 0 ? 100.0 : 100.0 * $covered / $statements;

printf("Line coverage: %.2f%% (%d/%d), minimum %.2f%%\n", $percent, $covered, $statements, $minimum);
exit($percent + 1e-9 < $minimum ? 1 : 0);
