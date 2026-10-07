<?php
require_once __DIR__ . '/includes/auth.php';
$user = current_user();
if (!$user) {
    redirect(app_url('login.php'));
}
$baseQuery = "SELECT w.id AS work_order_id, w.status, j.title, worker.name AS worker_name, c.name AS company_name, (SELECT COALESCE(m.message, '[File attachment]') FROM work_order_chat_messages m WHERE m.work_order_id = w.id ORDER BY m.id DESC LIMIT 1) AS latest_message, (SELECT m.created_at FROM work_order_chat_messages m WHERE m.work_order_id = w.id ORDER BY m.id DESC LIMIT 1) AS latest_at FROM work_orders w JOIN applications a ON a.id = w.application_id JOIN users worker ON worker.id = a.seeker_id JOIN jobs j ON j.id = a.job_id JOIN companies c ON c.id = j.company_id";
if ($user['role'] === 'admin') {
    $query = $pdo->query($baseQuery . ' ORDER BY latest_at IS NULL, latest_at DESC, w.id DESC');
} elseif ($user['role'] === 'seeker') {
    $query = $pdo->prepare($baseQuery . ' WHERE a.seeker_id = ? ORDER BY latest_at IS NULL, latest_at DESC, w.id DESC');
    $query->execute([$user['id']]);
} elseif ($user['role'] === 'employer') {
    $query = $pdo->prepare($baseQuery . ' WHERE c.user_id = ? ORDER BY latest_at IS NULL, latest_at DESC, w.id DESC');
    $query->execute([$user['id']]);
} else {
    http_response_code(403);
    exit('You do not have permission to view work chats.');
}
$chats = $query->fetchAll();
$pageTitle = 'Work chats';
require __DIR__ . '/includes/header.php';
?>
<section class="dashboard-heading"><div><span class="eyebrow"><?= $user['role'] === 'admin' ? 'ADMINISTRATION' : 'WORKLY MESSAGES' ?></span><h1>Work chats</h1><p>Conversations are shared by the worker, company, and administrators for each work order.</p></div></section>
<?php if ($user['role'] === 'admin'): ?><?php require __DIR__ . '/admin/nav.php'; ?><?php elseif ($user['role'] === 'employer'): ?><?php require __DIR__ . '/employer/nav.php'; ?><?php else: ?><nav class="dashboard-nav"><a href="<?= e(app_url('seeker/dashboard.php')) ?>">Overview</a><a href="<?= e(app_url('seeker/profile.php')) ?>">My profile</a><a href="<?= e(app_url('seeker/jobs.php')) ?>">Find jobs</a><a href="<?= e(app_url('seeker/applications.php')) ?>">My applications</a><a href="<?= e(app_url('seeker/work_orders.php')) ?>">My work</a><a class="active" href="<?= e(app_url('work_chats.php')) ?>">Work chats</a></nav><?php endif; ?>
<section class="content-card">
    <?php if ($chats): ?><div class="table-wrap"><table><thead><tr><th>Work order</th><th>Worker</th><th>Company</th><th>Latest message</th><th></th></tr></thead><tbody><?php foreach ($chats as $chat): ?><tr><td><strong>WO-<?= (int)$chat['work_order_id'] ?></strong><br><?= e($chat['title']) ?></td><td><?= e($chat['worker_name']) ?></td><td><?= e($chat['company_name']) ?></td><td><?= $chat['latest_message'] ? e(text_excerpt($chat['latest_message'], 100)) : 'No messages yet' ?></td><td><a class="text-link" href="<?= e(app_url('work_chat.php?id=' . (int)$chat['work_order_id'])) ?>">Open chat →</a></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty-state"><h3>No work chats yet</h3><p>A conversation will appear when you have a work order.</p></div><?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
