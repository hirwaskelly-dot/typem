
<?php

session_start();

require_once "config/database.php";

header("Content-Type: application/json");

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "message" => "Please log in."]);
    exit;
}

$current_id = (int) $_SESSION["user_id"];
$message_id = (int) ($_POST["message_id"] ?? 0);
$new_message = trim($_POST["message"] ?? "");

if ($message_id <= 0 || $new_message === "") {
    echo json_encode(["success" => false, "message" => "Invalid message."]);
    exit;
}

$stmt = $conn->prepare(
    "UPDATE messages
     SET message = ?
     WHERE id = ? AND sender_id = ?"
);

$stmt->bind_param("sii", $new_message, $message_id, $current_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Message not found or unchanged."
    ]);
}

$stmt->close();
