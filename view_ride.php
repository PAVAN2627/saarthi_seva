<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['ride_id'])) {
    echo "Ride ID not specified.";
    exit();
}

$user_id = $_SESSION['user_id'];
$ride_id = intval($_GET['ride_id']);

// Fetch ride details and owner email
$sql = "SELECT rides.*, users.email, users.name, users.mobile 
        FROM rides 
        JOIN users ON rides.user_id = users.id 
        WHERE rides.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $ride_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "No ride found.";
    exit();
}

$ride = $result->fetch_assoc();

// Stats for requests made TO this ride
$count_sql = "
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'Accepted' THEN 1 ELSE 0 END) AS accepted,
        SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) AS rejected
    FROM ride_requests
    WHERE ride_id = ?
";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("i", $ride_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$stats = $count_result->fetch_assoc();

// Fetch average rating for the ride owner
$rating_sql = "
    SELECT AVG(rr.rating) AS average_rating, COUNT(rr.rating) AS rating_count
    FROM ride_requests rr
    JOIN rides r ON rr.ride_id = r.id
    WHERE r.user_id = ?
";
$rating_stmt = $conn->prepare($rating_sql);
$rating_stmt->bind_param("i", $ride['user_id']);
$rating_stmt->execute();
$rating_result = $rating_stmt->get_result();
$rating = $rating_result->fetch_assoc();
$average_rating = $rating['average_rating'] ? number_format($rating['average_rating'], 1) : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Ride Details - Saarthi Seva</title>
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
  .details-container {
    max-width: 800px;
    width: 100%;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    padding: 32px;
    margin: 0 auto;
  }
  h2 {
    text-align: center;
    font-size: 1.75rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 24px;
  }
  h3 {
    text-align: center;
    font-size: 1.25rem;
    font-weight: 600;
    color: #4b5563;
    margin: 32px 0 16px;
  }
  .info-group {
    display: flex;
    justify-content: space-between;
    margin-bottom: 16px;
    padding: 12px;
    background: #f9fafb;
    border-radius: 8px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
  }
  .info-group:hover {
    transform: translateY(-4px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.1);
  }
  .info-group strong {
    color: #4b5563;
    font-weight: 600;
    width: 40%;
  }
  .info-group span {
    color: #1f2937;
    width: 60%;
    text-align: right;
  }
  .rating-group {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
    padding: 12px;
    background: #f0fdf4;
    border-radius: 8px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
  }
  .rating-group:hover {
    transform: translateY(-4px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.1);
  }
  .rating-group strong {
    color: #4b5563;
    font-weight: 600;
    width: 40%;
  }
  .rating-group span {
    color: #22c55e;
    font-weight: 600;
    width: 60%;
    text-align: right;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
  }
  .rating-group span svg {
    fill: #facc15;
    width: 20px;
    height: 20px;
  }
  .id-proof {
    text-align: center;
    margin: 24px 0;
  }
  .id-proof img {
    max-width: 300px;
    max-height: 300px;
    border-radius: 8px;
    border: 1px solid #d1d5db;
    transition: transform 0.3s ease;
  }
  .id-proof img:hover {
    transform: scale(1.05);
  }
  .id-proof a {
    display: inline-block;
    margin-top: 12px;
    color: #3b82f6;
    text-decoration: none;
    font-weight: 600;
    transition: color 0.3s ease;
  }
  .id-proof a:hover {
    color: #2563eb;
  }
  .charts-container {
    display: flex;
    justify-content: center;
    margin-top: 24px;
  }
  .chart-box {
    flex: 1 1 250px;
    max-width: 300px;
    background: #f9fafb;
    padding: 16px;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    text-align: center;
    transition: transform 0.3s ease;
  }
  .chart-box:hover {
    transform: translateY(-4px);
  }
  .chart-box h4 {
    font-size: 1rem;
    font-weight: 600;
    color: #4b5563;
    margin-bottom: 12px;
  }
  canvas {
    max-width: 100%;
    height: 250px !important;
    border-radius: 8px;
  }
  .booking-form {
    margin-top: 32px;
    padding: 24px;
    background: #f9fafb;
    border-radius: 12px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
  }
  .form-group {
    margin-bottom: 20px;
  }
  .form-group label {
    display: block;
    font-weight: 600;
    color: #4b5563;
    margin-bottom: 8px;
    font-size: 0.9rem;
  }
  .form-group input[type="text"] {
    width: 100%;
    padding: 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 1rem;
    background: white;
    transition: all 0.3s ease;
  }
  .form-group input[type="text"]:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    outline: none;
  }
  .checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
  .checkbox-group label {
    display: flex;
    align-items: center;
    color: #1f2937;
    font-size: 0.9rem;
  }
  .checkbox-group input[type="checkbox"] {
    margin-right: 8px;
    accent-color: #3b82f6;
  }
  .btn-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 24px;
    align-items: center;
  }
  .btn {
    padding: 12px 24px;
    background: linear-gradient(to right, #3b82f6, #2563eb);
    color: white;
    text-align: center;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
  }
  .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
  }
  .btn-back {
    background: linear-gradient(to right, #6b7280, #4b5563);
  }
  .btn-back:hover {
    background: linear-gradient(to right, #4b5563, #374151);
  }
</style>
</head>
<body>
<div class="details-container animate__animated animate__fadeIn">
  <h2 class="animate__animated animate__fadeInDown">Ride Details</h2>

  <div class="info-group animate__animated animate__fadeInUp">
    <strong>Owner Email</strong>
    <span><?= htmlspecialchars($ride['email']) ?></span>
  </div>
  <div class="info-group animate__animated animate__fadeInUp animate__delay-1s">
    <strong>Rider Name</strong>
    <span><?= htmlspecialchars($ride['name']) ?></span>
  </div>
  <div class="info-group animate__animated animate__fadeInUp animate__delay-2s">
    <strong>Rider Mobile</strong>
    <span><?= htmlspecialchars($ride['mobile']) ?></span>
  </div>
  <div class="info-group animate__animated animate__fadeInUp animate__delay-3s">
    <strong>Leaving From</strong>
    <span><?= htmlspecialchars($ride['leaving_from']) ?></span>
  </div>
  <div class="info-group animate__animated animate__fadeInUp animate__delay-4s">
    <strong>Going To</strong>
    <span><?= htmlspecialchars($ride['going_to']) ?></span>
  </div>
  <div class="info-group animate__animated animate__fadeInUp animate__delay-5s">
    <strong>Stops</strong>
    <span><?= htmlspecialchars($ride['stops']) ?></span>
  </div>
  <div class="info-group animate__animated animate__fadeInUp animate__delay-6s">
    <strong>Travel Date</strong>
    <span><?= htmlspecialchars($ride['travel_date']) ?></span>
  </div>
  <div class="info-group animate__animated animate__fadeInUp animate__delay-7s">
    <strong>Travel Time</strong>
    <span><?= htmlspecialchars($ride['travel_time']) ?></span>
  </div>
  <div class="info-group animate__animated animate__fadeInUp animate__delay-8s">
    <strong>Vehicle</strong>
    <span><?= htmlspecialchars($ride['vehicle_type']) ?> - <?= htmlspecialchars($ride['vehicle_name']) ?></span>
  </div>
  <div class="info-group animate__animated animate__fadeInUp animate__delay-9s">
    <strong>Purpose</strong>
    <span><?= htmlspecialchars($ride['purpose']) ?></span>
  </div>
  <div class="info-group animate__animated animate__fadeInUp animate__delay-10s">
    <strong>Members Allowed</strong>
    <span><?= htmlspecialchars($ride['members']) ?></span>
  </div>
  <div class="info-group animate__animated animate__fadeInUp animate__delay-11s">
    <strong>Pay Scale</strong>
    <span><?= htmlspecialchars($ride['pay_scale']) ?></span>
  </div>
  <div class="info-group animate__animated animate__fadeInUp animate__delay-12s">
    <strong>Additional Notes</strong>
    <span><?= htmlspecialchars($ride['notes']) ?></span>
  </div>

  <?php if (!empty($ride['id_proof'])) : ?>
    <div class="id-proof animate__animated animate__fadeInUp animate__delay-13s">
      <strong>ID Proof</strong>
      <img src="<?= htmlspecialchars($ride['id_proof']) ?>" alt="ID Proof" />
      <a href="<?= htmlspecialchars($ride['id_proof']) ?>" download>Download ID Proof</a>
    </div>
  <?php endif; ?>

  <div class="rating-group animate__animated animate__fadeInUp animate__delay-14s">
    <strong>Rider Rating</strong>
    <span>
      <?= $average_rating ? "$average_rating/5" : "No ratings yet" ?>
      <?php if ($average_rating): ?>
        <svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
      <?php endif; ?>
    </span>
  </div>

  <h3 class="animate__animated animate__fadeIn">Statistics Overview</h3>
  <div class="charts-container">
    <div class="chart-box animate__animated animate__fadeInUp">
      <h4>Requests Made to This Ride</h4>
      <canvas id="rideRequestsChart"></canvas>
    </div>
  </div>

  <div class="booking-form animate__animated animate__fadeInUp animate__delay-1s">
    <form method="post" action="book_ride.php" class="space-y-4">
      <input type="hidden" name="ride_id" value="<?= htmlspecialchars($ride['id']) ?>">

      <div class="form-group">
        <label for="start_point">Start Point</label>
        <input type="text" name="start_point" id="start_point" required class="transition-all duration-300" />
      </div>

      <div class="form-group">
        <label for="end_point">End Point</label>
        <input type="text" name="end_point" id="end_point" required class="transition-all duration-300" />
      </div>

      <div class="form-group">
        <label>Purpose</label>
        <div class="checkbox-group">
          <label><input type="checkbox" name="purpose[]" value="Person"> Person</label>
          <label><input type="checkbox" name="purpose[]" value="Medicine"> Medicine</label>
          <label><input type="checkbox" name="purpose[]" value="Delivery"> Delivery</label>
        </div>
      </div>

      <div class="btn-container">
        <button type="submit" class="btn">Request Ride</button>
      </div>
    </form>
  </div>

  <div class="btn-container animate__animated animate__fadeInUp animate__delay-2s">
    <a href="dashboard.php" class="btn btn-back">Back to Search</a>
  </div>
</div>

<script>
const rideRequestsCtx = document.getElementById('rideRequestsChart').getContext('2d');
const rideRequestsChart = new Chart(rideRequestsCtx, {
  type: 'bar',
  data: {
    labels: ['Total', 'Accepted', 'Rejected'],
    datasets: [{
      label: 'Requests Made to This Ride',
      data: [<?= $stats['total'] ?? 0 ?>, <?= $stats['accepted'] ?? 0 ?>, <?= $stats['rejected'] ?? 0 ?>],
      backgroundColor: ['#3b82f6', '#22c55e', '#ef4444'],
      borderColor: ['#2563eb', '#16a34a', '#dc2626'],
      borderWidth: 1
    }]
  },
  options: {
    responsive: true,
    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
    plugins: { legend: { display: false } }
  }
});
</script>
</body>
</html>