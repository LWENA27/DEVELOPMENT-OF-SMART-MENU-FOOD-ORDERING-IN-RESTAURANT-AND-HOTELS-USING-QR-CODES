<?php
// smart-menu/includes/auth.php

// Start session with secure settings if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']), // Set to true in production with HTTPS
        'cookie_samesite' => 'Strict'
    ]);
}

/**
 * Check if a user is logged in
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if the logged-in user is an admin
 * @return bool True if user is an admin, false otherwise
 */
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Log in a user with username and password
 * @param string $username
 * @param string $password
 * @return bool True on success, false on failure
 */
function loginUser($username, $password) {
    require_once __DIR__ . '/db.php';
    
    $db = getDb();
    if (!$db) {
        return false;
    }
    
    $stmt = $db->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        return true;
    }
    
    return false;
}

/**
 * Log out the current user
 */
function logoutUser() {
    $_SESSION = [];
    session_destroy();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
}
?>