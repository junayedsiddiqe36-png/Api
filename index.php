<?php
require __DIR__ . '/../includes/bootstrap.php';
$en = require __DIR__ . '/../lang/en.php';
$langs = ['en' => $en];
foreach (glob(__DIR__ . '/../lang/*.php') as $f) {
    $c = basename($f, '.php');
    if ($c === 'en' || !preg_match('/^[a-z]{2,5}$/', $c)) {
        continue;
    }
    $arr = require $f;
    if (is_array($arr)) {
        $langs[$c] = array_merge($en, $arr);
    }
}
$code = preg_replace('/[^a-z_]/i', '', (string)setting('default_lang', 'en')) ?: 'en';
if (!isset($langs[$code])) {
    $code = 'en';
}
$tcfg = theme_config();
$base = rtrim(BASE_URL, '/');
$v = @filemtime(__DIR__ . '/../assets/app.js') ?: 1;
header('Cache-Control: no-store');
?><!doctype html>
<html lang="<?= h($code) ?>"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#0b0e11">
<title><?= h(setting('app_name')) ?></title>
<script>(function(){var C=<?= json_encode($tcfg) ?>,th=C.theme;function dn(){var h=new Date().getHours();return (h>=18||h<6)?C.night:C.day;}try{if(!C.forced){var mine=localStorage.getItem('miner_theme'),ver=localStorage.getItem('miner_theme_admin');if(C.allow&&mine&&ver===C.ver){th=mine;}else if(C.mode==='daynight'){th=dn();}}}catch(e){if(!C.forced&&C.mode==='daynight'){th=dn();}}document.documentElement.setAttribute('data-theme',th);window.APP_THEME=C;})();</script>
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<link rel="stylesheet" href="<?= h($base) ?>/assets/app.css?v=<?= $v ?>">
</head><body>
<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>
<linearGradient id="gGold" x1="0" y1="0" x2="1" y2="1"><stop offset="0" style="stop-color:var(--c1)"/><stop offset=".55" style="stop-color:var(--c2)"/><stop offset="1" style="stop-color:var(--c3)"/></linearGradient>
<linearGradient id="gGreen" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#7cf0b8"/><stop offset="1" stop-color="#1fb86e"/></linearGradient>
<radialGradient id="gFur" cx="38%" cy="28%" r="80%"><stop offset="0" stop-color="#f9d093"/><stop offset=".55" stop-color="#e3a45d"/><stop offset="1" stop-color="#c5833b"/></radialGradient>
<radialGradient id="gCream" cx="40%" cy="30%" r="80%"><stop offset="0" stop-color="#ffffff"/><stop offset="1" stop-color="#ffe3b8"/></radialGradient>
<linearGradient id="gDia" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#eaffff"/><stop offset=".5" stop-color="#7dd3fc"/><stop offset="1" stop-color="#2f8fe0"/></linearGradient>
<linearGradient id="gSteel" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#f3f6fb"/><stop offset=".5" stop-color="#b4bfce"/><stop offset="1" stop-color="#6f7c8e"/></linearGradient>
<linearGradient id="gWood" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#e0a35a"/><stop offset="1" stop-color="#8a5622"/></linearGradient>
</defs></svg>

<div id="app">
  <header id="topbar"></header>
  <main id="view"><div class="loader"><div class="spin"></div></div></main>
  <nav id="nav"></nav>
</div>
<div id="toast"></div>
<div id="fx"></div>
<div id="pop"></div>
<div id="modal"></div>
<script>
window.APP_CFG = {api: <?= json_encode($base . '/api/index.php') ?>};
window.LANGS = <?= json_encode($langs, JSON_UNESCAPED_UNICODE) ?>;
window.LANG_DEFAULT = <?= json_encode($code) ?>;
</script>
<script src="<?= h($base) ?>/assets/app.js?v=<?= $v ?>"></script>
</body></html>
