<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$ride_id = $_GET['id'] ?? 0;

$stmt = $conn->prepare("SELECT rides.*, users.mobile FROM rides JOIN users ON rides.user_id = users.id WHERE rides.id=?");
$stmt->bind_param("i", $ride_id);
$stmt->execute();
$result = $stmt->get_result();
$ride = $result->fetch_assoc();

if (!$ride) {
    echo "Ride not found.";
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Ride Details - Saarthi Seva</title>
</head>
<body>
<h2>Ride Details</h2>
<p><strong>Driver mobile:</strong> <?php echo htmlspecialchars($ride['mobile']); ?></p>
<p><strong>From:</strong> <?php echo htmlspecialchars($ride['leaving_from']); ?></p>
<p><strong>To:</strong> <?php echo htmlspecialchars($ride['going_to']); ?></p>
<p><strong>Date:</strong> <?php echo htmlspecialchars($ride['travel_date']); ?></p>
<p><strong>Vehicle:</strong> <?php echo htmlspecialchars($ride['vehicle_type']); ?></p>
<p><strong>Purpose:</strong> <?php echo htmlspecialchars($ride['purpose']); ?></p>
<p><strong>Seats Available:</strong> <?php echo htmlspecialchars($ride['members']); ?></p>
<p><strong>Pay Scale:</strong> <?php echo htmlspecialchars($ride['pay_scale']); ?></p>
<p><strong>Notes:</strong> <?php echo htmlspecialchars($ride['notes']); ?></p>
<p><strong>ID Proof:</strong> <a href="<?php echo htmlspecialchars($ride['id_proof']); ?>" target="_blank">View</a></p>

<a href="chat.php?ride_id=<?php echo $ride_id; ?>">Chat with Driver</a><br>
<a href="search_results.php?leaving_from=<?php echo urlencode($ride['leaving_from']); ?>&going_to=<?php echo urlencode($ride['going_to']); ?>&travel_date=<?php echo $ride['travel_date']; ?>">Back to Search Results</a>

</body>
</html>
