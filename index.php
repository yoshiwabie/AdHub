<?php
session_start();

if (isset($_SESSION['user_id'])) {
    if($_SESSION['role'] == 'staff'){
        header("Location: admin/dashboard/dashboard.php");
        exit();
    }
    else if($_SESSION['role'] == 'client'){
        header("Location: client/dashboard/dashboard.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AdHub — Agency Campaign Manager</title>

<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

<style>
:root {
    --blue:       #1F3A93;
    --blue-dark:  #172d78;
    --blue-light: #2a4bbc;
    --teal:       #00B8A9;
    --teal-dark:  #009e91;
    --gray-bg:    #F4F4F9;
    --gray-text:  #2E2E2E;
    --gray-mid:   #6b7280;
    --white:      #ffffff;
    --border:     #dde0ec;
}

* { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }

body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    color: var(--gray-text);
    background: var(--white);
    font-size: 15px;
    line-height: 1.6;
}

/* ── NAVBAR ── */
.navbar {
    background: var(--blue);
    padding: 0 40px;
    height: 62px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 100;
}

.nav-brand {
    font-size: 20px;
    font-weight: 700;
    color: var(--white);
    text-decoration: none;
    letter-spacing: -0.01em;
}

.nav-brand span { color: var(--teal); }

.nav-links {
    display: flex;
    align-items: center;
    gap: 32px;
    list-style: none;
}

.nav-links a {
    color: rgba(255,255,255,0.8);
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: color 0.15s;
}

.nav-links a:hover { color: var(--white); }

/* ── HERO ── */
.hero {
    background: var(--blue);
    padding: 72px 40px 80px;
}

.hero-inner {
    max-width: 1160px;
    margin: auto;
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 64px;
    align-items: center;
}

.hero-tag {
    display: inline-block;
    background: rgba(0,184,169,0.15);
    color: var(--teal);
    border: 1px solid rgba(0,184,169,0.3);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    padding: 5px 12px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.hero-title {
    font-size: clamp(30px, 3.5vw, 44px);
    font-weight: 700;
    color: var(--white);
    line-height: 1.15;
    letter-spacing: -0.02em;
    margin-bottom: 18px;
}

.hero-title span { color: var(--teal); }

.hero-body {
    font-size: 15px;
    color: rgba(255,255,255,0.72);
    max-width: 480px;
    line-height: 1.75;
    margin-bottom: 36px;
}

.hero-bullets {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.hero-bullets li {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    color: rgba(255,255,255,0.8);
}

.hero-bullets li i {
    color: var(--teal);
    font-size: 16px;
    flex-shrink: 0;
}

/* ── LOGIN CARD ── */
.login-card {
    background: var(--white);
    border-radius: 12px;
    padding: 36px 32px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.18);
}

.login-card-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--gray-text);
    margin-bottom: 4px;
}

.login-card-sub {
    font-size: 13px;
    color: var(--gray-mid);
    margin-bottom: 24px;
}

.field-group { margin-bottom: 16px; }

.field-group label {
    display: block;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--gray-text);
    margin-bottom: 6px;
}

.field-group input {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 14px;
    color: var(--gray-text);
    background: var(--white);
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}

.field-group input::placeholder { color: #aab0c0; }

.field-group input:focus {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(31,58,147,0.1);
}

.pw-wrap { position: relative; }

.pw-wrap input { padding-right: 42px; }

.pw-toggle {
    position: absolute;
    right: 12px; top: 50%;
    transform: translateY(-50%);
    background: none; border: none;
    color: #aab0c0;
    cursor: pointer;
    font-size: 15px;
    padding: 0;
    transition: color 0.15s;
}

.pw-toggle:hover { color: var(--gray-text); }

.btn-login {
    width: 100%;
    background: var(--blue);
    color: var(--white);
    border: none;
    border-radius: 8px;
    padding: 11px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
    margin-top: 4px;
}

.btn-login:hover { background: var(--blue-light); }

.card-footer-text {
    text-align: center;
    margin-top: 16px;
    font-size: 13px;
    color: var(--gray-mid);
}

.card-footer-text a {
    color: var(--teal-dark);
    font-weight: 600;
    text-decoration: none;
}

.card-footer-text a:hover { text-decoration: underline; }

.alert-error {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #b91c1c;
    border-radius: 7px;
    padding: 10px 13px;
    font-size: 13px;
    margin-bottom: 16px;
}

/* ── STATS STRIP ── */
.stats-strip {
    background: var(--gray-bg);
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
    padding: 28px 40px;
}

.stats-inner {
    max-width: 1160px;
    margin: auto;
    display: flex;
    justify-content: space-around;
    flex-wrap: wrap;
    gap: 20px;
}

.stat-item { text-align: center; }

.stat-num {
    font-size: 26px;
    font-weight: 700;
    color: var(--blue);
    letter-spacing: -0.02em;
    line-height: 1;
}

.stat-label {
    font-size: 12px;
    color: var(--gray-mid);
    margin-top: 4px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

/* ── FEATURES ── */
.features-section {
    padding: 72px 40px;
    max-width: 1160px;
    margin: auto;
}

.section-header {
    margin-bottom: 44px;
}

.section-tag {
    display: inline-block;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--teal-dark);
    margin-bottom: 10px;
}

.section-title {
    font-size: clamp(22px, 2.5vw, 30px);
    font-weight: 700;
    color: var(--gray-text);
    letter-spacing: -0.02em;
    line-height: 1.2;
    margin-bottom: 10px;
}

.section-sub {
    font-size: 14.5px;
    color: var(--gray-mid);
    max-width: 480px;
    line-height: 1.7;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.feature-card {
    background: var(--white);
    border: 1.5px solid var(--border);
    border-radius: 10px;
    padding: 28px 26px;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.feature-card:hover {
    border-color: #b3c0e8;
    box-shadow: 0 4px 16px rgba(31,58,147,0.07);
}

.feature-icon {
    width: 42px; height: 42px;
    background: rgba(31,58,147,0.08);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--blue);
    font-size: 18px;
    margin-bottom: 16px;
}

.feature-name {
    font-size: 15px;
    font-weight: 700;
    color: var(--gray-text);
    margin-bottom: 8px;
}

.feature-desc {
    font-size: 13.5px;
    color: var(--gray-mid);
    line-height: 1.65;
}

/* ── HOW IT WORKS ── */
.how-section {
    background: var(--gray-bg);
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
    padding: 72px 40px;
}

.how-inner {
    max-width: 1160px;
    margin: auto;
}

.steps-list {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 24px;
    margin-top: 44px;
    counter-reset: steps;
}

.step-card {
    background: var(--white);
    border: 1.5px solid var(--border);
    border-radius: 10px;
    padding: 26px 22px;
}

.step-num {
    width: 34px; height: 34px;
    background: var(--blue);
    color: var(--white);
    border-radius: 50%;
    font-size: 14px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 14px;
}

.step-title {
    font-size: 14.5px;
    font-weight: 700;
    color: var(--gray-text);
    margin-bottom: 7px;
}

.step-text {
    font-size: 13px;
    color: var(--gray-mid);
    line-height: 1.65;
}

/* ── CTA ── */
.cta-section {
    padding: 72px 40px;
    max-width: 1160px;
    margin: auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 40px;
    flex-wrap: wrap;
}

.cta-text .cta-title {
    font-size: clamp(20px, 2.2vw, 26px);
    font-weight: 700;
    color: var(--gray-text);
    letter-spacing: -0.02em;
    margin-bottom: 8px;
}

.cta-text .cta-sub {
    font-size: 14px;
    color: var(--gray-mid);
}

.btn-cta {
    background: var(--teal);
    color: var(--white);
    border: none;
    border-radius: 8px;
    padding: 12px 30px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    white-space: nowrap;
    transition: background 0.15s;
    display: inline-block;
}

.btn-cta:hover { background: var(--teal-dark); color: var(--white); }

/* ── FOOTER ── */
footer {
    background: var(--blue);
    padding: 24px 40px;
}

.footer-inner {
    max-width: 1160px;
    margin: auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
}

.footer-brand {
    font-size: 16px;
    font-weight: 700;
    color: var(--white);
}

.footer-brand span { color: var(--teal); }

.footer-copy {
    font-size: 12.5px;
    color: rgba(255,255,255,0.55);
}

/* ── RESPONSIVE ── */
@media (max-width: 900px) {
    .navbar { padding: 0 20px; }
    .hero { padding: 48px 20px 56px; }
    .hero-inner { grid-template-columns: 1fr; gap: 36px; }
    .login-card { max-width: 440px; }
    .stats-strip, .features-section, .how-section, .cta-section { padding-left: 20px; padding-right: 20px; }
    .features-grid { grid-template-columns: 1fr 1fr; }
    .steps-list { grid-template-columns: 1fr 1fr; }
    footer { padding: 20px; }
}

@media (max-width: 560px) {
    .features-grid, .steps-list { grid-template-columns: 1fr; }
    .nav-links { display: none; }
}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <a href="#" class="nav-brand">Ad<span>Hub</span></a>
    <ul class="nav-links">
        <li><a href="#features">Features</a></li>
        <li><a href="#how-it-works">How It Works</a></li>
        <li><a href="auth/register.php">Sign Up</a></li>
    </ul>
</nav>

<!-- HERO -->
<section class="hero">
    <div class="hero-inner">

        <div>
            <span class="hero-tag">For Agencies &amp; Their Clients</span>
            <h1 class="hero-title">
                Manage campaigns.<br>
                Share progress.<br>
                <span>Stay aligned.</span>
            </h1>
            <p class="hero-body">
                AdHub gives digital marketing agencies a centralized workspace to share campaign updates, creative assets, and invoices directly with corporate clients.
            </p>
            <ul class="hero-bullets">
                <li><i class="bi bi-check-circle-fill"></i> Real-time campaign performance dashboards</li>
                <li><i class="bi bi-check-circle-fill"></i> Secure file and asset sharing with clients</li>
                <li><i class="bi bi-check-circle-fill"></i> Invoice tracking and billing transparency</li>
                <li><i class="bi bi-check-circle-fill"></i> Role-based access for staff and clients</li>
            </ul>
        </div>

        <!-- LOGIN CARD -->
        <div class="login-card">
            <p class="login-card-title">Sign In</p>
            <p class="login-card-sub">Access your AdHub workspace</p>

            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert-error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <form action="auth/login.php" method="POST">

                <div class="field-group">
                    <label for="email">Email Address</label>
                    <input type="email" name="email" id="email" placeholder="you@company.com" required>
                </div>

                <div class="field-group">
                    <label for="password">Password</label>
                    <div class="pw-wrap">
                        <input type="password" name="password" id="password" placeholder="••••••••" required>
                        <button type="button" class="pw-toggle" onclick="togglePw()">
                            <i class="bi bi-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" name="login" class="btn-login">Sign In</button>

            </form>

            <p class="card-footer-text">
                Don't have an account? <a href="auth/register.php">Sign up</a>
            </p>
        </div>

    </div>
</section>

<!-- STATS STRIP -->
<div class="stats-strip">
    <div class="stats-inner">
        <div class="stat-item">
            <div class="stat-num">500+</div>
            <div class="stat-label">Agencies Onboarded</div>
        </div>
        <div class="stat-item">
            <div class="stat-num">12,000+</div>
            <div class="stat-label">Campaigns Tracked</div>
        </div>
        <div class="stat-item">
            <div class="stat-num">98%</div>
            <div class="stat-label">Client Satisfaction</div>
        </div>
        <div class="stat-item">
            <div class="stat-num">2.4×</div>
            <div class="stat-label">Faster Reporting</div>
        </div>
    </div>
</div>

<!-- FEATURES -->
<section class="features-section" id="features">
    <div class="section-header">
        <span class="section-tag">Features</span>
        <h2 class="section-title">Everything you need in one place</h2>
        <p class="section-sub">From campaign kickoff to final invoice, AdHub keeps agencies and clients on the same page.</p>
    </div>

    <div class="features-grid">

        <div class="feature-card">
            <div class="feature-icon"><i class="bi bi-bar-chart-line"></i></div>
            <h3 class="feature-name">Analytics Dashboard</h3>
            <p class="feature-desc">Monitor campaign performance with real-time charts, KPI summaries, and exportable reports your clients can view directly.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon"><i class="bi bi-kanban"></i></div>
            <h3 class="feature-name">Campaign Workspace</h3>
            <p class="feature-desc">Organize tasks across teams with kanban boards. Keep deliverables on track and give clients clear visibility into progress.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon"><i class="bi bi-cloud-upload"></i></div>
            <h3 class="feature-name">Asset Management</h3>
            <p class="feature-desc">Upload and share creative files, ad copies, and media in a structured, secure library accessible to the right stakeholders.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon"><i class="bi bi-receipt"></i></div>
            <h3 class="feature-name">Invoice Sharing</h3>
            <p class="feature-desc">Send and track invoices directly within AdHub. Clients can view billing history and payment statuses without separate emails.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon"><i class="bi bi-people"></i></div>
            <h3 class="feature-name">Client Portal</h3>
            <p class="feature-desc">Give clients a dedicated, read-only view of their campaigns — reports, assets, and invoices in one clean interface.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon"><i class="bi bi-shield-lock"></i></div>
            <h3 class="feature-name">Role-Based Access</h3>
            <p class="feature-desc">Control exactly what each user can see and do. Staff and client roles ensure the right information reaches the right people.</p>
        </div>

    </div>
</section>

<!-- HOW IT WORKS -->
<section class="how-section" id="how-it-works">
    <div class="how-inner">
        <div class="section-header">
            <span class="section-tag">How It Works</span>
            <h2 class="section-title">Get your team running in four steps</h2>
            <p class="section-sub">AdHub is straightforward to set up and easy for both agency staff and clients to use from day one.</p>
        </div>

        <div class="steps-list">
            <div class="step-card">
                <div class="step-num">1</div>
                <h4 class="step-title">Create an Account</h4>
                <p class="step-text">Register your agency and set up your workspace with your branding and team structure.</p>
            </div>
            <div class="step-card">
                <div class="step-num">2</div>
                <h4 class="step-title">Add Your Team</h4>
                <p class="step-text">Invite staff members and assign roles so everyone has appropriate access to campaigns.</p>
            </div>
            <div class="step-card">
                <div class="step-num">3</div>
                <h4 class="step-title">Onboard Clients</h4>
                <p class="step-text">Send clients an invite to their portal where they can track campaign progress and assets.</p>
            </div>
            <div class="step-card">
                <div class="step-num">4</div>
                <h4 class="step-title">Manage & Report</h4>
                <p class="step-text">Run campaigns, share updates, upload assets, and send invoices — all from one dashboard.</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta-section">
    <div class="cta-text">
        <h2 class="cta-title">Ready to simplify client reporting?</h2>
        <p class="cta-sub">Join agencies already using AdHub to manage campaigns and stay aligned with their clients.</p>
    </div>
    <a href="auth/register.php" class="btn-cta">Get Started for Free</a>
</section>

<!-- FOOTER -->
<footer>
    <div class="footer-inner">
        <span class="footer-brand">Ad<span>Hub</span></span>
        <span class="footer-copy">© 2026 AdHub. All rights reserved.</span>
    </div>
</footer>

<script>
function togglePw() {
    const pw = document.getElementById('password');
    const ic = document.getElementById('eyeIcon');
    if (pw.type === 'password') {
        pw.type = 'text';
        ic.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        pw.type = 'password';
        ic.classList.replace('bi-eye-slash', 'bi-eye');
    }
}
</script>
</body>
</html>