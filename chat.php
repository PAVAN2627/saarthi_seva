<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$ride_id = $_GET['ride_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $message = trim($_POST['message']);
    if ($message != '') {
        $stmt = $conn->prepare("INSERT INTO chats (ride_id, sender_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $ride_id, $user_id, $message);
        $stmt->execute();
    }
}

// Fetch chat messages
$stmt = $conn->prepare("SELECT chats.*, users.mobile FROM chats JOIN users ON chats.sender_id = users.id WHERE ride_id=? ORDER BY sent_at ASC");
$stmt->bind_param("i", $ride_id);
$stmt->execute();
$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html>
<head>
<title>Chat - Saarthi Seva</title>
<style>
.chat-box {
  border: 1px solid #ccc;
  height: 300px;
  overflow-y: scroll;
  padding: 10px;
}
.message {
  margin-bottom: 10px;
}
.sender {
  font-weight: bold;
}
</style>
</head>
<body>
<h2>Chat for Ride #<?php echo $ride_id; ?></h2>
<div class="chat-box" id="chatBox">
<?php while ($chat = $result->fetch_assoc()): ?>
  <div class="message">
    <span class="sender"><?php echo htmlspecialchars($chat['mobile']); ?>:</span>
    <span class="text"><?php echo htmlspecialchars($chat['message']); ?></span>
    <br><small><?php echo $chat['sent_at']; ?></small>
  </div>
<?php endwhile; ?>
</div>

<form method="POST" action="">
  <textarea name="message" required placeholder="Type your message here..." rows="3" cols="50"></textarea><br>
  <button type="submit">Send</button>
</form>

<br><a href="ride_details.php?id=<?php echo $ride_id; ?>">Back to Ride Details</a>

<script>
// Scroll chat to bottom on load
var chatBox = document.getElementById('chatBox');
chatBox.scrollTop = chatBox.scrollHeight;
</script>

</body>
</html>
