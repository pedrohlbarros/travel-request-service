<?php

declare(strict_types=1);

$commandLine = is_array($_SERVER['argv'] ?? null) ? $_SERVER['argv'] : [];
$stringArguments = array_filter($commandLine, 'is_string');
$arguments = array_values($stringArguments);
$cloverPath = $arguments[1] ?? 'build/coverage/clover.xml';
$minimum = (float) ($arguments[2] ?? '100');

if (!is_file($cloverPath)) {
    fwrite(STDERR, "Coverage report not found: {$cloverPath}\n");
    exit(1);
}

$clover = simplexml_load_file($cloverPath);
$metrics = $clover === false ? null : $clover->project->metrics;

if ($metrics === null) {
    fwrite(STDERR, "Invalid coverage report: {$cloverPath}\n");
    exit(1);
}

$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$percent = $statements === 0 ? 100.0 : $covered / $statements * 100;

printf(
    "Line coverage: %.2f%% (%d/%d), minimum %.2f%%\n",
    $percent,
    $covered,
    $statements,
    $minimum,
);

exit($percent >= $minimum ? 0 : 1);
