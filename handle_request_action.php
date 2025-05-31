<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['request_id'])) {
    $request_id = intval($_POST['request_id']);
    $action = $_POST['action'];

    if (in_array($action, ['accept', 'reject'])) {
        $new_status = ($action === 'accept') ? 'Accepted' : 'Rejected';
        $stmt_update = $conn->prepare("UPDATE ride_requests SET status = ? WHERE id = ?");
        $stmt_update->bind_param("si", $new_status, $request_id);

        if ($stmt_update->execute()) {
            // success
        } else {
            // error handling if needed
        }

        $stmt_update->close();
        header("Location: dashboard.php");
        exit();
    }
}
header("Location: dashboard.php");
exit();
