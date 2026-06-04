<?php
session_start();

include('../../config/db.php');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client'){
    header("Location: ../../index.php");
    exit();
}

$campaign_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($campaign_id <= 0){ header("Location: ../campaigns/campaign_details.php"); exit(); }

$backUrl = "../campaigns/campaign_details.php?id=$campaign_id";

/*
========================================
HANDLE SAVE
========================================
*/
$errors = [];
if(isset($_POST['action']) && $_POST['action'] === 'save'){

    $name        = trim(mysqli_real_escape_string($conn, $_POST['campaign_name']));
    $description = trim(mysqli_real_escape_string($conn, $_POST['description']));
    $budget      = (float)$_POST['budget'];
    $deadline    = mysqli_real_escape_string($conn, $_POST['deadline']);
    $start_date  = mysqli_real_escape_string($conn, $_POST['start_date']);

    if(empty($name))     $errors[] = "Campaign name is required.";
    if($budget < 0)      $errors[] = "Budget cannot be negative.";
    if(empty($deadline)) $errors[] = "Deadline is required.";
    if(!empty($start_date) && !empty($deadline) && $deadline < $start_date)
        $errors[] = "Deadline cannot be before the start date.";

    if(empty($errors)){
        mysqli_query($conn, "
            UPDATE campaigns SET
                campaign_name = '$name',
                description   = '$description',
                budget        = '$budget',
                deadline      = '$deadline',
                start_date    = '$start_date'
            WHERE campaign_id = '$campaign_id'
        ");
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Campaign updated successfully.'];
        header("Location: edit_campaign.php?id=$campaign_id&saved=1");
        exit();
    }
}

/*
========================================
LOAD CAMPAIGN
========================================
*/
$query = mysqli_query($conn, "
    SELECT c.*, u.name as client_name
    FROM campaigns c
    LEFT JOIN users u ON c.client_id = u.user_id
    WHERE c.campaign_id = '$campaign_id'
    LIMIT 1
");

if(!$query){ die("Query failed: " . mysqli_error($conn)); }

$row = mysqli_fetch_assoc($query);

if(!$row){ header("Location: ../campaigns/campaign_details.php?id=$campaign_id"); exit(); }

if($row['client_id'] != $_SESSION['user_id']){
    header("Location: ../campaigns/campaign_details.php?id=$campaign_id");
    exit();
}

$flash      = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$isArchived = ($row['status'] === 'archived');

include('../../includes/topbar.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Campaign | AdHub</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<link rel="stylesheet" href="/AdHub_V2/assets/css/style.css">
<link rel="stylesheet" href="/AdHub_V2/assets/css/dashboard.css">

<style>
.main-content { margin-left: 260px; padding: 35px; }

.page-banner {
    background: linear-gradient(135deg, #1e293b, #334155);
    border-radius: 20px;
    padding: 26px 32px;
    color: white;
    margin-bottom: 28px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 14px;
}
.page-banner h1 { font-size: 24px; font-weight: 700; margin: 0 0 4px; color: white; }
.page-banner p  { margin: 0; color: #94a3b8; font-size: 13px; }

.archived-ribbon {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #fff7ed;
    border: 1.5px solid #fed7aa;
    border-radius: 14px;
    padding: 13px 18px;
    margin-bottom: 22px;
    font-size: 13px;
    color: #92400e;
    font-weight: 600;
}
.archived-ribbon i { font-size: 18px; color: #f97316; }

.form-card {
    background: white;
    border-radius: 22px;
    box-shadow: 0 4px 20px rgba(15,23,42,.07);
    overflow: hidden;
    margin-bottom: 24px;
}
.form-card-header {
    padding: 18px 24px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    gap: 10px;
}
.form-card-header h3 { font-size: 16px; font-weight: 700; color: #0f172a; margin: 0; }
.form-card-header .icon {
    width: 34px; height: 34px;
    border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px;
}
.form-card-body { padding: 26px 24px; }

.form-label { font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 6px; text-transform: uppercase; letter-spacing: .04em; }

.form-control, .form-select {
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    padding: 11px 14px;
    font-size: 14px;
    color: #0f172a;
    transition: border-color .15s, box-shadow .15s;
}
.form-control:focus, .form-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,.12);
    outline: none;
}
.form-control:disabled {
    background: #f8fafc;
    color: #94a3b8;
    cursor: not-allowed;
}
textarea.form-control { resize: vertical; min-height: 100px; }

.input-prefix-wrap { position: relative; }
.input-prefix-wrap .prefix {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    font-size: 14px; font-weight: 600; color: #64748b; pointer-events: none;
}
.input-prefix-wrap .form-control { padding-left: 30px; }

.action-bar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

.btn-save {
    background: #1F3A93;
    border: none;
    color: white;
    padding: 11px 24px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: background .15s, transform .12s;
}
.btn-save:hover { background: #162d7a; transform: translateY(-1px); }

.btn-cancel {
    color: #64748b;
    text-decoration: none;
    padding: 11px 20px;
    border-radius: 12px;
    border: 1.5px solid #e2e8f0;
    font-size: 14px;
    font-weight: 500;
    transition: background .12s;
    display: inline-flex;
    align-items: center;
    gap: 7px;
}
.btn-cancel:hover { background: #f8fafc; color: #374151; }

.flash-alert {
    border-radius: 14px;
    font-size: 13px;
    padding: 13px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 22px;
}
.flash-success { background: #f0fdf4; color: #15803d; border: 1.5px solid #bbf7d0; }
.flash-warning { background: #fffbeb; color: #92400e; border: 1.5px solid #fcd34d; }
.flash-error   { background: #fef2f2; color: #dc2626; border: 1.5px solid #fecaca; }

.error-box {
    background: #fef2f2;
    border: 1.5px solid #fecaca;
    border-radius: 14px;
    padding: 14px 18px;
    margin-bottom: 22px;
    font-size: 13px;
    color: #b91c1c;
}
.error-box ul { margin: 6px 0 0 16px; padding: 0; }
.error-box li { margin-bottom: 3px; }

.client-badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
    border-radius: 30px;
    padding: 6px 14px;
    font-size: 12px;
    font-weight: 600;
}
</style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-menu">
        <a href="../dashboard/dashboard.php"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
        <a href="../kanban/main_board.php" class="active"><i class="fa-solid fa-layer-group"></i> Campaigns</a>
        <a href="../retainer/retainer.php"><i class="fa-solid fa-wallet"></i> Retainer</a>
        <a href="../notifications/notifications.php"><i class="fa-regular fa-bell"></i> Notifications</a>
        <a href="../../auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
</div>

<div class="main-content">

    <div class="page-banner">
        <div>
            <h1><i class="fa-solid fa-pen-to-square me-2"></i>Edit Campaign</h1>
            <p>Update campaign details, budget, and deadline.</p>
        </div>
        <a href="<?= $backUrl; ?>" class="btn-cancel">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>

    <?php if($flash): ?>
        <div class="flash-alert flash-<?= $flash['type']; ?>">
            <i class="fa-solid fa-<?= $flash['type'] === 'success' ? 'circle-check' : 'triangle-exclamation'; ?>"></i>
            <?= htmlspecialchars($flash['msg']); ?>
        </div>
    <?php endif; ?>

    <?php if($isArchived): ?>
        <div class="archived-ribbon">
            <i class="fa-solid fa-box-archive"></i>
            This campaign is currently <strong>archived</strong>. Editing is disabled.
        </div>
    <?php endif; ?>

    <?php if(!empty($errors)): ?>
        <div class="error-box">
            <strong><i class="fa-solid fa-circle-exclamation me-1"></i> Please fix the following:</strong>
            <ul>
                <?php foreach($errors as $e): ?>
                    <li><?= htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" id="editForm">
        <input type="hidden" name="action" value="save">

        <div class="form-card">
            <div class="form-card-header">
                <div class="icon" style="background:#eff6ff; color:#3b82f6;">
                    <i class="fa-solid fa-bullhorn"></i>
                </div>
                <h3>Campaign Details</h3>
                <div class="ms-auto">
                    <span class="client-badge">
                        <i class="fa-regular fa-user"></i>
                        Client: <?= htmlspecialchars($row['client_name'] ?? 'N/A'); ?>
                    </span>
                </div>
            </div>
            <div class="form-card-body">
                <div class="row g-4">
                    <div class="col-12">
                        <label class="form-label">Campaign Name</label>
                        <input type="text" name="campaign_name" class="form-control"
                               value="<?= htmlspecialchars($row['campaign_name'] ?? ''); ?>"
                               placeholder="e.g. Summer Launch 2026"
                               <?= $isArchived ? 'disabled' : ''; ?> required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control"
                                  placeholder="Campaign goals, deliverables, notes…"
                                  <?= $isArchived ? 'disabled' : ''; ?>><?= htmlspecialchars($row['description'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-header">
                <div class="icon" style="background:#f0fdf4; color:#22c55e;">
                    <i class="fa-regular fa-calendar"></i>
                </div>
                <h3>Schedule &amp; Budget</h3>
            </div>
            <div class="form-card-body">
                <div class="row g-4">
                    <div class="col-md-4">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control"
                               value="<?= htmlspecialchars($row['start_date'] ?? ''); ?>"
                               <?= $isArchived ? 'disabled' : ''; ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Deadline</label>
                        <input type="date" name="deadline" class="form-control"
                               value="<?= htmlspecialchars($row['deadline'] ?? ''); ?>"
                               <?= $isArchived ? 'disabled' : ''; ?> required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Budget (₱)</label>
                        <div class="input-prefix-wrap">
                            <span class="prefix">₱</span>
                            <input type="number" step="0.01" min="0"
                                   name="budget" class="form-control"
                                   value="<?= htmlspecialchars($row['budget'] ?? '0'); ?>"
                                   placeholder="0.00"
                                   <?= $isArchived ? 'disabled' : ''; ?>>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if(!$isArchived): ?>
        <div class="action-bar mb-4">
            <button type="submit" class="btn-save">
                <i class="fa-solid fa-floppy-disk"></i> Save Changes
            </button>
            <a href="<?= $backUrl; ?>" class="btn-cancel">
                <i class="fa-solid fa-xmark"></i> Cancel
            </a>
        </div>
        <?php endif; ?>

    </form>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>