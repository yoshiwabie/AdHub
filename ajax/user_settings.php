<?php
// /AdHub_V2/ajax/user_settings.php

if (session_status() === PHP_SESSION_NONE) session_start();
include(__DIR__ . '/../config/db.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$action  = $_POST['action'] ?? '';

// ────────────────────────────────────────────────
// 1. UPDATE PROFILE  (name + email + optional avatar)
// ────────────────────────────────────────────────
if ($action === 'update_profile') {

    $name  = trim($_POST['name']  ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($name === '' || $email === '') {
        echo json_encode(['success' => false, 'message' => 'Name and email are required.']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
        exit;
    }

    // Check email not taken by another user
    $escaped_email = mysqli_real_escape_string($conn, $email);
    $check = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT user_id FROM users
         WHERE email = '$escaped_email' AND user_id != $user_id LIMIT 1"
    ));
    if ($check) {
        echo json_encode(['success' => false, 'message' => 'Email already in use by another account.']);
        exit;
    }

    $escaped_name = mysqli_real_escape_string($conn, $name);
    $avatar_url   = null;
    $blob_clause  = '';

    // ── Handle avatar upload → store as BLOB ──
    if (!empty($_FILES['avatar']['tmp_name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {

        $file    = $_FILES['avatar'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        // Validate MIME from actual file bytes, not browser-supplied type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, GIF, or WebP allowed.']);
            exit;
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File must be under 2 MB.']);
            exit;
        }

        // Read raw bytes
        $img_data = file_get_contents($file['tmp_name']);
        if ($img_data === false) {
            echo json_encode(['success' => false, 'message' => 'Failed to read uploaded file.']);
            exit;
        }

        // Use prepared statement for the BLOB — mysqli_real_escape_string
        // is unreliable for binary data
        $stmt = mysqli_prepare($conn,
            "UPDATE users
             SET name = ?, email = ?, profile_img_data = ?, profile_img_type = ?
             WHERE user_id = ?"
        );
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'DB prepare error: ' . mysqli_error($conn)]);
            exit;
        }

        mysqli_stmt_bind_param($stmt, 'ssssi', $name, $email, $img_data, $mime, $user_id);

        if (!mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => false, 'message' => 'DB execute error: ' . mysqli_stmt_error($stmt)]);
            mysqli_stmt_close($stmt);
            exit;
        }
        mysqli_stmt_close($stmt);

        // Update session — topbar will use the endpoint URL
        $_SESSION['name']       = $name;
        $_SESSION['email']      = $email;
        $_SESSION['has_avatar'] = true;

        $avatar_url = '/AdHub_V2/ajax/get_avatar.php?id=' . $user_id . '&t=' . time();

        echo json_encode([
            'success'    => true,
            'avatar_url' => $avatar_url,
            'reload'     => true,
        ]);
        exit;
    }

    // ── No avatar uploaded — just update name and email ──
    $ok = mysqli_query($conn,
        "UPDATE users
         SET name = '$escaped_name', email = '$escaped_email'
         WHERE user_id = $user_id"
    );

    if (!$ok) {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . mysqli_error($conn)]);
        exit;
    }

    $_SESSION['name']  = $name;
    $_SESSION['email'] = $email;

    // Return existing avatar URL if one already exists
    $existing = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT profile_img_data FROM users WHERE user_id = $user_id LIMIT 1"
    ));
    $has_avatar = !empty($existing['profile_img_data']);
    $_SESSION['has_avatar'] = $has_avatar;

    echo json_encode([
        'success'    => true,
        'avatar_url' => $has_avatar
            ? '/AdHub_V2/ajax/get_avatar.php?id=' . $user_id . '&t=' . time()
            : null,
        'reload'     => true,
    ]);
    exit;
}

// ────────────────────────────────────────────────
// 2. CHANGE PASSWORD
// ────────────────────────────────────────────────
if ($action === 'change_password') {

    $current  = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password']     ?? '';

    if ($current === '' || $new_pass === '') {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }
    if (strlen($new_pass) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
        exit;
    }

    $row = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT password FROM users WHERE user_id = $user_id LIMIT 1"
    ));

    if (!$row || !password_verify($current, $row['password'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }

    $hashed = mysqli_real_escape_string($conn, password_hash($new_pass, PASSWORD_DEFAULT));
    $ok     = mysqli_query($conn,
        "UPDATE users SET password = '$hashed' WHERE user_id = $user_id"
    );

    if (!$ok) {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . mysqli_error($conn)]);
        exit;
    }

    echo json_encode(['success' => true, 'reload' => true]);
    exit;
}

// ────────────────────────────────────────────────
// 3. TOGGLE PREFERENCE
// ────────────────────────────────────────────────
if ($action === 'toggle_pref') {

    $pref  = preg_replace('/[^a-z_]/', '', $_POST['pref'] ?? '');
    $value = intval($_POST['value'] ?? 0);

    $allowed_prefs = ['email_notif'];

    if (!in_array($pref, $allowed_prefs)) {
        echo json_encode(['success' => false, 'message' => 'Unknown preference.']);
        exit;
    }

    $ok = mysqli_query($conn,
        "INSERT INTO user_preferences (user_id, pref_key, pref_value)
         VALUES ($user_id, '$pref', $value)
         ON DUPLICATE KEY UPDATE pref_value = $value"
    );

    if (!$ok) {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . mysqli_error($conn)]);
        exit;
    }

    echo json_encode(['success' => true]);
    exit;
}

// ────────────────────────────────────────────────
// 4. MARK ALL NOTIFICATIONS READ
// ────────────────────────────────────────────────
if ($action === 'mark_all_read') {

    $ok = mysqli_query($conn,
        "UPDATE notifications SET is_read = 1
         WHERE user_id = $user_id AND is_read = 0"
    );

    echo json_encode(['success' => (bool)$ok]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);