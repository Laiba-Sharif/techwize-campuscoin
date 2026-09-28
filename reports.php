<?php
require_once 'config.php';
requireLogin();
$pageTitle = "Monthly Reports";
$activePage = "reports";
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
                <span class="bc-current" aria-current="page">Reports & Summaries</span>
            </nav>

            <div class="page-header">
                <div>
                    <p class="eyebrow">Financial Analytics</p>
                    <h1>Monthly Reports & Summaries</h1>
                    <p class="muted">Deep dive into your income vs. expense habits, daily patterns, and category distributions.</p>
                </div>
                <div class="button-row">
                    <!-- Export as PDF or Image, and Share by Email (SRS 1.6 Page 8) -->
                    <button class="outline-btn" type="button" onclick="exportTransactionsCSV()">CSV</button>
                    <button class="outline-btn" type="button" onclick="openShareEmailModal()">✉ Share Email</button>
                    <button class="primary-btn" type="button" onclick="window.print()">🖨 Export / Print PDF</button>
                </div>
            </div>

            <!-- MANDATORY SRS FEATURE: Comprehensive Report Filters (SRS 1.6 Page 8) -->
            <form id="reportFilterForm" class="filter-bar panel" method="GET" action="reports.php" onsubmit="applyReportFilters(event)">
                <div class="filter-item">
                    <label for="reportDateFrom">From Date</label>
                    <input type="date" id="reportDateFrom" name="date_from" value="<?php echo date('Y-m-01'); ?>">
                </div>

                <div class="filter-item">
                    <label for="reportDateTo">To Date</label>
                    <input type="date" id="reportDateTo" name="date_to" value="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="filter-item">
                    <label for="reportCategory">Category</label>
                    <select id="reportCategory" name="category_id">
                        <option value="all">All Categories</option>
                    </select>
                </div>

                <div class="filter-item">
                    <label for="reportType">Type / Source</label>
                    <select id="reportType" name="type">
                        <option value="all">All Types & Sources</option>
                        <option value="income">Income Only</option>
                        <option value="expense">Expense Only</option>
                    </select>
                </div>

                <div class="filter-actions-inline">
                    <button class="primary-btn" type="submit">Filter</button>
                    <button class="outline-btn" type="button" onclick="resetReportFilters()">Reset</button>
                </div>
            </form>

            <div class="stats-row">
                <div class="stat-card">
                    <span>Total Income</span>
                    <strong id="reportIncome">Rs. 0.00</strong>
                    <small>Filtered period</small>
                </div>
                <div class="stat-card">
                    <span>Total Expenses</span>
                    <strong id="reportExpense">Rs. 0.00</strong>
                    <small id="reportExpenseCount">0 transactions</small>
                </div>
                <div class="stat-card">
                    <span>Net Savings</span>
                    <strong id="reportBalance">Rs. 0.00</strong>
                    <small id="reportSavings">0% of income saved</small>
                </div>
            </div>

            <!-- MANDATORY SRS FEATURE: Daily & Weekly Spending Summaries for Current Month (SRS 1.6 Page 8) -->
            <div class="report-summary-cards">
                <section class="panel">
                    <div class="section-heading">
                        <div>
                            <h3>Daily Spending Pace</h3>
                            <span>Current month breakdown</span>
                        </div>
                        <span class="badge" id="dailyAvgBadge">Rs. 0.00 / day avg</span>
                    </div>
                    <div class="summary-pace-row">
                        <div class="pace-box">
                            <span class="pace-label">Today's Spend</span>
                            <strong id="todaySpend">Rs. 0.00</strong>
                        </div>
                        <div class="pace-box">
                            <span class="pace-label">Highest Day</span>
                            <strong id="highestDaySpend">Rs. 0.00</strong>
                            <small id="highestDayDate">-</small>
                        </div>
                        <div class="pace-box">
                            <span class="pace-label">Days Tracked</span>
                            <strong id="daysTrackedCount"><?php echo date('j'); ?> days</strong>
                        </div>
                    </div>
                </section>

                <section class="panel">
                    <div class="section-heading">
                        <div>
                            <h3>Weekly Spending Breakdown</h3>
                            <span>Weeks 1 to 4 of <?php echo date('F Y'); ?></span>
                        </div>
                    </div>
                    <div class="weekly-progress-grid" id="weeklySpendingGrid">
                        <div class="weekly-item"><span>Week 1 (1st-7th)</span><div class="progress-track"><span style="width:25%"></span></div><strong>Rs. 0</strong></div>
                        <div class="weekly-item"><span>Week 2 (8th-14th)</span><div class="progress-track"><span style="width:35%"></span></div><strong>Rs. 0</strong></div>
                        <div class="weekly-item"><span>Week 3 (15th-21st)</span><div class="progress-track"><span style="width:20%"></span></div><strong>Rs. 0</strong></div>
                        <div class="weekly-item"><span>Week 4 (22nd-End)</span><div class="progress-track"><span style="width:15%"></span></div><strong>Rs. 0</strong></div>
                    </div>
                </section>
            </div>

            <div class="content-grid">
                <section class="panel">
                    <div class="section-heading">
                        <h3>Monthly Cash Flow</h3>
                        <span>Last 6 months trend (SRS Section 1.6)</span>
                    </div>
                    <canvas id="reportChart"></canvas>
                </section>

                <section class="panel">
                    <div class="section-heading">
                        <h3>Expense Breakdown</h3>
                        <span>Category proportion</span>
                    </div>
                    <div class="breakdown" id="reportBreakdown"></div>
                </section>
            </div>
        </section>

        <!-- Share Report by Email Modal (SRS 1.6 Page 8) -->
        <div class="transaction-modal" id="shareEmailModal" aria-hidden="true">
            <div class="transaction-modal-box">
                <button class="modal-close" type="button" onclick="closeShareEmailModal()" aria-label="Close dialog">×</button>
                <p class="eyebrow">Share Report</p>
                <h2>Email Spending Summary</h2>
                <p class="muted">Send a snapshot of your monthly financial report to yourself or a parent/guardian.</p>
                
                <form id="shareEmailForm" method="POST" action="reports.php" onsubmit="handleSendReportEmail(event)">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    <input type="hidden" name="action" value="share_email">

                    <label for="shareEmailTo">
                        Recipient Email
                        <input type="email" id="shareEmailTo" name="recipient_email" placeholder="parent@example.com" required>
                    </label>

                    <label for="shareEmailNote">
                        Personal Note (Optional)
                        <textarea id="shareEmailNote" name="note" rows="3" placeholder="Here is my expense report for this month..."></textarea>
                    </label>

                    <div class="transaction-form-actions">
                        <button class="cancel-btn" type="button" onclick="closeShareEmailModal()"><span class="cancel-icon">×</span>Cancel</button>
                        <button class="primary-btn full-btn" type="submit">Send Summary</button>
                    </div>
                </form>
            </div>
        </div>

        <?php include "includes/footer.php"; ?>
    </main>
</div>
<script>
    initializeReportsPage();
</script>
