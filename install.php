<?php
// One-time installer. Delete this file after installation.
error_reporting(E_ALL);
ini_set('display_errors', '0');
$root = __DIR__;
$done = is_file($root . '/config.php');

function esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function detect_base_url()
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir;
}

$errors = [];
$result = null;
$v = [
    'db_host' => 'localhost', 'db_name' => '', 'db_user' => '', 'db_pass' => '',
    'base_url' => detect_base_url(), 'timezone' => 'UTC',
    'admin_user' => 'admin', 'admin_pass' => '',
    'bot_token' => '', 'bot_username' => '', 'app_name' => 'Crypto Miner',
];

if (!$done && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($v as $k => $d) {
        $v[$k] = trim((string)($_POST[$k] ?? ''));
    }
    $v['base_url'] = rtrim($v['base_url'], '/');
    if ($v['db_name'] === '' || $v['db_user'] === '') {
        $errors[] = 'Database name and user are required.';
    }
    if (strlen($v['admin_pass']) < 8) {
        $errors[] = 'Admin password must be at least 8 characters.';
    }
    if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $v['admin_user'])) {
        $errors[] = 'Admin username: 3-32 letters, numbers or underscore.';
    }
    if (!in_array($v['timezone'], timezone_identifiers_list(), true)) {
        $errors[] = 'Invalid timezone.';
    }
    if (!$errors) {
        try {
            $pdo = new PDO('mysql:host=' . $v['db_host'] . ';dbname=' . $v['db_name'] . ';charset=utf8mb4', $v['db_user'], $v['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec(file_get_contents($root . '/database.sql'));

            $cronKey = bin2hex(random_bytes(16));
            $whSecret = bin2hex(random_bytes(24));
            $cfg = "<?php\n"
                . "define('DB_HOST', " . var_export($v['db_host'], true) . ");\n"
                . "define('DB_NAME', " . var_export($v['db_name'], true) . ");\n"
                . "define('DB_USER', " . var_export($v['db_user'], true) . ");\n"
                . "define('DB_PASS', " . var_export($v['db_pass'], true) . ");\n"
                . "define('BASE_URL', " . var_export($v['base_url'], true) . ");\n"
                . "define('TIMEZONE', " . var_export($v['timezone'], true) . ");\n"
                . "define('CRON_KEY', " . var_export($cronKey, true) . ");\n"
                . "define('WEBHOOK_SECRET', " . var_export($whSecret, true) . ");\n"
                . "define('DEBUG', false);\n";
            if (file_put_contents($root . '/config.php', $cfg) === false) {
                throw new Exception('Could not write config.php. Check folder permissions.');
            }

            $st = $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash)");
            $st->execute([$v['admin_user'], password_hash($v['admin_pass'], PASSWORD_DEFAULT)]);

            $set = $pdo->prepare("INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v=VALUES(v)");
            $set->execute(['bot_token', $v['bot_token']]);
            $set->execute(['bot_username', ltrim($v['bot_username'], '@')]);
            $set->execute(['app_name', $v['app_name'] !== '' ? $v['app_name'] : 'Crypto Miner']);

            $webhook = 'Skipped (no bot token).';
            if ($v['bot_token'] !== '') {
                $call = function ($method, $params) use ($v) {
                    $ch = curl_init('https://api.telegram.org/bot' . $v['bot_token'] . '/' . $method);
                    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
                        CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($params)]);
                    $r = curl_exec($ch);
                    curl_close($ch);
                    return json_decode((string)$r, true);
                };
                $r = $call('setWebhook', ['url' => $v['base_url'] . '/webhook.php', 'secret_token' => $whSecret,
                    'allowed_updates' => ['message', 'callback_query'], 'drop_pending_updates' => true]);
                $call('setChatMenuButton', ['menu_button' => ['type' => 'web_app', 'text' => 'Open App', 'web_app' => ['url' => $v['base_url'] . '/app/']]]);
                $webhook = !empty($r['ok']) ? 'Webhook set successfully.' : 'Webhook failed: ' . esc($r['description'] ?? 'unknown error (HTTPS is required)');
            }
            $result = ['cron' => $cronKey, 'webhook' => $webhook, 'base' => $v['base_url']];
        } catch (Throwable $e) {
            $errors[] = 'Installation failed: ' . $e->getMessage();
            if (is_file($root . '/config.php') && !$result) {
                @unlink($root . '/config.php');
            }
        }
    }
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Installer</title>
<style>
body{margin:0;background:#0b0e11;color:#eaecef;font:15px/1.5 system-ui,Segoe UI,Roboto,sans-serif;padding:18px}
.box{max-width:560px;margin:0 auto;background:#161a1e;border:1px solid #2b3139;border-radius:14px;padding:20px}
h1{margin:0 0 4px;font-size:22px;color:#f0b90b}p{color:#848e9c;margin:0 0 14px}
label{display:block;margin:12px 0 4px;font-size:13px;color:#b7bdc6}
input{width:100%;box-sizing:border-box;padding:11px 12px;background:#0b0e11;border:1px solid #2b3139;border-radius:8px;color:#eaecef;font-size:15px}
h3{margin:22px 0 0;font-size:15px;color:#eaecef;border-top:1px solid #2b3139;padding-top:14px}
button{margin-top:20px;width:100%;padding:13px;border:0;border-radius:10px;background:#f0b90b;color:#1a1400;font-weight:700;font-size:16px;cursor:pointer}
.err{background:#3a1820;border:1px solid #f6465d;color:#ffb3bd;padding:10px 12px;border-radius:8px;margin-bottom:10px}
.ok{background:#12301f;border:1px solid #0ecb81;color:#9ff0c8;padding:12px;border-radius:8px;margin:10px 0}
code{background:#0b0e11;padding:2px 6px;border-radius:5px;word-break:break-all;display:inline-block}
</style></head><body><div class="box">
<h1>⛏️ Mining App Installer</h1>
<?php if ($done && !$result): ?>
  <p>The app is already installed. For security, delete <code>install.php</code> from your server.</p>
<?php elseif ($result): ?>
  <div class="ok"><b>Installation complete.</b><br><?= esc($result['webhook']) ?></div>
  <p><b>1. Cron job (required)</b> — add this to your hosting cron, run every minute:</p>
  <p><code>* * * * * curl -s "<?= esc($result['base']) ?>/cron.php?key=<?= esc($result['cron']) ?>" &gt;/dev/null</code></p>
  <p><b>2. Admin panel:</b> <a style="color:#f0b90b" href="<?= esc($result['base']) ?>/admin/"><?= esc($result['base']) ?>/admin/</a></p>
  <p><b>3.</b> Delete <code>install.php</code> now. Then open the admin panel → Settings to set your premium channel, payment channel, rates and limits.</p>
  <?php @unlink(__FILE__); ?>
<?php else: ?>
  <p>Create a MySQL database in your hosting panel first, then fill this form. HTTPS is required for Telegram Mini Apps.</p>
  <?php foreach ($errors as $e): ?><div class="err"><?= esc($e) ?></div><?php endforeach; ?>
  <form method="post" autocomplete="off">
    <h3 style="border:0;padding:0;margin-top:6px">Database</h3>
    <label>DB host</label><input name="db_host" value="<?= esc($v['db_host']) ?>">
    <label>DB name</label><input name="db_name" value="<?= esc($v['db_name']) ?>">
    <label>DB user</label><input name="db_user" value="<?= esc($v['db_user']) ?>">
    <label>DB password</label><input name="db_pass" type="password" value="">
    <h3>Site</h3>
    <label>Base URL (no trailing slash)</label><input name="base_url" value="<?= esc($v['base_url']) ?>">
    <label>Timezone</label><input name="timezone" value="<?= esc($v['timezone']) ?>">
    <h3>Admin account</h3>
    <label>Admin username</label><input name="admin_user" value="<?= esc($v['admin_user']) ?>">
    <label>Admin password (min 8 characters)</label><input name="admin_pass" type="password">
    <h3>Telegram bot</h3>
    <label>Bot token (from @BotFather)</label><input name="bot_token" value="<?= esc($v['bot_token']) ?>">
    <label>Bot username (without @)</label><input name="bot_username" value="<?= esc($v['bot_username']) ?>">
    <label>App name</label><input name="app_name" value="<?= esc($v['app_name']) ?>">
    <button type="submit">Install</button>
  </form>
<?php endif; ?>
</div></body></html>
