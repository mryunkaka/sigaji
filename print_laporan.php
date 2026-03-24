<?php

require __DIR__ . '/bootstrap/app.php';
$user = Auth::require();

$tanggalAwal = request_value('tanggal_awal');
$tanggalAkhir = request_value('tanggal_akhir');

if (!$tanggalAwal || !$tanggalAkhir) {
    exit('Tanggal awal dan akhir wajib diisi.');
}

$items = fetch_all(
    'SELECT p.*, u.name
     FROM penggajian p
     JOIN users u ON u.id = p.user_id
     WHERE u.unit_id = :unit_id AND p.tanggal_awal_gaji = :start AND p.tanggal_akhir_gaji = :end
     ORDER BY u.name',
    ['unit_id' => $user['unit_id'], 'start' => $tanggalAwal, 'end' => $tanggalAkhir]
);

$unit = fetch_one('SELECT * FROM units WHERE id = :id', ['id' => $user['unit_id']]);
$total = 0;
foreach ($items as $item) {
    $total += (int) $item['gaji_bersih'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Penggajian</title>
    <link rel="stylesheet" href="<?= e(asset_url('assets/app.css')) ?>">
</head>
<body class="overflow-auto bg-slate-100 p-6 text-slate-900">
    <div class="mx-auto max-w-6xl rounded-[32px] bg-white p-8 shadow-sm">
        <div class="flex items-start justify-between border-b border-slate-200 pb-6">
            <div>
                <p class="text-xs font-semibold tracking-[0.3em] text-slate-500">LAPORAN PENGGAJIAN</p>
                <h1 class="mt-3 text-3xl font-semibold"><?= e($unit['nama_unit'] ?? '-') ?></h1>
                <p class="mt-2 text-sm text-slate-500">Periode <?= e($tanggalAwal) ?> s/d <?= e($tanggalAkhir) ?></p>
            </div>
            <button onclick="window.print()" class="rounded-2xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Print</button>
        </div>

        <table class="mt-8 min-w-full divide-y divide-slate-200 overflow-hidden rounded-3xl border border-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Nama</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Gaji Pokok</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Gaji Kotor</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Potongan</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Gaji Bersih</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if (!$items): ?>
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Tidak ada data penggajian.</td></tr>
                <?php endif; ?>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="px-4 py-3 font-medium"><?= e($item['name']) ?></td>
                        <td class="px-4 py-3"><?= money($item['gaji_pokok']) ?></td>
                        <td class="px-4 py-3"><?= money($item['gaji_kotor']) ?></td>
                        <td class="px-4 py-3"><?= money($item['total_potongan']) ?></td>
                        <td class="px-4 py-3"><?= money($item['gaji_bersih']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="mt-8 rounded-[28px] bg-emerald-50 p-6">
            <p class="text-sm text-emerald-700">Total Pengeluaran Gaji</p>
            <p class="mt-2 text-4xl font-semibold text-emerald-700"><?= money($total) ?></p>
        </div>
    </div>
</body>
</html>
