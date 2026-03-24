<?php

require __DIR__ . '/bootstrap/app.php';
$user = Auth::require();

$id = (int) request_value('id');
$item = fetch_one(
    'SELECT p.*, u.name, u.jabatan, un.nama_unit, un.alamat_unit
     FROM penggajian p
     JOIN users u ON u.id = p.user_id
     JOIN units un ON un.id = u.unit_id
     WHERE p.id = :id AND u.unit_id = :unit_id',
    ['id' => $id, 'unit_id' => $user['unit_id']]
);

if (!$item) {
    exit('Slip tidak ditemukan.');
}

$attendance = fetch_all(
    'SELECT status, COUNT(*) AS total
     FROM absensi
     WHERE user_id = :user_id AND tanggal BETWEEN :start AND :end
     GROUP BY status',
    ['user_id' => $item['user_id'], 'start' => $item['tanggal_awal_gaji'], 'end' => $item['tanggal_akhir_gaji']]
);

$counts = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'cuti' => 0, 'alpa' => 0, 'off' => 0];
foreach ($attendance as $row) {
    if (isset($counts[$row['status']])) {
        $counts[$row['status']] = (int) $row['total'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Slip Gaji</title>
    <link rel="stylesheet" href="<?= e(asset_url('assets/app.css')) ?>">
</head>
<body class="overflow-auto bg-slate-100 p-6 text-slate-900">
    <div class="mx-auto max-w-4xl rounded-[32px] bg-white p-8 shadow-sm">
        <div class="flex items-start justify-between border-b border-slate-200 pb-6">
            <div>
                <p class="text-xs font-semibold tracking-[0.3em] text-slate-500">SLIP GAJI</p>
                <h1 class="mt-3 text-3xl font-semibold"><?= e($item['nama_unit']) ?></h1>
                <p class="mt-2 text-sm text-slate-500"><?= e($item['alamat_unit'] ?? '-') ?></p>
            </div>
            <button onclick="window.print()" class="rounded-2xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Print</button>
        </div>

        <div class="mt-6 grid gap-6 md:grid-cols-2">
            <div class="rounded-3xl bg-slate-50 p-5">
                <p class="text-sm text-slate-500">Karyawan</p>
                <p class="mt-2 text-xl font-semibold"><?= e($item['name']) ?></p>
                <p class="mt-1 text-sm text-slate-500"><?= e($item['jabatan'] ?: '-') ?></p>
            </div>
            <div class="rounded-3xl bg-slate-50 p-5">
                <p class="text-sm text-slate-500">Periode</p>
                <p class="mt-2 text-xl font-semibold"><?= e($item['tanggal_awal_gaji']) ?> s/d <?= e($item['tanggal_akhir_gaji']) ?></p>
                <p class="mt-1 text-sm text-slate-500">Dicetak <?= e(date('d F Y H:i')) ?></p>
            </div>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-2">
            <div>
                <h2 class="mb-4 text-lg font-semibold">Komponen Gaji</h2>
                <div class="space-y-3 rounded-3xl border border-slate-200 p-5">
                    <div class="flex justify-between"><span>Gaji Pokok</span><span><?= money($item['gaji_pokok']) ?></span></div>
                    <div class="flex justify-between"><span>Tunjangan BBM</span><span><?= money($item['tunjangan_bbm']) ?></span></div>
                    <div class="flex justify-between"><span>Tunjangan Makan</span><span><?= money($item['tunjangan_makan']) ?></span></div>
                    <div class="flex justify-between"><span>Tunjangan Jabatan</span><span><?= money($item['tunjangan_jabatan']) ?></span></div>
                    <div class="flex justify-between"><span>Tunjangan Kehadiran</span><span><?= money($item['tunjangan_kehadiran']) ?></span></div>
                    <div class="flex justify-between"><span>Tunjangan Lainnya</span><span><?= money($item['tunjangan_lainnya']) ?></span></div>
                    <div class="flex justify-between"><span>Lembur</span><span><?= money($item['lembur']) ?></span></div>
                    <div class="flex justify-between border-t border-slate-200 pt-3 font-semibold"><span>Gaji Kotor</span><span><?= money($item['gaji_kotor']) ?></span></div>
                </div>
            </div>
            <div>
                <h2 class="mb-4 text-lg font-semibold">Potongan</h2>
                <div class="space-y-3 rounded-3xl border border-slate-200 p-5">
                    <div class="flex justify-between"><span>Potongan Kehadiran</span><span><?= money($item['potongan_kehadiran']) ?></span></div>
                    <div class="flex justify-between"><span>Potongan Ijin</span><span><?= money($item['potongan_ijin']) ?></span></div>
                    <div class="flex justify-between"><span>Potongan Khusus / Hutang</span><span><?= money($item['potongan_khusus']) ?></span></div>
                    <div class="flex justify-between"><span>Potongan Terlambat</span><span><?= money($item['potongan_terlambat']) ?></span></div>
                    <div class="flex justify-between"><span>BPJS JHT</span><span><?= money($item['pot_bpjs_jht']) ?></span></div>
                    <div class="flex justify-between"><span>BPJS Kesehatan</span><span><?= money($item['pot_bpjs_kes']) ?></span></div>
                    <div class="flex justify-between border-t border-slate-200 pt-3 font-semibold"><span>Total Potongan</span><span><?= money($item['total_potongan']) ?></span></div>
                </div>
            </div>
        </div>

        <div class="mt-8 grid gap-4 md:grid-cols-3 lg:grid-cols-6">
            <?php foreach ($counts as $label => $value): ?>
                <div class="rounded-3xl bg-slate-50 p-4 text-center">
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500"><?= e($label) ?></p>
                    <p class="mt-2 text-2xl font-semibold"><?= e((string) $value) ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-8 rounded-[28px] bg-emerald-50 p-6">
            <p class="text-sm text-emerald-700">Gaji Bersih</p>
            <p class="mt-2 text-4xl font-semibold text-emerald-700"><?= money($item['gaji_bersih']) ?></p>
        </div>
    </div>
</body>
</html>
