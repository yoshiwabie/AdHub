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

$user_id     = $_SESSION['user_id'];
$campaign_id = intval($_GET['campaign_id'] ?? $_GET['id'] ?? $_POST['campaign_id'] ?? 0);

$campaignQuery = mysqli_query($conn,"
    SELECT c.*, u.name AS staff_name
    FROM campaigns c
    LEFT JOIN users u ON c.assigned_staff_id = u.user_id
    WHERE c.campaign_id = '$campaign_id'
    AND c.client_id = '$user_id'
    LIMIT 1
");

$campaign = mysqli_fetch_assoc($campaignQuery);

if(!$campaign){
    die("Campaign not found.");
}

/*
========================================
APPROVE ASSET
========================================
*/
if(isset($_POST['approve_asset'])){

    $asset_id     = intval($_POST['asset_id']);
    $milestone_id = intval($_POST['milestone_id']);

    mysqli_query($conn,"
        INSERT INTO approvals(milestone_id, client_id, status, feedback)
        VALUES('$milestone_id', '$user_id', 'approved', 'Approved by client.')
    ");

    mysqli_query($conn,"
        UPDATE milestones SET status = 'approved'
        WHERE milestone_id = '$milestone_id'
    ");

    $remaining = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT COUNT(*) as cnt
        FROM milestones
        WHERE campaign_id = '$campaign_id'
        AND status != 'approved'
    "));

    if($remaining['cnt'] == 0){

        mysqli_query($conn,"
            UPDATE campaigns
            SET status = 'review'
            WHERE campaign_id = '$campaign_id'
        ");

    }else{

        mysqli_query($conn,"
            UPDATE campaigns
            SET status = 'active'
            WHERE campaign_id = '$campaign_id'
        ");
    }

    $staff_id = $campaign['assigned_staff_id'];

    if($staff_id){
        mysqli_query($conn,"
            INSERT INTO notifications(user_id, title, message)
            VALUES('$staff_id', 'Asset Approved', 'A client approved an asset in campaign #$campaign_id.')
        ");
    }

    header("Location: review_asset.php?id=$campaign_id&asset_id=$asset_id");
    exit();
}

/*
========================================
REQUEST REVISION
========================================
*/
if(isset($_POST['request_revision'])){

    $asset_id     = intval($_POST['asset_id']);
    $milestone_id = intval($_POST['milestone_id']);
    $feedback     = mysqli_real_escape_string($conn, trim($_POST['feedback']));

    mysqli_query($conn,"
        INSERT INTO approvals(milestone_id, client_id, status, feedback)
        VALUES('$milestone_id', '$user_id', 'revision', '$feedback')
    ");

    include('../../config/update_campaign_status.php');
    updateCampaignStatus($conn, $campaign_id);

    mysqli_query($conn,"
        UPDATE milestones SET status = 'revision'
        WHERE milestone_id = '$milestone_id'
    ");

    $staff_id = $campaign['assigned_staff_id'];

    if($staff_id){
        mysqli_query($conn,"
            INSERT INTO notifications(user_id, title, message)
            VALUES('$staff_id', 'Revision Requested', 'A client requested revisions for an asset in campaign #$campaign_id.')
        ");
    }

    header("Location: review_asset.php?id=$campaign_id&asset_id=$asset_id");
    exit();
}

/*
========================================
GET ALL CAMPAIGN ASSETS
========================================
*/

$assetsQuery = mysqli_query($conn,"
    SELECT
        a.asset_id,
        a.file_path,
        a.uploaded_at,
        a.milestone_id,

        m.title AS milestone_title,
        m.status AS latest_status

    FROM assets a

    LEFT JOIN milestones m
        ON a.milestone_id = m.milestone_id

    WHERE m.campaign_id = '$campaign_id'

    ORDER BY a.uploaded_at DESC
");

/*
========================================
SELECTED ASSET
========================================
*/

$selected_asset_id = isset($_GET['asset_id'])
    ? intval($_GET['asset_id'])
    : 0;

$selected_asset = null;

if($selected_asset_id > 0){

    $selectedQuery = mysqli_query($conn,"
        SELECT
            a.*,

            m.title AS milestone_title,
            m.status AS latest_status,

            c.campaign_name,

            u.name AS uploader_name,

            (
                SELECT feedback
                FROM approvals ap
                WHERE ap.milestone_id = a.milestone_id
                ORDER BY ap.created_at DESC
                LIMIT 1
            ) AS latest_feedback

        FROM assets a

        LEFT JOIN milestones m
            ON a.milestone_id = m.milestone_id

        LEFT JOIN campaigns c
            ON m.campaign_id = c.campaign_id

        LEFT JOIN users u
            ON a.uploaded_by = u.user_id

        WHERE a.asset_id = '$selected_asset_id'

        LIMIT 1
    ");

    if($selectedQuery && mysqli_num_rows($selectedQuery) > 0){

        $selected_asset = mysqli_fetch_assoc($selectedQuery);
    }
}

/*
========================================
COMMENTS / FEEDBACK HISTORY
========================================
*/

$commentsQuery = null;

if($selected_asset){

    $mid = $selected_asset['milestone_id'];

    $commentsQuery = mysqli_query($conn,"
        SELECT
            a.*,
            u.name
        FROM approvals a

        LEFT JOIN users u
            ON a.client_id = u.user_id

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

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

<link rel="stylesheet" href="/AdHub_V2/assets/css/style.css">
<link rel="stylesheet" href="/AdHub_V2/assets/css/dashboard.css">

<style>

.main-content{
    margin-left:260px;
    margin-top:75px;
    padding:35px;
}

.review-header{
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

.review-header h1{
    font-size:28px;
    font-weight:700;
    margin:0;
    color:white;
}

.review-header p{
    margin:8px 0 0;
    color:#94a3b8;
    font-size:14px;
}

/* PANEL */
.panel-card{
    background:white;
    border-radius:22px;
    padding:22px;
    box-shadow:0 4px 16px rgba(15,23,42,0.06);
    height:100%;
}

.panel-card h6{
    font-size:15px;
    font-weight:700;
    color:#0f172a;
    margin-bottom:16px;
    display:flex;
    align-items:center;
    gap:8px;
}

/* ASSET LIST */
.asset-item{
    display:flex;
    align-items:center;
    gap:12px;
    padding:12px;
    border-radius:14px;
    text-decoration:none;
    color:#0f172a;
    border:1px solid #e2e8f0;
    margin-bottom:8px;
    transition:0.2s;
}

.asset-item:hover{
    background:#f8fafc;
    color:#0f172a;
    transform:translateY(-1px);
}

.asset-item.active{
    background:#eff6ff;
    border-color:#3b82f6;
}

.ext-badge{
    background:#1e293b;
    color:white;
    min-width:48px;
    text-align:center;
    font-size:11px;
    font-weight:700;
    padding:5px 10px;
    border-radius:999px;
}

.asset-name{
    font-size:13px;
    font-weight:600;
}

.asset-sub{
    font-size:11px;
    color:#94a3b8;
}

/* PREVIEW */
.preview-box{
    background:#0f172a;
    border-radius:22px;
    min-height:580px;
    padding:25px;
    display:flex;
    align-items:center;
    justify-content:center;
    overflow:hidden;
    position:relative;
}

.preview-img{
    max-width:100%;
    max-height:520px;
    border-radius:14px;
    object-fit:contain;
}

.preview-video{
    width:100%;
    max-height:520px;
    border-radius:14px;
}

.preview-pdf{
    width:100%;
    height:520px;
    border:none;
    border-radius:14px;
    background:white;
}

.empty-preview{
    color:white;
    text-align:center;
}

.empty-preview i{
    font-size:60px;
    margin-bottom:15px;
    opacity:0.4;
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

/* DETAILS */
.detail-row{
    padding:12px 0;
    border-bottom:1px solid #f1f5f9;
}

.detail-row:last-child{
    border:none;
}

.detail-label{
    font-size:12px;
    color:#64748b;
    margin-bottom:3px;
}

.detail-value{
    font-size:14px;
    font-weight:600;
    color:#0f172a;
}

.status-pill{
    display:inline-block;
    padding:7px 14px;
    border-radius:999px;
    font-size:12px;
    font-weight:700;
}

.status-approved{
    background:#dcfce7;
    color:#166534;
}

.status-revision{
    background:#fef3c7;
    color:#92400e;
}

.status-pending{
    background:#e2e8f0;
    color:#334155;
}

/* APPROVED BANNER */
.approved-banner{
    background:#f0fdf4;
    border:1.5px solid #86efac;
    border-radius:16px;
    padding:14px 16px;
    display:flex;
    align-items:center;
    gap:12px;
    margin-top:20px;
}

.approved-banner i{
    color:#16a34a;
    font-size:22px;
    flex-shrink:0;
}

.approved-banner-title{
    font-size:13px;
    font-weight:700;
    color:#166534;
}

.approved-banner-sub{
    font-size:11px;
    color:#15803d;
    margin-top:2px;
}

/* ACTIONS */
.action-buttons{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-top:20px;
}

.btn-modern{
    border:none;
    border-radius:12px;
    padding:10px 18px;
    font-size:14px;
    font-weight:600;
}

/* FEEDBACK */
.feedback-item{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:12px;
    padding:12px;
    margin-bottom:8px;
    transition:0.2s;
}

.feedback-item:hover{
    box-shadow:0 4px 10px rgba(0,0,0,0.05);
}

.feedback-avatar{
    width:30px;
    height:30px;
    border-radius:50%;
    background:#dbeafe;
    color:#1d4ed8;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:12px;
    font-weight:700;
    flex-shrink:0;
}

.modal-content{
    border:none;
    border-radius:24px;
}

.form-control{
    border-radius:14px;
    padding:12px;
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

<!-- MAIN -->

<div class="main-content">

    <!-- HEADER -->

    <div class="review-header">

        <div>

            <h1>
                <i class="fa-solid fa-photo-film me-2"></i>
                Review Assets
            </h1>

            <p>
                Review deliverables uploaded by your AdHub team,
                approve assets, or request revisions.
            </p>

        </div>

        <div>

            <a href="campaign_details.php?id=<?= $campaign_id; ?>"
               class="btn btn-outline-light btn-sm">

                <i class="fa-solid fa-arrow-left"></i>
                Campaign Details

            </a>

        </div>

    </div>

    <div class="row g-4">

        <!-- LEFT PANEL -->

        <div class="col-lg-3">

            <div class="panel-card">

                <h6>
                    <i class="fa-solid fa-folder-open text-primary"></i>
                    Campaign Assets
                </h6>

                <?php if(mysqli_num_rows($assetsQuery) > 0){ ?>

                    <?php while($a = mysqli_fetch_assoc($assetsQuery)) {

                        $ext = strtoupper(
                            pathinfo(
                                $a['file_path'],
                                PATHINFO_EXTENSION
                            )
                        );

                        $isActive = (
                            $selected_asset_id == $a['asset_id']
                        ) ? 'active' : '';

                        $shortName = strlen(
                            basename($a['file_path'])
                        ) > 24
                        ? substr(
                            basename($a['file_path']),
                            0,
                            24
                        ).'...'
                        : basename($a['file_path']);

                    ?>

                    <a href="?id=<?= $campaign_id; ?>&asset_id=<?= $a['asset_id']; ?>"
                       class="asset-item <?= $isActive; ?>">

                        <span class="ext-badge">
                            <?= $ext; ?>
                        </span>

                        <div style="flex:1; min-width:0;">

                            <div class="asset-name">
                                <?= htmlspecialchars($shortName); ?>
                            </div>

                            <div class="asset-sub">
                                <?= htmlspecialchars($a['milestone_title']); ?>
                            </div>

                        </div>

                        <?php if($a['latest_status'] == 'approved'){ ?>
                            <i class="fa-solid fa-circle-check"
                               style="color:#16a34a; font-size:14px; flex-shrink:0;"
                               title="Approved"></i>
                        <?php } elseif($a['latest_status'] == 'revision'){ ?>
                            <i class="fa-solid fa-rotate-left"
                               style="color:#d97706; font-size:14px; flex-shrink:0;"
                               title="Revision Requested"></i>
                        <?php } ?>

                    </a>

                    <?php } ?>

                <?php } else { ?>

                    <div class="text-center text-muted py-5">

                        <i class="fa-regular fa-folder-open d-block mb-2"
                           style="font-size:38px;"></i>

                        No assets uploaded yet.

                    </div>

                <?php } ?>

            </div>

        </div>

        <!-- PREVIEW -->

        <div class="col-lg-6">

            <div class="preview-box">

                <?php if($selected_asset){

                    $file = basename($selected_asset['file_path']);
                    $browserPath = "/AdHub_V2/assets/uploads/" . $file;
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

                ?>

                    <?php if($selected_asset['latest_status'] == 'approved'){ ?>
                        <div class="approved-overlay">
                            <i class="fa-solid fa-circle-check"></i>
                            Approved
                        </div>
                    <?php } ?>

                    <?php if(in_array($ext,['jpg','jpeg','png','gif','webp'])){ ?>

                        <img src="<?= $browserPath; ?>"
                             class="preview-img">

                    <?php } elseif(in_array($ext,['mp4','mov','webm'])){ ?>

                        <video controls class="preview-video">
                            <source src="<?= $browserPath; ?>">
                        </video>

                    <?php } elseif($ext == 'pdf'){ ?>

                        <iframe src="<?= $browserPath; ?>"
                                class="preview-pdf"></iframe>

                    <?php } else { ?>

                        <div class="empty-preview">

                            <i class="fa-solid fa-file-lines"></i>

                            <h4>Preview unavailable</h4>

                            <p style="color:#94a3b8;">
                                This file type cannot be previewed.
                            </p>

                            <a href="<?= $browserPath; ?>"
                               target="_blank"
                               class="btn btn-light btn-sm">

                                <i class="fa-solid fa-download me-1"></i>
                                Open File

                            </a>

                        </div>

                    <?php } ?>

                <?php } else { ?>

                    <div class="empty-preview">

                        <i class="fa-regular fa-image"></i>

                        <h4>No Asset Selected</h4>

                        <p style="color:#94a3b8;">
                            Select an asset from the left panel.
                        </p>

                    </div>

                <?php } ?>

            </div>

        </div>

        <!-- DETAILS -->

        <div class="col-lg-3">

            <div class="panel-card">

                <h6>
                    <i class="fa-solid fa-circle-info text-info"></i>
                    Asset Details
                </h6>

                <?php if($selected_asset){

                    $status = $selected_asset['latest_status'] ?? 'pending';

                    $statusClass = 'status-pending';

                    if($status == 'approved'){
                        $statusClass = 'status-approved';
                    }elseif($status == 'revision'){
                        $statusClass = 'status-revision';
                    }

                ?>

                    <div class="detail-row">

                        <div class="detail-label">
                            Campaign
                        </div>

                        <div class="detail-value">
                            <?= htmlspecialchars($selected_asset['campaign_name']); ?>
                        </div>

                    </div>

                    <div class="detail-row">

                        <div class="detail-label">
                            Milestone
                        </div>

                        <div class="detail-value">
                            <?= htmlspecialchars($selected_asset['milestone_title']); ?>
                        </div>

                    </div>

                    <div class="detail-row">

                        <div class="detail-label">
                            Uploaded By
                        </div>

                        <div class="detail-value">
                            <?= htmlspecialchars($selected_asset['uploader_name'] ?? 'Unknown'); ?>
                        </div>

                    </div>

                    <div class="detail-row">

                        <div class="detail-label">
                            Uploaded At
                        </div>

                        <div class="detail-value">
                            <?= date(
                                'M d, Y h:i A',
                                strtotime($selected_asset['uploaded_at'])
                            ); ?>
                        </div>

                    </div>

                    <div class="detail-row">

                        <div class="detail-label">
                            Status
                        </div>

                        <div class="detail-value">

                            <span class="status-pill <?= $statusClass; ?>">
                                <?= ucfirst($status); ?>
                            </span>

                        </div>

                    </div>

                    <div class="detail-row">

                        <div class="detail-label">
                            File Name
                        </div>

                        <div class="detail-value"
                             style="word-break:break-all;">

                            <?= htmlspecialchars(
                                basename($selected_asset['file_path'])
                            ); ?>

                        </div>

                    </div>

                    <!-- ACTIONS -->

                    <?php if($status == 'approved'){ ?>

                        <div class="approved-banner">

                            <i class="fa-solid fa-circle-check"></i>

                            <div>
                                <div class="approved-banner-title">
                                    Asset Approved
                                </div>
                                <div class="approved-banner-sub">
                                    This asset has already been approved.
                                </div>
                            </div>

                        </div>

                    <?php } else { ?>

                        <div class="action-buttons">

                            <!-- APPROVE -->
                            <form method="POST">

                                <input type="hidden"
                                       name="asset_id"
                                       value="<?= $selected_asset['asset_id']; ?>">

                                <input type="hidden"
                                       name="milestone_id"
                                       value="<?= $selected_asset['milestone_id']; ?>">

                                <button type="submit"
                                        name="approve_asset"
                                        class="btn btn-success btn-modern">

                                    <i class="fa-solid fa-check me-1"></i>
                                    Approve

                                </button>

                            </form>

                            <!-- REVISION -->

                            <button class="btn btn-warning btn-modern"
                                    data-bs-toggle="modal"
                                    data-bs-target="#revisionModal">

                                <i class="fa-solid fa-rotate-left me-1"></i>
                                Revision

                            </button>

                        </div>

                    <?php } ?>

                <?php } else { ?>

                    <p class="text-muted" style="font-size:13px;">
                        Select an asset to view details.
                    </p>

                <?php } ?>

            </div>

        </div>

    </div>

    <!-- FEEDBACK SECTION -->

    <div class="panel-card mt-4">

        <h6>
            <i class="fa-solid fa-comments text-success"></i>
            Client Feedback

            <?php if($commentsQuery){ ?>

                <span class="badge bg-secondary ms-1">
                    <?= mysqli_num_rows($commentsQuery); ?>
                </span>

            <?php } ?>

        </h6>

        <?php if($selected_asset){ ?>

            <?php if($commentsQuery && mysqli_num_rows($commentsQuery) > 0){ ?>

                <div style="max-height:320px; overflow-y:auto;">

                    <?php while($c = mysqli_fetch_assoc($commentsQuery)) { ?>

                        <div class="feedback-item">

                            <div class="d-flex align-items-center gap-2 mb-2">

                                <div class="feedback-avatar">
                                    <?= strtoupper(substr($c['name'],0,1)); ?>
                                </div>

                                <div class="flex-grow-1">
                                    <strong style="font-size:14px;">
                                        <?= htmlspecialchars($c['name']); ?>
                                    </strong>
                                </div>

                                <small class="text-muted">
                                    <?= date('M d, Y', strtotime($c['created_at'])); ?>
                                </small>

                                <span class="badge <?= $c['status'] == 'approved'
                                    ? 'bg-success'
                                    : 'bg-warning text-dark'; ?>">
                                    <?= ucfirst($c['status']); ?>
                                </span>

                            </div>

                            <p class="mb-0 text-muted"
                               style="font-size:14px; padding-left:42px;">
                                <?= htmlspecialchars($c['feedback']); ?>
                            </p>

                        </div>

                    <?php } ?>

                </div>

            <?php } else { ?>

                <div class="text-center text-muted py-5">

                    <i class="fa-regular fa-comment-dots d-block mb-2"
                       style="font-size:38px;"></i>

                    No feedback available yet.

                </div>

            <?php } ?>

        <?php } else { ?>

            <p class="text-muted">
                Select an asset to view feedback history.
            </p>

        <?php } ?>

    </div>

</div>

<!-- REVISION MODAL — only rendered when asset is NOT approved -->

<?php if($selected_asset && ($selected_asset['latest_status'] ?? '') != 'approved'){ ?>

<div class="modal fade"
     id="revisionModal"
     tabindex="-1">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <form method="POST">

                <div class="modal-header">

                    <h5 class="modal-title">
                        Request Revision
                    </h5>

                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <input type="hidden"
                           name="asset_id"
                           value="<?= $selected_asset['asset_id']; ?>">

                    <input type="hidden"
                           name="milestone_id"
                           value="<?= $selected_asset['milestone_id']; ?>">

                    <label class="form-label fw-semibold mb-2">
                        Revision Feedback
                    </label>

                    <textarea
                        name="feedback"
                        class="form-control"
                        rows="5"
                        placeholder="Explain what revisions should be improved..."
                        required></textarea>

                </div>

                <div class="modal-footer">

                    <button type="button"
                            class="btn btn-light"
                            data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit"
                            name="request_revision"
                            class="btn btn-warning">

                        <i class="fa-solid fa-paper-plane me-1"></i>
                        Submit Revision

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<?php } ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>