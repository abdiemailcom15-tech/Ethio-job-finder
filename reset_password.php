<?php
require_once __DIR__ . '/includes/auth.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Referrer-Policy: no-referrer');
if (current_user()) {
    redirect(role_home(current_user()['role']));
}

$token = trim((string)($_POST['token'] ?? $_GET['token'] ?? ''));
$tokenIsValid = preg_match('/^[a-f0-9]{64}$/', $token) === 1;
$account = null;
if ($tokenIsValid) {
    $query = $pdo->prepare('SELECT u.id, u.email FROM password_reset_tokens r JOIN users u ON u.id = r.user_id WHERE r.token_hash = ? AND r.expires_at > NOW() LIMIT 1');
    $query->execute([hash('sha256', $token)]);
    $account = $query->fetch() ?: null;
}

$error = '';
$complete = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$account) {
        $error = 'This password-reset link is invalid or has expired. Request a new link.';
    } else {
        $password = (string)($_POST['password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        if (strlen($password) < 8 || strlen($password) > 4096) {
            $error = 'Choose a password between 8 and 4,096 characters.';
        } elseif (!hash_equals($password, $confirmPassword)) {
            $error = 'The password confirmation does not match.';
        } else {
            $pdo->beginTransaction();
            try {
                $update = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                $update->execute([password_hash($password, PASSWORD_DEFAULT), $account['id']]);
                $delete = $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ? AND token_hash = ? AND expires_at > NOW()');
                $delete->execute([$account['id'], hash('sha256', $token)]);
                if ($delete->rowCount() !== 1) {
                    throw new RuntimeException('This password-reset link was already used. Request a new link.');
                }
                $pdo->commit();
                $complete = true;
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($exception instanceof RuntimeException) {
                    $error = $exception->getMessage();
                } else {
                    throw $exception;
                }
            }
        }
    }
}

$pageTitle = 'Choose a new password';
require __DIR__ . '/includes/header.php';
?>
<section class="auth-layout">
    <div class="auth-aside"><span class="eyebrow">ACCOUNT RECOVERY</span><h1>A fresh start.</h1><p>Your new password will secure your Workly account.</p></div>
    <div class="auth-card"><div class="form-heading"><span class="eyebrow">PASSWORD RESET</span><h2>Choose a new password</h2><p>Use at least 8 characters. Reset links expire after one hour and can only be used once.</p></div>
        <?php if ($complete): ?><div class="alert alert-success" role="status">Your password has been changed. You can now sign in.</div><a class="button button-full" href="<?= e(app_url('login.php')) ?>">Go to sign in</a>
        <?php elseif (!$account): ?><div class="alert alert-error"><?= e($error ?: 'This password-reset link is invalid or has expired. Request a new link.') ?></div><p class="form-switch"><a href="<?= e(app_url('forgot_password.php')) ?>">Request another reset link</a></p>
        <?php else: ?>
            <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
            <form method="post" class="form-stack">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <label>New password<input type="password" name="password" minlength="8" maxlength="4096" autocomplete="new-password" required></label>
                <label>Confirm new password<input type="password" name="confirm_password" minlength="8" maxlength="4096" autocomplete="new-password" required></label>
                <button class="button button-full" type="submit">Update password</button>
            </form>
        <?php endif; ?>
        <p class="form-switch"><a href="<?= e(app_url('login.php')) ?>">Back to sign in</a></p>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
