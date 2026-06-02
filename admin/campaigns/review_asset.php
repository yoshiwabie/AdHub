<?php
session_start();

include('../../config/db.php');
include('../../config/queries.php');
include('../../includes/topbar.php');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff'){
    header("Location: ../../index.php");
    exit();
}

$campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;

/*
========================================
GET ALL ASSETS FROM CAMPAIGN
========================================
*/
$assetsQuery = mysqli_query($conn, "
    SELECT
        a.asset_id,
        a.file_path,
        a.uploaded_at,
        a.milestone_id,
        m.title AS milestone_title,

        (
            SELECT status
            FROM approvals ap
            WHERE ap.milestone_id = a.milestone_id
            ORDER BY ap.created_at DESC
            LIMIT 1
        ) AS latest_status

    FROM assets a
    LEFT JOIN milestones m ON a.milestone_id = m.milestone_id
    WHERE m.campaign_id = '$campaign_id'
    ORDER BY a.uploaded_at DESC
");

/*
========================================
SELECTED ASSET
========================================
*/
$selected_asset_id = isset($_GET['asset_id']) ? intval($_GET['asset_id']) : 0;
$selected_asset    = null;

if($selected_asset_id > 0){
    $selectedQuery = mysqli_query($conn, "
        SELECT
            a.*,
            m.title    AS milestone_title,
            c.campaign_name,
            u.name     AS uploader_name,

            (
                SELECT status
                FROM approvals ap
                WHERE ap.milestone_id = a.milestone_id
                ORDER BY ap.created_at DESC
                LIMIT 1
            ) AS latest_status,

            (
                SELECT feedback
                FROM approvals ap
                WHERE ap.milestone_id = a.milestone_id
                ORDER BY ap.created_at DESC
                LIMIT 1
            ) AS latest_feedback

        FROM assets a
        LEFT JOIN milestones m ON a.milestone_id  = m.milestone_id
        LEFT JOIN campaigns c  ON m.campaign_id   = c.campaign_id
        LEFT JOIN users u      ON a.uploaded_by   = u.user_id
        WHERE a.asset_id = '$selected_asset_id'
        LIMIT 1
    ");

    if($selectedQuery && mysqli_num_rows($selectedQuery) > 0){
        $selected_asset = mysqli_fetch_assoc($selectedQuery);
    }
}

/*
========================================
COMMENTS
========================================
*/
$commentsQuery = null;

if($selected_asset){
    $mid = $selected_asset['milestone_id'];
    $commentsQuery = mysqli_query($conn, "
        SELECT a.*, u.name
        FROM approvals a
        LEFT JOIN users u ON a.client_id = u.user_id
        WHERE a.milestone_id = '$mid'
        ORDER BY a.created_at DESC
    ");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Review Assets | AdHub</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="/AdHub_V2/assets/css/style.css">
<link rel="stylesheet" href="/AdHub_V2/assets/css/dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

<style>

.main-content{
    margin-left:260px;
    padding:35px;
}

.page-title{
    font-size:28px;
    font-weight:700;
    color:var(--primary);
    margin-bottom:5px;
}

.page-subtitle{
    color:#64748b;
    font-size:14px;
    margin-bottom:25px;
}

/* PANEL CARDS */
.panel-card{
    background:white;
    border-radius:20px;
    padding:22px;
    box-shadow:0 4px 16px rgba(15,23,42,0.06);
    height:100%;
}

.panel-card h6{
    font-size:14px;
    font-weight:700;
    color:#0f172a;
    margin-bottom:14px;
    display:flex;
    align-items:center;
    gap:8px;
}

/* ASSET LIST */
.asset-item{
    display:flex;
    align-items:center;
    gap:10px;
    padding:10px 12px;
    border-radius:12px;
    cursor:pointer;
    transition:background 0.15s;
    text-decoration:none;
    color:#0f172a;
    margin-bottom:6px;
    border:1px solid #e2e8f0;
    font-size:13px;
}

.asset-item:hover{ background:#f1f5f9; color:#0f172a; }
.asset-item.active{ background:#eff6ff; border-color:#3b82f6; color:#1d4ed8; }

.asset-item .ext-badge{
    background:#e2e8f0;
    color:#475569;
    font-size:10px;
    font-weight:700;
    padding:2px 7px;
    border-radius:20px;
    text-transform:uppercase;
    flex-shrink:0;
}

/* PREVIEW */
.preview-box{
    background:#0f172a;
    border-radius:22px;
    padding:25px;
    text-align:center;
    min-height:560px;
    display:flex;
    align-items:center;
    justify-content:center;
    color:white;
    overflow:hidden;
    position:relative;
}

.preview-img{
    max-width:100%;
    max-height:500px;
    object-fit:contain;
    border-radius:14px;
}

.preview-video{
    width:100%;
    max-height:500px;
    border-radius:14px;
}

.preview-pdf{
    width:100%;
    height:500px;
    border:none;
    border-radius:14px;
    background:white;
}

/* APPROVED OVERLAY BADGE ON PREVIEW */
.approved-overlay{
    position:absolute;
    top:18px;
    right:18px;
    background:rgba(22,163,74,0.92);
    color:white;
    border-radius:999px;
    padding:8px 16px;
    font-size:13px;
    font-weight:700;
    display:flex;
    align-items:center;
    gap:7px;
    backdrop-filter:blur(4px);
    box-shadow:0 4px 12px rgba(0,0,0,0.25);
    pointer-events:none;
}

/* REVISION OVERLAY BADGE ON PREVIEW */
.revision-overlay{
    position:absolute;
    top:18px;
    right:18px;
    background:rgba(217,119,6,0.92);
    color:white;
    border-radius:999px;
    padding:8px 16px;
    font-size:13px;
    font-weight:700;
    display:flex;
    align-items:center;
    gap:7px;
    backdrop-filter:blur(4px);
    box-shadow:0 4px 12px rgba(0,0,0,0.25);
    pointer-events:none;
}

/* DETAILS */
.detail-row{
    padding:10px 0;
    border-bottom:1px solid #f1f5f9;
}

.detail-row:last-child{ border:none; }
.detail-label{ font-size:12px; color:#64748b; margin-bottom:2px; }
.detail-value{ font-size:14px; font-weight:600; color:#0f172a; }

.ext-pill{
    display:inline-block;
    background:#1e293b;
    color:white;
    font-size:11px;
    font-weight:700;
    padding:4px 12px;
    border-radius:30px;
    margin-top:6px;
    text-transform:uppercase;
    letter-spacing:0.5px;
}

/* STATUS PILL */
.status-pill{
    display:inline-block;
    padding:5px 12px;
    border-radius:999px;
    font-size:12px;
    font-weight:700;
    margin-top:6px;
}

.status-approved{ background:#dcfce7; color:#166534; }
.status-revision{ background:#fef3c7; color:#92400e; }
.status-pending{  background:#e2e8f0; color:#334155; }

/* STATUS BANNER IN DETAILS */
.status-banner{
    border-radius:14px;
    padding:13px 15px;
    display:flex;
    align-items:center;
    gap:11px;
    margin-top:18px;
}

.status-banner.approved{
    background:#f0fdf4;
    border:1.5px solid #86efac;
}

.status-banner.revision{
    background:#fffbeb;
    border:1.5px solid #fcd34d;
}

.status-banner i{ font-size:20px; flex-shrink:0; }
.status-banner.approved i{ color:#16a34a; }
.status-banner.revision i{ color:#d97706; }

.status-banner-title{
    font-size:13px;
    font-weight:700;
}

.status-banner.approved .status-banner-title{ color:#166534; }
.status-banner.revision .status-banner-title{ color:#92400e; }

.status-banner-sub{
    font-size:11px;
    margin-top:2px;
}

.status-banner.approved .status-banner-sub{ color:#15803d; }
.status-banner.revision .status-banner-sub{ color:#b45309; }

/* FEEDBACK */
.feedback-item{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:14px;
    padding:14px;
    margin-bottom:10px;
    transition:box-shadow 0.15s;
}

.feedback-item:hover{ box-shadow:0 4px 12px rgba(0,0,0,0.05); }

.feedback-avatar{
    width:32px;height:32px;
    border-radius:50%;
    background:#e2e8f0;
    display:flex;align-items:center;justify-content:center;
    font-weight:700;font-size:13px;color:#475569;
    flex-shrink:0;
}

/* EMPTY */
.empty-preview{
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:12px;
}

.empty-preview i{ font-size:64px; opacity:0.4; }

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

    <div class="page-title">
        <i class="fa-solid fa-photo-film me-2"></i>Review Assets
    </div>
    <div class="page-subtitle">
        Browse and preview uploaded campaign assets.
    </div>

    <div class="row g-4">

        <!-- LEFT: ASSET LIST -->
        <div class="col-lg-3">
            <div class="panel-card">

                <h6>
                    <i class="fa-solid fa-folder-open text-primary"></i>
                    Campaign Assets
                </h6>

                <?php if(mysqli_num_rows($assetsQuery) > 0){ ?>

                    <?php while($a = mysqli_fetch_assoc($assetsQuery)) {
                        $ext       = strtoupper(pathinfo($a['file_path'], PATHINFO_EXTENSION));
                        $isActive  = ($selected_asset_id == $a['asset_id']) ? 'active' : '';
                        $shortName = strlen(basename($a['file_path'])) > 22
                            ? substr(basename($a['file_path']), 0, 22) . '…'
                            : basename($a['file_path']);
                        $aStatus   = $a['latest_status'] ?? '';
                    ?>

                    <a href="?campaign_id=<?= $campaign_id; ?>&asset_id=<?= $a['asset_id']; ?>"
                       class="asset-item <?= $isActive; ?>">

                        <span class="ext-badge"><?= $ext; ?></span>

                        <div style="flex:1; min-width:0;">
                            <div><?= htmlspecialchars($shortName); ?></div>
                            <div style="font-size:11px;color:#94a3b8;">
                                <?= htmlspecialchars($a['milestone_title']); ?>
                            </div>
                        </div>

                        <?php if($aStatus == 'approved'){ ?>
                            <i class="fa-solid fa-circle-check"
                               style="color:#16a34a; font-size:14px; flex-shrink:0;"
                               title="Approved"></i>
                        <?php } elseif($aStatus == 'revision'){ ?>
                            <i class="fa-solid fa-rotate-left"
                               style="color:#d97706; font-size:14px; flex-shrink:0;"
                               title="Revision Requested"></i>
                        <?php } ?>

                    </a>

                    <?php } ?>

                <?php } else { ?>
                    <div class="text-center text-muted py-4" style="font-size:13px;">
                        <i class="fa-regular fa-folder-open d-block mb-2" style="font-size:28px;"></i>
                        No assets uploaded yet.
                    </div>
                <?php } ?>

            </div>
        </div>

        <!-- MIDDLE: PREVIEW -->
        <div class="col-lg-6">
            <div class="preview-box">

                <?php if($selected_asset){

                    $file        = ltrim(trim($selected_asset['file_path']), '/');
                    $browserPath = "/AdHub_V2/assets/" . $file;
                    $ext         = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    $previewStatus = $selected_asset['latest_status'] ?? '';
                ?>

                    <?php if($previewStatus == 'approved'){ ?>
                        <div class="approved-overlay">
                            <i class="fa-solid fa-circle-check"></i>
                            Approved
                        </div>
                    <?php } elseif($previewStatus == 'revision'){ ?>
                        <div class="revision-overlay">
                            <i class="fa-solid fa-rotate-left"></i>
                            Revision Requested
                        </div>
                    <?php } ?>

                    <?php if(in_array($ext, ['jpg','jpeg','png','gif','webp'])){ ?>

                        <img src="<?= $browserPath; ?>" class="preview-img">

                    <?php } elseif(in_array($ext, ['mp4','mov','webm'])){ ?>

                        <video controls class="preview-video">
                            <source src="<?= $browserPath; ?>">
                        </video>

                    <?php } elseif($ext == 'pdf'){ ?>

                        <iframe src="<?= $browserPath; ?>" class="preview-pdf"></iframe>

                    <?php } else { ?>

                        <div class="empty-preview">
                            <i class="fa-solid fa-file-lines"></i>
                            <h5>Preview not available</h5>
                            <p style="color:#94a3b8; font-size:14px;">
                                This file type cannot be previewed.
                            </p>
                            <a href="<?= $browserPath; ?>" target="_blank"
                               class="btn btn-light btn-sm">
                                <i class="fa-solid fa-download me-1"></i>Open File
                            </a>
                        </div>

                    <?php } ?>

                <?php } else { ?>

                    <div class="empty-preview">
                        <i class="fa-regular fa-image"></i>
                        <h4>No Asset Selected</h4>
                        <p style="color:#94a3b8; font-size:14px;">
                            Pick an asset from the left panel.
                        </p>
                    </div>

                <?php } ?>

            </div>
        </div>

        <!-- RIGHT: DETAILS -->
        <div class="col-lg-3">
            <div class="panel-card">

                <h6>
                    <i class="fa-solid fa-circle-info text-info"></i>
                    Asset Details
                </h6>

                <?php if($selected_asset){

                    $detailStatus = $selected_asset['latest_status'] ?? 'pending';

                ?>

                    <div class="detail-row">
                        <div class="detail-label">Campaign</div>
                        <div class="detail-value"><?= htmlspecialchars($selected_asset['campaign_name']); ?></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">Milestone</div>
                        <div class="detail-value"><?= htmlspecialchars($selected_asset['milestone_title']); ?></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">Uploaded By</div>
                        <div class="detail-value"><?= htmlspecialchars($selected_asset['uploader_name'] ?? 'Unknown'); ?></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">Uploaded At</div>
                        <div class="detail-value"><?= date("M d, Y h:i A", strtotime($selected_asset['uploaded_at'])); ?></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">File Name</div>
                        <div class="detail-value" style="word-break:break-all; font-size:13px;">
                            <?= htmlspecialchars(basename($selected_asset['file_path'])); ?>
                        </div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label">Client Status</div>
                        <div class="detail-value">
                            <?php
                                $sPillClass = 'status-pending';
                                if($detailStatus == 'approved') $sPillClass = 'status-approved';
                                elseif($detailStatus == 'revision') $sPillClass = 'status-revision';
                            ?>
                            <span class="status-pill <?= $sPillClass; ?>">
                                <?= $detailStatus == 'pending' ? 'Awaiting Review' : ucfirst($detailStatus); ?>
                            </span>
                        </div>
                    </div>

                    <span class="ext-pill">
                        <?= strtoupper(pathinfo($selected_asset['file_path'], PATHINFO_EXTENSION)); ?>
                    </span>

                    <!-- STATUS BANNER -->
                    <?php if($detailStatus == 'approved'){ ?>

                        <div class="status-banner approved">
                            <i class="fa-solid fa-circle-check"></i>
                            <div>
                                <div class="status-banner-title">Client Approved</div>
                                <div class="status-banner-sub">No further action needed.</div>
                            </div>
                        </div>

                    <?php } elseif($detailStatus == 'revision'){ ?>

                        <div class="status-banner revision">
                            <i class="fa-solid fa-rotate-left"></i>
                            <div>
                                <div class="status-banner-title">Revision Requested</div>
                                <div class="status-banner-sub">
                                    <?= htmlspecialchars($selected_asset['latest_feedback'] ?? 'See feedback below.'); ?>
                                </div>
                            </div>
                        </div>

                    <?php } ?>

                <?php } else { ?>
                    <p class="text-muted" style="font-size:13px;">
                        Select an asset to see details.
                    </p>
                <?php } ?>

            </div>
        </div>

    </div>

    <!-- FEEDBACK -->
    <div class="panel-card mt-4">

        <h6>
            <i class="fa-solid fa-comments text-success"></i>
            Client Feedback
            <?php if($commentsQuery){ ?>
                <span class="badge bg-secondary ms-1" style="font-size:11px;">
                    <?= mysqli_num_rows($commentsQuery); ?>
                </span>
            <?php } ?>
        </h6>

        <?php if($selected_asset){ ?>

            <?php if($commentsQuery && mysqli_num_rows($commentsQuery) > 0){ ?>

                <div style="max-height:300px; overflow-y:auto; padding-right:4px;">

                    <?php while($c = mysqli_fetch_assoc($commentsQuery)) { ?>

                        <div class="feedback-item">

                            <div class="d-flex align-items-center gap-2 mb-2">

                                <div class="feedback-avatar">
                                    <?= strtoupper(substr($c['name'], 0, 1)); ?>
                                </div>

                                <div class="flex-grow-1">
                                    <strong style="font-size:14px;">
                                        <?= htmlspecialchars($c['name']); ?>
                                    </strong>
                                </div>

                                <small class="text-muted">
                                    <?= date('M d, Y', strtotime($c['created_at'])); ?>
                                </small>

                                <span class="badge <?= $c['status'] == 'approved' ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                    <?= ucfirst($c['status']); ?>
                                </span>

                            </div>

                            <p class="mb-0 text-muted" style="font-size:14px; padding-left:40px;">
                                <?= htmlspecialchars($c['feedback']); ?>
                            </p>

                        </div>

                    <?php } ?>

                </div>

            <?php } else { ?>

                <div class="text-center text-muted py-4" style="font-size:13px;">
                    <i class="fa-regular fa-comment-dots d-block mb-2" style="font-size:28px;"></i>
                    No feedback for this asset yet.
                </div>

            <?php } ?>

        <?php } else { ?>

            <p class="text-muted" style="font-size:13px;">
                Select an asset to view client feedback.
            </p>

        <?php } ?>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>