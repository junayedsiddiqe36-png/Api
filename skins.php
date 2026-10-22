<?php
require __DIR__ . '/_layout.php';
require_admin();
$cat = skin_catalog();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach ($cat as $id => $d) {
        save_setting("skin_{$id}_on", isset($_POST["on_$id"]) ? '1' : '0');
        save_setting("skin_{$id}_lv", (string)max(1, min(99, (int)($_POST["lv_$id"] ?? $d[1]))));
        save_setting("skin_{$id}_coins", (string)max(0, (float)($_POST["coins_$id"] ?? 0)));
        save_setting("skin_{$id}_usd", (string)max(0, (float)($_POST["usd_$id"] ?? 0)));
    }
    alog('skins_update');
    flash('Character settings saved.');
    back();
}
$stats = [];
foreach (qa("SELECT skin, COUNT(*) AS c, COALESCE(SUM(CASE WHEN source='coins' THEN price ELSE 0 END),0) AS coins, COALESCE(SUM(CASE WHEN source='usd' THEN price ELSE 0 END),0) AS usd FROM user_skins GROUP BY skin") as $r) {
    $stats[$r['skin']] = $r;
}
admin_head('Characters (skins)', 'skins');
echo '<p class="muted">Used by the Hamster Boss theme. A character unlocks automatically when the user reaches its level. Users can also buy it earlier with coins or with their USD wallet balance. Set the level to 99 for purchase-only characters, and a price of 0 to disable that payment method.</p>';
echo '<form method="post">' . csrf_field() . '<div class="cols">';
foreach ($cat as $id => $d) {
    $c = skin_cfg($id);
    $st = isset($stats[$id]) ? $stats[$id] : null;
    echo '<div class="card"><div class="row"><b>' . h($d[0]) . '</b><label class="chk" style="margin:0"><input type="checkbox" name="on_' . h($id) . '" value="1" ' . ($c['on'] ? 'checked' : '') . '> Enabled</label></div>'
        . '<div class="evrow"><div><label>Unlock level (99 = buy only)</label><input type="number" min="1" max="99" name="lv_' . h($id) . '" value="' . (int)$c['lv'] . '"></div>'
        . '<div><label>Price in coins</label><input type="number" step="any" min="0" name="coins_' . h($id) . '" value="' . h($c['coins']) . '"></div>'
        . '<div><label>Price in USD</label><input type="number" step="any" min="0" name="usd_' . h($id) . '" value="' . h($c['usd']) . '"></div></div>'
        . '<p class="muted">' . ($st ? (int)$st['c'] . ' bought · ' . fmt($st['coins'], 0) . ' coins · $' . fmt($st['usd']) : 'No purchases yet') . '</p></div>';
}
echo '</div><button class="btn gold">Save characters</button></form>';
admin_foot();
