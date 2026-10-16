<?php
declare(strict_types=1);

/**
 * Database credentials: XAMPP defaults are user "root" with an empty password.
 * Change these if you set a MySQL password.
 */
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'smartpos_db');
define('DB_USER', 'root');
define('DB_PASS', '');

/**
 * Returns one shared PDO connection (created on first use).
 * Errors throw PDOException, which the global handler in bootstrap.php
 * logs and hides from the user.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    return $pdo;
}