<?php
require_once __DIR__ . '/config.php';
requireLogin();

$user = currentUser();
if (!$user) {
    header('Location: logout.php');
    exit;
}

$userId = currentUserId();
$pdo = getDbConnection();

$flashSuccess = getFlashMessage('success');
$flashError = getFlashMessage('error');
$error = '';

// ==========================================
// 1. MONTH SELECTION & BOUNDARIES
// ==========================================
$selectedMonth = trim($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}
$monthDate = $selectedMonth . '-01';
$monthStart = date('Y-m-01', strtotime($monthDate));
$monthEnd = date('Y-m-t', strtotime($monthDate));
$monthDisplay = date('F Y', strtotime($monthDate));

// ==========================================
// 2. HANDLE POST ACTIONS (SAVE & DELETE)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        $error = 'Security validation failed. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? 'save_budget';

        if ($action === 'save_budget') {
            $budgetId = (int)($_POST['budget_id'] ?? 0);
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $monthInput = trim($_POST['month'] ?? $selectedMonth);
            if (!preg_match('/^\d{4}-\d{2}$/', $monthInput)) {
                $monthInput = date('Y-m');
            }
            $targetMonthDate = $monthInput . '-01';
            $limitAmount = filter_var($_POST['limit_amount'] ?? 0, FILTER_VALIDATE_FLOAT);

            if ($categoryId <= 0) {
                $error = 'Please select a valid expense category.';
            } elseif ($limitAmount === false || $limitAmount <= 0) {
                $error = 'Please enter a valid monthly limit amount (greater than 0).';
            } else {
                // Verify category belongs to defaults or this user
                $catCheck = $pdo->prepare('
                    SELECT category_id FROM categories 
                    WHERE category_id = :cid AND (is_default = 1 OR user_id = :uid) AND type = "expense"
                    LIMIT 1
                ');
                $catCheck->execute([':cid' => $categoryId, ':uid' => $userId]);

                if (!$catCheck->fetch()) {
                    $error = 'The selected expense category is invalid.';
                } else {
                    if ($budgetId > 0) {
                        // UPDATE existing budget
                        $updateStmt = $pdo->prepare('
                            UPDATE budgets 
                            SET category_id = :cid, month = :month, limit_amount = :limit 
                            WHERE budget_id = :bid AND user_id = :uid
                        ');
                        $updateStmt->execute([
                            ':cid'   => $categoryId,
                            ':month' => $targetMonthDate,
                            ':limit' => $limitAmount,
                            ':bid'   => $budgetId,
                            ':uid'   => $userId
                        ]);
                        setFlashMessage('success', 'Budget limit updated successfully.');
                    } else {
                        // Check if a budget already exists for this category and month
                        $dupCheck = $pdo->prepare('
                            SELECT budget_id FROM budgets 
                            WHERE user_id = :uid AND category_id = :cid AND month = :month
                            LIMIT 1
                        ');
                        $dupCheck->execute([
                            ':uid'   => $userId,
                            ':cid'   => $categoryId,
                            ':month' => $targetMonthDate
                        ]);
                        $existingBudgetId = $dupCheck->fetchColumn();

                        if ($existingBudgetId) {
                            $updateExisting = $pdo->prepare('
                                UPDATE budgets 
                                SET limit_amount = :limit 
                                WHERE budget_id = :bid AND user_id = :uid
                            ');
                            $updateExisting->execute([
                                ':limit' => $limitAmount,
                                ':bid'   => $existingBudgetId,
                                ':uid'   => $userId
                            ]);
                            setFlashMessage('success', 'Budget limit updated for this category.');
                        } else {
                            $insertStmt = $pdo->prepare('
                                INSERT INTO budgets (user_id, category_id, month, limit_amount)
                                VALUES (:uid, :cid, :month, :limit)
                            ');
                            $insertStmt->execute([
                                ':uid'   => $userId,
                                ':cid'   => $categoryId,
                                ':month' => $targetMonthDate,
                                ':limit' => $limitAmount
                            ]);
                            setFlashMessage('success', 'New monthly budget created successfully.');
                        }
                    }
                    header('Location: budgets.php?month=' . urlencode($monthInput));
                    exit;
                }
            }
        } elseif ($action === 'delete_budget') {
            $budgetId = (int)($_POST['budget_id'] ?? 0);
            if ($budgetId > 0) {
                $delStmt = $pdo->prepare('DELETE FROM budgets WHERE budget_id = :bid AND user_id = :uid');
                $delStmt->execute([':bid' => $budgetId, ':uid' => $userId]);
                setFlashMessage('success', 'Budget removed successfully.');
            }
            header('Location: budgets.php?month=' . urlencode($selectedMonth));
            exit;
        }
    }
}

// ==========================================
// 3. FETCH BUDGETS & REAL-TIME SPENT FOR MONTH
// ==========================================
$budgetsStmt = $pdo->prepare('
    SELECT 
        b.budget_id,
        b.category_id,
        b.month,
        b.limit_amount,
        c.name AS category_name,
        c.icon AS category_icon,
        COALESCE(SUM(t.amount), 0) AS spent
    FROM budgets b
    JOIN categories c ON b.category_id = c.category_id
    LEFT JOIN transactions t ON t.category_id = b.category_id 
        AND t.user_id = b.user_id 
        AND t.type = "expense"
        AND t.date BETWEEN :start_date AND :end_date
    WHERE b.user_id = :user_id 
      AND b.month = :month_date
    GROUP BY b.budget_id, b.category_id, b.month, b.limit_amount, c.name, c.icon
    ORDER BY c.name ASC
');
$budgetsStmt->execute([
    ':user_id'    => $userId,
    ':start_date' => $monthStart,
    ':end_date'   => $monthEnd,
    ':month_date' => $monthDate
]);
$budgetsList = $budgetsStmt->fetchAll();

// ==========================================
// 4. FETCH AVAILABLE EXPENSE CATEGORIES
// ==========================================
$categoriesStmt = $pdo->prepare('
    SELECT category_id, name, icon 
    FROM categories 
    WHERE (is_default = 1 OR user_id = :user_id) 
      AND type = "expense"
    ORDER BY name ASC
');
$categoriesStmt->execute([':user_id' => $userId]);
$expenseCategories = $categoriesStmt->fetchAll();

// Collect warnings and alerts for in-app alert banner (SRS 1.6 Page 8)
$alerts = [];
foreach ($budgetsList as $b) {
    $spent = (float)$b['spent'];
    $limit = (float)$b['limit_amount'];
    if ($limit > 0) {
        $ratio = $spent / $limit;
        if ($ratio >= 1.0) {
            $alerts[] = [
                'type' => 'danger',
                'msg'  => "🚨 Budget Exceeded: " . e($b['category_name']) . " spending (Rs. " . number_format($spent, 2) . ") has surpassed your Rs. " . number_format($limit, 2) . " limit!"
            ];
        } elseif ($ratio >= 0.8) {
            $pct = round($ratio * 100);
            $alerts[] = [
                'type' => 'warning',
                'msg'  => "⚠️ Budget Warning: " . e($b['category_name']) . " is at {$pct}% of its cap (Rs. " . number_format($spent, 2) . " / Rs. " . number_format($limit, 2) . ")."
            ];
        }
    }
}

$pageTitle = "Budgets";
$activePage = "budgets";
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
                <span class="bc-current" aria-current="page">Budget Goals & Alerts</span>
            </nav>

            <div class="page-header">
                <div>
                    <p class="eyebrow">Plan before you spend</p>
                    <h1>Monthly Budgets & Alerts</h1>
                    <p class="muted">Set monthly category spending caps and monitor consumption progress in real time (SRS 1.6 Page 8).</p>
                </div>
                <div class="header-action-group">
                    <input type="month" id="budgetMonthFilter" class="month-picker-input" title="Filter budget month" 
                        value="<?php echo e($selectedMonth); ?>" 
                        onchange="window.location.href='budgets.php?month=' + encodeURIComponent(this.value);">
                    <button class="primary-btn" type="button" onclick="openAddBudgetModal()">＋ New budget</button>
                </div>
            </div>

            <?php if (!empty($flashSuccess)): ?>
                <div class="login-success" style="background:#eaf6ee;color:#1e7e34;padding:12px 16px;border-radius:10px;margin-bottom:18px;font-size:14px;" role="status">
                    <?php echo e($flashSuccess); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error) || !empty($flashError)): ?>
                <div class="login-error" style="margin-bottom:18px;" role="alert">
                    <?php echo e(!empty($error) ? $error : $flashError); ?>
                </div>
            <?php endif; ?>

            <!-- Real-time In-App Notification Alerts (SRS 1.6 Page 8) -->
            <?php if (!empty($alerts)): ?>
                <div style="margin-bottom:20px;display:flex;flex-direction:column;gap:8px;">
                    <?php foreach ($alerts as $al): ?>
                        <div style="padding:10px 14px;border-radius:8px;font-size:13px;background:<?php echo $al['type'] === 'danger' ? '#ffe3e3' : '#fff3bf'; ?>;color:<?php echo $al['type'] === 'danger' ? '#c92a2a' : '#d97706'; ?>;border-left:4px solid <?php echo $al['type'] === 'danger' ? '#c92a2a' : '#d97706'; ?>;">
                            <?php echo e($al['msg']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Real-time Budget Progress Grid (SRS 1.6 Page 8 & 1.8 Page 14) -->
            <div class="budget-grid" id="budgetGrid">
                <?php if (!empty($budgetsList)): ?>
                    <?php foreach ($budgetsList as $b): 
                        $spent = (float)$b['spent'];
                        $limit = (float)$b['limit_amount'];
                        $remaining = $limit - $spent;
                        $ratio = ($limit > 0) ? ($spent / $limit) : 0;
                        $percentage = min(round($ratio * 100), 100);

                        $barColor = 'var(--teal)';
                        if ($ratio >= 1.0) {
                            $barColor = '#c92a2a';
                        } elseif ($ratio >= 0.8) {
                            $barColor = '#d97706';
                        }
                    ?>
                        <div class="budget-panel">
                            <div class="budget-top">
                                <span><?php echo e($b['category_icon'] . ' ' . $b['category_name']); ?></span>
                                <b>Rs. <?php echo number_format($spent, 2); ?> / Rs. <?php echo number_format($limit, 2); ?></b>
                            </div>

                            <div class="progress-track">
                                <span style="width: <?php echo $percentage; ?>%; background: <?php echo $barColor; ?>;"></span>
                            </div>

                            <small style="display:flex;justify-content:space-between;align-items:center;margin-top:9px;">
                                <span>
                                    <?php if ($remaining >= 0): ?>
                                        Rs. <?php echo number_format($remaining, 2); ?> remaining
                                    <?php else: ?>
                                        <strong style="color:#c92a2a;">Rs. <?php echo number_format(abs($remaining), 2); ?> over budget</strong>
                                    <?php endif; ?>
                                </span>
                                <span style="font-weight:600;color:<?php echo $barColor; ?>;"><?php echo round($ratio * 100); ?>%</span>
                            </small>

                            <div class="budget-actions" style="margin-top:14px;display:flex;gap:8px;justify-content:flex-end;">
                                <button type="button" class="btn-xs outline-btn" 
                                    onclick='editBudget(<?php echo json_encode($b); ?>)'>
                                    Edit
                                </button>
                                <form method="POST" action="budgets.php?month=<?php echo urlencode($selectedMonth); ?>" style="display:inline;" onsubmit="return confirm('Delete this budget limit?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                                    <input type="hidden" name="action" value="delete_budget">
                                    <input type="hidden" name="budget_id" value="<?php echo e($b['budget_id']); ?>">
                                    <button type="submit" class="btn-xs cancel-btn">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="transaction-empty" style="grid-column:1 / -1;text-align:center;padding:36px 0;" class="muted">
                        <p style="margin-bottom:12px;">No budgets set for <?php echo e($monthDisplay); ?>.</p>
                        <button class="primary-btn" type="button" onclick="openAddBudgetModal()">＋ Set Your First Budget</button>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Budget Modal (SRS 1.8 Page 14) -->
        <div class="transaction-modal" id="budgetModal" aria-hidden="true">
            <div class="transaction-modal-box budget-modal-box">
                <button class="modal-close" type="button" onclick="closeBudgetModal()" aria-label="Close dialog">×</button>

                <p class="eyebrow" id="budgetModalEyebrow">Monthly budget</p>
                <h2 id="budgetModalTitle">Add budget</h2>
                <p class="muted">Set a spending limit for one category for a specific month.</p>

                <form id="budgetForm" method="POST" action="budgets.php?month=<?php echo urlencode($selectedMonth); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                    <input type="hidden" name="action" value="save_budget">
                    <input type="hidden" id="budgetId" name="budget_id" value="">

                    <label for="budgetMonth">
                        Applicable Month (SRS Section 1.8)
                        <input type="month" id="budgetMonth" name="month" required value="<?php echo e($selectedMonth); ?>">
                    </label>

                    <label for="budgetCategory">
                        Category
                        <select id="budgetCategory" name="category_id" required>
                            <?php foreach ($expenseCategories as $ec): ?>
                                <option value="<?php echo e($ec['category_id']); ?>">
                                    <?php echo e($ec['icon'] . ' ' . $ec['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label for="budgetAmount">
                        Monthly limit (PKR)
                        <input type="number" id="budgetAmount" name="limit_amount" min="1" step="0.01" placeholder="e.g. 5000" required>
                    </label>

                    <div class="transaction-form-actions">
                        <button class="cancel-btn" type="button" onclick="closeBudgetModal()">
                            <span class="cancel-icon">×</span>
                            Cancel
                        </button>
                        <button class="primary-btn full-btn" type="submit">Save budget</button>
                    </div>
                </form>
            </div>
        </div>

        <?php include "includes/footer.php"; ?>
    </main>
</div>

<script>
function openAddBudgetModal() {
    const modal = document.getElementById("budgetModal");
    const idInput = document.getElementById("budgetId");
    const amountInput = document.getElementById("budgetAmount");
    const monthInput = document.getElementById("budgetMonth");
    const title = document.getElementById("budgetModalTitle");
    const eyebrow = document.getElementById("budgetModalEyebrow");

    if (modal) {
        if (idInput) idInput.value = "";
        if (amountInput) amountInput.value = "";
        if (monthInput) monthInput.value = "<?php echo e($selectedMonth); ?>";
        if (title) title.textContent = "Add budget";
        if (eyebrow) eyebrow.textContent = "Monthly budget";

        modal.classList.add("show");
        modal.setAttribute("aria-hidden", "false");
    }
}

function editBudget(b) {
    const modal = document.getElementById("budgetModal");
    const idInput = document.getElementById("budgetId");
    const catSelect = document.getElementById("budgetCategory");
    const amountInput = document.getElementById("budgetAmount");
    const monthInput = document.getElementById("budgetMonth");
    const title = document.getElementById("budgetModalTitle");
    const eyebrow = document.getElementById("budgetModalEyebrow");

    if (modal) {
        if (idInput) idInput.value = b.budget_id;
        if (amountInput) amountInput.value = b.limit_amount;
        if (monthInput && b.month) monthInput.value = b.month.substring(0, 7);
        if (title) title.textContent = "Edit budget";
        if (eyebrow) eyebrow.textContent = "Update spending limit";

        if (catSelect) {
            for (let i = 0; i < catSelect.options.length; i++) {
                if (catSelect.options[i].value == b.category_id) {
                    catSelect.selectedIndex = i;
                    break;
                }
            }
        }

        modal.classList.add("show");
        modal.setAttribute("aria-hidden", "false");
    }
}

function closeBudgetModal() {
    const modal = document.getElementById("budgetModal");
    if (modal) {
        modal.classList.remove("show");
        modal.setAttribute("aria-hidden", "true");
    }
}
</script>
