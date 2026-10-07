<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('seeker');
$user = current_user();
$query = $pdo->prepare('SELECT * FROM seeker_profiles WHERE user_id = ?');
$query->execute([$user['id']]);
$profile = $query->fetch() ?: ['phone' => '', 'location' => '', 'skills' => '', 'bio' => '', 'cv_filename' => null, 'payout_provider' => '', 'payout_provider_other' => '', 'payout_account_name' => '', 'payout_account_number' => ''];
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $cv = $profile['cv_filename'];
    $name = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $provider = trim((string)($_POST['payout_provider'] ?? ''));
    $allowedProviders = ['Commercial Bank of Ethiopia', 'Telebirr', 'Awash Bank', 'Dashen Bank', 'Bank of Abyssinia', 'Oromia International Bank', 'Cooperative Bank of Oromia', 'Hibret Bank', 'Wegagen Bank', 'Zemen Bank', 'Nib International Bank', 'Other'];
    $providerOther = trim((string)($_POST['payout_provider_other'] ?? ''));
    $accountName = trim((string)($_POST['payout_account_name'] ?? ''));
    $accountNumber = trim((string)($_POST['payout_account_number'] ?? ''));
    $skills = trim((string)($_POST['skills'] ?? ''));
    $bio = trim((string)($_POST['bio'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $location = trim((string)($_POST['location'] ?? ''));
    $nameError = validate_person_name($name, 'Your name');
    $emailError = filter_var($email, FILTER_VALIDATE_EMAIL) && text_length($email) <= 190
        ? null
        : 'Enter a valid email address of 190 characters or fewer.';
    $phoneError = validate_ethiopian_phone($phone);
    $normalizedPhone = $phone === '' ? null : normalize_ethiopian_phone($phone);
    $storedPhone = normalize_ethiopian_phone((string)$profile['phone']);
    if ($phoneError === null && $normalizedPhone !== null && $normalizedPhone !== $storedPhone && phone_number_in_use($pdo, $normalizedPhone, (int)$user['id'])) {
        $phoneError = 'That phone number is already used by another account.';
    }
    $locationError = $location !== '' ? validate_place_name($location, 'Location') : null;
    $providerOtherError = validate_meaningful_text($providerOther, 'Other provider name', $provider === 'Other', 100, 2);
    $accountNameError = validate_meaningful_text($accountName, 'Account holder name', $provider !== '', 120, 2);
    $accountNumberError = validate_meaningful_text($accountNumber, 'Account number', $provider !== '', 100, 4);
    if ($provider === 'Telebirr' && $accountNumber !== '') {
        $accountPhoneError = validate_ethiopian_phone($accountNumber, 'Telebirr number', true);
        if ($accountPhoneError !== null) {
            $accountNumberError = $accountPhoneError;
        } else {
            $accountNumber = normalize_ethiopian_phone($accountNumber);
        }
    }
    $storedAccountNumber = (string)$profile['payout_account_number'];
    $accountUnchanged = $provider === (string)$profile['payout_provider']
        && ($provider === 'Telebirr'
            ? normalize_ethiopian_phone($accountNumber) === normalize_ethiopian_phone($storedAccountNumber)
            : strcasecmp($accountNumber, $storedAccountNumber) === 0);
    if ($accountNumberError === null && $provider !== '' && $accountNumber !== '' && !$accountUnchanged && payout_account_in_use($pdo, $accountNumber, (int)$user['id'], $provider)) {
        $accountNumberError = 'That payout account number is already used by another account.';
    }
    if ($nameError !== null) {
        $error = $nameError;
    } elseif ($emailError !== null) {
        $error = $emailError;
    } elseif (email_in_use($pdo, $email, (int)$user['id'])) {
        $error = 'That email address is already used by another account.';
    } elseif ($phoneError !== null) {
        $error = $phoneError;
    } elseif ($locationError !== null) {
        $error = $locationError;
    } elseif (($textareaError = validate_textarea($skills, 'Skills', false, 2000)) !== null) {
        $error = $textareaError;
    } elseif (($textareaError = validate_textarea($bio, 'About you', false, 5000)) !== null) {
        $error = $textareaError;
    } elseif ($provider !== '' && !in_array($provider, $allowedProviders, true)) {
        $error = 'Choose a supported payout provider.';
    } elseif ($providerOtherError !== null) {
        $error = $providerOtherError;
    } elseif ($accountNameError !== null) {
        $error = $accountNameError;
    } elseif ($accountNumberError !== null) {
        $error = $accountNumberError;
    } elseif ($provider !== '' && !preg_match('/\d{4}/', $accountNumber)) {
        $error = 'Enter an account number with at least 4 digits.';
    } elseif ($provider === '' && ($providerOther !== '' || $accountName !== '' || $accountNumber !== '')) {
        $error = 'Choose a payout provider before entering account details.';
    } elseif (text_length($providerOther) > 100 || text_length($accountName) > 120 || text_length($accountNumber) > 100) {
        $error = 'Check the maximum lengths for your payout details.';
    }
    try {
        if ($error !== '') {
            throw new RuntimeException($error);
        }
        $uploadedCv = null;
        if (isset($_FILES['cv']) && $_FILES['cv']['error'] !== UPLOAD_ERR_NO_FILE) {
            $cv = save_cv($_FILES['cv']);
            $uploadedCv = $cv;
        }
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?')->execute([$name, $email, $user['id']]);
            if ($email !== $user['email']) {
                $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?')->execute([$user['id']]);
            }
            $statement = $pdo->prepare('INSERT INTO seeker_profiles (user_id, phone, location, skills, bio, cv_filename, payout_provider, payout_provider_other, payout_account_name, payout_account_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE phone = VALUES(phone), location = VALUES(location), skills = VALUES(skills), bio = VALUES(bio), cv_filename = VALUES(cv_filename), payout_provider = VALUES(payout_provider), payout_provider_other = VALUES(payout_provider_other), payout_account_name = VALUES(payout_account_name), payout_account_number = VALUES(payout_account_number)');
            $statement->execute([$user['id'], $normalizedPhone, $location, $skills, $bio, $cv, $provider ?: null, $provider === 'Other' ? $providerOther : null, $provider ? $accountName : null, $provider ? $accountNumber : null]);
            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($uploadedCv !== null && $uploadedCv !== $profile['cv_filename']) {
                $path = __DIR__ . '/../uploads/cvs/' . $uploadedCv;
                if (is_file($path)) {
                    unlink($path);
                }
            }
            if ($exception->getCode() === '23000') {
                throw new RuntimeException('That email, phone number, or payout account number is already used by another account.');
            }
            throw $exception;
        }
        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['email'] = $email;
        flash('success', 'Your profile has been saved.');
        redirect(app_url('seeker/profile.php'));
    } catch (RuntimeException $exception) {
        $error = $exception->getMessage();
    }
}
$query->execute([$user['id']]);
$profile = $query->fetch() ?: $profile;
$pageTitle = 'My profile';
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-heading"><div><span class="eyebrow">YOUR CAREER SPACE</span><h1>My profile</h1><p>Help employers understand what you bring to the table.</p></div></section>
<nav class="dashboard-nav"><a href="<?= e(app_url('seeker/dashboard.php')) ?>">Overview</a><a class="active" href="<?= e(app_url('seeker/profile.php')) ?>">My profile</a><a href="<?= e(app_url('seeker/jobs.php')) ?>">Find jobs</a><a href="<?= e(app_url('seeker/applications.php')) ?>">My applications</a><a href="<?= e(app_url('seeker/work_orders.php')) ?>">My work</a><a href="<?= e(app_url('work_chats.php')) ?>">Work chats</a></nav>
<section class="content-card profile-card"><?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="form-stack">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="form-grid"><label>Full name<input name="name" required minlength="2" maxlength="120" value="<?= e($_POST['name'] ?? $user['name']) ?>"></label><label>Email address<input type="email" name="email" required maxlength="190" value="<?= e($_POST['email'] ?? $user['email']) ?>"></label>
    <label>Phone<input name="phone" type="tel" maxlength="20" placeholder="0912345678 or +251912345678" value="<?= e($_POST['phone'] ?? $profile['phone']) ?>"><span class="field-hint">Optional. Valid Ethiopian mobile number; cannot be shared with another account.</span></label><label>Location<input name="location" minlength="2" maxlength="120" placeholder="City, country or remote" value="<?= e($_POST['location'] ?? $profile['location']) ?>"></label></div>
    <label>Skills <span class="field-hint">Separate skills with commas</span><textarea name="skills" rows="3" maxlength="2000" placeholder="e.g. PHP, MySQL, project management"><?= e($_POST['skills'] ?? $profile['skills']) ?></textarea><span class="field-hint">Optional. If provided, enter at least 5 letters or numbers (up to 2,000).</span></label>
    <label>About you<textarea name="bio" rows="5" maxlength="5000" placeholder="Share a little about your experience and the work you enjoy."><?= e($_POST['bio'] ?? $profile['bio']) ?></textarea><span class="field-hint">Optional. If provided, enter at least 5 letters or numbers (up to 5,000).</span></label>
    <div class="section-heading compact"><div><span class="eyebrow">PAYMENT DETAILS</span><h2>Where companies can pay you</h2></div></div>
    <p class="muted-copy">These payout details are shared with a company only after it accepts your application into a work order. Double-check the account number before saving.</p>
    <label>Payout provider<select name="payout_provider"><option value="">Choose a payout method</option><?php foreach (['Commercial Bank of Ethiopia', 'Telebirr', 'Awash Bank', 'Dashen Bank', 'Bank of Abyssinia', 'Oromia International Bank', 'Cooperative Bank of Oromia', 'Hibret Bank', 'Wegagen Bank', 'Zemen Bank', 'Nib International Bank', 'Other'] as $providerOption): ?><option value="<?= e($providerOption) ?>" <?= ($_POST['payout_provider'] ?? $profile['payout_provider']) === $providerOption ? 'selected' : '' ?>><?= e($providerOption) ?></option><?php endforeach; ?></select></label>
    <label>Other provider name <span class="field-hint">Only needed when choosing Other</span><input name="payout_provider_other" minlength="2" maxlength="100" value="<?= e($_POST['payout_provider_other'] ?? $profile['payout_provider_other']) ?>"></label>
    <div class="form-grid"><label>Account holder name<input name="payout_account_name" minlength="2" maxlength="120" value="<?= e($_POST['payout_account_name'] ?? $profile['payout_account_name']) ?>"></label><label>Account number / Telebirr number<input name="payout_account_number" minlength="4" maxlength="100" value="<?= e($_POST['payout_account_number'] ?? $profile['payout_account_number']) ?>"><span class="field-hint">If a payout method is selected, enter an account number with at least 4 digits.</span></label></div>
    <label>CV <span class="field-hint">PDF, DOC, or DOCX · maximum 5 MB</span><input type="file" name="cv" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"></label>
    <?php if ($profile['cv_filename']): ?><p class="muted-copy">A CV is already uploaded. Choose another file above to replace it.</p><?php endif; ?>
    <button class="button" type="submit">Save profile <span>→</span></button>
</form></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
