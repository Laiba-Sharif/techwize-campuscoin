<?php
// Define bootstrap guard
if (!defined('CAMPUS_COIN_BOOTSTRAP')) {
    define('CAMPUS_COIN_BOOTSTRAP', true);
}

/**
 * Returns a singleton PDO database connection instance.
 * Prepared statements only.
 * User-safe error messages on failure.
 */
function getDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $credentialsFile = __DIR__ . '/db_credentials.php';

        if (!file_exists($credentialsFile)) {
            error_log('Campus Coin Error: Database credentials file missing.');
            exit('Application configuration error. Please contact system administrator.');
        }

        $config = require $credentialsFile;

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['dbname'],
            $config['charset']
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Associative array fetches
            PDO::ATTR_EMULATE_PREPARES   => false,                  // True native prepared statements
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $pdo = new PDO($dsn, $config['username'], $config['password'], $options);
        } catch (PDOException $e) {
            // Log real SQL error internally; show safe generic message to student/user
            error_log('Database Connection Failed: ' . $e->getMessage());
            exit('Unable to connect to the database. Please verify MySQL service is running on XAMPP.');
        }
    }

    return $pdo;
}
