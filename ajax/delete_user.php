<?php

require __DIR__ . '/../bootstrap/app.php';
$authUser = Auth::require();
verify_csrf();

$id = (int) request_value('id');
$record = fetch_one('SELECT * FROM users WHERE id = :id AND unit_id = :unit_id LIMIT 1', ['id' => $id, 'unit_id' => $authUser['unit_id']]);

if (!$record) {
    json_response(['success' => false, 'message' => 'User tidak ditemukan pada unit aktif.'], 404);
}

if ((int) $record['id'] === (int) $authUser['id']) {
    json_response(['success' => false, 'message' => 'User yang sedang login tidak bisa dihapus.'], 422);
}

execute_query('DELETE FROM users WHERE id = :id', ['id' => $id]);

json_response([
    'success' => true,
    'message' => 'User berhasil dihapus.',
    'reloadSection' => 'users',
    'closeModal' => request_value('modal_id'),
]);
