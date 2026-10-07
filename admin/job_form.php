<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$jobId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$job = ['company_id' => '', 'title' => '', 'category' => '', 'location' => '', 'latitude' => '', 'longitude' => '', 'job_type' => 'Full-time', 'salary' => '', 'description' => '', 'requirements' => '', 'duration_days' => '', 'deadline' => ''];
if ($jobId) {
    $query = $pdo->prepare('SELECT j.*, c.name AS company_name FROM jobs j JOIN companies c ON c.id = j.company_id WHERE j.id = ?');
    $query->execute([$jobId]);
    $existing = $query->fetch();
    if (!$existing) {
        http_response_code(404);
        exit('Job not found.');
    }
    $job = $existing;
}
$companies = $pdo->query("SELECT id, name FROM companies WHERE status = 'approved' ORDER BY name")->fetchAll();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $companyId = $jobId ? (int)$job['company_id'] : filter_input(INPUT_POST, 'company_id', FILTER_VALIDATE_INT);
    $values = [
        trim((string)($_POST['title'] ?? '')),
        trim((string)($_POST['category'] ?? '')),
        trim((string)($_POST['location'] ?? '')),
        (string)($_POST['job_type'] ?? ''),
        trim((string)($_POST['salary'] ?? '')),
        trim((string)($_POST['description'] ?? '')),
        trim((string)($_POST['requirements'] ?? '')),
        trim((string)($_POST['duration_days'] ?? '')),
        trim((string)($_POST['deadline'] ?? '')),
    ];
    $types = ['Full-time', 'Part-time', 'Contract', 'Internship', 'Remote'];
    $companyIds = array_map('intval', array_column($companies, 'id'));
    $duration = $values[7] === '' ? null : filter_var($values[7], FILTER_VALIDATE_INT);
    $deadline = $values[8] === '' ? null : DateTimeImmutable::createFromFormat('!Y-m-d', $values[8]);
    $dateErrors = DateTimeImmutable::getLastErrors();
    $deadlineValid = $values[8] === '' || ($deadline && $deadline->format('Y-m-d') === $values[8] && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0)) && $deadline >= new DateTimeImmutable('today'));
    $coordinates = parse_job_coordinates((string)($_POST['latitude'] ?? ''), (string)($_POST['longitude'] ?? ''));
    $descriptionError = validate_textarea($values[5], 'Role description', true, 10000);
    $requirementsError = validate_textarea($values[6], 'Requirements', false, 5000);
    $titleError = validate_job_title($values[0]);
    $categoryError = validate_meaningful_text($values[1], 'Job category', true, 100, 2);
    $locationError = validate_place_name($values[2], 'Job location');
    $categoryLetters = preg_match_all('/\p{L}/u', $values[1]);
    $salaryError = validate_meaningful_text($values[4], 'Salary or project budget', false, 100, 2);
    if ($titleError !== null) {
        $error = $titleError;
    } elseif ($categoryError !== null) {
        $error = $categoryError;
    } elseif ($categoryLetters === false || $categoryLetters < 2) {
        $error = 'Job category must include at least 2 letters.';
    } elseif ($locationError !== null) {
        $error = $locationError;
    } elseif ($salaryError !== null) {
        $error = $salaryError;
    } elseif ($coordinates === false) {
        $error = 'Enter both a valid latitude (-90 to 90) and longitude (-180 to 180), or leave both blank.';
    } elseif ($descriptionError !== null) {
        $error = $descriptionError;
    } elseif ($requirementsError !== null) {
        $error = $requirementsError;
    } elseif (!$companyId || (!$jobId && !in_array($companyId, $companyIds, true)) || !in_array($values[3], $types, true) || ($values[7] !== '' && ($duration === false || $duration < 1 || $duration > 3650)) || !$deadlineValid) {
        $error = 'Complete the required fields and select an approved company.';
    } else {
        $values[7] = $duration === null || $duration === false ? null : $duration;
        $values[8] = $values[8] ?: null;
    }
    if ($error === '' && $jobId) {
        $save = $pdo->prepare('UPDATE jobs SET title = ?, category = ?, location = ?, job_type = ?, salary = ?, description = ?, requirements = ?, duration_days = ?, deadline = ?, latitude = ?, longitude = ? WHERE id = ?');
        $save->execute([...$values, $coordinates['latitude'], $coordinates['longitude'], $jobId]);
        flash('success', 'Job listing updated.');
        redirect(app_url('admin/jobs.php'));
    } elseif ($error === '') {
        $save = $pdo->prepare("INSERT INTO jobs (company_id, title, category, location, job_type, salary, description, requirements, duration_days, deadline, latitude, longitude, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved')");
        $save->execute([$companyId, ...$values, $coordinates['latitude'], $coordinates['longitude']]);
        flash('success', 'Job listing created and published.');
        redirect(app_url('admin/jobs.php'));
    }
    if ($error !== '') {
        $job = array_merge($job, array_combine(['title', 'category', 'location', 'job_type', 'salary', 'description', 'requirements', 'duration_days', 'deadline'], $values) ?: []);
        $job['company_id'] = $companyId;
        $job['latitude'] = trim((string)($_POST['latitude'] ?? ''));
        $job['longitude'] = trim((string)($_POST['longitude'] ?? ''));
    }
}
$pageTitle = $jobId ? 'Edit job' : 'Add job';
$includeMapAssets = true;
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-heading"><div><span class="eyebrow">ADMINISTRATION</span><h1><?= $jobId ? 'Edit job' : 'Add a job' ?></h1><p>Manage a marketplace listing on behalf of an approved company.</p></div></section>
<?php require __DIR__ . '/nav.php'; ?>
<section class="content-card profile-card"><?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?><?php if (!$jobId && !$companies): ?><div class="alert alert-warning">Approve a company before creating a job listing.</div><?php endif; ?><form method="post" class="form-stack"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><?php if ($jobId): ?><p class="muted-copy"><strong>Company:</strong> <?= e($job['company_name']) ?> (assignment cannot be changed)</p><?php else: ?><label>Company<select name="company_id" required><?php foreach ($companies as $company): ?><option value="<?= (int)$company['id'] ?>"><?= e($company['name']) ?></option><?php endforeach; ?></select></label><?php endif; ?><div class="form-grid"><label>Job title *<input name="title" minlength="3" maxlength="180" required value="<?= e($job['title']) ?>"></label><label>Category *<input name="category" minlength="2" maxlength="100" required value="<?= e($job['category']) ?>"></label><label>Location *<input name="location" minlength="2" maxlength="120" required value="<?= e($job['location']) ?>"></label><label>Job type<select name="job_type"><?php foreach (['Full-time', 'Part-time', 'Contract', 'Internship', 'Remote'] as $type): ?><option value="<?= e($type) ?>" <?= $job['job_type'] === $type ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?></select></label><label>Estimated duration in days<input type="number" name="duration_days" min="1" max="3650" value="<?= e((string)$job['duration_days']) ?>"></label><label>Application / job deadline<input type="date" name="deadline" min="<?= e(date('Y-m-d')) ?>" value="<?= e($job['deadline']) ?>"></label></div><p class="field-hint">Duration and deadline are optional.</p><div class="location-picker"><span class="field-label">Exact job location <span class="field-hint">(optional for remote jobs)</span></span><p class="field-hint">Click the map to drop a precise pin, or enter its coordinates below. The pin is shown to job seekers.</p><div class="form-grid"><label>Latitude<input id="job-latitude" name="latitude" type="number" min="-90" max="90" step="any" value="<?= e((string)$job['latitude']) ?>" placeholder="e.g. 9.0320"></label><label>Longitude<input id="job-longitude" name="longitude" type="number" min="-180" max="180" step="any" value="<?= e((string)$job['longitude']) ?>" placeholder="e.g. 38.7469"></label></div><p class="field-hint" data-map-status role="status">Select the exact job location on the map or enter coordinates.</p><div class="location-map" data-location-map data-location-map-picker aria-label="Select the exact job location on the map"></div></div><label>Salary or project budget<input name="salary" minlength="2" maxlength="100" value="<?= e($job['salary']) ?>"></label><label>Role description *<textarea name="description" rows="7" maxlength="10000" required><?= e($job['description']) ?></textarea><span class="field-hint">Required. Include at least 5 letters or numbers; maximum 10,000.</span></label><label>Requirements<textarea name="requirements" rows="5" maxlength="5000"><?= e($job['requirements']) ?></textarea><span class="field-hint">Optional. If provided, use at least 5 letters or numbers (up to 5,000).</span></label><div class="inline-actions"><button class="button" type="submit" <?= !$jobId && !$companies ? 'disabled' : '' ?>><?= $jobId ? 'Save changes' : 'Create published job' ?></button><a class="button button-outline" href="<?= e(app_url('admin/jobs.php')) ?>">Cancel</a></div></form></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
