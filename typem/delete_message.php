
<?php

session_start();

require_once "config/database.php";

if (!isset($_SESSION["user_id"])) {
    exit("Please log in first.");
}

$current_id = (int) $_SESSION["user_id"];
$message_id = (int) ($_POST["message_id"] ?? 0);

if ($message_id <= 0) {
    exit("Invalid message.");
}

// Delete only a message sent by the logged-in user
$stmt = $conn->prepare(
    "DELETE FROM messages
     WHERE id = ? AND sender_id = ?"
);

$stmt->bind_param("ii", $message_id, $current_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo "success";
} else {
    echo "Message not found or you cannot delete it.";
}

$stmt->close();
