<?php
require __DIR__ . '/includes/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    if (!hash_equals(CRON_KEY, (string)($_GET['key'] ?? ''))) {
        http_response_code(403);
        exit('Forbidden');
    }
}
@set_time_limit(90);
$lock = sys_get_temp_dir() . '/miner_cron_' . md5(__DIR__) . '.lock';
$fp = fopen($lock, 'c');
if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) {
    exit('Already running');
}
try {
    mining_finish_due();
    announcements_run();
    promotions_run();
    autopost_run();
    broadcast_run(40);
    q("DELETE FROM rate_limits WHERE win_start < ?", [time() - 3600]);
} catch (Throwable $e) {
    error_log('cron: ' . $e->getMessage());
}
flock($fp, LOCK_UN);
echo 'OK ' . date('c');
