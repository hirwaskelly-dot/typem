
<?php
session_start();

header("Content-Type: application/json; charset=UTF-8");

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit();
}

require_once "config/database.php";

$current_id = (int) $_SESSION["user_id"];
$friend_id = filter_input(INPUT_GET, "user", FILTER_VALIDATE_INT);
$after_id = filter_input(INPUT_GET, "after", FILTER_VALIDATE_INT);

if (!$friend_id || $friend_id === $current_id || $after_id === false || $after_id === null) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid request"]);
    exit();
}

// Confirm the selected friend exists
$stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
$stmt->bind_param("i", $friend_id);
$stmt->execute();
$friend = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$friend) {
    http_response_code(404);
    echo json_encode(["error" => "User not found"]);
    exit();
}

// Fetch only new messages in this conversation
$stmt = $conn->prepare(
    "SELECT id, sender_id, message, created_at, is_read
     FROM messages
     WHERE id > ?
       AND (
           (sender_id = ? AND receiver_id = ?)
           OR
           (sender_id = ? AND receiver_id = ?)
       )
     ORDER BY id ASC
     LIMIT 100"
);

$stmt->bind_param(
    "iiiii",
    $after_id,
    $current_id,
    $friend_id,
    $friend_id,
    $current_id
);

$stmt->execute();
$result = $stmt->get_result();

$messages = [];

while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

$stmt->close();
// Mark incoming messages as read while conversation is open
$stmt = $conn->prepare(
    "UPDATE messages
     SET is_read = 1
     WHERE sender_id = ?
       AND receiver_id = ?
       AND is_read = 0"
);

$stmt->bind_param("ii", $friend_id, $current_id);
$stmt->execute();
$stmt->close();

echo json_encode([
    "messages" => $messages,
    "read_ids" => $read_ids
]);

// Get read status of messages sent by current user
$stmt = $conn->prepare(
    "SELECT id
     FROM messages
     WHERE sender_id = ?
       AND receiver_id = ?
       AND is_read = 1"
);

$stmt->bind_param("ii", $current_id, $friend_id);
$stmt->execute();

$read_result = $stmt->get_result();
$read_ids = [];

while ($row = $read_result->fetch_assoc()) {
    $read_ids[] = (int)$row["id"];
}

$stmt->close();
