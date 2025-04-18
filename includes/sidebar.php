<?php
// smart-menu/admin/includes/sidebar.php
require_once '../../includes/config.php';
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

<style>
.sidebar {
    width: 250px;
    background: #2c3e50;
    color: #fff;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
}
.sidebar-header {
    padding: 20px;
    text-align: center;
}
.sidebar-nav ul {
    list-style: none;
    padding: 0;
}
.sidebar-nav li {
    margin: 10px 0;
}
.sidebar-nav a {
    color: #fff;
    display: block;
    padding: 15px 20px;
    text-decoration: none;
}
.sidebar-nav a:hover {
    background: #34495e;
}
.sidebar-nav i {
    margin-right: 10px;
}
</style>