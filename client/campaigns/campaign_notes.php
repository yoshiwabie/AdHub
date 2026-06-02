<?php
session_start();
include('../../config/db.php');
include('../../includes/topbar.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'client') {
    header("Location: ../../index.php"); exit();
}

$user_id     = intval($_SESSION['user_id']);
$campaign_id = intval($_GET['id'] ?? 0);

// Verify client owns this campaign
$campaign = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT * FROM campaigns
    WHERE campaign_id = $campaign_id AND client_id = $user_id LIMIT 1
"));
if (!$campaign) { header("Location: ../../index.php"); exit(); }

$notes = mysqli_query($conn, "
    SELECT n.*, u.name AS staff_name
    FROM campaign_notes n
    JOIN users u ON n.staff_id = u.user_id
    WHERE n.campaign_id = $campaign_id
    ORDER BY n.is_pinned DESC, n.created_at DESC
");


?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Campaign Notes | AdHub</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<link rel="stylesheet" href="/AdHub_V2/assets/css/style.css">
<link rel="stylesheet" href="/AdHub_V2/assets/css/dashboard.css">
<style>
.main-content { margin-left: 260px; padding: 35px; }

.page-header {
    background: linear-gradient(135deg, #1e293b, #334155);
    border-radius: 24px; padding: 28px 32px; color: white;
    margin-bottom: 24px;
    display: flex; justify-content: space-between;
    align-items: center; flex-wrap: wrap; gap: 16px;
}
.page-header h1 { font-size: 26px; font-weight: 700; margin: 0; color: white; }
.page-header p  { margin: 4px 0 0; color: #94a3b8; font-size: 14px; }

.dashboard-card {
    background: white; border-radius: 24px; padding: 24px;
    box-shadow: 0 4px 16px rgba(15,23,42,0.06); margin-bottom: 24px;
}

/* Compose form */
.compose-panel {
    background: #f8fafc; border: 1.5px dashed #cbd5e1;
    border-radius: 18px; padding: 22px 24px; margin-bottom: 24px;
    transition: border-color .2s;
}
.compose-panel:focus-within { border-color: #3b82f6; background: #eff6ff; }
.compose-panel h6 {
    font-size: 14px; font-weight: 700; color: #0f172a;
    margin-bottom: 14px;
    display: flex; align-items: center; gap: 8px;
}
.compose-textarea {
    width: 100%; border: 1.5px solid #e2e8f0;
    border-radius: 12px; padding: 12px 14px;
    font-size: 14px; resize: vertical; min-height: 100px;
    color: #0f172a; background: white;
    transition: border-color .2s;
}
.compose-textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }

/* Notes list */
.note-item {
    background: white; border: 1px solid #f1f5f9;
    border-radius: 18px; padding: 18px 20px; margin-bottom: 14px;
    transition: box-shadow .2s;
    position: relative;
}
.note-item:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
.note-item.pinned { border-color: #fbbf24; background: #fffbeb; }

.note-pin-badge {
    position: absolute; top: 14px; right: 14px;
    background: #fef3c7; color: #b45309;
    font-size: 11px; font-weight: 700;
    padding: 3px 10px; border-radius: 999px;
    display: flex; align-items: center; gap: 4px;
}

.note-author {
    display: flex; align-items: center; gap: 10px; margin-bottom: 10px;
}
.note-avatar {
    width: 34px; height: 34px; border-radius: 50%;
    background: #dbeafe; color: #1d4ed8;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700;
}
.note-author-name { font-size: 14px; font-weight: 600; color: #0f172a; }
.note-author-time { font-size: 12px; color: #94a3b8; }

.note-body { font-size: 14px; color: #334155; line-height: 1.65; white-space: pre-wrap; }

.note-footer { display: flex; gap: 8px; margin-top: 12px; }
.btn-note-action {
    border: none; border-radius: 8px;
    padding: 6px 12px; font-size: 12px; font-weight: 600;
    cursor: pointer; transition: opacity .15s;
    display: inline-flex; align-items: center; gap: 5px;
}
.btn-note-edit   { background: #eff6ff; color: #2563eb; }
.btn-note-delete { background: #fff1f2; color: #e11d48; }

/* Toast */
.toast-fixed {
    position: fixed; top: 22px; right: 22px; z-index: 9999;
    padding: 14px 22px; border-radius: 14px;
    font-size: 14px; font-weight: 600;
    display: flex; align-items: center; gap: 10px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.14);
    animation: slideIn .3s ease, fadeOut .4s ease 3.5s forwards;
}
.toast-success { background: #16a34a; color: white; }
.toast-warning { background: #2563eb; color: white; }
.toast-danger  { background: #dc2626; color: white; }
@keyframes slideIn { from { opacity:0; transform:translateY(-14px); } to { opacity:1; transform:translateY(0); } }
@keyframes fadeOut { to { opacity:0; transform:translateY(-10px); } }

/* Modal */
.modal-content { border: none; border-radius: 24px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
.modal-header  { padding: 24px 28px 0; border: none; }
.modal-body    { padding: 20px 28px; }
.modal-footer  { padding: 0 28px 24px; border: none; gap: 10px; }
.modal-title   { font-size: 17px; font-weight: 700; color: #0f172a; }

.empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
.empty-state i { font-size: 48px; margin-bottom: 14px; display: block; }
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

<div class="main-content">

    <div class="page-header">
        <div>
            <h1><i class="fa-solid fa-message me-2"></i><?= htmlspecialchars($campaign['campaign_name']); ?></h1>
            <p>Updates &amp; announcements from your agency team</p>
        </div>
        <a href="../campaigns/campaign_details.php?id=<?= $campaign_id; ?>"
           class="btn btn-outline-light btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back
        </a>
    </div>

    <div class="dashboard-card">

        <h5 style="font-size:16px;font-weight:700;color:#0f172a;margin-bottom:20px;">
            Notes <span style="font-weight:400;color:#64748b;">(<?= mysqli_num_rows($notes); ?>)</span>
        </h5>

        <?php if (mysqli_num_rows($notes) > 0): ?>
            <?php while ($note = mysqli_fetch_assoc($notes)): ?>
            <div class="note-item <?= $note['is_pinned'] ? 'pinned' : ''; ?>">
                <?php if ($note['is_pinned']): ?>
                <div class="note-pin-badge"><i class="fa-solid fa-thumbtack"></i> Pinned</div>
                <?php endif; ?>
                <div class="note-author">
                    <div class="note-avatar"><?= strtoupper(substr($note['staff_name'], 0, 1)); ?></div>
                    <div>
                        <div class="note-author-name"><?= htmlspecialchars($note['staff_name']); ?></div>
                        <div class="note-author-time">
                            <i class="fa-regular fa-clock me-1"></i>
                            <?= date('M d, Y h:i A', strtotime($note['created_at'])); ?>
                        </div>
                    </div>
                </div>
                <div class="note-body"><?= htmlspecialchars($note['body']); ?></div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fa-regular fa-message" style="color:#cbd5e1;"></i>
                <p>No updates posted yet.</p>
            </div>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>