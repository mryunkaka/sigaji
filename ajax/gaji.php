<?php

require __DIR__ . '/../bootstrap/app.php';
$user = Auth::require();
ActivityLogService::logCurrentUser('open_section', 'Membuka halaman Gaji.', ['section' => 'gaji'], 'section', 'gaji');

$period = closing_period_filter_state();
$selectedMonth = $period['selected_month'];
$selectedYear = $period['selected_year'];
$startDate = $period['start'];
$endDate = $period['end'];
$monthOptions = $period['month_options'];
$yearOptions = $period['year_options'];
$defaultClosingRange = closing_period_range();
$defaultClosingEnd = new DateTimeImmutable((string) $defaultClosingRange['end']);
$defaultMonth = $defaultClosingEnd->format('n');
$defaultYear = $defaultClosingEnd->format('Y');
$generateYear = (int) request_value('generate_year', (int) $defaultYear);
if (!isset($yearOptions[(string) $generateYear])) {
    $generateYear = (int) $defaultYear;
}
$generateMonth = (string) request_value('generate_month', $selectedMonth);
if (!isset($monthOptions[$generateMonth])) {
    $generateMonth = (string) $defaultMonth;
}

$renderHiddenFields = static function (array $fields): string {
    $html = '';
    foreach ($fields as $name => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $html .= '<input type="hidden" name="' . e((string) $name) . '" value="' . e((string) $value) . '">';
    }
    return $html;
};

$employeesWithAttendance = (int) (fetch_one(
    'SELECT COUNT(DISTINCT a.user_id) AS total
     FROM absensi a
     JOIN users u ON u.id = a.user_id
     WHERE u.unit_id = :unit_id
       AND u.role != :role
       AND a.tanggal BETWEEN :start AND :end',
    ['unit_id' => $user['unit_id'], 'role' => 'owner', 'start' => $startDate, 'end' => $endDate]
)['total'] ?? 0);

$generatedEmployees = (int) (fetch_one(
    'SELECT COUNT(DISTINCT p.user_id) AS total
     FROM penggajian p
     JOIN users u ON u.id = p.user_id
     WHERE u.unit_id = :unit_id
       AND p.tanggal_awal_gaji = :start
       AND p.tanggal_akhir_gaji = :end',
    ['unit_id' => $user['unit_id'], 'start' => $startDate, 'end' => $endDate]
)['total'] ?? 0);

$pendingEmployees = max(0, $employeesWithAttendance - $generatedEmployees);

$totalPayrolls = (int) (fetch_one(
    'SELECT COUNT(*) AS total
     FROM penggajian p
     JOIN users u ON u.id = p.user_id
     WHERE u.unit_id = :unit_id
       AND p.tanggal_awal_gaji BETWEEN :filter_start AND :filter_end',
    ['unit_id' => $user['unit_id'], 'filter_start' => $startDate, 'filter_end' => $endDate]
)['total'] ?? 0);

$payrolls = fetch_all(
    'SELECT p.*, u.name, u.kode_absensi
     FROM penggajian p
     JOIN users u ON u.id = p.user_id
     WHERE u.unit_id = :unit_id
       AND p.tanggal_awal_gaji BETWEEN :filter_start AND :filter_end
     ORDER BY p.tanggal_awal_gaji DESC, p.id DESC',
    ['unit_id' => $user['unit_id'], 'filter_start' => $startDate, 'filter_end' => $endDate]
);

$employees = fetch_all(
    'SELECT id, name
     FROM users
     WHERE unit_id = :unit_id
       AND role != :role
     ORDER BY name',
    ['unit_id' => $user['unit_id'], 'role' => 'owner']
);
$employeeOptions = [];
foreach ($employees as $employee) {
    $employeeOptions[$employee['id']] = $employee['name'];
}

$generatedPeriods = fetch_all(
    'SELECT p.tanggal_awal_gaji, p.tanggal_akhir_gaji, COUNT(*) AS total_karyawan
     FROM penggajian p
     JOIN users u ON u.id = p.user_id
     WHERE u.unit_id = :unit_id
       AND p.tanggal_awal_gaji BETWEEN :filter_start AND :filter_end
     GROUP BY p.tanggal_awal_gaji, p.tanggal_akhir_gaji
     ORDER BY p.tanggal_awal_gaji DESC, p.tanggal_akhir_gaji DESC',
    ['unit_id' => $user['unit_id'], 'filter_start' => $startDate, 'filter_end' => $endDate]
);

$selectedPeriod = (string) request_value('period', '');
if ($selectedPeriod === '' && $generatedPeriods !== []) {
    $selectedPeriod = $generatedPeriods[0]['tanggal_awal_gaji'] . '|' . $generatedPeriods[0]['tanggal_akhir_gaji'];
}

$periodOptions = [];
foreach ($generatedPeriods as $period) {
    $value = $period['tanggal_awal_gaji'] . '|' . $period['tanggal_akhir_gaji'];
    $label = format_date_id($period['tanggal_awal_gaji']) . ' s/d ' . format_date_id($period['tanggal_akhir_gaji']) . ' (' . $period['total_karyawan'] . ' karyawan)';
    $periodOptions[$value] = $label;
}

$generateMonthStatuses = [];
for ($month = 1; $month <= 12; $month++) {
    $generatePeriod = closing_period_range_from_month_year($month, $generateYear, (int) $user['unit_id']);
    $attendanceCount = (int) (fetch_one(
        'SELECT COUNT(DISTINCT a.user_id) AS total
         FROM absensi a
         JOIN users u ON u.id = a.user_id
         WHERE u.unit_id = :unit_id
           AND u.role != :role
           AND a.tanggal BETWEEN :start AND :end',
        [
            'unit_id' => $user['unit_id'],
            'role' => 'owner',
            'start' => $generatePeriod['start'],
            'end' => $generatePeriod['end'],
        ]
    )['total'] ?? 0);

    $generatedCount = (int) (fetch_one(
        'SELECT COUNT(DISTINCT p.user_id) AS total
         FROM penggajian p
         JOIN users u ON u.id = p.user_id
         WHERE u.unit_id = :unit_id
           AND p.tanggal_awal_gaji = :start
           AND p.tanggal_akhir_gaji = :end',
        [
            'unit_id' => $user['unit_id'],
            'start' => $generatePeriod['start'],
            'end' => $generatePeriod['end'],
        ]
    )['total'] ?? 0);

    if ($attendanceCount === 0) {
        $statusLabel = 'Tidak Ada Data';
        $statusTone = 'slate';
    } elseif ($generatedCount >= $attendanceCount) {
        $statusLabel = 'Sudah Digenerate';
        $statusTone = 'emerald';
    } else {
        $statusLabel = 'Belum Digenerate';
        $statusTone = 'rose';
    }

    $generateMonthStatuses[(string) $month] = [
        'label' => $monthOptions[(string) $month] ?? (string) $month,
        'status_label' => $statusLabel,
        'status_tone' => $statusTone,
    ];
}

$tableId = 'gaji-table';
$bulkDeleteFormId = 'gaji-bulk-delete';
$tableRows = '';
$modals = '';
$renderPayrollEditForm = static function (array $item, string $modalId, array $employeeOptions): string {
    $infoFields = '<div class="md:col-span-2 xl:col-span-3 grid gap-4 md:grid-cols-2 xl:grid-cols-3">'
        . ui_input('id_display', 'ID Payroll', $item['id'], 'text', ['readonly' => 'readonly'])
        . ui_input('created_at_display', 'Created At', (string) ($item['created_at'] ?? '-'), 'text', ['readonly' => 'readonly'])
        . ui_input('updated_at_display', 'Updated At', (string) ($item['updated_at'] ?? '-'), 'text', ['readonly' => 'readonly'])
        . '</div>';

    $componentFields = ui_select('user_id', 'Karyawan', $employeeOptions, $item['user_id'], ['required' => 'required'])
        . ui_input('tanggal_awal_gaji', 'Tanggal Awal Gaji', $item['tanggal_awal_gaji'], 'date', ['required' => 'required'])
        . ui_input('tanggal_akhir_gaji', 'Tanggal Akhir Gaji', $item['tanggal_akhir_gaji'], 'date', ['required' => 'required'])
        . ui_input('gaji_pokok', 'Gaji Pokok', $item['gaji_pokok'], 'number', ['min' => '0', 'data-payroll-calc' => 'earning'])
        . ui_input('tunjangan_bbm', 'Tunjangan BBM', $item['tunjangan_bbm'], 'number', ['min' => '0', 'data-payroll-calc' => 'earning'])
        . ui_input('tunjangan_makan', 'Tunjangan Makan', $item['tunjangan_makan'], 'number', ['min' => '0', 'data-payroll-calc' => 'earning'])
        . ui_input('tunjangan_jabatan', 'Tunjangan Jabatan', $item['tunjangan_jabatan'], 'number', ['min' => '0', 'data-payroll-calc' => 'earning'])
        . ui_input('tunjangan_kehadiran', 'Tunjangan Kehadiran', $item['tunjangan_kehadiran'], 'number', ['min' => '0', 'data-payroll-calc' => 'earning'])
        . ui_input('tunjangan_lainnya', 'Tunjangan Lainnya', $item['tunjangan_lainnya'], 'number', ['min' => '0', 'data-payroll-calc' => 'earning'])
        . ui_input('lembur', 'Lembur', $item['lembur'], 'number', ['min' => '0', 'data-payroll-calc' => 'earning'])
        . ui_input('potongan_kehadiran', 'Potongan Kehadiran', $item['potongan_kehadiran'], 'number', ['min' => '0', 'data-payroll-calc' => 'deduction'])
        . ui_input('potongan_khusus', 'Potongan Khusus', $item['potongan_khusus'], 'number', ['min' => '0', 'data-payroll-calc' => 'deduction'])
        . ui_input('potongan_ijin', 'Potongan Ijin', $item['potongan_ijin'], 'number', ['min' => '0', 'data-payroll-calc' => 'deduction'])
        . ui_input('potongan_terlambat', 'Potongan Terlambat', $item['potongan_terlambat'], 'number', ['min' => '0', 'data-payroll-calc' => 'deduction'])
        . ui_input('pot_bpjs_jht', 'Potongan BPJS JHT', $item['pot_bpjs_jht'], 'number', ['min' => '0', 'data-payroll-calc' => 'deduction'])
        . ui_input('pot_bpjs_kes', 'Potongan BPJS Kesehatan', $item['pot_bpjs_kes'], 'number', ['min' => '0', 'data-payroll-calc' => 'deduction'])
        . ui_input('gaji_kotor', 'Gaji Kotor', $item['gaji_kotor'], 'number', ['readonly' => 'readonly', 'data-payroll-output' => 'gaji_kotor'])
        . ui_input('total_potongan', 'Total Potongan', $item['total_potongan'], 'number', ['readonly' => 'readonly', 'data-payroll-output' => 'total_potongan'])
        . ui_input('gaji_bersih', 'Gaji Bersih', $item['gaji_bersih'], 'number', ['readonly' => 'readonly', 'data-payroll-output' => 'gaji_bersih']);

    return '<form action="ajax/save_penggajian.php" method="post" data-ajax-form data-payroll-form class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">'
        . csrf_input()
        . '<input type="hidden" name="modal_id" value="' . e($modalId) . '">'
        . '<input type="hidden" name="reload_section" value="gaji">'
        . '<input type="hidden" name="id" value="' . e((string) $item['id']) . '">'
        . $infoFields
        . $componentFields
        . '<div class="md:col-span-2 xl:col-span-3 flex justify-end">' . ui_button('Simpan Payroll', ['type' => 'submit', 'variant' => 'success']) . '</div>'
        . '</form>';
};
foreach ($payrolls as $item) {
    $viewModalId = 'gaji-view-' . $item['id'];
    $editModalId = 'gaji-edit-' . $item['id'];
    $deleteModalId = 'gaji-delete-' . $item['id'];
    $downloadUrl = 'print_slip.php?id=' . e($item['id']) . '&download=1';
    $downloadAction = "(function(url){var frame=document.getElementById('payroll-pdf-download-frame');if(!frame){frame=document.createElement('iframe');frame.id='payroll-pdf-download-frame';frame.setAttribute('aria-hidden','true');frame.style.position='fixed';frame.style.right='0';frame.style.bottom='0';frame.style.width='1px';frame.style.height='1px';frame.style.border='0';frame.style.opacity='0';document.body.appendChild(frame);}frame.src=url + (url.indexOf('?')===-1?'?':'&') + '_dl=' + Date.now();})('" . $downloadUrl . "');";
    $tableRows .= '<tr>
        <td class="px-3 py-3 text-center"><input type="checkbox" value="' . e((string) $item['id']) . '" class="h-3.5 w-3.5 rounded border-slate-300 text-sky-600 focus:ring-sky-500" data-table-select></td>
        <td class="px-4 py-3 font-medium text-slate-900" data-search-text="' . e(trim((string) $item['name'] . ' ' . (string) ($item['kode_absensi'] ?? ''))) . '">' . e($item['name']) . '</td>
        <td class="px-4 py-3" data-sort-value="' . e($item['tanggal_awal_gaji']) . '">' . e(format_date_id($item['tanggal_awal_gaji'])) . ' s/d ' . e(format_date_id($item['tanggal_akhir_gaji'])) . '</td>
        <td class="px-4 py-3">' . money($item['gaji_kotor']) . '</td>
        <td class="px-4 py-3">' . money($item['total_potongan']) . '</td>
        <td class="px-4 py-3">' . money($item['gaji_bersih']) . '</td>
        <td class="px-4 py-3">
            <div class="flex flex-nowrap items-center gap-2">
                ' . ui_button('View', ['icon' => 'eye', 'variant' => 'info', 'icon_only' => true, 'attrs' => ['data-open-modal' => $viewModalId]]) . '
                ' . ui_button('Edit', ['icon' => 'pencil', 'variant' => 'amber', 'icon_only' => true, 'attrs' => ['data-open-modal' => $editModalId]]) . '
                ' . ui_button('Download PDF', ['icon' => 'document-arrow-down', 'variant' => 'secondary', 'icon_only' => true, 'attrs' => ['onclick' => $downloadAction]]) . '
                <a href="print_slip.php?id=' . e($item['id']) . '" target="_blank" rel="noopener">' . ui_button('Print Slip', ['icon' => 'printer', 'variant' => 'secondary', 'icon_only' => true]) . '</a>
                ' . ui_button('Hapus', ['icon' => 'trash', 'variant' => 'danger', 'icon_only' => true, 'attrs' => ['data-open-modal' => $deleteModalId]]) . '
            </div>
        </td>
    </tr>';

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
        . ui_detail_section('Rincian Komponen', [
            'Tunjangan BBM' => e(money($item['tunjangan_bbm'])),
            'Tunjangan Makan' => e(money($item['tunjangan_makan'])),
            'Tunjangan Jabatan' => e(money($item['tunjangan_jabatan'])),
            'Tunjangan Kehadiran' => e(money($item['tunjangan_kehadiran'])),
            'Tunjangan Lainnya' => e(money($item['tunjangan_lainnya'])),
            'Lembur' => e(money($item['lembur'])),
            'Pot. Telat' => e(money($item['potongan_terlambat'])),
            'Pot. Kehadiran' => e(money($item['potongan_kehadiran'])),
            'Pot. Ijin' => e(money($item['potongan_ijin'])),
            'Pot. Khusus' => e(money($item['potongan_khusus'])),
        ], 4)
        . '</div>';

    $deleteBody = '<form action="ajax/delete_penggajian_bulk.php" method="post" data-ajax-form class="space-y-5">'
        . csrf_input()
        . '<input type="hidden" name="ids" value="' . e((string) $item['id']) . '">'
        . '<input type="hidden" name="reload_section" value="gaji">'
        . '<p class="text-sm text-slate-600">Hapus payroll <strong>' . e($item['name']) . '</strong> untuk periode <strong>' . e(format_date_id($item['tanggal_awal_gaji'])) . ' s/d ' . e(format_date_id($item['tanggal_akhir_gaji'])) . '</strong> secara permanen?</p>'
        . '<div class="flex justify-end gap-3">'
        . ui_button('Batal', ['variant' => 'secondary', 'attrs' => ['data-close-modal' => $deleteModalId]])
        . ui_button('Hapus Permanen', ['type' => 'submit', 'variant' => 'danger', 'icon' => 'trash'])
        . '</div></form>';

    $modals .= ui_modal($viewModalId, 'Detail Penggajian', $viewBody, ['max_width' => 'max-w-5xl']);
    $modals .= ui_modal($editModalId, 'Edit Penggajian', $renderPayrollEditForm($item, $editModalId, $employeeOptions), ['max_width' => 'max-w-6xl']);
    $modals .= ui_modal($deleteModalId, 'Hapus Penggajian', $deleteBody, ['max_width' => 'max-w-xl']);
}

$filterForm = '<form class="flex flex-col gap-4 lg:flex-row lg:items-end" data-section-filter data-section="gaji">'
    . '<input type="hidden" name="generate_year" value="' . e((string) $generateYear) . '">'
    . '<div class="lg:min-w-0 lg:flex-1">' . ui_select('month', 'Bulan', $monthOptions, $selectedMonth, ['required' => 'required']) . '</div>'
    . '<div class="lg:min-w-0 lg:flex-1">' . ui_select('year', 'Tahun', $yearOptions, $selectedYear, ['required' => 'required']) . '</div>'
    . '<div class="flex flex-wrap items-end gap-3 lg:flex-none">'
    . ui_button('Terapkan Filter', ['type' => 'submit', 'variant' => 'secondary'])
    . ui_button('Reset', [
        'type' => 'button',
        'variant' => 'warning',
        'attrs' => [
            'data-load-section' => 'gaji',
            'data-section-params' => e(json_encode(['month' => $defaultMonth, 'year' => $defaultYear, 'generate_year' => $defaultYear], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        ],
    ])
    . '</div>'
    . '</form>';

$generateInfo = '<div class="grid gap-4 md:grid-cols-3">'
    . ui_stat('Karyawan Absen', (string) $employeesWithAttendance, 'Karyawan dengan absensi pada rentang generate', 'sky')
    . ui_stat('Sudah Generate', (string) $generatedEmployees, 'Karyawan yang payroll-nya sudah dibuat pada rentang ini', 'emerald')
    . ui_stat('Belum Generate', (string) $pendingEmployees, 'Karyawan yang masih bisa digenerate pada rentang ini', $pendingEmployees > 0 ? 'amber' : 'emerald')
    . '</div>';

$generateMonthButtons = '<div class="block lg:col-span-2">'
    . '<label class="mb-2 block text-sm font-medium text-slate-700">Bulan Periode</label>'
    . '<input type="hidden" name="month" value="' . e($generateMonth) . '" data-generate-month-input>'
    . '<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3" data-generate-month-picker>';

foreach ($generateMonthStatuses as $monthValue => $monthStatus) {
    $isSelected = (string) $monthValue === (string) $generateMonth;
    $buttonClass = $isSelected
        ? 'border-emerald-400 bg-emerald-50 ring-4 ring-emerald-100'
        : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50';

    $generateMonthButtons .= '<button type="button" class="flex items-center justify-between gap-3 rounded-2xl border px-4 py-3 text-left text-sm text-slate-900 transition ' . $buttonClass . '" data-generate-month-option="' . e($monthValue) . '">'
        . '<span class="font-medium">' . e($monthStatus['label']) . '</span>'
        . ui_badge($monthStatus['status_label'], $monthStatus['status_tone'])
        . '</button>';
}

$generateMonthButtons .= '</div></div>';

$generateForm = '<form action="ajax/generate_gaji.php" method="post" data-ajax-form class="grid gap-4 lg:grid-cols-[280px_1fr_auto]">'
    . csrf_input()
    . ui_select('year', 'Tahun Periode', $yearOptions, (string) $generateYear, ['required' => 'required', 'data-section-load-on-change' => 'gaji', 'data-section-load-param' => 'generate_year'])
    . $generateMonthButtons
    . '<div class="flex items-end">' . ui_button('Generate Gaji', ['type' => 'submit', 'variant' => 'success', 'icon' => 'plus']) . '</div>'
    . '</form>';

$reportForm = $generatedPeriods === []
    ? '<div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">Belum ada payroll yang sudah digenerate pada filter periode ini.</div>'
    : '<form action="print_laporan.php" method="get" target="_blank" class="grid gap-4 lg:grid-cols-[1fr_auto]">'
    . ui_select('period', 'Periode Generated', $periodOptions, $selectedPeriod, ['required' => 'required'])
    . '<div class="flex items-end">' . ui_button('Cetak Laporan', ['type' => 'submit', 'variant' => 'secondary', 'icon' => 'printer']) . '</div>'
    . '</form>';

$bulkDeleteForm = '<form id="' . e($bulkDeleteFormId) . '" action="ajax/delete_penggajian_bulk.php" method="post" data-ajax-form class="hidden">'
    . csrf_input()
    . '<input type="hidden" name="ids" value="">'
    . '<input type="hidden" name="reload_section" value="gaji">'
    . '</form>';

$rangeLabel = format_date_id($startDate) . ' - ' . format_date_id($endDate);

echo '<div class="space-y-6">';
echo ui_panel('Filter Periode Gaji', $filterForm, ['subtitle' => 'Periode selalu mengikuti closing 26 bulan sebelumnya sampai 25 bulan terpilih: ' . $rangeLabel . '.']);
echo ui_panel('Generate Penggajian', $generateInfo . '<div class="mt-6">' . $generateForm . '</div>', ['subtitle' => 'Pilih tahun lebih dulu, lalu pilih bulan sesuai statusnya. Sistem hanya membuat payroll untuk karyawan yang punya absensi dan belum tergenerate.']);
echo ui_panel('Laporan Penggajian', $reportForm, ['subtitle' => 'Hanya periode payroll yang sudah digenerate pada filter aktif yang bisa dicetak.']);
echo ui_panel('Daftar Penggajian', ui_table(
        [['label' => '<input type="checkbox" class="h-3.5 w-3.5 rounded border-slate-300 text-sky-600 focus:ring-sky-500" data-table-select-all>', 'sortable' => false, 'raw' => true], 'Karyawan', 'Periode', 'Gaji Kotor', 'Total Potongan', 'Gaji Bersih', ['label' => 'Aksi', 'sortable' => false]],
        $tableRows !== '' ? $tableRows : '<tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">Belum ada payroll pada periode filter ini.</td></tr>',
        [
            'bulk_actions' => [
                'form_id' => $bulkDeleteFormId,
                'item_label' => 'payroll',
                'total_items' => $totalPayrolls,
                'empty_message' => 'Pilih data payroll yang ingin dihapus.',
                'confirm_message' => 'Hapus permanen {count} data payroll terpilih?',
            ],
            'numeric_columns' => [3, 4, 5],
            'storage_key' => 'gaji-daftar',
            'search_column' => 1,
            'table_id' => $tableId,
        ]
    ) . $bulkDeleteForm,
    ['subtitle' => 'Payroll yang sudah digenerate pada periode filter ' . $rangeLabel]
);
echo '</div>';
echo $modals;
