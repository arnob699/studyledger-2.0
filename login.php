<?php
require_once __DIR__ . '/includes/functions.php';
require_guest();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_auth_csrf($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $stmt = get_db()->prepare('SELECT id, name, password_hash FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['csrf'] = bin2hex(random_bytes(24));
            header('Location: index.php');
            exit;
        }
        $error = 'Invalid email or password.';
    }
}
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Log in · Study Ledger</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body><main class="auth-page"><section class="auth-card"><div class="brand"><div class="brand-icon">$</div><div class="brand-text"><span class="brand-name">Study Ledger</span><span class="brand-tag">Study smarter</span></div></div><h1>Welcome back</h1><p class="muted">Log in to your private study ledger.</p><?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars(auth_csrf_token()) ?>"><div class="field"><label for="email">Email</label><input id="email" name="email" type="email" required autofocus></div><div class="field"><label for="password">Password</label><input id="password" name="password" type="password" required></div><button class="btn btn-primary" type="submit">Log in</button></form><p class="auth-link">New here? <a href="register.php">Create an account</a></p></section></main></body></html>