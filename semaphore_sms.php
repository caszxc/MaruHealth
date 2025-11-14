<?php
// semaphore_sms.php

function sendSMS($phone, $message) {
    $apiKey = "b7e3447e45e5c215e87ae547c027de14"; // Your Semaphore API key
    $senderId = "MaruHealth";

    // Convert to 63 format for Semaphore API
    $phoneForAPI = convertTo63($phone);

    $url = "https://semaphore.co/api/v4/messages";

    $data = [
        'apikey'    => $apiKey,
        'number'    => $phoneForAPI,
        'message'   => $message,
        'sendername'=> $senderId
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $error    = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Log using 09 format (user-friendly)
    $phoneForLog = convertTo09($phone);

    logSMSTransaction($phoneForLog, $message, ($httpCode >= 200 && $httpCode < 300) ? 'success' : 'failed', $error);

    return [
        'success'   => ($httpCode >= 200 && $httpCode < 300),
        'response'  => $response,
        'error'     => $error,
        'http_code' => $httpCode
    ];
}

// Convert any format → 63xxxxxxxxxx (for Semaphore API)
function convertTo63($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 1) === '0') {
        $phone = substr($phone, 1); // remove leading 0
    }
    if (substr($phone, 0, 2) !== '63') {
        $phone = '63' . $phone;
    }
    return $phone;
}

// Convert to 09xxxxxxxxx (for display & logs)
function convertTo09($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) === 11 && substr($phone, 0, 2) === '63') {
        return '0' . substr($phone, 2);
    }
    if (strlen($phone) === 10 && substr($phone, 0, 1) !== '0') {
        return '0' . $phone;
    }
    if (strlen($phone) === 11 && substr($phone, 0, 1) === '0') {
        return $phone; // already 09...
    }
    return $phone; // fallback
}

function logSMSTransaction($recipient_phone, $message, $status, $error_message = '') {
    global $conn;

    // Get real name from phone number
    $cleanPhone = preg_replace('/[^0-9]/', '', $recipient_phone);
    $stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) AS name FROM users WHERE REPLACE(phone_number, '-', '') LIKE :phone LIMIT 1");
    $stmt->execute([':phone' => "%$cleanPhone%"]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $recipient_name = $row ? $row['name'] : "Resident";

    $stmt = $conn->prepare("INSERT INTO sms_logs 
        (recipient_name, recipient_phone, message, status, error_message)
        VALUES (:name, :phone, :message, :status, :error_message)");

    $stmt->execute([
        ':name'     => $recipient_name,
        ':phone'    => $recipient_phone,        // Shows 09xxxxxxxxx
        ':message'  => $message,
        ':status'   => $status,
        ':error_message' => $error_message
    ]);
}