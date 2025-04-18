<?php
// smart-menu/admin/daily-menu.php - Manage Daily Menu
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
$daily_menu_id = $_GET['id'] ?? 0;
$selected_date = $_GET['date'] ?? date('Y-m-d');
$errors = [];
$success = '';

// Open database connection
$db = getDb();
if (!$db) {
    $errors[] = 'Database connection failed.';
}

// Handle form submissions (add item to daily menu)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add' && empty($errors)) {
    $menu_item_id = $_POST['menu_item_id'] ?? 0;
    $special_price = !empty($_POST['special_price']) ? floatval($_POST['special_price']) : null;
    $is_available = isset($_POST['is_available']) ? 1 : 0;

    // Validate inputs
    if ($menu_item_id <= 0) $errors[] = 'Please select a menu item.';
    if ($special_price !== null && $special_price < 0) $errors[] = 'Special price cannot be negative.';

    if (empty($errors)) {
        $stmt = $db->prepare("
            INSERT INTO daily_menu (menu_item_id, date_available, is_available, special_price)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE is_available = ?, special_price = ?
        ");
        if ($stmt) {
            $stmt->bind_param("isiddi", $menu_item_id, $selected_date, $is_available, $special_price, $is_available, $special_price);
            if ($stmt->execute()) {
                $success = 'Menu item added to daily menu successfully.';
            } else {
                $errors[] = 'Failed to add menu item to daily menu: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors[] = 'Error preparing insert statement: ' . $db->error;
        }
    }
}

// Handle update availability or special price
if ($action === 'update' && $daily_menu_id > 0 && empty($errors)) {
    $is_available = $_POST['is_available'] ?? 0;
    $special_price = !empty($_POST['special_price']) ? floatval($_POST['special_price']) : null;

    if ($special_price !== null && $special_price < 0) {
        $errors[] = 'Special price cannot be negative.';
    } else {
        $stmt = $db->prepare("UPDATE daily_menu SET is_available = ?, special_price = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("idi", $is_available, $special_price, $daily_menu_id);
            if ($stmt->execute()) {
                $success = 'Daily menu item updated successfully.';
            } else {
                $errors[] = 'Failed to update daily menu item: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors[] = 'Error preparing update statement: ' . $db->error;
        }
    }
}

// Handle delete action
if ($action === 'delete' && $daily_menu_id > 0 && empty($errors)) {
    $stmt = $db->prepare("DELETE FROM daily_menu WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $daily_menu_id);
        if ($stmt->execute()) {
            $success = 'Daily menu item removed successfully.';
        } else {
            $errors[] = 'Failed to remove daily menu item: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $errors[] = 'Error preparing delete statement: ' . $db->error;
    }
}

// Fetch all menu items for the dropdown
$menu_items = [];
if (empty($errors)) {
    $result = $db->query("SELECT mi.id, mi.name, c.name as category_name FROM menu_items mi JOIN categories c ON mi.category_id = c.id ORDER BY c.name, mi.name");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $menu_items[] = $row;
        }
    } else {
        $errors[] = 'Failed to fetch menu items: ' . $db->error;
    }
}

// Fetch daily menu items for the selected date
$daily_menu_items = [];
if (empty($errors)) {
    $stmt = $db->prepare("
        SELECT dm.*, mi.name as item_name, mi.price as original_price, c.name as category_name 
        FROM daily_menu dm 
        JOIN menu_items mi ON dm.menu_item_id = mi.id 
        JOIN categories c ON mi.category_id = c.id 
        WHERE dm.date_available = ?
        ORDER BY c.name, mi.name
    ");
    if ($stmt) {
        $stmt->bind_param("s", $selected_date);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $daily_menu_items[] = $row;
        }
        $stmt->close();
    } else {
        $errors[] = 'Error fetching daily menu items: ' . $db->error;
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
    <title>Manage Daily Menu - <?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Smart Menu'; ?></title>
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

        .filter-form, .add-form {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 20px;
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

        @media (max-width: 768px) {
            .data-table {
                overflow-x: auto;
                display: block;
            }

            .filter-form, .add-form {
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
                <h1>Manage Daily Menu</h1>
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

            <!-- Date Selector -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2>Select Date</h2>
                </div>
                <form method="GET" action="daily-menu.php" class="filter-form">
                    <div class="form-group">
                        <label for="date">Date</label>
                        <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($selected_date); ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-small">View Menu</button>
                </form>
            </div>

            <!-- Add Item to Daily Menu -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2>Add Item to Daily Menu (<?php echo htmlspecialchars($selected_date); ?>)</h2>
                </div>
                <form method="POST" action="daily-menu.php?action=add&date=<?php echo htmlspecialchars($selected_date); ?>" class="add-form">
                    <div class="form-group">
                        <label for="menu_item_id">Menu Item</label>
                        <select id="menu_item_id" name="menu_item_id" required>
                            <option value="">Select Item</option>
                            <?php foreach ($menu_items as $item): ?>
                                <option value="<?php echo $item['id']; ?>">
                                    <?php echo htmlspecialchars($item['category_name'] . ' - ' . $item['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="special_price">Special Price (TZS, optional)</label>
                        <input type="number" id="special_price" name="special_price" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_available" checked>
                            Available
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-small">Add to Daily Menu</button>
                </form>
            </div>

            <!-- Daily Menu List -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2>Daily Menu (<?php echo htmlspecialchars($selected_date); ?>)</h2>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Item Name</th>
                            <th>Original Price (TZS)</th>
                            <th>Special Price (TZS)</th>
                            <th>Available</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($daily_menu_items)): ?>
                            <tr>
                                <td colspan="6" class="no-data">No items in the daily menu for this date.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($daily_menu_items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td><?php echo number_format($item['original_price'], 2); ?></td>
                                    <td><?php echo $item['special_price'] !== null ? number_format($item['special_price'], 2) : 'N/A'; ?></td>
                                    <td>
                                        <form method="POST" action="daily-menu.php?action=update&id=<?php echo $item['id']; ?>&date=<?php echo htmlspecialchars($selected_date); ?>" style="display:inline;">
                                            <input type="hidden" name="special_price" value="<?php echo htmlspecialchars($item['special_price']); ?>">
                                            <input type="checkbox" name="is_available" onchange="this.form.submit()" <?php echo $item['is_available'] ? 'checked' : ''; ?>>
                                        </form>
                                    </td>
                                    <td>
                                        <a href="daily-menu.php?action=update&id=<?php echo $item['id']; ?>&date=<?php echo htmlspecialchars($selected_date); ?>" class="btn btn-primary btn-small"><i class="fas fa-edit"></i></a>
                                        <a href="daily-menu.php?action=delete&id=<?php echo $item['id']; ?>&date=<?php echo htmlspecialchars($selected_date); ?>" class="btn btn-secondary btn-small" onclick="return confirm('Are you sure you want to remove this item from the daily menu?');"><i class="fas fa-trash"></i></a>
                                    </td>
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