<?php
session_start();
require 'db.php';
require 'vendor/autoload.php'; // Adjust if not using Composer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Function to send acceptance email
function sendAcceptanceEmail($toEmail, $requestDetails) {
    $mail = new PHPMailer(true);
    try {
        // SMTP server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'pavanmalith3@gmail.com';
        $mail->Password = 'aiyq ydhn bltc wfqq';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Email content
        $mail->setFrom('pavanmalith3@gmail.com', 'Saarthi Seva');
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = 'Saarthi Seva Ride Request Accepted';

        $stops = !empty($requestDetails['stops']) ? $requestDetails['stops'] : 'None';
        $mail->Body = "
            <h3>Dear {$requestDetails['requester_name']},</h3>
            <p>Thank you for your ride request with Saarthi Seva. We are pleased to inform you that your request has been <b>accepted</b>.</p>
            <h4>Ride Details:</h4>
            <ul>
                <li><b>Request ID:</b> {$requestDetails['request_id']}</li>
                <li><b>Starting Point:</b> {$requestDetails['start_point']}</li>
                <li><b>Ending Point:</b> {$requestDetails['end_point']}</li>
                <li><b>Travel Date:</b> {$requestDetails['travel_date']}</li>
                <li><b>Travel Time:</b> {$requestDetails['travel_time']}</li>
                <li><b>Stops:</b> {$stops}</li>
                <li><b>Status:</b> Accepted</li>
            </ul>
            <p>Please ensure you are available at the specified starting point on <b>{$requestDetails['travel_date']}</b> at <b>{$requestDetails['travel_time']}</b>. For any queries, contact us at pavanmalith3@gmail.com.</p>
            <p>Thank you for choosing Saarthi Seva!</p>
            <p>Best regards,<br>Saarthi Seva Team</p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Handle accept/reject POST from received requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['request_id'])) {
    $request_id = intval($_POST['request_id']);
    $action = $_POST['action'];

    if (in_array($action, ['accept', 'reject'])) {
        $new_status = ($action === 'accept') ? 'Accepted' : 'Rejected';

        // Update the request status
        $stmt_update = $conn->prepare("UPDATE ride_requests SET status = ? WHERE id = ?");
        $stmt_update->bind_param("si", $new_status, $request_id);
        $stmt_update->execute();
        $stmt_update->close();

        // If action is accept, send email
        if ($action === 'accept') {
            // Fetch request details for email
            $stmt_details = $conn->prepare("
                SELECT rr.id AS request_id, rr.user_id, rr.start_point, rr.end_point, r.travel_date, r.travel_time, r.stops, u.name AS requester_name, u.email AS requester_email
                FROM ride_requests rr
                JOIN rides r ON rr.ride_id = r.id
                JOIN users u ON rr.user_id = u.id
                WHERE rr.id = ?
            ");
            // If using leaving_from and going_to from rides table, use this query instead:
            /*
            $stmt_details = $conn->prepare("
                SELECT rr.id AS request_id, rr.user_id, r.leaving_from AS start_point, r.going_to AS end_point, r.travel_date, r.travel_time, r.stops, u.name AS requester_name, u.email AS requester_email
                FROM ride_requests rr
                JOIN rides r ON rr.ride_id = r.id
                JOIN users u ON rr.user_id = u.id
                WHERE rr.id = ?
            ");
            */
            $stmt_details->bind_param("i", $request_id);
            $stmt_details->execute();
            $result = $stmt_details->get_result();
            $request_details = $result->fetch_assoc();
            $stmt_details->close();

            if ($request_details) {
                // Prepare details for email
                $email_details = [
                    'request_id' => $request_details['request_id'],
                    'requester_name' => $request_details['requester_name'],
                    'start_point' => $request_details['start_point'],
                    'end_point' => $request_details['end_point'],
                    'travel_date' => $request_details['travel_date'],
                    'travel_time' => $request_details['travel_time'],
                    'stops' => $request_details['stops'],
                ];

                // Send acceptance email
                if (!sendAcceptanceEmail($request_details['requester_email'], $email_details)) {
                    error_log("Failed to send acceptance email for request ID: $request_id");
                }
            }
        }

        header("Location: dashboard.php");
        exit();
    }
}

// Fetch user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch rides posted by user
$stmt_rides = $conn->prepare("SELECT * FROM rides WHERE user_id = ? ORDER BY travel_date DESC, travel_time DESC");
$stmt_rides->bind_param("i", $user_id);
$stmt_rides->execute();
$posted_rides = $stmt_rides->get_result();
$stmt_rides->close();

// Fetch ride requests made by user (requested rides)
$stmt_requests = $conn->prepare("
    SELECT rr.*, r.leaving_from, r.going_to, r.travel_date, r.travel_time, u.name AS rider_name
    FROM ride_requests rr
    LEFT JOIN rides r ON rr.ride_id = r.id
    LEFT JOIN users u ON r.user_id = u.id
    WHERE rr.user_id = ?
    ORDER BY r.travel_date DESC, r.travel_time DESC
");
$stmt_requests->bind_param("i", $user_id);
$stmt_requests->execute();
$requested_rides = $stmt_requests->get_result();
$stmt_requests->close();

// Fetch ride requests received on rides posted by user (received requests)
$stmt_received = $conn->prepare("
    SELECT rr.*, u.name AS requester_name, u.email AS requester_email, rr.start_point, rr.end_point, r.travel_date, r.travel_time, r.stops
    FROM ride_requests rr
    JOIN rides r ON rr.ride_id = r.id
    JOIN users u ON rr.user_id = u.id
    WHERE r.user_id = ?
    ORDER BY r.travel_date DESC, r.travel_time DESC
");
// If using leaving_from and going_to from rides table, use this query instead:
/*
$stmt_received = $conn->prepare("
    SELECT rr.*, u.name AS requester_name, u.email AS requester_email, r.leaving_from AS start_point, r.going_to AS end_point, r.travel_date, r.travel_time, r.stops
    FROM ride_requests rr
    JOIN rides r ON rr.ride_id = r.id
    JOIN users u ON rr.user_id = u.id
    WHERE r.user_id = ?
    ORDER BY r.travel_date DESC, r.travel_time DESC
");
*/
$stmt_received->bind_param("i", $user_id);
$stmt_received->execute();
$received_requests = $stmt_received->get_result();
$stmt_received->close();

// Determine logo based on email domain
$email = $user['email'];
$email_domain = substr(strrchr($email, "@"), 1);
$logo_url = '';
switch ($email_domain) {
    case 'gmail.com':
        $logo_url = 'https://www.google.com/favicon.ico';
        break;
    case 'yahoo.com':
        $logo_url = 'https://www.yahoo.com/favicon.ico';
        break;
    default:
        $logo_url = 'https://example.com/default-logo.png'; // Fallback logo
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Dashboard - Saarthi Seva</title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
<style>
  body {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
  }
  .sidebar {
    height: 100vh;
    width: 300px;
    background: #1f2937;
    color: white;
    position: fixed;
    top: 0;
    left: -300px;
    transition: left 0.3s ease-in-out;
    z-index: 1000;
    padding-top: 60px;
    overflow-y: auto;
  }
  .sidebar.active {
    left: 0;
    box-shadow: 4px 0 10px rgba(0,0,0,0.3);
  }
  .sidebar a {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    color: white;
    text-decoration: none;
    font-size: 1.125rem;
    transition: all 0.3s ease;
  }
  .sidebar a:hover {
    background: #3b82f6;
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
  }
  .main-content {
    transition: margin-left 0.3s ease-in-out;
    max-width: 800px;
    margin-left: auto;
    margin-right: auto;
    padding: 24px;
  }
  .main-content.shift {
    margin-left: 350px;
  }
  .hamburger div {
    transition: all 0.3s ease;
  }
  .hamburger.active div:nth-child(1) {
    transform: rotate(45deg) translate(5px, 5px);
  }
  .hamburger.active div:nth-child(2) {
    opacity: 0;
  }
  .hamburger.active div:nth-child(3) {
    transform: rotate(-45deg) translate(7px, -7px);
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
  .star-rating {
    direction: rtl;
    display: flex;
    justify-content: center;
    gap: 8px;
  }
  .star-rating input[type="radio"] {
    display: none;
  }
  .star-rating label {
    font-size: 2rem;
    color: #d1d5db;
    cursor: pointer;
    transition: all 0.3s ease;
    text-shadow: 0 0 5px rgba(0,0,0,0.1);
  }
  .star-rating label:hover,
  .star-rating input[type="radio"]:checked ~ label {
    color: #f59e0b;
    transform: scale(1.2);
    text-shadow: 0 0 10px rgba(245,158,11,0.5);
  }
  .star-rating input[type="radio"]:checked ~ label {
    color: #f59e0b;
  }
  .star-rating label:hover ~ label {
    color: #f59e0b;
  }
  #rating-popup {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 20px rgba(0,0,0,0.2);
    transform: translate(-50%, -50%) scale(0.8);
    transition: transform 0.3s ease, opacity 0.3s ease;
    max-width: 400px;
    width: 90%;
    opacity: 0;
  }
  #rating-popup.active {
    transform: translate(-50%, -50%) scale(1);
    opacity: 1;
  }
  #popup-overlay {
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(4px);
  }
  .form-container {
    max-width: 600px;
    margin: 0 auto;
  }
  .welcome-container {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
  }
</style>
</head>
<body>

<div class="hamburger fixed top-4 left-4 z-50 cursor-pointer" onclick="toggleSidebar()">
  <div class="w-8 h-1 bg-gray-800 mb-1.5 rounded"></div>
  <div class="w-8 h-1 bg-gray-800 mb-1.5 rounded"></div>
  <div class="w-8 h-1 bg-gray-800 rounded"></div>
</div>

<div class="sidebar">
  <a href="profile.php" class="animate__animated animate__pulse animate__faster">Profile</a>
  <a href="javascript:void(0)" onclick="showSection('post-ride-section')" class="animate__animated animate__pulse animate__faster">Post Ride</a>
  <a href="javascript:void(0)" onclick="showSection('posted-rides-section')" class="animate__animated animate__pulse animate__faster">Your Posted Rides</a>
  <a href="javascript:void(0)" onclick="showSection('received-requests-section')" class="animate__animated animate__pulse animate__faster">Received Ride Requests</a>
  <a href="javascript:void(0)" onclick="showSection('search-section')" class="animate__animated animate__pulse animate__faster">Search Ride</a>
  <a href="javascript:void(0)" onclick="showSection('requested-rides-section')" class="animate__animated animate__pulse animate__faster">Requested Rides</a>
  <a href="logout.php" class="animate__animated animate__pulse animate__faster">Logout</a>
</div>

<div class="main-content">
  <div class="welcome-container text-2xl font-bold mb-6 animate__animated animate__fadeIn">
    <span>Welcome, <?= htmlspecialchars($user['name']); ?></span>
  </div>

  <!-- Search Ride Section -->
  <div id="search-section" class="section animate__animated animate__fadeIn hidden">
    <h3 class="text-xl font-semibold mb-4 text-center">Search Rides</h3>
    <div class="form-container">
      <form method="GET" action="search_results.php" class="space-y-4 bg-white p-6 rounded-lg shadow-md">
        <div>
          <label class="block text-sm font-medium text-center">Leaving From</label>
          <input type="text" name="leaving_from" required class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-center">Going To</label>
          <input type="text" name="going_to" required class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-center">Travel Date</label>
          <input type="date" name="travel_date" required class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="text-center">
          <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-colors">Search</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Requested Rides Section -->
  <div id="requested-rides-section" class="section animate__animated animate__fadeIn hidden">
    <h3 class="text-xl font-semibold mb-4 text-center">Your Requested Rides</h3>
    <?php if ($requested_rides->num_rows > 0): ?>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Rider Name</th>
            <th>Ride From</th>
            <th>Ride To</th>
            <th>Date</th>
            <th>Time</th>
            <th>Status</th>
            <th>Rating</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $requested_rides->fetch_assoc()): ?>
            <tr class="animate__animated animate__fadeInUp">
              <td><?= htmlspecialchars($row['rider_name']); ?></td>
              <td><?= htmlspecialchars($row['start_point']); ?></td>
              <td><?= htmlspecialchars($row['end_point']); ?></td>
              <td><?= htmlspecialchars($row['travel_date']); ?></td>
              <td><?= htmlspecialchars($row['travel_time']); ?></td>
              <td><?= htmlspecialchars($row['status']); ?></td>
              <td>
                <?php 
                if ($row['rating'] !== null) {
                  echo str_repeat('⭐', intval($row['rating']));
                } else {
                  echo '-';
                }
                ?>
              </td>
              <td>
                <?php if ($row['status'] === 'Accepted' && $row['rating'] === null): ?>
                  <button onclick="openRatingPopup(<?= $row['id']; ?>)" class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600 transition-colors">Rate Rider</button>
                <?php else: ?>
                  -
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <p class="text-gray-600 text-center">You have not requested any rides yet.</p>
    <?php endif; ?>
  </div>

  <!-- Posted Rides Section -->
  <div id="posted-rides-section" class="section animate__animated animate__fadeIn hidden">
    <h3 class="text-xl font-semibold mb-4 text-center">Your Posted Rides</h3>
    <?php if ($posted_rides->num_rows > 0): ?>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Leaving From</th>
              <th>Going To</th>
              <th>Travel Date</th>
              <th>Travel Time</th>
              <th>Vehicle Type</th>
              <th>Vehicle Name</th>
              <th>Passengers</th>
              <th>Purpose</th>
              <th>Pay Scale</th>
              <th>Stops</th>
              <th>Notes</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($ride = $posted_rides->fetch_assoc()): ?>
              <tr class="animate__animated animate__fadeInUp">
                <td><?= htmlspecialchars($ride['leaving_from']) ?></td>
                <td><?= htmlspecialchars($ride['going_to']) ?></td>
                <td><?= htmlspecialchars($ride['travel_date']) ?></td>
                <td><?= htmlspecialchars($ride['travel_time']) ?></td>
                <td><?= htmlspecialchars($ride['vehicle_type']) ?></td>
                <td><?= htmlspecialchars($ride['vehicle_name']) ?></td>
                <td><?= htmlspecialchars($ride['members']) ?></td>
                <td><?= htmlspecialchars($ride['purpose']) ?></td>
                <td><?= htmlspecialchars($ride['pay_scale']) ?></td>
                <td><?= htmlspecialchars($ride['stops']) ?></td>
                <td><?= htmlspecialchars($ride['notes']) ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="text-gray-600 text-center">You have not posted any rides yet.</p>
    <?php endif; ?>
  </div>

  <!-- Received Requests Section -->
  <div id="received-requests-section" class="section animate__animated animate__fadeIn hidden">
    <h3 class="text-xl font-semibold mb-4 text-center">Received Ride Requests</h3>
    <?php if ($received_requests->num_rows > 0): ?>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Requester</th>
            <th>Email</th>
            <th>From</th>
            <th>To</th>
            <th>Date</th>
            <th>Time</th>
            <th>Stops</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($req = $received_requests->fetch_assoc()): ?>
            <tr class="animate__animated animate__fadeInUp">
              <td><?= htmlspecialchars($req['requester_name']); ?></td>
              <td><?= htmlspecialchars($req['requester_email']); ?></td>
              <td><?= htmlspecialchars($req['start_point']); ?></td>
              <td><?= htmlspecialchars($req['end_point']); ?></td>
              <td><?= htmlspecialchars($req['travel_date']); ?></td>
              <td><?= htmlspecialchars($req['travel_time']); ?></td>
              <td><?= htmlspecialchars($req['stops'] ?: 'None'); ?></td>
              <td><?= htmlspecialchars($req['status']); ?></td>
              <td>
                <?php if ($req['status'] === 'Pending'): ?>
                  <form method="POST" class="inline">
                    <input type="hidden" name="request_id" value="<?= $req['id']; ?>">
                    <button type="submit" name="action" value="accept" class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600 transition-colors">Accept</button>
                  </form>
                  <form method="POST" class="inline ml-2">
                    <input type="hidden" name="request_id" value="<?= $req['id']; ?>">
                    <button type="submit" name="action" value="reject" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 transition-colors">Reject</button>
                  </form>
                <?php else: ?>
                  -
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <p class="text-gray-600 text-center">No ride requests received yet.</p>
    <?php endif; ?>
  </div>

  <!-- Post Ride Section -->
  <div id="post-ride-section" class="section animate__animated animate__fadeIn hidden">
    <h3 class="text-xl font-semibold mb-4 text-center">Post Your Ride</h3>
    <div class="form-container">
      <form method="POST" action="post_ride.php" enctype="multipart/form-data" class="space-y-4 bg-white p-6 rounded-lg shadow-md">
        <div>
          <label class="block text-sm font-medium text-center">Leaving From</label>
          <input type="text" name="leaving_from" required class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-center">Going To</label>
          <input type="text" name="going_to" required class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-center">Travel Date</label>
          <input type="date" name="travel_date" required class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-center">Travel Time</label>
          <input type="time" name="travel_time" required class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-center">Stops (optional)</label>
          <input type="text" name="stops" placeholder="Stop1, Stop2, ..." class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-center">Vehicle Type</label>
          <select name="vehicle_type" required class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500">
            <option value="Bike">Bike</option>
            <option value="Car">Car</option>
            <option value="Traveller">Traveller</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-center">Vehicle Name / Model</label>
          <input type="text" name="vehicle_name" required class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-center">Purpose of Ride</label>
          <div class="space-y-2 text-center">
            <label class="inline-flex items-center"><input type="checkbox" name="purpose[]" value="Passenger"> Passenger</label><br>
            <label class="inline-flex items-center"><input type="checkbox" name="purpose[]" value="Medicine"> Medicine</label><br>
            <label class="inline-flex items-center"><input type="checkbox" name="purpose[]" value="Grocery"> Grocery</label><br>
            <label class="inline-flex items-center"><input type="checkbox" name="purpose[]" value="Other"> Other</label>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-center">Number of Passengers</label>
          <input type="number" name="members" min="1" required class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-center">Pay Scale</label>
          <input type="text" name="pay_scale" placeholder="Fare or Free" required class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-center">Upload ID Proof</label>
          <input type="file" name="id_proof" accept="image/*,application/pdf" required class="w-full p-2 border rounded">
        </div>
        <div>
          <label class="block text-sm font-medium text-center">Additional Notes</label>
          <textarea name="notes" class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-500"></textarea>
        </div>
        <div class="text-center">
          <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-colors">Post Ride</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Rating Popup -->
  <div id="popup-overlay" class="fixed inset-0 bg-black bg-opacity-50 hidden" onclick="closeRatingPopup()"></div>
  <div id="rating-popup" class="fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white p-6 rounded-lg hidden">
    <h3 class="text-lg font-semibold mb-4 text-center">Rate the Rider</h3>
    <form id="rating-form" onsubmit="submitRating(event)" class="space-y-4">
      <input type="hidden" id="request-id" name="request_id" value="">
      <div class="star-rating">
        <input type="radio" id="star5" name="rating" value="5" /><label for="star5" class="animate__animated animate__pulse animate__faster">★</label>
        <input type="radio" id="star4" name="rating" value="4" /><label for="star4" class="animate__animated animate__pulse animate__faster">★</label>
        <input type="radio" id="star3" name="rating" value="3" /><label for="star3" class="animate__animated animate__pulse animate__faster">★</label>
        <input type="radio" id="star2" name="rating" value="2" /><label for="star2" class="animate__animated animate__pulse animate__faster">★</label>
        <input type="radio" id="star1" name="rating" value="1" /><label for="star1" class="animate__animated animate__pulse animate__faster">★</label>
      </div>
      <div class="flex space-x-2 justify-center">
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-colors">Submit Rating</button>
        <button type="button" onclick="closeRatingPopup()" class="bg-gray-300 text-gray-800 px-4 py-2 rounded hover:bg-gray-400 transition-colors">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleSidebar() {
  const sidebar = document.querySelector('.sidebar');
  const mainContent = document.querySelector('.main-content');
  const hamburger = document.querySelector('.hamburger');
  sidebar.classList.toggle('active');
  mainContent.classList.toggle('shift');
  hamburger.classList.toggle('active');
}

function showSection(sectionId) {
  document.querySelectorAll('.section').forEach(s => s.classList.add('hidden'));
  const section = document.getElementById(sectionId);
  section.classList.remove('hidden');
  section.classList.add('animate__animated', 'animate__fadeIn');
  document.querySelector('.sidebar').classList.remove('active');
  document.querySelector('.main-content').classList.remove('shift');
  document.querySelector('.hamburger').classList.remove('active');
}

function openRatingPopup(requestId) {
  document.getElementById('request-id').value = requestId;
  const popup = document.getElementById('rating-popup');
  popup.style.display = 'block';
  popup.classList.add('active');
  document.getElementById('popup-overlay').style.display = 'block';
}

function closeRatingPopup() {
  const popup = document.getElementById('rating-popup');
  popup.style.display = 'none';
  popup.classList.remove('active');
  document.getElementById('popup-overlay').style.display = 'none';
  const stars = document.querySelectorAll('#rating-form input[name="rating"]');
  stars.forEach(star => star.checked = false);
}

function submitRating(event) {
  event.preventDefault();
  const requestId = document.getElementById('request-id').value;
  const rating = document.querySelector('#rating-form input[name="rating"]:checked');
  if (!rating) {
    alert('Please select a rating.');
    return;
  }

  fetch('submit_rating.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ request_id: requestId, rating: rating.value })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      alert('Rating submitted successfully!');
      closeRatingPopup();
      location.reload();
    } else {
      alert('Failed to submit rating: ' + data.message);
    }
  })
  .catch(() => alert('Error submitting rating'));
}

window.onload = function() {
  showSection('search-section');
}
</script>
</body>
</html>