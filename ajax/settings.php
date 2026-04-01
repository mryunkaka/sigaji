<?php

require __DIR__ . '/../bootstrap/app.php';
$authUser = Auth::require();

$migrationMissing = false;

try {
    $unit = fetch_one(
        'SELECT id, nama_unit, toleransi_terlambat_menit, hari_mulai_periode, hari_akhir_periode
         FROM units
         WHERE id = :id
         LIMIT 1',
        ['id' => $authUser['unit_id']]
    );
} catch (Throwable $exception) {
    $migrationMissing = true;
    $unit = fetch_one(
        'SELECT id, nama_unit, toleransi_terlambat_menit
         FROM units
         WHERE id = :id
         LIMIT 1',
        ['id' => $authUser['unit_id']]
    );
    if ($unit) {
        $unit['hari_mulai_periode'] = 26;
        $unit['hari_akhir_periode'] = 25;
    }
}

if (!$unit) {
    echo ui_panel('Setting Absensi', '<div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">Unit aktif tidak ditemukan.</div>');
    return;
}

$globalShiftRules = ShiftService::getGlobalRules((int) $authUser['unit_id']);
if ($globalShiftRules === []) {
    $globalShiftRules = [
        1 => ['jam_masuk' => '07:00', 'jam_keluar' => '15:00'],
        2 => ['jam_masuk' => '15:00', 'jam_keluar' => '23:00'],
        3 => ['jam_masuk' => '23:00', 'jam_keluar' => '07:00'],
    ];
}

$globalShiftFields = '<div class="md:col-span-2 overflow-hidden rounded-[24px] border border-slate-200" data-repeatable-table="global-shift">'
    . '<div class="overflow-x-auto">'
    . '<table class="min-w-full divide-y divide-slate-200 bg-white">'
    . '<thead class="bg-slate-50"><tr>'
    . '<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Shift</th>'
    . '<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Jam Masuk</th>'
    . '<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Jam Keluar</th>'
    . '<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Aksi</th>'
    . '</tr></thead><tbody class="divide-y divide-slate-100" data-repeatable-body>';
foreach ($globalShiftRules as $shiftCode => $rule) {
    $globalShiftFields .= '<tr data-repeatable-row>'
        . '<td class="px-4 py-3"><input type="number" name="global_shift_code[]" value="' . e((string) $shiftCode) . '" min="1" max="20" class="w-full rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100" required></td>'
        . '<td class="px-4 py-3"><input type="time" name="global_jam_masuk[]" value="' . e(substr((string) ($rule['jam_masuk'] ?? ''), 0, 5)) . '" class="w-full rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100" required></td>'
        . '<td class="px-4 py-3"><input type="time" name="global_jam_keluar[]" value="' . e(substr((string) ($rule['jam_keluar'] ?? ''), 0, 5)) . '" class="w-full rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100" required></td>'
        . '<td class="px-4 py-3"><button type="button" class="inline-flex items-center justify-center rounded-2xl px-3 py-2 text-sm font-semibold text-rose-600 ring-1 ring-rose-200 transition hover:bg-rose-50" data-repeatable-remove>Hapus</button></td>'
        . '</tr>';
}
$globalShiftFields .= '</tbody></table></div>'
    . '<template data-repeatable-template><tr data-repeatable-row>'
    . '<td class="px-4 py-3"><input type="number" name="global_shift_code[]" min="1" max="20" class="w-full rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100" required></td>'
    . '<td class="px-4 py-3"><input type="time" name="global_jam_masuk[]" class="w-full rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100" required></td>'
    . '<td class="px-4 py-3"><input type="time" name="global_jam_keluar[]" class="w-full rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100" required></td>'
    . '<td class="px-4 py-3"><button type="button" class="inline-flex items-center justify-center rounded-2xl px-3 py-2 text-sm font-semibold text-rose-600 ring-1 ring-rose-200 transition hover:bg-rose-50" data-repeatable-remove>Hapus</button></td>'
    . '</tr></template>'
    . '<div class="border-t border-slate-200 bg-slate-50 px-4 py-4"><button type="button" class="inline-flex items-center justify-center rounded-2xl px-4 py-2.5 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-200 transition hover:bg-emerald-50" data-repeatable-add>Tambah Shift</button></div>'
    . '</div>';

$jabatanRuleRows = ShiftService::getJabatanRuleRows((int) $authUser['unit_id']);
if ($jabatanRuleRows === []) {
    $jabatanRuleRows[] = ['jabatan' => '', 'shift_code' => '', 'jam_masuk' => '', 'jam_keluar' => ''];
}

$jabatanRuleFields = '<div class="md:col-span-2 overflow-hidden rounded-[24px] border border-slate-200" data-repeatable-table="jabatan-shift">'
    . '<div class="overflow-x-auto">'
    . '<table class="min-w-full divide-y divide-slate-200 bg-white">'
    . '<thead class="bg-slate-50"><tr>'
    . '<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Jabatan</th>'
    . '<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Shift</th>'
    . '<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Jam Masuk</th>'
    . '<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Jam Keluar</th>'
    . '<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Aksi</th>'
    . '</tr></thead><tbody class="divide-y divide-slate-100" data-repeatable-body>';
foreach ($jabatanRuleRows as $row) {
    $jabatanRuleFields .= '<tr data-repeatable-row>'
        . '<td class="px-4 py-3"><input type="text" name="jabatan_rule_jabatan[]" value="' . e(strtoupper((string) ($row['jabatan'] ?? ''))) . '" class="w-full rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3 text-sm uppercase text-slate-900 outline-none transition focus:border-amber-400 focus:bg-white focus:ring-4 focus:ring-amber-100" placeholder="SECURITY" data-force-uppercase></td>'
        . '<td class="px-4 py-3"><input type="number" name="jabatan_rule_shift[]" value="' . e((string) ($row['shift_code'] ?? '')) . '" min="1" max="20" class="w-full rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-amber-400 focus:bg-white focus:ring-4 focus:ring-amber-100"></td>'
        . '<td class="px-4 py-3"><input type="time" name="jabatan_rule_jam_masuk[]" value="' . e(substr((string) ($row['jam_masuk'] ?? ''), 0, 5)) . '" class="w-full rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-amber-400 focus:bg-white focus:ring-4 focus:ring-amber-100"></td>'
        . '<td class="px-4 py-3"><input type="time" name="jabatan_rule_jam_keluar[]" value="' . e(substr((string) ($row['jam_keluar'] ?? ''), 0, 5)) . '" class="w-full rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-amber-400 focus:bg-white focus:ring-4 focus:ring-amber-100"></td>'
        . '<td class="px-4 py-3"><button type="button" class="inline-flex items-center justify-center rounded-2xl px-3 py-2 text-sm font-semibold text-rose-600 ring-1 ring-rose-200 transition hover:bg-rose-50" data-repeatable-remove>Hapus</button></td>'
        . '</tr>';
}
$jabatanRuleFields .= '</tbody></table></div>'
    . '<template data-repeatable-template><tr data-repeatable-row>'
    . '<td class="px-4 py-3"><input type="text" name="jabatan_rule_jabatan[]" class="w-full rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3 text-sm uppercase text-slate-900 outline-none transition focus:border-amber-400 focus:bg-white focus:ring-4 focus:ring-amber-100" placeholder="SECURITY" data-force-uppercase></td>'
    . '<td class="px-4 py-3"><input type="number" name="jabatan_rule_shift[]" min="1" max="20" class="w-full rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-amber-400 focus:bg-white focus:ring-4 focus:ring-amber-100"></td>'
    . '<td class="px-4 py-3"><input type="time" name="jabatan_rule_jam_masuk[]" class="w-full rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-amber-400 focus:bg-white focus:ring-4 focus:ring-amber-100"></td>'
    . '<td class="px-4 py-3"><input type="time" name="jabatan_rule_jam_keluar[]" class="w-full rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-amber-400 focus:bg-white focus:ring-4 focus:ring-amber-100"></td>'
    . '<td class="px-4 py-3"><button type="button" class="inline-flex items-center justify-center rounded-2xl px-3 py-2 text-sm font-semibold text-rose-600 ring-1 ring-rose-200 transition hover:bg-rose-50" data-repeatable-remove>Hapus</button></td>'
    . '</tr></template>'
    . '<div class="border-t border-slate-200 bg-slate-50 px-4 py-4"><button type="button" class="inline-flex items-center justify-center rounded-2xl px-4 py-2.5 text-sm font-semibold text-amber-700 ring-1 ring-amber-200 transition hover:bg-amber-50" data-repeatable-add>Tambah Override Jabatan</button></div>'
    . '</div>';

$form = '<form action="ajax/save_settings.php" method="post" data-ajax-form class="grid gap-4 md:grid-cols-2">'
    . csrf_input()
    . ui_input('toleransi_terlambat_menit', 'Toleransi Terlambat Global (Menit)', $unit['toleransi_terlambat_menit'] ?? 0, 'number', ['min' => '0', 'required' => 'required'])
    . ui_input('hari_mulai_periode', 'Hari Mulai Periode', $unit['hari_mulai_periode'] ?? 26, 'number', ['min' => '1', 'max' => '31', 'required' => 'required'])
    . ui_input('hari_akhir_periode', 'Hari Akhir Periode', $unit['hari_akhir_periode'] ?? 25, 'number', ['min' => '1', 'max' => '31', 'required' => 'required'])
    . '<div class="md:col-span-2 rounded-[24px] border border-slate-200 bg-slate-50/80 px-4 py-4 text-sm text-slate-600">Toleransi ini berlaku untuk semua karyawan. Jika pada data karyawan diisi sendiri, maka nilai karyawan yang dipakai.</div>'
    . '<div class="md:col-span-2 rounded-[24px] border border-sky-200 bg-sky-50/80 px-4 py-4 text-sm text-sky-700">Periode laporan mengikuti hari mulai dan hari akhir yang Anda isi di sini. Contoh: 26 dan 25 berarti dari tanggal 26 bulan sebelumnya sampai tanggal 25 bulan yang dipilih.</div>'
    . '<div class="md:col-span-2 rounded-[24px] border border-emerald-200 bg-emerald-50/80 px-4 py-4 text-sm text-emerald-700">Isi jam kerja utama di tabel ini. Anda bisa menambah shift baru atau menghapus shift yang tidak dipakai.</div>'
    . $globalShiftFields
    . '<div class="md:col-span-2 rounded-[24px] border border-amber-200 bg-amber-50/80 px-4 py-4 text-sm text-amber-700">Jika jabatan tertentu punya jam kerja berbeda, isi di tabel ini. Satu jabatan bisa punya lebih dari satu shift.</div>'
    . $jabatanRuleFields
    . '<div class="md:col-span-2 flex justify-end">' . ui_button('Simpan Setting', ['type' => 'submit', 'variant' => 'success']) . '</div>'
    . '</form>';

if ($migrationMissing) {
    $form = '<div class="space-y-4">'
        . '<div class="rounded-[24px] border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-700">Pengaturan periode belum siap dipakai karena kolom database belum lengkap. Jalankan file SQL pembaruan terlebih dahulu.</div>'
        . $form
        . '</div>';
}

echo ui_panel(
    'Setting Absensi',
    $form,
    ['subtitle' => 'Unit aktif: ' . $unit['nama_unit'] . '. Atur toleransi, periode, dan jam kerja di sini.']
);
