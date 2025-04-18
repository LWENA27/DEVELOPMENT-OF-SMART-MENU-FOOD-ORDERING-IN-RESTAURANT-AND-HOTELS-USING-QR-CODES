<?php
// status.php - Order status tracking page
require_once 'includes/config.php';
require_once 'includes/db.php';

// Define order status constants
define('ORDER_STATUS_PENDING', 'pending');
define('ORDER_STATUS_PREPARING', 'preparing');
define('ORDER_STATUS_READY', 'ready');
define('ORDER_STATUS_DELIVERED', 'delivered');
define('ORDER_STATUS_CANCELLED', 'cancelled');

// Define status colors and labels (with Swahili translations)
const ORDER_STATUS_COLORS = [
    ORDER_STATUS_PENDING => '#f39c12',    // Orange for pending
    ORDER_STATUS_PREPARING => '#3498db',  // Blue for preparing
    ORDER_STATUS_READY => '#2ecc71',      // Green for ready
    ORDER_STATUS_DELIVERED => '#27ae60',  // Dark green for delivered
    ORDER_STATUS_CANCELLED => '#e74c3c'   // Red for cancelled
];

const ORDER_STATUS_LABELS_EN = [
    ORDER_STATUS_PENDING => 'Pending',
    ORDER_STATUS_PREPARING => 'Preparing',
    ORDER_STATUS_READY => 'Ready',
    ORDER_STATUS_DELIVERED => 'Delivered',
    ORDER_STATUS_CANCELLED => 'Cancelled'
];

const ORDER_STATUS_LABELS_SW = [
    ORDER_STATUS_PENDING => 'Inasubiri',
    ORDER_STATUS_PREPARING => 'Inatayarishwa',
    ORDER_STATUS_READY => 'Tayari',
    ORDER_STATUS_DELIVERED => 'Imewasilishwa',
    ORDER_STATUS_CANCELLED => 'Imefutwa'
];

// Determine language (default to English, switch to Swahili if requested)
$language = isset($_GET['lang']) ? $_GET['lang'] : 'en';
$ORDER_STATUS_LABELS = ($language === 'sw') ? ORDER_STATUS_LABELS_SW : ORDER_STATUS_LABELS_EN;

// Get order number from URL (sanitize input)
$orderNumber = isset($_GET['order']) ? htmlspecialchars(trim($_GET['order'])) : '';
$error = '';
$order = null;
$orderItems = [];

if (!empty($orderNumber)) {
    $db = getDb();
    
    // Get order details (using prepared statement to prevent SQL injection)
    $sql = "SELECT o.*, t.table_number, t.is_room 
            FROM orders o
            JOIN tables t ON o.table_id = t.id
            WHERE o.order_number = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param("s", $orderNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $error = ($language === 'sw') ? "Agizo halijapatikana. Tafadhali angalia namba yako ya agizo." : "Order not found. Please check your order number.";
    } else {
        $order = $result->fetch_assoc();
        
        // Get order items
        $sql = "SELECT oi.*, m.name, m.image
                FROM order_items oi
                JOIN menu_items m ON oi.menu_item_id = m.id
                WHERE oi.order_id = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param("i", $order['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($item = $result->fetch_assoc()) {
            $orderItems[] = $item;
        }
    }
    $stmt->close();
}

// Helper function for currency format (prices already in TSH)
function formatCurrency($amount) {
    return number_format($amount, 0) . ' TSH';
}

// Get estimated completion time based on preparation times
function getEstimatedCompletionTime($orderItems, $orderTime, $db) {
    $maxPrepTime = 0;
    
    foreach ($orderItems as $item) {
        $sql = "SELECT preparation_time FROM menu_items WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("i", $item['menu_item_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $menuItem = $result->fetch_assoc();
        
        if ($menuItem && $menuItem['preparation_time'] > $maxPrepTime) {
            $maxPrepTime = $menuItem['preparation_time'];
        }
        $stmt->close();
    }
    
    $buffer = min(count($orderItems) * 2, 15);
    $totalMinutes = $maxPrepTime + $buffer;
    
    $orderTimestamp = strtotime($orderTime);
    $estimatedTime = date('Y-m-d H:i:s', $orderTimestamp + ($totalMinutes * 60));
    
    return [
        'time' => $estimatedTime,
        'minutes' => $totalMinutes
    ];
}

// Get status timeline steps
function getStatusTimeline($status, $language) {
    $labels = ($language === 'sw') ? [
        ORDER_STATUS_PENDING => 'Agizo Limepokelewa',
        ORDER_STATUS_PREPARING => 'Inatayarishwa',
        ORDER_STATUS_READY => 'Tayari',
        ORDER_STATUS_DELIVERED => 'Imewasilishwa'
    ] : [
        ORDER_STATUS_PENDING => 'Order Received',
        ORDER_STATUS_PREPARING => 'Preparing',
        ORDER_STATUS_READY => 'Ready',
        ORDER_STATUS_DELIVERED => 'Delivered'
    ];
    
    $steps = [
        ['status' => ORDER_STATUS_PENDING, 'label' => $labels[ORDER_STATUS_PENDING], 'icon' => 'fa-receipt', 'complete' => false],
        ['status' => ORDER_STATUS_PREPARING, 'label' => $labels[ORDER_STATUS_PREPARING], 'icon' => 'fa-utensils', 'complete' => false],
        ['status' => ORDER_STATUS_READY, 'label' => $labels[ORDER_STATUS_READY], 'icon' => 'fa-check-circle', 'complete' => false],
        ['status' => ORDER_STATUS_DELIVERED, 'label' => $labels[ORDER_STATUS_DELIVERED], 'icon' => 'fa-hand-holding', 'complete' => false]
    ];
    
    $currentIndex = -1;
    foreach ($steps as $index => &$step) {
        if ($status === ORDER_STATUS_CANCELLED) {
            $step['complete'] = false;
        } else {
            switch ($status) {
                case ORDER_STATUS_DELIVERED:
                    $currentIndex = 3;
                    break;
                case ORDER_STATUS_READY:
                    $currentIndex = 2;
                    break;
                case ORDER_STATUS_PREPARING:
                    $currentIndex = 1;
                    break;
                case ORDER_STATUS_PENDING:
                    $currentIndex = 0;
                    break;
            }
            $step['complete'] = $index <= $currentIndex;
        }
    }
    
    return $steps;
}

// Handle feedback submission
$feedbackMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback']) && $order && $order['status'] === ORDER_STATUS_DELIVERED) {
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $comments = isset($_POST['comments']) ? htmlspecialchars(trim($_POST['comments'])) : '';
    
    if ($rating >= 1 && $rating <= 5) {
        $db = getDb();
        $sql = "INSERT INTO feedback (order_id, rating, comments) VALUES (?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("iis", $order['id'], $rating, $comments);
        if ($stmt->execute()) {
            $feedbackMessage = ($language === 'sw') ? "Asante kwa maoni yako!" : "Thank you for your feedback!";
        } else {
            $feedbackMessage = ($language === 'sw') ? "Hitilafu imetokea. Tafadhali jaribu tena." : "An error occurred. Please try again.";
        }
        $stmt->close();
    } else {
        $feedbackMessage = ($language === 'sw') ? "Tafadhali chagua ukadiriaji (1-5)." : "Please select a rating (1-5).";
    }
}

// If order exists, get estimated time and status timeline
$estimatedTime = null;
$statusTimeline = [];

if ($order) {
    $estimatedTime = getEstimatedCompletionTime($orderItems, $order['created_at'], $db);
    $statusTimeline = getStatusTimeline($order['status'], $language);
} elseif (empty($orderNumber)) {
    $error = ($language === 'sw') ? "Tafadhali weka namba ya agizo ili kufuatilia hali yake." : "Please provide an order number to track its status.";
}

// Close the database connection
if (isset($db)) {
    $db->close();
}
?>

<!DOCTYPE html>
<html lang="<?php echo $language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - <?php echo ($language === 'sw') ? 'Hali ya Agizo' : 'Order Status'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Accessibility-focused styles */
        :root {
            --button-min-size: 48px; /* Minimum touch target size for accessibility */
            --text-contrast: #000; /* High contrast for readability */
            --bg-contrast: #fff;
        }

        .status-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: var(--bg-contrast);
            color: var(--text-contrast);
        }
        
        .status-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .status-card {
            background-color: var(--card-bg);
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            color: white;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .order-meta {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        
        .meta-item {
            flex: 1;
            min-width: 150px;
            margin-bottom: 15px;
        }
        
        .meta-label {
            font-size: 12px;
            color: #747d8c;
            margin-bottom: 5px;
        }
        
        .meta-value {
            font-weight: bold;
        }
        
        .timeline {
            display: flex;
            justify-content: space-between;
            margin: 30px 0;
            position: relative;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 4px;
            background-color: #dfe4ea;
            z-index: 1;
        }
        
        .timeline-step {
            position: relative;
            z-index: 2;
            text-align: center;
            width: 75px;
        }
        
        .step-icon {
            width: 30px;
            height: 30px;
            background-color: #dfe4ea;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            color: white;
            transition: var(--transition);
        }
        
        .step-complete .step-icon {
            background-color: var(--success-color);
        }
        
        .step-current .step-icon {
            background-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(255, 71, 87, 0.2);
        }
        
        .step-label {
            font-size: 12px;
            font-weight: bold;
        }
        
        .order-items-summary {
            margin-top: 30px;
        }
        
        .order-item-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .order-item-image {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            overflow: hidden;
            margin-right: 15px;
        }
        
        .order-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .order-item-info {
            flex: 1;
        }
        
        .order-item-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .order-item-details {
            font-size: 12px;
            color: #747d8c;
        }
        
        .order-item-price {
            text-align: right;
            font-weight: bold;
            min-width: 80px;
        }
        
        .cancelled-order {
            background-color: rgba(255, 71, 87, 0.1);
            border: 1px solid var(--error-color);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
        }
        
        .refresh-status {
            text-align: center;
            margin: 20px 0;
        }
        
        .order-form {
            max-width: 500px;
            margin: 0 auto;
            text-align: center;
        }
        
        .order-input-group {
            display: flex;
            margin-bottom: 20px;
        }
        
        .order-input-group input {
            flex: 1;
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
            border-right: none;
        }
        
        .order-input-group button {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
            min-width: var(--button-min-size);
            min-height: var(--button-min-size);
            font-size: 16px; /* Larger text for readability */
        }
        
        .btn {
            min-width: var(--button-min-size);
            min-height: var(--button-min-size);
            font-size: 16px;
            padding: 10px;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .feedback-section {
            margin-top: 30px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
        }
        
        .feedback-section h4 {
            margin-bottom: 15px;
        }
        
        .rating-stars {
            display: flex;
            justify-content: center;
            margin-bottom: 15px;
        }
        
        .rating-stars input {
            display: none;
        }
        
        .rating-stars label {
            font-size: 24px;
            color: #ddd;
            cursor: pointer;
        }
        
        .rating-stars input:checked ~ label,
        .rating-stars label:hover,
        .rating-stars label:hover ~ label {
            color: #f39c12;
        }
        
        .feedback-section textarea {
            width: 100%;
            min-height: 100px;
            margin-bottom: 15px;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <div class="status-container">
        <!-- Language Switcher -->
        <div style="text-align: right; margin-bottom: 20px;">
            <a href="?order=<?php echo urlencode($orderNumber); ?>&lang=en" class="<?php echo $language === 'en' ? 'active' : ''; ?>">English</a> | 
            <a href="?order=<?php echo urlencode($orderNumber); ?>&lang=sw" class="<?php echo $language === 'sw' ? 'active' : ''; ?>">Swahili</a>
        </div>

        <!-- Header -->
        <header class="header">
            <div class="logo">
                <img src="assets/images/logo.png" alt="<?php echo SITE_NAME; ?>">
                <h1><?php echo SITE_NAME; ?></h1>
            </div>
        </header>
        
        <div class="status-header">
            <h2><?php echo ($language === 'sw') ? 'Ufuatiliaji wa Hali ya Agizo' : 'Order Status Tracking'; ?></h2>
        </div>
        
        <?php if ($error): ?>
        <div class="error-container">
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <p><?php echo $error; ?></p>
            </div>
            
            <div class="order-form">
                <p><?php echo ($language === 'sw') ? 'Ingiza namba yako ya agizo ili kufuatilia hali yake:' : 'Enter your order number to check its status:'; ?></p>
                <form method="get" action="status.php">
                    <div class="order-input-group">
                        <input type="text" name="order" placeholder="<?php echo ($language === 'sw') ? 'Ingiza namba ya agizo (mfano, T5-20240417-123)' : 'Enter order number (e.g., T5-20240417-123)'; ?>" required>
                        <input type="hidden" name="lang" value="<?php echo $language; ?>">
                        <button type="submit" class="btn btn-primary"><?php echo ($language === 'sw') ? 'Fuatilia' : 'Track'; ?></button>
                    </div>
                </form>
                <p><a href="index.php"><?php echo ($language === 'sw') ? 'Rudi kwenye Menyu' : 'Return to Menu'; ?></a></p>
            </div>
        </div>
        <?php elseif ($order): ?>
        
        <div class="status-card">
            <div class="status-badge" style="background-color: <?php echo ORDER_STATUS_COLORS[$order['status']]; ?>">
                <?php echo $ORDER_STATUS_LABELS[$order['status']]; ?>
            </div>
            
            <h3><?php echo ($language === 'sw') ? 'Agizo #' : 'Order #'; ?><?php echo $order['order_number']; ?></h3>
            
            <div class="order-meta">
                <div class="meta-item">
                    <div class="meta-label"><?php echo ($language === 'sw') ? 'Tarehe ya Agizo' : 'Order Date'; ?></div>
                    <div class="meta-value"><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></div>
                </div>
                
                <div class="meta-item">
                    <div class="meta-label"><?php echo ($language === 'sw') ? ($order['is_room'] ? 'Namba ya Chumba' : 'Namba ya Meza') : ($order['is_room'] ? 'Room Number' : 'Table Number'); ?></div>
                    <div class="meta-value"><?php echo $order['table_number']; ?></div>
                </div>
                
                <div class="meta-item">
                    <div class="meta-label"><?php echo ($language === 'sw') ? 'Jumla ya Kiasi' : 'Total Amount'; ?></div>
                    <div class="meta-value"><?php echo formatCurrency($order['total_amount']); ?></div>
                </div>
                
                <?php if ($order['status'] !== ORDER_STATUS_CANCELLED && $order['status'] !== ORDER_STATUS_DELIVERED): ?>
                <div class="meta-item">
                    <div class="meta-label"><?php echo ($language === 'sw') ? 'Muda wa Kumudu Unakadiriwa' : 'Estimated Completion'; ?></div>
                    <div class="meta-value"><?php echo date('g:i A', strtotime($estimatedTime['time'])); ?></div>
                </div>
                <?php endif; ?>
                
                <!-- Placeholder for M-Pesa Payment Status -->
                <div class="meta-item">
                    <div class="meta-label"><?php echo ($language === 'sw') ? 'Hali ya Malipo' : 'Payment Status'; ?></div>
                    <div class="meta-value"><?php echo ($language === 'sw') ? 'Imelipwa kupitia M-Pesa' : 'Paid via M-Pesa'; ?> (Placeholder)</div>
                </div>
            </div>
            
            <?php if ($order['status'] === ORDER_STATUS_CANCELLED): ?>
            <div class="cancelled-order">
                <i class="fas fa-times-circle"></i>
                <p><?php echo ($language === 'sw') ? 'Agizo hili limefutwa. Tafadhali wasiliana na wafanyakazi kwa msaada.' : 'This order has been cancelled. Please contact staff for assistance.'; ?></p>
            </div>
            <?php else: ?>
            
            <!-- Status Timeline -->
            <div class="timeline">
                <?php foreach ($statusTimeline as $step): ?>
                <div class="timeline-step <?php echo $step['complete'] ? 'step-complete' : ''; ?> <?php echo ($step['status'] === $order['status']) ? 'step-current' : ''; ?>">
                    <div class="step-icon">
                        <i class="fas <?php echo $step['icon']; ?>"></i>
                    </div>
                    <div class="step-label"><?php echo $step['label']; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Order Items -->
            <div class="order-items-summary">
                <h4><?php echo ($language === 'sw') ? 'Bidhaa za Agizo' : 'Order Items'; ?></h4>
                
                <?php foreach ($orderItems as $item): ?>
                <div class="order-item-row">
                    <div class="order-item-image">
                        <?php if (!empty($item['image']) && file_exists(UPLOADS_DIR . $item['image'])): ?>
                        <img src="<?php echo UPLOADS_URL . $item['image']; ?>" alt="<?php echo $item['name']; ?>">
                        <?php else: ?>
                        <img src="assets/images/default-food.jpg" alt="<?php echo $item['name']; ?>">
                        <?php endif; ?>
                    </div>
                    
                    <div class="order-item-info">
                        <div class="order-item-name"><?php echo $item['name']; ?></div>
                        <div class="order-item-details">
                            <?php echo $item['quantity']; ?> × <?php echo formatCurrency($item['unit_price']); ?>
                            <?php if (!empty($item['special_instructions'])): ?>
                            <div class="special-note"><?php echo ($language === 'sw') ? 'Maelezo: ' : 'Note: '; ?><?php echo htmlspecialchars($item['special_instructions']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="order-item-price">
                        <?php echo formatCurrency($item['quantity'] * $item['unit_price']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (!empty($order['notes'])): ?>
                <div class="order-notes-section">
                    <h4><?php echo ($language === 'sw') ? 'Maelezo ya Ziada' : 'Additional Notes'; ?></h4>
                    <p><?php echo htmlspecialchars($order['notes']); ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Feedback Form (only for delivered orders) -->
            <?php if ($order['status'] === ORDER_STATUS_DELIVERED): ?>
            <div class="feedback-section">
                <h4><?php echo ($language === 'sw') ? 'Toa Maoni Yako' : 'Provide Your Feedback'; ?></h4>
                <?php if ($feedbackMessage): ?>
                <p style="color: <?php echo strpos($feedbackMessage, 'Thank you') !== false || strpos($feedbackMessage, 'Asante') !== false ? 'green' : 'red'; ?>;"><?php echo $feedbackMessage; ?></p>
                <?php endif; ?>
                <form method="post">
                    <div class="rating-stars">
                        <input type="radio" name="rating" id="star5" value="5"><label for="star5">★</label>
                        <input type="radio" name="rating" id="star4" value="4"><label for="star4">★</label>
                        <input type="radio" name="rating" id="star3" value="3"><label for="star3">★</label>
                        <input type="radio" name="rating" id="star2" value="2"><label for="star2">★</label>
                        <input type="radio" name="rating" id="star1" value="1"><label for="star1">★</label>
                    </div>
                    <textarea name="comments" placeholder="<?php echo ($language === 'sw') ? 'Andika maoni yako hapa...' : 'Write your comments here...'; ?>"></textarea>
                    <button type="submit" name="submit_feedback" class="btn btn-primary"><?php echo ($language === 'sw') ? 'Tuma Maoni' : 'Submit Feedback'; ?></button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="refresh-status">
            <button id="refresh-btn" class="btn btn-secondary">
                <i class="fas fa-sync-alt"></i> <?php echo ($language === 'sw') ? 'Sasisha Hali' : 'Refresh Status'; ?>
            </button>
        </div>
        
        <div class="action-buttons">
            <a href="index.php<?php echo $order['table_id'] ? '?table=' . $order['table_id'] : ''; ?>&lang=<?php echo $language; ?>" class="btn btn-primary"><?php echo ($language === 'sw') ? 'Rudi kwenye Menyu' : 'Return to Menu'; ?></a>
        </div>
        
        <?php endif; ?>
        
        <footer class="footer">
            <p>© <?php echo date('Y'); ?> <?php echo SITE_NAME; ?> - <?php echo ($language === 'sw') ? 'Menyu ya Dijitali' : 'Digital Menu System'; ?></p>
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Refresh button functionality
            const refreshBtn = document.getElementById('refresh-btn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    location.reload();
                });
            }
            
            // Auto-refresh status every 30 seconds if order is not complete
            <?php if ($order && $order['status'] !== ORDER_STATUS_DELIVERED && $order['status'] !== ORDER_STATUS_CANCELLED): ?>
            setInterval(function() {
                location.reload();
            }, 30000);
            <?php endif; ?>
        });
    </script>
</body>
</html>