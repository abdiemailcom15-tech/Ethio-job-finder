<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('seeker');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$query = $pdo->prepare('SELECT w.*, j.title, c.name AS company_name, c.location AS company_location, i.id AS invoice_id, i.invoice_number, i.amount AS invoice_amount, i.status AS invoice_status, i.payment_reference, i.payment_proof_filename, i.issued_at, i.paid_at FROM work_orders w JOIN applications a ON a.id = w.application_id JOIN jobs j ON j.id = a.job_id JOIN companies c ON c.id = j.company_id LEFT JOIN invoices i ON i.work_order_id = w.id WHERE w.id = ? AND a.seeker_id = ?');
$query->execute([$id ?: 0, current_user()['id']]);
$work = $query->fetch();
if (!$work) {
    http_response_code(404);
    exit('Work order not found.');
}
$submissionsQuery = $pdo->prepare('SELECT * FROM work_submissions WHERE work_order_id = ? ORDER BY submitted_at DESC');
$submissionsQuery->execute([$work['id']]);
$submissions = $submissionsQuery->fetchAll();
$filesQuery = $pdo->prepare('SELECT * FROM work_submission_files WHERE submission_id = ?');
$issuesQuery = $pdo->prepare('SELECT wi.*, u.name AS raiser_name FROM work_order_issues wi JOIN users u ON u.id = wi.raised_by WHERE wi.work_order_id = ? ORDER BY wi.created_at DESC');
$issuesQuery->execute([$work['id']]);
$issues = $issuesQuery->fetchAll();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'report_issue') {
        $issueType = $_POST['issue_type'] ?? '';
        $description = trim((string)($_POST['description'] ?? ''));
        $descriptionError = validate_user_message($description, 'Issue description', true, 5000);
        if (!in_array($issueType, ['payment', 'work_quality', 'agreement', 'other'], true)) {
            $error = 'Choose a valid issue type.';
        } elseif ($descriptionError !== null) {
            $error = $descriptionError;
        } else {
            $pdo->prepare('INSERT INTO work_order_issues (work_order_id, raised_by, issue_type, description) VALUES (?, ?, ?, ?)')
                ->execute([$work['id'], current_user()['id'], $issueType, $description]);
            flash('success', 'Your issue was sent to the administrator for review.');
            redirect(app_url('seeker/work_order.php?id=' . (int)$work['id']));
        }
    } elseif (($_POST['action'] ?? '') === 'submit_work') {
    $message = trim((string)($_POST['message'] ?? ''));
    $messageError = validate_user_message($message, 'Submission notes', false, 5000);
    $upload = $_FILES['work_files'] ?? null;
    if (!in_array($work['status'], ['in_progress', 'revision_requested'], true)) {
        $error = 'This work order is not accepting a new submission.';
    } elseif ($messageError !== null) {
        $error = $messageError;
    } elseif (!$upload || !isset($upload['name']) || !is_array($upload['name'])) {
        $error = 'Choose at least one work file to submit.';
    } else {
        $files = [];
        for ($index = 0; $index < count($upload['name']); $index++) {
            if ($upload['error'][$index] !== UPLOAD_ERR_NO_FILE) {
                $files[] = [
                    'name' => $upload['name'][$index],
                    'type' => $upload['type'][$index],
                    'tmp_name' => $upload['tmp_name'][$index],
                    'error' => $upload['error'][$index],
                    'size' => $upload['size'][$index],
                ];
            }
        }
        if (count($files) < 1 || count($files) > 5) {
            $error = 'Choose between 1 and 5 work files, photos, or videos.';
        } elseif (array_sum(array_column($files, 'size')) > 30 * 1024 * 1024) {
            $error = 'The combined size of all work files, photos, and videos must be 30 MB or less.';
        } else {
            $savedFiles = [];
            try {
                $pdo->beginTransaction();
                $pdo->prepare("INSERT INTO work_submissions (work_order_id, message) VALUES (?, ?)")
                    ->execute([$work['id'], $message ?: null]);
                $submissionId = (int)$pdo->lastInsertId();
                foreach ($files as $file) {
                    $saved = save_work_file($file);
                    $savedFiles[] = $saved['stored_name'];
                    $pdo->prepare('INSERT INTO work_submission_files (submission_id, stored_name, original_name, mime_type) VALUES (?, ?, ?, ?)')
                        ->execute([$submissionId, $saved['stored_name'], $saved['original_name'], $saved['mime_type']]);
                }
                $workUpdate = $pdo->prepare("UPDATE work_orders SET status = 'submitted' WHERE id = ? AND status IN ('in_progress', 'revision_requested')");
                $workUpdate->execute([$work['id']]);
                if ($workUpdate->rowCount() !== 1) {
                    throw new RuntimeException('The work order status changed. Refresh the page and try again.');
                }
                $pdo->commit();
                flash('success', 'Your work files have been submitted for company review.');
                redirect(app_url('seeker/work_order.php?id=' . (int)$work['id']));
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                foreach ($savedFiles as $filename) {
                    $path = dirname(__DIR__) . '/uploads/work/' . $filename;
                    if (is_file($path)) {
                        unlink($path);
                    }
                }
                throw $exception;
            } catch (RuntimeException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                foreach ($savedFiles as $filename) {
                    $path = dirname(__DIR__) . '/uploads/work/' . $filename;
                    if (is_file($path)) {
                        unlink($path);
                    }
                }
                $error = $exception->getMessage();
            }
        }
        }
    } else {
        $error = 'Choose a valid work-order action.';
    }
}
$pageTitle = 'Work order';
require __DIR__ . '/../includes/header.php';
?>
<a class="back-link" href="<?= e(app_url('seeker/work_orders.php')) ?>">← Back to my work</a>
<section class="dashboard-heading"><div><span class="eyebrow">WORK ORDER WO-<?= (int)$work['id'] ?></span><h1><?= e($work['title']) ?></h1><p><?= e($work['company_name']) ?> · Agreed amount ETB <?= e(number_format((float)$work['agreed_amount'], 2)) ?></p></div><span class="badge badge-<?= e(application_status_class($work['status'])) ?>"><?= e(ucwords(str_replace('_', ' ', $work['status']))) ?></span></section>
<p><a class="button button-outline" href="<?= e(app_url('work_chat.php?id=' . (int)$work['id'])) ?>">Open work-order chat</a></p>
<section class="content-card profile-card">
    <?php if ($work['scope']): ?><div class="detail-copy"><h2>Work order scope</h2><p><?= nl2br(e($work['scope'])) ?></p></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if (in_array($work['status'], ['in_progress', 'revision_requested'], true)): ?>
        <?php if ($work['status'] === 'revision_requested' && $submissions): ?><div class="alert alert-warning">The company requested revisions. Review its feedback below, update your work, then submit the revised files.</div><?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="form-stack"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="submit_work"><label>Submission notes <span class="field-hint">Optional</span><textarea data-user-message name="message" rows="4" maxlength="5000" placeholder="Summarize your completed work or explain the revision."></textarea><span class="field-hint">Optional. If provided, use at least 5 letters or numbers (up to 5,000).</span></label><label>Work files, photos, or videos * <span class="field-hint">Choose 1–5 items, max 25 MB each. PDF, Word, Excel, ZIP, TXT, JPG, PNG, GIF, WEBP, MP4, WEBM, MOV, AVI, or MKV.</span><input type="file" name="work_files[]" multiple required accept=".pdf,.doc,.docx,.xls,.xlsx,.zip,.txt,.jpg,.jpeg,.png,.gif,.webp,.mp4,.webm,.mov,.avi,.mkv,.mpeg"></label><button class="button" type="submit">Submit work for review →</button></form>
    <?php elseif ($work['status'] === 'submitted'): ?><div class="alert alert-warning">Your files are with the company for review.</div>
    <?php elseif (in_array($work['status'], ['invoiced', 'paid', 'completed'], true)): ?><div class="alert alert-success">Your work has been approved. <?= $work['invoice_number'] ? 'Invoice ' . e($work['invoice_number']) . ' · ' . e(ucfirst($work['invoice_status'])) . '.' : '' ?></div><?php endif; ?>
</section>
<?php if ($work['invoice_id']): ?><section class="content-card"><span class="eyebrow">INVOICE</span><h2><?= e($work['invoice_number']) ?></h2><p>Amount: <strong>ETB <?= e(number_format((float)$work['invoice_amount'], 2)) ?></strong></p><p>Status: <span class="badge badge-<?= e(application_status_class($work['invoice_status'])) ?>"><?= e(ucfirst($work['invoice_status'])) ?></span></p><?php if ($work['paid_at']): ?><p>Payment reference: <?= e($work['payment_reference']) ?> · Paid <?= e(date('M j, Y', strtotime($work['paid_at']))) ?></p><?php if ($work['payment_proof_filename']): ?><p><a class="text-link" href="<?= e(app_url('payment_proof.php?id=' . (int)$work['invoice_id'])) ?>" target="_blank" rel="noopener noreferrer">View company payment screenshot ↗</a></p><?php endif; ?><?php endif; ?></section><?php endif; ?>
<section class="content-card"><div class="section-heading compact"><div><span class="eyebrow">ADMIN SUPPORT</span><h2>Report a payment or agreement issue</h2></div></div><p class="muted-copy">Report failed payments, disagreement about the scope, or another issue. An administrator can review and respond.</p><?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?><form method="post" class="form-stack"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="report_issue"><label>Issue type<select name="issue_type"><option value="payment">Payment problem</option><option value="agreement">Agreement / scope disagreement</option><option value="work_quality">Work quality</option><option value="other">Other</option></select></label><label>Describe the issue<textarea data-user-message name="description" rows="4" maxlength="5000" required></textarea><span class="field-hint">Explain what happened and when. Use at least 5 letters or numbers (up to 5,000).</span></label><button class="button button-outline" type="submit">Send to admin</button></form><?php foreach ($issues as $issue): ?><div class="submission-entry"><div class="submission-heading"><strong><?= e(ucfirst(str_replace('_', ' ', $issue['issue_type']))) ?> · <?= e(ucwords(str_replace('_', ' ', $issue['status']))) ?></strong><span class="muted-copy"><?= e(date('M j, Y', strtotime($issue['created_at']))) ?></span></div><p><?= nl2br(e($issue['description'])) ?></p><?php if ($issue['admin_response']): ?><div class="alert alert-success"><strong>Admin response:</strong><br><?= nl2br(e($issue['admin_response'])) ?></div><?php endif; ?></div><?php endforeach; ?></section>
<section class="content-card"><div class="section-heading compact"><div><span class="eyebrow">DELIVERY HISTORY</span><h2>Submissions &amp; company feedback</h2></div></div>
<?php foreach ($submissions as $submission): ?><article class="submission-entry"><div class="submission-heading"><strong>Submitted <?= e(date('M j, Y · g:i a', strtotime($submission['submitted_at']))) ?></strong><span class="badge badge-<?= e(application_status_class($submission['status'])) ?>"><?= e(ucwords(str_replace('_', ' ', $submission['status']))) ?></span></div><?php if ($submission['message']): ?><p><?= nl2br(e($submission['message'])) ?></p><?php endif; ?><?php if ($submission['review_note']): ?><div class="alert alert-warning"><strong>Company feedback:</strong><br><?= nl2br(e($submission['review_note'])) ?></div><?php endif; ?><ul class="file-list"><?php $filesQuery->execute([$submission['id']]); foreach ($filesQuery->fetchAll() as $file): ?><li><a href="<?= e(app_url('seeker/download_work_file.php?id=' . (int)$file['id'])) ?>"><?= e($file['original_name']) ?></a></li><?php endforeach; ?></ul></article><?php endforeach; ?>
<?php if (!$submissions): ?><div class="empty-state small"><p>No work files submitted yet.</p></div><?php endif; ?></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
