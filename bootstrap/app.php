<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/helpers.php';
load_env(__DIR__ . '/../.env');
date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Singapore'));

require_once __DIR__ . '/../services/Auth.php';
require_once __DIR__ . '/../services/PayrollService.php';
require_once __DIR__ . '/../services/AttendanceImportService.php';

require_once __DIR__ . '/../components/icon.php';
require_once __DIR__ . '/../components/button.php';
require_once __DIR__ . '/../components/modal.php';
require_once __DIR__ . '/../components/table.php';
require_once __DIR__ . '/../components/panel.php';
require_once __DIR__ . '/../components/detail.php';
require_once __DIR__ . '/../components/field.php';
require_once __DIR__ . '/../components/badge.php';
require_once __DIR__ . '/../components/stat.php';
