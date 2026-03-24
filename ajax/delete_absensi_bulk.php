<?php

require __DIR__ . '/../bootstrap/app.php';
$user = Auth::require();
verify_csrf();

$ids = array_values(array_filter(array_map('intval', explode(',', (string) request_value('ids', '')))));

if ($ids === []) {
    json_response(['success' => false, 'message' => 'Tidak ada data absensi yang dipilih.'], 422);
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$params = array_merge($ids, [$user['unit_id']]);

$ownedRows = fetch_all(
    "SELECT a.id
     FROM absensi a
     JOIN users u ON u.id = a.user_id
     WHERE a.id IN ($placeholders) AND u.unit_id = ?",
    $params
);

$ownedIds = array_map(static fn(array $row): int => (int) $row['id'], $ownedRows);

if ($ownedIds === []) {
    json_response(['success' => false, 'message' => 'Data absensi tidak ditemukan pada unit aktif.'], 404);
}

$deletePlaceholders = implode(',', array_fill(0, count($ownedIds), '?'));
execute_query("DELETE FROM absensi WHERE id IN ($deletePlaceholders)", $ownedIds);

json_response([
    'success' => true,
    'message' => count($ownedIds) . ' data absensi berhasil dihapus permanen.',
    'reloadSection' => 'absensi',
]);
