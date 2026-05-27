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

$campaign_id = $_GET['id'] ?? 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Campaign Manager | AdHub</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="/AdHub_V2/assets/css/style.css">
<link rel="stylesheet" href="/AdHub_V2/assets/css/dashboard.css">

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
/>

</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-menu">

        <a href="../dashboard/dashboard.php">
            <i class="fa-solid fa-table-columns"></i> Dashboard
        </a>

        <a href="../kanban/main_board.php" class="active">
            <i class="fa-solid fa-layer-group"></i> Campaigns
        </a>

        <a href="../timelogs/time_logs.php">
            <i class="fa-regular fa-clock"></i> Time Logs
        </a>

        <a href="../notifications/notifications.php">
            <i class="fa-regular fa-bell"></i> Notifications
        </a>

        <a href="../../auth/logout.php" class="logout-link">
            <i class="fa-solid fa-right-from-bracket"></i>Logout
        </a>

    </div>
</div>

<!-- MAIN -->
<div class="main-content">

<div class="container-fluid">

<!-- HEADER -->
<div class="dashboard-hero mb-4">
    <h1>Summer Nike Campaign</h1>
    <p>Promotional campaign for Nike summer collection launch.</p>
</div>

<div class="d-flex gap-2 mb-4">

    <a href="../reports/campaign_report.php?id=<?php echo $campaign_id; ?>" 
       class="btn btn-primary">

        <i class="fa-solid fa-chart-line"></i>
        View Campaign Report

    </a>

    <button onclick="window.print()" class="btn btn-outline-dark">

        <i class="fa-solid fa-print"></i>
        Print Summary

    </button>

</div>

<!-- INFO -->
<div class="stats-grid">

    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fa-solid fa-calendar"></i>
        </div>
        <div>
            <p>Duration</p>
            <h2 style="font-size:18px;">May 1 - June 30</h2>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fa-solid fa-user"></i>
        </div>
        <div>
            <p>Assigned Staff</p>
            <h2 style="font-size:18px;">3 Members</h2>
        </div>
    </div>

</div>

<!-- ROW -->
<div class="dashboard-grid">

<!-- ASSETS -->
<div class="dashboard-card">

    <div class="d-flex justify-content-between align-items-center mb-3">

        <div class="card-header-custom">
            <h3>Assets</h3>
        </div>

        <!-- UPLOAD BUTTON -->
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadAsset">
            <i class="fa fa-upload"></i> Upload
        </button>

    </div>

    <table class="table custom-table">
        <thead>
            <tr>
                <th>File</th>
                <th>Type</th>
                <th>Status</th>
            </tr>
        </thead>

        <tbody>
            <tr>
                <td>banner.psd</td>
                <td>PSD</td>
                <td><span class="badge bg-success">Approved</span></td>
            </tr>
        </tbody>
    </table>

</div>

<!-- BUDGET -->
<div class="dashboard-card">

    <div class="card-header-custom">
        <h3>Budget</h3>
    </div>

    <div class="progress-item">
        <div class="progress-top">
            <span>Used</span>
            <span>60%</span>
        </div>
        <div class="progress">
            <div class="progress-bar" style="width:60%"></div>
        </div>
    </div>

</div>

</div>

<!-- FEEDBACK -->
<div class="dashboard-card mt-4">

    <div class="card-header-custom">
        <h3>Feedback</h3>
    </div>

    <!-- CLICKABLE FEEDBACK -->
    <div class="notification-item" onclick="markRevised(1)">

        <div class="notification-icon blue">
            <i class="fa-solid fa-comment"></i>
        </div>

        <div class="notification-content">
            <h5>Client Feedback</h5>
            <p>Make banner more vibrant</p>
            <span>Click to mark as revised</span>
        </div>

    </div>

</div>

<!-- MILESTONES -->
<div class="dashboard-card mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">

        <div class="card-header-custom">
            <h3>Milestones</h3>
        </div>

        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addMilestone">
            <i class="fa fa-plus"></i> Add
        </button>

    </div>

    <div class="progress-item">
        <div class="progress-top">
            <span>Design Phase</span>
            <span>100%</span>
        </div>
        <div class="progress">
            <div class="progress-bar" style="width:100%"></div>
        </div>
    </div>

</div>

</div>

</div>

<!-- ================= MODAL: UPLOAD ASSET ================= -->
<div class="modal fade" id="uploadAsset">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header">
        <h5>Upload Asset</h5>
      </div>

      <div class="modal-body">

        <input type="file" class="form-control mb-2">
        <button class="btn btn-primary w-100">Upload</button>

      </div>

    </div>
  </div>
</div>

<!-- ================= MODAL: ADD MILESTONE ================= -->
<div class="modal fade" id="addMilestone">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header">
        <h5>Add Milestone</h5>
      </div>

      <div class="modal-body">
y
        <input type="text" class="form-control mb-2" placeholder="Milestone name">
        <button class="btn btn-success w-100">Add</button>

      </div>

    </div>
  </div>
</div>

<script>
function markRevised(id){
    alert("Marked as revised (you will connect this to DB + notification later)");
}
</script>

</body>
</html>