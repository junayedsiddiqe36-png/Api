<?php
require_once __DIR__ . '/../includes/bootstrap.php';
session_name('miner_admin');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Strict',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
]);
session_start();
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');

function require_admin()
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}
function csrf()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(20));
    }
    return $_SESSION['csrf'];
}
function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . h(csrf()) . '">';
}
function csrf_verify()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
            http_response_code(419);
            exit('Invalid or expired form token. Go back and reload the page.');
        }
    }
}
function alog($action, $target = null, $details = '')
{
    admin_log($action, $target, $details, $_SESSION['admin_id'] ?? null, $_SESSION['admin_name'] ?? null);
}
function flash($msg, $type = 'ok')
{
    $_SESSION['flash'] = [$msg, $type];
}
function admin_menu()
{
    // key => [label, href, icon, tone]
    return [
        'index' => ['Dashboard', 'index.php', 'grid', 'gold'], 'users' => ['Users', 'users.php', 'users', 'blue'],
        'withdrawals' => ['Withdrawals', 'withdrawals.php', 'up', 'green'], 'tasks' => ['Tasks', 'tasks.php', 'tasks', 'teal'],
        'tap' => ['Tap Game', 'tap.php', 'zap', 'orange'], 'theme' => ['Theme', 'theme.php', 'palette', 'violet'],
        'skins' => ['Skins', 'skins.php', 'hamster', 'gold'], 'autopost' => ['Auto posts', 'autopost.php', 'clock', 'blue'],
        'broadcast' => ['Broadcast', 'broadcast.php', 'send', 'green'], 'settings' => ['Settings', 'settings.php', 'gear', 'teal'],
        'logs' => ['Logs', 'logs.php', 'file', 'violet'], 'account' => ['Account', 'account.php', 'user', 'orange'],
    ];
}
function aicon($name)
{
    $p = [
        'grid' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'up' => '<line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/>',
        'tasks' => '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
        'zap' => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
        'palette' => '<circle cx="13.5" cy="6.5" r="1.5"/><circle cx="17.5" cy="10.5" r="1.5"/><circle cx="8.5" cy="7.5" r="1.5"/><circle cx="6.5" cy="12.5" r="1.5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.93 0 1.5-.67 1.5-1.5 0-.39-.15-.74-.39-1.04-.23-.29-.38-.63-.38-1.02 0-.83.67-1.5 1.5-1.5H16c3.31 0 6-2.69 6-6 0-4.96-4.48-9-10-9z"/>',
        'hamster' => '<circle cx="12" cy="13.5" r="7.5"/><circle cx="6.2" cy="6.8" r="2.6"/><circle cx="17.8" cy="6.8" r="2.6"/><circle cx="9.4" cy="12.4" r=".7"/><circle cx="14.6" cy="12.4" r=".7"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'send' => '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
        'gear' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1 7 17M17 7l2.1-2.1"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'tool' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
        'layers' => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
        'gem' => '<polygon points="6 3 18 3 22 9 12 22 2 9"/><path d="M2 9h20"/>',
        'swap' => '<polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>',
        'gift' => '<polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/>',
        'check' => '<polyline points="20 6 9 17 4 12"/>',
        'wheel' => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="2"/><path d="M12 2v8M12 14v8M2 12h8M14 12h8"/>',
        'award' => '<circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>',
        'bell' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'cpu' => '<rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/>',
    ];
    return '<svg class="ai" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">' . (isset($p[$name]) ? $p[$name] : $p['gear']) . '</svg>';
}
function admin_head($title, $active = '')
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . h($title) . ' · Admin</title><link rel="stylesheet" href="admin.css"><script src="admin.js"></script></head><body>';
    echo '<header class="top"><b>' . h(setting('app_name')) . ' Admin</b><span class="tools"><button type="button" class="atoggle" onclick="atog()">Light / Dark</button><a class="atoggle" href="logout.php">Logout</a></span></header>';
    echo '<nav class="menu">';
    foreach (admin_menu() as $k => $m) {
        echo '<a href="' . $m[1] . '" class="' . ($active === $k ? 'on' : '') . '">' . aicon($m[2]) . '<span>' . $m[0] . '</span></a>';
    }
    echo '</nav><main>';
    if (!empty($_SESSION['flash'])) {
        echo '<div class="flash ' . h($_SESSION['flash'][1]) . '">' . h($_SESSION['flash'][0]) . '</div>';
        unset($_SESSION['flash']);
    }
    echo '<h1>' . h($title) . '</h1>';
}
function admin_foot()
{
    echo '</main></body></html>';
}
function back($to = null)
{
    header('Location: ' . ($to ?: basename($_SERVER['SCRIPT_NAME'])));
    exit;
}

function series_days($sql, $days = 14)
{
    $map = [];
    foreach (qa($sql) as $r) {
        $map[$r['d']] = (float)$r['v'];
    }
    $labels = [];
    $vals = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i day"));
        $labels[] = date('d/m', strtotime($d));
        $vals[] = isset($map[$d]) ? $map[$d] : 0.0;
    }
    return [$labels, $vals];
}
function bar_chart($title, $series, $money = false, $color = 'var(--gold)')
{
    list($labels, $vals) = $series;
    $max = max($vals);
    $n = count($vals);
    $w = 300;
    $h = 110;
    $bw = $w / $n;
    $svg = '<svg viewBox="0 0 300 130" class="chart">';
    foreach ($vals as $i => $v) {
        $bh = $max > 0 ? ($v / $max) * $h : 0;
        $svg .= '<rect x="' . round($i * $bw + 3, 1) . '" y="' . round($h - $bh, 1) . '" width="' . round($bw - 6, 1) . '" height="' . round(max($bh, 1.5), 1) . '" rx="3" fill="' . $color . '" opacity="' . ($v > 0 ? '.95' : '.25') . '"/>';
        if ($i % 3 === 0) {
            $svg .= '<text x="' . round($i * $bw + $bw / 2, 1) . '" y="126" text-anchor="middle" font-size="8" fill="currentColor" opacity=".55">' . h($labels[$i]) . '</text>';
        }
    }
    $svg .= '</svg>';
    $total = array_sum($vals);
    return '<div class="card chartcard"><div class="row"><b>' . h($title) . '</b><span class="muted">14 days: ' . ($money ? '$' . fmt($total) : fmt($total, 0)) . '</span></div>' . $svg . '</div>';
}
