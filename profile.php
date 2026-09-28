<?php
require_once __DIR__ . '/config.php';
requireLogin();

$user = currentUser();
if (!$user) {
    header('Location: logout.php');
    exit;
}

$error = '';
$success = getFlashMessage('success') ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        $error = 'Security validation failed. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $pdo = getDbConnection();

        if ($action === 'update_profile') {
            $name = trim($_POST['name'] ?? '');
            $academicYear = trim($_POST['academic_year'] ?? '');
            $allowance = filter_var($_POST['monthly_allowance'] ?? 0, FILTER_VALIDATE_FLOAT);
            $savingsGoal = filter_var($_POST['monthly_savings_goal'] ?? 0, FILTER_VALIDATE_FLOAT);

            if (empty($name) || mb_strlen($name) < 2) {
                $error = 'Please enter a valid full name.';
            } elseif ($allowance === false || $allowance < 0) {
                $error = 'Monthly allowance must be a positive number.';
            } elseif ($savingsGoal === false || $savingsGoal < 0) {
                $error = 'Monthly savings goal must be a positive number.';
            } else {
                $stmt = $pdo->prepare('
                    UPDATE users 
                    SET name = :name, academic_year = :year, monthly_allowance = :allowance, monthly_savings_goal = :goal 
                    WHERE user_id = :id
                ');
                $stmt->execute([
                    ':name'      => $name,
                    ':year'      => $academicYear,
                    ':allowance' => $allowance,
                    ':goal'      => $savingsGoal,
                    ':id'        => currentUserId()
                ]);

                $_SESSION['user_name'] = $name;
                setFlashMessage('success', 'Profile and financial baseline updated successfully.');
                header('Location: profile.php');
                exit;
            }
        } elseif ($action === 'change_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmNewPassword = $_POST['confirm_new_password'] ?? '';

            $passStmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = :id');
            $passStmt->execute([':id' => currentUserId()]);
            $currentHash = $passStmt->fetchColumn();

            if (!password_verify($currentPassword, $currentHash)) {
                $error = 'The current password you entered is incorrect.';
            } elseif (mb_strlen($newPassword) < 6) {
                $error = 'New password must be at least 6 characters long.';
            } elseif ($newPassword !== $confirmNewPassword) {
                $error = 'New passwords do not match. Please re-enter.';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                $updatePassStmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE user_id = :id');
                $updatePassStmt->execute([
                    ':hash' => $newHash,
                    ':id'   => currentUserId()
                ]);

                setFlashMessage('success', 'Your password has been updated securely.');
                header('Location: profile.php');
                exit;
            }
        }
    }
}

// Re-fetch fresh user record
$user = currentUser();

// Initials calculation
$parts = explode(' ', trim($user['name']));
$initials = mb_substr($parts[0], 0, 1) . (isset($parts[1]) ? mb_substr($parts[1], 0, 1) : mb_substr($parts[0], 1, 1));
$initials = strtoupper($initials);

$pageTitle = "Student Profile";
$activePage = "profile";
include "includes/header.php";
?>
<div class="app-shell">
    <?php include "includes/sidebar.php"; ?>

    <main class="main-content">
        <?php include "includes/topbar.php"; ?>

        <section class="page-content">
            <nav class="breadcrumbs" aria-label="Breadcrumb">
                <a href="index.php">Home</a>
                <span class="bc-sep">/</span>
                <span class="bc-current" aria-current="page">Student Profile</span>
            </nav>

            <div class="page-header">
                <div>
                    <p class="eyebrow">Account Settings</p>
                    <h1>Student Profile & Financial Baseline</h1>
                    <p class="muted">Manage your personal details, academic cohort, allowance baselines, and security credentials (SRS 1.6 Page 6).</p>
                </div>
            </div>

            <?php if (!empty($success)): ?>
                <div class="login-success" style="background:#eaf6ee;color:#1e7e34;padding:12px 16px;border-radius:10px;margin-bottom:18px;font-size:14px;" role="status">
                    <?php echo e($success); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="login-error" style="margin-bottom:18px;" role="alert">
                    <?php echo e($error); ?>
                </div>
            <?php endif; ?>

            <div class="profile-grid">
                <!-- Identity Card -->
                <section class="panel profile-card">
                    <div class="profile-avatar" id="profileCardAvatar"><?php echo e($initials); ?></div>
                    <h2 id="profileCardName"><?php echo e($user['name']); ?></h2>
                    <p class="muted"><?php echo e(ucfirst($user['role'])); ?> Account</p>
                    <div class="profile-info">
                        <div><span>Email</span><strong id="profileCardEmail"><?php echo e($user['email']); ?></strong></div>
                        <div><span>Academic Year</span><strong id="profileCardYear"><?php echo e($user['academic_year'] ?? 'Not set'); ?></strong></div>
                        <div><span>Base Allowance</span><strong id="profileCardAllowance">Rs. <?php echo number_format((float)$user['monthly_allowance'], 2); ?> / mo</strong></div>
                        <div><span>Savings Goal</span><strong id="profileCardGoal">Rs. <?php echo number_format((float)$user['monthly_savings_goal'], 2); ?> / mo</strong></div>
                        <div><span>Member Since</span><strong><?php echo date('F Y', strtotime($user['created_at'])); ?></strong></div>
                        <div><span>Currency</span><strong>PKR — Rs.</strong></div>
                    </div>
                </section>

                <!-- Editable Profile Form (SRS 1.6 Page 6 & SRS 1.8 Page 13) -->
                <section class="panel form-panel">
                    <h3>Personal & Financial Information</h3>
                    <p class="muted">Update baseline values that power your personalized savings tips and alerts.</p>

                    <form id="profileForm" method="POST" action="profile.php" style="margin-top:16px;">
                        <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                        <input type="hidden" name="action" value="update_profile">

                        <label for="profileName">
                            Full name
                            <input type="text" id="profileName" name="name" value="<?php echo e($user['name']); ?>" required>
                        </label>

                        <label for="profileEmail">
                            Email address (Read-only)
                            <input type="email" id="profileEmail" name="email" value="<?php echo e($user['email']); ?>" readonly style="background:#f1f3f1;cursor:not-allowed;">
                        </label>

                        <!-- MANDATORY SRS REQUIREMENT: Academic Year (SRS 1.6 Page 6 & 1.8 Page 13) -->
                        <label for="profileAcademicYear">
                            Academic Year
                            <select id="profileAcademicYear" name="academic_year" required>
                                <?php
                                $years = [
                                    '1st Year (Freshman)',
                                    '2nd Year (Sophomore)',
                                    '3rd Year (Junior)',
                                    'Final Year (Senior)',
                                    'Postgraduate'
                                ];
                                foreach ($years as $yr):
                                ?>
                                    <option value="<?php echo e($yr); ?>" <?php echo ($user['academic_year'] === $yr) ? 'selected' : ''; ?>>
                                        <?php echo e($yr); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <!-- MANDATORY SRS REQUIREMENT: Monthly Allowance Baseline (SRS 1.6 Page 6) -->
                        <label for="profileAllowance">
                            Monthly Allowance Baseline (PKR)
                            <input type="number" id="profileAllowance" name="monthly_allowance" value="<?php echo e($user['monthly_allowance']); ?>" min="0" step="0.01" required>
                        </label>

                        <!-- MANDATORY SRS REQUIREMENT: Monthly Savings Goal (SRS 1.6 Page 6 & 1.8 Page 13) -->
                        <label for="profileSavingsGoal">
                            Monthly Savings Goal Baseline (PKR)
                            <input type="number" id="profileSavingsGoal" name="monthly_savings_goal" value="<?php echo e($user['monthly_savings_goal']); ?>" min="0" step="0.01" required>
                        </label>

                        <button class="primary-btn" type="submit" style="margin-top:12px;">Save Profile Changes</button>
                    </form>

                    <!-- Account Security Section -->
                    <hr style="margin: 28px 0; border: none; border-top: 1px solid var(--line);">
                    <h3>Account Security</h3>
                    <p class="muted">Change your student login password.</p>

                    <form id="passwordForm" method="POST" action="profile.php" style="margin-top:16px;">
                        <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                        <input type="hidden" name="action" value="change_password">

                        <label for="currentPassword">
                            Current Password
                            <input type="password" id="currentPassword" name="current_password" placeholder="Enter current password" required>
                        </label>

                        <div class="form-grid-two">
                            <label for="newPassword">
                                New Password
                                <input type="password" id="newPassword" name="new_password" placeholder="At least 6 chars" required minlength="6">
                            </label>

                            <label for="confirmNewPassword">
                                Confirm New Password
                                <input type="password" id="confirmNewPassword" name="confirm_new_password" placeholder="Re-enter new password" required minlength="6">
                            </label>
                        </div>

                        <button class="outline-btn" type="submit" style="margin-top:12px;">Update Password</button>
                    </form>
                </section>
            </div>
        </section>

        <?php include "includes/footer.php"; ?>
    </main>
</div>
