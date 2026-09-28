<?php
require_once __DIR__ . '/config.php';

$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$message = '';
$error = '';
$tokenValid = false;

$pdo = getDbConnection();
$user = null;

if (!empty($token)) {
    $stmt = $pdo->prepare('SELECT user_id, email, reset_expires FROM users WHERE reset_token = :token LIMIT 1');
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch();

    if ($user && !empty($user['reset_expires'])) {
        if (strtotime($user['reset_expires']) >= time()) {
            $tokenValid = true;
        } else {
            $error = 'This password reset link has expired. Please request a new one.';
        }
    } else {
        $error = 'Invalid or used password reset tokennnnnnnn.';
    }
} else {
    $error = 'No reset token provided. Please request a password reset.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid && $user) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        $error = 'Security validation failed. Please refresh the page and try again.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (mb_strlen($password) < 6) {
            $error = 'New password must be at least 6 characters long.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match. Please re-enter.';
        } else {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $updateStmt = $pdo->prepare('UPDATE users SET password_hash = :hash, reset_token = NULL, reset_expires = NULL WHERE user_id = :id');
            $updated = $updateStmt->execute([
                ':hash' => $passwordHash,
                ':id'   => $user['user_id']
            ]);

            if ($updated) {
                setFlashMessage('success', 'Your password has been reset successfully! You can now sign in.');
                header('Location: login.php');
                exit;
            } else {
                $error = 'An error occurred while updating your password. Please try again.';
            }
        }
    }
}

$pageTitle = 'Reset Password';
include 'includes/header.php';
?>
<div class="auth-page">
    <div class="auth-art">
        <div class="auth-brand"><div class="brand-mark">C</div><strong>Campus Coin</strong></div>
        <div class="auth-copy">
            <p class="eyebrow">Set New Password</p>
            <h1>Secure your student account.</h1>
            <p>Choose a strong password to protect your income and expense data.</p>
        </div>
        <div class="auth-mini-card">
            <span>Password Protection</span>
            <strong>Bcrypt Hashed</strong>
            <small>Stored securely via password_hash()</small>
        </div>
    </div>

    <div class="auth-form-wrap">
        <div class="auth-form">
            <div class="mobile-brand auth-mobile"><div class="brand-mark">C</div><strong>Campus Coin</strong></div>
            <p class="eyebrow">Account Recovery</p>
            <h2>Create New Password</h2>
            <p class="muted">Enter your new credentials below.</p>

            <?php if ($error !== ''): ?>
                <div class="login-error" role="alert"><?php echo e($error); ?></div>
            <?php endif; ?>

            <?php if ($tokenValid && $user): ?>
                <form method="POST" action="reset-password.php" id="resetPasswordForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                    <input type="hidden" name="token" value="<?php echo e($token); ?>">

                    <label for="newPassword">
                        New Password
                        <input type="password" id="newPassword" name="password" placeholder="At least 6 characters" required minlength="6">
                    </label>

                    <label for="confirmNewPassword">
                        Confirm New Password
                        <input type="password" id="confirmNewPassword" name="confirm_password" placeholder="Re-enter new password" required minlength="6">
                    </label>

                    <button class="primary-btn full-btn" type="submit">Update Password</button>
                </form>
            <?php else: ?>
                <div style="margin-top:20px;">
                    <a href="forgot-password.php" class="primary-btn full-btn" style="text-align:center;display:block;">Request New Reset Link</a>
                </div>
            <?php endif; ?>

            <p class="auth-switch"><a href="login.php">Back to sign in</a></p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
