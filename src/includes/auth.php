<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication-related functions

function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function login($username, $password) {
    $user = getUserByUsername($username);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['admin_logged_in'] = true; // Set admin login session
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
        header("Location: ../admin/login.php");
        exit();
    }
}

function getUserByUsername($username) {
    include 'db.php'; // Include database connection
    global $pdo; // Ensure $pdo is accessible
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>