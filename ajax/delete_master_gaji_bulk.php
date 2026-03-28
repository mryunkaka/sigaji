<?php

require __DIR__ . '/../bootstrap/app.php';

try {
    $user = Auth::require();
    verify_csrf();

    $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) request_value('ids', ''))))));

    if ($ids === []) {
        json_response(['success' => false, 'message' => 'Tidak ada master gaji yang dipilih.'], 422);
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $params = array_merge($ids, [$user['unit_id']]);

    $ownedRows = fetch_all(
        "SELECT mg.id
         FROM master_gaji mg
         JOIN users u ON u.id = mg.user_id
         WHERE mg.id IN ($placeholders) AND u.unit_id = ?",
        $params
    );

    $ownedIds = array_map(static fn(array $row): int => (int) $row['id'], $ownedRows);

    if ($ownedIds === []) {
        json_response(['success' => false, 'message' => 'Master gaji tidak ditemukan pada unit aktif.'], 404);
    }

    $deletePlaceholders = implode(',', array_fill(0, count($ownedIds), '?'));
    execute_query("DELETE FROM master_gaji WHERE id IN ($deletePlaceholders)", $ownedIds);

    json_response([
        'success' => true,
        'message' => count($ownedIds) . ' master gaji berhasil dihapus permanen.',
        'reloadSection' => 'validasi',
    ]);
} catch (Throwable $e) {
    json_response([
        'success' => false,
        'message' => 'Hapus master gaji massal gagal diproses: ' . $e->getMessage(),
    ], 500);
}
