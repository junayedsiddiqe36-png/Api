<?php
/* Payment success announcements (real approved/paid withdrawals only). */
function announcements_run()
{
    if (!setting('ann_enabled')) {
        return;
    }
    $channel = trim((string)setting('ann_channel'));
    if ($channel === '') {
        return;
    }
    $interval = max(1, (int)setting('ann_interval')) * 60;
    if (time() - (int)setting('ann_last_run', 0) < $interval) {
        return;
    }
    save_setting('ann_last_run', (string)time());

    $statuses = setting('ann_statuses') === 'approved_paid' ? "'approved','paid'" : "'paid'";
    $rows = qa("SELECT w.*, u.telegram_id FROM withdrawals w
                JOIN users u ON u.id=w.user_id
                LEFT JOIN announcements a ON a.withdrawal_id=w.id
                WHERE a.id IS NULL AND w.status IN ($statuses) ORDER BY w.id ASC LIMIT 10");
    $bot = ltrim((string)setting('bot_username'), '@');
    $openUrl = trim((string)setting('ann_open_url')) ?: ($bot !== '' ? 'https://t.me/' . $bot : '');
    foreach ($rows as $w) {
        $claim = q("INSERT IGNORE INTO announcements (withdrawal_id) VALUES (?)", [$w['id']]);
        if ($claim->rowCount() !== 1) {
            continue;
        }
        $tid = (string)$w['telegram_id'];
        if (setting('ann_mask') && strlen($tid) > 5) {
            $tid = substr($tid, 0, 3) . str_repeat('*', strlen($tid) - 5) . substr($tid, -2);
        }
        $text = strtr((string)setting('ann_template'), [
            '{user_id}' => h($tid),
            '{amount}' => fmt($w['amount']),
            '{method}' => h($w['method']),
            '{status}' => strtoupper($w['status']),
        ]);
        $params = ['chat_id' => $channel, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
        if ($openUrl !== '') {
            $params['reply_markup'] = ['inline_keyboard' => [[['text' => 'OPEN APP', 'url' => $openUrl]]]];
        }
        $r = tg('sendMessage', $params);
        if (!empty($r['ok'])) {
            q("UPDATE announcements SET message_id=? WHERE withdrawal_id=?", [$r['result']['message_id'] ?? null, $w['id']]);
        } else {
            q("DELETE FROM announcements WHERE withdrawal_id=?", [$w['id']]); // retry next interval
            error_log('announcement failed: ' . ($r['description'] ?? ''));
            break;
        }
    }
}

/* Broadcast queue worker (sends to all non-banned users in batches). */
function broadcast_run($budget = 40)
{
    $b = q1("SELECT * FROM broadcasts WHERE status IN ('pending','running') ORDER BY id ASC LIMIT 1");
    if (!$b) {
        return;
    }
    if ($b['status'] === 'pending') {
        $total = (int)qv("SELECT COUNT(*) FROM users WHERE status<>'banned'");
        q("UPDATE broadcasts SET status='running', total=? WHERE id=?", [$total, $b['id']]);
    }
    $last = (int)$b['last_user_id'];
    $sent = (int)$b['sent'];
    $failed = (int)$b['failed'];
    $t0 = time();
    $markup = null;
    if ($b['btn_text'] && $b['btn_url']) {
        $markup = ['inline_keyboard' => [[['text' => $b['btn_text'], 'url' => $b['btn_url']]]]];
    }
    while (time() - $t0 < $budget) {
        $rows = qa("SELECT id, telegram_id FROM users WHERE id>? AND status<>'banned' ORDER BY id ASC LIMIT 25", [$last]);
        if (!$rows) {
            q("UPDATE broadcasts SET status='done', sent=?, failed=?, last_user_id=? WHERE id=?", [$sent, $failed, $last, $b['id']]);
            return;
        }
        foreach ($rows as $r) {
            $last = (int)$r['id'];
            if ($b['image_url']) {
                $p = ['chat_id' => $r['telegram_id'], 'photo' => $b['image_url'], 'caption' => mb_substr($b['body'], 0, 1000), 'parse_mode' => 'HTML'];
                $m = 'sendPhoto';
            } else {
                $p = ['chat_id' => $r['telegram_id'], 'text' => $b['body'], 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
                $m = 'sendMessage';
            }
            if ($markup) {
                $p['reply_markup'] = $markup;
            }
            $res = tg($m, $p);
            if (!empty($res['ok'])) {
                $sent++;
            } else {
                $failed++;
            }
            usleep(40000);
        }
        q("UPDATE broadcasts SET sent=?, failed=?, last_user_id=? WHERE id=?", [$sent, $failed, $last, $b['id']]);
    }
}

/* Promotion channel: periodic posts built ONLY from real numbers in your database. */
function promotions_build()
{
    $bot = ltrim((string)setting('bot_username'), '@');
    $total = (int)qv("SELECT COUNT(*) FROM users WHERE status<>'banned'");
    $active = (int)qv("SELECT COUNT(*) FROM mining_sessions WHERE status='active' AND ends_at>?", [time()]);
    $today = (float)qv("SELECT COALESCE(SUM(amount),0) FROM mining_transactions WHERE created_at >= CURDATE()");
    $paid = (float)qv("SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE status='paid'");
    $top = qa("SELECT first_name, username, total_mined FROM users WHERE status<>'banned' AND total_mined>0 ORDER BY total_mined DESC LIMIT 3");
    $medals = ['🥇', '🥈', '🥉'];
    $lines = [];
    foreach ($top as $i => $r) {
        $name = ltrim(display_name($r), '@');
        if (setting('promo_mask')) {
            $name = mb_substr($name, 0, 2) . '***';
        }
        $lines[] = $medals[$i] . ' ' . h($name) . ' — ' . fmt($r['total_mined'], 0);
    }
    if (!$lines) {
        $lines[] = '—';
    }
    $text = strtr((string)setting('promo_template'), [
        '{app_name}' => h(setting('app_name')),
        '{total_users}' => fmt($total, 0),
        '{active_miners}' => fmt($active, 0),
        '{coins_today}' => fmt($today, 0),
        '{top_miners}' => implode("\n", $lines),
        '{rate}' => fmt(setting('conv_coins_per_usd'), 0),
        '{min_withdraw}' => fmt(setting('wd_min')),
        '{total_paid}' => fmt($paid),
    ]);
    $url = trim((string)setting('promo_btn_url')) ?: ($bot !== '' ? 'https://t.me/' . $bot : '');
    $markup = null;
    if ($url !== '') {
        $markup = ['inline_keyboard' => [[['text' => (string)setting('promo_btn_text') ?: 'START MINING', 'url' => $url]]]];
    }
    return [$text, $markup];
}

function promotions_post()
{
    $channel = trim((string)setting('promo_channel'));
    if ($channel === '') {
        return ['ok' => false, 'description' => 'Promotion channel is not set'];
    }
    list($text, $markup) = promotions_build();
    $p = ['chat_id' => $channel, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
    if ($markup) {
        $p['reply_markup'] = $markup;
    }
    return tg('sendMessage', $p);
}

function promotions_run()
{
    if (!setting('promo_enabled') || trim((string)setting('promo_channel')) === '') {
        return;
    }
    $interval = max(1, (int)setting('promo_interval')) * 60;
    if (time() - (int)setting('promo_last_run', 0) < $interval) {
        return;
    }
    save_setting('promo_last_run', (string)time());
    $r = promotions_post();
    if (empty($r['ok'])) {
        error_log('promotion post failed: ' . ($r['description'] ?? ''));
    }
}

/* Auto message rotation: up to 10 admin-written posts, sent one by one every N minutes, then back to #1. */
function autopost_pick()
{
    $cur = (int)setting('fw_next', 1);
    if ($cur < 1 || $cur > 10) {
        $cur = 1;
    }
    for ($i = 0; $i < 10; $i++) {
        $n = (($cur - 1 + $i) % 10) + 1;
        if (trim((string)setting('fw_msg_' . $n)) !== '' || trim((string)setting('fw_img_' . $n)) !== '') {
            return $n;
        }
    }
    return 0;
}

function autopost_send($slot)
{
    $channel = trim((string)setting('fw_channel'));
    if ($channel === '') {
        return ['ok' => false, 'description' => 'Channel is not set'];
    }
    $text = trim((string)setting('fw_msg_' . $slot));
    $img = trim((string)setting('fw_img_' . $slot));
    $btnText = trim((string)setting('fw_btn_text'));
    $btnUrl = trim((string)setting('fw_btn_url'));
    $markup = ($btnText !== '' && $btnUrl !== '') ? ['inline_keyboard' => [[['text' => $btnText, 'url' => $btnUrl]]]] : null;
    if ($img !== '') {
        $p = ['chat_id' => $channel, 'photo' => $img, 'parse_mode' => 'HTML'];
        if (mb_strlen($text) <= 1000) {
            $p['caption'] = $text;
            if ($markup) {
                $p['reply_markup'] = $markup;
            }
            return tg('sendPhoto', $p);
        }
        $r = tg('sendPhoto', $p);
        if (empty($r['ok'])) {
            return $r;
        }
    }
    $p = ['chat_id' => $channel, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($markup) {
        $p['reply_markup'] = $markup;
    }
    return tg('sendMessage', $p);
}

function autopost_run()
{
    if (!setting('fw_enabled') || trim((string)setting('fw_channel')) === '') {
        return;
    }
    $interval = max(1, (int)setting('fw_interval', 10)) * 60;
    if (time() - (int)setting('fw_last_run', 0) < $interval) {
        return;
    }
    $slot = autopost_pick();
    if (!$slot) {
        return;
    }
    save_setting('fw_last_run', (string)time());
    save_setting('fw_next', (string)(($slot % 10) + 1));
    $r = autopost_send($slot);
    if (empty($r['ok'])) {
        error_log('autopost failed: ' . ($r['description'] ?? ''));
    }
}
