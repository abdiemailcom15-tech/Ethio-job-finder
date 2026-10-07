<?php
require_once __DIR__ . '/../includes/auth.php';
if (current_user()) {
    redirect(role_home(current_user()['role']));
}
redirect(app_url('login.php'));
