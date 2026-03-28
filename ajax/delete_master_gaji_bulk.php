<?php

require __DIR__ . '/../bootstrap/app.php';

try {
    $user = Auth::require();
    verify_csrf();

    $selectionMode = (string) request_value('selection_mode', 'ids');
    $selectionParams = request_json_value('selection_params');
    $excludedIds = request_int_list('excluded_ids');
    $ownedIds = [];

    if ($selectionMode === 'all_filtered') {
        $search = trim((string) ($selectionParams['search'] ?? ''));
        $searchSql = '';
        $params = ['unit_id' => $user['unit_id']];

        if ($search !== '') {
            $searchSql = ' AND u.name LIKE :search ';
            $params['search'] = '%' . $search . '%';
        }

        $ownedRows = fetch_all(
            'SELECT mg.id
             FROM master_gaji mg
             JOIN users u ON u.id = mg.user_id
             WHERE u.unit_id = :unit_id' . $searchSql,
            $params
        );

        $ownedIds = array_map(static fn(array $row): int => (int) $row['id'], $ownedRows);
        if ($excludedIds !== []) {
            $ownedIds = array_values(array_diff($ownedIds, $excludedIds));
        }
    } else {
        $ids = request_int_list('ids');

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
    }

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
