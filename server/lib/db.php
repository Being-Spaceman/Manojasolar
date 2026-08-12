<?php
/**
 * One PDO connection per request, opened here and left for PHP to close when
 * the request ends. Never a persistent connection — Hostinger's 75-connection
 * cap is shared across every site on the account, and a persistent pool is
 * how a low-traffic form page quietly exhausts it.
 */

declare(strict_types=1);

function mnj_config(): array
{
    static $config = null;
    if ($config === null) {
        $path = __DIR__ . '/../config.php';
        if (!is_file($path)) {
            http_response_code(500);
            exit('server/config.php is missing — copy config.example.php and fill in real values.');
        }
        $config = require $path;
    }
    return $config;
}

function mnj_db(): PDO
{
    $db = mnj_config()['db'];
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $db['host'],
        $db['name'],
        $db['charset'] ?? 'utf8mb4',
    );

    return new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

/** Salted SHA-256 of the client IP. The raw IP is never stored anywhere. */
function mnj_ip_hash(): string
{
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    // X-Forwarded-For can be a comma-separated chain; the client is the first entry.
    $ip = trim(explode(',', $ip)[0]);
    $salt = mnj_config()['ip_hash_salt'];
    return hash('sha256', $salt . '|' . $ip);
}
