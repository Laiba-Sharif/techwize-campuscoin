<?php
require_once __DIR__ . '/config.php';

// If user is already authenticated, redirect to dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Retained field values for user convenience
$name = '';
$email = '';
$academicYear = '1st Year (Freshman)';
$allowance = '15000';
$savingsGoal = '3000';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. CSRF Token Validation
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        $error = 'Security validation failed. Please refresh the page and try again.';
    } else {
        // 2. Extract and sanitize inputs
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $academicYear = trim($_POST['academic_year'] ?? '1st Year (Freshman)');
        $allowance = filter_var($_POST['monthly_allowance'] ?? 0, FILTER_VALIDATE_FLOAT);
        $savingsGoal = filter_var($_POST['monthly_savings_goal'] ?? 0, FILTER_VALIDATE_FLOAT);

        // 3. Server-side validation
        if (empty($name) || mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $error = 'Please enter a valid full name (2–100 characters).';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (mb_strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match. Please re-enter.';
        } elseif ($allowance === false || $allowance < 0) {
            $error = 'Please enter a valid positive monthly allowance.';
        } elseif ($savingsGoal === false || $savingsGoal < 0) {
            $error = 'Please enter a valid positive savings goal.';
        } else {
            // 4. Check for duplicate email in database using prepared statement
            $pdo = getDbConnection();
            $checkStmt = $pdo->prepare('SELECT user_id FROM users WHERE email = :email LIMIT 1');
            $checkStmt->execute([':email' => $email]);

            if ($checkStmt->fetch()) {
                $error = 'This email address is already registered. Please sign in or use another email.';
            } else {
                // 5. Hash password with bcrypt
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);

                // 6. Insert new student account into MySQL
                $insertStmt = $pdo->prepare('
                    INSERT INTO users (name, email, password_hash, role, academic_year, monthly_allowance, monthly_savings_goal, is_active)
                    VALUES (:name, :email, :password_hash, "student", :academic_year, :monthly_allowance, :monthly_savings_goal, 1)
                ');

                $inserted = $insertStmt->execute([
                    ':name'                 => $name,
                    ':email'                => $email,
                    ':password_hash'        => $passwordHash,
                    ':academic_year'        => $academicYear,
                    ':monthly_allowance'    => $allowance,
                    ':monthly_savings_goal' => $savingsGoal
                ]);

                if ($inserted) {
                    $newUserId = (int)$pdo->lastInsertId();

                    // 7. Auto-login the new student and redirect to dashboard
                    session_regenerate_id(true);
                    $_SESSION['campus_coin_logged_in'] = true;
                    $_SESSION['user_id'] = $newUserId;
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['role'] = 'student';

                    setFlashMessage('success', 'Welcome to Campus Coin! Your student profile has been created.');
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error = 'Unable to create your account at this time. Please try again.';
                }
            }
        }
    }
}

$pageTitle = "Create account";
include "includes/header.php";
?>
<div class="auth-page">
    <div class="auth-art">
        <div class="auth-brand"><div class="brand-mark">C</div><strong>Campus Coin</strong></div>
        <div class="auth-copy">
            <p class="eyebrow">Start with clarity</p>
            <h1>Build better money habits, one month at a time.</h1>
            <p>Create a student-friendly finance space for your allowance, budgets and savings.</p>
        </div>
        <div class="auth-stat-row">
            <div><strong>12</strong><span>Default Categories</span></div>
            <div><strong>6</strong><span>Months Trends</span></div>
            <div><strong>1</strong><span>Savings Goal</span></div>
        </div>
    </div>
    <div class="auth-form-wrap">
        <div class="auth-form">
            <div class="mobile-brand auth-mobile"><div class="brand-mark">C</div><strong>Campus Coin</strong></div>
            <p class="eyebrow">Get started</p>
            <h2>Create your student account</h2>
            <p class="muted">Set up your profile and spending baseline in seconds.</p>

            <?php if ($error !== ''): ?>
                <div class="login-error" role="alert"><?php echo e($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="signup.php" id="signupForm" novalidate>
                <!-- CSRF Token (SRS Security Requirement) -->
                <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                
                <label for="signupName">
                    Full name
                    <input type="text" id="signupName" name="name" placeholder="Ayesha Khan" value="<?php echo e($name); ?>" required>
                </label>

                <label for="signupEmail">
                    Email address
                    <input type="email" id="signupEmail" name="email" placeholder="student@example.com" value="<?php echo e($email); ?>" required>
                </label>

                <div class="form-grid-two">
                    <label for="signupPassword">
                        Password
                        <input type="password" id="signupPassword" name="password" placeholder="At least 6 chars" required minlength="6">
                    </label>

                    <label for="signupConfirmPassword">
                        Confirm password
                        <input type="password" id="signupConfirmPassword" name="confirm_password" placeholder="Re-enter password" required minlength="6">
                    </label>
                </div>

                <!-- SRS-Required Profile Baselines (SRS 1.6 Page 6 & SRS 1.8 Page 13) -->
                <div class="form-grid-two">
                    <label for="signupAcademicYear">
                        Academic year
                        <select id="signupAcademicYear" name="academic_year" required>
                            <option value="1st Year (Freshman)" <?php echo ($academicYear === '1st Year (Freshman)') ? 'selected' : ''; ?>>1st Year (Freshman)</option>
                            <option value="2nd Year (Sophomore)" <?php echo ($academicYear === '2nd Year (Sophomore)') ? 'selected' : ''; ?>>2nd Year (Sophomore)</option>
                            <option value="3rd Year (Junior)" <?php echo ($academicYear === '3rd Year (Junior)') ? 'selected' : ''; ?>>3rd Year (Junior)</option>
                            <option value="Final Year (Senior)" <?php echo ($academicYear === 'Final Year (Senior)') ? 'selected' : ''; ?>>Final Year (Senior)</option>
                            <option value="Postgraduate" <?php echo ($academicYear === 'Postgraduate') ? 'selected' : ''; ?>>Postgraduate</option>
                        </select>
                    </label>

                    <label for="signupAllowance">
                        Monthly allowance (PKR)
                        <input type="number" id="signupAllowance" name="monthly_allowance" min="0" step="0.01" placeholder="e.g. 15000" value="<?php echo e($allowance); ?>">
                    </label>
                </div>

                <label for="signupSavingsGoal">
                    Monthly savings goal (PKR)
                    <input type="number" id="signupSavingsGoal" name="monthly_savings_goal" min="0" step="0.01" placeholder="e.g. 3000" value="<?php echo e($savingsGoal); ?>">
                </label>

                <button class="primary-btn full-btn" type="submit">Create account</button>
            </form>
            <p class="auth-switch">Already have an account? <a href="login.php">Sign in</a></p>
            <p class="auth-switch"><a href="admin/login.php">Admin portal →</a></p>
        </div>
    </div>
</div>
<?php include "includes/footer.php"; ?>
