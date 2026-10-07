<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$exists = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
if ($exists > 0) {
    fwrite(STDERR, "An administrator already exists. This initial setup command can only be used once.\n");
    exit(1);
}
$name = trim((string)readline('Admin name: '));
$email = strtolower(trim((string)readline('Admin email: ')));
$password = (string)readline('Admin password (minimum 12 characters): ');
$nameError = validate_person_name($name, 'Admin name');
if ($nameError !== null || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12 || strlen($password) > 4096) {
    fwrite(STDERR, ($nameError ?? 'Enter a valid email and password of 12–4,096 characters.') . "\n");
    exit(1);
}
$insert = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
$insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
fwrite(STDOUT, "Administrator created. Sign in at /login.php.\n");
