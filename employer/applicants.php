<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('employer');
$companyQuery = $pdo->prepare('SELECT id FROM companies WHERE user_id = ?');
$companyQuery->execute([current_user()['id']]);
$companyId = (int)$companyQuery->fetchColumn();
$jobId = filter_input(INPUT_GET, 'job_id', FILTER_VALIDATE_INT);
$sql = 'SELECT a.id, a.status, a.applied_at, u.name AS seeker_name, j.title, j.id AS job_id FROM applications a JOIN users u ON u.id = a.seeker_id JOIN jobs j ON j.id = a.job_id WHERE j.company_id = ?';
$params = [$companyId];
if ($jobId) {
    $sql .= ' AND j.id = ?';
    $params[] = $jobId;
}
$sql .= ' ORDER BY a.applied_at DESC';
$query = $pdo->prepare($sql);
$query->execute($params);
$applications = $query->fetchAll();
$pageTitle = 'Applicants';
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-heading"><div><span class="eyebrow">EMPLOYER SPACE</span><h1>Applicants</h1><p>Get to know the people interested in your opportunities.</p></div></section>
<?php require __DIR__ . '/nav.php'; ?>
<section class="content-card"><div class="table-wrap"><table><thead><tr><th>Candidate</th><th>Opportunity</th><th>Applied</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($applications as $application): ?><tr><td><strong><?= e($application['seeker_name']) ?></strong></td><td><?= e($application['title']) ?></td><td><?= e(date('M j, Y', strtotime($application['applied_at']))) ?></td><td><span class="badge badge-<?= e(application_status_class($application['status'])) ?>"><?= e(ucfirst($application['status'])) ?></span></td><td><a class="text-link" href="<?= e(app_url('employer/application_details.php?id=' . (int)$application['id'])) ?>">Review application →</a></td></tr><?php endforeach; ?></tbody></table></div><?php if (!$applications): ?><div class="empty-state"><p>No candidates have applied yet.</p></div><?php endif; ?></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
