<?php

require __DIR__ . '/../bootstrap/app.php';
$user = Auth::require();
verify_csrf();

$record = fetch_one(
    'SELECT p.*
     FROM penggajian p
     JOIN users u ON u.id = p.user_id
     WHERE p.id = :id AND u.unit_id = :unit_id',
    ['id' => (int) request_value('id'), 'unit_id' => $user['unit_id']]
);

if (!$record) {
    json_response(['success' => false, 'message' => 'Data penggajian tidak ditemukan.'], 404);
}

$gajiPokok = (int) request_value('gaji_pokok', $record['gaji_pokok']);
$tunjanganBbm = (int) request_value('tunjangan_bbm', $record['tunjangan_bbm']);
$tunjanganMakan = (int) ($record['tunjangan_makan'] ?? 0);
$tunjanganJabatan = (int) ($record['tunjangan_jabatan'] ?? 0);
$tunjanganKehadiran = (int) ($record['tunjangan_kehadiran'] ?? 0);
$tunjanganLainnya = (int) request_value('tunjangan_lainnya', $record['tunjangan_lainnya']);
$lembur = (int) request_value('lembur', $record['lembur']);

$potonganKehadiran = (int) request_value('potongan_kehadiran', $record['potongan_kehadiran']);
$potonganIjin = (int) request_value('potongan_ijin', $record['potongan_ijin']);
$potonganKhusus = (int) request_value('potongan_khusus', $record['potongan_khusus']);
$potonganTerlambat = (int) request_value('potongan_terlambat', $record['potongan_terlambat']);
$potBpjsJht = (int) ($record['pot_bpjs_jht'] ?? 0);
$potBpjsKes = (int) ($record['pot_bpjs_kes'] ?? 0);

$gajiKotor = $gajiPokok + $tunjanganBbm + $tunjanganMakan + $tunjanganJabatan + $tunjanganKehadiran + $tunjanganLainnya + $lembur;
$totalPotongan = $potonganKehadiran + $potonganIjin + $potonganKhusus + $potonganTerlambat + $potBpjsJht + $potBpjsKes;
$gajiBersih = $gajiKotor - $totalPotongan;

execute_query(
    'UPDATE penggajian
     SET gaji_pokok = :gaji_pokok,
         gaji_kotor = :gaji_kotor,
         gaji_bersih = :gaji_bersih,
         tunjangan_bbm = :tunjangan_bbm,
         tunjangan_lainnya = :tunjangan_lainnya,
         lembur = :lembur,
         potongan_kehadiran = :potongan_kehadiran,
         potongan_ijin = :potongan_ijin,
         potongan_khusus = :potongan_khusus,
         potongan_terlambat = :potongan_terlambat,
         total_potongan = :total_potongan,
         updated_at = :updated_at
     WHERE id = :id',
    [
        'gaji_pokok' => $gajiPokok,
        'gaji_kotor' => $gajiKotor,
        'gaji_bersih' => $gajiBersih,
        'tunjangan_bbm' => $tunjanganBbm,
        'tunjangan_lainnya' => $tunjanganLainnya,
        'lembur' => $lembur,
        'potongan_kehadiran' => $potonganKehadiran,
        'potongan_ijin' => $potonganIjin,
        'potongan_khusus' => $potonganKhusus,
        'potongan_terlambat' => $potonganTerlambat,
        'total_potongan' => $totalPotongan,
        'updated_at' => now_string(),
        'id' => (int) request_value('id'),
    ]
);

json_response([
    'success' => true,
    'message' => 'Payroll berhasil divalidasi.',
    'reloadSection' => 'validasi',
    'closeModal' => request_value('modal_id'),
]);
