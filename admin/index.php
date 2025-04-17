<!-- <?php
// admin/index.php - Admin Dashboard
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check if admin is logged in
if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit;
}

// Get stats for dashboard
$db = getDb();

// Get total orders today
$date = date('Y-m-d');
$result = $db->query("SELECT COUNT(*) as total_orders, 
                     SUM(total_amount) as total_sales 
                     FROM orders 
                     WHERE DATE(created_at) = '$date'");
$orderStats = $result->fetch_assoc();

// Get pending orders
$result = $db->query("SELECT COUNT(*) as pending_orders 
                     FROM orders 
                     WHERE status IN ('pending', 'confirmed', 'preparing') 
                     AND DATE(created_at) = '$date'");
$pendingStats = $result->fetch_assoc();

// Get active menu items today
$result = $db->query("SELECT COUNT(*) as active_items 
                     FROM daily_menu 
                     WHERE date_available = '$date' AND is_available = 1");
$menuStats = $result->fetch_assoc();

// Get recent orders
$recentOrders = [];
$result = $db->query("SELECT o.id, o.order_number, o.status, o.total_amount, o.created_at, t.table_number
                     FROM orders o
                     JOIN tables t ON o.table_id = t.id
                     ORDER BY o.created_at DESC
                     LIMIT 5");
while ($row = $result->fetch_assoc()) {
    $recentOrders[] = $row;
}

// Get top selling items today
$topItems = [];
$result = $db->query("SELECT m.name, SUM(oi.quantity) as total_sold
                     FROM order_items oi
                     JOIN menu_items m ON oi.menu_item_id = m.id
                     JOIN orders o ON oi.order_id = o.id
                     WHERE DATE(o.created_at) = '$date'
                     GROUP BY oi.menu_item_id
                     ORDER BY total_sold DESC
                     LIMIT 5");
while ($row = $result->fetch_assoc()) {
    $topItems[] = $row;
}
?> -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="admin-container">
        <!-- Admin Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1>Dashboard</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION['user_name']; ?></span>
                    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                    <div class="stat-details">
                        <h3>Today's Orders</h3>
                        <p class="stat-number"><?php echo $orderStats['total_orders'] ?? 0; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
                    <div class="stat-details">
                        <h3>Today's Sales</h3>
                        <p class="stat-number">$<?php echo number_format($orderStats['total_sales'] ?? 0, 2); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
                    <div class="stat-details">
                        <h3>Pending Orders</h3>
                        <p class="stat-number"><?php echo $pendingStats['pending_orders'] ?? 0; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-utensils"></i></div>
                    <div class="stat-details">
                        <h3>Active Menu Items</h3>
                        <p class="stat-number"><?php echo $menuStats['active_items'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Recent Orders and Top Items -->
            <div class="dashboard-grid">
                <!-- Recent Orders -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>Recent Orders</h2>
                        <a href="orders.php" class="view-all">View All</a>
                    </div>
                    <div class="card-content">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Table/Room</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentOrders)): ?>
                                <tr>
                                    <td colspan="5" class="no-data">No recent orders</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td><a href="order-details.php?id=<?php echo $order['id']; ?>"><?php echo $order['order_number']; ?></a></td>
                                        <td><?php echo $order['table_number']; ?></td>
                                        <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td><span class="status-badge status-<?php echo strtolower($order['status']); ?>"><?php echo ucfirst($order['status']); ?></span></td>
                                        <td><?php echo date('h:i A', strtotime($order['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Top Selling Items -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>Top Selling Items</h2>
                        <a href="reports.php" class="view-all">View Reports</a>
                    </div>
                    <div class="card-content">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Units Sold</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($topItems)): ?>
                                <tr>
                                    <td colspan="2" class="no-data">No sales data available</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($topItems as $item): ?>
                                    <tr>
                                        <td><?php echo $item['name']; ?></td>
                                        <td><?php echo $item['total_sold']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <h2>Quick Actions</h2>
                <div class="action-buttons">
                    <a href="menu.php" class="action-btn">
                        <i class="fas fa-utensils"></i>
                        <span>Manage Menu</span>
                    </a>
                    <a href="daily-menu.php" class="action-btn">
                        <i class="fas fa-calendar-day"></i>
                        <span>Today's Menu</span>
                    </a>
                    <a href="orders.php" class="action-btn">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Process Orders</span>
                    </a>
                    <a href="qr-codes.php" class="action-btn">
                        <i class="fas fa-qrcode"></i>
                        <span>QR Codes</span>
                    </a>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/admin.js"></script>
</body>
</html>