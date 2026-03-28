<?php

require __DIR__ . '/../bootstrap/app.php';
$user = Auth::require();

$filterPreset = (string) request_value('filter', 'previous_month');
$today = new DateTimeImmutable('today');

$resolveRange = static function (string $preset) use ($today): array {
    return match ($preset) {
        'current_month' => [
            'start' => $today->modify('first day of this month')->format('Y-m-d'),
            'end' => $today->modify('last day of this month')->format('Y-m-d'),
        ],
        'one_month_ago' => [
            'start' => $today->modify('first day of -2 month')->format('Y-m-d'),
            'end' => $today->modify('last day of -2 month')->format('Y-m-d'),
        ],
        'two_months_ago' => [
            'start' => $today->modify('first day of -3 month')->format('Y-m-d'),
            'end' => $today->modify('last day of -3 month')->format('Y-m-d'),
        ],
        default => [
            'start' => $today->modify('first day of last month')->format('Y-m-d'),
            'end' => $today->modify('last day of last month')->format('Y-m-d'),
        ],
    };
};

$defaultRange = $resolveRange($filterPreset);
$startDate = (string) request_value('start_date', $defaultRange['start']);
$endDate = (string) request_value('end_date', $defaultRange['end']);

if ($filterPreset !== 'custom') {
    $startDate = $defaultRange['start'];
    $endDate = $defaultRange['end'];
}

if (!$startDate || !$endDate || strtotime($startDate) === false || strtotime($endDate) === false) {
    $fallback = $resolveRange('previous_month');
    $filterPreset = 'previous_month';
    $startDate = $fallback['start'];
    $endDate = $fallback['end'];
}

if ($startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}

$pageSize = 25;
$currentPage = max(1, (int) request_value('page', 1));
$search = trim((string) request_value('search', ''));
$generateFilterPreset = (string) request_value('generate_filter', $filterPreset);

$generateDefaultRange = $resolveRange($generateFilterPreset);
$generateStartDate = (string) request_value('generate_start_date', $generateDefaultRange['start']);
$generateEndDate = (string) request_value('generate_end_date', $generateDefaultRange['end']);

if ($generateFilterPreset !== 'custom') {
    $generateStartDate = $generateDefaultRange['start'];
    $generateEndDate = $generateDefaultRange['end'];
}

if (!$generateStartDate || !$generateEndDate || strtotime($generateStartDate) === false || strtotime($generateEndDate) === false) {
    $generateFallback = $resolveRange('previous_month');
    $generateFilterPreset = 'previous_month';
    $generateStartDate = $generateFallback['start'];
    $generateEndDate = $generateFallback['end'];
}

if ($generateStartDate > $generateEndDate) {
    [$generateStartDate, $generateEndDate] = [$generateEndDate, $generateStartDate];
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
    ['unit_id' => $user['unit_id'], 'role' => 'owner', 'start' => $generateStartDate, 'end' => $generateEndDate]
)['total'] ?? 0);

$generatedEmployees = (int) (fetch_one(
    'SELECT COUNT(DISTINCT p.user_id) AS total
     FROM penggajian p
     JOIN users u ON u.id = p.user_id
     WHERE u.unit_id = :unit_id
       AND p.tanggal_awal_gaji = :start
       AND p.tanggal_akhir_gaji = :end',
    ['unit_id' => $user['unit_id'], 'start' => $generateStartDate, 'end' => $generateEndDate]
)['total'] ?? 0);

$pendingEmployees = max(0, $employeesWithAttendance - $generatedEmployees);

$searchSql = '';
$searchParams = [];
if ($search !== '') {
    $searchSql = ' AND u.name LIKE :search ';
    $searchParams['search'] = '%' . $search . '%';
}

$totalPayrolls = (int) (fetch_one(
    'SELECT COUNT(*) AS total
     FROM penggajian p
     JOIN users u ON u.id = p.user_id
     WHERE u.unit_id = :unit_id
       AND p.tanggal_awal_gaji BETWEEN :filter_start AND :filter_end' . $searchSql,
    ['unit_id' => $user['unit_id'], 'filter_start' => $startDate, 'filter_end' => $endDate] + $searchParams
)['total'] ?? 0);
$totalPages = max(1, (int) ceil($totalPayrolls / $pageSize));
$currentPage = min($currentPage, $totalPages);
$offset = ($currentPage - 1) * $pageSize;

$payrolls = fetch_all(
    'SELECT p.*, u.name
     FROM penggajian p
     JOIN users u ON u.id = p.user_id
     WHERE u.unit_id = :unit_id
       AND p.tanggal_awal_gaji BETWEEN :filter_start AND :filter_end' . $searchSql . '
     ORDER BY p.tanggal_awal_gaji DESC, p.id DESC
     LIMIT ' . $pageSize . ' OFFSET ' . $offset,
    ['unit_id' => $user['unit_id'], 'filter_start' => $startDate, 'filter_end' => $endDate] + $searchParams
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
    $tableRows .= '<tr>
        <td class="px-3 py-3 text-center"><input type="checkbox" value="' . e((string) $item['id']) . '" class="h-3.5 w-3.5 rounded border-slate-300 text-sky-600 focus:ring-sky-500" data-table-select></td>
        <td class="px-4 py-3 font-medium text-slate-900">' . e($item['name']) . '</td>
        <td class="px-4 py-3" data-sort-value="' . e($item['tanggal_awal_gaji']) . '">' . e(format_date_id($item['tanggal_awal_gaji'])) . ' s/d ' . e(format_date_id($item['tanggal_akhir_gaji'])) . '</td>
        <td class="px-4 py-3">' . money($item['gaji_kotor']) . '</td>
        <td class="px-4 py-3">' . money($item['total_potongan']) . '</td>
        <td class="px-4 py-3">' . money($item['gaji_bersih']) . '</td>
        <td class="px-4 py-3">
            <div class="flex flex-nowrap items-center gap-2">
                ' . ui_button('View', ['icon' => 'eye', 'variant' => 'info', 'icon_only' => true, 'attrs' => ['data-open-modal' => $viewModalId]]) . '
                ' . ui_button('Edit', ['icon' => 'pencil', 'variant' => 'amber', 'icon_only' => true, 'attrs' => ['data-open-modal' => $editModalId]]) . '
                <a href="print_slip.php?id=' . e($item['id']) . '" target="_blank">' . ui_button('Slip', ['icon' => 'printer', 'variant' => 'secondary', 'icon_only' => true]) . '</a>
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

$filterOptions = [
    'current_month' => 'Bulan ini',
    'previous_month' => 'Bulan sebelumnya',
    'one_month_ago' => '1 bulan yang lalu',
    'two_months_ago' => '2 bulan yang lalu',
    'custom' => 'Custom filter tanggal',
];

$filterForm = '<form class="grid gap-4 lg:grid-cols-[220px_1fr_1fr_auto]" data-section-filter data-section="gaji">'
    . $renderHiddenFields([
        'generate_filter' => $generateFilterPreset,
        'generate_start_date' => $generateStartDate,
        'generate_end_date' => $generateEndDate,
    ])
    . ui_select('filter', 'Periode Aktif', $filterOptions, $filterPreset)
    . ui_input('start_date', 'Tanggal Awal', $startDate, 'date')
    . ui_input('end_date', 'Tanggal Akhir', $endDate, 'date')
    . '<div class="flex items-end">' . ui_button('Terapkan Filter', ['type' => 'submit', 'variant' => 'secondary']) . '</div>'
    . '</form>';

$generateFilterForm = '<form class="grid gap-4 lg:grid-cols-[220px_1fr_1fr_auto]" data-section-filter data-section="gaji">'
    . $renderHiddenFields([
        'filter' => $filterPreset,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'search' => $search,
    ])
    . ui_select('generate_filter', 'Cek Belum Generate', $filterOptions, $generateFilterPreset)
    . ui_input('generate_start_date', 'Tanggal Awal Generate', $generateStartDate, 'date')
    . ui_input('generate_end_date', 'Tanggal Akhir Generate', $generateEndDate, 'date')
    . '<div class="flex items-end">' . ui_button('Cek Rentang', ['type' => 'submit', 'variant' => 'secondary']) . '</div>'
    . '</form>';

$generateInfo = '<div class="grid gap-4 md:grid-cols-3">'
    . ui_stat('Karyawan Absen', (string) $employeesWithAttendance, 'Karyawan dengan absensi pada rentang generate', 'sky')
    . ui_stat('Sudah Generate', (string) $generatedEmployees, 'Karyawan yang payroll-nya sudah dibuat pada rentang ini', 'emerald')
    . ui_stat('Belum Generate', (string) $pendingEmployees, 'Karyawan yang masih bisa digenerate pada rentang ini', $pendingEmployees > 0 ? 'amber' : 'emerald')
    . '</div>';

$generateForm = '<form action="ajax/generate_gaji.php" method="post" data-ajax-form class="grid gap-4 lg:grid-cols-[220px_1fr_1fr_auto]">'
    . csrf_input()
    . ui_select('filter', 'Mode Periode', $filterOptions, $generateFilterPreset)
    . ui_input('tanggal_awal', 'Tanggal Awal', $generateStartDate, 'date', ['required' => 'required'])
    . ui_input('tanggal_akhir', 'Tanggal Akhir', $generateEndDate, 'date', ['required' => 'required'])
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
$generateRangeLabel = format_date_id($generateStartDate) . ' - ' . format_date_id($generateEndDate);

echo '<div class="space-y-6">';
echo ui_panel('Filter Periode Gaji', $filterForm, ['subtitle' => 'Pilih periode kerja yang ingin dicek untuk generate dan laporan.']);
echo ui_panel('Generate Penggajian', $generateFilterForm . '<div class="mt-6">' . $generateInfo . '</div><div class="mt-6">' . $generateForm . '</div>', ['subtitle' => 'Cek dan generate payroll untuk rentang ' . $generateRangeLabel . '. Sistem hanya membuat payroll untuk karyawan yang punya absensi dan belum tergenerate pada rentang ini.']);
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
            'table_id' => $tableId,
            'server_pagination' => [
                'section' => 'gaji',
                'current_page' => $currentPage,
                'total_pages' => $totalPages,
                'total_items' => $totalPayrolls,
                'page_param' => 'page',
                'params' => [
                    'filter' => $filterPreset,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'search' => $search,
                    'generate_filter' => $generateFilterPreset,
                    'generate_start_date' => $generateStartDate,
                    'generate_end_date' => $generateEndDate,
                ],
                'search' => $search,
            ],
        ]
    ) . $bulkDeleteForm,
    ['subtitle' => 'Payroll yang sudah digenerate pada periode filter ' . $rangeLabel]
);
echo '</div>';
echo $modals;
