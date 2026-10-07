<?php
require_once __DIR__ . '/includes/auth.php';

$user = current_user();
if (!$user) {
    redirect(app_url('login.php'));
}
$workOrderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$workQuery = $pdo->prepare('SELECT w.id, w.status, j.title, a.seeker_id, worker.name AS worker_name, c.user_id AS company_user_id, c.name AS company_name FROM work_orders w JOIN applications a ON a.id = w.application_id JOIN users worker ON worker.id = a.seeker_id JOIN jobs j ON j.id = a.job_id JOIN companies c ON c.id = j.company_id WHERE w.id = ?');
$workQuery->execute([$workOrderId ?: 0]);
$work = $workQuery->fetch();
if (!$work) {
    http_response_code(404);
    exit('Work-order conversation not found.');
}
if ($user['role'] !== 'admin' && (int)$user['id'] !== (int)$work['seeker_id'] && (int)$user['id'] !== (int)$work['company_user_id']) {
    http_response_code(403);
    exit('You do not have permission to view this conversation.');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $message = trim((string)($_POST['message'] ?? ''));
    $messageError = validate_user_message($message, 'Message', false, 5000);
    $file = $_FILES['attachment'] ?? null;
    if ($messageError !== null) {
        $error = $messageError;
    } elseif ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = 'The attachment could not be uploaded. Check the file size and try again.';
    } elseif ($message === '' && (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)) {
        $error = 'Write a message or attach a file.';
    } else {
        $saved = null;
        try {
            if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $saved = save_chat_attachment($file);
            }
            $insert = $pdo->prepare('INSERT INTO work_order_chat_messages (work_order_id, sender_id, message, attachment_filename, attachment_original_name, attachment_mime) VALUES (?, ?, ?, ?, ?, ?)');
            $insert->execute([
                $work['id'],
                $user['id'],
                $message ?: null,
                $saved['stored_name'] ?? null,
                $saved['original_name'] ?? null,
                $saved['mime_type'] ?? null,
            ]);
            redirect(app_url('work_chat.php?id=' . (int)$work['id']));
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        } catch (PDOException $exception) {
            if ($saved !== null) {
                $path = __DIR__ . '/uploads/chat/' . $saved['stored_name'];
                if (is_file($path)) {
                    unlink($path);
                }
            }
            throw $exception;
        }
    }
}

$messagesQuery = $pdo->prepare('SELECT m.*, u.name AS sender_name, u.role AS sender_role FROM work_order_chat_messages m JOIN users u ON u.id = m.sender_id WHERE m.work_order_id = ? ORDER BY m.id DESC LIMIT 100');
$messagesQuery->execute([$work['id']]);
$messages = array_reverse($messagesQuery->fetchAll());
$pageTitle = 'Work-order chat';
require __DIR__ . '/includes/header.php';
?>
<a class="back-link" href="<?= e(role_home($user['role'])) ?>">← Back to dashboard</a>
<section class="dashboard-heading"><div><span class="eyebrow">WORK ORDER WO-<?= (int)$work['id'] ?></span><h1>Conversation</h1><p><?= e($work['title']) ?> · Worker: <?= e($work['worker_name']) ?> · Company: <?= e($work['company_name']) ?></p></div><span class="badge badge-<?= e(application_status_class($work['status'])) ?>"><?= e(ucwords(str_replace('_', ' ', $work['status']))) ?></span></section>
<section class="content-card chat-card">
    <div class="section-heading compact"><div><span class="eyebrow">WORKER · COMPANY · ADMIN</span><h2>Work-order messages</h2><p>Administrators can join this conversation to help resolve questions.</p></div><a class="button button-outline button-small" href="<?= e(app_url('work_chat.php?id=' . (int)$work['id'])) ?>">Refresh</a></div>
    <div class="chat-messages" aria-live="polite">
        <?php if (!$messages): ?><p class="empty-state small">No messages yet. Start the conversation below.</p><?php endif; ?>
        <?php foreach ($messages as $item): ?><article class="chat-message <?= (int)$item['sender_id'] === (int)$user['id'] ? 'chat-message-own' : '' ?>"><div class="chat-message-heading"><strong><?= e($item['sender_name']) ?></strong><span class="badge badge-muted"><?= e(ucfirst($item['sender_role'])) ?></span><time><?= e(date('M j, Y g:i a', strtotime($item['created_at']))) ?></time></div><?php if ($item['message']): ?><p><?= nl2br(e($item['message'])) ?></p><?php endif; ?><?php if ($item['attachment_filename']): ?><a class="text-link" href="<?= e(app_url('work_chat_attachment.php?id=' . (int)$item['id'])) ?>">Download <?= e($item['attachment_original_name']) ?></a><?php endif; ?></article><?php endforeach; ?>
    </div>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="form-stack chat-compose"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label>Message<textarea data-user-message name="message" rows="3" maxlength="5000" placeholder="Write a message to the worker, company, or admin."><?= e($_POST['message'] ?? '') ?></textarea></label><label>Attachment <span class="field-hint">Optional · up to 25 MB · supported work documents, photos, or videos</span><input type="file" name="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.zip,.txt,.jpg,.jpeg,.png,.gif,.webp,.mp4,.webm,.mov,.avi,.mkv,.mpeg"></label><button class="button" type="submit">Send message</button></form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
