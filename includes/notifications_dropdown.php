<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include(__DIR__ . '/../config/db.php');

$notifQuery = mysqli_query($conn, "
    SELECT title, message, created_at
    FROM notifications
    ORDER BY created_at DESC
    LIMIT 3
");
?>
<style>
    /* TOPBAR ICON BUTTON */
.icon-btn {
    background: transparent;
    border: none;
    font-size: 18px;
    cursor: pointer;
    color: #0f172a;
    padding: 8px;
    border-radius: 8px;
    transition: background 0.2s;
}

.icon-btn:hover {
    background: #f1f5f9;
}

/* WRAPPER */
.notif-wrapper {
    position: relative;
}

/* DROPDOWN */
.notif-dropdown {
    position: absolute;
    right: 0;
    top: 42px;
    width: 320px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    z-index: 9999;

    opacity: 0;
    visibility: hidden;
    transform: translateY(-8px);
    transition: 0.2s ease;
}

/* ACTIVE STATE */
.notif-dropdown.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

/* HEADER */
.notif-header {
    padding: 12px 14px;
    border-bottom: 1px solid #e2e8f0;
    font-size: 13px;
}

/* BODY */
.notif-body {
    max-height: 300px;
    overflow-y: auto;
}

/* ITEM */
.notif-item {
    padding: 12px 14px;
    border-bottom: 1px solid #f1f5f9;
}

.notif-item:hover {
    background: #f8fafc;
}

.notif-title {
    font-size: 13px;
    font-weight: 600;
    color: #0f172a;
}

.notif-msg {
    font-size: 12px;
    color: #64748b;
    margin-top: 3px;
}

.notif-time {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 6px;
}

/* EMPTY */
.notif-empty {
    padding: 14px;
    font-size: 13px;
    color: #94a3b8;
}
</style>

<!-- ========================================
TOPBAR
======================================== -->

<header class="topbar">

    <div class="logo-section">
        <h3>AdHub</h3>
    </div>

    <div class="topbar-icons">

        <!-- NOTIFICATIONS -->
        <div class="notif-wrapper">

            <button class="icon-btn" id="notifBell">
                <i class="fa-regular fa-bell"></i>
            </button>

            <div class="notif-dropdown" id="notifDropdown">

                <div class="notif-header">
                    <strong>Notifications</strong>
                </div>

                <div class="notif-body">

                    <?php if(mysqli_num_rows($notifQuery) > 0){ ?>

                        <?php while($row = mysqli_fetch_assoc($notifQuery)) { ?>

                            <div class="notif-item">
                                <div class="notif-title">
                                    <?= htmlspecialchars($row['title']); ?>
                                </div>

                                <div class="notif-msg">
                                    <?= htmlspecialchars($row['message']); ?>
                                </div>

                                <div class="notif-time">
                                    <?= date('M d, h:i A', strtotime($row['created_at'])); ?>
                                </div>
                            </div>

                        <?php } ?>

                    <?php } else { ?>

                        <div class="notif-empty">
                            No notifications yet
                        </div>

                    <?php } ?>

                </div>

            </div>
        </div>

        <!-- SETTINGS -->
        <button class="icon-btn">
            <i class="fa-solid fa-gear"></i>
        </button>

        <!-- PROFILE -->
        <div class="profile-box">
            <img src="/AdHub_V2/assets/images/default-profile.png">
        </div>

    </div>

</header>

<!-- ========================================
NOTIF TOGGLE SCRIPT
======================================== -->

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