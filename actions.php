<?php
function require_active($u)
{
    if ($u['status'] !== 'active') {
        throw new ApiError('Your account is frozen. Please contact support.', 403);
    }
}
function wd_methods()
{
    $m = array_filter(array_map('trim', explode(',', (string)setting('wd_methods'))), 'strlen');
    return array_values($m);
}
function validate_address($method, $addr)
{
    $m = strtoupper($method);
    if (strpos($m, 'TRC20') !== false) {
        return (bool)preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $addr);
    }
    if (strpos($m, 'BEP20') !== false) {
        return (bool)preg_match('/^0x[a-fA-F0-9]{40}$/', $addr);
    }
    if (strpos($m, 'BINANCE') !== false) {
        return (bool)preg_match('/^[0-9]{5,15}$/', $addr);
    }
    return (bool)preg_match('/^[A-Za-z0-9:_\-\.]{5,120}$/', $addr);
}

/* ---------- Daily / check-in info ---------- */
function daily_rewards_list()
{
    $r = [];
    foreach (explode(',', (string)setting('daily_rewards')) as $x) {
        $x = trim($x);
        if ($x !== '' && is_numeric($x)) {
            $r[] = (float)$x;
        }
    }
    return $r ?: [100.0];
}
function daily_info($u)
{
    $rewards = daily_rewards_list();
    $today = date('Y-m-d');
    $yest = date('Y-m-d', strtotime('-1 day'));
    $claimed = $u['last_daily_date'] === $today;
    $streak = (int)$u['daily_streak'];
    if ($claimed) {
        $day = (($streak - 1) % count($rewards)) + 1;
        $cur = $streak;
    } else {
        $cur = ($u['last_daily_date'] === $yest) ? $streak : 0;
        $day = (($cur) % count($rewards)) + 1;
    }
    return ['enabled' => (bool)setting('daily_enabled'), 'rewards' => $rewards, 'day' => $day, 'claimed' => $claimed, 'streak' => $cur];
}
function checkin_info($u)
{
    $today = date('Y-m-d');
    $yest = date('Y-m-d', strtotime('-1 day'));
    $claimed = $u['last_checkin_date'] === $today;
    $streak = (int)$u['checkin_streak'];
    if (!$claimed && $u['last_checkin_date'] !== $yest && setting('checkin_reset')) {
        $streak = 0;
    }
    return [
        'enabled' => (bool)setting('checkin_enabled'), 'claimed' => $claimed, 'streak' => $streak,
        'reward' => (float)setting('checkin_reward'), 'streak_days' => (int)setting('checkin_streak_days'),
        'streak_bonus' => (float)setting('checkin_streak_bonus'),
    ];
}

/* ---------- State ---------- */
function api_state($uid)
{
    mining_settle($uid);
    $u = q1("SELECT * FROM users WHERE id=?", [$uid]);
    $sp = user_speed($u);
    $sess = q1("SELECT * FROM mining_sessions WHERE user_id=? AND status='active'", [$uid]);
    $mining = ['status' => 'idle'];
    if ($sess) {
        $mining = [
            'status' => 'active', 'started_at' => (int)$sess['started_at'], 'ends_at' => (int)$sess['ends_at'],
            'speed' => (float)$sess['speed'], 'credited' => (float)$sess['credited'],
        ];
    } else {
        $last = q1("SELECT * FROM mining_sessions WHERE user_id=? ORDER BY id DESC LIMIT 1", [$uid]);
        if ($last && $last['status'] === 'completed') {
            $mining = ['status' => 'completed', 'credited' => (float)$last['credited']];
        }
    }
    $cap = (float)setting('mine_daily_limit');
    $minedToday = ($u['mined_date'] === date('Y-m-d')) ? (float)$u['mined_today'] : 0.0;
    $room = $cap > 0 ? max(0, $cap - $minedToday) : null;

    $refTotal = (int)qv("SELECT COUNT(*) FROM referrals WHERE referrer_id=?", [$uid]);
    $wdCount = (int)qv("SELECT COUNT(*) FROM withdrawals WHERE user_id=? AND status IN ('pending','approved','paid')", [$uid]);
    $bot = ltrim((string)setting('bot_username'), '@');
    $refLink = $bot !== '' ? 'https://t.me/' . $bot . '?start=' . $u['ref_code'] : '';

    return [
        'now' => time(),
        'user' => [
            'id' => (int)$u['id'], 'telegram_id' => $u['telegram_id'], 'username' => $u['username'], 'first_name' => $u['first_name'],
            'coins' => (float)$u['coins'], 'total_earned' => (float)$u['total_earned'], 'total_mined' => (float)$u['total_mined'],
            'spent_coins' => (float)$u['spent_coins'], 'converted_coins' => (float)$u['converted_coins'],
            'usd' => (float)$u['usd_balance'], 'usd_earned' => (float)$u['usd_earned'], 'usd_withdrawn' => (float)$u['usd_withdrawn'],
            'usd_pending' => (float)$u['usd_pending'], 'tier' => $u['tier'], 'status' => $u['status'],
            'premium_boost' => (bool)$u['premium_boost'], 'ref_code' => $u['ref_code'], 'ref_link' => $refLink,
            'total_refs' => $refTotal, 'withdrawals' => $wdCount,
        ],
        'speed' => $sp,
        'skin' => ['current' => skin_current($u, $sp['level']['level'], skin_owned($uid)), 'auto' => !isset($u['skin_auto']) || (int)$u['skin_auto'] === 1],
        'mining' => $mining,
        'mine_room' => $room,
        'daily' => daily_info($u),
        'spin' => spin_info($u),
        'tap' => tap_info($u),
        'checkin' => checkin_info($u),
        'cfg' => [
            'app_name' => setting('app_name'), 'maintenance' => (bool)setting('maintenance'),
            'mine_enabled' => (bool)setting('mine_enabled'), 'mine_duration' => (int)setting('mine_duration'),
            'mine_interval' => (int)setting('mine_interval'),
            'conv_enabled' => (bool)setting('conv_enabled'), 'coins_per_usd' => (float)setting('conv_coins_per_usd'),
            'conv_min' => (float)setting('conv_min_coins'),
            'wd_enabled' => (bool)setting('wd_enabled'), 'wd_min' => (float)setting('wd_min'), 'wd_max' => (float)setting('wd_max'),
            'wd_methods' => wd_methods(),
            'premium_enabled' => (bool)setting('premium_enabled'), 'premium_url' => premium_url(),
            'premium_join_text' => setting('premium_join_text'), 'premium_message' => setting('premium_verify_message'),
            'premium_bonus' => (float)setting('premium_speed_bonus'),
            'ref_enabled' => (bool)setting('ref_enabled'), 'ref_speed_bonus' => (float)setting('ref_speed_bonus'),
            'ref_coin_bonus' => (float)setting('ref_coin_bonus'),
            'tasks_enabled' => (bool)setting('tasks_enabled'),
            'lb_mining' => (bool)setting('lb_mining_enabled'), 'lb_ref' => (bool)setting('lb_ref_enabled'),
            'support_url' => setting('support_url'),
            'levels' => levels_list(),
            'tiers' => ['free' => (float)setting('tier_mult_free'), 'premium' => (float)setting('tier_mult_premium'), 'vip' => (float)setting('tier_mult_vip')],
        ],
    ];
}

/* ---------- Dispatcher ---------- */
function mut($uid, $message)
{
    return ['message' => $message, 'state' => api_state($uid)];
}
function api_dispatch($action, $u, $in)
{
    $uid = (int)$u['id'];
    if ($action !== 'state' && setting('maintenance')) {
        throw new ApiError('Maintenance mode is on. Please come back soon.', 503);
    }
    switch ($action) {
        case 'state':
            return ['state' => api_state($uid)];
        case 'mine_start':
            rate_limit_or_fail("mine:$uid", 10, 60);
            mining_start($uid);
            return mut($uid, 'Mining started!');
        case 'mine_stop':
            rate_limit_or_fail("mine:$uid", 10, 60);
            mining_settle($uid, true);
            return mut($uid, 'Mining stopped.');
        case 'premium_verify':
            rate_limit_or_fail("prem:$uid", 6, 60);
            return mut($uid, act_premium_verify($uid));
        case 'convert':
            rate_limit_or_fail("conv:$uid", 8, 60);
            return mut($uid, act_convert($uid, (float)($in['coins'] ?? 0)));
        case 'withdraw':
            rate_limit_or_fail("wd:$uid", 5, 60);
            return mut($uid, act_withdraw($uid, (float)($in['amount'] ?? 0), (string)($in['method'] ?? ''), trim((string)($in['address'] ?? ''))));
        case 'withdraw_cancel':
            rate_limit_or_fail("wd:$uid", 5, 60);
            return mut($uid, act_withdraw_cancel($uid, (int)($in['id'] ?? 0)));
        case 'withdrawals':
            return ['items' => qa("SELECT id, amount, method, address, status, admin_note, created_at FROM withdrawals WHERE user_id=? ORDER BY id DESC LIMIT 30", [$uid])];
        case 'history':
            $before = (int)($in['before'] ?? 0);
            $sql = "SELECT id, tx_id, currency, amount, type, status, note, created_at FROM wallet_transactions WHERE user_id=?"
                . ($before > 0 ? " AND id < " . $before : "") . " ORDER BY id DESC LIMIT 30";
            return ['items' => qa($sql, [$uid])];
        case 'daily_claim':
            rate_limit_or_fail("claim:$uid", 10, 60);
            return mut($uid, act_daily_claim($uid));
        case 'checkin_claim':
            rate_limit_or_fail("claim:$uid", 10, 60);
            return mut($uid, act_checkin_claim($uid));
        case 'tasks':
            return ['items' => act_tasks($uid)];
        case 'task_start':
            return act_task_start($uid, (int)($in['id'] ?? 0));
        case 'task_claim':
            rate_limit_or_fail("task:$uid", 12, 60);
            return mut($uid, act_task_claim($uid, (int)($in['id'] ?? 0)));
        case 'referrals':
            return act_referrals($uid);
        case 'leaderboard':
            return act_leaderboard($uid);
        case 'tap':
            rate_limit_or_fail("tap:$uid", 90, 60);
            return act_tap($uid, (int)($in['n'] ?? 0));
        case 'spin':
            rate_limit_or_fail("spin:$uid", 6, 60);
            return act_spin($uid);
        case 'skins':
            return act_skins($uid);
        case 'skin_equip':
            rate_limit_or_fail("skin:$uid", 20, 60);
            return mut($uid, act_skin_equip($uid, (string)($in['id'] ?? '')));
        case 'skin_auto':
            rate_limit_or_fail("skin:$uid", 20, 60);
            return mut($uid, act_skin_auto($uid));
        case 'skin_buy':
            rate_limit_or_fail("skinbuy:$uid", 8, 60);
            return mut($uid, act_skin_buy($uid, (string)($in['id'] ?? ''), (string)($in['with'] ?? 'coins')));
        case 'notify_toggle':
            q("UPDATE users SET notify = 1 - notify WHERE id=?", [$uid]);
            return mut($uid, 'Notification setting updated.');
    }
    throw new ApiError('Unknown action.', 404);
}

/* ---------- Premium ---------- */
function act_premium_verify($uid)
{
    if (!setting('premium_enabled')) {
        throw new ApiError('Premium boost is not available.');
    }
    $u = q1("SELECT * FROM users WHERE id=?", [$uid]);
    if (q1("SELECT id FROM premium_memberships WHERE user_id=?", [$uid])) {
        return 'Your premium boost was already activated.';
    }
    $chat = trim((string)setting('premium_channel_id')) ?: trim((string)setting('premium_channel_username'));
    if ($chat === '') {
        throw new ApiError('Premium channel is not configured yet.');
    }
    $r = tg('getChatMember', ['chat_id' => $chat, 'user_id' => (int)$u['telegram_id']]);
    if (empty($r['ok'])) {
        throw new ApiError('Could not verify membership. Please try again later.');
    }
    $st = $r['result']['status'] ?? '';
    $member = in_array($st, ['creator', 'administrator', 'member'], true) || ($st === 'restricted' && !empty($r['result']['is_member']));
    if (!$member) {
        throw new ApiError('You have not joined the premium channel yet.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        q("SELECT id FROM users WHERE id=? FOR UPDATE", [$uid]);
        $ins = q("INSERT IGNORE INTO premium_memberships (user_id, channel, bonus_speed) VALUES (?,?,?)", [$uid, $chat, dec(setting('premium_speed_bonus'))]);
        if ($ins->rowCount() === 1) {
            q("UPDATE users SET premium_boost=1 WHERE id=?", [$uid]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    if ($ins->rowCount() === 1) {
        notify_user($uid, 'premium', "💎 <b>Premium Mining Boost Activated!</b>\n\n+" . fmt(setting('premium_speed_bonus')) . " coins/min on your next mining session.");
        return 'Premium Boost Activated! +' . fmt(setting('premium_speed_bonus')) . ' coins/min (applies from your next session).';
    }
    return 'Your premium boost was already activated.';
}

/* ---------- Conversion ---------- */
function act_convert($uid, $coins)
{
    if (!setting('conv_enabled')) {
        throw new ApiError('Conversion is currently disabled.');
    }
    $coins = floor($coins);
    $min = (float)setting('conv_min_coins');
    $rate = (float)setting('conv_coins_per_usd');
    if ($rate <= 0) {
        throw new ApiError('Conversion rate is not configured.');
    }
    if ($coins < $min || $coins <= 0) {
        throw new ApiError('Minimum conversion is ' . fmt($min, 0) . ' coins.');
    }
    $usd = floor($coins / $rate * 10000) / 10000;
    if ($usd <= 0) {
        throw new ApiError('Amount is too small.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $u = q1("SELECT * FROM users WHERE id=? FOR UPDATE", [$uid]);
        require_active($u);
        if ((float)$u['coins'] < $coins) {
            throw new ApiError('Not enough coins.');
        }
        q("UPDATE users SET coins=coins-?, spent_coins=spent_coins+?, converted_coins=converted_coins+?, usd_balance=usd_balance+?, usd_earned=usd_earned+? WHERE id=?",
            [dec($coins), dec($coins), dec($coins), dec($usd), dec($usd), $uid]);
        q("INSERT INTO conversions (user_id, coins, usd, rate) VALUES (?,?,?,?)", [$uid, dec($coins), dec($usd), dec($rate)]);
        $cid = (int)$pdo->lastInsertId();
        wallet_tx($uid, 'COIN', -$coins, 'conversion', 'Converted to USD', 'completed', $cid);
        wallet_tx($uid, 'USD', $usd, 'conversion', 'Converted from coins', 'completed', $cid);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return 'Converted ' . fmt($coins, 0) . ' coins to $' . fmt($usd, 4) . '.';
}

/* ---------- Withdrawals ---------- */
function act_withdraw($uid, $amount, $method, $address)
{
    if (!setting('wd_enabled')) {
        throw new ApiError('Withdrawals are currently disabled.');
    }
    $amount = floor($amount * 10000) / 10000;
    $min = (float)setting('wd_min');
    $max = (float)setting('wd_max');
    if (!in_array($method, wd_methods(), true)) {
        throw new ApiError('Choose a supported payment method.');
    }
    if (!validate_address($method, $address)) {
        throw new ApiError('Invalid wallet address / UID for ' . $method . '.');
    }
    if ($amount < $min) {
        throw new ApiError('Minimum withdrawal is $' . fmt($min) . '.');
    }
    if ($max > 0 && $amount > $max) {
        throw new ApiError('Maximum withdrawal per request is $' . fmt($max) . '.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $u = q1("SELECT * FROM users WHERE id=? FOR UPDATE", [$uid]);
        require_active($u);
        if ((float)$u['usd_balance'] < $amount) {
            throw new ApiError('Not enough USD balance.');
        }
        if (q1("SELECT id FROM withdrawals WHERE user_id=? AND status IN ('pending','approved')", [$uid])) {
            throw new ApiError('You already have a withdrawal in progress.');
        }
        $cool = (float)setting('wd_cooldown_hours');
        if ($cool > 0 && $u['last_withdraw_at'] && strtotime($u['last_withdraw_at']) + $cool * 3600 > time()) {
            $wait = ceil((strtotime($u['last_withdraw_at']) + $cool * 3600 - time()) / 60);
            throw new ApiError('Withdrawal cooldown active. Try again in ' . $wait . ' minutes.');
        }
        $daily = (float)setting('wd_daily_limit');
        if ($daily > 0) {
            $used = (float)qv("SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE user_id=? AND status IN ('pending','approved','paid') AND DATE(created_at)=CURDATE()", [$uid]);
            if ($used + $amount > $daily) {
                throw new ApiError('Daily withdrawal limit reached ($' . fmt($daily) . ').');
            }
        }
        q("UPDATE users SET usd_balance=usd_balance-?, usd_pending=usd_pending+?, last_withdraw_at=NOW() WHERE id=?", [dec($amount), dec($amount), $uid]);
        q("INSERT INTO withdrawals (user_id, amount, method, address) VALUES (?,?,?,?)", [$uid, dec($amount), $method, $address]);
        $wid = (int)$pdo->lastInsertId();
        wallet_tx($uid, 'USD', -$amount, 'withdrawal', $method, 'pending', $wid);
        // anti-fraud: same payout address used by another account
        $dup = (int)qv("SELECT COUNT(DISTINCT user_id) FROM withdrawals WHERE address=? AND user_id<>?", [$address, $uid]);
        if ($dup > 0) {
            flag_user($uid, 'Payout address shared with another account');
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return 'Withdrawal request submitted. It is now pending review.';
}

function act_withdraw_cancel($uid, $id)
{
    $w = q1("SELECT * FROM withdrawals WHERE id=? AND user_id=?", [$id, $uid]);
    if (!$w || $w['status'] !== 'pending') {
        throw new ApiError('Only pending withdrawals can be cancelled.');
    }
    withdrawal_set_status($id, 'cancelled', 'User', '');
    return 'Withdrawal cancelled and refunded.';
}

/**
 * Moves a withdrawal to a new status and keeps balances / ledger in sync.
 */
function withdrawal_set_status($id, $new, $by, $note = '', $payoutRef = '')
{
    $pdo = db();
    $notice = null;
    $uid = 0;
    $pdo->beginTransaction();
    try {
        $w = q1("SELECT * FROM withdrawals WHERE id=? FOR UPDATE", [$id]);
        if (!$w) {
            throw new ApiError('Withdrawal not found.');
        }
        $old = $w['status'];
        $uid = (int)$w['user_id'];
        $amt = (float)$w['amount'];
        $allowed = [
            'pending' => ['approved', 'paid', 'rejected', 'cancelled'],
            'approved' => ['paid', 'rejected'],
        ];
        if (!isset($allowed[$old]) || !in_array($new, $allowed[$old], true)) {
            throw new ApiError('This status change is not allowed.');
        }
        q("SELECT id FROM users WHERE id=? FOR UPDATE", [$uid]);
        if ($new === 'paid') {
            q("UPDATE users SET usd_pending=GREATEST(0,usd_pending-?), usd_withdrawn=usd_withdrawn+? WHERE id=?", [dec($amt), dec($amt), $uid]);
            q("UPDATE wallet_transactions SET status='paid' WHERE type='withdrawal' AND ref_id=?", [$id]);
            $notice = "💸 <b>Your withdrawal has been paid!</b>\n\nAmount: <b>$" . fmt($amt) . "</b> via " . h($w['method']) . ".";
        } elseif ($new === 'approved') {
            q("UPDATE wallet_transactions SET status='approved' WHERE type='withdrawal' AND ref_id=?", [$id]);
            $notice = "💸 <b>Your withdrawal has been approved!</b>\n\nAmount: <b>$" . fmt($amt) . "</b> via " . h($w['method']) . ".";
        } else { // rejected / cancelled -> refund
            q("UPDATE users SET usd_balance=usd_balance+?, usd_pending=GREATEST(0,usd_pending-?) WHERE id=?", [dec($amt), dec($amt), $uid]);
            q("UPDATE wallet_transactions SET status=? WHERE type='withdrawal' AND ref_id=?", [$new, $id]);
            wallet_tx($uid, 'USD', $amt, 'refund', 'Withdrawal ' . $new, 'completed', $id);
            if ($new === 'rejected') {
                $notice = "❌ <b>Your withdrawal was rejected.</b>\n\n$" . fmt($amt) . " was returned to your USD balance." . ($note !== '' ? "\nReason: " . h($note) : '');
            }
        }
        q("UPDATE withdrawals SET status=?, admin_note=?, payout_ref=COALESCE(NULLIF(?, ''), payout_ref), processed_by=?, updated_at=NOW() WHERE id=?",
            [$new, $note !== '' ? $note : $w['admin_note'], $payoutRef, $by, $id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    if ($notice) {
        notify_user($uid, 'withdrawal', $notice);
    }
}

/* ---------- Daily reward & check-in ---------- */
function act_daily_claim($uid)
{
    if (!setting('daily_enabled')) {
        throw new ApiError('Daily reward is disabled.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $u = q1("SELECT * FROM users WHERE id=? FOR UPDATE", [$uid]);
        require_active($u);
        $today = date('Y-m-d');
        $yest = date('Y-m-d', strtotime('-1 day'));
        if ($u['last_daily_date'] === $today) {
            throw new ApiError('You already claimed today\'s reward.');
        }
        $rewards = daily_rewards_list();
        $streak = ($u['last_daily_date'] === $yest) ? (int)$u['daily_streak'] + 1 : 1;
        $day = (($streak - 1) % count($rewards)) + 1;
        $reward = $rewards[$day - 1];
        q("INSERT INTO daily_rewards (user_id, day_no, reward, claim_date) VALUES (?,?,?,?)", [$uid, $day, dec($reward), $today]);
        q("UPDATE users SET daily_streak=?, last_daily_date=? WHERE id=?", [$streak, $today, $uid]);
        credit_coins($uid, $reward, 'daily_reward', 'Daily reward day ' . $day);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e instanceof PDOException && $e->getCode() === '23000') {
            throw new ApiError('You already claimed today\'s reward.');
        }
        throw $e;
    }
    return 'Daily reward claimed: +' . fmt($reward, 0) . ' coins (Day ' . $day . ').';
}

function act_checkin_claim($uid)
{
    if (!setting('checkin_enabled')) {
        throw new ApiError('Check-in is disabled.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $u = q1("SELECT * FROM users WHERE id=? FOR UPDATE", [$uid]);
        require_active($u);
        $today = date('Y-m-d');
        $yest = date('Y-m-d', strtotime('-1 day'));
        if ($u['last_checkin_date'] === $today) {
            throw new ApiError('You already checked in today.');
        }
        if ($u['last_checkin_date'] === $yest) {
            $streak = (int)$u['checkin_streak'] + 1;
        } else {
            $streak = (setting('checkin_reset') || !$u['last_checkin_date']) ? 1 : (int)$u['checkin_streak'] + 1;
        }
        $reward = (float)setting('checkin_reward');
        $days = (int)setting('checkin_streak_days');
        $bonus = ($days > 0 && $streak % $days === 0) ? (float)setting('checkin_streak_bonus') : 0.0;
        q("INSERT INTO daily_checkins (user_id, checkin_date, streak, reward) VALUES (?,?,?,?)", [$uid, $today, $streak, dec($reward + $bonus)]);
        q("UPDATE users SET checkin_streak=?, last_checkin_date=? WHERE id=?", [$streak, $today, $uid]);
        credit_coins($uid, $reward + $bonus, 'checkin', $streak . ' day streak');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e instanceof PDOException && $e->getCode() === '23000') {
            throw new ApiError('You already checked in today.');
        }
        throw $e;
    }
    return 'Check-in done! ' . $streak . ' day streak, +' . fmt($reward + $bonus, 0) . ' coins.';
}

/* ---------- Tasks ---------- */
function task_chat($t)
{
    if (!in_array($t['type'], ['telegram_channel', 'telegram_group'], true)) {
        return '';
    }
    if (trim((string)$t['chat_id']) !== '') {
        return trim($t['chat_id']);
    }
    if (preg_match('#t\.me/([A-Za-z][A-Za-z0-9_]{3,})#', (string)$t['url'], $m)) {
        return '@' . $m[1];
    }
    return '';
}
function act_tasks($uid)
{
    if (!setting('tasks_enabled')) {
        return [];
    }
    $rows = qa("SELECT t.*, c.started_at AS c_start, c.claimed_at AS c_claimed FROM tasks t
                LEFT JOIN task_claims c ON c.task_id=t.id AND c.user_id=?
                WHERE t.is_active=1 ORDER BY t.sort_order, t.id", [$uid]);
    $out = [];
    foreach ($rows as $t) {
        $status = $t['c_claimed'] ? 'done' : ($t['c_start'] ? 'started' : 'new');
        $min = (int)$t['min_seconds'] > 0 ? (int)$t['min_seconds'] : (int)setting('task_min_seconds');
        $wait = ($status === 'started' && task_chat($t) === '') ? max(0, (int)$t['c_start'] + $min - time()) : 0;
        $out[] = [
            'id' => (int)$t['id'], 'title' => $t['title'], 'description' => $t['description'], 'type' => $t['type'],
            'url' => $t['url'], 'reward' => (float)$t['reward'], 'status' => $status, 'wait' => $wait,
        ];
    }
    return $out;
}
function act_task_start($uid, $id)
{
    $t = q1("SELECT * FROM tasks WHERE id=? AND is_active=1", [$id]);
    if (!$t || !setting('tasks_enabled')) {
        throw new ApiError('Task not found.', 404);
    }
    q("INSERT IGNORE INTO task_claims (user_id, task_id, started_at) VALUES (?,?,?)", [$uid, $id, time()]);
    return ['url' => $t['url']];
}
function act_task_claim($uid, $id)
{
    $t = q1("SELECT * FROM tasks WHERE id=? AND is_active=1", [$id]);
    if (!$t || !setting('tasks_enabled')) {
        throw new ApiError('Task not found.', 404);
    }
    $u = q1("SELECT * FROM users WHERE id=?", [$uid]);
    require_active($u);
    $c = q1("SELECT * FROM task_claims WHERE user_id=? AND task_id=?", [$uid, $id]);
    if (!$c) {
        throw new ApiError('Start the task first.');
    }
    if ($c['claimed_at']) {
        throw new ApiError('Reward already claimed.');
    }
    $chat = task_chat($t);
    if ($chat !== '') {
        $r = tg('getChatMember', ['chat_id' => $chat, 'user_id' => (int)$u['telegram_id']]);
        if (empty($r['ok'])) {
            throw new ApiError('Could not verify this task right now. Try again later.');
        }
        $st = $r['result']['status'] ?? '';
        $ok = in_array($st, ['creator', 'administrator', 'member'], true) || ($st === 'restricted' && !empty($r['result']['is_member']));
        if (!$ok) {
            throw new ApiError('Task not completed yet. Join first, then verify.');
        }
    } else {
        $min = (int)$t['min_seconds'] > 0 ? (int)$t['min_seconds'] : (int)setting('task_min_seconds');
        if (time() - (int)$c['started_at'] < $min) {
            throw new ApiError('Please complete the task first (wait ' . ($min - (time() - (int)$c['started_at'])) . 's).');
        }
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = q("UPDATE task_claims SET claimed_at=NOW(), reward=? WHERE user_id=? AND task_id=? AND claimed_at IS NULL", [dec($t['reward']), $uid, $id]);
        if ($st->rowCount() !== 1) {
            throw new ApiError('Reward already claimed.');
        }
        credit_coins($uid, (float)$t['reward'], 'task', $t['title'], $id);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return 'Task complete: +' . fmt($t['reward'], 0) . ' coins.';
}

/* ---------- Referrals & leaderboard ---------- */
function act_referrals($uid)
{
    $rows = qa("SELECT r.is_active, r.bonus_paid, r.created_at, u.first_name, u.username
                FROM referrals r JOIN users u ON u.id=r.referred_id
                WHERE r.referrer_id=? ORDER BY r.id DESC LIMIT 40", [$uid]);
    $hist = [];
    foreach ($rows as $r) {
        $hist[] = ['name' => display_name($r), 'active' => (bool)$r['is_active'], 'bonus' => (float)$r['bonus_paid'], 'date' => $r['created_at']];
    }
    return [
        'total' => (int)qv("SELECT COUNT(*) FROM referrals WHERE referrer_id=?", [$uid]),
        'active' => (int)qv("SELECT COUNT(*) FROM referrals WHERE referrer_id=? AND is_active=1", [$uid]),
        'earnings' => (float)qv("SELECT COALESCE(SUM(bonus_paid),0) FROM referrals WHERE referrer_id=?", [$uid]),
        'history' => $hist,
    ];
}
function act_leaderboard($uid)
{
    $n = max(3, min(100, (int)setting('lb_size')));
    $out = ['mining' => null, 'referral' => null];
    if (setting('lb_mining_enabled')) {
        $rows = qa("SELECT id, first_name, username, total_mined FROM users WHERE status<>'banned' AND total_mined>0 ORDER BY total_mined DESC LIMIT " . $n);
        $list = [];
        foreach ($rows as $r) {
            $list[] = ['name' => display_name($r), 'value' => (float)$r['total_mined'], 'me' => (int)$r['id'] === $uid];
        }
        $mine = (float)qv("SELECT total_mined FROM users WHERE id=?", [$uid]);
        $rank = 1 + (int)qv("SELECT COUNT(*) FROM users WHERE status<>'banned' AND total_mined>?", [dec($mine)]);
        $out['mining'] = ['list' => $list, 'rank' => $rank];
    }
    if (setting('lb_ref_enabled')) {
        $rows = qa("SELECT u.id, u.first_name, u.username, COUNT(r.id) AS c FROM referrals r JOIN users u ON u.id=r.referrer_id
                    WHERE u.status<>'banned' GROUP BY u.id, u.first_name, u.username ORDER BY c DESC LIMIT " . $n);
        $list = [];
        foreach ($rows as $r) {
            $list[] = ['name' => display_name($r), 'value' => (int)$r['c'], 'me' => (int)$r['id'] === $uid];
        }
        $out['referral'] = ['list' => $list];
    }
    return $out;
}

/* ---------- Character skins (Hamster Boss) ---------- */
function skin_catalog()
{
    // id => [name, default unlock level (99 = purchase only), default coin price, default USD price]
    return [
        'classic' => ['Classic', 1, 0, 0],
        'miner' => ['Hard Hat Miner', 2, 3000, 0.15],
        'ceo' => ['Office Boss', 3, 8000, 0.40],
        'cool' => ['Cool Shades', 4, 20000, 1.00],
        'astro' => ['Astronaut', 5, 40000, 2.00],
        'king' => ['Diamond King', 6, 80000, 4.00],
        'ninja' => ['Shadow Ninja', 99, 60000, 3.00],
        'wizard' => ['Magic Wizard', 99, 100000, 5.00],
    ];
}
function skin_cfg($id)
{
    $d = skin_catalog()[$id];
    return [
        'id' => $id, 'name' => $d[0],
        'lv' => (int)setting("skin_{$id}_lv", $d[1]),
        'coins' => (float)setting("skin_{$id}_coins", $d[2]),
        'usd' => (float)setting("skin_{$id}_usd", $d[3]),
        'on' => setting("skin_{$id}_on", '1') === '1',
    ];
}
function skin_owned($uid)
{
    $out = [];
    foreach (qa("SELECT skin FROM user_skins WHERE user_id=?", [$uid]) as $r) {
        $out[] = $r['skin'];
    }
    return $out;
}
function skin_available($id, $c, $level, $owned)
{
    return $c['on'] && (in_array($id, $owned, true) || ($c['lv'] < 99 && $c['lv'] <= $level));
}
function skin_current($u, $level, $owned)
{
    $cat = skin_catalog();
    $cur = isset($u['skin']) ? (string)$u['skin'] : '';
    $auto = !isset($u['skin_auto']) || (int)$u['skin_auto'] === 1;
    if (!$auto && $cur !== '' && isset($cat[$cur]) && skin_available($cur, skin_cfg($cur), $level, $owned)) {
        return $cur;
    }
    $best = 'classic';
    $bl = -1;
    foreach ($cat as $id => $d) {
        $c = skin_cfg($id);
        if ($c['on'] && $c['lv'] < 99 && $c['lv'] <= $level && $c['lv'] >= $bl) {
            $best = $id;
            $bl = $c['lv'];
        }
    }
    return $best;
}
function act_skins($uid)
{
    $u = q1("SELECT * FROM users WHERE id=?", [$uid]);
    $level = user_speed($u)['level']['level'];
    $owned = skin_owned($uid);
    $cur = skin_current($u, $level, $owned);
    $items = [];
    foreach (skin_catalog() as $id => $d) {
        $c = skin_cfg($id);
        if (!$c['on']) {
            continue;
        }
        $items[] = [
            'id' => $id, 'name' => $c['name'], 'lv' => $c['lv'], 'coins' => $c['coins'], 'usd' => $c['usd'],
            'available' => skin_available($id, $c, $level, $owned), 'owned' => in_array($id, $owned, true), 'equipped' => $id === $cur,
        ];
    }
    return ['items' => $items, 'auto' => !isset($u['skin_auto']) || (int)$u['skin_auto'] === 1, 'level' => $level];
}
function act_skin_equip($uid, $id)
{
    if (!isset(skin_catalog()[$id])) {
        throw new ApiError('Unknown character.');
    }
    $u = q1("SELECT * FROM users WHERE id=?", [$uid]);
    $level = user_speed($u)['level']['level'];
    if (!skin_available($id, skin_cfg($id), $level, skin_owned($uid))) {
        throw new ApiError('This character is locked.');
    }
    q("UPDATE users SET skin=?, skin_auto=0 WHERE id=?", [$id, $uid]);
    return 'Character equipped.';
}
function act_skin_auto($uid)
{
    q("UPDATE users SET skin_auto=1 WHERE id=?", [$uid]);
    return 'Characters now follow your level.';
}
function act_skin_buy($uid, $id, $with)
{
    if (!isset(skin_catalog()[$id])) {
        throw new ApiError('Unknown character.');
    }
    $c = skin_cfg($id);
    if (!$c['on']) {
        throw new ApiError('This character is not available.');
    }
    $with = $with === 'usd' ? 'usd' : 'coins';
    $price = $with === 'usd' ? $c['usd'] : $c['coins'];
    if ($price <= 0) {
        throw new ApiError('This character cannot be bought with ' . ($with === 'usd' ? 'USD' : 'coins') . '.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $u = q1("SELECT * FROM users WHERE id=? FOR UPDATE", [$uid]);
        require_active($u);
        $level = user_speed($u)['level']['level'];
        if (skin_available($id, $c, $level, skin_owned($uid))) {
            throw new ApiError('You already have this character.');
        }
        if ($with === 'coins') {
            if ((float)$u['coins'] < $price) {
                throw new ApiError('Not enough coins.');
            }
            q("UPDATE users SET coins=coins-?, spent_coins=spent_coins+? WHERE id=?", [dec($price), dec($price), $uid]);
            wallet_tx($uid, 'COIN', -$price, 'skin', $c['name']);
        } else {
            if ((float)$u['usd_balance'] < $price) {
                throw new ApiError('Not enough USD balance.');
            }
            q("UPDATE users SET usd_balance=usd_balance-? WHERE id=?", [dec($price), $uid]);
            wallet_tx($uid, 'USD', -$price, 'skin', $c['name']);
        }
        q("INSERT INTO user_skins (user_id, skin, source, price) VALUES (?,?,?,?)", [$uid, $id, $with, dec($price)]);
        q("UPDATE users SET skin=?, skin_auto=0 WHERE id=?", [$id, $uid]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return 'New character unlocked: ' . $c['name'];
}

/* ---------- Lucky spin ---------- */
function spin_prizes()
{
    $p = [];
    foreach (explode(',', (string)setting('spin_prizes')) as $x) {
        $x = trim($x);
        if ($x !== '' && is_numeric($x) && (float)$x > 0) {
            $p[] = (float)$x;
        }
    }
    if (count($p) < 2) {
        $p = [50.0, 100.0, 200.0, 500.0, 1000.0, 2500.0];
    }
    return array_slice($p, 0, 10);
}
function spin_weights($n)
{
    $w = [];
    foreach (explode(',', (string)setting('spin_weights')) as $x) {
        $x = trim($x);
        if ($x !== '' && is_numeric($x) && (float)$x >= 0) {
            $w[] = (float)$x;
        }
    }
    if (count($w) !== $n || array_sum($w) <= 0) {
        $w = array_fill(0, $n, 1.0);
    }
    return $w;
}
function spin_info($u)
{
    $enabled = setting('spin_enabled') === '1';
    $cool = max(1, (float)setting('spin_cooldown_hours', 24)) * 3600;
    $last = !empty($u['last_spin_at']) ? strtotime($u['last_spin_at']) : 0;
    $next = $last ? max(0, (int)($last + $cool - time())) : 0;
    return ['enabled' => $enabled, 'ready' => $enabled && $next <= 0, 'next_in' => $next, 'prizes' => spin_prizes()];
}
function act_spin($uid)
{
    if (setting('spin_enabled') !== '1') {
        throw new ApiError('Lucky spin is disabled.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $u = q1("SELECT * FROM users WHERE id=? FOR UPDATE", [$uid]);
        require_active($u);
        $info = spin_info($u);
        if (!$info['ready']) {
            throw new ApiError('Your next spin is not ready yet.');
        }
        $prizes = $info['prizes'];
        $w = spin_weights(count($prizes));
        $total = array_sum($w);
        $r = (random_int(1, 1000000) / 1000000) * $total;
        $acc = 0;
        $idx = count($prizes) - 1;
        foreach ($w as $i => $x) {
            $acc += $x;
            if ($r <= $acc) {
                $idx = $i;
                break;
            }
        }
        $prize = $prizes[$idx];
        q("UPDATE users SET last_spin_at=NOW() WHERE id=?", [$uid]);
        credit_coins($uid, $prize, 'spin', 'Lucky spin');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return ['index' => $idx, 'prize' => $prize, 'state' => api_state($uid)];
}

/* ---------- Tap game (admin switch: tap_enabled) ---------- */
function tap_colors()
{
    $defaults = ['accent' => '#4fc3f7', 'accent2' => '#3b82f6', 'bg' => '#000000', 'card' => '#0a111c', 'text' => '#ffffff', 'coin' => '#2f6df0', 'glow' => '#38bdf8'];
    $out = [];
    foreach ($defaults as $k => $d) {
        $v = (string)setting('tap_color_' . $k, $d);
        $out[$k] = preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : $d;
    }
    return $out;
}
function tap_energy_now($u, $max, $regen)
{
    $now = time();
    $at = (int)(isset($u['tap_energy_at']) ? $u['tap_energy_at'] : 0);
    if ($at === 0) {
        return (float)$max;
    }
    $e = (float)$u['tap_energy'] + max(0, $now - $at) * $regen;
    return min((float)$max, $e);
}
function tap_info($u)
{
    $max = max(10, (float)setting('tap_energy_max', 1000));
    $regen = max(0.001, (float)setting('tap_regen', 0.1));
    $cap = (float)setting('tap_daily_cap', 0);
    $today = (isset($u['tap_date']) && $u['tap_date'] === date('Y-m-d')) ? (float)$u['tap_today'] : 0.0;
    $logo = trim((string)setting('tap_logo_url', ''));
    return [
        'enabled' => setting('tap_enabled') === '1',
        'open_mode' => in_array(setting('tap_open_mode', 'only'), ['tap', 'button'], true) ? setting('tap_open_mode') : 'only',
        'object' => setting('tap_object') === 'hamster' ? 'hamster' : 'coin',
        'per_tap' => (float)setting('tap_per_tap', 0.1),
        'max' => $max, 'energy' => round(tap_energy_now($u, $max, $regen), 3), 'energy_at' => time(), 'regen' => $regen,
        'daily_cap' => $cap, 'today' => $today, 'cap_left' => $cap > 0 ? max(0, $cap - $today) : null,
        'token' => (string)setting('tap_token_name', 'COINS'),
        'logo' => preg_match('#^https://\S+$#', $logo) ? $logo : '',
        'colors' => tap_colors(),
    ];
}
function act_tap($uid, $n)
{
    if (setting('tap_enabled') !== '1') {
        throw new ApiError('Tap game is turned off.');
    }
    $n = (int)$n;
    if ($n < 1) {
        throw new ApiError('No taps.');
    }
    $n = min($n, 150);
    $max = max(10, (float)setting('tap_energy_max', 1000));
    $regen = max(0.001, (float)setting('tap_regen', 0.1));
    $per = max(0, (float)setting('tap_per_tap', 0.1));
    $rate = max(1, (float)setting('tap_max_rate', 12));
    $cap = (float)setting('tap_daily_cap', 0);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $u = q1("SELECT * FROM users WHERE id=? FOR UPDATE", [$uid]);
        require_active($u);
        $now = time();
        $at = (int)$u['tap_energy_at'];
        $energy = tap_energy_now($u, $max, $regen);
        $elapsed = $at > 0 ? max(1, $now - $at) : 1;
        $byRate = (int)floor($elapsed * $rate) + 15;
        $accepted = min($n, (int)floor($energy), $byRate);
        $today = ($u['tap_date'] === date('Y-m-d')) ? (float)$u['tap_today'] : 0.0;
        if ($cap > 0 && $per > 0) {
            $accepted = min($accepted, (int)floor(max(0, $cap - $today) / $per));
        }
        $accepted = max(0, $accepted);
        $coins = round($accepted * $per, 4);
        q("UPDATE users SET tap_energy=?, tap_energy_at=?, tap_today=?, tap_date=? WHERE id=?",
            [dec($energy - $accepted, 3), $now, dec($today + $coins), date('Y-m-d'), $uid]);
        if ($coins > 0) {
            q("UPDATE users SET coins=coins+?, total_earned=total_earned+? WHERE id=?", [dec($coins), dec($coins), $uid]);
            $row = q1("SELECT id FROM wallet_transactions WHERE user_id=? AND type='tap' AND created_at >= CURDATE() ORDER BY id DESC LIMIT 1", [$uid]);
            if ($row) {
                q("UPDATE wallet_transactions SET amount=amount+? WHERE id=?", [dec($coins), $row['id']]);
            } else {
                wallet_tx($uid, 'COIN', $coins, 'tap', 'Tap & Earn');
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return ['accepted' => $accepted, 'state' => api_state($uid)];
}

