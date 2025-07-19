<?php

// Load composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables for testing
$projectRoot = dirname(__DIR__);
if (file_exists($projectRoot . '/.env')) {
    $dotenv = Dotenv\Dotenv::createUnsafeImmutable($projectRoot);
    $dotenv->load();
}
