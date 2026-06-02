<?php
session_start();

include('../../config/db.php');

if(!isset($_SESSION['user_id'])){
    header("Location: ../../index.php");
    exit();
}

if($_SESSION['role'] != 'staff'){
    header("Location: ../../index.php");
    exit();
}

$user_id     = $_SESSION['user_id'];
$campaign_id = intval($_GET['id'] ?? 0);

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
CREATE MILESTONE
========================================
*/
if(isset($_POST['create_milestone'])){

    $title    = mysqli_real_escape_string($conn, trim($_POST['title']));
    $deadline = mysqli_real_escape_string($conn, trim($_POST['deadline']));
    $status   = 'pending';

    if($title && $deadline){
        mysqli_query($conn,"
            INSERT INTO milestones (campaign_id, title, deadline, status)
            VALUES ('$campaign_id', '$title', '$deadline', '$status')
        ");
    }

    header("Location: campaign_details.php?id=$campaign_id&milestone_created=1");
    exit();
}

/*
========================================
EDIT MILESTONE
========================================
*/
if(isset($_POST['edit_milestone'])){

    $milestone_id = intval($_POST['milestone_id']);
    $title        = mysqli_real_escape_string($conn, trim($_POST['title']));
    $deadline     = mysqli_real_escape_string($conn, trim($_POST['deadline']));
    $status       = mysqli_real_escape_string($conn, trim($_POST['status']));

    mysqli_query($conn,"
        UPDATE milestones
        SET title    = '$title',
            deadline = '$deadline',
            status   = '$status'
        WHERE milestone_id = '$milestone_id'
        AND campaign_id    = '$campaign_id'
    ");

    header("Location: campaign_details.php?id=$campaign_id&milestone_updated=1");
    exit();
}

/*
========================================
DELETE MILESTONE
========================================
*/
if(isset($_POST['delete_milestone'])){

    $milestone_id = intval($_POST['milestone_id']);

    mysqli_query($conn,"
        DELETE FROM milestones
        WHERE milestone_id = '$milestone_id'
        AND campaign_id    = '$campaign_id'
    ");

    header("Location: campaign_details.php?id=$campaign_id&milestone_deleted=1");
    exit();
}

/*
========================================
NOTIFY CLIENT - REVISION DONE
========================================
*/
if(isset($_POST['notify_client'])){

    $approval_id = intval($_POST['approval_id'] ?? 0);
    $client_id   = intval($_POST['client_id']   ?? 0);

    if(!$approval_id || !$client_id) die("Missing POST data");

    $approvalRow = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT a.milestone_id, m.title
        FROM approvals a
        JOIN milestones m ON a.milestone_id = m.milestone_id
        WHERE a.approval_id = $approval_id
        LIMIT 1
    "));

    if(!$approvalRow) die("Approval not found");

    $milestone_title = mysqli_real_escape_string($conn, $approvalRow['title']);

    mysqli_query($conn,"UPDATE approvals SET notified = 1 WHERE approval_id = $approval_id");

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
if(!$campaign) die("Campaign not found.");

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

include('../../includes/topbar.php');
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
    padding:35px;
}

/* ── HEADER ── */
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
    font-size:28px;font-weight:700;
    margin:0 0 6px;color:white;
}

.campaign-header p{ margin:0; color:#94a3b8; font-size:14px; }

/* ── INFO CARDS ── */
.info-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:18px;
    margin-bottom:25px;
}

.info-card{
    background:white;border-radius:20px;
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
    width:40px;height:40px;border-radius:12px;
    display:flex;align-items:center;justify-content:center;
    font-size:16px;margin-bottom:12px;
}

.info-icon.blue  { background:#eff6ff; color:#3b82f6; }
.info-icon.green { background:#f0fdf4; color:#22c55e; }
.info-icon.orange{ background:#fff7ed; color:#f97316; }
.info-icon.purple{ background:#faf5ff; color:#a855f7; }

.info-title{ font-size:13px; color:#64748b; margin-bottom:4px; }
.info-value{ font-size:20px; font-weight:700; color:#0f172a; }

/* ── DASHBOARD CARD ── */
.dashboard-card{
    background:white;border-radius:24px;
    padding:24px;
    box-shadow:0 4px 16px rgba(15,23,42,0.06);
    margin-bottom:24px;
}

.card-header-custom{
    display:flex;justify-content:space-between;align-items:center;
    margin-bottom:20px;
}

.card-header-custom h3{ font-size:18px; font-weight:700; color:#0f172a; margin:0; }

/* ── TABLE ── */
.custom-table thead{ background:#f8fafc; }
.custom-table th{ border:none; color:#64748b; font-size:13px; font-weight:600; }
.custom-table td{ vertical-align:middle; border-color:#f1f5f9; font-size:14px; }
.custom-table tbody tr{ transition:background 0.15s; }
.custom-table tbody tr:hover{ background:#f8fafc; }

.version-badge{
    background:#dbeafe;color:#1d4ed8;
    padding:4px 10px;border-radius:30px;
    font-size:12px;font-weight:600;
}

/* ── PROGRESS ── */
.progress{ height:8px; border-radius:30px; background:#e2e8f0; }
.progress-bar{ border-radius:30px; transition:width 0.6s ease; }

/* ── FEEDBACK ── */
.feedback-card{
    background:#f8fafc;border:1px solid #e2e8f0;
    border-radius:16px;padding:16px;margin-bottom:14px;
    transition:box-shadow 0.2s;
}
.feedback-card:hover{ box-shadow:0 4px 14px rgba(0,0,0,0.06); }

/* ── EMPTY STATE ── */
.empty-state{ text-align:center; padding:50px 20px; color:#94a3b8; }
.empty-state i{ font-size:46px; margin-bottom:12px; display:block; }

/* ══════════════════════════════════════
   MILESTONE MANAGER
══════════════════════════════════════ */

/* Add Milestone form panel */
.milestone-form-panel{
    background:#f8fafc;
    border:1.5px dashed #cbd5e1;
    border-radius:18px;
    padding:22px 24px;
    margin-bottom:24px;
    transition:border-color 0.2s;
}

.milestone-form-panel:focus-within{
    border-color:#3b82f6;
    background:#eff6ff;
}

.milestone-form-panel h6{
    font-size:14px;font-weight:700;
    color:#0f172a;margin-bottom:16px;
    display:flex;align-items:center;gap:8px;
}

.form-label-sm{
    font-size:12px;font-weight:600;
    color:#64748b;margin-bottom:5px;
}

.form-control-sm-custom{
    border:1.5px solid #e2e8f0;
    border-radius:10px;
    padding:9px 13px;
    font-size:13px;
    width:100%;
    transition:border-color 0.2s, box-shadow 0.2s;
    background:white;
    color:#0f172a;
}

.form-control-sm-custom:focus{
    outline:none;
    border-color:#3b82f6;
    box-shadow:0 0 0 3px rgba(59,130,246,0.1);
}

.btn-add-milestone{
    background:linear-gradient(135deg,#3b82f6,#2563eb);
    color:white;border:none;
    border-radius:10px;
    padding:10px 20px;
    font-size:13px;font-weight:600;
    cursor:pointer;
    transition:opacity 0.2s,transform 0.15s;
    display:inline-flex;align-items:center;gap:7px;
    white-space:nowrap;
}

.btn-add-milestone:hover{ opacity:0.9; transform:translateY(-1px); }

/* Milestone row actions */
.milestone-actions{
    display:flex;gap:6px;align-items:center;
    flex-wrap:nowrap;
}

.btn-icon{
    border:none;border-radius:9px;
    padding:6px 10px;
    font-size:12px;font-weight:600;
    cursor:pointer;
    display:inline-flex;align-items:center;gap:5px;
    transition:opacity 0.15s,transform 0.15s;
    white-space:nowrap;
}

.btn-icon:hover{ opacity:0.85; transform:translateY(-1px); }

.btn-edit{
    background:#eff6ff;color:#2563eb;
}

.btn-delete{
    background:#fff1f2;color:#e11d48;
}

/* Status badge select inside edit modal */
.status-select{
    border:1.5px solid #e2e8f0;
    border-radius:10px;
    padding:9px 13px;
    font-size:13px;
    width:100%;
    background:white;
    color:#0f172a;
    transition:border-color 0.2s;
}

.status-select:focus{
    outline:none;
    border-color:#3b82f6;
    box-shadow:0 0 0 3px rgba(59,130,246,0.1);
}

/* Modal overrides */
.modal-content{
    border:none;border-radius:24px;
    box-shadow:0 20px 60px rgba(0,0,0,0.15);
}

.modal-header{
    padding:24px 28px 0;border:none;
}

.modal-body{ padding:20px 28px; }

.modal-footer{
    padding:0 28px 24px;border:none;gap:10px;
}

.modal-title{ font-size:17px; font-weight:700; color:#0f172a; }

/* Delete confirm icon */
.delete-icon-wrap{
    width:60px;height:60px;border-radius:50%;
    background:#fff1f2;
    display:flex;align-items:center;justify-content:center;
    font-size:26px;color:#e11d48;
    margin:0 auto 16px;
}

/* Toast */
.toast-fixed{
    position:fixed;top:22px;right:22px;
    z-index:9999;
    padding:14px 22px;
    border-radius:14px;
    font-size:14px;font-weight:600;
    display:flex;align-items:center;gap:10px;
    box-shadow:0 8px 24px rgba(0,0,0,0.14);
    animation:slideInToast 0.3s ease, fadeOutToast 0.4s ease 3.5s forwards;
}

.toast-success{ background:#16a34a; color:white; }
.toast-warning{ background:#d97706; color:white; }
.toast-danger { background:#dc2626; color:white; }

@keyframes slideInToast{
    from{ opacity:0; transform:translateY(-14px); }
    to  { opacity:1; transform:translateY(0); }
}

@keyframes fadeOutToast{
    to{ opacity:0; transform:translateY(-10px); }
}

/* Progress label */
.progress-label{
    display:flex;justify-content:space-between;
    font-size:13px;color:#64748b;margin-bottom:8px;
}

/* Milestone count pill */
.milestone-count{
    background:#f1f5f9;color:#475569;
    border-radius:999px;padding:3px 10px;
    font-size:12px;font-weight:600;
}

</style>
</head>
<body>

<!-- ── TOAST NOTIFICATIONS ── -->
<?php if(isset($_GET['milestone_created'])): ?>
    <div class="toast-fixed toast-success">
        <i class="fa-solid fa-circle-check"></i> Milestone created successfully!
    </div>
<?php elseif(isset($_GET['milestone_updated'])): ?>
    <div class="toast-fixed toast-warning" style="background:#2563eb;">
        <i class="fa-solid fa-pen-to-square"></i> Milestone updated successfully!
    </div>
<?php elseif(isset($_GET['milestone_deleted'])): ?>
    <div class="toast-fixed toast-danger">
        <i class="fa-solid fa-trash"></i> Milestone deleted.
    </div>
<?php elseif(isset($_GET['notified'])): ?>
    <div class="toast-fixed toast-success">
        <i class="fa-solid fa-bell"></i> Client has been notified!
    </div>
<?php endif; ?>

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

    <!-- CAMPAIGN HEADER -->
    <div class="campaign-header">
        <div>
            <h1><?= htmlspecialchars($campaign['campaign_name']); ?></h1>
            <p><?= htmlspecialchars($campaign['description']); ?></p>
        </div>

        <div class="d-flex gap-2 flex-wrap">

            <a href="../campaigns/campaign_notes.php?id=<?= $campaign_id; ?>"
                class="btn btn-info btn-sm text-white">
                    <i class="fa-solid fa-message"></i> Notes
            </a>

            <?php if($campaign['assigned_staff_id'] == $user_id): ?>
                <a href="../assets/upload_assets.php?id=<?= $campaign_id; ?>"
                   class="btn btn-success btn-sm">
                    <i class="fa-solid fa-upload"></i> Upload Asset
                </a>
            <?php else: ?>
                <button class="btn btn-secondary btn-sm" disabled>
                    <i class="fa-solid fa-upload"></i> Upload
                </button>
            <?php endif; ?>

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
            <div class="info-icon blue"><i class="fa-regular fa-calendar"></i></div>
            <div class="info-title">Duration</div>
            <div class="info-value" style="font-size:15px;">
                <?= date('M d', strtotime($campaign['start_date'])); ?> —
                <?= date('M d, Y', strtotime($campaign['deadline'])); ?>
            </div>
        </div>

        <div class="info-card green">
            <div class="info-icon green"><i class="fa-solid fa-user-tie"></i></div>
            <div class="info-title">Assigned Staff</div>
            <div class="info-value" style="font-size:16px;">
                <?= htmlspecialchars($campaign['staff_name'] ?? 'Unassigned'); ?>
            </div>
        </div>

        <div class="info-card orange">
            <div class="info-icon orange"><i class="fa-solid fa-peso-sign"></i></div>
            <div class="info-title">Budget</div>
            <div class="info-value">₱<?= number_format($budget, 2); ?></div>
        </div>

        <div class="info-card purple">
            <div class="info-icon purple"><i class="fa-solid fa-chart-line"></i></div>
            <div class="info-title">Progress</div>
            <div class="info-value"><?= $progressPercent; ?>%</div>
        </div>

    </div>

    <!-- ══════════════════════════════════════
         MILESTONE MANAGER
    ══════════════════════════════════════ -->
    <div class="dashboard-card">

        <div class="card-header-custom">
            <h3>
                <i class="fa-solid fa-list-check me-2 text-success"></i>
                Milestone Manager
                <span class="milestone-count ms-2">
                    <?= mysqli_num_rows($milestones); ?> milestones
                </span>
            </h3>
            <span class="badge bg-success fs-6"><?= $progressPercent; ?>% Complete</span>
        </div>

        <!-- PROGRESS BAR -->
        <div class="progress-label">
            <span><?= $doneMilestones; ?> of <?= $totalMilestones == 1 && $progressData['total'] == 0 ? 0 : $progressData['total']; ?> approved</span>
            <span><?= $progressPercent; ?>%</span>
        </div>
        <div class="progress mb-4">
            <div class="progress-bar bg-success"
                 id="mainProgressBar"
                 style="width:<?= $progressPercent; ?>%">
            </div>
        </div>

        <!-- ── CREATE FORM ── -->
        <div class="milestone-form-panel">
            <h6>
                <i class="fa-solid fa-plus-circle text-primary"></i>
                Add New Milestone
            </h6>
            <form method="POST" action="campaign_details.php?id=<?= $campaign_id; ?>">
                <div class="row g-3 align-items-end">

                    <div class="col-md-5">
                        <div class="form-label-sm">Milestone Title <span class="text-danger">*</span></div>
                        <input type="text"
                               name="title"
                               class="form-control-sm-custom"
                               placeholder="e.g. Initial Design Draft"
                               required>
                    </div>

                    <div class="col-md-4">
                        <div class="form-label-sm">Deadline <span class="text-danger">*</span></div>
                        <input type="date"
                               name="deadline"
                               class="form-control-sm-custom"
                               min="<?= date('Y-m-d'); ?>"
                               required>
                    </div>

                    <div class="col-md-3">
                        <button type="submit"
                                name="create_milestone"
                                class="btn-add-milestone w-100">
                            <i class="fa-solid fa-plus"></i>
                            Add Milestone
                        </button>
                    </div>

                </div>
            </form>
        </div>

        <!-- ── MILESTONE TABLE ── -->
        <?php
        mysqli_data_seek($milestones, 0);
        $milestoneCount = mysqli_num_rows($milestones);
        ?>

        <?php if($milestoneCount > 0): ?>

        <table class="table custom-table">
            <thead>
                <tr>
                    <th style="width:40%;">Milestone</th>
                    <th>Status</th>
                    <th>Deadline</th>
                    <th>Days Left</th>
                    <th style="width:160px;">Actions</th>
                </tr>
            </thead>
            <tbody>

            <?php while($mile = mysqli_fetch_assoc($milestones)):

                $mBadge = 'bg-secondary';
                $mBadgeText = 'Pending';
                if($mile['status'] == 'approved'){
                    $mBadge = 'bg-success';
                    $mBadgeText = 'Approved';
                } elseif($mile['status'] == 'revision'){
                    $mBadge = 'bg-warning text-dark';
                    $mBadgeText = 'Revision';
                }

                $deadline   = new DateTime($mile['deadline']);
                $today      = new DateTime();
                $daysLeft   = (int)$today->diff($deadline)->format('%r%a');
                $daysLabel  = $daysLeft > 0
                    ? "<span style='color:#16a34a;font-weight:600;'>+$daysLeft days</span>"
                    : ($daysLeft == 0
                        ? "<span style='color:#d97706;font-weight:600;'>Today</span>"
                        : "<span style='color:#dc2626;font-weight:600;'>".abs($daysLeft)." overdue</span>");
            ?>
            <tr>

                <td>
                    <strong style="font-size:14px;">
                        <?= htmlspecialchars($mile['title']); ?>
                    </strong>
                </td>

                <td>
                    <span class="badge <?= $mBadge; ?>"><?= $mBadgeText; ?></span>
                </td>

                <td style="font-size:13px; color:#475569;">
                    <?= date('M d, Y', strtotime($mile['deadline'])); ?>
                </td>

                <td style="font-size:13px;">
                    <?= $daysLabel; ?>
                </td>

                <td>
                    <div class="milestone-actions">

                        <!-- EDIT BUTTON -->
                        <button class="btn-icon btn-edit"
                                onclick="openEditModal(
                                    <?= $mile['milestone_id']; ?>,
                                    '<?= htmlspecialchars(addslashes($mile['title'])); ?>',
                                    '<?= $mile['deadline']; ?>',
                                    '<?= $mile['status']; ?>'
                                )">
                            <i class="fa-solid fa-pen-to-square"></i>
                            Edit
                        </button>

                        <!-- DELETE BUTTON -->
                        <button class="btn-icon btn-delete"
                                onclick="openDeleteModal(
                                    <?= $mile['milestone_id']; ?>,
                                    '<?= htmlspecialchars(addslashes($mile['title'])); ?>'
                                )">
                            <i class="fa-solid fa-trash"></i>
                            Delete
                        </button>

                    </div>
                </td>

            </tr>
            <?php endwhile; ?>

            </tbody>
        </table>

        <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-flag" style="color:#cbd5e1;"></i>
                <p>No milestones yet. Add your first one above.</p>
            </div>
        <?php endif; ?>

    </div>

    <!-- UPLOADS TABLE -->
    <div class="dashboard-card">

        <div class="card-header-custom">
            <h3><i class="fa-solid fa-photo-film me-2 text-primary"></i>Uploaded Assets</h3>
            <?php if($campaign['assigned_staff_id'] == $user_id): ?>
                <a href="../assets/upload_assets.php?id=<?= $campaign_id; ?>"
                   class="btn btn-success btn-sm">
                    <i class="fa-solid fa-plus"></i> Add Asset
                </a>
            <?php endif; ?>
        </div>

        <?php if(mysqli_num_rows($assets) > 0): ?>

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
            <?php while($asset = mysqli_fetch_assoc($assets)):

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
            <?php endwhile; ?>
            </tbody>
        </table>

        <?php else: ?>
            <div class="empty-state">
                <i class="fa-regular fa-folder-open"></i>
                <p>No assets uploaded yet.</p>
            </div>
        <?php endif; ?>

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

                <?php if(mysqli_num_rows($feedbackQuery) > 0): ?>

                    <?php while($fb = mysqli_fetch_assoc($feedbackQuery)): ?>

                        <div class="feedback-card">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div style="width:34px;height:34px;border-radius:50%;background:#e2e8f0;
                                                display:flex;align-items:center;justify-content:center;
                                                font-weight:700;font-size:14px;color:#475569;">
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

                            <?php if($fb['status'] == 'revision'): ?>
                            <form method="POST" action="campaign_details.php?id=<?= $campaign_id; ?>" class="mt-3">
                                <input type="hidden" name="notify_client" value="1">
                                <input type="hidden" name="approval_id"   value="<?= $fb['approval_id']; ?>">
                                <input type="hidden" name="client_id"     value="<?= $fb['client_id']; ?>">
                                <button type="submit"
                                        class="btn btn-info btn-sm w-100 mt-2 text-white"
                                        <?= $fb['notified'] ? 'disabled' : ''; ?>>
                                    <i class="fa-solid fa-<?= $fb['notified'] ? 'check' : 'bell'; ?> me-1"></i>
                                    <?= $fb['notified'] ? 'Client Notified' : 'Notify Client — Revision Done'; ?>
                                </button>
                            </form>
                            <?php endif; ?>

                        </div>

                    <?php endwhile; ?>

                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa-regular fa-comment-dots"></i>
                        <p>No client feedback yet.</p>
                    </div>
                <?php endif; ?>

                </div>

            </div>
        </div>

    </div>

</div><!-- /main-content -->


<!-- ══════════════════════════════════════
     EDIT MILESTONE MODAL
══════════════════════════════════════ -->
<div class="modal fade" id="editMilestoneModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <form method="POST" action="campaign_details.php?id=<?= $campaign_id; ?>">
                <input type="hidden" name="edit_milestone" value="1">
                <input type="hidden" name="milestone_id" id="edit_milestone_id">

                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fa-solid fa-pen-to-square text-primary me-2"></i>
                        Edit Milestone
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label-sm d-block mb-1">
                            Milestone Title <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               name="title"
                               id="edit_title"
                               class="form-control-sm-custom"
                               required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label-sm d-block mb-1">
                            Deadline <span class="text-danger">*</span>
                        </label>
                        <input type="date"
                               name="deadline"
                               id="edit_deadline"
                               class="form-control-sm-custom"
                               required>
                    </div>

                    <div class="mb-1">
                        <label class="form-label-sm d-block mb-1">
                            Status <span class="text-danger">*</span>
                        </label>
                        <select name="status" id="edit_status" class="status-select" required>
                            <option value="pending">Pending</option>
                            <option value="revision">Revision</option>
                            <option value="approved">Approved</option>
                        </select>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-light px-4"
                            data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit"
                            class="btn btn-primary px-4 fw-bold">
                        <i class="fa-solid fa-save me-1"></i>
                        Save Changes
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>


<!-- ══════════════════════════════════════
     DELETE MILESTONE MODAL
══════════════════════════════════════ -->
<div class="modal fade" id="deleteMilestoneModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <form method="POST" action="campaign_details.php?id=<?= $campaign_id; ?>">
                <input type="hidden" name="delete_milestone" value="1">
                <input type="hidden" name="milestone_id" id="delete_milestone_id">

                <div class="modal-header">
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body text-center pb-0">

                    <div class="delete-icon-wrap">
                        <i class="fa-solid fa-trash"></i>
                    </div>

                    <h5 style="font-weight:700;color:#0f172a;margin-bottom:10px;">
                        Delete Milestone?
                    </h5>

                    <p style="color:#64748b;font-size:14px;margin-bottom:0;">
                        You're about to delete
                        <strong id="delete_milestone_name" style="color:#0f172a;"></strong>.
                        <br>This action cannot be undone.
                    </p>

                </div>

                <div class="modal-footer justify-content-center">
                    <button type="button"
                            class="btn btn-light px-4"
                            data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit"
                            class="btn btn-danger px-4 fw-bold">
                        <i class="fa-solid fa-trash me-1"></i>
                        Yes, Delete
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>


<script>

/* ── Open Edit Modal ── */
function openEditModal(id, title, deadline, status){
    document.getElementById('edit_milestone_id').value = id;
    document.getElementById('edit_title').value        = title;
    document.getElementById('edit_deadline').value     = deadline;
    document.getElementById('edit_status').value       = status;
    new bootstrap.Modal(document.getElementById('editMilestoneModal')).show();
}

/* ── Open Delete Modal ── */
function openDeleteModal(id, title){
    document.getElementById('delete_milestone_id').value   = id;
    document.getElementById('delete_milestone_name').textContent = title;
    new bootstrap.Modal(document.getElementById('deleteMilestoneModal')).show();
}

/* ── Auto-dismiss toast after 4s ── */
setTimeout(() => {
    const toast = document.querySelector('.toast-fixed');
    if(toast) toast.style.animation = 'fadeOutToast 0.4s ease forwards';
}, 4000);

/* ── Budget Chart ── */
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
        plugins: { legend: { position: 'bottom' } }
    }
});

</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>