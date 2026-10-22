<?php
require __DIR__ . '/_layout.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);
    $map = ['approve' => 'approved', 'reject' => 'rejected', 'paid' => 'paid'];
    $do = $_POST['do'] ?? '';
    if (isset($map[$do])) {
        try {
            withdrawal_set_status($id, $map[$do], $_SESSION['admin_name'], trim((string)($_POST['note'] ?? '')), trim((string)($_POST['ref'] ?? '')));
            alog('withdrawal_' . $map[$do], (int)qv("SELECT user_id FROM withdrawals WHERE id=?", [$id]), "withdrawal #$id");
            flash("Withdrawal #$id updated to " . $map[$do] . '.');
        } catch (ApiError $e) {
            flash($e->getMessage(), 'err');
        }
    }
    back('withdrawals.php?status=' . urlencode($_GET['status'] ?? 'pending'));
}
$status = $_GET['status'] ?? 'pending';
$valid = ['pending', 'approved', 'paid', 'rejected', 'cancelled', 'all'];
if (!in_array($status, $valid, true)) {
    $status = 'pending';
}
$rows = qa("SELECT w.*, u.telegram_id, u.username, u.flagged FROM withdrawals w JOIN users u ON u.id=w.user_id "
    . ($status !== 'all' ? "WHERE w.status=? " : "") . "ORDER BY w.id DESC LIMIT 100", $status !== 'all' ? [$status] : []);
admin_head('Withdrawals', 'withdrawals');
echo '<div class="tabs">';
foreach ($valid as $s) {
    echo '<a href="?status=' . $s . '" class="' . ($s === $status ? 'on' : '') . '">' . ucfirst($s) . '</a>';
}
echo '</div>';
if (!$rows) {
    echo '<p class="muted">No withdrawals.</p>';
}
echo '<div class="cols">';
foreach ($rows as $w) {
    $dup = (int)qv("SELECT COUNT(DISTINCT user_id) FROM withdrawals WHERE address=? AND user_id<>?", [$w['address'], $w['user_id']]);
    echo '<div class="card"><div class="row"><b>#' . $w['id'] . ' · $' . fmt($w['amount']) . '</b><span class="pill ' . h($w['status']) . '">' . h($w['status']) . '</span></div>'
        . '<div class="muted">User <a href="users.php?id=' . $w['user_id'] . '">#' . $w['user_id'] . '</a> · TG ' . h($w['telegram_id']) . ' · ' . ($w['username'] ? '@' . h($w['username']) : 'no username') . '</div>'
        . '<div>' . h($w['method']) . '</div><div class="addr">' . h($w['address']) . '</div>'
        . '<div class="muted">' . h($w['created_at']) . '</div>';
    if ($w['flagged'] || $dup) {
        echo '<div class="flash err">⚠ Suspicious' . ($dup ? ': address used by ' . $dup . ' other account(s)' : '') . '</div>';
    }
    if ($w['admin_note']) {
        echo '<div class="muted">Note: ' . h($w['admin_note']) . '</div>';
    }
    if ($w['payout_ref']) {
        echo '<div class="muted">Payout ref: ' . h($w['payout_ref']) . '</div>';
    }
    if (in_array($w['status'], ['pending', 'approved'], true)) {
        echo '<form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="id" value="' . $w['id'] . '">'
            . '<input name="note" placeholder="Note / reason (optional)"><input name="ref" placeholder="Payout TX hash (optional)">';
        if ($w['status'] === 'pending') {
            echo '<button name="do" value="approve" class="btn green">APPROVE</button>';
        }
        echo '<button name="do" value="paid" class="btn gold" onclick="return confirm(\'Mark as PAID? Make sure you sent the payment.\')">MARK AS PAID</button>'
            . '<button name="do" value="reject" class="btn red" onclick="return confirm(\'Reject and refund the user?\')">REJECT</button></form>';
    }
    echo '</div>';
}
echo '</div>';
admin_foot();
