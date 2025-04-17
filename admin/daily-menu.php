<?php
// admin/daily-menu.php - Manage daily menu items
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check if admin is logged in
if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit;
}

$db = getDb();
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_daily_menu'])) {
        try {
            $db->beginTransaction();
            
            // Clear existing daily menu for this date
            $stmt = $db->prepare("DELETE FROM daily_menu WHERE date_available = ?");
            $stmt->bind_param("s", $date);
            $stmt->execute();
            
            // Add selected items to daily menu
            if (isset($_POST['menu_items']) && is_array($_POST['menu_items'])) {
                $stmt = $db->prepare("INSERT INTO daily_menu (menu_item_id, date_available, is_available, special_price) VALUES (?, ?, 1, ?)");
                
                foreach ($_POST['menu_items'] as $item_id) {
                    $special_price = !empty($_POST['special_price'][$item_id]) ? $_POST['special_price'][$item_id] : NULL;
                    $stmt->bind_param("isd", $item_id, $date, $special_price);
                    $stmt->execute();
                }
            }
            
            $db->commit();
            $message = "Daily menu for " . date('F j, Y', strtotime($date)) . " has been updated successfully.";
        } catch (Exception $e) {
            $db->rollback();
            $error = "Error updating daily menu: " . $e->getMessage();
        }
    } elseif (isset($_POST['toggle_availability'])) {
        $item_id = $_POST['item_id'];
        $is_available = $_POST['is_available'] ? 0 : 1; // Toggle availability
        
        $stmt = $db->prepare("UPDATE daily_menu SET is_available = ? WHERE menu_item_id = ? AND date_available = ?");
        $stmt->bind_param("iis", $is_available, $item_id, $date);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'is_available' => $is_available]);
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => $db->getError()]);
            exit;
        }
    }
}

// Get all menu items grouped by category
$menuItems = [];
$result = $db->query("SELECT m.id, m.name, m.price, m.preparation_time, m.is_fast_food, 
                     c.name as category_name, c.id as category_id
                     FROM menu_items m
                     JOIN categories c ON m.category_id = c.id
                     ORDER BY c.name, m.name");

while ($row = $result->fetch_assoc()) {
    if (!isset($menuItems[$row['category_id']])) {
        $menuItems[$row['category_id']] = [
            'name' => $row['category_name'],
            'items' => []
        ];
    }
    $menuItems[$row['category_id']]['items'][] = $row;
}

// Get currently selected daily menu items
$dailyMenuItems = [];
$result = $db->query("SELECT menu_item_id, is_available, special_price 
                     FROM daily_menu 
                     WHERE date_available = '$date'");

while ($row = $result->fetch_assoc()) {
    $dailyMenuItems[$row['menu_item_id']] = [
        'is_available' => $row['is_available'],
        'special_price' => $row['special_price']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Menu Management - <?php echo SITE_NAME; ?></title>
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
                <h1>Daily Menu Management</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION['user_name']; ?></span>
                    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
            
            <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <!-- Date Selector -->
            <div class="date-selector">
                <form method="get" action="daily-menu.php" id="date-form">
                    <div class="form-group">
                        <label for="date">Select Date:</label>
                        <input type="date" id="date" name="date" value="<?php echo $date; ?>">
                        <button type="submit" class="btn">Load Menu</button>
                    </div>
                </form>
            </div>
            
            <!-- Daily Menu Form -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Select Menu Items for <?php echo date('F j, Y', strtotime($date)); ?></h2>
                </div>
                <div class="card-content">
                    <form method="post" action="daily-menu.php?date=<?php echo $date; ?>">
                        <?php if (empty($menuItems)): ?>
                            <p class="no-data">No menu items available. <a href="menu.php">Add menu items first</a>.</p>
                        <?php else: ?>
                            <?php foreach ($menuItems as $category): ?>
                                <div class="menu-category">
                                    <h3><?php echo $category['name']; ?></h3>
                                    <div class="menu-items">
                                        <?php foreach ($category['items'] as $item): ?>
                                            <?php 
                                            $isSelected = isset($dailyMenuItems[$item['id']]);
                                            $isAvailable = $isSelected ? $dailyMenuItems[$item['id']]['is_available'] : false;
                                            $specialPrice = $isSelected ? $dailyMenuItems[$item['id']]['special_price'] : '';
                                            ?>
                                            <div class="menu-item <?php echo $isSelected ? 'selected' : ''; ?> <?php echo !$isAvailable ? 'unavailable' : ''; ?>">
                                                <div class="checkbox-container">
                                                    <input type="checkbox" id="item-<?php echo $item['id']; ?>" 
                                                           name="menu_items[]" value="<?php echo $item['id']; ?>"
                                                           <?php echo $isSelected ? 'checked' : ''; ?>>
                                                    <label for="item-<?php echo $item['id']; ?>"><?php echo $item['name']; ?></label>
                                                </div>
                                                <div class="item-details">
                                                    <span class="item-price">Regular Price: $<?php echo number_format($item['price'], 2); ?></span>
                                                    <span class="prep-time">
                                                        <?php if ($item['is_fast_food']): ?>
                                                            <i class="fas fa-bolt"></i> Fast Food (<?php echo $item['preparation_time']; ?> min)
                                                        <?php else: ?>
                                                            <i class="fas fa-clock"></i> Preparation: <?php echo $item['preparation_time']; ?> min
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                                <div class="item-controls">
                                                    <div class="form-group special-price">
                                                        <label for="special-<?php echo $item['id']; ?>">Special Price ($):</label>
                                                        <input type="number" id="special-<?php echo $item['id']; ?>" 
                                                               name="special_price[<?php echo $item['id']; ?>]" 
                                                               value="<?php echo $specialPrice; ?>" 
                                                               step="0.01" min="0" placeholder="Optional">
                                                    </div>
                                                    <?php if ($isSelected): ?>
                                                    <button type="button" class="toggle-btn <?php echo $isAvailable ? 'available' : 'unavailable'; ?>"
                                                            data-item-id="<?php echo $item['id']; ?>"
                                                            data-is-available="<?php echo $isAvailable ? '1' : '0'; ?>">
                                                        <?php echo $isAvailable ? '<i class="fas fa-check-circle"></i> Available' : '<i class="fas fa-times-circle"></i> Unavailable'; ?>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="form-actions">
                                <button type="submit" name="update_daily_menu" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Daily Menu
                                </button>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                                </a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>
    $(document).ready(function() {
        // Automatically submit form when date changes
        $('#date').change(function() {
            $('#date-form').submit();
        });
        
        // Handle availability toggle
        $('.toggle-btn').click(function() {
            const btn = $(this);
            const itemId = btn.data('item-id');
            const isAvailable = btn.data('is-available');
            
            $.ajax({
                url: 'daily-menu.php?date=<?php echo $date; ?>',
                type: 'POST',
                data: {
                    toggle_availability: true,
                    item_id: itemId,
                    is_available: isAvailable
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        if (response.is_available) {
                            btn.html('<i class="fas fa-check-circle"></i> Available');
                            btn.removeClass('unavailable').addClass('available');
                            btn.closest('.menu-item').removeClass('unavailable');
                        } else {
                            btn.html('<i class="fas fa-times-circle"></i> Unavailable');
                            btn.removeClass('available').addClass('unavailable');
                            btn.closest('.menu-item').addClass('unavailable');
                        }
                        btn.data('is-available', response.is_available);
                    } else {
                        alert('Error updating availability: ' + response.error);
                    }
                },
                error: function() {
                    alert('An error occurred while updating availability.');
                }
            });
        });
    });
    </script>
</body>
</html>