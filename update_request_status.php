<?php
session_start();
header('Content-Type: application/json');
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['request_id'], $data['status'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$request_id = (int)$data['request_id'];
$status = $data['status'];

// Validate status value
if (!in_array($status, ['Accepted', 'Rejected'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

// Verify the request belongs to a ride posted by this user
$stmt_check = $conn->prepare("
  SELECT rr.id FROM ride_requests rr
  JOIN rides r ON rr.ride_id = r.id
  WHERE rr.id = ? AND r.user_id = ?
");
$stmt_check->bind_param("ii", $request_id, $user_id);
$stmt_check->execute();
$result = $stmt_check->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized or request not found']);
    exit;
}

// Update the status
$stmt_update = $conn->prepare("UPDATE ride_requests SET status = ? WHERE id = ?");
$stmt_update->bind_param("si", $status, $request_id);
if ($stmt_update->execute()) {
    // ✅ Fetch ride and user details to send email
    $query = "
        SELECT 
            rr.id AS request_id,
            ru.email AS requester_email,
            ru.name AS requester_name,
            r.start_location,
            r.end_location,
            r.ride_date,
            r.ride_time,
            ru2.email AS rider_email,
            ru2.name AS rider_name
        FROM ride_requests rr
        JOIN users ru ON rr.user_id = ru.id
        JOIN rides r ON rr.ride_id = r.id
        JOIN users ru2 ON r.user_id = ru2.id
        WHERE rr.id = ?
    ";

    $stmt_details = $conn->prepare($query);
    $stmt_details->bind_param("i", $request_id);
    $stmt_details->execute();
    $result_details = $stmt_details->get_result();
    $details = $result_details->fetch_assoc();

    $to = $details['requester_email'];
    $subject = "Ride Request Status: $status";

    $message = "
    <html>
    <head><title>Ride Request $status</title></head>
    <body>
        <p>Dear {$details['requester_name']},</p>
        <p>Your ride request has been <strong>$status</strong> by the rider.</p>

        <h3>Ride Details:</h3>
        <ul>
            <li><strong>Rider Name:</strong> {$details['rider_name']}</li>
            <li><strong>Rider Email:</strong> {$details['rider_email']}</li>
            <li><strong>From:</strong> {$details['start_location']}</li>
            <li><strong>To:</strong> {$details['end_location']}</li>
            <li><strong>Date:</strong> {$details['ride_date']}</li>
            <li><strong>Time:</strong> {$details['ride_time']}</li>
        </ul>

        <p>Thank you for using Saarthi Seva!</p>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: no-reply@saarthiseva.com\r\n";

    mail($to, $subject, $message, $headers);

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update status']);
}
?>
