<?php

require __DIR__ . '/../bootstrap/app.php';

try {
    $authUser = Auth::require();
    verify_csrf();

    $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) request_value('ids', ''))))));

    if ($ids === []) {
        json_response(['success' => false, 'message' => 'Tidak ada unit yang dipilih.'], 422);
    }

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

    if ($deletableIds === []) {
        json_response([
            'success' => false,
            'message' => 'Unit yang dipilih tidak bisa dihapus. Pastikan bukan unit aktif dan tidak memiliki user.',
        ], 422);
    }

    $deletePlaceholders = implode(',', array_fill(0, count($deletableIds), '?'));
    execute_query("DELETE FROM units WHERE id IN ($deletePlaceholders)", $deletableIds);

    $skippedCount = count($ids) - count($deletableIds);
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
