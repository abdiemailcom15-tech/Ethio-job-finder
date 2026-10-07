<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$counts = [
    'jobs' => (int)$pdo->query("SELECT COUNT(*) FROM jobs WHERE status = 'approved'")->fetchColumn(),
    'pending' => (int)$pdo->query("SELECT COUNT(*) FROM jobs WHERE status = 'pending'")->fetchColumn(),
    'companies' => (int)$pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn(),
    'seekers' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'seeker'")->fetchColumn(),
    'applications' => (int)$pdo->query('SELECT COUNT(*) FROM applications')->fetchColumn(),
];
$recent = $pdo->query("SELECT a.id, a.status, a.applied_at, u.name AS seeker_name, j.title AS job_title, c.name AS company_name
    FROM applications a JOIN users u ON u.id = a.seeker_id JOIN jobs j ON j.id = a.job_id
    JOIN companies c ON c.id = j.company_id ORDER BY a.applied_at DESC LIMIT 6")->fetchAll();
$pageTitle = 'Admin dashboard';
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-heading"><div><span class="eyebrow">ADMINISTRATION</span><h1>Overview</h1><p>Keep an eye on your marketplace and the people in it.</p></div><a class="button" href="<?= e(app_url('admin/jobs.php')) ?>">Review pending jobs</a></section>
<nav class="dashboard-nav"><a class="active" href="<?= e(app_url('admin/dashboard.php')) ?>">Overview</a><a href="<?= e(app_url('admin/account.php')) ?>">Admin account</a><a href="<?= e(app_url('admin/jobs.php')) ?>">Jobs</a><a href="<?= e(app_url('admin/companies.php')) ?>">Companies</a><a href="<?= e(app_url('admin/job_seekers.php')) ?>">Job seekers</a><a href="<?= e(app_url('admin/applications.php')) ?>">Applications</a><a href="<?= e(app_url('admin/work_order_issues.php')) ?>">Work order issues</a><a href="<?= e(app_url('admin/chats.php')) ?>">Work chats</a><a href="<?= e(app_url('admin/reports.php')) ?>">Reports</a></nav>
<div class="metric-grid">
    <article class="metric-card"><span>Live jobs</span><strong><?= $counts['jobs'] ?></strong><small>Approved opportunities</small></article>
    <article class="metric-card"><span>Needs review</span><strong><?= $counts['pending'] ?></strong><small>Jobs awaiting approval</small></article>
    <article class="metric-card"><span>Companies</span><strong><?= $counts['companies'] ?></strong><small>Employer profiles</small></article>
    <article class="metric-card"><span>Job seekers</span><strong><?= $counts['seekers'] ?></strong><small>Registered candidates</small></article>
</div>
<section class="content-card"><div class="section-heading compact"><div><span class="eyebrow">LATEST ACTIVITY</span><h2>Recent applications</h2></div><a class="text-link" href="<?= e(app_url('admin/applications.php')) ?>">View all →</a></div>
    <?php if ($recent): ?><div class="table-wrap"><table><thead><tr><th>Candidate</th><th>Role</th><th>Company</th><th>Status</th><th>Applied</th></tr></thead><tbody>
    <?php foreach ($recent as $row): ?><tr><td><?= e($row['seeker_name']) ?></td><td><?= e($row['job_title']) ?></td><td><?= e($row['company_name']) ?></td><td><span class="badge badge-<?= e(application_status_class($row['status'])) ?>"><?= e(ucfirst($row['status'])) ?></span></td><td><?= e(date('M j, Y', strtotime($row['applied_at']))) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php else: ?><div class="empty-state small"><p>Applications will appear here as candidates apply.</p></div><?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
