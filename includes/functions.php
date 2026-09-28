<?php
if (!defined('CAMPUS_COIN_BOOTSTRAP')) {
    define('CAMPUS_COIN_BOOTSTRAP', true);
}

/**
 * Escapes HTML output safely to prevent XSS (Cross-Site Scripting).
 */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Generates or retrieves existing CSRF token for the active session.
 */
function generateCsrfToken(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Validates submitted CSRF token against current session token using timing-safe comparison.
 */
function validateCsrfToken(?string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sets a flash message to display on subsequent page render.
 */
function setFlashMessage(string $type, string $message): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_' . $type] = $message;
}

/**
 * Retrieves and clears a flash message.
 */
function getFlashMessage(string $type): ?string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $key = 'flash_' . $type;
    if (isset($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $msg;
    }
    return null;
}

/**
 * Checks if a student or user is currently logged in.
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['campus_coin_logged_in']) && 
           $_SESSION['campus_coin_logged_in'] === true && 
           !empty($_SESSION['user_id']);
}

/**
 * Enforces student/user login. Redirects to login.php if unauthenticated.
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Checks if the logged-in user is an administrator.
 */
function isAdmin(): bool
{
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Enforces admin authorization.
 */
function requireAdmin(): void
{
    if (!isAdmin()) {
        $inAdminDir = (strpos($_SERVER['PHP_SELF'] ?? '', '/admin/') !== false);
        $redirectUrl = $inAdminDir ? 'login.php' : 'admin/login.php';
        header('Location: ' . $redirectUrl);
        exit;
    }
}

/**
 * Returns current authenticated user's ID.
 */
function currentUserId(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

/**
 * Returns current authenticated user's display name.
 */
function currentUserName(): string
{
    return (string)($_SESSION['user_name'] ?? 'Student');
}

/**
 * Fetches the complete profile record of the currently authenticated user.
 */
function currentUser(): ?array
{
    $userId = currentUserId();
    if ($userId <= 0) {
        return null;
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT user_id, name, email, role, academic_year, monthly_allowance, monthly_savings_goal, is_active, created_at FROM users WHERE user_id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    return $user ?: null;
}
