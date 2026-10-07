<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('seeker');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(app_url('seeker/jobs.php'));
}
verify_csrf();
$jobId = filter_input(INPUT_POST, 'job_id', FILTER_VALIDATE_INT);
$coverLetter = trim((string)($_POST['cover_letter'] ?? ''));
if (!$jobId) {
    flash('error', 'The application could not be submitted. Please check your details.');
    redirect(app_url('seeker/jobs.php'));
}
$coverLetterError = validate_user_message($coverLetter, 'Cover letter', false, 5000);
if ($coverLetterError !== null) {
    flash('error', $coverLetterError);
    redirect(app_url('seeker/job_details.php?id=' . $jobId));
}
$jobQuery = $pdo->prepare("SELECT j.id FROM jobs j JOIN companies c ON c.id = j.company_id WHERE j.id = ? AND j.status = 'approved' AND c.status = 'approved' AND (j.deadline IS NULL OR j.deadline >= CURDATE())");
$jobQuery->execute([$jobId]);
if (!$jobQuery->fetch()) {
    flash('error', 'That opportunity is no longer accepting applications.');
    redirect(app_url('seeker/jobs.php'));
}
try {
    $pdo->prepare('INSERT INTO applications (job_id, seeker_id, cover_letter) VALUES (?, ?, ?)')
        ->execute([$jobId, current_user()['id'], $coverLetter ?: null]);
    flash('success', 'Your application has been sent. Good luck!');
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        flash('error', 'You have already applied to this opportunity.');
    } else {
        throw $exception;
    }
}
redirect(app_url('seeker/applications.php'));
