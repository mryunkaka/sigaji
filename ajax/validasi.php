<?php

require __DIR__ . '/../bootstrap/app.php';
$user = Auth::require();

$masters = fetch_all(
    'SELECT mg.*, u.name, u.jabatan
     FROM master_gaji mg
     JOIN users u ON u.id = mg.user_id
     WHERE u.unit_id = :unit_id AND u.role != "owner"
     ORDER BY u.name',
    ['unit_id' => $user['unit_id']]
);

$payrolls = fetch_all(
    'SELECT p.*, u.name
     FROM penggajian p
     JOIN users u ON u.id = p.user_id
     WHERE u.unit_id = :unit_id
     ORDER BY p.id DESC
     LIMIT 20',
    ['unit_id' => $user['unit_id']]
);

$masterRows = '';
$masterModals = '';
foreach ($masters as $item) {
    $modalId = 'master-edit-' . $item['id'];
    $masterRows .= '<tr>
        <td class="px-4 py-3 font-medium text-slate-900">' . e($item['name']) . '</td>
        <td class="px-4 py-3">' . e($item['jabatan'] ?: '-') . '</td>
        <td class="px-4 py-3">' . money($item['gaji_pokok']) . '</td>
        <td class="px-4 py-3">' . money($item['potongan_terlambat']) . '/menit</td>
        <td class="px-4 py-3">' . money($item['tunjangan_makan']) . '</td>
        <td class="px-4 py-3">' . money($item['tunjangan_jabatan']) . '</td>
        <td class="px-4 py-3">' . ui_button('Edit', ['icon' => 'pencil', 'variant' => 'secondary', 'attrs' => ['data-open-modal' => $modalId]]) . '</td>
    </tr>';

    $body = '<form action="ajax/save_master_gaji.php" method="post" data-ajax-form class="grid gap-4 md:grid-cols-2">'
        . csrf_input()
        . '<input type="hidden" name="modal_id" value="' . e($modalId) . '">'
        . '<input type="hidden" name="id" value="' . e($item['id']) . '">'
        . ui_input('gaji_pokok', 'Gaji Pokok', $item['gaji_pokok'], 'number', ['min' => '0'])
        . ui_input('potongan_terlambat', 'Potongan Terlambat / Menit', $item['potongan_terlambat'], 'number', ['min' => '0'])
        . ui_input('tunjangan_bbm', 'Tunjangan BBM', $item['tunjangan_bbm'], 'number', ['min' => '0'])
        . ui_input('tunjangan_makan', 'Tunjangan Makan / Hadir', $item['tunjangan_makan'], 'number', ['min' => '0'])
        . ui_input('tunjangan_jabatan', 'Tunjangan Jabatan / Hadir', $item['tunjangan_jabatan'], 'number', ['min' => '0'])
        . ui_input('tunjangan_kehadiran', 'Tunjangan Kehadiran', $item['tunjangan_kehadiran'], 'number', ['min' => '0'])
        . ui_input('tunjangan_lainnya', 'Tunjangan Lainnya', $item['tunjangan_lainnya'], 'number', ['min' => '0'])
        . ui_input('pot_bpjs_jht', 'Potongan BPJS JHT', $item['pot_bpjs_jht'], 'number', ['min' => '0'])
        . ui_input('pot_bpjs_kes', 'Potongan BPJS Kesehatan', $item['pot_bpjs_kes'], 'number', ['min' => '0'])
        . '<div class="md:col-span-2 flex justify-end">' . ui_button('Simpan Master Gaji', ['type' => 'submit', 'variant' => 'success']) . '</div>'
        . '</form>';
    $masterModals .= ui_modal($modalId, 'Validasi Master Gaji', $body);
}

$payrollRows = '';
$payrollModals = '';
foreach ($payrolls as $item) {
    $modalId = 'payroll-edit-' . $item['id'];
    $payrollRows .= '<tr>
        <td class="px-4 py-3 font-medium text-slate-900">' . e($item['name']) . '</td>
        <td class="px-4 py-3">' . e($item['tanggal_awal_gaji']) . ' s/d ' . e($item['tanggal_akhir_gaji']) . '</td>
        <td class="px-4 py-3">' . money($item['gaji_pokok']) . '</td>
        <td class="px-4 py-3">' . money($item['potongan_terlambat']) . '</td>
        <td class="px-4 py-3">' . money($item['potongan_khusus']) . '</td>
        <td class="px-4 py-3">' . money($item['gaji_bersih']) . '</td>
        <td class="px-4 py-3">' . ui_button('Override', ['icon' => 'pencil', 'variant' => 'secondary', 'attrs' => ['data-open-modal' => $modalId]]) . '</td>
    </tr>';

    $body = '<form action="ajax/save_penggajian.php" method="post" data-ajax-form class="grid gap-4 md:grid-cols-2">'
        . csrf_input()
        . '<input type="hidden" name="modal_id" value="' . e($modalId) . '">'
        . '<input type="hidden" name="id" value="' . e($item['id']) . '">'
        . ui_input('gaji_pokok', 'Edit Gaji Pokok', $item['gaji_pokok'], 'number', ['min' => '0'])
        . ui_input('potongan_terlambat', 'Override Telat', $item['potongan_terlambat'], 'number', ['min' => '0'])
        . ui_input('potongan_khusus', 'Potongan Khusus / Hutang', $item['potongan_khusus'], 'number', ['min' => '0'])
        . ui_input('potongan_ijin', 'Potongan Ijin', $item['potongan_ijin'], 'number', ['min' => '0'])
        . ui_input('potongan_kehadiran', 'Potongan Kehadiran', $item['potongan_kehadiran'], 'number', ['min' => '0'])
        . ui_input('lembur', 'Lembur', $item['lembur'], 'number', ['min' => '0'])
        . ui_input('tunjangan_bbm', 'Tunjangan BBM', $item['tunjangan_bbm'], 'number', ['min' => '0'])
        . ui_input('tunjangan_lainnya', 'Tunjangan Lainnya', $item['tunjangan_lainnya'], 'number', ['min' => '0'])
        . '<div class="md:col-span-2 flex justify-end">' . ui_button('Simpan Override', ['type' => 'submit', 'variant' => 'success']) . '</div>'
        . '</form>';
    $payrollModals .= ui_modal($modalId, 'Override Payroll', $body);
}

echo '<div class="space-y-6">';
echo ui_panel('Validasi Master Gaji', ui_table(
    ['Karyawan', 'Jabatan', 'Gaji Pokok', 'Denda Telat', 'Tunjangan Makan', 'Tunjangan Jabatan', 'Aksi'],
    $masterRows !== '' ? $masterRows : '<tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">Belum ada master gaji.</td></tr>'
), ['subtitle' => 'Basis angka default penggajian sesuai flow lama']);

echo ui_panel('Override Payroll', ui_table(
    ['Karyawan', 'Periode', 'Gaji Pokok', 'Pot. Telat', 'Pot. Khusus/Hutang', 'Gaji Bersih', 'Aksi'],
    $payrollRows !== '' ? $payrollRows : '<tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">Belum ada payroll untuk divalidasi.</td></tr>'
), ['subtitle' => 'Ruang validasi manual setelah generate penggajian']);
echo '</div>';
echo $masterModals . $payrollModals;
