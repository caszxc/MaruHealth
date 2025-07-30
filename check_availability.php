<?php
// check_availability.php
include 'config.php';

header('Content-Type: application/json');

try {
    $response = ['exists' => false, 'message' => '', 'field' => ''];

    if (isset($_POST['email']) || isset($_POST['phone'])) {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);

        // Prepare the query to check both users and pending_users tables
        $stmt = $conn->prepare("
            SELECT 'users' as source FROM users WHERE email = :email OR phone_number = :phone
            UNION
            SELECT 'pending_users' as source FROM pending_users WHERE email = :email OR phone_number = :phone
        ");
        $stmt->execute([':email' => $email, ':phone' => $phone]);

        if ($stmt->rowCount() > 0) {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $response['exists'] = true;
            $response['field'] = $email ? 'email' : 'phone';
            $response['message'] = $email ? 
                ($result['source'] === 'users' ? 'This email is already registered with an active account' : 'This email is already pending approval') :
                ($result['source'] === 'users' ? 'This phone number is already registered with an active account' : 'This phone number is already pending approval');
        }
    }

    echo json_encode($response);
} catch (PDOException $e) {
    echo json_encode(['exists' => false, 'message' => 'Error checking data: ' . $e->getMessage(), 'field' => '']);
}
?>