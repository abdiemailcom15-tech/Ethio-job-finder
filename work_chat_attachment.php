<?php
require_once __DIR__ . '/includes/auth.php';

$user = current_user();
if (!$user) {
    redirect(app_url('login.php'));
}
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$query = $pdo->prepare('SELECT m.attachment_filename, m.attachment_original_name, m.attachment_mime, w.id AS work_order_id, a.seeker_id, c.user_id AS company_user_id FROM work_order_chat_messages m JOIN work_orders w ON w.id = m.work_order_id JOIN applications a ON a.id = w.application_id JOIN jobs j ON j.id = a.job_id JOIN companies c ON c.id = j.company_id WHERE m.id = ?');
$query->execute([$id ?: 0]);
$file = $query->fetch();
if (!$file || !$file['attachment_filename'] || ($user['role'] !== 'admin' && (int)$user['id'] !== (int)$file['seeker_id'] && (int)$user['id'] !== (int)$file['company_user_id'])) {
    http_response_code(404);
    exit('Chat attachment not found.');
}
$path = __DIR__ . '/uploads/chat/' . basename($file['attachment_filename']);
if (!is_file($path)) {
    http_response_code(404);
    exit('Chat attachment is missing.');
}
header('Content-Type: ' . $file['attachment_mime']);
header('Content-Length: ' . (string)filesize($path));
header('Content-Disposition: attachment; filename="' . rawurlencode($file['attachment_original_name']) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
