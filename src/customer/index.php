<?php
session_start();
include '../includes/db.php';
include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Smart Menu</title>
    <link rel="stylesheet" href="styles/customer.css">
</head>
<body>
    <main>
        <h1>Welcome to the Smart Menu System</h1>
        <p>Experience a seamless dining experience by browsing our menu and placing orders directly from your device.</p>
        <a href="menu.php" class="btn">View Menu</a>
        <a href="order-status.php" class="btn">Check Order Status</a>
    </main>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>