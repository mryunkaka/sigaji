<?php

final class AttendanceImportService
{
    public static function import(string $uploadedPath, int $unitId): array
    {
        $ext = strtolower(pathinfo($uploadedPath, PATHINFO_EXTENSION));
        $rows = match ($ext) {
            'csv' => self::parseCsv($uploadedPath),
            'xlsx' => self::parseXlsx($uploadedPath),
            default => throw new RuntimeException('Format file harus CSV atau XLSX.'),
        };

        if (count($rows) <= 1) {
            throw new RuntimeException('File absensi tidak berisi data.');
        }

        $summary = [
            'imported' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }

            $rowNumber = $index + 1;

            try {
                $nama = trim(preg_replace('/\s+/', ' ', strtoupper((string) ($row[1] ?? ''))));
                $jabatan = strtolower(trim((string) ($row[2] ?? '')));
                $tanggal = self::parseDateValue($row[3] ?? null);
                $jamMasukRaw = trim((string) ($row[4] ?? ''));
                $jamKeluarRaw = trim((string) ($row[5] ?? ''));
                $shift = is_numeric($row[6] ?? null) ? (int) $row[6] : null;

                if ($nama === '' || !$tanggal) {
                    $summary['skipped']++;
                    $summary['errors'][] = "Baris {$rowNumber}: nama/tanggal tidak valid.";
                    continue;
                }

                $user = fetch_one('SELECT * FROM users WHERE unit_id = :unit_id AND name = :name LIMIT 1', [
                    'unit_id' => $unitId,
                    'name' => $nama,
                ]);

                if (!$user) {
                    $email = self::generateUniqueEmail($nama, $unitId);
                    execute_query(
                        'INSERT INTO users (name, email, password, jabatan, role, unit_id, tanggal_bergabung, created_at, updated_at)
                         VALUES (:name, :email, :password, :jabatan, :role, :unit_id, :tanggal_bergabung, :created_at, :updated_at)',
                        [
                            'name' => $nama,
                            'email' => $email,
                            'password' => password_hash('password', PASSWORD_BCRYPT),
                            'jabatan' => $jabatan,
                            'role' => 'karyawan',
                            'unit_id' => $unitId,
                            'tanggal_bergabung' => date('Y-m-d'),
                            'created_at' => now_string(),
                            'updated_at' => now_string(),
                        ]
                    );
                    $user = fetch_one('SELECT * FROM users WHERE email = :email LIMIT 1', ['email' => $email]);
                }

                PayrollService::ensureMasterGaji((int) $user['id']);

                $status = self::determineStatus($jamMasukRaw, $jamKeluarRaw, $shift);
                $jamMasuk = $status === 'hadir' ? self::parseTimeValue($jamMasukRaw) : null;
                $jamKeluar = $status === 'hadir' ? self::parseTimeValue($jamKeluarRaw) : null;
                $totalTerlambat = $status === 'hadir'
                    ? self::calculateLateMinutes($jamMasuk, $shift, $jabatan)
                    : 0;

                $master = PayrollService::ensureMasterGaji((int) $user['id']);
                $potonganRate = (int) round((float) ($master['potongan_terlambat'] ?? 1000));
                $exists = fetch_one(
                    'SELECT id FROM absensi WHERE user_id = :user_id AND tanggal = :tanggal LIMIT 1',
                    ['user_id' => $user['id'], 'tanggal' => $tanggal]
                );

                if ($exists) {
                    $summary['skipped']++;
                    continue;
                }

                execute_query(
                    'INSERT INTO absensi (user_id, shift, tanggal, status, jam_masuk, jam_keluar, total_menit_terlambat, jumlah_potongan, keterangan_potongan, created_at, updated_at)
                     VALUES (:user_id, :shift, :tanggal, :status, :jam_masuk, :jam_keluar, :terlambat, :jumlah_potongan, :keterangan_potongan, :created_at, :updated_at)',
                    [
                        'user_id' => $user['id'],
                        'shift' => $shift,
                        'tanggal' => $tanggal,
                        'status' => $status,
                        'jam_masuk' => $jamMasuk,
                        'jam_keluar' => $jamKeluar,
                        'terlambat' => $totalTerlambat,
                        'jumlah_potongan' => $totalTerlambat * $potonganRate,
                        'keterangan_potongan' => $totalTerlambat > 0 ? "Terlambat {$totalTerlambat} menit" : '',
                        'created_at' => now_string(),
                        'updated_at' => now_string(),
                    ]
                );

                $summary['imported']++;
            } catch (Throwable $e) {
                $summary['skipped']++;
                $summary['errors'][] = "Baris {$rowNumber}: " . $e->getMessage();
            }
        }

        return $summary;
    }

    private static function parseCsv(string $path): array
    {
        $rows = [];
        if (($handle = fopen($path, 'rb')) === false) {
            throw new RuntimeException('Gagal membuka file CSV.');
        }

        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            $rows[] = $data;
        }

        fclose($handle);
        return $rows;
    }

    private static function parseXlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File XLSX tidak bisa dibuka.');
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $xml = simplexml_load_string($sharedXml);
            if ($xml) {
                foreach ($xml->si as $item) {
                    $text = '';
                    foreach ($item->t as $t) {
                        $text .= (string) $t;
                    }
                    if ($text === '' && isset($item->r)) {
                        foreach ($item->r as $run) {
                            $text .= (string) $run->t;
                        }
                    }
                    $sharedStrings[] = $text;
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException('Sheet1 XLSX tidak ditemukan.');
        }

        $xml = simplexml_load_string($sheetXml);
        if (!$xml) {
            throw new RuntimeException('XML XLSX tidak valid.');
        }

        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                preg_match('/([A-Z]+)(\d+)/', $ref, $matches);
                $columnIndex = self::columnIndex($matches[1] ?? 'A');
                $type = (string) $cell['t'];
                $value = (string) ($cell->v ?? '');
                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                }
                $cells[$columnIndex] = $value;
            }

            if ($cells !== []) {
                ksort($cells);
                $max = max(array_keys($cells));
                $normalized = array_fill(0, $max + 1, '');
                foreach ($cells as $idx => $value) {
                    $normalized[$idx] = $value;
                }
                $rows[] = $normalized;
            }
        }

        return $rows;
    }

    private static function columnIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }
        return $index - 1;
    }

    private static function parseDateValue($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $days = (float) $value;
            $unix = (int) (($days - 25569) * 86400);
            return gmdate('Y-m-d', $unix);
        }

        $timestamp = strtotime((string) $value);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    private static function parseTimeValue($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $seconds = (int) round(((float) $value % 1) * 86400);
            return gmdate('H:i:s', $seconds);
        }

        $timestamp = strtotime((string) $value);
        return $timestamp ? date('H:i:s', $timestamp) : null;
    }

    private static function determineStatus(string $jamMasuk, string $jamKeluar, ?int $shift): string
    {
        $statusTexts = ['SAKIT', 'IZIN', 'ALPA', 'OFF', 'CUTI'];
        $masukUpper = strtoupper($jamMasuk);
        $keluarUpper = strtoupper($jamKeluar);

        if (in_array($masukUpper, $statusTexts, true)) {
            return strtolower($masukUpper);
        }

        if (in_array($keluarUpper, $statusTexts, true)) {
            return strtolower($keluarUpper);
        }

        if (!in_array($shift, [1, 2, 3], true) && $jamMasuk === '' && $jamKeluar === '') {
            return 'off';
        }

        return ($jamMasuk !== '' || $jamKeluar !== '') ? 'hadir' : 'alpa';
    }

    private static function calculateLateMinutes(?string $jamMasuk, ?int $shift, string $jabatan): int
    {
        if (!$jamMasuk || !$shift) {
            return 0;
        }

        [$hour, $minute] = array_map('intval', explode(':', substr($jamMasuk, 0, 5)));
        $arrival = ($hour * 60) + $minute;
        $role = strtolower(trim($jabatan));

        if ($role === 'security') {
            $target = match ($shift) {
                1 => 8 * 60,
                2 => 16 * 60,
                3 => (23 * 60) + 59,
                default => null,
            };

            if ($target === null) {
                return 0;
            }

            if ($shift === 3) {
                if ($hour < 23 || ($hour === 23 && $minute < 59)) {
                    return 0;
                }
                if ($hour < 12) {
                    $arrival += 1440;
                }
            }

            return max(0, $arrival - $target);
        }

        if ($role === 'general') {
            if ($shift === 1) {
                return max(0, $arrival - (7 * 60));
            }

            if ($shift === 2) {
                if ($arrival < (7 * 60)) {
                    $arrival += 1440;
                }
                return max(0, $arrival - (19 * 60));
            }

            return 0;
        }

        $target = match ($shift) {
            1 => 7 * 60,
            2 => 15 * 60,
            3 => 23 * 60,
            default => null,
        };

        if ($target === null) {
            return 0;
        }

        if ($shift === 3 && $arrival < (7 * 60)) {
            $arrival += 1440;
        }

        return max(0, $arrival - $target);
    }

    private static function generateUniqueEmail(string $name, int $unitId): string
    {
        $base = preg_replace('/[^a-z0-9]+/', '.', strtolower($name));
        $base = trim($base ?: 'user', '.');
        $candidate = "{$base}.u{$unitId}@example.com";

        if (!fetch_one('SELECT id FROM users WHERE email = :email LIMIT 1', ['email' => $candidate])) {
            return $candidate;
        }

        for ($i = 2; $i <= 99; $i++) {
            $candidate = "{$base}.u{$unitId}.{$i}@example.com";
            if (!fetch_one('SELECT id FROM users WHERE email = :email LIMIT 1', ['email' => $candidate])) {
                return $candidate;
            }
        }

        return "{$base}.u{$unitId}." . bin2hex(random_bytes(3)) . '@example.com';
    }
}
