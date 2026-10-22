<?php
require __DIR__ . '/_layout.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $do = $_POST['do'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($do === 'save') {
        $f = [
            trim((string)$_POST['title']), trim((string)$_POST['description']), $_POST['type'] ?? 'website', trim((string)$_POST['url']),
            trim((string)$_POST['chat_id']), dec(abs((float)$_POST['reward'])), (int)$_POST['min_seconds'], (int)$_POST['sort_order'],
            isset($_POST['is_active']) ? 1 : 0,
        ];
        if ($f[0] === '') {
            flash('Title is required.', 'err');
            back();
        }
        if ($id > 0) {
            q("UPDATE tasks SET title=?, description=?, type=?, url=?, chat_id=?, reward=?, min_seconds=?, sort_order=?, is_active=? WHERE id=?", array_merge($f, [$id]));
            alog('task_update', null, "#$id " . $f[0]);
        } else {
            q("INSERT INTO tasks (title, description, type, url, chat_id, reward, min_seconds, sort_order, is_active) VALUES (?,?,?,?,?,?,?,?,?)", $f);
            alog('task_create', null, $f[0]);
        }
        flash('Task saved.');
    } elseif ($do === 'delete' && $id > 0) {
        q("DELETE FROM tasks WHERE id=?", [$id]);
        q("DELETE FROM task_claims WHERE task_id=? AND claimed_at IS NULL", [$id]);
        alog('task_delete', null, "#$id");
        flash('Task deleted.');
    }
    back();
}
$edit = ($e = (int)($_GET['edit'] ?? 0)) ? q1("SELECT * FROM tasks WHERE id=?", [$e]) : null;
$t = $edit ?: ['id' => 0, 'title' => '', 'description' => '', 'type' => 'telegram_channel', 'url' => '', 'chat_id' => '', 'reward' => '100', 'min_seconds' => 0, 'sort_order' => 0, 'is_active' => 1];
admin_head('Tasks', 'tasks');
echo '<form method="post" class="card">' . csrf_field() . '<input type="hidden" name="do" value="save"><input type="hidden" name="id" value="' . (int)$t['id'] . '">'
    . '<b>' . ($edit ? 'Edit task #' . $t['id'] : 'New task') . '</b>'
    . '<label>Title</label><input name="title" value="' . h($t['title']) . '" required>'
    . '<label>Description</label><input name="description" value="' . h($t['description']) . '">'
    . '<label>Type</label><select name="type">';
foreach (['telegram_channel' => 'Join Telegram Channel', 'telegram_group' => 'Join Telegram Group', 'website' => 'Visit Website', 'video' => 'Watch Tutorial', 'social' => 'Follow Social Account'] as $k => $l) {
    echo '<option value="' . $k . '" ' . ($t['type'] === $k ? 'selected' : '') . '>' . $l . '</option>';
}
echo '</select><label>Link (URL)</label><input name="url" value="' . h($t['url']) . '" placeholder="https://t.me/yourchannel">'
    . '<label>Chat ID / @username for Telegram verification (optional; bot must be admin there)</label><input name="chat_id" value="' . h($t['chat_id']) . '">'
    . '<label>Reward (coins)</label><input name="reward" type="number" step="any" value="' . h($t['reward']) . '">'
    . '<label>Wait seconds before claim (0 = default; used for non-Telegram tasks)</label><input name="min_seconds" type="number" value="' . (int)$t['min_seconds'] . '">'
    . '<label>Sort order</label><input name="sort_order" type="number" value="' . (int)$t['sort_order'] . '">'
    . '<label class="chk"><input type="checkbox" name="is_active" value="1" ' . ($t['is_active'] ? 'checked' : '') . '> Active</label>'
    . '<button class="btn gold">Save task</button></form>';
$rows = qa("SELECT t.*, (SELECT COUNT(*) FROM task_claims c WHERE c.task_id=t.id AND c.claimed_at IS NOT NULL) AS done FROM tasks t ORDER BY sort_order, id");
echo '<div class="secttl">Your tasks</div><div class="cols">';
foreach ($rows as $r) {
    echo '<div class="card"><div class="row"><b>' . h($r['title']) . '</b><span class="pill ' . ($r['is_active'] ? 'paid' : 'rejected') . '">' . ($r['is_active'] ? 'active' : 'off') . '</span></div>'
        . '<div class="muted">' . h($r['type']) . ' · +' . fmt($r['reward'], 0) . ' coins · ' . (int)$r['done'] . ' completed</div>'
        . '<div class="inline"><a class="btn" href="?edit=' . $r['id'] . '">Edit</a><form method="post" onsubmit="return confirm(\'Delete this task?\')">' . csrf_field()
        . '<input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="' . $r['id'] . '"><button class="btn red">Delete</button></form></div></div>';
}
echo '</div>';
admin_foot();
