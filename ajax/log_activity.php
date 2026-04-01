<?php

require __DIR__ . '/../bootstrap/app.php';
Auth::require();
verify_csrf();

$action = trim((string) request_value('action_name', ''));
$description = trim((string) request_value('description', ''));
$targetType = trim((string) request_value('target_type', ''));
$targetId = trim((string) request_value('target_id', ''));
$context = request_json_value('context');

if ($action === '' || $description === '') {
    json_response(['success' => false, 'message' => 'Data log aktivitas tidak lengkap.'], 422);
}

ActivityLogService::logCurrentUser(
    $action,
    $description,
    $context,
    $targetType,
    $targetId !== '' ? $targetId : null
);

json_response(['success' => true, 'message' => 'Aktivitas tercatat.']);
