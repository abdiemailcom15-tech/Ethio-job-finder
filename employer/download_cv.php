<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('employer');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$query = $pdo->prepare('SELECT p.cv_filename, u.name FROM applications a JOIN users u ON u.id = a.seeker_id JOIN seeker_profiles p ON p.user_id = u.id JOIN jobs j ON j.id = a.job_id JOIN companies c ON c.id = j.company_id WHERE a.id = ? AND c.user_id = ?');
$query->execute([$id ?: 0, current_user()['id']]);
$candidate = $query->fetch();
if (!$candidate || !$candidate['cv_filename']) {
    http_response_code(404);
    exit('CV not found.');
}
$path = dirname(__DIR__) . '/uploads/cvs/' . basename($candidate['cv_filename']);
if (!is_file($path)) {
    http_response_code(404);
    exit('CV file is missing.');
}
$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = ['pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'][$extension] ?? 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));
header('Content-Disposition: attachment; filename="candidate-cv.' . $extension . '"');
readfile($path);
