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
$modals = '';
foreach ($latestPayroll as $item) {
    $viewModalId = 'dashboard-payroll-view-' . $item['id'];
    $rows .= '<tr>
        <td class="px-4 py-3 font-medium text-slate-900">' . e($item['name']) . '</td>
        <td class="px-4 py-3">' . e(format_date_id($item['tanggal_awal_gaji'])) . ' s/d ' . e(format_date_id($item['tanggal_akhir_gaji'])) . '</td>
        <td class="px-4 py-3">' . money($item['gaji_bersih']) . '</td>
        <td class="px-4 py-3">' . money($item['total_potongan']) . '</td>
        <td class="px-4 py-3">' . ui_button('View', ['icon' => 'eye', 'variant' => 'info', 'icon_only' => true, 'attrs' => ['data-open-modal' => $viewModalId]]) . '</td>
    </tr>';

    $viewBody = '<div class="space-y-6">'
        . ui_detail_section('Ringkasan Payroll', [
            'Karyawan' => e($item['name']),
            'Periode Awal' => e(format_date_id($item['tanggal_awal_gaji'])),
            'Periode Akhir' => e(format_date_id($item['tanggal_akhir_gaji'])),
            'Gaji Bersih' => e(money($item['gaji_bersih'])),
            'Total Potongan' => e(money($item['total_potongan'])),
        ], 3)
        . '</div>';

    $modals .= ui_modal($viewModalId, 'Detail Payroll Terbaru', $viewBody, ['max_width' => 'max-w-4xl']);
}

echo '<div class="grid gap-4 xl:grid-cols-4">'
    . ui_stat('Karyawan Aktif', (string) ($summary['total_karyawan'] ?? 0), 'User non-owner pada unit aktif', 'emerald')
    . ui_stat('Absensi Bulan Ini', (string) ($summary['total_absensi'] ?? 0), 'Rekap absensi periode berjalan', 'sky')
    . ui_stat('Total Gaji Bulan Ini', money($summary['total_gaji'] ?? 0), 'Akumulasi gaji bersih payroll', 'amber')
    . ui_stat('Menit Terlambat', (string) ($summary['total_terlambat'] ?? 0), 'Total keterlambatan bulan berjalan', 'rose')
    . '</div>';

echo '<div class="mt-6">'
    . ui_panel('Penggajian Terbaru', ui_table(
        ['Karyawan', 'Periode', 'Gaji Bersih', 'Total Potongan', 'View'],
        $rows !== '' ? $rows : '<tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Belum ada data penggajian.</td></tr>',
        ['numeric_columns' => [2, 3], 'storage_key' => 'dashboard-payroll-latest']
    ), ['subtitle' => 'Ringkasan 10 payroll terbaru pada unit aktif']) .
    '</div>';
echo $modals;
