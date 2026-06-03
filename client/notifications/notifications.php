<?php
session_start();
include('../../config/db.php');
include('../../includes/topbar.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php"); exit();
}
if ($_SESSION['role'] != 'client') {
    header("Location: ../../index.php"); exit();
}

$user_id = intval($_SESSION['user_id']);
$filter  = $_GET['filter'] ?? 'all';

$filterCondition = "";
if ($filter == 'approved') {
    $filterCondition = "AND LOWER(title) LIKE '%approved%'";
} elseif ($filter == 'revision') {
    $filterCondition = "AND LOWER(title) LIKE '%revision%'";
} elseif ($filter == 'upload') {
    $filterCondition = "AND LOWER(title) LIKE '%upload%'";
} elseif ($filter == 'deadline') {
    $filterCondition = "AND LOWER(title) LIKE '%deadline%'";
}

$notifications = mysqli_query($conn, "
    SELECT * FROM notifications
    WHERE user_id = $user_id
      AND deleted_at IS NULL
      $filterCondition
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

.btn-mark-all {
    background: rgba(255,255,255,0.15);
    color: white;
    border: 1px solid rgba(255,255,255,0.25);
    border-radius: 10px;
    padding: 8px 18px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s;
}
.btn-mark-all:hover { background: rgba(255,255,255,0.25); }

/* Filter bar */
.filter-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 24px;
}
.filter-btn {
    border-radius: 50px;
    font-size: 13px;
    font-weight: 600;
    padding: 8px 18px;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all .2s;
}

.notif-card {
    background: white;
    border-radius: 24px;
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
}
.notif-toolbar h3 { font-size: 16px; font-weight: 700; color: #0f172a; margin: 0; }

.notif-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px 20px;
    border-bottom: 1px solid #f8fafc;
    transition: background .15s;
    position: relative;
}
.notif-item:last-child  { border-bottom: none; }
.notif-item:hover       { background: #fafafa; }
.notif-item.unread      { background: #f0f7ff; }
.notif-item.unread:hover { background: #e8f2ff; }

.notif-item.unread::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 4px;
    background: #3b82f6;
    border-radius: 0 4px 4px 0;
}

.notif-icon {
    width: 44px; height: 44px;
    border-radius: 13px;
    display: flex; align-items: center; justify-content: center;
    font-size: 17px; flex-shrink: 0;
}
.icon-success { background: #dcfce7; color: #16a34a; }
.icon-warning { background: #fef3c7; color: #d97706; }
.icon-blue    { background: #dbeafe; color: #2563eb; }
.icon-red     { background: #fee2e2; color: #dc2626; }

.notif-body    { flex: 1; min-width: 0; }
.notif-title   { font-size: 14px; font-weight: 600; color: #0f172a; }
.notif-message { font-size: 13px; color: #475569; margin-top: 3px; line-height: 1.5; }
.notif-time    { font-size: 12px; color: #94a3b8; margin-top: 6px; }

.notif-actions { display: flex; gap: 6px; flex-shrink: 0; align-items: center; }

.btn-read, .btn-del {
    border: none; border-radius: 8px;
    padding: 6px 10px; font-size: 12px; font-weight: 600;
    cursor: pointer; transition: opacity .15s;
    display: inline-flex; align-items: center; gap: 5px;
}
.btn-read { background: #f0fdf4; color: #16a34a; }
.btn-del  { background: #fff1f2; color: #e11d48; }
.btn-read:hover, .btn-del:hover { opacity: .8; }
.notif-item:not(.unread) .btn-read { display: none; }

.empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
.empty-state i { font-size: 48px; margin-bottom: 14px; display: block; }
.empty-state h5 { font-size: 16px; color: #64748b; margin-bottom: 6px; }
.empty-state p  { font-size: 13px; margin: 0; }
</style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-menu">
        <a href="../dashboard/dashboard.php"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
        <a href="../kanban/main_board.php"><i class="fa-solid fa-layer-group"></i> Campaigns</a>
        <a href="../retainer/retainer.php"><i class="fa-solid fa-wallet"></i> Retainer</a>
        <a href="notifications.php" class="active"><i class="fa-regular fa-bell"></i> Notifications</a>
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

    <!-- FILTER BAR -->
    <div class="filter-bar">
        <a href="notifications.php?filter=all"
           class="btn filter-btn <?= $filter == 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">
            <i class="fa-solid fa-layer-group"></i> All
        </a>
        <a href="notifications.php?filter=approved"
           class="btn filter-btn <?= $filter == 'approved' ? 'btn-success' : 'btn-outline-success'; ?>">
            <i class="fa-solid fa-circle-check"></i> Approved
        </a>
        <a href="notifications.php?filter=revision"
           class="btn filter-btn <?= $filter == 'revision' ? 'btn-warning' : 'btn-outline-warning'; ?>">
            <i class="fa-solid fa-rotate-right"></i> Revision
        </a>
        <a href="notifications.php?filter=upload"
           class="btn filter-btn <?= $filter == 'upload' ? 'btn-info' : 'btn-outline-info'; ?>">
            <i class="fa-solid fa-file-arrow-up"></i> Uploads
        </a>
        <a href="notifications.php?filter=deadline"
           class="btn filter-btn <?= $filter == 'deadline' ? 'btn-danger' : 'btn-outline-danger'; ?>">
            <i class="fa-solid fa-clock"></i> Deadlines
        </a>
    </div>

    <div class="notif-card">
        <div class="notif-toolbar">
            <h3>All notifications
                <span style="font-weight:400;color:#64748b;">
                    (<?= mysqli_num_rows($notifications); ?>)
                </span>
            </h3>
        </div>

        <?php if (mysqli_num_rows($notifications) > 0): ?>
            <?php while ($n = mysqli_fetch_assoc($notifications)):

                $icon  = 'fa-bell';
                $color = 'icon-blue';
                $t     = strtolower($n['title']);

                if (str_contains($t, 'approved'))     { $icon = 'fa-circle-check';  $color = 'icon-success'; }
                elseif (str_contains($t, 'revision')) { $icon = 'fa-rotate-right';  $color = 'icon-warning'; }
                elseif (str_contains($t, 'upload'))   { $icon = 'fa-file-arrow-up'; $color = 'icon-blue';    }
                elseif (str_contains($t, 'deadline')) { $icon = 'fa-clock';         $color = 'icon-red';     }
            ?>
            <div class="notif-item <?= !$n['is_read'] ? 'unread' : ''; ?>"
                 id="notif-<?= $n['notification_id']; ?>">

                <div class="notif-icon <?= $color; ?>">
                    <i class="fa-solid <?= $icon; ?>"></i>
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
                <h5>No notifications found</h5>
                <p>Try selecting a different filter.</p>
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
            this.remove();
            const badge = document.getElementById('tb-notif-badge');
            if (badge) { badge.textContent = '0'; badge.classList.remove('show'); }
            // Update subtitle in page header
            document.querySelector('.page-header p').textContent = '0 unread notifications';
        }
    });
});

function updateUnreadCount(delta) {
    // Update topbar badge
    const badge = document.getElementById('tb-notif-badge');
    if (badge) {
        let count = parseInt(badge.textContent) || 0;
        count = Math.max(0, count + delta);
        badge.textContent = count > 9 ? '9+' : count;
        count > 0 ? badge.classList.add('show') : badge.classList.remove('show');
    }
    // Update page header subtitle
    const subtitle = document.querySelector('.page-header p');
    if (subtitle) {
        let current = parseInt(subtitle.textContent) || 0;
        current = Math.max(0, current + delta);
        subtitle.textContent = current + ' unread notification' + (current != 1 ? 's' : '');
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>