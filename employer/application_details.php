<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('employer');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$query = $pdo->prepare('SELECT a.*, u.name AS seeker_name, u.email, p.phone, p.location, p.skills, p.bio, p.cv_filename, j.title, j.id AS job_id FROM applications a JOIN users u ON u.id = a.seeker_id LEFT JOIN seeker_profiles p ON p.user_id = u.id JOIN jobs j ON j.id = a.job_id JOIN companies c ON c.id = j.company_id WHERE a.id = ? AND c.user_id = ?');
$query->execute([$id ?: 0, current_user()['id']]);
$application = $query->fetch();
if (!$application) {
    http_response_code(404);
    exit('Application not found.');
}
$workOrderQuery = $pdo->prepare('SELECT id, status FROM work_orders WHERE application_id = ?');
$workOrderQuery->execute([$application['id']]);
$workOrder = $workOrderQuery->fetch();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'start_work') {
        $amount = filter_var($_POST['agreed_amount'] ?? '', FILTER_VALIDATE_FLOAT);
        $scope = trim((string)($_POST['scope'] ?? ''));
        $scopeError = validate_user_message($scope, 'Work order scope', false, 5000);
        if ($amount === false || $amount <= 0 || $amount > 9999999999.99) {
            flash('error', 'Enter a valid agreed amount greater than zero.');
        } elseif ($scopeError !== null) {
            flash('error', $scopeError);
        } elseif (!in_array($application['status'], ['submitted', 'reviewing', 'accepted'], true) || $workOrder) {
            flash('error', 'This application cannot be accepted or already has a work order.');
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE applications SET status = 'accepted' WHERE id = ?")->execute([$application['id']]);
                $pdo->prepare("INSERT INTO work_orders (application_id, agreed_amount, scope) VALUES (?, ?, ?)")
                    ->execute([$application['id'], number_format((float)$amount, 2, '.', ''), $scope ?: null]);
                $newWorkOrderId = (int)$pdo->lastInsertId();
                $pdo->commit();
                flash('success', 'Applicant accepted. The work order is ready for the worker.');
                redirect(app_url('employer/work_order.php?id=' . $newWorkOrderId));
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
        }
    } elseif ($action === 'application_status') {
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['reviewing', 'rejected'], true) || !in_array($application['status'], ['submitted', 'reviewing'], true)) {
            flash('error', 'This application can no longer be changed to that status.');
        } else {
            $pdo->prepare('UPDATE applications SET status = ? WHERE id = ?')->execute([$status, $application['id']]);
            flash('success', 'Application status updated.');
        }
    } else {
        flash('error', 'Choose a valid application action.');
    }
    redirect(app_url('employer/application_details.php?id=' . (int)$application['id']));
}
$pageTitle = 'Review applicant';
require __DIR__ . '/../includes/header.php';
?>
<a class="back-link" href="<?= e(app_url('employer/applicants.php')) ?>">← Back to applicants</a>
<section class="detail-layout"><article class="content-card applicant-card"><span class="eyebrow">CANDIDATE PROFILE</span><h1><?= e($application['seeker_name']) ?></h1><p class="company-name">Applied for <?= e($application['title']) ?></p><div class="profile-facts"><p><strong>Email</strong><a href="mailto:<?= e($application['email']) ?>"><?= e($application['email']) ?></a></p><p><strong>Phone</strong><?= e($application['phone'] ?: 'Not provided') ?></p><p><strong>Location</strong><?= e($application['location'] ?: 'Not provided') ?></p></div><?php if ($application['skills']): ?><div class="detail-copy"><h2>Skills</h2><p><?= nl2br(e($application['skills'])) ?></p></div><?php endif; ?><?php if ($application['bio']): ?><div class="detail-copy"><h2>About</h2><p><?= nl2br(e($application['bio'])) ?></p></div><?php endif; ?><?php if ($application['cover_letter']): ?><div class="detail-copy"><h2>Cover letter</h2><p><?= nl2br(e($application['cover_letter'])) ?></p></div><?php endif; ?><?php if ($application['cv_filename']): ?><a class="button button-outline" href="<?= e(app_url('employer/download_cv.php?id=' . (int)$application['id'])) ?>">Download CV <span>↓</span></a><?php endif; ?></article>
<aside class="content-card apply-card"><span class="eyebrow">APPLICATION STATUS</span><h2><?= e(ucfirst($application['status'])) ?></h2><p>Applied <?= e(date('F j, Y', strtotime($application['applied_at']))) ?>.</p>
<?php if ($workOrder): ?><a class="button button-full" href="<?= e(app_url('employer/work_order.php?id=' . (int)$workOrder['id'])) ?>">Open work order →</a>
<?php elseif (in_array($application['status'], ['submitted', 'reviewing', 'accepted'], true)): ?>
<form method="post" class="form-stack"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="start_work"><label>Agreed project amount *<input type="number" name="agreed_amount" min="0.01" max="9999999999.99" step="0.01" required placeholder="e.g. 500.00"></label><label>Work order scope <span class="field-hint">Optional notes for the worker</span><textarea data-user-message name="scope" rows="4" maxlength="5000" placeholder="Describe the agreed deliverables and expectations."></textarea><span class="field-hint">Optional. If provided, enter a clear note or a short reply (up to 5,000).</span></label><button class="button button-full" type="submit">Accept &amp; create work order</button></form>
<?php endif; ?>
<?php if (in_array($application['status'], ['submitted', 'reviewing'], true)): ?><form method="post" class="form-stack"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="application_status"><label>Application status<select name="status"><option value="reviewing" <?= $application['status'] === 'reviewing' ? 'selected' : '' ?>>Reviewing</option><option value="rejected">Reject application</option></select></label><button class="button button-outline button-full" type="submit">Update application</button></form><?php endif; ?></aside></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
