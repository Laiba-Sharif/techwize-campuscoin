<?php
require_once '../config.php';
requireAdmin();
$pageTitle = 'Admin Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> | Campus Coin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-page">
    <div class="admin-shell">
        <aside class="admin-sidebar" id="adminSidebar">
            <div class="brand">
                <div class="brand-mark">C</div>
                <div>
                    <strong>Campus Coin</strong>
                    <span>Admin Panel</span>
                </div>
            </div>

            <nav class="admin-nav">
                <a class="active" href="index.php"><span>⌂</span> Dashboard</a>
                <a href="#users"><span>♙</span> Users</a>
                <a href="#categories"><span>☷</span> Default Categories</a>
                <a href="#announcements"><span>📢</span> Announcements & Tips</a>
                <a href="#transactions"><span>↕</span> Transactions</a>
                <a href="#stats"><span>📈</span> Usage Stats</a>
            </nav>

            <div class="admin-sidebar-bottom">
                <a href="../dashboard.php">← Student dashboard</a>
                <a href="logout.php">↪ Logout</a>
            </div>
        </aside>

        <main class="admin-main">
            <header class="admin-topbar">
                <div class="admin-top-left">
                    <button class="mobile-menu-btn" id="adminMobileMenuBtn" type="button" aria-label="Toggle navigation">☰</button>
                    <div>
                        <p class="eyebrow">Campus Coin Administration</p>
                        <h1>Admin Command Center 👋</h1>
                        <p class="muted">System oversight: users, default categories, announcements & usage metrics.</p>
                    </div>
                </div>
                <div class="admin-top-actions">
                    <span class="admin-date" id="adminDateTime"></span>
                    <button class="icon-btn" id="themeToggle" type="button" title="Toggle theme">◐</button>
                    <div class="admin-avatar"><?php echo e(strtoupper(substr($_SESSION['user_name'] ?? 'AD', 0, 2))); ?></div>
                </div>
            </header>

            <section class="admin-content">
                <!-- Usage Statistics (SRS 1.6 Page 9) -->
                <div class="admin-stat-grid" id="stats">
                    <article class="admin-stat-card">
                        <span>Total Registered Users</span>
                        <strong id="adminUserCount">1</strong>
                        <small>Active profiles</small>
                    </article>
                    <article class="admin-stat-card">
                        <span>Total Transactions Logged</span>
                        <strong id="adminTransactionCount">0</strong>
                        <small>Across all students</small>
                    </article>
                    <article class="admin-stat-card">
                        <span>System Total Income</span>
                        <strong id="adminIncomeTotal">Rs. 0</strong>
                        <small>Student cashflow</small>
                    </article>
                    <article class="admin-stat-card">
                        <span>System Total Expenses</span>
                        <strong id="adminExpenseTotal">Rs. 0</strong>
                        <small>Student spending</small>
                    </article>
                </div>

                <!-- Users Oversight (SRS 1.6 Page 9: view, disable, reset) -->
                <div class="admin-panel-grid">
                    <section class="admin-panel" id="users">
                        <div class="section-heading">
                            <div>
                                <h2>Student Accounts</h2>
                                <span>View, disable, or reset user access (SRS 1.6 Page 9)</span>
                            </div>
                        </div>
                        <div class="admin-table-wrap">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Academic Year</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="adminUsersTable">
                                    <tr>
                                        <td><strong>Ayesha Khan</strong></td>
                                        <td>ayesha@example.com</td>
                                        <td>2nd Year</td>
                                        <td><span class="badge active-badge">Active</span></td>
                                        <td>
                                            <button class="btn-xs outline-btn" onclick="toggleUserStatus(1, this)">Disable</button>
                                            <button class="btn-xs cancel-btn" onclick="resetUserPassword(1)">Reset Password</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- Most-Used Categories & System Intelligence (SRS 1.6 Page 9) -->
                    <section class="admin-panel">
                        <div class="section-heading">
                            <div>
                                <h2>Most-Used Categories</h2>
                                <span>Platform popularity index</span>
                            </div>
                        </div>
                        <div class="admin-category-ranking" id="adminCategoryStats">
                            <div class="stat-progress-row">
                                <span>1. Food & Canteen</span>
                                <div class="progress-track"><span style="width: 78%;"></span></div>
                                <strong>78%</strong>
                            </div>
                            <div class="stat-progress-row">
                                <span>2. Transport & Bus Pass</span>
                                <div class="progress-track"><span style="width: 52%;"></span></div>
                                <strong>52%</strong>
                            </div>
                            <div class="stat-progress-row">
                                <span>3. Books & Stationery</span>
                                <div class="progress-track"><span style="width: 41%;"></span></div>
                                <strong>41%</strong>
                            </div>
                            <div class="stat-progress-row">
                                <span>4. Subscriptions & Tech</span>
                                <div class="progress-track"><span style="width: 30%;"></span></div>
                                <strong>30%</strong>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Default Categories Management (SRS 1.6 Page 9) -->
                <section class="admin-panel" id="categories" style="margin-top:24px;">
                    <div class="section-heading">
                        <div>
                            <h2>Default System Categories</h2>
                            <span>Categories automatically available to all registered students (SRS 1.6 Page 9)</span>
                        </div>
                        <button class="primary-btn" onclick="openAdminCategoryModal()">＋ Add Default Category</button>
                    </div>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr><th>Category Name</th><th>Type</th><th>Availability</th><th>Actions</th></tr>
                            </thead>
                            <tbody id="adminDefaultCategoriesTable">
                                <tr><td>Food</td><td><span class="badge">Expense</span></td><td>System Default</td><td><button class="btn-xs outline-btn" onclick="editDefaultCategory('Food')">Edit</button></td></tr>
                                <tr><td>Transport</td><td><span class="badge">Expense</span></td><td>System Default</td><td><button class="btn-xs outline-btn" onclick="editDefaultCategory('Transport')">Edit</button></td></tr>
                                <tr><td>Allowance</td><td><span class="badge active-badge">Income</span></td><td>System Default</td><td><button class="btn-xs outline-btn" onclick="editDefaultCategory('Allowance')">Edit</button></td></tr>
                                <tr><td>Part-time Job</td><td><span class="badge active-badge">Income</span></td><td>System Default</td><td><button class="btn-xs outline-btn" onclick="editDefaultCategory('Part-time Job')">Edit</button></td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- System-Wide Announcements & Tip Templates (SRS 1.6 Page 9) -->
                <section class="admin-panel" id="announcements" style="margin-top:24px;">
                    <div class="section-heading">
                        <div>
                            <h2>System-Wide Announcements & Tip Templates</h2>
                            <span>Publish broadcast notifications or saving tips templates to students (SRS 1.6 Page 9)</span>
                        </div>
                        <button class="primary-btn" onclick="openAnnouncementModal()">＋ New Announcement / Tip Template</button>
                    </div>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr><th>Title</th><th>Type</th><th>Preview</th><th>Target Audience</th><th>Actions</th></tr>
                            </thead>
                            <tbody id="adminAnnouncementsTable">
                                <tr>
                                    <td><strong>Semester Budget Kickoff</strong></td>
                                    <td><span class="badge">Announcement</span></td>
                                    <td>Remember to log your semester textbook allowance early!</td>
                                    <td>All Students</td>
                                    <td><button class="btn-xs cancel-btn" onclick="deleteAnnouncement(1, this)">Delete</button></td>
                                </tr>
                                <tr>
                                    <td><strong>Canteen Cap Suggestion</strong></td>
                                    <td><span class="badge active-badge">Tip Template</span></td>
                                    <td>Set a weekly food limit to prevent mid-month deficits.</td>
                                    <td>Budget Alert</td>
                                    <td><button class="btn-xs cancel-btn" onclick="deleteAnnouncement(2, this)">Delete</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Transactions Overview Table -->
                <section class="admin-panel" id="transactions" style="margin-top:24px;">
                    <div class="section-heading">
                        <div>
                            <h2>Recent System Transactions</h2>
                            <span>Global activity stream across student ledgers</span>
                        </div>
                    </div>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr><th>Title</th><th>Type</th><th>Amount</th><th>Date</th></tr>
                            </thead>
                            <tbody id="adminTransactionsTable"></tbody>
                        </table>
                    </div>
                </section>
            </section>
        </main>
    </div>

    <!-- Admin Category Modal -->
    <div class="transaction-modal" id="adminCategoryModal" aria-hidden="true">
        <div class="transaction-modal-box">
            <button class="modal-close" type="button" onclick="closeAdminCategoryModal()" aria-label="Close dialog">×</button>
            <p class="eyebrow">Category Master</p>
            <h2>Add Default Category</h2>
            <form id="adminCategoryForm" onsubmit="handleSaveAdminCategory(event)">
                <label for="adminCatName">Category Name
                    <input type="text" id="adminCatName" required placeholder="e.g. Scholarship, Healthcare">
                </label>
                <label for="adminCatType">Category Type
                    <select id="adminCatType">
                        <option value="expense">Expense</option>
                        <option value="income">Income</option>
                    </select>
                </label>
                <div class="transaction-form-actions">
                    <button class="cancel-btn" type="button" onclick="closeAdminCategoryModal()"><span class="cancel-icon">×</span>Cancel</button>
                    <button class="primary-btn full-btn" type="submit">Save Default Category</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Admin Announcement Modal -->
    <div class="transaction-modal" id="adminAnnouncementModal" aria-hidden="true">
        <div class="transaction-modal-box">
            <button class="modal-close" type="button" onclick="closeAnnouncementModal()" aria-label="Close dialog">×</button>
            <p class="eyebrow">Broadcast System</p>
            <h2>Create Announcement / Tip Template</h2>
            <form id="adminAnnouncementForm" onsubmit="handleSaveAnnouncement(event)">
                <label for="announceTitle">Title
                    <input type="text" id="announceTitle" required placeholder="e.g. Exam Season Budget Alert">
                </label>
                <label for="announceType">Type
                    <select id="announceType">
                        <option value="announcement">System Announcement</option>
                        <option value="tip_template">Tip Template</option>
                    </select>
                </label>
                <label for="announceBody">Message Content
                    <textarea id="announceBody" rows="3" required placeholder="Broadcast message or template text for students..."></textarea>
                </label>
                <div class="transaction-form-actions">
                    <button class="cancel-btn" type="button" onclick="closeAnnouncementModal()"><span class="cancel-icon">×</span>Cancel</button>
                    <button class="primary-btn full-btn" type="submit">Publish to Students</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
