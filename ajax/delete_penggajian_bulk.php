<?php

require __DIR__ . '/../bootstrap/app.php';

try {
    $user = Auth::require();
    verify_csrf();

    $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) request_value('ids', ''))))));

    if ($ids === []) {
        json_response(['success' => false, 'message' => 'Tidak ada data penggajian yang dipilih.'], 422);
    }

    $reloadSection = (string) request_value('reload_section', 'gaji');
    if (!in_array($reloadSection, ['dashboard', 'gaji', 'validasi'], true)) {
        $reloadSection = 'gaji';
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

    if ($ownedIds === []) {
        json_response(['success' => false, 'message' => 'Data penggajian tidak ditemukan pada unit aktif.'], 404);
    }

    $deletePlaceholders = implode(',', array_fill(0, count($ownedIds), '?'));
    execute_query("DELETE FROM penggajian WHERE id IN ($deletePlaceholders)", $ownedIds);

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
