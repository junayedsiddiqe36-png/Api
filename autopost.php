<?php
require __DIR__ . '/_layout.php';
require_admin();
function https_ok($u)
{
    return $u === '' || (bool)preg_match('#^https://\S+$#', $u);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $do = $_POST['do'] ?? 'save';
    if ($do === 'test') {
        $slot = autopost_pick();
        if (!$slot) {
            flash('Write at least one message first (and save).', 'err');
        } else {
            $r = autopost_send($slot);
            flash(!empty($r['ok']) ? "Test sent: message #$slot." : 'Failed: ' . ($r['description'] ?? 'unknown error'), !empty($r['ok']) ? 'ok' : 'err');
        }
        back();
    }
    if ($do === 'reset') {
        save_setting('fw_next', '1');
        alog('autopost_reset');
        flash('The cycle will restart from message 1.');
        back();
    }
    $btnUrl = trim((string)($_POST['fw_btn_url'] ?? ''));
    $bad = !https_ok($btnUrl);
    for ($i = 1; $i <= 10; $i++) {
        if (!https_ok(trim((string)($_POST["fw_img_$i"] ?? '')))) {
            $bad = true;
        }
    }
    if ($bad) {
        flash('Button link and image links must start with https://', 'err');
        back();
    }
    save_setting('fw_enabled', isset($_POST['fw_enabled']) ? '1' : '0');
    save_setting('fw_channel', trim((string)($_POST['fw_channel'] ?? '')));
    save_setting('fw_interval', (string)max(1, (int)($_POST['fw_interval'] ?? 10)));
    save_setting('fw_btn_text', mb_substr(trim((string)($_POST['fw_btn_text'] ?? '')), 0, 60));
    save_setting('fw_btn_url', $btnUrl);
    for ($i = 1; $i <= 10; $i++) {
        save_setting("fw_msg_$i", str_replace("\r\n", "\n", (string)($_POST["fw_msg_$i"] ?? '')));
        save_setting("fw_img_$i", trim((string)($_POST["fw_img_$i"] ?? '')));
    }
    alog('autopost_update');
    flash('Auto posts saved.');
    back();
}
$next = (int)setting('fw_next', 1);
if ($next < 1 || $next > 10) {
    $next = 1;
}
$last = (int)setting('fw_last_run', 0);
admin_head('Auto posts', 'autopost');
echo '<p class="muted">Write up to 10 messages. Every N minutes the next one is posted to your channel (1 → 10, then back to 1). Empty boxes are skipped. '
    . 'Next message: <b>#' . $next . '</b> · Last post: ' . ($last ? h(date('Y-m-d H:i:s', $last)) : 'never') . '</p>';
echo '<form method="post">' . csrf_field() . '<input type="hidden" name="do" value="save"><div class="card">'
    . '<label class="chk"><input type="checkbox" name="fw_enabled" value="1" ' . (setting('fw_enabled') === '1' ? 'checked' : '') . '> Auto posting enabled</label>'
    . '<label>Channel (@username or -100... ID; the bot must be an admin there)</label><input name="fw_channel" value="' . h(setting('fw_channel')) . '">'
    . '<label>Send a message every (minutes)</label><input type="number" min="1" name="fw_interval" value="' . h(setting('fw_interval', 10)) . '">'
    . '<label>Button name (shown under every message, e.g. Support)</label><input name="fw_btn_text" value="' . h(setting('fw_btn_text', 'Support')) . '" placeholder="Support">'
    . '<label>Button link (https://...)</label><input name="fw_btn_url" value="' . h(setting('fw_btn_url')) . '" placeholder="https://t.me/yoursupport">'
    . '<p class="muted">Leave the button name or link empty to post without a button.</p></div>';
echo '<div class="secttl">Messages 1 to 10</div><div class="cols">';
for ($i = 1; $i <= 10; $i++) {
    echo '<details class="card" ' . ($i === 1 ? 'open' : '') . '><summary>Message ' . $i . (trim((string)setting("fw_msg_$i")) !== '' ? ' ✓' : '') . '</summary>'
        . '<label>Text (HTML allowed: &lt;b&gt; &lt;i&gt; &lt;a href=...&gt;)</label><textarea name="fw_msg_' . $i . '" rows="5">' . h(setting("fw_msg_$i")) . '</textarea>'
        . '<label>Photo link (optional, https://...)</label><input name="fw_img_' . $i . '" value="' . h(setting("fw_img_$i")) . '"></details>';
}
echo '</div><button class="btn gold">Save auto posts</button></form>';
echo '<div class="inline" style="margin-top:12px"><form method="post">' . csrf_field() . '<input type="hidden" name="do" value="test"><button class="btn">Send next message now (test)</button></form>'
    . '<form method="post">' . csrf_field() . '<input type="hidden" name="do" value="reset"><button class="btn">Restart from message 1</button></form></div>';
admin_foot();
