<?php

require __DIR__ . '/../bootstrap/app.php';
$user = Auth::require();

$payrolls = fetch_all(
    'SELECT p.*, u.name
     FROM penggajian p
     JOIN users u ON u.id = p.user_id
     WHERE u.unit_id = :unit_id
     ORDER BY p.id DESC
     LIMIT 50',
    ['unit_id' => $user['unit_id']]
);

$tableRows = '';
foreach ($payrolls as $item) {
    $tableRows .= '<tr>
        <td class="px-4 py-3 font-medium text-slate-900">' . e($item['name']) . '</td>
        <td class="px-4 py-3">' . e($item['tanggal_awal_gaji']) . ' s/d ' . e($item['tanggal_akhir_gaji']) . '</td>
        <td class="px-4 py-3">' . money($item['gaji_kotor']) . '</td>
        <td class="px-4 py-3">' . money($item['total_potongan']) . '</td>
        <td class="px-4 py-3">' . money($item['gaji_bersih']) . '</td>
        <td class="px-4 py-3">
            <div class="flex gap-2">
                <a href="print_slip.php?id=' . e($item['id']) . '" target="_blank">' . ui_button('Slip', ['icon' => 'printer', 'variant' => 'secondary']) . '</a>
            </div>
        </td>
    </tr>';
}

$generateForm = '<form action="ajax/generate_gaji.php" method="post" data-ajax-form class="grid gap-4 lg:grid-cols-[1fr_1fr_auto]">'
    . csrf_input()
    . ui_input('tanggal_awal', 'Tanggal Awal', date('Y-m-01'), 'date', ['required' => 'required'])
    . ui_input('tanggal_akhir', 'Tanggal Akhir', date('Y-m-t'), 'date', ['required' => 'required'])
    . '<div class="flex items-end">' . ui_button('Generate Gaji', ['type' => 'submit', 'variant' => 'success', 'icon' => 'plus']) . '</div>'
    . '</form>';

$reportForm = '<form action="print_laporan.php" method="get" target="_blank" class="grid gap-4 lg:grid-cols-[1fr_1fr_auto]">'
    . ui_input('tanggal_awal', 'Tanggal Awal', date('Y-m-01'), 'date', ['required' => 'required'])
    . ui_input('tanggal_akhir', 'Tanggal Akhir', date('Y-m-t'), 'date', ['required' => 'required'])
    . '<div class="flex items-end">' . ui_button('Cetak Laporan', ['type' => 'submit', 'variant' => 'secondary', 'icon' => 'printer']) . '</div>'
    . '</form>';

echo '<div class="space-y-6">';
echo ui_panel('Generate Penggajian', $generateForm, ['subtitle' => 'Mirror flow penggajian otomatis lama: buat payroll per periode berdasarkan absensi yang tersedia.']);
echo ui_panel('Laporan Penggajian', $reportForm, ['subtitle' => 'Slip per karyawan dan laporan periode dibuka sebagai halaman print-friendly.']);
echo ui_panel('Daftar Penggajian', ui_table(
    ['Karyawan', 'Periode', 'Gaji Kotor', 'Total Potongan', 'Gaji Bersih', 'Cetak'],
    $tableRows !== '' ? $tableRows : '<tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">Belum ada payroll.</td></tr>'
), ['subtitle' => '50 penggajian terbaru pada unit aktif']);
echo '</div>';
