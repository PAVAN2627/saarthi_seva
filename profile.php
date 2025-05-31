<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user details
$stmt = $conn->prepare("SELECT name, email, mobile, profile_image FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Fetch stats for requests made TO user's rides
$count_sql = "
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'Accepted' THEN 1 ELSE 0 END) AS accepted,
        SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) AS rejected
    FROM ride_requests rr
    JOIN rides r ON rr.ride_id = r.id
    WHERE r.user_id = ?
";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$stats = $count_result->fetch_assoc();

// Fetch stats for rides POSTED by this user (accepted/rejected requests across all rides)
$posted_sql = "
    SELECT 
        COUNT(rr.id) AS total,
        SUM(CASE WHEN rr.status = 'Accepted' THEN 1 ELSE 0 END) AS accepted,
        SUM(CASE WHEN rr.status = 'Rejected' THEN 1 ELSE 0 END) AS rejected
    FROM rides r
    LEFT JOIN ride_requests rr ON r.id = rr.ride_id
    WHERE r.user_id = ?
";
$posted_stmt = $conn->prepare($posted_sql);
$posted_stmt->bind_param("i", $user_id);
$posted_stmt->execute();
$posted_result = $posted_stmt->get_result();
$posted_stats = $posted_result->fetch_assoc();

// Fetch stats for ride REQUESTS made BY this user (accepted/rejected statuses on requests user made)
$requested_sql = "
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'Accepted' THEN 1 ELSE 0 END) AS accepted,
        SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) AS rejected
    FROM ride_requests
    WHERE user_id = ?
";
$requested_stmt = $conn->prepare($requested_sql);
$requested_stmt->bind_param("i", $user_id);
$requested_stmt->execute();
$requested_result = $requested_stmt->get_result();
$requested_stats = $requested_result->fetch_assoc();

// Fetch average rating given to this user as a ride owner
$rating_sql = "SELECT AVG(rating) AS avg_rating FROM ride_requests WHERE user_id = ?";
$rating_stmt = $conn->prepare($rating_sql);
$rating_stmt->bind_param("i", $user_id);
$rating_stmt->execute();
$rating_result = $rating_stmt->get_result();
$rating_row = $rating_result->fetch_assoc();

// Fix null value before rounding
$avg_rating = $rating_row['avg_rating'] !== null ? round($rating_row['avg_rating'], 2) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Your Profile - Saarthi Seva</title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
  body {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
  }
  .profile-container {
    max-width: 800px;
    width: 100%;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    padding: 32px;
    margin: 0 auto;
  }
  .profile-img {
    display: block;
    margin: 0 auto 20px;
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #3b82f6;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    transition: transform 0.3s ease;
  }
  .profile-img:hover {
    transform: scale(1.05);
  }
  h2 {
    text-align: center;
    font-size: 2rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 24px;
  }
  .info {
    margin-bottom: 20px;
    padding: 16px;
    background: #f9fafb;
    border-radius: 8px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
  }
  .info:hover {
    transform: translateY(-4px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.1);
  }
  .info label {
    font-weight: 600;
    color: #4b5563;
    display: block;
    margin-bottom: 8px;
  }
  .info span {
    color: #1f2937;
    font-size: 1.1rem;
  }
  .btn-container {
    display: flex;
    justify-content: center;
    gap: 16px;
    margin-top: 32px;
  }
  .btn {
    padding: 12px 32px;
    background: linear-gradient(to right, #3b82f6, #2563eb);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
  }
  .btn:hover {
    background: linear-gradient(to right, #2563eb, #1e40af);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
  }
  .rating {
    text-align: center;
    margin: 24px 0;
    font-size: 1.25rem;
    color: #1f2937;
  }
  .stars {
    color: #f59e0b;
    font-size: 2rem;
    display: flex;
    justify-content: center;
    gap: 4px;
  }
  .stars span {
    transition: transform 0.3s ease, text-shadow 0.3s ease;
  }
  .stars span:hover {
    transform: scale(1.2);
    text-shadow: 0 0 10px rgba(245,158,11,0.5);
  }
  h3 {
    text-align: center;
    font-size: 1.5rem;
    font-weight: 600;
    color: #1f2937;
    margin: 32px 0 16px;
  }
  .charts-container {
    display: flex;
    flex-wrap: wrap;
    gap: 24px;
    justify-content: center;
  }
  .chart-box {
    flex: 1 1 300px;
    background: #f9fafb;
    padding: 16px;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
  }
  .chart-box:hover {
    transform: translateY(-4px);
  }
  .chart-box h4 {
    text-align: center;
    font-size: 1.1rem;
    font-weight: 600;
    color: #4b5563;
    margin-bottom: 12px;
  }
  canvas {
    max-width: 100%;
    height: 300px !important;
    border-radius: 8px;
  }
</style>
</head>
<body>
<div class="profile-container animate__animated animate__fadeIn">
  <h2>Your Profile</h2>

  <?php if ($user['profile_image'] && file_exists('./Uploads/profile_images/' . $user['profile_image'])): ?>
    <img src="<?php echo './Uploads/profile_images/' . htmlspecialchars($user['profile_image']); ?>" alt="Profile Image" class="profile-img animate__animated animate__zoomIn" />
  <?php else: ?>
    <img src="default-profile.png" alt="Profile Image" class="profile-img animate__animated animate__zoomIn" />
  <?php endif; ?>

  <div class="info animate__animated animate__fadeInUp">
    <label>Name</label>
    <span><?= htmlspecialchars($user['name']) ?></span>
  </div>

  <div class="info animate__animated animate__fadeInUp animate__delay-1s">
    <label>Email</label>
    <span><?= htmlspecialchars($user['email']) ?></span>
  </div>

  <div class="info animate__animated animate__fadeInUp animate__delay-2s">
    <label>Mobile</label>
    <span><?= htmlspecialchars($user['mobile']) ?></span>
  </div>

  <div class="rating animate__animated animate__fadeIn">
    <strong>Your Average Rating: </strong>
    <div class="stars">
      <?php
      $fullStars = floor($avg_rating);
      $halfStar = ($avg_rating - $fullStars >= 0.5) ? true : false;
      for ($i = 0; $i < $fullStars; $i++) {
        echo "<span class='animate__animated animate__pulse animate__faster'>★</span>";
      }
      if ($halfStar) {
        echo "<span class='animate__animated animate__pulse animate__faster'>☆</span>";
      }
      $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
      for ($i = 0; $i < $emptyStars; $i++) {
        echo "<span class='animate__animated animate__pulse animate__faster'>☆</span>";
      }
      ?>
    </div>
    <span>(<?= $avg_rating ?> / 5)</span>
  </div>

  <h3>Ride Requests & Activity Stats</h3>
  <div class="charts-container">
    <div class="chart-box animate__animated animate__fadeInLeft">
      <h4>Requests Made to Your Rides</h4>
      <canvas id="rideRequestsChart"></canvas>
    </div>
    <div class="chart-box animate__animated animate__fadeInRight">
      <h4>Your Posted Rides Requests</h4>
      <canvas id="postedRidesChart"></canvas>
    </div>
    <div class="chart-box animate__animated animate__fadeInUp">
      <h4>Your Ride Requests</h4>
      <canvas id="requestedRidesChart"></canvas>
    </div>
  </div>

  <div class="btn-container animate__animated animate__fadeInUp animate__delay-3s">
    <a href="dashboard.php" class="btn">Back to Dashboard</a>
    <a href="update_profile.php" class="btn">Update Profile</a>
  </div>
</div>

<script>
const rideRequestsCtx = document.getElementById('rideRequestsChart').getContext('2d');
const rideRequestsChart = new Chart(rideRequestsCtx, {
  type: 'bar',
  data: {
    labels: ['Total', 'Accepted', 'Rejected'],
    datasets: [{
      label: 'Requests Made to Your Rides',
      data: [<?= $stats['total'] ?? 0 ?>, <?= $stats['accepted'] ?? 0 ?>, <?= $stats['rejected'] ?? 0 ?>],
      backgroundColor: ['#3b82f6', '#22c55e', '#ef4444'],
      borderColor: ['#2563eb', '#16a34a', '#dc2626'],
      borderWidth: 1
    }]
  },
  options: {
    scales: {
      y: { beginAtZero: true, ticks: { precision: 0 } }
    },
    plugins: {
      legend: { display: false }
    }
  }
});

const postedRidesCtx = document.getElementById('postedRidesChart').getContext('2d');
const postedRidesChart = new Chart(postedRidesCtx, {
  type: 'pie',
  data: {
    labels: ['Total Requests', 'Accepted', 'Rejected'],
    datasets: [{
      label: 'Your Posted Rides Requests',
      data: [<?= $posted_stats['total'] ?? 0 ?>, <?= $posted_stats['accepted'] ?? 0 ?>, <?= $posted_stats['rejected'] ?? 0 ?>],
      backgroundColor: ['#6b7280', '#22c55e', '#ef4444'],
      borderColor: ['#4b5563', '#16a34a', '#dc2626'],
      borderWidth: 1
    }]
  },
  options: {
    plugins: {
      legend: { position: 'bottom' }
    }
  }
});

const requestedRidesCtx = document.getElementById('requestedRidesChart').getContext('2d');
const requestedRidesChart = new Chart(requestedRidesCtx, {
  type: 'doughnut',
  data: {
    labels: ['Total Requests Made', 'Accepted', 'Rejected'],
    datasets: [{
      label: 'Your Ride Requests',
      data: [<?= $requested_stats['total'] ?? 0 ?>, <?= $requested_stats['accepted'] ?? 0 ?>, <?= $requested_stats['rejected'] ?? 0 ?>],
      backgroundColor: ['#06b6d4', '#22c55e', '#ef4444'],
      borderColor: ['#0891b2', '#16a34a', '#dc2626'],
      borderWidth: 1
    }]
  },
  options: {
    plugins: {
      legend: { position: 'bottom' }
    }
  }
});
</script>
</body>
</html>