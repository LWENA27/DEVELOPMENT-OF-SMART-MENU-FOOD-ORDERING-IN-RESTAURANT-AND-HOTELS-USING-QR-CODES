<?php
session_start();
include '../includes/db.php';
include '../includes/header.php';

$order_id = $_GET['order_id'] ?? null;
$order_status = null;

if ($order_id) {
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = :order_id");
    $stmt->execute(['order_id' => $order_id]);
    $order_status = $stmt->fetchColumn();
}

?>

<main>
    <h1>Order Status</h1>
    <?php if ($order_status): ?>
        <p>Your order status is: <strong><?php echo htmlspecialchars($order_status); ?></strong></p>
    <?php else: ?>
        <p>Order not found. Please check your order ID.</p>
    <?php endif; ?>
</main>

<?php include '../includes/footer.php'; ?>