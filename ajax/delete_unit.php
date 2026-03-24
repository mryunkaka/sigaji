<?php

require __DIR__ . '/../bootstrap/app.php';
$authUser = Auth::require();
verify_csrf();

$id = (int) request_value('id');

if ($id === (int) $authUser['unit_id']) {
    json_response(['success' => false, 'message' => 'Unit aktif tidak bisa dihapus saat sedang digunakan.'], 422);
}

$hasUsers = fetch_one('SELECT id FROM users WHERE unit_id = :unit_id LIMIT 1', ['unit_id' => $id]);
if ($hasUsers) {
    json_response(['success' => false, 'message' => 'Unit yang masih memiliki user tidak bisa dihapus.'], 422);
}

execute_query('DELETE FROM units WHERE id = :id', ['id' => $id]);

json_response([
    'success' => true,
    'message' => 'Unit berhasil dihapus.',
    'reloadSection' => 'units',
    'closeModal' => request_value('modal_id'),
]);
