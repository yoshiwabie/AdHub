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
$name    = $_SESSION['name'];

/*
========================================
SAVE TIME LOG
========================================
*/
if(isset($_POST['save_log'])){

    $campaign_id  = mysqli_real_escape_string($conn, $_POST['campaign_id']);
    $hours        = mysqli_real_escape_string($conn, $_POST['hours']);
    $log_date     = mysqli_real_escape_string($conn, $_POST['log_date']);
    $hourly_rate  = mysqli_real_escape_string($conn, $_POST['hourly_rate']);

    if($log_date < date('Y-m-d')){
        $_SESSION['error'] = "You cannot log time for a past date.";
    } else {
        $cost = $hours * $hourly_rate;

        // INSERT time log
        mysqli_query($conn,"
            INSERT INTO time_logs(campaign_id, staff_id, log_date, hours, hourly_rate, cost)
            VALUES('$campaign_id','$user_id','$log_date','$hours','$hourly_rate','$cost')
        ");

        // GET client_id for this campaign
        $campRow = mysqli_fetch_assoc(mysqli_query($conn,"
            SELECT client_id FROM campaigns WHERE campaign_id = '$campaign_id' LIMIT 1
        "));
        $client_id = $campRow['client_id'] ?? null;

        // UPDATE retainer
        if($client_id){
            mysqli_query($conn,"
                UPDATE retainers
                SET used_amount      = used_amount + $cost,
                    remaining_amount = remaining_amount - $cost
                WHERE client_id = '$client_id'
            ");
        }

        $_SESSION['success'] = "Time log saved successfully.";
    }

    header("Location: time_logs.php");
    exit();
}

/*
========================================
SEARCH
========================================
*/
$search = $_GET['search'] ?? '';

/*
========================================
CAMPAIGNS
========================================
*/
$campaigns = mysqli_query($conn,"
    SELECT * FROM campaigns
    WHERE assigned_staff_id = '$user_id'
    ORDER BY campaign_name ASC
");

/*
========================================
LOGS
========================================
*/
$logsQuery = mysqli_query($conn,"
    SELECT t.*, c.campaign_name
    FROM time_logs t
    LEFT JOIN campaigns c ON t.campaign_id = c.campaign_id
    WHERE t.staff_id = '$user_id'
    AND c.campaign_name LIKE '%$search%'
    ORDER BY t.log_id DESC
");

/*
========================================
STATS
========================================
*/
$stats = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT SUM(hours) as total_hours,
           SUM(cost)  as total_cost,
           COUNT(DISTINCT campaign_id) as total_campaigns
    FROM time_logs WHERE staff_id = '$user_id'
"));

$total_hours     = $stats['total_hours']     ?? 0;
$total_cost      = $stats['total_cost']      ?? 0;
$total_campaigns = $stats['total_campaigns'] ?? 0;

/*
========================================
HOURLY RATE
========================================
*/
$default_rate = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT hourly_rate FROM users WHERE user_id = '$user_id'
"))['hourly_rate'] ?? 500;
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

.main-content{
    margin-left:260px;
    margin-top:75px;
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

/* STAT CARDS */
.stats-grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:18px;
    margin-bottom:24px;
}

.stat-card{
    background:white;
    border-radius:20px;
    padding:22px;
    box-shadow:0 4px 16px rgba(15,23,42,0.06);
    display:flex;
    align-items:center;
    gap:16px;
    border-left:4px solid transparent;
    transition:transform 0.2s;
}

.stat-card:hover{ transform:translateY(-3px); }
.stat-card.blue  { border-color:#3b82f6; }
.stat-card.green { border-color:#22c55e; }
.stat-card.orange{ border-color:#f97316; }

.stat-icon{
    width:48px; height:48px;
    border-radius:14px;
    display:flex; align-items:center; justify-content:center;
    font-size:20px; flex-shrink:0;
}

.stat-icon.blue  { background:#eff6ff; color:#3b82f6; }
.stat-icon.green { background:#f0fdf4; color:#22c55e; }
.stat-icon.orange{ background:#fff7ed; color:#f97316; }

.stat-card h2{
    font-size:24px; font-weight:700; color:#0f172a; margin:0 0 2px;
}

.stat-card p{ font-size:13px; color:#64748b; margin:0; }

/* DASHBOARD CARD */
.dashboard-card{
    background:white;
    border-radius:22px;
    padding:26px;
    box-shadow:0 4px 16px rgba(15,23,42,0.06);
    margin-bottom:22px;
}

.card-title{
    font-size:17px;
    font-weight:700;
    color:#0f172a;
    margin-bottom:20px;
    display:flex;
    align-items:center;
    gap:8px;
}

/* FORM */
.form-label{ font-weight:600; font-size:13px; color:#374151; margin-bottom:6px; }

.form-control,
.form-select{
    border-radius:12px;
    padding:11px 14px;
    border:1px solid #e2e8f0;
    font-size:14px;
}

.form-control:focus,
.form-select:focus{
    border-color:#3b82f6;
    box-shadow:0 0 0 3px rgba(59,130,246,0.1);
}

.cost-preview{
    background:#f0fdf4;
    border:1px solid #bbf7d0;
    border-radius:12px;
    padding:12px 16px;
    font-size:14px;
    color:#15803d;
    font-weight:600;
    display:flex;
    align-items:center;
    gap:8px;
}

.submit-btn{
    background:#3b82f6;
    border:none;
    padding:12px 24px;
    border-radius:14px;
    color:white;
    font-weight:600;
    font-size:14px;
    transition:0.2s;
    display:inline-flex;
    align-items:center;
    gap:8px;
}

.submit-btn:hover{ background:#2563eb; transform:translateY(-1px); }

/* TABLE */
.custom-table thead{ background:#f8fafc; }
.custom-table th{ border:none; color:#64748b; font-size:13px; font-weight:600; }
.custom-table td{ vertical-align:middle; border-color:#f1f5f9; font-size:14px; }
.custom-table tbody tr{ transition:background 0.15s; }
.custom-table tbody tr:hover{ background:#f8fafc; }

/* COST BADGE */
.cost-badge{
    background:#dcfce7;
    color:#15803d;
    padding:6px 12px;
    border-radius:30px;
    font-size:12px;
    font-weight:600;
}

/* SEARCH */
.search-wrap{ position:relative; }
.search-wrap input{ padding-left:36px; }
.search-wrap i{
    position:absolute;
    left:12px; top:50%;
    transform:translateY(-50%);
    color:#94a3b8; font-size:14px;
}

/* ALERTS */
.alert{ border-radius:16px; border:none; font-size:14px; }

/* EMPTY */
.empty-state{
    text-align:center; padding:50px 20px; color:#94a3b8;
}
.empty-state i{ font-size:44px; margin-bottom:12px; display:block; }
.empty-state p{ font-size:14px; margin:0; }

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

        <a href="time_logs.php" class="active">
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
    <div class="page-banner">
        <div>
            <h1><i class="fa-regular fa-clock me-2"></i>Time Logs</h1>
            <p>Track working hours and campaign costs across your assignments.</p>
        </div>
        <div style="
            background:rgba(255,255,255,0.08);
            border-radius:14px;
            padding:12px 20px;
            text-align:center;
            color:#e2e8f0;
            font-size:13px;
        ">
            <strong style="font-size:20px; color:white; display:block;">
                <?= date('d'); ?>
            </strong>
            <?= date('F Y'); ?>
        </div>
    </div>

    <!-- ALERTS -->
    <?php if(isset($_SESSION['success'])){ ?>
        <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
            <i class="fa-solid fa-circle-check"></i>
            <?= $_SESSION['success']; ?>
        </div>
    <?php unset($_SESSION['success']); } ?>

    <?php if(isset($_SESSION['error'])){ ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= $_SESSION['error']; ?>
        </div>
    <?php unset($_SESSION['error']); } ?>

    <!-- STATS -->
    <div class="stats-grid">

        <div class="stat-card blue">
            <div class="stat-icon blue">
                <i class="fa-regular fa-clock"></i>
            </div>
            <div>
                <h2><?= number_format($total_hours, 1); ?> hrs</h2>
                <p>Total Logged Hours</p>
            </div>
        </div>

        <div class="stat-card green">
            <div class="stat-icon green">
                <i class="fa-solid fa-peso-sign"></i>
            </div>
            <div>
                <h2>₱<?= number_format($total_cost, 2); ?></h2>
                <p>Total Cost Deducted</p>
            </div>
        </div>

        <div class="stat-card orange">
            <div class="stat-icon orange">
                <i class="fa-solid fa-layer-group"></i>
            </div>
            <div>
                <h2><?= $total_campaigns; ?></h2>
                <p>Campaigns Logged</p>
            </div>
        </div>

    </div>

    <!-- LOG FORM -->
    <div class="dashboard-card">

        <div class="card-title">
            <i class="fa-solid fa-plus-circle text-primary"></i>
            Add Time Log
        </div>

        <form method="POST" id="logForm">

            <div class="row g-3">

                <div class="col-md-4">
                    <label class="form-label">Campaign</label>
                    <select name="campaign_id" class="form-select" required>
                        <option value="">Select Campaign</option>
                        <?php while($camp = mysqli_fetch_assoc($campaigns)) { ?>
                            <option value="<?= $camp['campaign_id']; ?>">
                                <?= htmlspecialchars($camp['campaign_name']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Hours</label>
                    <input type="number" step="0.5" min="0.5" max="24"
                           name="hours" id="hoursInput"
                           class="form-control" placeholder="e.g. 8"
                           oninput="updateCost()" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="log_date"
                           min="<?= date('Y-m-d'); ?>"
                           class="form-control" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Hourly Rate (₱)</label>
                    <input type="number" step="0.01" min="1"
                           name="hourly_rate" id="rateInput"
                           class="form-control"
                           value="<?= $default_rate; ?>"
                           oninput="updateCost()" required>
                </div>

            </div>

            <!-- COST PREVIEW -->
            <div class="cost-preview mt-3 mb-4" id="costPreview">
                <i class="fa-solid fa-calculator"></i>
                Estimated Cost: <span id="costAmount">₱0.00</span>
            </div>

            <button type="submit" name="save_log" class="submit-btn">
                <i class="fa-solid fa-plus"></i>
                Save Time Log
            </button>

        </form>

    </div>

    <!-- LOG TABLE -->
    <div class="dashboard-card">

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">

            <div class="card-title mb-0">
                <i class="fa-solid fa-table-list text-primary"></i>
                Time Log History
                <span class="badge bg-secondary ms-1" style="font-size:12px;">
                    <?= mysqli_num_rows($logsQuery); ?>
                </span>
            </div>

            <form method="GET">
                <div class="search-wrap">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="search"
                           value="<?= htmlspecialchars($search); ?>"
                           class="form-control"
                           style="width:240px;"
                           placeholder="Search campaign...">
                </div>
            </form>

        </div>

        <div class="table-responsive">

            <?php if(mysqli_num_rows($logsQuery) > 0){ ?>

            <table class="table custom-table">
                <thead>
                    <tr>
                        <th>Campaign</th>
                        <th>Date</th>
                        <th>Hours</th>
                        <th>Rate</th>
                        <th>Cost</th>
                    </tr>
                </thead>
                <tbody>
                <?php while($log = mysqli_fetch_assoc($logsQuery)) { ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($log['campaign_name']); ?></strong>
                        </td>
                        <td>
                            <i class="fa-regular fa-calendar me-1 text-muted"></i>
                            <?= date('M d, Y', strtotime($log['log_date'])); ?>
                        </td>
                        <td>
                            <span style="font-weight:600;">
                                <?= number_format($log['hours'], 1); ?>
                            </span>
                            <span class="text-muted">hrs</span>
                        </td>
                        <td class="text-muted">
                            ₱<?= number_format($log['hourly_rate'], 2); ?>/hr
                        </td>
                        <td>
                            <span class="cost-badge">
                                ₱<?= number_format($log['cost'], 2); ?>
                            </span>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>

            <?php } else { ?>

                <div class="empty-state">
                    <i class="fa-regular fa-clock"></i>
                    <p>
                        <?= $search
                            ? "No logs found for \"" . htmlspecialchars($search) . "\""
                            : "No time logs recorded yet."; ?>
                    </p>
                </div>

            <?php } ?>

        </div>

    </div>

</div>

<script>
function updateCost(){
    const hours = parseFloat(document.getElementById('hoursInput').value) || 0;
    const rate  = parseFloat(document.getElementById('rateInput').value)  || 0;
    const cost  = hours * rate;
    document.getElementById('costAmount').textContent =
        '₱' + cost.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>