<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $ride_id = isset($_POST['ride_id']) ? intval($_POST['ride_id']) : 0;
    $start_point = isset($_POST['start_point']) ? trim($_POST['start_point']) : '';
    $end_point = isset($_POST['end_point']) ? trim($_POST['end_point']) : '';

    if ($ride_id <= 0 || empty($start_point) || empty($end_point)) {
        $error = "Please fill all required fields.";
    } else {
        // Get purposes array from POST, if none selected, empty string
        if (isset($_POST['purpose']) && is_array($_POST['purpose'])) {
            // Sanitize purposes to prevent SQL injection
            $purposes = array_map(function($p) use ($conn) {
                return $conn->real_escape_string($p);
            }, $_POST['purpose']);
            $purpose_str = implode(',', $purposes);
        } else {
            $purpose_str = '';
        }

        // Check if ride exists
        $stmt = $conn->prepare("SELECT * FROM rides WHERE id = ?");
        $stmt->bind_param("i", $ride_id);
        $stmt->execute();
        $ride_result = $stmt->get_result();
        if ($ride_result->num_rows === 0) {
            $error = "Ride not found.";
        } else {
            // Insert into ride_requests table including purpose
            $stmt = $conn->prepare("INSERT INTO ride_requests (user_id, ride_id, start_point, end_point, purpose, request_time) VALUES (?, ?, ?, ?, ?, NOW())");
            if (!$stmt) {
                $error = "Prepare failed: (" . $conn->errno . ") " . $conn->error;
            } else {
                $stmt->bind_param("iisss", $user_id, $ride_id, $start_point, $end_point, $purpose_str);
                if ($stmt->execute()) {
                    $success = true;
                } else {
                    $error = "Error sending ride request: " . $conn->error;
                }
            }
        }
    }
} else {
    $error = "Invalid request method.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Book Ride - Saarthi Seva</title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
<style>
  body {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
    overflow: hidden;
  }
  .success-container {
    max-width: 500px;
    width: 100%;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    padding: 32px;
    text-align: center;
  }
  .success-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 16px;
  }
  .success-icon svg {
    fill: #22c55e;
    width: 100%;
    height: 100%;
  }
  .success-message {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 24px;
  }
  .error-message {
    max-width: 500px;
    width: 100%;
    background: #fef2f2;
    border-radius: 12px;
    padding: 16px;
    text-align: center;
    color: #ef4444;
    font-size: 1rem;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
  }
  .error-message a {
    color: #3b82f6;
    text-decoration: none;
    font-weight: 600;
  }
  .error-message a:hover {
    color: #2563eb;
  }
  .btn {
    padding: 12px 24px;
    background: linear-gradient(to right, #6b7280, #4b5563);
    color: white;
    text-align: center;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
    display: inline-block;
  }
  .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    background: linear-gradient(to right, #4b5563, #374151);
  }
</style>
</head>
<body>
<?php if ($success): ?>
  <div class="success-container animate__animated animate__fadeIn">
    <div class="success-icon animate__animated animate__bounceIn">
      <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
    </div>
    <h2 class="success-message animate__animated animate__zoomIn">Ride Booked Successfully</h2>
    <a href="dashboard.php" class="btn animate__animated animate__fadeInUp animate__delay-1s">Back to Dashboard</a>
  </div>
  <script>
    // Trigger confetti animation
    confetti({
      particleCount: 100,
      spread: 70,
      origin: { y: 0.6 }
    });

    // Redirect to dashboard after 3 seconds
    setTimeout(() => {
      window.location.href = 'dashboard.php';
    }, 3000);
  </script>
<?php else: ?>
  <div class="error-message animate__animated animate__shakeX">
    <?= htmlspecialchars($error) ?>
    <br><br>
    <a href="dashboard.php" class="animate__animated animate__fadeInUp">Back to Dashboard</a>
  </div>
<?php endif; ?>
</body>
</html>