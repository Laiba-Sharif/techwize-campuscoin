<?php
require_once __DIR__ . '/../config.php';

if (isAdmin()) {
    header('Location: index.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        $error = 'Security validation failed. Please refresh and try again.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter both your administrator email and password.';
        } else {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare('SELECT user_id, name, email, password_hash, role, is_active FROM users WHERE email = :email AND role = "admin" LIMIT 1');
            $stmt->execute([':email' => $email]);
            $admin = $stmt->fetch();

            if (!$admin || !password_verify($password, $admin['password_hash'])) {
                $error = 'Invalid administrator credentials.';
            } elseif ((int)$admin['is_active'] !== 1) {
                $error = 'This administrative account has been deactivated.';
            } else {
                session_regenerate_id(true);

                $_SESSION['campus_coin_logged_in'] = true;
                $_SESSION['campus_coin_admin'] = true;
                $_SESSION['user_id'] = (int)$admin['user_id'];
                $_SESSION['user_name'] = $admin['name'];
                $_SESSION['user_email'] = $admin['email'];
                $_SESSION['role'] = 'admin';

                header('Location: index.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Campus Coin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-login-page">
    <main class="admin-login-shell">
        <section class="admin-login-card">
            <div class="brand admin-brand">
                <div class="brand-mark">C</div>
                <div>
                    <strong>Campus Coin</strong>
                    <span>Administration</span>
                </div>
            </div>

            <p class="eyebrow">Secure Admin Portal</p>
            <h1>Admin sign in</h1>
            <p class="muted">Access system statistics, category masters, announcements and student account controls.</p>

            <?php if ($error !== ''): ?>
                <div class="login-error" role="alert"><?php echo e($error); ?></div>
            <?php endif; ?>

            <div class="demo-login-box">
                <strong>Admin Seed Account (MySQL)</strong>
                <span>Email: admin@campuscoin.com</span>
                <span>Password: admin123</span>
            </div>

            <form method="POST" action="login.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">

                <label for="adminEmail">
                    Admin email
                    <input type="email" id="adminEmail" name="email" placeholder="admin@campuscoin.com" value="<?php echo e($email); ?>" required>
                </label>
                <label for="adminPassword">
                    Password
                    <input type="password" id="adminPassword" name="password" placeholder="Enter admin password" required>
                </label>
                <button class="primary-btn full-btn" type="submit">Enter admin dashboard</button>
            </form>

            <a class="admin-back-link" href="../login.php">← Back to student login</a>
        </section>
    </main>
</body>
</html>
