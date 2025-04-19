<?php
// smart-menu/admin/index.php - Admin Dashboard
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check if admin is logged in
if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit;
}

// Get stats for dashboard
try {
    $db = getDb();
    if (!$db) {
        throw new Exception('Database connection failed');
    }

    // Get total orders today
    $date = date('Y-m-d');
    $stmt = $db->prepare("SELECT COUNT(*) as total_orders, SUM(total_amount) as total_sales 
                         FROM orders 
                         WHERE DATE(created_at) = ?");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $orderStats = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Get pending orders
    $stmt = $db->prepare("SELECT COUNT(*) as pending_orders 
                         FROM orders 
                         WHERE status IN ('pending', 'confirmed', 'preparing') 
                         AND DATE(created_at) = ?");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $pendingStats = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Get active menu items today
    $stmt = $db->prepare("SELECT COUNT(*) as active_items 
                         FROM daily_menu 
                         WHERE date_available = ? AND is_available = 1");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $menuStats = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Get recent orders
    $recentOrders = [];
    $stmt = $db->prepare("SELECT o.id, o.order_number, o.status, o.total_amount, o.created_at, t.table_number
                         FROM orders o
                         JOIN tables t ON o.table_id = t.id
                         ORDER BY o.created_at DESC
                         LIMIT 5");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recentOrders[] = $row;
    }
    $stmt->close();

    // Get top selling items today
    $topItems = [];
    $stmt = $db->prepare("SELECT m.name, SUM(oi.quantity) as total_sold
                         FROM order_items oi
                         JOIN menu_items m ON oi.menu_item_id = m.id
                         JOIN orders o ON oi.order_id = o.id
                         WHERE DATE(o.created_at) = ?
                         GROUP BY oi.menu_item_id
                         ORDER BY total_sold DESC
                         LIMIT 5");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $topItems[] = $row;
    }
    $stmt->close();

    // Get recent customer feedback
    $recentFeedback = [];
    $stmt = $db->prepare("SELECT f.id, f.rating, f.comments, f.order_id, o.order_number, t.table_number
                         FROM feedback f
                         JOIN orders o ON f.order_id = o.id
                         JOIN tables t ON o.table_id = t.id
                         ORDER BY f.id DESC
                         LIMIT 5");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recentFeedback[] = $row;
    }
    $stmt->close();

    $db->close();
} catch (Exception $e) {
    error_log($e->getMessage());
    echo '<div class="error">An error occurred. Please try again later.</div>';
    exit;
}

// Helper function for currency format (prices in TSH)
function formatCurrency($amount) {
    return number_format($amount, 0) . ' TSH';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Smart Menu'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        /* Accessibility-focused styles */
        :root {
            --button-min-size: 48px; /* Minimum touch target size for accessibility */
            --text-contrast: #000; /* High contrast for readability */
            --bg-contrast: #fff;
        }

        .admin-container {
            background: var(--bg-contrast);
            color: var(--text-contrast);
        }

        .dashboard-card {
            background: var(--bg-contrast);
            color: var(--text-contrast);
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th, .data-table td {
            padding: 10px;
            text-align: left;
            font-size: 16px; /* Larger text for readability */
        }

        .rating-stars {
            color: #f39c12;
        }

        .action-btn {
            min-width: var(--button-min-size);
            min-height: var(--button-min-size);
            font-size: 16px;
            padding: 10px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Admin Sidebar -->
        <?php
        $sidebarPath = __DIR__ . '/includes/sidebar.php';
        if (file_exists($sidebarPath)) {
            include 'includes/sidebar.php';
        } else {
            error_log("Sidebar file not found at: $sidebarPath");
            echo '<div class="error">Error: Sidebar not found. Please ensure admin/includes/sidebar.php exists.</div>';
        }
        ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1>Dashboard</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Admin'; ?></span>
                    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                    <div class="stat-details">
                        <h3>Today's Orders</h3>
                        <p class="stat-number"><?php echo htmlspecialchars($orderStats['total_orders'] ?? 0); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
                    <div class="stat-details">
                        <h3>Today's Sales</h3>
                        <p class="stat-number"><?php echo formatCurrency($orderStats['total_sales'] ?? 0); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
                    <div class="stat-details">
                        <h3>Pending Orders</h3>
                        <p class="stat-number"><?php echo htmlspecialchars($pendingStats['pending_orders'] ?? 0); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-utensils"></i></div>
                    <div class="stat-details">
                        <h3>Active Menu Items</h3>
                        <p class="stat-number"><?php echo htmlspecialchars($menuStats['active_items'] ?? 0); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Recent Orders, Top Items, and Customer Feedback -->
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
                                        <td><a href="order-details.php?id=<?php echo htmlspecialchars($order['id']); ?>"><?php echo htmlspecialchars($order['order_number']); ?></a></td>
                                        <td><?php echo htmlspecialchars($order['table_number']); ?></td>
                                        <td><?php echo formatCurrency($order['total_amount']); ?></td>
                                        <td><span class="status-badge status-<?php echo htmlspecialchars(strtolower($order['status'])); ?>"><?php echo htmlspecialchars(ucfirst($order['status'])); ?></span></td>
                                        <td><?php echo htmlspecialchars(date('h:i A', strtotime($order['created_at']))); ?></td>
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
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['total_sold']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Customer Feedback -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>Customer Feedback</h2>
                        <a href="feedback.php" class="view-all">View All</a>
                    </div>
                    <div class="card-content">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Table/Room</th>
                                    <th>Rating</th>
                                    <th>Comments</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentFeedback)): ?>
                                <tr>
                                    <td colspan="4" class="no-data">No feedback available</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($recentFeedback as $feedback): ?>
                                    <tr>
                                        <td><a href="order-details.php?id=<?php echo htmlspecialchars($feedback['order_id']); ?>"><?php echo htmlspecialchars($feedback['order_number']); ?></a></td>
                                        <td><?php echo htmlspecialchars($feedback['table_number']); ?></td>
                                        <td class="rating-stars"><?php echo str_repeat('★', $feedback['rating']) . str_repeat('☆', 5 - $feedback['rating']); ?></td>
                                        <td><?php echo htmlspecialchars($feedback['comments'] ?: 'No comments'); ?></td>
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