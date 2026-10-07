<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('seeker');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$query = $pdo->prepare('SELECT f.stored_name, f.original_name, f.mime_type FROM work_submission_files f JOIN work_submissions s ON s.id = f.submission_id JOIN work_orders w ON w.id = s.work_order_id JOIN applications a ON a.id = w.application_id WHERE f.id = ? AND a.seeker_id = ?');
$query->execute([$id ?: 0, current_user()['id']]);
$file = $query->fetch();
if (!$file) {
    http_response_code(404);
    exit('Work file not found.');
}
$path = dirname(__DIR__) . '/uploads/work/' . basename($file['stored_name']);
if (!is_file($path)) {
    http_response_code(404);
    exit('Work file is missing.');
}
header('Content-Type: ' . $file['mime_type']);
header('Content-Length: ' . (string)filesize($path));
$inline = ($_GET['view'] ?? '') === '1' && (str_starts_with($file['mime_type'], 'image/') || str_starts_with($file['mime_type'], 'video/'));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . rawurlencode($file['original_name']) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
