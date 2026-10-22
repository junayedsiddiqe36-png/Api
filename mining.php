<?php
/* ---------- Wallet helpers ---------- */
function wallet_tx($uid, $cur, $amt, $type, $note = '', $status = 'completed', $ref = null)
{
    q("INSERT INTO wallet_transactions (tx_id,user_id,currency,amount,type,status,note,ref_id) VALUES (?,?,?,?,?,?,?,?)",
        [tx_id(), $uid, $cur, dec($amt), $type, $status, $note, $ref]);
    return (int)db()->lastInsertId();
}
function credit_coins($uid, $amt, $type, $note = '', $ref = null)
{
    if ($amt <= 0) {
        return;
    }
    q("UPDATE users SET coins=coins+?, total_earned=total_earned+? WHERE id=?", [dec($amt), dec($amt), $uid]);
    wallet_tx($uid, 'COIN', $amt, $type, $note, 'completed', $ref);
}

/* ---------- Telegram WebApp auth ---------- */
function validate_init_data($init)
{
    $token = (string)setting('bot_token');
    if ($init === '' || $token === '') {
        throw new ApiError('Open this app from Telegram.', 401);
    }
    parse_str($init, $data);
    if (empty($data['hash']) || empty($data['user'])) {
        throw new ApiError('Invalid Telegram data.', 401);
    }
    $hash = $data['hash'];
    unset($data['hash']);
    ksort($data);
    $pairs = [];
    foreach ($data as $k => $v) {
        $pairs[] = $k . '=' . $v;
    }
    $secret = hash_hmac('sha256', $token, 'WebAppData', true);
    $calc = hash_hmac('sha256', implode("\n", $pairs), $secret);
    if (!hash_equals($calc, $hash)) {
        throw new ApiError('Telegram signature check failed.', 401);
    }
    if (empty($data['auth_date']) || time() - (int)$data['auth_date'] > 86400) {
        throw new ApiError('Session expired. Please reopen the app.', 401);
    }
    $user = json_decode($data['user'], true);
    if (!is_array($user) || empty($user['id'])) {
        throw new ApiError('Invalid Telegram user.', 401);
    }
    return ['user' => $user, 'start_param' => $data['start_param'] ?? null];
}

/* ---------- Users ---------- */
function register_user($tgId, $username, $first, $refCode = null)
{
    $u = q1("SELECT * FROM users WHERE telegram_id=?", [$tgId]);
    if ($u) {
        if ($u['username'] !== $username || $u['first_name'] !== $first) {
            q("UPDATE users SET username=?, first_name=? WHERE id=?", [$username, $first, $u['id']]);
            $u['username'] = $username;
            $u['first_name'] = $first;
        }
        return $u;
    }
    if (!setting('reg_enabled')) {
        throw new ApiError('Registration is currently closed.', 403);
    }
    $referrer = null;
    if ($refCode && setting('ref_enabled') && preg_match('/^[A-Za-z0-9]{4,16}$/', $refCode)) {
        $referrer = q1("SELECT id, telegram_id, status FROM users WHERE ref_code=?", [strtoupper($refCode)]);
        if ($referrer && ((int)$referrer['telegram_id'] === (int)$tgId || $referrer['status'] === 'banned')) {
            $referrer = null;
        }
        $limit = (int)setting('ref_limit');
        if ($referrer && $limit > 0 && (int)qv("SELECT COUNT(*) FROM referrals WHERE referrer_id=?", [$referrer['id']]) >= $limit) {
            $referrer = null;
        }
    }
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code = strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
        if (!q1("SELECT id FROM users WHERE ref_code=?", [$code])) {
            break;
        }
    }
    try {
        q("INSERT INTO users (telegram_id, username, first_name, ref_code, referred_by, last_seen, language) VALUES (?,?,?,?,?,NOW(),?)",
            [$tgId, $username, $first, $code, $referrer ? $referrer['id'] : null, (string)setting('default_lang', 'en')]);
    } catch (PDOException $e) {
        $u = q1("SELECT * FROM users WHERE telegram_id=?", [$tgId]);
        if ($u) {
            return $u;
        }
        throw $e;
    }
    $uid = (int)db()->lastInsertId();
    if ($referrer) {
        q("INSERT IGNORE INTO referrals (referrer_id, referred_id) VALUES (?,?)", [$referrer['id'], $uid]);
        notify_user((int)$referrer['id'], 'referral', "👥 <b>New Referral Joined!</b>\n\n" . h($first ?: 'A friend') . " joined using your link.");
        if ((int)setting('ref_min_sessions') <= 0) {
            referral_check_activation($uid);
        }
    }
    return q1("SELECT * FROM users WHERE id=?", [$uid]);
}

function referral_check_activation($referredId)
{
    $r = q1("SELECT * FROM referrals WHERE referred_id=? AND is_active=0", [$referredId]);
    if (!$r) {
        return;
    }
    $need = (int)setting('ref_min_sessions');
    $done = (int)qv("SELECT COUNT(*) FROM mining_sessions WHERE user_id=? AND status='completed'", [$referredId]);
    if ($done < $need) {
        return;
    }
    $bonus = (float)setting('ref_coin_bonus');
    $st = q("UPDATE referrals SET is_active=1, activated_at=NOW(), bonus_paid=? WHERE id=? AND is_active=0", [dec($bonus), $r['id']]);
    if ($st->rowCount() === 1 && $bonus > 0) {
        credit_coins((int)$r['referrer_id'], $bonus, 'referral', 'Referral bonus', (int)$referredId);
    }
}

/* ---------- Miner levels (raised by active referrals) ---------- */
function level_thresholds()
{
    $th = [];
    foreach (explode(',', (string)setting('level_thresholds')) as $x) {
        $x = trim($x);
        if ($x !== '' && is_numeric($x)) {
            $th[] = (float)$x;
        }
    }
    if (!$th) {
        $th = [0.0];
    }
    sort($th);
    return $th;
}
function level_names()
{
    $n = [];
    foreach (explode(',', (string)setting('level_names')) as $x) {
        $x = trim($x);
        if ($x !== '') {
            $n[] = $x;
        }
    }
    return $n;
}
function level_info($activeRefs)
{
    $th = level_thresholds();
    $names = level_names();
    $level = 1;
    foreach ($th as $i => $need) {
        if ($activeRefs >= $need) {
            $level = $i + 1;
        }
    }
    return [
        'level' => $level,
        'name' => isset($names[$level - 1]) ? $names[$level - 1] : 'Level ' . $level,
        'refs' => (int)$activeRefs,
        'from' => (float)$th[$level - 1],
        'next' => isset($th[$level]) ? (float)$th[$level] : null,
        'max' => count($th),
        'bonus' => round(($level - 1) * (float)setting('level_speed_bonus'), 4),
    ];
}
function levels_list()
{
    $names = level_names();
    $out = [];
    foreach (level_thresholds() as $i => $need) {
        $out[] = [
            'level' => $i + 1,
            'name' => isset($names[$i]) ? $names[$i] : 'Level ' . ($i + 1),
            'need' => (int)$need,
            'bonus' => round($i * (float)setting('level_speed_bonus'), 4),
        ];
    }
    return $out;
}

/* ---------- Speed ---------- */
function user_speed($u)
{
    $base = (float)setting('mine_base_speed');
    $ref = 0.0;
    $activeRefs = 0;
    if (setting('ref_enabled')) {
        $activeRefs = (int)qv("SELECT COUNT(*) FROM referrals WHERE referrer_id=? AND is_active=1", [$u['id']]);
        $limit = (int)setting('ref_limit');
        if ($limit > 0) {
            $activeRefs = min($activeRefs, $limit);
        }
        $ref = $activeRefs * (float)setting('ref_speed_bonus');
    }
    $prem = ($u['premium_boost'] && setting('premium_enabled')) ? (float)setting('premium_speed_bonus') : 0.0;
    $boost = (float)$u['extra_boost'];
    $mult = (float)setting('tier_mult_' . $u['tier'], 1);
    if ($mult <= 0) {
        $mult = 1;
    }
    $lvl = level_info($activeRefs);
    $speed = ($base + $ref + $lvl['bonus'] + $prem + $boost) * $mult;
    $min = (float)setting('mine_min_speed');
    $max = (float)setting('mine_max_speed');
    if ($min > 0) {
        $speed = max($speed, $min);
    }
    if ($max > 0) {
        $speed = min($speed, $max);
    }
    return [
        'base' => $base, 'referral' => $ref, 'active_refs' => $activeRefs, 'premium' => $prem,
        'boost' => $boost, 'multiplier' => $mult, 'speed' => round($speed, 4),
        'level_bonus' => $lvl['bonus'], 'level' => $lvl,
    ];
}

/* ---------- Mining sessions ---------- */
function mining_begin($u)
{
    $speed = user_speed($u)['speed'];
    $now = time();
    $dur = max(1, (int)setting('mine_duration')) * 60;
    $txRow = wallet_tx((int)$u['id'], 'COIN', 0, 'mining', 'Mining session', 'active');
    q("INSERT INTO mining_sessions (user_id, started_at, ends_at, speed, status, active_user, tx_row) VALUES (?,?,?,?, 'active', ?, ?)",
        [$u['id'], $now, $now + $dur, dec($speed), $u['id'], $txRow]);
}

function mining_start($uid)
{
    if (setting('maintenance')) {
        throw new ApiError('Maintenance mode is on. Please try again later.', 503);
    }
    if (!setting('mine_enabled')) {
        throw new ApiError('Mining is currently disabled.');
    }
    mining_settle($uid);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $u = q1("SELECT * FROM users WHERE id=? FOR UPDATE", [$uid]);
        if (!$u || $u['status'] !== 'active') {
            throw new ApiError('Your account cannot mine right now.', 403);
        }
        if (q1("SELECT id FROM mining_sessions WHERE user_id=? AND status='active'", [$uid])) {
            throw new ApiError('A mining session is already running.');
        }
        mining_begin($u);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Credit due coins for the active session (server clock only).
 * Completes the session when time is up, or stops it when $stop is true.
 */
function mining_settle($uid, $stop = false)
{
    $pdo = db();
    $completed = false;
    $credited = 0.0;
    $pdo->beginTransaction();
    try {
        $u = q1("SELECT * FROM users WHERE id=? FOR UPDATE", [$uid]);
        $s = $u ? q1("SELECT * FROM mining_sessions WHERE user_id=? AND status='active' FOR UPDATE", [$uid]) : null;
        if (!$s) {
            $pdo->commit();
            return;
        }
        $now = time();
        $start = (int)$s['started_at'];
        $end = (int)$s['ends_at'];
        $speed = (float)$s['speed'];
        $interval = max(1, (int)setting('mine_interval')) * 60;
        $finished = $now >= $end;
        if ($finished) {
            $due = (($end - $start) / 60) * $speed;
        } else {
            $due = floor(($now - $start) / $interval) * ($interval / 60) * $speed;
        }
        $due = round($due, 4);
        $delta = max(0, round($due - (float)$s['credited'], 4));

        $today = date('Y-m-d');
        $minedToday = ($u['mined_date'] === $today) ? (float)$u['mined_today'] : 0.0;
        $cap = (float)setting('mine_daily_limit');
        if ($cap > 0) {
            $delta = max(0, min($delta, round($cap - $minedToday, 4)));
        }
        if ($delta > 0) {
            q("UPDATE users SET coins=coins+?, total_earned=total_earned+?, total_mined=total_mined+?, mined_today=?, mined_date=? WHERE id=?",
                [dec($delta), dec($delta), dec($delta), dec($minedToday + $delta), $today, $uid]);
            q("INSERT INTO mining_transactions (user_id, session_id, amount) VALUES (?,?,?)", [$uid, $s['id'], dec($delta)]);
        }
        $credited = round((float)$s['credited'] + $delta, 4);

        if ($finished || $stop) {
            $status = $finished ? 'completed' : 'stopped';
            q("UPDATE mining_sessions SET credited=?, status=?, active_user=NULL, notified=1 WHERE id=?", [dec($credited), $status, $s['id']]);
            if ($s['tx_row']) {
                if ($credited > 0) {
                    q("UPDATE wallet_transactions SET amount=?, status='completed' WHERE id=?", [dec($credited), $s['tx_row']]);
                } else {
                    q("DELETE FROM wallet_transactions WHERE id=?", [$s['tx_row']]);
                }
            }
            $completed = $finished;
            if ($finished && setting('mine_auto_restart') && setting('mine_enabled') && !setting('maintenance') && $u['status'] === 'active') {
                mining_begin($u);
            }
        } else {
            q("UPDATE mining_sessions SET credited=? WHERE id=?", [dec($credited), $s['id']]);
            if ($s['tx_row']) {
                q("UPDATE wallet_transactions SET amount=? WHERE id=?", [dec($credited), $s['tx_row']]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    if ($completed) {
        referral_check_activation($uid);
        notify_user($uid, 'mining', "⛏️ <b>Mining Session Completed!</b>\n\nYou mined <b>" . fmt($credited) . "</b> coins.", app_button('⛏️ MINE AGAIN'));
    }
}

function mining_finish_due()
{
    $rows = qa("SELECT DISTINCT user_id FROM mining_sessions WHERE status='active' AND ends_at<=? LIMIT 300", [time()]);
    foreach ($rows as $r) {
        try {
            mining_settle((int)$r['user_id']);
        } catch (Throwable $e) {
            error_log('mining_finish_due: ' . $e->getMessage());
        }
    }
}
