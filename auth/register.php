<head>
    <link rel="icon" type="image/png" href="/AdHub_V2/assets/adHub_LOGO.png">
</head>
<?php
include('../config/db.php');

if(isset($_POST['register'])){

    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $role     = $_POST['role'] ?? '';

    // =========================
    // SERVER-SIDE VALIDATION
    // =========================

    if(empty($name) || empty($email) || empty($password) || empty($confirm) || empty($role)){
        $error = "All fields are required.";
    }
    elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $error = "Please enter a valid email address.";
    }
    elseif(strlen($password) < 8){
        $error = "Password must be at least 8 characters.";
    }
    elseif($password !== $confirm){
        $error = "Passwords do not match.";
    }
    elseif(!in_array($role, ['client', 'staff'])){
        $error = "Invalid role selected.";
    }
    else {

        // prevent duplicate email
        $check = mysqli_query($conn, "SELECT user_id FROM users WHERE email = '$email' LIMIT 1");

        if(mysqli_num_rows($check) > 0){
            $error = "An account with that email already exists.";
        }
        else {

            $hashed = password_hash($password, PASSWORD_DEFAULT);

            $query  = "INSERT INTO users (name, email, password, role)
                       VALUES ('$name', '$email', '$hashed', '$role')";

            if(mysqli_query($conn, $query)){
                header("Location: ../index.php");
                exit();
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<?php include('../includes/header.php'); ?>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --blue:      #1F3A93;
    --blue-dark: #172d78;
    --teal:      #00B8A9;
    --teal-dark: #009e91;
    --gray-bg:   #F4F4F9;
    --gray-text: #2E2E2E;
    --gray-mid:  #6b7280;
    --border:    #dde0ec;
    --white:     #ffffff;
}

body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    background: var(--gray-bg);
    color: var(--gray-text);
    min-height: 100vh;
}

.register-wrap {
    min-height: calc(100vh - 62px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 48px 20px;
}

.register-card {
    background: var(--white);
    border: 1.5px solid var(--border);
    border-radius: 12px;
    padding: 40px 36px;
    width: 100%;
    max-width: 460px;
    box-shadow: 0 4px 20px rgba(31,58,147,0.07);
}

.card-brand {
    font-size: 18px;
    font-weight: 700;
    color: var(--blue);
    margin-bottom: 6px;
}

.card-brand span { color: var(--teal); }

.card-title {
    font-size: 20px;
    font-weight: 700;
    color: var(--gray-text);
    letter-spacing: -0.02em;
    margin-bottom: 4px;
}

.card-sub {
    font-size: 13px;
    color: var(--gray-mid);
    margin-bottom: 28px;
}

.divider {
    height: 1px;
    background: var(--border);
    margin-bottom: 24px;
}

/* Fields */
.field-group {
    margin-bottom: 16px;
}

.field-group label {
    display: block;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--gray-text);
    margin-bottom: 6px;
}

.field-group input,
.field-group select {
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
    appearance: none;
}

.field-group input::placeholder { color: #aab0c0; }

.field-group input:focus,
.field-group select:focus {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(31,58,147,0.1);
}

.select-wrap {
    position: relative;
}

.select-wrap::after {
    content: '\F282';
    font-family: 'Bootstrap-icons';
    position: absolute;
    right: 14px; top: 50%;
    transform: translateY(-50%);
    color: var(--gray-mid);
    font-size: 13px;
    pointer-events: none;
}

/* Password row — two fields side by side */
.pw-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.pw-wrap {
    position: relative;
}

.pw-wrap input { padding-right: 40px; }

.pw-toggle {
    position: absolute;
    right: 12px; top: 50%;
    transform: translateY(-50%);
    background: none; border: none;
    color: #aab0c0; cursor: pointer;
    font-size: 15px; padding: 0;
    transition: color 0.15s;
}

.pw-toggle:hover { color: var(--gray-text); }

/* Strength bar */
.strength-bar {
    display: flex;
    gap: 4px;
    margin-top: 7px;
}

.strength-bar span {
    flex: 1;
    height: 3px;
    border-radius: 2px;
    background: var(--border);
    transition: background 0.25s;
}

.strength-label {
    font-size: 11px;
    color: var(--gray-mid);
    margin-top: 4px;
    min-height: 14px;
}

/* Error / hint */
.alert-error {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #b91c1c;
    border-radius: 7px;
    padding: 10px 13px;
    font-size: 13px;
    margin-bottom: 18px;
    display: flex;
    align-items: flex-start;
    gap: 8px;
}

.alert-error i { flex-shrink: 0; margin-top: 1px; }

.match-hint {
    font-size: 11.5px;
    margin-top: 5px;
    min-height: 14px;
}

/* Submit */
.btn-register {
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
    margin-top: 6px;
}

.btn-register:hover { background: var(--blue-dark); }

.login-link {
    text-align: center;
    margin-top: 18px;
    font-size: 13px;
    color: var(--gray-mid);
}

.login-link a {
    color: var(--teal-dark);
    font-weight: 600;
    text-decoration: none;
}

.login-link a:hover { text-decoration: underline; }

@media (max-width: 480px) {
    .register-card { padding: 28px 20px; }
    .pw-row { grid-template-columns: 1fr; }
}
</style>

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

<div class="register-wrap">
    <div class="register-card">

        <p class="card-brand">Ad<span>Hub</span></p>
        <h1 class="card-title">Create an Account</h1>
        <p class="card-sub">Fill in the details below to get started.</p>

        <div class="divider"></div>

        <?php if(isset($error)): ?>
            <div class="alert-error">
                <i class="bi bi-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" novalidate>

            <!-- Name -->
            <div class="field-group">
                <label for="name">Full Name</label>
                <input
                    type="text"
                    name="name"
                    id="name"
                    placeholder="e.g. Jane Santos"
                    value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>"
                    required
                >
            </div>

            <!-- Email -->
            <div class="field-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    name="email"
                    id="email"
                    placeholder="you@company.com"
                    value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                    required
                >
            </div>

            <!-- Role -->
            <div class="field-group">
                <label for="role">Role</label>
                <div class="select-wrap">
                    <select name="role" id="role" required>
                        <option value="" disabled <?= !isset($_POST['role']) ? 'selected' : '' ?>>Select a role</option>
                        <option value="client" <?= (isset($_POST['role']) && $_POST['role'] === 'client') ? 'selected' : '' ?>>Client</option>
                        <option value="staff"  <?= (isset($_POST['role']) && $_POST['role'] === 'staff')  ? 'selected' : '' ?>>Staff</option>
                    </select>
                </div>
            </div>

            <!-- Password row -->
            <div class="pw-row">

                <div class="field-group">
                    <label for="password">Password</label>
                    <div class="pw-wrap">
                        <input
                            type="password"
                            name="password"
                            id="password"
                            placeholder="••••••••"
                            oninput="checkStrength(this.value)"
                            required
                        >
                        <button type="button" class="pw-toggle" onclick="togglePw('password','eye1')">
                            <i class="bi bi-eye" id="eye1"></i>
                        </button>
                    </div>
                    <div class="strength-bar">
                        <span id="s1"></span>
                        <span id="s2"></span>
                        <span id="s3"></span>
                        <span id="s4"></span>
                    </div>
                    <div class="strength-label" id="strengthLabel"></div>
                </div>

                <div class="field-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="pw-wrap">
                        <input
                            type="password"
                            name="confirm_password"
                            id="confirm_password"
                            placeholder="••••••••"
                            oninput="checkMatch()"
                            required
                        >
                        <button type="button" class="pw-toggle" onclick="togglePw('confirm_password','eye2')">
                            <i class="bi bi-eye" id="eye2"></i>
                        </button>
                    </div>
                    <div class="match-hint" id="matchHint"></div>
                </div>

            </div>

            <button type="submit" name="register" class="btn-register">Create Account</button>

        </form>

        <p class="login-link">
            Already have an account? <a href="/AdHub_V2/index.php">Sign in</a>
        </p>

    </div>
</div>

<script>
function togglePw(id, iconId) {
    const input = document.getElementById(id);
    const icon  = document.getElementById(iconId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}

function checkStrength(val) {
    const bars   = [s1, s2, s3, s4];
    const label  = document.getElementById('strengthLabel');
    const colors = ['#ef4444', '#f97316', '#eab308', '#00B8A9'];
    const labels = ['Weak', 'Fair', 'Good', 'Strong'];

    let score = 0;
    if (val.length >= 8)              score++;
    if (/[A-Z]/.test(val))            score++;
    if (/[0-9]/.test(val))            score++;
    if (/[^A-Za-z0-9]/.test(val))     score++;

    bars.forEach((b, i) => {
        b.style.background = i < score ? colors[score - 1] : 'var(--border)';
    });

    label.textContent = val.length ? labels[score - 1] || '' : '';
    label.style.color = val.length ? colors[score - 1] : 'var(--gray-mid)';
    checkMatch();
}

function checkMatch() {
    const pw   = document.getElementById('password').value;
    const conf = document.getElementById('confirm_password').value;
    const hint = document.getElementById('matchHint');
    if (!conf) { hint.textContent = ''; return; }
    if (pw === conf) {
        hint.textContent = '✓ Passwords match';
        hint.style.color = '#00B8A9';
    } else {
        hint.textContent = '✗ Passwords do not match';
        hint.style.color = '#ef4444';
    }
}
</script>

<?php include('../includes/footer.php'); ?>