<?php
require_once __DIR__ . '/includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $user = current_user();
    redirect($user ? role_home($user['role']) : app_url('login.php'));
}
verify_csrf();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
session_start();
flash('success', 'You have been signed out.');
redirect(app_url('index.php'));
