<?php

require __DIR__ . '/bootstrap/app.php';
$user = Auth::require();

$period = (string) request_value('period', '');
$tanggalAwal = request_value('tanggal_awal');
$tanggalAkhir = request_value('tanggal_akhir');

if ($period !== '' && str_contains($period, '|')) {
    [$tanggalAwal, $tanggalAkhir] = explode('|', $period, 2);
}

if (!$tanggalAwal || !$tanggalAkhir) {
    exit('Tanggal awal dan akhir wajib diisi.');
}

$items = fetch_all(
    'SELECT p.*, u.id AS user_id, u.name, u.email
     FROM penggajian p
     JOIN users u ON u.id = p.user_id
     WHERE u.unit_id = :unit_id AND p.tanggal_awal_gaji = :start AND p.tanggal_akhir_gaji = :end
     ORDER BY u.name',
    ['unit_id' => $user['unit_id'], 'start' => $tanggalAwal, 'end' => $tanggalAkhir]
);

$unit = fetch_one('SELECT * FROM units WHERE id = :id', ['id' => $user['unit_id']]);
$logoPath = null;
if (!empty($unit['logo_unit'])) {
    $candidate = __DIR__ . '/public/storage/' . ltrim((string) $unit['logo_unit'], '/');
    if (is_file($candidate)) {
        $logoPath = $candidate;
    }
}

$totals = [
    'hadir' => 0, 'izin' => 0, 'alpa' => 0, 'sakit' => 0, 'off' => 0,
    'gaji_pokok' => 0, 'tunjangan_bbm' => 0, 'tunjangan_makan' => 0, 'tunjangan_jabatan' => 0,
    'tunjangan_kehadiran' => 0, 'tunjangan_lainnya' => 0, 'lembur' => 0,
    'potongan_kehadiran' => 0, 'potongan_ijin' => 0, 'potongan_khusus' => 0,
    'potongan_terlambat' => 0, 'pot_bpjs_jht' => 0, 'pot_bpjs_kes' => 0,
    'gaji_kotor' => 0, 'gaji_bersih' => 0,
];

$rows = [];
foreach ($items as $index => $item) {
    $attendance = fetch_one(
        "SELECT
            SUM(status = 'hadir') AS hadir,
            SUM(status = 'izin') AS izin,
            SUM(status = 'alpa') AS alpa,
            SUM(status = 'sakit') AS sakit,
            SUM(status = 'off') AS off_day
         FROM absensi
         WHERE user_id = :user_id AND tanggal BETWEEN :start AND :end",
        ['user_id' => $item['user_id'], 'start' => $tanggalAwal, 'end' => $tanggalAkhir]
    ) ?: [];

    $row = [
        'no' => $index + 1,
        'name' => $item['name'],
        'hadir' => (int) ($attendance['hadir'] ?? 0),
        'izin' => (int) ($attendance['izin'] ?? 0),
        'alpa' => (int) ($attendance['alpa'] ?? 0),
        'sakit' => (int) ($attendance['sakit'] ?? 0),
        'off' => (int) ($attendance['off_day'] ?? 0),
        'gaji_pokok' => (int) $item['gaji_pokok'],
        'tunjangan_bbm' => (int) $item['tunjangan_bbm'],
        'tunjangan_makan' => (int) $item['tunjangan_makan'],
        'tunjangan_jabatan' => (int) $item['tunjangan_jabatan'],
        'tunjangan_kehadiran' => (int) $item['tunjangan_kehadiran'],
        'tunjangan_lainnya' => (int) $item['tunjangan_lainnya'],
        'lembur' => (int) $item['lembur'],
        'potongan_kehadiran' => (int) $item['potongan_kehadiran'],
        'potongan_ijin' => (int) $item['potongan_ijin'],
        'potongan_khusus' => (int) $item['potongan_khusus'],
        'potongan_terlambat' => (int) $item['potongan_terlambat'],
        'pot_bpjs_jht' => (int) $item['pot_bpjs_jht'],
        'pot_bpjs_kes' => (int) $item['pot_bpjs_kes'],
        'gaji_kotor' => (int) $item['gaji_kotor'],
        'gaji_bersih' => (int) $item['gaji_bersih'],
    ];
    $rows[] = $row;

    foreach ($totals as $key => $value) {
        if (isset($row[$key])) {
            $totals[$key] += $row[$key];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Penggajian - <?= e($unit['nama_unit'] ?? 'Unit') ?></title>
    <style>
        @page { size: A4 landscape; margin: 8mm; }
        body { font-family: Arial, sans-serif; font-size: 9px; color: #111827; margin: 0; }
        .print-actions { margin-bottom: 12px; }
        .print-actions button { background: #0f172a; color: #fff; border: 0; border-radius: 10px; padding: 10px 14px; cursor: pointer; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #111827; padding-bottom: 10px; margin-bottom: 12px; }
        .logo img { max-height: 52px; max-width: 80px; }
        .company-name { font-size: 16px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 5px 6px; text-align: center; }
        th { background: #0f766e; color: #fff; font-size: 8px; text-transform: uppercase; letter-spacing: .08em; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        tfoot th { background: #111827; color: #fff; }
        .right { text-align: right; }
        .left { text-align: left; }
        @media print { .print-actions { display: none; } }
    </style>
</head>
<body>
    <div class="print-actions">
        <button onclick="window.print()">Print Laporan</button>
    </div>

    <div class="header">
        <div class="logo">
            <?php if ($logoPath): ?>
                <img src="data:image/png;base64,<?= base64_encode((string) file_get_contents($logoPath)) ?>" alt="Logo Unit">
            <?php endif; ?>
        </div>
        <div style="text-align:right;">
            <div class="company-name"><?= e($unit['nama_unit'] ?? '-') ?></div>
            <div><?= e($unit['alamat_unit'] ?? '-') ?></div>
            <div>Telepon: <?= e($unit['no_hp_unit'] ?? '-') ?></div>
        </div>
    </div>

    <h2 style="margin:0 0 6px 0;">Laporan Penggajian</h2>
    <p style="margin:0 0 10px 0;">Periode: <?= e(format_date_id($tanggalAwal)) ?> hingga <?= e(format_date_id($tanggalAkhir)) ?></p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Karyawan</th>
                <th>Hadir</th>
                <th>Izin</th>
                <th>Alpa</th>
                <th>Sakit</th>
                <th>Off</th>
                <th>Gaji Pokok</th>
                <th>Tunjangan BBM</th>
                <th>Tunjangan Makan</th>
                <th>Tunjangan Jabatan</th>
                <th>Tunjangan Kehadiran</th>
                <th>Tunjangan Lainnya</th>
                <th>Lembur</th>
                <th>Pot. Kehadiran</th>
                <th>Pot. Ijin</th>
                <th>Pot. Khusus</th>
                <th>Pot. Telat</th>
                <th>Pot. BPJS JHT</th>
                <th>Pot. BPJS KES</th>
                <th>Gaji Kotor</th>
                <th>Gaji Bersih</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="22">Tidak ada data penggajian dalam periode ini.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e((string) $row['no']) ?></td>
                    <td class="left"><?= e($row['name']) ?></td>
                    <td><?= e((string) $row['hadir']) ?></td>
                    <td><?= e((string) $row['izin']) ?></td>
                    <td><?= e((string) $row['alpa']) ?></td>
                    <td><?= e((string) $row['sakit']) ?></td>
                    <td><?= e((string) $row['off']) ?></td>
                    <td class="right"><?= number_format($row['gaji_pokok'], 0, ',', '.') ?></td>
                    <td class="right"><?= number_format($row['tunjangan_bbm'], 0, ',', '.') ?></td>
                    <td class="right"><?= number_format($row['tunjangan_makan'], 0, ',', '.') ?></td>
                    <td class="right"><?= number_format($row['tunjangan_jabatan'], 0, ',', '.') ?></td>
                    <td class="right"><?= number_format($row['tunjangan_kehadiran'], 0, ',', '.') ?></td>
                    <td class="right"><?= number_format($row['tunjangan_lainnya'], 0, ',', '.') ?></td>
                    <td class="right"><?= number_format($row['lembur'], 0, ',', '.') ?></td>
                    <td class="right"><?= number_format($row['potongan_kehadiran'], 0, ',', '.') ?></td>
                    <td class="right"><?= number_format($row['potongan_ijin'], 0, ',', '.') ?></td>
                    <td class="right"><?= number_format($row['potongan_khusus'], 0, ',', '.') ?></td>
                    <td class="right"><?= number_format($row['potongan_terlambat'], 0, ',', '.') ?></td>
                    <td class="right"><?= number_format($row['pot_bpjs_jht'], 0, ',', '.') ?></td>
                    <td class="right"><?= number_format($row['pot_bpjs_kes'], 0, ',', '.') ?></td>
                    <td class="right"><?= number_format($row['gaji_kotor'], 0, ',', '.') ?></td>
                    <td class="right"><?= number_format($row['gaji_bersih'], 0, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2">Total</th>
                <th><?= e((string) $totals['hadir']) ?></th>
                <th><?= e((string) $totals['izin']) ?></th>
                <th><?= e((string) $totals['alpa']) ?></th>
                <th><?= e((string) $totals['sakit']) ?></th>
                <th><?= e((string) $totals['off']) ?></th>
                <th><?= number_format($totals['gaji_pokok'], 0, ',', '.') ?></th>
                <th><?= number_format($totals['tunjangan_bbm'], 0, ',', '.') ?></th>
                <th><?= number_format($totals['tunjangan_makan'], 0, ',', '.') ?></th>
                <th><?= number_format($totals['tunjangan_jabatan'], 0, ',', '.') ?></th>
                <th><?= number_format($totals['tunjangan_kehadiran'], 0, ',', '.') ?></th>
                <th><?= number_format($totals['tunjangan_lainnya'], 0, ',', '.') ?></th>
                <th><?= number_format($totals['lembur'], 0, ',', '.') ?></th>
                <th><?= number_format($totals['potongan_kehadiran'], 0, ',', '.') ?></th>
                <th><?= number_format($totals['potongan_ijin'], 0, ',', '.') ?></th>
                <th><?= number_format($totals['potongan_khusus'], 0, ',', '.') ?></th>
                <th><?= number_format($totals['potongan_terlambat'], 0, ',', '.') ?></th>
                <th><?= number_format($totals['pot_bpjs_jht'], 0, ',', '.') ?></th>
                <th><?= number_format($totals['pot_bpjs_kes'], 0, ',', '.') ?></th>
                <th><?= number_format($totals['gaji_kotor'], 0, ',', '.') ?></th>
                <th><?= number_format($totals['gaji_bersih'], 0, ',', '.') ?></th>
            </tr>
        </tfoot>
    </table>
</body>
</html>
