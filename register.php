<?php
require_once __DIR__ . '/includes/functions.php';
require_guest();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_auth_csrf($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            $error = 'Enter a name, a valid email, and a password of at least 8 characters.';
        } else {
            try {
                $pdo = get_db();
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
                $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                $userId = (int)$pdo->lastInsertId();
                $pdo->prepare('INSERT INTO app_settings (user_id) VALUES (?)')->execute([$userId]);
                $pdo->commit();
                session_regenerate_id(true);
                $_SESSION['user_id'] = $userId;
                $_SESSION['csrf'] = bin2hex(random_bytes(24));
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                if (get_db()->inTransaction()) get_db()->rollBack();
                $error = $e->errorInfo[1] === 1062 ? 'That email is already registered.' : 'Could not create your account.';
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Create account · Study Ledger</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body><main class="auth-page"><section class="auth-card"><div class="brand"><div class="brand-icon">$</div><div class="brand-text"><span class="brand-name">Study Ledger</span><span class="brand-tag">Study smarter</span></div></div><h1>Create your ledger</h1><p class="muted">Your subjects, cycles, and goals stay private to you.</p><?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars(auth_csrf_token()) ?>"><div class="field"><label for="name">Name</label><input id="name" name="name" required></div><div class="field"><label for="email">Email</label><input id="email" name="email" type="email" required></div><div class="field"><label for="password">Password</label><input id="password" name="password" type="password" minlength="8" required></div><button class="btn btn-primary" type="submit">Create account</button></form><p class="auth-link">Already registered? <a href="login.php">Log in</a></p></section></main></body></html>