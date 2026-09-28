<div class="sidebar-overlay" id="sidebarOverlay"></div>
<aside class="sidebar" id="studentSidebar">
    <div class="brand">
        <div class="brand-mark">C</div>
        <div>
            <strong>Campus Coin</strong>
            <span>Student Finance</span>
        </div>
    </div>

    <nav class="side-nav">
        <a href="dashboard.php" class="nav-link <?php echo ($activePage === 'dashboard') ? 'active' : ''; ?>">
            <span class="nav-icon">⌂</span>
            <span>Dashboard</span>
        </a>
        <a href="transactions.php" class="nav-link <?php echo ($activePage === 'transactions') ? 'active' : ''; ?>">
            <span class="nav-icon">↕</span>
            <span>Transactions</span>
        </a>
        <a href="reports.php" class="nav-link <?php echo ($activePage === 'reports') ? 'active' : ''; ?>">
            <span class="nav-icon">▥</span>
            <span>Reports</span>
        </a>
        <a href="savings-goals.php" class="nav-link <?php echo ($activePage === 'goals') ? 'active' : ''; ?>">
            <span class="nav-icon">◇</span>
            <span>Savings goals</span>
        </a>
        <a href="budgets.php" class="nav-link <?php echo ($activePage === 'budgets') ? 'active' : ''; ?>">
            <span class="nav-icon">◎</span>
            <span>Budgets</span>
        </a>
        <a href="categories.php" class="nav-link <?php echo ($activePage === 'categories') ? 'active' : ''; ?>">
            <span class="nav-icon">☷</span>
            <span>Categories</span>
        </a>
        <a href="saving-tips.php" class="nav-link <?php echo ($activePage === 'tips') ? 'active' : ''; ?>">
            <span class="nav-icon">☆</span>
            <span>Saving tips</span>
        </a>
        <a href="profile.php" class="nav-link <?php echo ($activePage === 'profile') ? 'active' : ''; ?>">
            <span class="nav-icon">♙</span>
            <span>Profile</span>
        </a>
        <a class="sidebar-link logout-link" href="logout.php">↪ <span>Logout</span></a>
</nav>

</aside>
