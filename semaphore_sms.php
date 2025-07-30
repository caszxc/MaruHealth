<?php
// Add this to the top of account_requests.php after session_start(); and before any HTML output
// Create semaphore_sms.php in the same directory as your other PHP files

function sendSMS($phone, $message) {
    // Semaphore API Configuration
    $apiKey = "b7e3447e45e5c215e87ae547c027de14"; // Replace with your actual Semaphore API key
    $senderId = "SEMAPHORE"; // Optional: Your registered sender ID
    
    // Format the phone number (ensure it has the Philippines country code +63)
    $phone = formatPhoneNumber($phone);
    
    // API Endpoint
    $url = "https://semaphore.co/api/v4/messages";
    
    // Prepare the data
    $data = [
        'apikey' => $apiKey,
        'number' => $phone,
        'message' => $message
    ];
    
    if (!empty($senderId)) {
        $data['senderId'] = $senderId;
    }
    
    // Initialize cURL session
    $ch = curl_init($url);
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // Execute cURL request
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Close cURL session
    curl_close($ch);
    
    // Log the SMS transaction
    logSMSTransaction($phone, $message, ($httpCode >= 200 && $httpCode < 300) ? 'success' : 'failed', $error);
    
    // Return the response
    return [
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'response' => $response,
        'error' => $error,
        'http_code' => $httpCode
    ];
}

function formatPhoneNumber($phone) {
    // Remove any non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Remove leading 0 if present
    if (substr($phone, 0, 1) == '0') {
        $phone = substr($phone, 1);
    }
    
    // Add Philippines country code if not already present
    if (substr($phone, 0, 2) != '63') {
        $phone = '63' . $phone;
    }
    
    return $phone;
}

function logSMSTransaction($recipient_phone, $message, $status, $error_message = '') {
    global $conn;
    
    // Get recipient name from phone number - you'll need to implement this
    // For now, we'll just use "User" as the recipient name
    $recipient_name = "User";
    
    $stmt = $conn->prepare("
        INSERT INTO sms_logs (recipient_name, recipient_phone, message, status, error_message)
        VALUES (:recipient_name, :recipient_phone, :message, :status, :error_message)
    ");
    
    $stmt->execute([
        ':recipient_name' => $recipient_name,
        ':recipient_phone' => $recipient_phone,
        ':message' => $message,
        ':status' => $status,
        ':error_message' => $error_message
    ]);
}