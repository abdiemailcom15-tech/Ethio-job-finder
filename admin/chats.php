<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
redirect(app_url('work_chats.php'));
