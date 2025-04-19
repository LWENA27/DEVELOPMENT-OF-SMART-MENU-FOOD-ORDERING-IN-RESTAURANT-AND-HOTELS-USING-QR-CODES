<?php
// index.php - Customer-facing menu interface
require_once 'includes/config.php';
require_once 'includes/db.php';

// Get table ID from QR code
$table_id = isset($_GET['table']) ? (int)$_GET['table'] : 0;
$error = '';

// Validate table ID
$db = getDb();
$stmt = $db->prepare("SELECT id, table_number, is_room FROM tables WHERE id = ?");
$stmt->bind_param("i", $table_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $error = "Invalid table or room. Please scan a valid QR code.";
    $table = null;
} else {
    $table = $result->fetch_assoc();
}

// Get current date
$today = date('Y-m-d');

// Get menu categories with available items
$categories = [];
if (!$error) {
    $sql = "SELECT DISTINCT c.id, c.name, c.description
            FROM categories c
            JOIN menu_items m ON c.id = m.category_id
            JOIN daily_menu dm ON m.id = dm.menu_item_id
            WHERE dm.date_available = ? AND dm.is_available = 1
            ORDER BY c.name";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Function to format preparation time
function formatPrepTime($minutes, $isFastFood) {
    if ($isFastFood) {
        return "<span class='fast-food'>Fast Food - $minutes min</span>";
    } else {
        return "<span class='regular-prep'>$minutes min preparation</span>";
    }
}

// Handle order submission
$orderSuccess = false;
$orderNumber = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    try {
        $db->begin_transaction();
        
        // Check if we have items in the cart
        if (!empty($_POST['items']) && is_array($_POST['items'])) {
            $tableId = $_POST['table_id'];
            $notes = trim($_POST['order_notes']);
            $totalAmount = 0;
            
            // Generate order number: TableNumber-YYYYMMDD-Sequence
            $sequence = mt_rand(100, 999);
            $dateCode = date('Ymd');
            $tableQuery = $db->prepare("SELECT table_number FROM tables WHERE id = ?");
            $tableQuery->bind_param("i", $tableId);
            $tableQuery->execute();
            $tableResult = $tableQuery->get_result();
            $tableRow = $tableResult->fetch_assoc();
            $tableNumber = $tableRow['table_number'];
            $orderNumber = $tableNumber . '-' . $dateCode . '-' . $sequence;
            
            // Create order record
            $orderStmt = $db->prepare("INSERT INTO orders (table_id, order_number, status, total_amount, notes) VALUES (?, ?, 'pending', 0, ?)");
            $orderStmt->bind_param("iss", $tableId, $orderNumber, $notes);
            $orderStmt->execute();
            $orderId = $db->insert_id;
            
            // Process each item
            foreach ($_POST['items'] as $itemId => $qty) {
                $qty = (int)$qty;
                if ($qty <= 0) continue;
                
                // Get item details
                $itemStmt = $db->prepare("SELECT m.id, m.name, dm.special_price, m.price 
                                         FROM menu_items m 
                                         JOIN daily_menu dm ON m.id = dm.menu_item_id 
                                         WHERE m.id = ? AND dm.date_available = ? AND dm.is_available = 1");
                $itemStmt->bind_param("is", $itemId, $today);
                $itemStmt->execute();
                $itemResult = $itemStmt->get_result();
                
                if ($item = $itemResult->fetch_assoc()) {
                    $unitPrice = !is_null($item['special_price']) ? $item['special_price'] : $item['price'];
                    $specialInstructions = isset($_POST['instructions'][$itemId]) ? trim($_POST['instructions'][$itemId]) : '';
                    
                    // Add item to order
                    $orderItemStmt = $db->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price, special_instructions) 
                                                 VALUES (?, ?, ?, ?, ?)");
                   $orderItemStmt->bind_param("iiids", $orderId, $itemId, $qty, $unitPrice, $specialInstructions);
                    $orderItemStmt->execute();
                    
                    // Add to total amount
                    $totalAmount += ($unitPrice * $qty);
                }
            }
            
            // Update order total
            $updateOrderStmt = $db->prepare("UPDATE orders SET total_amount = ? WHERE id = ?");
            $updateOrderStmt->bind_param("di", $totalAmount, $orderId);
            $updateOrderStmt->execute();
            
            $db->commit();
            $orderSuccess = true;
        }
    } catch (Exception $e) {
        $db->rollback();
        $error = "Failed to place order: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Digital Menu</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="menu-container">
        <!-- Header -->
        <header class="header">
            <div class="logo">
                <img src="assets/images/logo.png" alt="<?php echo SITE_NAME; ?>">
                <h1><?php echo SITE_NAME; ?></h1>
            </div>
            
            <?php if ($table): ?>
            <div class="table-info">
                <i class="fas <?php echo $table['is_room'] ? 'fa-bed' : 'fa-utensils'; ?>"></i>
                <span><?php echo $table['is_room'] ? 'Room' : 'Table'; ?>: <?php echo $table['table_number']; ?></span>
            </div>
            <?php endif; ?>
            
            <div class="cart-icon" id="cart-icon">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-count">0</span>
            </div>
        </header>
        
        <?php if ($error): ?>
        <div class="error-container">
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <p><?php echo $error; ?></p>
            </div>
            <p>Please scan a valid QR code to access the menu.</p>
        </div>
        <?php elseif ($orderSuccess): ?>
        <div class="order-success">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2>Order Placed Successfully!</h2>
            <p>Your order number: <strong><?php echo $orderNumber; ?></strong></p>
            <p>We are preparing your order. Thank you for your patience!</p>
            <div class="action-buttons">
                <a href="index.php?table=<?php echo $table_id; ?>" class="btn btn-primary">Order More Items</a>
                <a href="status.php?order=<?php echo $orderNumber; ?>" class="btn btn-secondary">Track Order Status</a>
            </div>
        </div>
        <?php else: ?>
        
        <!-- Menu Categories Navigation -->
        <nav class="category-nav">
            <ul>
                <?php foreach ($categories as $category): ?>
                <li><a href="#category-<?php echo $category['id']; ?>"><?php echo $category['name']; ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
        
        <!-- Menu Items -->
        <div class="menu-content">
            <form id="order-form" method="post" action="index.php?table=<?php echo $table_id; ?>">
                <input type="hidden" name="table_id" value="<?php echo $table_id; ?>">
                
                <?php if (empty($categories)): ?>
                <div class="no-menu">
                    <p>No menu items available today. Please check back later or contact staff for assistance.</p>
                </div>
                <?php else: ?>
                    <?php foreach ($categories as $category): ?>
                    <section class="menu-section" id="category-<?php echo $category['id']; ?>">
                        <h2 class="category-title"><?php echo $category['name']; ?></h2>
                        <p class="category-desc"><?php echo $category['description']; ?></p>
                        
                        <div class="menu-items">
                            <?php
                            // Get items for this category
                            $sql = "SELECT m.id, m.name, m.description, m.price, m.preparation_time, m.is_fast_food, 
                                   dm.special_price, m.image
                                   FROM menu_items m
                                   JOIN daily_menu dm ON m.id = dm.menu_item_id
                                   WHERE m.category_id = ? AND dm.date_available = ? AND dm.is_available = 1
                                   ORDER BY m.name";
                            
                            $stmt = $db->prepare($sql);
                            $stmt->bind_param("is", $category['id'], $today);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            while ($item = $result->fetch_assoc()):
                                $price = !is_null($item['special_price']) ? $item['special_price'] : $item['price'];
                                $hasDiscount = !is_null($item['special_price']) && $item['special_price'] < $item['price'];
                            ?>
                            <div class="menu-item" data-id="<?php echo $item['id']; ?>">
                                <div class="item-image">
                                    <?php if (!empty($item['image']) && file_exists(UPLOADS_DIR . $item['image'])): ?>
                                    <img src="<?php echo UPLOADS_URL . $item['image']; ?>" alt="<?php echo $item['name']; ?>">
                                    <?php else: ?>
                                    <img src="assets/images/default-food.jpg" alt="<?php echo $item['name']; ?>">
                                    <?php endif; ?>
                                </div>
                                
                                <div class="item-details">
                                    <h3 class="item-name"><?php echo $item['name']; ?></h3>
                                    <p class="item-desc"><?php echo $item['description']; ?></p>
                                    <div class="item-info">
                                        <p class="item-price">
                                            <?php if ($hasDiscount): ?>
                                            <span class="original-price"><?php echo formatCurrency($item['price']); ?></span>
                                            <span class="special-price"><?php echo formatCurrency($price); ?></span>
                                            <?php else: ?>
                                            <?php echo formatCurrency($price); ?>
                                            <?php endif; ?>
                                        </p>
                                        <p class="prep-time"><?php echo formatPrepTime($item['preparation_time'], $item['is_fast_food']); ?></p>
                                    </div>
                                </div>
                                
                                <div class="item-actions">
                                    <div class="quantity-control">
                                        <button type="button" class="qty-btn qty-minus" data-id="<?php echo $item['id']; ?>"><i class="fas fa-minus"></i></button>
                                        <input type="number" name="items[<?php echo $item['id']; ?>]" value="0" min="0" max="20" class="qty-input" data-id="<?php echo $item['id']; ?>">
                                        <button type="button" class="qty-btn qty-plus" data-id="<?php echo $item['id']; ?>"><i class="fas fa-plus"></i></button>
                                    </div>
                                    <div class="special-instructions">
                                        <textarea name="instructions[<?php echo $item['id']; ?>]" placeholder="Special instructions..."></textarea>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </section>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Order Summary -->
                <div class="order-summary" id="order-summary">
                    <h2><i class="fas fa-shopping-cart"></i> Your Order</h2>
                    <div class="order-items-list" id="order-items-list">
                        <p class="empty-cart">Your cart is empty. Add items to place an order.</p>
                    </div>
                    <div class="order-total">
                        <p>Total: <span id="total-amount"><?php echo formatCurrency(0); ?></span></p>
                    </div>
                    <div class="order-notes">
                        <label for="order_notes">Additional notes for the kitchen:</label>
                        <textarea name="order_notes" id="order_notes"></textarea>
                    </div>
                    <div class="order-actions">
                        <button type="submit" name="place_order" class="btn btn-primary" id="place-order-btn" disabled>Place Order</button>
                        <button type="button" class="btn btn-secondary" id="clear-cart-btn">Clear Cart</button>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
        <footer class="footer">
            <p>© <?php echo date('Y'); ?> <?php echo SITE_NAME; ?> - Digital Menu System</p>
            <p><a href="#" id="call-waiter-btn"><i class="fas fa-bell"></i> Call Waiter</a></p>
        </footer>
    </div>
    
    <!-- Call Waiter Modal -->
    <div id="waiter-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal">×</span>
            <h2><i class="fas fa-bell"></i> Call Waiter</h2>
            <p>How can we help you?</p>
            <form id="waiter-form">
                <div class="request-options">
                    <button type="button" class="request-btn" data-request="water">Water</button>
                    <button type="button" class="request-btn" data-request="cutlery">Cutlery</button>
                    <button type="button" class="request-btn" data-request="napkins">Napkins</button>
                    <button type="button" class="request-btn" data-request="bill">Bill</button>
                </div>
                <div class="other-request">
                    <label for="other-request">Other request:</label>
                    <textarea id="other-request" placeholder="Please specify..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Send Request</button>
            </form>
            <div id="waiter-response" class="hidden">
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <p>Your request has been received. A staff member will be with you shortly.</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Helper function for currency format -->
    <?php 
    function formatCurrency($amount) {
        return '$' . number_format($amount, 2);
    }
    ?>

    <script src="assets/js/menu.js"></script>
    <script>
        // Initialize with table ID for socket connections
        document.addEventListener('DOMContentLoaded', function() {
            initMenu(<?php echo $table_id; ?>);
        });
    </script>
</body>
</html>