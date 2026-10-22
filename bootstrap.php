<?php
if (!is_file(__DIR__ . '/../config.php')) {
    http_response_code(503);
    echo 'Application is not installed. Please open install.php first.';
    exit;
}
require_once __DIR__ . '/../config.php';
date_default_timezone_set(TIMEZONE);
mb_internal_encoding('UTF-8');

class ApiError extends Exception
{
    public $status;
    public function __construct($message, $status = 400)
    {
        parent::__construct($message);
        $this->status = $status;
    }
}

/* ---------- Database ---------- */
function db()
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    }
    return $pdo;
}
function q($sql, $p = [])
{
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st;
}
function q1($sql, $p = [])
{
    $r = q($sql, $p)->fetch();
    return $r ? $r : null;
}
function qa($sql, $p = [])
{
    return q($sql, $p)->fetchAll();
}
function qv($sql, $p = [])
{
    $r = q($sql, $p)->fetch(PDO::FETCH_NUM);
    return $r ? $r[0] : null;
}

/* ---------- Small helpers ---------- */
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function dec($n, $d = 4)
{
    return number_format((float)$n, $d, '.', '');
}
function fmt($n, $d = 2)
{
    return number_format((float)$n, $d, '.', ',');
}
function tx_id()
{
    return 'TX' . strtoupper(bin2hex(random_bytes(6)));
}
function client_ip()
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
function rate_limit($key, $max, $window)
{
    $now = time();
    q("INSERT INTO rate_limits (k,hits,win_start) VALUES (?,1,?)
       ON DUPLICATE KEY UPDATE hits = IF(win_start <= ?, 1, hits + 1), win_start = IF(win_start <= ?, ?, win_start)",
        [$key, $now, $now - $window, $now - $window, $now]);
    $hits = (int)qv("SELECT hits FROM rate_limits WHERE k=?", [$key]);
    return $hits <= $max;
}
function rate_limit_or_fail($key, $max, $window)
{
    if (!rate_limit($key, $max, $window)) {
        throw new ApiError('Too many attempts. Please slow down.', 429);
    }
}

/* ---------- Settings ---------- */
function settings_schema()
{
    $welcome = "🚀 <b>Welcome to {app_name}</b>\n\nStart mining, earn coins, increase your mining speed and convert your rewards.";
    $tpl = "💸 <b>PAYMENT SUCCESSFUL</b>\n\n👤 User ID: <code>{user_id}</code>\n💰 Amount: <b>\${amount}</b>\n🌐 Method: {method}\n🟢 Status: {status}\n\nCongratulations! 🎉";
    $promo = "🚀 <b>{app_name}</b>\n\n👥 Miners: <b>{total_users}</b>\n⛏️ Mining right now: <b>{active_miners}</b>\n🪙 Mined today: <b>{coins_today}</b> coins\n\n🏆 <b>Top miners</b>\n{top_miners}\n\n💱 {rate} coins = $1 · Min withdrawal \${min_withdraw}\n\nStart mining for free 👇";
    // [key, label, type, default, options]
    return [
        'Mining' => [
            ['mine_enabled', 'Mining enabled', 'bool', '1'],
            ['mine_base_speed', 'Base mining speed (coins / minute)', 'number', '1.00'],
            ['mine_duration', 'Mining duration (minutes)', 'number', '240'],
            ['mine_interval', 'Coin credit interval (minutes)', 'number', '5'],
            ['mine_min_speed', 'Minimum mining speed limit', 'number', '0.10'],
            ['mine_max_speed', 'Maximum mining speed limit (0 = none)', 'number', '50'],
            ['mine_daily_limit', 'Daily mining limit per user in coins (0 = none)', 'number', '0'],
            ['mine_auto_restart', 'Auto restart mining after a session ends', 'bool', '0'],
        ],
        'Miner tiers (multiplier)' => [
            ['tier_mult_free', 'FREE miner multiplier', 'number', '1'],
            ['tier_mult_premium', 'PREMIUM miner multiplier', 'number', '2'],
            ['tier_mult_vip', 'VIP miner multiplier', 'number', '5'],
        ],
        'Referral' => [
            ['ref_enabled', 'Referral system enabled', 'bool', '1'],
            ['ref_speed_bonus', 'Speed bonus per active referral (coins / minute)', 'number', '0.20'],
            ['ref_coin_bonus', 'Coin bonus to referrer per valid referral', 'number', '100'],
            ['ref_limit', 'Referral limit per user (0 = unlimited)', 'number', '100'],
            ['ref_min_sessions', 'Valid referral: completed mining sessions needed (0 = instant)', 'number', '1'],
            ['level_names', 'Miner level names (comma separated, from level 1)', 'text', 'Rookie,Bronze,Silver,Gold,Platinum,Diamond'],
            ['level_thresholds', 'Active referrals needed per level (comma separated, first = 0)', 'text', '0,3,10,25,50,100'],
            ['level_speed_bonus', 'Extra speed for each level above 1 (coins / minute)', 'number', '0.10'],
        ],
        'Premium channel boost' => [
            ['premium_enabled', 'Premium boost enabled', 'bool', '1'],
            ['premium_channel_username', 'Premium channel username (e.g. @mychannel)', 'text', ''],
            ['premium_channel_id', 'Premium channel ID (e.g. -1001234567890)', 'text', ''],
            ['premium_channel_url', 'Premium channel join link (https://t.me/...)', 'text', ''],
            ['premium_speed_bonus', 'Premium speed bonus (coins / minute)', 'number', '0.50'],
            ['premium_join_text', 'Join button text', 'text', 'JOIN PREMIUM CHANNEL'],
            ['premium_verify_message', 'Verification message', 'textarea', 'Join our premium channel, then tap VERIFY MEMBERSHIP to unlock your mining boost.'],
        ],
        'Conversion' => [
            ['conv_enabled', 'Conversion enabled', 'bool', '1'],
            ['conv_coins_per_usd', 'Coins required per 1 USD', 'number', '20000'],
            ['conv_min_coins', 'Minimum conversion (coins)', 'number', '10000'],
        ],
        'Withdrawal' => [
            ['wd_enabled', 'Withdrawals enabled', 'bool', '1'],
            ['wd_min', 'Minimum withdrawal (USD)', 'number', '0.50'],
            ['wd_max', 'Maximum withdrawal per request (USD, 0 = none)', 'number', '100'],
            ['wd_daily_limit', 'Daily withdrawal limit per user (USD, 0 = none)', 'number', '100'],
            ['wd_cooldown_hours', 'Withdrawal cooldown (hours between requests)', 'number', '24'],
            ['wd_methods', 'Payment methods (comma separated: USDT TRC20, USDT BEP20, Binance Pay)', 'text', 'USDT TRC20,USDT BEP20,Binance Pay'],
        ],
        'Daily reward' => [
            ['daily_enabled', 'Daily reward enabled', 'bool', '1'],
            ['daily_rewards', 'Reward per day (comma separated, cycle repeats)', 'text', '100,150,200,250,300,400,1000'],
        ],
        'Daily check-in' => [
            ['checkin_enabled', 'Check-in enabled', 'bool', '1'],
            ['checkin_reward', 'Reward per check-in (coins)', 'number', '50'],
            ['checkin_streak_days', 'Streak length for bonus (days)', 'number', '7'],
            ['checkin_streak_bonus', 'Streak bonus (coins)', 'number', '500'],
            ['checkin_reset', 'Reset streak when a day is missed', 'bool', '1'],
        ],
        'Lucky spin' => [
            ['spin_enabled', 'Lucky spin enabled', 'bool', '1'],
            ['spin_prizes', 'Prizes in coins (comma separated, 2 to 10 values)', 'text', '50,100,200,500,1000,2500'],
            ['spin_weights', 'Chance weights (same count as prizes; bigger = more likely)', 'text', '40,28,16,9,5,2'],
            ['spin_cooldown_hours', 'Hours between free spins', 'number', '24'],
        ],
        'Tasks' => [
            ['tasks_enabled', 'Tasks enabled', 'bool', '1'],
            ['task_min_seconds', 'Default wait before a task can be claimed (seconds)', 'number', '15'],
        ],
        'Leaderboard' => [
            ['lb_mining_enabled', 'Mining leaderboard enabled', 'bool', '1'],
            ['lb_ref_enabled', 'Referral leaderboard enabled', 'bool', '1'],
            ['lb_size', 'Entries per leaderboard', 'number', '20'],
        ],
        'Payment success announcements' => [
            ['ann_enabled', 'Announcements enabled', 'bool', '0'],
            ['ann_channel', 'Payment channel (@username or -100... ID)', 'text', ''],
            ['ann_interval', 'Announcement interval (minutes)', 'number', '10'],
            ['ann_statuses', 'Announce withdrawals that are', 'select', 'paid', ['paid' => 'Paid only', 'approved_paid' => 'Approved or Paid']],
            ['ann_mask', 'Mask part of the user ID in announcements', 'bool', '1'],
            ['ann_open_url', 'OPEN APP button URL (e.g. https://t.me/YourBot)', 'text', ''],
            ['ann_template', 'Message template ({user_id} {amount} {method} {status})', 'textarea', $tpl],
        ],
        'Promotion channel (real stats auto-post)' => [
            ['promo_enabled', 'Promotion posts enabled', 'bool', '0'],
            ['promo_channel', 'Promotion channel (@username or -100... ID, bot must be admin)', 'text', ''],
            ['promo_interval', 'Post interval (minutes)', 'number', '10'],
            ['promo_mask', 'Mask part of miner names in the top list', 'bool', '1'],
            ['promo_btn_text', 'Button text', 'text', 'START MINING'],
            ['promo_btn_url', 'Button URL (empty = your bot link)', 'text', ''],
            ['promo_template', 'Post template: {app_name} {total_users} {active_miners} {coins_today} {top_miners} {rate} {min_withdraw} {total_paid}', 'textarea', $promo],
        ],
        'Notifications' => [
            ['notify_mining', 'Mining completed', 'bool', '1'],
            ['notify_referral', 'New referral', 'bool', '1'],
            ['notify_premium', 'Premium boost activated', 'bool', '1'],
            ['notify_withdrawal', 'Withdrawal updates', 'bool', '1'],
        ],
        'Bot & app' => [
            ['bot_token', 'Bot token', 'secret', ''],
            ['bot_username', 'Bot username (without @)', 'text', ''],
            ['app_name', 'App name', 'text', 'Crypto Miner'],
            ['support_url', 'Support link (https://t.me/...)', 'text', ''],
            ['welcome_text', 'Welcome message (HTML, use {app_name})', 'textarea', $welcome],
            ['default_lang', 'Language code', 'text', 'en'],
        ],
        'System' => [
            ['maintenance', 'Maintenance mode', 'bool', '0'],
            ['reg_enabled', 'New registrations enabled', 'bool', '1'],
        ],
    ];
}
function setting_defaults()
{
    $d = [];
    foreach (settings_schema() as $fields) {
        foreach ($fields as $f) {
            $d[$f[0]] = $f[3];
        }
    }
    $d['app_theme'] = 'goldverse';
    $d['theme_user_choice'] = '1';
    $d['theme_mode'] = 'fixed';
    $d['theme_day'] = 'gold';
    $d['theme_night'] = 'violet';
    $d['tap_enabled'] = '0';
    $d['tap_open_mode'] = 'only';
    $d['tap_object'] = 'coin';
    $d['tap_per_tap'] = '0.1';
    $d['tap_energy_max'] = '1000';
    $d['tap_regen'] = '0.1';
    $d['tap_max_rate'] = '12';
    $d['tap_daily_cap'] = '2000';
    $d['tap_token_name'] = 'COINS';
    $d['tap_logo_url'] = '';
    $d['tap_color_accent'] = '#4fc3f7';
    $d['tap_color_accent2'] = '#3b82f6';
    $d['tap_color_bg'] = '#000000';
    $d['tap_color_card'] = '#0a111c';
    $d['tap_color_text'] = '#ffffff';
    $d['tap_color_coin'] = '#2f6df0';
    $d['tap_color_glow'] = '#38bdf8';
    return $d;
}
function theme_list()
{
    // id => [name, colour 1, colour 2, description, default unlock level]
    return [
        'goldverse' => ['Gold Verse', '#ffe27a', '#ff9f1a', 'Blue sky, gold ore pile and a pickaxe (tap the pile)', 1],
        'satoshi' => ['Satoshi Gold', '#ffe08a', '#d99a1c', 'Golden circuit board with a shining Bitcoin', 1],
        'hamster' => ['Hamster Boss', '#ffd166', '#f57c00', 'Cute hamster boss with character skins (unlock by level or buy)', 1],
        'gold' => ['Gold Rush', '#ffd96b', '#f0a50b', 'Miner smashes a Bitcoin with a hammer (hammer, helmet and coin grow with level)', 1],
        'ocean' => ['Ocean Crystal', '#7dd3fc', '#0ea5e9', 'Pickaxe cracks a blue crystal', 1],
        'drill' => ['Iron Drill', '#ffb47a', '#e8590c', 'Drilling machine bores into an ore wall', 2],
        'violet' => ['Neon Night', '#c9bbff', '#7c5cf0', 'Glowing mining rig with spinning fans', 2],
        'diver' => ['Deep Sea Diver', '#5eead4', '#0d9488', 'Diver collects coins from a treasure chest', 3],
        'rose' => ['Rose Rocket', '#fda4af', '#e11d48', 'Rocket on the pad with orbiting coins', 3],
        'space' => ['Space Mine', '#d9f99d', '#65a30d', 'Drone lasers an asteroid', 4],
        'crescent' => ['Eid Crescent', '#6ee7b7', '#10b981', 'Crescent, star and swinging lantern', 4],
    ];
}
function app_theme()
{
    $t = (string)setting('app_theme');
    return isset(theme_list()[$t]) ? $t : 'gold';
}
function settings_all($reload = false)
{
    static $cache = null;
    if ($cache === null || $reload) {
        $cache = setting_defaults();
        foreach (qa("SELECT k, v FROM settings") as $r) {
            $cache[$r['k']] = $r['v'];
        }
    }
    return $cache;
}
function setting($k, $default = '')
{
    $a = settings_all();
    return array_key_exists($k, $a) ? $a[$k] : $default;
}
function save_setting($k, $v)
{
    q("INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)", [$k, (string)$v]);
    settings_all(true);
}

/* ---------- Telegram ---------- */
function tg($method, $params = [], $token = null)
{
    $token = $token !== null ? $token : (string)setting('bot_token');
    if ($token === '') {
        return ['ok' => false, 'description' => 'Bot token is not configured'];
    }
    $ch = curl_init('https://api.telegram.org/bot' . $token . '/' . $method);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($params, JSON_UNESCAPED_UNICODE),
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) {
        return ['ok' => false, 'description' => $err ?: 'Network error'];
    }
    $j = json_decode($res, true);
    return is_array($j) ? $j : ['ok' => false, 'description' => 'Bad response from Telegram'];
}
function app_url()
{
    return rtrim(BASE_URL, '/') . '/app/';
}
function app_button($text)
{
    return ['inline_keyboard' => [[['text' => $text, 'web_app' => ['url' => app_url()]]]]];
}
function notify_user($uid, $type, $text, $markup = null)
{
    if (!setting('notify_' . $type)) {
        return;
    }
    $u = q1("SELECT telegram_id, notify FROM users WHERE id=?", [$uid]);
    if (!$u || !$u['notify']) {
        return;
    }
    $p = ['chat_id' => $u['telegram_id'], 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
    if ($markup) {
        $p['reply_markup'] = $markup;
    }
    $r = tg('sendMessage', $p);
    q("INSERT INTO notifications (user_id, type, message, sent, error) VALUES (?,?,?,?,?)", [
        $uid, $type, mb_substr(strip_tags($text), 0, 480), !empty($r['ok']) ? 1 : 0,
        !empty($r['ok']) ? null : mb_substr((string)($r['description'] ?? ''), 0, 250),
    ]);
}
function premium_url()
{
    $u = trim((string)setting('premium_channel_url'));
    if ($u !== '') {
        return $u;
    }
    $n = ltrim(trim((string)setting('premium_channel_username')), '@');
    return $n !== '' ? 'https://t.me/' . $n : '';
}
function display_name($row)
{
    if (!empty($row['username'])) {
        return '@' . $row['username'];
    }
    return !empty($row['first_name']) ? $row['first_name'] : 'User';
}

/* ---------- Logging / fraud ---------- */
function admin_log($action, $targetUser = null, $details = '', $adminId = null, $adminName = null)
{
    q("INSERT INTO admin_logs (admin_id, admin_name, action, target_user, details, ip) VALUES (?,?,?,?,?,?)",
        [$adminId, $adminName, $action, $targetUser, mb_substr((string)$details, 0, 2000), client_ip()]);
}
function flag_user($uid, $reason)
{
    q("UPDATE users SET flagged=1, flag_reason=? WHERE id=?", [mb_substr($reason, 0, 250), $uid]);
}

function migrate_v9()
{
    try {
        q("CREATE TABLE IF NOT EXISTS user_skins (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, skin VARCHAR(24) NOT NULL, source VARCHAR(12) NOT NULL DEFAULT 'coins', price DECIMAL(18,4) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_user_skin (user_id, skin)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        if (!q1("SHOW COLUMNS FROM users LIKE 'skin'")) {
            q("ALTER TABLE users ADD COLUMN skin VARCHAR(24) NOT NULL DEFAULT '', ADD COLUMN skin_auto TINYINT(1) NOT NULL DEFAULT 1");
        }
        save_setting('schema_v9', '1');
    } catch (Throwable $e) {
        error_log('migrate_v9: ' . $e->getMessage());
    }
}

function migrate_v10()
{
    try {
        if (!q1("SHOW COLUMNS FROM users LIKE 'last_spin_at'")) {
            q("ALTER TABLE users ADD COLUMN last_spin_at DATETIME NULL");
        }
        save_setting('schema_v10', '1');
    } catch (Throwable $e) {
        error_log('migrate_v10: ' . $e->getMessage());
    }
}

function migrate_v11()
{
    try {
        if (!q1("SHOW COLUMNS FROM users LIKE 'tap_energy'")) {
            q("ALTER TABLE users ADD COLUMN tap_energy DECIMAL(12,3) NOT NULL DEFAULT 0, ADD COLUMN tap_energy_at INT UNSIGNED NOT NULL DEFAULT 0, ADD COLUMN tap_today DECIMAL(20,4) NOT NULL DEFAULT 0, ADD COLUMN tap_date DATE NULL");
        }
        save_setting('schema_v11', '1');
    } catch (Throwable $e) {
        error_log('migrate_v11: ' . $e->getMessage());
    }
}

function theme_config()
{
    $all = theme_list();
    $today = date('Y-m-d');
    $forced = false;
    $theme = app_theme();
    for ($i = 1; $i <= 3; $i++) {
        $th = (string)setting("theme_ev{$i}_theme", '');
        $from = (string)setting("theme_ev{$i}_from", '');
        $to = (string)setting("theme_ev{$i}_to", '');
        if (isset($all[$th]) && $from !== '' && $to !== '' && $today >= $from && $today <= $to) {
            $theme = $th;
            $forced = true;
            break;
        }
    }
    $mode = setting('theme_mode') === 'daynight' ? 'daynight' : 'fixed';
    $day = (string)setting('theme_day');
    $night = (string)setting('theme_night');
    if (!isset($all[$day])) {
        $day = 'gold';
    }
    if (!isset($all[$night])) {
        $night = 'violet';
    }
    $unlock = [];
    foreach ($all as $id => $t) {
        $unlock[$id] = max(1, (int)setting('theme_lv_' . $id, $t[4]));
    }
    $allow = !$forced && setting('theme_user_choice') === '1';
    return [
        'theme' => $theme, 'forced' => $forced, 'mode' => $mode, 'day' => $day, 'night' => $night,
        'allow' => $allow, 'unlock' => $unlock,
        'ver' => substr(md5(json_encode([$theme, $forced, $mode, $day, $night])), 0, 8),
    ];
}

require_once __DIR__ . '/mining.php';
require_once __DIR__ . '/actions.php';
require_once __DIR__ . '/jobs.php';
require_once __DIR__ . '/bot.php';

if (setting('schema_v9') !== '1') {
    migrate_v9();
}
if (setting('schema_v10') !== '1') {
    migrate_v10();
}
if (setting('schema_v11') !== '1') {
    migrate_v11();
}
