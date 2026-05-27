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
$name    = $_SESSION['name'];

/*
========================================
OVERALL RETAINER SUMMARY
========================================
*/
$retainerQuery = mysqli_query($conn,"
    SELECT
        SUM(total_amount)     AS total_retainer,
        SUM(used_amount)      AS used_retainer,
        SUM(remaining_amount) AS remaining_retainer
    FROM retainers
    WHERE client_id = '$user_id'
");
$retainer = mysqli_fetch_assoc($retainerQuery);

$total_retainer     = $retainer['total_retainer']     ?? 0;
$used_retainer      = $retainer['used_retainer']      ?? 0;
$remaining_retainer = $retainer['remaining_retainer'] ?? 0;
$retainer_pct       = $total_retainer > 0
    ? round(($used_retainer / $total_retainer) * 100)
    : 0;

/*
========================================
PER-CAMPAIGN BREAKDOWN
(budget from campaigns.budget,
 spent from SUM(time_logs.cost))
========================================
*/
$campaignQuery = mysqli_query($conn,"
    SELECT
        c.campaign_id,
        c.campaign_name,
        c.status,
        c.budget,
        COALESCE(SUM(tl.cost), 0)             AS spent,
        c.budget - COALESCE(SUM(tl.cost), 0)  AS balance
    FROM campaigns c
    LEFT JOIN time_logs tl ON tl.campaign_id = c.campaign_id
    WHERE c.client_id = '$user_id'
    GROUP BY c.campaign_id
    ORDER BY c.campaign_name ASC
");

$campaigns   = [];
$totalBudget = 0;
$totalSpent  = 0;

while($row = mysqli_fetch_assoc($campaignQuery)){
    $campaigns[]  = $row;
    $totalBudget += $row['budget'];
    $totalSpent  += $row['spent'];
}

$overBudgetCount = count(array_filter($campaigns, fn($c) => $c['balance'] < 0));
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Retainer | AdHub</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<link rel="stylesheet" href="/AdHub_V2/assets/css/style.css">
<link rel="stylesheet" href="/AdHub_V2/assets/css/dashboard.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>

.main-content {
    margin-left: 260px;
    margin-top: 75px;
    padding: 35px;
}

/* ── HERO ── */
.page-banner {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    border-radius: 24px;
    padding: 26px 32px;
    color: white;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 14px;
}

.page-banner h1 { font-size: 26px; font-weight: 700; color: white; margin: 0 0 5px; }
.page-banner p  { margin: 0; color: #94a3b8; font-size: 14px; }

.alert-pill {
    background: rgba(239,68,68,0.18);
    border: 1px solid rgba(239,68,68,0.35);
    border-radius: 50px;
    padding: 10px 20px;
    font-size: 13px;
    font-weight: 700;
    color: #fca5a5;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* ── STAT CARDS ── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
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
.stat-card.red    { border-color: #ef4444; }
.stat-card.green  { border-color: #22c55e; }
.stat-card.orange { border-color: #f97316; }

.stat-icon {
    width: 48px; height: 48px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; flex-shrink: 0;
}

.stat-icon.blue   { background: #eff6ff; color: #3b82f6; }
.stat-icon.red    { background: #fef2f2; color: #ef4444; }
.stat-icon.green  { background: #f0fdf4; color: #22c55e; }
.stat-icon.orange { background: #fff7ed; color: #f97316; }

.stat-card h2 { font-size: 22px; font-weight: 700; color: #0f172a; margin: 0 0 2px; }
.stat-card p  { font-size: 13px; color: #64748b; margin: 0; }

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

.card-header-custom h3 { font-size: 17px; font-weight: 700; color: #0f172a; margin: 0; }

/* ── RETAINER PROGRESS ── */
.retainer-bar-wrap { margin-bottom: 8px; }

.retainer-bar-track {
    height: 14px;
    background: #e2e8f0;
    border-radius: 30px;
    overflow: hidden;
    margin-bottom: 10px;
}

.retainer-bar-fill {
    height: 100%;
    border-radius: 30px;
    background: linear-gradient(90deg, #3b82f6, #6366f1);
    transition: width 0.6s ease;
}

.retainer-bar-fill.danger {
    background: linear-gradient(90deg, #f97316, #ef4444);
}

/* ── BUDGET BOXES ── */
.budget-box {
    border-radius: 16px;
    padding: 16px 18px;
    text-align: center;
}

.budget-box .label  { font-size: 12px; color: #64748b; margin-bottom: 4px; }
.budget-box .amount { font-size: 18px; font-weight: 700; }

/* ── CAMPAIGN TABLE ── */
.custom-table thead { background: #f8fafc; }
.custom-table th    { border: none; color: #64748b; font-size: 13px; font-weight: 600; padding: 12px 16px; }
.custom-table td    { vertical-align: middle; border-color: #f1f5f9; font-size: 14px; padding: 14px 16px; }
.custom-table tbody tr { transition: background 0.15s; }
.custom-table tbody tr:hover { background: #f8fafc; }

/* mini progress */
.mini-progress {
    height: 6px;
    border-radius: 20px;
    background: #e2e8f0;
    min-width: 80px;
}

.mini-progress-bar {
    height: 100%;
    border-radius: 20px;
}

/* over-budget row */
.row-overbudget { background: #fff5f5 !important; }
.row-overbudget:hover { background: #fee2e2 !important; }

/* ── BALANCE BADGE ── */
.balance-ok   { color: #16a34a; font-weight: 700; }
.balance-over { color: #dc2626; font-weight: 700; }

/* ── SETTLE ALERT ── */
.settle-alert {
    background: #fff5f5;
    border: 1px solid #fecaca;
    border-radius: 16px;
    padding: 16px 20px;
    margin-bottom: 24px;
    display: flex;
    align-items: flex-start;
    gap: 14px;
}

.settle-alert-icon {
    width: 42px; height: 42px;
    min-width: 42px;
    background: #fee2e2;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    color: #dc2626; font-size: 18px;
}

.settle-alert h5 { font-size: 15px; font-weight: 700; color: #991b1b; margin: 0 0 4px; }
.settle-alert p  { font-size: 13px; color: #b91c1c; margin: 0; }

/* ── EMPTY STATE ── */
.empty-state { text-align: center; padding: 50px 20px; color: #94a3b8; }
.empty-state i { font-size: 44px; margin-bottom: 12px; display: block; }
.empty-state p { font-size: 13px; margin: 0; }

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

        <a href="retainers.php" class="active">
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

<!-- MAIN CONTENT -->
<div class="main-content">

    <!-- HERO BANNER -->
    <div class="page-banner">
        <div>
            <h1><i class="fa-solid fa-wallet me-2"></i>Retainer Overview</h1>
            <p>Your overall budget pool and per-campaign spending breakdown.</p>
        </div>
        <?php if($overBudgetCount > 0): ?>
        <div class="alert-pill">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <?= $overBudgetCount; ?> campaign<?= $overBudgetCount > 1 ? 's' : ''; ?> over budget
        </div>
        <?php endif; ?>
    </div>

    <!-- OVER-BUDGET SETTLE ALERT -->
    <?php if($overBudgetCount > 0):
        $totalOverage = array_sum(array_map(
            fn($c) => $c['balance'] < 0 ? abs($c['balance']) : 0,
            $campaigns
        ));
    ?>
    <div class="settle-alert">
        <div class="settle-alert-icon">
            <i class="fa-solid fa-circle-exclamation"></i>
        </div>
        <div>
            <h5>Balance to Settle</h5>
            <p>
                <?= $overBudgetCount; ?> of your campaign<?= $overBudgetCount > 1 ? 's have' : ' has'; ?>
                exceeded its allocated budget due to logged staff hours.
                A total of <strong>₱<?= number_format($totalOverage, 2); ?></strong>
                is outstanding. Please contact your account manager to settle.
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- STAT CARDS -->
    <div class="stats-grid">

        <div class="stat-card blue">
            <div class="stat-icon blue"><i class="fa-solid fa-wallet"></i></div>
            <div>
                <h2>₱<?= number_format($total_retainer, 2); ?></h2>
                <p>Total Retainer</p>
            </div>
        </div>

        <div class="stat-card red">
            <div class="stat-icon red"><i class="fa-solid fa-arrow-trend-up"></i></div>
            <div>
                <h2>₱<?= number_format($used_retainer, 2); ?></h2>
                <p>Total Used</p>
            </div>
        </div>

        <div class="stat-card green">
            <div class="stat-icon green"><i class="fa-solid fa-piggy-bank"></i></div>
            <div>
                <h2>₱<?= number_format($remaining_retainer, 2); ?></h2>
                <p>Remaining</p>
            </div>
        </div>

        <div class="stat-card orange">
            <div class="stat-icon orange"><i class="fa-solid fa-layer-group"></i></div>
            <div>
                <h2><?= count($campaigns); ?></h2>
                <p>Total Campaigns</p>
            </div>
        </div>

    </div>

    <!-- ROW: RETAINER UTILIZATION + DOUGHNUT -->
    <div class="row g-4 mb-0">

        <!-- UTILIZATION BAR -->
        <div class="col-lg-7">
            <div class="dashboard-card h-100">

                <div class="card-header-custom">
                    <h3><i class="fa-solid fa-chart-bar me-2 text-primary"></i>Retainer Utilization</h3>
                    <span class="badge <?= $retainer_pct >= 90 ? 'bg-danger' : ($retainer_pct >= 70 ? 'bg-warning text-dark' : 'bg-primary'); ?>">
                        <?= $retainer_pct; ?>% Used
                    </span>
                </div>

                <div class="retainer-bar-wrap mb-4">
                    <div class="retainer-bar-track">
                        <div class="retainer-bar-fill <?= $retainer_pct >= 90 ? 'danger' : ''; ?>"
                             style="width: <?= min($retainer_pct, 100); ?>%"></div>
                    </div>
                    <div class="d-flex justify-content-between" style="font-size:12px; color:#94a3b8;">
                        <span>₱0</span>
                        <span>₱<?= number_format($total_retainer, 2); ?></span>
                    </div>
                </div>

                <div class="row g-2">

                    <div class="col-4">
                        <div class="budget-box" style="background:#f8fafc;">
                            <div class="label">Total Budget</div>
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

        <!-- DOUGHNUT -->
        <div class="col-lg-5">
            <div class="dashboard-card h-100 d-flex flex-column align-items-center justify-content-center">

                <div class="card-header-custom w-100">
                    <h3><i class="fa-solid fa-circle-half-stroke me-2 text-warning"></i>Allocation</h3>
                </div>

                <div style="max-width: 220px; width: 100%;">
                    <canvas id="retainerChart"></canvas>
                </div>

            </div>
        </div>

    </div>

    <!-- CAMPAIGN BUDGET BREAKDOWN -->
    <div class="dashboard-card" style="margin-top: 24px;">

        <div class="card-header-custom">
            <h3><i class="fa-solid fa-layer-group me-2 text-primary"></i>Campaign Budget Breakdown</h3>
            <?php if($overBudgetCount > 0): ?>
            <span class="badge bg-danger">
                <i class="fa-solid fa-triangle-exclamation me-1"></i>
                <?= $overBudgetCount; ?> Over Budget
            </span>
            <?php endif; ?>
        </div>

        <?php if(count($campaigns) > 0): ?>

        <div class="table-responsive">
        <table class="table custom-table mb-0">
            <thead>
                <tr>
                    <th>Campaign</th>
                    <th>Status</th>
                    <th>Allocated Budget</th>
                    <th>Staff Cost (Logged)</th>
                    <th>Spend Progress</th>
                    <th>Balance</th>
                </tr>
            </thead>
            <tbody>

            <?php foreach($campaigns as $row):
                $budget  = (float) $row['budget'];
                $spent   = (float) $row['spent'];
                $balance = $budget - $spent;
                $pct     = $budget > 0 ? min(round(($spent / $budget) * 100), 100) : 0;
                $isOver  = $balance < 0;

                $barColor = $isOver ? '#ef4444'
                    : ($pct >= 80 ? '#f97316' : '#3b82f6');

                $statusClass = match(strtolower($row['status'])){
                    'completed' => 'bg-success',
                    'active'    => 'bg-primary',
                    'review'    => 'bg-info',
                    'planning'  => 'bg-warning text-dark',
                    default     => 'bg-secondary'
                };
            ?>
            <tr class="<?= $isOver ? 'row-overbudget' : ''; ?>">

                <td>
                    <div style="font-weight: 600; color: #0f172a;">
                        <?= htmlspecialchars($row['campaign_name']); ?>
                    </div>
                    <?php if($isOver): ?>
                    <small style="color:#dc2626; font-size:11px;">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>Over budget
                    </small>
                    <?php endif; ?>
                </td>

                <td>
                    <span class="badge <?= $statusClass; ?>">
                        <?= ucfirst($row['status']); ?>
                    </span>
                </td>

                <td>₱<?= number_format($budget, 2); ?></td>

                <td>₱<?= number_format($spent, 2); ?></td>

                <td style="min-width: 120px;">
                    <div class="mini-progress">
                        <div class="mini-progress-bar"
                             style="width: <?= $pct; ?>%; background: <?= $barColor; ?>;"></div>
                    </div>
                    <small style="font-size: 11px; color: #94a3b8;"><?= $pct; ?>%</small>
                </td>

                <td>
                    <?php if($isOver): ?>
                        <span class="balance-over">
                            <i class="fa-solid fa-circle-exclamation me-1"></i>
                            -₱<?= number_format(abs($balance), 2); ?>
                        </span>
                    <?php else: ?>
                        <span class="balance-ok">
                            <i class="fa-solid fa-circle-check me-1"></i>
                            ₱<?= number_format($balance, 2); ?>
                        </span>
                    <?php endif; ?>
                </td>

            </tr>
            <?php endforeach; ?>

            </tbody>

            <!-- TOTALS FOOTER -->
            <tfoot style="background: #f8fafc; font-weight: 700; font-size: 14px;">
                <tr>
                    <td colspan="2" style="color: #0f172a;">Totals</td>
                    <td>₱<?= number_format($totalBudget, 2); ?></td>
                    <td>₱<?= number_format($totalSpent,  2); ?></td>
                    <td></td>
                    <td>
                        <?php $totalBalance = $totalBudget - $totalSpent; ?>
                        <?php if($totalBalance < 0): ?>
                            <span class="balance-over">-₱<?= number_format(abs($totalBalance), 2); ?></span>
                        <?php else: ?>
                            <span class="balance-ok">₱<?= number_format($totalBalance, 2); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            </tfoot>

        </table>
        </div>

        <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-layer-group"></i>
                <p>No campaigns found.</p>
            </div>
        <?php endif; ?>

    </div>

</div>

<script>
new Chart(document.getElementById('retainerChart'), {
    type: 'doughnut',
    data: {
        labels: ['Used', 'Remaining'],
        datasets: [{
            data: [<?= $used_retainer; ?>, <?= max($remaining_retainer, 0); ?>],
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