<?php
// admin/qr-codes.php - Generate and manage QR codes
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check if admin is logged in
if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit;
}

$db = getDb();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['generate_qr'])) {
        try {
            // Get tables without QR codes or where QR code regeneration is requested
            $sql = "SELECT id, table_number FROM tables WHERE ";
            $whereConditions = [];
            $params = [];
            $types = "";
            
            if (!empty($_POST['tables']) && is_array($_POST['tables'])) {
                $placeholders = implode(',', array_fill(0, count($_POST['tables']), '?'));
                $whereConditions[] = "id IN ($placeholders)";
                $params = array_merge($params, $_POST['tables']);
                $types .= str_repeat("i", count($_POST['tables']));
            } else {
                $whereConditions[] = "1 = 0"; // Nothing selected
            }
            
            $sql .= implode(' OR ', $whereConditions);
            $stmt = $db->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Error preparing query: " . $db->error);
            }
            
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $count = 0;
            while ($table = $result->fetch_assoc()) {
                // Create directory if it doesn't exist
                if (!is_dir(QR_CODE_DIR)) {
                    mkdir(QR_CODE_DIR, 0755, true);
                }
                
                $tableId = $table['id'];
                $tableNumber = $table['table_number'];
                $qrFilename = 'table_' . preg_replace('/[^A-Za-z0-9]/', '_', $tableNumber) . '.png';
                $qrPath = QR_CODE_DIR . $qrFilename;
                $qrUrl = QR_CODE_URL . $qrFilename;
                
                // Generate QR code content (URL to the menu with table ID)
                $qrContent = SITE_URL . 'index.php?table=' . $tableId;
                
                // Use goqr.me API to generate QR code
                $apiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qrContent);
                $qrImage = file_get_contents($apiUrl);
                
                if ($qrImage === false) {
                    throw new Exception("Failed to fetch QR code from API for table $tableNumber.");
                }
                
                // Save the QR code image
                file_put_contents($qrPath, $qrImage);
                
                // Update table record with QR code URL
                $updateStmt = $db->prepare("UPDATE tables SET qr_code = ? WHERE id = ?");
                if (!$updateStmt) {
                    throw new Exception("Error preparing update query: " . $db->error);
                }
                $updateStmt->bind_param("si", $qrFilename, $tableId);
                $updateStmt->execute();
                $updateStmt->close();
                
                $count++;
            }
            
            $stmt->close();
            
            if ($count > 0) {
                $message = "Successfully generated $count QR code(s).";
            } else {
                $error = "No tables selected for QR code generation.";
            }
        } catch (Exception $e) {
            $error = "Error generating QR codes: " . $e->getMessage();
        }
    } elseif (isset($_POST['add_table'])) {
        // Add new table/room
        $tableNumber = trim($_POST['table_number']);
        $isRoom = isset($_POST['is_room']) ? 1 : 0;
        $location = trim($_POST['location']);
        
        if (empty($tableNumber)) {
            $error = "Table/Room number is required.";
        } else {
            // Check if table number already exists
            $stmt = $db->prepare("SELECT id FROM tables WHERE table_number = ?");
            if (!$stmt) {
                $error = "Error preparing query: " . $db->error;
            } else {
                $stmt->bind_param("s", $tableNumber);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error = "Table/Room number '$tableNumber' already exists.";
                } else {
                    $stmt->close();
                    $stmt = $db->prepare("INSERT INTO tables (table_number, is_room, location, qr_code) VALUES (?, ?, ?, '')");
                    if (!$stmt) {
                        $error = "Error preparing insert query: " . $db->error;
                    } else {
                        $stmt->bind_param("sis", $tableNumber, $isRoom, $location);
                        
                        if ($stmt->execute()) {
                            $message = ($isRoom ? "Room" : "Table") . " '$tableNumber' added successfully.";
                        } else {
                            $error = "Error adding " . ($isRoom ? "room" : "table") . ": " . $db->error;
                        }
                    }
                }
                $stmt->close();
            }
        }
    } elseif (isset($_POST['delete_table'])) {
        // Delete table/room
        $tableId = $_POST['table_id'];
        
        // First, fetch the QR code filename to delete the file
        $qrQuery = $db->prepare("SELECT qr_code FROM tables WHERE id = ?");
        if (!$qrQuery) {
            $error = "Error preparing QR query: " . $db->error;
        } else {
            $qrQuery->bind_param("i", $tableId);
            $qrQuery->execute();
            $qrResult = $qrQuery->get_result();
            
            if ($qrRow = $qrResult->fetch_assoc()) {
                $qrFile = QR_CODE_DIR . $qrRow['qr_code'];
                if (file_exists($qrFile)) {
                    unlink($qrFile);
                }
            }
            $qrQuery->close();
            
            // Now delete the table
            $stmt = $db->prepare("DELETE FROM tables WHERE id = ?");
            if (!$stmt) {
                $error = "Error preparing delete query: " . $db->error;
            } else {
                $stmt->bind_param("i", $tableId);
                
                if ($stmt->execute()) {
                    $message = "Table/Room deleted successfully.";
                } else {
                    $error = "Error deleting table/room: " . $db->error;
                }
                $stmt->close();
            }
        }
    }
}

// Get all tables
$tables = [];
$result = $db->query("SELECT id, table_number, is_room, location, qr_code FROM tables ORDER BY is_room, table_number");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $tables[] = $row;
    }
} else {
    $error = "Error fetching tables: " . $db->error;
}

// Close the database connection
if ($db) {
    $db->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Management - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="admin-container">
        <!-- Admin Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1>QR Code Management</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Admin'; ?></span>
                    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
            
            <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <!-- Add New Table/Room -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Add New Table/Room</h2>
                </div>
                <div class="card-content">
                    <form method="post" action="qr-codes.php" class="form-horizontal">
                        <div class="form-group">
                            <label for="table_number">Table/Room Number:</label>
                            <input type="text" id="table_number" name="table_number" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="is_room">Type:</label>
                            <div class="radio-group">
                                <input type="checkbox" id="is_room" name="is_room" value="1">
                                <label for="is_room">This is a Room (not a Table)</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="location">Location:</label>
                            <input type="text" id="location" name="location" placeholder="e.g., Main Hall, 1st Floor">
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="add_table" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Table/Room
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Manage QR Codes -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Generate & Manage QR Codes</h2>
                </div>
                <div class="card-content">
                    <form method="post" action="qr-codes.php">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="select-all"></th>
                                        <th>Number</th>
                                        <th>Type</th>
                                        <th>Location</th>
                                        <th>QR Code</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($tables)): ?>
                                    <tr>
                                        <td colspan="6" class="no-data">No tables/rooms added yet.</td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($tables as $table): ?>
                                        <tr>
                                            <td><input type="checkbox" name="tables[]" value="<?php echo htmlspecialchars($table['id']); ?>" class="table-checkbox"></td>
                                            <td><?php echo htmlspecialchars($table['table_number']); ?></td>
                                            <td><?php echo $table['is_room'] ? 'Room' : 'Table'; ?></td>
                                            <td><?php echo htmlspecialchars($table['location']); ?></td>
                                            <td>
                                                <?php if (!empty($table['qr_code'])): ?>
                                                <a href="<?php echo htmlspecialchars(QR_CODE_URL . $table['qr_code']); ?>" target="_blank" class="qr-preview">
                                                    <img src="<?php echo htmlspecialchars(QR_CODE_URL . $table['qr_code']); ?>" alt="QR Code" width="50">
                                                    <span>View</span>
                                                </a>
                                                <?php else: ?>
                                                <span class="no-qr">Not Generated</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="table-actions">
                                                    <form method="post" action="qr-codes.php" class="delete-form">
                                                        <input type="hidden" name="table_id" value="<?php echo htmlspecialchars($table['id']); ?>">
                                                        <button type="submit" name="delete_table" class="btn btn-danger btn-sm" 
                                                                onclick="return confirm('Are you sure you want to delete this <?php echo $table['is_room'] ? 'room' : 'table'; ?>?')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="generate_qr" class="btn btn-primary">
                                <i class="fas fa-qrcode"></i> Generate QR Codes for Selected
                            </button>
                            <a href="#" id="print-selected" class="btn btn-secondary">
                                <i class="fas fa-print"></i> Print Selected QR Codes
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>
    $(document).ready(function() {
        // Select all checkbox
        $('#select-all').change(function() {
            $('.table-checkbox').prop('checked', $(this).prop('checked'));
        });
        
        // Handle print selected
        $('#print-selected').click(function(e) {
            e.preventDefault();
            
            const selected = $('.table-checkbox:checked');
            if (selected.length === 0) {
                alert('Please select at least one table/room to print QR codes.');
                return;
            }
            
            // Create print window
            const printWindow = window.open('', '_blank', 'width=800,height=600');
            printWindow.document.write('<html><head><title>Print QR Codes</title>');
            printWindow.document.write('<style>');
            printWindow.document.write(`
                body { font-family: Arial, sans-serif; }
                .qr-container { display: inline-block; margin: 10px; text-align: center; page-break-inside: avoid; }
                .qr-code { border: 1px solid #ddd; padding: 10px; }
                .qr-code img { width: 200px; height: 200px; }
                .qr-info { margin-top: 10px; font-size: 14px; }
                @media print {
                    .page-break { page-break-after: always; }
                    .no-print { display: none; }
                }
            `);
            printWindow.document.write('</style></head><body>');
            
            // Add print button
            printWindow.document.write('<div class="no-print" style="text-align: center; margin: 20px;">');
            printWindow.document.write('<button onclick="window.print()">Print QR Codes</button>');
            printWindow.document.write('</div>');
            
            // Add QR codes
            selected.each(function() {
                const tableId = $(this).val();
                const row = $(this).closest('tr');
                const tableNumber = row.find('td:nth-child(2)').text();
                const qrImg = row.find('.qr-preview img').attr('src');
                
                if (qrImg) {
                    printWindow.document.write('<div class="qr-container">');
                    printWindow.document.write('<div class="qr-code">');
                    printWindow.document.write(`<img src="${qrImg}" alt="QR Code">`);
                    printWindow.document.write('</div>');
                    printWindow.document.write(`<div class="qr-info">${tableNumber}</div>`);
                    printWindow.document.write('</div>');
                }
            });
            
            printWindow.document.write('</body></html>');
            printWindow.document.close();
        });
    });
    </script>
</body>
</html>