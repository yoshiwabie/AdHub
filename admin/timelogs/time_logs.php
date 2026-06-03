<?php
session_start();

include('../../config/db.php');
include('../../includes/topbar.php');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff'){
    header("Location: ../../index.php");
    exit();
}

$user_id      = $_SESSION['user_id'];
$name         = $_SESSION['name'];
$today        = date('Y-m-d');

/*
========================================
ADD LOG
========================================
*/
if(isset($_POST['save_log'])){

    $campaign_id  = mysqli_real_escape_string($conn, $_POST['campaign_id']);
    $hours        = (float)$_POST['hours'];
    $log_date     = mysqli_real_escape_string($conn, $_POST['log_date']);
    $hourly_rate  = (float)$_POST['hourly_rate'];

    if($log_date < $today){
        $_SESSION['flash'] = ['type'=>'error','msg'=>'You cannot log time for a past date.'];
    } else {
        $cost = $hours * $hourly_rate;
        mysqli_query($conn,"
            INSERT INTO time_logs(campaign_id, staff_id, log_date, hours, hourly_rate, cost)
            VALUES('$campaign_id','$user_id','$log_date','$hours','$hourly_rate','$cost')
        ");

        $campRow   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT client_id FROM campaigns WHERE campaign_id='$campaign_id' LIMIT 1"));
        $client_id = $campRow['client_id'] ?? null;
        if($client_id){
            mysqli_query($conn,"
                UPDATE retainers
                SET used_amount = used_amount + $cost,
                    remaining_amount = remaining_amount - $cost
                WHERE client_id = '$client_id'
            ");
        }
        $_SESSION['flash'] = ['type'=>'success','msg'=>'Time log saved successfully.'];
    }
    header("Location: time_logs.php"); exit();
}

/*
========================================
EDIT LOG  (same-day only)
========================================
*/
if(isset($_POST['edit_log'])){

    $log_id      = (int)$_POST['log_id'];
    $hours       = (float)$_POST['edit_hours'];
    $hourly_rate = (float)$_POST['edit_hourly_rate'];

    // Verify ownership + same-day
    $existing = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT * FROM time_logs WHERE log_id='$log_id' AND staff_id='$user_id' LIMIT 1
    "));

    if(!$existing){
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Log not found.'];
    } elseif($existing['log_date'] !== $today){
        $_SESSION['flash'] = ['type'=>'error','msg'=>'You can only edit logs created today.'];
    } else {
        $old_cost  = (float)$existing['cost'];
        $new_cost  = $hours * $hourly_rate;
        $diff_cost = $new_cost - $old_cost;

        mysqli_query($conn,"
            UPDATE time_logs SET hours='$hours', hourly_rate='$hourly_rate', cost='$new_cost'
            WHERE log_id='$log_id' AND staff_id='$user_id'
        ");

        // Adjust retainer
        $campRow   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT client_id FROM campaigns WHERE campaign_id='{$existing['campaign_id']}' LIMIT 1"));
        $client_id = $campRow['client_id'] ?? null;
        if($client_id && $diff_cost != 0){
            mysqli_query($conn,"
                UPDATE retainers
                SET used_amount = used_amount + $diff_cost,
                    remaining_amount = remaining_amount - $diff_cost
                WHERE client_id = '$client_id'
            ");
        }
        $_SESSION['flash'] = ['type'=>'success','msg'=>'Time log updated.'];
    }
    header("Location: time_logs.php"); exit();
}

/*
========================================
DELETE LOG  (same-day only)
========================================
*/
if(isset($_POST['delete_log'])){

    $log_id = (int)$_POST['log_id'];

    $existing = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT * FROM time_logs WHERE log_id='$log_id' AND staff_id='$user_id' LIMIT 1
    "));

    if(!$existing){
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Log not found.'];
    } elseif($existing['log_date'] !== $today){
        $_SESSION['flash'] = ['type'=>'error','msg'=>'You can only delete logs created today.'];
    } else {
        $cost = (float)$existing['cost'];
        mysqli_query($conn,"DELETE FROM time_logs WHERE log_id='$log_id' AND staff_id='$user_id'");

        $campRow   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT client_id FROM campaigns WHERE campaign_id='{$existing['campaign_id']}' LIMIT 1"));
        $client_id = $campRow['client_id'] ?? null;
        if($client_id && $cost > 0){
            mysqli_query($conn,"
                UPDATE retainers
                SET used_amount = used_amount - $cost,
                    remaining_amount = remaining_amount + $cost
                WHERE client_id = '$client_id'
            ");
        }
        $_SESSION['flash'] = ['type'=>'success','msg'=>'Time log deleted.'];
    }
    header("Location: time_logs.php"); exit();
}

/*
========================================
SEARCH & DATA
========================================
*/
$search = $_GET['search'] ?? '';
$searchEsc = mysqli_real_escape_string($conn, $search);

$campaigns = mysqli_query($conn,"
    SELECT * FROM campaigns WHERE assigned_staff_id='$user_id' ORDER BY campaign_name ASC
");

$logsQuery = mysqli_query($conn,"
    SELECT t.*, c.campaign_name
    FROM time_logs t
    LEFT JOIN campaigns c ON t.campaign_id = c.campaign_id
    WHERE t.staff_id = '$user_id'
    AND c.campaign_name LIKE '%$searchEsc%'
    ORDER BY t.log_id DESC
");

$stats = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT SUM(hours) as total_hours, SUM(cost) as total_cost, COUNT(DISTINCT campaign_id) as total_campaigns
    FROM time_logs WHERE staff_id='$user_id'
"));

$total_hours     = $stats['total_hours']     ?? 0;
$total_cost      = $stats['total_cost']      ?? 0;
$total_campaigns = $stats['total_campaigns'] ?? 0;

$default_rate = mysqli_fetch_assoc(mysqli_query($conn,"SELECT hourly_rate FROM users WHERE user_id='$user_id'"))['hourly_rate'] ?? 500;

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Time Logs | AdHub</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<link rel="stylesheet" href="/AdHub_V2/assets/css/style.css">
<link rel="stylesheet" href="/AdHub_V2/assets/css/dashboard.css">

<style>
.main-content { margin-left: 260px; padding: 35px; }

/* ── BANNER ── */
.page-banner {
    background: linear-gradient(135deg, #1e293b, #334155);
    border-radius: 20px;
    padding: 26px 32px;
    color: white;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 14px;
}
.page-banner h1 { font-size: 24px; font-weight: 700; margin: 0 0 4px; color: white; }
.page-banner p  { margin: 0; color: #94a3b8; font-size: 13px; }

/* ── STATS ── */
.stats-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 18px; margin-bottom: 24px; }

.stat-card {
    background: white;
    border-radius: 18px;
    padding: 20px 22px;
    box-shadow: 0 4px 16px rgba(15,23,42,.06);
    display: flex;
    align-items: center;
    gap: 16px;
    border-left: 4px solid transparent;
    transition: transform .2s;
}
.stat-card:hover { transform: translateY(-2px); }
.stat-card.blue   { border-color: #3b82f6; }
.stat-card.green  { border-color: #22c55e; }
.stat-card.orange { border-color: #f97316; }

.stat-icon { width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.stat-icon.blue   { background: #eff6ff; color: #3b82f6; }
.stat-icon.green  { background: #f0fdf4; color: #22c55e; }
.stat-icon.orange { background: #fff7ed; color: #f97316; }
.stat-card h2 { font-size: 22px; font-weight: 700; color: #0f172a; margin: 0 0 2px; }
.stat-card p  { font-size: 13px; color: #64748b; margin: 0; }

/* ── DASHBOARD CARD ── */
.dashboard-card { background: white; border-radius: 20px; padding: 24px; box-shadow: 0 4px 16px rgba(15,23,42,.06); margin-bottom: 22px; }
.card-title { font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 18px; display: flex; align-items: center; gap: 8px; }

/* ── FORM ── */
.form-label  { font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 6px; text-transform: uppercase; letter-spacing: .04em; }
.form-control, .form-select { border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 11px 14px; font-size: 14px; }
.form-control:focus, .form-select:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.1); outline: none; }

.cost-preview {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 12px;
    padding: 11px 16px;
    font-size: 14px;
    color: #15803d;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.submit-btn {
    background: #1F3A93;
    border: none;
    padding: 11px 22px;
    border-radius: 12px;
    color: white;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: background .15s, transform .12s;
}
.submit-btn:hover { background: #162d7a; transform: translateY(-1px); }

/* ── TABLE ── */
.custom-table thead { background: #f8fafc; }
.custom-table th    { border: none; color: #64748b; font-size: 13px; font-weight: 600; }
.custom-table td    { vertical-align: middle; border-color: #f1f5f9; font-size: 14px; }
.custom-table tbody tr { transition: background .15s; }
.custom-table tbody tr:hover { background: #f8fafc; }

.cost-badge { background: #dcfce7; color: #15803d; padding: 5px 12px; border-radius: 30px; font-size: 12px; font-weight: 600; }

/* ── TODAY BADGE ── */
.today-badge {
    background: #eff6ff;
    color: #1d4ed8;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    margin-left: 6px;
    vertical-align: middle;
}

/* ── ROW ACTIONS ── */
.row-actions { display: flex; gap: 6px; align-items: center; }

.btn-edit-log {
    width: 30px; height: 30px;
    border-radius: 8px;
    border: 1.5px solid #bfdbfe;
    background: #eff6ff;
    color: #3b82f6;
    font-size: 12px;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background .12s, border-color .12s;
}
.btn-edit-log:hover { background: #dbeafe; border-color: #93c5fd; }

.btn-delete-log {
    width: 30px; height: 30px;
    border-radius: 8px;
    border: 1.5px solid #fecaca;
    background: #fef2f2;
    color: #dc2626;
    font-size: 12px;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background .12s, border-color .12s;
}
.btn-delete-log:hover { background: #fee2e2; border-color: #f87171; }

.btn-locked {
    width: 30px; height: 30px;
    border-radius: 8px;
    border: 1.5px solid #e2e8f0;
    background: #f8fafc;
    color: #cbd5e1;
    font-size: 12px;
    cursor: not-allowed;
    display: flex; align-items: center; justify-content: center;
}

/* ── SEARCH ── */
.search-wrap { position: relative; }
.search-wrap input { padding-left: 36px; }
.search-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px; }

/* ── FLASH ── */
.flash-alert {
    border-radius: 14px;
    padding: 12px 16px;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}
.flash-success { background: #f0fdf4; color: #15803d; border: 1.5px solid #bbf7d0; }
.flash-error   { background: #fef2f2; color: #dc2626; border: 1.5px solid #fecaca; }

/* ── EMPTY ── */
.empty-state { text-align: center; padding: 50px 20px; color: #94a3b8; }
.empty-state i { font-size: 42px; margin-bottom: 10px; display: block; }
.empty-state p { font-size: 13px; margin: 0; }

/* ── MODALS ── */
.modal-bg {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.45);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}
.modal-bg.open { display: flex; }

.modal-box {
    background: white;
    border-radius: 20px;
    width: 440px;
    max-width: 95vw;
    box-shadow: 0 20px 60px rgba(0,0,0,.18);
    overflow: hidden;
    animation: modalIn .2s ease;
}
@keyframes modalIn { from { opacity:0; transform:scale(.95) translateY(8px); } to { opacity:1; transform:scale(1) translateY(0); } }

.modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px 18px;
    border-bottom: 1px solid #f1f5f9;
}
.modal-head h4 { font-size: 15px; font-weight: 700; color: #0f172a; margin: 0; }
.modal-close {
    width: 28px; height: 28px;
    border-radius: 7px;
    background: none; border: none;
    cursor: pointer; font-size: 15px; color: #94a3b8;
    display: flex; align-items: center; justify-content: center;
    transition: background .12s;
}
.modal-close:hover { background: #f1f5f9; color: #374151; }

.modal-body { padding: 20px 18px; }
.modal-foot {
    display: flex;
    gap: 8px;
    padding: 12px 18px;
    border-top: 1px solid #f1f5f9;
    background: #f8fafc;
}
.modal-foot button { flex: 1; padding: 10px; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; border: none; }
.btn-modal-cancel  { background: white; border: 1.5px solid #e2e8f0 !important; color: #374151; }
.btn-modal-save    { background: #1F3A93; color: white; }
.btn-modal-delete  { background: #dc2626; color: white; }

/* ── LOCK NOTICE ── */
.lock-notice {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 30px;
    padding: 5px 12px;
    font-size: 11px;
    color: #64748b;
    font-weight: 500;
}
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-menu">
        <a href="../dashboard/dashboard.php"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
        <a href="../kanban/main_board.php"><i class="fa-solid fa-layer-group"></i> Campaigns</a>
        <a href="time_logs.php" class="active"><i class="fa-regular fa-clock"></i> Time Logs</a>
        <a href="../notifications/notifications.php"><i class="fa-regular fa-bell"></i> Notifications</a>
        <a href="../../auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
</div>

<!-- MAIN -->
<div class="main-content">

    <!-- BANNER -->
    <div class="page-banner">
        <div>
            <h1><i class="fa-regular fa-clock me-2"></i>Time Logs</h1>
            <p>Track working hours and campaign costs. You can edit or delete today's entries only.</p>
        </div>
        <div style="background:rgba(255,255,255,.08);border-radius:12px;padding:12px 20px;text-align:center;color:#e2e8f0;font-size:13px;">
            <strong style="font-size:20px;color:white;display:block;"><?= date('d'); ?></strong>
            <?= date('F Y'); ?>
        </div>
    </div>

    <?php if($flash): ?>
        <div class="flash-alert flash-<?= $flash['type']; ?>">
            <i class="fa-solid fa-<?= $flash['type'] === 'success' ? 'circle-check' : 'circle-exclamation'; ?>"></i>
            <?= htmlspecialchars($flash['msg']); ?>
        </div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-icon blue"><i class="fa-regular fa-clock"></i></div>
            <div><h2><?= number_format($total_hours, 1); ?> hrs</h2><p>Total Logged Hours</p></div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon green"><i class="fa-solid fa-peso-sign"></i></div>
            <div><h2>₱<?= number_format($total_cost, 2); ?></h2><p>Total Cost Deducted</p></div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon orange"><i class="fa-solid fa-layer-group"></i></div>
            <div><h2><?= $total_campaigns; ?></h2><p>Campaigns Logged</p></div>
        </div>
    </div>

    <!-- ADD LOG FORM -->
    <div class="dashboard-card">
        <div class="card-title"><i class="fa-solid fa-plus-circle text-primary"></i> Add Time Log</div>
        <form method="POST" id="logForm">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Campaign</label>
                    <select name="campaign_id" class="form-select" required>
                        <option value="">Select Campaign</option>
                        <?php while($camp = mysqli_fetch_assoc($campaigns)): ?>
                            <option value="<?= $camp['campaign_id']; ?>"><?= htmlspecialchars($camp['campaign_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Hours</label>
                    <input type="number" step="0.5" min="0.5" max="24" name="hours" id="hoursInput" class="form-control" placeholder="e.g. 8" oninput="updateCost()" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="log_date" min="<?= $today; ?>" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Hourly Rate (₱)</label>
                    <input type="number" step="0.01" min="1" name="hourly_rate" id="rateInput" class="form-control" value="<?= $default_rate; ?>" oninput="updateCost()" required>
                </div>
            </div>
            <div class="cost-preview mt-3 mb-4">
                <i class="fa-solid fa-calculator"></i>
                Estimated Cost: <span id="costAmount">₱0.00</span>
            </div>
            <button type="submit" name="save_log" class="submit-btn">
                <i class="fa-solid fa-plus"></i> Save Time Log
            </button>
        </form>
    </div>

    <!-- LOG TABLE -->
    <div class="dashboard-card">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div class="card-title mb-0">
                <i class="fa-solid fa-table-list text-primary"></i>
                Time Log History
                <span class="badge bg-secondary ms-1" style="font-size:12px;"><?= mysqli_num_rows($logsQuery); ?></span>
            </div>
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <span class="lock-notice">
                    <i class="fa-solid fa-lock"></i>
                    Edit &amp; delete available for today's entries only
                </span>
                <form method="GET">
                    <div class="search-wrap">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search); ?>"
                               class="form-control" style="width:220px;" placeholder="Search campaign…">
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <?php if(mysqli_num_rows($logsQuery) > 0): ?>
            <table class="table custom-table">
                <thead>
                    <tr>
                        <th>Campaign</th>
                        <th>Date</th>
                        <th>Hours</th>
                        <th>Rate</th>
                        <th>Cost</th>
                        <th style="width:90px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php while($log = mysqli_fetch_assoc($logsQuery)):
                    $isToday = ($log['log_date'] === $today);
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($log['campaign_name']); ?></strong></td>
                        <td>
                            <i class="fa-regular fa-calendar me-1 text-muted"></i>
                            <?= date('M d, Y', strtotime($log['log_date'])); ?>
                            <?php if($isToday): ?><span class="today-badge">Today</span><?php endif; ?>
                        </td>
                        <td>
                            <span style="font-weight:600;"><?= number_format($log['hours'], 1); ?></span>
                            <span class="text-muted">hrs</span>
                        </td>
                        <td class="text-muted">₱<?= number_format($log['hourly_rate'], 2); ?>/hr</td>
                        <td><span class="cost-badge">₱<?= number_format($log['cost'], 2); ?></span></td>
                        <td>
                            <?php if($isToday): ?>
                            <div class="row-actions">
                                <button class="btn-edit-log"
                                    title="Edit"
                                    onclick="openEditModal(<?= $log['log_id']; ?>, <?= $log['hours']; ?>, <?= $log['hourly_rate']; ?>, '<?= htmlspecialchars($log['campaign_name']); ?>', '<?= $log['log_date']; ?>')">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button class="btn-delete-log"
                                    title="Delete"
                                    onclick="openDeleteModal(<?= $log['log_id']; ?>, '<?= htmlspecialchars(addslashes($log['campaign_name'])); ?>', '<?= date('M d, Y', strtotime($log['log_date'])); ?>', '<?= number_format($log['hours'],1); ?>')">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                            <?php else: ?>
                            <div class="row-actions">
                                <span class="btn-locked" title="Locked — can only edit today's logs"><i class="fa-solid fa-lock"></i></span>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa-regular fa-clock"></i>
                    <p><?= $search ? 'No logs found for "' . htmlspecialchars($search) . '"' : 'No time logs recorded yet.'; ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════
     EDIT MODAL
══════════════════════════════════════ -->
<div class="modal-bg" id="editModal">
    <div class="modal-box">
        <div class="modal-head">
            <h4><i class="fa-solid fa-pen me-2 text-primary"></i>Edit Time Log</h4>
            <button class="modal-close" onclick="closeModal('editModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="edit_log" value="1">
            <input type="hidden" name="log_id" id="edit_log_id">
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Campaign</label>
                    <input type="text" id="edit_campaign_name" class="form-control" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label">Date</label>
                    <input type="text" id="edit_log_date_display" class="form-control" disabled>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label">Hours</label>
                        <input type="number" step="0.5" min="0.5" max="24"
                               name="edit_hours" id="edit_hours"
                               class="form-control" required
                               oninput="updateEditCost()">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Hourly Rate (₱)</label>
                        <input type="number" step="0.01" min="1"
                               name="edit_hourly_rate" id="edit_hourly_rate"
                               class="form-control" required
                               oninput="updateEditCost()">
                    </div>
                </div>
                <div class="cost-preview mt-3">
                    <i class="fa-solid fa-calculator"></i>
                    New Cost: <span id="editCostAmount">₱0.00</span>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-modal-cancel" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn-modal-save">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════
     DELETE MODAL
══════════════════════════════════════ -->
<div class="modal-bg" id="deleteModal">
    <div class="modal-box">
        <div class="modal-head">
            <h4><i class="fa-solid fa-trash me-2 text-danger"></i>Delete Time Log</h4>
            <button class="modal-close" onclick="closeModal('deleteModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="delete_log" value="1">
            <input type="hidden" name="log_id" id="delete_log_id">
            <div class="modal-body" style="text-align:center; padding: 28px 24px;">
                <div style="width:56px;height:56px;border-radius:50%;background:#fef2f2;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:24px;color:#dc2626;">
                    <i class="fa-solid fa-trash"></i>
                </div>
                <h5 style="font-size:16px;font-weight:700;color:#0f172a;margin-bottom:8px;">Delete this log entry?</h5>
                <p id="deleteModalDesc" style="font-size:13px;color:#64748b;line-height:1.6;margin:0;"></p>
                <p style="font-size:12px;color:#ef4444;margin-top:10px;">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                    This will also reverse the cost from the client's retainer.
                </p>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-modal-cancel" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="btn-modal-delete">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
/* ── ADD FORM COST ── */
function updateCost(){
    const h = parseFloat(document.getElementById('hoursInput').value) || 0;
    const r = parseFloat(document.getElementById('rateInput').value)  || 0;
    document.getElementById('costAmount').textContent =
        '₱' + (h*r).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
}

/* ── EDIT MODAL ── */
function openEditModal(id, hours, rate, campaign, date){
    document.getElementById('edit_log_id').value          = id;
    document.getElementById('edit_hours').value           = hours;
    document.getElementById('edit_hourly_rate').value     = rate;
    document.getElementById('edit_campaign_name').value   = campaign;
    document.getElementById('edit_log_date_display').value = date;
    updateEditCost();
    document.getElementById('editModal').classList.add('open');
}

function updateEditCost(){
    const h = parseFloat(document.getElementById('edit_hours').value)       || 0;
    const r = parseFloat(document.getElementById('edit_hourly_rate').value) || 0;
    document.getElementById('editCostAmount').textContent =
        '₱' + (h*r).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
}

/* ── DELETE MODAL ── */
function openDeleteModal(id, campaign, date, hours){
    document.getElementById('delete_log_id').value = id;
    document.getElementById('deleteModalDesc').innerHTML =
        `<strong>${campaign}</strong> — ${hours} hrs on ${date}`;
    document.getElementById('deleteModal').classList.add('open');
}

/* ── CLOSE ── */
function closeModal(id){
    document.getElementById(id).classList.remove('open');
}

document.querySelectorAll('.modal-bg').forEach(bg => {
    bg.addEventListener('click', e => { if(e.target === bg) bg.classList.remove('open'); });
});

document.addEventListener('keydown', e => {
    if(e.key === 'Escape')
        document.querySelectorAll('.modal-bg.open').forEach(m => m.classList.remove('open'));
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>