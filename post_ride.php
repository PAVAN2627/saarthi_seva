<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    echo "POST received<br>";

    $user_id = $_SESSION['user_id'];
    $leaving_from = $_POST['leaving_from'];
    $going_to = $_POST['going_to'];
    $travel_date = $_POST['travel_date'];
    $travel_time = $_POST['travel_time'];
$stops = $_POST['stops']; // can be empty string

    $vehicle_type = $_POST['vehicle_type'];
    $vehicle_name = $_POST['vehicle_name'];

    $purpose_array = isset($_POST['purpose']) ? $_POST['purpose'] : [];
$purpose = implode(", ", $purpose_array); // Converts array to comma-separated string

    $members = (int)$_POST['members'];
    $pay_scale = $_POST['pay_scale'];
    $notes = $_POST['notes'];

    $target_dir = "uploads/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $file_name = basename($_FILES["id_proof"]["name"]);
    $target_file = $target_dir . time() . "_" . $file_name;

    if (isset($_FILES['id_proof'])) {
        echo "File received: " . $_FILES['id_proof']['name'] . "<br>";
    } else {
        echo "No file received<br>";
    }

    if (move_uploaded_file($_FILES["id_proof"]["tmp_name"], $target_file)) {
        echo "File uploaded successfully<br>";

       // After confirming file upload successful
$stmt = $conn->prepare("INSERT INTO rides 
(user_id, leaving_from, going_to, travel_date, travel_time, vehicle_type, vehicle_name, purpose, members, pay_scale, id_proof, notes, stops)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

if (!$stmt) {
    echo "Prepare failed: (" . $conn->errno . ") " . $conn->error;
    exit();
}
$stmt->bind_param("isssssssiisss", $user_id, $leaving_from, $going_to, $travel_date, $travel_time, $vehicle_type, $vehicle_name, $purpose, $members, $pay_scale, $target_file, $notes, $stops);



        if ($stmt->execute()) {
            echo "Ride added successfully. Redirecting...";
            header("Refresh:2; url=dashboard.php?msg=ride_added");
            exit();
        } else {
            echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
        }
    } else {
        $error = error_get_last();
        echo "Upload error: " . $error['message'];
    }
}
?>
