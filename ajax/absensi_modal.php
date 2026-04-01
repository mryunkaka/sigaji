<?php

require __DIR__ . '/../bootstrap/app.php';
$user = Auth::require();
ActivityLogService::logCurrentUser('open_modal', 'Membuka modal absensi.', ['mode' => request_value('mode', 'view'), 'id' => (int) request_value('id', 0)], 'absensi', (string) request_value('id', ''));

$id = (int) request_value('id', 0);
$mode = (string) request_value('mode', 'view');

if ($id <= 0 || !in_array($mode, ['view', 'edit'], true)) {
    json_response(['success' => false, 'message' => 'Permintaan modal absensi tidak valid.'], 422);
}

$record = fetch_one(
    'SELECT a.*, u.name, u.jabatan
     FROM absensi a
     JOIN users u ON u.id = a.user_id
     WHERE a.id = :id AND u.unit_id = :unit_id
     LIMIT 1',
    ['id' => $id, 'unit_id' => $user['unit_id']]
);

if (!$record) {
    json_response(['success' => false, 'message' => 'Data absensi tidak ditemukan.'], 404);
}

$statusOptions = [
    'hadir' => 'Hadir',
    'sakit' => 'Sakit',
    'izin' => 'Izin',
    'cuti' => 'Cuti',
    'alpa' => 'Alpa',
    'wfh' => 'WFH',
    'off' => 'Off',
];

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

$renderAbsensiForm = static function (array $employees, array $statusOptions, array $item, string $modalId, array $shiftOptions) use ($renderEmployeeSelect): string {

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
        . '<div class="md:col-span-2 flex justify-end">' . ui_button('Simpan Absensi', ['type' => 'submit', 'variant' => 'success']) . '</div>'
        . '</form>';
};

$badgeTone = match ($record['status']) {
    'hadir' => 'emerald',
    'sakit', 'izin', 'cuti' => 'amber',
    'alpa' => 'rose',
    default => 'slate',
};

if ($mode === 'view') {
    $body = '<div class="space-y-6">'
        . ui_detail_section('Informasi Absensi', [
            'Karyawan' => e($record['name']),
            'Jabatan' => e($record['jabatan'] ?? '-'),
            'Tanggal' => e(format_date_id($record['tanggal'])),
            'Status' => ui_badge(ucfirst($record['status']), $badgeTone),
            'Shift' => e($record['shift'] ?: '-'),
            'Jam Masuk' => e($record['jam_masuk'] ?: '-'),
            'Jam Keluar' => e($record['jam_keluar'] ?: '-'),
            'Terlambat' => e((string) ((int) $record['total_menit_terlambat'])) . ' menit',
            'Jumlah Potongan' => e(money($record['jumlah_potongan'])),
        ], 3)
        . ui_detail_section('Komponen Tambahan', [
            'Lembur' => e(money($record['lembur'] ?? 0)),
            'Potongan Kehadiran' => e(money($record['potongan_kehadiran'] ?? 0)),
            'Potongan Ijin' => e(money($record['potongan_ijin'] ?? 0)),
            'Potongan Khusus' => e(money($record['potongan_khusus'] ?? 0)),
        ], 4)
        . ui_detail_section('Keterangan', [
            'Potongan Terlambat' => nl2br(e($record['keterangan_potongan'] ?? '-')),
            'Lembur' => nl2br(e($record['keterangan_lembur'] ?? '-')),
            'Potongan Kehadiran' => nl2br(e($record['keterangan_kehadiran'] ?? '-')),
            'Ijin' => nl2br(e($record['keterangan_ijin'] ?? '-')),
            'Potongan Khusus' => nl2br(e($record['keterangan_khusus'] ?? '-')),
        ], 1)
        . '</div>';

    json_response([
        'success' => true,
        'title' => 'Detail Absensi',
        'body' => $body,
    ]);
}

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

json_response([
    'success' => true,
    'title' => 'Edit Absensi',
    'body' => $renderAbsensiForm($employees, $statusOptions, $record, 'absensi-remote-edit', $globalShiftOptions),
]);
