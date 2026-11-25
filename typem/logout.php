
<?php
session_start();

require_once "config/database.php";

if (isset($_SESSION["user_id"])) {
    $user_id = (int) $_SESSION["user_id"];

    $stmt = $conn->prepare(
        "UPDATE users SET is_online = 0 WHERE id = ?"
    );

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

$_SESSION = [];

session_destroy();

header("Location: login.php");
exit();
?>
