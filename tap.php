<?php
require __DIR__ . '/_layout.php';
require_admin();
$colorDefs = [
    'accent' => ['Accent (numbers, highlights)', '#4fc3f7'], 'accent2' => ['Accent 2 (button gradient end)', '#3b82f6'],
    'bg' => ['Background', '#000000'], 'card' => ['Card background', '#0a111c'], 'text' => ['Text', '#ffffff'],
    'coin' => ['Coin colour', '#2f6df0'], 'glow' => ['Coin glow', '#38bdf8'],
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $do = $_POST['do'] ?? 'save';
    if ($do === 'reset_colors') {
        foreach ($colorDefs as $k => $d) {
            save_setting('tap_color_' . $k, $d[1]);
        }
        alog('tap_colors_reset');
        flash('Colours reset to the default blue style.');
        back();
    }
    $num = function ($key, $min, $max, $def) {
        $v = isset($_POST[$key]) && is_numeric($_POST[$key]) ? (float)$_POST[$key] : $def;
        return (string)max($min, min($max, $v));
    };
    save_setting('tap_enabled', isset($_POST['tap_enabled']) ? '1' : '0');
    $om = (string)($_POST['tap_open_mode'] ?? 'only');
    save_setting('tap_open_mode', in_array($om, ['tap', 'button'], true) ? $om : 'only');
    save_setting('tap_object', ($_POST['tap_object'] ?? '') === 'hamster' ? 'hamster' : 'coin');
    save_setting('tap_per_tap', $num('tap_per_tap', 0, 1000, 0.1));
    save_setting('tap_energy_max', $num('tap_energy_max', 10, 100000, 1000));
    save_setting('tap_regen', $num('tap_regen', 0.001, 1000, 0.1));
    save_setting('tap_max_rate', $num('tap_max_rate', 1, 50, 12));
    save_setting('tap_daily_cap', $num('tap_daily_cap', 0, 1000000000, 2000));
    save_setting('tap_token_name', mb_substr(trim(strip_tags((string)($_POST['tap_token_name'] ?? 'COINS'))), 0, 16) ?: 'COINS');
    $logo = trim((string)($_POST['tap_logo_url'] ?? ''));
    save_setting('tap_logo_url', preg_match('#^https://\S+$#', $logo) ? $logo : '');
    foreach ($colorDefs as $k => $d) {
        $c = (string)($_POST['color_' . $k] ?? '');
        save_setting('tap_color_' . $k, preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : $d[1]);
    }
    alog('tap_update', null, 'enabled=' . (isset($_POST['tap_enabled']) ? '1' : '0'));
    flash('Tap game settings saved.');
    back();
}
$on = setting('tap_enabled') === '1';
$perHour = (float)setting('tap_regen', 0.1) * 3600 * (float)setting('tap_per_tap', 0.1);
$cols = [];
foreach ($colorDefs as $k => $d) {
    $v = (string)setting('tap_color_' . $k, $d[1]);
    $cols[$k] = preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : $d[1];
}
admin_head('Tap Game', 'tap');
echo '<form method="post" id="tapform">' . csrf_field() . '<input type="hidden" name="do" value="save">';
echo '<div class="cols">'
    . '<label class="card fbox sw wide" style="padding:16px"><span><b style="font-size:16px">Tap game ' . ($on ? 'is ON' : 'is OFF') . '</b><br><small class="muted">When ON, users get a Tap &amp; Earn mini game (tap the coin, energy bar, daily rewards, tasks, refer and wallet pages).</small></span><input type="checkbox" class="swc" name="tap_enabled" value="1" ' . ($on ? 'checked' : '') . '></label>'
    . '<div class="fbox"><label>What the app looks like when Tap is ON</label><select name="tap_open_mode">'
    . '<option value="only" ' . (!in_array(setting('tap_open_mode', 'only'), ['tap', 'button'], true) ? 'selected' : '') . '>Tap game only (old mining app hidden)</option>'
    . '<option value="tap" ' . (setting('tap_open_mode') === 'tap' ? 'selected' : '') . '>Open in Tap game (users can go back to mining)</option>'
    . '<option value="button" ' . (setting('tap_open_mode') === 'button' ? 'selected' : '') . '>Button on the old home page</option></select></div>'
    . '<div class="fbox"><label>What users tap</label><select name="tap_object"><option value="coin" ' . (setting('tap_object') !== 'hamster' ? 'selected' : '') . '>Shiny coin</option><option value="hamster" ' . (setting('tap_object') === 'hamster' ? 'selected' : '') . '>Hamster boss (with character skins)</option></select></div>'
    . '<div class="fbox"><label>Token name shown next to the balance</label><input name="tap_token_name" maxlength="16" value="' . h(setting('tap_token_name', 'COINS')) . '"></div>'
    . '</div>';
echo '<div class="secttl">Gameplay</div><div class="fgrid">'
    . '<div class="fbox"><label>Coins per tap</label><input type="number" step="any" min="0" name="tap_per_tap" value="' . h(setting('tap_per_tap', 0.1)) . '"></div>'
    . '<div class="fbox"><label>Energy bar size (taps)</label><input type="number" step="1" min="10" name="tap_energy_max" value="' . h(setting('tap_energy_max', 1000)) . '"></div>'
    . '<div class="fbox"><label>Energy refill per second</label><input type="number" step="any" min="0.001" name="tap_regen" value="' . h(setting('tap_regen', 0.1)) . '"></div>'
    . '<div class="fbox"><label>Max taps per second (anti-cheat)</label><input type="number" step="1" min="1" name="tap_max_rate" value="' . h(setting('tap_max_rate', 12)) . '"></div>'
    . '<div class="fbox"><label>Daily coin limit per user (0 = none)</label><input type="number" step="any" min="0" name="tap_daily_cap" value="' . h(setting('tap_daily_cap', 2000)) . '"></div>'
    . '<div class="fbox"><label>Coin logo image link (https, optional)</label><input name="tap_logo_url" value="' . h(setting('tap_logo_url', '')) . '" placeholder="https://.../logo.png"></div>'
    . '</div><p class="muted">A player tapping non-stop is limited by the energy refill: about ' . fmt($perHour, 1) . ' coins per hour after the first full bar, and never more than the daily limit.</p>';
echo '<div class="secttl">Tap game colours</div><div class="fgrid">'
    . '<div class="fbox wide"><div class="tprev" id="tpv"><div class="tpc" id="tpc"></div><button type="button" class="tpb" id="tpb">Button</button><b id="tpt">12,450.250</b></div></div>';
foreach ($colorDefs as $k => $d) {
    echo '<div class="fbox"><label>' . h($d[0]) . '</label><input type="color" name="color_' . h($k) . '" data-c="' . h($k) . '" value="' . h($cols[$k]) . '"></div>';
}
echo '</div><div class="inline" style="margin-top:14px"><button class="btn gold">Save Tap game settings</button></div></form>';
echo '<form method="post" class="inline" style="margin-top:8px">' . csrf_field() . '<input type="hidden" name="do" value="reset_colors"><button class="btn" onclick="return confirm(\'Reset all Tap game colours?\')">Reset colours</button></form>';
echo '<script>(function(){var f=document.getElementById("tapform");function g(k){return f.querySelector("[data-c=\'"+k+"\']").value;}function mix(a,b,t){var x=parseInt(a.slice(1),16),y=parseInt(b.slice(1),16);function c(s){return Math.round(((x>>s)&255)+(((y>>s)&255)-((x>>s)&255))*t);}return "rgb("+c(16)+","+c(8)+","+c(0)+")";}'
    . 'function u(){var coin=g("coin"),p=document.getElementById("tpc");document.getElementById("tpv").style.background=g("bg");p.style.background="radial-gradient(circle at 34% 28%,"+mix(coin,"#ffffff",.4)+","+coin+" 60%,"+mix(coin,"#000000",.32)+")";p.style.boxShadow="0 0 28px "+g("glow");var b=document.getElementById("tpb");b.style.background="linear-gradient(180deg,"+g("accent")+","+g("accent2")+")";b.style.color="#02121f";var t=document.getElementById("tpt");t.style.color=g("accent");}'
    . 'f.addEventListener("input",u);u();})();</script>';
admin_foot();
