<?php
// includes/config.php - Database and system configuration

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'smart_menu');

// System configuration
define('SITE_NAME', 'Smart Menu');
define('SITE_URL', 'http://localhost/smart-menu/');
define('ADMIN_URL', SITE_URL . 'admin/');
define('UPLOADS_DIR', $_SERVER['DOCUMENT_ROOT'] . '/smart-menu/assets/uploads/');
define('UPLOADS_URL', SITE_URL . 'assets/uploads/');

// Session timeout in seconds (3 hours)
define('SESSION_TIMEOUT', 10800);

// QR code configuration
define('QR_CODE_DIR', $_SERVER['DOCUMENT_ROOT'] . '/smart-menu/qr-gen/codes/');
define('QR_CODE_URL', SITE_URL . 'qr-gen/codes/');

// Number of items per page for pagination
define('ITEMS_PER_PAGE', 10);
?>