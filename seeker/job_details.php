<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('seeker');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$statement = $pdo->prepare("SELECT j.*, c.name AS company_name, c.location AS company_location, c.website, c.description AS company_description FROM jobs j JOIN companies c ON c.id = j.company_id WHERE j.id = ? AND j.status = 'approved' AND c.status = 'approved' AND (j.deadline IS NULL OR j.deadline >= CURDATE())");
$statement->execute([$id ?: 0]);
$job = $statement->fetch();
if (!$job) {
    http_response_code(404);
    exit('This opportunity is not available.');
}
$applyCheck = $pdo->prepare('SELECT id FROM applications WHERE job_id = ? AND seeker_id = ?');
$applyCheck->execute([$job['id'], current_user()['id']]);
$alreadyApplied = (bool)$applyCheck->fetchColumn();
$pageTitle = $job['title'];
$mapUrl = job_map_url($job['latitude'], $job['longitude']);
$includeMapAssets = $mapUrl !== null;
require __DIR__ . '/../includes/header.php';
?>
<a class="back-link" href="<?= e(app_url('seeker/jobs.php')) ?>">← Back to opportunities</a>
<section class="detail-layout"><article class="content-card job-detail"><div class="job-card-top"><span class="company-avatar avatar-large"><?= e(strtoupper(substr($job['company_name'], 0, 1))) ?></span><span class="job-type"><?= e($job['job_type']) ?></span></div><h1><?= e($job['title']) ?></h1><p class="company-name"><?= e($job['company_name']) ?></p><div class="job-meta"><span>⌖ <?= e($job['location']) ?></span><span>◈ <?= e($job['category']) ?></span><span>◷ <?= e(date('M j, Y', strtotime($job['created_at']))) ?></span><?php if ($job['duration_days']): ?><span>Estimated duration: <?= (int)$job['duration_days'] ?> days</span><?php endif; ?><?php if ($job['deadline']): ?><span>Apply by <?= e(date('M j, Y', strtotime($job['deadline']))) ?></span><?php endif; ?></div><?php if ($job['salary']): ?><p class="salary"><?= e($job['salary']) ?></p><?php endif; ?>
<div class="detail-copy"><h2>About the role</h2><p><?= nl2br(e($job['description'])) ?></p><?php if ($job['requirements']): ?><h2>What we're looking for</h2><p><?= nl2br(e($job['requirements'])) ?></p><?php endif; ?></div><section class="location-panel"><h2>Exact job location</h2><?php if ($mapUrl): ?><p class="location-coordinates">Coordinates: <?= e(number_format((float)$job['latitude'], 7, '.', '')) ?>, <?= e(number_format((float)$job['longitude'], 7, '.', '')) ?></p><p class="field-hint" data-map-status role="status">Loading exact location map.</p><div class="location-map" data-location-map data-latitude="<?= e($job['latitude']) ?>" data-longitude="<?= e($job['longitude']) ?>" aria-label="Map showing the exact job location"></div><a class="location-external-link" href="<?= e($mapUrl) ?>" target="_blank" rel="noopener noreferrer">Open this pin in OpenStreetMap ↗</a><?php else: ?><p class="field-hint">The employer has not provided an exact map pin for this listing.</p><?php endif; ?></section></article>
<aside class="content-card apply-card"><span class="eyebrow">READY FOR WHAT'S NEXT?</span><h2>Make your move.</h2><p>Share a little about why this role feels right for you.</p><?php if ($alreadyApplied): ?><div class="alert alert-success">You have already applied to this opportunity.</div><a class="button button-full" href="<?= e(app_url('seeker/applications.php')) ?>">View my applications</a><?php else: ?><form method="post" action="<?= e(app_url('seeker/apply.php')) ?>" class="form-stack"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>"><label>Cover letter <span class="field-hint">Optional</span><textarea data-user-message name="cover_letter" rows="7" maxlength="5000" placeholder="Tell the employer what interests you about this opportunity."></textarea><span class="field-hint">Optional. If provided, use at least 5 letters or numbers (up to 5,000).</span></label><button class="button button-full" type="submit">Apply now <span>→</span></button></form><?php endif; ?></aside></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
