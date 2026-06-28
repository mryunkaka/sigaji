<?php

require __DIR__ . '/../bootstrap/app.php';
$authUser = Auth::require();
verify_csrf();

if (($authUser['role'] ?? '') !== 'owner') {
    json_response(['success' => false, 'message' => 'Hanya owner yang bisa memvalidasi subscription.'], 403);
}

$action = (string) request_value('subscription_action', '');
$notes = trim((string) request_value('admin_notes', ''));

if ($action === 'approve') {
    $result = SubscriptionService::approve((int) request_value('request_id'), (int) $authUser['id'], $notes);
} elseif ($action === 'reject') {
    $result = SubscriptionService::reject((int) request_value('request_id'), (int) $authUser['id'], $notes);
} elseif ($action === 'manual_extend') {
    $result = SubscriptionService::manualExtend(
        (int) request_value('unit_id'),
        (string) request_value('duration_code'),
        (int) $authUser['id'],
        $notes
    );
} else {
    $result = ['success' => false, 'message' => 'Aksi subscription tidak valid.'];
}

json_response([
    'success' => $result['success'],
    'message' => $result['message'],
    'reloadSection' => $result['success'] ? 'settings' : null,
], $result['success'] ? 200 : 422);
