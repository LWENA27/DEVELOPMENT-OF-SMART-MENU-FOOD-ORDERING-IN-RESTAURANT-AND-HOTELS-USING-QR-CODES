<?php
// smart-menu/admin/reports.php - Sales Reports
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check if admin is logged in
if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit;
}

// Initialize variables
$errors = [];
$success = '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$where_conditions = [];
$params = [];
$types = '';

// Open database connection
$db = getDb();
if (!$db) {
    $errors[] = 'Database connection failed.';
}

// Handle date range filter
if ($date_from && $date_to && empty($errors)) {
    $date_from = date('Y-m-d 00:00:00', strtotime($date_from));
    $date_to = date('Y-m-d 23:59:59', strtotime($date_to));
    if ($date_from > $date_to) {
        $errors[] = 'Start date cannot be after end date.';
    } else {
        $where_conditions[] = "o.created_at BETWEEN ? AND ?";
        $params[] = $date_from;
        $params[] = $date_to;
        $types .= "ss";
    }
}

// Fetch total revenue (only for Delivered orders)
$total_revenue = 0;
if (empty($errors)) {
    $where_conditions[] = "o.status = 'delivered'";
    $where_clause = !empty($where_conditions) ? " WHERE " . implode(" AND ", $where_conditions) : "";
    $query = "SELECT SUM(total_amount) as total FROM orders o" . $where_clause;
    $stmt = $db->prepare($query);
    if ($stmt) {
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $total_revenue = $result->fetch_assoc()['total'] ?? 0;
        $stmt->close();
    } else {
        $errors[] = 'Error fetching total revenue: ' . $db->error;
    }
}

// Fetch total orders
$total_orders = 0;
if (empty($errors)) {
    $where_clause = !empty($where_conditions) && count($where_conditions) > 1 ? " WHERE " . implode(" AND ", array_slice($where_conditions, 0, -1)) : "";
    $query = "SELECT COUNT(*) as total FROM orders o" . $where_clause;
    $stmt = $db->prepare($query);
    if ($stmt) {
        if ($params && $types !== "s") { // Exclude status parameter
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $total_orders = $result->fetch_assoc()['total'] ?? 0;
        $stmt->close();
    } else {
        $errors[] = 'Error fetching total orders: ' . $db->error;
    }
}

// Fetch orders by status
$orders_by_status = ['pending' => 0, 'confirmed' => 0, 'preparing' => 0, 'ready' => 0, 'delivered' => 0, 'cancelled' => 0];
if (empty($errors)) {
    $where_clause = !empty($where_conditions) && count($where_conditions) > 1 ? " WHERE " . implode(" AND ", array_slice($where_conditions, 0, -1)) : "";
    $query = "SELECT status, COUNT(*) as count FROM orders o" . $where_clause . " GROUP BY status";
    $stmt = $db->prepare($query);
    if ($stmt) {
        if ($params && $types !== "s") {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $orders_by_status[$row['status']] = $row['count'];
        }
        $stmt->close();
    } else {
        $errors[] = 'Error fetching orders by status: ' . $db->error;
    }
}

// Fetch top-selling items
$top_items = [];
if (empty($errors)) {
    $where_clause = !empty($where_conditions) && count($where_conditions) > 1 ? " WHERE " . implode(" AND ", array_slice($where_conditions, 0, -1)) : "";
    $query = "
        SELECT mi.name, SUM(oi.quantity) as total_quantity, SUM(oi.quantity * oi.unit_price) as total_revenue
        FROM order_items oi
        JOIN menu_items mi ON oi.menu_item_id = mi.id
        JOIN orders o ON oi.order_id = o.id
        $where_clause
        GROUP BY mi.id, mi.name
        ORDER BY total_quantity DESC
        LIMIT 10
    ";
    $stmt = $db->prepare($query);
    if ($stmt) {
        if ($params && $types !== "s") {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $top_items[] = $row;
        }
        $stmt->close();
    } else {
        $errors[] = 'Error fetching top-selling items: ' . $db->error;
    }
}

// Close the database connection
if ($db) {
    $db->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports - <?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Smart Menu'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .form-group {
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
        }

        .dashboard-card {
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 20px;
            width: 100%;
            max-width: 1200px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: var(--background-color);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            box-shadow: var(--shadow);
        }

        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 1.2em;
            color: var(--text-color);
        }

        .stat-card p {
            margin: 0;
            font-size: 1.5em;
            color: var(--primary-color);
        }

        .filter-form {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .data-table {
                overflow-x: auto;
                display: block;
            }

            .filter-form {
                flex-direction: column;
            }
        }

        .error-container, .success-message {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 8px;
        }

        .error-container {
            background-color: rgba(255, 71, 87, 0.1);
            border: 1px solid var(--error-color);
        }

        .success-message {
            background-color: rgba(46, 213, 115, 0.1);
            border: 1px solid var(--success-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .error-message {
            color: var(--error-color);
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php
        $sidebarPath = __DIR__ . '/includes/sidebar.php';
        if (file_exists($sidebarPath)) {
            include 'includes/sidebar.php';
        } else {
            error_log("Sidebar file not found at: $sidebarPath");
            echo '<div class="error">Error: Sidebar not found.</div>';
        }
        ?>
        
        <main class="main-content">
            <div class="header">
                <h1>Sales Reports</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Admin'; ?></span>
                    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="error-container">
                    <?php foreach ($errors as $error): ?>
                        <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <p><?php echo htmlspecialchars($success); ?></p>
                </div>
            <?php endif; ?>

            <!-- Date Range Filter -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2>Filter Reports</h2>
                </div>
                <form method="GET" action="reports.php" class="filter-form">
                    <div class="form-group">
                        <label for="date_from">From</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_to">To</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary btn-small">Filter</button>
                    <a href="reports.php" class="btn btn-secondary btn-small">Clear Filter</a>
                </form>
            </div>

            <!-- Key Metrics -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2>Key Metrics</h2>
                </div>
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Total Revenue (Delivered Orders)</h3>
                        <p><?php echo number_format($total_revenue, 2); ?> TZS</p>
                    </div>
                    <div class="stat-card">
                        <h3>Total Orders</h3>
                        <p><?php echo htmlspecialchars($total_orders); ?></p>
                    </div>
                    <?php foreach ($orders_by_status as $status => $count): ?>
                        <div class="stat-card">
                            <h3><?php echo htmlspecialchars(ucfirst($status)); ?> Orders</h3>
                            <p><?php echo htmlspecialchars($count); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Top Selling Items -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2>Top Selling Items</h2>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Quantity Sold</th>
                            <th>Total Revenue (TZS)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($top_items)): ?>
                            <tr>
                                <td colspan="3" class="no-data">No sales data available.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($top_items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['total_quantity']); ?></td>
                                    <td><?php echo number_format($item['total_revenue'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>