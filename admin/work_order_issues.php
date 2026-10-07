<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $issueId = filter_input(INPUT_POST, 'issue_id', FILTER_VALIDATE_INT);
    $status = $_POST['status'] ?? '';
    $response = trim((string)($_POST['admin_response'] ?? ''));
    $responseError = validate_user_message($response, 'Admin response', false, 5000);
    if (!$issueId || !in_array($status, ['open', 'in_review', 'resolved'], true)) {
        flash('error', 'Check the issue status and response length.');
    } elseif ($responseError !== null) {
        flash('error', $responseError);
    } elseif ($status === 'resolved' && $response === '') {
        flash('error', 'Add a meaningful response before resolving an issue.');
    } else {
        $update = $pdo->prepare('UPDATE work_order_issues SET status = ?, admin_response = ?, resolved_by = ?, resolved_at = ? WHERE id = ?');
        $update->execute([
            $status,
            $response ?: null,
            $status === 'resolved' ? current_user()['id'] : null,
            $status === 'resolved' ? date('Y-m-d H:i:s') : null,
            $issueId,
        ]);
        flash('success', 'Work order issue updated.');
    }
    redirect(app_url('admin/work_order_issues.php'));
}
$issues = $pdo->query('SELECT wi.*, w.id AS work_order_id, j.title, c.name AS company_name, worker.name AS worker_name, reporter.name AS reporter_name, reporter.role AS reporter_role, admin_user.name AS admin_name, i.invoice_number, i.amount AS invoice_amount, i.status AS invoice_status, i.payment_reference FROM work_order_issues wi JOIN work_orders w ON w.id = wi.work_order_id JOIN applications a ON a.id = w.application_id JOIN jobs j ON j.id = a.job_id JOIN companies c ON c.id = j.company_id JOIN users worker ON worker.id = a.seeker_id JOIN users reporter ON reporter.id = wi.raised_by LEFT JOIN users admin_user ON admin_user.id = wi.resolved_by LEFT JOIN invoices i ON i.work_order_id = w.id ORDER BY FIELD(wi.status, "open", "in_review", "resolved"), wi.created_at DESC')->fetchAll();
$pageTitle = 'Work order issues';
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-heading"><div><span class="eyebrow">ADMINISTRATION</span><h1>Work order issues</h1><p>Review payment failures and disagreements reported by workers or companies.</p></div></section>
<?php require __DIR__ . '/nav.php'; ?>
<?php foreach ($issues as $issue): ?><section class="content-card issue-card"><div class="section-heading compact"><div><span class="eyebrow">WO-<?= (int)$issue['work_order_id'] ?> · <?= e(ucfirst(str_replace('_', ' ', $issue['issue_type']))) ?></span><h2><?= e($issue['title']) ?></h2><p><?= e($issue['company_name']) ?> · Worker: <?= e($issue['worker_name']) ?></p></div><span class="badge badge-<?= e(application_status_class($issue['status'])) ?>"><?= e(ucwords(str_replace('_', ' ', $issue['status']))) ?></span></div><p><strong>Reported by <?= e($issue['reporter_name']) ?> (<?= e(ucfirst($issue['reporter_role'])) ?>)</strong> · <?= e(date('M j, Y g:i a', strtotime($issue['created_at']))) ?></p><?php if ($issue['invoice_number']): ?><p class="muted-copy">Invoice <?= e($issue['invoice_number']) ?> · ETB <?= e(number_format((float)$issue['invoice_amount'], 2)) ?> · <?= e(ucfirst($issue['invoice_status'])) ?><?= $issue['payment_reference'] ? ' · Reference: ' . e($issue['payment_reference']) : '' ?></p><?php endif; ?><p class="issue-description"><?= nl2br(e($issue['description'])) ?></p><?php if ($issue['resolved_at']): ?><p class="muted-copy">Resolved by <?= e($issue['admin_name']) ?> · <?= e(date('M j, Y g:i a', strtotime($issue['resolved_at']))) ?></p><?php endif; ?><form method="post" class="form-stack review-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="issue_id" value="<?= (int)$issue['id'] ?>"><label>Admin response <textarea data-user-message name="admin_response" rows="4" maxlength="5000" placeholder="Tell both parties how this issue is handled."><?= e($issue['admin_response']) ?></textarea><span class="field-hint">Optional unless resolving the issue. Use at least 5 letters or numbers when provided.</span></label><div class="form-grid"><label>Issue status<select name="status"><option value="open" <?= $issue['status'] === 'open' ? 'selected' : '' ?>>Open</option><option value="in_review" <?= $issue['status'] === 'in_review' ? 'selected' : '' ?>>In review</option><option value="resolved" <?= $issue['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option></select></label><button class="button" type="submit">Save resolution</button></div></form></section><?php endforeach; ?>
<?php if (!$issues): ?><section class="content-card"><div class="empty-state"><span class="empty-icon">✓</span><h3>No issues reported</h3><p>Payment problems and work-order disputes will appear here.</p></div></section><?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
