<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include(__DIR__ . '/../config/db.php');

$user_id = intval($_GET['id'] ?? $_SESSION['user_id'] ?? 0);
if (!$user_id) { http_response_code(404); exit; }

$row = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT profile_img_data, profile_img_type FROM users WHERE user_id = $user_id LIMIT 1"
));

if (!$row || empty($row['profile_img_data'])) {
    http_response_code(404); exit;
}

header('Content-Type: '  . $row['profile_img_type']);
header('Cache-Control: max-age=86400');
echo $row['profile_img_data'];
exit;