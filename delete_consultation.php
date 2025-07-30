<?php
include 'config.php';

if (!isset($_GET['id'])) {
    die("No consultation ID provided.");
}

$consultation_id = $_GET['id'];

// Verify if the consultation exists
$checkQuery = "SELECT * FROM consultations WHERE id = :id";
$stmt = $conn->prepare($checkQuery);
$stmt->bindParam(":id", $consultation_id, PDO::PARAM_INT);
$stmt->execute();
$consultation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$consultation) {
    die("Consultation not found.");
}

// Proceed with deletion
$deleteQuery = "DELETE FROM consultations WHERE id = :id";
$stmt = $conn->prepare($deleteQuery);
$stmt->bindParam(":id", $consultation_id, PDO::PARAM_INT);

if ($stmt->execute()) {
    echo "<script>alert('Consultation deleted successfully!'); window.location.href='view_patient.php?id=" . $consultation['patient_id'] . "';</script>";
} else {
    echo "Error deleting consultation.";
}
?>
