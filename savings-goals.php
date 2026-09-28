<?php
require_once 'config.php';
requireLogin();
$pageTitle = "Savings Goals";
$activePage = "goals";
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
                <span class="bc-current" aria-current="page">Savings Goals</span>
            </nav>

            <div class="page-header">
                <div>
                    <p class="eyebrow">Save With Purpose</p>
                    <h1>Target Savings Goals</h1>
                    <p class="muted">Create custom savings targets (e.g. Laptop, Certifications, Trip) and track incremental progress (SRS 1.6 Page 6).</p>
                </div>
                <button class="primary-btn" type="button" onclick="openGoalModal()">＋ New goal</button>
            </div>
            <div class="goal-grid" id="goalGrid"></div>
        </section>

        <!-- Savings Goal Modal -->
        <div class="transaction-modal" id="goalModal" aria-hidden="true">
            <div class="transaction-modal-box">
                <button class="modal-close" type="button" onclick="closeGoalModal()" aria-label="Close dialog">×</button>
                <p class="eyebrow">Savings Plan</p>
                <h2 id="goalModalTitle">Add savings goal</h2>
                <form id="goalForm" method="POST" action="savings-goals.php">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    <input type="hidden" id="goalId" name="goal_id" value="">

                    <label for="goalName">
                        Goal name
                        <input type="text" id="goalName" name="name" placeholder="e.g. New laptop, Exam fees" required>
                    </label>

                    <label for="goalTarget">
                        Target amount (PKR)
                        <input type="number" id="goalTarget" name="target_amount" min="1" step="0.01" placeholder="e.g. 50000" required>
                    </label>

                    <label for="goalSaved">
                        Current amount saved (PKR)
                        <input type="number" id="goalSaved" name="saved_amount" min="0" step="0.01" placeholder="e.g. 10000" required>
                    </label>

                    <label for="goalTargetDate">
                        Target Completion Date
                        <input type="date" id="goalTargetDate" name="target_date">
                    </label>

                    <label id="goalAddAmountWrap" style="display:none;" for="goalAddAmount">
                        Add to saved amount (PKR)
                        <input type="number" id="goalAddAmount" name="add_amount" min="1" step="0.01" placeholder="e.g. 500">
                    </label>

                    <div class="transaction-form-actions">
                        <button class="cancel-btn" type="button" onclick="closeGoalModal()"><span class="cancel-icon">×</span>Cancel</button>
                        <button class="primary-btn full-btn" type="submit">Save goal</button>
                    </div>
                </form>
            </div>
        </div>
        <?php include "includes/footer.php"; ?>
    </main>
</div>
<script>
    initializeGoalsPage();
</script>
