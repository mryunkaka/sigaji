<?php

require __DIR__ . '/../bootstrap/app.php';

try {
    $authUser = Auth::require();
    verify_csrf();

    $selectionMode = (string) request_value('selection_mode', 'ids');
    $selectionParams = request_json_value('selection_params');
    $excludedIds = request_int_list('excluded_ids');
    $ownedIds = [];

    if ($selectionMode === 'all_filtered') {
        $search = trim((string) ($selectionParams['search'] ?? ''));
        $searchSql = '';
        $params = [
            'unit_id' => $authUser['unit_id'],
            'auth_id' => $authUser['id'],
        ];

        if ($search !== '') {
            $searchSql = ' AND name LIKE :search ';
            $params['search'] = '%' . $search . '%';
        }

        $ownedRows = fetch_all(
            'SELECT id
             FROM users
             WHERE unit_id = :unit_id
               AND id != :auth_id' . $searchSql,
            $params
        );

        $ownedIds = array_map(static fn(array $row): int => (int) $row['id'], $ownedRows);
        if ($excludedIds !== []) {
            $ownedIds = array_values(array_diff($ownedIds, $excludedIds));
        }
    } else {
        $ids = request_int_list('ids');

        if ($ids === []) {
            json_response(['success' => false, 'message' => 'Tidak ada user yang dipilih.'], 422);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge($ids, [$authUser['unit_id'], $authUser['id']]);

        $ownedRows = fetch_all(
            "SELECT id
             FROM users
             WHERE id IN ($placeholders) AND unit_id = ? AND id != ?",
            $params
        );

        $ownedIds = array_map(static fn(array $row): int => (int) $row['id'], $ownedRows);
    }

    if ($ownedIds === []) {
        json_response(['success' => false, 'message' => 'User yang dipilih tidak bisa dihapus.'], 422);
    }

    $deletePlaceholders = implode(',', array_fill(0, count($ownedIds), '?'));
    execute_query("DELETE FROM users WHERE id IN ($deletePlaceholders)", $ownedIds);
    ActivityLogService::logCurrentUser(
        'delete_user_bulk',
        'Menghapus user secara massal.',
        [
            'total_deleted' => count($ownedIds),
            'user_ids' => $ownedIds,
        ],
        'user',
        implode(',', $ownedIds)
    );

    json_response([
        'success' => true,
        'message' => count($ownedIds) . ' user berhasil dihapus permanen.',
        'reloadSection' => 'users',
    ]);
} catch (Throwable $e) {
    json_response([
        'success' => false,
        'message' => 'Hapus user massal gagal diproses: ' . $e->getMessage(),
    ], 500);
}
