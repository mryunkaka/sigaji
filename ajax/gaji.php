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

$tableRows = '';
$modals = '';
foreach ($payrolls as $item) {
    $viewModalId = 'gaji-view-' . $item['id'];
    $tableRows .= '<tr>
        <td class="px-4 py-3 font-medium text-slate-900">' . e($item['name']) . '</td>
        <td class="px-4 py-3" data-sort-value="' . e($item['tanggal_awal_gaji']) . '">' . e(format_date_id($item['tanggal_awal_gaji'])) . ' s/d ' . e(format_date_id($item['tanggal_akhir_gaji'])) . '</td>
        <td class="px-4 py-3">' . money($item['gaji_kotor']) . '</td>
        <td class="px-4 py-3">' . money($item['total_potongan']) . '</td>
        <td class="px-4 py-3">' . money($item['gaji_bersih']) . '</td>
        <td class="px-4 py-3">
            <div class="flex flex-wrap gap-2">
                ' . ui_button('View', ['icon' => 'eye', 'variant' => 'info', 'icon_only' => true, 'attrs' => ['data-open-modal' => $viewModalId]]) . '
                <a href="print_slip.php?id=' . e($item['id']) . '" target="_blank">' . ui_button('Slip', ['icon' => 'printer', 'variant' => 'secondary', 'icon_only' => true]) . '</a>
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

    $modals .= ui_modal($viewModalId, 'Detail Penggajian', $viewBody, ['max_width' => 'max-w-5xl']);
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

$rangeLabel = format_date_id($startDate) . ' - ' . format_date_id($endDate);
$generateRangeLabel = format_date_id($generateStartDate) . ' - ' . format_date_id($generateEndDate);

echo '<div class="space-y-6">';
echo ui_panel('Filter Periode Gaji', $filterForm, ['subtitle' => 'Pilih periode kerja yang ingin dicek untuk generate dan laporan.']);
echo ui_panel('Generate Penggajian', $generateFilterForm . '<div class="mt-6">' . $generateInfo . '</div><div class="mt-6">' . $generateForm . '</div>', ['subtitle' => 'Cek dan generate payroll untuk rentang ' . $generateRangeLabel . '. Sistem hanya membuat payroll untuk karyawan yang punya absensi dan belum tergenerate pada rentang ini.']);
echo ui_panel('Laporan Penggajian', $reportForm, ['subtitle' => 'Hanya periode payroll yang sudah digenerate pada filter aktif yang bisa dicetak.']);
echo ui_panel('Daftar Penggajian', ui_table(
    ['Karyawan', 'Periode', 'Gaji Kotor', 'Total Potongan', 'Gaji Bersih', 'Cetak'],
    $tableRows !== '' ? $tableRows : '<tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">Belum ada payroll pada periode filter ini.</td></tr>',
    [
        'numeric_columns' => [2, 3, 4],
        'storage_key' => 'gaji-daftar',
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
), ['subtitle' => 'Payroll yang sudah digenerate pada periode filter ' . $rangeLabel]);
echo '</div>';
echo $modals;
