
<?php
require_once "config/database.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $display_name = trim($_POST["display_name"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $display_name === "" || $password === "") {
        $message = "Please fill in all fields.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
        $message = "Username must be 3-50 characters and use only letters, numbers or underscores.";
    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters.";
    } else {

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            "INSERT INTO users (username, display_name, password_hash)
             VALUES (?, ?, ?)"
        );

        $stmt->bind_param(
            "sss",
            $username,
            $display_name,
            $password_hash
        );

        if ($stmt->execute()) {
            $message = "Account created successfully! You can now log in.";
        } else {
            if ($stmt->errno === 1062) {
                $message = "That username is already taken.";
            } else {
                $message = "Something went wrong. Please try again.";
            }
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join TYPEM</title>

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

        .register-box {
            background: #1c293c;
            padding: 35px;
            width: 100%;
            max-width: 420px;
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
            font-weight: bold;
            cursor: pointer;
            margin-top: 15px;
        }

        .message {
            text-align: center;
            color: #60a5fa;
            margin: 15px 0;
        }

        a {
            color: #60a5fa;
        }
    </style>
</head>

<body>

<div class="register-box">

    <h1>TYPEM.</h1>

    <p>Create your account</p>

    <?php if ($message !== ""): ?>
        <div class="message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <input
            type="text"
            name="display_name"
            placeholder="Your name"
            maxlength="100"
            required
        >

        <input
            type="text"
            name="username"
            placeholder="Choose a username"
            maxlength="50"
            required
        >

        <input
            type="password"
            name="password"
            placeholder="Create a password (8+ characters)"
            minlength="8"
            required
        >

        <button type="submit">Create Account</button>

    </form>

    <p>Already have an account? <a href="login.php">Login</a></p>

</div>

</body>
</html>
