<?php
session_start();

include('../../config/db.php');
include('../../includes/topbar.php');

if(!isset($_SESSION['user_id'])){
    header("Location: ../../index.php");
    exit();
}

if($_SESSION['role'] != 'client'){
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$name    = $_SESSION['name'];

$filter = $_GET['filter'] ?? 'all';

$filterCondition = "";

if($filter == 'approved'){
    $filterCondition = "AND LOWER(title) LIKE '%approved%'";
} elseif($filter == 'revision'){
    $filterCondition = "AND LOWER(title) LIKE '%revision%'";
} elseif($filter == 'upload'){
    $filterCondition = "AND LOWER(title) LIKE '%upload%'";
} elseif($filter == 'deadline'){
    $filterCondition = "AND LOWER(title) LIKE '%deadline%'";
}

$notifications = mysqli_query($conn,"
    SELECT *
    FROM notifications
    WHERE user_id = '$user_id'
    $filterCondition
    ORDER BY created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications | AdHub</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="/AdHub_V2/assets/css/style.css">
<link rel="stylesheet" href="/AdHub_V2/assets/css/dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

<style>

.main-content {
    margin-left: 260px;
    margin-top: 75px;
    padding: 35px;
}

/* ── HERO ── */
.page-banner {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    border-radius: 24px;
    padding: 26px 32px;
    color: white;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 14px;
}

.page-banner h1 {
    font-size: 26px;
    font-weight: 700;
    color: white;
    margin: 0 0 5px;
}

.page-banner p { margin: 0; color: #94a3b8; font-size: 14px; }

.count-pill {
    background: rgba(255,255,255,0.12);
    border-radius: 50px;
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 700;
    color: white;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* ── FILTER PILLS ── */
.filter-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 24px;
}

.filter-btn {
    border-radius: 50px;
    font-size: 13px;
    font-weight: 600;
    padding: 8px 18px;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}

/* ── DASHBOARD CARD ── */
.dashboard-card {
    background: white;
    border-radius: 24px;
    padding: 24px;
    box-shadow: 0 4px 16px rgba(15,23,42,0.06);
}

/* ── NOTIFICATION ITEMS ── */
.notification-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.notification-item {
    display: flex;
    gap: 14px;
    padding: 16px;
    border-radius: 16px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    align-items: flex-start;
    transition: box-shadow 0.15s, transform 0.15s;
}

.notification-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.06);
}

.notification-icon {
    width: 46px;
    height: 46px;
    min-width: 46px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.icon-success  { background: #dcfce7; color: #16a34a; }
.icon-warning  { background: #fef3c7; color: #d97706; }
.icon-blue     { background: #dbeafe; color: #2563eb; }
.icon-red      { background: #fee2e2; color: #dc2626; }

.notification-content { flex: 1; }

.notif-title {
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 4px;
}

.notif-msg {
    font-size: 13px;
    color: #64748b;
    margin: 0 0 6px;
    line-height: 1.5;
}

.notif-date {
    font-size: 11px;
    color: #94a3b8;
    display: flex;
    align-items: center;
    gap: 4px;
}

/* ── EMPTY STATE ── */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #94a3b8;
}

.empty-state i {
    font-size: 52px;
    margin-bottom: 14px;
    display: block;
}

.empty-state h5 { font-size: 16px; color: #64748b; margin-bottom: 6px; }
.empty-state p  { font-size: 13px; margin: 0; }

</style>

</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-menu">

        <a href="../dashboard/dashboard.php">
            <i class="fa-solid fa-table-columns"></i>
            Dashboard
        </a>

        <a href="../kanban/main_board.php">
            <i class="fa-solid fa-layer-group"></i>
            Campaigns
        </a>

        <a href="../retainer/retainer.php">
            <i class="fa-solid fa-wallet"></i>
            Retainer
        </a>

        <a href="notifications.php" class="active">
            <i class="fa-regular fa-bell"></i>
            Notifications
        </a>

        <a href="../../auth/logout.php">
            <i class="fa-solid fa-right-from-bracket"></i>
            Logout
        </a>

    </div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">

    <!-- HERO BANNER -->
    <div class="page-banner">
        <div>
            <h1><i class="fa-regular fa-bell me-2"></i>Notifications</h1>
            <p>Latest updates from your campaigns and tasks.</p>
        </div>
        <div class="count-pill">
            <i class="fa-solid fa-layer-group"></i>
            <?= mysqli_num_rows($notifications); ?> Notifications
        </div>
    </div>

    <!-- FILTER BUTTONS -->
    <div class="filter-bar">

        <a href="notifications.php?filter=all"
           class="btn filter-btn <?= $filter == 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">
            <i class="fa-solid fa-layer-group"></i> All
        </a>

        <a href="notifications.php?filter=approved"
           class="btn filter-btn <?= $filter == 'approved' ? 'btn-success' : 'btn-outline-success'; ?>">
            <i class="fa-solid fa-circle-check"></i> Approved
        </a>

        <a href="notifications.php?filter=revision"
           class="btn filter-btn <?= $filter == 'revision' ? 'btn-warning' : 'btn-outline-warning'; ?>">
            <i class="fa-solid fa-rotate-right"></i> Revision
        </a>

        <a href="notifications.php?filter=upload"
           class="btn filter-btn <?= $filter == 'upload' ? 'btn-info' : 'btn-outline-info'; ?>">
            <i class="fa-solid fa-file-arrow-up"></i> Uploads
        </a>

        <a href="notifications.php?filter=deadline"
           class="btn filter-btn <?= $filter == 'deadline' ? 'btn-danger' : 'btn-outline-danger'; ?>">
            <i class="fa-solid fa-clock"></i> Deadlines
        </a>

    </div>

    <!-- NOTIFICATION LIST -->
    <div class="dashboard-card">
        <div class="notification-list">

            <?php if(mysqli_num_rows($notifications) > 0): ?>

                <?php while($row = mysqli_fetch_assoc($notifications)) {

                    $icon  = "fa-bell";
                    $color = "icon-blue";

                    $titleLower = strtolower($row['title']);

                    if(str_contains($titleLower, 'approved')){
                        $icon  = "fa-circle-check";
                        $color = "icon-success";
                    } elseif(str_contains($titleLower, 'revision')){
                        $icon  = "fa-rotate-right";
                        $color = "icon-warning";
                    } elseif(str_contains($titleLower, 'upload')){
                        $icon  = "fa-file-arrow-up";
                        $color = "icon-blue";
                    } elseif(str_contains($titleLower, 'deadline')){
                        $icon  = "fa-clock";
                        $color = "icon-red";
                    }
                ?>

                <div class="notification-item">

                    <div class="notification-icon <?= $color; ?>">
                        <i class="fa-solid <?= $icon; ?>"></i>
                    </div>

                    <div class="notification-content">
                        <div class="notif-title"><?= htmlspecialchars($row['title']); ?></div>
                        <div class="notif-msg"><?= htmlspecialchars($row['message']); ?></div>
                        <div class="notif-date">
                            <i class="fa-regular fa-clock"></i>
                            <?= date('M d, Y h:i A', strtotime($row['created_at'])); ?>
                        </div>
                    </div>

                </div>

                <?php } ?>

            <?php else: ?>

                <div class="empty-state">
                    <i class="fa-regular fa-bell-slash"></i>
                    <h5>No notifications found</h5>
                    <p>Try selecting a different filter.</p>
                </div>

            <?php endif; ?>

        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>