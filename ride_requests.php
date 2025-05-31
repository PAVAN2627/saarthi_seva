session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$sql = "SELECT rr.*, r.leaving_from, r.going_to, r.travel_date, u.email AS requester_email
        FROM ride_requests rr
        JOIN rides r ON rr.ride_id = r.id
        JOIN users u ON rr.user_id = u.id
        WHERE r.user_id = ?
        ORDER BY rr.request_time DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Ride Requests</title>
</head>
<body>
<h2>Ride Requests for Your Rides</h2>

<?php if ($result->num_rows === 0): ?>
    <p>No requests found for your rides.</p>
<?php else: ?>
    <table border="1" cellpadding="5" cellspacing="0">
        <tr>
            <th>Ride</th>
            <th>Requester Email</th>
            <th>Start Point</th>
            <th>End Point</th>
            <th>Request Time</th>
        </tr>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['leaving_from']) ?> to <?= htmlspecialchars($row['going_to']) ?> on <?= htmlspecialchars($row['travel_date']) ?></td>
                <td><?= htmlspecialchars($row['requester_email']) ?></td>
                <td><?= htmlspecialchars($row['start_point']) ?></td>
                <td><?= htmlspecialchars($row['end_point']) ?></td>
                <td><?= htmlspecialchars($row['request_time']) ?></td>
            </tr>
        <?php endwhile; ?>
    </table>
<?php endif; ?>

<br>
<a href="dashboard.php">Back to Dashboard</a>

</body>
</html>
