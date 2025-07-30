<?php
session_start();
require_once "config.php";

// Check if user is logged in as super admin or staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['super_admin', 'staff'])) {
    echo "Unauthorized access.";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "Invalid request method.";
    exit();
}

$medicine_id = isset($_POST['medicine_id']) ? (int)$_POST['medicine_id'] : 0;
$admin_id = isset($_POST['admin_id']) ? (int)$_POST['admin_id'] : 0;
$add_stock = isset($_POST['add_stock']) ? (int)$_POST['add_stock'] : 0;

if ($medicine_id <= 0 || $admin_id <= 0 || $add_stock <= 0) {
    echo "Invalid medicine ID, admin ID, or stock quantity.";
    exit();
}

try {
    // Fetch current stock and min_stock for comparison
    $currentStmt = $conn->prepare("SELECT stocks, min_stock FROM medicines WHERE id = :id");
    $currentStmt->bindParam(':id', $medicine_id);
    $currentStmt->execute();
    $medicine = $currentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$medicine) {
        echo "Medicine not found.";
        exit();
    }

    $currentStock = (int)$medicine['stocks'];
    $minStock = (int)$medicine['min_stock'];

    // Calculate new stock
    $newStock = $currentStock + $add_stock;

    // Determine stock status
    if ($newStock <= 0) {
        $stock_status = 'Out of Stock';
    } elseif ($newStock <= $minStock) {
        $stock_status = 'Low Stock';
    } else {
        $stock_status = 'In Stock';
    }

    // Update medicine stock and stock_status
    $stmt = $conn->prepare("UPDATE medicines SET 
        stocks = ?,
        stock_status = ?
        WHERE id = ?");

    $stmt->execute([$newStock, $stock_status, $medicine_id]);

    // Log stock change
    $reason = 'Restock';
    $historyStmt = $conn->prepare("
        INSERT INTO stock_history (medicine_id, quantity_change, reason, changed_by)
        VALUES (:medicine_id, :quantity_change, :reason, :changed_by)
    ");
    $historyStmt->execute([
        ':medicine_id' => $medicine_id,
        ':quantity_change' => $add_stock,
        ':reason' => $reason,
        ':changed_by' => $admin_id
    ]);

    echo "Stock added successfully!";
} catch (PDOException $e) {
    echo "Error adding stock: " . $e->getMessage();
}
?>