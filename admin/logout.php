<?php
// smart-menu/admin/logout.php - Admin Logout
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Clear all session data
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: ' . SITE_URL . 'admin/login.php');
exit;
?>