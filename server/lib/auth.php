<?php
/**
 * Session auth for /leads (shared `desk` login) and /admin (owner-only
 * `admin` login). Two bcrypt hashes in config.php, no user table — there is
 * nothing to register, only two shared passwords.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function mnj_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'secure' => true,
        'samesite' => 'Strict',
    ]);
    session_name('mnj_session');
    session_start();
}

/** @return 'admin'|'desk'|null */
function mnj_current_role(): ?string
{
    mnj_session_start();
    $role = $_SESSION['mnj_role'] ?? null;
    return in_array($role, ['admin', 'desk'], true) ? $role : null;
}

function mnj_logout(): void
{
    mnj_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** One CSRF token per session, reused for every form on the page. */
function mnj_csrf_token(): string
{
    mnj_session_start();
    if (empty($_SESSION['mnj_csrf'])) {
        $_SESSION['mnj_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['mnj_csrf'];
}

function mnj_csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(mnj_csrf_token()) . '">';
}

function mnj_csrf_verify(?string $token): bool
{
    mnj_session_start();
    return is_string($token) && is_string($_SESSION['mnj_csrf'] ?? null)
        && hash_equals($_SESSION['mnj_csrf'], $token);
}

/** 5 failed attempts per IP hash per 15 minutes, across both /leads and /admin. */
function mnj_login_rate_limited(): bool
{
    $pdo = mnj_db();
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts WHERE ip_hash = :ip AND attempted_at > (NOW() - INTERVAL 15 MINUTE)'
    );
    $stmt->execute(['ip' => mnj_ip_hash()]);
    return (int) $stmt->fetchColumn() >= 5;
}

function mnj_record_failed_login(): void
{
    $pdo = mnj_db();
    $stmt = $pdo->prepare('INSERT INTO login_attempts (ip_hash) VALUES (:ip)');
    $stmt->execute(['ip' => mnj_ip_hash()]);
}

/**
 * Verify a submitted password against both role hashes and, on success,
 * establish the session. Returns null on any failure (wrong password OR
 * rate-limited) — the caller doesn't need to distinguish the two beyond
 * showing one generic message, so a bot probing the login can't tell which
 * case it hit either.
 */
function mnj_login(string $password): ?string
{
    mnj_session_start();

    if (mnj_login_rate_limited()) {
        return null;
    }

    $config = mnj_config();
    $role = null;
    if ($password !== '' && password_verify($password, $config['admin_password_hash'])) {
        $role = 'admin';
    } elseif ($password !== '' && password_verify($password, $config['desk_password_hash'])) {
        $role = 'desk';
    }

    if ($role === null) {
        mnj_record_failed_login();
        return null;
    }

    // Regenerate the session id on privilege change so a session fixed before
    // login can't be reused after it.
    session_regenerate_id(true);
    $_SESSION['mnj_role'] = $role;
    $_SESSION['mnj_login_at'] = time();
    return $role;
}

/**
 * Every /leads and /admin entry page starts with the same three lines:
 * process a submitted login form if there is one, then read the current
 * role. Centralised so a page can't accidentally render the login form
 * without ever handling its own submission — that gap previously meant
 * call-sheet.php, export-log.php and archive.php would show a password box
 * that silently ignored whatever you typed into it.
 *
 * On success this redirects (to strip the POST) and does not return.
 *
 * @return string|null an error message to show under the login form, or null
 */
function mnj_handle_login_post(): ?string
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['password'])) {
        return null;
    }
    if (!mnj_csrf_verify($_POST['csrf'] ?? null)) {
        return 'Session expired, please try again.';
    }
    $role = mnj_login((string) $_POST['password']);
    if ($role === null) {
        return 'Wrong password, or too many attempts — wait 15 minutes.';
    }
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}
