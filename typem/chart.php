
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once "config/database.php";


$current_user = htmlspecialchars(
    $_SESSION["display_name"],
    ENT_QUOTES,
    "UTF-8"
);

$current_id = (int) $_SESSION["user_id"];

$stmt = $conn->prepare(
    "UPDATE users SET is_online = 1 WHERE id = ?"
);
$stmt->bind_param("i", $current_id);
$stmt->execute();
$stmt->close();

// Profile picture upload security
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$upload_message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_FILES["profile_picture"])) {

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
// Load current user's profile picture
$stmt = $conn->prepare(
    "SELECT profile_picture FROM users WHERE id = ?"
);

$stmt->bind_param("i", $current_id);
$stmt->execute();

$my_picture = $stmt->get_result()->fetch_assoc()["profile_picture"];

$stmt->close();

// Get recent conversations
$recent_stmt = $conn->prepare("
    SELECT
        u.id,
        u.username,
        u.display_name,
        u.profile_picture,
        u.is_online,

        (
            SELECT COUNT(*)
            FROM messages AS unread
            WHERE unread.sender_id = u.id
              AND unread.receiver_id = ?
              AND unread.is_read = 0
        ) AS unread_count,

        (
            SELECT m2.message
            FROM messages AS m2
            WHERE
                (m2.sender_id = ? AND m2.receiver_id = u.id)
                OR
                (m2.sender_id = u.id AND m2.receiver_id = ?)
            ORDER BY m2.created_at DESC, m2.id DESC
            LIMIT 1
        ) AS last_message,

        MAX(m.created_at) AS last_message_time

    FROM messages m

    INNER JOIN users u
        ON u.id = CASE
            WHEN m.sender_id = ? THEN m.receiver_id
            ELSE m.sender_id
        END

    WHERE m.sender_id = ? OR m.receiver_id = ?

    GROUP BY
        u.id,
        u.username,
        u.display_name,
        u.profile_picture,
        u.is_online

    ORDER BY last_message_time DESC
    LIMIT 20
");

$recent_stmt->bind_param(
    "iiiiii",
    $current_id,
    $current_id,
    $current_id,
    $current_id,
    $current_id,
    $current_id
);

$recent_stmt->execute();
$recent_conversations = $recent_stmt->get_result();



$stmt = $conn->prepare(
    "SELECT id, username, display_name, profile_picture,is_online
     FROM users
     WHERE id != ?
     ORDER BY display_name ASC
     LIMIT 50"
);

$stmt->bind_param("i", $current_id);
$stmt->execute();

$people = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TYPEM - Charts</title>

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, sans-serif;
    background: #101827;
    color: white;
}

.app {
    display: flex;
    min-height: 100vh;
}

.sidebar {
    width: 320px;
    background: #172337;
    padding: 25px;
    border-right: 1px solid #293950;
}

.logo {
    color: #60a5fa;
    font-size: 30px;
    font-weight: bold;
    margin-bottom: 35px;
}

.profile {
    background: #24344d;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 30px;
}

.profile small {
    color: #9aabc3;
}

.people-title {
    margin-bottom: 18px;
    color: #a9bad1;
}

.person {
    display: block;
    padding: 13px;
    margin-bottom: 10px;
    border-radius: 9px;
    background: #213149;
    color: white;
    text-decoration: none;
}

.person:hover {
    background: #2c4567;
}

.person small {
    display: block;
    color: #a9bad1;
    margin-top: 5px;
}

.main {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    text-align: center;
    padding: 30px;
}

.welcome h1 {
    font-size: 35px;
    margin-bottom: 15px;
}

.welcome h1 span {
    color: #60a5fa;
}

.welcome p {
    color: #a9bad1;
    line-height: 1.7;
}

.logout {
    display: inline-block;
    margin-top: 25px;
    padding: 12px 22px;
    border-radius: 8px;
    background: #2563eb;
    color: white;
    text-decoration: none;
}

.empty {
    color: #9aabc3;
    line-height: 1.6;
}

@media(max-width: 650px) {
    .app {
        flex-direction: column;
    }

    .sidebar {
        width: 100%;
        border-right: none;
        border-bottom: 1px solid #293950;
    }

    .main {
        min-height: 350px;
    }
}
.avatar {
    width: 65px;
    height: 65px;
    border-radius: 50%;
    object-fit: cover;
    display: block;
    margin-bottom: 12px;
}

.placeholder {
    background: #2563eb;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 25px;
}
.online {
    color: #22c55e !important;
    font-weight: bold;
}

.offline {
    color: #9ca3af !important;
    font-weight: bold;
}
.friend-search {
    padding: 15px;
}

.friend-search input {
    width: 100%;
    padding: 13px;
    border: 1px solid #263650;
    border-radius: 10px;
    background: #111d30;
    color: white;
    outline: none;
    box-sizing: border-box;
}

.friend-search input:focus {
    border-color: #2563eb;
}

.friend-search input::placeholder {
    color: #94a3b8;
}
.last-message {
    display: block;
    color: #94a3b8;
    font-size: 12px;
    margin-top: 5px;
    max-width: 180px;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}
.message-time {
    display: block;
    color: #64748b;
    font-size: 11px;
    margin-top: 4px;
}
.unread-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 21px;
    height: 21px;
    padding: 0 6px;
    border-radius: 50px;
    background: #2563eb;
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.35);
    margin-left: auto;
}
.person {
    display: flex;
    align-items: center;
    gap: 10px;
}

.person .unread-badge {
    flex-shrink: 0;
}
.person-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
    min-width: 0;
}

.conversation-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
    margin-left: auto;
}

.last-message {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 160px;
}
.settings-link {
    display: block;
    padding: 13px 16px;
    margin: 15px 0;
    background: #1e2d46;
    color: #ffffff;
    text-decoration: none;
    border-radius: 10px;
    transition: 0.2s;
}

.settings-link:hover {
    background: #263f63;
    color: #60a5fa;
}
</style>
</head>

<body>

<div class="app">

    <aside class="sidebar">

        <div class="logo">TYPEM.</div>

<div class="profile">

   <a href="settings.php" class="profile profile-link">

    <?php if (!empty($my_picture)): ?>

        <img
            src="<?= htmlspecialchars($my_picture) ?>"
            class="avatar"
            alt="Profile picture"
        >

    <?php else: ?>

        <div class="avatar placeholder">T</div>

    <?php endif; ?>

    <strong><?= htmlspecialchars($current_user) ?></strong>

    <br>
    <small>⚙️ User Settings</small>

</a>

    

</div>

<h3 class="people-title">Recent Conversations</h3>
<?php if ($recent_conversations->num_rows > 0): ?>

    <?php while ($recent = $recent_conversations->fetch_assoc()): ?>
<?php
echo "<!-- Friend: " . htmlspecialchars($recent["display_name"] ?? "MISSING") . " -->";
?>
       <a href="conversation.php?user=<?= (int)$recent["id"] ?>"
   class="person">

    <?php if (!empty($recent["profile_picture"])): ?>
        <img
            src="<?= htmlspecialchars($recent["profile_picture"]) ?>"
            class="avatar"
            alt="Profile picture"
        >
    <?php else: ?>
        <div class="avatar placeholder">T</div>
    <?php endif; ?>

    <div class="person-info">

        <strong>
            <?= htmlspecialchars($recent["display_name"] ?? $recent["username"]) ?>
        </strong>

        <small class="last-message">
            <?= htmlspecialchars($recent["last_message"] ?? "No messages yet") ?>
        </small>

        <small class="<?= !empty($recent["is_online"]) ? 'online' : 'offline' ?>">
            <?= !empty($recent["is_online"]) ? 'Online' : 'Offline' ?>
        </small>

    </div>

    <div class="conversation-meta">

        <small class="message-time">
            <?php
            if (!empty($recent["last_message_time"])) {
                $message_time = strtotime($recent["last_message_time"]);

                if (date("Y-m-d", $message_time) === date("Y-m-d")) {
                    echo date("H:i", $message_time);
                } elseif (date("Y-m-d", $message_time) === date("Y-m-d", strtotime("yesterday"))) {
                    echo "Yesterday";
                } else {
                    echo date("d/m/Y", $message_time);
                }
            }
            ?>
        </small>

        <?php if ((int)$recent["unread_count"] > 0): ?>
            <span class="unread-badge">
                <?= (int)$recent["unread_count"] ?>
            </span>
        <?php endif; ?>

    </div>

</a>

    <?php endwhile; ?>

<?php else: ?>

    <p class="empty">No recent conversations yet.</p>

<?php endif; ?>

<?php $recent_stmt->close(); ?>

        <h3 class="people-title">People</h3>
        <div class="friend-search">
    <input
        type="text"
        id="friendSearch"
        placeholder="Search friends..."
    >
</div>

        <?php if ($people->num_rows > 0): ?>

            <?php while ($person = $people->fetch_assoc()): ?>

  <a href="conversation.php?user=<?= (int)$person["id"] ?>" class="person">

    <?php if (!empty($person["profile_picture"])): ?>
        <img
            src="<?= htmlspecialchars($person["profile_picture"]) ?>"
            class="avatar"
            alt="Friend's picture"
        >
    <?php else: ?>
        <div class="avatar placeholder">T</div>
    <?php endif; ?>

    <strong>
        <?= htmlspecialchars($person["display_name"] ?: $person["username"]) ?>
    </strong>

    <small class="<?= $person["is_online"] ? 'online' : 'offline' ?>">
        <?= $person["is_online"] ? 'Online' : 'Offline' ?>
    </small>

    <small>
        @<?= htmlspecialchars($person["username"]) ?>
    </small>

</a>

            <?php endwhile; ?>

        <?php else: ?>

            <p class="empty">
                No other users yet.
                Invite your friends to join TYPEM!
            </p>

        <?php endif; ?>

        <?php $stmt->close(); ?>

    </aside>

    <main class="main">

        <div class="welcome">

            <h1>Welcome to <span>TYPEM</span>!</h1>

            <p>
                Your people. Your conversations.<br>
                Your new place to connect.
            </p>

            <p>
                Select a person from the list to get started.
                Messaging is coming next!
            </p>

            <a href="logout.php" class="logout">
                Logout
            </a>

        </div>

    </main>

</div>
<script>
const friendSearch = document.getElementById("friendSearch");

friendSearch.addEventListener("input", function () {
    const search = this.value.toLowerCase();

    document.querySelectorAll(".person").forEach(function (person) {
        const name = person.textContent.toLowerCase();

        person.style.display = name.includes(search)
            ? "flex"
            : "none";
    });
});
</script>
</body>
</html>
