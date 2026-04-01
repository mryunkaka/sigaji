<?php

require __DIR__ . '/../bootstrap/app.php';
$authUser = Auth::require();
verify_csrf();

$unit = fetch_one(
    'SELECT id
     FROM units
     WHERE id = :id
     LIMIT 1',
    ['id' => $authUser['unit_id']]
);

if (!$unit) {
    json_response(['success' => false, 'message' => 'Unit aktif tidak ditemukan.'], 404);
}

$tolerance = max(0, (int) request_value('toleransi_terlambat_menit', 0));
$startDay = max(1, min(31, (int) request_value('hari_mulai_periode', 26)));
$endDay = max(1, min(31, (int) request_value('hari_akhir_periode', 25)));
$globalShiftCodes = $_POST['global_shift_code'] ?? [];
$globalJamMasuk = $_POST['global_jam_masuk'] ?? [];
$globalJamKeluar = $_POST['global_jam_keluar'] ?? [];
$jabatanNames = $_POST['jabatan_rule_jabatan'] ?? [];
$jabatanShiftCodes = $_POST['jabatan_rule_shift'] ?? [];
$jabatanJamMasuk = $_POST['jabatan_rule_jam_masuk'] ?? [];
$jabatanJamKeluar = $_POST['jabatan_rule_jam_keluar'] ?? [];

$globalRules = [];
foreach ($globalShiftCodes as $index => $shiftCodeRaw) {
    $shiftCode = (int) $shiftCodeRaw;
    $jamMasuk = trim((string) ($globalJamMasuk[$index] ?? ''));
    $jamKeluar = trim((string) ($globalJamKeluar[$index] ?? ''));
    if ($shiftCode <= 0 || $jamMasuk === '' || $jamKeluar === '') {
        continue;
    }

    $globalRules[$shiftCode] = [
        'shift_code' => $shiftCode,
        'jam_masuk' => $jamMasuk . ':00',
        'jam_keluar' => $jamKeluar . ':00',
    ];
}

$jabatanRules = [];
foreach ($jabatanNames as $index => $jabatan) {
    $jabatan = strtoupper(trim((string) $jabatan));
    $shiftCode = (int) ($jabatanShiftCodes[$index] ?? 0);
    $jamMasuk = trim((string) ($jabatanJamMasuk[$index] ?? ''));
    $jamKeluar = trim((string) ($jabatanJamKeluar[$index] ?? ''));

    if ($jabatan === '' || $shiftCode <= 0 || $jamMasuk === '' || $jamKeluar === '') {
        continue;
    }

    $dedupeKey = strtolower($jabatan) . '|' . $shiftCode;
    $jabatanRules[$dedupeKey] = [
        'jabatan' => $jabatan,
        'shift_code' => $shiftCode,
        'jam_masuk' => $jamMasuk . ':00',
        'jam_keluar' => $jamKeluar . ':00',
    ];
}

try {
    execute_query(
        'UPDATE units
         SET toleransi_terlambat_menit = :toleransi_terlambat_menit,
             hari_mulai_periode = :hari_mulai_periode,
             hari_akhir_periode = :hari_akhir_periode,
             updated_at = :updated_at
         WHERE id = :id',
        [
            'toleransi_terlambat_menit' => $tolerance,
            'hari_mulai_periode' => $startDay,
            'hari_akhir_periode' => $endDay,
            'updated_at' => now_string(),
            'id' => $authUser['unit_id'],
        ]
    );

    ShiftService::replaceGlobalRules((int) $authUser['unit_id'], array_values($globalRules));
    ShiftService::replaceJabatanRules((int) $authUser['unit_id'], array_values($jabatanRules));
} catch (Throwable $exception) {
    json_response([
        'success' => false,
        'message' => 'Setting shift/periode belum lengkap di database. Jalankan migrasi SQL terbaru terlebih dahulu.',
    ], 422);
}

ActivityLogService::logCurrentUser(
    'update_settings',
    'Memperbarui setting unit aktif.',
    [
        'unit_id' => $authUser['unit_id'],
        'toleransi_terlambat_menit' => $tolerance,
        'hari_mulai_periode' => $startDay,
        'hari_akhir_periode' => $endDay,
        'global_shift_count' => count($globalRules),
        'jabatan_shift_count' => count($jabatanRules),
    ],
    'unit_settings',
    $authUser['unit_id']
);

json_response([
    'success' => true,
    'message' => 'Setting absensi dan periode closing berhasil diperbarui.',
    'reloadSection' => 'settings',
]);
