<?php
$servername = "localhost";
$username = "root";  // default for XAMPP, change if needed
$password = "";      // default empty for XAMPP
$dbname = "saarthi_seva";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
