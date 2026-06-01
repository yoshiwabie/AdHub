<?php
session_start();

include('../../config/db.php');
include('../../config/queries.php');
include('../../includes/topbar.php');

if(!isset($_SESSION['user_id'])){
    header("Location: index.php");
    exit();
}

if($_SESSION['role'] != 'staff'){
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$name    = $_SESSION['name'];

/*
========================================
STATS
========================================
*/
$total_campaigns  = countTable($conn, "campaigns");
$total_assets     = countTable($conn, "assets");
$total_feedbacks  = countTable($conn, "approvals");
$total_milestones = countTable($conn, "milestones");

$recent_campaigns  = getRecentCampaigns($conn);
$approved_assets   = getApprovedAssets($conn);
$revision_assets   = getRevisionAssets($conn);
$milestone_progress = getMilestoneProgress($conn);

/*
========================================
BUDGET
========================================
*/
$budgetData  = mysqli_query($conn,"
    SELECT MONTH(log_date) as month, SUM(cost) as total_cost
    FROM time_logs
    GROUP BY MONTH(log_date)
    ORDER BY MONTH(log_date)
");

$monthlyCosts = array_fill(0, 12, 0);
while($row = mysqli_fetch_assoc($budgetData)){
    $monthlyCosts[(int)$row['month'] - 1] = (float)$row['total_cost'];
}

$total_spent  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT SUM(cost) as s FROM time_logs"))['s'] ?? 0;
$total_budget = mysqli_fetch_assoc(mysqli_query($conn,"SELECT SUM(total_amount) as s FROM retainers"))['s'] ?? 0;
$remaining    = max(0, $total_budget - $total_spent);
$budget_pct   = $total_budget > 0 ? round(($total_spent / $total_budget) * 100) : 0;

/*
========================================
NOTIFICATIONS
========================================
*/
$notificationsQuery = mysqli_query($conn,"
    SELECT * FROM notifications
    WHERE user_id = '$user_id' OR user_id IS NULL
    ORDER BY created_at DESC
    LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard | AdHub</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<link rel="stylesheet" href="/AdHub_V2/assets/css/style.css">
<link rel="stylesheet" href="/AdHub_V2/assets/css/dashboard.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>

/* HERO */
.hero-banner{
    background:linear-gradient(135deg, #1e293b 0%, #334155 100%);
    border-radius:24px;
    padding:30px 35px;
    color:white;
    margin-bottom:24px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:16px;
}

.hero-banner h1{
    font-size:26px;
    font-weight:700;
    margin:0 0 6px;
    color:white;
}

.hero-banner p{
    margin:0;
    color:#94a3b8;
    font-size:14px;
}

.hero-date{
    background:rgba(255,255,255,0.08);
    border-radius:14px;
    padding:12px 18px;
    text-align:center;
    font-size:13px;
    color:#cbd5e1;
}

.hero-date strong{
    display:block;
    font-size:22px;
    color:white;
    line-height:1.2;
}

/* STAT CARDS */
.stats-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:18px;
    margin-bottom:24px;
}

.stat-card{
    background:white;
    border-radius:20px;
    padding:20px 22px;
    box-shadow:0 4px 16px rgba(15,23,42,0.06);
    display:flex;
    align-items:center;
    gap:16px;
    transition:transform 0.2s;
    border-left:4px solid transparent;
}

.stat-card:hover{ transform:translateY(-3px); }

.stat-card.blue  { border-color:#3b82f6; }
.stat-card.green { border-color:#22c55e; }
.stat-card.orange{ border-color:#f97316; }
.stat-card.red   { border-color:#ef4444; }

.stat-icon{
    width:48px; height:48px;
    border-radius:14px;
    display:flex; align-items:center; justify-content:center;
    font-size:20px;
    flex-shrink:0;
}

.stat-icon.blue  { background:#eff6ff; color:#3b82f6; }
.stat-icon.green { background:#f0fdf4; color:#22c55e; }
.stat-icon.orange{ background:#fff7ed; color:#f97316; }
.stat-icon.red   { background:#fef2f2; color:#ef4444; }

.stat-card h2{
    font-size:26px; font-weight:700;
    color:#0f172a; margin:0 0 2px;
}

.stat-card p{
    font-size:13px; color:#64748b; margin:0;
}

/* DASHBOARD CARD */
.dashboard-card{
    background:white;
    border-radius:24px;
    padding:24px;
    box-shadow:0 4px 16px rgba(15,23,42,0.06);
    margin-bottom:24px;
}

.card-header-custom{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
}

.card-header-custom h3{
    font-size:17px; font-weight:700;
    color:#0f172a; margin:0;
}

/* TABLE */
.custom-table thead{ background:#f8fafc; }
.custom-table th{ border:none; color:#64748b; font-size:13px; font-weight:600; }
.custom-table td{ vertical-align:middle; border-color:#f1f5f9; font-size:14px; }
.custom-table tbody tr{ transition:background 0.15s; cursor:pointer; }
.custom-table tbody tr:hover{ background:#f8fafc; }

/* PROGRESS */
.progress{ height:8px; border-radius:30px; background:#e2e8f0; }
.progress-bar{ border-radius:30px; background:var(--primary); }

.progress-item{ margin-bottom:18px; }
.progress-top{
    display:flex; justify-content:space-between;
    font-size:13px; font-weight:600;
    color:#0f172a; margin-bottom:6px;
}

/* NOTIFICATION ITEM */
.notif-item{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:16px;
    padding:14px 16px;
    margin-bottom:12px;
    display:flex;
    gap:12px;
    align-items:flex-start;
    transition:box-shadow 0.15s;
}

.notif-item:hover{ box-shadow:0 4px 12px rgba(0,0,0,0.05); }

.notif-dot{
    width:10px; height:10px;
    border-radius:50%;
    background:#3b82f6;
    flex-shrink:0;
    margin-top:5px;
}

.notif-title{
    font-size:14px; font-weight:600;
    color:#0f172a; margin-bottom:3px;
}

.notif-msg{
    font-size:13px; color:#64748b;
    line-height:1.5;
}

.notif-date{ font-size:11px; color:#94a3b8; margin-top:4px; }

/* BUDGET BOXES */
.budget-box{
    border-radius:16px;
    padding:16px 18px;
    text-align:center;
}

.budget-box .label{ font-size:12px; color:#64748b; margin-bottom:4px; }
.budget-box .amount{ font-size:18px; font-weight:700; }

/* EMPTY STATE */
.empty-state{
    text-align:center; padding:40px 20px; color:#94a3b8;
}
.empty-state i{ font-size:40px; margin-bottom:10px; display:block; }
.empty-state p{ font-size:13px; margin:0; }

</style>

</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-menu">

        <a href="dashboard.php" class="active">
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
            <p>Here's an overview of your campaigns, assets, and activity.</p>
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
                <i class="fa-solid fa-layer-group"></i>
            </div>
            <div>
                <h2><?= $total_campaigns; ?></h2>
                <p>Total Campaigns</p>
            </div>
        </div>

        <div class="stat-card green">
            <div class="stat-icon green">
                <i class="fa-solid fa-file-arrow-up"></i>
            </div>
            <div>
                <h2><?= $total_assets; ?></h2>
                <p>Uploaded Assets</p>
            </div>
        </div>

        <div class="stat-card orange">
            <div class="stat-icon orange">
                <i class="fa-solid fa-list-check"></i>
            </div>
            <div>
                <h2><?= $total_milestones; ?></h2>
                <p>Milestones</p>
            </div>
        </div>

        <div class="stat-card red">
            <div class="stat-icon red">
                <i class="fa-regular fa-comments"></i>
            </div>
            <div>
                <h2><?= $total_feedbacks; ?></h2>
                <p>Feedback Entries</p>
            </div>
        </div>

    </div>

    <!-- ROW 1: CAMPAIGNS + MILESTONES -->
    <div class="row g-4 mb-0">

        <!-- RECENT CAMPAIGNS -->
        <div class="col-lg-7">
            <div class="dashboard-card h-100">

                <div class="card-header-custom">
                    <h3><i class="fa-solid fa-layer-group me-2 text-primary"></i>Recent Campaigns</h3>
                </div>

                <?php if(mysqli_num_rows($recent_campaigns) > 0){ ?>

                <table class="table custom-table">
                    <thead>
                        <tr>
                            <th>Campaign</th>
                            <th>Status</th>
                            <th>Deadline</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($row = mysqli_fetch_assoc($recent_campaigns)) {
                        $status = strtolower($row['status']);
                        $badgeClass = match($status){
                            'completed' => 'bg-success',
                            'active'    => 'bg-primary',
                            'review'    => 'bg-info',
                            'planning'  => 'bg-warning text-dark',
                            default     => 'bg-secondary'
                        };
                    ?>
                        <tr onclick="window.location='../campaigns/campaign_details.php?id=<?= $row['campaign_id']; ?>'">
                            <td>
                                <div style="font-weight:600; color:var(--primary);">
                                    <?= htmlspecialchars($row['campaign_name']); ?>
                                </div>
                            </td>
                            <td><span class="badge <?= $badgeClass; ?>"><?= ucfirst($row['status']); ?></span></td>
                            <td><?= date('M d, Y', strtotime($row['deadline'])); ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>

                <?php } else { ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-layer-group"></i>
                        <p>No campaigns found.</p>
                    </div>
                <?php } ?>

            </div>
        </div>

        <!-- MILESTONE PROGRESS -->
        <div class="col-lg-5">
            <div class="dashboard-card h-100">

                <div class="card-header-custom">
                    <h3><i class="fa-solid fa-flag me-2 text-success"></i>Milestone Progress</h3>
                </div>

                <?php
                $mpRows = [];
                while($row = mysqli_fetch_assoc($milestone_progress)) $mpRows[] = $row;
                ?>

                <?php if(count($mpRows) > 0){ ?>

                    <div style="max-height:320px; overflow-y:auto; padding-right:4px;">
                    <?php foreach($mpRows as $row){
                        $total   = $row['total'] ?: 1;
                        $done    = $row['done']  ?: 0;
                        $percent = round(($done / $total) * 100);

                        $barColor = $percent == 100 ? 'bg-success'
                            : ($percent >= 50 ? 'bg-primary' : 'bg-warning');
                    ?>
                        <div class="progress-item">
                            <div class="progress-top">
                                <span><?= htmlspecialchars($row['campaign_name']); ?></span>
                                <span><?= $percent; ?>%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar <?= $barColor; ?>"
                                     style="width:<?= $percent; ?>%"></div>
                            </div>
                        </div>
                    <?php } ?>
                    </div>

                <?php } else { ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-list-check"></i>
                        <p>No milestones yet.</p>
                    </div>
                <?php } ?>

            </div>
        </div>

    </div>

    <!-- ROW 2: APPROVED + REVISION -->
    <div class="row g-4 mt-0">

        <div class="col-lg-6">
            <div class="dashboard-card h-100">

                <div class="card-header-custom">
                    <h3><i class="fa-solid fa-circle-check me-2 text-success"></i>Approved Assets</h3>
                </div>

                <?php if(mysqli_num_rows($approved_assets) > 0){ ?>

                <table class="table custom-table">
                    <thead>
                        <tr><th>Asset</th><th>Campaign</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php while($row = mysqli_fetch_assoc($approved_assets)) { ?>
                        <tr>
                            <td>
                                <i class="fa-regular fa-file me-1 text-muted"></i>
                                <?= htmlspecialchars(basename($row['file_path'])); ?>
                            </td>
                            <td><?= htmlspecialchars($row['campaign_name']); ?></td>
                            <td><span class="badge bg-success">Approved</span></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>

                <?php } else { ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-circle-check"></i>
                        <p>No approved assets yet.</p>
                    </div>
                <?php } ?>

            </div>
        </div>

        <div class="col-lg-6">
            <div class="dashboard-card h-100">

                <div class="card-header-custom">
                    <h3><i class="fa-solid fa-rotate-right me-2 text-warning"></i>Assets In Revision</h3>
                </div>

                <?php if(mysqli_num_rows($revision_assets) > 0){ ?>

                <table class="table custom-table">
                    <thead>
                        <tr><th>Asset</th><th>Campaign</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php while($row = mysqli_fetch_assoc($revision_assets)) { ?>
                        <tr>
                            <td>
                                <i class="fa-regular fa-file me-1 text-muted"></i>
                                <?= htmlspecialchars(basename($row['file_path'])); ?>
                            </td>
                            <td><?= htmlspecialchars($row['campaign_name']); ?>
                            </td>
                            <td><span class="badge bg-warning text-dark">Revision</span></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>

                <?php } else { ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-rotate-right"></i>
                        <p>No assets in revision.</p>
                    </div>
                <?php } ?>

            </div>
        </div>

    </div>

    <!-- ROW 3: BUDGET + NOTIFICATIONS -->
    <div class="row g-4 mt-0">

        <!-- BUDGET -->
        <div class="col-lg-6">
            <div class="dashboard-card h-100">

                <div class="card-header-custom">
                    <h3><i class="fa-solid fa-peso-sign me-2 text-warning"></i>Budget Utilization</h3>
                    <span class="badge <?= $budget_pct >= 90 ? 'bg-danger' : 'bg-primary'; ?>">
                        <?= $budget_pct; ?>% Used
                    </span>
                </div>

                <div style="max-width:260px; margin:auto;">
                    <canvas id="budgetUtilizationChart"></canvas>
                </div>

                <div class="row mt-4 g-2">

                    <div class="col-4">
                        <div class="budget-box" style="background:#f8fafc;">
                            <div class="label">Total Budget</div>
                            <div class="amount" style="color:#0f172a;">
                                ₱<?= number_format($total_budget, 2); ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-4">
                        <div class="budget-box" style="background:#fef2f2;">
                            <div class="label">Total Spent</div>
                            <div class="amount" style="color:#dc2626;">
                                ₱<?= number_format($total_spent, 2); ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-4">
                        <div class="budget-box" style="background:#f0fdf4;">
                            <div class="label">Remaining</div>
                            <div class="amount" style="color:#16a34a;">
                                ₱<?= number_format($remaining, 2); ?>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>

        <!-- NOTIFICATIONS -->
        <div class="col-lg-6">
            <div class="dashboard-card h-100">

                <div class="card-header-custom">
                    <h3><i class="fa-regular fa-bell me-2 text-info"></i>Recent Notifications</h3>
                    <a href="../notifications/notifications.php"
                       class="btn btn-sm btn-outline-dark">
                        View All
                    </a>
                </div>

                <?php if(mysqli_num_rows($notificationsQuery) > 0){ ?>

                    <?php while($notif = mysqli_fetch_assoc($notificationsQuery)) { ?>

                        <div class="notif-item">
                            <div class="notif-dot"></div>
                            <div class="flex-grow-1">
                                <div class="notif-title">
                                    <?= htmlspecialchars($notif['title']); ?>
                                </div>
                                <div class="notif-msg">
                                    <?= htmlspecialchars($notif['message']); ?>
                                </div>
                                <div class="notif-date">
                                    <i class="fa-regular fa-clock me-1"></i>
                                    <?= date('M d, Y h:i A', strtotime($notif['created_at'])); ?>
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
new Chart(document.getElementById('budgetUtilizationChart'), {
    type: 'doughnut',
    data: {
        labels: ['Used', 'Remaining'],
        datasets: [{
            data: [<?= $total_spent; ?>, <?= $remaining; ?>],
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