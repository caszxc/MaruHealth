<?php
// check_availability.php - Create this as a new file
include 'config.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $response = ['exists' => false, 'message' => ''];
    
    try {
        if (isset($_POST['email'])) {
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            
            if (!empty($email)) {
                // Check if email exists in users, pending_users, or guardians table
                $stmt = $conn->prepare("
                    SELECT 'users' as source FROM users WHERE email = :email
                    UNION
                    SELECT 'pending_users' as source FROM pending_users WHERE email = :email
                    UNION
                    SELECT 'guardians' as source FROM guardians WHERE email = :email
                ");
                $stmt->execute([':email' => $email]);
                
                if ($stmt->rowCount() > 0) {
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $response['exists'] = true;
                    
                    switch ($result['source']) {
                        case 'users':
                            $response['message'] = 'Email is already registered with an active account';
                            break;
                        case 'pending_users':
                            $response['message'] = 'Email is already pending approval';
                            break;
                        case 'guardians':
                            $response['message'] = 'Email is already registered as a guardian';
                            break;
                    }
                }
            }
        }
        
        if (isset($_POST['phone'])) {
            $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
            
            if (!empty($phone)) {
                // Check if phone exists in users, pending_users, or guardians table
                $stmt = $conn->prepare("
                    SELECT 'users' as source FROM users WHERE phone_number = :phone
                    UNION
                    SELECT 'pending_users' as source FROM pending_users WHERE phone_number = :phone
                    UNION
                    SELECT 'guardians' as source FROM guardians WHERE phone_number = :phone
                ");
                $stmt->execute([':phone' => $phone]);
                
                if ($stmt->rowCount() > 0) {
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $response['exists'] = true;
                    
                    switch ($result['source']) {
                        case 'users':
                            $response['message'] = 'Phone number is already registered with an active account';
                            break;
                        case 'pending_users':
                            $response['message'] = 'Phone number is already pending approval';
                            break;
                        case 'guardians':
                            $response['message'] = 'Phone number is already registered as a guardian';
                            break;
                    }
                }
            }
        }
        
    } catch (PDOException $e) {
        $response['exists'] = true;
        $response['message'] = 'Error checking availability';
    }
    
    echo json_encode($response);
} else {
    echo json_encode(['exists' => true, 'message' => 'Invalid request method']);
}
?>