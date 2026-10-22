<?php
require __DIR__ . '/_layout.php';
require_admin();
$page = max(1, (int)($_GET['p'] ?? 1));
$per = 50;
$total = (int)qv("SELECT COUNT(*) FROM admin_logs");
$rows = qa("SELECT * FROM admin_logs ORDER BY id DESC LIMIT $per OFFSET " . (($page - 1) * $per));
admin_head('Admin logs', 'logs');
echo '<div class="scroll"><table><tr><th>Time</th><th>Admin</th><th>Action</th><th>User</th><th>Details</th><th>IP</th></tr>';
foreach ($rows as $r) {
    echo '<tr><td>' . h($r['created_at']) . '</td><td>' . h($r['admin_name']) . '</td><td>' . h($r['action']) . '</td><td>' . ($r['target_user'] ? '<a href="users.php?id=' . $r['target_user'] . '">#' . $r['target_user'] . '</a>' : '') . '</td><td>' . h($r['details']) . '</td><td>' . h($r['ip']) . '</td></tr>';
}
echo '</table></div><div class="pager">';
if ($page > 1) {
    echo '<a href="?p=' . ($page - 1) . '">« Prev</a>';
}
if ($page * $per < $total) {
    echo '<a href="?p=' . ($page + 1) . '">Next »</a>';
}
echo '</div>';
admin_foot();
