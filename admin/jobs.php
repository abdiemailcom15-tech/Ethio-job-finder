<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = filter_input(INPUT_POST, 'job_id', FILTER_VALIDATE_INT);
    $status = $_POST['status'] ?? '';
    if (($_POST['action'] ?? '') === 'delete' && $id) {
        $pdo->prepare('DELETE FROM jobs WHERE id = ?')->execute([$id]);
        flash('success', 'Job and its applications were deleted.');
    } elseif ($id && in_array($status, ['approved', 'rejected', 'closed'], true)) {
        $pdo->prepare('UPDATE jobs SET status = ? WHERE id = ?')->execute([$status, $id]);
        flash('success', 'Job status updated.');
    } else {
        flash('error', 'Choose a valid job and action.');
    }
    redirect(app_url('admin/jobs.php'));
}
$jobs = $pdo->query("SELECT j.*, c.name AS company_name FROM jobs j JOIN companies c ON c.id = j.company_id ORDER BY FIELD(j.status, 'pending', 'approved', 'rejected', 'closed'), j.created_at DESC")->fetchAll();
$pageTitle = 'Manage jobs';
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-heading"><div><span class="eyebrow">ADMINISTRATION</span><h1>Manage jobs</h1><p>Review job listings and keep the opportunities board up to date.</p></div><a class="button" href="<?= e(app_url('admin/job_form.php')) ?>">Add a job <span>+</span></a></section>
<?php require __DIR__ . '/nav.php'; ?>
<section class="content-card"><div class="table-wrap"><table><thead><tr><th>Role</th><th>Company</th><th>Location</th><th>Posted</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php foreach ($jobs as $job): ?><tr><td><strong><?= e($job['title']) ?></strong><small class="table-sub"><?= e($job['category']) ?> · <?= e($job['job_type']) ?></small></td><td><?= e($job['company_name']) ?></td><td><?= e($job['location']) ?></td><td><?= e(date('M j, Y', strtotime($job['created_at']))) ?></td><td><span class="badge badge-<?= e(application_status_class($job['status'])) ?>"><?= e(ucfirst($job['status'])) ?></span></td><td><div class="inline-actions"><form method="post" class="inline-actions"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>"><select name="status" aria-label="Set job status"><option value="approved">Approve</option><option value="rejected">Reject</option><option value="closed">Close</option></select><button class="button button-small" type="submit">Save</button></form><a class="button button-small button-outline" href="<?= e(app_url('admin/job_form.php?id=' . (int)$job['id'])) ?>">Edit</a><form method="post" data-confirm="Delete this job and its applications? This cannot be undone."><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>"><input type="hidden" name="action" value="delete"><button class="button button-small button-danger" type="submit">Delete</button></form></div></td></tr><?php endforeach; ?>
</tbody></table></div><?php if (!$jobs): ?><div class="empty-state"><p>No jobs have been posted yet.</p></div><?php endif; ?></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
