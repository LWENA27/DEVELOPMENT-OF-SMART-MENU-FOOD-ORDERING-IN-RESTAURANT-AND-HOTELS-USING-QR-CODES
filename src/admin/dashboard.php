<?php
session_start();
include '../includes/db.php';
include '../includes/auth.php';

// Check if the user is logged in
if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Fetch key metrics for the dashboard
$totalOrders = getTotalOrders(); // Function to get total orders from the database
$totalMenuItems = getTotalMenuItems(); // Function to get total menu items from the database
$totalCustomers = getTotalCustomers(); // Function to get total customers from the database

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="styles/admin.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <main>
        <h1>Admin Dashboard</h1>
        <div class="dashboard-metrics">
            <div class="metric">
                <h2>Total Orders</h2>
                <p><?php echo $totalOrders; ?></p>
            </div>
            <div class="metric">
                <h2>Total Menu Items</h2>
                <p><?php echo $totalMenuItems; ?></p>
            </div>
            <div class="metric">
                <h2>Total Customers</h2>
                <p><?php echo $totalCustomers; ?></p>
            </div>
        </div>
        <div class="management-options">
            <h2>Management Options</h2>
            <ul>
                <li><a href="manage-menu.php">Manage Menu</a></li>
                <li><a href="manage-orders.php">Manage Orders</a></li>
            </ul>
        </div>
    </main>
    <?php include '../includes/footer.php'; ?>
</body>
</html>