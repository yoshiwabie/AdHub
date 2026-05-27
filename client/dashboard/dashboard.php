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
$name = $_SESSION['name'];
 
/*
========================================
TOP STATS
========================================
*/
 
$retainerQuery = mysqli_query($conn,"
    SELECT SUM(total_amount) as total_retainer,
           SUM(used_amount) as used_retainer,
           SUM(remaining_amount) as remaining_retainer
    FROM retainers
    WHERE client_id = '$user_id'
");
 
$retainerData = mysqli_fetch_assoc($retainerQuery);
 
$total_retainer     = $retainerData['total_retainer']     ?? 0;
$used_retainer      = $retainerData['used_retainer']      ?? 0;
$remaining_retainer = $retainerData['remaining_retainer'] ?? 0;
 
$retainer_pct = $total_retainer > 0 ? round(($used_retainer / $total_retainer) * 100) : 0;
 
/* CAMPAIGNS */
$campaignQuery = mysqli_query($conn,"
    SELECT COUNT(*) as total
    FROM campaigns
    WHERE client_id = '$user_id'
");
$total_campaigns = mysqli_fetch_assoc($campaignQuery)['total'];
 
/* UPCOMING MILESTONES */
$milestoneQuery = mysqli_query($conn,"
    SELECT COUNT(*) as total
    FROM milestones m
    JOIN campaigns c ON m.campaign_id = c.campaign_id
    WHERE c.client_id = '$user_id'
    AND m.deadline >= CURDATE()
");
$total_upcoming = mysqli_fetch_assoc($milestoneQuery)['total'];
 
/*
========================================
CAMPAIGNS
========================================
*/
$campaigns = mysqli_query($conn,"
    SELECT *
    FROM campaigns
    WHERE client_id = '$user_id'
    ORDER BY deadline ASC
");
 
/*
========================================
UPCOMING MILESTONES
========================================
*/
$milestones = mysqli_query($conn,"
    SELECT m.*, c.campaign_name
    FROM milestones m
    JOIN campaigns c ON m.campaign_id = c.campaign_id
    WHERE c.client_id = '$user_id'
    ORDER BY m.deadline ASC
    LIMIT 5
");
 
/*
========================================
NOTIFICATIONS
========================================
*/
$notifications = mysqli_query($conn,"
    SELECT *
    FROM notifications
    WHERE user_id = '$user_id'
    ORDER BY created_at DESC
    LIMIT 5
");
?>
 
<!DOCTYPE html>
<html lang="en">
<head>
 
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Client Dashboard | AdHub</title>
 
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<link rel="stylesheet" href="/AdHub_V2/assets/css/style.css">
<link rel="stylesheet" href="/AdHub_V2/assets/css/dashboard.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
 
<style>
 
/* ── HERO ── */
.hero-banner {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    border-radius: 24px;
    padding: 30px 35px;
    color: white;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}
 
.hero-banner h1 {
    font-size: 26px;
    font-weight: 700;
    margin: 0 0 6px;
    color: white;
}
 
.hero-banner p {
    margin: 0;
    color: #94a3b8;
    font-size: 14px;
}
 
.hero-date {
    background: rgba(255,255,255,0.08);
    border-radius: 14px;
    padding: 12px 18px;
    text-align: center;
    font-size: 13px;
    color: #cbd5e1;
}
 
.hero-date strong {
    display: block;
    font-size: 22px;
    color: white;
    line-height: 1.2;
}
 
/* ── STAT CARDS ── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 24px;
}
 
.stat-card {
    background: white;
    border-radius: 20px;
    padding: 20px 22px;
    box-shadow: 0 4px 16px rgba(15,23,42,0.06);
    display: flex;
    align-items: center;
    gap: 16px;
    transition: transform 0.2s;
    border-left: 4px solid transparent;
}
 
.stat-card:hover { transform: translateY(-3px); }
 
.stat-card.blue   { border-color: #3b82f6; }
.stat-card.green  { border-color: #22c55e; }
.stat-card.orange { border-color: #f97316; }
 
.stat-icon {
    width: 48px; height: 48px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}
 
.stat-icon.blue   { background: #eff6ff; color: #3b82f6; }
.stat-icon.green  { background: #f0fdf4; color: #22c55e; }
.stat-icon.orange { background: #fff7ed; color: #f97316; }
 
.stat-card h2 {
    font-size: 26px; font-weight: 700;
    color: #0f172a; margin: 0 0 2px;
}
 
.stat-card p {
    font-size: 13px; color: #64748b; margin: 0;
}
 
/* ── DASHBOARD CARD ── */
.dashboard-card {
    background: white;
    border-radius: 24px;
    padding: 24px;
    box-shadow: 0 4px 16px rgba(15,23,42,0.06);
    margin-bottom: 24px;
}
 
.card-header-custom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
 
.card-header-custom h3 {
    font-size: 17px; font-weight: 700;
    color: #0f172a; margin: 0;
}
 
/* ── TABLE ── */
.custom-table thead { background: #f8fafc; }
.custom-table th    { border: none; color: #64748b; font-size: 13px; font-weight: 600; }
.custom-table td    { vertical-align: middle; border-color: #f1f5f9; font-size: 14px; }
.custom-table tbody tr { transition: background 0.15s; cursor: pointer; }
.custom-table tbody tr:hover { background: #f8fafc; }
 
/* ── PROGRESS ── */
.progress { height: 8px; border-radius: 30px; background: #e2e8f0; }
.progress-bar { border-radius: 30px; }
 
.progress-item { margin-bottom: 18px; }
.progress-top {
    display: flex; justify-content: space-between;
    font-size: 13px; font-weight: 600;
    color: #0f172a; margin-bottom: 6px;
}
 
/* ── MILESTONE ITEM ── */
.milestone-item {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 14px 16px;
    margin-bottom: 12px;
    transition: box-shadow 0.15s;
}
 
.milestone-item:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
 
.milestone-item .title {
    font-size: 14px; font-weight: 600;
    color: #0f172a; margin-bottom: 3px;
}
 
.milestone-item .sub {
    font-size: 13px; color: #64748b;
}
 
.milestone-item .deadline {
    font-size: 11px; color: #94a3b8; margin-top: 4px;
}
 
/* ── NOTIFICATION ITEM ── */
.notif-item {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 14px 16px;
    margin-bottom: 12px;
    display: flex;
    gap: 12px;
    align-items: flex-start;
    transition: box-shadow 0.15s;
}
 
.notif-item:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
 
.notif-dot {
    width: 10px; height: 10px;
    border-radius: 50%;
    background: #3b82f6;
    flex-shrink: 0;
    margin-top: 5px;
}
 
.notif-title  { font-size: 14px; font-weight: 600; color: #0f172a; margin-bottom: 3px; }
.notif-msg    { font-size: 13px; color: #64748b; line-height: 1.5; }
.notif-date   { font-size: 11px; color: #94a3b8; margin-top: 4px; }
 
/* ── BUDGET / RETAINER BOXES ── */
.budget-box {
    border-radius: 16px;
    padding: 16px 18px;
    text-align: center;
}
 
.budget-box .label  { font-size: 12px; color: #64748b; margin-bottom: 4px; }
.budget-box .amount { font-size: 18px; font-weight: 700; }
 
/* ── EMPTY STATE ── */
.empty-state { text-align: center; padding: 40px 20px; color: #94a3b8; }
.empty-state i { font-size: 40px; margin-bottom: 10px; display: block; }
.empty-state p { font-size: 13px; margin: 0; }
 
</style>
 
</head>
<body>
 
<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-menu">
 
        <a href="../dashboard/dashboard.php" class="active">
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
 
        <a href="../notifications/notifications.php">
            <i class="fa-regular fa-bell"></i>
            Notifications
        </a>
 
        <a href="../../auth/logout.php">
            <i class="fa-solid fa-right-from-bracket"></i>
            Logout
        </a>
 
    </div>
</div>
 
<!-- MAIN -->
<div class="main-content">
 
    <!-- HERO BANNER -->
    <div class="hero-banner">
        <div>
            <h1>Welcome back, <?= htmlspecialchars($name); ?> 👋</h1>
            <p>Here's your campaign overview and retainer allocation.</p>
        </div>
        <div class="hero-date">
            <strong><?= date('d'); ?></strong>
            <?= date('F Y'); ?>
        </div>
    </div>
 
    <!-- STAT CARDS -->
    <div class="stats-grid">
 
        <div class="stat-card blue">
            <div class="stat-icon blue">
                <i class="fa-solid fa-wallet"></i>
            </div>
            <div>
                <h2>₱<?= number_format($total_retainer, 2); ?></h2>
                <p>Total Retainer</p>
            </div>
        </div>
 
        <div class="stat-card green">
            <div class="stat-icon green">
                <i class="fa-solid fa-layer-group"></i>
            </div>
            <div>
                <h2><?= $total_campaigns; ?></h2>
                <p>Total Campaigns</p>
            </div>
        </div>
 
        <div class="stat-card orange">
            <div class="stat-icon orange">
                <i class="fa-solid fa-list-check"></i>
            </div>
            <div>
                <h2><?= $total_upcoming; ?></h2>
                <p>Upcoming Milestones</p>
            </div>
        </div>
 
    </div>
 
    <!-- ROW 1: CAMPAIGNS + RETAINER CHART -->
    <div class="row g-4 mb-0">
 
        <!-- ACTIVE CAMPAIGNS -->
        <div class="col-lg-7">
            <div class="dashboard-card h-100">
 
                <div class="card-header-custom">
                    <h3><i class="fa-solid fa-layer-group me-2 text-primary"></i>Active Campaigns</h3>
                </div>
 
                <?php
                $campaignRows = [];
                while($row = mysqli_fetch_assoc($campaigns)) $campaignRows[] = $row;
                ?>
 
                <?php if(count($campaignRows) > 0) { ?>
 
                <div style="max-height:360px; overflow-y:auto; padding-right:4px;">
                <?php foreach($campaignRows as $row) {
                    $campaign_id = $row['campaign_id'];
 
                    $progressQuery = mysqli_query($conn,"
                        SELECT COUNT(*) as total, SUM(status='approved') as completed
                        FROM milestones WHERE campaign_id = '$campaign_id'
                    ");
                    $progressData = mysqli_fetch_assoc($progressQuery);
                    $total     = $progressData['total']     ?: 1;
                    $completed = $progressData['completed'] ?: 0;
                    $progress  = round(($completed / $total) * 100);
 
                    $status = strtolower($row['status']);
                    $badgeClass = match($status){
                        'completed' => 'bg-success',
                        'active'    => 'bg-primary',
                        'review'    => 'bg-info',
                        'planning'  => 'bg-warning text-dark',
                        default     => 'bg-secondary'
                    };
 
                    $barColor = $progress == 100 ? 'bg-success'
                        : ($progress >= 50 ? 'bg-primary' : 'bg-warning');
                ?>
                    <div class="progress-item" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:16px; padding:14px 16px; margin-bottom:12px;">
                        <div class="progress-top">
                            <span style="color:var(--primary);">
                                <?= htmlspecialchars($row['campaign_name']); ?>
                            </span>
                            <span class="d-flex align-items-center gap-2">
                                <span class="badge <?= $badgeClass; ?>"><?= ucfirst($row['status']); ?></span>
                                <span style="color:#94a3b8; font-weight:400; font-size:12px;">
                                    <?= date('M d, Y', strtotime($row['deadline'])); ?>
                                </span>
                            </span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar <?= $barColor; ?>" style="width:<?= $progress; ?>%"></div>
                        </div>
                        <div style="text-align:right; font-size:11px; color:#94a3b8; margin-top:5px;">
                            <?= $progress; ?>% milestones completed
                        </div>
                    </div>
                <?php } ?>
                </div>
 
                <?php } else { ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-layer-group"></i>
                        <p>No campaigns found.</p>
                    </div>
                <?php } ?>
 
            </div>
        </div>
 
        <!-- RETAINER ALLOCATION -->
        <div class="col-lg-5">
            <div class="dashboard-card h-100">
 
                <div class="card-header-custom">
                    <h3><i class="fa-solid fa-wallet me-2 text-warning"></i>Retainer Allocation</h3>
                    <span class="badge <?= $retainer_pct >= 90 ? 'bg-danger' : 'bg-primary'; ?>">
                        <?= $retainer_pct; ?>% Used
                    </span>
                </div>
 
                <div style="max-width:220px; margin:auto;">
                    <canvas id="retainerChart"></canvas>
                </div>
 
                <div class="row mt-4 g-2">
 
                    <div class="col-4">
                        <div class="budget-box" style="background:#f8fafc;">
                            <div class="label">Total</div>
                            <div class="amount" style="color:#0f172a;">
                                ₱<?= number_format($total_retainer, 2); ?>
                            </div>
                        </div>
                    </div>
 
                    <div class="col-4">
                        <div class="budget-box" style="background:#fef2f2;">
                            <div class="label">Used</div>
                            <div class="amount" style="color:#dc2626;">
                                ₱<?= number_format($used_retainer, 2); ?>
                            </div>
                        </div>
                    </div>
 
                    <div class="col-4">
                        <div class="budget-box" style="background:#f0fdf4;">
                            <div class="label">Remaining</div>
                            <div class="amount" style="color:#16a34a;">
                                ₱<?= number_format($remaining_retainer, 2); ?>
                            </div>
                        </div>
                    </div>
 
                </div>
 
            </div>
        </div>
 
    </div>
 
    <!-- ROW 2: MILESTONES + NOTIFICATIONS -->
    <div class="row g-4 mt-0">
 
        <!-- UPCOMING MILESTONES -->
        <div class="col-lg-6">
            <div class="dashboard-card h-100">
 
                <div class="card-header-custom">
                    <h3><i class="fa-solid fa-flag me-2 text-success"></i>Upcoming Milestones</h3>
                </div>
 
                <?php
                $milestoneRows = [];
                while($row = mysqli_fetch_assoc($milestones)) $milestoneRows[] = $row;
                ?>
 
                <?php if(count($milestoneRows) > 0) { ?>
                    <?php foreach($milestoneRows as $row) { ?>
                    <div class="milestone-item">
                        <div class="title"><?= htmlspecialchars($row['title']); ?></div>
                        <div class="sub"><?= htmlspecialchars($row['campaign_name']); ?></div>
                        <div class="deadline">
                            <i class="fa-regular fa-calendar me-1"></i>
                            Deadline: <?= date('M d, Y', strtotime($row['deadline'])); ?>
                        </div>
                    </div>
                    <?php } ?>
                <?php } else { ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-flag"></i>
                        <p>No upcoming milestones.</p>
                    </div>
                <?php } ?>
 
            </div>
        </div>
 
        <!-- NOTIFICATIONS -->
        <div class="col-lg-6">
            <div class="dashboard-card h-100">
 
                <div class="card-header-custom">
                    <h3><i class="fa-regular fa-bell me-2 text-info"></i>Recent Notifications</h3>
                    <a href="../notifications/notifications.php" class="btn btn-sm btn-outline-dark">
                        View All
                    </a>
                </div>
 
                <?php
                $notifRows = [];
                while($row = mysqli_fetch_assoc($notifications)) $notifRows[] = $row;
                ?>
 
                <?php if(count($notifRows) > 0) { ?>
                    <?php foreach($notifRows as $row) { ?>
                    <div class="notif-item">
                        <div class="notif-dot"></div>
                        <div class="flex-grow-1">
                            <div class="notif-title"><?= htmlspecialchars($row['title']); ?></div>
                            <div class="notif-msg"><?= htmlspecialchars($row['message']); ?></div>
                            <div class="notif-date">
                                <i class="fa-regular fa-clock me-1"></i>
                                <?= date('M d, Y h:i A', strtotime($row['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                <?php } else { ?>
                    <div class="empty-state">
                        <i class="fa-regular fa-bell"></i>
                        <p>No notifications yet.</p>
                    </div>
                <?php } ?>
 
            </div>
        </div>
 
    </div>
 
</div>
 
<script>
new Chart(document.getElementById('retainerChart'), {
    type: 'doughnut',
    data: {
        labels: ['Used', 'Remaining'],
        datasets: [{
            data: [<?= $used_retainer; ?>, <?= $remaining_retainer; ?>],
            backgroundColor: ['#ef4444', '#22c55e'],
            borderWidth: 0
        }]
    },
    options: {
        cutout: '70%',
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
</script>
 
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>