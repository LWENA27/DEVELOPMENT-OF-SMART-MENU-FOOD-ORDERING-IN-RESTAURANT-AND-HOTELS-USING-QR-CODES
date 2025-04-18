<?php
// smart-menu/includes/config.php - Database and system configuration

// Database configuration (update for production)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Use secure password in production
define('DB_NAME', 'smart_menu');

// System configuration
define('SITE_NAME', 'Smart Menu');
define('SITE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/smart_menu_qr/');
define('ADMIN_URL', SITE_URL . 'admin/');

// Uploads directory (ensure it exists and is writable)
$uploadsDir = $_SERVER['DOCUMENT_ROOT'] . '/smart_menu_qr/assets/uploads/';
if (!is_dir($uploadsDir)) {
    @mkdir($uploadsDir, 0755, true);
}
define('UPLOADS_DIR', $uploadsDir);
define('UPLOADS_URL', SITE_URL . 'assets/uploads/');

// Session timeout in seconds (3 hours)
define('SESSION_TIMEOUT', 10800);

// QR code configuration (ensure directory exists and is writable)
$qrCodeDir = $_SERVER['DOCUMENT_ROOT'] . '/smart_menu_qr/qr-gen/codes/';
if (!is_dir($qrCodeDir)) {
    @mkdir($qrCodeDir, 0755, true);
}
define('QR_CODE_DIR', $qrCodeDir);
define('QR_CODE_URL', SITE_URL . 'qr-gen/codes/');

// Number of items per page for pagination
define('ITEMS_PER_PAGE', 10);
?>