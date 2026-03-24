<?php

require __DIR__ . '/../bootstrap/app.php';
$user = Auth::require();
verify_csrf();

$id = (int) request_value('id', 0);
$userId = (int) request_value('user_id', 0);

$employee = fetch_one(
    'SELECT u.id, u.jabatan, COALESCE(mg.potongan_terlambat, 1000) AS potongan_terlambat
     FROM users u
     LEFT JOIN master_gaji mg ON mg.user_id = u.id
     WHERE u.id = :id AND u.unit_id = :unit_id AND u.role != :role
     LIMIT 1',
    ['id' => $userId, 'unit_id' => $user['unit_id'], 'role' => 'owner']
);

if (!$employee) {
    json_response(['success' => false, 'message' => 'Karyawan tidak ditemukan pada unit aktif.'], 422);
}

$jamMasuk = request_value('jam_masuk') !== '' ? request_value('jam_masuk') . ':00' : null;
$jamKeluar = request_value('jam_keluar') !== '' ? request_value('jam_keluar') . ':00' : null;
$shift = request_value('shift') !== '' ? request_value('shift') : null;
$jabatan = strtolower((string) ($employee['jabatan'] ?? ''));

$jamMasukSah = match (true) {
    $jabatan !== 'security' && $shift === '1' => '08:00:00',
    $jabatan !== 'security' && $shift === '2' => '16:00:00',
    $jabatan !== 'security' && $shift === '3' => '23:00:00',
    $jabatan === 'security' && $shift === '1' => '07:00:00',
    $jabatan === 'security' && $shift === '2' => '15:00:00',
    $jabatan === 'security' && $shift === '3' => '00:00:00',
    default => null,
};

$totalTerlambat = 0;
if ($jamMasuk && $jamMasukSah) {
    $actual = strtotime('1970-01-01 ' . $jamMasuk);
    $target = strtotime('1970-01-01 ' . $jamMasukSah);
    if ($actual !== false && $target !== false && $actual > $target) {
        $totalTerlambat = (int) round(($actual - $target) / 60);
    }
}

$jumlahPotongan = $totalTerlambat * (int) round((float) ($employee['potongan_terlambat'] ?? 1000));

if ($id > 0) {
    $record = fetch_one(
        'SELECT a.id
         FROM absensi a
         JOIN users u ON u.id = a.user_id
         WHERE a.id = :id AND u.unit_id = :unit_id',
        ['id' => $id, 'unit_id' => $user['unit_id']]
    );

    if (!$record) {
        json_response(['success' => false, 'message' => 'Data absensi tidak ditemukan.'], 404);
    }

    execute_query(
        'UPDATE absensi
         SET user_id = :user_id,
             tanggal = :tanggal,
             shift = :shift,
             status = :status,
             jam_masuk = :jam_masuk,
             jam_keluar = :jam_keluar,
             total_menit_terlambat = :total_menit_terlambat,
             jumlah_potongan = :jumlah_potongan,
             lembur = :lembur,
             keterangan_lembur = :keterangan_lembur,
             potongan_kehadiran = :potongan_kehadiran,
             keterangan_kehadiran = :keterangan_kehadiran,
             potongan_ijin = :potongan_ijin,
             keterangan_ijin = :keterangan_ijin,
             potongan_khusus = :potongan_khusus,
             keterangan_potongan = :keterangan_potongan,
             keterangan_khusus = :keterangan_khusus,
             updated_at = :updated_at
         WHERE id = :id',
        [
            'user_id' => $userId,
            'tanggal' => request_value('tanggal'),
            'shift' => $shift,
            'status' => request_value('status'),
            'jam_masuk' => $jamMasuk,
            'jam_keluar' => $jamKeluar,
            'total_menit_terlambat' => $totalTerlambat,
            'jumlah_potongan' => $jumlahPotongan,
            'lembur' => (int) request_value('lembur', 0),
            'keterangan_lembur' => request_value('keterangan_lembur', ''),
            'potongan_kehadiran' => (int) request_value('potongan_kehadiran', 0),
            'keterangan_kehadiran' => request_value('keterangan_kehadiran', ''),
            'potongan_ijin' => (int) request_value('potongan_ijin', 0),
            'keterangan_ijin' => request_value('keterangan_ijin', ''),
            'potongan_khusus' => (int) request_value('potongan_khusus', 0),
            'keterangan_potongan' => request_value('keterangan_potongan', ''),
            'keterangan_khusus' => request_value('keterangan_khusus', ''),
            'updated_at' => now_string(),
            'id' => $id,
        ]
    );

    json_response([
        'success' => true,
        'message' => 'Absensi berhasil diperbarui.',
        'reloadSection' => 'absensi',
        'closeModal' => request_value('modal_id'),
    ]);
}

$inserted = execute_query(
    'INSERT INTO absensi (
        user_id, tanggal, shift, status, jam_masuk, jam_keluar, total_menit_terlambat, jumlah_potongan,
        lembur, keterangan_lembur, potongan_kehadiran, keterangan_kehadiran, potongan_ijin, keterangan_ijin,
        potongan_khusus, keterangan_potongan, keterangan_khusus, created_at, updated_at
    ) VALUES (
        :user_id, :tanggal, :shift, :status, :jam_masuk, :jam_keluar, :total_menit_terlambat, :jumlah_potongan,
        :lembur, :keterangan_lembur, :potongan_kehadiran, :keterangan_kehadiran, :potongan_ijin, :keterangan_ijin,
        :potongan_khusus, :keterangan_potongan, :keterangan_khusus, :created_at, :updated_at
    )',
    [
        'user_id' => $userId,
        'tanggal' => request_value('tanggal'),
        'shift' => $shift,
        'status' => request_value('status'),
        'jam_masuk' => $jamMasuk,
        'jam_keluar' => $jamKeluar,
        'total_menit_terlambat' => $totalTerlambat,
        'jumlah_potongan' => $jumlahPotongan,
        'lembur' => (int) request_value('lembur', 0),
        'keterangan_lembur' => request_value('keterangan_lembur', ''),
        'potongan_kehadiran' => (int) request_value('potongan_kehadiran', 0),
        'keterangan_kehadiran' => request_value('keterangan_kehadiran', ''),
        'potongan_ijin' => (int) request_value('potongan_ijin', 0),
        'keterangan_ijin' => request_value('keterangan_ijin', ''),
        'potongan_khusus' => (int) request_value('potongan_khusus', 0),
        'keterangan_potongan' => request_value('keterangan_potongan', ''),
        'keterangan_khusus' => request_value('keterangan_khusus', ''),
        'created_at' => now_string(),
        'updated_at' => now_string(),
    ]
);

json_response([
    'success' => $inserted,
    'message' => $inserted ? 'Absensi manual berhasil ditambahkan.' : 'Gagal menambahkan absensi manual.',
    'reloadSection' => $inserted ? 'absensi' : null,
    'closeModal' => $inserted ? request_value('modal_id') : null,
], $inserted ? 200 : 500);
