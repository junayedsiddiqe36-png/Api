<?php
require __DIR__ . '/_layout.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $do = $_POST['do'] ?? '';
    if ($do === 'create') {
        $body = trim((string)$_POST['body']);
        $img = trim((string)$_POST['image_url']);
        $bt = trim((string)$_POST['btn_text']);
        $bu = trim((string)$_POST['btn_url']);
        if ($body === '') {
            flash('Message text is required.', 'err');
        } elseif (($img !== '' && !preg_match('#^https://#', $img)) || ($bu !== '' && !preg_match('#^https://#', $bu))) {
            flash('Image and button URLs must start with https://', 'err');
        } else {
            q("INSERT INTO broadcasts (body, image_url, btn_text, btn_url) VALUES (?,?,?,?)", [$body, $img ?: null, $bt ?: null, $bu ?: null]);
            alog('broadcast_create', null, mb_substr($body, 0, 100));
            flash('Broadcast queued. It is sent by the cron job (or press "Send now").');
        }
    } elseif ($do === 'run') {
        @set_time_limit(60);
        broadcast_run(25);
        flash('Batch processed.');
    }
    back();
}
admin_head('Broadcast', 'broadcast');
echo '<form method="post" class="card">' . csrf_field() . '<input type="hidden" name="do" value="create">'
    . '<label>Message (HTML allowed: &lt;b&gt; &lt;i&gt; &lt;a href&gt;)</label><textarea name="body" rows="6" required></textarea>'
    . '<label>Image URL (optional, https)</label><input name="image_url">'
    . '<label>Button text (optional)</label><input name="btn_text"><label>Button URL (https)</label><input name="btn_url">'
    . '<button class="btn gold">Queue broadcast</button></form>';
echo '<form method="post">' . csrf_field() . '<input type="hidden" name="do" value="run"><button class="btn">Send now (process queue ~25s)</button></form>';
foreach (qa("SELECT * FROM broadcasts ORDER BY id DESC LIMIT 15") as $b) {
    echo '<div class="card"><div class="row"><b>#' . $b['id'] . '</b><span class="pill ' . ($b['status'] === 'done' ? 'paid' : 'pending') . '">' . h($b['status']) . '</span></div>'
        . '<div class="muted">' . h(mb_substr($b['body'], 0, 120)) . '</div><div>Sent ' . $b['sent'] . ' · Failed ' . $b['failed'] . ' / ' . $b['total'] . '</div></div>';
}
admin_foot();
