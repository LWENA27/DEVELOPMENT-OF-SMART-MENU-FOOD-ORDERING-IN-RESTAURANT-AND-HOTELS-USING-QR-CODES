<?php
// smart-menu/admin/menu.php - Manage Menu Items
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
$menu_item_id = $_GET['id'] ?? 0;
$errors = [];
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $is_available = isset($_POST['is_available']) ? 1 : 0;

    // Validate inputs
    if (empty($name)) $errors[] = 'Name is required.';
    if ($price <= 0) $errors[] = 'Price must be greater than 0.';
    if ($category_id <= 0) $errors[] = 'Category is required.';
    if ($stock < 0) $errors[] = 'Stock cannot be negative.';

    if (empty($errors)) {
        $db = getDb();
        if (!$db) {
            $errors[] = 'Database connection failed.';
        } else {
            if ($action === 'edit' && $menu_item_id > 0) {
                // Update existing menu item
                $stmt = $db->prepare("UPDATE menu_items SET name = ?, description = ?, price = ?, category_id = ?, stock = ?, is_available = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("ssdiiii", $name, $description, $price, $category_id, $stock, $is_available, $menu_item_id);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    die("Error preparing update statement: " . $db->error);
                }
            } else {
                // Add new menu item
                $stmt = $db->prepare("INSERT INTO menu_items (name, description, price, category_id, stock, is_available) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("ssdiii", $name, $description, $price, $category_id, $stock, $is_available);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    die("Error preparing insert statement: " . $db->error);
                }
            }
            

            if ($stmt->execute()) {
                $success = $action === 'edit' ? 'Menu item updated successfully.' : 'Menu item added successfully.';
            } else {
                $errors[] = 'Failed to save menu item: ' . $db->error;
            }
            $stmt->close();
        }
    }
}

// Handle delete action
if ($action === 'delete' && $menu_item_id > 0) {
    $db = getDb();
    if ($db) {
        $stmt = $db->prepare("DELETE FROM menu_items WHERE id = ?");
        $stmt->bind_param("i", $menu_item_id);
        if ($stmt->execute()) {
            $success = 'Menu item deleted successfully.';
        } else {
            $errors[] = 'Failed to delete menu item: ' . $db->error;
        }
        $stmt->close();
    } else {
        $errors[] = 'Database connection failed.';
    }
}

// Handle copy action
if ($action === 'copy' && $menu_item_id > 0) {
    $db = getDb();
    if ($db) {
        $stmt = $db->prepare("SELECT name, description, price, category_id, stock, is_available FROM menu_items WHERE id = ?");
        $stmt->bind_param("i", $menu_item_id);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($item) {
            $stmt = $db->prepare("INSERT INTO menu_items (name, description, price, category_id, stock, is_available) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdiii", $item['name'], $item['description'], $item['price'], $item['category_id'], $item['stock'], $item['is_available']);
            if ($stmt->execute()) {
                $success = 'Menu item copied successfully.';
            } else {
                $errors[] = 'Failed to copy menu item: ' . $db->error;
            }
            $stmt->close();
        }
    } else {
        $errors[] = 'Database connection failed.';
    }
}

// Fetch menu item for editing
$menu_item = null;
if ($action === 'edit' && $menu_item_id > 0) {
    $db = getDb();
    if ($db) {
        $stmt = $db->prepare("SELECT * FROM menu_items WHERE id = ?");
        $stmt->bind_param("i", $menu_item_id);
        $stmt->execute();
        $menu_item = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// Fetch all menu items
$menu_items = [];
if (!isset($db) || !$db) {
    $db = getDb();
}
if ($db) {
    $result = $db->query("SELECT * FROM menu_items ORDER BY category_id, name");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $menu_items[] = $row;
        }
    } else {
        $errors[] = 'Failed to fetch menu items: ' . $db->error;
    }
}

// Close database connection at the end of all operations
if (isset($db) && $db) {
    $db->close();
}

// Define category mapping for display
$category_names = [
    1 => 'Main Dishes',
    2 => 'Sides',
    3 => 'Drinks',
    4 => 'Desserts'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Menu - <?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Smart Menu'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        /* Form layout improvements */
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

        .dashboard-card form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 0;
            max-width: 100%;
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

        /* Fix mobile layout */
        @media (max-width: 768px) {
            .dashboard-card form {
                grid-template-columns: 1fr;
            }

            .data-table {
                overflow-x: auto;
                display: block;
            }
        }

        /* Improve error and success messages */
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

        /* Form field styling */
        textarea {
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 8px 12px;
            width: 100%;
            font-family: inherit;
            min-height: 100px;
            resize: vertical;
        }

        textarea:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        /* Checkbox styling */
        input[type="checkbox"] {
            width: auto;
            margin-right: 10px;
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
                <h1>Manage Menu</h1>
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

            <!-- Add/Edit Menu Item Form -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2><?php echo $action === 'edit' ? 'Edit Menu Item' : 'Add Menu Item'; ?></h2>
                </div>
                <form method="POST" action="menu.php<?php echo $action === 'edit' ? '?action=edit&id=' . $menu_item_id : ''; ?>">
                    <div class="form-group">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" value="<?php echo $menu_item ? htmlspecialchars($menu_item['name']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description"><?php echo $menu_item ? htmlspecialchars($menu_item['description']) : ''; ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="price">Price (TZS)</label>
                        <input type="number" id="price" name="price" step="0.01" value="<?php echo $menu_item ? htmlspecialchars($menu_item['price']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id" required>
                            <option value="">Select Category</option>
                            <option value="1" <?php echo $menu_item && $menu_item['category_id'] == 1 ? 'selected' : ''; ?>>Main Dishes</option>
                            <option value="2" <?php echo $menu_item && $menu_item['category_id'] == 2 ? 'selected' : ''; ?>>Sides</option>
                            <option value="3" <?php echo $menu_item && $menu_item['category_id'] == 3 ? 'selected' : ''; ?>>Drinks</option>
                            <option value="4" <?php echo $menu_item && $menu_item['category_id'] == 4 ? 'selected' : ''; ?>>Desserts</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="stock">Stock</label>
                        <input type="number" id="stock" name="stock" value="<?php echo $menu_item && isset($menu_item['stock']) ? htmlspecialchars($menu_item['stock']) : '0'; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_available" <?php echo $menu_item && isset($menu_item['is_available']) && $menu_item['is_available'] ? 'checked' : ''; ?>>
                            Available
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary"><?php echo $action === 'edit' ? 'Update Item' : 'Add Item'; ?></button>
                </form>
            </div>

            <!-- Menu Items List -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2>Menu Items</h2>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all"></th>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Price (TZS)</th>
                            <th>Category</th>
                            <th>Stock</th>
                            <th>Available</th>
                            <th>Created At</th>
                            <th>Updated At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($menu_items)): ?>
                            <tr>
                                <td colspan="11" class="no-data">No menu items available.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($menu_items as $item): ?>
                                <tr>
                                    <td><input type="checkbox" name="item_ids[]" value="<?php echo $item['id']; ?>"></td>
                                    <td><?php echo htmlspecialchars($item['id']); ?></td>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['description'] ?? 'N/A'); ?></td>
                                    <td><?php echo number_format($item['price'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($category_names[$item['category_id']] ?? 'Unknown'); ?></td>
                                    <td><?php echo isset($item['stock']) ? htmlspecialchars($item['stock']) : '0'; ?></td>
                                    <td><?php echo isset($item['is_available']) && $item['is_available'] ? 'Yes' : 'No'; ?></td>
                                    <td><?php echo htmlspecialchars($item['created_at'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($item['updated_at'] ?? 'N/A'); ?></td>
                                    <td>
                                        <a href="menu.php?action=edit&id=<?php echo $item['id']; ?>" class="btn btn-primary btn-small"><i class="fas fa-edit"></i></a>
                                        <a href="menu.php?action=copy&id=<?php echo $item['id']; ?>" class="btn btn-primary btn-small"><i class="fas fa-copy"></i></a>
                                        <a href="menu.php?action=delete&id=<?php echo $item['id']; ?>" class="btn btn-secondary btn-small" onclick="return confirm('Are you sure you want to delete this item?');"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        // Select all checkboxes
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="item_ids[]"]');
            checkboxes.forEach(checkbox => checkbox.checked = this.checked);
        });
    </script>
</body>
</html>