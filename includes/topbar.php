<head>
    <link rel="icon" type="image/png" href="/AdHub_V2/assets/adHub_LOGO.png">
</head>
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
$user_id   = $_SESSION['user_id'] ?? 0;
$user_name = $_SESSION['name']    ?? 'User';
$user_role = $_SESSION['role']    ?? 'client';
$user_pic = $_SESSION['has_avatar'] ?? false
    ? '/AdHub_V2/ajax/get_avatar.php?id=' . $user_id
    : null;

$initials = 'U';
if (!empty(trim($user_name))) {
    $words = explode(' ', trim($user_name));
    $initials = strtoupper(implode('', array_map(fn($w) => $w[0], $words)));
    $initials = substr($initials, 0, 2);
}

/*
========================================
FOLDER PATH  (role → folder name)
========================================
*/
$folder = ($user_role === 'staff') ? 'admin' : 'client';

/*
========================================
NOTIFICATIONS  (latest 5)
========================================
*/
$notifQuery = mysqli_query($conn, "
    SELECT notification_id AS id, title, message, created_at
    FROM   notifications
    WHERE  user_id = '" . mysqli_real_escape_string($conn, $user_id) . "'
    ORDER  BY created_at DESC
    LIMIT  5
");

$notifications = [];
while ($row = mysqli_fetch_assoc($notifQuery)) {
    $notifications[] = $row;
}
$notif_count = count($notifications);

/*
========================================
MARK ALL READ  (AJAX — session-based)
========================================
*/
if (isset($_GET['mark_all_read']) && $_GET['mark_all_read'] == 1) {
    $_SESSION['notif_all_read'] = 1;
    echo json_encode(['success' => true]);
    exit;
}

$unread_count = isset($_SESSION['notif_all_read']) ? 0 : $notif_count;

// Reset unread flag when new notifications arrive (simple heuristic)
if ($notif_count === 0) {
    $_SESSION['notif_all_read'] = 1;
}
?>

<style>
/* ── RESET ── */
*, *::before, *::after { box-sizing: border-box; }

/* ── VARIABLES ── */
:root {
    --tb-h:        60px;
    --accent:      #1F3A93;
    --accent-dark: #162d7a;
    --accent-lite: #e8ecf8;
    --accent-mid:  #c5cef0;
    --white:       #ffffff;
    --text-1:      #111827;
    --text-2:      #4b5563;
    --text-3:      #9ca3af;
    --border:      #e5e7eb;
    --surface:     #f9fafb;
    --danger:      #dc2626;
    --danger-bg:   #fef2f2;
    --danger-bd:   #fecaca;
    --r:           10px;
    --r-lg:        14px;
    --drop-notif:  320px;
    --drop-set:    290px;
    --shadow:      0 8px 30px rgba(0,0,0,.12), 0 2px 8px rgba(0,0,0,.06);
}

/* ── TOPBAR ── */

.tb-drop {
    z-index: 999999 !important;
    position: absolute;
}

.topbar {
    overflow: visible !important;
}

.tb-drop-wrap {
    position: relative;
}

.adhub-topbar {
    height: var(--tb-h);
    background: #1F3A93 !important;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
    position: sticky;
    top: 0;
    z-index: 500;
    box-shadow: 0 2px 12px rgba(31,58,147,.35);
}

.adhub-topbar .tb-logo {
    font-size: 20px;
    font-weight: 800;
    color: var(--white);
    letter-spacing: -.5px;
}

/* ── ICON ROW ── */
.tb-icons {
    display: flex;
    align-items: center;
    gap: 4px;
}

/* ── ICON BUTTON ── */
.tb-btn {
    width: 38px;
    height: 38px;
    border-radius: var(--r);
    background: transparent;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255,255,255,.80);
    font-size: 18px;
    transition: background .15s, color .15s;
    position: relative;
    flex-shrink: 0;
}
.tb-btn:hover  { background: rgba(255,255,255,.15); color: #fff; }
.tb-btn.active { background: rgba(255,255,255,.20); color: #fff; }

/* ── BADGE ── */
.tb-badge {
    position: absolute;
    top: 3px; right: 3px;
    min-width: 17px; height: 17px;
    padding: 0 4px;
    border-radius: 9px;
    background: #ef4444;
    color: #fff;
    font-size: 9px;
    font-weight: 700;
    line-height: 17px;
    text-align: center;
    border: 2px solid var(--accent);
    display: none;
}
.tb-badge.show { display: block; }

/* ── AVATAR ── */
.tb-avatar {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: rgba(255,255,255,.20);
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    border: 2px solid rgba(255,255,255,.35);
    overflow: hidden;
    flex-shrink: 0;
    transition: border-color .15s;
}
.tb-avatar:hover { border-color: rgba(255,255,255,.8); }
.tb-avatar img   { width: 100%; height: 100%; object-fit: cover; }

/* ── DROPDOWN WRAPPER ── */
.tb-drop-wrap { position: relative; }

/* ── DROPDOWN PANEL ── */
.tb-drop {
    position: absolute;
    top: calc(100% + 12px);
    right: 0;
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    box-shadow: var(--shadow);
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-8px) scale(.97);
    transform-origin: top right;
    transition: opacity .18s, transform .18s, visibility .18s;
    overflow: hidden;
}
.tb-drop.open {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
}

#tb-notif-drop { width: var(--drop-notif); }
#tb-set-drop   { width: var(--drop-set); }

/* ── DROP HEADER ── */
.tb-drop-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 13px 16px 11px;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}
.tb-drop-head-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--text-1);
}
.tb-drop-head-title span {
    font-weight: 400;
    color: var(--text-3);
    font-size: 12px;
    margin-left: 4px;
}
.tb-mark-btn {
    font-size: 12px;
    color: var(--accent);
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 6px;
    transition: background .12s;
    white-space: nowrap;
    flex-shrink: 0;
}
.tb-mark-btn:hover { background: var(--accent-lite); }

/* ── NOTIF LIST ── */
.tb-notif-list {
    max-height: 280px;
    overflow-y: auto;
    overflow-x: hidden;
}

.tb-notif-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 11px 16px;
    border-bottom: 1px solid #f3f4f6;
    transition: background .12s;
}
.tb-notif-item:last-child { border-bottom: none; }
.tb-notif-item:hover { background: #fafafa; }

.tb-notif-icon {
    width: 32px; height: 32px;
    border-radius: 8px;
    background: var(--accent-lite);
    color: var(--accent);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    flex-shrink: 0;
    margin-top: 1px;
}

.tb-notif-body  { flex: 1; min-width: 0; }
.tb-notif-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-1);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.tb-notif-msg {
    font-size: 12px;
    color: var(--text-2);
    margin-top: 2px;
    line-height: 1.45;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.tb-notif-time {
    font-size: 11px;
    color: var(--text-3);
    margin-top: 4px;
}

.tb-notif-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    background: var(--accent);
    flex-shrink: 0;
    margin-top: 6px;
}

.tb-notif-empty {
    padding: 32px 16px;
    text-align: center;
    font-size: 13px;
    color: var(--text-3);
}
.tb-notif-empty i {
    font-size: 28px;
    display: block;
    margin-bottom: 8px;
    opacity: .4;
}

/* ── DROP FOOTER ── */
.tb-drop-footer {
    border-top: 1px solid var(--border);
    padding: 10px 16px;
}
.tb-drop-footer a {
    display: block;
    text-align: center;
    font-size: 12px;
    font-weight: 600;
    color: var(--accent);
    text-decoration: none;
    padding: 8px;
    border-radius: var(--r);
    background: var(--accent-lite);
    transition: background .12s;
    white-space: nowrap;
}
.tb-drop-footer a:hover { background: var(--accent-mid); }

/* ── SETTINGS: USER CARD ── */
.tb-set-user {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
}
.tb-set-avatar {
    width: 42px; height: 42px;
    border-radius: 50%;
    background: var(--accent-lite);
    color: var(--accent);
    font-size: 14px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border: 2px solid var(--accent-mid);
    overflow: hidden;
}
.tb-set-avatar img { width: 100%; height: 100%; object-fit: cover; }
.tb-set-name {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-1);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.tb-set-role {
    font-size: 11px;
    color: var(--text-3);
    text-transform: capitalize;
    margin-top: 2px;
}

/* ── SETTINGS: SECTION ── */
.tb-set-section { padding: 4px 0; border-bottom: 1px solid var(--border); }
.tb-set-section:last-child { border-bottom: none; }
.tb-set-label {
    padding: 8px 16px 3px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: var(--text-3);
}

/* ── SETTINGS: ITEM ── */
.tb-set-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 16px;
    cursor: pointer;
    transition: background .12s;
    text-decoration: none;
    color: var(--text-1);
    width: 100%;
    border: none;
    background: none;
    text-align: left;
}
.tb-set-item:hover { background: var(--surface); }

.tb-set-item-icon {
    width: 30px; height: 30px;
    border-radius: 8px;
    background: var(--surface);
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    color: var(--text-2);
    flex-shrink: 0;
}

.tb-set-item-text { flex: 1; min-width: 0; }
.tb-set-item-name {
    font-size: 13px;
    color: var(--text-1);
    font-weight: 500;
    white-space: nowrap;
}
.tb-set-item-sub  {
    font-size: 11px;
    color: var(--text-3);
    margin-top: 1px;
    white-space: nowrap;
}
.tb-set-chevron { font-size: 11px; color: var(--text-3); flex-shrink: 0; }

/* danger row */
.tb-set-item.danger .tb-set-item-name  { color: var(--danger); }
.tb-set-item.danger .tb-set-item-icon  {
    background: var(--danger-bg);
    border-color: var(--danger-bd);
    color: var(--danger);
}

/* ── TOGGLE ── */
.tb-toggle {
    width: 36px; height: 20px;
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
    width: 16px; height: 16px;
    border-radius: 50%;
    background: #fff;
    top: 2px; left: 2px;
    transition: transform .2s;
    box-shadow: 0 1px 3px rgba(0,0,0,.15);
}
.tb-toggle.on::after { transform: translateX(16px); }

/* ── MODALS ── */
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
    border-radius: var(--r-lg);
    width: 420px;
    max-width: 95vw;
    box-shadow: 0 20px 60px rgba(0,0,0,.2);
    overflow: hidden;
}

.tb-modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 18px;
    border-bottom: 1px solid var(--border);
}
.tb-modal-head h4 { font-size: 15px; font-weight: 700; color: var(--text-1); margin: 0; }
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
.tb-form-group input {
    width: 100%;
    height: 40px;
    border: 1px solid var(--border);
    border-radius: var(--r);
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
    border-radius: var(--r);
    border: 1px dashed var(--border);
    margin-bottom: 16px;
}
.tb-avatar-preview {
    width: 52px; height: 52px;
    border-radius: 50%;
    background: var(--accent-lite);
    color: var(--accent);
    font-size: 18px;
    font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
    border: 2px solid var(--accent-mid);
}
.tb-avatar-preview img { width: 100%; height: 100%; object-fit: cover; }
.tb-upload-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 12px;
    font-size: 12px;
    border-radius: var(--r);
    border: 1px solid var(--border);
    background: #fff;
    cursor: pointer;
    color: var(--text-2);
    transition: border-color .15s, color .15s;
}
.tb-upload-btn:hover { border-color: var(--accent); color: var(--accent); }
.tb-upload-hint { font-size: 11px; color: var(--text-3); margin-top: 3px; }

.tb-modal-foot {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 12px 18px;
    border-top: 1px solid var(--border);
    background: var(--surface);
}
.tb-btn-ghost {
    padding: 8px 16px;
    border-radius: var(--r);
    border: 1px solid var(--border);
    background: #fff;
    font-size: 13px;
    cursor: pointer;
    color: var(--text-2);
    transition: background .12s;
}
.tb-btn-ghost:hover { background: var(--border); }
.tb-btn-primary {
    padding: 8px 16px;
    border-radius: var(--r);
    border: none;
    background: var(--accent);
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background .12s;
}
.tb-btn-primary:hover { background: var(--accent-dark); }

.tb-alert {
    padding: 9px 12px;
    border-radius: var(--r);
    font-size: 12px;
    margin-bottom: 12px;
    display: none;
}
.tb-alert.show    { display: block; }
.tb-alert.success { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.tb-alert.error   { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
</style>

<!-- ============================================================
TOPBAR HTML
============================================================ -->
<header class="adhub-topbar">

    <div class="tb-logo">AdHub</div>

    <div class="tb-icons">

        <!-- NOTIFICATIONS -->
        <div class="tb-drop-wrap">
            <button class="tb-btn" id="tb-notif-btn" aria-label="Notifications">
                <i class="fa-regular fa-bell"></i>
                <span class="tb-badge <?= $unread_count > 0 ? 'show' : '' ?>" id="tb-notif-badge">
                    <?= $unread_count > 9 ? '9+' : $unread_count ?>
                </span>
            </button>

            <div class="tb-drop" id="tb-notif-drop">
                <div class="tb-drop-head">
                    <span class="tb-drop-head-title">
                        Notifications
                        <?php if ($unread_count > 0): ?>
                            <span>(<?= $unread_count ?> unread)</span>
                        <?php endif; ?>
                    </span>
                    <?php if ($unread_count > 0): ?>
                        <button class="tb-mark-btn" id="tb-mark-all">Mark all read</button>
                    <?php endif; ?>
                </div>

                <div class="tb-notif-list">
                    <?php if (count($notifications) > 0): ?>
                        <?php foreach ($notifications as $n): ?>
                        <div class="tb-notif-item">
                            <div class="tb-notif-icon">
                                <i class="fa-solid fa-circle-info"></i>
                            </div>
                            <div class="tb-notif-body">
                                <div class="tb-notif-title"><?= htmlspecialchars($n['title']) ?></div>
                                <div class="tb-notif-msg"><?= htmlspecialchars($n['message']) ?></div>
                                <div class="tb-notif-time"><?= date('M d, g:i A', strtotime($n['created_at'])) ?></div>
                            </div>
                            <?php if (!isset($_SESSION['notif_all_read'])): ?>
                                <div class="tb-notif-dot"></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="tb-notif-empty">
                            <i class="fa-regular fa-bell-slash"></i>
                            No notifications yet
                        </div>
                    <?php endif; ?>
                </div>

                <div class="tb-drop-footer">
                    <a href="/AdHub_V2/<?= $folder ?>/notifications/notifications.php">View all notifications</a>
                </div>
            </div>
        </div>

        <!-- SETTINGS -->
        <div class="tb-drop-wrap">
            <button class="tb-btn" id="tb-set-btn" aria-label="Settings">
                <i class="fa-solid fa-gear"></i>
            </button>

            <div class="tb-drop" id="tb-set-drop">

                <!-- user card -->
                <div class="tb-set-user">
                    <div class="tb-set-avatar" id="tb-set-avatar">
                        <?php if ($user_pic): ?>
                            <img src="/AdHub_V2/ajax/get_avatar.php?id=<?= $user_id ?>">
                        <?php else: ?>
                            <?= htmlspecialchars($initials) ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="tb-set-name"><?= htmlspecialchars($user_name) ?></div>
                        <div class="tb-set-role"><?= htmlspecialchars($user_role === 'staff' ? 'Staff' : 'Client') ?></div>
                    </div>
                </div>

                <!-- Account -->
                <div class="tb-set-section">
                    <div class="tb-set-label">Account</div>
                    <div class="tb-set-item" onclick="tbOpenModal('tb-profile-modal')">
                        <div class="tb-set-item-icon"><i class="fa-regular fa-user"></i></div>
                        <div class="tb-set-item-text">
                            <div class="tb-set-item-name">Edit profile</div>
                            <div class="tb-set-item-sub">Name, photo</div>
                        </div>
                        <i class="fa-solid fa-chevron-right tb-set-chevron"></i>
                    </div>
                    <div class="tb-set-item" onclick="tbOpenModal('tb-password-modal')">
                        <div class="tb-set-item-icon"><i class="fa-solid fa-lock"></i></div>
                        <div class="tb-set-item-text">
                            <div class="tb-set-item-name">Change password</div>
                        </div>
                        <i class="fa-solid fa-chevron-right tb-set-chevron"></i>
                    </div>
                </div>

                <!-- Preferences -->
                <div class="tb-set-section">
                    <div class="tb-set-label">Preferences</div>
                    <div class="tb-set-item" style="cursor:default;">
                        <div class="tb-set-item-icon"><i class="fa-regular fa-bell"></i></div>
                        <div class="tb-set-item-text">
                            <div class="tb-set-item-name">Email notifications</div>
                        </div>
                        <button class="tb-toggle on" id="tb-email-toggle"
                                onclick="tbTogglePref(this)"
                                aria-label="Toggle email notifications"></button>
                    </div>
                </div>

                <!-- Sign out -->
                <div class="tb-set-section">
                    <a class="tb-set-item danger"
                       href="/AdHub_V2/auth/logout.php"
                       onclick="return confirm('Sign out of AdHub?')">
                        <div class="tb-set-item-icon">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        </div>
                        <div class="tb-set-item-text">
                            <div class="tb-set-item-name">Sign out</div>
                        </div>
                    </a>
                </div>

            </div>
        </div>

        <!-- AVATAR -->
        <div class="tb-avatar" onclick="tbOpenModal('tb-profile-modal')" title="Edit profile" id="tb-topbar-avatar">
            <?php if ($user_pic): ?>
                <img src="<?= htmlspecialchars($user_pic) ?>" alt="avatar">
            <?php else: ?>
                <?= htmlspecialchars($initials) ?>
            <?php endif; ?>
        </div>

    </div>
</header>


<!-- ============================================================
MODAL: EDIT PROFILE
============================================================ -->
<div class="tb-modal-bg" id="tb-profile-modal">
    <div class="tb-modal">
        <div class="tb-modal-head">
            <h4>Edit profile</h4>
            <button class="tb-modal-close" onclick="tbCloseModal('tb-profile-modal')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="tb-modal-body">
            <div class="tb-alert" id="tb-profile-alert"></div>
            <div class="tb-avatar-upload">
                <div class="tb-avatar-preview" id="tb-avatar-preview">
                    <?php if ($user_pic): ?>
                        <img src="<?= htmlspecialchars($user_pic) ?>" alt="preview">
                    <?php else: ?>
                        <?= htmlspecialchars($initials) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="tb-avatar-file" class="tb-upload-btn">
                        <i class="fa-solid fa-upload"></i> Upload photo
                    </label>
                    <input type="file" id="tb-avatar-file" accept="image/*" style="display:none">
                    <div class="tb-upload-hint">JPG, PNG or GIF · max 2 MB</div>
                </div>
            </div>
            <div class="tb-form-group">
                <label for="tb-profile-name">Full name</label>
                <input type="text" id="tb-profile-name" value="<?= htmlspecialchars($user_name) ?>" placeholder="Your name">
            </div>
            <div class="tb-form-group">
                <label for="tb-profile-email">Email</label>
                <input type="email" id="tb-profile-email" value="<?= htmlspecialchars($_SESSION['email'] ?? '') ?>" placeholder="your@email.com">
            </div>
        </div>
        <div class="tb-modal-foot">
            <button class="tb-btn-ghost" onclick="tbCloseModal('tb-profile-modal')">Cancel</button>
            <button class="tb-btn-primary" onclick="tbSaveProfile()">Save changes</button>
        </div>
    </div>
</div>


<!-- ============================================================
MODAL: CHANGE PASSWORD
============================================================ -->
<div class="tb-modal-bg" id="tb-password-modal">
    <div class="tb-modal">
        <div class="tb-modal-head">
            <h4>Change password</h4>
            <button class="tb-modal-close" onclick="tbCloseModal('tb-password-modal')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="tb-modal-body">
            <div class="tb-alert" id="tb-password-alert"></div>
            <div class="tb-form-group">
                <label for="tb-curr-pass">Current password</label>
                <input type="password" id="tb-curr-pass" placeholder="••••••••" autocomplete="off">
            </div>
            <div class="tb-form-group">
                <label for="tb-new-pass">New password</label>
                <input type="password" id="tb-new-pass" placeholder="••••••••" autocomplete="off">
            </div>
            <div class="tb-form-group">
                <label for="tb-confirm-pass">Confirm new password</label>
                <input type="password" id="tb-confirm-pass" placeholder="••••••••" autocomplete="off">
            </div>
        </div>
        <div class="tb-modal-foot">
            <button class="tb-btn-ghost" onclick="tbCloseModal('tb-password-modal')">Cancel</button>
            <button class="tb-btn-primary" onclick="tbSavePassword()">Update password</button>
        </div>
    </div>
</div>


<!-- ============================================================
SCRIPTS
============================================================ -->
<script>
(function () {

    /* ── dropdown toggle ── */
    const drops = {
        'tb-notif-btn': 'tb-notif-drop',
        'tb-set-btn':   'tb-set-drop',
    };

    Object.entries(drops).forEach(([btnId, dropId]) => {
        document.getElementById(btnId).addEventListener('click', function (e) {
            e.stopPropagation();
            const drop   = document.getElementById(dropId);
            const isOpen = drop.classList.contains('open');
            closeAll();
            if (!isOpen) {
                drop.classList.add('open');
                this.classList.add('active');
            }
        });
    });

    document.addEventListener('click', closeAll);

    function closeAll() {
        document.querySelectorAll('.tb-drop').forEach(d => d.classList.remove('open'));
        document.querySelectorAll('.tb-btn').forEach(b => b.classList.remove('active'));
    }

    /* ── mark all read ── */
    const markBtn = document.getElementById('tb-mark-all');
    if (markBtn) {
        markBtn.addEventListener('click', function () {
        fetch('?mark_all_read=1')
            .then(r => r.json())
            .then(data => {
                document.querySelectorAll('.tb-notif-dot').forEach(d => d.remove());
                const badge = document.getElementById('tb-notif-badge');
                badge.classList.remove('show');
                badge.textContent = '0';
                this.style.display = 'none';
                document.querySelector('.tb-drop-head-title span') &&
                    (document.querySelector('.tb-drop-head-title span').style.display = 'none');
                if (data.reload) setTimeout(() => location.reload(), 600);
            });
        });
    }

    /* ── modals ── */
    window.tbOpenModal = function (id) {
        closeAll();
        document.getElementById(id).classList.add('open');
    };
    window.tbCloseModal = function (id) {
        document.getElementById(id).classList.remove('open');
        clearAlerts();
    };

    document.querySelectorAll('.tb-modal-bg').forEach(bg => {
        bg.addEventListener('click', function (e) {
            if (e.target === this) {
                this.classList.remove('open');
                clearAlerts();
            }
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
    document.getElementById('tb-avatar-file').addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        if (file.size > 2 * 1024 * 1024) {
            showAlert('tb-profile-alert', 'error', 'File too large. Max 2 MB.');
            return;
        }
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('tb-avatar-preview').innerHTML =
                '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">';
        };
        reader.readAsDataURL(file);
    });

    /* ── save profile ── */
    window.tbSaveProfile = function () {
        const name  = document.getElementById('tb-profile-name').value.trim();
        const email = document.getElementById('tb-profile-email').value.trim();
        const file  = document.getElementById('tb-avatar-file').files[0];

        if (!name)  { showAlert('tb-profile-alert', 'error', 'Name cannot be empty.');  return; }
        if (!email) { showAlert('tb-profile-alert', 'error', 'Email cannot be empty.'); return; }

        const fd = new FormData();
        fd.append('action', 'update_profile');
        fd.append('name',   name);
        fd.append('email',  email);
        if (file) fd.append('avatar', file);

        fetch('/AdHub_V2/ajax/user_settings.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert('tb-profile-alert', 'success', 'Profile updated successfully.');
                    tbUpdateAvatar(name, data.avatar_url ?? null);
                    setTimeout(() => {
                        tbCloseModal('tb-profile-modal');
                        if (data.reload) location.reload();
                    }, 1200);
                } else {
                    showAlert('tb-profile-alert', 'error', data.message ?? 'Something went wrong.');
                }
            })
            .catch(() => showAlert('tb-profile-alert', 'error', 'Network error. Please try again.'));
    };

    /* ── save password ── */
    window.tbSavePassword = function () {
        const curr    = document.getElementById('tb-curr-pass').value;
        const newPass = document.getElementById('tb-new-pass').value;
        const confirm = document.getElementById('tb-confirm-pass').value;

        if (!curr || !newPass || !confirm) {
            showAlert('tb-password-alert', 'error', 'Please fill in all fields.');
            return;
        }
        if (newPass.length < 8) {
            showAlert('tb-password-alert', 'error', 'New password must be at least 8 characters.');
            return;
        }
        if (newPass !== confirm) {
            showAlert('tb-password-alert', 'error', 'Passwords do not match.');
            return;
        }

        const fd = new FormData();
        fd.append('action',           'change_password');
        fd.append('current_password', curr);
        fd.append('new_password',     newPass);

        fetch('/AdHub_V2/ajax/user_settings.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert('tb-password-alert', 'success', 'Password updated.');
                    document.getElementById('tb-curr-pass').value    = '';
                    document.getElementById('tb-new-pass').value     = '';
                    document.getElementById('tb-confirm-pass').value = '';
                    setTimeout(() => {
                        tbCloseModal('tb-password-modal');
                        if (data.reload) location.reload();
                    }, 1200);
                } else {
                    showAlert('tb-password-alert', 'error', data.message ?? 'Incorrect current password.');
                }
            })
            .catch(() => showAlert('tb-password-alert', 'error', 'Network error. Please try again.'));
    };

    /* ── email toggle ── */
    window.tbTogglePref = function (btn) {
        btn.classList.toggle('on');
        const val = btn.classList.contains('on') ? 1 : 0;
        fetch('/AdHub_V2/ajax/user_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    'action=toggle_pref&pref=email_notif&value=' + val
        });
    };

    /* ── live avatar update after profile save ── */
    function tbUpdateAvatar(name, avatarUrl) {
        const initials = name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
        const targets  = ['tb-topbar-avatar', 'tb-set-avatar'];
        targets.forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.innerHTML = avatarUrl
                ? '<img src="' + avatarUrl + '" style="width:100%;height:100%;object-fit:cover;">'
                : initials;
        });
        document.querySelector('.tb-set-name') &&
            (document.querySelector('.tb-set-name').textContent = name);
    }

})();
</script>