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
// 1. HANDLE CATEGORY CRUD (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        $error = 'Security validation failed. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? 'save_category';

        try {
            // ------------------------------------------
            // A. SAVE OR UPDATE PERSONAL CATEGORY
            // ------------------------------------------
            if ($action === 'save_category') {
                $catId = (int)($_POST['category_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $type = in_array($_POST['type'] ?? '', ['income', 'expense']) ? $_POST['type'] : 'expense';

                if (empty($name) || mb_strlen($name) < 2 || mb_strlen($name) > 40) {
                    $error = 'Please enter a valid category name (2–40 characters).';
                } else {
                    // Check if a category with this name already exists for this student or as default
                    $dupStmt = $pdo->prepare('
                        SELECT category_id FROM categories 
                        WHERE LOWER(name) = LOWER(:name) AND type = :type 
                          AND (is_default = 1 OR user_id = :uid)
                          AND category_id != :cid
                        LIMIT 1
                    ');
                    $dupStmt->execute([
                        ':name' => $name,
                        ':type' => $type,
                        ':uid'  => $userId,
                        ':cid'  => $catId
                    ]);

                    if ($dupStmt->fetch()) {
                        $error = 'A category with this name already exists in ' . ucfirst($type) . ' categories.';
                    } else {
                        if ($catId > 0) {
                            // UPDATE personal category (must belong to this student and not be system default)
                            $updateStmt = $pdo->prepare('
                                UPDATE categories 
                                SET name = :name, type = :type 
                                WHERE category_id = :cid AND user_id = :uid AND is_default = 0
                            ');
                            $updateStmt->execute([
                                ':name' => $name,
                                ':type' => $type,
                                ':cid'  => $catId,
                                ':uid'  => $userId
                            ]);
                            setFlashMessage('success', 'Personal category updated successfully.');
                        } else {
                            // INSERT new personal category
                            $icon = ($type === 'income') ? '💰' : '🏷️';
                            $insertStmt = $pdo->prepare('
                                INSERT INTO categories (user_id, name, type, is_default, icon)
                                VALUES (:uid, :name, :type, 0, :icon)
                            ');
                            $insertStmt->execute([
                                ':uid'  => $userId,
                                ':name' => $name,
                                ':type' => $type,
                                ':icon' => $icon
                            ]);
                            setFlashMessage('success', 'Custom category created successfully.');
                        }
                        header('Location: categories.php');
                        exit;
                    }
                }
            }

            // ------------------------------------------
            // B. DELETE PERSONAL CATEGORY (SRS 1.6 Page 7)
            // ------------------------------------------
            elseif ($action === 'delete_category') {
                $catId = (int)($_POST['category_id'] ?? 0);
                if ($catId > 0) {
                    // Verify it belongs to this student and is NOT a system default
                    $checkStmt = $pdo->prepare('SELECT type FROM categories WHERE category_id = :cid AND user_id = :uid AND is_default = 0 LIMIT 1');
                    $checkStmt->execute([':cid' => $catId, ':uid' => $userId]);
                    $cat = $checkStmt->fetch();

                    if ($cat) {
                        // Check if any transactions are linked to this category
                        $txCountStmt = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE category_id = :cid AND user_id = :uid');
                        $txCountStmt->execute([':cid' => $catId, ':uid' => $userId]);
                        $txCount = (int)$txCountStmt->fetchColumn();

                        if ($txCount > 0) {
                            // Reassign linked transactions to Miscellaneous (12) or Other Income (5) to preserve history
                            $fallbackId = ($cat['type'] === 'income') ? 5 : 12;
                            $reassignStmt = $pdo->prepare('UPDATE transactions SET category_id = :fallback WHERE category_id = :cid AND user_id = :uid');
                            $reassignStmt->execute([':fallback' => $fallbackId, ':cid' => $catId, ':uid' => $userId]);
                        }

                        // Delete the personal category
                        $delStmt = $pdo->prepare('DELETE FROM categories WHERE category_id = :cid AND user_id = :uid AND is_default = 0');
                        $delStmt->execute([':cid' => $catId, ':uid' => $userId]);

                        setFlashMessage('success', 'Personal category removed. Linked transaction history has been preserved.');
                    } else {
                        setFlashMessage('error', 'Default system categories cannot be deleted.');
                    }
                }
                header('Location: categories.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log('Category action database error: ' . $e->getMessage());
            $error = 'A database error occurred while processing your request. Please try again.';
        }
    }
}

// ==========================================
// 2. QUERY FILTERS & SEARCH
// ==========================================
$searchQuery = trim($_GET['search'] ?? '');
$filterType = trim($_GET['type'] ?? 'all');

// ==========================================
// 3. FETCH PERSONAL CATEGORIES
// ==========================================
$personalCategories = [];
try {
    $personalSql = '
        SELECT 
            c.category_id, c.name, c.type, c.icon, c.is_default,
            COUNT(t.transaction_id) AS tx_count,
            COALESCE(SUM(t.amount), 0) AS total_amount
        FROM categories c
        LEFT JOIN transactions t ON t.category_id = c.category_id AND t.user_id = :tx_uid
        WHERE c.is_default = 0 AND c.user_id = :cat_uid
    ';
    $personalParams = [
        ':tx_uid'  => $userId,
        ':cat_uid' => $userId
    ];

    if (!empty($searchQuery)) {
        $personalSql .= ' AND c.name LIKE :p_search';
        $personalParams[':p_search'] = '%' . $searchQuery . '%';
    }

    if ($filterType === 'income' || $filterType === 'expense') {
        $personalSql .= ' AND c.type = :p_type';
        $personalParams[':p_type'] = $filterType;
    }

    $personalSql .= '
        GROUP BY c.category_id, c.name, c.type, c.icon, c.is_default
        ORDER BY c.category_id DESC
    ';

    $personalStmt = $pdo->prepare($personalSql);
    $personalStmt->execute($personalParams);
    $personalCategories = $personalStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Database error fetching personal categories: ' . $e->getMessage());
    $error = 'Unable to load personal categories at this time.';
}

// ==========================================
// 4. FETCH SYSTEM DEFAULT CATEGORIES (SRS Page 7)
// ==========================================
$defaultCategories = [];
try {
    $defaultSql = '
        SELECT 
            c.category_id, c.name, c.type, c.icon, c.is_default,
            COUNT(t.transaction_id) AS tx_count,
            COALESCE(SUM(t.amount), 0) AS total_amount
        FROM categories c
        LEFT JOIN transactions t ON t.category_id = c.category_id AND t.user_id = :tx_uid
        WHERE c.is_default = 1
    ';
    $defaultParams = [':tx_uid' => $userId];

    if (!empty($searchQuery)) {
        $defaultSql .= ' AND c.name LIKE :d_search';
        $defaultParams[':d_search'] = '%' . $searchQuery . '%';
    }

    if ($filterType === 'income' || $filterType === 'expense') {
        $defaultSql .= ' AND c.type = :d_type';
        $defaultParams[':d_type'] = $filterType;
    }

    $defaultSql .= '
        GROUP BY c.category_id, c.name, c.type, c.icon, c.is_default
        ORDER BY c.type DESC, c.name ASC
    ';

    $defaultStmt = $pdo->prepare($defaultSql);
    $defaultStmt->execute($defaultParams);
    $defaultCategories = $defaultStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Database error fetching default categories: ' . $e->getMessage());
    $error = 'Unable to load system default categories at this time.';
}

$pageTitle = "Manage Categories";
$activePage = "categories";
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
                <span class="bc-current" aria-current="page">Manage Own Categories</span>
            </nav>

            <div class="page-header">
                <div>
                    <p class="eyebrow">Personalized Financial Structure</p>
                    <h1>Manage Own Categories</h1>
                    <p class="muted">Add custom categories for gigs, freelance, hobbies, or specific campus clubs (SRS 1.6 Page 7).</p>
                </div>
                <button class="primary-btn" type="button" onclick="openAddCategoryModal()">
                    ＋ Add category
                </button>
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

            <!-- Search & Filter Controls -->
            <form class="filter-bar panel" method="GET" action="categories.php" style="margin-bottom:24px;">
                <input type="search" name="search" placeholder="Search categories..." value="<?php echo e($searchQuery); ?>">
                
                <select name="type">
                    <option value="all" <?php echo ($filterType === 'all') ? 'selected' : ''; ?>>All types</option>
                    <option value="income" <?php echo ($filterType === 'income') ? 'selected' : ''; ?>>Income</option>
                    <option value="expense" <?php echo ($filterType === 'expense') ? 'selected' : ''; ?>>Expense</option>
                </select>

                <button class="primary-btn" type="submit">Filter</button>
                <?php if (!empty($searchQuery) || $filterType !== 'all'): ?>
                    <a href="categories.php" class="outline-btn" style="text-decoration:none;display:inline-flex;align-items:center;">Clear</a>
                <?php endif; ?>
            </form>

            <!-- SECTION 1: Personal User-Created Categories (SRS 1.6 Page 7) -->
            <section class="panel" style="margin-bottom:28px;">
                <div class="section-heading">
                    <div>
                        <h3>My Personal Categories</h3>
                        <span>Custom categories created specifically for your account</span>
                    </div>
                    <span class="badge" style="background:#e5f1ef;color:var(--teal);font-weight:600;">
                        <?php echo count($personalCategories); ?> Custom Categories
                    </span>
                </div>

                <div class="category-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(250px, 1fr));gap:16px;margin-top:14px;">
                    <?php if (!empty($personalCategories)): ?>
                        <?php foreach ($personalCategories as $pcat): 
                            $isInc = ($pcat['type'] === 'income');
                        ?>
                            <div class="category-card panel" style="background:var(--white);border:1px solid var(--line);border-radius:14px;padding:18px;position:relative;">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <span style="font-size:22px;background:var(--teal-soft);width:40px;height:40px;display:grid;place-items:center;border-radius:10px;">
                                            <?php echo e($pcat['icon']); ?>
                                        </span>
                                        <div>
                                            <strong style="font-size:15px;display:block;"><?php echo e($pcat['name']); ?></strong>
                                            <span class="badge <?php echo $isInc ? 'active-badge' : ''; ?>" style="font-size:10px;<?php echo !$isInc ? 'background:#ffe3e3;color:#c92a2a;' : ''; ?>">
                                                <?php echo ucfirst($pcat['type']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <span class="badge" style="background:#f4f4f4;color:#666;font-size:10px;">Custom</span>
                                </div>

                                <div style="border-top:1px solid var(--line);padding-top:12px;margin-top:10px;display:flex;justify-content:space-between;align-items:center;font-size:12px;">
                                    <span class="muted"><?php echo $pcat['tx_count']; ?> logged</span>
                                    <strong>Rs. <?php echo number_format((float)$pcat['total_amount'], 2); ?></strong>
                                </div>

                                <div style="display:flex;gap:8px;margin-top:12px;">
                                    <button class="btn-xs outline-btn" style="flex:1;" type="button" 
                                        onclick='editCategory(<?php echo json_encode($pcat); ?>)'>
                                        Edit
                                    </button>
                                    <form method="POST" action="categories.php" style="flex:1;" onsubmit="return confirm('Delete this personal category? Linked transactions will be safely reassigned to Miscellaneous/Other Income.');">
                                        <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="category_id" value="<?php echo e($pcat['category_id']); ?>">
                                        <button class="btn-xs cancel-btn" style="width:100%;" type="submit">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="grid-column:1 / -1;text-align:center;padding:24px 0;" class="muted">
                            <?php if (!empty($searchQuery) || $filterType !== 'all'): ?>
                                <p>No personal categories matched your search/filter criteria.</p>
                            <?php else: ?>
                                <p>You haven't created any personal categories yet.</p>
                                <button class="outline-btn" style="margin-top:10px;" type="button" onclick="openAddCategoryModal()">＋ Create First Category</button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- SECTION 2: System Default Categories (SRS 1.6 Page 7) -->
            <section class="panel">
                <div class="section-heading">
                    <div>
                        <h3>System Default Categories</h3>
                        <span>Standard student categories available across Campus Coin (SRS 1.6 Page 7)</span>
                    </div>
                    <span class="badge" style="background:#eaf6ee;color:#1e7e34;font-weight:600;">
                        <?php echo count($defaultCategories); ?> Standard
                    </span>
                </div>

                <div class="category-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(250px, 1fr));gap:16px;margin-top:14px;">
                    <?php if (!empty($defaultCategories)): ?>
                        <?php foreach ($defaultCategories as $dcat): 
                            $isInc = ($dcat['type'] === 'income');
                        ?>
                            <div class="category-card panel" style="background:var(--white);border:1px solid var(--line);border-radius:14px;padding:16px;">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <span style="font-size:20px;background:#f8f9fa;width:38px;height:38px;display:grid;place-items:center;border-radius:10px;">
                                            <?php echo e($dcat['icon']); ?>
                                        </span>
                                        <div>
                                            <strong style="font-size:14px;display:block;"><?php echo e($dcat['name']); ?></strong>
                                            <span class="badge <?php echo $isInc ? 'active-badge' : ''; ?>" style="font-size:10px;<?php echo !$isInc ? 'background:#ffe3e3;color:#c92a2a;' : ''; ?>">
                                                <?php echo ucfirst($dcat['type']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <span class="badge" style="background:#e8f4f2;color:var(--teal);font-size:10px;">Default</span>
                                </div>

                                <div style="border-top:1px solid var(--line);padding-top:10px;margin-top:8px;display:flex;justify-content:space-between;align-items:center;font-size:12px;">
                                    <span class="muted"><?php echo $dcat['tx_count']; ?> transactions</span>
                                    <strong>Rs. <?php echo number_format((float)$dcat['total_amount'], 2); ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="grid-column:1 / -1;text-align:center;padding:24px 0;" class="muted">
                            <p>No system default categories matched your search/filter criteria.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </section>

        <!-- Category Add/Edit Modal (SRS 1.6 Page 7) -->
        <div class="transaction-modal" id="categoryModal" aria-hidden="true">
            <div class="transaction-modal-box category-modal-box">
                <button class="modal-close" type="button" onclick="closeCategoryModal()" aria-label="Close dialog">×</button>

                <p class="eyebrow" id="categoryModalEyebrow">Personalize your budget</p>
                <h2 id="categoryModalTitle">Add category</h2>
                <p class="muted" id="categoryModalDesc">Choose whether this category is for income or expenses.</p>

                <form id="categoryForm" method="POST" action="categories.php">
                    <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                    <input type="hidden" name="action" value="save_category">
                    <input type="hidden" id="categoryId" name="category_id" value="">

                    <label for="categoryName">
                        Category name
                        <input type="text" id="categoryName" name="name" placeholder="e.g. Freelance, Photography, Gym" maxlength="40" required autocomplete="off">
                    </label>

                    <label for="categoryType">
                        Category type
                        <select id="categoryType" name="type" required>
                            <option value="expense">Expense</option>
                            <option value="income">Income</option>
                        </select>
                    </label>

                    <div class="transaction-form-actions">
                        <button class="cancel-btn" type="button" onclick="closeCategoryModal()">
                            <span class="cancel-icon">×</span>
                            Cancel
                        </button>
                        <button class="primary-btn full-btn" id="categorySaveBtn" type="submit">Save category</button>
                    </div>
                </form>
            </div>
        </div>

        <?php include "includes/footer.php"; ?>
    </main>
</div>

<script>
function openAddCategoryModal() {
    const modal = document.getElementById("categoryModal");
    const titleEl = document.getElementById("categoryModalTitle");
    const eyebrowEl = document.getElementById("categoryModalEyebrow");
    const idInput = document.getElementById("categoryId");
    const nameInput = document.getElementById("categoryName");
    const typeSelect = document.getElementById("categoryType");

    if (modal) {
        idInput.value = "";
        nameInput.value = "";
        typeSelect.value = "expense";
        eyebrowEl.textContent = "Personalize your budget";
        titleEl.textContent = "Add personal category";

        modal.classList.add("show");
        modal.setAttribute("aria-hidden", "false");
    }
}

function editCategory(cat) {
    const modal = document.getElementById("categoryModal");
    const titleEl = document.getElementById("categoryModalTitle");
    const eyebrowEl = document.getElementById("categoryModalEyebrow");
    const idInput = document.getElementById("categoryId");
    const nameInput = document.getElementById("categoryName");
    const typeSelect = document.getElementById("categoryType");

    if (modal) {
        idInput.value = cat.category_id;
        nameInput.value = cat.name;
        typeSelect.value = cat.type;
        eyebrowEl.textContent = "Update category";
        titleEl.textContent = "Edit '" + cat.name + "'";

        modal.classList.add("show");
        modal.setAttribute("aria-hidden", "false");
    }
}

function closeCategoryModal() {
    const modal = document.getElementById("categoryModal");
    if (modal) {
        modal.classList.remove("show");
        modal.setAttribute("aria-hidden", "true");
    }
}
</script>
