<?php

require __DIR__ . '/../bootstrap/app.php';
$authUser = Auth::require();
verify_csrf();

$unit = fetch_one(
    'SELECT id
     FROM units
     WHERE id = :id
     LIMIT 1',
    ['id' => $authUser['unit_id']]
);

if (!$unit) {
    json_response(['success' => false, 'message' => 'Unit aktif tidak ditemukan.'], 404);
}

$tolerance = max(0, (int) request_value('toleransi_terlambat_menit', 0));

execute_query(
    'UPDATE units
     SET toleransi_terlambat_menit = :toleransi_terlambat_menit,
         updated_at = :updated_at
     WHERE id = :id',
    [
        'toleransi_terlambat_menit' => $tolerance,
        'updated_at' => now_string(),
        'id' => $authUser['unit_id'],
    ]
);

json_response([
    'success' => true,
    'message' => 'Setting toleransi terlambat berhasil diperbarui.',
    'reloadSection' => 'settings',
]);
