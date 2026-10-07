<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = filter_input(INPUT_POST, 'company_id', FILTER_VALIDATE_INT);
    $status = $_POST['status'] ?? '';
    if ($id && in_array($status, ['approved', 'rejected'], true)) {
        $pdo->prepare('UPDATE companies SET status = ? WHERE id = ?')->execute([$status, $id]);
        flash('success', 'Company status updated.');
    } else {
        flash('error', 'Choose a valid company and status.');
    }
    redirect(app_url('admin/companies.php'));
}
$companies = $pdo->query('SELECT c.*, u.email, u.name AS contact_name, (SELECT COUNT(*) FROM jobs j WHERE j.company_id = c.id) AS job_count FROM companies c JOIN users u ON u.id = c.user_id ORDER BY FIELD(c.status, "pending", "approved", "rejected"), c.created_at DESC')->fetchAll();
$pageTitle = 'Manage companies';
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-heading"><div><span class="eyebrow">ADMINISTRATION</span><h1>Companies</h1><p>Review employer profiles and manage access to hiring tools.</p></div></section>
<?php require __DIR__ . '/nav.php'; ?>
<section class="content-card"><div class="table-wrap"><table><thead><tr><th>Company</th><th>Contact</th><th>Location</th><th>Jobs</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php foreach ($companies as $company): ?><tr><td><strong><?= e($company['name']) ?></strong><?php if ($company['website']): ?><a class="table-sub" href="<?= e($company['website']) ?>" target="_blank" rel="noopener noreferrer"><?= e($company['website']) ?></a><?php endif; ?></td><td><?= e($company['contact_name']) ?><small class="table-sub"><?= e($company['email']) ?></small></td><td><?= e($company['location']) ?></td><td><?= (int)$company['job_count'] ?></td><td><span class="badge badge-<?= e(application_status_class($company['status'])) ?>"><?= e(ucfirst($company['status'])) ?></span></td><td><form method="post" class="inline-actions"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="company_id" value="<?= (int)$company['id'] ?>"><select name="status" aria-label="Set company status"><option value="approved">Approve</option><option value="rejected">Reject</option></select><button class="button button-small" type="submit">Save</button></form></td></tr><?php endforeach; ?>
</tbody></table></div><?php if (!$companies): ?><div class="empty-state"><p>No company profiles yet.</p></div><?php endif; ?></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
