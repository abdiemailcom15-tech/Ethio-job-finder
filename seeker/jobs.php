<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('seeker');
$search = trim((string)($_GET['q'] ?? ''));
$category = trim((string)($_GET['category'] ?? ''));
$location = trim((string)($_GET['location'] ?? ''));
$sql = "SELECT j.*, c.name AS company_name FROM jobs j JOIN companies c ON c.id = j.company_id WHERE j.status = 'approved' AND c.status = 'approved' AND (j.deadline IS NULL OR j.deadline >= CURDATE())";
$params = [];
if ($search !== '') {
    $sql .= ' AND (j.title LIKE ? OR j.description LIKE ? OR c.name LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term);
}
if ($category !== '') {
    $sql .= ' AND j.category = ?';
    $params[] = $category;
}
if ($location !== '') {
    $sql .= ' AND j.location LIKE ?';
    $params[] = '%' . $location . '%';
}
$sql .= ' ORDER BY j.created_at DESC';
$statement = $pdo->prepare($sql);
$statement->execute($params);
$jobs = $statement->fetchAll();
$categories = $pdo->query("SELECT DISTINCT category FROM jobs WHERE status = 'approved' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$pageTitle = 'Find jobs';
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-heading"><div><span class="eyebrow">YOUR NEXT OPPORTUNITY</span><h1>Find your fit</h1><p>Explore roles from approved employers.</p></div></section>
<nav class="dashboard-nav"><a href="<?= e(app_url('seeker/dashboard.php')) ?>">Overview</a><a href="<?= e(app_url('seeker/profile.php')) ?>">My profile</a><a class="active" href="<?= e(app_url('seeker/jobs.php')) ?>">Find jobs</a><a href="<?= e(app_url('seeker/applications.php')) ?>">My applications</a><a href="<?= e(app_url('seeker/work_orders.php')) ?>">My work</a><a href="<?= e(app_url('work_chats.php')) ?>">Work chats</a></nav>
<form class="search-panel seeker-search" method="get">
    <label class="search-field"><span>⌕</span><input type="search" name="q" placeholder="Job title, company, or keyword" value="<?= e($search) ?>"></label>
    <label class="search-field"><span>◈</span><select name="category"><option value="">All categories</option><?php foreach ($categories as $item): ?><option value="<?= e($item) ?>" <?= $category === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select></label>
    <label class="search-field"><span>⌖</span><input name="location" placeholder="Location" value="<?= e($location) ?>"></label><button class="button" type="submit">Search</button>
</form>
<div class="results-caption"><span><?= count($jobs) ?> opportunities</span></div>
<div class="job-grid">
<?php foreach ($jobs as $job): ?><article class="job-card"><div class="job-card-top"><span class="company-avatar"><?= e(strtoupper(substr($job['company_name'], 0, 1))) ?></span><span class="job-type"><?= e($job['job_type']) ?></span></div><h3><a href="<?= e(app_url('seeker/job_details.php?id=' . (int)$job['id'])) ?>"><?= e($job['title']) ?></a></h3><p class="company-name"><?= e($job['company_name']) ?></p><p class="job-description"><?= e(text_excerpt($job['description'], 135)) ?></p><div class="job-meta"><span>⌖ <?= e($job['location']) ?></span><span>◈ <?= e($job['category']) ?></span></div><?php if ($mapUrl = job_map_url($job['latitude'], $job['longitude'])): ?><a class="map-link" href="<?= e($mapUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="Open exact map for <?= e($job['title']) ?>">View exact map ↗</a><?php endif; ?><?php if ($job['duration_days'] || $job['deadline']): ?><div class="job-meta job-schedule"><?php if ($job['duration_days']): ?><span>◷ <?= (int)$job['duration_days'] ?> day estimate</span><?php endif; ?><?php if ($job['deadline']): ?><span>Apply by <?= e(date('M j, Y', strtotime($job['deadline']))) ?></span><?php endif; ?></div><?php endif; ?><?php if ($job['salary']): ?><div class="salary"><?= e($job['salary']) ?></div><?php endif; ?><a class="card-link" href="<?= e(app_url('seeker/job_details.php?id=' . (int)$job['id'])) ?>">View opportunity <span>→</span></a></article><?php endforeach; ?>
</div>
<?php if (!$jobs): ?><div class="empty-state"><span class="empty-icon">⌕</span><h3>No opportunities match that search</h3><p>Try another keyword or broaden your filters.</p></div><?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
