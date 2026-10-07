<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('seeker');
$user = current_user();
$profileQuery = $pdo->prepare('SELECT * FROM seeker_profiles WHERE user_id = ?');
$profileQuery->execute([$user['id']]);
$profile = $profileQuery->fetch();
$stats = $pdo->prepare("SELECT COUNT(*) AS total, SUM(status = 'accepted') AS accepted, SUM(status = 'reviewing') AS reviewing FROM applications WHERE seeker_id = ?");
$stats->execute([$user['id']]);
$summary = $stats->fetch();
$applications = $pdo->prepare('SELECT a.status, a.applied_at, j.title, j.id AS job_id, c.name AS company_name FROM applications a JOIN jobs j ON j.id = a.job_id JOIN companies c ON c.id = j.company_id WHERE a.seeker_id = ? ORDER BY a.applied_at DESC LIMIT 5');
$applications->execute([$user['id']]);
$recent = $applications->fetchAll();
$pageTitle = 'Your dashboard';
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-heading"><div><span class="eyebrow">YOUR CAREER SPACE</span><h1>Welcome, <?= e($user['name']) ?>.</h1><p>Keep your profile fresh and stay close to your next opportunity.</p></div><a class="button" href="<?= e(app_url('seeker/jobs.php')) ?>">Find a job <span>→</span></a></section>
<nav class="dashboard-nav"><a class="active" href="<?= e(app_url('seeker/dashboard.php')) ?>">Overview</a><a href="<?= e(app_url('seeker/profile.php')) ?>">My profile</a><a href="<?= e(app_url('seeker/jobs.php')) ?>">Find jobs</a><a href="<?= e(app_url('seeker/applications.php')) ?>">My applications</a><a href="<?= e(app_url('seeker/work_orders.php')) ?>">My work</a><a href="<?= e(app_url('work_chats.php')) ?>">Work chats</a></nav>
<div class="metric-grid seeker-metrics"><article class="metric-card"><span>Applications sent</span><strong><?= (int)$summary['total'] ?></strong><small>Your opportunities in motion</small></article><article class="metric-card"><span>In review</span><strong><?= (int)$summary['reviewing'] ?></strong><small>Being considered by employers</small></article><article class="metric-card"><span>Profile</span><strong><?= $profile && ($profile['skills'] || $profile['cv_filename']) ? 'Ready' : 'Start' ?></strong><small><a href="<?= e(app_url('seeker/profile.php')) ?>">Complete your profile →</a></small></article></div>
<section class="content-card"><div class="section-heading compact"><div><span class="eyebrow">YOUR PROGRESS</span><h2>Recent applications</h2></div><a class="text-link" href="<?= e(app_url('seeker/applications.php')) ?>">View all →</a></div>
<?php if ($recent): ?><div class="table-wrap"><table><thead><tr><th>Opportunity</th><th>Company</th><th>Applied</th><th>Status</th></tr></thead><tbody><?php foreach ($recent as $item): ?><tr><td><a class="table-primary" href="<?= e(app_url('seeker/job_details.php?id=' . (int)$item['job_id'])) ?>"><?= e($item['title']) ?></a></td><td><?= e($item['company_name']) ?></td><td><?= e(date('M j, Y', strtotime($item['applied_at']))) ?></td><td><span class="badge badge-<?= e(application_status_class($item['status'])) ?>"><?= e(ucfirst($item['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty-state small"><p>Your applications will show up here once you apply to a role.</p><a class="text-link" href="<?= e(app_url('seeker/jobs.php')) ?>">Explore open jobs →</a></div><?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
