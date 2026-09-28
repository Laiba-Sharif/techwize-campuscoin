<?php
require_once __DIR__ . '/config.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$email = '';
$flashSuccess = getFlashMessage('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. CSRF Token Validation
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        $error = 'Security validation failed. Please refresh the page and try again.';
    } else {
        // 2. Extract credentials
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter both your email address and password.';
        } else {
            // 3. PDO prepared statement querying user by email
            $pdo = getDbConnection();
            $stmt = $pdo->prepare('SELECT user_id, name, email, password_hash, role, is_active FROM users WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            // 4. Verify password with bcrypt
            if (!$user || !password_verify($password, $user['password_hash'])) {
                $error = 'Invalid email or password. Please check your credentials.';
            } elseif ((int)$user['is_active'] !== 1) {
                // Check if admin has disabled the account (SRS 1.6 Page 9)
                $error = 'Your account has been deactivated. Please contact the campus administrator.';
            } else {
                // 5. Authentication successful - regenerate session ID to prevent fixation
                session_regenerate_id(true);

                $_SESSION['campus_coin_logged_in'] = true;
                $_SESSION['user_id'] = (int)$user['user_id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                if ($user['role'] === 'admin') {
                    $_SESSION['campus_coin_admin'] = true;
                    $_SESSION['campus_coin_admin_name'] = $user['name'];
                }

                header('Location: dashboard.php');
                exit;
            }
        }
    }
}

$pageTitle = 'Login';
include 'includes/header.php';
?>
<div class="auth-page">
    <div class="auth-art">
        <div class="auth-brand"><div class="brand-mark">C</div><strong>Campus Coin</strong></div>
        <div class="auth-copy">
            <p class="eyebrow">Student finance made simple</p>
            <h1>Know your money. Plan your month.</h1>
            <p>Keep your allowance, expenses and savings goals in one calm, simple space.</p>
        </div>
        <div class="auth-mini-card">
            <span>Security Standard</span>
            <strong>PDO Prepared Statements</strong>
            <small>Protected against SQL Injection & XSS</small>
        </div>
    </div>

    <div class="auth-form-wrap">
        <div class="auth-form">
            <div class="mobile-brand auth-mobile"><div class="brand-mark">C</div><strong>Campus Coin</strong></div>
            <p class="eyebrow">Welcome back</p>
            <h2>Sign in to your account</h2>
            <p class="muted">Enter your student credentials to continue.</p>

            <?php if (!empty($flashSuccess)): ?>
                <div class="login-success" style="background:#eaf6ee;color:#1e7e34;padding:12px 14px;border-radius:9px;margin-bottom:14px;font-size:14px;" role="status">
                    <?php echo e($flashSuccess); ?>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="login-error" role="alert"><?php echo e($error); ?></div>
            <?php endif; ?>

            <div class="demo-login-box">
                <strong>Default Seed Accounts (Active in MySQL)</strong>
                <span>Student: student@campuscoin.com | Pass: 123456</span>
                <span>Admin: admin@campuscoin.com | Pass: admin123</span>
            </div>

            <form method="POST" action="login.php" novalidate>
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">

                <label for="loginEmail">
                    Email address
                    <input type="email" id="loginEmail" name="email" placeholder="you@example.com" value="<?php echo e($email); ?>" required>
                </label>

                <label for="loginPassword">
                    Password
                    <input type="password" id="loginPassword" name="password" placeholder="Enter password" required>
                </label>

                <div class="form-row-between">
                    <label class="check-label" for="loginRemember">
                        <input type="checkbox" id="loginRemember" name="remember" value="1">
                        Remember me
                    </label>
                    <a href="forgot-password.php">Forgot password?</a>
                </div>

                <button class="primary-btn full-btn" type="submit">Sign in</button>
            </form>

            <p class="auth-switch">Don't have an account? <a href="signup.php">Create one</a></p>
            <p class="auth-switch"><a href="admin/login.php">Admin portal sign in →</a></p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
