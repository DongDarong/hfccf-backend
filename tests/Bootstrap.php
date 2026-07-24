<?php

// Load Composer autoloader first
require_once __DIR__ . '/../vendor/autoload.php';

// Set environment for SQLite testing
$_ENV['DB_FOREIGN_KEYS'] = 'false';
