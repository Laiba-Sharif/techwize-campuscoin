<?php
require_once __DIR__ . '/config.php';

$message = '';
$resetLink = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        $error = 'Security validation failed. Please refresh the page and try again.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare('SELECT user_id, name, email, is_active FROM users WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if ($user && (int)$user['is_active'] === 1) {
                // Generate secure random 32-byte hex token valid for 1 hour
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $updateStmt = $pdo->prepare('UPDATE users SET reset_token = :token, reset_expires = :expires WHERE user_id = :id');
                $updateStmt->execute([
                    ':token'   => $token,
                    ':expires' => $expires,
                    ':id'      => $user['user_id']
                ]);

                // Construct tokenized reset link
                $resetLink = 'reset-password.php?token=' . urlencode($token);
                $message = 'A password reset token has been generated for ' . e($user['email']) . '.';
            } else {
                // User-safe generic response
                $message = 'If this email is registered in Campus Coin, a reset link has been dispatched.';
            }
        }
    }
}

$pageTitle = 'Forgot Password';
include 'includes/header.php';
?>
<div class="auth-page">
    <div class="auth-art">
        <div class="auth-brand"><div class="brand-mark">C</div><strong>Campus Coin</strong></div>
        <div class="auth-copy">
            <p class="eyebrow">Account Recovery</p>
            <h1>Reset your password easily.</h1>
            <p>Enter your student email address and we will provide a secure recovery link to reset your account password.</p>
        </div>
        <div class="auth-mini-card">
            <span>Security Note</span>
            <strong>Tokenized Verification</strong>
            <small>Compliant with SRS Section 1.6</small>
        </div>
    </div>

    <div class="auth-form-wrap">
        <div class="auth-form">
            <div class="mobile-brand auth-mobile"><div class="brand-mark">C</div><strong>Campus Coin</strong></div>
            <p class="eyebrow">Security</p>
            <h2>Forgot Password</h2>
            <p class="muted">Enter your registered email to reset your credentials.</p>

            <?php if ($message !== ''): ?>
                <div class="login-success" style="background:#eaf6ee;color:#1e7e34;padding:14px;border-radius:10px;margin-bottom:14px;font-size:13px;" role="status">
                    <p style="margin-bottom:6px;"><?php echo e($message); ?></p>
                    <?php if (!empty($resetLink)): ?>
                        <div style="margin-top:10px;padding-top:10px;border-top:1px dashed #b5dfc3;">
                            <strong>Local Testing Simulation:</strong><br>
                            <a href="<?php echo e($resetLink); ?>" class="primary-btn" style="display:inline-block;margin-top:6px;padding:6px 14px;font-size:12px;">Click here to Reset Password →</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="login-error" role="alert"><?php echo e($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="forgot-password.php" id="forgotPasswordForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                
                <label for="resetEmail">
                    Registered Email address
                    <input type="email" id="resetEmail" name="email" placeholder="student@campuscoin.com" required>
                </label>

                <button class="primary-btn full-btn" type="submit">Send Reset Link</button>
            </form>

            <p class="auth-switch">Remember your password? <a href="login.php">Back to sign in</a></p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
