<?php
session_start();
include('../../config/db.php');
include('../../includes/topbar.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php"); exit();
}

$user_id = intval($_SESSION['user_id']);

// Fetch notifications (excluding soft-deleted)
$notifs = mysqli_query($conn, "
    SELECT * FROM notifications
    WHERE user_id = $user_id
      AND deleted_at IS NULL
    ORDER BY created_at DESC
");

$unread = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS c FROM notifications
    WHERE user_id = $user_id AND is_read = 0 AND deleted_at IS NULL
"))['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications | AdHub</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<link rel="stylesheet" href="/AdHub_V2/assets/css/style.css">
<link rel="stylesheet" href="/AdHub_V2/assets/css/dashboard.css">
<style>
.main-content { margin-left: 260px; padding: 35px; }

.page-header {
    background: linear-gradient(135deg, #1e293b, #334155);
    border-radius: 24px;
    padding: 28px 32px;
    color: white;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}
.page-header h1 { font-size: 26px; font-weight: 700; margin: 0; color: white; }
.page-header p  { margin: 4px 0 0; color: #94a3b8; font-size: 14px; }

.notif-card {
    background: white;
    border-radius: 18px;
    box-shadow: 0 4px 16px rgba(15,23,42,0.06);
    overflow: hidden;
    margin-bottom: 24px;
}

.notif-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    flex-wrap: wrap;
    gap: 10px;
}

.notif-toolbar h3 { font-size: 16px; font-weight: 700; color: #0f172a; margin: 0; }

.btn-mark-all {
    background: #eff6ff; color: #2563eb;
    border: none; border-radius: 10px;
    padding: 8px 16px; font-size: 13px; font-weight: 600;
    cursor: pointer; transition: opacity .2s;
}
.btn-mark-all:hover { opacity: .85; }

.notif-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px 20px;
    border-bottom: 1px solid #f8fafc;
    transition: background .15s;
    position: relative;
}
.notif-item:last-child { border-bottom: none; }
.notif-item:hover      { background: #fafafa; }
.notif-item.unread     { background: #f0f7ff; }
.notif-item.unread:hover { background: #e8f2ff; }

/* Unread indicator stripe */
.notif-item.unread::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 4px;
    background: #3b82f6;
    border-radius: 0 4px 4px 0;
}

.notif-icon {
    width: 40px; height: 40px;
    border-radius: 12px;
    background: #eff6ff; color: #3b82f6;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; flex-shrink: 0;
}

.notif-body     { flex: 1; min-width: 0; }
.notif-title    { font-size: 14px; font-weight: 600; color: #0f172a; }
.notif-message  { font-size: 13px; color: #475569; margin-top: 3px; line-height: 1.5; }
.notif-time     { font-size: 12px; color: #94a3b8; margin-top: 6px; }

.notif-actions  { display: flex; gap: 6px; flex-shrink: 0; }

.btn-read, .btn-del {
    border: none; border-radius: 8px;
    padding: 6px 10px; font-size: 12px; font-weight: 600;
    cursor: pointer; transition: opacity .15s;
    display: inline-flex; align-items: center; gap: 5px;
}
.btn-read { background: #f0fdf4; color: #16a34a; }
.btn-del  { background: #fff1f2; color: #e11d48; }
.btn-read:hover, .btn-del:hover { opacity: .8; }

/* Hide read button if already read */
.notif-item:not(.unread) .btn-read { display: none; }

.empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
.empty-state i { font-size: 48px; margin-bottom: 14px; display: block; }
</style>
</head>
<body>

<!-- SIDEBAR (same as your existing staff sidebar) -->
<div class="sidebar">
    <div class="sidebar-menu">
        <a href="../dashboard/dashboard.php"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
        <a href="../kanban/main_board.php"><i class="fa-solid fa-layer-group"></i> Campaigns</a>
        <a href="../timelogs/time_logs.php"><i class="fa-regular fa-clock"></i> Time Logs</a>
        <a href="../notifications/notifications.php" class="active"><i class="fa-regular fa-bell"></i> Notifications</a>
        <a href="../../auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
</div>

<div class="main-content">

    <div class="page-header">
        <div>
            <h1><i class="fa-regular fa-bell me-2"></i>Notifications</h1>
            <p><?= $unread; ?> unread notification<?= $unread != 1 ? 's' : ''; ?></p>
        </div>
        <?php if ($unread > 0): ?>
        <button class="btn-mark-all" id="markAllBtn">
            <i class="fa-solid fa-check-double me-1"></i> Mark all as read
        </button>
        <?php endif; ?>
    </div>

    <div class="notif-card">
        <div class="notif-toolbar">
            <h3>All notifications <span style="font-weight:400;color:#64748b;">(<?= mysqli_num_rows($notifs); ?>)</span></h3>
        </div>

        <?php if (mysqli_num_rows($notifs) > 0): ?>

            <?php while ($n = mysqli_fetch_assoc($notifs)): ?>
            <div class="notif-item <?= !$n['is_read'] ? 'unread' : ''; ?>"
                 id="notif-<?= $n['notification_id']; ?>">

                <div class="notif-icon">
                    <i class="fa-solid fa-circle-info"></i>
                </div>

                <div class="notif-body">
                    <div class="notif-title"><?= htmlspecialchars($n['title']); ?></div>
                    <div class="notif-message"><?= htmlspecialchars($n['message']); ?></div>
                    <div class="notif-time">
                        <i class="fa-regular fa-clock me-1"></i>
                        <?= date('M d, Y h:i A', strtotime($n['created_at'])); ?>
                    </div>
                </div>

                <div class="notif-actions">
                    <button class="btn-read"
                            onclick="markRead(<?= $n['notification_id']; ?>, this)">
                        <i class="fa-solid fa-check"></i> Read
                    </button>
                    <button class="btn-del"
                            onclick="deleteNotif(<?= $n['notification_id']; ?>)">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>

            </div>
            <?php endwhile; ?>

        <?php else: ?>
            <div class="empty-state">
                <i class="fa-regular fa-bell-slash"></i>
                <p>You're all caught up! No notifications.</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
const AJAX = '/AdHub_V2/ajax/notifications_actions.php';

function markRead(id, btn) {
    fetch(AJAX, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=mark_read&notification_id=' + id
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const row = document.getElementById('notif-' + id);
            row.classList.remove('unread');
            btn.style.display = 'none';
            updateUnreadCount(-1);
        }
    });
}

function deleteNotif(id) {
    if (!confirm('Delete this notification?')) return;
    fetch(AJAX, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete&notification_id=' + id
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const row = document.getElementById('notif-' + id);
            const wasUnread = row.classList.contains('unread');
            row.style.transition = 'opacity 0.3s';
            row.style.opacity = '0';
            setTimeout(() => {
                row.remove();
                if (wasUnread) updateUnreadCount(-1);
            }, 300);
        }
    });
}

document.getElementById('markAllBtn')?.addEventListener('click', function () {
    fetch(AJAX, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=mark_all_read'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.querySelectorAll('.notif-item.unread').forEach(row => {
                row.classList.remove('unread');
                row.querySelector('.btn-read')?.style.setProperty('display', 'none');
            });
            document.getElementById('markAllBtn').remove();
            // Reset the topbar badge
            const badge = document.getElementById('tb-notif-badge');
            if (badge) { badge.textContent = '0'; badge.classList.remove('show'); }
        }
    });
});

function updateUnreadCount(delta) {
    const badge = document.getElementById('tb-notif-badge');
    if (!badge) return;
    let count = parseInt(badge.textContent) || 0;
    count = Math.max(0, count + delta);
    badge.textContent = count > 9 ? '9+' : count;
    count > 0 ? badge.classList.add('show') : badge.classList.remove('show');
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>