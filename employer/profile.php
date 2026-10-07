<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('employer');
$query = $pdo->prepare('SELECT * FROM companies WHERE user_id = ?');
$query->execute([current_user()['id']]);
$company = $query->fetch();
if (!$company) {
    http_response_code(500);
    exit('Company profile not found.');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string)($_POST['name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $website = trim((string)($_POST['website'] ?? ''));
    $location = trim((string)($_POST['location'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $websiteScheme = strtolower((string)parse_url($website, PHP_URL_SCHEME));
    $nameError = validate_company_name($name);
    $phoneError = validate_ethiopian_phone($phone, 'Company phone number');
    $normalizedPhone = $phone === '' ? null : normalize_ethiopian_phone($phone);
    $storedPhone = normalize_ethiopian_phone((string)$company['phone']);
    $locationError = $location !== '' ? validate_place_name($location, 'Company location') : null;
    if ($nameError !== null) {
        $error = $nameError;
    } elseif ($phoneError !== null) {
        $error = $phoneError;
    } elseif ($normalizedPhone !== null && $normalizedPhone !== $storedPhone && phone_number_in_use($pdo, $normalizedPhone, (int)current_user()['id'])) {
        $error = 'That phone number is already used by another account.';
    } elseif ($locationError !== null) {
        $error = $locationError;
    } elseif ($website !== '' && (text_length($website) > 255 || !filter_var($website, FILTER_VALIDATE_URL) || !in_array($websiteScheme, ['http', 'https'], true))) {
        $error = 'Enter a valid website URL beginning with http:// or https://.';
    } elseif (($descriptionError = validate_textarea($description, 'Company description', false, 5000)) !== null) {
        $error = $descriptionError;
    } else {
        $pdo->prepare('UPDATE companies SET name = ?, phone = ?, website = ?, location = ?, description = ? WHERE id = ?')
            ->execute([$name, $normalizedPhone, $website ?: null, $location, $description ?: null, $company['id']]);
        flash('success', 'Company profile saved.');
        redirect(app_url('employer/profile.php'));
    }
}
$pageTitle = 'Company profile';
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-heading"><div><span class="eyebrow">EMPLOYER SPACE</span><h1>Company profile</h1><p>Tell job seekers what makes your team a great place to work.</p></div></section>
<?php require __DIR__ . '/nav.php'; ?>
<section class="content-card profile-card"><?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?><form method="post" class="form-stack"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label>Company name<input name="name" minlength="2" maxlength="160" required value="<?= e($_POST['name'] ?? $company['name']) ?>"></label><div class="form-grid"><label>Company phone<input name="phone" type="tel" maxlength="20" placeholder="0912345678 or +251912345678" value="<?= e($_POST['phone'] ?? $company['phone']) ?>"><span class="field-hint">Optional. Valid Ethiopian mobile number; must not be used by another account.</span></label><label>Website<input type="url" name="website" maxlength="255" placeholder="https://example.com" value="<?= e($_POST['website'] ?? $company['website']) ?>"></label><label>Location<input name="location" minlength="2" maxlength="120" placeholder="City, country or remote" value="<?= e($_POST['location'] ?? $company['location']) ?>"></label></div><label>About the company<textarea name="description" rows="6" maxlength="5000"><?= e($_POST['description'] ?? $company['description']) ?></textarea><span class="field-hint">Optional. If provided, write at least 5 letters or numbers (up to 5,000).</span></label><p class="muted-copy">Company status: <span class="badge badge-<?= e(application_status_class($company['status'])) ?>"><?= e(ucfirst($company['status'])) ?></span></p><button class="button" type="submit">Save company profile <span>→</span></button></form></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
