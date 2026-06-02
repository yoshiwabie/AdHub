<?php
session_start();

include('../../config/db.php');
include('../../includes/topbar.php');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff'){
    header("Location: ../../index.php");
    exit();
}

$campaign_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($campaign_id <= 0){
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$campaignQuery = mysqli_query($conn, "SELECT * FROM campaigns WHERE campaign_id = '$campaign_id' LIMIT 1");
$campaign = mysqli_fetch_assoc($campaignQuery);

if(!$campaign || $campaign['assigned_staff_id'] != $user_id){
    header("Location: ../../index.php");
    exit();
}

$errors  = [];
$success = false;

if(isset($_POST['upload'])){

    $milestone_id = (int)$_POST['milestone_id'];

    if(empty($_FILES['file']['name'])){
        $errors[] = "Please select a file to upload.";
    }

    if(empty($milestone_id)){
        $errors[] = "Please select a milestone.";
    }

    if(empty($errors)){

        $file    = $_FILES['file']['name'];
        $tmp     = $_FILES['file']['tmp_name'];
        $size    = $_FILES['file']['size'];
        $ext     = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','pdf','docx','mp4'];

        if(!in_array($ext, $allowed)){
            $errors[] = "Invalid file type. Allowed: JPG, PNG, PDF, DOCX, MP4.";

        } elseif($size > 50 * 1024 * 1024){
            $errors[] = "File size must not exceed 50MB.";

        } else {

            // ── Build absolute path from this file's location ──────────────
            $uploadDir  = realpath(__DIR__ . '/../../assets/uploads');

            // If the folder doesn't exist yet, try to create it
            if(!$uploadDir){
                mkdir(__DIR__ . '/../../assets/uploads', 0755, true);
                $uploadDir = realpath(__DIR__ . '/../../assets/uploads');
            }

            if(!$uploadDir || !is_writable($uploadDir)){
                $errors[] = "Upload directory is missing or not writable: "
                            . __DIR__ . '/../../assets/uploads';

            } else {

                $newName    = time() . "_" . rand(1000,9999) . "." . $ext;
                $uploadPath = $uploadDir . DIRECTORY_SEPARATOR . $newName;

                if(move_uploaded_file($tmp, $uploadPath)){

                    // Store a consistent relative path
                    $dbPath = 'uploads/' . $newName;

                    mysqli_query($conn,"
                        INSERT INTO assets (milestone_id, file_path, uploaded_by, uploaded_at)
                        VALUES ('$milestone_id', '$dbPath', '$user_id', NOW())
                    ");

                    include('../../config/update_campaign_status.php');

                    $client_id     = $campaign['client_id'];
                    $campaign_name = mysqli_real_escape_string($conn, $campaign['campaign_name']);

                    mysqli_query($conn,"
                        INSERT INTO notifications (user_id, title, message)
                        VALUES (
                            '$client_id',
                            'New Asset Uploaded',
                            'Your staff has uploaded a new asset for campaign: $campaign_name.'
                        )
                    ");

                    updateCampaignStatus($conn, $campaign_id);
                    
                    header("Location: ../campaigns/campaign_details.php?id=$campaign_id");
                    exit();

                } else {

                    // Surface exactly what PHP knows about the failure
                    $phpErr  = error_get_last();
                    $errors[] = "move_uploaded_file() failed.<br>"
                                . "• Temp file: " . htmlspecialchars($tmp) . "<br>"
                                . "• Destination: " . htmlspecialchars($uploadPath) . "<br>"
                                . "• PHP error: " . htmlspecialchars($phpErr['message'] ?? 'none') . "<br>"
                                . "• upload_tmp_dir: " . htmlspecialchars(ini_get('upload_tmp_dir') ?: sys_get_temp_dir()) . "<br>"
                                . "• upload_max_filesize: " . htmlspecialchars(ini_get('upload_max_filesize'));
                }
            }
        }
    }
}

$milestones = mysqli_query($conn,"
    SELECT * FROM milestones WHERE campaign_id='$campaign_id' ORDER BY milestone_id ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Upload Asset | AdHub</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/AdHub_V2/assets/css/style.css">
<link rel="stylesheet" href="/AdHub_V2/assets/css/dashboard.css">

<style>

*, *::before, *::after { box-sizing: border-box; }

body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    background: #f1f5f9;
}

.main-content { 
    margin-left:260px; 
    padding:40px 35px; 
    min-height:calc(100vh - 60px); 
}

/* ── PAGE HEADER ── */
.page-header {
    max-width: 780px;
    margin: 0 auto 28px;
    display: flex;
    align-items: center;
    gap: 14px;
}

.page-header-icon {
    width: 52px;
    height: 52px;
    border-radius: 16px;
    background: linear-gradient(135deg, #3b82f6, #6366f1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
    flex-shrink: 0;
    box-shadow: 0 6px 16px rgba(99,102,241,0.3);
}

.page-header-text h1 {
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 3px;
    line-height: 1.2;
}

.page-header-text p {
    font-size: 13px;
    color: #64748b;
    margin: 0;
}

/* ── FORM CARD ── */
.form-card {
    max-width: 780px;
    margin: 0 auto;
    background: white;
    border-radius: 28px;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(15,23,42,0.07);
}

/* Campaign context strip */
.campaign-strip {
    background: linear-gradient(135deg, #1e293b, #334155);
    padding: 16px 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}

.campaign-strip-name {
    color: white;
    font-weight: 700;
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.campaign-strip-name i {
    color: #94a3b8;
    font-size: 13px;
}

.campaign-strip-meta {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}

.strip-badge {
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 30px;
    padding: 4px 12px;
    font-size: 12px;
    color: #cbd5e1;
    display: flex;
    align-items: center;
    gap: 5px;
}

/* Form body */
.form-body {
    padding: 32px 28px;
}

/* ── SECTION LABEL ── */
.section-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #94a3b8;
    margin-bottom: 10px;
}

/* ── MILESTONE SELECT ── */
.milestone-select-wrapper {
    position: relative;
}

.milestone-select-wrapper .form-select {
    border: 2px solid #e2e8f0;
    border-radius: 14px;
    padding: 13px 16px;
    font-size: 14px;
    font-weight: 500;
    color: #0f172a;
    background-color: #f8fafc;
    appearance: none;
    cursor: pointer;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.milestone-select-wrapper .form-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 4px rgba(59,130,246,0.1);
    outline: none;
    background: white;
}

.milestone-select-wrapper::after {
    content: '\f078';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
    pointer-events: none;
    font-size: 12px;
}

/* ── UPLOAD ZONE ── */
.upload-zone {
    border: 2px dashed #cbd5e1;
    border-radius: 20px;
    background: #f8fafc;
    padding: 40px 28px;
    text-align: center;
    cursor: pointer;
    transition: all 0.25s ease;
    position: relative;
    overflow: hidden;
}

.upload-zone:hover,
.upload-zone.drag-over {
    border-color: #3b82f6;
    background: #eff6ff;
}

.upload-zone.drag-over {
    transform: scale(1.01);
}

.upload-zone-icon {
    width: 72px;
    height: 72px;
    background: linear-gradient(135deg, #dbeafe, #e0e7ff);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    font-size: 28px;
    color: #3b82f6;
    transition: transform 0.2s;
}

.upload-zone:hover .upload-zone-icon {
    transform: translateY(-3px);
}

.upload-zone h5 {
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 6px;
}

.upload-zone p {
    font-size: 13px;
    color: #94a3b8;
    margin-bottom: 18px;
}

/* Hidden real input */
.upload-zone input[type="file"] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
    width: 100%;
    height: 100%;
}

/* File type pills */
.file-pills {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 6px;
}

.file-pill {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 30px;
    padding: 4px 11px;
    font-size: 11px;
    font-weight: 600;
    color: #475569;
    display: flex;
    align-items: center;
    gap: 4px;
}

.file-pill i { font-size: 10px; color: #94a3b8; }

/* File preview bar */
.file-preview {
    display: none;
    background: #f0fdf4;
    border: 1.5px solid #bbf7d0;
    border-radius: 14px;
    padding: 12px 16px;
    margin-top: 14px;
    align-items: center;
    gap: 12px;
}

.file-preview.visible { display: flex; }

.file-preview-icon {
    width: 38px;
    height: 38px;
    background: #22c55e;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 15px;
    flex-shrink: 0;
}

.file-preview-info { flex: 1; min-width: 0; }
.file-preview-name {
    font-size: 13px;
    font-weight: 600;
    color: #15803d;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.file-preview-size { font-size: 11px; color: #86efac; }

.file-preview-remove {
    background: none;
    border: none;
    color: #86efac;
    cursor: pointer;
    font-size: 16px;
    padding: 0 4px;
    transition: color 0.15s;
}
.file-preview-remove:hover { color: #ef4444; }

/* ── DIVIDER ── */
.form-divider {
    border: none;
    border-top: 1px solid #f1f5f9;
    margin: 28px 0;
}

/* ── ACTIONS ── */
.form-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.btn-upload {
    background: linear-gradient(135deg, #3b82f6, #6366f1);
    border: none;
    padding: 13px 28px;
    border-radius: 14px;
    color: white;
    font-weight: 700;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: opacity 0.2s, transform 0.15s, box-shadow 0.2s;
    box-shadow: 0 4px 14px rgba(99,102,241,0.35);
}

.btn-upload:hover {
    opacity: 0.92;
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(99,102,241,0.4);
}

.btn-upload:active { transform: translateY(0); }

.btn-cancel {
    padding: 13px 22px;
    border-radius: 14px;
    border: 1.5px solid #e2e8f0;
    background: white;
    color: #64748b;
    font-weight: 600;
    font-size: 14px;
    text-decoration: none;
    transition: background 0.15s, border-color 0.15s;
}

.btn-cancel:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    color: #475569;
}

.size-note {
    margin-left: auto;
    font-size: 12px;
    color: #94a3b8;
    display: flex;
    align-items: center;
    gap: 5px;
}

/* ── ERRORS ── */
.error-box {
    background: #fef2f2;
    border: 1.5px solid #fecaca;
    border-radius: 14px;
    padding: 14px 18px;
    margin-bottom: 24px;
    font-size: 13px;
    color: #b91c1c;
}

.error-box i { margin-right: 6px; }

/* ── ANIMATE IN ── */
.form-card {
    animation: slideUp 0.35s cubic-bezier(0.16,1,0.3,1) both;
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
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

    <!-- Page header -->
    <div class="page-header">
        <div class="page-header-icon">
            <i class="fa-solid fa-cloud-arrow-up"></i>
        </div>
        <div class="page-header-text">
            <h1>Upload Asset</h1>
            <p>Attach a deliverable file to a campaign milestone.</p>
        </div>
    </div>

    <div class="form-card">

        <!-- Campaign context strip -->
        <div class="campaign-strip">
            <div class="campaign-strip-name">
                <i class="fa-solid fa-bullhorn"></i>
                <?= htmlspecialchars($campaign['campaign_name']); ?>
            </div>
            <div class="campaign-strip-meta">
                <div class="strip-badge">
                    <i class="fa-regular fa-calendar"></i>
                    <?= date('M d', strtotime($campaign['start_date'])); ?>
                    —
                    <?= date('M d, Y', strtotime($campaign['deadline'])); ?>
                </div>
            </div>
        </div>

        <!-- Form body -->
        <div class="form-body">

            <!-- Errors -->
            <?php if(!empty($errors)){ ?>
                <div class="error-box">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?php foreach($errors as $e){ ?>
                        <div><?= htmlspecialchars($e); ?></div>
                    <?php } ?>
                </div>
            <?php } ?>

            <form method="POST" enctype="multipart/form-data" id="uploadForm">

                <!-- Milestone -->
                <div class="mb-4">
                    <div class="section-label">Milestone</div>
                    <div class="milestone-select-wrapper">
                        <select name="milestone_id" class="form-select" required>
                            <option value="" disabled selected>Choose a milestone…</option>
                            <?php while($m = mysqli_fetch_assoc($milestones)) { ?>
                                <option value="<?= $m['milestone_id']; ?>"
                                    <?= (isset($_POST['milestone_id']) && $_POST['milestone_id'] == $m['milestone_id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($m['title']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <hr class="form-divider">

                <!-- Upload zone -->
                <div class="mb-2">
                    <div class="section-label">File</div>
                    <div class="upload-zone" id="uploadZone">
                        <input type="file"
                               name="file"
                               id="fileInput"
                               accept=".jpg,.jpeg,.png,.pdf,.docx,.mp4"
                               required>
                        <div class="upload-zone-icon">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                        </div>
                        <h5>Drag & drop or click to browse</h5>
                        <p>Select the file you want to attach</p>
                        <div class="file-pills">
                            <span class="file-pill"><i class="fa-regular fa-image"></i> JPG</span>
                            <span class="file-pill"><i class="fa-regular fa-image"></i> PNG</span>
                            <span class="file-pill"><i class="fa-regular fa-file-pdf"></i> PDF</span>
                            <span class="file-pill"><i class="fa-regular fa-file-word"></i> DOCX</span>
                            <span class="file-pill"><i class="fa-solid fa-film"></i> MP4</span>
                        </div>
                    </div>

                    <!-- File preview -->
                    <div class="file-preview" id="filePreview">
                        <div class="file-preview-icon">
                            <i class="fa-regular fa-file-lines" id="previewIcon"></i>
                        </div>
                        <div class="file-preview-info">
                            <div class="file-preview-name" id="previewName">—</div>
                            <div class="file-preview-size" id="previewSize">—</div>
                        </div>
                        <button type="button" class="file-preview-remove" id="removeFile" title="Remove">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>

                <hr class="form-divider">

                <!-- Actions -->
                <div class="form-actions">
                    <button type="submit" name="upload" class="btn-upload" id="submitBtn">
                        <i class="fa-solid fa-upload"></i>
                        Upload Asset
                    </button>
                    <a href="../campaigns/campaign_details.php?id=<?= $campaign_id; ?>"
                       class="btn-cancel">
                        Cancel
                    </a>
                    <span class="size-note">
                        <i class="fa-solid fa-shield-halved"></i>
                        Max 50 MB
                    </span>
                </div>

            </form>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const zone      = document.getElementById('uploadZone');
const fileInput = document.getElementById('fileInput');
const preview   = document.getElementById('filePreview');
const previewName = document.getElementById('previewName');
const previewSize = document.getElementById('previewSize');
const previewIcon = document.getElementById('previewIcon');
const removeBtn  = document.getElementById('removeFile');

// Icon map by extension
const iconMap = {
    jpg: 'fa-regular fa-image', jpeg: 'fa-regular fa-image', png: 'fa-regular fa-image',
    pdf: 'fa-regular fa-file-pdf',
    docx: 'fa-regular fa-file-word',
    mp4: 'fa-solid fa-film'
};

function formatSize(bytes){
    if(bytes < 1024) return bytes + ' B';
    if(bytes < 1024*1024) return (bytes/1024).toFixed(1) + ' KB';
    return (bytes/(1024*1024)).toFixed(1) + ' MB';
}

function showPreview(file){
    const ext = file.name.split('.').pop().toLowerCase();
    previewName.textContent = file.name;
    previewSize.textContent = formatSize(file.size);
    previewIcon.className = iconMap[ext] || 'fa-regular fa-file';
    preview.classList.add('visible');
}

fileInput.addEventListener('change', () => {
    if(fileInput.files.length) showPreview(fileInput.files[0]);
    else preview.classList.remove('visible');
});

// Drag & drop
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.classList.remove('drag-over');
    if(e.dataTransfer.files.length){
        fileInput.files = e.dataTransfer.files;
        showPreview(fileInput.files[0]);
    }
});

// Remove file
removeBtn.addEventListener('click', () => {
    fileInput.value = '';
    preview.classList.remove('visible');
});

// Upload button loading state
document.getElementById('uploadForm').addEventListener('submit', function(){
    const btn = document.getElementById('submitBtn');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploading…';
    btn.style.opacity = '0.75';
    // Delay disabling so the button value is included in the POST
    setTimeout(() => { btn.disabled = true; }, 100);
});
</script>
</body>
</html>