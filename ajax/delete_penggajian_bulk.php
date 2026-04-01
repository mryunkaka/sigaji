<?php

require __DIR__ . '/../bootstrap/app.php';

try {
    $user = Auth::require();
    verify_csrf();

    $reloadSection = (string) request_value('reload_section', 'gaji');
    if (!in_array($reloadSection, ['dashboard', 'gaji', 'validasi'], true)) {
        $reloadSection = 'gaji';
    }

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

        if ($reloadSection === 'validasi') {
            $ownedRows = fetch_all(
                'SELECT p.id
                 FROM penggajian p
                 JOIN users u ON u.id = p.user_id
                 WHERE u.unit_id = :unit_id' . $searchSql,
                $params
            );
        } else {
            $month = (int) ($selectionParams['month'] ?? 0);
            $year = (int) ($selectionParams['year'] ?? 0);
            $period = ($month >= 1 && $month <= 12 && $year >= 2000)
                ? closing_period_range_from_month_year($month, $year)
                : closing_period_filter_state();
            $params['start'] = (string) $period['start'];
            $params['end'] = (string) $period['end'];

            $ownedRows = fetch_all(
                'SELECT p.id
                 FROM penggajian p
                 JOIN users u ON u.id = p.user_id
                 WHERE u.unit_id = :unit_id
                   AND p.tanggal_awal_gaji BETWEEN :start AND :end' . $searchSql,
                $params
            );
        }

        $ownedIds = array_map(static fn(array $row): int => (int) $row['id'], $ownedRows);
        if ($excludedIds !== []) {
            $ownedIds = array_values(array_diff($ownedIds, $excludedIds));
        }
    } else {
        $ids = request_int_list('ids');

        if ($ids === []) {
            json_response(['success' => false, 'message' => 'Tidak ada data penggajian yang dipilih.'], 422);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge($ids, [$user['unit_id']]);

        $ownedRows = fetch_all(
            "SELECT p.id
             FROM penggajian p
             JOIN users u ON u.id = p.user_id
             WHERE p.id IN ($placeholders) AND u.unit_id = ?",
            $params
        );

        $ownedIds = array_map(static fn(array $row): int => (int) $row['id'], $ownedRows);
    }

    if ($ownedIds === []) {
        json_response(['success' => false, 'message' => 'Data penggajian tidak ditemukan pada unit aktif.'], 404);
    }

    $deletePlaceholders = implode(',', array_fill(0, count($ownedIds), '?'));
    execute_query("DELETE FROM penggajian WHERE id IN ($deletePlaceholders)", $ownedIds);
    ActivityLogService::logCurrentUser(
        'delete_penggajian_bulk',
        'Menghapus payroll secara massal.',
        [
            'total_deleted' => count($ownedIds),
            'penggajian_ids' => $ownedIds,
        ],
        'penggajian',
        implode(',', $ownedIds)
    );

    json_response([
        'success' => true,
        'message' => count($ownedIds) . ' data penggajian berhasil dihapus permanen.',
        'reloadSection' => $reloadSection,
    ]);
} catch (Throwable $e) {
    json_response([
        'success' => false,
        'message' => 'Hapus penggajian massal gagal diproses: ' . $e->getMessage(),
    ], 500);
}
