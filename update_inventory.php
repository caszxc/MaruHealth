<?php
// update_inventory.php
require_once "config.php"; // Include database connection

date_default_timezone_set('Asia/Manila');

// Start transaction
try {
    $conn->beginTransaction();

    // Update batch stock status and expiry status
    $batchStmt = $conn->prepare("
        SELECT id, stocks, expiration_date 
        FROM medicine_batches
    ");
    $batchStmt->execute();
    $batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($batches as $batch) {
        $batchId = $batch['id'];
        $stocks = $batch['stocks'];
        $expirationDate = $batch['expiration_date'];
        
        // Update stock status
        $stockStatus = ($stocks > 0) ? 'In Stock' : 'Out of Stock';
        
        // Update expiry status
        $today = new DateTime();
        $expDate = new DateTime($expirationDate);
        $interval = $today->diff($expDate);
        $daysUntilExpiry = $interval->days;
        
        if ($interval->invert == 1) {
            $expiryStatus = 'Expired';
        } elseif ($daysUntilExpiry <= 7) {
            $expiryStatus = 'Expiring within a week';
        } elseif ($daysUntilExpiry <= 30) {
            $expiryStatus = 'Expiring within a month';
        } else {
            $expiryStatus = 'Valid';
        }
        
        // Update medicine_batches table
        $updateBatchStmt = $conn->prepare("
            UPDATE medicine_batches 
            SET stock_status = :stock_status, 
                expiry_status = :expiry_status
            WHERE id = :batch_id
        ");
        $updateBatchStmt->execute([
            ':stock_status' => $stockStatus,
            ':expiry_status' => $expiryStatus,
            ':batch_id' => $batchId
        ]);
    }

    // Update medicine catalog stock status
    $catalogStmt = $conn->prepare("
        SELECT id, min_stock 
        FROM medicines_catalog
    ");
    $catalogStmt->execute();
    $catalogs = $catalogStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($catalogs as $catalog) {
        $catalogId = $catalog['id'];
        $minStock = $catalog['min_stock'];

        // Calculate total stock for the catalog item from all its batches
        $totalStockStmt = $conn->prepare("
            SELECT SUM(stocks) as total_stock
            FROM medicine_batches
            WHERE catalog_id = :catalog_id
            AND expiry_status != 'Expired'
        ");
        $totalStockStmt->execute([':catalog_id' => $catalogId]);
        $totalStock = $totalStockStmt->fetchColumn() ?: 0;

        // Determine stock status
        if ($totalStock == 0) {
            $stockStatus = 'Out of Stock';
        } elseif ($totalStock <= $minStock) {
            $stockStatus = 'Low Stock';
        } else {
            $stockStatus = 'In Stock';
        }

        // Update medicines_catalog table
        $updateCatalogStmt = $conn->prepare("
            UPDATE medicines_catalog 
            SET stock_status = :stock_status
            WHERE id = :catalog_id
        ");
        $updateCatalogStmt->execute([
            ':stock_status' => $stockStatus,
            ':catalog_id' => $catalogId
        ]);
    }

    // Commit transaction
    $conn->commit();
} catch (PDOException $e) {
    // Rollback transaction on error
    $conn->rollBack();
}
?>