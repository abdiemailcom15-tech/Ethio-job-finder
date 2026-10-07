<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$totals = [
    'accounts' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'companies' => (int)$pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn(),
    'jobs' => (int)$pdo->query("SELECT COUNT(*) FROM jobs WHERE status = 'approved'")->fetchColumn(),
    'applications' => (int)$pdo->query('SELECT COUNT(*) FROM applications')->fetchColumn(),
];
$monthly = $pdo->query("SELECT DATE_FORMAT(applied_at, '%Y-%m') AS month, COUNT(*) AS total FROM applications WHERE applied_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY month ORDER BY month")->fetchAll();
$byStatus = $pdo->query('SELECT status, COUNT(*) AS total FROM applications GROUP BY status ORDER BY total DESC')->fetchAll();
$pageTitle = 'Reports';
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-heading"><div><span class="eyebrow">ADMINISTRATION</span><h1>Marketplace reports</h1><p>A snapshot of activity across Workly.</p></div></section>
<?php require __DIR__ . '/nav.php'; ?>
<div class="metric-grid">
<?php foreach (['accounts' => 'Accounts', 'companies' => 'Companies', 'jobs' => 'Live jobs', 'applications' => 'Applications'] as $key => $label): ?><article class="metric-card"><span><?= e($label) ?></span><strong><?= number_format($totals[$key]) ?></strong><small>Current total</small></article><?php endforeach; ?>
</div>
<section class="content-card report-grid"><div><div class="section-heading compact"><div><span class="eyebrow">LAST SIX MONTHS</span><h2>Applications by month</h2></div></div>
<?php if ($monthly): foreach ($monthly as $month): ?><div class="bar-row"><span><?= e(date('M Y', strtotime($month['month'] . '-01'))) ?></span><div class="bar-track"><i style="width:<?= (int)round(100 * $month['total'] / max(array_column($monthly, 'total'))) ?>%"></i></div><strong><?= (int)$month['total'] ?></strong></div><?php endforeach; else: ?><p class="muted-copy">There is no application activity to report yet.</p><?php endif; ?></div>
<div><div class="section-heading compact"><div><span class="eyebrow">PIPELINE</span><h2>Application status</h2></div></div>
<?php if ($byStatus): foreach ($byStatus as $row): ?><div class="status-row"><span class="badge badge-<?= e(application_status_class($row['status'])) ?>"><?= e(ucfirst($row['status'])) ?></span><strong><?= (int)$row['total'] ?></strong></div><?php endforeach; else: ?><p class="muted-copy">No applications yet.</p><?php endif; ?></div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
