<?php
// smart-menu/admin/feedback.php - Customer Feedback Management
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check if admin is logged in
if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit;
}

// Initialize variables for filtering and pagination
$itemsPerPage = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $itemsPerPage;

// Filters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$rating = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$orderNumber = isset($_GET['order_number']) ? trim($_GET['order_number']) : '';
$sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'date_desc';

// Build the query conditions
$conditions = [];
$params = [];
$types = '';

if ($startDate) {
    $conditions[] = "DATE(o.created_at) >= ?";
    $params[] = $startDate;
    $types .= 's';
}

if ($endDate) {
    $conditions[] = "DATE(o.created_at) <= ?";
    $params[] = $endDate;
    $types .= 's';
}

if ($rating > 0 && $rating <= 5) {
    $conditions[] = "f.rating = ?";
    $params[] = $rating;
    $types .= 'i';
}

if ($orderNumber) {
    $conditions[] = "o.order_number LIKE ?";
    $params[] = "%$orderNumber%";
    $types .= 's';
}

$whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Determine sorting
$orderBy = "ORDER BY ";
switch ($sortBy) {
    case 'date_asc':
        $orderBy .= "o.created_at ASC";
        break;
    case 'rating_asc':
        $orderBy .= "f.rating ASC";
        break;
    case 'rating_desc':
        $orderBy .= "f.rating DESC";
        break;
    default: // date_desc
        $orderBy .= "o.created_at DESC";
}

try {
    $db = getDb();
    if (!$db) {
        throw new Exception('Database connection failed');
    }

    // Get total number of feedback entries (for pagination)
    $sql = "SELECT COUNT(*) as total 
            FROM feedback f
            JOIN orders o ON f.order_id = o.id
            JOIN tables t ON o.table_id = t.id
            $whereClause";
    $stmt = $db->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $totalFeedback = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $totalPages = ceil($totalFeedback / $itemsPerPage);

    // Fetch feedback entries for the current page
    $feedbackEntries = [];
    $sql = "SELECT f.id, f.rating, f.comments, f.order_id, o.order_number, o.total_amount, o.created_at, t.table_number
            FROM feedback f
            JOIN orders o ON f.order_id = o.id
            JOIN tables t ON o.table_id = t.id
            $whereClause
            $orderBy
            LIMIT ? OFFSET ?";
    $stmt = $db->prepare($sql);
    $paramTypes = $types . 'ii';
    $paramValues = array_merge($params, [$itemsPerPage, $offset]);
    $stmt->bind_param($paramTypes, ...$paramValues);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $feedbackEntries[] = $row;
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
    <title>Customer Feedback - <?php echo defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Smart Menu'; ?></title>
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

        .main-content {
            padding: 20px;
        }

        .filter-form {
            background: var(--bg-contrast);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .filter-form .form-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .filter-form input, .filter-form select {
            padding: 8px;
            font-size: 16px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }

        .filter-form button {
            min-width: var(--button-min-size);
            min-height: var(--button-min-size);
            font-size: 16px;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }

        .dashboard-card {
            background: var(--bg-contrast);
            color: var(--text-contrast);
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            padding: 20px;
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

        .data-table th {
            background-color: #f5f5f5;
        }

        .data-table tr:nth-child(even) {
            background-color: #fafafa;
        }

        .rating-stars {
            color: #f39c12;
        }

        .pagination {
            margin-top: 20px;
            text-align: center;
        }

        .pagination a {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
            font-size: 16px;
        }

        .pagination a.active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }

        .pagination a.disabled {
            color: #ccc;
            cursor: not-allowed;
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
                <h1>Customer Feedback</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Admin'; ?></span>
                    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

            <!-- Filter Form -->
            <div class="filter-form">
                <form method="get" action="feedback.php">
                    <div class="form-group">
                        <label for="start_date">Start Date:</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" max="<?php echo date('Y-m-d'); ?>">
                        
                        <label for="end_date">End Date:</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" max="<?php echo date('Y-m-d'); ?>">
                        
                        <label for="rating">Rating:</label>
                        <select id="rating" name="rating">
                            <option value="0" <?php echo $rating == 0 ? 'selected' : ''; ?>>All Ratings</option>
                            <option value="1" <?php echo $rating == 1 ? 'selected' : ''; ?>>1 Star</option>
                            <option value="2" <?php echo $rating == 2 ? 'selected' : ''; ?>>2 Stars</option>
                            <option value="3" <?php echo $rating == 3 ? 'selected' : ''; ?>>3 Stars</option>
                            <option value="4" <?php echo $rating == 4 ? 'selected' : ''; ?>>4 Stars</option>
                            <option value="5" <?php echo $rating == 5 ? 'selected' : ''; ?>>5 Stars</option>
                        </select>
                        
                        <label for="order_number">Order Number:</label>
                        <input type="text" id="order_number" name="order_number" value="<?php echo htmlspecialchars($orderNumber); ?>" placeholder="e.g., T1-20250419-123">
                    </div>
                    <div class="form-group">
                        <label for="sort_by">Sort By:</label>
                        <select id="sort_by" name="sort_by">
                            <option value="date_desc" <?php echo $sortBy == 'date_desc' ? 'selected' : ''; ?>>Date (Newest First)</option>
                            <option value="date_asc" <?php echo $sortBy == 'date_asc' ? 'selected' : ''; ?>>Date (Oldest First)</option>
                            <option value="rating_desc" <?php echo $sortBy == 'rating_desc' ? 'selected' : ''; ?>>Rating (High to Low)</option>
                            <option value="rating_asc" <?php echo $sortBy == 'rating_asc' ? 'selected' : ''; ?>>Rating (Low to High)</option>
                        </select>
                        
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
                        <a href="feedback.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</a>
                    </div>
                </form>
            </div>

            <!-- Feedback Table -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2>Feedback Entries (<?php echo $totalFeedback; ?> Total)</h2>
                </div>
                <div class="card-content">
                    <table class="data-table" role="grid">
                        <thead>
                            <tr>
                                <th scope="col">Feedback ID</th>
                                <th scope="col">Order #</th>
                                <th scope="col">Table/Room</th>
                                <th scope="col">Order Total</th>
                                <th scope="col">Rating</th>
                                <th scope="col">Comments</th>
                                <th scope="col">Date Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($feedbackEntries)): ?>
                            <tr>
                                <td colspan="7" class="no-data">No feedback matches your criteria.</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($feedbackEntries as $feedback): ?>
                                <?php
                                // Debugging: Log the feedback entry to check for issues
                                error_log("Feedback Entry: " . print_r($feedback, true));
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($feedback['id']); ?></td>
                                    <td>
                                        <?php if (isset($feedback['order_id']) && isset($feedback['order_number'])): ?>
                                            <a href="order-details.php?id=<?php echo htmlspecialchars($feedback['order_id']); ?>" aria-label="View order details for <?php echo htmlspecialchars($feedback['order_number']); ?>">
                                                <?php echo htmlspecialchars($feedback['order_number']); ?>
                                            </a>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($feedback['order_number'] ?? 'Unknown Order'); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($feedback['table_number']); ?></td>
                                    <td><?php echo formatCurrency($feedback['total_amount']); ?></td>
                                    <td class="rating-stars" aria-label="Rating: <?php echo $feedback['rating']; ?> stars"><?php echo str_repeat('★', $feedback['rating']) . str_repeat('☆', 5 - $feedback['rating']); ?></td>
                                    <td><?php echo htmlspecialchars($feedback['comments'] ?: 'No comments'); ?></td>
                                    <td><?php echo htmlspecialchars(date('M j, Y h:i A', strtotime($feedback['created_at']))); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php
                    $queryParams = $_GET;
                    $queryParams['page'] = max(1, $page - 1);
                    $prevLink = 'feedback.php?' . http_build_query($queryParams);
                    $queryParams['page'] = min($totalPages, $page + 1);
                    $nextLink = 'feedback.php?' . http_build_query($queryParams);
                    ?>
                    <a href="<?php echo $prevLink; ?>" class="<?php echo $page <= 1 ? 'disabled' : ''; ?>" aria-label="Previous Page">« Prev</a>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php
                        $queryParams['page'] = $i;
                        $pageLink = 'feedback.php?' . http_build_query($queryParams);
                        ?>
                        <a href="<?php echo $pageLink; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>" aria-label="Page <?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <a href="<?php echo $nextLink; ?>" class="<?php echo $page >= $totalPages ? 'disabled' : ''; ?>" aria-label="Next Page">Next »</a>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../assets/js/admin.js"></script>
</body>
</html>