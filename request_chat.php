<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo "Please log in to view this page.";
    exit;
}

$user_id = $_SESSION['user_id'];

if (!isset($_GET['request_id'])) {
    echo "Invalid request.";
    exit;
}

$request_id = (int)$_GET['request_id'];

// Fetch ride request details
$stmt = $conn->prepare("
  SELECT 
    rr.id AS request_id,
    ru.name AS requester_name,
    ru.email AS requester_email,
    ru.mobile AS requester_mobile,
    ru.profile_image,
    rr.start_point,
    rr.end_point,
    rr.request_time,
    rr.ride_id
  FROM ride_requests rr
  JOIN users ru ON rr.user_id = ru.id
  JOIN rides r ON rr.ride_id = r.id
  WHERE rr.id = ? AND r.user_id = ?
");

$stmt->bind_param("ii", $request_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "Unauthorized access or request not found.";
    exit;
}

$details = $result->fetch_assoc();
$ride_id = $details['ride_id'];

// Chart 1: Requests for this ride
$chart_stmt = $conn->prepare("
    SELECT 
      COUNT(*) AS total_requests,
      SUM(CASE WHEN status = 'Accepted' THEN 1 ELSE 0 END) AS accepted,
      SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) AS rejected
    FROM ride_requests
    WHERE ride_id = ?
");
$chart_stmt->bind_param("i", $ride_id);
$chart_stmt->execute();
$chart_result = $chart_stmt->get_result();
$chart_data = $chart_result->fetch_assoc();

// Chart 2: "Rides I Posted"
$posted_stmt = $conn->prepare("
  SELECT 
    COUNT(*) AS total,
    SUM(CASE WHEN rr.status = 'Accepted' THEN 1 ELSE 0 END) AS accepted,
    SUM(CASE WHEN rr.status = 'Rejected' THEN 1 ELSE 0 END) AS rejected
  FROM rides r
  LEFT JOIN ride_requests rr ON r.id = rr.ride_id
  WHERE r.user_id = ?
");
$posted_stmt->bind_param("i", $user_id);
$posted_stmt->execute();
$posted_result = $posted_stmt->get_result();
$posted_stats = $posted_result->fetch_assoc();

// Chart 3: "Rides I Requested"
$requested_stmt = $conn->prepare("
  SELECT 
    COUNT(*) AS total,
    SUM(CASE WHEN status = 'Accepted' THEN 1 ELSE 0 END) AS accepted,
    SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) AS rejected
  FROM ride_requests
  WHERE user_id = ?
");
$requested_stmt->bind_param("i", $user_id);
$requested_stmt->execute();
$requested_result = $requested_stmt->get_result();
$requested_stats = $requested_result->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
  <title>Chat with <?= htmlspecialchars($details['requester_name']) ?></title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .profile { display: flex; align-items: center; margin-bottom: 20px; }
    .profile img { border-radius: 50%; width: 80px; height: 80px; margin-right: 15px; }
    .details ul { list-style: none; padding: 0; }
    .details li { margin-bottom: 8px; }
    #chat-box { border: 1px solid #ccc; padding: 10px; height: 300px; overflow-y: scroll; margin-top: 20px; }
    #chat-input { width: 100%; padding: 10px; margin-top: 10px; }
    .chart-container { max-width: 600px; margin: 30px auto; }
  </style>
</head>
<body>

  <div class="profile">
    <?php if (!empty($details['profile_image']) && file_exists('uploads/profile_images/' . $details['profile_image'])): ?>
      <img src="uploads/profile_images/<?= htmlspecialchars($details['profile_image']) ?>" alt="Profile Picture">
    <?php else: ?>
      <img src="assets/images/default-profile.png" alt="Default Profile Picture">
    <?php endif; ?>

    <h2><?= htmlspecialchars($details['requester_name']) ?></h2>
  </div>

  <div class="details">
    <h3>Ride Request Details</h3>
    <ul>
      <li><strong>Email:</strong> <?= htmlspecialchars($details['requester_email']) ?></li>
      <li><strong>Phone:</strong> <?= htmlspecialchars($details['requester_mobile']) ?></li>
      <li><strong>From:</strong> <?= htmlspecialchars($details['start_point']) ?></li>
      <li><strong>To:</strong> <?= htmlspecialchars($details['end_point']) ?></li>
      <li><strong>Time:</strong> <?= htmlspecialchars($details['request_time']) ?></li>
    </ul>
  </div>

  <!-- Chart 1: Requests for This Ride -->
  <div class="chart-container">
    <h3 style="text-align: center;">This Ride Request Stats</h3>
    <canvas id="rideChart"></canvas>
    <script>
      const rideCtx = document.getElementById('rideChart').getContext('2d');
      new Chart(rideCtx, {
        type: 'bar',
        data: {
          labels: ['Total', 'Accepted', 'Rejected'],
          datasets: [{
            label: 'Requests for this Ride',
            data: [
              <?= $chart_data['total_requests'] ?>,
              <?= $chart_data['accepted'] ?>,
              <?= $chart_data['rejected'] ?>
            ],
            backgroundColor: ['#007bff', '#28a745', '#dc3545'],
            borderColor: ['#007bff', '#28a745', '#dc3545'],
            borderWidth: 1
          }]
        },
        options: {
          scales: {
            y: {
              beginAtZero: true,
              ticks: { precision: 0 }
            }
          }
        }
      });
    </script>
  </div>

  <!-- Chart 2: Rides I Posted -->
  <div class="chart-container">
    <h3 style="text-align: center;">Rides I Posted - Requests Overview</h3>
    <canvas id="postedChart"></canvas>
    <script>
      const postedCtx = document.getElementById('postedChart').getContext('2d');
      new Chart(postedCtx, {
        type: 'bar',
        data: {
          labels: ['Total Received', 'Accepted', 'Rejected'],
          datasets: [{
            label: 'Requests on My Rides',
            data: [
              <?= $posted_stats['total'] ?>,
              <?= $posted_stats['accepted'] ?>,
              <?= $posted_stats['rejected'] ?>
            ],
            backgroundColor: ['#6f42c1', '#28a745', '#dc3545'],
            borderColor: ['#6f42c1', '#28a745', '#dc3545'],
            borderWidth: 1
          }]
        },
        options: {
          scales: {
            y: {
              beginAtZero: true,
              ticks: { precision: 0 }
            }
          }
        }
      });
    </script>
  </div>

  <!-- Chart 3: Rides I Requested -->
  <div class="chart-container">
    <h3 style="text-align: center;">Rides I Requested - Status Overview</h3>
    <canvas id="requestedChart"></canvas>
    <script>
      const requestedCtx = document.getElementById('requestedChart').getContext('2d');
      new Chart(requestedCtx, {
        type: 'bar',
        data: {
          labels: ['Total Sent', 'Accepted', 'Rejected'],
          datasets: [{
            label: 'My Ride Requests',
            data: [
              <?= $requested_stats['total'] ?>,
              <?= $requested_stats['accepted'] ?>,
              <?= $requested_stats['rejected'] ?>
            ],
            backgroundColor: ['#17a2b8', '#28a745', '#dc3545'],
            borderColor: ['#17a2b8', '#28a745', '#dc3545'],
            borderWidth: 1
          }]
        },
        options: {
          scales: {
            y: {
              beginAtZero: true,
              ticks: { precision: 0 }
            }
          }
        }
      });
    </script>
  </div>

  <div id="chat-box">
    <p><em>Chat functionality coming soon...</em></p>
  </div>

  <input type="text" id="chat-input" placeholder="Type your message..." disabled>

</body>
</html>
