<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('seeker');
$statement = $pdo->prepare('SELECT a.*, j.title, j.id AS job_id, j.location, c.name AS company_name FROM applications a JOIN jobs j ON j.id = a.job_id JOIN companies c ON c.id = j.company_id WHERE a.seeker_id = ? ORDER BY a.applied_at DESC');
$statement->execute([current_user()['id']]);
$applications = $statement->fetchAll();
$pageTitle = 'My applications';
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-heading"><div><span class="eyebrow">YOUR CAREER SPACE</span><h1>My applications</h1><p>Keep track of every opportunity you've pursued.</p></div><a class="button" href="<?= e(app_url('seeker/jobs.php')) ?>">Find a job <span>→</span></a></section>
<nav class="dashboard-nav"><a href="<?= e(app_url('seeker/dashboard.php')) ?>">Overview</a><a href="<?= e(app_url('seeker/profile.php')) ?>">My profile</a><a href="<?= e(app_url('seeker/jobs.php')) ?>">Find jobs</a><a class="active" href="<?= e(app_url('seeker/applications.php')) ?>">My applications</a><a href="<?= e(app_url('seeker/work_orders.php')) ?>">My work</a><a href="<?= e(app_url('work_chats.php')) ?>">Work chats</a></nav>
<section class="content-card"><div class="table-wrap"><table><thead><tr><th>Opportunity</th><th>Company</th><th>Location</th><th>Applied</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($applications as $application): ?><tr><td><a class="table-primary" href="<?= e(app_url('seeker/job_details.php?id=' . (int)$application['job_id'])) ?>"><?= e($application['title']) ?></a></td><td><?= e($application['company_name']) ?></td><td><?= e($application['location']) ?></td><td><?= e(date('M j, Y', strtotime($application['applied_at']))) ?></td><td><span class="badge badge-<?= e(application_status_class($application['status'])) ?>"><?= e(ucfirst($application['status'])) ?></span></td><td><?php if ($application['status'] === 'accepted'): ?><a class="text-link" href="<?= e(app_url('seeker/work_orders.php')) ?>">View work →</a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php if (!$applications): ?><div class="empty-state"><span class="empty-icon">✦</span><h3>Your next move is waiting</h3><p>Explore open jobs and apply to the opportunities that feel right.</p><a class="button" href="<?= e(app_url('seeker/jobs.php')) ?>">Explore jobs</a></div><?php endif; ?></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
