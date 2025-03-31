<?php
session_start();
include '../includes/db.php';
include '../includes/auth.php';

// Check if the user is logged in
if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Handle form submissions for adding, editing, and deleting menu items
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_item'])) {
        // Add new menu item
        $name = $_POST['name'];
        $description = $_POST['description'];
        $price = $_POST['price'];
        $image = $_FILES['image']['name'];
        $target = "../assets/images/" . basename($image);

        $query = "INSERT INTO menu_items (name, description, price, image) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssds", $name, $description, $price, $image);
        $stmt->execute();

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            echo "Menu item added successfully.";
        } else {
            echo "Failed to upload image.";
        }
    } elseif (isset($_POST['edit_item'])) {
        // Edit existing menu item
        $id = $_POST['id'];
        $name = $_POST['name'];
        $description = $_POST['description'];
        $price = $_POST['price'];

        $query = "UPDATE menu_items SET name=?, description=?, price=? WHERE id=?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssdi", $name, $description, $price, $id);
        $stmt->execute();

        echo "Menu item updated successfully.";
    } elseif (isset($_POST['delete_item'])) {
        // Delete menu item
        $id = $_POST['id'];

        $query = "DELETE FROM menu_items WHERE id=?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();

        echo "Menu item deleted successfully.";
    }
}

// Fetch all menu items for display
$query = "SELECT * FROM menu_items";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Menu</title>
    <link rel="stylesheet" href="styles/admin.css">
</head>
<body>
    <header>
        <h1>Manage Menu</h1>
        <a href="dashboard.php">Dashboard</a>
        <a href="logout.php">Logout</a>
    </header>
    <main>
        <h2>Add New Menu Item</h2>
        <form action="manage-menu.php" method="POST" enctype="multipart/form-data">
            <input type="text" name="name" placeholder="Item Name" required>
            <textarea name="description" placeholder="Description" required></textarea>
            <input type="number" name="price" placeholder="Price" step="0.01" required>
            <input type="file" name="image" required>
            <button type="submit" name="add_item">Add Item</button>
        </form>

        <h2>Current Menu Items</h2>
        <table>
            <tr>
                <th>Name</th>
                <th>Description</th>
                <th>Price</th>
                <th>Actions</th>
            </tr>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['name']); ?></td>
                <td><?php echo htmlspecialchars($row['description']); ?></td>
                <td><?php echo htmlspecialchars($row['price']); ?></td>
                <td>
                    <form action="manage-menu.php" method="POST" style="display:inline;">
                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                        <button type="submit" name="delete_item">Delete</button>
                    </form>
                    <button onclick="editItem(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name']); ?>', '<?php echo htmlspecialchars($row['description']); ?>', <?php echo $row['price']; ?>)">Edit</button>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </main>
    <footer>
        <p>&copy; 2023 Your Company</p>
    </footer>
    <script src="../scripts/admin.js"></script>
</body>
</html>