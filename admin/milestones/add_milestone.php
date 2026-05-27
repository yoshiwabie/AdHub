<?php
session_start();

include('../../config/db.php');
include('../../includes/topbar.php');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff'){
    header("Location: ../../index.php");
    exit();
}

// ✅ FIXED: Get campaign_id FIRST before using it
$campaign_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($campaign_id <= 0){
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ✅ FIXED: Now fetch campaign AFTER campaign_id is defined
$campaignQuery = mysqli_query($conn, "SELECT * FROM campaigns WHERE campaign_id = '$campaign_id' LIMIT 1");
$campaign = mysqli_fetch_assoc($campaignQuery);

// Campaign not found or unassigned staff
if(!$campaign || $campaign['assigned_staff_id'] != $user_id){
    header("Location: ../../index.php");
    exit();
}

$errors = [];
$success = false;

if(isset($_POST['save'])){

    $title    = trim($_POST['title'] ?? '');
    $deadline = trim($_POST['deadline'] ?? '');

    // Basic validation
    if(empty($title)){
        $errors[] = "Milestone title is required.";
    }

    if(empty($deadline)){
        $errors[] = "Deadline is required.";
    } elseif($deadline < $campaign['start_date']){
        $errors[] = "Deadline cannot be before the campaign start date.";
    } elseif($deadline > $campaign['deadline']){
        $errors[] = "Deadline cannot exceed the campaign deadline ("
                    . date('M d, Y', strtotime($campaign['deadline'])) . ").";
    }

    if(empty($errors)){

        $title_safe    = mysqli_real_escape_string($conn, $title);
        $deadline_safe = mysqli_real_escape_string($conn, $deadline);

        mysqli_query($conn,"
            INSERT INTO milestones (campaign_id, title, deadline, status)
            VALUES ('$campaign_id', '$title_safe', '$deadline_safe', 'pending')
        ");

        include('../../config/update_campaign_status.php');
        updateCampaignStatus($conn, $campaign_id);

        header("Location: ../campaigns/campaign_details.php?id=$campaign_id");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Milestone | AdHub</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<link rel="stylesheet" href="/AdHub_V2/assets/css/style.css">
<link rel="stylesheet" href="/AdHub_V2/assets/css/dashboard.css">

<style>

.main-content {
    margin-left: 260px;
    margin-top: 75px;
    padding: 35px;
}

.form-card {
    max-width: 650px;
    margin: auto;
    background: white;
    border-radius: 24px;
    padding: 35px;
    box-shadow: 0 6px 20px rgba(15,23,42,0.08);
}

.form-title {
    font-size: 26px;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 6px;
}

.form-subtitle {
    color: #64748b;
    font-size: 14px;
    margin-bottom: 28px;
}

/* Campaign context banner */
.campaign-context {
    background: #f1f5f9;
    border-radius: 14px;
    padding: 14px 18px;
    margin-bottom: 24px;
    font-size: 13px;
    color: #475569;
    display: flex;
    align-items: center;
    gap: 10px;
    border-left: 4px solid var(--primary, #3b82f6);
}

.campaign-context strong {
    color: #0f172a;
}

.custom-btn {
    background: var(--primary, #3b82f6);
    border: none;
    padding: 12px 24px;
    border-radius: 14px;
    color: white;
    font-weight: 600;
    transition: opacity 0.2s;
}

.custom-btn:hover {
    opacity: 0.88;
    color: white;
}

.form-control:focus {
    border-color: var(--primary, #3b82f6);
    box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
}

.deadline-hint {
    font-size: 12px;
    color: #94a3b8;
    margin-top: 5px;
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
    <div class="form-card">

        <div class="form-title">
            <i class="fa-solid fa-flag me-2"></i>Add Milestone
        </div>

        <div class="form-subtitle">
            Create a new milestone for this campaign.
        </div>

        <!-- Campaign context banner -->
        <div class="campaign-context">
            <i class="fa-solid fa-bullhorn"></i>
            <span>
                Campaign: <strong><?= htmlspecialchars($campaign['campaign_name']); ?></strong>
                &nbsp;·&nbsp;
                Deadline: <strong><?= date('M d, Y', strtotime($campaign['deadline'])); ?></strong>
            </span>
        </div>

        <!-- Validation errors -->
        <?php if(!empty($errors)){ ?>
            <div class="alert alert-danger border-0 rounded-3 mb-4" style="font-size:14px;">
                <i class="fa-solid fa-circle-exclamation me-2"></i>
                <?php foreach($errors as $err){ ?>
                    <div><?= htmlspecialchars($err); ?></div>
                <?php } ?>
            </div>
        <?php } ?>

        <form method="POST" novalidate>

            <!-- Title -->
            <div class="mb-4">
                <label class="form-label fw-semibold">
                    Milestone Title <span class="text-danger">*</span>
                </label>
                <input type="text"
                       name="title"
                       class="form-control"
                       placeholder="e.g. Initial Design Draft"
                       value="<?= htmlspecialchars($_POST['title'] ?? ''); ?>"
                       required>
            </div>

            <!-- Deadline -->
            <div class="mb-4">
                <label class="form-label fw-semibold">
                    Milestone Deadline <span class="text-danger">*</span>
                </label>
                <input type="date"
                       name="deadline"
                       class="form-control"
                       min="<?= $campaign['start_date']; ?>"
                       max="<?= $campaign['deadline']; ?>"
                       value="<?= htmlspecialchars($_POST['deadline'] ?? ''); ?>"
                       required>
                <div class="deadline-hint">
                    <i class="fa-regular fa-calendar me-1"></i>
                    Must be between
                    <?= date('M d, Y', strtotime($campaign['start_date'])); ?>
                    and
                    <?= date('M d, Y', strtotime($campaign['deadline'])); ?>
                </div>
            </div>

            <!-- Actions -->
            <div class="d-flex align-items-center gap-2 mt-2">

                <button type="submit" name="save" class="custom-btn">
                    <i class="fa-solid fa-plus me-1"></i>
                    Save Milestone
                </button>

                <a href="../campaigns/campaign_details.php?id=<?= $campaign_id; ?>"
                   class="btn btn-light"
                   style="border-radius:14px; padding:12px 20px;">
                    Cancel
                </a>

            </div>

        </form>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>