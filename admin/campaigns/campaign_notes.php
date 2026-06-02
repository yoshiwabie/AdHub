<?php
session_start();
include('../../config/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    header("Location: ../../index.php"); exit();
}

$user_id     = intval($_SESSION['user_id']);
$campaign_id = intval($_GET['id'] ?? 0);

// Make sure staff is assigned to this campaign
$check = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT * FROM campaigns
    WHERE campaign_id = $campaign_id AND assigned_staff_id = $user_id LIMIT 1
"));
if (!$check) { header("Location: ../../index.php"); exit(); }

/*
========================================
CREATE NOTE
========================================
*/
if (isset($_POST['create_note'])) {
    $body      = mysqli_real_escape_string($conn, trim($_POST['body']));
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;

    if ($body) {
        mysqli_query($conn, "
            INSERT INTO campaign_notes (campaign_id, staff_id, body, is_pinned)
            VALUES ($campaign_id, $user_id, '$body', $is_pinned)
        ");

        // Notify the client
        $clientRow = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT client_id FROM campaigns WHERE campaign_id = $campaign_id LIMIT 1
        "));
        if ($clientRow && $clientRow['client_id']) {
            $client_id    = intval($clientRow['client_id']);
            $camp_name    = mysqli_real_escape_string($conn, $check['campaign_name']);
            mysqli_query($conn, "
                INSERT INTO notifications (user_id, title, message, role, created_at)
                VALUES (
                    $client_id,
                    'New campaign update',
                    'A new note was posted in campaign \"$camp_name\".',
                    'client',
                    NOW()
                )
            ");
        }
    }
    header("Location: campaign_notes.php?id=$campaign_id&created=1"); exit();
}

/*
========================================
EDIT NOTE
========================================
*/
if (isset($_POST['edit_note'])) {
    $note_id   = intval($_POST['note_id']);
    $body      = mysqli_real_escape_string($conn, trim($_POST['body']));
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;

    mysqli_query($conn, "
        UPDATE campaign_notes
        SET body = '$body', is_pinned = $is_pinned
        WHERE note_id = $note_id
          AND campaign_id = $campaign_id
          AND staff_id    = $user_id
    ");
    header("Location: campaign_notes.php?id=$campaign_id&updated=1"); exit();
}

/*
========================================
DELETE NOTE
========================================
*/
if (isset($_POST['delete_note'])) {
    $note_id = intval($_POST['note_id']);
    mysqli_query($conn, "
        DELETE FROM campaign_notes
        WHERE note_id = $note_id
          AND campaign_id = $campaign_id
          AND staff_id    = $user_id
    ");
    header("Location: campaign_notes.php?id=$campaign_id&deleted=1"); exit();
}

/*
========================================
FETCH DATA
========================================
*/
$campaign = $check;

$notes = mysqli_query($conn, "
    SELECT n.*, u.name AS staff_name
    FROM campaign_notes n
    JOIN users u ON n.staff_id = u.user_id
    WHERE n.campaign_id = $campaign_id
    ORDER BY n.is_pinned DESC, n.created_at DESC
");

include('../../includes/topbar.php');
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

<!-- TOASTS -->
<?php if (isset($_GET['created'])): ?>
    <div class="toast-fixed toast-success"><i class="fa-solid fa-circle-check"></i> Note posted!</div>
<?php elseif (isset($_GET['updated'])): ?>
    <div class="toast-fixed toast-warning"><i class="fa-solid fa-pen-to-square"></i> Note updated.</div>
<?php elseif (isset($_GET['deleted'])): ?>
    <div class="toast-fixed toast-danger"><i class="fa-solid fa-trash"></i> Note deleted.</div>
<?php endif; ?>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-menu">
        <a href="../dashboard/dashboard.php"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
        <a href="../kanban/main_board.php" class="active"><i class="fa-solid fa-layer-group"></i> Campaigns</a>
        <a href="../timelogs/time_logs.php"><i class="fa-regular fa-clock"></i> Time Logs</a>
        <a href="../notifications/notifications.php"><i class="fa-regular fa-bell"></i> Notifications</a>
        <a href="../../auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
</div>

<div class="main-content">

    <!-- HEADER -->
    <div class="page-header">
        <div>
            <h1>
                <i class="fa-solid fa-message me-2"></i>
                <?= htmlspecialchars($campaign['campaign_name']); ?>
            </h1>
            <p>Campaign Notes &amp; Announcements</p>
        </div>
        <a href="campaign_details.php?id=<?= $campaign_id; ?>"
           class="btn btn-outline-light btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Campaign
        </a>
    </div>

    <div class="dashboard-card">

        <!-- COMPOSE -->
        <div class="compose-panel">
            <h6><i class="fa-solid fa-pen-to-square text-primary"></i> Post a note</h6>
            <form method="POST" action="campaign_notes.php?id=<?= $campaign_id; ?>">
                <textarea name="body"
                          class="compose-textarea"
                          placeholder="Write an update, announcement, or reminder for the client..."
                          required></textarea>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox"
                               name="is_pinned" id="pinCheck">
                        <label class="form-check-label" for="pinCheck" style="font-size:13px;">
                            <i class="fa-solid fa-thumbtack me-1 text-warning"></i> Pin this note
                        </label>
                    </div>
                    <button type="submit" name="create_note"
                            class="btn btn-primary btn-sm px-4">
                        <i class="fa-solid fa-paper-plane me-1"></i> Post Note
                    </button>
                </div>
            </form>
        </div>

        <!-- NOTES LIST -->
        <?php $count = mysqli_num_rows($notes); ?>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 style="font-size:16px;font-weight:700;color:#0f172a;margin:0;">
                All notes <span style="font-weight:400;color:#64748b;">(<?= $count; ?>)</span>
            </h5>
        </div>

        <?php if ($count > 0): ?>
            <?php while ($note = mysqli_fetch_assoc($notes)): ?>
            <div class="note-item <?= $note['is_pinned'] ? 'pinned' : ''; ?>">

                <?php if ($note['is_pinned']): ?>
                <div class="note-pin-badge">
                    <i class="fa-solid fa-thumbtack"></i> Pinned
                </div>
                <?php endif; ?>

                <div class="note-author">
                    <div class="note-avatar">
                        <?= strtoupper(substr($note['staff_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <div class="note-author-name"><?= htmlspecialchars($note['staff_name']); ?></div>
                        <div class="note-author-time">
                            <i class="fa-regular fa-clock me-1"></i>
                            <?= date('M d, Y h:i A', strtotime($note['created_at'])); ?>
                            <?php if ($note['updated_at']): ?>
                                &nbsp;· <em>edited</em>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="note-body"><?= htmlspecialchars($note['body']); ?></div>

                <!-- Only show actions if this staff posted it -->
                <?php if ($note['staff_id'] == $user_id): ?>
                <div class="note-footer">
                    <button class="btn-note-action btn-note-edit"
                            onclick="openEditModal(
                                <?= $note['note_id']; ?>,
                                <?= $note['is_pinned']; ?>,
                                <?= json_encode($note['body']); ?>
                            )">
                        <i class="fa-solid fa-pen-to-square"></i> Edit
                    </button>
                    <button class="btn-note-action btn-note-delete"
                            onclick="openDeleteModal(<?= $note['note_id']; ?>)">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                </div>
                <?php endif; ?>

            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fa-regular fa-message" style="color:#cbd5e1;"></i>
                <p>No notes yet. Post the first one above.</p>
            </div>
        <?php endif; ?>

    </div>

</div><!-- /main-content -->

<!-- EDIT MODAL -->
<div class="modal fade" id="editNoteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="campaign_notes.php?id=<?= $campaign_id; ?>">
                <input type="hidden" name="edit_note" value="1">
                <input type="hidden" name="note_id"   id="edit_note_id">

                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fa-solid fa-pen-to-square text-primary me-2"></i> Edit note
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <textarea name="body" id="edit_body"
                              class="compose-textarea" style="min-height:120px;"
                              required></textarea>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox"
                               name="is_pinned" id="edit_pin">
                        <label class="form-check-label" for="edit_pin" style="font-size:13px;">
                            <i class="fa-solid fa-thumbtack me-1 text-warning"></i> Pin this note
                        </label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">
                        <i class="fa-solid fa-save me-1"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DELETE MODAL -->
<div class="modal fade" id="deleteNoteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="campaign_notes.php?id=<?= $campaign_id; ?>">
                <input type="hidden" name="delete_note" value="1">
                <input type="hidden" name="note_id" id="delete_note_id">

                <div class="modal-header">
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div style="width:60px;height:60px;border-radius:50%;background:#fff1f2;
                                display:flex;align-items:center;justify-content:center;
                                font-size:26px;color:#e11d48;margin:0 auto 16px;">
                        <i class="fa-solid fa-trash"></i>
                    </div>
                    <h5 style="font-weight:700;color:#0f172a;">Delete note?</h5>
                    <p style="color:#64748b;font-size:14px;">This cannot be undone.</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4 fw-bold">
                        <i class="fa-solid fa-trash me-1"></i> Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditModal(id, pinned, body) {
    document.getElementById('edit_note_id').value = id;
    document.getElementById('edit_body').value    = body;
    document.getElementById('edit_pin').checked   = pinned == 1;
    new bootstrap.Modal(document.getElementById('editNoteModal')).show();
}
function openDeleteModal(id) {
    document.getElementById('delete_note_id').value = id;
    new bootstrap.Modal(document.getElementById('deleteNoteModal')).show();
}
setTimeout(() => {
    const t = document.querySelector('.toast-fixed');
    if (t) t.style.animation = 'fadeOut 0.4s ease forwards';
}, 4000);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>