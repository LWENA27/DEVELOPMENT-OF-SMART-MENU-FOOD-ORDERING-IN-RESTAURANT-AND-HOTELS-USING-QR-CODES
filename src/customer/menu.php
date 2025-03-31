<?php
include '../includes/db.php';
include '../includes/header.php';

// Fetch menu items from the database
$query = "SELECT * FROM menu_items WHERE available = 1";
$result = mysqli_query($conn, $query);

?>

<main>
    <h1>Restaurant Menu</h1>
    <div class="menu-items">
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <div class="menu-item">
                <h2><?php echo $row['name']; ?></h2>
                <p><?php echo $row['description']; ?></p>
                <p>Price: <?php echo number_format($row['price'], 2); ?> TZS</p>
                <img src="../assets/images/<?php echo $row['image']; ?>" alt="<?php echo $row['name']; ?>">
                <a href="order.php?item_id=<?php echo $row['id']; ?>" class="order-button">Order Now</a>
            </div>
        <?php endwhile; ?>
    </div>
</main>

<?php
include '../includes/footer.php';
?>