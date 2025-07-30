<?php
// check_unclaimed_medicines.php - Run this script daily via cron job
require_once "config.php";

// Get unclaimed medicine requests that have passed their claim until date
$currentDate = date('Y-m-d H:i:s');
$unclaimed_query = "SELECT mr.id, mr.claim_until_date 
                    FROM medicine_requests mr
                    WHERE mr.request_status = 'pending' 
                    AND mr.claim_until_date < :current_date
                    AND mr.claimed_date IS NULL";

$unclaimed_stmt = $conn->prepare($unclaimed_query);
$unclaimed_stmt->bindParam(':current_date', $currentDate);
$unclaimed_stmt->execute();
$unclaimed_requests = $unclaimed_stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($unclaimed_requests) > 0) {
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // For each expired request, return medicines to inventory
        foreach ($unclaimed_requests as $request) {
            $request_id = $request['id'];
            
            // Get all the distribution records for this request
            $distributions_query = "SELECT md.id, md.inventory_medicine_id, md.quantity
                                    FROM medicine_distributions md
                                    WHERE md.request_id = :request_id
                                    AND md.status = 'reserved'";
            $distributions_stmt = $conn->prepare($distributions_query);
            $distributions_stmt->bindParam(':request_id', $request_id);
            $distributions_stmt->execute();
            $distributions = $distributions_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Update each distribution record and return stock to inventory
            foreach ($distributions as $distribution) {
                // Mark distribution as returned
                $update_distribution_query = "UPDATE medicine_distributions 
                                             SET status = 'returned' 
                                             WHERE id = :id";
                $update_distribution_stmt = $conn->prepare($update_distribution_query);
                $update_distribution_stmt->bindParam(':id', $distribution['id']);
                $update_distribution_stmt->execute();
                
                // Return stock to inventory
                $return_stock_query = "UPDATE medicines 
                                      SET stocks = stocks + :quantity 
                                      WHERE id = :medicine_id";
                $return_stock_stmt = $conn->prepare($return_stock_query);
                $return_stock_stmt->bindParam(':quantity', $distribution['quantity']);
                $return_stock_stmt->bindParam(':medicine_id', $distribution['inventory_medicine_id']);
                $return_stock_stmt->execute();
            }
            
            // Update the request status to declined (or you could create a new status like 'expired')
            $update_request_query = "UPDATE medicine_requests 
                                    SET request_status = 'declined' 
                                    WHERE id = :request_id";
            $update_request_stmt = $conn->prepare($update_request_query);
            $update_request_stmt->bindParam(':request_id', $request_id);
            $update_request_stmt->execute();
            
            // Log the action (optional)
            error_log("Returned medicines to inventory for expired request #$request_id");
        }
        
        // Commit transaction
        $conn->commit();
        
        echo "Successfully processed " . count($unclaimed_requests) . " expired medicine requests.\n";
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollBack();
        echo "Error processing expired requests: " . $e->getMessage() . "\n";
        error_log("Error in check_unclaimed_medicines.php: " . $e->getMessage());
    }
} else {
    echo "No expired medicine requests to process.\n";
}
?>