<?php
require __DIR__ . '/_layout.php';
require_admin();
$all = theme_list();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $theme = (string)($_POST['theme'] ?? '');
    if (isset($all[$theme])) {
        save_setting('app_theme', $theme);
    }
    save_setting('theme_mode', ($_POST['mode'] ?? '') === 'daynight' ? 'daynight' : 'fixed');
    foreach (['theme_day', 'theme_night'] as $k) {
        $v = (string)($_POST[$k] ?? '');
        if (isset($all[$v])) {
            save_setting($k, $v);
        }
    }
    for ($i = 1; $i <= 3; $i++) {
        $th = (string)($_POST["ev{$i}_theme"] ?? '');
        $from = (string)($_POST["ev{$i}_from"] ?? '');
        $to = (string)($_POST["ev{$i}_to"] ?? '');
        $okd = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to);
        save_setting("theme_ev{$i}_theme", isset($all[$th]) && $okd ? $th : '');
        save_setting("theme_ev{$i}_from", isset($all[$th]) && $okd ? $from : '');
        save_setting("theme_ev{$i}_to", isset($all[$th]) && $okd ? $to : '');
    }
    foreach ($all as $id => $t) {
        save_setting('theme_lv_' . $id, (string)max(1, (int)($_POST['lv_' . $id] ?? $t[4])));
    }
    save_setting('theme_user_choice', isset($_POST['user_choice']) ? '1' : '0');
    alog('theme_update', null, $theme . ' mode=' . ($_POST['mode'] ?? '') . ' user_choice=' . (isset($_POST['user_choice']) ? '1' : '0'));
    flash('Theme settings saved. Users see them the next time they open the app.');
    back();
}
$cur = app_theme();
$mode = setting('theme_mode') === 'daynight' ? 'daynight' : 'fixed';
$uc = setting('theme_user_choice') === '1';
function theme_select($name, $selected, $all, $blank = false)
{
    $o = '<select name="' . h($name) . '">' . ($blank ? '<option value="">none</option>' : '');
    foreach ($all as $id => $t) {
        $o .= '<option value="' . h($id) . '" ' . ($id === $selected ? 'selected' : '') . '>' . h($t[0]) . '</option>';
    }
    return $o . '</select>';
}
admin_head('Theme', 'theme');
echo '<form method="post">' . csrf_field();
echo '<div class="cols">'
    . '<label class="card fbox sw wide" style="padding:16px"><span><b style="font-size:16px">Users can change the theme: ' . ($uc ? 'ON' : 'OFF') . '</b><br><small class="muted">Turn this OFF and users cannot pick a theme themselves. Everyone sees the theme you choose below.</small></span><input type="checkbox" class="swc" name="user_choice" value="1" ' . ($uc ? 'checked' : '') . '></label>'
    . '<div class="card"><b>Theme mode</b>'
    . '<label class="chk"><input type="radio" name="mode" value="fixed" ' . ($mode === 'fixed' ? 'checked' : '') . '> Fixed theme</label>'
    . '<label class="chk"><input type="radio" name="mode" value="daynight" ' . ($mode === 'daynight' ? 'checked' : '') . '> Auto day / night (06:00 to 18:00 day)</label></div>'
    . '<div class="card"><b>Day and night themes</b><div class="fbox" style="margin-top:8px"><label>Day theme</label>' . theme_select('theme_day', setting('theme_day', 'gold'), $all) . '</div><div class="fbox" style="margin-top:8px"><label>Night theme</label>' . theme_select('theme_night', setting('theme_night', 'violet'), $all) . '</div></div>';
echo '<div class="card wide"><b>Fixed theme and unlock levels</b><p class="muted">Pick the theme everyone sees. The number is the miner level needed before a user can choose that theme (1 = open to all).</p><div class="themegrid">';
foreach ($all as $id => $t) {
    $lv = (int)setting('theme_lv_' . $id, $t[4]);
    echo '<label class="themecard' . ($id === $cur ? ' on' : '') . '"><input type="radio" name="theme" value="' . h($id) . '" ' . ($id === $cur ? 'checked' : '') . '>'
        . '<span class="sw" style="background:linear-gradient(135deg,' . h($t[1]) . ',' . h($t[2]) . ')"></span>'
        . '<b>' . h($t[0]) . '</b><small>' . h($t[3]) . '</small>'
        . '<span class="lv">Unlock level <input type="number" min="1" max="50" name="lv_' . h($id) . '" value="' . $lv . '"></span></label>';
}
echo '</div></div>';
for ($i = 1; $i <= 3; $i++) {
    echo '<div class="card"><b>Festival theme ' . $i . '</b><div class="fbox" style="margin-top:8px"><label>Theme</label>' . theme_select("ev{$i}_theme", (string)setting("theme_ev{$i}_theme", ''), $all, true) . '</div>'
        . '<div class="fgrid" style="margin-top:8px"><div class="fbox"><label>From</label><input type="date" name="ev' . $i . '_from" value="' . h(setting("theme_ev{$i}_from", '')) . '"></div>'
        . '<div class="fbox"><label>To</label><input type="date" name="ev' . $i . '_to" value="' . h(setting("theme_ev{$i}_to", '')) . '"></div></div></div>';
}
echo '</div><p class="muted">During a festival date range the festival theme is applied to everyone and users cannot change it.</p><button class="btn gold">Save theme settings</button></form>';
admin_foot();
