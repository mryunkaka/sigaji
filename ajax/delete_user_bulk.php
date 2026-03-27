<?php

require __DIR__ . '/../bootstrap/app.php';

try {
    $authUser = Auth::require();
    verify_csrf();

    $ids = array_values(array_filter(array_map('intval', explode(',', (string) request_value('ids', '')))));

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

    if ($ownedIds === []) {
        json_response(['success' => false, 'message' => 'User yang dipilih tidak bisa dihapus.'], 422);
    }

    $deletePlaceholders = implode(',', array_fill(0, count($ownedIds), '?'));
    execute_query("DELETE FROM users WHERE id IN ($deletePlaceholders)", $ownedIds);

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
