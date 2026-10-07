<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('employer');
$companyQuery = $pdo->prepare('SELECT * FROM companies WHERE user_id = ?');
$companyQuery->execute([current_user()['id']]);
$company = $companyQuery->fetch();
if (!$company) {
    http_response_code(500);
    exit('Company profile not found. Please contact an administrator.');
}
$countsQuery = $pdo->prepare("SELECT COUNT(*) AS jobs, SUM(status = 'approved') AS live FROM jobs WHERE company_id = ?");
$countsQuery->execute([$company['id']]);
$counts = $countsQuery->fetch();
$appsQuery = $pdo->prepare('SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id = a.job_id WHERE j.company_id = ?');
$appsQuery->execute([$company['id']]);
$applicationCount = (int)$appsQuery->fetchColumn();
$recentQuery = $pdo->prepare('SELECT a.id, a.status, a.applied_at, u.name AS seeker_name, j.title FROM applications a JOIN users u ON u.id = a.seeker_id JOIN jobs j ON j.id = a.job_id WHERE j.company_id = ? ORDER BY a.applied_at DESC LIMIT 6');
$recentQuery->execute([$company['id']]);
$recent = $recentQuery->fetchAll();
$pageTitle = 'Employer dashboard';
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-heading"><div><span class="eyebrow">EMPLOYER SPACE</span><h1>Welcome, <?= e($company['name']) ?>.</h1><p>Find your next great teammate.</p></div><?php if ($company['status'] !== 'rejected'): ?><a class="button" href="<?= e(app_url('employer/add_job.php')) ?>">Post a job <span>→</span></a><?php endif; ?></section>
<?php require __DIR__ . '/nav.php'; ?>
<?php if ($company['status'] === 'pending'): ?><div class="alert alert-warning">Your company profile is <strong>pending</strong>. You can submit jobs now; they will become visible after the company and job are approved.</div><?php elseif ($company['status'] === 'rejected'): ?><div class="alert alert-warning">Your company profile is <strong>rejected</strong>. Please contact an administrator for help.</div><?php endif; ?>
<div class="metric-grid"><article class="metric-card"><span>All job posts</span><strong><?= (int)$counts['jobs'] ?></strong><small>Across your company</small></article><article class="metric-card"><span>Live opportunities</span><strong><?= (int)$counts['live'] ?></strong><small>Visible to job seekers</small></article><article class="metric-card"><span>Applications</span><strong><?= $applicationCount ?></strong><small>Candidate interest</small></article></div>
<section class="content-card"><div class="section-heading compact"><div><span class="eyebrow">NEW CANDIDATES</span><h2>Recent applications</h2></div><a class="text-link" href="<?= e(app_url('employer/applicants.php')) ?>">View all →</a></div>
<?php if ($recent): ?><div class="table-wrap"><table><thead><tr><th>Candidate</th><th>Role</th><th>Applied</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($recent as $application): ?><tr><td><?= e($application['seeker_name']) ?></td><td><?= e($application['title']) ?></td><td><?= e(date('M j, Y', strtotime($application['applied_at']))) ?></td><td><span class="badge badge-<?= e(application_status_class($application['status'])) ?>"><?= e(ucfirst($application['status'])) ?></span></td><td><a class="text-link" href="<?= e(app_url('employer/application_details.php?id=' . (int)$application['id'])) ?>">Review →</a></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty-state small"><p>Candidate applications will appear here.</p></div><?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
