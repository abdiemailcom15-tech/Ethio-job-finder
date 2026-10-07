<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$applications = $pdo->query('SELECT a.*, u.name AS seeker_name, u.email, j.title, c.name AS company_name FROM applications a JOIN users u ON u.id = a.seeker_id JOIN jobs j ON j.id = a.job_id JOIN companies c ON c.id = j.company_id ORDER BY a.applied_at DESC')->fetchAll();
$pageTitle = 'Applications';
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-heading"><div><span class="eyebrow">ADMINISTRATION</span><h1>Applications</h1><p>Track applications moving through the marketplace.</p></div></section>
<?php require __DIR__ . '/nav.php'; ?>
<section class="content-card"><div class="table-wrap"><table><thead><tr><th>Candidate</th><th>Opportunity</th><th>Company</th><th>Status</th><th>Applied</th></tr></thead><tbody>
<?php foreach ($applications as $application): ?><tr><td><strong><?= e($application['seeker_name']) ?></strong><small class="table-sub"><?= e($application['email']) ?></small></td><td><?= e($application['title']) ?></td><td><?= e($application['company_name']) ?></td><td><span class="badge badge-<?= e(application_status_class($application['status'])) ?>"><?= e(ucfirst($application['status'])) ?></span></td><td><?= e(date('M j, Y', strtotime($application['applied_at']))) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php if (!$applications): ?><div class="empty-state"><p>No applications have been submitted yet.</p></div><?php endif; ?></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
