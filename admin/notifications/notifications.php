<?php
session_start();

include('../../config/db.php');
include('../../includes/topbar.php');

if(!isset($_SESSION['user_id'])){
    header("Location: ../../index.php");
    exit();
}

if($_SESSION['role'] != 'staff'){
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$name = $_SESSION['name'];

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
    WHERE (user_id IS NULL OR user_id = '$user_id')
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

.main-content{
    margin-left:260px;
    margin-top:75px;
    padding:35px;
}

.page-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:30px;
}

.page-header h1{
    font-size:34px;
    font-weight:700;
    color:var(--primary);
}

.page-header p{
    color:#64748b;
}

.notification-list{
    display:flex;
    flex-direction:column;
    gap:15px;
}

.notification-item{
    display:flex;
    gap:15px;
    padding:15px;
    border-radius:16px;
    background:#f8fafc;
    transition:0.2s;
}

.notification-item:hover{
    transform:translateY(-2px);
    box-shadow:0 6px 15px rgba(0,0,0,0.06);
}

.notification-icon{
    width:45px;
    height:45px;
    min-width:45px;
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:18px;
}

.success{ background:#dcfce7; color:#16a34a; }
.warning{ background:#fef3c7; color:#d97706; }
.blue{ background:#dbeafe; color:#2563eb; }
.red{ background:#fee2e2; color:#dc2626; }

.notification-content h5{
    margin:0;
    font-size:15px;
    font-weight:600;
}

.notification-content p{
    margin:2px 0;
    font-size:14px;
    color:#64748b;
}

.notification-content span{
    font-size:12px;
    color:#94a3b8;
}

.filter-btn{
    border-radius:30px;
    font-size:13px;
    padding:6px 16px;
}

.empty-state{
    text-align:center;
    padding:60px 20px;
    color:#94a3b8;
}

.empty-state i{
    font-size:52px;
    margin-bottom:15px;
    display:block;
}

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

        <a href="../timelogs/time_logs.php">
            <i class="fa-regular fa-clock"></i>
            Time Logs
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

    <div class="page-header">

        <div>
            <h1>Notifications</h1>
            <p>Latest updates from your campaigns and tasks.</p>
        </div>

        <!-- TOTAL COUNT BADGE -->
        <span class="badge bg-primary fs-6">
            <?= mysqli_num_rows($notifications); ?> Notifications
        </span>

    </div>

    <!-- FILTER BUTTONS -->
    <div class="d-flex gap-2 flex-wrap mb-4">

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

    <div class="dashboard-card">

        <div class="notification-list">

            <?php if(mysqli_num_rows($notifications) > 0): ?>

                <?php while($row = mysqli_fetch_assoc($notifications)) {

                    $icon = "fa-bell";
                    $color = "blue";

                    if(str_contains(strtolower($row['title']), 'approved')){
                        $icon = "fa-circle-check";
                        $color = "success";
                    } elseif(str_contains(strtolower($row['title']), 'revision')){
                        $icon = "fa-rotate-right";
                        $color = "warning";
                    } elseif(str_contains(strtolower($row['title']), 'upload')){
                        $icon = "fa-file-arrow-up";
                        $color = "blue";
                    } elseif(str_contains(strtolower($row['title']), 'deadline')){
                        $icon = "fa-clock";
                        $color = "red";
                    }
                ?>

                <div class="notification-item">

                    <div class="notification-icon <?= $color; ?>">
                        <i class="fa-solid <?= $icon; ?>"></i>
                    </div>

                    <div class="notification-content">
                        <h5><?= htmlspecialchars($row['title']); ?></h5>
                        <p><?= htmlspecialchars($row['message']); ?></p>
                        <span>
                            <?= date('M d, Y h:i A', strtotime($row['created_at'])); ?>
                        </span>
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