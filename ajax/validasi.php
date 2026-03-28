<?php

require __DIR__ . '/../bootstrap/app.php';
$user = Auth::require();

$pageSize = 25;
$masterPage = max(1, (int) request_value('master_page', 1));
$payrollPage = max(1, (int) request_value('payroll_page', 1));
$search = trim((string) request_value('search', ''));
$masterSearchSql = '';
$masterSearchParams = [];
$payrollSearchSql = '';
$payrollSearchParams = [];
if ($search !== '') {
    $masterSearchSql = ' AND u.name LIKE :search ';
    $masterSearchParams['search'] = '%' . $search . '%';
    $payrollSearchSql = ' AND u.name LIKE :search ';
    $payrollSearchParams['search'] = '%' . $search . '%';
}

$totalMasters = (int) (fetch_one(
    'SELECT COUNT(*) AS total
     FROM master_gaji mg
     JOIN users u ON u.id = mg.user_id
     WHERE u.unit_id = :unit_id AND u.role != "owner"' . $masterSearchSql,
    ['unit_id' => $user['unit_id']] + $masterSearchParams
)['total'] ?? 0);
$masterPages = max(1, (int) ceil($totalMasters / $pageSize));
$masterPage = min($masterPage, $masterPages);
$masterOffset = ($masterPage - 1) * $pageSize;

$masters = fetch_all(
    'SELECT mg.*, u.name, u.jabatan
     FROM master_gaji mg
     JOIN users u ON u.id = mg.user_id
     WHERE u.unit_id = :unit_id AND u.role != "owner"' . $masterSearchSql . '
     ORDER BY u.name
     LIMIT ' . $pageSize . ' OFFSET ' . $masterOffset,
    ['unit_id' => $user['unit_id']] + $masterSearchParams
);

$totalPayrolls = (int) (fetch_one(
    'SELECT COUNT(*) AS total
     FROM penggajian p
     JOIN users u ON u.id = p.user_id
     WHERE u.unit_id = :unit_id' . $payrollSearchSql,
    ['unit_id' => $user['unit_id']] + $payrollSearchParams
)['total'] ?? 0);
$payrollPages = max(1, (int) ceil($totalPayrolls / $pageSize));
$payrollPage = min($payrollPage, $payrollPages);
$payrollOffset = ($payrollPage - 1) * $pageSize;

$payrolls = fetch_all(
    'SELECT p.*, u.name
     FROM penggajian p
     JOIN users u ON u.id = p.user_id
     WHERE u.unit_id = :unit_id' . $payrollSearchSql . '
     ORDER BY p.id DESC
     LIMIT ' . $pageSize . ' OFFSET ' . $payrollOffset,
    ['unit_id' => $user['unit_id']] + $payrollSearchParams
);

$masterTableId = 'validasi-master-table';
$masterBulkDeleteFormId = 'validasi-master-bulk-delete';
$payrollTableId = 'validasi-payroll-table';
$payrollBulkDeleteFormId = 'validasi-payroll-bulk-delete';
$masterRows = '';
$masterModals = '';
foreach ($masters as $item) {
    $modalId = 'master-edit-' . $item['id'];
    $viewModalId = 'master-view-' . $item['id'];
    $deleteModalId = 'master-delete-' . $item['id'];
    $masterRows .= '<tr>
        <td class="px-3 py-3 text-center"><input type="checkbox" value="' . e((string) $item['id']) . '" class="h-3.5 w-3.5 rounded border-slate-300 text-sky-600 focus:ring-sky-500" data-table-select></td>
        <td class="px-4 py-3 font-medium text-slate-900">' . e($item['name']) . '</td>
        <td class="px-4 py-3">' . e($item['jabatan'] ?: '-') . '</td>
        <td class="px-4 py-3">' . money($item['gaji_pokok']) . '</td>
        <td class="px-4 py-3">' . money($item['potongan_terlambat']) . '/menit</td>
        <td class="px-4 py-3">' . money($item['tunjangan_makan']) . '</td>
        <td class="px-4 py-3">' . money($item['tunjangan_jabatan']) . '</td>
        <td class="px-4 py-3"><div class="flex flex-nowrap items-center gap-2">'
            . ui_button('View', ['icon' => 'eye', 'variant' => 'info', 'icon_only' => true, 'attrs' => ['data-open-modal' => $viewModalId]])
            . ui_button('Edit', ['icon' => 'pencil', 'variant' => 'amber', 'icon_only' => true, 'attrs' => ['data-open-modal' => $modalId]])
            . ui_button('Hapus', ['icon' => 'trash', 'variant' => 'danger', 'icon_only' => true, 'attrs' => ['data-open-modal' => $deleteModalId]])
            . '</div></td>
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
    $viewBody = '<div class="space-y-6">'
        . ui_detail_section('Informasi Karyawan', [
            'Karyawan' => e($item['name']),
            'Jabatan' => e($item['jabatan'] ?: '-'),
        ], 2)
        . ui_detail_section('Komponen Master Gaji', [
            'Gaji Pokok' => e(money($item['gaji_pokok'])),
            'Potongan Terlambat / Menit' => e(money($item['potongan_terlambat'])) . '/menit',
            'Tunjangan BBM' => e(money($item['tunjangan_bbm'])),
            'Tunjangan Makan / Hadir' => e(money($item['tunjangan_makan'])),
            'Tunjangan Jabatan / Hadir' => e(money($item['tunjangan_jabatan'])),
            'Tunjangan Kehadiran' => e(money($item['tunjangan_kehadiran'])),
            'Tunjangan Lainnya' => e(money($item['tunjangan_lainnya'])),
            'Potongan BPJS JHT' => e(money($item['pot_bpjs_jht'])),
            'Potongan BPJS Kesehatan' => e(money($item['pot_bpjs_kes'])),
        ], 3)
        . '</div>';

    $deleteBody = '<form action="ajax/delete_master_gaji_bulk.php" method="post" data-ajax-form class="space-y-5">'
        . csrf_input()
        . '<input type="hidden" name="ids" value="' . e((string) $item['id']) . '">'
        . '<p class="text-sm text-slate-600">Hapus master gaji <strong>' . e($item['name']) . '</strong> secara permanen?</p>'
        . '<div class="flex justify-end gap-3">'
        . ui_button('Batal', ['variant' => 'secondary', 'attrs' => ['data-close-modal' => $deleteModalId]])
        . ui_button('Hapus Permanen', ['type' => 'submit', 'variant' => 'danger', 'icon' => 'trash'])
        . '</div></form>';

    $masterModals .= ui_modal($viewModalId, 'Detail Master Gaji', $viewBody, ['max_width' => 'max-w-5xl']);
    $masterModals .= ui_modal($modalId, 'Validasi Master Gaji', $body);
    $masterModals .= ui_modal($deleteModalId, 'Hapus Master Gaji', $deleteBody, ['max_width' => 'max-w-xl']);
}

$payrollRows = '';
$payrollModals = '';
foreach ($payrolls as $item) {
    $modalId = 'payroll-edit-' . $item['id'];
    $viewModalId = 'payroll-view-' . $item['id'];
    $deleteModalId = 'payroll-delete-' . $item['id'];
    $payrollRows .= '<tr>
        <td class="px-3 py-3 text-center"><input type="checkbox" value="' . e((string) $item['id']) . '" class="h-3.5 w-3.5 rounded border-slate-300 text-sky-600 focus:ring-sky-500" data-table-select></td>
        <td class="px-4 py-3 font-medium text-slate-900">' . e($item['name']) . '</td>
        <td class="px-4 py-3">' . e(format_date_id($item['tanggal_awal_gaji'])) . ' s/d ' . e(format_date_id($item['tanggal_akhir_gaji'])) . '</td>
        <td class="px-4 py-3">' . money($item['gaji_pokok']) . '</td>
        <td class="px-4 py-3">' . money($item['potongan_terlambat']) . '</td>
        <td class="px-4 py-3">' . money($item['potongan_khusus']) . '</td>
        <td class="px-4 py-3">' . money($item['gaji_bersih']) . '</td>
        <td class="px-4 py-3"><div class="flex flex-nowrap items-center gap-2">'
            . ui_button('View', ['icon' => 'eye', 'variant' => 'info', 'icon_only' => true, 'attrs' => ['data-open-modal' => $viewModalId]])
            . ui_button('Override', ['icon' => 'pencil', 'variant' => 'amber', 'icon_only' => true, 'attrs' => ['data-open-modal' => $modalId]])
            . ui_button('Hapus', ['icon' => 'trash', 'variant' => 'danger', 'icon_only' => true, 'attrs' => ['data-open-modal' => $deleteModalId]])
            . '</div></td>
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
    $viewBody = '<div class="space-y-6">'
        . ui_detail_section('Informasi Payroll', [
            'Karyawan' => e($item['name']),
            'Periode Awal' => e(format_date_id($item['tanggal_awal_gaji'])),
            'Periode Akhir' => e(format_date_id($item['tanggal_akhir_gaji'])),
            'Gaji Pokok' => e(money($item['gaji_pokok'])),
            'Gaji Kotor' => e(money($item['gaji_kotor'])),
            'Total Potongan' => e(money($item['total_potongan'])),
            'Gaji Bersih' => e(money($item['gaji_bersih'])),
        ], 3)
        . ui_detail_section('Komponen Payroll', [
            'Potongan Telat' => e(money($item['potongan_terlambat'])),
            'Potongan Khusus' => e(money($item['potongan_khusus'])),
            'Potongan Ijin' => e(money($item['potongan_ijin'])),
            'Potongan Kehadiran' => e(money($item['potongan_kehadiran'])),
            'Lembur' => e(money($item['lembur'])),
            'Tunjangan BBM' => e(money($item['tunjangan_bbm'])),
            'Tunjangan Lainnya' => e(money($item['tunjangan_lainnya'])),
        ], 3)
        . '</div>';

    $deleteBody = '<form action="ajax/delete_penggajian_bulk.php" method="post" data-ajax-form class="space-y-5">'
        . csrf_input()
        . '<input type="hidden" name="ids" value="' . e((string) $item['id']) . '">'
        . '<input type="hidden" name="reload_section" value="validasi">'
        . '<p class="text-sm text-slate-600">Hapus payroll <strong>' . e($item['name']) . '</strong> untuk periode <strong>' . e(format_date_id($item['tanggal_awal_gaji'])) . ' s/d ' . e(format_date_id($item['tanggal_akhir_gaji'])) . '</strong> secara permanen?</p>'
        . '<div class="flex justify-end gap-3">'
        . ui_button('Batal', ['variant' => 'secondary', 'attrs' => ['data-close-modal' => $deleteModalId]])
        . ui_button('Hapus Permanen', ['type' => 'submit', 'variant' => 'danger', 'icon' => 'trash'])
        . '</div></form>';

    $payrollModals .= ui_modal($viewModalId, 'Detail Payroll', $viewBody, ['max_width' => 'max-w-5xl']);
    $payrollModals .= ui_modal($modalId, 'Override Payroll', $body);
    $payrollModals .= ui_modal($deleteModalId, 'Hapus Payroll', $deleteBody, ['max_width' => 'max-w-xl']);
}

$masterBulkDeleteForm = '<form id="' . e($masterBulkDeleteFormId) . '" action="ajax/delete_master_gaji_bulk.php" method="post" data-ajax-form class="hidden">'
    . csrf_input()
    . '<input type="hidden" name="ids" value="">'
    . '</form>';

$payrollBulkDeleteForm = '<form id="' . e($payrollBulkDeleteFormId) . '" action="ajax/delete_penggajian_bulk.php" method="post" data-ajax-form class="hidden">'
    . csrf_input()
    . '<input type="hidden" name="ids" value="">'
    . '<input type="hidden" name="reload_section" value="validasi">'
    . '</form>';

echo '<div class="space-y-6">';
echo ui_panel('Validasi Master Gaji', '<div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">'
    . '<div class="flex flex-wrap gap-3">'
    . ui_button('Hapus Permanen', [
        'icon' => 'trash',
        'variant' => 'danger',
        'attrs' => [
            'data-bulk-delete' => '1',
            'data-table-target' => $masterTableId,
            'data-form-target' => $masterBulkDeleteFormId,
            'data-bulk-item-label' => 'master gaji',
            'data-bulk-empty-message' => 'Pilih master gaji yang ingin dihapus.',
            'data-bulk-confirm-message' => 'Hapus permanen {count} master gaji terpilih?',
        ],
    ])
    . '</div>'
    . '</div>'
    . ui_table(
        [['label' => '<input type="checkbox" class="h-3.5 w-3.5 rounded border-slate-300 text-sky-600 focus:ring-sky-500" data-table-select-all>', 'sortable' => false, 'raw' => true], 'Karyawan', 'Jabatan', 'Gaji Pokok', 'Denda Telat', 'Tunjangan Makan', 'Tunjangan Jabatan', ['label' => 'Aksi', 'sortable' => false]],
        $masterRows !== '' ? $masterRows : '<tr><td colspan="8" class="px-4 py-8 text-center text-slate-500">Belum ada master gaji.</td></tr>',
        [
            'numeric_columns' => [3, 4, 5, 6],
            'storage_key' => 'validasi-master-gaji',
            'table_id' => $masterTableId,
            'server_pagination' => [
                'section' => 'validasi',
                'current_page' => $masterPage,
                'total_pages' => $masterPages,
                'total_items' => $totalMasters,
                'page_param' => 'master_page',
                'params' => [
                    'payroll_page' => $payrollPage,
                    'search' => $search,
                ],
                'search' => $search,
            ],
        ]
    ) . $masterBulkDeleteForm,
    ['subtitle' => 'Basis angka default penggajian sesuai flow lama']
);

echo ui_panel('Override Payroll', '<div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">'
    . '<div class="flex flex-wrap gap-3">'
    . ui_button('Hapus Permanen', [
        'icon' => 'trash',
        'variant' => 'danger',
        'attrs' => [
            'data-bulk-delete' => '1',
            'data-table-target' => $payrollTableId,
            'data-form-target' => $payrollBulkDeleteFormId,
            'data-bulk-item-label' => 'payroll',
            'data-bulk-empty-message' => 'Pilih payroll yang ingin dihapus.',
            'data-bulk-confirm-message' => 'Hapus permanen {count} payroll terpilih?',
        ],
    ])
    . '</div>'
    . '</div>'
    . ui_table(
        [['label' => '<input type="checkbox" class="h-3.5 w-3.5 rounded border-slate-300 text-sky-600 focus:ring-sky-500" data-table-select-all>', 'sortable' => false, 'raw' => true], 'Karyawan', 'Periode', 'Gaji Pokok', 'Pot. Telat', 'Pot. Khusus/Hutang', 'Gaji Bersih', ['label' => 'Aksi', 'sortable' => false]],
        $payrollRows !== '' ? $payrollRows : '<tr><td colspan="8" class="px-4 py-8 text-center text-slate-500">Belum ada payroll untuk divalidasi.</td></tr>',
        [
            'numeric_columns' => [3, 4, 5, 6],
            'storage_key' => 'validasi-override-payroll',
            'table_id' => $payrollTableId,
            'server_pagination' => [
                'section' => 'validasi',
                'current_page' => $payrollPage,
                'total_pages' => $payrollPages,
                'total_items' => $totalPayrolls,
                'page_param' => 'payroll_page',
                'params' => [
                    'master_page' => $masterPage,
                    'search' => $search,
                ],
                'search' => $search,
            ],
        ]
    ) . $payrollBulkDeleteForm,
    ['subtitle' => 'Ruang validasi manual setelah generate penggajian']
);
echo '</div>';
echo $masterModals . $payrollModals;
