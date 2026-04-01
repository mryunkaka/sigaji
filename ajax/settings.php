<?php

require __DIR__ . '/../bootstrap/app.php';
$authUser = Auth::require();

$unit = fetch_one(
    'SELECT id, nama_unit, toleransi_terlambat_menit
     FROM units
     WHERE id = :id
     LIMIT 1',
    ['id' => $authUser['unit_id']]
);

if (!$unit) {
    echo ui_panel('Setting Absensi', '<div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">Unit aktif tidak ditemukan.</div>');
    return;
}

$form = '<form action="ajax/save_settings.php" method="post" data-ajax-form class="grid gap-4 md:grid-cols-2">'
    . csrf_input()
    . ui_input('toleransi_terlambat_menit', 'Toleransi Terlambat Global (Menit)', $unit['toleransi_terlambat_menit'] ?? 0, 'number', ['min' => '0', 'required' => 'required'])
    . '<div class="md:col-span-2 rounded-[24px] border border-slate-200 bg-slate-50/80 px-4 py-4 text-sm text-slate-600">Nilai ini berlaku untuk seluruh karyawan pada unit aktif. Jika toleransi pada user diisi, nilai user akan meng-override setting global.</div>'
    . '<div class="md:col-span-2 flex justify-end">' . ui_button('Simpan Setting', ['type' => 'submit', 'variant' => 'success']) . '</div>'
    . '</form>';

echo ui_panel(
    'Setting Absensi',
    $form,
    ['subtitle' => 'Unit aktif: ' . $unit['nama_unit'] . '. Atur toleransi keterlambatan default per unit di sini.']
);
