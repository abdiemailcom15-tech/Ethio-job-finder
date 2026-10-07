<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('employer');
$query = $pdo->prepare('SELECT w.id, w.status, w.agreed_amount, w.created_at, u.name AS seeker_name, j.title, i.invoice_number, i.status AS invoice_status FROM work_orders w JOIN applications a ON a.id = w.application_id JOIN users u ON u.id = a.seeker_id JOIN jobs j ON j.id = a.job_id JOIN companies c ON c.id = j.company_id LEFT JOIN invoices i ON i.work_order_id = w.id WHERE c.user_id = ? ORDER BY w.created_at DESC');
$query->execute([current_user()['id']]);
$workOrders = $query->fetchAll();
$pageTitle = 'Work orders';
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-heading"><div><span class="eyebrow">EMPLOYER SPACE</span><h1>Work orders</h1><p>Review deliveries, request revisions, approve work, and record invoice payments.</p></div></section>
<?php require __DIR__ . '/nav.php'; ?>
<section class="content-card"><div class="table-wrap"><table><thead><tr><th>Project</th><th>Worker</th><th>Agreed amount</th><th>Work status</th><th>Invoice</th><th></th></tr></thead><tbody><?php foreach ($workOrders as $work): ?><tr><td><strong><?= e($work['title']) ?></strong><small class="table-sub">WO-<?= (int)$work['id'] ?></small></td><td><?= e($work['seeker_name']) ?></td><td>ETB <?= e(number_format((float)$work['agreed_amount'], 2)) ?></td><td><span class="badge badge-<?= e(application_status_class($work['status'])) ?>"><?= e(ucwords(str_replace('_', ' ', $work['status']))) ?></span></td><td><?= e($work['invoice_number'] ?: '—') ?><?= $work['invoice_status'] ? ' · ' . e(ucfirst($work['invoice_status'])) : '' ?></td><td><a class="text-link" href="<?= e(app_url('employer/work_order.php?id=' . (int)$work['id'])) ?>">Open →</a></td></tr><?php endforeach; ?></tbody></table></div><?php if (!$workOrders): ?><div class="empty-state"><span class="empty-icon">✦</span><h3>No work orders yet</h3><p>Accept an applicant to create a work order and begin a project.</p><a class="text-link" href="<?= e(app_url('employer/applicants.php')) ?>">Review applicants →</a></div><?php endif; ?></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
