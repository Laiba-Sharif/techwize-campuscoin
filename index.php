<?php
$pageTitle = "Welcome";
include "includes/header.php";
?>
<div class="landing">
    <nav class="landing-nav">
        <a class="landing-brand" href="index.php"><span class="brand-mark">C</span><strong>Campus Coin</strong></a>
        <div><a href="login.php" class="nav-login">Sign in</a><a href="signup.php" class="primary-btn">Get started</a></div>
    </nav>
    <section class="hero-section">
        <div class="hero-copy">
            <p class="eyebrow">STUDENT FINANCE, SIMPLIFIED</p>
            <h1>Make every rupee<br><em>count.</em></h1>
            <p>Campus Coin helps students understand spending, plan budgets and build better savings habits without the clutter.</p>
            <div class="hero-buttons"><a href="signup.php" class="primary-btn">Create free account</a><a href="login.php" class="text-link">Explore dashboard →</a></div>
        </div>
        <div class="hero-visual">
            <div class="hero-glow"></div>
            <div class="hero-dashboard-card">
                <div class="mini-top"><span>September overview</span><span>●</span></div>
                <strong>Rs. 248.50</strong>
                <span class="mini-muted">available balance</span>
                <div class="mini-bars"><i style="height:50%"></i><i style="height:70%"></i><i style="height:42%"></i><i style="height:85%"></i><i style="height:62%"></i><i style="height:92%"></i></div>
                <div class="mini-bottom"><span>Income <b>Rs. 420</b></span><span>Expenses <b>Rs. 171</b></span></div>
            </div>
        </div>
    </section>
    <section class="landing-features">
        <div><span>01</span><h3>Track spending</h3><p>See where your allowance goes with clean categories.</p></div>
        <div><span>02</span><h3>Plan budgets</h3><p>Set limits for food, travel, academics and more.</p></div>
        <div><span>03</span><h3>Save with purpose</h3><p>Follow your savings goal and monthly progress.</p></div>
    </section>

    <!-- MANDATORY SRS DELIVERABLE: Application Sitemap (SRS 1.9, Page 15) -->
    <section class="landing-sitemap" id="sitemap">
        <div class="sitemap-header">
            <p class="eyebrow">APPLICATION ARCHITECTURE</p>
            <h2>Campus Coin Site Flow & Sitemap</h2>
            <p class="muted">A complete visual directory of all user and administrative workflows.</p>
        </div>
        <div class="sitemap-grid">
            <div class="sitemap-col">
                <h4>1. Public / Authentication</h4>
                <ul>
                    <li><a href="index.php">⌂ Landing Page</a></li>
                    <li><a href="login.php">🔑 Student Sign In</a></li>
                    <li><a href="signup.php">📝 Student Registration</a></li>
                    <li><a href="forgot-password.php">🔄 Password Recovery</a></li>
                    <li><a href="admin/login.php">🛡️ Admin Portal Sign In</a></li>
                </ul>
            </div>
            <div class="sitemap-col">
                <h4>2. Student Core Operations</h4>
                <ul>
                    <li><a href="dashboard.php">📊 Student Dashboard</a></li>
                    <li><a href="transactions.php">↕ Transactions Ledger & CSV</a></li>
                    <li><a href="categories.php">☷ Manage Own Categories</a></li>
                    <li><a href="budgets.php">◎ Monthly Budgets & Alerts</a></li>
                    <li><a href="savings-goals.php">◇ Target Savings Goals</a></li>
                </ul>
            </div>
            <div class="sitemap-col">
                <h4>3. Insights, Reports & Settings</h4>
                <ul>
                    <li><a href="reports.php">▥ Monthly Reports & Export</a></li>
                    <li><a href="saving-tips.php">☆ Personalized Tips & AI Insights</a></li>
                    <li><a href="profile.php">♙ Student Profile & Baseline</a></li>
                    <li><a href="logout.php">↪ Secure Logout</a></li>
                </ul>
            </div>
            <div class="sitemap-col">
                <h4>4. Administration Panel</h4>
                <ul>
                    <li><a href="admin/index.php#users">👥 User Accounts Oversight</a></li>
                    <li><a href="admin/index.php#categories">🏷️ Default Categories CRUD</a></li>
                    <li><a href="admin/index.php#announcements">📢 System Announcements</a></li>
                    <li><a href="admin/index.php#stats">📈 System-Wide Usage Stats</a></li>
                    <li><a href="admin/logout.php">↪ Admin Sign Out</a></li>
                </ul>
            </div>
        </div>
    </section>
</div>
<?php include "includes/footer.php"; ?>

