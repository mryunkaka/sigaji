<?php

require __DIR__ . '/../bootstrap/app.php';
$user = Auth::require();
verify_csrf();

$record = fetch_one(
    'SELECT a.id
     FROM absensi a
     JOIN users u ON u.id = a.user_id
     WHERE a.id = :id AND u.unit_id = :unit_id',
    ['id' => (int) request_value('id'), 'unit_id' => $user['unit_id']]
);

if (!$record) {
    json_response(['success' => false, 'message' => 'Data absensi tidak ditemukan.'], 404);
}

$totalTerlambat = max(0, (int) request_value('total_menit_terlambat', 0));
$master = fetch_one(
    'SELECT mg.potongan_terlambat
     FROM master_gaji mg
     JOIN absensi a ON a.user_id = mg.user_id
     WHERE a.id = :id
     LIMIT 1',
    ['id' => (int) request_value('id')]
) ?: ['potongan_terlambat' => 1000];

$jumlahPotongan = $totalTerlambat * (int) round((float) ($master['potongan_terlambat'] ?? 1000));

execute_query(
    'UPDATE absensi
     SET tanggal = :tanggal,
         shift = :shift,
         status = :status,
         jam_masuk = :jam_masuk,
         jam_keluar = :jam_keluar,
         total_menit_terlambat = :total_menit_terlambat,
         jumlah_potongan = :jumlah_potongan,
         lembur = :lembur,
         potongan_kehadiran = :potongan_kehadiran,
         potongan_ijin = :potongan_ijin,
         potongan_khusus = :potongan_khusus,
         keterangan_potongan = :keterangan_potongan,
         keterangan_khusus = :keterangan_khusus,
         updated_at = :updated_at
     WHERE id = :id',
    [
        'tanggal' => request_value('tanggal'),
        'shift' => request_value('shift') !== '' ? request_value('shift') : null,
        'status' => request_value('status'),
        'jam_masuk' => request_value('jam_masuk') !== '' ? request_value('jam_masuk') . ':00' : null,
        'jam_keluar' => request_value('jam_keluar') !== '' ? request_value('jam_keluar') . ':00' : null,
        'total_menit_terlambat' => $totalTerlambat,
        'jumlah_potongan' => $jumlahPotongan,
        'lembur' => (int) request_value('lembur', 0),
        'potongan_kehadiran' => (int) request_value('potongan_kehadiran', 0),
        'potongan_ijin' => (int) request_value('potongan_ijin', 0),
        'potongan_khusus' => (int) request_value('potongan_khusus', 0),
        'keterangan_potongan' => request_value('keterangan_potongan', ''),
        'keterangan_khusus' => request_value('keterangan_khusus', ''),
        'updated_at' => now_string(),
        'id' => (int) request_value('id'),
    ]
);

json_response([
    'success' => true,
    'message' => 'Absensi berhasil diperbarui.',
    'reloadSection' => 'absensi',
    'closeModal' => request_value('modal_id'),
]);
