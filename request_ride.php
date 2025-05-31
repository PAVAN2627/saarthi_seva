<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $ride_id = $_POST['ride_id'];
    $start_point = trim($_POST['start_point']);
    $end_point = trim($_POST['end_point']);

    $stmt = $conn->prepare("INSERT INTO ride_requests (ride_id, user_id, start_point, end_point) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $ride_id, $user_id, $start_point, $end_point);

    if ($stmt->execute()) {
        echo "Ride request sent successfully!";
    } else {
        echo "Error sending request: " . $stmt->error;
    }
}
?>
<br><a href="dashboard.php">Back to Dashboard</a>
