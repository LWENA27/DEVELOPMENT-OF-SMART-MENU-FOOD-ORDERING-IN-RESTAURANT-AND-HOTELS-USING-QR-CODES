<?php
session_start();
require 'src/includes/db.php';
// require 'src/includes/header.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Smart Menu System</title>
    <link rel="stylesheet" href="src/customer/styles/customer.css">
</head>
<body>
    <header>
        <h1>Welcome to the QR Smart Menu System</h1>
        <nav>
            <ul>
                <li><a href="src/customer/index.php">Home</a></li>
                <li><a href="src/customer/menu.php">Menu</a></li>
                <li><a href="src/admin/login.php">Admin Login</a></li>
            </ul>
        </nav>
    </header>
    <main>
        <h2>Explore Our Smart Menu</h2>
        <p>Scan the QR codes at your table to view the menu and place your orders directly from your smartphone!</p>
    </main>
    <?php //require 'src/includes/footer.php'; ?>
</body>
</html>