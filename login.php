<?php
require __DIR__ . '/_layout.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (!rate_limit('adminlogin:' . client_ip(), 8, 600)) {
        $err = 'Too many attempts. Try again in 10 minutes.';
    } else {
        $a = q1("SELECT * FROM admins WHERE username=?", [trim((string)($_POST['username'] ?? ''))]);
        if ($a && password_verify((string)($_POST['password'] ?? ''), $a['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$a['id'];
            $_SESSION['admin_name'] = $a['username'];
            q("UPDATE admins SET last_login=NOW() WHERE id=?", [$a['id']]);
            alog('login');
            header('Location: index.php');
            exit;
        }
        $err = 'Invalid username or password.';
        admin_log('login_failed', null, (string)($_POST['username'] ?? ''));
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Login</title><link rel="stylesheet" href="admin.css"><script src="admin.js"></script></head><body class="login">
<form method="post" class="card loginbox"><h1>⛏️ Admin Login</h1>
<?php if ($err): ?><div class="flash err"><?= h($err) ?></div><?php endif; ?>
<?= csrf_field() ?>
<label>Username</label><input name="username" autocomplete="username" required>
<label>Password</label><input name="password" type="password" autocomplete="current-password" required>
<button class="btn gold">Sign in</button></form></body></html>
