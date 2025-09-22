<?php
// get_batch_summary.php
session_start();
require_once "config.php";

// Check if user is logged in as health staff
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['admin_role'], ['health_staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

// Check if catalog_id is provided
if (!isset($_POST['catalog_id']) || empty($_POST['catalog_id'])) {
    echo json_encode(['success' => false, 'message' => 'Catalog ID is required.']);
    exit();
}

$catalog_id = trim($_POST['catalog_id']);

try {
    // Fetch min_stock from medicines_catalog
    $catalogStmt = $conn->prepare("SELECT min_stock FROM medicines_catalog WHERE id = :catalog_id");
    $catalogStmt->execute([':catalog_id' => $catalog_id]);
    $catalog = $catalogStmt->fetch(PDO::FETCH_ASSOC);

    if (!$catalog) {
        echo json_encode(['success' => false, 'message' => 'Medicine not found.']);
        exit();
    }
    $min_stock = $catalog['min_stock'];

    // Total Stock (sum of stocks for non-expired batches)
    $totalStockStmt = $conn->prepare("
        SELECT SUM(stocks) as total_stock
        FROM medicine_batches
        WHERE catalog_id = :catalog_id AND expiry_status != 'Expired'
    ");
    $totalStockStmt->execute([':catalog_id' => $catalog_id]);
    $total_stock = $totalStockStmt->fetchColumn() ?: 0;

    // Number of Batches
    $batchCountStmt = $conn->prepare("
        SELECT COUNT(id) as batch_count
        FROM medicine_batches
        WHERE catalog_id = :catalog_id
    ");
    $batchCountStmt->execute([':catalog_id' => $catalog_id]);
    $batch_count = $batchCountStmt->fetchColumn() ?: 0;

    // Expiry Status Summary
    $expiryStatusStmt = $conn->prepare("
        SELECT expiry_status, COUNT(id) as count
        FROM medicine_batches
        WHERE catalog_id = :catalog_id
        GROUP BY expiry_status
    ");
    $expiryStatusStmt->execute([':catalog_id' => $catalog_id]);
    $expiry_statuses = $expiryStatusStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Initialize all possible statuses
    $expiry_summary = [
        'Valid' => $expiry_statuses['Valid'] ?? 0,
        'Expiring within a month' => $expiry_statuses['Expiring within a month'] ?? 0,
        'Expiring within a week' => $expiry_statuses['Expiring within a week'] ?? 0,
        'Expired' => $expiry_statuses['Expired'] ?? 0
    ];
    $expiry_summary_text = implode(", ", array_map(
        fn($status, $count) => "$count $status",
        array_keys($expiry_summary),
        $expiry_summary
    ));

    // Earliest Expiration Date (non-expired batches)
    $earliestExpiryStmt = $conn->prepare("
        SELECT MIN(expiration_date) as earliest_expiry
        FROM medicine_batches
        WHERE catalog_id = :catalog_id AND expiry_status != 'Expired'
    ");
    $earliestExpiryStmt->execute([':catalog_id' => $catalog_id]);
    $earliest_expiry = $earliestExpiryStmt->fetchColumn() ?: 'N/A';

    // Stock Status Summary
    $stock_status = 'Out of Stock';
    if ($total_stock > $min_stock) {
        $stock_status = 'In Stock';
    } elseif ($total_stock > 0 && $total_stock <= $min_stock) {
        $stock_status = 'Low Stock';
    }

    // Return JSON response
    echo json_encode([
        'success' => true,
        'data' => [
            'total_stock' => $total_stock,
            'batch_count' => $batch_count,
            'expiry_summary' => $expiry_summary_text,
            'earliest_expiry' => $earliest_expiry,
            'stock_status' => $stock_status
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching batch summary: ' . $e->getMessage()]);
}
?>