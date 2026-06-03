<?php

session_start();

include('../config/db.php');

if(isset($_POST['login'])){

    $email    = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    $query  = "SELECT * FROM users WHERE email='$email'";
    $result = mysqli_query($conn, $query);

    if(mysqli_num_rows($result) > 0){

        $user = mysqli_fetch_assoc($result);

        if(password_verify($password, $user['password'])){

            $_SESSION['user_id']    = $user['user_id'];
            $_SESSION['name']       = $user['name'];
            $_SESSION['email']      = $user['email'];
            $_SESSION['role']       = $user['role'];

            // ── Avatar: check if BLOB data exists in DB ──
            $uid        = intval($user['user_id']);
            $avatarRow  = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT profile_img_data FROM users
                 WHERE user_id = $uid LIMIT 1"
            ));
            $hasAvatar  = !empty($avatarRow['profile_img_data']);

            $_SESSION['has_avatar']  = $hasAvatar;
            $_SESSION['profile_pic'] = $hasAvatar
                ? '/AdHub_V2/ajax/get_avatar.php?id=' . $uid
                : null;

            if($user['role'] == 'staff'){
                header("Location: ../admin/dashboard/dashboard.php");
                exit();
            }
            else if($user['role'] == 'client'){
                header("Location: ../client/dashboard/dashboard.php");
                exit();
            }

        } else {

            $_SESSION['error'] = "Incorrect password.";
            header("Location: ../index.php");
            exit();
        }

    } else {

        $_SESSION['error'] = "Account not found.";
        header("Location: ../index.php");
        exit();
    }
}
?>