<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set error log path
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
ini_set('log_errors', 1);

// Log startup errors
error_log("=== Application Started at " . date('Y-m-d H:i:s') . " ===");
?> 