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

$user_id     = $_SESSION['user_id'];
$campaign_id = $_GET['id'] ?? 0;

/*
========================================
BLOCK UNASSIGNED STAFF
========================================
*/
$checkQuery    = mysqli_query($conn, "SELECT * FROM campaigns WHERE campaign_id = '$campaign_id' LIMIT 1");
$checkCampaign = mysqli_fetch_assoc($checkQuery);

if(!$checkCampaign || $checkCampaign['assigned_staff_id'] != $user_id){
    header("Location: ../../index.php");
    exit();
}

/*
========================================
NOTIFY CLIENT - REVISION DONE
========================================
*/
if(isset($_POST['notify_client'])){

    $approval_id = $_POST['approval_id'] ?? null;
    $client_id   = $_POST['client_id']   ?? null;

    if(!$approval_id || !$client_id){
        die("Missing POST data");
    }

    $approvalRow = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT a.milestone_id, m.title
        FROM approvals a
        JOIN milestones m ON a.milestone_id = m.milestone_id
        WHERE a.approval_id = $approval_id
        LIMIT 1
    "));

    if(!$approvalRow){
        die("Approval not found");
    }

    $milestone_title = mysqli_real_escape_string($conn, $approvalRow['title']);

    mysqli_query($conn,"
        UPDATE approvals SET notified = 1 WHERE approval_id = $approval_id
    ");

    mysqli_query($conn,"
        INSERT INTO notifications (user_id, title, message, role, created_at)
        VALUES (
            $client_id,
            'Revision Completed',
            'The revision for milestone \"$milestone_title\" has been completed. Please review.',
            'client',
            NOW()
        )
    ");

    header("Location: campaign_details.php?id=$campaign_id&notified=1");
    exit();
}

/*
========================================
GET CAMPAIGN
========================================
*/
$campaignQuery = mysqli_query($conn,"
    SELECT c.*, u.name as staff_name
    FROM campaigns c
    LEFT JOIN users u ON c.assigned_staff_id = u.user_id
    WHERE c.campaign_id = '$campaign_id'
    LIMIT 1
");

$campaign = mysqli_fetch_assoc($campaignQuery);

if(!$campaign){
    die("Campaign not found.");
}

/*
========================================
MILESTONES
========================================
*/
$milestones = mysqli_query($conn,"
    SELECT * FROM milestones
    WHERE campaign_id = '$campaign_id'
    ORDER BY milestone_id ASC
");

$progressQuery = mysqli_query($conn,"
    SELECT COUNT(*) as total, SUM(status='approved') as done
    FROM milestones
    WHERE campaign_id = '$campaign_id'
");

$progressData    = mysqli_fetch_assoc($progressQuery);
$totalMilestones = $progressData['total'] ?: 1;
$doneMilestones  = $progressData['done']  ?: 0;
$progressPercent = round(($doneMilestones / $totalMilestones) * 100);

/*
========================================
ASSETS
========================================
*/
$assets = mysqli_query($conn,"
    SELECT 
        a.*,
        m.title AS milestone_title,
        u.name  AS uploader_name,
        (SELECT COUNT(*) FROM asset_versions av WHERE av.asset_id = a.asset_id) AS version_count
    FROM assets a
    LEFT JOIN milestones m ON a.milestone_id = m.milestone_id
    LEFT JOIN users u      ON a.uploaded_by  = u.user_id
    WHERE m.campaign_id = '$campaign_id'
    ORDER BY a.uploaded_at DESC
");

/*
========================================
FEEDBACK
========================================
*/
$feedbackQuery = mysqli_query($conn,"
    SELECT a.*, a.notified, u.name
    FROM approvals a
    LEFT JOIN users u ON a.client_id = u.user_id
    WHERE a.milestone_id IN (
        SELECT milestone_id FROM milestones WHERE campaign_id = '$campaign_id'
    )
    AND a.feedback IS NOT NULL AND a.feedback != ''
    ORDER BY a.created_at DESC
");

/*
========================================
BUDGET
========================================
*/
$spentRow    = mysqli_fetch_assoc(mysqli_query($conn,"SELECT SUM(cost) as total_spent FROM time_logs WHERE campaign_id = '$campaign_id'"));
$total_spent = $spentRow['total_spent'] ?? 0;
$budget      = $campaign['budget'] ?? 0;
$remaining   = max(0, $budget - $total_spent);
$budget_percent = ($budget > 0) ? round(($total_spent / $budget) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($campaign['campaign_name']); ?> | AdHub</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<link rel="stylesheet" href="/AdHub_V2/assets/css/style.css">
<link rel="stylesheet" href="/AdHub_V2/assets/css/dashboard.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>

.main-content{
    margin-left:260px;
    margin-top:75px;
    padding:35px;
}

/* HEADER */
.campaign-header{
    background:linear-gradient(135deg,#1e293b,#334155);
    border-radius:24px;
    padding:30px 35px;
    color:white;
    margin-bottom:25px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:20px;
}

.campaign-header h1{
    font-size:28px;
    font-weight:700;
    margin:0 0 6px;
    color:white;
}

.campaign-header p{
    margin:0;
    color:#94a3b8;
    font-size:14px;
}

/* INFO CARDS */
.info-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:18px;
    margin-bottom:25px;
}

.info-card{
    background:white;
    border-radius:20px;
    padding:20px 22px;
    box-shadow:0 4px 16px rgba(15,23,42,0.06);
    border-left:4px solid transparent;
    transition:transform 0.2s;
}

.info-card:hover{ transform:translateY(-2px); }

.info-card.blue  { border-color:#3b82f6; }
.info-card.green { border-color:#22c55e; }
.info-card.orange{ border-color:#f97316; }
.info-card.purple{ border-color:#a855f7; }

.info-icon{
    width:40px;
    height:40px;
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:16px;
    margin-bottom:12px;
}

.info-icon.blue  { background:#eff6ff; color:#3b82f6; }
.info-icon.green { background:#f0fdf4; color:#22c55e; }
.info-icon.orange{ background:#fff7ed; color:#f97316; }
.info-icon.purple{ background:#faf5ff; color:#a855f7; }

.info-title{ font-size:13px; color:#64748b; margin-bottom:4px; }
.info-value{ font-size:20px; font-weight:700; color:#0f172a; }

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
    font-size:18px;
    font-weight:700;
    color:#0f172a;
    margin:0;
}

/* TABLE */
.custom-table thead{ background:#f8fafc; }
.custom-table th{ border:none; color:#64748b; font-size:13px; font-weight:600; }
.custom-table td{ vertical-align:middle; border-color:#f1f5f9; font-size:14px; }
.custom-table tbody tr{ transition:background 0.15s; }
.custom-table tbody tr:hover{ background:#f8fafc; }

/* BADGES */
.version-badge{
    background:#dbeafe;
    color:#1d4ed8;
    padding:4px 10px;
    border-radius:30px;
    font-size:12px;
    font-weight:600;
}

/* PROGRESS */
.progress{ height:8px; border-radius:30px; background:#e2e8f0; }
.progress-bar{ border-radius:30px; }

/* FEEDBACK */
.feedback-card{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:16px;
    padding:16px;
    margin-bottom:14px;
    transition:box-shadow 0.2s;
}

.feedback-card:hover{
    box-shadow:0 4px 14px rgba(0,0,0,0.06);
}

/* EMPTY STATE */
.empty-state{
    text-align:center;
    padding:50px 20px;
    color:#94a3b8;
}

.empty-state i{
    font-size:46px;
    margin-bottom:12px;
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

    <?php if(isset($_GET['notified'])){ ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-check-circle me-2"></i>
            Client has been notified about the completed revision.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php } ?>

    <!-- CAMPAIGN HEADER BANNER -->
    <div class="campaign-header">

        <div>
            <h1><?= htmlspecialchars($campaign['campaign_name']); ?></h1>
            <p><?= htmlspecialchars($campaign['description']); ?></p>
        </div>

        <div class="d-flex gap-2 flex-wrap">

            <?php if($campaign['assigned_staff_id'] == $user_id){ ?>

                <a href="../milestones/add_milestone.php?id=<?= $campaign_id; ?>"
                class="btn btn-light btn-sm">
                    <i class="fa-solid fa-plus"></i> Milestone
                </a>

                <a href="../assets/upload_assets.php?id=<?= $campaign_id; ?>"
                   class="btn btn-success btn-sm">
                    <i class="fa-solid fa-upload"></i> Upload
                </a>

            <?php } else { ?>

                <a href="../milestones/add_milestone.php?id=<?= $campaign_id; ?>"
                    class="btn btn-light btn-sm">
                    <i class="fa-solid fa-plus"></i> Milestone
                </a>

                <button class="btn btn-secondary btn-sm" disabled
                        title="You are not assigned to this campaign">
                    <i class="fa-solid fa-upload"></i> Upload
                </button>

            <?php } ?>

            <a href="../campaigns/review_asset.php?campaign_id=<?= $campaign_id; ?>"
               class="btn btn-outline-light btn-sm">
                <i class="fa-solid fa-eye"></i> Review Assets
            </a>

            <a href="../reports/campaign_report.php?id=<?= $campaign_id; ?>"
               class="btn btn-warning btn-sm">
                <i class="fa-solid fa-file-pdf"></i> Report
            </a>

        </div>

    </div>

    <!-- INFO CARDS -->
    <div class="info-grid">

        <div class="info-card blue">
            <div class="info-icon blue">
                <i class="fa-regular fa-calendar"></i>
            </div>
            <div class="info-title">Duration</div>
            <div class="info-value" style="font-size:15px;">
                <?= date('M d', strtotime($campaign['start_date'])); ?>
                —
                <?= date('M d, Y', strtotime($campaign['deadline'])); ?>
            </div>
        </div>

        <div class="info-card green">
            <div class="info-icon green">
                <i class="fa-solid fa-user-tie"></i>
            </div>
            <div class="info-title">Assigned Staff</div>
            <div class="info-value" style="font-size:16px;">
                <?= htmlspecialchars($campaign['staff_name'] ?? 'Unassigned'); ?>
            </div>
        </div>

        <div class="info-card orange">
            <div class="info-icon orange">
                <i class="fa-solid fa-peso-sign"></i>
            </div>
            <div class="info-title">Budget</div>
            <div class="info-value">₱<?= number_format($budget, 2); ?></div>
        </div>

        <div class="info-card purple">
            <div class="info-icon purple">
                <i class="fa-solid fa-chart-line"></i>
            </div>
            <div class="info-title">Progress</div>
            <div class="info-value"><?= $progressPercent; ?>%</div>
        </div>

    </div>

    <!-- UPLOADS TABLE -->
    <div class="dashboard-card">

        <div class="card-header-custom">
            <h3><i class="fa-solid fa-photo-film me-2 text-primary"></i>Uploaded Assets</h3>
            <?php if($campaign['assigned_staff_id'] == $user_id){ ?>
                <a href="../assets/upload_assets.php?id=<?= $campaign_id; ?>"
                   class="btn btn-success btn-sm">
                    <i class="fa-solid fa-plus"></i> Add Asset
                </a>
            <?php } ?>
        </div>

        <?php if(mysqli_num_rows($assets) > 0){ ?>

        <table class="table custom-table">
            <thead>
                <tr>
                    <th>File</th>
                    <th>Milestone</th>
                    <th>Version</th>
                    <th>Uploaded</th>
                    <th>By</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php while($asset = mysqli_fetch_assoc($assets)) {

                $statusRow = mysqli_fetch_assoc(mysqli_query($conn,"
                    SELECT status FROM approvals
                    WHERE milestone_id = '".$asset['milestone_id']."'
                    ORDER BY approval_id DESC LIMIT 1
                "));
                $status = $statusRow['status'] ?? 'pending';

                $badgeClass = 'bg-secondary';
                if($status == 'approved') $badgeClass = 'bg-success';
                elseif($status == 'revision') $badgeClass = 'bg-warning text-dark';
            ?>
                <tr>
                    <td>
                        <i class="fa-regular fa-file me-1 text-muted"></i>
                        <?= htmlspecialchars(basename($asset['file_path'])); ?>
                    </td>
                    <td><?= htmlspecialchars($asset['milestone_title']); ?></td>
                    <td><span class="version-badge">v<?= $asset['version_count'] ?: 1; ?></span></td>
                    <td><?= date('M d, Y', strtotime($asset['uploaded_at'])); ?></td>
                    <td><?= htmlspecialchars($asset['uploader_name'] ?? 'Unknown'); ?></td>
                    <td><span class="badge <?= $badgeClass; ?>"><?= ucfirst($status); ?></span></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>

        <?php } else { ?>
            <div class="empty-state">
                <i class="fa-regular fa-folder-open"></i>
                <p>No assets uploaded yet.</p>
            </div>
        <?php } ?>

    </div>

    <!-- MILESTONES -->
    <div class="dashboard-card">

        <div class="card-header-custom">
            <h3><i class="fa-solid fa-list-check me-2 text-success"></i>Milestones</h3>
            <span class="badge bg-success fs-6"><?= $progressPercent; ?>% Complete</span>
        </div>

        <div class="progress mb-4">
            <div class="progress-bar bg-success"
                 style="width:<?= $progressPercent; ?>%">
            </div>
        </div>

        <?php if(mysqli_num_rows($milestones) > 0){ ?>

        <table class="table custom-table">
            <thead>
                <tr>
                    <th>Milestone</th>
                    <th>Status</th>
                    <th>Deadline</th>
                </tr>
            </thead>
            <tbody>
            <?php mysqli_data_seek($milestones, 0);
            while($mile = mysqli_fetch_assoc($milestones)) {

                $mBadge = 'bg-secondary';
                if($mile['status'] == 'approved') $mBadge = 'bg-success';
                elseif($mile['status'] == 'revision') $mBadge = 'bg-warning text-dark';
            ?>
                <tr>
                    <td><strong><?= htmlspecialchars($mile['title']); ?></strong></td>
                    <td><span class="badge <?= $mBadge; ?>"><?= ucfirst($mile['status']); ?></span></td>
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

    <!-- BOTTOM ROW -->
    <div class="row g-4">

        <!-- BUDGET -->
        <div class="col-md-6">
            <div class="dashboard-card h-100">

                <div class="card-header-custom">
                    <h3><i class="fa-solid fa-peso-sign me-2 text-warning"></i>Budget Utilization</h3>
                    <span class="badge <?= $budget_percent >= 90 ? 'bg-danger' : 'bg-primary'; ?>">
                        <?= $budget_percent; ?>% Used
                    </span>
                </div>

                <div style="max-width:280px; margin:auto;">
                    <canvas id="budgetChart"></canvas>
                </div>

                <div class="mt-4">

                    <div class="d-flex justify-content-between align-items-center py-2"
                         style="border-bottom:1px solid #f1f5f9;">
                        <span class="text-muted">Total Budget</span>
                        <strong>₱<?= number_format($budget, 2); ?></strong>
                    </div>

                    <div class="d-flex justify-content-between align-items-center py-2"
                         style="border-bottom:1px solid #f1f5f9;">
                        <span class="text-muted">Total Spent</span>
                        <strong class="text-danger">₱<?= number_format($total_spent, 2); ?></strong>
                    </div>

                    <div class="d-flex justify-content-between align-items-center py-2">
                        <span class="text-muted">Remaining</span>
                        <strong class="text-success">₱<?= number_format($remaining, 2); ?></strong>
                    </div>

                </div>

            </div>
        </div>

        <!-- FEEDBACK -->
        <div class="col-md-6">
            <div class="dashboard-card h-100">

                <div class="card-header-custom">
                    <h3><i class="fa-solid fa-comments me-2 text-info"></i>Client Feedback</h3>
                    <span class="badge bg-secondary">
                        <?= mysqli_num_rows($feedbackQuery); ?> entries
                    </span>
                </div>

                <div style="max-height:420px; overflow-y:auto;">

                    <?php if(mysqli_num_rows($feedbackQuery) > 0){ ?>

                        <?php while($fb = mysqli_fetch_assoc($feedbackQuery)) { ?>

                            <div class="feedback-card">

                                <div class="d-flex justify-content-between align-items-center mb-2">

                                    <div class="d-flex align-items-center gap-2">
                                        <div style="
                                            width:34px;height:34px;
                                            border-radius:50%;
                                            background:#e2e8f0;
                                            display:flex;align-items:center;justify-content:center;
                                            font-weight:700;font-size:14px;color:#475569;
                                        ">
                                            <?= strtoupper(substr($fb['name'], 0, 1)); ?>
                                        </div>
                                        <strong><?= htmlspecialchars($fb['name']); ?></strong>
                                    </div>

                                    <span class="badge <?= $fb['status'] == 'revision' ? 'bg-warning text-dark' : 'bg-success'; ?>">
                                        <?= $fb['status'] == 'revision' ? 'Revision' : 'Approved'; ?>
                                    </span>

                                </div>

                                <p class="text-muted mb-2" style="font-size:14px;">
                                    <?= htmlspecialchars($fb['feedback']); ?>
                                </p>

                                <small class="text-secondary">
                                    <i class="fa-regular fa-clock me-1"></i>
                                    <?= date('M d, Y h:i A', strtotime($fb['created_at'])); ?>
                                </small>

                                <?php if($fb['status'] == 'revision'){ ?>
                                <form method="POST" action="campaign_details.php?id=<?= $campaign_id; ?>" class="mt-3">
                                    <input type="hidden" name="notify_client" value="1">
                                    <input type="hidden" name="approval_id"   value="<?= $fb['approval_id']; ?>">
                                    <input type="hidden" name="client_id"      value="<?= $fb['client_id']; ?>">
                                    <button type="submit"
                                            class="btn btn-info btn-sm w-100 mt-2 text-white"
                                            <?= $fb['notified'] ? 'disabled' : ''; ?>>
                                        <i class="fa-solid fa-<?= $fb['notified'] ? 'check' : 'bell'; ?> me-1"></i>
                                        <?= $fb['notified'] ? 'Client Notified' : 'Notify Client — Revision Done'; ?>
                                    </button>
                                </form>
                            <?php } ?>

                            </div>

                        <?php } ?>

                    <?php } else { ?>

                        <div class="empty-state">
                            <i class="fa-regular fa-comment-dots"></i>
                            <p>No client feedback yet.</p>
                        </div>

                    <?php } ?>

                </div>

            </div>
        </div>

    </div>

</div>

<script>
new Chart(document.getElementById('budgetChart'), {
    type: 'doughnut',
    data: {
        labels: ['Spent', 'Remaining'],
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