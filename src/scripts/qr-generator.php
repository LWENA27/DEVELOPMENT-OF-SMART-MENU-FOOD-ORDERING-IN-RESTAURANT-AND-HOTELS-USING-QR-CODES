<?php
require_once '../includes/db.php';
require_once '../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

function generateQRCode($data, $filename) {
    $qrCode = new QrCode($data);
    $qrCode->setSize(300);
    $writer = new PngWriter();
    $result = $writer->write($qrCode);
    
    // Save the QR code to the specified file
    $result->saveToFile($filename);
}

// Example usage
if (isset($_POST['menu_item_id'])) {
    $menuItemId = $_POST['menu_item_id'];
    
    // Fetch menu item details from the database
    $stmt = $pdo->prepare("SELECT name FROM menu_items WHERE id = :id");
    $stmt->execute(['id' => $menuItemId]);
    $menuItem = $stmt->fetch();

    if ($menuItem) {
        $data = "http://yourdomain.com/customer/menu.php?item_id=" . $menuItemId;
        $filename = "../assets/qr-codes/qr_" . $menuItemId . ".png";
        
        generateQRCode($data, $filename);
        
        echo json_encode(['success' => true, 'filename' => $filename]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Menu item not found.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No menu item ID provided.']);
}
?>