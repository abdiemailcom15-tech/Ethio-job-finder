<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Find your next opportunity';
$search = trim((string)($_GET['q'] ?? ''));
$category = trim((string)($_GET['category'] ?? ''));
$location = trim((string)($_GET['location'] ?? ''));
$sql = "SELECT j.*, c.name AS company_name, c.location AS company_location,
               (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) AS applicant_count
        FROM jobs j JOIN companies c ON c.id = j.company_id
        WHERE j.status = 'approved' AND c.status = 'approved' AND (j.deadline IS NULL OR j.deadline >= CURDATE())";
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
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <div class="hero-copy">
        <span class="eyebrow">YOUR NEXT CHAPTER STARTS HERE</span>
        <h1>Good work.<br><span>Good people.</span><br>Great possibilities.</h1>
        <p>Discover opportunities from teams that are building what comes next.</p>
        <a class="button" href="#jobs">Explore open roles <span aria-hidden="true">→</span></a>
    </div>
    <div class="hero-art" aria-hidden="true">
        <div class="art-card art-card-top"><span class="art-icon">✦</span><div><strong>Make your move</strong><small>New opportunities await</small></div></div>
        <div class="art-orbit"><span>W</span></div>
        <div class="art-card art-card-bottom"><span class="online-dot"></span><div><strong>2,400+</strong><small>teams hiring now</small></div></div>
        <span class="art-spark spark-one">✳</span><span class="art-spark spark-two">✦</span>
    </div>
</section>
<section class="stats-strip"><div><strong><?= number_format(count($jobs)) ?>+</strong><span>Open opportunities</span></div><div><strong>Everywhere</strong><span>Remote &amp; local work</span></div><div><strong>For everyone</strong><span>Find your next step</span></div></section>
<section id="jobs" class="section jobs-section">
    <div class="section-heading"><div><span class="eyebrow">FIND YOUR FIT</span><h2>Explore open roles</h2></div><p>Thoughtful teams. Meaningful work. Your next opportunity.</p></div>
    <form class="search-panel" method="get" action="<?= e(app_url('index.php')) ?>">
        <label class="search-field"><span>⌕</span><input type="search" name="q" placeholder="Job title, company, or keyword" value="<?= e($search) ?>"></label>
        <label class="search-field"><span>◈</span><select name="category"><option value="">All categories</option><?php foreach ($categories as $item): ?><option value="<?= e($item) ?>" <?= $category === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select></label>
        <label class="search-field"><span>⌖</span><input type="text" name="location" placeholder="Location" value="<?= e($location) ?>"></label>
        <button class="button" type="submit">Search jobs</button>
    </form>
    <div class="results-caption"><span><?= count($jobs) ?> opportunities</span><span>Recently posted</span></div>
    <div class="job-grid">
        <?php foreach ($jobs as $job): ?>
            <article class="job-card">
                <div class="job-card-top"><span class="company-avatar"><?= e(strtoupper(substr($job['company_name'], 0, 1))) ?></span><span class="job-type"><?= e($job['job_type']) ?></span></div>
                <h3><a href="<?= e(app_url('seeker/job_details.php?id=' . (int)$job['id'])) ?>"><?= e($job['title']) ?></a></h3>
                <p class="company-name"><?= e($job['company_name']) ?></p>
                <p class="job-description"><?= e(text_excerpt($job['description'], 135)) ?></p>
                <div class="job-meta"><span>⌖ <?= e($job['location']) ?></span><span>◈ <?= e($job['category']) ?></span></div>
                <?php if ($mapUrl = job_map_url($job['latitude'], $job['longitude'])): ?><a class="map-link" href="<?= e($mapUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="Open exact map for <?= e($job['title']) ?>">View exact map ↗</a><?php endif; ?>
                <?php if ($job['duration_days'] || $job['deadline']): ?><div class="job-meta job-schedule"><?php if ($job['duration_days']): ?><span>◷ <?= (int)$job['duration_days'] ?> day estimate</span><?php endif; ?><?php if ($job['deadline']): ?><span>Apply by <?= e(date('M j, Y', strtotime($job['deadline']))) ?></span><?php endif; ?></div><?php endif; ?>
                <?php if ($job['salary']): ?><div class="salary"><?= e($job['salary']) ?></div><?php endif; ?>
                <a class="card-link" href="<?= e(app_url('seeker/job_details.php?id=' . (int)$job['id'])) ?>">View opportunity <span>→</span></a>
            </article>
        <?php endforeach; ?>
    </div>
    <?php if (!$jobs): ?><div class="empty-state"><span class="empty-icon">⌕</span><h3>No roles found just yet</h3><p>Try adjusting your search, or check back soon for new opportunities.</p></div><?php endif; ?>
</section>
<section class="callout"><div><span class="eyebrow">HIRING GREAT PEOPLE?</span><h2>Your next teammate is out there.</h2><p>Build your company profile and share your next opportunity.</p></div><a class="button button-light" href="<?= e(app_url('register.php?role=employer')) ?>">Start hiring <span>→</span></a></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
