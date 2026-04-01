<?php

require __DIR__ . '/bootstrap/app.php';
$user = Auth::require();

$id = (int) request_value('id');
$autoDownload = (string) request_value('download', '') === '1';
$item = fetch_one(
    'SELECT p.*, u.name, u.kode_absensi, u.jabatan, u.nik, u.npwp, u.no_hp, u.alamat, u.tanggal_bergabung, un.nama_unit, un.alamat_unit, un.no_hp_unit, un.logo_unit
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

$formatShortPeriod = static function (?string $value): string {
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return (string) $value;
    }

    $months = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Apr',
        5 => 'Mei',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Agu',
        9 => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Des',
    ];

    return date('j', $timestamp) . ' ' . ($months[(int) date('n', $timestamp)] ?? date('M', $timestamp));
};

$formatUpperPeriod = static function (?string $value): string {
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return strtoupper((string) $value);
    }

    $months = [
        1 => 'JAN',
        2 => 'FEB',
        3 => 'MAR',
        4 => 'APR',
        5 => 'MEI',
        6 => 'JUN',
        7 => 'JUL',
        8 => 'AGU',
        9 => 'SEP',
        10 => 'OKT',
        11 => 'NOV',
        12 => 'DES',
    ];

    return date('j', $timestamp) . ' ' . ($months[(int) date('n', $timestamp)] ?? strtoupper(date('M', $timestamp)));
};

$sanitizeFilenamePart = static function (?string $value, bool $replaceSpacesWithUnderscore = false): string {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $value = strtoupper($value);
    $value = preg_replace('/[\\\\\\/:*?"<>|]+/', '-', $value) ?? $value;
    $value = preg_replace('/\s+/', $replaceSpacesWithUnderscore ? '_' : ' ', $value) ?? $value;
    $value = trim($value, " .-_");

    return $value;
};

$periodLabel = $formatShortPeriod($item['tanggal_awal_gaji']) . ' - ' . $formatShortPeriod($item['tanggal_akhir_gaji']) . ' ' . date('Y', strtotime($item['tanggal_akhir_gaji']));
$periodLabelUpper = $formatUpperPeriod($item['tanggal_awal_gaji']) . ' - ' . $formatUpperPeriod($item['tanggal_akhir_gaji']) . ' ' . date('Y', strtotime($item['tanggal_akhir_gaji']));
$pdfFilenameParts = array_values(array_filter([
    'SLIP_GAJI',
    $sanitizeFilenamePart((string) ($item['kode_absensi'] ?? ''), true),
    $sanitizeFilenamePart((string) ($item['name'] ?? ''), true),
    $sanitizeFilenamePart($periodLabelUpper),
    $sanitizeFilenamePart((string) ($item['nama_unit'] ?? ''), true),
], static fn ($part) => $part !== ''));
$pdfBaseFilename = implode('-', $pdfFilenameParts);
$pdfFilename = $pdfBaseFilename . '.pdf';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pdfFilename) ?></title>
    <style>
        :root {
            --page-width: 297mm;
            --page-height: 210mm;
            --content-width: 281mm;
            --content-height: 194mm;
        }

        @page { size: A4 landscape; margin: 8mm; }

        * { box-sizing: border-box; }
        html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            line-height: 1.2;
            color: #111827;
            margin: 0;
            background: #e5e7eb;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .print-actions {
            margin: 0 auto 12px;
            width: var(--content-width);
            padding-top: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .print-actions-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .print-actions button {
            background: #0f172a;
            color: #fff;
            border: 0;
            border-radius: 10px;
            padding: 10px 14px;
            cursor: pointer;
        }

        .print-actions p {
            margin: 0;
            color: #475569;
            font-size: 13px;
        }

        .page-shell {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 88px);
            padding: 0 0 16px;
        }

        .page-fit {
            width: var(--page-width);
            height: var(--page-height);
            background: #ffffff;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.14);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .page-fit.exporting {
            box-shadow: none;
        }

        .sheet-frame {
            width: var(--content-width);
            height: var(--content-height);
            overflow: hidden;
            background: #ffffff;
            display: flex;
        }

        .slip {
            border: 1px solid #111827;
            padding: 8px;
            background: #ffffff;
            width: 100%;
            min-height: 100%;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .slip-main {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .header-table,
        .info-table,
        .main-table,
        .summary-table,
        .detail-table,
        .notes-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .header-table td,
        .info-table td {
            padding: 3px 5px;
            vertical-align: top;
        }

        .main-table td,
        .main-table th,
        .summary-table td,
        .summary-table th,
        .detail-table td,
        .detail-table th,
        .notes-table td,
        .notes-table th {
            border: 1px solid #111827;
            padding: 3px 5px;
            vertical-align: top;
            word-break: break-word;
        }

        .main-table > tbody > tr > td,
        .summary-table > tbody > tr > td {
            padding: 0 4px 0 0;
            border: none;
        }

        .main-table > tbody > tr > td:last-child,
        .summary-table > tbody > tr > td:last-child {
            padding-right: 0;
        }

        .logo-box { width: 74px; text-align: center; }
        .logo-box img { max-width: 58px; max-height: 58px; }
        .company-name {
            font-size: 20px;
            line-height: 1.05;
            font-weight: bold;
            text-align: center;
        }

        .section-title {
            background: #dbeafe;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .right { text-align: right; }
        .center { text-align: center; }
        .total-row { font-weight: bold; background: #ecfccb; }
        .notes-table { font-size: 9px; }
        .notes-table td, .notes-table th { padding: 2px 4px; }
        .summary-total {
            padding: 10px;
            background: #ecfccb;
            border: 1px solid #65a30d;
        }
        .summary-total-value {
            font-size: 18px;
            line-height: 1.05;
            font-weight: bold;
        }
        .signature {
            margin-top: auto;
            width: 100%;
        }
        .signature td {
            border: none;
            padding-top: 10px;
        }
        .small { font-size: 9px; line-height: 1.15; color: #4b5563; }

        @media print {
            .print-actions { display: none; }
            body { margin: 0; background: #ffffff; }
            .page-shell { padding: 0; }
            .page-fit {
                box-shadow: none;
            }
            .slip,
            .sheet-frame,
            .page-fit,
            .main-table,
            .summary-table,
            .detail-table,
            .notes-table,
            .signature {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="print-actions">
        <p id="download-status"><?= e($autoDownload ? 'Menyiapkan unduhan PDF...' : 'Halaman print siap dipakai. Klik Print Slip atau Download PDF sesuai kebutuhan.') ?></p>
        <div class="print-actions-group">
            <button type="button" id="download-slip">Download PDF</button>
        </div>
    </div>

    <div class="page-shell">
    <div id="slip-fit" class="page-fit">
    <div class="sheet-frame">
    <div id="slip-content" class="slip">
        <div class="slip-main">
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
                    <div class="summary-total">
                        <div class="summary-total-value">Total diterima: <?= money($item['gaji_bersih']) ?></div>
                        <div class="small" style="margin-top: 6px;"><em>Terbilang: <?= e($gajiBersihText) ?></em></div>
                        <div class="small" style="margin-top: 8px;"><?= e($item['alamat'] ?: '-') ?></div>
                    </div>
                </td>
            </tr>
        </table>
        </div>

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
    </div>
    </div>
    </div>

    <script src="<?= e(asset_url('assets/vendor/html2pdf.bundle.min.js')) ?>"></script>
    <script>
        (() => {
            const fitArea = document.getElementById('slip-fit');
            const slip = document.getElementById('slip-content');
            const downloadButton = document.getElementById('download-slip');
            const downloadStatus = document.getElementById('download-status');
            const pdfBaseFilename = <?= json_encode($pdfBaseFilename, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            const pdfFilename = <?= json_encode($pdfFilename, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            const autoDownload = <?= $autoDownload ? 'true' : 'false' ?>;
            let isDownloading = false;

            if (!fitArea || !slip) {
                return;
            }

            const supportsZoom = 'zoom' in slip.style;

            const resetScale = () => {
                if (supportsZoom) {
                    slip.style.zoom = '1';
                } else {
                    slip.style.transform = 'none';
                    slip.style.transformOrigin = 'top left';
                    slip.style.width = '100%';
                }
            };

            const applyScale = (scale) => {
                const nextScale = Math.max(0.5, Math.min(1, scale));

                if (supportsZoom) {
                    slip.style.zoom = nextScale.toFixed(3);
                    return;
                }

                slip.style.transform = `scale(${nextScale.toFixed(3)})`;
                slip.style.transformOrigin = 'top left';
                slip.style.width = `${(100 / nextScale).toFixed(3)}%`;
            };

            const fitSlipToSinglePage = () => {
                resetScale();

                window.requestAnimationFrame(() => {
                    const availableWidth = fitArea.clientWidth;
                    const availableHeight = fitArea.clientHeight;
                    const naturalWidth = slip.scrollWidth;
                    const naturalHeight = slip.scrollHeight;

                    if (!availableWidth || !availableHeight || !naturalWidth || !naturalHeight) {
                        return;
                    }

                    const scale = Math.min(1, availableWidth / naturalWidth, availableHeight / naturalHeight);
                    applyScale(scale);
                });
            };

            document.addEventListener('DOMContentLoaded', fitSlipToSinglePage);
            window.addEventListener('load', fitSlipToSinglePage);
            window.addEventListener('resize', fitSlipToSinglePage);
            window.addEventListener('beforeprint', fitSlipToSinglePage);
            window.setTimeout(fitSlipToSinglePage, 60);

            const setDownloadState = (loading, message) => {
                isDownloading = loading;

                if (downloadButton) {
                    downloadButton.disabled = loading;
                    downloadButton.textContent = loading ? 'Membuat PDF...' : 'Download PDF';
                }

                if (downloadStatus) {
                    downloadStatus.textContent = message;
                }
            };

            const resolveDownloadFilename = () => {
                const storageKey = `slip-pdf-download-count:${pdfBaseFilename}`;

                try {
                    const currentCount = Number.parseInt(window.localStorage.getItem(storageKey) || '0', 10);
                    const nextCount = Number.isFinite(currentCount) && currentCount > 0 ? currentCount + 1 : 1;
                    window.localStorage.setItem(storageKey, String(nextCount));

                    if (nextCount <= 1) {
                        return pdfFilename;
                    }

                    return `${pdfBaseFilename}-PDF_COPY_${nextCount}.pdf`;
                } catch (error) {
                    console.warn('Local storage unavailable, fallback to base PDF filename.', error);
                    return pdfFilename;
                }
            };

            const downloadPdf = async () => {
                if (isDownloading || typeof window.html2pdf === 'undefined') {
                    if (typeof window.html2pdf === 'undefined' && downloadStatus) {
                        downloadStatus.textContent = 'Library PDF gagal dimuat. Muat ulang halaman lalu coba lagi.';
                    }
                    return;
                }

                setDownloadState(true, 'Membuat file PDF dan memulai unduhan...');
                fitSlipToSinglePage();

                try {
                    await new Promise((resolve) => window.setTimeout(resolve, 250));
                    const nextFilename = resolveDownloadFilename();
                    fitArea.classList.add('exporting');
                    await window.html2pdf().set({
                        filename: nextFilename,
                        margin: 0,
                        image: { type: 'jpeg', quality: 0.98 },
                        html2canvas: {
                            scale: 2,
                            useCORS: true,
                            backgroundColor: '#ffffff',
                        },
                        jsPDF: {
                            unit: 'mm',
                            format: 'a4',
                            orientation: 'landscape',
                        },
                        pagebreak: { mode: ['avoid-all', 'css', 'legacy'] },
                    }).from(fitArea).save();

                    setDownloadState(false, 'PDF berhasil diunduh. Jika browser memblokir unduhan, klik tombol Download PDF.');
                } catch (error) {
                    console.error(error);
                    setDownloadState(false, 'Gagal membuat PDF. Klik tombol Download PDF untuk mencoba lagi.');
                } finally {
                    fitArea.classList.remove('exporting');
                }
            };

            if (downloadButton) {
                downloadButton.addEventListener('click', downloadPdf);
            }

            if (autoDownload) {
                window.addEventListener('load', () => {
                    window.setTimeout(downloadPdf, 400);
                }, { once: true });
            }
        })();
    </script>
</body>
</html>
