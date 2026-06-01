<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include(__DIR__ . '/../config/db.php');

/*
========================================
SESSION DATA
========================================
*/
$user_id   = $_SESSION['user_id']   ?? 0;
$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['role']      ?? 'client';
$user_pic  = $_SESSION['profile_pic'] ?? null;

$initials = strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', trim($user_name)))));
$initials = substr($initials, 0, 2);

/*
========================================
NOTIFICATIONS  (unread first, latest 5)
========================================
*/
$notifQuery = mysqli_query($conn, "
    SELECT notification_id AS id, title, message, created_at,
           0 AS is_read,
           'info' AS type
    FROM notifications
    WHERE user_id = '" . mysqli_real_escape_string($conn, $user_id) . "'
    ORDER BY created_at DESC
    LIMIT 5
");

$notifications = [];
$unread_count  = 0;
while ($row = mysqli_fetch_assoc($notifQuery)) {
    $notifications[] = $row;
    if (!$row['is_read']) $unread_count++;
}

// Since DB has no is_read column, use session to track if user already marked all read
if (isset($_SESSION['notif_all_read']) && $_SESSION['notif_all_read'] == 1) {
    $unread_count = 0;
}

/*
========================================
MARK ALL READ  (AJAX handler)
========================================
*/
if (isset($_GET['mark_all_read']) && $_GET['mark_all_read'] == 1) {
    $_SESSION['notif_all_read'] = 1;
    echo json_encode(['success' => true]);
    exit;
}

/*
========================================
NOTIFICATION ICON  helper
========================================
type: info | success | warning | danger
========================================
*/
if (!function_exists('notif_icon_class')) {
    function notif_icon_class(string $type): string {
        return match ($type) {
            'success' => 'ni-success',
            'warning' => 'ni-warning',
            'danger'  => 'ni-danger',
            default   => 'ni-info',
        };
    }
}

if (!function_exists('notif_icon')) {
    function notif_icon(string $type): string {
        return match ($type) {
            'success' => 'fa-circle-check',
            'warning' => 'fa-triangle-exclamation',
            'danger'  => 'fa-circle-xmark',
            default   => 'fa-circle-info',
        };
    }
}
?>

<!-- ============================================================
TOPBAR STYLES
============================================================ -->
<style>
/* ---------- BASE ---------- */
:root {
    --tb-h: 58px;
    --tb-bg: #ffffff;
    --tb-border: #e9edf2;
    --tb-shadow: 0 1px 3px rgba(0,0,0,.06);

    --accent: #3b5bdb;
    --accent-light: #eef2ff;
    --accent-text: #3b5bdb;

    --text-1: #111827;
    --text-2: #4b5563;
    --text-3: #9ca3af;

    --surface: #f9fafb;
    --border: #e5e7eb;
    --radius: 10px;
    --radius-lg: 14px;

    --drop-w: 320px;
    --settings-w: 280px;

    --ni-info-bg: #eff6ff;  --ni-info-fg: #2563eb;
    --ni-success-bg: #f0fdf4; --ni-success-fg: #16a34a;
    --ni-warning-bg: #fffbeb; --ni-warning-fg: #d97706;
    --ni-danger-bg:  #fef2f2; --ni-danger-fg:  #dc2626;
}

/* ---------- TOPBAR ---------- */
.topbar {
    height: var(--tb-h);
    background: var(--tb-bg);
    border-bottom: 1px solid var(--tb-border);
    box-shadow: var(--tb-shadow);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px;
    position: sticky;
    top: 0;
    z-index: 500;
}

.topbar .logo-section h3 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-1);
    letter-spacing: -.4px;
}

.topbar-icons {
    display: flex;
    align-items: center;
    gap: 6px;
}

/* ---------- ICON BUTTON ---------- */
.tb-btn {
    width: 36px;
    height: 36px;
    border-radius: var(--radius);
    background: transparent;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-2);
    font-size: 17px;
    transition: background .15s, color .15s;
    position: relative;
}
.tb-btn:hover { background: var(--surface); color: var(--text-1); }
.tb-btn.is-open { background: var(--accent-light); color: var(--accent); }

/* unread badge */
.tb-badge {
    position: absolute;
    top: 4px; right: 4px;
    min-width: 16px; height: 16px;
    padding: 0 4px;
    border-radius: 8px;
    background: #ef4444;
    color: #fff;
    font-size: 9px;
    font-weight: 700;
    line-height: 16px;
    text-align: center;
    border: 2px solid var(--tb-bg);
    display: none;
}
.tb-badge.visible { display: block; }

/* ---------- AVATAR ---------- */
.tb-avatar {
    width: 34px; height: 34px;
    border-radius: 50%;
    overflow: hidden;
    cursor: pointer;
    border: 2px solid var(--border);
    transition: border-color .15s;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--accent-light);
    color: var(--accent-text);
    font-size: 12px;
    font-weight: 600;
}
.tb-avatar img { width: 100%; height: 100%; object-fit: cover; }
.tb-avatar:hover { border-color: var(--accent); }

/* ---------- DROPDOWN SHARED ---------- */
.tb-dropdown-wrap { position: relative; }

.tb-dropdown {
    position: absolute;
    right: 0;
    top: calc(100% + 10px);
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: 0 8px 28px rgba(0,0,0,.10), 0 2px 8px rgba(0,0,0,.06);
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-6px) scale(.98);
    transform-origin: top right;
    transition: opacity .18s ease, transform .18s ease, visibility .18s;
}
.tb-dropdown.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
}

.drop-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 13px 15px 11px;
    border-bottom: 1px solid var(--border);
}
.drop-head-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-1);
}
.drop-head-action {
    font-size: 12px;
    color: var(--accent-text);
    background: none;
    border: none;
    cursor: pointer;
    padding: 3px 7px;
    border-radius: 6px;
    transition: background .12s;
}
.drop-head-action:hover { background: var(--accent-light); }

/* ---------- NOTIFICATION DROPDOWN ---------- */
#notifDropdown { width: var(--drop-w); }

.notif-list { max-height: 300px; overflow-y: auto; }

.notif-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 11px 15px;
    border-bottom: 1px solid #f3f4f6;
    cursor: default;
    transition: background .12s;
}
.notif-item:last-child { border-bottom: none; }
.notif-item:hover { background: #fafafa; }

.notif-icon-wrap {
    width: 32px; height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    flex-shrink: 0;
    margin-top: 1px;
}
.ni-info    { background: var(--ni-info-bg);    color: var(--ni-info-fg); }
.ni-success { background: var(--ni-success-bg); color: var(--ni-success-fg); }
.ni-warning { background: var(--ni-warning-bg); color: var(--ni-warning-fg); }
.ni-danger  { background: var(--ni-danger-bg);  color: var(--ni-danger-fg); }

.notif-body { flex: 1; min-width: 0; }
.notif-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-1);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.notif-msg {
    font-size: 12px;
    color: var(--text-2);
    margin-top: 2px;
    line-height: 1.45;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.notif-time {
    font-size: 11px;
    color: var(--text-3);
    margin-top: 4px;
}

.notif-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    background: var(--accent);
    flex-shrink: 0;
    margin-top: 6px;
}

.notif-empty {
    padding: 28px 15px;
    text-align: center;
    font-size: 13px;
    color: var(--text-3);
}
.notif-empty i { font-size: 26px; display: block; margin-bottom: 8px; opacity: .45; }

.drop-footer {
    padding: 10px 15px;
    border-top: 1px solid var(--border);
}
.drop-footer a {
    display: block;
    text-align: center;
    font-size: 12px;
    color: var(--accent-text);
    text-decoration: none;
    padding: 7px;
    border-radius: var(--radius);
    background: var(--accent-light);
    transition: opacity .12s;
}
.drop-footer a:hover { opacity: .8; }

/* ---------- SETTINGS DROPDOWN ---------- */
#settingsDropdown { width: var(--settings-w); }

.settings-user-card {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 13px 15px;
    border-bottom: 1px solid var(--border);
}
.settings-user-avatar {
    width: 40px; height: 40px;
    border-radius: 50%;
    overflow: hidden;
    background: var(--accent-light);
    color: var(--accent-text);
    font-size: 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border: 2px solid var(--border);
}
.settings-user-avatar img { width: 100%; height: 100%; object-fit: cover; }
.settings-user-name { font-size: 14px; font-weight: 600; color: var(--text-1); }
.settings-user-role {
    font-size: 11px;
    color: var(--text-3);
    text-transform: capitalize;
    margin-top: 1px;
}

.settings-section { padding: 5px 0; border-bottom: 1px solid var(--border); }
.settings-section:last-child { border-bottom: none; }
.settings-section-label {
    padding: 7px 15px 3px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: var(--text-3);
}

.settings-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 15px;
    cursor: pointer;
    transition: background .12s;
    text-decoration: none;
    color: inherit;
}
.settings-item:hover { background: var(--surface); }

.settings-item-icon {
    width: 28px; height: 28px;
    border-radius: 7px;
    background: var(--surface);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    color: var(--text-2);
    flex-shrink: 0;
    border: 1px solid var(--border);
}

.settings-item-text { flex: 1; }
.settings-item-label { font-size: 13px; color: var(--text-1); }
.settings-item-meta  { font-size: 11px; color: var(--text-3); margin-top: 1px; }
.settings-item-right { font-size: 12px; color: var(--text-3); }

.settings-item.danger .settings-item-label { color: #dc2626; }
.settings-item.danger .settings-item-icon  { background: #fef2f2; border-color: #fecaca; color: #dc2626; }

/* ---- toggle switch ---- */
.tb-toggle {
    width: 34px; height: 19px;
    border-radius: 10px;
    background: var(--border);
    border: none;
    cursor: pointer;
    position: relative;
    transition: background .2s;
    flex-shrink: 0;
}
.tb-toggle.on { background: var(--accent); }
.tb-toggle::after {
    content: '';
    position: absolute;
    width: 15px; height: 15px;
    border-radius: 50%;
    background: #fff;
    top: 2px; left: 2px;
    transition: transform .2s;
    box-shadow: 0 1px 3px rgba(0,0,0,.15);
}
.tb-toggle.on::after { transform: translateX(15px); }

/* ---- modal overlay ---- */
.tb-modal-bg {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.45);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}
.tb-modal-bg.open { display: flex; }

.tb-modal {
    background: #fff;
    border-radius: var(--radius-lg);
    width: 420px;
    max-width: 95vw;
    box-shadow: 0 20px 60px rgba(0,0,0,.2);
    overflow: hidden;
}

.tb-modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px 18px;
    border-bottom: 1px solid var(--border);
}
.tb-modal-head h4 { font-size: 15px; font-weight: 600; color: var(--text-1); margin: 0; }
.tb-modal-close {
    width: 28px; height: 28px;
    border-radius: 6px;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 16px;
    color: var(--text-3);
    display: flex; align-items: center; justify-content: center;
    transition: background .12s;
}
.tb-modal-close:hover { background: var(--surface); color: var(--text-1); }

.tb-modal-body { padding: 18px; }

.tb-form-group { margin-bottom: 14px; }
.tb-form-group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-2);
    margin-bottom: 5px;
}
.tb-form-group input[type="text"],
.tb-form-group input[type="email"],
.tb-form-group input[type="password"] {
    width: 100%;
    height: 38px;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 0 12px;
    font-size: 13px;
    color: var(--text-1);
    background: #fff;
    outline: none;
    transition: border-color .15s;
}
.tb-form-group input:focus { border-color: var(--accent); }

.tb-avatar-upload {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px;
    background: var(--surface);
    border-radius: var(--radius);
    border: 1px dashed var(--border);
    margin-bottom: 16px;
}
.tb-avatar-preview {
    width: 52px; height: 52px;
    border-radius: 50%;
    background: var(--accent-light);
    color: var(--accent-text);
    font-size: 18px;
    font-weight: 600;
    display: flex; align-items: center; justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
    border: 2px solid var(--border);
}
.tb-avatar-preview img { width: 100%; height: 100%; object-fit: cover; }
.tb-avatar-upload-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 12px;
    font-size: 12px;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    background: #fff;
    cursor: pointer;
    color: var(--text-2);
    transition: border-color .15s;
}
.tb-avatar-upload-btn:hover { border-color: var(--accent); color: var(--accent); }
.tb-avatar-upload-hint { font-size: 11px; color: var(--text-3); margin-top: 3px; }

.tb-modal-foot {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 12px 18px;
    border-top: 1px solid var(--border);
    background: var(--surface);
}
.tb-btn-ghost {
    padding: 7px 16px;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    background: #fff;
    font-size: 13px;
    cursor: pointer;
    color: var(--text-2);
    transition: background .12s;
}
.tb-btn-ghost:hover { background: var(--border); }
.tb-btn-primary {
    padding: 7px 16px;
    border-radius: var(--radius);
    border: none;
    background: var(--accent);
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: opacity .12s;
}
.tb-btn-primary:hover { opacity: .88; }

.tb-alert {
    padding: 9px 12px;
    border-radius: var(--radius);
    font-size: 12px;
    margin-bottom: 12px;
    display: none;
}
.tb-alert.show { display: block; }
.tb-alert.success { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.tb-alert.error   { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
</style>

<!-- ============================================================
TOPBAR HTML
============================================================ -->

<header class="topbar">

    <div class="logo-section">
        <h3>AdHub</h3>
    </div>

    <div class="topbar-icons">

        <!-- ── NOTIFICATIONS ── -->
        <div class="tb-dropdown-wrap">

            <button class="tb-btn" id="notifBell" aria-label="Notifications">
                <i class="fa-regular fa-bell"></i>
                <span class="tb-badge <?= $unread_count > 0 ? 'visible' : '' ?>" id="notifBadge">
                    <?= $unread_count > 9 ? '9+' : $unread_count ?>
                </span>
            </button>

            <div class="tb-dropdown" id="notifDropdown">

                <div class="drop-head">
                    <span class="drop-head-title">
                        Notifications
                        <?php if($unread_count > 0): ?>
                            <span style="font-size:11px;font-weight:400;color:var(--text-3);">(<?= $unread_count ?> unread)</span>
                        <?php endif; ?>
                    </span>
                    <?php if($unread_count > 0): ?>
                        <button class="drop-head-action" id="markAllReadBtn">Mark all read</button>
                    <?php endif; ?>
                </div>

                <div class="notif-list">
                    <?php if(count($notifications) > 0): ?>

                        <?php foreach($notifications as $notif):
                            $type = $notif['type'] ?? 'info';
                        ?>
                        <div class="notif-item">
                            <div class="notif-icon-wrap <?= notif_icon_class($type) ?>">
                                <i class="fa-solid <?= notif_icon($type) ?>"></i>
                            </div>
                            <div class="notif-body">
                                <div class="notif-title"><?= htmlspecialchars($notif['title']) ?></div>
                                <div class="notif-msg"><?= htmlspecialchars($notif['message']) ?></div>
                                <div class="notif-time"><?= date('M d, g:i A', strtotime($notif['created_at'])) ?></div>
                            </div>
                            <?php if(!$notif['is_read']): ?>
                                <div class="notif-dot"></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>

                    <?php else: ?>
                        <div class="notif-empty">
                            <i class="fa-regular fa-bell-slash"></i>
                            No notifications yet
                        </div>
                    <?php endif; ?>
                </div>

                <div class="drop-footer">
                    <a href="/AdHub_V2/<?= $user_role ?>/notifications/notifications.php">
                        View all notifications
                    </a>
                </div>

            </div>
        </div>

        <!-- ── SETTINGS GEAR ── -->
        <div class="tb-dropdown-wrap">

            <button class="tb-btn" id="settingsGear" aria-label="Settings">
                <i class="fa-solid fa-gear"></i>
            </button>

            <div class="tb-dropdown" id="settingsDropdown">

                <!-- user card -->
                <div class="settings-user-card">
                    <div class="settings-user-avatar" id="settingsAvatarThumb">
                        <?php if($user_pic): ?>
                            <img src="<?= htmlspecialchars($user_pic) ?>" alt="Profile">
                        <?php else: ?>
                            <?= htmlspecialchars($initials) ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="settings-user-name"><?= htmlspecialchars($user_name) ?></div>
                        <div class="settings-user-role"><?= htmlspecialchars($user_role) ?></div>
                    </div>
                </div>

                <!-- Account section -->
                <div class="settings-section">
                    <div class="settings-section-label">Account</div>

                    <div class="settings-item" onclick="openModal('profileModal')">
                        <div class="settings-item-icon"><i class="fa-regular fa-user"></i></div>
                        <div class="settings-item-text">
                            <div class="settings-item-label">Edit profile</div>
                            <div class="settings-item-meta">Name, photo</div>
                        </div>
                        <i class="fa-solid fa-chevron-right settings-item-right"></i>
                    </div>

                    <div class="settings-item" onclick="openModal('passwordModal')">
                        <div class="settings-item-icon"><i class="fa-solid fa-lock"></i></div>
                        <div class="settings-item-text">
                            <div class="settings-item-label">Change password</div>
                        </div>
                        <i class="fa-solid fa-chevron-right settings-item-right"></i>
                    </div>
                </div>

                <!-- Preferences section -->
                <div class="settings-section">
                    <div class="settings-section-label">Preferences</div>

                    <div class="settings-item" style="cursor:default;">
                        <div class="settings-item-icon"><i class="fa-regular fa-bell"></i></div>
                        <div class="settings-item-text">
                            <div class="settings-item-label">Email notifications</div>
                        </div>
                        <button class="tb-toggle <?= ($_SESSION['email_notif'] ?? 1) ? 'on' : '' ?>"
                                id="emailToggle"
                                onclick="togglePref('email_notif', this)"
                                aria-label="Toggle email notifications"></button>
                    </div>
                </div>

                <!-- Sign out -->
                <div class="settings-section">
                    <a class="settings-item danger"
                       href="/AdHub_V2/logout.php"
                       onclick="return confirm('Sign out of AdHub?')">
                        <div class="settings-item-icon"><i class="fa-solid fa-arrow-right-from-bracket"></i></div>
                        <div class="settings-item-text">
                            <div class="settings-item-label">Sign out</div>
                        </div>
                    </a>
                </div>

            </div>
        </div>

        <!-- ── AVATAR ── -->
        <div class="tb-avatar" id="topbarAvatar" onclick="openModal('profileModal')" title="Edit profile">
            <?php if($user_pic): ?>
                <img src="<?= htmlspecialchars($user_pic) ?>" alt="Profile" id="topbarAvatarImg">
            <?php else: ?>
                <span id="topbarAvatarInitials"><?= htmlspecialchars($initials) ?></span>
            <?php endif; ?>
        </div>

    </div>
</header>


<!-- ============================================================
MODAL: EDIT PROFILE
============================================================ -->
<div class="tb-modal-bg" id="profileModal">
    <div class="tb-modal">
        <div class="tb-modal-head">
            <h4>Edit profile</h4>
            <button class="tb-modal-close" onclick="closeModal('profileModal')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="tb-modal-body">

            <div class="tb-alert" id="profileAlert"></div>

            <!-- avatar upload -->
            <div class="tb-avatar-upload">
                <div class="tb-avatar-preview" id="avatarPreview">
                    <?php if($user_pic): ?>
                        <img src="<?= htmlspecialchars($user_pic) ?>" id="avatarPreviewImg" alt="Preview">
                    <?php else: ?>
                        <span id="avatarPreviewInitials"><?= htmlspecialchars($initials) ?></span>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="tb-avatar-upload-btn" for="avatarFile">
                        <i class="fa-solid fa-upload"></i> Upload photo
                    </label>
                    <input type="file" id="avatarFile" accept="image/*" style="display:none">
                    <div class="tb-avatar-hint">JPG, PNG or GIF · max 2 MB</div>
                </div>
            </div>

            <form id="profileForm">
                <div class="tb-form-group">
                    <label>Full name</label>
                    <input type="text" name="name" id="profileName"
                           value="<?= htmlspecialchars($user_name) ?>"
                           placeholder="Your name">
                </div>
                <div class="tb-form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="profileEmail"
                           value="<?= htmlspecialchars($_SESSION['email'] ?? '') ?>"
                           placeholder="your@email.com">
                </div>
            </form>
        </div>
        <div class="tb-modal-foot">
            <button class="tb-btn-ghost" onclick="closeModal('profileModal')">Cancel</button>
            <button class="tb-btn-primary" onclick="saveProfile()">Save changes</button>
        </div>
    </div>
</div>


<!-- ============================================================
MODAL: CHANGE PASSWORD
============================================================ -->
<div class="tb-modal-bg" id="passwordModal">
    <div class="tb-modal">
        <div class="tb-modal-head">
            <h4>Change password</h4>
            <button class="tb-modal-close" onclick="closeModal('passwordModal')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="tb-modal-body">

            <div class="tb-alert" id="passwordAlert"></div>

            <form id="passwordForm" autocomplete="off">
                <div class="tb-form-group">
                    <label>Current password</label>
                    <input type="password" name="current_password" id="currentPassword" placeholder="••••••••">
                </div>
                <div class="tb-form-group">
                    <label>New password</label>
                    <input type="password" name="new_password" id="newPassword" placeholder="••••••••">
                </div>
                <div class="tb-form-group">
                    <label>Confirm new password</label>
                    <input type="password" name="confirm_password" id="confirmPassword" placeholder="••••••••">
                </div>
            </form>
        </div>
        <div class="tb-modal-foot">
            <button class="tb-btn-ghost" onclick="closeModal('passwordModal')">Cancel</button>
            <button class="tb-btn-primary" onclick="savePassword()">Update password</button>
        </div>
    </div>
</div>


<!-- ============================================================
SCRIPTS
============================================================ -->
<script>
(function () {

    /* ── dropdown toggle ── */
    const dropMap = {
        notifBell:    'notifDropdown',
        settingsGear: 'settingsDropdown',
    };

    Object.entries(dropMap).forEach(([btnId, dropId]) => {
        const btn  = document.getElementById(btnId);
        const drop = document.getElementById(dropId);

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = drop.classList.contains('show');
            closeAllDropdowns();
            if (!isOpen) {
                drop.classList.add('show');
                btn.classList.add('is-open');
            }
        });
    });

    document.addEventListener('click', closeAllDropdowns);

    function closeAllDropdowns() {
        document.querySelectorAll('.tb-dropdown').forEach(d => d.classList.remove('show'));
        document.querySelectorAll('.tb-btn').forEach(b => b.classList.remove('is-open'));
    }

    /* ── mark all read ── */
    const markBtn = document.getElementById('markAllReadBtn');
    if (markBtn) {
        markBtn.addEventListener('click', () => {
            fetch('?mark_all_read=1')
                .then(r => r.json())
                .then(() => {
                    document.querySelectorAll('.notif-dot').forEach(d => d.remove());
                    document.getElementById('notifBadge').classList.remove('visible');
                    markBtn.style.display = 'none';
                });
        });
    }

    /* ── modals ── */
    window.openModal = function (id) {
        closeAllDropdowns();
        document.getElementById(id).classList.add('open');
    };
    window.closeModal = function (id) {
        document.getElementById(id).classList.remove('open');
        clearAlerts();
    };

    document.querySelectorAll('.tb-modal-bg').forEach(bg => {
        bg.addEventListener('click', e => {
            if (e.target === bg) bg.classList.remove('open');
        });
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.tb-modal-bg.open').forEach(m => m.classList.remove('open'));
            clearAlerts();
        }
    });

    function clearAlerts() {
        document.querySelectorAll('.tb-alert').forEach(a => {
            a.className = 'tb-alert';
            a.textContent = '';
        });
    }

    function showAlert(id, type, msg) {
        const el = document.getElementById(id);
        el.className = 'tb-alert show ' + type;
        el.textContent = msg;
    }

    /* ── avatar preview ── */
    document.getElementById('avatarFile').addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        if (file.size > 2 * 1024 * 1024) {
            showAlert('profileAlert', 'error', 'File too large. Maximum size is 2 MB.');
            return;
        }
        const reader = new FileReader();
        reader.onload = e => {
            const src = e.target.result;
            const preview = document.getElementById('avatarPreview');
            preview.innerHTML = '<img src="' + src + '" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">';
        };
        reader.readAsDataURL(file);
    });

    /* ── save profile ── */
    window.saveProfile = function () {
        const name  = document.getElementById('profileName').value.trim();
        const email = document.getElementById('profileEmail').value.trim();
        const file  = document.getElementById('avatarFile').files[0];

        if (!name) { showAlert('profileAlert', 'error', 'Name cannot be empty.'); return; }
        if (!email) { showAlert('profileAlert', 'error', 'Email cannot be empty.'); return; }

        const fd = new FormData();
        fd.append('action', 'update_profile');
        fd.append('name', name);
        fd.append('email', email);
        if (file) fd.append('avatar', file);

        fetch('/AdHub_V2/ajax/user_settings.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert('profileAlert', 'success', 'Profile updated successfully.');
                    /* update topbar avatar live */
                    updateTopbarAvatar(name, data.avatar_url ?? null);
                    setTimeout(() => closeModal('profileModal'), 1200);
                } else {
                    showAlert('profileAlert', 'error', data.message ?? 'Something went wrong.');
                }
            })
            .catch(() => showAlert('profileAlert', 'error', 'Network error. Please try again.'));
    };

    /* ── save password ── */
    window.savePassword = function () {
        const curr    = document.getElementById('currentPassword').value;
        const newPass = document.getElementById('newPassword').value;
        const confirm = document.getElementById('confirmPassword').value;

        if (!curr || !newPass || !confirm) {
            showAlert('passwordAlert', 'error', 'Please fill in all fields.');
            return;
        }
        if (newPass.length < 8) {
            showAlert('passwordAlert', 'error', 'New password must be at least 8 characters.');
            return;
        }
        if (newPass !== confirm) {
            showAlert('passwordAlert', 'error', 'Passwords do not match.');
            return;
        }

        const fd = new FormData();
        fd.append('action', 'change_password');
        fd.append('current_password', curr);
        fd.append('new_password', newPass);

        fetch('/AdHub_V2/ajax/user_settings.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert('passwordAlert', 'success', 'Password updated.');
                    document.getElementById('passwordForm').reset();
                    setTimeout(() => closeModal('passwordModal'), 1200);
                } else {
                    showAlert('passwordAlert', 'error', data.message ?? 'Incorrect current password.');
                }
            })
            .catch(() => showAlert('passwordAlert', 'error', 'Network error. Please try again.'));
    };

    /* ── email notification toggle ── */
    window.togglePref = function (pref, btn) {
        btn.classList.toggle('on');
        const val = btn.classList.contains('on') ? 1 : 0;
        fetch('/AdHub_V2/ajax/user_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=toggle_pref&pref=' + pref + '&value=' + val
        });
    };

    /* ── update topbar avatar after save ── */
    function updateTopbarAvatar(name, avatarUrl) {
        const initials = name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);

        ['topbarAvatar', 'settingsAvatarThumb'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            if (avatarUrl) {
                el.innerHTML = '<img src="' + avatarUrl + '" style="width:100%;height:100%;object-fit:cover;">';
            } else {
                el.innerHTML = '<span>' + initials + '</span>';
            }
        });

        const nameEls = document.querySelectorAll('.settings-user-name');
        nameEls.forEach(el => el.textContent = name);
    }

})();
</script>