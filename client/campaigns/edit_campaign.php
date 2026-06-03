<?php
session_start();

include('../../config/db.php');

// Allow both staff and client
if(!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['staff', 'client'])){
    header("Location: ../../index.php");
    exit();
}

$campaign_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($campaign_id <= 0){ header("Location: ../kanban/main_board.php"); exit(); }

$isClient = $_SESSION['role'] === 'client';
$backUrl  = $isClient
    ? "../campaigns/campaign_details.php?id=$campaign_id"
    : "../kanban/main_board.php";

/*
========================================
FETCH STAFF LIST (staff only)
========================================
*/
$staffList = null;
if(!$isClient){
    $staffList = mysqli_query($conn, "SELECT user_id, name FROM users WHERE role = 'staff' ORDER BY name ASC");
}

/*
========================================
HANDLE ARCHIVE
========================================
*/
if(isset($_POST['action']) && $_POST['action'] === 'archive' && !$isClient){
    mysqli_query($conn, "UPDATE campaigns SET status = 'archived' WHERE campaign_id = '$campaign_id'");
    $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'Campaign archived successfully.'];
    header("Location: ../kanban/main_board.php");
    exit();
}

/*
========================================
HANDLE UNARCHIVE
========================================
*/
if(isset($_POST['action']) && $_POST['action'] === 'unarchive' && !$isClient){
    mysqli_query($conn, "UPDATE campaigns SET status = 'planning' WHERE campaign_id = '$campaign_id'");
    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Campaign restored to Planning.'];
    header("Location: edit_campaign.php?id=$campaign_id");
    exit();
}

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

    // Clients cannot change assigned staff — preserve existing value
    if(!$isClient){
        $staff_id = (int)$_POST['assigned_staff_id'];
    }

    if(empty($name))        $errors[] = "Campaign name is required.";
    if($budget < 0)         $errors[] = "Budget cannot be negative.";
    if(empty($deadline))    $errors[] = "Deadline is required.";
    if(!empty($start_date) && !empty($deadline) && $deadline < $start_date)
        $errors[] = "Deadline cannot be before the start date.";

    if(empty($errors)){
        if($isClient){
            // Clients: update only name, description, budget, dates
            mysqli_query($conn, "
                UPDATE campaigns SET
                    campaign_name = '$name',
                    description   = '$description',
                    budget        = '$budget',
                    deadline      = '$deadline',
                    start_date    = '$start_date'
                WHERE campaign_id = '$campaign_id'
            ");
        } else {
            // Staff: full update including assigned staff
            mysqli_query($conn, "
                UPDATE campaigns SET
                    campaign_name     = '$name',
                    description       = '$description',
                    budget            = '$budget',
                    deadline          = '$deadline',
                    start_date        = '$start_date',
                    assigned_staff_id = '$staff_id'
                WHERE campaign_id = '$campaign_id'
            ");
        }
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
$row = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT c.*, u.name as client_name
    FROM campaigns c
    LEFT JOIN users u ON c.client_id = u.user_id
    WHERE c.campaign_id = '$campaign_id'
    LIMIT 1
"));

if(!$row){ header("Location: ../kanban/main_board.php"); exit(); }

// Extra security: clients can only edit their own campaigns
if($isClient && $row['client_id'] != $_SESSION['user_id']){
    header("Location: ../kanban/main_board.php");
    exit();
}

$flash = $_SESSION['flash'] ?? null;
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

/* ── PAGE HEADER ── */
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

/* ── ARCHIVED RIBBON ── */
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

/* ── FORM CARD ── */
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

/* ── FORM CONTROLS ── */
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
.form-control:disabled, .form-select:disabled {
    background: #f8fafc;
    color: #94a3b8;
    cursor: not-allowed;
}
textarea.form-control { resize: vertical; min-height: 100px; }

/* ── BUDGET INPUT ── */
.input-prefix-wrap { position: relative; }
.input-prefix-wrap .prefix {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    font-size: 14px; font-weight: 600; color: #64748b; pointer-events: none;
}
.input-prefix-wrap .form-control { padding-left: 30px; }

/* ── ACTION BAR ── */
.action-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

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

/* ── ARCHIVE / DANGER ZONE ── */
.danger-zone {
    background: white;
    border-radius: 22px;
    box-shadow: 0 4px 20px rgba(15,23,42,.07);
    overflow: hidden;
    border: 1.5px solid #fecaca;
}
.danger-zone-header {
    background: #fef2f2;
    padding: 16px 24px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.danger-zone-header h3 { font-size: 15px; font-weight: 700; color: #dc2626; margin: 0; }
.danger-zone-body { padding: 22px 24px; }

.archive-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 14px;
}
.archive-desc h5 { font-size: 14px; font-weight: 700; color: #0f172a; margin: 0 0 4px; }
.archive-desc p  { font-size: 13px; color: #64748b; margin: 0; }

.btn-archive {
    background: #dc2626;
    border: none;
    color: white;
    padding: 10px 22px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: background .15s;
}
.btn-archive:hover { background: #b91c1c; }

.btn-unarchive {
    background: #16a34a;
    border: none;
    color: white;
    padding: 10px 22px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: background .15s;
}
.btn-unarchive:hover { background: #15803d; }

/* ── CONFIRM MODAL ── */
.confirm-modal-bg {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.45);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}
.confirm-modal-bg.open { display: flex; }

.confirm-modal {
    background: white;
    border-radius: 20px;
    width: 420px;
    max-width: 94vw;
    box-shadow: 0 20px 60px rgba(0,0,0,.2);
    overflow: hidden;
    animation: modalIn .22s ease;
}
@keyframes modalIn { from { opacity:0; transform: scale(.94) translateY(10px); } to { opacity:1; transform: scale(1) translateY(0); } }

.confirm-modal-icon {
    width: 60px; height: 60px;
    border-radius: 50%;
    background: #fef2f2;
    display: flex; align-items: center; justify-content: center;
    font-size: 26px; color: #dc2626;
    margin: 32px auto 16px;
}
.confirm-modal h4  { font-size: 18px; font-weight: 700; text-align: center; color: #0f172a; margin: 0 24px 8px; }
.confirm-modal p   { font-size: 13px; color: #64748b; text-align: center; margin: 0 28px 28px; line-height: 1.6; }
.confirm-modal-foot {
    display: flex;
    gap: 10px;
    padding: 16px 20px;
    border-top: 1px solid #f1f5f9;
    background: #f8fafc;
}
.confirm-modal-foot button { flex: 1; padding: 11px; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; border: none; }
.btn-confirm-cancel  { background: white; border: 1.5px solid #e2e8f0 !important; color: #374151; }
.btn-confirm-archive { background: #dc2626; color: white; }

/* ── FLASH ── */
.flash-alert {
    border-radius: 14px;
    border: none;
    font-size: 13px;
    padding: 13px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 22px;
}
.flash-success { background: #f0fdf4; color: #15803d; border: 1.5px solid #bbf7d0 !important; }
.flash-warning { background: #fffbeb; color: #92400e; border: 1.5px solid #fcd34d !important; }
.flash-error   { background: #fef2f2; color: #dc2626; border: 1.5px solid #fecaca !important; }

/* ── ERROR BOX ── */
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

/* ── CLIENT BADGE ── */
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

<!-- SIDEBAR — client vs staff -->
<div class="sidebar">
    <div class="sidebar-menu">
        <a href="../dashboard/dashboard.php"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
        <a href="../kanban/main_board.php" class="active"><i class="fa-solid fa-layer-group"></i> Campaigns</a>

        <?php if($isClient): ?>
            <a href="../retainer/retainer.php"><i class="fa-solid fa-wallet"></i> Retainer</a>
        <?php else: ?>
            <a href="../timelogs/time_logs.php"><i class="fa-regular fa-clock"></i> Time Logs</a>
        <?php endif; ?>

        <a href="../notifications/notifications.php"><i class="fa-regular fa-bell"></i> Notifications</a>
        <a href="../../auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
</div>

<!-- MAIN -->
<div class="main-content">

    <!-- PAGE BANNER -->
    <div class="page-banner">
        <div>
            <h1><i class="fa-solid fa-pen-to-square me-2"></i>Edit Campaign</h1>
            <p>Update campaign details, budget, deadline<?= !$isClient ? ', and assigned staff' : ''; ?>.</p>
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
            This campaign is currently <strong>archived</strong>. Editing is disabled. Restore it to make changes.
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

        <!-- ── CAMPAIGN DETAILS ── -->
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
                               value="<?= htmlspecialchars($row['campaign_name']); ?>"
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

        <!-- ── SCHEDULE & BUDGET ── -->
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
                               value="<?= htmlspecialchars($row['deadline']); ?>"
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

        <!-- ── ASSIGNED STAFF — staff only ── -->
        <?php if(!$isClient): ?>
        <div class="form-card">
            <div class="form-card-header">
                <div class="icon" style="background:#faf5ff; color:#a855f7;">
                    <i class="fa-solid fa-user-tie"></i>
                </div>
                <h3>Assigned Staff</h3>
            </div>
            <div class="form-card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label">Staff Member</label>
                        <select name="assigned_staff_id" class="form-select"
                                <?= $isArchived ? 'disabled' : ''; ?>>
                            <option value="0">— Unassigned —</option>
                            <?php
                            mysqli_data_seek($staffList, 0);
                            while($s = mysqli_fetch_assoc($staffList)):
                               $sel = ($s['user_id'] == ($row['assigned_staff_id'] ?? 0)) ? 'selected' : '';
                            ?>
                                <option value="<?= $s['user_id']; ?>" <?= $sel; ?>>
                                    <?= htmlspecialchars($s['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── ACTION BAR ── -->
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

    <!-- ── DANGER ZONE — staff only ── -->
    <?php if(!$isClient): ?>
    <div class="danger-zone">
        <div class="danger-zone-header">
            <i class="fa-solid fa-triangle-exclamation" style="color:#dc2626;"></i>
            <h3>Danger Zone</h3>
        </div>
        <div class="danger-zone-body">
            <div class="archive-row">
                <div class="archive-desc">
                    <?php if($isArchived): ?>
                        <h5>Restore Campaign</h5>
                        <p>Bring this campaign back to active Planning status. All data is preserved.</p>
                    <?php else: ?>
                        <h5>Archive Campaign</h5>
                        <p>Hide this campaign from the active board. It won't affect any data — assets, milestones, and logs are preserved.</p>
                    <?php endif; ?>
                </div>
                <?php if($isArchived): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="unarchive">
                        <button type="submit" class="btn-unarchive">
                            <i class="fa-solid fa-box-open"></i> Restore Campaign
                        </button>
                    </form>
                <?php else: ?>
                    <button class="btn-archive" onclick="openArchiveConfirm()">
                        <i class="fa-solid fa-box-archive"></i> Archive Campaign
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- ── ARCHIVE CONFIRM MODAL — staff only ── -->
<?php if(!$isClient): ?>
<div class="confirm-modal-bg" id="archiveModal">
    <div class="confirm-modal">
        <div class="confirm-modal-icon">
            <i class="fa-solid fa-box-archive"></i>
        </div>
        <h4>Archive this campaign?</h4>
        <p>
            "<strong><?= htmlspecialchars($row['campaign_name']); ?></strong>" will be hidden from the active board.
            All milestones, assets, time logs, and feedback are kept intact.
            You can restore it at any time.
        </p>
        <div class="confirm-modal-foot">
            <button class="btn-confirm-cancel" onclick="closeArchiveConfirm()">Cancel</button>
            <form method="POST" style="flex:1;">
                <input type="hidden" name="action" value="archive">
                <button type="submit" class="btn-confirm-archive"
                        style="width:100%; padding:11px; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; border:none;">
                    Yes, Archive
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function openArchiveConfirm(){
    document.getElementById('archiveModal').classList.add('open');
}
function closeArchiveConfirm(){
    document.getElementById('archiveModal').classList.remove('open');
}
document.getElementById('archiveModal').addEventListener('click', function(e){
    if(e.target === this) closeArchiveConfirm();
});
document.addEventListener('keydown', e => {
    if(e.key === 'Escape') closeArchiveConfirm();
});
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>