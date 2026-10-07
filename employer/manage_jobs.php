<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('employer');
$companyQuery = $pdo->prepare('SELECT id FROM companies WHERE user_id = ?');
$companyQuery->execute([current_user()['id']]);
$companyId = (int)$companyQuery->fetchColumn();
if (!$companyId) {
    http_response_code(500);
    exit('Company profile not found.');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $jobId = filter_input(INPUT_POST, 'job_id', FILTER_VALIDATE_INT);
    if ($jobId) {
        $delete = $pdo->prepare('DELETE FROM jobs WHERE id = ? AND company_id = ?');
        $delete->execute([$jobId, $companyId]);
        flash($delete->rowCount() ? 'success' : 'error', $delete->rowCount() ? 'Job and its applications were deleted.' : 'That job could not be found.');
    }
    redirect(app_url('employer/manage_jobs.php'));
}
$query = $pdo->prepare('SELECT j.*, (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) AS applicant_count FROM jobs j WHERE j.company_id = ? ORDER BY j.created_at DESC');
$query->execute([$companyId]);
$jobs = $query->fetchAll();
$pageTitle = 'Manage jobs';
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-heading"><div><span class="eyebrow">EMPLOYER SPACE</span><h1>Manage jobs</h1><p>Keep your listings current and review candidate interest.</p></div><a class="button" href="<?= e(app_url('employer/add_job.php')) ?>">Post a job <span>→</span></a></section>
<?php require __DIR__ . '/nav.php'; ?>
<section class="content-card"><div class="table-wrap"><table><thead><tr><th>Opportunity</th><th>Location</th><th>Duration / deadline</th><th>Applicants</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach ($jobs as $job): ?><tr><td><strong><?= e($job['title']) ?></strong><small class="table-sub"><?= e($job['category']) ?> · <?= e($job['job_type']) ?></small></td><td><?= e($job['location']) ?></td><td><?= $job['duration_days'] ? (int)$job['duration_days'] . ' days' : '—' ?><?= $job['deadline'] ? '<small class="table-sub">Apply by ' . e(date('M j, Y', strtotime($job['deadline']))) . '</small>' : '' ?></td><td><a class="text-link" href="<?= e(app_url('employer/applicants.php?job_id=' . (int)$job['id'])) ?>"><?= (int)$job['applicant_count'] ?> candidates</a></td><td><span class="badge badge-<?= e(application_status_class($job['status'])) ?>"><?= e(ucfirst($job['status'])) ?></span></td><td><div class="inline-actions"><a class="button button-small button-outline" href="<?= e(app_url('employer/add_job.php?id=' . (int)$job['id'])) ?>">Edit</a><form method="post" data-confirm="Delete this job and its applications? This cannot be undone."><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>"><button class="button button-small button-danger" type="submit">Delete</button></form></div></td></tr><?php endforeach; ?></tbody></table></div><?php if (!$jobs): ?><div class="empty-state"><span class="empty-icon">✦</span><h3>Your first opportunity starts here</h3><p>Post a role and connect with your next teammate.</p><a class="button" href="<?= e(app_url('employer/add_job.php')) ?>">Post a job</a></div><?php endif; ?></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
