<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('employer');
$companyQuery = $pdo->prepare('SELECT * FROM companies WHERE user_id = ?');
$companyQuery->execute([current_user()['id']]);
$company = $companyQuery->fetch();
if (!$company) {
    http_response_code(500);
    exit('Company profile not found.');
}
$jobId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$job = ['title' => '', 'category' => '', 'location' => '', 'latitude' => '', 'longitude' => '', 'job_type' => 'Full-time', 'salary' => '', 'description' => '', 'requirements' => '', 'duration_days' => '', 'deadline' => ''];
if ($jobId) {
    $jobQuery = $pdo->prepare('SELECT * FROM jobs WHERE id = ? AND company_id = ?');
    $jobQuery->execute([$jobId, $company['id']]);
    $existing = $jobQuery->fetch();
    if (!$existing) {
        http_response_code(404);
        exit('Job not found.');
    }
    $job = $existing;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($company['status'] === 'rejected') {
        $error = 'Your company profile was rejected. Please contact an administrator before posting jobs.';
    } else {
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
        } elseif (!in_array($values[3], $types, true) || ($values[7] !== '' && ($duration === false || $duration < 1 || $duration > 3650)) || !$deadlineValid) {
            $error = 'Complete the required fields and check the maximum lengths.';
        } else {
            $values[7] = $duration === null || $duration === false ? null : $duration;
            $values[8] = $values[8] ?: null;
            if ($jobId) {
                $save = $pdo->prepare("UPDATE jobs SET title = ?, category = ?, location = ?, job_type = ?, salary = ?, description = ?, requirements = ?, duration_days = ?, deadline = ?, latitude = ?, longitude = ?, status = 'pending' WHERE id = ? AND company_id = ?");
                $save->execute([...$values, $coordinates['latitude'], $coordinates['longitude'], $jobId, $company['id']]);
                flash('success', 'Your job was updated and sent for admin approval.');
            } else {
                $save = $pdo->prepare('INSERT INTO jobs (title, category, location, job_type, salary, description, requirements, duration_days, deadline, latitude, longitude, company_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $save->execute([...$values, $coordinates['latitude'], $coordinates['longitude'], $company['id']]);
                flash('success', 'Your job was posted and is waiting for admin approval.');
            }
            redirect(app_url('employer/manage_jobs.php'));
        }
        $job = array_combine(['title', 'category', 'location', 'job_type', 'salary', 'description', 'requirements', 'duration_days', 'deadline'], $values);
        $job['latitude'] = trim((string)($_POST['latitude'] ?? ''));
        $job['longitude'] = trim((string)($_POST['longitude'] ?? ''));
    }
}
$pageTitle = $jobId ? 'Edit job' : 'Post a job';
$includeMapAssets = true;
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-heading"><div><span class="eyebrow">EMPLOYER SPACE</span><h1><?= $jobId ? 'Edit your job' : 'Post an opportunity' ?></h1><p>Clear, thoughtful listings help the right people find you.</p></div></section>
<?php require __DIR__ . '/nav.php'; ?>
<?php if ($company['status'] === 'pending'): ?><div class="alert alert-warning">Your company profile is awaiting review. You can submit jobs now; they will become visible after the company and job are approved.</div><?php elseif ($company['status'] === 'rejected'): ?><div class="alert alert-warning">Your company profile was rejected. <a href="<?= e(app_url('employer/profile.php')) ?>">Review your profile</a> and contact an administrator before posting jobs.</div><?php endif; ?>
<section class="content-card profile-card"><?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?><form method="post" class="form-stack"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="form-grid"><label>Job title *<input name="title" minlength="3" maxlength="180" required value="<?= e($job['title']) ?>"></label><label>Category *<input name="category" minlength="2" maxlength="100" required placeholder="e.g. Engineering, Design" value="<?= e($job['category']) ?>"></label><label>Location *<input name="location" minlength="2" maxlength="120" required placeholder="City, country or remote" value="<?= e($job['location']) ?>"></label><label>Job type *<select name="job_type"><?php foreach (['Full-time', 'Part-time', 'Contract', 'Internship', 'Remote'] as $type): ?><option value="<?= e($type) ?>" <?= $job['job_type'] === $type ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?></select></label><label>Estimated duration in days<input type="number" name="duration_days" min="1" max="3650" value="<?= e((string)$job['duration_days']) ?>" placeholder="e.g. 30"></label><label>Application / job deadline<input type="date" name="deadline" min="<?= e(date('Y-m-d')) ?>" value="<?= e($job['deadline']) ?>"></label></div><p class="field-hint">Duration and deadline are optional. Deadline is the last date to apply; duration is the estimated time to complete the work.</p><div class="location-picker"><span class="field-label">Exact job location <span class="field-hint">(optional for remote jobs)</span></span><p class="field-hint">Click the map to drop a precise pin, or enter its coordinates below. The pin is shown to job seekers.</p><div class="form-grid"><label>Latitude<input id="job-latitude" name="latitude" type="number" min="-90" max="90" step="any" value="<?= e((string)$job['latitude']) ?>" placeholder="e.g. 9.0320"></label><label>Longitude<input id="job-longitude" name="longitude" type="number" min="-180" max="180" step="any" value="<?= e((string)$job['longitude']) ?>" placeholder="e.g. 38.7469"></label></div><p class="field-hint" data-map-status role="status">Select the exact job location on the map or enter coordinates.</p><div class="location-map" data-location-map data-location-map-picker aria-label="Select the exact job location on the map"></div></div><label>Salary or project budget<input name="salary" minlength="2" maxlength="100" placeholder="e.g. ETB 60,000 – 75,000" value="<?= e($job['salary']) ?>"></label><label>Role description *<textarea name="description" rows="7" maxlength="10000" required><?= e($job['description']) ?></textarea><span class="field-hint">Required. Include at least 5 letters or numbers; maximum 10,000.</span></label><label>Requirements<textarea name="requirements" rows="5" maxlength="5000" placeholder="Experience, skills, or qualifications"><?= e($job['requirements']) ?></textarea><span class="field-hint">Optional. If provided, use at least 5 letters or numbers (up to 5,000).</span></label><button class="button" type="submit" <?= $company['status'] === 'rejected' ? 'disabled' : '' ?>><?= $jobId ? 'Save changes' : 'Submit for approval' ?> <span>→</span></button></form></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
