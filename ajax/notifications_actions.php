<?php
session_start();
include('../config/db.php');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = intval($_SESSION['user_id']);
$action  = $_POST['action'] ?? '';

header('Content-Type: application/json');

switch ($action) {

    case 'mark_read':
        $id = intval($_POST['notification_id'] ?? 0);
        mysqli_query($conn, "
            UPDATE notifications
            SET is_read = 1
            WHERE notification_id = $id AND user_id = $user_id
        ");
        echo json_encode(['success' => true, 'reload' => true]);
        break;

    case 'mark_all_read':
        mysqli_query($conn, "
            UPDATE notifications
            SET is_read = 1
            WHERE user_id = $user_id AND deleted_at IS NULL
        ");
        echo json_encode(['success' => true, 'reload' => true]);
        break;

    case 'delete':
        $id = intval($_POST['notification_id'] ?? 0);
        mysqli_query($conn, "
            UPDATE notifications
            SET deleted_at = NOW()
            WHERE notification_id = $id AND user_id = $user_id
        ");
        echo json_encode(['success' => true, 'reload' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}