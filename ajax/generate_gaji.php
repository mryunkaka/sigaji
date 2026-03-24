<?php

require __DIR__ . '/../bootstrap/app.php';
$user = Auth::require();
verify_csrf();

$tanggalAwal = request_value('tanggal_awal');
$tanggalAkhir = request_value('tanggal_akhir');

if (!$tanggalAwal || !$tanggalAkhir) {
    json_response(['success' => false, 'message' => 'Tanggal awal dan akhir wajib diisi.'], 422);
}

$users = fetch_all(
    'SELECT * FROM users WHERE unit_id = :unit_id AND role != "owner" ORDER BY name',
    ['unit_id' => $user['unit_id']]
);

$processed = 0;
foreach ($users as $employee) {
    $hasAttendance = fetch_one(
        'SELECT id FROM absensi WHERE user_id = :user_id AND tanggal BETWEEN :start AND :end LIMIT 1',
        ['user_id' => $employee['id'], 'start' => $tanggalAwal, 'end' => $tanggalAkhir]
    );

    if (!$hasAttendance) {
        continue;
    }

    $exists = fetch_one(
        'SELECT id FROM penggajian WHERE user_id = :user_id AND tanggal_awal_gaji = :start AND tanggal_akhir_gaji = :end LIMIT 1',
        ['user_id' => $employee['id'], 'start' => $tanggalAwal, 'end' => $tanggalAkhir]
    );

    if ($exists) {
        continue;
    }

    $salary = PayrollService::calculateSalary((int) $employee['id'], $tanggalAwal, $tanggalAkhir);

    execute_query(
        'INSERT INTO penggajian (user_id, tanggal_awal_gaji, tanggal_akhir_gaji, gaji_bersih, gaji_pokok, gaji_kotor, tunjangan_bbm, tunjangan_makan, tunjangan_jabatan, tunjangan_kehadiran, tunjangan_lainnya, lembur, potongan_kehadiran, potongan_khusus, potongan_ijin, potongan_terlambat, pot_bpjs_jht, pot_bpjs_kes, total_potongan, created_at, updated_at)
         VALUES (:user_id, :tanggal_awal_gaji, :tanggal_akhir_gaji, :gaji_bersih, :gaji_pokok, :gaji_kotor, :tunjangan_bbm, :tunjangan_makan, :tunjangan_jabatan, :tunjangan_kehadiran, :tunjangan_lainnya, :lembur, :potongan_kehadiran, :potongan_khusus, :potongan_ijin, :potongan_terlambat, :pot_bpjs_jht, :pot_bpjs_kes, :total_potongan, :created_at, :updated_at)',
        [
            'user_id' => $salary['user_id'],
            'tanggal_awal_gaji' => $salary['tanggal_awal_gaji'],
            'tanggal_akhir_gaji' => $salary['tanggal_akhir_gaji'],
            'gaji_bersih' => $salary['gaji_bersih'],
            'gaji_pokok' => $salary['gaji_pokok'],
            'gaji_kotor' => $salary['gaji_kotor'],
            'tunjangan_bbm' => $salary['tunjangan_bbm'],
            'tunjangan_makan' => $salary['tunjangan_makan'],
            'tunjangan_jabatan' => $salary['tunjangan_jabatan'],
            'tunjangan_kehadiran' => $salary['tunjangan_kehadiran'],
            'tunjangan_lainnya' => $salary['tunjangan_lainnya'],
            'lembur' => $salary['lembur'],
            'potongan_kehadiran' => $salary['potongan_kehadiran'],
            'potongan_khusus' => $salary['potongan_khusus'],
            'potongan_ijin' => $salary['potongan_ijin'],
            'potongan_terlambat' => $salary['potongan_terlambat'],
            'pot_bpjs_jht' => $salary['pot_bpjs_jht'],
            'pot_bpjs_kes' => $salary['pot_bpjs_kes'],
            'total_potongan' => $salary['total_potongan'],
            'created_at' => now_string(),
            'updated_at' => now_string(),
        ]
    );

    $processed++;
}

if ($processed === 0) {
    json_response(['success' => false, 'message' => 'Tidak ada payroll baru yang dapat dibuat untuk periode tersebut.'], 422);
}

json_response([
    'success' => true,
    'message' => "Generate penggajian selesai untuk {$processed} karyawan.",
    'reloadSection' => 'gaji',
]);
