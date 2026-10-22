<?php
require __DIR__ . '/_layout.php';
require_admin();
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
    csrf_verify();
    $do = $_POST['do'] ?? '';
    $u = q1("SELECT * FROM users WHERE id=?", [$id]);
    if ($u) {
        $reason = trim((string)($_POST['reason'] ?? ''));
        $amt = abs((float)($_POST['amount'] ?? 0));
        $pdo = db();
        switch ($do) {
            case 'ban': case 'unban': case 'freeze': case 'unfreeze':
                $new = ['ban' => 'banned', 'unban' => 'active', 'freeze' => 'frozen', 'unfreeze' => 'active'][$do];
                q("UPDATE users SET status=? WHERE id=?", [$new, $id]);
                if ($new !== 'active') {
                    mining_settle($id, true);
                }
                alog('user_' . $do, $id, $reason);
                flash("User set to $new.");
                break;
            case 'unflag':
                q("UPDATE users SET flagged=0, flag_reason=NULL WHERE id=?", [$id]);
                alog('user_unflag', $id);
                flash('Flag cleared.');
                break;
            case 'add_coins': case 'remove_coins': case 'add_usd': case 'remove_usd':
                if ($amt <= 0 || $reason === '') {
                    flash('Enter an amount and a reason.', 'err');
                    break;
                }
                $pdo->beginTransaction();
                $cur = q1("SELECT coins, usd_balance FROM users WHERE id=? FOR UPDATE", [$id]);
                if ($do === 'add_coins') {
                    credit_coins($id, $amt, 'admin_adjust', $reason);
                } elseif ($do === 'remove_coins') {
                    $amt = min($amt, (float)$cur['coins']);
                    q("UPDATE users SET coins=coins-?, spent_coins=spent_coins+? WHERE id=?", [dec($amt), dec($amt), $id]);
                    wallet_tx($id, 'COIN', -$amt, 'admin_adjust', $reason);
                } elseif ($do === 'add_usd') {
                    q("UPDATE users SET usd_balance=usd_balance+?, usd_earned=usd_earned+? WHERE id=?", [dec($amt), dec($amt), $id]);
                    wallet_tx($id, 'USD', $amt, 'admin_adjust', $reason);
                } else {
                    $amt = min($amt, (float)$cur['usd_balance']);
                    q("UPDATE users SET usd_balance=usd_balance-? WHERE id=?", [dec($amt), $id]);
                    wallet_tx($id, 'USD', -$amt, 'admin_adjust', $reason);
                }
                $pdo->commit();
                alog('balance_' . $do, $id, dec($amt) . ' | ' . $reason);
                flash('Balance updated.');
                break;
            case 'tier':
                $tier = in_array($_POST['tier'] ?? '', ['free', 'premium', 'vip'], true) ? $_POST['tier'] : 'free';
                $boost = max(0, (float)($_POST['extra_boost'] ?? 0));
                q("UPDATE users SET tier=?, extra_boost=? WHERE id=?", [$tier, dec($boost), $id]);
                alog('user_tier', $id, "$tier boost=$boost");
                flash('Tier / boost updated (applies to next mining session).');
                break;
        }
    }
    back('users.php?id=' . $id);
}

if ($id > 0) {
    $u = q1("SELECT * FROM users WHERE id=?", [$id]);
    if (!$u) {
        flash('User not found.', 'err');
        back('users.php');
    }
    admin_head('User #' . $id, 'users');
    echo '<div class="card"><div class="row"><b>' . h(display_name($u)) . '</b><span class="pill ' . h($u['status']) . '">' . h($u['status']) . '</span></div>'
        . '<div class="muted">Telegram ID ' . h($u['telegram_id']) . ' · joined ' . h($u['created_at']) . ' · last seen ' . h($u['last_seen']) . '</div>'
        . '<div class="grid">'
        . '<div class="stat"><span>Coins</span><b>' . fmt($u['coins']) . '</b></div><div class="stat"><span>USD</span><b>$' . fmt($u['usd_balance']) . '</b></div>'
        . '<div class="stat"><span>Total mined</span><b>' . fmt($u['total_mined']) . '</b></div><div class="stat"><span>USD withdrawn</span><b>$' . fmt($u['usd_withdrawn']) . '</b></div>'
        . '<div class="stat"><span>Speed now</span><b>' . fmt(user_speed($u)['speed']) . '</b></div><div class="stat"><span>Tier / boost</span><b>' . h($u['tier']) . ' / +' . fmt($u['extra_boost']) . '</b></div>'
        . '<div class="stat"><span>Referrals</span><b>' . (int)qv("SELECT COUNT(*) FROM referrals WHERE referrer_id=?", [$id]) . '</b></div><div class="stat"><span>Premium</span><b>' . ($u['premium_boost'] ? 'yes' : 'no') . '</b></div></div>';
    if ($u['flagged']) {
        echo '<div class="flash err">⚠ Flagged: ' . h($u['flag_reason']) . '</div>';
    }
    echo '</div>';

    echo '<div class="card"><b>Actions</b><form method="post" class="inline">' . csrf_field() . '<input name="reason" placeholder="Reason (logged)">';
    foreach (['ban' => 'red', 'unban' => 'green', 'freeze' => 'red', 'unfreeze' => 'green'] as $a => $c) {
        echo '<button name="do" value="' . $a . '" class="btn ' . $c . '">' . strtoupper($a) . '</button>';
    }
    if ($u['flagged']) {
        echo '<button name="do" value="unflag" class="btn">CLEAR FLAG</button>';
    }
    echo '</form></div>';

    echo '<div class="card"><b>Manual balance adjustment</b><form method="post" class="inline">' . csrf_field()
        . '<input name="amount" type="number" step="any" min="0" placeholder="Amount"><input name="reason" placeholder="Reason (required)">'
        . '<button name="do" value="add_coins" class="btn green">+ COINS</button><button name="do" value="remove_coins" class="btn red">− COINS</button>'
        . '<button name="do" value="add_usd" class="btn green">+ USD</button><button name="do" value="remove_usd" class="btn red">− USD</button></form></div>';

    echo '<div class="card"><b>Tier &amp; extra boost</b><form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="do" value="tier"><select name="tier">';
    foreach (['free', 'premium', 'vip'] as $tr) {
        echo '<option ' . ($u['tier'] === $tr ? 'selected' : '') . '>' . $tr . '</option>';
    }
    echo '</select><input name="extra_boost" type="number" step="any" min="0" value="' . h($u['extra_boost']) . '"><button class="btn">SAVE</button></form></div>';

    $tables = [
        'Mining history' => ["SELECT id, FROM_UNIXTIME(started_at) AS started, speed, credited, status FROM mining_sessions WHERE user_id=? ORDER BY id DESC LIMIT 15", [$id]],
        'Referral history' => ["SELECT r.referred_id AS user, r.is_active, r.bonus_paid, r.created_at FROM referrals r WHERE r.referrer_id=? ORDER BY r.id DESC LIMIT 20", [$id]],
        'Withdrawal history' => ["SELECT id, amount, method, address, status, created_at FROM withdrawals WHERE user_id=? ORDER BY id DESC LIMIT 15", [$id]],
        'Transactions' => ["SELECT tx_id, currency, amount, type, status, note, created_at FROM wallet_transactions WHERE user_id=? ORDER BY id DESC LIMIT 30", [$id]],
    ];
    foreach ($tables as $title => $qq) {
        $rows = qa($qq[0], $qq[1]);
        echo '<div class="card"><b>' . h($title) . '</b>';
        if (!$rows) {
            echo '<p class="muted">None.</p></div>';
            continue;
        }
        echo '<div class="scroll"><table><tr>';
        foreach (array_keys($rows[0]) as $c) {
            echo '<th>' . h($c) . '</th>';
        }
        echo '</tr>';
        foreach ($rows as $r) {
            echo '<tr>';
            foreach ($r as $c) {
                echo '<td>' . h((string)$c) . '</td>';
            }
            echo '</tr>';
        }
        echo '</table></div></div>';
    }
    admin_foot();
    exit;
}

$search = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['p'] ?? 1));
$per = 30;
$where = '';
$params = [];
if ($search !== '') {
    $where = "WHERE telegram_id = ? OR id = ? OR username LIKE ? OR first_name LIKE ?";
    $params = [ctype_digit($search) ? $search : 0, ctype_digit($search) ? $search : 0, '%' . $search . '%', '%' . $search . '%'];
}
$total = (int)qv("SELECT COUNT(*) FROM users $where", $params);
$rows = qa("SELECT id, telegram_id, username, first_name, coins, usd_balance, status, flagged, created_at FROM users $where ORDER BY id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $params);
admin_head('Users (' . $total . ')', 'users');
echo '<form class="inline"><input name="q" value="' . h($search) . '" placeholder="Search by ID, Telegram ID, username or name"><button class="btn gold">Search</button></form>';
echo '<div class="cols">';
foreach ($rows as $r) {
    echo '<a class="card ucard" href="?id=' . $r['id'] . '"><div class="row"><b>' . h(display_name($r)) . ($r['flagged'] ? ' ⚠' : '') . '</b><span class="pill ' . h($r['status']) . '">' . h($r['status']) . '</span></div>'
        . '<span class="muted">#' . $r['id'] . ' · TG ' . h($r['telegram_id']) . '</span>'
        . '<span>' . fmt($r['coins'], 0) . ' coins · $' . fmt($r['usd_balance']) . '</span></a>';
}
echo '</div><div class="pager">';
if ($page > 1) {
    echo '<a href="?q=' . urlencode($search) . '&p=' . ($page - 1) . '">« Prev</a>';
}
if ($page * $per < $total) {
    echo '<a href="?q=' . urlencode($search) . '&p=' . ($page + 1) . '">Next »</a>';
}
echo '</div>';
admin_foot();
