<?php
session_start();

include('../../config/db.php');
include('../../includes/topbar.php');

if(!isset($_SESSION['user_id'])){
    header("Location: ../../index.php");
    exit();
}

$campaign_id = $_GET['id'] ?? 0;

/*
========================================
GET CAMPAIGN
========================================
*/
$campaignQuery = mysqli_query($conn,"
    SELECT c.*, u.name as staff_name, cl.name as client_name
    FROM campaigns c
    LEFT JOIN users u  ON c.assigned_staff_id = u.user_id
    LEFT JOIN users cl ON c.client_id = cl.user_id
    WHERE c.campaign_id = '$campaign_id'
");

$campaign = mysqli_fetch_assoc($campaignQuery);
if(!$campaign) die("Campaign not found.");

$campaign_name = $campaign['campaign_name'];
$status        = $campaign['status'];

/*
========================================
MILESTONE STATS
========================================
*/
$milestoneData    = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) as total, SUM(status='approved') as completed
    FROM milestones WHERE campaign_id = '$campaign_id'
"));
$total_milestones     = $milestoneData['total']     ?? 0;
$completed_milestones = $milestoneData['completed'] ?? 0;
$milestone_percentage = $total_milestones > 0
    ? round(($completed_milestones / $total_milestones) * 100) : 0;

/*
========================================
ASSET STATS
========================================
*/
$total_assets = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) as c FROM assets a
    LEFT JOIN milestones m ON a.milestone_id = m.milestone_id
    WHERE m.campaign_id = '$campaign_id'
"))['c'] ?? 0;

$approved_assets = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) as c FROM approvals ap
    LEFT JOIN milestones m ON ap.milestone_id = m.milestone_id
    WHERE m.campaign_id = '$campaign_id' AND ap.status = 'approved'
"))['c'] ?? 0;

/*
========================================
BUDGET
========================================
*/
$total_retainer  = $campaign['budget'] ?? 0;
$used_budget     = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT SUM(cost) as s FROM time_logs WHERE campaign_id = '$campaign_id'
"))['s'] ?? 0;
$remaining_budget  = max(0, $total_retainer - $used_budget);
$budget_percentage = $total_retainer > 0
    ? round(($used_budget / $total_retainer) * 100) : 0;

/*
========================================
TOTAL HOURS
========================================
*/
$total_hours = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT SUM(hours) as s FROM time_logs WHERE campaign_id = '$campaign_id'
"))['s'] ?? 0;

/*
========================================
MILESTONES TABLE
========================================
*/
$milestones = mysqli_query($conn,"
    SELECT * FROM milestones
    WHERE campaign_id = '$campaign_id'
    ORDER BY milestone_id ASC
");

/*
========================================
TIME LOG SUMMARY
========================================
*/
$timeLogs = mysqli_query($conn,"
    SELECT u.name, SUM(t.hours) as total_hours, SUM(t.cost) as total_cost
    FROM time_logs t
    LEFT JOIN users u ON t.staff_id = u.user_id
    WHERE t.campaign_id = '$campaign_id'
    GROUP BY t.staff_id
    ORDER BY total_hours DESC
");

/*
========================================
RECENT ASSETS
========================================
*/
$recent_assets = mysqli_query($conn,"
    SELECT a.*, u.name as uploader
    FROM assets a
    LEFT JOIN users u      ON a.uploaded_by  = u.user_id
    LEFT JOIN milestones m ON a.milestone_id = m.milestone_id
    WHERE m.campaign_id = '$campaign_id'
    ORDER BY a.uploaded_at DESC
    LIMIT 5
");

/*
========================================
STATUS CONFIG
========================================
*/
$statusColors = [
    'planning'  => ['bg' => '#eff6ff', 'color' => '#2563eb', 'label' => 'Planning'],
    'active'    => ['bg' => '#fff7ed', 'color' => '#ea580c', 'label' => 'Active'],
    'review'    => ['bg' => '#faf5ff', 'color' => '#7c3aed', 'label' => 'Review'],
    'completed' => ['bg' => '#f0fdf4', 'color' => '#16a34a', 'label' => 'Completed'],
];
$sc = $statusColors[$status] ?? ['bg' => '#f1f5f9', 'color' => '#64748b', 'label' => ucfirst($status)];

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($campaign_name); ?> Report | AdHub</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<link rel="stylesheet" href="/AdHub_V2/assets/css/style.css">
<link rel="stylesheet" href="/AdHub_V2/assets/css/dashboard.css">

<style>

.main-content{
    margin-left:260px;
    padding:35px;
}

/* HEADER BANNER */
.page-banner{
    background:linear-gradient(135deg,#1e293b,#334155);
    border-radius:24px;
    padding:26px 32px;
    color:white;
    margin-bottom:24px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:14px;
}

.page-banner h1{
    font-size:26px;
    font-weight:700;
    color:white;
    margin:0 0 5px;
}

.page-banner p{ margin:0; color:#94a3b8; font-size:14px; }

/* HEADER BANNER */
.report-banner{
    background:linear-gradient(135deg,#1e293b,#334155);
    border-radius:24px;
    padding:28px 35px;
    color:white;
    margin-bottom:24px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:16px;
}

.report-banner h1{
    font-size:26px;
    font-weight:700;
    color:white;
    margin:0 0 6px;
}

.report-banner p{
    margin:0;
    color:#94a3b8;
    font-size:14px;
}

.report-meta{
    display:flex;
    gap:20px;
    flex-wrap:wrap;
}

.meta-pill{
    background:rgba(255,255,255,0.1);
    border-radius:12px;
    padding:10px 16px;
    font-size:13px;
    color:#e2e8f0;
    display:flex;
    align-items:center;
    gap:8px;
}

/* KPI CARDS */
.kpi-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:18px;
    margin-bottom:24px;
}

.kpi-card{
    background:white;
    border-radius:20px;
    padding:22px;
    box-shadow:0 4px 16px rgba(15,23,42,0.06);
    border-top:4px solid transparent;
    transition:transform 0.2s;
}

.kpi-card:hover{ transform:translateY(-3px); }
.kpi-card.blue  { border-color:#3b82f6; }
.kpi-card.green { border-color:#22c55e; }
.kpi-card.orange{ border-color:#f97316; }
.kpi-card.purple{ border-color:#a855f7; }

.kpi-icon{
    width:42px; height:42px;
    border-radius:12px;
    display:flex; align-items:center; justify-content:center;
    font-size:17px; margin-bottom:14px;
}

.kpi-icon.blue  { background:#eff6ff; color:#3b82f6; }
.kpi-icon.green { background:#f0fdf4; color:#22c55e; }
.kpi-icon.orange{ background:#fff7ed; color:#f97316; }
.kpi-icon.purple{ background:#faf5ff; color:#a855f7; }

.kpi-label{ font-size:13px; color:#64748b; margin-bottom:6px; }

.kpi-value{
    font-size:28px; font-weight:700; color:#0f172a; margin-bottom:6px;
}

.kpi-sub{ font-size:12px; color:#94a3b8; }

/* SECTION CARD */
.section-card{
    background:white;
    border-radius:22px;
    padding:26px;
    box-shadow:0 4px 16px rgba(15,23,42,0.06);
    margin-bottom:22px;
}

.section-title{
    font-size:17px;
    font-weight:700;
    color:#0f172a;
    margin-bottom:20px;
    padding-bottom:12px;
    border-bottom:1px solid #f1f5f9;
    display:flex;
    align-items:center;
    gap:10px;
}

/* BUDGET BOXES */
.budget-grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:14px;
    margin-bottom:20px;
}

.budget-box{
    border-radius:16px;
    padding:18px;
    text-align:center;
}

.budget-box .b-label{ font-size:12px; color:#64748b; margin-bottom:6px; }
.budget-box .b-value{ font-size:22px; font-weight:700; }

/* TABLE */
.custom-table thead{ background:#f8fafc; }
.custom-table th{ border:none; color:#64748b; font-size:13px; font-weight:600; }
.custom-table td{ vertical-align:middle; border-color:#f1f5f9; font-size:14px; }
.custom-table tbody tr:hover{ background:#f8fafc; }

/* PROGRESS */
.progress{ height:10px; border-radius:30px; background:#e2e8f0; }
.progress-bar{ border-radius:30px; }

/* ACTIVITY */
.activity-item{
    display:flex;
    gap:14px;
    padding:14px 0;
    border-bottom:1px solid #f1f5f9;
    align-items:center;
}

.activity-item:last-child{ border:none; }

.activity-icon{
    width:42px; height:42px;
    border-radius:12px;
    background:#eff6ff;
    color:#3b82f6;
    display:flex; align-items:center; justify-content:center;
    flex-shrink:0;
    font-size:16px;
}

.activity-name{ font-size:14px; font-weight:600; color:#0f172a; margin-bottom:3px; }
.activity-meta{ font-size:13px; color:#64748b; }

/* EMPTY */
.empty-state{
    text-align:center; padding:40px 20px; color:#94a3b8;
}
.empty-state i{ font-size:40px; margin-bottom:10px; display:block; }

/* PRINT */
@media print {

    .sidebar, .topbar, .no-print{ display:none !important; }

    .main-content{ margin:0 !important; padding:20px !important; }

    .report-banner{
        background:#1e293b !important;
        -webkit-print-color-adjust:exact;
        print-color-adjust:exact;
    }

    .kpi-card, .section-card{ box-shadow:none !important; border:1px solid #e5e7eb; }

    .kpi-grid{ grid-template-columns:repeat(2,1fr); }
    .budget-grid{ grid-template-columns:repeat(3,1fr); }

    .progress{ height:8px; }

    table th{ background:#f3f4f6 !important; -webkit-print-color-adjust:exact; }
    table td{ font-size:11px; padding:8px; border-bottom:1px solid #e5e7eb; }
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

        <a href="../kanban/main_board.php" class="active">
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

    <!-- BANNER -->
    <div class="report-banner">

        <div>
            <h1>
                <i class="fa-solid fa-file-chart-column me-2"></i>
                <?= htmlspecialchars($campaign_name); ?>
            </h1>
            <p>Campaign performance report &mdash; Generated on <?= date('F d, Y'); ?></p>
        </div>

        <div class="report-meta">

            <div class="meta-pill">
                <i class="fa-regular fa-user"></i>
                <?= htmlspecialchars($campaign['client_name'] ?? 'No Client'); ?>
            </div>

            <div class="meta-pill">
                <i class="fa-regular fa-calendar"></i>
                <?= date('M d', strtotime($campaign['start_date'])); ?>
                &mdash;
                <?= date('M d, Y', strtotime($campaign['deadline'])); ?>
            </div>

            <div class="meta-pill" style="background:<?= $sc['bg']; ?>; color:<?= $sc['color']; ?>;">
                <?= $sc['label']; ?>
            </div>

            <button onclick="window.print()"
                    class="btn btn-light btn-sm no-print">
                <i class="fa-solid fa-print me-1"></i> Print Report
            </button>

        </div>

    </div>

    <!-- KPI CARDS -->
    <div class="kpi-grid">

        <div class="kpi-card blue">
            <div class="kpi-icon blue"><i class="fa-solid fa-flag"></i></div>
            <div class="kpi-label">Milestone Completion</div>
            <div class="kpi-value"><?= $milestone_percentage; ?>%</div>
            <div class="progress">
                <div class="progress-bar" style="width:<?= $milestone_percentage; ?>%; background:#3b82f6;"></div>
            </div>
            <div class="kpi-sub mt-2"><?= $completed_milestones; ?> of <?= $total_milestones; ?> completed</div>
        </div>

        <div class="kpi-card green">
            <div class="kpi-icon green"><i class="fa-solid fa-file-arrow-up"></i></div>
            <div class="kpi-label">Total Assets</div>
            <div class="kpi-value"><?= $total_assets; ?></div>
            <div class="kpi-sub">Uploaded campaign files</div>
        </div>

        <div class="kpi-card orange">
            <div class="kpi-icon orange"><i class="fa-solid fa-circle-check"></i></div>
            <div class="kpi-label">Approved Assets</div>
            <div class="kpi-value"><?= $approved_assets; ?></div>
            <div class="kpi-sub">Client approved</div>
        </div>

        <div class="kpi-card purple">
            <div class="kpi-icon purple"><i class="fa-regular fa-clock"></i></div>
            <div class="kpi-label">Hours Logged</div>
            <div class="kpi-value"><?= number_format($total_hours, 1); ?>h</div>
            <div class="kpi-sub">Combined staff hours</div>
        </div>

    </div>

    <!-- FINANCIAL SUMMARY -->
    <div class="section-card">

        <div class="section-title">
            <i class="fa-solid fa-peso-sign text-warning"></i>
            Financial Summary
        </div>

        <div class="budget-grid">

            <div class="budget-box" style="background:#f8fafc;">
                <div class="b-label">Total Budget</div>
                <div class="b-value" style="color:#0f172a;">
                    ₱<?= number_format($total_retainer, 2); ?>
                </div>
            </div>

            <div class="budget-box" style="background:#fef2f2;">
                <div class="b-label">Budget Used</div>
                <div class="b-value" style="color:#dc2626;">
                    ₱<?= number_format($used_budget, 2); ?>
                </div>
            </div>

            <div class="budget-box" style="background:#f0fdf4;">
                <div class="b-label">Remaining</div>
                <div class="b-value" style="color:#16a34a;">
                    ₱<?= number_format($remaining_budget, 2); ?>
                </div>
            </div>

        </div>

        <div class="d-flex justify-content-between mb-2" style="font-size:13px;">
            <span class="text-muted">Budget Usage</span>
            <strong><?= $budget_percentage; ?>%
                <?php if($budget_percentage >= 90){ ?>
                    <span class="badge bg-danger ms-1">Over Budget Risk</span>
                <?php } ?>
            </strong>
        </div>

        <div class="progress">
            <div class="progress-bar <?= $budget_percentage >= 90 ? 'bg-danger' : 'bg-primary'; ?>"
                 style="width:<?= $budget_percentage; ?>%">
            </div>
        </div>

    </div>

    <!-- MILESTONES -->
    <div class="section-card">

        <div class="section-title">
            <i class="fa-solid fa-list-check text-success"></i>
            Milestones Breakdown
        </div>

        <?php if(mysqli_num_rows($milestones) > 0){ ?>

        <table class="table custom-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Milestone</th>
                    <th>Status</th>
                    <th>Deadline</th>
                </tr>
            </thead>
            <tbody>
            <?php $i = 1; while($mile = mysqli_fetch_assoc($milestones)) {
                $mStatus = $mile['status'];
                $mBadge  = match($mStatus){
                    'approved'  => 'bg-success',
                    'revision'  => 'bg-warning text-dark',
                    default     => 'bg-secondary'
                };
            ?>
                <tr>
                    <td class="text-muted"><?= $i++; ?></td>
                    <td><strong><?= htmlspecialchars($mile['title']); ?></strong></td>
                    <td><span class="badge <?= $mBadge; ?>"><?= ucfirst($mStatus); ?></span></td>
                    <td><?= date('M d, Y', strtotime($mile['deadline'])); ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>

        <?php } else { ?>
            <div class="empty-state">
                <i class="fa-solid fa-flag"></i>
                <p>No milestones added yet.</p>
            </div>
        <?php } ?>

    </div>

    <!-- TIME LOGS -->
    <div class="section-card">

        <div class="section-title">
            <i class="fa-regular fa-clock text-primary"></i>
            Time Log Summary
        </div>

        <?php if(mysqli_num_rows($timeLogs) > 0){ ?>

        <table class="table custom-table">
            <thead>
                <tr>
                    <th>Staff Member</th>
                    <th>Total Hours</th>
                    <th>Total Cost</th>
                    <th>Cost Share</th>
                </tr>
            </thead>
            <tbody>
            <?php while($log = mysqli_fetch_assoc($timeLogs)) {
                $share = $used_budget > 0
                    ? round(($log['total_cost'] / $used_budget) * 100) : 0;
            ?>
                <tr>
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="
                                width:32px; height:32px; border-radius:50%;
                                background:#e2e8f0; color:#475569;
                                display:flex; align-items:center; justify-content:center;
                                font-weight:700; font-size:13px;
                            ">
                                <?= strtoupper(substr($log['name'], 0, 1)); ?>
                            </div>
                            <?= htmlspecialchars($log['name']); ?>
                        </div>
                    </td>
                    <td><?= number_format($log['total_hours'], 1); ?>h</td>
                    <td>₱<?= number_format($log['total_cost'], 2); ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height:6px;">
                                <div class="progress-bar bg-primary"
                                     style="width:<?= $share; ?>%"></div>
                            </div>
                            <small><?= $share; ?>%</small>
                        </div>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>

        <?php } else { ?>
            <div class="empty-state">
                <i class="fa-regular fa-clock"></i>
                <p>No time logs recorded yet.</p>
            </div>
        <?php } ?>

    </div>

    <!-- RECENT ASSETS -->
    <div class="section-card">

        <div class="section-title">
            <i class="fa-solid fa-photo-film text-info"></i>
            Recent Asset Activity
        </div>

        <?php if(mysqli_num_rows($recent_assets) > 0){ ?>

            <?php while($asset = mysqli_fetch_assoc($recent_assets)) {
                $ext = strtoupper(pathinfo($asset['file_path'], PATHINFO_EXTENSION));
            ?>

            <div class="activity-item">

                <div class="activity-icon">
                    <i class="fa-solid fa-file-arrow-up"></i>
                </div>

                <div class="flex-grow-1">
                    <div class="activity-name">
                        <?= htmlspecialchars(basename($asset['file_path'])); ?>
                    </div>
                    <div class="activity-meta">
                        Uploaded by <strong><?= htmlspecialchars($asset['uploader']); ?></strong>
                        on <?= date('M d, Y h:i A', strtotime($asset['uploaded_at'])); ?>
                    </div>
                </div>

                <span style="
                    background:#1e293b; color:white;
                    font-size:10px; font-weight:700;
                    padding:3px 10px; border-radius:20px;
                    text-transform:uppercase; letter-spacing:0.5px;
                ">
                    <?= $ext; ?>
                </span>

            </div>

            <?php } ?>

        <?php } else { ?>
            <div class="empty-state">
                <i class="fa-regular fa-folder-open"></i>
                <p>No recent asset activity.</p>
            </div>
        <?php } ?>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>