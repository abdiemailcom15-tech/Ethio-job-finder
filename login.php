<?php
require_once __DIR__ . '/includes/auth.php';
if (current_user()) {
    redirect(role_home(current_user()['role']));
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || text_length($email) > 190 || $password === '' || strlen($password) > 4096) {
        $error = 'Enter a valid email address and password.';
    } else {
        $statement = $pdo->prepare('SELECT u.id, u.name, u.email, u.password, u.role, c.id AS company_id FROM users u LEFT JOIN companies c ON c.user_id = u.id WHERE u.email = ?');
        $statement->execute([$email]);
        $user = $statement->fetch();
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            unset($user['password']);
            $_SESSION['user'] = $user;
            redirect(role_home($user['role']));
        }
        $error = 'Email or password is incorrect.';
    }
}
$pageTitle = 'Sign in';
require __DIR__ . '/includes/header.php';
?>
<section class="auth-layout">
    <div class="auth-aside"><span class="eyebrow">WELCOME BACK</span><h1>Make your next move.</h1><p>Sign in to pick up right where your career journey left off.</p><div class="aside-note"><span>✦</span> The right opportunity can change everything.</div></div>
    <div class="auth-card"><div class="form-heading"><span class="eyebrow">YOUR WORKLY ACCOUNT</span><h2>Sign in</h2><p>Enter your details to continue.</p></div>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="form-stack">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label>Email address<input type="email" name="email" maxlength="190" autocomplete="email" required value="<?= e($_POST['email'] ?? '') ?>"></label>
            <label>Password<input type="password" name="password" maxlength="4096" autocomplete="current-password" required></label>
            <button class="button button-full" type="submit">Sign in <span>→</span></button>
        </form>
        <p class="form-switch"><a href="<?= e(app_url('forgot_password.php')) ?>">Forgot your password?</a></p>
        <p class="form-switch">New to Workly? <a href="<?= e(app_url('register.php')) ?>">Create an account</a></p>
        <p class="form-footnote">Administrator? Sign in here with your admin account.</p>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
