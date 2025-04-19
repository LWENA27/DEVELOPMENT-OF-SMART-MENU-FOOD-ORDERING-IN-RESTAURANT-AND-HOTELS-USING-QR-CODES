<?php
// index.php - Main menu page with order functionality
require_once 'includes/config.php';
require_once 'includes/db.php';

// Determine language (default to English, switch to Swahili if requested)
$language = isset($_GET['lang']) ? $_GET['lang'] : 'en';

// Get table ID from URL (sanitize input)
$tableId = isset($_GET['table']) ? (int)$_GET['table'] : 0;
$table = null;
$categories = [];
$menuItems = [];
$cart = [];
$error = '';

// Connect to the database
$db = getDb();

// Validate table ID
if ($tableId > 0) {
    $sql = "SELECT * FROM tables WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $tableId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $error = ($language === 'sw') ? "Meza haipatikani. Tafadhali angalia namba ya meza." : "Table not found. Please check the table number.";
    } else {
        $table = $result->fetch_assoc();
        
        // Fetch categories
        $sql = "SELECT * FROM categories ORDER BY name ASC";
        $result = $db->query($sql);
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        
        // Fetch menu items with their categories
        $sql = "SELECT mi.*, c.name as category_name 
                FROM menu_items mi 
                JOIN categories c ON mi.category_id = c.id 
                WHERE mi.is_available = 1 
                ORDER BY c.name ASC, mi.name ASC";
        $result = $db->query($sql);
        while ($row = $result->fetch_assoc()) {
            $menuItems[] = $row;
        }
        
        // Group menu items by category
        $menuItemsByCategory = [];
        foreach ($menuItems as $item) {
            $menuItemsByCategory[$item['category_name']][] = $item;
        }
    }
    $stmt->close();
} else {
    $error = ($language === 'sw') ? "Tafadhali toa namba ya meza ili kuendelea." : "Please provide a table number to proceed.";
}

// Handle cart operations (Add to cart, Update quantity, Remove)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    $cart = isset($_SESSION['cart'][$tableId]) ? $_SESSION['cart'][$tableId] : [];
    
    if (isset($_POST['add_to_cart'])) {
        $itemId = (int)$_POST['item_id'];
        $quantity = (int)$_POST['quantity'];
        $specialInstructions = htmlspecialchars(trim($_POST['special_instructions']));
        
        // Validate item exists
        $sql = "SELECT * FROM menu_items WHERE id = ? AND is_available = 1";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("i", $itemId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $item = $result->fetch_assoc();
            $cartKey = $itemId . '-' . md5($specialInstructions);
            
            if (isset($cart[$cartKey])) {
                $cart[$cartKey]['quantity'] += $quantity;
            } else {
                $cart[$cartKey] = [
                    'item_id' => $itemId,
                    'name' => $item['name'],
                    'price' => $item['price'],
                    'quantity' => $quantity,
                    'special_instructions' => $specialInstructions
                ];
            }
        }
        $stmt->close();
    } elseif (isset($_POST['update_cart'])) {
        $cartKey = $_POST['cart_key'];
        $quantity = (int)$_POST['quantity'];
        
        if ($quantity <= 0) {
            unset($cart[$cartKey]);
        } else {
            $cart[$cartKey]['quantity'] = $quantity;
        }
    } elseif (isset($_POST['remove_item'])) {
        $cartKey = $_POST['cart_key'];
        unset($cart[$cartKey]);
    } elseif (isset($_POST['place_order']) && !empty($cart)) {
        $notes = htmlspecialchars(trim($_POST['order_notes']));
        
        // Calculate total amount
        $totalAmount = 0;
        foreach ($cart as $item) {
            $totalAmount += $item['price'] * $item['quantity'];
        }
        
        // Generate order number
        $orderNumber = 'T' . $tableId . '-' . date('Ymd') . '-' . rand(100, 999);
        
        // Insert order into database
        $sql = "INSERT INTO orders (order_number, table_id, total_amount, status, notes, created_at) 
                VALUES (?, ?, ?, 'pending', ?, NOW())";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("sids", $orderNumber, $tableId, $totalAmount, $notes);
        $stmt->execute();
        $orderId = $stmt->insert_id;
        
        // Insert order items
        $sql = "INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price, special_instructions) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        
        foreach ($cart as $item) {
            $stmt->bind_param("iiids", $orderId, $item['item_id'], $item['quantity'], $item['price'], $item['special_instructions']);
            $stmt->execute();
        }
        
        // Clear cart after placing order
        $cart = [];
        $_SESSION['cart'][$tableId] = [];
        
        // Redirect to status page with language parameter
        header("Location: status.php?order=" . urlencode($orderNumber) . "&lang=" . $language);
        exit;
    }
    
    // Update session cart
    $_SESSION['cart'][$tableId] = $cart;
}

// Helper function for currency format (prices already in TSH)
function formatCurrency($amount) {
    return number_format($amount, 0) . ' TSH';
}

// Calculate cart totals
$cartSubtotal = 0;
foreach ($cart as $item) {
    $cartSubtotal += $item['price'] * $item['quantity'];
}

// Close database connection
$db->close();
?>

<!DOCTYPE html>
<html lang="<?php echo $language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - <?php echo ($language === 'sw') ? 'Menyu ya Dijitali' : 'Digital Menu'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Accessibility-focused styles */
        :root {
            --button-min-size: 48px; /* Minimum touch target size for accessibility */
            --text-contrast: #000; /* High contrast for readability */
            --bg-contrast: #fff;
        }

        .menu-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: var(--bg-contrast);
            color: var(--text-contrast);
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .logo {
            display: flex;
            align-items: center;
        }
        
        .logo img {
            width: 40px;
            margin-right: 10px;
        }
        
        .category-section {
            margin-bottom: 40px;
        }
        
        .category-section h2 {
            font-size: 24px;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 5px;
        }
        
        .menu-items {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .menu-item {
            background-color: var(--card-bg);
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 15px;
            display: flex;
            transition: var(--transition);
        }
        
        .menu-item:hover {
            transform: translateY(-5px);
        }
        
        .menu-item-image {
            width: 100px;
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
            margin-right: 15px;
        }
        
        .menu-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .menu-item-info {
            flex: 1;
        }
        
        .menu-item-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .menu-item-description {
            font-size: 14px;
            color: #747d8c;
            margin-bottom: 10px;
        }
        
        .menu-item-price {
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .menu-item-form {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .menu-item-form input[type="number"] {
            width: 60px;
            padding: 5px;
            font-size: 16px;
        }
        
        .menu-item-form input[type="text"] {
            flex: 1;
            padding: 5px;
            font-size: 16px;
        }
        
        .menu-item-form button {
            min-width: var(--button-min-size);
            min-height: var(--button-min-size);
            font-size: 16px;
            padding: 5px 10px;
        }
        
        .cart-section {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 350px;
            background-color: var(--card-bg);
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .cart-section h3 {
            margin-bottom: 20px;
        }
        
        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .cart-item-info {
            flex: 1;
        }
        
        .cart-item-name {
            font-weight: bold;
        }
        
        .cart-item-details {
            font-size: 12px;
            color: #747d8c;
        }
        
        .cart-item-controls {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .cart-item-controls input {
            width: 50px;
            padding: 5px;
            font-size: 16px;
        }
        
        .cart-item-controls button {
            min-width: var(--button-min-size);
            min-height: var(--button-min-size);
            font-size: 16px;
            padding: 5px;
        }
        
        .cart-subtotal {
            margin-top: 20px;
            font-weight: bold;
        }
        
        .order-notes {
            margin-top: 20px;
        }
        
        .order-notes textarea {
            width: 100%;
            min-height: 80px;
            font-size: 16px;
        }
        
        .place-order-btn {
            margin-top: 20px;
            width: 100%;
            min-height: var(--button-min-size);
            font-size: 16px;
        }
        
        .offline-message {
            background-color: #ffeaa7;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            display: none;
        }
    </style>
</head>
<body>
    <div class="menu-container">
        <!-- Language Switcher -->
        <div style="text-align: right; margin-bottom: 20px;">
            <a href="?table=<?php echo $tableId; ?>&lang=en" class="<?php echo $language === 'en' ? 'active' : ''; ?>">English</a> | 
            <a href="?table=<?php echo $tableId; ?>&lang=sw" class="<?php echo $language === 'sw' ? 'active' : ''; ?>">Swahili</a>
        </div>

        <!-- Header -->
        <header class="header">
            <div class="logo">
                <img src="assets/images/logo.png" alt="<?php echo SITE_NAME; ?>">
                <h1><?php echo SITE_NAME; ?></h1>
            </div>
            <div class="table-info">
                <h3><?php echo ($language === 'sw') ? ($table['is_room'] ? 'Chumba #' : 'Meza #') : ($table['is_room'] ? 'Room #' : 'Table #'); ?><?php echo $table['table_number'] ?? ''; ?></h3>
            </div>
        </header>
        
        <!-- Offline Message -->
        <div class="offline-message" id="offlineMessage">
            <p><?php echo ($language === 'sw') ? 'Uko nje ya mtandao. Unaweza kuona menyu lakini huwezi kuagiza hadi uunganishwe kwenye mtandao.' : 'You are offline. You can view the menu, but you cannot place an order until you are online.'; ?></p>
        </div>

        <?php if ($error): ?>
        <div class="error-container">
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <p><?php echo $error; ?></p>
            </div>
        </div>
        <?php else: ?>
        
        <!-- Menu Categories and Items -->
        <?php foreach ($menuItemsByCategory as $categoryName => $items): ?>
        <div class="category-section">
            <h2><?php echo htmlspecialchars($categoryName); ?></h2>
            <div class="menu-items" id="category-<?php echo htmlspecialchars($categoryName); ?>">
                <?php foreach ($items as $item): ?>
                <div class="menu-item">
                    <div class="menu-item-image">
                        <?php if (!empty($item['image']) && file_exists(UPLOADS_DIR . $item['image'])): ?>
                        <img src="<?php echo UPLOADS_URL . $item['image']; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                        <?php else: ?>
                        <img src="assets/images/default-food.jpg" alt="<?php echo htmlspecialchars($item['name']); ?>">
                        <?php endif; ?>
                    </div>
                    <div class="menu-item-info">
                        <div class="menu-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                        <div class="menu-item-description"><?php echo htmlspecialchars($item['description']); ?></div>
                        <div class="menu-item-price"><?php echo formatCurrency($item['price']); ?></div>
                        <form method="post" class="menu-item-form">
                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                            <input type="number" name="quantity" value="1" min="1" required>
                            <input type="text" name="special_instructions" placeholder="<?php echo ($language === 'sw') ? 'Maelezo ya Ziada (mfano, bila vitunguu)' : 'Special Instructions (e.g., no onions)'; ?>">
                            <button type="submit" name="add_to_cart" class="btn btn-primary">
                                <i class="fas fa-cart-plus"></i> <?php echo ($language === 'sw') ? 'Ongeza' : 'Add'; ?>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <!-- Cart Section -->
        <div class="cart-section">
            <h3><?php echo ($language === 'sw') ? 'Rukwama' : 'Cart'; ?> (<?php echo count($cart); ?> <?php echo ($language === 'sw') ? 'Bidhaa' : 'Items'; ?>)</h3>
            
            <?php if (empty($cart)): ?>
            <p><?php echo ($language === 'sw') ? 'Rukwama yako ni tupu.' : 'Your cart is empty.'; ?></p>
            <?php else: ?>
            <?php foreach ($cart as $cartKey => $item): ?>
            <div class="cart-item">
                <div class="cart-item-info">
                    <div class="cart-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                    <div class="cart-item-details">
                        <?php echo $item['quantity']; ?> × <?php echo formatCurrency($item['price']); ?>
                        <?php if (!empty($item['special_instructions'])): ?>
                        <div class="special-note"><?php echo ($language === 'sw') ? 'Maelezo: ' : 'Note: '; ?><?php echo htmlspecialchars($item['special_instructions']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="cart-item-controls">
                    <form method="post">
                        <input type="hidden" name="cart_key" value="<?php echo $cartKey; ?>">
                        <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="0">
                        <button type="submit" name="update_cart" class="btn btn-secondary"><i class="fas fa-sync"></i></button>
                        <button type="submit" name="remove_item" class="btn btn-danger"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
            
            <div class="cart-subtotal">
                <?php echo ($language === 'sw') ? 'Jumla Ndogo: ' : 'Subtotal: '; ?><?php echo formatCurrency($cartSubtotal); ?>
            </div>
            
            <div class="order-notes">
                <textarea name="order_notes" placeholder="<?php echo ($language === 'sw') ? 'Maelezo ya Agizo (hiari)' : 'Order Notes (optional)'; ?>"></textarea>
            </div>
            
            <!-- Placeholder for M-Pesa Payment -->
            <div style="margin-top: 10px; font-size: 14px; color: #747d8c;">
                <?php echo ($language === 'sw') ? 'Malipo yatakubaliwa kupitia M-Pesa' : 'Payment will be processed via M-Pesa'; ?> (Placeholder)
            </div>
            
            <form method="post">
                <input type="hidden" name="order_notes" value="<?php echo isset($_POST['order_notes']) ? htmlspecialchars($_POST['order_notes']) : ''; ?>">
                <button type="submit" name="place_order" class="btn btn-primary place-order-btn"><?php echo ($language === 'sw') ? 'Weka Agizo' : 'Place Order'; ?></button>
            </form>
            
            <p style="margin-top: 10px; text-align: center;">
                <a href="status.php?order=<?php echo isset($orderNumber) ? urlencode($orderNumber) : ''; ?>&lang=<?php echo $language; ?>">
                    <?php echo ($language === 'sw') ? 'Fuatilia Agizo' : 'Track Order'; ?>
                </a>
            </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <footer class="footer">
            <p>© <?php echo date('Y'); ?> <?php echo SITE_NAME; ?> - <?php echo ($language === 'sw') ? 'Menyu ya Dijitali' : 'Digital Menu System'; ?></p>
        </footer>
    </div>

    <script>
        // Offline functionality: Cache menu data in local storage
        document.addEventListener('DOMContentLoaded', function() {
            const menuData = <?php echo json_encode($menuItemsByCategory); ?>;
            const language = '<?php echo $language; ?>';
            
            // Store menu data in local storage
            localStorage.setItem('menuData', JSON.stringify(menuData));
            localStorage.setItem('language', language);
            
            // Check online status
            const offlineMessage = document.getElementById('offlineMessage');
            function updateOnlineStatus() {
                if (!navigator.onLine) {
                    offlineMessage.style.display = 'block';
                    // Disable order buttons when offline
                    document.querySelectorAll('button[name="add_to_cart"], button[name="place_order"]').forEach(btn => {
                        btn.disabled = true;
                        btn.style.opacity = '0.5';
                    });
                } else {
                    offlineMessage.style.display = 'none';
                    document.querySelectorAll('button[name="add_to_cart"], button[name="place_order"]').forEach(btn => {
                        btn.disabled = false;
                        btn.style.opacity = '1';
                    });
                }
            }
            
            // Initial check and event listeners for online/offline status
            updateOnlineStatus();
            window.addEventListener('online', updateOnlineStatus);
            window.addEventListener('offline', updateOnlineStatus);
            
            // Load menu from local storage if offline
            if (!navigator.onLine) {
                const cachedMenu = JSON.parse(localStorage.getItem('menuData'));
                const lang = localStorage.getItem('language');
                if (cachedMenu) {
                    Object.keys(cachedMenu).forEach(category => {
                        const categorySection = document.getElementById('category-' + category);
                        if (categorySection) {
                            categorySection.innerHTML = ''; // Clear existing items
                            cachedMenu[category].forEach(item => {
                                const itemHtml = `
                                    <div class="menu-item">
                                        <div class="menu-item-image">
                                            <img src="${item.image && '<?php echo UPLOADS_URL; ?>' + item.image || 'assets/images/default-food.jpg'}" alt="${item.name}">
                                        </div>
                                        <div class="menu-item-info">
                                            <div class="menu-item-name">${item.name}</div>
                                            <div class="menu-item-description">${item.description}</div>
                                            <div class="menu-item-price">${formatCurrency(item.price)}</div>
                                            <form method="post" class="menu-item-form">
                                                <input type="hidden" name="item_id" value="${item.id}">
                                                <input type="number" name="quantity" value="1" min="1" required>
                                                <input type="text" name="special_instructions" placeholder="${lang === 'sw' ? 'Maelezo ya Ziada (mfano, bila vitunguu)' : 'Special Instructions (e.g., no onions)'}">
                                                <button type="submit" name="add_to_cart" class="btn btn-primary" disabled>
                                                    <i class="fas fa-cart-plus"></i> ${lang === 'sw' ? 'Ongeza' : 'Add'}
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                `;
                                categorySection.innerHTML += itemHtml;
                            });
                        }
                    });
                }
            }
        });

        // JavaScript version of formatCurrency for offline use
        function formatCurrency(amount) {
            return amount.toLocaleString('en-US', { maximumFractionDigits: 0 }) + ' TSH';
        }
    </script>
</body>
</html>