<?php
require_once __DIR__ . '/includes/auth.php';
if (current_user()) {
    redirect(role_home(current_user()['role']));
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    if (filter_var($email, FILTER_VALIDATE_EMAIL) && text_length($email) <= 190) {
        $query = $pdo->prepare('SELECT id, name, email FROM users WHERE email = ? LIMIT 1');
        $query->execute([$email]);
        $account = $query->fetch();
        if ($account) {
            $resetQuery = $pdo->prepare('SELECT requested_at FROM password_reset_tokens WHERE user_id = ?');
            $resetQuery->execute([$account['id']]);
            $previousRequest = $resetQuery->fetchColumn();
            if ($previousRequest === false || strtotime((string)$previousRequest) <= time() - 60) {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $save = $pdo->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, requested_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW()) ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), expires_at = VALUES(expires_at), requested_at = VALUES(requested_at)');
                $save->execute([$account['id'], $tokenHash]);
                $resetUrl = app_url('reset_password.php?token=' . rawurlencode($token));
                if (!send_password_reset_email($account['email'], $account['name'], $resetUrl)) {
                    $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ? AND token_hash = ?')
                        ->execute([$account['id'], $tokenHash]);
                }
            }
        }
    }
    $message = 'If an account matches that email, a password-reset link will be sent. The link expires after one hour. Check your inbox and spam folder.';
}

$pageTitle = 'Forgot password';
require __DIR__ . '/includes/header.php';
?>
<section class="auth-layout">
    <div class="auth-aside"><span class="eyebrow">ACCOUNT RECOVERY</span><h1>Get back to your work.</h1><p>We will email you a secure link to choose a new password.</p></div>
    <div class="auth-card"><div class="form-heading"><span class="eyebrow">PASSWORD RESET</span><h2>Forgot your password?</h2><p>Enter the email address associated with your worker, company, or admin account.</p></div>
        <?php if ($message): ?><div class="alert alert-success" role="status"><?= e($message) ?></div><?php endif; ?>
        <form method="post" class="form-stack">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label>Email address<input type="email" name="email" maxlength="190" autocomplete="email" required value="<?= e($_POST['email'] ?? '') ?>"></label>
            <button class="button button-full" type="submit">Send reset link</button>
        </form>
        <p class="form-switch"><a href="<?= e(app_url('login.php')) ?>">Back to sign in</a></p>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
