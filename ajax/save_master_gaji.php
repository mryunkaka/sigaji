<?php

require __DIR__ . '/../bootstrap/app.php';
$user = Auth::require();
verify_csrf();

$record = fetch_one(
    'SELECT mg.id
     FROM master_gaji mg
     JOIN users u ON u.id = mg.user_id
     WHERE mg.id = :id AND u.unit_id = :unit_id',
    ['id' => (int) request_value('id'), 'unit_id' => $user['unit_id']]
);

if (!$record) {
    json_response(['success' => false, 'message' => 'Master gaji tidak ditemukan.'], 404);
}

execute_query(
    'UPDATE master_gaji
     SET gaji_pokok = :gaji_pokok,
         tunjangan_bbm = :tunjangan_bbm,
         tunjangan_makan = :tunjangan_makan,
         tunjangan_jabatan = :tunjangan_jabatan,
         tunjangan_kehadiran = :tunjangan_kehadiran,
         tunjangan_lainnya = :tunjangan_lainnya,
         potongan_terlambat = :potongan_terlambat,
         pot_bpjs_jht = :pot_bpjs_jht,
         pot_bpjs_kes = :pot_bpjs_kes,
         updated_at = :updated_at
     WHERE id = :id',
    [
        'gaji_pokok' => (int) request_value('gaji_pokok', 0),
        'tunjangan_bbm' => (int) request_value('tunjangan_bbm', 0),
        'tunjangan_makan' => (int) request_value('tunjangan_makan', 0),
        'tunjangan_jabatan' => (int) request_value('tunjangan_jabatan', 0),
        'tunjangan_kehadiran' => (int) request_value('tunjangan_kehadiran', 0),
        'tunjangan_lainnya' => (int) request_value('tunjangan_lainnya', 0),
        'potongan_terlambat' => (int) request_value('potongan_terlambat', 0),
        'pot_bpjs_jht' => (int) request_value('pot_bpjs_jht', 0),
        'pot_bpjs_kes' => (int) request_value('pot_bpjs_kes', 0),
        'updated_at' => now_string(),
        'id' => (int) request_value('id'),
    ]
);
ActivityLogService::logCurrentUser(
    'update_master_gaji',
    'Memperbarui master gaji.',
    [
        'master_gaji_id' => (int) request_value('id'),
    ],
    'master_gaji',
    (int) request_value('id')
);

json_response([
    'success' => true,
    'message' => 'Master gaji berhasil diperbarui.',
    'reloadSection' => 'validasi',
    'closeModal' => request_value('modal_id'),
]);
