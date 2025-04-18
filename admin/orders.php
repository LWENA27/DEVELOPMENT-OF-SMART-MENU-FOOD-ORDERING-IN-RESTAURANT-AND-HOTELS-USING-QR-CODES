<?php
// smart-menu/admin/orders.php - Manage Orders
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
$action = $_GET['action'] ?? '';
$order_id = $_GET['id'] ?? 0;
$errors = [];
$success = '';

// Open database connection
$db = getDb();
if (!$db) {
    $errors[] = 'Database connection failed.';
}

// Handle status update
if ($action === 'update_status' && $order_id > 0 && empty($errors)) {
    $new_status = $_POST['status'] ?? '';
    $valid_statuses = ['pending', 'confirmed', 'preparing', 'ready', 'delivered', 'cancelled'];
    if (in_array($new_status, $valid_statuses)) {
        $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $new_status, $order_id);
            if ($stmt->execute()) {
                $success = 'Order status updated successfully.';
            } else {
                $errors[] = 'Failed to update order status: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors[] = 'Error preparing status update statement: ' . $db->error;
        }
    } else {
        $errors[] = 'Invalid status selected.';
    }
}

// Fetch order details for viewing
$order_details = null;
$order_items = [];
if ($action === 'view' && $order_id > 0 && empty($errors)) {
    $stmt = $db->prepare("
        SELECT o.*, t.table_number, t.is_room, t.location 
        FROM orders o 
        JOIN tables t ON o.table_id = t.id 
        WHERE o.id = ?
    ");
    if ($stmt) {
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $order_details = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($order_details) {
            $stmt = $db->prepare("
                SELECT oi.*, mi.name AS item_name 
                FROM order_items oi 
                JOIN menu_items mi ON oi.menu_item_id = mi.id 
                WHERE oi.order_id = ?
            ");
            if ($stmt) {
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    // Fetch customizations for each order item
                    $row['customizations'] = [];
                    $custom_stmt = $db->prepare("
                        SELECT oio.*, io.name AS option_name 
                        FROM order_item_options oio 
                        JOIN item_options io ON oio.item_option_id = io.id 
                        WHERE oio.order_item_id = ?
                    ");
                    if ($custom_stmt) {
                        $custom_stmt->bind_param("i", $row['id']);
                        $custom_stmt->execute();
                        $custom_result = $custom_stmt->get_result();
                        while ($custom_row = $custom_result->fetch_assoc()) {
                            $row['customizations'][] = $custom_row;
                        }
                        $custom_stmt->close();
                    }
                    $order_items[] = $row;
                }
                $stmt->close();
            } else {
                $errors[] = 'Error preparing order items statement: ' . $db->error;
            }
        }
    } else {
        $errors[] = 'Error preparing order details statement: ' . $db->error;
    }
}

// Fetch all orders
$orders = [];
if (empty($errors)) {
    $result = $db->query("
        SELECT o.*, t.table_number, t.is_room, t.location 
        FROM orders o 
        JOIN tables t ON o.table_id = t.id 
        ORDER BY o.created_at DESC
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    } else {
        $errors[] = 'Failed to fetch orders: ' . $db->error;
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
    <title>Manage Orders - <?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Smart Menu'; ?></title>
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

        .btn-small {
            padding: 6px 12px;
            font-size: 14px;
            margin-right: 5px;
        }

        .btn-secondary {
            background-color: var(--error-color);
            color: white;
        }

        .btn-secondary:hover {
            background-color: #ff6b81;
        }

        .btn-success {
            background-color: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background-color: #2ed573;
        }

        @media (max-width: 768px) {
            .data-table {
                overflow-x: auto;
                display: block;
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

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow: auto;
        }

        .modal-content {
            background-color: var(--card-bg);
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            box-shadow: var(--shadow);
        }

        .close {
            color: var(--text-color);
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: var(--error-color);
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
                <h1>Manage Orders</h1>
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

            <!-- Orders List -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2>Orders</h2>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all"></th>
                            <th>Order Number</th>
                            <th>Table/Room</th>
                            <th>Total Amount (TZS)</th>
                            <th>Status</th>
                            <th>Order Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="7" class="no-data">No orders available.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><input type="checkbox" name="order_ids[]" value="<?php echo $order['id']; ?>"></td>
                                    <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                    <td><?php echo htmlspecialchars($order['table_number']) . ($order['is_room'] ? ' (Room)' : ' (Table)'); ?></td>
                                    <td><?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td>
                                        <form method="POST" action="orders.php?action=update_status&id=<?php echo $order['id']; ?>" style="display:inline;">
                                            <select name="status" onchange="this.form.submit()">
                                                <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="confirmed" <?php echo $order['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                <option value="preparing" <?php echo $order['status'] === 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                                                <option value="ready" <?php echo $order['status'] === 'ready' ? 'selected' : ''; ?>>Ready</option>
                                                <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td><?php echo htmlspecialchars($order['created_at']); ?></td>
                                    <td>
                                        <button class="btn btn-primary btn-small view-details" data-order-id="<?php echo $order['id']; ?>"><i class="fas fa-eye"></i> View</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Modal for Order Details -->
            <div id="orderModal" class="modal">
                <div class="modal-content">
                    <span class="close">×</span>
                    <h2>Order Details</h2>
                    <?php if ($order_details): ?>
                        <p><strong>Order Number:</strong> <?php echo htmlspecialchars($order_details['order_number']); ?></p>
                        <p><strong>Table/Room:</strong> <?php echo htmlspecialchars($order_details['table_number']) . ($order_details['is_room'] ? ' (Room)' : ' (Table)'); ?></p>
                        <p><strong>Location:</strong> <?php echo htmlspecialchars($order_details['location']); ?></p>
                        <p><strong>Total Amount:</strong> <?php echo number_format($order_details['total_amount'], 2); ?> TZS</p>
                        <p><strong>Status:</strong> <?php echo htmlspecialchars($order_details['status']); ?></p>
                        <p><strong>Notes:</strong> <?php echo htmlspecialchars($order_details['notes'] ?? 'N/A'); ?></p>
                        <p><strong>Order Date:</strong> <?php echo htmlspecialchars($order_details['created_at']); ?></p>
                        <h3>Items</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Item Name</th>
                                    <th>Quantity</th>
                                    <th>Unit Price (TZS)</th>
                                    <th>Subtotal (TZS)</th>
                                    <th>Special Instructions</th>
                                    <th>Customizations</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($order_items)): ?>
                                    <tr>
                                        <td colspan="6" class="no-data">No items in this order.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($order_items as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                            <td><?php echo number_format($item['unit_price'], 2); ?></td>
                                            <td><?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($item['special_instructions'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php if (!empty($item['customizations'])): ?>
                                                    <ul>
                                                        <?php foreach ($item['customizations'] as $custom): ?>
                                                            <li><?php echo htmlspecialchars($custom['option_name']) . ' (+' . number_format($custom['price_adjustment'], 2) . ' TZS)'; ?></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php else: ?>
                                                    None
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No order details available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Select all checkboxes
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="order_ids[]"]');
            checkboxes.forEach(checkbox => checkbox.checked = this.checked);
        });

        // Modal functionality
        const modal = document.getElementById('orderModal');
        const closeBtn = document.getElementsByClassName('close')[0];
        const viewButtons = document.getElementsByClassName('view-details');

        Array.from(viewButtons).forEach(button => {
            button.addEventListener('click', function() {
                const orderId = this.getAttribute('data-order-id');
                window.location.href = 'orders.php?action=view&id=' + orderId;
            });
        });

        <?php if ($action === 'view' && $order_details): ?>
            modal.style.display = 'block';
        <?php endif; ?>

        closeBtn.addEventListener('click', function() {
            modal.style.display = 'none';
            window.history.pushState({}, '', 'orders.php');
        });

        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
                window.history.pushState({}, '', 'orders.php');
            }
        });
    </script>
</body>
</html>