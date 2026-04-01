<?php

final class AttendanceImportConflictException extends RuntimeException
{
    public function __construct(private array $conflict)
    {
        parent::__construct((string) ($conflict['message'] ?? 'Konflik kode absensi ditemukan.'));
    }

    public function conflict(): array
    {
        return $this->conflict;
    }
}

final class AttendanceImportService
{
    public static function importUploadedFile(string $temporaryPath, string $originalName, int $unitId, array $options = []): array
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        return self::import($temporaryPath, $unitId, $extension, $options);
    }

    public static function import(string $uploadedPath, int $unitId, ?string $extension = null, array $options = []): array
    {
        $ext = strtolower($extension ?: pathinfo($uploadedPath, PATHINFO_EXTENSION));
        $rows = match ($ext) {
            'csv' => self::parseCsv($uploadedPath),
            'xlsx' => self::parseXlsx($uploadedPath),
            default => throw new RuntimeException('Format file harus CSV atau XLSX.'),
        };

        if (count($rows) <= 1) {
            throw new RuntimeException('File absensi tidak berisi data.');
        }

        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }

            $rowNumber = $index + 1;
            $nama = trim(preg_replace('/\s+/', ' ', strtoupper((string) ($row[1] ?? ''))));
            $tanggal = self::parseDateValue($row[3] ?? null);
            if ($nama === '' || !$tanggal) {
                continue;
            }

            if (self::normalizeAttendanceCode($row[0] ?? '') === null) {
                throw new RuntimeException("Baris {$rowNumber}: KODE FINGER wajib diisi. Import dibatalkan dan tidak ada data yang disimpan.");
            }
        }

        $summary = [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
            'date_range' => [
                'start' => null,
                'end' => null,
            ],
        ];

        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }

            $rowNumber = $index + 1;

            try {
                $kodeAbsensi = self::normalizeAttendanceCode($row[0] ?? '');
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

                if ($summary['date_range']['start'] === null || $tanggal < $summary['date_range']['start']) {
                    $summary['date_range']['start'] = $tanggal;
                }

                if ($summary['date_range']['end'] === null || $tanggal > $summary['date_range']['end']) {
                    $summary['date_range']['end'] = $tanggal;
                }

                $user = self::resolveImportUser($unitId, $nama, $jabatan, $kodeAbsensi, $options);

                PayrollService::ensureMasterGaji((int) $user['id']);

                $status = self::determineStatus($jamMasukRaw, $jamKeluarRaw, $shift);
                $jamMasuk = $status === 'hadir' ? self::parseTimeValue($jamMasukRaw) : null;
                $jamKeluar = $status === 'hadir' ? self::parseTimeValue($jamKeluarRaw) : null;
                $shiftContext = ShiftService::resolveEmployeeShiftContext($user, $shift);
                $totalTerlambat = AttendanceRules::calculateLateFromRecord(
                    $status,
                    $jamMasuk,
                    $shift,
                    $jabatan,
                    (int) ($user['toleransi_terlambat_menit_efektif'] ?? 0),
                    $shiftContext['scheduled_jam_masuk'] ?? null
                );

                $master = PayrollService::ensureMasterGaji((int) $user['id']);
                $potonganRate = (int) round((float) ($master['potongan_terlambat'] ?? 1000));
                $payload = [
                    'shift' => $shift,
                    'tanggal' => $tanggal,
                    'status' => $status,
                    'jam_masuk' => $jamMasuk,
                    'jam_keluar' => $jamKeluar,
                    'total_menit_terlambat' => $totalTerlambat,
                    'jumlah_potongan' => $totalTerlambat * $potonganRate,
                    'keterangan_potongan' => $totalTerlambat > 0 ? "Terlambat {$totalTerlambat} menit" : '',
                ];

                $existing = fetch_one(
                    'SELECT id, shift, tanggal, status, jam_masuk, jam_keluar, total_menit_terlambat, jumlah_potongan, keterangan_potongan
                     FROM absensi
                     WHERE user_id = :user_id AND tanggal = :tanggal
                     LIMIT 1',
                    ['user_id' => $user['id'], 'tanggal' => $tanggal]
                );

                if ($existing) {
                    if (self::isSameAttendance($existing, $payload)) {
                        $summary['skipped']++;
                        continue;
                    }

                    execute_query(
                        'UPDATE absensi
                         SET shift = :shift,
                             status = :status,
                             jam_masuk = :jam_masuk,
                             jam_keluar = :jam_keluar,
                             total_menit_terlambat = :total_menit_terlambat,
                             jumlah_potongan = :jumlah_potongan,
                             keterangan_potongan = :keterangan_potongan,
                             updated_at = :updated_at
                         WHERE id = :id',
                        [
                            'shift' => $payload['shift'],
                            'status' => $payload['status'],
                            'jam_masuk' => $payload['jam_masuk'],
                            'jam_keluar' => $payload['jam_keluar'],
                            'total_menit_terlambat' => $payload['total_menit_terlambat'],
                            'jumlah_potongan' => $payload['jumlah_potongan'],
                            'keterangan_potongan' => $payload['keterangan_potongan'],
                            'updated_at' => now_string(),
                            'id' => $existing['id'],
                        ]
                    );

                    $summary['updated']++;
                    continue;
                }

                execute_query(
                    'INSERT INTO absensi (user_id, shift, tanggal, status, jam_masuk, jam_keluar, total_menit_terlambat, jumlah_potongan, keterangan_potongan, created_at, updated_at)
                     VALUES (:user_id, :shift, :tanggal, :status, :jam_masuk, :jam_keluar, :terlambat, :jumlah_potongan, :keterangan_potongan, :created_at, :updated_at)',
                    [
                        'user_id' => $user['id'],
                        'shift' => $payload['shift'],
                        'tanggal' => $payload['tanggal'],
                        'status' => $payload['status'],
                        'jam_masuk' => $payload['jam_masuk'],
                        'jam_keluar' => $payload['jam_keluar'],
                        'terlambat' => $payload['total_menit_terlambat'],
                        'jumlah_potongan' => $payload['jumlah_potongan'],
                        'keterangan_potongan' => $payload['keterangan_potongan'],
                        'created_at' => now_string(),
                        'updated_at' => now_string(),
                    ]
                );

                $summary['imported']++;
            } catch (AttendanceImportConflictException $e) {
                throw $e;
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
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('Ekstensi ZipArchive PHP belum aktif, file XLSX belum bisa diproses.');
        }

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
            $numeric = (float) $value;
            $fraction = fmod($numeric, 1.0);
            if ($fraction < 0) {
                $fraction += 1.0;
            }
            $seconds = (int) round($fraction * 86400);
            if ($seconds >= 86400) {
                $seconds = 86399;
            }
            return gmdate('H:i:s', $seconds);
        }

        $timestamp = strtotime((string) $value);
        return $timestamp ? date('H:i:s', $timestamp) : null;
    }

    private static function isSameAttendance(array $existing, array $payload): bool
    {
        $normalize = static function ($value): string {
            if ($value === null || $value === '') {
                return '';
            }
            return (string) $value;
        };

        return $normalize($existing['shift'] ?? null) === $normalize($payload['shift'] ?? null)
            && $normalize($existing['tanggal'] ?? null) === $normalize($payload['tanggal'] ?? null)
            && $normalize($existing['status'] ?? null) === $normalize($payload['status'] ?? null)
            && $normalize($existing['jam_masuk'] ?? null) === $normalize($payload['jam_masuk'] ?? null)
            && $normalize($existing['jam_keluar'] ?? null) === $normalize($payload['jam_keluar'] ?? null)
            && (int) ($existing['total_menit_terlambat'] ?? 0) === (int) ($payload['total_menit_terlambat'] ?? 0)
            && (int) ($existing['jumlah_potongan'] ?? 0) === (int) ($payload['jumlah_potongan'] ?? 0)
            && $normalize($existing['keterangan_potongan'] ?? null) === $normalize($payload['keterangan_potongan'] ?? null);
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

        if (($shift === null || $shift <= 0) && $jamMasuk === '' && $jamKeluar === '') {
            return 'off';
        }

        return ($jamMasuk !== '' || $jamKeluar !== '') ? 'hadir' : 'alpa';
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

    private static function normalizeAttendanceCode($value): ?string
    {
        $normalized = strtoupper(trim((string) $value));
        return $normalized === '' ? null : preg_replace('/\s+/', '', $normalized);
    }

    private static function fetchImportUserByCode(int $unitId, string $code): ?array
    {
        return fetch_one(
            'SELECT u.*,
                    COALESCE(u.toleransi_terlambat_menit, un.toleransi_terlambat_menit, 0) AS toleransi_terlambat_menit_efektif
             FROM users u
             JOIN units un ON un.id = u.unit_id
             WHERE u.unit_id = :unit_id
               AND u.kode_absensi = :kode_absensi
             LIMIT 1',
            [
                'unit_id' => $unitId,
                'kode_absensi' => $code,
            ]
        );
    }

    private static function fetchImportUserByName(int $unitId, string $name): ?array
    {
        return fetch_one(
            'SELECT u.*,
                    COALESCE(u.toleransi_terlambat_menit, un.toleransi_terlambat_menit, 0) AS toleransi_terlambat_menit_efektif
             FROM users u
             JOIN units un ON un.id = u.unit_id
             WHERE u.unit_id = :unit_id
               AND UPPER(TRIM(u.name)) = :name
             ORDER BY CASE WHEN u.tanggal_resign IS NULL THEN 0 ELSE 1 END, u.id DESC
             LIMIT 1',
            [
                'unit_id' => $unitId,
                'name' => strtoupper(trim($name)),
            ]
        );
    }

    private static function createImportedUser(int $unitId, string $name, string $jabatan, ?string $kodeAbsensi): array
    {
        $email = self::generateUniqueEmail($name, $unitId);
        execute_query(
            'INSERT INTO users (name, email, password, jabatan, role, unit_id, kode_absensi, tanggal_bergabung, created_at, updated_at)
             VALUES (:name, :email, :password, :jabatan, :role, :unit_id, :kode_absensi, :tanggal_bergabung, :created_at, :updated_at)',
            [
                'name' => $name,
                'email' => $email,
                'password' => password_hash('password', PASSWORD_BCRYPT),
                'jabatan' => $jabatan,
                'role' => 'karyawan',
                'unit_id' => $unitId,
                'kode_absensi' => $kodeAbsensi,
                'tanggal_bergabung' => date('Y-m-d'),
                'created_at' => now_string(),
                'updated_at' => now_string(),
            ]
        );

        return self::fetchImportUserByEmail($email) ?? throw new RuntimeException('User hasil import gagal ditemukan.');
    }

    private static function fetchImportUserByEmail(string $email): ?array
    {
        return fetch_one(
            'SELECT u.*,
                    COALESCE(u.toleransi_terlambat_menit, un.toleransi_terlambat_menit, 0) AS toleransi_terlambat_menit_efektif
             FROM users u
             JOIN units un ON un.id = u.unit_id
             WHERE u.email = :email
             LIMIT 1',
            ['email' => $email]
        );
    }

    private static function resolveImportUser(int $unitId, string $name, string $jabatan, ?string $kodeAbsensi, array $options): array
    {
        $existingByCode = $kodeAbsensi !== null ? self::fetchImportUserByCode($unitId, $kodeAbsensi) : null;
        if ($existingByCode) {
            $existingName = strtoupper(trim((string) ($existingByCode['name'] ?? '')));
            if ($existingName !== strtoupper(trim($name))) {
                if (empty($options['allow_code_takeover'])) {
                    throw new AttendanceImportConflictException([
                        'code' => $kodeAbsensi,
                        'existing_name' => (string) ($existingByCode['name'] ?? ''),
                        'import_name' => $name,
                        'message' => 'Kode absensi ini telah ada di database untuk nama ' . ($existingByCode['name'] ?? '-') . '. Jika dilanjutkan, user lama akan ditandai resign per hari ini dan kode dipindahkan ke ' . $name . '.',
                    ]);
                }

                execute_query(
                    'UPDATE users
                     SET kode_absensi = NULL,
                         tanggal_resign = :tanggal_resign,
                         updated_at = :updated_at
                     WHERE id = :id',
                    [
                        'tanggal_resign' => date('Y-m-d'),
                        'updated_at' => now_string(),
                        'id' => $existingByCode['id'],
                    ]
                );

                $targetUser = self::fetchImportUserByName($unitId, $name);
                if (!$targetUser) {
                    return self::createImportedUser($unitId, $name, $jabatan, $kodeAbsensi);
                }

                execute_query(
                    'UPDATE users
                     SET kode_absensi = :kode_absensi,
                         jabatan = :jabatan,
                         tanggal_resign = NULL,
                         updated_at = :updated_at
                     WHERE id = :id',
                    [
                        'kode_absensi' => $kodeAbsensi,
                        'jabatan' => $jabatan !== '' ? $jabatan : ($targetUser['jabatan'] ?? ''),
                        'updated_at' => now_string(),
                        'id' => $targetUser['id'],
                    ]
                );

                return self::fetchImportUserByName($unitId, $name) ?? throw new RuntimeException('User tujuan takeover gagal ditemukan.');
            }

            if (($existingByCode['jabatan'] ?? '') !== $jabatan && $jabatan !== '') {
                execute_query(
                    'UPDATE users SET jabatan = :jabatan, updated_at = :updated_at WHERE id = :id',
                    [
                        'jabatan' => $jabatan,
                        'updated_at' => now_string(),
                        'id' => $existingByCode['id'],
                    ]
                );
                return self::fetchImportUserByCode($unitId, $kodeAbsensi) ?? $existingByCode;
            }

            return $existingByCode;
        }

        $existingByName = self::fetchImportUserByName($unitId, $name);
        if ($existingByName) {
            if ($kodeAbsensi !== null && ($existingByName['kode_absensi'] ?? null) !== $kodeAbsensi) {
                execute_query(
                    'UPDATE users
                     SET kode_absensi = :kode_absensi,
                         jabatan = :jabatan,
                         tanggal_resign = NULL,
                         updated_at = :updated_at
                     WHERE id = :id',
                    [
                        'kode_absensi' => $kodeAbsensi,
                        'jabatan' => $jabatan !== '' ? $jabatan : ($existingByName['jabatan'] ?? ''),
                        'updated_at' => now_string(),
                        'id' => $existingByName['id'],
                    ]
                );
                return self::fetchImportUserByName($unitId, $name) ?? $existingByName;
            }

            return $existingByName;
        }

        return self::createImportedUser($unitId, $name, $jabatan, $kodeAbsensi);
    }
}
