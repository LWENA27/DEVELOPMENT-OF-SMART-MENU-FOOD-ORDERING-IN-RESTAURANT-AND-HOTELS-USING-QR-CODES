<?php
/**
 * config.php - Configuration settings for digital menu system
 */

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Site configuration
define('SITE_NAME', 'Bistro Digital');
define('SITE_URL', 'https://example.com'); // Change to your actual URL

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Change in production
define('DB_PASS', '');           // Change in production
define('DB_NAME', 'digital_menu');

// File paths and URLs
define('ROOT_DIR', dirname(__DIR__) . '/');
define('UPLOADS_DIR', ROOT_DIR . 'uploads/');
define('UPLOADS_URL', SITE_URL . '/uploads/');
define('ASSETS_URL', SITE_URL . '/assets/');

// Session settings
session_start();
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS

// Timezone
date_default_timezone_set('UTC'); // Change to your timezone

// Order status definitions
define('ORDER_STATUS_PENDING', 'pending');
define('ORDER_STATUS_PREPARING', 'preparing');
define('ORDER_STATUS_READY', 'ready');
define('ORDER_STATUS_DELIVERED', 'delivered');
define('ORDER_STATUS_CANCELLED', 'cancelled');

// Order status to display text mapping
define('ORDER_STATUS_LABELS', [
    ORDER_STATUS_PENDING => 'Pending',
    ORDER_STATUS_PREPARING => 'Preparing',
    ORDER_STATUS_READY => 'Ready',
    ORDER_STATUS_DELIVERED => 'Delivered',
    ORDER_STATUS_CANCELLED => 'Cancelled'
]);

// Order status to color mapping for UI
define('ORDER_STATUS_COLORS', [
    ORDER_STATUS_PENDING => '#ffa502',   // Orange
    ORDER_STATUS_PREPARING => '#3742fa', // Blue
    ORDER_STATUS_READY => '#2ed573',     // Green
    ORDER_STATUS_DELIVERED => '#747d8c', // Gray
    ORDER_STATUS_CANCELLED => '#ff4757'  // Red
]);