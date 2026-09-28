<?php
if (!defined('CAMPUS_COIN_BOOTSTRAP')) {
    define('CAMPUS_COIN_BOOTSTRAP', true);
}

// Start secure session if not started
if (session_status() === PHP_SESSION_NONE) {
    // Session security parameters
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');

    session_start();
}

// Require PDO database connection
require_once __DIR__ . '/config/database.php';

// Require helper functions (CSRF, escaping, authentication)
require_once __DIR__ . '/includes/functions.php';

// Pre-initialize CSRF token
generateCsrfToken();
