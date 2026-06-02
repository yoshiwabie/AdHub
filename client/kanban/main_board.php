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
GET CAMPAIGNS BY DYNAMIC COLUMN STATUS
========================================
*/
function getClientCampaigns($conn, $column, $user_id){

    $query = "
        SELECT
            c.*,
            u.name as staff_name,

            COUNT(DISTINCT m.milestone_id) as total_milestones,

            SUM(
                CASE
                    WHEN m.status = 'approved'
                    THEN 1
                    ELSE 0
                END
            ) as approved_milestones,

            COUNT(DISTINCT a.asset_id) as total_assets

        FROM campaigns c

        LEFT JOIN users u
            ON c.assigned_staff_id = u.user_id

        LEFT JOIN milestones m
            ON c.campaign_id = m.campaign_id

        LEFT JOIN assets a
            ON m.milestone_id = a.milestone_id

        WHERE c.client_id = '$user_id'

        GROUP BY c.campaign_id

        ORDER BY
            CASE
                WHEN c.status = 'completed' THEN c.deadline
            END DESC,

            CASE
                WHEN c.status != 'completed' THEN c.deadline
            END ASC
    ";

    $result = mysqli_query($conn, $query);

    $campaigns = [];

    foreach($result as $row){

        $status = '';

        $totalMilestones    = (int)$row['total_milestones'];
        $approvedMilestones = (int)$row['approved_milestones'];
        $totalAssets        = (int)$row['total_assets'];

        /*
        ========================================
        COMPLETED
        ========================================
        */
        if($row['status'] == 'completed'){

            $status = 'completed';

        }

        /*
        ========================================
        REVIEW
        ALL milestones approved
        HAS assets
        ========================================
        */
        else if(
            $totalMilestones > 0 &&
            $approvedMilestones == $totalMilestones &&
            $totalAssets > 0
        ){

            $status = 'review';

        }

        /*
        ========================================
        ACTIVE
        HAS milestones/assets
        BUT not fully approved
        ========================================
        */
        else if(
            $totalMilestones > 0 ||
            $totalAssets > 0
        ){

            $status = 'active';

        }

        /*
        ========================================
        PLANNING
        no milestones/assets
        ========================================
        */
        else{

            $status = 'planning';

        }

        if($status == $column){
            $campaigns[] = $row;
        }
    }

    return $campaigns;
}

/*
========================================
GET PROGRESS
========================================
*/
function getProgress($conn, $campaign_id){

    $q = mysqli_query($conn,"
        SELECT
            COUNT(*) as total,
            SUM(status='approved') as done
        FROM milestones
        WHERE campaign_id = '$campaign_id'
    ");

    $d = mysqli_fetch_assoc($q);

    $total = $d['total'] ?: 1;
    $done  = $d['done'] ?: 0;

    return round(($done / $total) * 100);
}

/*
========================================
COLUMN DATA
========================================
*/
$planning  = getClientCampaigns($conn, 'planning',  $user_id);
$active    = getClientCampaigns($conn, 'active',    $user_id);
$review    = getClientCampaigns($conn, 'review',    $user_id);
$completed = getClientCampaigns($conn, 'completed', $user_id);

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Campaign Board | AdHub</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<link rel="stylesheet" href="/AdHub_V2/assets/css/style.css">
<link rel="stylesheet" href="/AdHub_V2/assets/css/dashboard.css">

<style>

/* ── LAYOUT ── */
.main-content {
    margin-left: 260px
    padding: 35px;
}

/* ── HEADER ── */
.page-banner {
    background: linear-gradient(135deg, #1e293b, #334155);
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

.page-banner h1 {
    font-size: 26px;
    font-weight: 700;
    color: white;
    margin: 0 0 5px;
}

.page-banner p { margin: 0; color: #94a3b8; font-size: 14px; }

.create-btn {
    background: white;
    color: #1e293b;
    border: none;
    padding: 11px 22px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: opacity 0.2s;
    text-decoration: none;
}

.create-btn:hover { opacity: 0.9; color: #1e293b; }

/* ── SUMMARY PILLS ── */
.summary-bar {
    display: flex;
    gap: 12px;
    margin-bottom: 28px;
    flex-wrap: wrap;
}

.summary-pill {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 50px;
    font-size: 13px;
    font-weight: 600;
    background: white;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
}

.summary-pill .dot {
    width: 10px; height: 10px;
    border-radius: 50%;
}

/* ── BOARD ── */
.board-wrapper { overflow-x: auto; padding-bottom: 20px; }

.kanban-board {
    display: grid;
    grid-template-columns: repeat(4, 300px);
    gap: 20px;
    min-width: max-content;
}

/* ── COLUMNS ── */
.board-column {
    border-radius: 24px;
    padding: 0;
    min-height: 700px;
    overflow: hidden;
    box-shadow: 0 6px 20px rgba(15,23,42,0.07);
}

.column-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 20px 16px;
}

.column-header h3 {
    font-size: 16px;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.task-count {
    padding: 4px 12px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 700;
}

.column-body {
    padding: 0 14px 14px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

/* PLANNING — blue */
.col-planning { background: #eff6ff; }
.col-planning .column-header { background: #2563eb; color: white; }
.col-planning .task-count { background: rgba(255,255,255,0.25); color: white; }
.col-planning .task-card { border-left: 4px solid #2563eb; }
.col-planning .task-card:hover { box-shadow: 0 8px 24px rgba(37,99,235,0.15); }
.col-planning .progress-bar { background: #2563eb !important; }
.col-planning .status-pill { background: #dbeafe; color: #1d4ed8; }

/* ACTIVE — orange */
.col-active { background: #fff7ed; }
.col-active .column-header { background: #ea580c; color: white; }
.col-active .task-count { background: rgba(255,255,255,0.25); color: white; }
.col-active .task-card { border-left: 4px solid #ea580c; }
.col-active .task-card:hover { box-shadow: 0 8px 24px rgba(234,88,12,0.15); }
.col-active .progress-bar { background: #ea580c !important; }
.col-active .status-pill { background: #ffedd5; color: #c2410c; }

/* REVIEW — purple */
.col-review { background: #faf5ff; }
.col-review .column-header { background: #7c3aed; color: white; }
.col-review .task-count { background: rgba(255,255,255,0.25); color: white; }
.col-review .task-card { border-left: 4px solid #7c3aed; }
.col-review .task-card:hover { box-shadow: 0 8px 24px rgba(124,58,237,0.15); }
.col-review .progress-bar { background: #7c3aed !important; }
.col-review .status-pill { background: #ede9fe; color: #6d28d9; }

/* COMPLETED — green */
.col-completed { background: #f0fdf4; }
.col-completed .column-header { background: #16a34a; color: white; }
.col-completed .task-count { background: rgba(255,255,255,0.25); color: white; }
.col-completed .task-card { border-left: 4px solid #16a34a; }
.col-completed .task-card:hover { box-shadow: 0 8px 24px rgba(22,163,74,0.15); }
.col-completed .progress-bar { background: #16a34a !important; }
.col-completed .status-pill { background: #dcfce7; color: #15803d; }

/* ── TASK CARD ── */
.task-link { text-decoration: none; color: inherit; }

.task-card {
    background: white;
    border-radius: 16px;
    padding: 16px;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.task-card:hover { transform: translateY(-3px); }

.task-title {
    font-size: 15px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 6px;
    line-height: 1.3;
}

.staff-label {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.task-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 12px;
}

.status-pill {
    font-size: 11px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 50px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.deadline-label {
    font-size: 12px;
    color: #94a3b8;
    display: flex;
    align-items: center;
    gap: 4px;
}

.deadline-label.urgent { color: #dc2626; }

/* ── PROGRESS ── */
.progress {
    height: 5px;
    margin-top: 12px;
    border-radius: 20px;
    background: #e2e8f0;
}

.progress-bar { border-radius: 20px; }

.progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    color: #94a3b8;
    margin-top: 5px;
}

/* ── EMPTY STATE ── */
.empty-col {
    text-align: center;
    padding: 40px 20px;
    color: #cbd5e1;
}

.empty-col i { font-size: 36px; margin-bottom: 10px; display: block; }
.empty-col p { font-size: 13px; margin: 0; }

/* ── MODAL ── */
.modal-content { border-radius: 24px !important; border: none !important; }
.modal-header  { padding: 24px 28px 0; }
.modal-body    { padding: 20px 28px; }
.modal-footer  { padding: 0 28px 24px; }

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

        <a href="main_board.php" class="active">
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

<!-- MAIN CONTENT -->
<div class="main-content">

    <!-- HEADER -->
    <div class="page-banner">
        <div>
            <h1><i class="fa-solid fa-layer-group me-2"></i>Campaign Board</h1>
            <p>Track all your ongoing campaigns and approvals.</p>
        </div>
        <button class="create-btn"
                data-bs-toggle="modal"
                data-bs-target="#addCampaignModal">
            <i class="fa-solid fa-plus"></i>
            Add Campaign
        </button>
    </div>

    <!-- SUMMARY PILLS -->
    <div class="summary-bar">

        <div class="summary-pill">
            <div class="dot" style="background:#2563eb;"></div>
            Planning &nbsp;<strong><?= count($planning); ?></strong>
        </div>

        <div class="summary-pill">
            <div class="dot" style="background:#ea580c;"></div>
            Active &nbsp;<strong><?= count($active); ?></strong>
        </div>

        <div class="summary-pill">
            <div class="dot" style="background:#7c3aed;"></div>
            Review &nbsp;<strong><?= count($review); ?></strong>
        </div>

        <div class="summary-pill">
            <div class="dot" style="background:#16a34a;"></div>
            Completed &nbsp;<strong><?= count($completed); ?></strong>
        </div>

    </div>

    <!-- BOARD -->
    <div class="board-wrapper">
    <div class="kanban-board">

        <?php
        function renderColumn($conn, $result, $label, $icon, $colClass, $emptyMsg){
            $count = count($result);

            echo "
            <div class='board-column $colClass'>
                <div class='column-header'>
                    <h3><i class='fa-solid $icon'></i> $label</h3>
                    <span class='task-count'>$count</span>
                </div>
                <div class='column-body'>
            ";

            if($count == 0){
                echo "
                <div class='empty-col'>
                    <i class='fa-regular fa-folder-open'></i>
                    <p>$emptyMsg</p>
                </div>
                ";
            }

            foreach($result as $row){
                global $conn;

                $campaign_id = $row['campaign_id'];
                $progress    = getProgress($conn, $campaign_id);

                $deadlineTs  = strtotime($row['deadline']);
                $daysLeft    = ceil(($deadlineTs - time()) / 86400);
                $urgentClass = ($daysLeft <= 3 && $label != 'Completed') ? 'urgent' : '';
                $deadlineText = date('M d, Y', $deadlineTs);

                $staff = htmlspecialchars($row['staff_name'] ?? 'Awaiting Assignment');
                $name  = htmlspecialchars($row['campaign_name']);
                $url   = "../campaigns/campaign_details.php?id=$campaign_id";

                echo "
                <a class='task-link' href='$url'>
                    <div class='task-card'>

                        <div class='task-title'>$name</div>

                        <div class='staff-label'>
                            <i class='fa-regular fa-user'></i>
                            $staff
                        </div>

                        <div class='task-meta'>
                            <span class='status-pill'>$label</span>
                            <span class='deadline-label $urgentClass'>
                                <i class='fa-regular fa-calendar'></i>
                                $deadlineText
                            </span>
                        </div>

                        <div class='progress'>
                            <div class='progress-bar' style='width:{$progress}%'></div>
                        </div>

                        <div class='progress-label'>
                            <span>Progress</span>
                            <span>{$progress}%</span>
                        </div>

                    </div>
                </a>
                ";
            }

            echo "</div></div>";
        }

        renderColumn($conn, $planning,  'Planning',  'fa-pencil',           'col-planning',  'No campaigns in planning.');
        renderColumn($conn, $active,    'Active',    'fa-bolt',             'col-active',    'No active campaigns.');
        renderColumn($conn, $review,    'Review',    'fa-magnifying-glass', 'col-review',    'No campaigns under review.');
        renderColumn($conn, $completed, 'Completed', 'fa-circle-check',     'col-completed', 'No completed campaigns yet.');
        ?>

    </div>
    </div>

</div>

<!-- ADD CAMPAIGN MODAL -->
<div class="modal fade" id="addCampaignModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">

            <div class="modal-header border-0">
                <h4 class="modal-title fw-bold">
                    <i class="fa-solid fa-plus me-2 text-primary"></i>
                    Create New Campaign
                </h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form action="../campaigns/create_campaign.php" method="POST">

                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Campaign Title</label>
                            <input type="text" name="campaign_name" class="form-control" required>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="4" required></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Budget</label>
                            <input type="number" name="budget" class="form-control" step="0.01" min="0" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Start Date</label>
                            <input type="date" name="start_date" class="form-control" min="<?= date('Y-m-d'); ?>" required>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Deadline</label>
                            <input type="date" name="deadline" class="form-control" min="<?= date('Y-m-d'); ?>" required>
                        </div>

                    </div>
                </div>

                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Create Campaign</button>
                </div>

            </form>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>