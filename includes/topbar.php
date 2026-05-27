<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include(__DIR__ . '/../config/db.php');

/*
========================================
FETCH LATEST 3 NOTIFICATIONS
========================================
*/

$user_id = $_SESSION['user_id'] ?? 0;

$notifQuery = mysqli_query($conn, "
    SELECT title, message, created_at
    FROM notifications
    WHERE user_id = '$user_id'
    ORDER BY created_at DESC
    LIMIT 3
");
?>

<header class="topbar">

    <div class="logo-section">
        <h3>AdHub</h3>
    </div>

    <div class="topbar-icons">

        <!-- NOTIFICATIONS WRAPPER -->
        <div class="notif-wrapper">

            <a href="#" class="notif-bell" id="notifBell">
                <i class="fa-regular fa-bell"></i>
            </a>

            <!-- DROPDOWN -->
            <div class="notif-dropdown" id="notifDropdown">

                <?php if(mysqli_num_rows($notifQuery) > 0){ ?>

                    <?php while($row = mysqli_fetch_assoc($notifQuery)) { ?>

                        <div class="notif-item">

                            <strong>
                                <?= htmlspecialchars($row['title']); ?>
                            </strong>

                            <p>
                                <?= htmlspecialchars($row['message']); ?>
                            </p>

                            <small>
                                <?= date('M d, h:i A', strtotime($row['created_at'])); ?>
                            </small>

                        </div>

                    <?php } ?>

                <?php } else { ?>

                    <div class="notif-item">
                        <p>No notifications yet</p>
                    </div>

                <?php } ?>

                <!-- VIEW ALL BUTTON -->
                <a href="/AdHub_V2/staff/notifications/notifications.php"
                   class="notif-view-all">
                    View All Notifications
                </a>

            </div>

        </div>


        <!-- SETTINGS -->

        <a href="#">
            <i class="fa-solid fa-gear"></i>
        </a>

        <!-- PROFILE -->

        <div class="profile-box">
            <img src="/AdHub_V2/assets/images/default-profile.png">
        </div>

    </div>

</header>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const bell = document.getElementById("notifBell");
    const dropdown = document.getElementById("notifDropdown");

    bell.addEventListener("click", function (e) {
        e.preventDefault();
        dropdown.classList.toggle("show");
    });

    document.addEventListener("click", function (e) {
        if (!bell.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.remove("show");
        }
    });

});
</script>