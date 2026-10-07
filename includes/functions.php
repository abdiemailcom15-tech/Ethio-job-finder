<?php
declare(strict_types=1);

function app_url(string $path = ''): string
{
    $directory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $base = preg_replace('~/(admin|seeker|employer)$~', '', $directory) ?? $directory;
    if ($base === '/' || $base === '.') {
        $base = '';
    }
    return str_replace(' ', '%20', rtrim($base, '/') . '/' . ltrim($path, '/'));
}

function e(mixed $value): string
{
    if ($value !== null && !is_scalar($value)) {
        return '';
    }
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function normalize_ethiopian_phone(string $value): ?string
{
    $value = trim($value);
    if (!preg_match('/^\+?[0-9\s().-]+$/', $value)) {
        return null;
    }
    $digits = preg_replace('/\D/', '', $value) ?? '';
    if (preg_match('/^0[79]\d{8}$/', $digits)) {
        return '+251' . substr($digits, 1);
    }
    if (preg_match('/^251[79]\d{8}$/', $digits)) {
        return '+' . $digits;
    }
    return null;
}

function validate_ethiopian_phone(string $value, string $label = 'Phone number', bool $required = false): ?string
{
    if (trim($value) === '') {
        return $required ? $label . ' is required.' : null;
    }
    if (normalize_ethiopian_phone($value) === null) {
        return $label . ' must be a valid Ethiopian mobile number, such as 0912345678 or +251912345678.';
    }
    return null;
}

function phone_number_in_use(PDO $pdo, string $normalizedPhone, int $excludeUserId): bool
{
    $query = $pdo->prepare("SELECT phone AS value FROM seeker_profiles WHERE user_id <> ? AND phone IS NOT NULL AND phone <> '' UNION ALL SELECT phone AS value FROM companies WHERE user_id <> ? AND phone IS NOT NULL AND phone <> '' UNION ALL SELECT payout_account_number AS value FROM seeker_profiles WHERE user_id <> ? AND payout_provider = 'Telebirr' AND payout_account_number IS NOT NULL AND payout_account_number <> ''");
    $query->execute([$excludeUserId, $excludeUserId, $excludeUserId]);
    foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $phone) {
        if (normalize_ethiopian_phone((string)$phone) === $normalizedPhone) {
            return true;
        }
    }
    return false;
}

function email_in_use(PDO $pdo, string $email, int $excludeUserId = 0): bool
{
    $query = $pdo->prepare('SELECT 1 FROM users WHERE email = ? AND id <> ? LIMIT 1');
    $query->execute([$email, $excludeUserId]);
    return (bool)$query->fetchColumn();
}

function payout_account_in_use(PDO $pdo, string $accountNumber, int $excludeUserId, string $provider): bool
{
    $query = $pdo->prepare('SELECT payout_provider, payout_account_number FROM seeker_profiles WHERE user_id <> ? AND payout_account_number IS NOT NULL AND payout_account_number <> ?');
    $query->execute([$excludeUserId, '']);
    $normalizedPhone = $provider === 'Telebirr' ? normalize_ethiopian_phone($accountNumber) : null;
    foreach ($query->fetchAll() as $account) {
        if (strcasecmp((string)$account['payout_account_number'], $accountNumber) === 0) {
            return true;
        }
        if ($normalizedPhone !== null && $account['payout_provider'] === 'Telebirr' && normalize_ethiopian_phone((string)$account['payout_account_number']) === $normalizedPhone) {
            return true;
        }
    }
    return false;
}

function send_password_reset_email(string $email, string $name, string $resetUrl): bool
{
    $from = getenv('APP_MAIL_FROM') ?: (string)ini_get('sendmail_from');
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        error_log('Password reset email not sent: APP_MAIL_FROM is not a valid email address.');
        return false;
    }

    $subject = 'Reset your Workly password';
    $body = "Hello {$name},\r\n\r\n";
    $body .= "We received a request to reset your Workly password. Use this link within one hour:\r\n";
    $body .= $resetUrl . "\r\n\r\n";
    $body .= "If you did not request this change, you can ignore this email.\r\n";
    $headers = [
        'From' => $from,
        'Content-Type' => 'text/plain; charset=UTF-8',
        'X-Mailer' => 'PHP/' . PHP_VERSION,
    ];

    try {
        $sent = mail($email, $subject, $body, $headers);
    } catch (Throwable $exception) {
        error_log('Password reset email could not be sent: ' . $exception->getMessage());
        return false;
    }
    if (!$sent) {
        error_log('Password reset email could not be sent; check the XAMPP sendmail configuration.');
    }
    return $sent;
}

function has_repeated_spam_pattern(string $value): bool
{
    $normalized = strtolower(preg_replace('/[^\p{L}\p{N}]/u', '', $value) ?? '');
    if ($normalized === '') {
        return false;
    }
    if (preg_match('/(\p{L})\1{3,}/u', $normalized)) {
        return true;
    }

    $keyboardRows = ['qwertyuiop', 'asdfghjkl', 'zxcvbnm'];
    foreach ($keyboardRows as $keyboardRow) {
        foreach ([$keyboardRow, strrev($keyboardRow)] as $sequence) {
            for ($index = 0; $index <= strlen($sequence) - 3; $index++) {
                if (str_contains($normalized, substr($sequence, $index, 3))) {
                    return true;
                }
            }
        }
    }

    $words = preg_split('/[^\p{L}\p{N}]+/u', strtolower($value), -1, PREG_SPLIT_NO_EMPTY);
    if ($words === false) {
        return true;
    }

    foreach ($words as $word) {
        $characters = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false) {
            return true;
        }
        $length = count($characters);
        for ($patternLength = 2; $patternLength <= min(8, intdiv($length, 2)); $patternLength++) {
            $pattern = array_slice($characters, 0, $patternLength);
            $repetitions = 0;
            while (array_slice($characters, $repetitions * $patternLength, $patternLength) === $pattern) {
                $repetitions++;
            }
            if ($repetitions >= 3 || ($length >= 5 && $repetitions >= 2 && $repetitions * $patternLength < $length)) {
                return true;
            }
        }

        if (preg_match('/^[a-z]{5,8}$/', $word)) {
            $vowels = preg_match_all('/[aeiouy]/', $word);
            $letterCounts = array_count_values($characters);
            $repeatedLetters = array_sum(array_map(static fn(int $count): int => max(0, $count - 1), $letterCounts));
            if ($vowels !== false && $vowels <= 1 && $repeatedLetters >= 3) {
                return true;
            }
        }
    }
    return false;
}

function validate_meaningful_text(string $value, string $label, bool $required, int $maximum, int $minimum = 3): ?string
{
    $value = trim($value);
    if ($value === '') {
        return $required ? $label . ' is required. Enter meaningful information.' : null;
    }
    if (text_length($value) > $maximum) {
        return $label . ' must be ' . number_format($maximum) . ' characters or fewer.';
    }

    $meaningfulCharacters = preg_match_all('/[\p{L}\p{N}]/u', $value);
    if ($meaningfulCharacters === false) {
        return $label . ' contains invalid text. Check the characters and try again.';
    }
    if ($meaningfulCharacters < $minimum) {
        return $label . ' must contain at least ' . $minimum . ' letters or numbers so the information is meaningful.';
    }
    if (has_repeated_spam_pattern($value)) {
        return $label . ' contains repeated characters, a keyboard sequence, or random-looking text. Enter meaningful information.';
    }

    return null;
}

function validate_job_title(string $value, string $label = 'Job title'): ?string
{
    $error = validate_meaningful_text($value, $label, true, 180, 3);
    if ($error !== null) {
        return $error;
    }
    if (preg_match_all('/\p{L}/u', $value) < 3) {
        return $label . ' must contain at least 3 letters.';
    }
    $characters = preg_match_all('/[\p{L}\p{N}]/u', $value);
    if ($characters && (text_length($value) - $characters) / text_length($value) > 0.4) {
        return $label . ' contains too many symbols.';
    }
    return null;
}

function validate_company_name(string $value, string $label = 'Company name'): ?string
{
    $error = validate_meaningful_text($value, $label, true, 160, 2);
    if ($error !== null) {
        return $error;
    }
    if (preg_match_all('/\p{L}/u', $value) < 2) {
        return $label . ' must contain at least 2 letters.';
    }
    $characters = preg_match_all('/[\p{L}\p{N}]/u', $value);
    if ($characters && (text_length($value) - $characters) / text_length($value) > 0.4) {
        return $label . ' contains too many symbols.';
    }
    return null;
}

function validate_person_name(string $value, string $label = 'Name'): ?string
{
    $error = validate_meaningful_text($value, $label, true, 120, 2);
    if ($error !== null) {
        return $error;
    }
    if (preg_match_all('/\p{L}/u', $value) < 2) {
        return $label . ' must contain at least 2 letters.';
    }
    $lettersAndNumbers = preg_match_all('/[\p{L}\p{N}]/u', $value);
    if ($lettersAndNumbers && (text_length($value) - $lettersAndNumbers) / text_length($value) > 0.35) {
        return $label . ' contains too many symbols.';
    }
    return null;
}

function validate_place_name(string $value, string $label): ?string
{
    $error = validate_meaningful_text($value, $label, true, 120, 2);
    if ($error !== null) {
        return $error;
    }
    if (preg_match_all('/\p{L}/u', $value) < 2) {
        return $label . ' must include a place name with at least 2 letters.';
    }
    return null;
}

function validate_user_message(string $value, string $label, bool $required, int $maximum): ?string
{
    $value = trim($value);
    if ($value === '') {
        return $required ? $label . ' is required.' : null;
    }
    if (text_length($value) > $maximum) {
        return $label . ' must be ' . number_format($maximum) . ' characters or fewer.';
    }
    if (has_repeated_spam_pattern($value)) {
        return $label . ' contains repeated characters, a keyboard sequence, or random-looking text. Enter a meaningful message.';
    }

    $letters = preg_match_all('/\p{L}/u', $value);
    $lettersAndNumbers = preg_match_all('/[\p{L}\p{N}]/u', $value);
    $numbers = preg_match_all('/\p{N}/u', $value);
    if ($letters === false || $lettersAndNumbers === false || $numbers === false) {
        return $label . ' contains invalid text. Check the characters and try again.';
    }
    $length = max(1, text_length($value));
    if ($lettersAndNumbers / $length < 0.5 || ($length - $lettersAndNumbers) / $length > 0.4 || $numbers / $length > 0.4) {
        return $label . ' contains too many numbers or symbols. Please write a clear message.';
    }
    $shortReply = strtolower(preg_replace('/[^\p{L}]/u', '', $value) ?? '');
    if (in_array($shortReply, ['hi', 'ok', 'yes', 'no', 'thanks'], true)) {
        return null;
    }
    if ($letters < 3) {
        return $label . ' must include at least 3 letters or be a recognized short reply such as “OK”.';
    }

    return null;
}

function validate_textarea(string $value, string $label, bool $required, int $maximum): ?string
{
    return validate_meaningful_text($value, $label, $required, $maximum, 5);
}

function parse_job_coordinates(string $latitude, string $longitude): array|false
{
    $latitude = trim($latitude);
    $longitude = trim($longitude);
    if ($latitude === '' && $longitude === '') {
        return ['latitude' => null, 'longitude' => null];
    }
    if ($latitude === '' || $longitude === '' || !is_numeric($latitude) || !is_numeric($longitude)) {
        return false;
    }

    $latitude = (float)$latitude;
    $longitude = (float)$longitude;
    if (!is_finite($latitude) || !is_finite($longitude) || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        return false;
    }

    return ['latitude' => $latitude, 'longitude' => $longitude];
}

function job_map_url(mixed $latitude, mixed $longitude): ?string
{
    if ($latitude === null || $longitude === null) {
        return null;
    }
    $coordinates = parse_job_coordinates((string)$latitude, (string)$longitude);
    if ($coordinates === false || $coordinates['latitude'] === null) {
        return null;
    }

    $latitude = number_format($coordinates['latitude'], 7, '.', '');
    $longitude = number_format($coordinates['longitude'], 7, '.', '');
    return 'https://www.openstreetmap.org/?mlat=' . $latitude . '&mlon=' . $longitude . '#map=16/' . $latitude . '/' . $longitude;
}

function text_excerpt(string $value, int $length): string
{
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($value, 0, $length, '…', 'UTF-8');
    }
    $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
    if ($characters === false || count($characters) <= $length) {
        return $value;
    }
    return implode('', array_slice($characters, 0, max(0, $length - 1))) . '…';
}

function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Your session expired. Go back, refresh the page, and try again.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $message;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function role_home(string $role): string
{
    return match ($role) {
        'admin' => app_url('admin/dashboard.php'),
        'employer' => app_url('employer/dashboard.php'),
        default => app_url('seeker/dashboard.php'),
    };
}

function require_role(string ...$roles): void
{
    $user = current_user();
    if (!$user) {
        flash('error', 'Please sign in to continue.');
        redirect(app_url('login.php'));
    }
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('You do not have permission to view this page.');
    }
}

function application_status_class(string $status): string
{
    return match ($status) {
        'accepted', 'approved', 'paid', 'completed' => 'success',
        'rejected' => 'danger',
        'reviewing', 'submitted', 'revision_requested', 'invoiced', 'open', 'in_review' => 'warning',
        default => 'muted',
    };
}

function save_cv(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Choose a CV file to upload.');
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('The CV must be 5 MB or smaller.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $extensions = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];
    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Upload a PDF, DOC, or DOCX CV.');
    }

    $directory = dirname(__DIR__) . '/uploads/cvs';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('The CV upload directory could not be created.');
    }
    $filename = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('The CV could not be saved. Check folder permissions.');
    }
    return $filename;
}

function supported_work_file_types(): array
{
    return [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/zip' => 'zip',
        'application/x-zip' => 'zip',
        'application/x-zip-compressed' => 'zip',
        'text/plain' => 'txt',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
        'video/x-msvideo' => 'avi',
        'video/x-matroska' => 'mkv',
        'video/mpeg' => 'mpeg',
    ];
}

function save_work_file(array $file): array
{
    return save_verified_upload($file, 'work', supported_work_file_types(), 25 * 1024 * 1024, 'work file');
}

function save_chat_attachment(array $file): array
{
    return save_verified_upload($file, 'chat', supported_work_file_types(), 25 * 1024 * 1024, 'chat attachment');
}

function save_payment_proof(array $file): array
{
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    return save_verified_upload($file, 'payment-proofs', $extensions, 5 * 1024 * 1024, 'payment screenshot');
}

function save_verified_upload(array $file, string $directoryName, array $extensions, int $maximumSize, string $label): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_INI_SIZE || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException('The ' . $label . ' exceeds the server upload limit.');
        }
        throw new RuntimeException('The ' . $label . ' could not be uploaded. Check the file and try again.');
    }
    if (($file['size'] ?? 0) > $maximumSize) {
        throw new RuntimeException('The ' . $label . ' must be ' . number_format((int)($maximumSize / 1024 / 1024)) . ' MB or smaller.');
    }
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('The ' . $label . ' upload is invalid.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!is_string($mime) || !isset($extensions[$mime])) {
        throw new RuntimeException('The ' . $label . ' has an unsupported file type.');
    }

    $directory = dirname(__DIR__) . '/uploads/' . $directoryName;
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('The ' . $label . ' upload directory could not be created.');
    }
    $storedName = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $storedName)) {
        throw new RuntimeException('The ' . $label . ' could not be saved. Check folder permissions.');
    }
    $originalName = basename((string)($file['name'] ?? 'work-file'));
    $originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?? 'work-file';
    return [
        'stored_name' => $storedName,
        'original_name' => text_excerpt($originalName, 250),
        'mime_type' => $mime,
    ];
}
