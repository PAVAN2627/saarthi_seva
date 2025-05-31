<?php
session_start();
require 'db.php';

// Get JSON input
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['request_id'], $data['rating'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$request_id = intval($data['request_id']);
$rating = intval($data['rating']);

// Validate rating range
if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid rating value']);
    exit;
}

// Optional: Verify user owns the request or is authorized to rate

// Update rating in DB
$stmt = $conn->prepare("UPDATE ride_requests SET rating = ? WHERE id = ?");
$stmt->bind_param("ii", $rating, $request_id);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'DB update failed']);
}
$stmt->close();
$conn->close();
?>
