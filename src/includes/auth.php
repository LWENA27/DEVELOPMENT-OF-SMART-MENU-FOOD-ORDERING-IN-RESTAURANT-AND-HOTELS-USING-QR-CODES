<?php
session_start();

function login($username, $password) {
    // Assuming you have a function to get user data from the database
    $user = getUserByUsername($username);
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        return true;
    }
    return false;
}

function logout() {
    session_unset();
    session_destroy();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /qr-smart-menu-system/src/admin/login.php");
        exit();
    }
}

function getUserByUsername($username) {
    include 'db.php'; // Include database connection
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>