
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once "config/database.php";

$current_id = (int) $_SESSION["user_id"];

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$upload_message = "";

// Get current user information
$stmt = $conn->prepare(
    "SELECT username, display_name, profile_picture
     FROM users WHERE id = ?"
);

$stmt->bind_param("i", $current_id);
$stmt->execute();
$stmt->bind_result($username, $display_name, $my_picture);
$stmt->fetch();
$stmt->close();

if (!$display_name) {
    $display_name = "TYPEM User";
}


$name_message = "";

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["update_display_name"])
) {
    $token = $_POST["csrf_token"] ?? "";
    $new_name = trim($_POST["display_name"] ?? "");

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
        $name_message = "Invalid request. Refresh the page.";

    } elseif ($new_name === "") {
        $name_message = "Display name cannot be empty.";

    } elseif (strlen($new_name) > 100) {
        $name_message = "Display name is too long.";

    } else {
        $stmt = $conn->prepare(
            "UPDATE users SET display_name = ? WHERE id = ?"
        );

        $stmt->bind_param("si", $new_name, $current_id);

        if ($stmt->execute()) {
            $display_name = $new_name;
            $_SESSION["display_name"] = $new_name;
            $name_message = "Display name updated!";
        } else {
            $name_message = "Could not update display name.";
        }

        $stmt->close();
    }
}


// Handle profile picture upload
if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["update_picture"])
    && isset($_FILES["profile_picture"])
) {
    $token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
        $upload_message = "Invalid request. Refresh the page.";

    } elseif ($_FILES["profile_picture"]["error"] !== UPLOAD_ERR_OK) {
        $upload_message = "Please select a valid picture.";

    } elseif ($_FILES["profile_picture"]["size"] > 2 * 1024 * 1024) {
        $upload_message = "Picture must be smaller than 2 MB.";

    } else {
        $file = $_FILES["profile_picture"];

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file["tmp_name"]);

        $allowed = [
            "image/jpeg" => "jpg",
            "image/png" => "png",
            "image/webp" => "webp"
        ];

        if (!isset($allowed[$mime])) {
            $upload_message = "Only JPG, PNG and WEBP images are allowed.";

        } else {
            $filename = bin2hex(random_bytes(16))
                . "." . $allowed[$mime];

            $upload_dir = __DIR__ . "/assets/uploads/";

            if (!is_dir($upload_dir) || !is_writable($upload_dir)) {
                $upload_message = "Upload folder is not writable.";

            } elseif (move_uploaded_file(
                $file["tmp_name"],
                $upload_dir . $filename
            )) {
                $picture_path = "assets/uploads/" . $filename;

                $stmt = $conn->prepare(
                    "UPDATE users SET profile_picture = ? WHERE id = ?"
                );

                $stmt->bind_param(
                    "si",
                    $picture_path,
                    $current_id
                );

                if ($stmt->execute()) {
                    $my_picture = $picture_path;
                    $upload_message = "Profile picture updated!";
                } else {
                    unlink($upload_dir . $filename);
                    $upload_message = "Could not save your picture.";
                }

                $stmt->close();

            } else {
                $upload_message = "Picture upload failed.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>TYPEM - User Settings</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 30px 15px;
    background: #0b1120;
    color: #ffffff;
    font-family: Arial, sans-serif;
}

.settings-container {
    max-width: 650px;
    margin: auto;
}

.logo {
    font-size: 30px;
    font-weight: bold;
    color: #3984ff;
    margin-bottom: 30px;
}

.settings-card {
    background: #151f35;
    border: 1px solid #263853;
    border-radius: 18px;
    padding: 25px;
    margin-bottom: 20px;
}

.settings-card h2 {
    margin-top: 0;
    margin-bottom: 8px;
}

.subtitle {
    color: #9aacc5;
    font-size: 14px;
    margin-bottom: 25px;
}

.profile-section {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.profile-picture {
    width: 110px;
    height: 110px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #3984ff;
}

.placeholder {
    width: 110px;
    height: 110px;
    border-radius: 50%;
    background: #263d60;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 42px;
    font-weight: bold;
    color: #60a5fa;
}

.profile-info {
    flex: 1;
}

.profile-info strong {
    font-size: 19px;
}

.profile-info small {
    display: block;
    color: #9aacc5;
    margin-top: 7px;
}

input[type="file"] {
    display: block;
    width: 100%;
    margin: 20px 0;
    color: #a9bdd8;
}

button {
    border: none;
    background: #3984ff;
    color: white;
    padding: 12px 20px;
    border-radius: 9px;
    cursor: pointer;
    font-weight: bold;
}

button:hover {
    background: #2563eb;
}

.message {
    margin-top: 18px;
    padding: 12px;
    background: #1e3658;
    border-radius: 8px;
    color: #93c5fd;
}

.account-row {
    padding: 14px 0;
    border-bottom: 1px solid #293851;
}

.account-row:last-child {
    border-bottom: none;
}

.account-row small {
    display: block;
    color: #9aacc5;
    margin-bottom: 6px;
}

.back-link {
    display: inline-block;
    margin-bottom: 20px;
    color: #60a5fa;
    text-decoration: none;
}

.back-link:hover {
    text-decoration: underline;
}
</style>
</head>

<body>

<div class="settings-container">

    <div class="logo">TYPEM.</div>

    <a href="chart.php" class="back-link">
        ← Back to Conversations
    </a>

    <div class="settings-card">

        <h2>User Settings</h2>

        <p class="subtitle">
            Manage your TYPEM profile and account.
        </p>

        <div class="profile-section">

            <?php if (!empty($my_picture)): ?>

                <img
                    src="<?= htmlspecialchars($my_picture, ENT_QUOTES, 'UTF-8') ?>"
                    class="profile-picture"
                    alt="Profile picture"
                >

            <?php else: ?>

                <div class="placeholder">
                    <?= htmlspecialchars(strtoupper(substr($display_name, 0, 1))) ?>
                </div>

            <?php endif; ?>

            <div class="profile-info">
                <strong>
                    <?= htmlspecialchars($display_name) ?>
                </strong>

                <small>
                    @<?= htmlspecialchars($username ?? "") ?>
                </small>

                <small>Your TYPEM account</small>
            </div>

        </div>

        <form method="POST" enctype="multipart/form-data">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>"
            >

            <input
                type="file"
                name="profile_picture"
                accept="image/jpeg,image/png,image/webp"
                required
            >

            <button type="submit" name="update_picture">
                Change Profile Picture
            </button>

        </form>

        <?php if ($upload_message !== ""): ?>
            <div class="message">
                <?= htmlspecialchars($upload_message) ?>
            </div>
        <?php endif; ?>

    </div>

<div class="settings-card">

    <h2>Edit Display Name</h2>

    <p class="subtitle">
        Choose the name your friends see on TYPEM.
    </p>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>"
        >

        <label>Display Name</label>

        <input
            type="text"
            name="display_name"
            value="<?= htmlspecialchars($display_name) ?>"
            maxlength="100"
            required
            style="width:100%; padding:13px; margin:12px 0 18px;
                   border-radius:9px; border:1px solid #34445e;
                   background:#0b1120; color:white;"
        >

        <button type="submit" name="update_display_name">
            Save Changes
        </button>

    </form>

    <?php if ($name_message !== ""): ?>
        <div class="message">
            <?= htmlspecialchars($name_message) ?>
        </div>
    <?php endif; ?>

</div>

    <div class="settings-card">

        <h2>Account Information</h2>

        <div class="account-row">
            <small>Display Name</small>
            <?= htmlspecialchars($display_name) ?>
        </div>

        <div class="account-row">
            <small>Username</small>
            @<?= htmlspecialchars($username ?? "") ?>
        </div>

    </div>

</div>

</body>
</html>
