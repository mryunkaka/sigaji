<?php

require __DIR__ . '/../bootstrap/app.php';
$user = Auth::require();

$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

$summary = fetch_one(
    'SELECT
        (SELECT COUNT(*) FROM users WHERE unit_id = :unit_id AND role != "owner") AS total_karyawan,
        (SELECT COUNT(*) FROM absensi a JOIN users u ON u.id = a.user_id WHERE u.unit_id = :unit_id AND a.tanggal BETWEEN :start AND :end) AS total_absensi,
        (SELECT COALESCE(SUM(gaji_bersih), 0) FROM penggajian p JOIN users u ON u.id = p.user_id WHERE u.unit_id = :unit_id AND p.tanggal_awal_gaji BETWEEN :start AND :end) AS total_gaji,
        (SELECT COALESCE(SUM(total_menit_terlambat), 0) FROM absensi a JOIN users u ON u.id = a.user_id WHERE u.unit_id = :unit_id AND a.tanggal BETWEEN :start AND :end) AS total_terlambat',
    ['unit_id' => $user['unit_id'], 'start' => $monthStart, 'end' => $monthEnd]
) ?: [];

$latestPayroll = fetch_all(
    'SELECT p.*, u.name
     FROM penggajian p
     JOIN users u ON u.id = p.user_id
     WHERE u.unit_id = :unit_id
     ORDER BY p.id DESC
     LIMIT 10',
    ['unit_id' => $user['unit_id']]
);

$rows = '';
foreach ($latestPayroll as $item) {
    $rows .= '<tr>
        <td class="px-4 py-3 font-medium text-slate-900">' . e($item['name']) . '</td>
        <td class="px-4 py-3">' . e($item['tanggal_awal_gaji']) . ' s/d ' . e($item['tanggal_akhir_gaji']) . '</td>
        <td class="px-4 py-3">' . money($item['gaji_bersih']) . '</td>
        <td class="px-4 py-3">' . money($item['total_potongan']) . '</td>
    </tr>';
}

echo '<div class="grid gap-4 xl:grid-cols-4">'
    . ui_stat('Karyawan Aktif', (string) ($summary['total_karyawan'] ?? 0), 'User non-owner pada unit aktif', 'emerald')
    . ui_stat('Absensi Bulan Ini', (string) ($summary['total_absensi'] ?? 0), 'Rekap absensi periode berjalan', 'sky')
    . ui_stat('Total Gaji Bulan Ini', money($summary['total_gaji'] ?? 0), 'Akumulasi gaji bersih payroll', 'amber')
    . ui_stat('Menit Terlambat', (string) ($summary['total_terlambat'] ?? 0), 'Total keterlambatan bulan berjalan', 'rose')
    . '</div>';

echo '<div class="mt-6">'
    . ui_panel('Penggajian Terbaru', ui_table(
        ['Karyawan', 'Periode', 'Gaji Bersih', 'Total Potongan'],
        $rows !== '' ? $rows : '<tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">Belum ada data penggajian.</td></tr>'
    ), ['subtitle' => 'Ringkasan 10 payroll terbaru pada unit aktif']) .
    '</div>';
