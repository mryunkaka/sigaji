<?php

require __DIR__ . '/../bootstrap/app.php';
$user = Auth::require();
ActivityLogService::logCurrentUser('open_section', 'Membuka halaman Absensi.', ['section' => 'absensi'], 'section', 'absensi');

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

$statusCounts = fetch_all(
    'SELECT a.status, COUNT(*) AS total
     FROM absensi a
     JOIN users u ON u.id = a.user_id
     WHERE u.unit_id = :unit_id AND a.tanggal BETWEEN :start AND :end
     GROUP BY a.status',
    ['unit_id' => $user['unit_id'], 'start' => $startDate, 'end' => $endDate]
);

$statsMap = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'cuti' => 0, 'alpa' => 0];
foreach ($statusCounts as $count) {
    if (isset($statsMap[$count['status']])) {
        $statsMap[$count['status']] = (int) $count['total'];
    }
}

$totalRecords = (int) (fetch_one(
    'SELECT COUNT(*) AS total
     FROM absensi a
     JOIN users u ON u.id = a.user_id
     WHERE u.unit_id = :unit_id AND a.tanggal BETWEEN :start AND :end',
    ['unit_id' => $user['unit_id'], 'start' => $startDate, 'end' => $endDate]
)['total'] ?? 0);

$records = fetch_all(
    'SELECT a.*, u.name, u.jabatan, u.kode_absensi
     FROM absensi a
     JOIN users u ON u.id = a.user_id
     WHERE u.unit_id = :unit_id AND a.tanggal BETWEEN :start AND :end
     ORDER BY a.tanggal ASC, a.id ASC',
    ['unit_id' => $user['unit_id'], 'start' => $startDate, 'end' => $endDate]
);

$statusOptions = [
    'hadir' => 'Hadir',
    'sakit' => 'Sakit',
    'izin' => 'Izin',
    'cuti' => 'Cuti',
    'alpa' => 'Alpa',
    'wfh' => 'WFH',
    'off' => 'Off',
];

$employees = fetch_all(
    'SELECT u.id,
            u.unit_id,
            u.name,
            u.jabatan,
            u.default_shift,
            u.jam_masuk_default,
            u.jam_keluar_default,
            COALESCE(mg.potongan_terlambat, 1000) AS potongan_terlambat,
            COALESCE(u.toleransi_terlambat_menit, un.toleransi_terlambat_menit, 0) AS toleransi_terlambat_menit
     FROM users u
     JOIN units un ON un.id = u.unit_id
     LEFT JOIN master_gaji mg ON mg.user_id = u.id
     WHERE u.unit_id = :unit_id AND u.role != :role
     ORDER BY u.name',
    ['unit_id' => $user['unit_id'], 'role' => 'owner']
);
$globalShiftOptions = ['' => 'Pilih Shift'] + ShiftService::getShiftOptions((int) $user['unit_id']);

$renderEmployeeSelect = static function (string $name, string $label, array $employees, $selected = null): string {
    static $employeeSelectCounter = 0;
    $employeeSelectCounter++;
    $id = 'field-' . preg_replace('/[^a-z0-9_-]+/i', '-', $name) . '-' . $employeeSelectCounter;
    $options = '<option value="">Pilih Karyawan</option>';
    foreach ($employees as $employee) {
        $isSelected = (string) $employee['id'] === (string) $selected ? ' selected' : '';
        $shiftContext = ShiftService::resolveEmployeeShiftContext($employee);
        $options .= '<option value="' . e((string) $employee['id']) . '" data-jabatan="' . e((string) ($employee['jabatan'] ?? '')) . '" data-potongan-terlambat="' . e((string) $employee['potongan_terlambat']) . '" data-toleransi-terlambat="' . e((string) ($employee['toleransi_terlambat_menit'] ?? 0)) . '" data-default-shift="' . e((string) ($shiftContext['default_shift'] ?? '')) . '" data-default-jam-masuk="' . e((string) ($shiftContext['scheduled_jam_masuk'] ?? '')) . '" data-default-jam-keluar="' . e((string) ($shiftContext['scheduled_jam_keluar'] ?? '')) . '" data-shift-rules="' . e(json_encode($shiftContext['rules'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '"' . $isSelected . '>' . e($employee['name']) . '</option>';
    }

    return '<div class="block">
        <label for="' . e($id) . '" class="mb-2 block text-sm font-medium text-slate-700">' . e($label) . '</label>
        <select id="' . e($id) . '" name="' . e($name) . '" class="w-full rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100" required data-absensi-calc>
            ' . $options . '
        </select>
    </div>';
};

$renderAbsensiForm = static function (array $employees, array $statusOptions, array $item, string $modalId, array $shiftOptions, bool $isCreate = false) use ($renderEmployeeSelect): string {

    return '<form action="ajax/save_absensi.php" method="post" data-ajax-form data-absensi-form class="grid gap-4 md:grid-cols-2">'
        . csrf_input()
        . '<input type="hidden" name="modal_id" value="' . e($modalId) . '">'
        . '<input type="hidden" name="id" value="' . e((string) ($item['id'] ?? '')) . '">'
        . $renderEmployeeSelect('user_id', 'Karyawan', $employees, $item['user_id'] ?? null)
        . ui_input('tanggal', 'Tanggal', $item['tanggal'] ?? '', 'date', ['required' => 'required'])
        . ui_select('shift', 'Shift', $shiftOptions, $item['shift'] ?? '', ['data-absensi-calc' => '1'])
        . ui_select('status', 'Status', $statusOptions, $item['status'] ?? 'hadir', ['required' => 'required', 'data-absensi-calc' => '1'])
        . ui_input('jam_masuk', 'Jam Masuk', isset($item['jam_masuk']) ? substr((string) $item['jam_masuk'], 0, 5) : '', 'time', ['data-absensi-calc' => '1'])
        . ui_input('jam_keluar', 'Jam Keluar', isset($item['jam_keluar']) ? substr((string) $item['jam_keluar'], 0, 5) : '', 'time')
        . ui_input('total_menit_terlambat', 'Total Menit Terlambat', $item['total_menit_terlambat'] ?? 0, 'number', ['min' => '0', 'readonly' => 'readonly'])
        . ui_input('jumlah_potongan', 'Jumlah Potongan (Rupiah)', $item['jumlah_potongan'] ?? 0, 'number', ['min' => '0', 'readonly' => 'readonly'])
        . ui_input('lembur', 'Lembur', $item['lembur'] ?? 0, 'number', ['min' => '0'])
        . ui_input('potongan_kehadiran', 'Potongan Kehadiran', $item['potongan_kehadiran'] ?? 0, 'number', ['min' => '0'])
        . ui_input('potongan_ijin', 'Potongan Ijin', $item['potongan_ijin'] ?? 0, 'number', ['min' => '0'])
        . ui_input('potongan_khusus', 'Potongan Khusus', $item['potongan_khusus'] ?? 0, 'number', ['min' => '0'])
        . '<div class="md:col-span-2">' . ui_textarea('keterangan_potongan', 'Keterangan Potongan Terlambat', $item['keterangan_potongan'] ?? '') . '</div>'
        . '<div class="md:col-span-2">' . ui_textarea('keterangan_lembur', 'Keterangan Lembur', $item['keterangan_lembur'] ?? '') . '</div>'
        . '<div class="md:col-span-2">' . ui_textarea('keterangan_kehadiran', 'Keterangan Potongan Kehadiran', $item['keterangan_kehadiran'] ?? '') . '</div>'
        . '<div class="md:col-span-2">' . ui_textarea('keterangan_ijin', 'Keterangan Ijin', $item['keterangan_ijin'] ?? '') . '</div>'
        . '<div class="md:col-span-2">' . ui_textarea('keterangan_khusus', 'Keterangan Potongan Khusus', $item['keterangan_khusus'] ?? '') . '</div>'
        . '<div class="md:col-span-2 flex justify-end">' . ui_button($isCreate ? 'Tambah Absensi' : 'Simpan Absensi', ['type' => 'submit', 'variant' => 'success']) . '</div>'
        . '</form>';
};

$tableId = 'absensi-table';
$tableRows = '';
$modals = '';

foreach ($records as $item) {
    $modalId = 'absensi-edit-' . $item['id'];
    $viewModalId = 'absensi-view-' . $item['id'];
    $deleteModalId = 'absensi-delete-' . $item['id'];
    $badgeTone = match ($item['status']) {
        'hadir' => 'emerald',
        'sakit', 'izin', 'cuti' => 'amber',
        'alpa' => 'rose',
        default => 'slate',
    };

    $tableRows .= '<tr>
        <td class="px-3 py-3 text-center"><input type="checkbox" value="' . e((string) $item['id']) . '" class="h-3.5 w-3.5 rounded border-slate-300 text-sky-600 focus:ring-sky-500" data-table-select></td>
        <td class="px-4 py-3 font-medium text-slate-900" data-search-text="' . e(trim((string) $item['name'] . ' ' . (string) ($item['kode_absensi'] ?? ''))) . '" title="' . e($item['name']) . '">' . e($item['name']) . '</td>
        <td class="px-4 py-3" title="' . e($item['jabatan'] ?? '-') . '">' . e($item['jabatan'] ?? '-') . '</td>
        <td class="whitespace-nowrap px-4 py-3" style="max-width:none;min-width:245px;overflow:visible;text-overflow:clip;" data-sort-value="' . e($item['tanggal']) . '" title="' . e(format_date_id($item['tanggal'], false, true)) . '">' . e(format_date_id($item['tanggal'], false, true)) . '</td>
        <td class="px-4 py-3">' . ui_badge(ucfirst($item['status']), $badgeTone) . '</td>
        <td class="px-4 py-3">' . e($item['shift'] ?: '-') . '</td>
        <td class="px-4 py-3">' . e($item['jam_masuk'] ?: '-') . '</td>
        <td class="px-4 py-3">' . e($item['jam_keluar'] ?: '-') . '</td>
        <td class="px-4 py-3">' . (int) $item['total_menit_terlambat'] . '</td>
        <td class="px-4 py-3" title="' . e(money($item['jumlah_potongan'])) . '">' . money($item['jumlah_potongan']) . '</td>
        <td class="max-w-none whitespace-nowrap px-4 py-3">
            <div class="flex flex-nowrap items-center gap-2">
                ' . ui_button('View', ['icon' => 'eye', 'variant' => 'info', 'icon_only' => true, 'attrs' => ['data-open-modal' => $viewModalId]]) . '
                ' . ui_button('Edit', ['icon' => 'pencil', 'variant' => 'amber', 'icon_only' => true, 'attrs' => ['data-open-modal' => $modalId]]) . '
                ' . ui_button('Hapus', ['icon' => 'trash', 'variant' => 'danger', 'icon_only' => true, 'attrs' => ['data-open-modal' => $deleteModalId]]) . '
            </div>
        </td>
    </tr>';

    $viewBody = '<div class="space-y-6">'
        . ui_detail_section('Informasi Absensi', [
            'Karyawan' => e($item['name']),
            'Jabatan' => e($item['jabatan'] ?? '-'),
            'Tanggal' => e(format_date_id($item['tanggal'])),
            'Status' => ui_badge(ucfirst($item['status']), $badgeTone),
            'Shift' => e($item['shift'] ?: '-'),
            'Jam Masuk' => e($item['jam_masuk'] ?: '-'),
            'Jam Keluar' => e($item['jam_keluar'] ?: '-'),
            'Terlambat' => e((string) ((int) $item['total_menit_terlambat'])) . ' menit',
            'Jumlah Potongan' => e(money($item['jumlah_potongan'])),
        ], 3)
        . ui_detail_section('Komponen Tambahan', [
            'Lembur' => e(money($item['lembur'] ?? 0)),
            'Potongan Kehadiran' => e(money($item['potongan_kehadiran'] ?? 0)),
            'Potongan Ijin' => e(money($item['potongan_ijin'] ?? 0)),
            'Potongan Khusus' => e(money($item['potongan_khusus'] ?? 0)),
        ], 4)
        . ui_detail_section('Keterangan', [
            'Potongan Terlambat' => nl2br(e($item['keterangan_potongan'] ?? '-')),
            'Lembur' => nl2br(e($item['keterangan_lembur'] ?? '-')),
            'Potongan Kehadiran' => nl2br(e($item['keterangan_kehadiran'] ?? '-')),
            'Ijin' => nl2br(e($item['keterangan_ijin'] ?? '-')),
            'Potongan Khusus' => nl2br(e($item['keterangan_khusus'] ?? '-')),
        ], 1)
        . '</div>';

    $deleteBody = '<form action="ajax/delete_absensi_bulk.php" method="post" data-ajax-form class="space-y-5">'
        . csrf_input()
        . '<input type="hidden" name="ids" value="' . e((string) $item['id']) . '">'
        . '<p class="text-sm text-slate-600">Hapus data absensi <strong>' . e($item['name']) . '</strong> pada tanggal <strong>' . e(format_date_id($item['tanggal'])) . '</strong> secara permanen?</p>'
        . '<div class="flex justify-end gap-3">'
        . ui_button('Batal', ['variant' => 'secondary', 'attrs' => ['data-close-modal' => $deleteModalId]])
        . ui_button('Hapus Permanen', ['type' => 'submit', 'variant' => 'danger', 'icon' => 'trash'])
        . '</div></form>';

    $modals .= ui_modal($viewModalId, 'Detail Absensi', $viewBody, ['max_width' => 'max-w-5xl']);
    $modals .= ui_modal($modalId, 'Edit Absensi', $renderAbsensiForm($employees, $statusOptions, $item, $modalId, $globalShiftOptions));
    $modals .= ui_modal($deleteModalId, 'Hapus Absensi', $deleteBody, ['max_width' => 'max-w-xl']);
}

$createModalId = 'absensi-create';
$modals .= ui_modal($createModalId, 'Tambah Absensi Manual', $renderAbsensiForm($employees, $statusOptions, [
    'tanggal' => $endDate,
    'status' => 'hadir',
    'total_menit_terlambat' => 0,
    'jumlah_potongan' => 0,
    'lembur' => 0,
    'potongan_kehadiran' => 0,
    'potongan_ijin' => 0,
    'potongan_khusus' => 0,
], $createModalId, $globalShiftOptions, true));

$uploadInputId = 'absensi-upload-file';
$uploadForm = '<form action="ajax/upload_absen.php" method="post" enctype="multipart/form-data" data-ajax-form data-upload-progress="import-absensi" class="grid gap-4 md:grid-cols-[1fr_auto]">'
    . csrf_input()
    . '<div class="block"><label for="' . e($uploadInputId) . '" class="mb-2 block text-sm font-medium text-slate-700">File Absensi</label><input id="' . e($uploadInputId) . '" type="file" name="file" accept=".csv,.xlsx" required class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm"></div>'
    . '<div class="flex items-end">' . ui_button('Upload Absensi', ['type' => 'submit', 'variant' => 'success', 'icon' => 'arrow-up-tray']) . '</div>'
    . '</form>';

$bulkDeleteFormId = 'absensi-bulk-delete';
$bulkDeleteForm = '<form id="' . e($bulkDeleteFormId) . '" action="ajax/delete_absensi_bulk.php" method="post" data-ajax-form class="hidden">'
    . csrf_input()
    . '<input type="hidden" name="ids" value="">'
    . '</form>';

$filterForm = '<form class="flex flex-col gap-4 lg:flex-row lg:items-end" data-section-filter data-section="absensi">'
    . '<div class="lg:min-w-0 lg:flex-1">' . ui_select('month', 'Bulan', $monthOptions, $selectedMonth, ['required' => 'required']) . '</div>'
    . '<div class="lg:min-w-0 lg:flex-1">' . ui_select('year', 'Tahun', $yearOptions, $selectedYear, ['required' => 'required']) . '</div>'
    . '<div class="flex flex-wrap items-end gap-3 lg:flex-none">'
    . ui_button('Terapkan Filter', ['type' => 'submit', 'variant' => 'secondary'])
    . ui_button('Reset', [
        'variant' => 'warning',
        'attrs' => [
            'data-load-section' => 'absensi',
            'data-section-params' => json_encode([
                'month' => $defaultMonth,
                'year' => $defaultYear,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ],
    ])
    . '</div>'
    . '</form>';

$rangeLabel = date('d M Y', strtotime($startDate)) . ' - ' . date('d M Y', strtotime($endDate));

echo '<div class="grid gap-4 xl:grid-cols-5">'
    . ui_stat('Hadir', (string) $statsMap['hadir'], 'Status hadir periode aktif', 'emerald')
    . ui_stat('Sakit', (string) $statsMap['sakit'], 'Status sakit periode aktif', 'sky')
    . ui_stat('Izin', (string) $statsMap['izin'], 'Status izin periode aktif', 'amber')
    . ui_stat('Cuti', (string) $statsMap['cuti'], 'Status cuti periode aktif', 'sky')
    . ui_stat('Alpa', (string) $statsMap['alpa'], 'Status alpa periode aktif', 'rose')
    . '</div>';

echo '<div class="mt-6 space-y-6">';
echo ui_panel('Upload Absensi', $uploadForm, ['subtitle' => 'Mirror dari import Excel lama. File CSV/XLSX akan diparsing lalu membuat user/master gaji bila belum ada.']);
echo ui_panel('Filter Periode Absensi', $filterForm, ['subtitle' => 'Periode selalu mengikuti closing 26 bulan sebelumnya sampai 25 bulan terpilih: ' . $rangeLabel . '.']);
echo ui_panel('Riwayat Absensi',
    '<div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">'
    . '<div class="flex flex-wrap gap-3">'
    . ui_button('Tambah Absensi Manual', ['icon' => 'plus', 'variant' => 'primary', 'attrs' => ['data-open-modal' => $createModalId]])
    . '</div>'
    . '</div>'
    . ui_table(
    [['label' => '<input type="checkbox" class="h-3.5 w-3.5 rounded border-slate-300 text-sky-600 focus:ring-sky-500" data-table-select-all>', 'sortable' => false, 'raw' => true], 'Nama', 'Jabatan', 'Tanggal', 'Status', 'Shift', 'Jam Masuk', 'Jam Keluar', 'Telat', 'Potongan', 'Aksi'],
    $tableRows !== '' ? $tableRows : '<tr><td colspan="11" class="px-4 py-8 text-center text-slate-500">Belum ada data absensi.</td></tr>',
    [
        'bulk_actions' => [
            'form_id' => $bulkDeleteFormId,
            'item_label' => 'data absensi',
            'total_items' => $totalRecords,
            'empty_message' => 'Pilih data absensi yang ingin dihapus.',
            'confirm_message' => 'Hapus permanen {count} data absensi terpilih?',
        ],
        'numeric_columns' => [8, 9],
        'storage_key' => 'absensi-history',
        'search_column' => 1,
        'table_id' => $tableId,
    ]
) . $bulkDeleteForm, ['subtitle' => 'Semua data absensi unit aktif pada periode ' . $rangeLabel]);
echo '</div>';
echo $modals;
