<?php
require_once __DIR__ . '/includes/auth.php';
if (current_user()) {
    redirect(role_home(current_user()['role']));
}
$error = '';
$roleValue = $_POST['role'] ?? ($_GET['role'] ?? 'seeker');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $role = (string)($_POST['role'] ?? 'seeker');
    $companyName = trim((string)($_POST['company_name'] ?? ''));
    $companyLocation = trim((string)($_POST['company_location'] ?? ''));
    $companyPhone = trim((string)($_POST['company_phone'] ?? ''));
    $companyDescription = trim((string)($_POST['company_description'] ?? ''));
    $nameError = validate_person_name($name, 'Your name');
    $companyNameError = $role === 'employer' ? validate_company_name($companyName) : null;
    $companyLocationError = $role === 'employer' && $companyLocation !== '' ? validate_place_name($companyLocation, 'Company location') : null;
    $companyPhoneError = $role === 'employer' ? validate_ethiopian_phone($companyPhone, 'Company phone number') : null;
    $normalizedCompanyPhone = $companyPhone === '' ? null : normalize_ethiopian_phone($companyPhone);
    if ($nameError !== null) {
        $error = $nameError;
    } elseif ($companyNameError !== null) {
        $error = $companyNameError;
    } elseif ($companyLocationError !== null) {
        $error = $companyLocationError;
    } elseif ($companyPhoneError !== null) {
        $error = $companyPhoneError;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || text_length($email) > 190 || strlen($password) < 8 || strlen($password) > 4096 || !in_array($role, ['seeker', 'employer'], true)) {
        $error = 'Enter a valid email (up to 190 characters), choose an account type, and use a password of 8–4,096 characters.';
    } elseif (email_in_use($pdo, $email)) {
        $error = 'That email address or phone number is already used by another account.';
    } elseif ($normalizedCompanyPhone !== null && phone_number_in_use($pdo, $normalizedCompanyPhone, 0)) {
        $error = 'That phone number is already used by another account.';
    } elseif ($role === 'employer' && ($descriptionError = validate_textarea($companyDescription, 'Company description', false, 5000)) !== null) {
        $error = $descriptionError;
    } else {
        try {
            $pdo->beginTransaction();
            $insert = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
            $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
            $userId = (int)$pdo->lastInsertId();
            if ($role === 'seeker') {
                $pdo->prepare('INSERT INTO seeker_profiles (user_id) VALUES (?)')->execute([$userId]);
            } else {
                $pdo->prepare('INSERT INTO companies (user_id, name, phone, location, description) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$userId, $companyName, $normalizedCompanyPhone, $companyLocation, $companyDescription ?: null]);
            }
            $pdo->commit();
            if ($role === 'employer') {
                flash('success', 'Company registration successful. Please log in with your registered account. Your company profile is pending admin approval.');
            } else {
                flash('success', 'Job seeker registration successful. Please log in with your registered account.');
            }
            redirect(app_url('login.php'));
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            $error = 'An account with that email already exists.';
        }
    }
}
$pageTitle = 'Create your account';
require __DIR__ . '/includes/header.php';
?>
<section class="auth-layout">
    <div class="auth-aside"><span class="eyebrow">A BETTER WAY TO FIND WHAT'S NEXT</span><h1>Opportunity starts with a hello.</h1><p>Join a community built around good work and great people.</p><div class="aside-note"><span>✦</span> One profile. A world of possibilities.</div></div>
    <div class="auth-card"><div class="form-heading"><span class="eyebrow">GET STARTED</span><h2>Create an account</h2><p>Choose how you want to use Workly.</p></div>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="form-stack" id="register-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label>I am here to
                <select name="role" id="account-role"><option value="seeker" <?= $roleValue === 'seeker' ? 'selected' : '' ?>>Find a job</option><option value="employer" <?= $roleValue === 'employer' ? 'selected' : '' ?>>Hire great people</option></select>
            </label>
            <label>Your name<input name="name" required minlength="2" maxlength="120" autocomplete="name" value="<?= e($_POST['name'] ?? '') ?>"></label>
            <label>Email address<input type="email" name="email" maxlength="190" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>"></label>
            <label>Password <span class="field-hint">(8–4,096 characters)</span><input type="password" name="password" minlength="8" maxlength="4096" required autocomplete="new-password"></label>
            <div id="company-fields" <?= $roleValue !== 'employer' ? 'hidden' : '' ?>>
                <label>Company name<input name="company_name" minlength="2" maxlength="160" value="<?= e($_POST['company_name'] ?? '') ?>" <?= $roleValue === 'employer' ? 'required' : '' ?>></label>
                <label>Company location<input name="company_location" minlength="2" maxlength="120" value="<?= e($_POST['company_location'] ?? '') ?>"></label>
                <label>Company phone<input name="company_phone" type="tel" maxlength="20" placeholder="0912345678 or +251912345678" value="<?= e($_POST['company_phone'] ?? '') ?>"><span class="field-hint">Optional. Use a valid Ethiopian mobile number.</span></label>
                <label>About your company<textarea name="company_description" rows="3" maxlength="5000"><?= e($_POST['company_description'] ?? '') ?></textarea><span class="field-hint">Optional. If provided, write at least 5 letters or numbers (up to 5,000).</span></label>
            </div>
            <button class="button button-full" type="submit">Create account <span>→</span></button>
        </form>
        <p class="form-switch">Already have an account? <a href="<?= e(app_url('login.php')) ?>">Sign in</a></p>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
