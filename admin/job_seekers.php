<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$seekers = $pdo->query("SELECT u.id, u.name, u.email, u.created_at, p.location, p.skills, (SELECT COUNT(*) FROM applications a WHERE a.seeker_id = u.id) AS applications FROM users u LEFT JOIN seeker_profiles p ON p.user_id = u.id WHERE u.role = 'seeker' ORDER BY u.created_at DESC")->fetchAll();
$pageTitle = 'Job seekers';
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-heading"><div><span class="eyebrow">ADMINISTRATION</span><h1>Job seekers</h1><p>View registered candidates and their marketplace activity.</p></div></section>
<?php require __DIR__ . '/nav.php'; ?>
<section class="content-card"><div class="table-wrap"><table><thead><tr><th>Candidate</th><th>Location</th><th>Skills</th><th>Applications</th><th>Joined</th></tr></thead><tbody>
<?php foreach ($seekers as $seeker): ?><tr><td><strong><?= e($seeker['name']) ?></strong><small class="table-sub"><?= e($seeker['email']) ?></small></td><td><?= e($seeker['location'] ?: '—') ?></td><td><?= e($seeker['skills'] ? text_excerpt($seeker['skills'], 80) : '—') ?></td><td><?= (int)$seeker['applications'] ?></td><td><?= e(date('M j, Y', strtotime($seeker['created_at']))) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php if (!$seekers): ?><div class="empty-state"><p>No job seekers have registered yet.</p></div><?php endif; ?></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
