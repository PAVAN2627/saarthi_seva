<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$leaving_from = isset($_GET['leaving_from']) ? strtolower(trim($_GET['leaving_from'])) : '';
$going_to = isset($_GET['going_to']) ? strtolower(trim($_GET['going_to'])) : '';
$travel_date = isset($_GET['travel_date']) ? trim($_GET['travel_date']) : '';

if (!$leaving_from || !$going_to || !$travel_date) {
    echo "Please provide all search details.";
    exit();
}
$user_id = $_SESSION['user_id'];
$sql = "SELECT rides.*, users.email 
        FROM rides 
        JOIN users ON rides.user_id = users.id 
        WHERE travel_date = ? AND rides.user_id != ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $travel_date, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$matched_rides = [];

while ($ride = $result->fetch_assoc()) {
    $ride_start = strtolower($ride['leaving_from']);
    $ride_end = strtolower($ride['going_to']);
    $stops = strtolower($ride['stops']);
    $stop_list = array_map('trim', explode(',', $stops));

    $full_route = array_merge([$ride_start], $stop_list, [$ride_end]);

    $start_index = array_search($leaving_from, $full_route);
    $end_index = array_search($going_to, $full_route);

    if ($start_index !== false && $end_index !== false && $start_index < $end_index) {
        $matched_rides[] = $ride;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Search Results - Saarthi Seva</title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
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
  .results-container {
    max-width: 1200px;
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
  .no-results {
    text-align: center;
    color: #4b5563;
    font-size: 1rem;
    margin: 24px 0;
  }
  .table-container {
    overflow-x: auto;
    background: white;
    border-radius: 12px;
    box-shadow: 0 6px 12px rgba(0,0,0,0.1);
    margin-top: 16px;
  }
  table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    border-radius: 12px;
    overflow: hidden;
  }
  th, td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
  }
  th {
    background: linear-gradient(to right, #4b5563, #6b7280);
    color: white;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.875rem;
  }
  td {
    background: #ffffff;
    transition: background 0.2s ease;
  }
  tr:hover td {
    background: #f0f9ff;
  }
  .btn {
    padding: 8px 16px;
    background: linear-gradient(to right, #3b82f6, #2563eb);
    color: white;
    text-align: center;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    display: inline-block;
  }
  .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
  }
  .btn-back {
    background: linear-gradient(to right, #6b7280, #4b5563);
    margin-top: 24px;
    display: block;
    text-align: center;
  }
  .btn-back:hover {
    background: linear-gradient(to right, #4b5563, #374151);
  }
</style>
</head>
<body>
<div class="results-container animate__animated animate__fadeIn">
  <h2 class="animate__animated animate__fadeInDown">Search Results</h2>

  <?php if (empty($matched_rides)) : ?>
    <p class="no-results animate__animated animate__fadeIn">No rides found matching your search criteria.</p>
  <?php else : ?>
    <div class="table-container animate__animated animate__fadeInUp">
      <table>
        <thead>
          <tr>
            <th>Leaving From</th>
            <th>Going To</th>
            <th>Stops</th>
            <th>Travel Date</th>
            <th>Travel Time</th>
            <th>Vehicle</th>
            <th>Purpose</th>
            <th>Members</th>
            <th>Pay Scale</th>
            <th>Contact</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($matched_rides as $ride) : ?>
            <tr class="animate__animated animate__fadeInUp">
              <td><?= htmlspecialchars($ride['leaving_from']) ?></td>
              <td><?= htmlspecialchars($ride['going_to']) ?></td>
              <td><?= htmlspecialchars($ride['stops']) ?></td>
              <td><?= htmlspecialchars($ride['travel_date']) ?></td>
              <td><?= htmlspecialchars($ride['travel_time']) ?></td>
              <td><?= htmlspecialchars($ride['vehicle_type']) . ' - ' . htmlspecialchars($ride['vehicle_name']) ?></td>
              <td><?= htmlspecialchars($ride['purpose']) ?></td>
              <td><?= htmlspecialchars($ride['members']) ?></td>
              <td><?= htmlspecialchars($ride['pay_scale']) ?></td>
              <td><?= htmlspecialchars($ride['email']) ?></td>
              <td>
                <form action="view_ride.php" method="get">
                  <input type="hidden" name="ride_id" value="<?= $ride['id'] ?>">
                  <button type="submit" class="btn">View Details</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <a href="dashboard.php" class="btn btn-back animate__animated animate__fadeInUp animate__delay-1s">Back to Dashboard</a>
</div>
</body>
</html>