<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$user = current_user();
$query = $pdo->prepare('SELECT id, name, email, password FROM users WHERE id = ? AND role = ?');
$query->execute([$user['id'], 'admin']);
$account = $query->fetch();
if (!$account) {
    http_response_code(404);
    exit('Admin account not found.');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $nameError = validate_person_name($name, 'Admin name');
    if ($nameError !== null) {
        $error = $nameError;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || text_length($email) > 190) {
        $error = 'Enter a valid email address of 190 characters or fewer.';
    } elseif (email_in_use($pdo, $email, (int)$account['id'])) {
        $error = 'That email address is already used by another account.';
    } elseif (!password_verify($currentPassword, $account['password'])) {
        $error = 'Enter your current password to confirm these changes.';
    } elseif ($newPassword !== '' && (strlen($newPassword) < 8 || strlen($newPassword) > 4096)) {
        $error = 'A new password must be between 8 and 4,096 characters.';
    } elseif ($newPassword !== '' && !hash_equals($newPassword, $confirmPassword)) {
        $error = 'The new password confirmation does not match.';
    } elseif ($newPassword === '' && $confirmPassword !== '') {
        $error = 'Enter a new password or leave both new-password fields blank.';
    } else {
        $passwordHash = $newPassword === '' ? $account['password'] : password_hash($newPassword, PASSWORD_DEFAULT);
        try {
            $update = $pdo->prepare('UPDATE users SET name = ?, email = ?, password = ? WHERE id = ? AND role = ?');
            $update->execute([$name, $email, $passwordHash, $account['id'], 'admin']);
            if ($email !== $account['email'] || $newPassword !== '') {
                $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?')->execute([$account['id']]);
            }
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['email'] = $email;
            flash('success', 'Admin account settings updated.');
            redirect(app_url('admin/account.php'));
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            $error = 'That email address is already used by another account.';
        }
    }
}

$pageTitle = 'Admin account';
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-heading"><div><span class="eyebrow">ADMINISTRATION</span><h1>Admin account</h1><p>Update your profile email and password.</p></div></section>
<?php require __DIR__ . '/nav.php'; ?>
<section class="content-card profile-card">
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="form-stack">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label>Name<input name="name" minlength="2" maxlength="120" required value="<?= e($_POST['name'] ?? $account['name']) ?>"></label>
        <label>Email address<input type="email" name="email" maxlength="190" required autocomplete="email" value="<?= e($_POST['email'] ?? $account['email']) ?>"></label>
        <label>Current password <span class="field-hint">Required to confirm account changes</span><input type="password" name="current_password" maxlength="4096" autocomplete="current-password" required></label>
        <label>New password <span class="field-hint">Leave blank to keep your current password</span><input type="password" name="new_password" minlength="8" maxlength="4096" autocomplete="new-password"></label>
        <label>Confirm new password<input type="password" name="confirm_password" minlength="8" maxlength="4096" autocomplete="new-password"></label>
        <button class="button" type="submit">Save account settings</button>
    </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
