<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once "config/database.php";

$current_id = (int) $_SESSION["user_id"];
$friend_id = filter_input(INPUT_GET, "user", FILTER_VALIDATE_INT);

if (!$friend_id || $friend_id === $current_id) {
    exit("Invalid conversation.");
}

// Small helper for safe HTML output.
function h($value): string {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

// Load the friend. These fields are also used by the conversation header.
$stmt = $conn->prepare(
    "SELECT id, username, display_name, profile_picture, is_online
     FROM users
     WHERE id = ?
     LIMIT 1"
);
$stmt->bind_param("i", $friend_id);
$stmt->execute();
$friend = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$friend) {
    exit("User not found.");
}

$friend_name = !empty($friend["display_name"])
    ? $friend["display_name"]
    : $friend["username"];

// Load people for the sidebar.
$people_stmt = $conn->prepare(
    "SELECT id, username, display_name, profile_picture, is_online
     FROM users
     WHERE id != ?
     ORDER BY display_name ASC
     LIMIT 50"
);
$people_stmt->bind_param("i", $current_id);
$people_stmt->execute();
$people = $people_stmt->get_result();

// Mark incoming messages in this conversation as read.
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

// Create a CSRF token for the message form.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$error = "";

// Send a new message or a reply.

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $message = trim($_POST["message"] ?? "");
    $token = $_POST["csrf_token"] ?? "";

    $file = $_FILES["attachment"] ?? null;

    $hasFile = $file && $file["error"] !== UPLOAD_ERR_NO_FILE;

    if (!hash_equals($_SESSION["csrf_token"], $token)) {

        $error = "Invalid request. Please refresh the page.";

    } elseif ($message === "" && !$hasFile) {

        $error = "Please enter a message or select an attachment.";

    } elseif (strlen($message) > 5000) {

        $error = "Message is too long.";

    } else {

        $reply_to = !empty($_POST["reply_to"])
            ? (int)$_POST["reply_to"]
            : null;

        $attachmentPath = null;
        $attachmentName = null;
        $attachmentType = null;
        $attachmentSize = null;

        $uploadDirectory = __DIR__ . "/uploads/messages/";

        $allowedTypes = [
            "image/jpeg" => "jpg",
            "image/png" => "png",
            "image/gif" => "gif",
            "image/webp" => "webp",

            "application/pdf" => "pdf",

            "application/msword" => "doc",
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document" => "docx",

            "application/vnd.ms-excel" => "xls",
            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" => "xlsx",

            "application/vnd.ms-powerpoint" => "ppt",
            "application/vnd.openxmlformats-officedocument.presentationml.presentation" => "pptx",

            "text/plain" => "txt",
            "text/csv" => "csv",

            "application/zip" => "zip",
            "application/x-zip-compressed" => "zip",

            "audio/mpeg" => "mp3",
            "audio/wav" => "wav",
            "audio/ogg" => "ogg",

            "video/mp4" => "mp4",
            "video/webm" => "webm"
        ];

        $uploadValid = true;

        if ($hasFile) {

            if ($file["error"] !== UPLOAD_ERR_OK) {

                $error = "File upload failed. Please try again.";
                $uploadValid = false;

            } elseif ($file["size"] > 10 * 1024 * 1024) {

                $error = "File must not exceed 10 MB.";
                $uploadValid = false;

            } else {

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($file["tmp_name"]);

                if (!isset($allowedTypes[$mimeType])) {

                    $error = "This file type is not supported.";
                    $uploadValid = false;

                } else {

                    $extension = $allowedTypes[$mimeType];

                    $newFileName = bin2hex(random_bytes(16))
                        . "." . $extension;

                    $attachmentPath = "uploads/messages/" . $newFileName;

                    $attachmentName = basename($file["name"]);
                    $attachmentType = $mimeType;
                    $attachmentSize = $file["size"];

                    if (!move_uploaded_file(
                        $file["tmp_name"],
                        $uploadDirectory . $newFileName
                    )) {

                        $error = "Could not save the attachment.";
                        $uploadValid = false;
                    }
                }
            }
        }

        if ($uploadValid) {

            $stmt = $conn->prepare(
                "INSERT INTO messages
                (sender_id, receiver_id, message, reply_to,
                 attachment_path, attachment_name,
                 attachment_type, attachment_size)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "iisisssi",
                $current_id,
                $friend_id,
                $message,
                $reply_to,
                $attachmentPath,
                $attachmentName,
                $attachmentType,
                $attachmentSize
            );

            if ($stmt->execute()) {

                $stmt->close();

                header("Location: conversation.php?user=" . $friend_id);
                exit();

            } else {

                if ($attachmentPath !== null) {
                    @unlink(__DIR__ . "/" . $attachmentPath);
                }

                $error = "Could not send message.";
                $stmt->close();
            }
        }
    }
}

// Load the latest 100 messages, including the text and sender of any quoted message.

$stmt = $conn->prepare(
    "SELECT
        m.id,
        m.sender_id,
        m.message,
        m.reply_to,
        m.created_at,
        m.is_read,

        m.attachment_path,
        m.attachment_name,
        m.attachment_type,
        m.attachment_size,

        original.message AS reply_message,
        original.sender_id AS reply_sender_id

     FROM messages m

     LEFT JOIN messages original
        ON m.reply_to = original.id

     WHERE
        (m.sender_id = ? AND m.receiver_id = ?)
        OR
        (m.sender_id = ? AND m.receiver_id = ?)

     ORDER BY m.id DESC
     LIMIT 100"
);

$stmt->bind_param(
    "iiii",
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

$messages = array_reverse($messages);
$last_message_id = !empty($messages)
    ? (int) end($messages)["id"]
    : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TYPEM Conversation</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #101827;
    color: white;
    font-family: Arial, sans-serif;
}

/* Main layout */
.chat-layout {
    display: flex;
    width: 100%;
    max-width: 1500px;
    height: 100vh;
    margin: auto;
    background: #111827;
    overflow: hidden;
}

/* Sidebar */
.chat-sidebar {
    width: 320px;
    min-width: 280px;
    padding: 22px 15px;
    background: #172337;
    border-right: 1px solid #2b3a50;
    display: flex;
    flex-direction: column;
}

.sidebar-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.sidebar-top h2 {
    color: #3b82f6;
    font-size: 25px;
    margin: 0;
}

.sidebar-top a {
    color: #93c5fd;
    text-decoration: none;
    font-size: 13px;
}

.chat-sidebar > input {
    width: 100%;
    height: 46px;
    flex: 0 0 46px;
    padding: 0 14px;
    margin-bottom: 18px;
    border: 1px solid #334155;
    border-radius: 10px;
    background: #263449;
    color: white;
    outline: none;
}

.chat-sidebar h3 {
    color: #94a3b8;
    font-size: 13px;
    letter-spacing: 1px;
}

.friends-list {
    flex: 1;
    overflow-y: auto;
}

.friend-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    margin-bottom: 7px;
    border-radius: 10px;
    text-decoration: none;
    color: white;
}

.friend-item:hover {
    background: #263449;
}

.friend-item.active {
    background: #263f69;
    border-left: 3px solid #3b82f6;
}

.friend-item .avatar {
    width: 45px;
    height: 45px;
    min-width: 45px;
    border-radius: 50%;
    object-fit: cover;
}

.friend-item .placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #2563eb;
    font-weight: bold;
}

.friend-details {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.friend-details .online {
    color: #22c55e;
}

.friend-details .offline {
    color: #94a3b8;
}

/* Chat area */
.chat-main {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    background: #0f172a;
}

.chat-main .header {
    padding: 18px 25px;
    background: #172337;
    border-bottom: 1px solid #2b3a50;
}

.header-profile {
    display: flex;
    align-items: center;
    gap: 13px;
}

.header-avatar {
    width: 48px;
    height: 48px;
    min-width: 48px;
    border-radius: 50%;
    object-fit: cover;
}

.header-avatar.placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #2563eb;
    color: white;
    font-size: 20px;
    font-weight: bold;
}

.header-info h2 {
    margin: 0 0 4px;
    font-size: 19px;
}

.header-info small {
    color: #94a3b8;
}

.friend-status {
    margin-top: 5px;
    font-size: 11px;
    color: #22c55e;
}

/* Messages */
.chat-main .messages {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.message {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 6px;
    width: fit-content;
    max-width: 70%;
    min-width: 90px;
    padding: 27px 15px 10px;
    border-radius: 15px;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.message.sent {
    align-self: flex-end;
    background: #2563eb;
    border-bottom-right-radius: 4px;
}

.message.received {
    align-self: flex-start;
    background: #263449;
    border-bottom-left-radius: 4px;
}

.message-text {
    color: white;
    font-size: 14px;
    white-space: normal;
}

.message-meta {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 7px;
    min-height: 14px;
}

.message-meta small {
    font-size: 10px;
    color: #cbd5e1;
}

.seen-status {
    color: #bfdbfe;
    font-size: 10px;
    font-weight: 600;
}

.edited-label {
    color: #dbeafe;
    font-size: 10px;
}

/* Reply quote inside a message */
.reply-quote {
    background: rgba(0, 0, 0, 0.18);
    border-left: 3px solid #93c5fd;
    border-radius: 6px;
    padding: 8px 10px;
    margin-bottom: 5px;
}

.reply-quote strong {
    color: #bfdbfe;
    font-size: 11px;
}

.reply-quote p {
    color: #e2e8f0;
    font-size: 12px;
    margin: 4px 0 0;
    overflow-wrap: anywhere;
}

/* Three-dot message menu */
.message-menu-wrap {
    position: absolute;
    top: 5px;
    right: 6px;
    z-index: 5;
}

.message-menu-toggle {
    width: 27px;
    height: 25px;
    padding: 0;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: #e2e8f0;
    font-size: 20px;
    line-height: 20px;
    cursor: pointer;
}

.message-menu-toggle:hover {
    background: rgba(255, 255, 255, 0.15);
}

.message-menu {
    display: none;
    position: absolute;
    top: 29px;
    right: 0;
    min-width: 125px;
    padding: 5px;
    flex-direction: column;
    gap: 3px;
    background: #172337;
    border: 1px solid #334155;
    border-radius: 9px;
    box-shadow: 0 8px 22px rgba(0, 0, 0, 0.35);
    z-index: 20;
}

.message-menu.open {
    display: flex;
}

.message-menu button {
    width: 100%;
    padding: 9px 10px;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: #f8fafc;
    text-align: left;
    font-size: 13px;
    cursor: pointer;
}

.message-menu button:hover {
    background: #263449;
}

.message-menu .delete-message {
    color: #fca5a5;
}

/* Empty conversation */
.empty {
    color: #94a3b8;
    text-align: center;
    margin: auto;
}

/* Typing indicator */
.typing-indicator {
    min-height: 22px;
    padding: 3px 20px;
    color: #22c55e;
    font-size: 12px;
    font-style: italic;
}

/* Composer and reply preview */
.form-area {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 12px 15px 15px;
    background: #172337;
    border-top: 1px solid #2b3a50;
}

.reply-preview {
    display: none;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 9px 12px;
    background: #263449;
    border-left: 3px solid #60a5fa;
    border-radius: 8px;
}

.reply-preview strong {
    color: #93c5fd;
    font-size: 12px;
}

.reply-preview p {
    margin: 4px 0 0;
    color: #e2e8f0;
    font-size: 13px;
    overflow-wrap: anywhere;
}

.reply-preview button {
    border: 0;
    background: transparent;
    color: white;
    font-size: 18px;
    cursor: pointer;
}

.composer-row {
    display: flex;
    gap: 12px;
}

.composer-row input {
    flex: 1;
    min-width: 0;
    padding: 14px;
    border: 1px solid #334155;
    border-radius: 12px;
    background: #263449;
    color: white;
    outline: none;
}

.composer-row button {
    padding: 0 22px;
    border: none;
    border-radius: 10px;
    background: #2563eb;
    color: white;
    font-weight: bold;
    cursor: pointer;
}

.error {
    color: #fca5a5;
    padding: 10px 20px;
}

/* Mobile */
@media (max-width: 700px) {
    .chat-layout {
        flex-direction: column;
    }

    .chat-sidebar {
        width: 100%;
        min-width: 0;
        max-height: 190px;
        padding: 12px;
    }

    .friends-list {
        display: flex;
        gap: 8px;
        overflow-x: auto;
    }

    .friend-item {
        min-width: 130px;
    }

    .chat-main {
        flex: 1;
        min-height: 0;
    }

    .chat-main .message {
        max-width: 88%;
    }

    .chat-main .header {
        padding: 12px 15px;
    }
}

/* =================================
   TYPEM — PREMIUM DARK THEME
================================= */

:root {
    --bg-main: #0b1120;
    --bg-sidebar: #111a2e;
    --bg-panel: #151f35;
    --bg-hover: #1e2b45;
    --blue: #3984ff;
    --blue-hover: #2671ed;
    --text-main: #f1f5f9;
    --text-muted: #94a3b8;
    --border: #26344d;
}

/* Main page */

body {
    background: var(--bg-main);
    color: var(--text-main);
    font-family: "Segoe UI", Arial, sans-serif;
}

/* Application layout */

.chat-layout {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
}

/* Sidebar */

.chat-sidebar {
    background: var(--bg-sidebar);
    border-right: 1px solid var(--border);
    color: var(--text-main);
}

.chat-sidebar input {
    background: var(--bg-panel);
    color: var(--text-main);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 11px 13px;
    outline: none;
}

.chat-sidebar input:focus {
    border-color: var(--blue);
}

.friend-item {
    color: var(--text-main);
    border-radius: 10px;
    transition: background .2s ease;
}

.friend-item:hover {
    background: var(--bg-hover);
}

/* Chat area */

.chat-main {
    background: var(--bg-main);
    color: var(--text-main);
}

/* Chat header */

.chat-main > header {
    background: var(--bg-sidebar);
    border-bottom: 1px solid var(--border);
}

/* Messages area */

.messages {
    background: var(--bg-main);
    padding: 22px;
}

/* Message bubbles */

.message {
    border-radius: 16px;
    padding: 11px 15px;
    max-width: 70%;
    box-shadow: 0 3px 10px rgba(0,0,0,.15);
}

.message.sent {
    background: #1d4ed8;
    color: white;
    border-bottom-right-radius: 5px;
}

.message.received {
    background: #1e293b;
    color: var(--text-main);
    border: 1px solid var(--border);
    border-bottom-left-radius: 5px;
}

.message-text {
    color: inherit;
    line-height: 1.6;
}

.message-meta small {
    color: #cbd5e1;
}

/* Reply preview */

.reply-quote {
    background: rgba(0,0,0,.18);
    border-left: 3px solid var(--blue);
    border-radius: 7px;
    padding: 8px 10px;
}

/* Message composer */

.form-area {
    background: var(--bg-sidebar);
    border-top: 1px solid var(--border);
    padding: 15px;
}

.form-area input[name="message"],
.form-area textarea[name="message"] {
    background: var(--bg-panel);
    color: var(--text-main);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 13px;
    outline: none;
}

.form-area input[name="message"]:focus,
.form-area textarea[name="message"]:focus {
    border-color: var(--blue);
}

.form-area button[type="submit"] {
    background: var(--blue);
    color: white;
    border: none;
    border-radius: 12px;
    padding: 12px 22px;
    font-weight: 600;
    cursor: pointer;
}

.form-area button[type="submit"]:hover {
    background: var(--blue-hover);
}

/* Typing indicator */

.typing-indicator {
    color: #60a5fa;
}

/* Scrollbar */

.messages::-webkit-scrollbar {
    width: 6px;
}

.messages::-webkit-scrollbar-thumb {
    background: #334155;
    border-radius: 10px;
}

/* =================================
   TYPEM — PREMIUM SIDEBAR
================================= */

.chat-sidebar {
    background: #0f172a;
    border-right: 1px solid #26344d;
    padding: 20px 14px;
}

/* Sidebar logo */

.chat-sidebar .logo {
    color: #ffffff;
    font-size: 25px;
    font-weight: 800;
    letter-spacing: 2px;
    padding: 8px 12px 22px;
}

.chat-sidebar .logo span {
    color: #3984ff;
}

/* Profile section */

.chat-sidebar .profile {
    background: #17243a;
    border: 1px solid #26344d;
    border-radius: 14px;
    padding: 13px;
    margin-bottom: 22px;
}

.chat-sidebar .profile h3,
.chat-sidebar .profile strong {
    color: #f8fafc;
}

.chat-sidebar .profile p {
    color: #94a3b8;
}

/* Friend search */

.chat-sidebar input {
    width: 100%;
    box-sizing: border-box;
    background: #17243a;
    color: #ffffff;
    border: 1px solid #26344d;
    border-radius: 12px;
    padding: 12px 14px;
    margin-bottom: 18px;
}

.chat-sidebar input::placeholder {
    color: #8493aa;
}

/* Friends heading */

.chat-sidebar h3 {
    color: #94a3b8;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    padding: 0 10px;
    margin-bottom: 12px;
}

/* Friend cards */

.friend-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 12px;
    margin-bottom: 5px;
    border-radius: 12px;
    transition: all .2s ease;
    border: 1px solid transparent;
}

.friend-item:hover {
    background: #1a2942;
    transform: translateX(3px);
}

.friend-item.active {
    background: #172f55;
    border-color: #28569a;
}

.friend-item img,
.friend-item .avatar {
    width: 43px;
    height: 43px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
}

.friend-item .friend-name {
    color: #f1f5f9;
    font-size: 14px;
    font-weight: 600;
}

.friend-item .friend-status {
    color: #94a3b8;
    font-size: 11px;
}

/* Online indicator */

.friend-item .online-dot {
    background: #22c55e;
    width: 9px;
    height: 9px;
    border-radius: 50%;
}

/* Unread badges */

.friend-item .unread-count {
    background: #3984ff;
    color: white;
    border-radius: 20px;
    padding: 3px 7px;
    font-size: 11px;
    font-weight: 700;
}

.send-icon-btn {
    width: 58px;
    height: 58px;
    padding: 5px;
    border: 1px solid #168bff;
    border-radius: 17px;
    background: #07152e;
    cursor: pointer;
    box-shadow: 0 0 15px rgba(0, 110, 255, .35);
    transition: .25s ease;
}

.send-icon-btn img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.send-icon-btn:hover {
    transform: scale(1.08);
    box-shadow: 0 0 25px #087cff;
}

/* =================================
   TYPEM — SIGNATURE COMPOSER
================================= */

.form-area {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 17px 20px;
    background: #0c1425;
    border-top: 1px solid #263c5c;
}

/* Message input */

.form-area input[name="message"],
.form-area textarea[name="message"] {
    flex: 1;
    min-width: 0;
    height: 54px;
    box-sizing: border-box;
    padding: 0 20px;
    border: 1px solid #293c5b;
    border-radius: 16px;
    background: linear-gradient(145deg, #111d32, #0d1729);
    color: #ffffff;
    font-size: 15px;
    transition: border .25s, box-shadow .25s;
}

/* Focus effect */

.form-area:focus-within input[name="message"],
.form-area:focus-within textarea[name="message"] {
    border-color: #3285ff;
    box-shadow: 0 0 0 3px rgba(50,133,255,.10),
                0 0 18px rgba(50,133,255,.12);
}

.form-area input[name="message"]::placeholder,
.form-area textarea[name="message"]::placeholder {
    color: #8292ac;
}

/* Skull button alignment */

.send-icon-btn {
    flex-shrink: 0;
    width: 58px;
    height: 58px;
    border-radius: 17px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.send-icon-btn img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

/* Smooth composer animation */

.form-area input[name="message"]:focus {
    outline: none;
}

.attach-btn {
    width: 48px;
    height: 48px;
    flex-shrink: 0;
    border: 1px solid #293c5b;
    border-radius: 15px;
    background: #17243a;
    color: #60a5fa;
    font-size: 30px;
    font-weight: 300;
    cursor: pointer;
    transition: .2s ease;
}

.attach-btn:hover {
    background: #203b60;
    border-color: #3984ff;
    transform: scale(1.05);
}
.form-area {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    flex-wrap: nowrap !important;
    gap: 10px;
}

.attach-btn {
    flex: 0 0 48px;
}

.form-area input[name="message"],
.form-area textarea[name="message"] {
    flex: 1 1 auto;
    min-width: 0;
}

.send-icon-btn {
    flex: 0 0 58px;
}

.attachment-wrapper {
    position: relative;
}

.attachment-menu {
    position: absolute;
    bottom: 65px;
    left: 0;
    width: 180px;
    background: #17243a;
    border: 1px solid #3984ff;
    border-radius: 12px;
    padding: 8px;
    z-index: 999;
}

.attachment-menu[hidden] {
    display: none !important;
}

.attachment-menu button {
    display: block;
    width: 100%;
    padding: 12px;
    background: transparent;
    color: white;
    border: none;
    text-align: left;
    cursor: pointer;
    border-radius: 8px;
}

.attachment-menu button:hover {
    background: #263d60;
}

.attachment-link {
    display: inline-block;
    margin-top: 8px;
    padding: 10px 14px;
    background: #1e3454;
    color: #8dbaff;
    border: 1px solid #3984ff;
    border-radius: 10px;
    text-decoration: none;
    overflow-wrap: anywhere;
}

.message img {
    object-fit: cover;
}

</style>
</head>

<body>
<div class="chat-layout">

    <!-- LEFT SIDEBAR -->
    <aside class="chat-sidebar">

        <div class="sidebar-top">
            <h2>TYPEM.</h2>
            <a href="chart.php">🔙🔙🔙</a>
        </div>

        <input
            type="text"
            id="friendSearch"
            placeholder="Search friends..."
            autocomplete="off"
        >

        <h3>Friends</h3>

        <div class="friends-list">
            <?php while ($person = $people->fetch_assoc()): ?>
                <?php
                $person_name = !empty($person["display_name"])
                    ? $person["display_name"]
                    : $person["username"];
                ?>
                <a
                    href="conversation.php?user=<?= (int)$person["id"] ?>"
                    class="friend-item <?= ((int)$person["id"] === $friend_id) ? 'active' : '' ?>"
                >
                    <?php if (!empty($person["profile_picture"])): ?>
                        <img
                            src="<?= h($person["profile_picture"]) ?>"
                            class="avatar"
                            alt="Profile picture"
                        >
                    <?php else: ?>
                        <div class="avatar placeholder">
                            <?= h(strtoupper(substr($person_name, 0, 1))) ?>
                        </div>
                    <?php endif; ?>

                    <div class="friend-details">
                        <strong><?= h($person_name) ?></strong>
                        <small class="<?= !empty($person["is_online"]) ? 'online' : 'offline' ?>">
                            <?= !empty($person["is_online"]) ? 'Online' : 'Offline' ?>
                        </small>
                    </div>
                </a>
            <?php endwhile; ?>
        </div>
    </aside>

    <!-- RIGHT CHAT AREA -->
    <main class="chat-main">

        <div class="header">
            <div class="header-profile">
                <?php if (!empty($friend["profile_picture"])): ?>
                    <img
                        src="<?= h($friend["profile_picture"]) ?>"
                        class="header-avatar"
                        alt="Profile picture"
                    >
                <?php else: ?>
                    <div class="header-avatar placeholder">
                        <?= h(strtoupper(substr($friend_name, 0, 1))) ?>
                    </div>
                <?php endif; ?>

                <div class="header-info">
                    <h2><?= h($friend_name) ?></h2>
                    <small>@<?= h($friend["username"]) ?></small>
                    <div class="friend-status">
                        <?= !empty($friend["is_online"]) ? "● Online" : "● Offline" ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="messages" id="messages">
            <?php if (empty($messages)): ?>
                <p class="empty">No messages yet. Say hello!</p>
            <?php endif; ?>

            <?php foreach ($messages as $msg): ?>
                <?php
                $is_sent = ((int)$msg["sender_id"] === $current_id);
                $reply_sender_name = ((int)($msg["reply_sender_id"] ?? 0) === $current_id)
                    ? "You"
                    : $friend_name;
                ?>
                <div
                    class="message <?= $is_sent ? 'sent' : 'received' ?>"
                    data-message-id="<?= (int)$msg["id"] ?>"
                >
                    <?php if (!empty($msg["reply_to"]) && !empty($msg["reply_message"])): ?>
                        <div class="reply-quote">
                            <strong><?= h($reply_sender_name) ?></strong>
                            <p><?= h($msg["reply_message"]) ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="message-text"><?= nl2br(h($msg["message"])) ?></div>

                    
<?php if (!empty($msg["attachment_path"])): ?>

    <?php
    $attachmentUrl = $msg["attachment_path"];
    $attachmentName = $msg["attachment_name"];
    $attachmentType = $msg["attachment_type"];
    ?>

    <?php if (strpos($attachmentType, "image/") === 0): ?>

        <a href="<?= h($attachmentUrl) ?>" target="_blank">
            <img
                src="<?= h($attachmentUrl) ?>"
                alt="Attached image"
                style="max-width:220px; max-height:220px;
                       border-radius:12px; display:block;
                       margin-top:8px;"
            >
        </a>

    <?php else: ?>

        <a href="<?= h($attachmentUrl) ?>"
           download="<?= h($attachmentName) ?>"
           class="attachment-link">
            📎 <?= h($attachmentName) ?>
        </a>

    <?php endif; ?>

<?php endif; ?>

                    <div class="message-meta">
                        <small><?= h(date("H:i", strtotime($msg["created_at"]))) ?></small>

                        <?php if ($is_sent && (int)$msg["is_read"] === 1): ?>
                            <span class="seen-status">👍👍</span>
                        <?php endif; ?>
                    </div>

                    <div class="message-menu-wrap">
                        <button
                            type="button"
                            class="message-menu-toggle"
                            aria-label="Message options"
                            aria-expanded="false"
                        >⋮</button>

                        <div class="message-menu">
                            <?php if ($is_sent): ?>
                                <button
                                    type="button"
                                    class="edit-message"
                                    data-id="<?= (int)$msg["id"] ?>"
                                >Edit</button>

                                <button
                                    type="button"
                                    class="delete-message"
                                    data-id="<?= (int)$msg["id"] ?>"
                                >Delete</button>
                            <?php else: ?>
                                <button
                                    type="button"
                                    class="reply-message"
                                    data-id="<?= (int)$msg["id"] ?>"
                                >Reply</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="typingIndicator" class="typing-indicator"></div>

        <?php if ($error !== ""): ?>
            <div class="error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="form-area">
            <div id="replyPreview" class="reply-preview">
                <div>
                    <strong>Replying to:</strong>
                    <p id="replyMessageText"></p>
                </div>
                <button type="button" id="cancelReply" aria-label="Cancel reply">×</button>
            </div>

<div class="attachment-wrapper">

    <button type="button" id="attachBtn" class="attach-btn">+</button>

    <div id="attachmentMenu" class="attachment-menu" hidden>
        <button type="button" id="chooseFile">📎 Add file</button>
        <button type="button" id="chooseImage">🖼️ Upload image</button>
    </div>

   <input type="file"
       id="fileInput"
       name="attachment"
       hidden>

<input type="file"
       id="imageInput"
       name="attachment"
       accept="image/*"
       hidden>
</div>

            <input
                type="hidden"
                name="reply_to"
                id="replyToInput"
                value=""
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= h($_SESSION["csrf_token"]) ?>"
            >

            <div class="composer-row">
                <input
                    type="text"
                    name="message"
                    id="messageInput"
                    placeholder="Type a message..."
                    maxlength="5000"
                    autocomplete="off"
                    required
                >
               <button type="submit" class="send-icon-btn"
        aria-label="Send message">
    <img src="assets/image/typem-skull.png"
         alt="TYPEM Send">
</button>
            </div>
        </form>

    </main>
</div>

<script>
const messagesBox = document.getElementById("messages");
const friendId = <?= (int)$friend_id ?>;
const currentUserId = <?= (int)$current_id ?>;
let lastMessageId = <?= $last_message_id ?>;

const replyPreview = document.getElementById("replyPreview");
const replyMessageText = document.getElementById("replyMessageText");
const replyToInput = document.getElementById("replyToInput");
const cancelReply = document.getElementById("cancelReply");
const messageInput = document.getElementById("messageInput");
const typingIndicator = document.getElementById("typingIndicator");

// Close every open message menu.
function closeMessageMenus(exceptMenu = null) {
    document.querySelectorAll(".message-menu.open").forEach(function(menu) {
        if (menu !== exceptMenu) {
            menu.classList.remove("open");
            const toggle = menu.parentElement.querySelector(".message-menu-toggle");
            if (toggle) toggle.setAttribute("aria-expanded", "false");
        }
    });
}

// Build the message menu for messages received through polling.
function createMessageMenu(isSent, messageId) {
    const wrap = document.createElement("div");
    wrap.className = "message-menu-wrap";

    const toggle = document.createElement("button");
    toggle.type = "button";
    toggle.className = "message-menu-toggle";
    toggle.setAttribute("aria-label", "Message options");
    toggle.setAttribute("aria-expanded", "false");
    toggle.textContent = "⋮";

    const menu = document.createElement("div");
    menu.className = "message-menu";

    if (isSent) {
        const editButton = document.createElement("button");
        editButton.type = "button";
        editButton.className = "edit-message";
        editButton.dataset.id = messageId;
        editButton.textContent = "Edit";

        const deleteButton = document.createElement("button");
        deleteButton.type = "button";
        deleteButton.className = "delete-message";
        deleteButton.dataset.id = messageId;
        deleteButton.textContent = "Delete";

        menu.appendChild(editButton);
        menu.appendChild(deleteButton);
    } else {
        const replyButton = document.createElement("button");
        replyButton.type = "button";
        replyButton.className = "reply-message";
        replyButton.dataset.id = messageId;
        replyButton.textContent = "Reply";
        menu.appendChild(replyButton);
    }

    wrap.appendChild(toggle);
    wrap.appendChild(menu);
    return wrap;
}

// Add a newly received message with the same layout and menu as existing messages.
function addMessage(msg) {
    if (messagesBox.querySelector('[data-message-id="' + msg.id + '"]')) {
        return;
    }

    const isSent = Number(msg.sender_id) === currentUserId;
    const bubble = document.createElement("div");
    bubble.className = "message " + (isSent ? "sent" : "received");
    bubble.dataset.messageId = msg.id;

    const content = document.createElement("div");
    content.className = "message-text";
    content.textContent = msg.message;

    const meta = document.createElement("div");
    meta.className = "message-meta";

    const time = document.createElement("small");
    const dateValue = String(msg.created_at || "");
    const parsedDate = new Date(dateValue.replace(" ", "T"));
    time.textContent = Number.isNaN(parsedDate.getTime())
        ? dateValue
        : parsedDate.toLocaleTimeString([], {hour: "2-digit", minute: "2-digit"});

    meta.appendChild(time);

    if (isSent && Number(msg.is_read) === 1) {
        const seen = document.createElement("span");
        seen.className = "seen-status";
        seen.textContent = "👍👍";
        meta.appendChild(seen);
    }

    bubble.appendChild(content);
    bubble.appendChild(meta);
    bubble.appendChild(createMessageMenu(isSent, msg.id));

    const emptyMessage = messagesBox.querySelector(".empty");
    if (emptyMessage) emptyMessage.remove();

    messagesBox.appendChild(bubble);
    messagesBox.scrollTop = messagesBox.scrollHeight;
}

// One click handler manages opening and closing all three-dot menus.
messagesBox.addEventListener("click", async function(e) {
    const toggle = e.target.closest(".message-menu-toggle");

    if (toggle) {
        const menu = toggle.parentElement.querySelector(".message-menu");
        const willOpen = !menu.classList.contains("open");
        closeMessageMenus(menu);
        menu.classList.toggle("open", willOpen);
        toggle.setAttribute("aria-expanded", willOpen ? "true" : "false");
        return;
    }

    const replyButton = e.target.closest(".reply-message");
    if (replyButton) {
        const bubble = replyButton.closest(".message");
        const originalText = bubble.querySelector(".message-text").innerText;

        replyToInput.value = replyButton.dataset.id;
        replyMessageText.textContent = originalText;
        replyPreview.style.display = "flex";
        messageInput.focus();
        closeMessageMenus();
        return;
    }

    const deleteButton = e.target.closest(".delete-message");
    if (deleteButton) {
        if (!confirm("Are you sure you want to delete this message?")) {
            return;
        }

        const data = new URLSearchParams();
        data.append("message_id", deleteButton.dataset.id);

        try {
            const response = await fetch("delete_message.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: data
            });

            const result = await response.text();

            if (result.trim() === "success") {
                deleteButton.closest(".message").remove();
                closeMessageMenus();
            } else {
                alert(result);
            }
        } catch (error) {
            alert("Could not delete the message. Please try again.");
        }
        return;
    }

    const editButton = e.target.closest(".edit-message");
    if (editButton) {
        const bubble = editButton.closest(".message");
        const messageText = bubble.querySelector(".message-text");
        const oldMessage = messageText.innerText;
        const newMessage = prompt("Edit your message:", oldMessage);

        if (newMessage === null) return;

        if (newMessage.trim() === "") {
            alert("Message cannot be empty.");
            return;
        }

        const data = new URLSearchParams();
        data.append("message_id", editButton.dataset.id);
        data.append("message", newMessage.trim());

        try {
            const response = await fetch("edit_message.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: data
            });

            const result = await response.json();

            if (result.success) {
                messageText.textContent = newMessage.trim();

                let edited = bubble.querySelector(".edited-label");
                if (!edited) {
                    edited = document.createElement("small");
                    edited.className = "edited-label";
                    edited.textContent = "Edited";
                    bubble.querySelector(".message-meta").appendChild(edited);
                }

                closeMessageMenus();
            } else {
                alert(result.message || "Could not edit this message.");
            }
        } catch (error) {
            alert("Could not edit the message.");
        }
    }
});

// Clicking outside a menu closes it.
document.addEventListener("click", function(e) {
    if (!e.target.closest(".message-menu-wrap")) {
        closeMessageMenus();
    }
});

// Cancel the current reply.
cancelReply.addEventListener("click", function() {
    replyToInput.value = "";
    replyMessageText.textContent = "";
    replyPreview.style.display = "none";
});

// Friend search.
const friendSearch = document.getElementById("friendSearch");

friendSearch.addEventListener("input", function() {
    const searchText = this.value.toLowerCase();

    document.querySelectorAll(".friend-item").forEach(function(friend) {
        const friendName = friend.textContent.toLowerCase();
        friend.style.display = friendName.includes(searchText) ? "flex" : "none";
    });
});

// Typing status: use the actual message input, not the hidden CSRF input.
let typingTimer;

function sendTypingStatus(action) {
    const data = new URLSearchParams();
    data.append("friend_id", friendId);
    data.append("action", action);

    fetch("typing.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: data
    }).catch(function(error) {
        console.log("Typing status update failed:", error);
    });
}

messageInput.addEventListener("input", function() {
    if (this.value.trim() !== "") {
        sendTypingStatus("typing");
    } else {
        sendTypingStatus("stop");
    }

    clearTimeout(typingTimer);
    typingTimer = setTimeout(function() {
        sendTypingStatus("stop");
    }, 3000);
});

messageInput.addEventListener("blur", function() {
    sendTypingStatus("stop");
});

// Check whether the friend is typing.
async function checkFriendTyping() {
    try {
        const response = await fetch(
            "check_typing.php?friend_id=" + friendId,
            {cache: "no-store"}
        );

        if (!response.ok) return;

        const result = await response.json();
        typingIndicator.textContent = result.typing ? "Friend is typing..." : "";
    } catch (error) {
        console.log("Typing check failed:", error);
    }
}

// Poll for new messages and read receipts.
async function checkMessages() {
    try {
        const response = await fetch(
            "fetch_messages.php?user=" + friendId + "&after=" + lastMessageId,
            {cache: "no-store"}
        );

        if (!response.ok) return;

        const data = await response.json();

        if (Array.isArray(data.messages)) {
            for (const msg of data.messages) {
                addMessage(msg);
                lastMessageId = Math.max(lastMessageId, Number(msg.id));
            }
        }

        if (Array.isArray(data.read_ids)) {
            for (const messageId of data.read_ids) {
                const bubble = messagesBox.querySelector(
                    '[data-message-id="' + messageId + '"]'
                );

                if (bubble && bubble.classList.contains("sent")) {
                    const meta = bubble.querySelector(".message-meta");

                    if (meta && !meta.querySelector(".seen-status")) {
                        const seen = document.createElement("span");
                        seen.className = "seen-status";
                        seen.textContent = "👍👍";
                        meta.appendChild(seen);
                    }
                }
            }
        }
    } catch (error) {
        console.log("Waiting for messages...");
    }
}

// Initial scroll to the latest message.
messagesBox.scrollTop = messagesBox.scrollHeight;

setInterval(checkMessages, 2000);
setInterval(checkFriendTyping, 1000);

// Clear typing status when the page is being left.
window.addEventListener("beforeunload", function() {
    sendTypingStatus("stop");
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const attachBtn = document.getElementById("attachBtn");
    const menu = document.getElementById("attachmentMenu");

    const fileInput = document.getElementById("fileInput");
    const imageInput = document.getElementById("imageInput");

    document.getElementById("chooseFile").onclick = function () {
        menu.hidden = true;
        fileInput.click();
    };

    document.getElementById("chooseImage").onclick = function () {
        menu.hidden = true;
        imageInput.click();
    };

    attachBtn.onclick = function () {
        menu.hidden = !menu.hidden;
    };

    document.addEventListener("click", function (event) {
        if (!event.target.closest(".attachment-wrapper")) {
            menu.hidden = true;
        }
    });

});
</script>

</body>
</html>
