<?php

require __DIR__ . '/bootstrap/app.php';
$user = Auth::require();

$id = (int) request_value('id');
$item = fetch_one(
    'SELECT p.*, u.name, u.jabatan, u.nik, u.npwp, u.no_hp, u.alamat, u.tanggal_bergabung, un.nama_unit, un.alamat_unit, un.no_hp_unit, un.logo_unit
     FROM penggajian p
     JOIN users u ON u.id = p.user_id
     JOIN units un ON un.id = u.unit_id
     WHERE p.id = :id AND u.unit_id = :unit_id',
    ['id' => $id, 'unit_id' => $user['unit_id']]
);

if (!$item) {
    exit('Slip tidak ditemukan.');
}

$absensiRows = fetch_all(
    'SELECT *
     FROM absensi
     WHERE user_id = :user_id AND tanggal BETWEEN :start AND :end
     ORDER BY tanggal ASC, id ASC',
    ['user_id' => $item['user_id'], 'start' => $item['tanggal_awal_gaji'], 'end' => $item['tanggal_akhir_gaji']]
);

$counts = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'cuti' => 0, 'alpa' => 0, 'off' => 0];
$lemburRows = [];
$potonganKhususRows = [];
$totalTerlambat = 0;

foreach ($absensiRows as $row) {
    if (isset($counts[$row['status']])) {
        $counts[$row['status']]++;
    }
    $totalTerlambat += (int) ($row['total_menit_terlambat'] ?? 0);
    if ((int) ($row['lembur'] ?? 0) > 0) {
        $lemburRows[] = $row;
    }
    if ((int) ($row['potongan_khusus'] ?? 0) > 0) {
        $potonganKhususRows[] = $row;
    }
}

$owner = fetch_one(
    'SELECT name FROM users WHERE unit_id = :unit_id AND role = :role ORDER BY id ASC LIMIT 1',
    ['unit_id' => $user['unit_id'], 'role' => 'owner']
);

$slipNumber = 'SLIP/' . $item['id'] . '/' . date('m', strtotime($item['tanggal_awal_gaji'])) . '/' . date('Y', strtotime($item['tanggal_awal_gaji']));
$gajiBersihText = ucwords(trim(terbilang_id((int) $item['gaji_bersih']))) . ' rupiah';
$logoPath = public_asset_path($item['logo_unit'] ?? null);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Slip Gaji - <?= e($item['nama_unit']) ?></title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #111827; margin: 0; background: #e5e7eb; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .print-actions { margin-bottom: 12px; }
        .print-actions button { background: #0f172a; color: #fff; border: 0; border-radius: 10px; padding: 10px 14px; cursor: pointer; }
        .slip { border: 1px solid #111827; padding: 10px; background: #ffffff; }
        .header-table, .info-table, .main-table, .summary-table, .detail-table, .notes-table { width: 100%; border-collapse: collapse; }
        .header-table td, .info-table td { padding: 4px 6px; vertical-align: top; }
        .main-table td, .main-table th, .summary-table td, .summary-table th, .detail-table td, .detail-table th, .notes-table td, .notes-table th { border: 1px solid #111827; padding: 5px 6px; vertical-align: top; }
        .logo-box { width: 88px; text-align: center; }
        .logo-box img { max-width: 74px; max-height: 74px; }
        .company-name { font-size: 24px; font-weight: bold; text-align: center; }
        .section-title { background: #dbeafe; font-weight: bold; text-transform: uppercase; }
        .right { text-align: right; }
        .center { text-align: center; }
        .total-row { font-weight: bold; background: #ecfccb; }
        .signature { margin-top: 18px; width: 100%; }
        .signature td { border: none; padding-top: 20px; }
        .small { font-size: 10px; color: #4b5563; }
        @media print {
            .print-actions { display: none; }
            body { margin: 0; background: #ffffff; }
        }
    </style>
</head>
<body>
    <div class="print-actions">
        <button onclick="window.print()">Print Slip</button>
    </div>

    <div class="slip">
        <table class="header-table">
            <tr>
                <td class="logo-box" rowspan="3">
                    <?php if ($logoPath): ?>
                        <img src="data:image/png;base64,<?= base64_encode((string) file_get_contents($logoPath)) ?>" alt="Logo Unit">
                    <?php endif; ?>
                </td>
                <td class="company-name" rowspan="2"><?= e($item['nama_unit']) ?></td>
                <td width="13%">No Slip</td>
                <td width="18%">: <?= e($slipNumber) ?></td>
            </tr>
            <tr>
                <td>Tanggal Cetak</td>
                <td>: <?= e(format_date_id(now_string(), true)) ?></td>
            </tr>
            <tr>
                <td colspan="3" style="font-weight: bold;">Salary Slip</td>
            </tr>
        </table>

        <table class="info-table" style="margin-top: 8px; background:#f8fafc; border:1px solid #111827;">
            <tr>
                <td width="14%">NIK</td>
                <td width="36%">: <?= e($item['nik'] ?: '-') ?></td>
                <td width="14%">Periode</td>
                <td width="36%">: <?= e(format_date_id($item['tanggal_awal_gaji'])) ?> - <?= e(format_date_id($item['tanggal_akhir_gaji'])) ?></td>
            </tr>
            <tr>
                <td>Nama</td>
                <td>: <?= e($item['name']) ?></td>
                <td>Telepon</td>
                <td>: <?= e($item['no_hp'] ?: '-') ?></td>
            </tr>
            <tr>
                <td>Jabatan</td>
                <td>: <?= e($item['jabatan'] ?: '-') ?></td>
                <td>NPWP</td>
                <td>: <?= e($item['npwp'] ?: '-') ?></td>
            </tr>
            <tr>
                <td>Departemen/Unit</td>
                <td>: <?= e($item['nama_unit']) ?></td>
                <td>Tanggal Bergabung</td>
                <td>: <?= e($item['tanggal_bergabung'] ? format_date_id($item['tanggal_bergabung']) : '-') ?></td>
            </tr>
        </table>

        <table class="main-table" style="margin-top: 8px;">
            <tr>
                <td width="33.33%">
                    <table class="detail-table">
                        <tr class="section-title"><td colspan="2">Komponen Pendapatan</td></tr>
                        <tr><td>Gaji Pokok</td><td class="right"><?= money($item['gaji_pokok']) ?></td></tr>
                        <tr><td>Tunjangan Jabatan</td><td class="right"><?= (int) $item['tunjangan_jabatan'] > 0 ? money($item['tunjangan_jabatan']) : '-' ?></td></tr>
                        <tr><td>Tunjangan BBM</td><td class="right"><?= (int) $item['tunjangan_bbm'] > 0 ? money($item['tunjangan_bbm']) : '-' ?></td></tr>
                        <tr><td>Tunjangan Lainnya</td><td class="right"><?= (int) $item['tunjangan_lainnya'] > 0 ? money($item['tunjangan_lainnya']) : '-' ?></td></tr>
                        <tr><td>Tunjangan Makan</td><td class="right"><?= (int) $item['tunjangan_makan'] > 0 ? money($item['tunjangan_makan']) : '-' ?></td></tr>
                        <tr><td>Tunjangan Kehadiran</td><td class="right"><?= (int) $item['tunjangan_kehadiran'] > 0 ? money($item['tunjangan_kehadiran']) : '-' ?></td></tr>
                        <tr><td>Tunjangan Lembur</td><td class="right"><?= (int) $item['lembur'] > 0 ? money($item['lembur']) : '-' ?></td></tr>
                        <tr class="total-row"><td>Total Pendapatan (+)</td><td class="right"><?= money($item['gaji_kotor']) ?></td></tr>
                    </table>
                </td>
                <td width="33.33%">
                    <table class="detail-table">
                        <tr class="section-title"><td colspan="2">Potongan Perusahaan & Pihak Kedua</td></tr>
                        <tr><td>Potongan Kehadiran</td><td class="right"><?= money($item['potongan_kehadiran']) ?></td></tr>
                        <tr><td>Potongan BPJS JHT</td><td class="right"><?= money($item['pot_bpjs_jht']) ?></td></tr>
                        <tr><td>Potongan BPJS Kesehatan</td><td class="right"><?= money($item['pot_bpjs_kes']) ?></td></tr>
                        <tr><td>Potongan Ijin</td><td class="right"><?= money($item['potongan_ijin']) ?></td></tr>
                        <tr><td>Potongan Terlambat</td><td class="right"><?= money($item['potongan_terlambat']) ?></td></tr>
                        <tr><td>Potongan Khusus</td><td class="right"><?= money($item['potongan_khusus']) ?></td></tr>
                        <tr class="total-row"><td>Total Potongan (-)</td><td class="right"><?= money($item['total_potongan']) ?></td></tr>
                    </table>
                </td>
                <td width="33.33%">
                    <table class="detail-table">
                        <tr class="section-title"><td colspan="3">Detail Lain-Lain</td></tr>
                        <tr>
                            <td width="24%">Lembur</td>
                            <td colspan="2">
                                <?php if ($lemburRows): ?>
                                    <table class="notes-table">
                                        <?php foreach ($lemburRows as $row): ?>
                                            <tr>
                                                <td width="24%"><?= e(format_date_id($row['tanggal'])) ?></td>
                                                <td width="20%" class="right"><?= number_format((int) $row['lembur'], 0, ',', '.') ?></td>
                                                <td><?= e($row['keterangan_lembur'] ?: '-') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </table>
                                <?php else: ?>
                                    Tidak ada data lembur.
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Ijin</td>
                            <td colspan="2">
                                <?php if ($counts['izin'] > 0): ?>
                                    <strong>Total Ijin:</strong> <?= e((string) $counts['izin']) ?> hari<br>
                                    <strong>Total Potongan Ijin:</strong> <?= money($item['potongan_ijin']) ?>
                                <?php else: ?>
                                    Tidak ada data ijin.
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Pot. Khusus</td>
                            <td colspan="2">
                                <?php if ($potonganKhususRows): ?>
                                    <table class="notes-table">
                                        <?php foreach ($potonganKhususRows as $row): ?>
                                            <tr>
                                                <td width="24%"><?= e(format_date_id($row['tanggal'])) ?></td>
                                                <td width="20%" class="right"><?= money($row['potongan_khusus']) ?></td>
                                                <td><?= e($row['keterangan_khusus'] ?: '-') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </table>
                                <?php else: ?>
                                    Tidak ada data potongan khusus.
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Keterlambatan</td>
                            <td colspan="2">
                                <?php if ($totalTerlambat > 0): ?>
                                    <strong>Total:</strong> <?= e((string) $totalTerlambat) ?> menit<br>
                                    <strong>Total Denda:</strong> <?= money($item['potongan_terlambat']) ?>
                                <?php else: ?>
                                    Tidak ada data keterlambatan.
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="summary-table" style="margin-top: 8px;">
            <tr>
                <td width="33.33%">
                    <table class="detail-table">
                        <tr class="section-title"><td colspan="2">Detail Absen</td></tr>
                        <tr><td>Hadir</td><td><?= e((string) $counts['hadir']) ?></td></tr>
                        <tr><td>Ijin</td><td><?= e((string) $counts['izin']) ?></td></tr>
                        <tr><td>Off</td><td><?= e((string) $counts['off']) ?></td></tr>
                        <tr><td>Cuti</td><td><?= e((string) $counts['cuti']) ?></td></tr>
                        <tr><td>Alpa</td><td><?= e((string) $counts['alpa']) ?></td></tr>
                        <tr><td>Sakit</td><td><?= e((string) $counts['sakit']) ?></td></tr>
                    </table>
                </td>
                <td width="66.66%" style="padding-left: 10px;">
                    <div style="padding: 14px 12px; background:#ecfccb; border:1px solid #65a30d;">
                        <div style="font-size: 22px; font-weight: bold;">Total diterima: <?= money($item['gaji_bersih']) ?></div>
                        <div class="small" style="margin-top: 6px;"><em>Terbilang: <?= e($gajiBersihText) ?></em></div>
                        <div class="small" style="margin-top: 8px;"><?= e($item['alamat'] ?: '-') ?></div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="signature">
            <tr>
                <td width="70%"></td>
                <td class="center">
                    Diverifikasi Oleh<br><br><br>
                    <strong><?= e($owner['name'] ?? $user['name']) ?></strong>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
