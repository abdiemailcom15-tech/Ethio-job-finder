<?php
require_once __DIR__ . '/includes/auth.php';

$user = current_user();
if (!$user) {
    redirect(app_url('login.php'));
}
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$query = $pdo->prepare('SELECT i.payment_proof_filename, i.payment_proof_mime, a.seeker_id, c.user_id AS company_user_id FROM invoices i JOIN work_orders w ON w.id = i.work_order_id JOIN applications a ON a.id = w.application_id JOIN jobs j ON j.id = a.job_id JOIN companies c ON c.id = j.company_id WHERE i.id = ?');
$query->execute([$id ?: 0]);
$proof = $query->fetch();
if (!$proof || !$proof['payment_proof_filename'] || ($user['role'] !== 'admin' && (int)$user['id'] !== (int)$proof['seeker_id'] && (int)$user['id'] !== (int)$proof['company_user_id'])) {
    http_response_code(404);
    exit('Payment screenshot not found.');
}
$path = __DIR__ . '/uploads/payment-proofs/' . basename($proof['payment_proof_filename']);
if (!is_file($path)) {
    http_response_code(404);
    exit('Payment screenshot is missing.');
}
header('Content-Type: ' . $proof['payment_proof_mime']);
header('Content-Length: ' . (string)filesize($path));
header('Content-Disposition: inline; filename="payment-proof.' . rawurlencode(pathinfo($proof['payment_proof_filename'], PATHINFO_EXTENSION)) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
