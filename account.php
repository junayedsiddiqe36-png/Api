<?php
require __DIR__ . '/_layout.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $a = q1("SELECT * FROM admins WHERE id=?", [$_SESSION['admin_id']]);
    $new = (string)($_POST['new'] ?? '');
    if (!password_verify((string)($_POST['current'] ?? ''), $a['password_hash'])) {
        flash('Current password is wrong.', 'err');
    } elseif (strlen($new) < 8 || $new !== (string)($_POST['confirm'] ?? '')) {
        flash('New password must be 8+ characters and match the confirmation.', 'err');
    } else {
        q("UPDATE admins SET password_hash=? WHERE id=?", [password_hash($new, PASSWORD_DEFAULT), $a['id']]);
        alog('password_change');
        flash('Password changed.');
    }
    back();
}
admin_head('Account', 'account');
echo '<form method="post" class="card">' . csrf_field()
    . '<label>Current password</label><input type="password" name="current" required autocomplete="current-password">'
    . '<label>New password</label><input type="password" name="new" required autocomplete="new-password">'
    . '<label>Confirm new password</label><input type="password" name="confirm" required autocomplete="new-password">'
    . '<button class="btn gold">Change password</button></form>';
admin_foot();
