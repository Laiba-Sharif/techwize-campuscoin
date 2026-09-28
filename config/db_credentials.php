<?php
// Prevent direct web access to credentials
if (!defined('CAMPUS_COIN_BOOTSTRAP')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

return [
    'host'     => 'localhost',
    'dbname'   => 'campus_coin',
    'username' => 'root',
    'password' => '', // Default XAMPP MySQL password is empty
    'charset'  => 'utf8mb4'
];
