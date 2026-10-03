<?php
/**
 * Database connection.
 * Edit the four constants below to match your local MySQL/MariaDB setup.
 */

// Set PHP timezone to match the app locale.
// Without this, PHP's date() runs in UTC — e.g. at 02:49 Bangladesh time (UTC+6)
// PHP thinks the date is still yesterday, so the manual-log future-date guard
// rejects today's Bangladesh date as "in the future" and silently blocks the insert.
date_default_timezone_set('Asia/Dhaka');
define('DB_HOST', 'sql107.ezyro.com');
define('DB_NAME', 'ezyro_42913920_personal_database');
define('DB_USER', 'ezyro_42913920');
define('DB_PASS', '2d01a905');

function get_db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            // Sync MySQL session timezone with the PHP timezone set above.
            // Without this, MySQL's CURDATE()/NOW() run on the server's system timezone
            // (usually UTC), so daily/weekly stats return zeros for sessions logged
            // under today's local date.
            $offset = (new DateTimeZone(date_default_timezone_get()))
                        ->getOffset(new DateTime('now', new DateTimeZone('UTC')));
            $sign   = $offset >= 0 ? '+' : '-';
            $abs    = abs($offset);
            $tzStr  = sprintf('%s%02d:%02d', $sign, intdiv($abs, 3600), ($abs % 3600) / 60);
            $pdo->exec("SET time_zone = '{$tzStr}'");
        } catch (PDOException $e) {
            http_response_code(500);
            die('Database connection failed. Check config/db.php — ' . htmlspecialchars($e->getMessage()));
        }
    }

    return $pdo;
}
