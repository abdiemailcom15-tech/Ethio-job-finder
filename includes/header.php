<?php
require_once __DIR__ . '/auth.php';
$user = current_user();
$flashMessage = take_flash();
$pageTitle = $pageTitle ?? 'Find work that moves you';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · Workly</title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>">
    <?php if (!empty($includeMapAssets)): ?>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <?php endif; ?>
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= e(app_url('index.php')) ?>"><span class="brand-mark">W</span> Ethio work finder</a>
    <button class="menu-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false">☰</button>
    <nav class="main-nav">
        <a href="<?= e(app_url('index.php#jobs')) ?>">Find jobs</a>
        <?php if ($user): ?>
            <a href="<?= e(role_home($user['role'])) ?>">Dashboard</a>
            <a href="<?= e(app_url('work_chats.php')) ?>">Work chats</a>
            <?php if ($user['role'] === 'seeker'): ?><a href="<?= e(app_url('seeker/profile.php')) ?>">My profile</a><?php endif; ?>
            <?php if ($user['role'] === 'employer'): ?><a href="<?= e(app_url('employer/add_job.php')) ?>">Post a job</a><?php endif; ?>
            <span class="nav-user"><?= e($user['name']) ?></span>
            <form action="<?= e(app_url('logout.php')) ?>" method="post" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="nav-link" type="submit">Sign out</button>
            </form>
        <?php else: ?>
            <a href="<?= e(app_url('login.php')) ?>">Sign in</a>
            <a class="button button-small" href="<?= e(app_url('register.php')) ?>">Create account</a>
        <?php endif; ?>
    </nav>
</header>
<main class="page-shell">
    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= e($flashMessage['type']) ?>" role="status"><?= e($flashMessage['message']) ?></div>
    <?php endif; ?>
