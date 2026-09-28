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
// 1. HANDLE QUICK-ADD TRANSACTION SUBMISSION
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_add_transaction') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        $error = 'Security validation failed. Please refresh and try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $amount = filter_var($_POST['amount'] ?? 0, FILTER_VALIDATE_FLOAT);
        $type = in_array($_POST['type'] ?? '', ['income', 'expense']) ? $_POST['type'] : 'expense';
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $date = trim($_POST['date'] ?? date('Y-m-d'));
        $time = trim($_POST['time'] ?? date('H:i:s'));
        $isRecurring = isset($_POST['is_recurring']) ? 1 : 0;

        if (empty($title)) {
            $error = 'Please enter a transaction title or description.';
        } elseif ($amount === false || $amount <= 0) {
            $error = 'Please enter a valid positive amount.';
        } elseif ($categoryId <= 0) {
            $error = 'Please select a valid category.';
        } else {
            // Verify category belongs to system default or this user
            $catCheck = $pdo->prepare('SELECT category_id FROM categories WHERE category_id = :cid AND (is_default = 1 OR user_id = :uid) LIMIT 1');
            $catCheck->execute([':cid' => $categoryId, ':uid' => $userId]);
            if (!$catCheck->fetch()) {
                $error = 'Selected category is invalid.';
            } else {
                // Insert transaction
                $insertStmt = $pdo->prepare('
                    INSERT INTO transactions (user_id, category_id, amount, type, title, is_recurring, date, time)
                    VALUES (:user_id, :category_id, :amount, :type, :title, :is_recurring, :date, :time)
                ');
                $inserted = $insertStmt->execute([
                    ':user_id'      => $userId,
                    ':category_id'  => $categoryId,
                    ':amount'       => $amount,
                    ':type'         => $type,
                    ':title'        => $title,
                    ':is_recurring' => $isRecurring,
                    ':date'         => $date,
                    ':time'         => $time
                ]);

                if ($inserted && $isRecurring) {
                    // Record in recurring transactions
                    $recStmt = $pdo->prepare('
                        INSERT INTO recurring_transactions (user_id, category_id, title, amount, type, frequency, is_active)
                        VALUES (:user_id, :category_id, :title, :amount, :type, "monthly", 1)
                    ');
                    $recStmt->execute([
                        ':user_id'     => $userId,
                        ':category_id' => $categoryId,
                        ':title'       => $title,
                        ':amount'      => $amount,
                        ':type'        => $type
                    ]);
                }

                setFlashMessage('success', 'Transaction logged successfully!');
                header('Location: dashboard.php');
                exit;
            }
        }
    }
}

// ==========================================
// 2. TIME-OF-DAY GREETING & DATES
// ==========================================
$hour = (int)date('H');
if ($hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour < 17) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}

$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$monthName = date('F Y');
$prevMonthStart = date('Y-m-01', strtotime('-1 month'));
$prevMonthEnd = date('Y-m-t', strtotime('-1 month'));

// ==========================================
// 3. FINANCIAL SUMMARY FOR CURRENT MONTH
// ==========================================
$statsStmt = $pdo->prepare('
    SELECT 
        COALESCE(SUM(CASE WHEN type = "income" THEN amount ELSE 0 END), 0) AS total_income,
        COALESCE(SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END), 0) AS total_expense
    FROM transactions
    WHERE user_id = :user_id 
      AND date BETWEEN :start AND :end
');
$statsStmt->execute([
    ':user_id' => $userId,
    ':start'   => $monthStart,
    ':end'     => $monthEnd
]);
$stats = $statsStmt->fetch();

$thisMonthIncome = (float)($stats['total_income'] ?? 0);
$thisMonthExpense = (float)($stats['total_expense'] ?? 0);
$thisMonthBalance = $thisMonthIncome - $thisMonthExpense;

// Month-over-month comparison
$prevStmt = $pdo->prepare('
    SELECT COALESCE(SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END), 0) AS prev_expense
    FROM transactions
    WHERE user_id = :user_id 
      AND date BETWEEN :prev_start AND :prev_end
');
$prevStmt->execute([
    ':user_id'    => $userId,
    ':prev_start' => $prevMonthStart,
    ':prev_end'   => $prevMonthEnd
]);
$prevExpense = (float)$prevStmt->fetchColumn();

if ($prevExpense > 0) {
    $diff = $thisMonthExpense - $prevExpense;
    $pct = abs(round(($diff / $prevExpense) * 100, 1));
    if ($diff < 0) {
        $comparisonText = "You have spent Rs. " . number_format(abs($diff), 2) . " less than last month ({$pct}% drop). Great job saving!";
    } elseif ($diff > 0) {
        $comparisonText = "Expenses are Rs. " . number_format($diff, 2) . " higher than last month ({$pct}% rise). Keep an eye on dining & discretionary costs.";
    } else {
        $comparisonText = "Your spending is tracking exactly on par with last month's pace.";
    }
} else {
    $comparisonText = "Welcome to your financial overview for {$monthName}! Start logging income and expenses to track trends.";
}

// ==========================================
// 4. BUDGET WARNING ALERTS (SRS 1.6 Page 8)
// ==========================================
$budgetAlertsStmt = $pdo->prepare('
    SELECT 
        b.limit_amount,
        c.name AS category_name,
        COALESCE(SUM(t.amount), 0) AS spent
    FROM budgets b
    JOIN categories c ON b.category_id = c.category_id
    LEFT JOIN transactions t ON t.category_id = b.category_id 
        AND t.user_id = b.user_id 
        AND t.type = "expense"
        AND t.date BETWEEN :start AND :end
    WHERE b.user_id = :user_id
      AND b.month = :budget_month
    GROUP BY b.budget_id, b.category_id, b.limit_amount, c.name
');
$budgetAlertsStmt->execute([
    ':user_id'      => $userId,
    ':start'        => $monthStart,
    ':end'          => $monthEnd,
    ':budget_month' => $monthStart
]);
$budgetAlerts = $budgetAlertsStmt->fetchAll();

$triggeredAlerts = [];
foreach ($budgetAlerts as $b) {
    $spent = (float)$b['spent'];
    $limit = (float)$b['limit_amount'];
    if ($limit > 0) {
        $ratio = $spent / $limit;
        if ($ratio >= 1.0) {
            $triggeredAlerts[] = [
                'type' => 'danger',
                'text' => "Budget Exceeded! You have spent Rs. " . number_format($spent, 2) . " of your Rs. " . number_format($limit, 2) . " limit for " . e($b['category_name']) . "."
            ];
        } elseif ($ratio >= 0.8) {
            $pct = round($ratio * 100);
            $triggeredAlerts[] = [
                'type' => 'warning',
                'text' => "Budget Warning: " . e($b['category_name']) . " is at {$pct}% of its monthly cap (Rs. " . number_format($spent, 2) . " / " . number_format($limit, 2) . ")."
            ];
        }
    }
}

// ==========================================
// 5. TOP SPENDING CATEGORY THIS MONTH
// ==========================================
$topCatStmt = $pdo->prepare('
    SELECT 
        c.name AS category_name,
        c.icon,
        SUM(t.amount) AS total_spent,
        COUNT(t.transaction_id) AS tx_count
    FROM transactions t
    JOIN categories c ON t.category_id = c.category_id
    WHERE t.user_id = :user_id 
      AND t.type = "expense"
      AND t.date BETWEEN :start AND :end
    GROUP BY t.category_id, c.name, c.icon
    ORDER BY total_spent DESC 
    LIMIT 1
');
$topCatStmt->execute([
    ':user_id' => $userId,
    ':start'   => $monthStart,
    ':end'     => $monthEnd
]);
$topCategory = $topCatStmt->fetch();

// ==========================================
// 6. BUDGET VS ACTUAL HIGHLIGHT
// ==========================================
$budgetHighlightStmt = $pdo->prepare('
    SELECT 
        c.name AS category_name,
        b.limit_amount,
        COALESCE(SUM(t.amount), 0) AS spent
    FROM budgets b
    JOIN categories c ON b.category_id = c.category_id
    LEFT JOIN transactions t ON t.category_id = b.category_id 
        AND t.user_id = b.user_id 
        AND t.type = "expense"
        AND t.date BETWEEN :start AND :end
    WHERE b.user_id = :user_id 
      AND b.month = :month
    GROUP BY b.budget_id, b.category_id, b.limit_amount, c.name
    ORDER BY spent DESC
    LIMIT 1
');
$budgetHighlightStmt->execute([
    ':user_id' => $userId,
    ':start'   => $monthStart,
    ':end'     => $monthEnd,
    ':month'   => $monthStart
]);
$budgetHighlight = $budgetHighlightStmt->fetch();

// Daily spending calculation
$daysInMonth = (int)date('t');
$todaySpendStmt = $pdo->prepare('
    SELECT COALESCE(SUM(amount), 0) 
    FROM transactions 
    WHERE user_id = :user_id AND type = "expense" AND date = :today
');
$todaySpendStmt->execute([':user_id' => $userId, ':today' => date('Y-m-d')]);
$todaySpent = (float)$todaySpendStmt->fetchColumn();

$dailyAllowance = ($user['monthly_allowance'] > 0) ? ($user['monthly_allowance'] / $daysInMonth) : 0;

// ==========================================
// 7. RECENT TRANSACTIONS STREAM (Last 6)
// ==========================================
$recentTransStmt = $pdo->prepare('
    SELECT 
        t.transaction_id,
        t.title,
        t.amount,
        t.type,
        t.date,
        t.time,
        t.is_recurring,
        c.name AS category_name,
        c.icon AS category_icon
    FROM transactions t
    JOIN categories c ON t.category_id = c.category_id
    WHERE t.user_id = :user_id
    ORDER BY t.date DESC, t.transaction_id DESC
    LIMIT 6
');
$recentTransStmt->execute([':user_id' => $userId]);
$recentTransactions = $recentTransStmt->fetchAll();

// ==========================================
// 8. CATEGORY SPENDING BREAKDOWN LIST
// ==========================================
$categoryBreakdownStmt = $pdo->prepare('
    SELECT 
        c.name AS category_name,
        c.icon,
        SUM(t.amount) AS total_spent
    FROM transactions t
    JOIN categories c ON t.category_id = c.category_id
    WHERE t.user_id = :user_id 
      AND t.type = "expense"
      AND t.date BETWEEN :start AND :end
    GROUP BY t.category_id, c.name, c.icon
    ORDER BY total_spent DESC 
    LIMIT 5
');
$categoryBreakdownStmt->execute([
    ':user_id' => $userId,
    ':start'   => $monthStart,
    ':end'     => $monthEnd
]);
$categoryBreakdown = $categoryBreakdownStmt->fetchAll();

// ==========================================
// 9. 6-MONTH CASHFLOW FOR CHART.JS
// ==========================================
$sixMonthsAgo = date('Y-m-01', strtotime('-5 months'));
$chartStmt = $pdo->prepare('
    SELECT 
        DATE_FORMAT(date, "%Y-%m") AS month_key,
        SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) AS income,
        SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) AS expense
    FROM transactions
    WHERE user_id = :user_id
      AND date >= :six_months_ago
    GROUP BY month_key
');
$chartStmt->execute([
    ':user_id'        => $userId,
    ':six_months_ago' => $sixMonthsAgo
]);
$chartRows = $chartStmt->fetchAll();

$chartMap = [];
foreach ($chartRows as $cr) {
    $chartMap[$cr['month_key']] = [
        'income'  => (float)$cr['income'],
        'expense' => (float)$cr['expense']
    ];
}

$chartLabels = [];
$chartIncomeData = [];
$chartExpenseData = [];

for ($i = 5; $i >= 0; $i--) {
    $mKey = date('Y-m', strtotime("-$i months"));
    $mLabel = date('M Y', strtotime("-$i months"));

    $chartLabels[] = $mLabel;
    $chartIncomeData[] = $chartMap[$mKey]['income'] ?? 0;
    $chartExpenseData[] = $chartMap[$mKey]['expense'] ?? 0;
}

// ==========================================
// 10. SAVING TIP / AI INSIGHT FROM DB
// ==========================================
$insightStmt = $pdo->prepare('
    SELECT growth_flag_text, summary_text, tip_text 
    FROM insights 
    WHERE user_id = :user_id 
    ORDER BY insight_id DESC 
    LIMIT 1
');
$insightStmt->execute([':user_id' => $userId]);
$activeInsight = $insightStmt->fetch();

// ==========================================
// 11. FETCH CATEGORIES FOR QUICK-ADD MODAL
// ==========================================
$categoriesStmt = $pdo->prepare('
    SELECT category_id, name, type, icon 
    FROM categories 
    WHERE is_default = 1 OR user_id = :user_id
    ORDER BY type DESC, name ASC
');
$categoriesStmt->execute([':user_id' => $userId]);
$categoriesList = $categoriesStmt->fetchAll();

$pageTitle = "Dashboard";
$activePage = "dashboard";
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
                <span class="bc-current" aria-current="page">Dashboard</span>
            </nav>

            <div class="welcome-row">
                <div>
                    <p class="eyebrow">Campus Coin Dashboard</p>
                    <h1 id="welcomeName"><?php echo e($greeting . ', ' . $user['name']); ?></h1>
                    <p class="muted" id="welcomeSummary">Here’s how your finances look for <?php echo e($monthName); ?>.</p>
                </div>
                <a href="reports.php" class="text-link">View detailed monthly report →</a>
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

            <!-- Real-Time Budget Alerts & Comparison (SRS 1.6 Page 8) -->
            <div class="dashboard-alert-row">
                <section class="alert-card">
                    <div class="section-heading">
                        <h3>Budget alerts</h3>
                        <span>Real-time limit tracking</span>
                    </div>
                    <div id="budgetAlerts">
                        <?php if (!empty($triggeredAlerts)): ?>
                            <?php foreach ($triggeredAlerts as $alert): ?>
                                <div class="budget-alert-item <?php echo e($alert['type']); ?>" style="padding:8px 12px;border-radius:8px;font-size:12px;margin-bottom:6px;background:<?php echo $alert['type'] === 'danger' ? '#ffe3e3' : '#fff3bf'; ?>;color:<?php echo $alert['type'] === 'danger' ? '#c92a2a' : '#d97706'; ?>;">
                                    <?php echo e($alert['text']); ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="muted" style="font-size:13px;padding:6px 0;">All category budgets are healthy and within limits.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="alert-card">
                    <div class="section-heading">
                        <h3>Monthly comparison</h3>
                        <span>Current vs previous</span>
                    </div>
                    <p class="comparison-text" id="monthlyComparison"><?php echo e($comparisonText); ?></p>
                </section>
            </div>

            <div class="dashboard-grid">
                <!-- Balance & Quick-Add Card -->
                <section class="balance-card">
                    <div class="balance-content">
                        <p>This month's net balance</p>
                        <h2 id="balanceAmount">
                            Rs. <?php echo number_format($thisMonthBalance, 2); ?>
                        </h2>
                        <div class="money-stats">
                            <div>
                                <span>↑ Income</span>
                                <strong id="incomeAmount">Rs. <?php echo number_format($thisMonthIncome, 2); ?></strong>
                            </div>
                            <div>
                                <span>↓ Expenses</span>
                                <strong id="expenseAmount">Rs. <?php echo number_format($thisMonthExpense, 2); ?></strong>
                            </div>
                        </div>
                        <div class="quick-actions" id="quick-actions">
                            <button class="primary-btn" type="button" onclick="openTransactionModal('income')">＋ Add income</button>
                            <button class="outline-btn" type="button" onclick="openTransactionModal('expense')">＋ Add expense</button>
                        </div>
                    </div>
                </section>

                <!-- Dynamic Saving Tip Card (SRS 1.6 Page 8) -->
                <aside class="tip-card" id="dashboardSavingTip">
                    <div class="card-heading">
                        <span class="mini-icon">☆</span>
                        <span>Personalized Saving Tip</span>
                        <span class="tip-pinned-badge" id="tipPinnedBadge" style="display:inline-block;">📌 Active</span>
                    </div>
                    <p id="dashboardTipText">
                        <?php 
                        if ($activeInsight && !empty($activeInsight['tip_text'])) {
                            echo e($activeInsight['tip_text']);
                        } else {
                            echo "Setting a weekly dining ceiling and taking advantage of student transport passes protects up to 25% of your allowance.";
                        }
                        ?>
                    </p>
                    <div class="tip-actions">
                        <a href="saving-tips.php" class="text-link" style="font-size:12px;">Explore all saving tips & insights →</a>
                    </div>
                </aside>

                <!-- Daily Spending Limit Card -->
                <section class="small-card">
                    <p class="card-label">Daily spending pace</p>
                    <div class="category-highlight">
                        <div class="category-icon food">₨</div>
                        <div>
                            <h3 id="dailyLimitText">Rs. <?php echo number_format($todaySpent, 2); ?></h3>
                            <p id="dailyLimitStatus">
                                <?php if ($dailyAllowance > 0): ?>
                                    Target pace: Rs. <?php echo number_format($dailyAllowance, 2); ?> / day
                                <?php else: ?>
                                    Set monthly allowance in profile.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Top Category This Month (SRS 1.6 Page 7) -->
                <section class="small-card">
                    <p class="card-label">Top category this month</p>
                    <div class="category-highlight">
                        <div class="category-icon food"><?php echo e($topCategory['icon'] ?? '₨'); ?></div>
                        <div>
                            <h3><?php echo e($topCategory['category_name'] ?? 'No expenses yet'); ?></h3>
                            <p>
                                <?php if ($topCategory): ?>
                                    Rs. <?php echo number_format((float)$topCategory['total_spent'], 2); ?> spent (<?php echo e($topCategory['tx_count']); ?> transactions)
                                <?php else: ?>
                                    Log transactions to see top spending.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Budget vs. Actual Widget (SRS 1.6 Page 7) -->
                <section class="small-card">
                    <?php if ($budgetHighlight): 
                        $bSpent = (float)$budgetHighlight['spent'];
                        $bLimit = (float)$budgetHighlight['limit_amount'];
                        $bPct = $bLimit > 0 ? min(round(($bSpent / $bLimit) * 100), 100) : 0;
                        $barColor = $bPct >= 100 ? '#c92a2a' : ($bPct >= 80 ? '#d97706' : 'var(--teal)');
                    ?>
                        <div class="budget-title">
                            <div>
                                <p class="card-label">Budget vs actual — <?php echo e($budgetHighlight['category_name']); ?></p>
                                <strong>Rs. <?php echo number_format($bSpent, 2); ?> spent</strong>
                            </div>
                            <span>Rs. <?php echo number_format($bLimit, 2); ?> limit</span>
                        </div>
                        <div class="progress-track" role="progressbar" aria-valuenow="<?php echo $bPct; ?>" aria-valuemin="0" aria-valuemax="100">
                            <span style="width: <?php echo $bPct; ?>%; background: <?php echo $barColor; ?>;"></span>
                        </div>
                    <?php else: ?>
                        <div class="budget-title">
                            <div>
                                <p class="card-label">Budget vs actual</p>
                                <strong>No monthly budget set</strong>
                            </div>
                            <a href="budgets.php" class="text-link" style="font-size:12px;">Set budget →</a>
                        </div>
                        <div class="progress-track">
                            <span style="width: 0%;"></span>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- Recent Transactions Ledger (SRS 1.6 Page 7) -->
                <section class="transactions-card">
                    <div class="section-heading">
                        <h3>Recent transactions</h3>
                        <a href="transactions.php">View all</a>
                    </div>
                    <div class="transaction-list" id="transactionList">
                        <?php if (!empty($recentTransactions)): ?>
                            <?php foreach ($recentTransactions as $tx): 
                                $isIncome = ($tx['type'] === 'income');
                                $sign = $isIncome ? '+ ' : '- ';
                                $colorClass = $isIncome ? 'income-color' : 'expense-color';
                            ?>
                                <div class="transaction-row" style="display:flex;align-items:center;justify-content:space-between;padding:11px 0;border-bottom:1px solid var(--line);">
                                    <div style="display:flex;align-items:center;gap:12px;">
                                        <div class="category-icon" style="width:36px;height:36px;border-radius:10px;background:var(--teal-soft);display:grid;place-items:center;font-size:16px;">
                                            <?php echo e($tx['category_icon'] ?? ($isIncome ? '💰' : '📦')); ?>
                                        </div>
                                        <div>
                                            <strong style="font-size:13px;display:block;"><?php echo e($tx['title']); ?></strong>
                                            <small class="muted" style="font-size:11px;">
                                                <?php echo e($tx['category_name']); ?> · <?php echo date('M d, Y', strtotime($tx['date'])); ?>
                                                <?php if (!empty($tx['is_recurring'])): ?>
                                                    <span class="badge" style="font-size:9px;background:#e5f1ef;color:var(--teal);padding:1px 5px;border-radius:4px;margin-left:4px;">Recurring</span>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                    <strong style="font-size:14px;color:<?php echo $isIncome ? '#2b8a3e' : '#c92a2a'; ?>;">
                                        <?php echo $sign . 'Rs. ' . number_format((float)$tx['amount'], 2); ?>
                                    </strong>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="muted" style="text-align:center;padding:24px 0;">No transactions recorded yet. Click '+ Add income' or '+ Add expense' to record your first entry.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <aside class="side-stack">
                    <!-- Spending By Category Breakdown -->
                    <section class="spending-card">
                        <div class="section-heading">
                            <h3>Spending by category</h3>
                            <span><?php echo e($monthName); ?></span>
                        </div>
                        <div class="legend-list">
                            <?php if (!empty($categoryBreakdown)): ?>
                                <?php foreach ($categoryBreakdown as $cb): ?>
                                    <div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;">
                                        <span><?php echo e($cb['icon'] . ' ' . $cb['category_name']); ?></span>
                                        <b>Rs. <?php echo number_format((float)$cb['total_spent'], 2); ?></b>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="muted" style="font-size:12px;">No category expenses logged this month.</p>
                            <?php endif; ?>
                        </div>
                    </section>

                    <!-- Income vs Expense 6-Month Chart (Chart.js) -->
                    <section class="chart-card">
                        <div class="section-heading">
                            <h3>Income vs expense — 6 months</h3>
                        </div>
                        <canvas id="incomeExpenseChart"></canvas>
                    </section>
                </aside>
            </div>
        </section>

        <!-- Quick-Add Transaction Modal -->
        <div class="transaction-modal" id="transactionModal" aria-hidden="true">
            <div class="transaction-modal-box">
                <button class="modal-close" type="button" onclick="closeTransactionModal()" aria-label="Close dialog">×</button>
                <p class="eyebrow" id="transactionModalEyebrow">New transaction</p>
                <h2 id="transactionModalTitle">Add income</h2>
                <p class="muted">Enter the transaction details below.</p>

                <form id="transactionForm" method="POST" action="dashboard.php">
                    <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                    <input type="hidden" name="action" value="quick_add_transaction">
                    <input type="hidden" id="transactionType" name="type" value="income">

                    <label for="transactionTitle">
                        Title / Description
                        <input type="text" id="transactionTitle" name="title" placeholder="e.g. Monthly allowance, Lunch at cafe" required autocomplete="off">
                    </label>

                    <!-- AI Category Suggestion Pill (SRS 1.6 Page 7) -->
                    <div class="ai-suggestion-box" id="aiSuggestionNotice" style="display:none;">
                        <span class="ai-badge">🤖 AI Suggestion:</span>
                        <span id="aiSuggestedText">Food</span>
                        <button type="button" class="ai-apply-btn" id="aiApplyBtn" onclick="applyAiSuggestion()">Apply</button>
                    </div>

                    <label for="transactionAmount">
                        Amount (PKR)
                        <input type="number" id="transactionAmount" name="amount" min="1" step="0.01" placeholder="0.00" required>
                    </label>

                    <label for="transactionCategory">
                        Category
                        <select id="transactionCategory" name="category_id" required>
                            <?php foreach ($categoriesList as $cat): ?>
                                <option value="<?php echo e($cat['category_id']); ?>" data-type="<?php echo e($cat['type']); ?>">
                                    <?php echo e($cat['icon'] . ' ' . $cat['name'] . ' (' . ucfirst($cat['type']) . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <div class="form-grid-two">
                        <label for="transactionDate">
                            Date
                            <input type="date" id="transactionDate" name="date" required value="<?php echo date('Y-m-d'); ?>">
                        </label>

                        <label for="transactionTime">
                            Time
                            <input type="time" id="transactionTime" name="time" required value="<?php echo date('H:i'); ?>">
                        </label>
                    </div>

                    <!-- Recurring Transaction Toggle (SRS 1.6 Page 7) -->
                    <div class="form-check-wrap">
                        <label class="check-label" for="transactionIsRecurring">
                            <input type="checkbox" id="transactionIsRecurring" name="is_recurring" value="1">
                            <span>Repeat monthly (Recurring allowance/subscription)</span>
                        </label>
                    </div>

                    <div class="transaction-form-actions">
                        <button class="cancel-btn" type="button" onclick="closeTransactionModal()">
                            <span class="cancel-icon">×</span>
                            Cancel
                        </button>
                        <button class="primary-btn full-btn" id="transactionSaveButton" type="submit">Save transaction</button>
                    </div>
                </form>
            </div>
        </div>

        <?php include "includes/footer.php"; ?>
    </main>
</div>

<!-- Dynamic 6-Month Chart Initialization -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const chartCtx = document.getElementById("incomeExpenseChart");
    if (chartCtx) {
        const labels = <?php echo json_encode($chartLabels); ?>;
        const incomeData = <?php echo json_encode($chartIncomeData); ?>;
        const expenseData = <?php echo json_encode($chartExpenseData); ?>;

        new Chart(chartCtx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Income (PKR)',
                        data: incomeData,
                        backgroundColor: '#176e67',
                        borderRadius: 6
                    },
                    {
                        label: 'Expenses (PKR)',
                        data: expenseData,
                        backgroundColor: '#dca51c',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: { family: "'DM Sans', sans-serif", size: 11 },
                            boxWidth: 12
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10 } }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            font: { size: 10 },
                            callback: function(val) { return 'Rs. ' + val.toLocaleString(); }
                        }
                    }
                }
            }
        });
    }
});

function openTransactionModal(type) {
    const modal = document.getElementById("transactionModal");
    const titleEl = document.getElementById("transactionModalTitle");
    const typeInput = document.getElementById("transactionType");
    const catSelect = document.getElementById("transactionCategory");

    if (modal && titleEl && typeInput) {
        typeInput.value = type;
        titleEl.textContent = (type === "income") ? "Add income" : "Add expense";

        // Filter category dropdown to match type
        if (catSelect) {
            for (let i = 0; i < catSelect.options.length; i++) {
                const optType = catSelect.options[i].getAttribute("data-type");
                catSelect.options[i].style.display = (optType === type) ? "" : "none";
            }
            // Select first visible
            for (let i = 0; i < catSelect.options.length; i++) {
                if (catSelect.options[i].getAttribute("data-type") === type) {
                    catSelect.selectedIndex = i;
                    break;
                }
            }
        }

        modal.classList.add("show");
        modal.setAttribute("aria-hidden", "false");
    }
}

function closeTransactionModal() {
    const modal = document.getElementById("transactionModal");
    if (modal) {
        modal.classList.remove("show");
        modal.setAttribute("aria-hidden", "true");
    }
}
</script>
