<?php
//email_function.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Adjust path if PHPMailer is installed via Composer

function sendEmail($recipientEmail, $recipientName, $subject, $message) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'mail.maruhealth.site'; // Replace with your SMTP server
        $mail->SMTPAuth = true;
        $mail->Username = '_mainaccount@maruhealth.site'; // Replace with your email
        $mail->Password = 'w]f[e6a^qTW$'; // Replace with your app-specific password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        // Recipients
        $mail->setFrom('_mainaccount@maruhealth.site', 'Maru-Health');
        $mail->addAddress($recipientEmail, $recipientName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);

        $mail->send();
        
        // Log success in database
        global $conn;
        $logStmt = $conn->prepare("
            INSERT INTO email_logs (recipient_name, recipient_email, subject, message, status)
            VALUES (:name, :email, :subject, :message, 'success')
        ");
        $logStmt->execute([
            ':name' => $recipientName,
            ':email' => $recipientEmail,
            ':subject' => $subject,
            ':message' => $message
        ]);
        
        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (Exception $e) {
        // Log failure in database
        global $conn;
        $logStmt = $conn->prepare("
            INSERT INTO email_logs (recipient_name, recipient_email, subject, message, status, error_message)
            VALUES (:name, :email, :subject, :message, 'failed', :error)
        ");
        $logStmt->execute([
            ':name' => $recipientName,
            ':email' => $recipientEmail,
            ':subject' => $subject,
            ':message' => $message,
            ':error' => $mail->ErrorInfo
        ]);
        
        return ['success' => false, 'message' => 'Email could not be sent. Error: ' . $mail->ErrorInfo];
    }
}
?>