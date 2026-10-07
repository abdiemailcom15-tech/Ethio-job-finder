<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('seeker');
$query = $pdo->prepare('SELECT w.id, w.status, w.agreed_amount, w.created_at, j.title, c.name AS company_name, i.invoice_number, i.status AS invoice_status FROM work_orders w JOIN applications a ON a.id = w.application_id JOIN jobs j ON j.id = a.job_id JOIN companies c ON c.id = j.company_id LEFT JOIN invoices i ON i.work_order_id = w.id WHERE a.seeker_id = ? ORDER BY w.created_at DESC');
$query->execute([current_user()['id']]);
$workOrders = $query->fetchAll();
$pageTitle = 'My work orders';
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-heading"><div><span class="eyebrow">YOUR CAREER SPACE</span><h1>My work</h1><p>Deliver work, respond to feedback, and track invoices.</p></div></section>
<nav class="dashboard-nav"><a href="<?= e(app_url('seeker/dashboard.php')) ?>">Overview</a><a href="<?= e(app_url('seeker/profile.php')) ?>">My profile</a><a href="<?= e(app_url('seeker/jobs.php')) ?>">Find jobs</a><a href="<?= e(app_url('seeker/applications.php')) ?>">My applications</a><a class="active" href="<?= e(app_url('seeker/work_orders.php')) ?>">My work</a><a href="<?= e(app_url('work_chats.php')) ?>">Work chats</a></nav>
<section class="content-card"><div class="table-wrap"><table><thead><tr><th>Work order</th><th>Company</th><th>Agreed amount</th><th>Status</th><th>Invoice</th><th></th></tr></thead><tbody><?php foreach ($workOrders as $work): ?><tr><td><strong><?= e($work['title']) ?></strong><small class="table-sub">WO-<?= (int)$work['id'] ?></small></td><td><?= e($work['company_name']) ?></td><td>ETB <?= e(number_format((float)$work['agreed_amount'], 2)) ?></td><td><span class="badge badge-<?= e(application_status_class($work['status'])) ?>"><?= e(ucwords(str_replace('_', ' ', $work['status']))) ?></span></td><td><?= e($work['invoice_number'] ?: '—') ?><?= $work['invoice_status'] ? ' · ' . e(ucfirst($work['invoice_status'])) : '' ?></td><td><a class="text-link" href="<?= e(app_url('seeker/work_order.php?id=' . (int)$work['id'])) ?>">Open →</a></td></tr><?php endforeach; ?></tbody></table></div><?php if (!$workOrders): ?><div class="empty-state"><span class="empty-icon">✦</span><h3>No active work orders yet</h3><p>When a company accepts your application, your work order will appear here.</p><a class="button" href="<?= e(app_url('seeker/applications.php')) ?>">View applications</a></div><?php endif; ?></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
