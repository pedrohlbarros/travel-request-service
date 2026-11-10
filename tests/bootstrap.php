<?php

declare(strict_types=1);

$testDatabase = getenv('DB_TEST_DATABASE');
$testDatabase = \is_string($testDatabase) && $testDatabase !== '' ? $testDatabase : 'travel_request_testing';

putenv("DB_DATABASE={$testDatabase}");
$_ENV['DB_DATABASE'] = $testDatabase;
$_SERVER['DB_DATABASE'] = $testDatabase;

require __DIR__ . '/../vendor/autoload.php';
