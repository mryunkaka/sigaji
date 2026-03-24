<?php

require __DIR__ . '/../bootstrap/app.php';
$user = Auth::require();

$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$statusCounts = fetch_all(
    'SELECT a.status, COUNT(*) AS total
     FROM absensi a
     JOIN users u ON u.id = a.user_id
     WHERE u.unit_id = :unit_id AND a.tanggal BETWEEN :start AND :end
     GROUP BY a.status',
    ['unit_id' => $user['unit_id'], 'start' => $monthStart, 'end' => $monthEnd]
);

$statsMap = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'cuti' => 0, 'alpa' => 0];
foreach ($statusCounts as $count) {
    if (isset($statsMap[$count['status']])) {
        $statsMap[$count['status']] = (int) $count['total'];
    }
}

$records = fetch_all(
    'SELECT a.*, u.name, u.jabatan
     FROM absensi a
     JOIN users u ON u.id = a.user_id
     WHERE u.unit_id = :unit_id
     ORDER BY a.tanggal DESC, a.id DESC
     LIMIT 80',
    ['unit_id' => $user['unit_id']]
);

$statusOptions = [
    'hadir' => 'Hadir',
    'sakit' => 'Sakit',
    'izin' => 'Izin',
    'cuti' => 'Cuti',
    'alpa' => 'Alpa',
    'off' => 'Off',
];

$tableRows = '';
$modals = '';

foreach ($records as $item) {
    $modalId = 'absensi-edit-' . $item['id'];
    $badgeTone = match ($item['status']) {
        'hadir' => 'emerald',
        'sakit', 'izin', 'cuti' => 'amber',
        'alpa' => 'rose',
        default => 'slate',
    };

    $tableRows .= '<tr>
        <td class="px-4 py-3 font-medium text-slate-900">' . e($item['name']) . '</td>
        <td class="px-4 py-3">' . e($item['jabatan'] ?? '-') . '</td>
        <td class="px-4 py-3">' . e($item['tanggal']) . '</td>
        <td class="px-4 py-3">' . ui_badge(ucfirst($item['status']), $badgeTone) . '</td>
        <td class="px-4 py-3">' . e($item['shift'] ?: '-') . '</td>
        <td class="px-4 py-3">' . e($item['jam_masuk'] ?: '-') . '</td>
        <td class="px-4 py-3">' . e($item['jam_keluar'] ?: '-') . '</td>
        <td class="px-4 py-3">' . (int) $item['total_menit_terlambat'] . '</td>
        <td class="px-4 py-3">' . money($item['jumlah_potongan']) . '</td>
        <td class="px-4 py-3">' . ui_button('Edit', ['icon' => 'pencil', 'variant' => 'secondary', 'attrs' => ['data-open-modal' => $modalId]]) . '</td>
    </tr>';

    $body = '<form action="ajax/save_absensi.php" method="post" data-ajax-form class="grid gap-4 md:grid-cols-2">'
        . csrf_input()
        . '<input type="hidden" name="modal_id" value="' . e($modalId) . '">'
        . '<input type="hidden" name="id" value="' . e($item['id']) . '">'
        . ui_input('tanggal', 'Tanggal', $item['tanggal'], 'date', ['required' => 'required'])
        . ui_select('shift', 'Shift', ['' => 'Pilih Shift', '1' => 'Shift 1', '2' => 'Shift 2', '3' => 'Shift 3'], $item['shift'])
        . ui_select('status', 'Status', $statusOptions, $item['status'])
        . ui_input('total_menit_terlambat', 'Total Terlambat', $item['total_menit_terlambat'], 'number', ['min' => '0'])
        . ui_input('jam_masuk', 'Jam Masuk', $item['jam_masuk'], 'time')
        . ui_input('jam_keluar', 'Jam Keluar', $item['jam_keluar'], 'time')
        . ui_input('lembur', 'Lembur', $item['lembur'], 'number', ['min' => '0'])
        . ui_input('potongan_kehadiran', 'Potongan Kehadiran', $item['potongan_kehadiran'], 'number', ['min' => '0'])
        . ui_input('potongan_ijin', 'Potongan Ijin', $item['potongan_ijin'], 'number', ['min' => '0'])
        . ui_input('potongan_khusus', 'Potongan Khusus / Hutang', $item['potongan_khusus'], 'number', ['min' => '0'])
        . '<div class="md:col-span-2">' . ui_textarea('keterangan_potongan', 'Keterangan Potongan', $item['keterangan_potongan'] ?? '') . '</div>'
        . '<div class="md:col-span-2">' . ui_textarea('keterangan_khusus', 'Keterangan Khusus', $item['keterangan_khusus'] ?? '') . '</div>'
        . '<div class="md:col-span-2 flex justify-end">' . ui_button('Simpan Absensi', ['type' => 'submit', 'variant' => 'success']) . '</div>'
        . '</form>';

    $modals .= ui_modal($modalId, 'Edit Absensi', $body);
}

$uploadForm = '<form action="ajax/upload_absen.php" method="post" enctype="multipart/form-data" data-ajax-form class="grid gap-4 md:grid-cols-[1fr_auto]">'
    . csrf_input()
    . '<label class="block"><span class="mb-2 block text-sm font-medium text-slate-700">File Absensi</span><input type="file" name="file" accept=".csv,.xlsx" required class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm"></label>'
    . '<div class="flex items-end">' . ui_button('Upload Absensi', ['type' => 'submit', 'variant' => 'success', 'icon' => 'arrow-up-tray']) . '</div>'
    . '</form>';

echo '<div class="grid gap-4 xl:grid-cols-5">'
    . ui_stat('Hadir', (string) $statsMap['hadir'], 'Status hadir bulan ini', 'emerald')
    . ui_stat('Sakit', (string) $statsMap['sakit'], 'Status sakit bulan ini', 'sky')
    . ui_stat('Izin', (string) $statsMap['izin'], 'Status izin bulan ini', 'amber')
    . ui_stat('Cuti', (string) $statsMap['cuti'], 'Status cuti bulan ini', 'sky')
    . ui_stat('Alpa', (string) $statsMap['alpa'], 'Status alpa bulan ini', 'rose')
    . '</div>';

echo '<div class="mt-6 space-y-6">';
echo ui_panel('Upload Absensi', $uploadForm, ['subtitle' => 'Mirror dari import Excel lama. File CSV/XLSX akan diparsing lalu membuat user/master gaji bila belum ada.']);
echo ui_panel('Riwayat Absensi', ui_table(
    ['Nama', 'Jabatan', 'Tanggal', 'Status', 'Shift', 'Jam Masuk', 'Jam Keluar', 'Telat', 'Potongan', 'Aksi'],
    $tableRows !== '' ? $tableRows : '<tr><td colspan="10" class="px-4 py-8 text-center text-slate-500">Belum ada data absensi.</td></tr>'
), ['subtitle' => '80 data absensi terbaru untuk unit aktif']);
echo '</div>';
echo $modals;
