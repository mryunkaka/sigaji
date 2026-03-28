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

$summary = fetch_one(
    'SELECT
        (SELECT COUNT(*) FROM users WHERE unit_id = :unit_id AND role != "owner") AS total_karyawan,
        (SELECT COUNT(*) FROM absensi a JOIN users u ON u.id = a.user_id WHERE u.unit_id = :unit_id AND a.tanggal BETWEEN :start AND :end) AS total_absensi,
        (SELECT COALESCE(SUM(gaji_bersih), 0) FROM penggajian p JOIN users u ON u.id = p.user_id WHERE u.unit_id = :unit_id AND p.tanggal_awal_gaji BETWEEN :start AND :end) AS total_gaji,
        (SELECT COALESCE(SUM(total_menit_terlambat), 0) FROM absensi a JOIN users u ON u.id = a.user_id WHERE u.unit_id = :unit_id AND a.tanggal BETWEEN :start AND :end) AS total_terlambat',
    ['unit_id' => $user['unit_id'], 'start' => $startDate, 'end' => $endDate]
) ?: [];

$latestPayroll = fetch_all(
    'SELECT p.*, u.name
     FROM penggajian p
     JOIN users u ON u.id = p.user_id
     WHERE u.unit_id = :unit_id
       AND p.tanggal_awal_gaji BETWEEN :start AND :end
     ORDER BY p.id DESC
     LIMIT 10',
    ['unit_id' => $user['unit_id'], 'start' => $startDate, 'end' => $endDate]
);

$filterOptions = [
    'current_month' => 'Bulan ini',
    'previous_month' => 'Bulan sebelumnya',
    'one_month_ago' => '1 bulan yang lalu',
    'two_months_ago' => '2 bulan yang lalu',
    'custom' => 'Custom filter tanggal',
];

$filterForm = '<form class="grid gap-4 lg:grid-cols-[220px_1fr_1fr_auto]" data-section-filter data-section="dashboard">'
    . ui_select('filter', 'Periode Dashboard', $filterOptions, $filterPreset)
    . ui_input('start_date', 'Tanggal Awal', $startDate, 'date')
    . ui_input('end_date', 'Tanggal Akhir', $endDate, 'date')
    . '<div class="flex items-end">' . ui_button('Terapkan Filter', ['type' => 'submit', 'variant' => 'secondary']) . '</div>'
    . '</form>';

$rangeLabel = format_date_id($startDate) . ' - ' . format_date_id($endDate);

$rows = '';
$modals = '';
foreach ($latestPayroll as $item) {
    $viewModalId = 'dashboard-payroll-view-' . $item['id'];
    $deleteModalId = 'dashboard-payroll-delete-' . $item['id'];
    $rows .= '<tr>
        <td class="px-4 py-3 font-medium text-slate-900">' . e($item['name']) . '</td>
        <td class="px-4 py-3">' . e(format_date_id($item['tanggal_awal_gaji'])) . ' s/d ' . e(format_date_id($item['tanggal_akhir_gaji'])) . '</td>
        <td class="px-4 py-3">' . money($item['gaji_bersih']) . '</td>
        <td class="px-4 py-3">' . money($item['total_potongan']) . '</td>
        <td class="px-4 py-3"><div class="flex flex-nowrap items-center gap-2">'
            . ui_button('View', ['icon' => 'eye', 'variant' => 'info', 'icon_only' => true, 'attrs' => ['data-open-modal' => $viewModalId]])
            . ui_button('Hapus', ['icon' => 'trash', 'variant' => 'danger', 'icon_only' => true, 'attrs' => ['data-open-modal' => $deleteModalId]])
            . '</div></td>
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

    $deleteBody = '<form action="ajax/delete_penggajian_bulk.php" method="post" data-ajax-form class="space-y-5">'
        . csrf_input()
        . '<input type="hidden" name="ids" value="' . e((string) $item['id']) . '">'
        . '<input type="hidden" name="reload_section" value="dashboard">'
        . '<p class="text-sm text-slate-600">Hapus payroll <strong>' . e($item['name']) . '</strong> untuk periode <strong>' . e(format_date_id($item['tanggal_awal_gaji'])) . ' s/d ' . e(format_date_id($item['tanggal_akhir_gaji'])) . '</strong> secara permanen?</p>'
        . '<div class="flex justify-end gap-3">'
        . ui_button('Batal', ['variant' => 'secondary', 'attrs' => ['data-close-modal' => $deleteModalId]])
        . ui_button('Hapus Permanen', ['type' => 'submit', 'variant' => 'danger', 'icon' => 'trash'])
        . '</div></form>';

    $modals .= ui_modal($viewModalId, 'Detail Payroll Terbaru', $viewBody, ['max_width' => 'max-w-4xl']);
    $modals .= ui_modal($deleteModalId, 'Hapus Payroll', $deleteBody, ['max_width' => 'max-w-xl']);
}

echo '<div class="grid gap-4 xl:grid-cols-4">'
    . ui_stat('Karyawan Aktif', (string) ($summary['total_karyawan'] ?? 0), 'User non-owner pada unit aktif', 'emerald')
    . ui_stat('Total Absensi', (string) ($summary['total_absensi'] ?? 0), 'Rekap absensi periode terpilih', 'sky')
    . ui_stat('Total Gaji', money($summary['total_gaji'] ?? 0), 'Akumulasi gaji bersih payroll periode terpilih', 'amber')
    . ui_stat('Menit Terlambat', (string) ($summary['total_terlambat'] ?? 0), 'Total keterlambatan periode terpilih', 'rose')
    . '</div>';

echo '<div class="mt-6 space-y-6">'
    . ui_panel('Filter Dashboard', $filterForm, ['subtitle' => 'Default bulan sebelumnya. Pilih periode dashboard sesuai kebutuhan laporan.'])
    . ui_panel('Penggajian Terbaru', ui_table(
        ['Karyawan', 'Periode', 'Gaji Bersih', 'Total Potongan', ['label' => 'Aksi', 'sortable' => false]],
        $rows !== '' ? $rows : '<tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Belum ada data penggajian.</td></tr>',
        ['numeric_columns' => [2, 3], 'storage_key' => 'dashboard-payroll-latest']
    ), ['subtitle' => 'Ringkasan 10 payroll terbaru pada periode ' . $rangeLabel]) .
    '</div>';
echo $modals;
