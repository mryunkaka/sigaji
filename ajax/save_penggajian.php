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

$reloadSection = (string) request_value('reload_section', 'validasi');
if (!in_array($reloadSection, ['gaji', 'validasi'], true)) {
    $reloadSection = 'validasi';
}

$userId = (int) request_value('user_id', $record['user_id']);
$selectedUser = fetch_one(
    'SELECT id
     FROM users
     WHERE id = :id
       AND unit_id = :unit_id
       AND role != :role
     LIMIT 1',
    ['id' => $userId, 'unit_id' => $user['unit_id'], 'role' => 'owner']
);

if (!$selectedUser) {
    json_response(['success' => false, 'message' => 'Karyawan payroll tidak valid pada unit aktif.'], 422);
}

$tanggalAwalGaji = (string) request_value('tanggal_awal_gaji', $record['tanggal_awal_gaji']);
$tanggalAkhirGaji = (string) request_value('tanggal_akhir_gaji', $record['tanggal_akhir_gaji']);

if ($tanggalAwalGaji === '' || $tanggalAkhirGaji === '') {
    json_response(['success' => false, 'message' => 'Tanggal awal dan tanggal akhir payroll wajib diisi.'], 422);
}

if ($tanggalAwalGaji > $tanggalAkhirGaji) {
    [$tanggalAwalGaji, $tanggalAkhirGaji] = [$tanggalAkhirGaji, $tanggalAwalGaji];
}

$gajiPokok = (int) request_value('gaji_pokok', $record['gaji_pokok']);
$tunjanganBbm = (int) request_value('tunjangan_bbm', $record['tunjangan_bbm']);
$tunjanganMakan = (int) request_value('tunjangan_makan', $record['tunjangan_makan'] ?? 0);
$tunjanganJabatan = (int) request_value('tunjangan_jabatan', $record['tunjangan_jabatan'] ?? 0);
$tunjanganKehadiran = (int) request_value('tunjangan_kehadiran', $record['tunjangan_kehadiran'] ?? 0);
$tunjanganLainnya = (int) request_value('tunjangan_lainnya', $record['tunjangan_lainnya']);
$lembur = (int) request_value('lembur', $record['lembur']);

$potonganKehadiran = (int) request_value('potongan_kehadiran', $record['potongan_kehadiran']);
$potonganIjin = (int) request_value('potongan_ijin', $record['potongan_ijin']);
$potonganKhusus = (int) request_value('potongan_khusus', $record['potongan_khusus']);
$potonganTerlambat = (int) request_value('potongan_terlambat', $record['potongan_terlambat']);
$potBpjsJht = (int) request_value('pot_bpjs_jht', $record['pot_bpjs_jht'] ?? 0);
$potBpjsKes = (int) request_value('pot_bpjs_kes', $record['pot_bpjs_kes'] ?? 0);

$gajiKotor = $gajiPokok + $tunjanganBbm + $tunjanganMakan + $tunjanganJabatan + $tunjanganKehadiran + $tunjanganLainnya + $lembur;
$totalPotongan = $potonganKehadiran + $potonganIjin + $potonganKhusus + $potonganTerlambat + $potBpjsJht + $potBpjsKes;
$gajiBersih = $gajiKotor - $totalPotongan;

execute_query(
    'UPDATE penggajian
     SET user_id = :user_id,
         tanggal_awal_gaji = :tanggal_awal_gaji,
         tanggal_akhir_gaji = :tanggal_akhir_gaji,
         gaji_pokok = :gaji_pokok,
         gaji_kotor = :gaji_kotor,
         gaji_bersih = :gaji_bersih,
         tunjangan_bbm = :tunjangan_bbm,
         tunjangan_makan = :tunjangan_makan,
         tunjangan_jabatan = :tunjangan_jabatan,
         tunjangan_kehadiran = :tunjangan_kehadiran,
         tunjangan_lainnya = :tunjangan_lainnya,
         lembur = :lembur,
         potongan_kehadiran = :potongan_kehadiran,
         potongan_ijin = :potongan_ijin,
         potongan_khusus = :potongan_khusus,
         potongan_terlambat = :potongan_terlambat,
         pot_bpjs_jht = :pot_bpjs_jht,
         pot_bpjs_kes = :pot_bpjs_kes,
         total_potongan = :total_potongan,
         updated_at = :updated_at
     WHERE id = :id',
    [
        'user_id' => $userId,
        'tanggal_awal_gaji' => $tanggalAwalGaji,
        'tanggal_akhir_gaji' => $tanggalAkhirGaji,
        'gaji_pokok' => $gajiPokok,
        'gaji_kotor' => $gajiKotor,
        'gaji_bersih' => $gajiBersih,
        'tunjangan_bbm' => $tunjanganBbm,
        'tunjangan_makan' => $tunjanganMakan,
        'tunjangan_jabatan' => $tunjanganJabatan,
        'tunjangan_kehadiran' => $tunjanganKehadiran,
        'tunjangan_lainnya' => $tunjanganLainnya,
        'lembur' => $lembur,
        'potongan_kehadiran' => $potonganKehadiran,
        'potongan_ijin' => $potonganIjin,
        'potongan_khusus' => $potonganKhusus,
        'potongan_terlambat' => $potonganTerlambat,
        'pot_bpjs_jht' => $potBpjsJht,
        'pot_bpjs_kes' => $potBpjsKes,
        'total_potongan' => $totalPotongan,
        'updated_at' => now_string(),
        'id' => (int) request_value('id'),
    ]
);

json_response([
    'success' => true,
    'message' => 'Payroll berhasil diperbarui.',
    'reloadSection' => $reloadSection,
    'closeModal' => request_value('modal_id'),
]);
