<?php

require __DIR__ . '/../bootstrap/app.php';

try {
    $authUser = Auth::require();
    verify_csrf();

    $selectionMode = (string) request_value('selection_mode', 'ids');
    $selectionParams = request_json_value('selection_params');
    $excludedIds = request_int_list('excluded_ids');
    $deletableIds = [];
    $requestedIds = [];

    if ($selectionMode === 'all_filtered') {
        $search = trim((string) ($selectionParams['search'] ?? ''));
        $searchSql = '';
        $params = ['active_unit_id' => $authUser['unit_id']];

        if ($search !== '') {
            $searchSql = ' AND un.nama_unit LIKE :search ';
            $params['search'] = '%' . $search . '%';
        }

        $deletableRows = fetch_all(
            'SELECT un.id
             FROM units un
             LEFT JOIN users u ON u.unit_id = un.id
             WHERE un.id != :active_unit_id' . $searchSql . '
             GROUP BY un.id
             HAVING COUNT(u.id) = 0',
            $params
        );

        $deletableIds = array_map(static fn(array $row): int => (int) $row['id'], $deletableRows);
        if ($excludedIds !== []) {
            $deletableIds = array_values(array_diff($deletableIds, $excludedIds));
        }
        $requestedIds = $deletableIds;
    } else {
        $ids = request_int_list('ids');

        if ($ids === []) {
            json_response(['success' => false, 'message' => 'Tidak ada unit yang dipilih.'], 422);
        }

        $requestedIds = $ids;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$authUser['unit_id']], $ids);

        $deletableRows = fetch_all(
            "SELECT un.id
             FROM units un
             LEFT JOIN users u ON u.unit_id = un.id
             WHERE un.id != ? AND un.id IN ($placeholders)
             GROUP BY un.id
             HAVING COUNT(u.id) = 0",
            $params
        );

        $deletableIds = array_map(static fn(array $row): int => (int) $row['id'], $deletableRows);
    }

    if ($deletableIds === []) {
        json_response([
            'success' => false,
            'message' => 'Unit yang dipilih tidak bisa dihapus. Pastikan bukan unit aktif dan tidak memiliki user.',
        ], 422);
    }

    $deletePlaceholders = implode(',', array_fill(0, count($deletableIds), '?'));
    execute_query("DELETE FROM units WHERE id IN ($deletePlaceholders)", $deletableIds);
    ActivityLogService::logCurrentUser(
        'delete_unit_bulk',
        'Menghapus unit secara massal.',
        [
            'total_deleted' => count($deletableIds),
            'unit_ids' => $deletableIds,
        ],
        'unit',
        implode(',', $deletableIds)
    );

    $skippedCount = max(0, count($requestedIds) - count($deletableIds));
    $message = count($deletableIds) . ' unit berhasil dihapus permanen.';
    if ($skippedCount > 0) {
        $message .= ' ' . $skippedCount . ' unit dilewati karena sedang aktif, masih memiliki user, atau tidak ditemukan.';
    }

    json_response([
        'success' => true,
        'message' => $message,
        'reloadSection' => 'units',
    ]);
} catch (Throwable $e) {
    json_response([
        'success' => false,
        'message' => 'Hapus unit massal gagal diproses: ' . $e->getMessage(),
    ], 500);
}
