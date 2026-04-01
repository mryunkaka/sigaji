<?php

require __DIR__ . '/../bootstrap/app.php';
$user = Auth::require();
verify_csrf();

$selectionMode = (string) request_value('selection_mode', 'ids');
$selectionParams = request_json_value('selection_params');
$excludedIds = request_int_list('excluded_ids');
$ownedIds = [];

if ($selectionMode === 'all_filtered') {
    $search = trim((string) ($selectionParams['search'] ?? ''));
    $month = (int) ($selectionParams['month'] ?? 0);
    $year = (int) ($selectionParams['year'] ?? 0);
    $period = ($month >= 1 && $month <= 12 && $year >= 2000)
        ? closing_period_range_from_month_year($month, $year)
        : closing_period_filter_state();
    $startDate = (string) $period['start'];
    $endDate = (string) $period['end'];
    $searchSql = '';
    $params = [
        'unit_id' => $user['unit_id'],
        'start' => $startDate,
        'end' => $endDate,
    ];

    if ($search !== '') {
        $searchSql = ' AND u.name LIKE :search ';
        $params['search'] = '%' . $search . '%';
    }

    $ownedRows = fetch_all(
        'SELECT a.id
         FROM absensi a
         JOIN users u ON u.id = a.user_id
         WHERE u.unit_id = :unit_id
           AND a.tanggal BETWEEN :start AND :end' . $searchSql,
        $params
    );

    $ownedIds = array_map(static fn(array $row): int => (int) $row['id'], $ownedRows);
    if ($excludedIds !== []) {
        $ownedIds = array_values(array_diff($ownedIds, $excludedIds));
    }
} else {
    $ids = request_int_list('ids');

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
}

if ($ownedIds === []) {
    json_response(['success' => false, 'message' => 'Data absensi tidak ditemukan pada unit aktif.'], 404);
}

$deletePlaceholders = implode(',', array_fill(0, count($ownedIds), '?'));
    execute_query("DELETE FROM absensi WHERE id IN ($deletePlaceholders)", $ownedIds);
    ActivityLogService::logCurrentUser(
        'delete_absensi_bulk',
        'Menghapus absensi secara massal.',
        [
            'total_deleted' => count($ownedIds),
            'absensi_ids' => $ownedIds,
        ],
        'absensi',
        implode(',', $ownedIds)
    );

    json_response([
    'success' => true,
    'message' => count($ownedIds) . ' data absensi berhasil dihapus permanen.',
    'reloadSection' => 'absensi',
]);
