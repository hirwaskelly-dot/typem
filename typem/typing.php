
<?php

session_start();

require_once "config/database.php";

header("Content-Type: application/json");

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["success" => false]);
    exit;
}

$current_id = (int) $_SESSION["user_id"];

$friend_id = (int) ($_POST["friend_id"] ?? 0);
$action = $_POST["action"] ?? "";

if ($friend_id <= 0 || $friend_id === $current_id) {
    echo json_encode(["success" => false]);
    exit;
}

if ($action === "typing") {

    $stmt = $conn->prepare(
        "INSERT INTO typing_status (sender_id, receiver_id)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP"
    );

    $stmt->bind_param("ii", $current_id, $friend_id);
    $stmt->execute();
    $stmt->close();

} elseif ($action === "stop") {

    $stmt = $conn->prepare(
        "DELETE FROM typing_status
         WHERE sender_id = ? AND receiver_id = ?"
    );

    $stmt->bind_param("ii", $current_id, $friend_id);
    $stmt->execute();
    $stmt->close();

} else {
    echo json_encode(["success" => false]);
    exit;
}

echo json_encode(["success" => true]);
