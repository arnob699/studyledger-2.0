<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function current_user(): ?array
{
    if (!current_user_id()) return null;
    $stmt = get_db()->prepare('SELECT id, name, email FROM users WHERE id = ?');
    $stmt->execute([current_user_id()]);
    return $stmt->fetch() ?: null;
}

function require_auth(bool $json = false): void
{
    if (current_user_id()) return;
    if ($json) {
        json_out(['error' => 'Please log in to continue.'], 401);
    }
    header('Location: login.php');
    exit;
}

function require_guest(): void
{
    if (current_user_id()) {
        header('Location: index.php');
        exit;
    }
}

function auth_csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}

function verify_auth_csrf(?string $token): bool
{
    return is_string($token) && hash_equals($_SESSION['csrf'] ?? '', $token);
}