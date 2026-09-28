<?php
require_once __DIR__ . '/../config.php';

unset($_SESSION['campus_coin_admin']);
unset($_SESSION['campus_coin_admin_name']);

// If logged in only as admin, destroy full session
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

header('Location: login.php');
exit;
