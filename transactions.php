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
// 1. HANDLE POST ACTIONS (CRUD & CSV IMPORT)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        $error = 'Security validation failed. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? 'save_transaction';

        // ------------------------------------------
        // A. SAVE / UPDATE TRANSACTION
        // ------------------------------------------
        if ($action === 'save_transaction') {
            $transId = (int)($_POST['id'] ?? 0);
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
                // Verify category belongs to defaults or this user
                $catCheck = $pdo->prepare('SELECT category_id FROM categories WHERE category_id = :cid AND (is_default = 1 OR user_id = :uid) LIMIT 1');
                $catCheck->execute([':cid' => $categoryId, ':uid' => $userId]);
                if (!$catCheck->fetch()) {
                    $error = 'Selected category is invalid.';
                } else {
                    if ($transId > 0) {
                        // UPDATE existing transaction (scoped to this student)
                        $updateStmt = $pdo->prepare('
                            UPDATE transactions 
                            SET title = :title, amount = :amount, type = :type, category_id = :cid, date = :date, time = :time, is_recurring = :is_recurring 
                            WHERE transaction_id = :tid AND user_id = :uid
                        ');
                        $updateStmt->execute([
                            ':title'        => $title,
                            ':amount'       => $amount,
                            ':type'         => $type,
                            ':cid'          => $categoryId,
                            ':date'         => $date,
                            ':time'         => $time,
                            ':is_recurring' => $isRecurring,
                            ':tid'          => $transId,
                            ':uid'          => $userId
                        ]);
                        setFlashMessage('success', 'Transaction updated successfully.');
                    } else {
                        // INSERT new transaction
                        $insertStmt = $pdo->prepare('
                            INSERT INTO transactions (user_id, category_id, amount, type, title, is_recurring, date, time)
                            VALUES (:uid, :cid, :amount, :type, :title, :is_recurring, :date, :time)
                        ');
                        $insertStmt->execute([
                            ':uid'          => $userId,
                            ':cid'          => $categoryId,
                            ':amount'       => $amount,
                            ':type'         => $type,
                            ':title'        => $title,
                            ':is_recurring' => $isRecurring,
                            ':date'         => $date,
                            ':time'         => $time
                        ]);

                        if ($isRecurring) {
                            $recStmt = $pdo->prepare('
                                INSERT INTO recurring_transactions (user_id, category_id, title, amount, type, frequency, is_active)
                                VALUES (:uid, :cid, :title, :amount, :type, "monthly", 1)
                            ');
                            $recStmt->execute([
                                ':uid'   => $userId,
                                ':cid'   => $categoryId,
                                ':title' => $title,
                                ':amount'=> $amount,
                                ':type'  => $type
                            ]);
                        }
                        setFlashMessage('success', 'Transaction added successfully.');
                    }
                    header('Location: transactions.php');
                    exit;
                }
            }
        }

        // ------------------------------------------
        // B. DELETE TRANSACTION
        // ------------------------------------------
        elseif ($action === 'delete_transaction') {
            $transId = (int)($_POST['id'] ?? 0);
            if ($transId > 0) {
                $delStmt = $pdo->prepare('DELETE FROM transactions WHERE transaction_id = :tid AND user_id = :uid');
                $delStmt->execute([':tid' => $transId, ':uid' => $userId]);
                setFlashMessage('success', 'Transaction deleted successfully.');
            }
            header('Location: transactions.php');
            exit;
        }

        // ------------------------------------------
        // C. SAVE RECURRING TRANSACTION
        // ------------------------------------------
        elseif ($action === 'save_recurring') {
            $title = trim($_POST['title'] ?? '');
            $amount = filter_var($_POST['amount'] ?? 0, FILTER_VALIDATE_FLOAT);
            $type = in_array($_POST['type'] ?? '', ['income', 'expense']) ? $_POST['type'] : 'expense';

            if (empty($title)) {
                $error = 'Please enter a recurring transaction title.';
            } elseif ($amount === false || $amount <= 0) {
                $error = 'Please enter a valid recurring amount.';
            } else {
                $recStmt = $pdo->prepare('
                    INSERT INTO recurring_transactions (user_id, category_id, title, amount, type, frequency, is_active)
                    VALUES (:uid, NULL, :title, :amount, :type, "monthly", 1)
                ');
                $recStmt->execute([
                    ':uid'   => $userId,
                    ':title' => $title,
                    ':amount'=> $amount,
                    ':type'  => $type
                ]);
                setFlashMessage('success', 'Recurring plan saved successfully.');
                header('Location: transactions.php');
                exit;
            }
        }

        // ------------------------------------------
        // D. DELETE RECURRING TRANSACTION
        // ------------------------------------------
        elseif ($action === 'delete_recurring') {
            $recId = (int)($_POST['id'] ?? 0);
            if ($recId > 0) {
                $delRec = $pdo->prepare('DELETE FROM recurring_transactions WHERE recurring_id = :rid AND user_id = :uid');
                $delRec->execute([':rid' => $recId, ':uid' => $userId]);
                setFlashMessage('success', 'Recurring transaction removed.');
            }
            header('Location: transactions.php');
            exit;
        }

        // ------------------------------------------
        // E. BULK CSV IMPORT PARSER (SRS 1.6 Page 6 & 8)
        // ------------------------------------------
        elseif ($action === 'import_csv') {
            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Please select a valid CSV file to upload.';
            } else {
                $fileTmp = $_FILES['csv_file']['tmp_name'];
                $fileName = $_FILES['csv_file']['name'];
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if ($fileExt !== 'csv') {
                    $error = 'Only .csv files are supported for transaction import.';
                } else {
                    $handle = fopen($fileTmp, 'r');
                    if ($handle === false) {
                        $error = 'Unable to read the uploaded CSV file.';
                    } else {
                        // Fetch all category names mapped to IDs for this student
                        $catMapStmt = $pdo->prepare('SELECT category_id, LOWER(name) as name_lower, type FROM categories WHERE is_default = 1 OR user_id = :uid');
                        $catMapStmt->execute([':uid' => $userId]);
                        $availableCats = $catMapStmt->fetchAll();

                        $expenseDefaultId = 6; // Food as fallback
                        $incomeDefaultId = 1;  // Allowance as fallback
                        foreach ($availableCats as $ac) {
                            if ($ac['name_lower'] === 'miscellaneous') $expenseDefaultId = $ac['category_id'];
                            if ($ac['name_lower'] === 'other income') $incomeDefaultId = $ac['category_id'];
                        }

                        $autoCategorize = isset($_POST['auto_categorize']) ? 1 : 0;
                        $importedCount = 0;
                        $rowIndex = 0;
                        $headerMap = [];

                        $pdo->beginTransaction();

                        $insertTransStmt = $pdo->prepare('
                            INSERT INTO transactions (user_id, category_id, amount, type, title, is_recurring, date, time)
                            VALUES (:uid, :cid, :amount, :type, :title, 0, :date, "12:00:00")
                        ');

                        while (($data = fgetcsv($handle, 2048, ',')) !== false) {
                            $rowIndex++;
                            if (empty(array_filter($data))) continue; // Skip blank lines

                            // Header detection on first line
                            if ($rowIndex === 1) {
                                foreach ($data as $colIdx => $colName) {
                                    $cleanCol = strtolower(trim(preg_replace('/[^a-zA-Z]/', '', $colName)));
                                    $headerMap[$cleanCol] = $colIdx;
                                }
                                continue;
                            }

                            // Extract fields by detected header or by fallback index
                            $txDate = isset($headerMap['date']) ? trim($data[$headerMap['date']] ?? '') : trim($data[0] ?? '');
                            $txTitle = isset($headerMap['title']) ? trim($data[$headerMap['title']] ?? '') : (isset($headerMap['description']) ? trim($data[$headerMap['description']] ?? '') : trim($data[1] ?? ''));
                            $txAmountRaw = isset($headerMap['amount']) ? trim($data[$headerMap['amount']] ?? '') : trim($data[2] ?? '');
                            $txTypeRaw = isset($headerMap['type']) ? strtolower(trim($data[$headerMap['type']] ?? '')) : strtolower(trim($data[3] ?? ''));
                            $txCategoryRaw = isset($headerMap['category']) ? strtolower(trim($data[$headerMap['category']] ?? '')) : strtolower(trim($data[4] ?? ''));

                            // Normalize amount
                            $txAmount = (float)preg_replace('/[^0-9.]/', '', $txAmountRaw);
                            if ($txAmount <= 0) continue;

                            // Normalize date
                            $parsedDate = strtotime($txDate);
                            $txDate = ($parsedDate !== false) ? date('Y-m-d', $parsedDate) : date('Y-m-d');

                            // Normalize type
                            $txType = (strpos($txTypeRaw, 'inc') !== false) ? 'income' : 'expense';

                            // Determine Category ID
                            $categoryId = null;
                            if (!empty($txCategoryRaw)) {
                                foreach ($availableCats as $ac) {
                                    if ($ac['name_lower'] === $txCategoryRaw || strpos($ac['name_lower'], $txCategoryRaw) !== false) {
                                        $categoryId = (int)$ac['category_id'];
                                        break;
                                    }
                                }
                            }

                            // AI / Rule-based batch categorization fallback (SRS 1.6 Page 8)
                            if ($categoryId === null && $autoCategorize) {
                                $haystack = strtolower($txTitle);
                                if (preg_match('/cafe|coffee|lunch|dinner|burger|pizza|biryani|tea|chai|food|canteen|snack/i', $haystack)) {
                                    $categoryId = 6; // Food
                                } elseif (preg_match('/bus|uber|careem|rickshaw|metro|train|petrol|fuel|ticket|transit/i', $haystack)) {
                                    $categoryId = 7; // Transport
                                } elseif (preg_match('/hostel|rent|mess|room|electricity/i', $haystack)) {
                                    $categoryId = 8; // Hostel/Rent
                                } elseif (preg_match('/book|tuition|stationery|copy|pen|exam|course/i', $haystack)) {
                                    $categoryId = 9; // Academics
                                } elseif (preg_match('/netflix|spotify|youtube|cloud|subscription/i', $haystack)) {
                                    $categoryId = 10; // Subscriptions
                                } elseif (preg_match('/movie|cinema|outing|game|bowling/i', $haystack)) {
                                    $categoryId = 11; // Entertainment
                                } elseif (preg_match('/allowance|pocket money|family/i', $haystack)) {
                                    $categoryId = 1; // Allowance
                                    $txType = 'income';
                                } elseif (preg_match('/salary|job|freelance|tutoring/i', $haystack)) {
                                    $categoryId = 2; // Part-time Job
                                    $txType = 'income';
                                }
                            }

                            // Default fallback category if still null
                            if ($categoryId === null) {
                                $categoryId = ($txType === 'income') ? $incomeDefaultId : $expenseDefaultId;
                            }

                            $insertTransStmt->execute([
                                ':uid'    => $userId,
                                ':cid'    => $categoryId,
                                ':amount' => $txAmount,
                                ':type'   => $txType,
                                ':title'  => !empty($txTitle) ? $txTitle : 'Imported Transaction'
                            ]);

                            $importedCount++;
                        }

                        fclose($handle);
                        $pdo->commit();

                        setFlashMessage('success', "Batch import complete! Successfully imported {$importedCount} transactions from CSV.");
                        header('Location: transactions.php');
                        exit;
                    }
                }
            }
        }
    }
}

// ==========================================
// 2. QUERY FILTERS & SEARCH
// ==========================================
$searchQuery = trim($_GET['search'] ?? '');
$filterType = trim($_GET['type'] ?? 'all');
$filterCategory = (int)($_GET['category_id'] ?? 0);
$filterDateFrom = trim($_GET['date_from'] ?? '');
$filterDateTo = trim($_GET['date_to'] ?? '');

$sql = '
    SELECT 
        t.transaction_id,
        t.title,
        t.amount,
        t.type,
        t.date,
        t.time,
        t.is_recurring,
        t.category_id,
        c.name AS category_name,
        c.icon AS category_icon
    FROM transactions t
    JOIN categories c ON t.category_id = c.category_id
    WHERE t.user_id = :user_id
';

$params = [':user_id' => $userId];

if (!empty($searchQuery)) {
    $sql .= ' AND t.title LIKE :search';
    $params[':search'] = '%' . $searchQuery . '%';
}

if ($filterType === 'income' || $filterType === 'expense') {
    $sql .= ' AND t.type = :type';
    $params[':type'] = $filterType;
}

if ($filterCategory > 0) {
    $sql .= ' AND t.category_id = :category_id';
    $params[':category_id'] = $filterCategory;
}

if (!empty($filterDateFrom)) {
    $sql .= ' AND t.date >= :date_from';
    $params[':date_from'] = $filterDateFrom;
}

if (!empty($filterDateTo)) {
    $sql .= ' AND t.date <= :date_to';
    $params[':date_to'] = $filterDateTo;
}

$sql .= ' ORDER BY t.date DESC, t.transaction_id DESC';

$transactionsStmt = $pdo->prepare($sql);
$transactionsStmt->execute($params);
$transactionsList = $transactionsStmt->fetchAll();
$totalTransactionsCount = count($transactionsList);

// ==========================================
// 3. FETCH RECURRING TRANSACTIONS
// ==========================================
$recListStmt = $pdo->prepare('
    SELECT r.recurring_id, r.title, r.amount, r.type, r.frequency, c.name AS category_name, c.icon AS category_icon
    FROM recurring_transactions r
    LEFT JOIN categories c ON r.category_id = c.category_id
    WHERE r.user_id = :user_id AND r.is_active = 1
    ORDER BY r.recurring_id DESC
');
$recListStmt->execute([':user_id' => $userId]);
$recurringList = $recListStmt->fetchAll();

// ==========================================
// 4. FETCH CATEGORIES FOR SELECTORS
// ==========================================
$categoriesStmt = $pdo->prepare('
    SELECT category_id, name, type, icon 
    FROM categories 
    WHERE is_default = 1 OR user_id = :user_id
    ORDER BY type DESC, name ASC
');
$categoriesStmt->execute([':user_id' => $userId]);
$categories = $categoriesStmt->fetchAll();

$pageTitle = "Transactions";
$activePage = "transactions";
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
                <span class="bc-current" aria-current="page">Transactions</span>
            </nav>

            <div class="page-header">
                <div>
                    <p class="eyebrow">Every rupee, organized</p>
                    <h1>Transactions Ledger</h1>
                    <p class="muted">Search, filter, bulk-import and edit your income and expenses (SRS 1.6 Page 7).</p>
                </div>
                <div class="header-action-group">
                    <!-- Bulk CSV Import Button (SRS 1.6 Page 6 & 8) -->
                    <button class="outline-btn" type="button" onclick="openCsvModal()">📥 Import CSV</button>
                    <button class="primary-btn" type="button" onclick="openTransactionModal('expense')">＋ Add transaction</button>
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

            <!-- Enhanced Filter Bar (SRS 1.6 Page 8) -->
            <form class="filter-bar panel" method="GET" action="transactions.php">
                <input type="search" name="search" placeholder="Search by title..." value="<?php echo e($searchQuery); ?>">
                
                <select name="type">
                    <option value="all" <?php echo ($filterType === 'all') ? 'selected' : ''; ?>>All types</option>
                    <option value="income" <?php echo ($filterType === 'income') ? 'selected' : ''; ?>>Income</option>
                    <option value="expense" <?php echo ($filterType === 'expense') ? 'selected' : ''; ?>>Expense</option>
                </select>

                <select name="category_id">
                    <option value="0">All categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo e($cat['category_id']); ?>" <?php echo ($filterCategory === (int)$cat['category_id']) ? 'selected' : ''; ?>>
                            <?php echo e($cat['icon'] . ' ' . $cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div class="filter-date-group">
                    <label for="filterDateFrom" class="sr-only">From</label>
                    <input type="date" id="filterDateFrom" name="date_from" title="Start date" value="<?php echo e($filterDateFrom); ?>">
                    <span class="date-sep">to</span>
                    <label for="filterDateTo" class="sr-only">To</label>
                    <input type="date" id="filterDateTo" name="date_to" title="End date" value="<?php echo e($filterDateTo); ?>">
                </div>

                <button class="primary-btn" type="submit">Filter</button>
                <a href="transactions.php" class="outline-btn" style="text-decoration:none;display:inline-flex;align-items:center;">Clear</a>
            </form>

            <!-- All Transactions Ledger Table (SRS 1.6 Page 7) -->
            <section class="panel">
                <div class="section-heading">
                    <h3>All transactions</h3>
                    <span id="transactionCountLabel"><?php echo $totalTransactionsCount; ?> transaction<?php echo $totalTransactionsCount === 1 ? '' : 's'; ?></span>
                </div>

                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Category</th>
                                <th>Title / Description</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($transactionsList)): ?>
                                <?php foreach ($transactionsList as $tx): 
                                    $isIncome = ($tx['type'] === 'income');
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo date('M d, Y', strtotime($tx['date'])); ?></strong><br>
                                            <small class="muted"><?php echo date('h:i A', strtotime($tx['time'])); ?></small>
                                        </td>
                                        <td>
                                            <span style="font-size:15px;margin-right:4px;"><?php echo e($tx['category_icon']); ?></span>
                                            <?php echo e($tx['category_name']); ?>
                                        </td>
                                        <td>
                                            <strong><?php echo e($tx['title']); ?></strong>
                                            <?php if (!empty($tx['is_recurring'])): ?>
                                                <span class="badge" style="background:#eaf6ee;color:#1e7e34;font-size:10px;margin-left:6px;">Recurring</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $isIncome ? 'active-badge' : ''; ?>" style="<?php echo !$isIncome ? 'background:#ffe3e3;color:#c92a2a;' : ''; ?>">
                                                <?php echo ucfirst($tx['type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong style="font-size:14px;color:<?php echo $isIncome ? '#2b8a3e' : '#c92a2a'; ?>;">
                                                <?php echo ($isIncome ? '+ ' : '- ') . 'Rs. ' . number_format((float)$tx['amount'], 2); ?>
                                            </strong>
                                        </td>
                                        <td style="text-align:right;">
                                            <button class="btn-xs outline-btn" type="button" 
                                                onclick='editTransaction(<?php echo json_encode($tx); ?>)'>
                                                Edit
                                            </button>
                                            <form method="POST" action="transactions.php" style="display:inline-block;" onsubmit="return confirm('Delete this transaction? This action cannot be undone.');">
                                                <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                                                <input type="hidden" name="action" value="delete_transaction">
                                                <input type="hidden" name="id" value="<?php echo e($tx['transaction_id']); ?>">
                                                <button class="btn-xs cancel-btn" type="submit">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align:center;padding:28px 0;" class="muted">
                                        No transactions match your search or filter criteria. Click '＋ Add transaction' or 'Import CSV' to record expenses!
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Recurring Transactions Panel (SRS 1.6 Page 7) -->
            <section class="panel recurring-panel" style="margin-top:24px;">
                <div class="section-heading">
                    <div>
                        <h3>Recurring transactions</h3>
                        <p class="muted">Regular monthly allowance, stipend, or recurring subscriptions (SRS 1.6 Page 7).</p>
                    </div>
                    <button class="outline-btn" type="button" onclick="openRecurringModal()">＋ Add recurring</button>
                </div>

                <div id="recurringList" class="recurring-list">
                    <?php if (!empty($recurringList)): ?>
                        <div class="admin-table-wrap">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Plan Name</th>
                                        <th>Type</th>
                                        <th>Frequency</th>
                                        <th>Amount</th>
                                        <th style="text-align:right;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recurringList as $rec): 
                                        $isInc = ($rec['type'] === 'income');
                                    ?>
                                        <tr>
                                            <td><strong><?php echo e($rec['title']); ?></strong></td>
                                            <td>
                                                <span class="badge <?php echo $isInc ? 'active-badge' : ''; ?>" style="<?php echo !$isInc ? 'background:#ffe3e3;color:#c92a2a;' : ''; ?>">
                                                    <?php echo ucfirst($rec['type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo ucfirst($rec['frequency']); ?></td>
                                            <td><strong style="color:<?php echo $isInc ? '#2b8a3e' : '#c92a2a'; ?>;">Rs. <?php echo number_format((float)$rec['amount'], 2); ?></strong></td>
                                            <td style="text-align:right;">
                                                <form method="POST" action="transactions.php" style="display:inline-block;" onsubmit="return confirm('Remove this recurring reminder?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                                                    <input type="hidden" name="action" value="delete_recurring">
                                                    <input type="hidden" name="id" value="<?php echo e($rec['recurring_id']); ?>">
                                                    <button class="btn-xs cancel-btn" type="submit">Remove</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="muted" style="font-size:13px;padding:8px 0;">No recurring plans established. Click '+ Add recurring' to establish monthly reminders.</p>
                    <?php endif; ?>
                </div>
            </section>
        </section>

        <!-- Transaction Add/Edit Modal -->
        <div class="transaction-modal" id="transactionModal" aria-hidden="true">
            <div class="transaction-modal-box">
                <button class="modal-close" type="button" onclick="closeTransactionModal()" aria-label="Close dialog">×</button>
                <p class="eyebrow" id="transactionModalEyebrow">New transaction</p>
                <h2 id="transactionModalTitle">Add expense</h2>
                <p class="muted">Enter the transaction details below.</p>

                <form id="transactionForm" method="POST" action="transactions.php">
                    <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                    <input type="hidden" name="action" value="save_transaction">
                    <input type="hidden" id="transactionId" name="id" value="">
                    <input type="hidden" id="transactionType" name="type" value="expense">

                    <label for="transactionTitle">
                        Title / Description
                        <input type="text" id="transactionTitle" name="title" placeholder="e.g. Lunch at campus cafe" required autocomplete="off">
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
                            <?php foreach ($categories as $cat): ?>
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

                    <div class="form-check-wrap">
                        <label class="check-label" for="transactionIsRecurring">
                            <input type="checkbox" id="transactionIsRecurring" name="is_recurring" value="1">
                            <span>Repeat monthly (Recurring allowance/subscription)</span>
                        </label>
                    </div>

                    <div class="transaction-form-actions">
                        <button class="cancel-btn" type="button" onclick="closeTransactionModal()"><span class="cancel-icon">×</span>Cancel</button>
                        <button class="primary-btn full-btn" id="transactionSaveButton" type="submit">Save transaction</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Recurring Transaction Modal -->
        <div class="transaction-modal" id="recurringModal" aria-hidden="true">
            <div class="transaction-modal-box">
                <button class="modal-close" type="button" onclick="closeRecurringModal()" aria-label="Close dialog">×</button>
                <p class="eyebrow">Monthly planning</p>
                <h2>Add recurring transaction</h2>
                <p class="muted">Create a monthly plan for regular money movement.</p>
                <form id="recurringForm" method="POST" action="transactions.php">
                    <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                    <input type="hidden" name="action" value="save_recurring">

                    <label for="recurringTitle">Title<input type="text" id="recurringTitle" name="title" placeholder="e.g. Monthly allowance, Netflix" required></label>
                    <label for="recurringAmount">Amount (PKR)<input type="number" id="recurringAmount" name="amount" min="1" step="0.01" required></label>
                    <label for="recurringType">Type
                        <select id="recurringType" name="type">
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                        </select>
                    </label>
                    <div class="transaction-form-actions">
                        <button class="cancel-btn" type="button" onclick="closeRecurringModal()"><span class="cancel-icon">×</span>Cancel</button>
                        <button class="primary-btn full-btn" type="submit">Save recurring</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MANDATORY SRS COMPONENT: CSV Bulk Import Modal (SRS 1.6 Page 6 & 8) -->
        <div class="transaction-modal" id="csvImportModal" aria-hidden="true">
            <div class="transaction-modal-box">
                <button class="modal-close" type="button" onclick="closeCsvModal()" aria-label="Close dialog">×</button>
                <p class="eyebrow">Data Import</p>
                <h2>Import Transactions via CSV</h2>
                <p class="muted">Upload your bank statement or historical transactions CSV file with optional auto-categorization.</p>

                <form id="csvImportForm" method="POST" action="transactions.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                    <input type="hidden" name="action" value="import_csv">

                    <div class="csv-upload-dropzone" onclick="document.getElementById('csvFileInput').click()">
                        <span class="upload-icon">📄</span>
                        <p><strong>Click to browse</strong> or drag & drop your .csv file</p>
                        <small>Expected columns: Date, Title, Amount, Type, Category (optional)</small>
                        <input type="file" id="csvFileInput" name="csv_file" accept=".csv" style="display:none;" onchange="handleCsvFileSelect(this)">
                    </div>

                    <div id="csvFileInfo" class="csv-file-info" style="display:none;margin-top:10px;font-size:12px;background:#eef6f5;padding:8px 12px;border-radius:6px;"></div>

                    <div class="form-check-wrap" style="margin-top:14px;">
                        <label class="check-label" for="autoCategorizeCsv">
                            <input type="checkbox" id="autoCategorizeCsv" name="auto_categorize" value="1" checked>
                            <span>Enable AI/rule-based batch categorization on import</span>
                        </label>
                    </div>

                    <div class="transaction-form-actions">
                        <button class="cancel-btn" type="button" onclick="closeCsvModal()"><span class="cancel-icon">×</span>Cancel</button>
                        <button class="primary-btn full-btn" id="csvUploadBtn" type="submit">Upload & Process</button>
                    </div>
                </form>
            </div>
        </div>

        <?php include "includes/footer.php"; ?>
    </main>
</div>

<script>
function openTransactionModal(type) {
    const modal = document.getElementById("transactionModal");
    const titleEl = document.getElementById("transactionModalTitle");
    const eyebrowEl = document.getElementById("transactionModalEyebrow");
    const idInput = document.getElementById("transactionId");
    const typeInput = document.getElementById("transactionType");
    const titleInput = document.getElementById("transactionTitle");
    const amountInput = document.getElementById("transactionAmount");
    const dateInput = document.getElementById("transactionDate");
    const catSelect = document.getElementById("transactionCategory");
    const recCheck = document.getElementById("transactionIsRecurring");

    if (modal && titleEl && typeInput) {
        idInput.value = "";
        typeInput.value = type;
        eyebrowEl.textContent = "New transaction";
        titleEl.textContent = (type === "income") ? "Add income" : "Add expense";
        titleInput.value = "";
        amountInput.value = "";
        dateInput.value = "<?php echo date('Y-m-d'); ?>";
        if (recCheck) recCheck.checked = false;

        if (catSelect) {
            for (let i = 0; i < catSelect.options.length; i++) {
                const optType = catSelect.options[i].getAttribute("data-type");
                catSelect.options[i].style.display = (optType === type) ? "" : "none";
            }
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

function editTransaction(tx) {
    const modal = document.getElementById("transactionModal");
    const titleEl = document.getElementById("transactionModalTitle");
    const eyebrowEl = document.getElementById("transactionModalEyebrow");
    const idInput = document.getElementById("transactionId");
    const typeInput = document.getElementById("transactionType");
    const titleInput = document.getElementById("transactionTitle");
    const amountInput = document.getElementById("transactionAmount");
    const dateInput = document.getElementById("transactionDate");
    const timeInput = document.getElementById("transactionTime");
    const catSelect = document.getElementById("transactionCategory");
    const recCheck = document.getElementById("transactionIsRecurring");

    if (modal) {
        idInput.value = tx.transaction_id;
        typeInput.value = tx.type;
        eyebrowEl.textContent = "Edit transaction";
        titleEl.textContent = "Update " + (tx.type === "income" ? "Income" : "Expense");
        titleInput.value = tx.title;
        amountInput.value = tx.amount;
        dateInput.value = tx.date;
        timeInput.value = tx.time;
        if (recCheck) recCheck.checked = (tx.is_recurring == 1);

        if (catSelect) {
            for (let i = 0; i < catSelect.options.length; i++) {
                catSelect.options[i].style.display = "";
                if (catSelect.options[i].value == tx.category_id) {
                    catSelect.selectedIndex = i;
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

function openRecurringModal() {
    const modal = document.getElementById("recurringModal");
    if (modal) {
        modal.classList.add("show");
        modal.setAttribute("aria-hidden", "false");
    }
}

function closeRecurringModal() {
    const modal = document.getElementById("recurringModal");
    if (modal) {
        modal.classList.remove("show");
        modal.setAttribute("aria-hidden", "true");
    }
}
</script>
