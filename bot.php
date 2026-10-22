<?php
function bot_keyboard($u)
{
    $rows = [[['text' => '⛏️ OPEN MINING APP', 'web_app' => ['url' => app_url()]]]];
    $row2 = [['text' => '👥 Referral Link', 'callback_data' => 'ref']];
    $support = trim((string)setting('support_url'));
    if ($support !== '') {
        $row2[] = ['text' => '💬 Support', 'url' => $support];
    }
    $rows[] = $row2;
    $row3 = [];
    $prem = premium_url();
    if ($prem !== '' && setting('premium_enabled')) {
        $row3[] = ['text' => '💎 Premium Channel', 'url' => $prem];
    }
    $row3[] = ['text' => '🔔 Notifications: ' . ($u['notify'] ? 'ON' : 'OFF'), 'callback_data' => 'notif'];
    $rows[] = $row3;
    return ['inline_keyboard' => $rows];
}

function bot_referral_text($u)
{
    $bot = ltrim((string)setting('bot_username'), '@');
    $link = 'https://t.me/' . $bot . '?start=' . $u['ref_code'];
    return [
        "👥 <b>Your referral link</b>\n\n<code>" . h($link) . "</code>\n\nEarn +" . fmt(setting('ref_coin_bonus'), 0) . " coins and +" . fmt(setting('ref_speed_bonus')) . " coins/min for every active friend.",
        ['inline_keyboard' => [[['text' => '📤 Share link', 'url' => 'https://t.me/share/url?url=' . rawurlencode($link) . '&text=' . rawurlencode('Start mining with me!')]]]],
    ];
}

function bot_send($chatId, $text, $markup = null)
{
    $p = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
    if ($markup) {
        $p['reply_markup'] = $markup;
    }
    return tg('sendMessage', $p);
}

function bot_handle($upd)
{
    if (isset($upd['callback_query'])) {
        $cb = $upd['callback_query'];
        $from = $cb['from'];
        $u = q1("SELECT * FROM users WHERE telegram_id=?", [$from['id']]);
        if (!$u || $u['status'] === 'banned') {
            tg('answerCallbackQuery', ['callback_query_id' => $cb['id']]);
            return;
        }
        $chat = $cb['message']['chat']['id'] ?? $from['id'];
        if ($cb['data'] === 'ref') {
            list($t, $m) = bot_referral_text($u);
            bot_send($chat, $t, $m);
        } elseif ($cb['data'] === 'notif') {
            q("UPDATE users SET notify=1-notify WHERE id=?", [$u['id']]);
            $u['notify'] = 1 - (int)$u['notify'];
            tg('editMessageReplyMarkup', ['chat_id' => $chat, 'message_id' => $cb['message']['message_id'], 'reply_markup' => bot_keyboard($u)]);
        }
        tg('answerCallbackQuery', ['callback_query_id' => $cb['id'], 'text' => $cb['data'] === 'notif' ? 'Notifications ' . ($u['notify'] ? 'ON' : 'OFF') : '']);
        return;
    }

    $m = $upd['message'] ?? null;
    if (!$m || ($m['chat']['type'] ?? '') !== 'private' || empty($m['from'])) {
        return;
    }
    $from = $m['from'];
    $text = trim((string)($m['text'] ?? ''));
    $chat = $m['chat']['id'];
    if (!rate_limit('bot:' . $from['id'], 20, 60)) {
        return;
    }
    $cmd = '';
    $arg = '';
    if (preg_match('#^/(\w+)(?:@\w+)?(?:\s+(.*))?$#s', $text, $mm)) {
        $cmd = strtolower($mm[1]);
        $arg = trim($mm[2] ?? '');
    }
    try {
        $u = register_user((int)$from['id'], $from['username'] ?? null, $from['first_name'] ?? null, $cmd === 'start' ? $arg : null);
    } catch (ApiError $e) {
        bot_send($chat, h($e->getMessage()));
        return;
    }
    if ($u['status'] === 'banned') {
        bot_send($chat, '🚫 Your account is banned.');
        return;
    }
    switch ($cmd) {
        case 'referral':
            list($t, $mk) = bot_referral_text($u);
            bot_send($chat, $t, $mk);
            return;
        case 'support':
            $s = trim((string)setting('support_url'));
            bot_send($chat, $s !== '' ? '💬 Support: ' . h($s) : '💬 Support is not configured yet.');
            return;
        case 'premium':
            $p = premium_url();
            bot_send($chat, $p !== '' ? '💎 Premium channel: ' . h($p) . "\nJoin, then open the app and tap VERIFY MEMBERSHIP." : '💎 Premium channel is not configured yet.');
            return;
        case 'notifications':
            q("UPDATE users SET notify=1-notify WHERE id=?", [$u['id']]);
            $u['notify'] = 1 - (int)$u['notify'];
            bot_send($chat, '🔔 Notifications are now ' . ($u['notify'] ? 'ON' : 'OFF') . '.', bot_keyboard($u));
            return;
    }
    $welcome = str_replace('{app_name}', h(setting('app_name')), (string)setting('welcome_text'));
    bot_send($chat, $welcome, bot_keyboard($u));
}
