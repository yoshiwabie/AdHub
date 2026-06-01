
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: var(--font-sans); }

.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 20px;
  height: 56px;
  background: var(--color-background-primary);
  border-bottom: 0.5px solid var(--color-border-tertiary);
}
.logo { font-size: 17px; font-weight: 500; color: var(--color-text-primary); letter-spacing: -0.3px; }
.topbar-right { display: flex; align-items: center; gap: 4px; }

.tb-btn {
  background: transparent;
  border: none;
  width: 36px;
  height: 36px;
  border-radius: var(--border-radius-md);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  color: var(--color-text-secondary);
  font-size: 18px;
  transition: background 0.15s, color 0.15s;
  position: relative;
}
.tb-btn:hover { background: var(--color-background-secondary); color: var(--color-text-primary); }
.tb-btn.active { background: var(--color-background-secondary); color: var(--color-text-primary); }

.badge {
  position: absolute;
  top: 5px; right: 5px;
  width: 8px; height: 8px;
  border-radius: 50%;
  background: #E24B4A;
  border: 2px solid var(--color-background-primary);
}

.avatar {
  width: 32px; height: 32px;
  border-radius: 50%;
  background: var(--color-background-info);
  color: var(--color-text-info);
  font-size: 12px;
  font-weight: 500;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  border: 0.5px solid var(--color-border-tertiary);
  margin-left: 4px;
}

.dropdown-wrap { position: relative; }

.dropdown {
  position: absolute;
  right: 0;
  top: calc(100% + 8px);
  width: 300px;
  background: var(--color-background-primary);
  border: 0.5px solid var(--color-border-secondary);
  border-radius: var(--border-radius-lg);
  z-index: 100;
  display: none;
}
.dropdown.open { display: block; }

.drop-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 14px 10px;
  border-bottom: 0.5px solid var(--color-border-tertiary);
}
.drop-title { font-size: 13px; font-weight: 500; color: var(--color-text-primary); }
.drop-action {
  font-size: 12px;
  color: var(--color-text-info);
  background: none;
  border: none;
  cursor: pointer;
  padding: 2px 6px;
  border-radius: var(--border-radius-md);
}
.drop-action:hover { background: var(--color-background-info); }

.notif-list { max-height: 280px; overflow-y: auto; }

.notif-item {
  display: flex;
  gap: 10px;
  padding: 11px 14px;
  border-bottom: 0.5px solid var(--color-border-tertiary);
  cursor: pointer;
  transition: background 0.12s;
}
.notif-item:last-child { border-bottom: none; }
.notif-item:hover { background: var(--color-background-secondary); }
.notif-item.unread .notif-dot { opacity: 1; }
.notif-dot {
  width: 7px; height: 7px;
  border-radius: 50%;
  background: #378ADD;
  flex-shrink: 0;
  margin-top: 5px;
  opacity: 0;
}
.notif-content { flex: 1; min-width: 0; }
.notif-title-text { font-size: 13px; font-weight: 500; color: var(--color-text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.notif-msg { font-size: 12px; color: var(--color-text-secondary); margin-top: 2px; line-height: 1.4; }
.notif-time { font-size: 11px; color: var(--color-text-tertiary); margin-top: 4px; }

.notif-icon {
  width: 30px; height: 30px; border-radius: var(--border-radius-md);
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; flex-shrink: 0;
}
.ni-blue { background: var(--color-background-info); color: var(--color-text-info); }
.ni-green { background: var(--color-background-success); color: var(--color-text-success); }
.ni-amber { background: var(--color-background-warning); color: var(--color-text-warning); }
.ni-red { background: var(--color-background-danger); color: var(--color-text-danger); }

.drop-footer {
  padding: 10px 14px;
  border-top: 0.5px solid var(--color-border-tertiary);
}
.drop-footer button {
  width: 100%;
  padding: 7px;
  font-size: 12px;
  border-radius: var(--border-radius-md);
  background: var(--color-background-secondary);
  border: 0.5px solid var(--color-border-tertiary);
  color: var(--color-text-secondary);
  cursor: pointer;
  transition: background 0.12s;
}
.drop-footer button:hover { background: var(--color-background-tertiary); }

.settings-dropdown { width: 260px; }
.settings-section { padding: 6px 0; border-bottom: 0.5px solid var(--color-border-tertiary); }
.settings-section:last-child { border-bottom: none; }
.section-label {
  padding: 6px 14px 4px;
  font-size: 10px;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--color-text-tertiary);
}
.settings-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 14px;
  cursor: pointer;
  transition: background 0.12s;
  border-radius: 0;
}
.settings-item:hover { background: var(--color-background-secondary); }
.settings-item-icon {
  width: 28px; height: 28px;
  border-radius: var(--border-radius-md);
  background: var(--color-background-secondary);
  display: flex; align-items: center; justify-content: center;
  font-size: 14px;
  color: var(--color-text-secondary);
  flex-shrink: 0;
}
.settings-item-body { flex: 1; min-width: 0; }
.settings-item-label { font-size: 13px; color: var(--color-text-primary); }
.settings-item-desc { font-size: 11px; color: var(--color-text-tertiary); margin-top: 1px; }
.settings-item-right { font-size: 12px; color: var(--color-text-tertiary); }

.toggle-wrap { display: flex; align-items: center; }
.toggle {
  width: 32px; height: 18px;
  border-radius: 9px;
  background: var(--color-border-secondary);
  position: relative;
  cursor: pointer;
  transition: background 0.15s;
  flex-shrink: 0;
  border: none;
}
.toggle.on { background: #378ADD; }
.toggle::after {
  content: '';
  position: absolute;
  width: 14px; height: 14px;
  border-radius: 50%;
  background: var(--color-background-primary);
  top: 2px; left: 2px;
  transition: transform 0.15s;
}
.toggle.on::after { transform: translateX(14px); }

.danger-item .settings-item-label { color: var(--color-text-danger); }
.danger-item .settings-item-icon { background: var(--color-background-danger); color: var(--color-text-danger); }

.empty-state {
  padding: 24px 14px;
  text-align: center;
  font-size: 13px;
  color: var(--color-text-tertiary);
}
.empty-state i { font-size: 24px; display: block; margin-bottom: 8px; opacity: 0.4; }

.demo-controls {
  display: flex; gap: 8px; flex-wrap: wrap;
  padding: 0 0 16px;
  border-bottom: 0.5px solid var(--color-border-tertiary);
  margin-bottom: 16px;
}
.demo-btn {
  font-size: 12px;
  padding: 5px 12px;
  border-radius: var(--border-radius-md);
  border: 0.5px solid var(--color-border-secondary);
  background: var(--color-background-secondary);
  color: var(--color-text-secondary);
  cursor: pointer;
}
.demo-btn:hover { background: var(--color-background-tertiary); }
.demo-label { font-size: 12px; color: var(--color-text-tertiary); padding: 6px 0; align-self: center; }
</style>

<div style="padding: 1rem 0;">
  <div class="demo-controls">
    <span class="demo-label">Try it:</span>
    <button class="demo-btn" onclick="openDrop('notif')">Open notifications</button>
    <button class="demo-btn" onclick="openDrop('settings')">Open settings</button>
  </div>

  <div style="background: var(--color-background-secondary); border-radius: var(--border-radius-lg); padding: 6px; border: 0.5px solid var(--color-border-tertiary); position: relative; min-height: 360px;">

    <div class="topbar">
      <div class="logo">AdHub</div>
      <div class="topbar-right">

        <div class="dropdown-wrap" id="notif-wrap">
          <button class="tb-btn" id="notif-btn" onclick="toggle('notif')" aria-label="Notifications">
            <i class="ti ti-bell" aria-hidden="true"></i>
            <span class="badge" id="notif-badge"></span>
          </button>
          <div class="dropdown open" id="notif-drop" style="display:none;">
            <div class="drop-header">
              <span class="drop-title">Notifications</span>
              <button class="drop-action" onclick="markAllRead()">Mark all read</button>
            </div>
            <div class="notif-list" id="notif-list"></div>
            <div class="drop-footer">
              <button onclick="alert('View all notifications')">View all notifications</button>
            </div>
          </div>
        </div>

        <div class="dropdown-wrap" id="settings-wrap">
          <button class="tb-btn" id="settings-btn" onclick="toggle('settings')" aria-label="Settings">
            <i class="ti ti-settings" aria-hidden="true"></i>
          </button>
          <div class="dropdown settings-dropdown" id="settings-drop" style="display:none;">
            <div class="drop-header">
              <span class="drop-title">Settings</span>
            </div>

            <div class="settings-section">
              <div class="section-label">Account</div>
              <div class="settings-item" onclick="nav('Profile')">
                <div class="settings-item-icon"><i class="ti ti-user" aria-hidden="true"></i></div>
                <div class="settings-item-body">
                  <div class="settings-item-label">Profile</div>
                  <div class="settings-item-desc">Name, photo, bio</div>
                </div>
                <i class="ti ti-chevron-right settings-item-right" aria-hidden="true"></i>
              </div>
              <div class="settings-item" onclick="nav('Security')">
                <div class="settings-item-icon"><i class="ti ti-lock" aria-hidden="true"></i></div>
                <div class="settings-item-body">
                  <div class="settings-item-label">Security</div>
                  <div class="settings-item-desc">Password, 2FA</div>
                </div>
                <i class="ti ti-chevron-right settings-item-right" aria-hidden="true"></i>
              </div>
            </div>

            <div class="settings-section">
              <div class="section-label">Preferences</div>
              <div class="settings-item">
                <div class="settings-item-icon"><i class="ti ti-bell" aria-hidden="true"></i></div>
                <div class="settings-item-body">
                  <div class="settings-item-label">Email alerts</div>
                </div>
                <div class="toggle-wrap">
                  <button class="toggle on" id="toggle-email" onclick="toggleSwitch('toggle-email')" aria-label="Toggle email alerts"></button>
                </div>
              </div>
              <div class="settings-item">
                <div class="settings-item-icon"><i class="ti ti-moon" aria-hidden="true"></i></div>
                <div class="settings-item-body">
                  <div class="settings-item-label">Dark mode</div>
                </div>
                <div class="toggle-wrap">
                  <button class="toggle" id="toggle-dark" onclick="toggleSwitch('toggle-dark')" aria-label="Toggle dark mode"></button>
                </div>
              </div>
              <div class="settings-item" onclick="nav('Language')">
                <div class="settings-item-icon"><i class="ti ti-language" aria-hidden="true"></i></div>
                <div class="settings-item-body">
                  <div class="settings-item-label">Language</div>
                </div>
                <span class="settings-item-right">English</span>
              </div>
            </div>

            <div class="settings-section">
              <div class="section-label">Support</div>
              <div class="settings-item" onclick="nav('Help')">
                <div class="settings-item-icon"><i class="ti ti-help" aria-hidden="true"></i></div>
                <div class="settings-item-body">
                  <div class="settings-item-label">Help & docs</div>
                </div>
                <i class="ti ti-chevron-right settings-item-right" aria-hidden="true"></i>
              </div>
            </div>

            <div class="settings-section">
              <div class="danger-item settings-item" onclick="nav('Logout')">
                <div class="settings-item-icon"><i class="ti ti-logout" aria-hidden="true"></i></div>
                <div class="settings-item-body">
                  <div class="settings-item-label">Sign out</div>
                </div>
              </div>
            </div>

          </div>
        </div>

        <div class="avatar" onclick="toggle('settings')" title="Your profile">JS</div>

      </div>
    </div>

  </div>
</div>

<script>
const notifs = [
  { id:1, unread:true,  icon:'ti-file-invoice', iconClass:'ni-blue',  title:'New campaign submitted', msg:'Client "BrandCo" submitted a new ad for review.', time:'2 min ago' },
  { id:2, unread:true,  icon:'ti-circle-check', iconClass:'ni-green', title:'Ad approved', msg:'Your creative for "Summer Sale" was approved.', time:'1 hr ago' },
  { id:3, unread:false, icon:'ti-alert-triangle',iconClass:'ni-amber', title:'Budget threshold reached', msg:'Campaign "App Install Q3" has used 80% of its budget.', time:'3 hrs ago' },
  { id:4, unread:false, icon:'ti-user-plus', iconClass:'ni-blue', title:'New client registered', msg:'JohnDoe Apparel just created an account.', time:'Yesterday' },
];

function renderNotifs() {
  const list = document.getElementById('notif-list');
  const unreadCount = notifs.filter(n => n.unread).length;
  document.getElementById('notif-badge').style.display = unreadCount ? 'block' : 'none';
  if (!notifs.length) {
    list.innerHTML = '<div class="empty-state"><i class="ti ti-bell-off"></i>No notifications yet</div>';
    return;
  }
  list.innerHTML = notifs.map(n => `
    <div class="notif-item ${n.unread ? 'unread' : ''}" onclick="readNotif(${n.id})">
      <div class="notif-icon ${n.iconClass}"><i class="ti ${n.icon}" aria-hidden="true"></i></div>
      <div class="notif-content">
        <div class="notif-title-text">${n.title}</div>
        <div class="notif-msg">${n.msg}</div>
        <div class="notif-time">${n.time}</div>
      </div>
      <div class="notif-dot"></div>
    </div>`).join('');
}

function readNotif(id) {
  const n = notifs.find(x => x.id === id);
  if (n) n.unread = false;
  renderNotifs();
}

function markAllRead() {
  notifs.forEach(n => n.unread = false);
  renderNotifs();
}

function toggle(which) {
  const notifDrop = document.getElementById('notif-drop');
  const settingsDrop = document.getElementById('settings-drop');
  const notifBtn = document.getElementById('notif-btn');
  const settingsBtn = document.getElementById('settings-btn');
  if (which === 'notif') {
    const isOpen = notifDrop.style.display !== 'none';
    notifDrop.style.display = isOpen ? 'none' : 'block';
    settingsDrop.style.display = 'none';
    notifBtn.classList.toggle('active', !isOpen);
    settingsBtn.classList.remove('active');
  } else {
    const isOpen = settingsDrop.style.display !== 'none';
    settingsDrop.style.display = isOpen ? 'none' : 'block';
    notifDrop.style.display = 'none';
    settingsBtn.classList.toggle('active', !isOpen);
    notifBtn.classList.remove('active');
  }
}

function openDrop(which) { 
  const notifDrop = document.getElementById('notif-drop');
  const settingsDrop = document.getElementById('settings-drop');
  notifDrop.style.display = 'none';
  settingsDrop.style.display = 'none';
  if (which === 'notif') notifDrop.style.display = 'block';
  else settingsDrop.style.display = 'block';
}

function toggleSwitch(id) {
  document.getElementById(id).classList.toggle('on');
}

function nav(page) { alert('Navigate to: ' + page); }

document.addEventListener('click', function(e) {
  const nw = document.getElementById('notif-wrap');
  const sw = document.getElementById('settings-wrap');
  const av = document.querySelector('.avatar');
  if (!nw.contains(e.target)) {
    document.getElementById('notif-drop').style.display = 'none';
    document.getElementById('notif-btn').classList.remove('active');
  }
  if (!sw.contains(e.target) && !av.contains(e.target)) {
    document.getElementById('settings-drop').style.display = 'none';
    document.getElementById('settings-btn').classList.remove('active');
  }
});

renderNotifs();
</script>