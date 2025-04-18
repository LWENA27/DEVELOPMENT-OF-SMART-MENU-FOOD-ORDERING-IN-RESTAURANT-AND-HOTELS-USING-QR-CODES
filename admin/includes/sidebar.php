<?php
// smart-menu/admin/includes/sidebar.php
$configPath = __DIR__ . '/../../includes/config.php';
if (!file_exists($configPath)) {
    error_log("Config file not found at: $configPath");
    echo '<div class="error">Error: Configuration file not found. Please ensure includes/config.php exists.</div>';
    exit;
}
require_once $configPath;
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <h2><?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Smart Menu'; ?></h2>
    </div>
    <nav class="sidebar-nav">
        <ul>
            <li><a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="menu.php"><i class="fas fa-utensils"></i> Manage Menu</a></li>
            <li><a href="daily-menu.php"><i class="fas fa-calendar-day"></i> Today's Menu</a></li>
            <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="qr-codes.php"><i class="fas fa-qrcode"></i> QR Codes</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>
</aside>