<header class="topbar">
    <button class="mobile-menu-btn" id="mobileMenuBtn" type="button" aria-label="Open navigation" aria-expanded="false">☰</button>
    <div class="mobile-brand">
        <div class="brand-mark">C</div>
        <strong>Campus Coin</strong>
    </div>

    <div class="current-date-time" id="currentDateTime"></div>
    <div class="topbar-actions">
        <!-- Accessibility Font Size Adjuster (SRS 1.6 Page 9) -->
        <div class="font-size-controls" title="Adjust font size for accessibility">
            <button class="font-btn" id="fontDecreaseBtn" type="button" aria-label="Decrease font size">A-</button>
            <button class="font-btn" id="fontResetBtn" type="button" aria-label="Reset font size">A</button>
            <button class="font-btn" id="fontIncreaseBtn" type="button" aria-label="Increase font size">A+</button>
        </div>

        <button class="icon-btn" id="themeToggle" type="button" title="Toggle theme">◐</button>
        
        <div class="notification-wrap">
            <button class="icon-btn notification-btn" id="notificationBtn" type="button" title="Notifications" aria-expanded="false">
                🔔
                <span class="notification-dot" id="notificationDot"></span>
            </button>
            <div class="notification-dropdown" id="notificationDropdown">
                <div class="notification-header">
                    <h4>Notifications & Alerts</h4>
                    <span class="badge-dot">Live</span>
                </div>
                <div class="notification-list" id="notificationList">
                    <div class="notification-item unread">
                        <strong>Budget Warning</strong>
                        <p>Food spending is near 80% of your monthly limit.</p>
                        <small>Just now</small>
                    </div>
                    <div class="notification-item">
                        <strong>System Tip</strong>
                        <p>Track your transport allowance early to avoid deficits.</p>
                        <small>Yesterday</small>
                    </div>
                </div>
            </div>
        </div>
        <?php
        $userInitials = 'CC';
        if (!empty($_SESSION['user_name'])) {
            $parts = explode(' ', trim($_SESSION['user_name']));
            $userInitials = mb_substr($parts[0], 0, 1) . (isset($parts[1]) ? mb_substr($parts[1], 0, 1) : mb_substr($parts[0], 1, 1));
            $userInitials = strtoupper($userInitials);
        }
        ?>
        <a class="avatar" href="profile.php" title="View Profile" id="topbarAvatar"><?php echo e($userInitials); ?></a>
    </div>
</header>

