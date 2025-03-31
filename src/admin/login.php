<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
include '../includes/db.php';
include '../includes/auth.php';

// Function to check if any admin exists
function adminExists() {
    global $pdo; // Assuming $pdo is defined in db.php
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    return $stmt->fetchColumn() > 0;
}

// Function to add a new admin
function addAdmin($username, $password) {
    global $pdo;
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT); // Hash the password
    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (:username, :password, 'admin')");
    $stmt->execute([':username' => $username, ':password' => $hashedPassword]);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_admin'])) {
        // Add admin form submission
        $username = $_POST['username'];
        $password = $_POST['password'];
        addAdmin($username, $password);
        $success = "Admin user added successfully. You can now log in.";
    } elseif (isset($_POST['login'])) {
        // Login form submission
        $username = $_POST['username'];
        $password = $_POST['password'];

        $user = getUserByUsername($username);
        if ($user && password_verify($password, $user['password']) && $user['role'] === 'admin') {
            $_SESSION['username'] = $username;
            $_SESSION['admin_logged_in'] = true;
            header('Location: dashboard.php');
            exit();
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="styles/admin.css">
</head>
<body>
    <header>
        <h1>Admin Login</h1>
    </header>
    <main>
        <?php if (!adminExists()): ?>
            <!-- Add Admin Form -->
            <h2>Add Admin</h2>
            <form id="add-admin-form" method="POST" action="">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
                
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
                
                <button type="submit" name="add_admin">Add Admin</button>
                <?php if (isset($success)): ?>
                    <p class="success"><?php echo $success; ?></p>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <!-- Login Form -->
            <h2>Login</h2>
            <form id="admin-login-form" method="POST" action="">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
                
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
                
                <button type="submit" name="login">Login</button>
                <?php if (isset($error)): ?>
                    <p class="error"><?php echo $error; ?></p>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </main>
    <footer>
        <p>&copy; 2023 Your Company</p>
    </footer>
</body>
</html>