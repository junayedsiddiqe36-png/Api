<?php
require __DIR__ . '/_layout.php';
require_admin();
$schema = settings_schema();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $do = $_POST['do'] ?? 'save';
    if ($do === 'webhook') {
        $token = (string)setting('bot_token');
        if ($token === '') {
            flash('Set the bot token first.', 'err');
        } else {
            $r = tg('setWebhook', ['url' => rtrim(BASE_URL, '/') . '/webhook.php', 'secret_token' => WEBHOOK_SECRET,
                'allowed_updates' => ['message', 'callback_query'], 'drop_pending_updates' => true]);
            tg('setChatMenuButton', ['menu_button' => ['type' => 'web_app', 'text' => 'Open App', 'web_app' => ['url' => app_url()]]]);
            flash(!empty($r['ok']) ? 'Webhook and menu button set.' : 'Webhook error: ' . ($r['description'] ?? 'unknown'), !empty($r['ok']) ? 'ok' : 'err');
            alog('set_webhook');
        }
        back();
    }
    if ($do === 'promo_test') {
        $r = promotions_post();
        flash(!empty($r['ok']) ? 'Test promotion post sent.' : 'Failed: ' . ($r['description'] ?? 'unknown error'), !empty($r['ok']) ? 'ok' : 'err');
        alog('promo_test');
        back();
    }
    $changed = [];
    foreach ($schema as $fields) {
        foreach ($fields as $f) {
            $k = $f[0];
            $type = $f[2];
            if ($type === 'bool') {
                $val = isset($_POST[$k]) ? '1' : '0';
            } else {
                if (!isset($_POST[$k])) {
                    continue;
                }
                $val = trim((string)$_POST[$k]);
                if ($type === 'secret' && $val === '') {
                    continue; // keep existing secret
                }
                if ($type === 'number' && !is_numeric($val)) {
                    flash('Invalid number for: ' . $f[1], 'err');
                    back();
                }
                if ($type === 'select' && !isset($f[4][$val])) {
                    continue;
                }
                if ($type === 'textarea') {
                    $val = str_replace("\r\n", "\n", (string)$_POST[$k]);
                }
            }
            if ((string)setting($k) !== (string)$val) {
                $changed[] = $k;
                save_setting($k, $val);
            }
        }
    }
    if ($changed) {
        alog('settings_update', null, implode(', ', array_diff($changed, ['bot_token'])) . (in_array('bot_token', $changed) ? ' bot_token(changed)' : ''));
    }
    flash('Settings saved.');
    back();
}
$gicons = [
    'Mining' => ['tool', 'gold'], 'Miner tiers (multiplier)' => ['layers', 'violet'], 'Referral' => ['users', 'blue'],
    'Premium channel boost' => ['gem', 'violet'], 'Conversion' => ['swap', 'teal'], 'Withdrawal' => ['up', 'green'],
    'Daily reward' => ['gift', 'orange'], 'Daily check-in' => ['check', 'green'], 'Lucky spin' => ['wheel', 'gold'],
    'Tasks' => ['tasks', 'teal'], 'Leaderboard' => ['award', 'gold'], 'Payment success announcements' => ['send', 'green'],
    'Promotion channel (real stats auto-post)' => ['send', 'blue'], 'Notifications' => ['bell', 'orange'],
    'Bot & app' => ['cpu', 'blue'], 'System' => ['gear', 'red'],
];
admin_head('Settings', 'settings');
echo '<div class="cols"><form method="post" class="card">' . csrf_field() . '<input type="hidden" name="do" value="webhook">'
    . '<b>Bot webhook</b><p class="muted">Run this after saving the bot token.</p><button class="btn">Set webhook &amp; menu button</button></form>'
    . '<form method="post" class="card">' . csrf_field() . '<input type="hidden" name="do" value="promo_test">'
    . '<b>Promotion test</b><p class="muted">Save settings first, then send one test post.</p><button class="btn">Send test post</button></form></div>';
echo '<div class="secttl">Setting groups (tap a box to open)</div>';
echo '<form method="post">' . csrf_field() . '<input type="hidden" name="do" value="save"><div class="tiles">';
$gi = 0;
foreach ($schema as $group => $fields) {
    $ic = isset($gicons[$group]) ? $gicons[$group] : ['gear', 'gold'];
    echo '<button type="button" class="tileg t-' . $ic[1] . '" data-open="g' . $gi . '"><span class="ti">' . aicon($ic[0]) . '</span><b>' . h($group) . '</b><small>' . count($fields) . ' options</small></button>';
    $gi++;
}
echo '</div>';
$gi = 0;
foreach ($schema as $group => $fields) {
    echo '<div class="panel" id="g' . $gi . '"><div class="sheet"><div class="phead"><b>' . h($group) . '</b><button type="button" class="pclose" data-close>&times;</button></div><div class="fgrid">';
    foreach ($fields as $f) {
        list($k, $label, $type) = $f;
        $val = setting($k);
        if ($type === 'bool') {
            echo '<label class="fbox sw"><span>' . h($label) . '</span><input type="checkbox" class="swc" name="' . h($k) . '" value="1" ' . ($val === '1' ? 'checked' : '') . '></label>';
        } elseif ($type === 'textarea') {
            echo '<div class="fbox wide"><label>' . h($label) . '</label><textarea name="' . h($k) . '" rows="5">' . h($val) . '</textarea></div>';
        } elseif ($type === 'select') {
            echo '<div class="fbox"><label>' . h($label) . '</label><select name="' . h($k) . '">';
            foreach ($f[4] as $ov => $ol) {
                echo '<option value="' . h($ov) . '" ' . ($val === (string)$ov ? 'selected' : '') . '>' . h($ol) . '</option>';
            }
            echo '</select></div>';
        } elseif ($type === 'secret') {
            echo '<div class="fbox"><label>' . h($label) . '</label><input type="password" name="' . h($k) . '" placeholder="' . ($val !== '' ? 'saved (blank = keep)' : 'not set') . '" autocomplete="new-password"></div>';
        } else {
            echo '<div class="fbox"><label>' . h($label) . '</label><input type="' . ($type === 'number' ? 'number' : 'text') . '" ' . ($type === 'number' ? 'step="any"' : '') . ' name="' . h($k) . '" value="' . h($val) . '"></div>';
        }
    }
    echo '</div><div class="pfoot"><button class="btn gold">Save all settings</button></div></div></div>';
    $gi++;
}
echo '</form>';
admin_foot();
