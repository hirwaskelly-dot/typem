
<?php
session_start();
require_once "config/database.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = $conn->prepare(
        "SELECT id, username, display_name, password_hash
         FROM users
         WHERE username = ?"
    );

    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {

        if (password_verify($password, $user["password_hash"])) {

            session_regenerate_id(true);

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["display_name"] = $user["display_name"];

            header("Location: chart.php");
            exit();

        } else {
            $message = "Incorrect username or password.";
        }

    } else {
        $message = "Incorrect username or password.";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>TYPEM Login</title>

<style>
* {
    box-sizing: border-box;
}

body {
    background: #101827;
    color: white;
    font-family: Arial, sans-serif;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    margin: 0;
    padding: 20px;
}

.login-box {
    background: #1c293c;
    padding: 35px;
    width: 100%;
    max-width: 400px;
    border-radius: 15px;
}

h1 {
    text-align: center;
    color: #60a5fa;
}

p {
    text-align: center;
    color: #b8c4d8;
}

input {
    width: 100%;
    padding: 14px;
    margin: 10px 0;
    border: 1px solid #34445d;
    border-radius: 8px;
    background: #101827;
    color: white;
}

button {
    width: 100%;
    padding: 14px;
    background: #2563eb;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    margin-top: 15px;
    font-weight: bold;
}

.message {
    color: #f87171;
    text-align: center;
}

a {
    color: #60a5fa;
}
</style>
</head>

<body>

<div class="login-box">

    <h1>TYPEM.</h1>

    <p>Welcome back!</p>

    <?php if ($message !== ""): ?>
        <div class="message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <input
            type="text"
            name="username"
            placeholder="Username"
            required
        >

        <input
            type="password"
            name="password"
            placeholder="Password"
            required
        >

        <button type="submit">Login</button>

    </form>

    <p>Don't have an account?
        <a href="register.php">Register</a>
    </p>

</div>

</body>
</html>
