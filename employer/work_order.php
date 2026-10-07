<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('employer');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$query = $pdo->prepare('SELECT w.*, a.id AS application_id, u.id AS seeker_id, u.name AS seeker_name, u.email, p.payout_provider, p.payout_provider_other, p.payout_account_name, p.payout_account_number, j.title, j.duration_days, j.deadline, c.name AS company_name, i.id AS invoice_id, i.invoice_number, i.amount AS invoice_amount, i.status AS invoice_status, i.payment_reference, i.payment_proof_filename, i.payment_proof_mime, i.issued_at, i.paid_at FROM work_orders w JOIN applications a ON a.id = w.application_id JOIN users u ON u.id = a.seeker_id LEFT JOIN seeker_profiles p ON p.user_id = u.id JOIN jobs j ON j.id = a.job_id JOIN companies c ON c.id = j.company_id LEFT JOIN invoices i ON i.work_order_id = w.id WHERE w.id = ? AND c.user_id = ?');
$query->execute([$id ?: 0, current_user()['id']]);
$work = $query->fetch();
if (!$work) {
    http_response_code(404);
    exit('Work order not found.');
}
$submissionsQuery = $pdo->prepare('SELECT * FROM work_submissions WHERE work_order_id = ? ORDER BY submitted_at DESC');
$submissionsQuery->execute([$work['id']]);
$submissions = $submissionsQuery->fetchAll();
$issuesQuery = $pdo->prepare('SELECT wi.*, u.name AS raiser_name FROM work_order_issues wi JOIN users u ON u.id = wi.raised_by WHERE wi.work_order_id = ? ORDER BY wi.created_at DESC');
$issuesQuery->execute([$work['id']]);
$issues = $issuesQuery->fetchAll();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'report_issue') {
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
            redirect(app_url('employer/work_order.php?id=' . (int)$work['id']));
        }
    } elseif ($action === 'review_submission') {
        $submissionId = filter_input(INPUT_POST, 'submission_id', FILTER_VALIDATE_INT);
        $decision = $_POST['decision'] ?? '';
        $note = trim((string)($_POST['review_note'] ?? ''));
        $noteError = validate_user_message($note, 'Review feedback', $decision === 'revision', 5000);
        $latest = $pdo->prepare("SELECT id FROM work_submissions WHERE work_order_id = ? AND status = 'submitted' ORDER BY submitted_at DESC LIMIT 1");
        $latest->execute([$work['id']]);
        if (!$submissionId || (int)$latest->fetchColumn() !== $submissionId || $work['status'] !== 'submitted') {
            $error = 'This submission is no longer awaiting review.';
        } elseif (!in_array($decision, ['approve', 'revision'], true)) {
            $error = 'Choose approve or request revision.';
        } elseif ($noteError !== null) {
            $error = $noteError;
        } else {
            try {
                $pdo->beginTransaction();
                if ($decision === 'revision') {
                    $pdo->prepare("UPDATE work_submissions SET status = 'revision_requested', review_note = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ?")
                        ->execute([$note, $submissionId]);
                    $pdo->prepare("UPDATE work_orders SET status = 'revision_requested' WHERE id = ?")->execute([$work['id']]);
                    $message = 'Revision requested. The worker can submit updated files.';
                } else {
                    $invoiceNumber = 'INV-' . date('Ymd') . '-WO' . (int)$work['id'] . '-' . strtoupper(bin2hex(random_bytes(2)));
                    $pdo->prepare("UPDATE work_submissions SET status = 'approved', review_note = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ?")
                        ->execute([$note ?: null, $submissionId]);
                    $pdo->prepare("INSERT INTO invoices (work_order_id, invoice_number, amount) VALUES (?, ?, ?)")
                        ->execute([$work['id'], $invoiceNumber, $work['agreed_amount']]);
                    $pdo->prepare("UPDATE work_orders SET status = 'invoiced' WHERE id = ?")->execute([$work['id']]);
                    $message = 'Work approved and invoice ' . $invoiceNumber . ' issued.';
                }
                $pdo->commit();
                flash('success', $message);
                redirect(app_url('employer/work_order.php?id=' . (int)$work['id']));
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
        }
    } elseif ($action === 'record_payment') {
        $reference = trim((string)($_POST['payment_reference'] ?? ''));
        $referenceError = validate_meaningful_text($reference, 'Payment reference', true, 120, 4);
        $proofFile = $_FILES['payment_proof'] ?? null;
        if (!$work['invoice_id'] || $work['invoice_status'] !== 'pending' || $work['status'] !== 'invoiced') {
            $error = 'There is no pending invoice to mark as paid.';
        } elseif ($referenceError !== null) {
            $error = $referenceError;
        } elseif ($proofFile && !in_array($proofFile['error'] ?? UPLOAD_ERR_NO_FILE, [UPLOAD_ERR_NO_FILE, UPLOAD_ERR_OK], true)) {
            $error = 'The payment screenshot could not be uploaded. Check the image size and try again.';
        } else {
            $savedProof = null;
            try {
                if ($proofFile && ($proofFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $savedProof = save_payment_proof($proofFile);
                }
                $pdo->beginTransaction();
                $paymentUpdate = $pdo->prepare("UPDATE invoices SET status = 'paid', payment_reference = ?, payment_proof_filename = ?, payment_proof_mime = ?, paid_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'pending'");
                $paymentUpdate->execute([$reference, $savedProof['stored_name'] ?? null, $savedProof['mime_type'] ?? null, $work['invoice_id']]);
                if ($paymentUpdate->rowCount() !== 1) {
                    $pdo->rollBack();
                    if ($savedProof !== null && is_file(__DIR__ . '/../uploads/payment-proofs/' . $savedProof['stored_name'])) {
                        unlink(__DIR__ . '/../uploads/payment-proofs/' . $savedProof['stored_name']);
                    }
                    $error = 'The invoice payment status could not be updated.';
                } else {
                    $pdo->prepare("UPDATE work_orders SET status = 'completed' WHERE id = ? AND status = 'invoiced'")->execute([$work['id']]);
                    $pdo->commit();
                    flash('success', 'Payment recorded. The work order is complete.');
                    redirect(app_url('employer/work_order.php?id=' . (int)$work['id']));
                }
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($savedProof !== null && is_file(__DIR__ . '/../uploads/payment-proofs/' . $savedProof['stored_name'])) {
                    unlink(__DIR__ . '/../uploads/payment-proofs/' . $savedProof['stored_name']);
                }
                throw $exception;
            } catch (RuntimeException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($savedProof !== null && is_file(__DIR__ . '/../uploads/payment-proofs/' . $savedProof['stored_name'])) {
                    unlink(__DIR__ . '/../uploads/payment-proofs/' . $savedProof['stored_name']);
                }
                $error = $exception->getMessage();
            }
        }
    } else {
        $error = 'Choose a valid work-order action.';
    }
}
$filesQuery = $pdo->prepare('SELECT * FROM work_submission_files WHERE submission_id = ?');
$pageTitle = 'Work order';
require __DIR__ . '/../includes/header.php';
?>
<a class="back-link" href="<?= e(app_url('employer/work_orders.php')) ?>">← Back to work orders</a>
<section class="dashboard-heading"><div><span class="eyebrow">WORK ORDER WO-<?= (int)$work['id'] ?></span><h1><?= e($work['title']) ?></h1><p>Worker: <?= e($work['seeker_name']) ?> · <a href="mailto:<?= e($work['email']) ?>"><?= e($work['email']) ?></a></p></div><span class="badge badge-<?= e(application_status_class($work['status'])) ?>"><?= e(ucwords(str_replace('_', ' ', $work['status']))) ?></span></section>
<p><a class="button button-outline" href="<?= e(app_url('work_chat.php?id=' . (int)$work['id'])) ?>">Open work-order chat</a></p>
<section class="content-card"><h2>Work order terms</h2><p><strong>Agreed amount:</strong> ETB <?= e(number_format((float)$work['agreed_amount'], 2)) ?></p><?php if ($work['duration_days']): ?><p><strong>Estimated duration:</strong> <?= (int)$work['duration_days'] ?> days</p><?php endif; ?><?php if ($work['deadline']): ?><p><strong>Job deadline:</strong> <?= e(date('M j, Y', strtotime($work['deadline']))) ?></p><?php endif; ?><?php if ($work['scope']): ?><p><?= nl2br(e($work['scope'])) ?></p><?php endif; ?><h3>Worker payout details</h3><?php if ($work['payout_provider'] && $work['payout_account_number']): ?><p><strong>Provider:</strong> <?= e($work['payout_provider'] === 'Other' ? $work['payout_provider_other'] : $work['payout_provider']) ?><br><strong>Account holder:</strong> <?= e($work['payout_account_name']) ?><br><strong>Account / Telebirr number:</strong> <?= e($work['payout_account_number']) ?></p><?php else: ?><p class="muted-copy">The worker has not added payout details yet. Contact them before arranging payment.</p><?php endif; ?></section>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<section class="content-card"><div class="section-heading compact"><div><span class="eyebrow">DELIVERY HISTORY</span><h2>Worker submissions</h2></div></div>
<?php foreach ($submissions as $submission): ?><article class="submission-entry"><div class="submission-heading"><strong>Submitted <?= e(date('M j, Y · g:i a', strtotime($submission['submitted_at']))) ?></strong><span class="badge badge-<?= e(application_status_class($submission['status'])) ?>"><?= e(ucwords(str_replace('_', ' ', $submission['status']))) ?></span></div><?php if ($submission['message']): ?><p><?= nl2br(e($submission['message'])) ?></p><?php endif; ?><ul class="file-list"><?php $filesQuery->execute([$submission['id']]); foreach ($filesQuery->fetchAll() as $file): ?><li><a href="<?= e(app_url('employer/download_work_file.php?id=' . (int)$file['id'])) ?>"><?= e($file['original_name']) ?></a></li><?php endforeach; ?></ul><?php if ($submission['review_note']): ?><div class="alert alert-warning"><strong>Your feedback:</strong><br><?= nl2br(e($submission['review_note'])) ?></div><?php endif; ?>
<?php if ($work['status'] === 'submitted' && $submission['status'] === 'submitted'): ?><form method="post" class="form-stack review-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="review_submission"><input type="hidden" name="submission_id" value="<?= (int)$submission['id'] ?>"><label>Feedback <span class="field-hint">Required when requesting a revision</span><textarea data-user-message name="review_note" rows="3" maxlength="5000"></textarea><span class="field-hint">Use at least 5 letters or numbers when providing feedback (up to 5,000).</span></label><div class="inline-actions"><button class="button button-small" name="decision" value="approve" type="submit">Approve work &amp; issue invoice</button><button class="button button-small button-outline" name="decision" value="revision" type="submit">Request revision</button></div></form><?php endif; ?></article><?php endforeach; ?>
<?php if (!$submissions): ?><div class="empty-state small"><p>The worker has not submitted work files yet.</p></div><?php endif; ?></section>
<?php if ($work['invoice_id']): ?><section class="content-card"><span class="eyebrow">INVOICE</span><h2><?= e($work['invoice_number']) ?></h2><p>Amount due: <strong>ETB <?= e(number_format((float)$work['invoice_amount'], 2)) ?></strong></p><p>Issued <?= e(date('M j, Y', strtotime($work['issued_at']))) ?> · <span class="badge badge-<?= e(application_status_class($work['invoice_status'])) ?>"><?= e(ucfirst($work['invoice_status'])) ?></span></p><?php if ($work['invoice_status'] === 'pending'): ?><form method="post" enctype="multipart/form-data" class="form-stack"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="record_payment"><label>External payment reference *<span class="field-hint">Record the bank transfer or other payment reference. No online payment is processed here.</span><input name="payment_reference" minlength="4" maxlength="120" required placeholder="Transaction ID or payment note"></label><label>Payment screenshot <span class="field-hint">Optional · JPG, PNG, GIF, or WEBP · maximum 5 MB</span><input type="file" name="payment_proof" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp"></label><button class="button" type="submit">Record payment &amp; complete work</button></form><?php else: ?><div class="alert alert-success">Payment recorded<?= $work['payment_reference'] ? ' · Reference: ' . e($work['payment_reference']) : '' ?><?= $work['paid_at'] ? ' · ' . e(date('M j, Y', strtotime($work['paid_at']))) : '' ?>.</div><?php if ($work['payment_proof_filename']): ?><p><a class="text-link" href="<?= e(app_url('payment_proof.php?id=' . (int)$work['invoice_id'])) ?>" target="_blank" rel="noopener noreferrer">View payment screenshot sent to worker ↗</a></p><?php endif; ?><?php endif; ?></section><?php endif; ?>
<section class="content-card"><div class="section-heading compact"><div><span class="eyebrow">ADMIN SUPPORT</span><h2>Report a payment or agreement issue</h2></div></div><p class="muted-copy">Report failed payments, disagreement about the scope, or another issue. An administrator can review and respond.</p><?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?><form method="post" class="form-stack"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="report_issue"><label>Issue type<select name="issue_type"><option value="payment">Payment problem</option><option value="agreement">Agreement / scope disagreement</option><option value="work_quality">Work quality</option><option value="other">Other</option></select></label><label>Describe the issue<textarea data-user-message name="description" rows="4" maxlength="5000" required></textarea><span class="field-hint">Explain what happened and when. Use at least 5 letters or numbers (up to 5,000).</span></label><button class="button button-outline" type="submit">Send to admin</button></form><?php foreach ($issues as $issue): ?><div class="submission-entry"><div class="submission-heading"><strong><?= e(ucfirst(str_replace('_', ' ', $issue['issue_type']))) ?> · <?= e(ucwords(str_replace('_', ' ', $issue['status']))) ?></strong><span class="muted-copy"><?= e(date('M j, Y', strtotime($issue['created_at']))) ?></span></div><p><?= nl2br(e($issue['description'])) ?></p><?php if ($issue['admin_response']): ?><div class="alert alert-success"><strong>Admin response:</strong><br><?= nl2br(e($issue['admin_response'])) ?></div><?php endif; ?></div><?php endforeach; ?></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
