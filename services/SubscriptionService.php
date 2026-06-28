<?php

final class SubscriptionService
{
    private const LOCK_DAY = 28;
    private const UPLOAD_DIR = 'uploads/subscriptions';

    public static function ensureSchema(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }

        try {
            self::ensureUnitColumn('subscription_expires_at', 'DATETIME NULL DEFAULT NULL');
            self::ensureUnitColumn('subscription_locked_at', 'DATETIME NULL DEFAULT NULL');
            self::ensureUnitColumn('subscription_notes', 'TEXT NULL DEFAULT NULL');

            execute_query(
                'CREATE TABLE IF NOT EXISTS subscription_requests (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    unit_id BIGINT UNSIGNED NOT NULL,
                    requested_by VARCHAR(255) NULL,
                    contact VARCHAR(255) NULL,
                    duration_code VARCHAR(50) NOT NULL,
                    duration_label VARCHAR(100) NOT NULL,
                    proof_path VARCHAR(255) NOT NULL,
                    status VARCHAR(30) NOT NULL DEFAULT "pending",
                    admin_notes TEXT NULL,
                    approved_by BIGINT UNSIGNED NULL,
                    approved_at DATETIME NULL,
                    rejected_by BIGINT UNSIGNED NULL,
                    rejected_at DATETIME NULL,
                    starts_at DATETIME NULL,
                    expires_at DATETIME NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    PRIMARY KEY (id),
                    KEY subscription_requests_unit_id_index (unit_id),
                    KEY subscription_requests_status_index (status),
                    CONSTRAINT subscription_requests_unit_id_foreign FOREIGN KEY (unit_id) REFERENCES units (id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $exception) {
            // The app should still boot so the existing UI can show a clear database error.
        }

        $ready = true;
    }

    public static function durationOptions(): array
    {
        return [
            '1_month' => '1 Bulan',
            '2_months' => '2 Bulan',
            '3_months' => '3 Bulan',
            '6_months' => '6 Bulan',
            '1_year' => '1 Tahun',
            '2_years' => '2 Tahun',
        ];
    }

    public static function statusForUnit(int $unitId): array
    {
        self::ensureSchema();
        try {
            $unit = fetch_one(
                'SELECT id, nama_unit, subscription_expires_at, subscription_locked_at
                 FROM units
                 WHERE id = :id
                 LIMIT 1',
                ['id' => $unitId]
            );
        } catch (Throwable $exception) {
            $unit = fetch_one('SELECT id, nama_unit FROM units WHERE id = :id LIMIT 1', ['id' => $unitId]);
            return [
                'active' => false,
                'locked' => true,
                'message' => 'Database subscription belum siap. Jalankan docs/subscription-schema.sql.',
                'expires_at' => null,
                'locked_at' => null,
                'unit' => $unit,
            ];
        }

        if (!$unit) {
            return [
                'active' => false,
                'locked' => true,
                'message' => 'Unit tidak ditemukan.',
                'expires_at' => null,
                'locked_at' => null,
                'unit' => null,
            ];
        }

        $now = new DateTimeImmutable('now');
        $expiresAt = self::globalExpiresAt();
        $locked = !$expiresAt || $now >= $expiresAt;

        if ($locked && empty($unit['subscription_locked_at'])) {
            self::markLocked();
            $unit['subscription_locked_at'] = now_string();
        }

        return [
            'active' => !$locked,
            'locked' => $locked,
            'message' => $locked
                ? 'Subscription aplikasi sudah jatuh tempo. Upload bukti pembayaran lalu tunggu validasi.'
                : 'Subscription aktif sampai ' . format_date_id($expiresAt->format('Y-m-d H:i:s'), true) . '.',
            'expires_at' => $expiresAt ? $expiresAt->format('Y-m-d H:i:s') : null,
            'locked_at' => $unit['subscription_locked_at'] ?? null,
            'unit' => $unit,
        ];
    }

    public static function isActiveForUnit(int $unitId): bool
    {
        return self::statusForUnit($unitId)['active'];
    }

    public static function globalStatus(): array
    {
        self::ensureSchema();
        $expiresAt = self::globalExpiresAt();
        $now = new DateTimeImmutable('now');
        $locked = !$expiresAt || $now >= $expiresAt;

        if ($locked) {
            self::markLocked();
        }

        $units = fetch_all(
            'SELECT id, nama_unit, subscription_expires_at, subscription_locked_at, subscription_notes
             FROM units
             ORDER BY nama_unit'
        );

        return [
            'active' => !$locked,
            'locked' => $locked,
            'expires_at' => $expiresAt ? $expiresAt->format('Y-m-d H:i:s') : null,
            'units' => $units,
        ];
    }

    public static function submitRequest(array $post, array $file): array
    {
        self::ensureSchema();

        $unitId = (int) ($post['unit_id'] ?? 0);
        $durationCode = (string) ($post['duration_code'] ?? '');
        $options = self::durationOptions();
        $requestedBy = trim((string) ($post['requested_by'] ?? ''));
        $contact = trim((string) ($post['contact'] ?? ''));

        $unit = $unitId > 0 ? fetch_one('SELECT id FROM units WHERE id = :id LIMIT 1', ['id' => $unitId]) : null;
        if (!$unit) {
            return ['success' => false, 'message' => 'Unit wajib dipilih.'];
        }

        if (!isset($options[$durationCode])) {
            return ['success' => false, 'message' => 'Pilih durasi berlangganan yang valid.'];
        }

        $proofPath = self::storeProofFile($file);
        if ($proofPath === null) {
            return ['success' => false, 'message' => 'Upload bukti pembayaran berupa JPG, PNG, WEBP, atau PDF maksimal 5 MB.'];
        }

        execute_query(
            'INSERT INTO subscription_requests (
                unit_id, requested_by, contact, duration_code, duration_label, proof_path, status, created_at, updated_at
            ) VALUES (
                :unit_id, :requested_by, :contact, :duration_code, :duration_label, :proof_path, "pending", :created_at, :updated_at
            )',
            [
                'unit_id' => $unitId,
                'requested_by' => $requestedBy !== '' ? $requestedBy : null,
                'contact' => $contact !== '' ? $contact : null,
                'duration_code' => $durationCode,
                'duration_label' => $options[$durationCode],
                'proof_path' => $proofPath,
                'created_at' => now_string(),
                'updated_at' => now_string(),
            ]
        );

        ActivityLogService::log(
            'subscription_request',
            'User mengirim bukti pembayaran subscription.',
            ['unit_id' => $unitId, 'duration' => $durationCode],
            null,
            $unitId,
            'subscription_request',
            null
        );

        return ['success' => true, 'message' => 'Bukti pembayaran berhasil dikirim. Silakan tunggu validasi manual.'];
    }

    public static function pendingRequests(?int $unitId = null): array
    {
        self::ensureSchema();
        $where = $unitId ? 'WHERE sr.unit_id = :unit_id' : '';
        $params = $unitId ? ['unit_id' => $unitId] : [];

        return fetch_all(
            'SELECT sr.*, u.nama_unit
             FROM subscription_requests sr
             JOIN units u ON u.id = sr.unit_id
             ' . $where . '
             ORDER BY sr.status = "pending" DESC, sr.created_at DESC
             LIMIT 100',
            $params
        );
    }

    public static function approve(int $requestId, int $adminId, string $notes = ''): array
    {
        self::ensureSchema();
        $request = fetch_one('SELECT * FROM subscription_requests WHERE id = :id LIMIT 1', ['id' => $requestId]);
        if (!$request) {
            return ['success' => false, 'message' => 'Pengajuan tidak ditemukan.'];
        }

        [$startsAt, $expiresAt] = self::calculatePeriod((string) $request['duration_code']);

        execute_query(
            'UPDATE units
             SET subscription_expires_at = :expires_at,
                 subscription_locked_at = NULL,
                 subscription_notes = :notes,
                 updated_at = :updated_at
             WHERE id > 0',
            [
                'expires_at' => $expiresAt,
                'notes' => $notes !== '' ? $notes : null,
                'updated_at' => now_string(),
            ]
        );

        execute_query(
            'UPDATE subscription_requests
             SET status = "approved",
                 admin_notes = :admin_notes,
                 approved_by = :approved_by,
                 approved_at = :approved_at,
                 starts_at = :starts_at,
                 expires_at = :expires_at,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'admin_notes' => $notes !== '' ? $notes : null,
                'approved_by' => $adminId,
                'approved_at' => now_string(),
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'updated_at' => now_string(),
                'id' => $requestId,
            ]
        );

        ActivityLogService::logCurrentUser(
            'subscription_approve',
            'Menyetujui perpanjangan subscription.',
            ['request_id' => $requestId, 'unit_id' => (int) $request['unit_id'], 'expires_at' => $expiresAt],
            'subscription_request',
            $requestId
        );

        return ['success' => true, 'message' => 'Subscription berhasil diperpanjang sampai ' . format_date_id($expiresAt, true) . '.'];
    }

    public static function reject(int $requestId, int $adminId, string $notes = ''): array
    {
        self::ensureSchema();
        $request = fetch_one('SELECT * FROM subscription_requests WHERE id = :id LIMIT 1', ['id' => $requestId]);
        if (!$request) {
            return ['success' => false, 'message' => 'Pengajuan tidak ditemukan.'];
        }

        execute_query(
            'UPDATE subscription_requests
             SET status = "rejected",
                 admin_notes = :admin_notes,
                 rejected_by = :rejected_by,
                 rejected_at = :rejected_at,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'admin_notes' => $notes !== '' ? $notes : null,
                'rejected_by' => $adminId,
                'rejected_at' => now_string(),
                'updated_at' => now_string(),
                'id' => $requestId,
            ]
        );

        ActivityLogService::logCurrentUser(
            'subscription_reject',
            'Menolak pengajuan perpanjangan subscription.',
            ['request_id' => $requestId, 'unit_id' => (int) $request['unit_id']],
            'subscription_request',
            $requestId
        );

        return ['success' => true, 'message' => 'Pengajuan subscription ditolak.'];
    }

    public static function manualExtend(int $unitId, string $durationCode, int $adminId, string $notes = ''): array
    {
        self::ensureSchema();
        $unit = fetch_one('SELECT id FROM units WHERE id = :id LIMIT 1', ['id' => $unitId]);
        if (!$unit) {
            return ['success' => false, 'message' => 'Unit tidak ditemukan.'];
        }

        if (!isset(self::durationOptions()[$durationCode])) {
            return ['success' => false, 'message' => 'Durasi subscription tidak valid.'];
        }

        [$startsAt, $expiresAt] = self::calculatePeriod($durationCode);
        execute_query(
            'UPDATE units
             SET subscription_expires_at = :expires_at,
                 subscription_locked_at = NULL,
                 subscription_notes = :notes,
                 updated_at = :updated_at
             WHERE id > 0',
            [
                'expires_at' => $expiresAt,
                'notes' => $notes !== '' ? $notes : null,
                'updated_at' => now_string(),
            ]
        );

        ActivityLogService::logCurrentUser(
            'subscription_manual_extend',
            'Memperpanjang subscription secara manual.',
            ['unit_id' => $unitId, 'duration' => $durationCode, 'starts_at' => $startsAt, 'expires_at' => $expiresAt],
            'unit',
            $unitId
        );

        return ['success' => true, 'message' => 'Subscription manual aktif sampai ' . format_date_id($expiresAt, true) . '.'];
    }

    public static function setGlobalExpiry(string $expiresAt, string $notes = ''): array
    {
        self::ensureSchema();
        $date = self::dateOrNull($expiresAt);
        if (!$date) {
            return ['success' => false, 'message' => 'Tanggal dan jam subscription tidak valid.'];
        }

        execute_query(
            'UPDATE units
             SET subscription_expires_at = :expires_at,
                 subscription_locked_at = CASE WHEN :expires_at_check > NOW() THEN NULL ELSE COALESCE(subscription_locked_at, NOW()) END,
                 subscription_notes = :notes,
                 updated_at = :updated_at
             WHERE id > 0',
            [
                'expires_at' => $date->format('Y-m-d H:i:s'),
                'expires_at_check' => $date->format('Y-m-d H:i:s'),
                'notes' => $notes !== '' ? $notes : null,
                'updated_at' => now_string(),
            ]
        );

        ActivityLogService::log(
            'subscription_set_expiry',
            'Mengatur tanggal dan jam subscription global.',
            ['expires_at' => $date->format('Y-m-d H:i:s')],
            Auth::id(),
            Auth::unitId(),
            'subscription',
            'global'
        );

        return ['success' => true, 'message' => 'Tanggal subscription berhasil diatur sampai ' . format_date_id($date->format('Y-m-d H:i:s'), true) . '.'];
    }

    public static function disableGlobal(string $notes = ''): array
    {
        self::ensureSchema();
        execute_query(
            'UPDATE units
             SET subscription_expires_at = :expires_at,
                 subscription_locked_at = :locked_at,
                 subscription_notes = :notes,
                 updated_at = :updated_at
             WHERE id > 0',
            [
                'expires_at' => now_string(),
                'locked_at' => now_string(),
                'notes' => $notes !== '' ? $notes : 'Subscription dimatikan manual.',
                'updated_at' => now_string(),
            ]
        );

        ActivityLogService::log(
            'subscription_disable',
            'Mematikan subscription global.',
            [],
            Auth::id(),
            Auth::unitId(),
            'subscription',
            'global'
        );

        return ['success' => true, 'message' => 'Subscription dimatikan. Semua unit akan terkunci.'];
    }

    private static function ensureUnitColumn(string $column, string $definition): void
    {
        $exists = fetch_one(
            'SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = "units"
               AND COLUMN_NAME = :column
             LIMIT 1',
            ['column' => $column]
        );

        if ($exists) {
            return;
        }

        execute_query('ALTER TABLE units ADD COLUMN ' . $column . ' ' . $definition);
    }

    private static function storeProofFile(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > 5 * 1024 * 1024) {
            return null;
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $originalName = (string) ($file['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        if (!in_array($extension, $allowedExtensions, true) || !is_uploaded_file($tmpName)) {
            return null;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmpName);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        if (!in_array($mime, $allowedMimes, true)) {
            return null;
        }

        $dir = __DIR__ . '/../public/' . self::UPLOAD_DIR;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $filename = 'subscription-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
        $target = $dir . '/' . $filename;
        if (!move_uploaded_file($tmpName, $target)) {
            return null;
        }

        return self::UPLOAD_DIR . '/' . $filename;
    }

    private static function calculatePeriod(string $durationCode): array
    {
        $months = match ($durationCode) {
            '1_month' => 1,
            '2_months' => 2,
            '3_months' => 3,
            '6_months' => 6,
            '1_year' => 12,
            '2_years' => 24,
            default => 1,
        };

        $now = new DateTimeImmutable('now');
        $currentExpiry = self::globalExpiresAt();
        $base = ($currentExpiry && $currentExpiry > $now) ? $currentExpiry : $now;
        $startsAt = $base > $now ? $base : $now;
        $targetMonth = $base->modify('first day of this month')->modify('+' . $months . ' month');
        $expiresAt = $targetMonth->setDate(
            (int) $targetMonth->format('Y'),
            (int) $targetMonth->format('m'),
            self::LOCK_DAY
        )->setTime(0, 0, 0);

        return [$startsAt->format('Y-m-d H:i:s'), $expiresAt->format('Y-m-d H:i:s')];
    }

    private static function markLocked(): void
    {
        try {
            execute_query(
                'UPDATE units
                 SET subscription_locked_at = :locked_at,
                     updated_at = :updated_at
                 WHERE subscription_locked_at IS NULL',
                ['locked_at' => now_string(), 'updated_at' => now_string()]
            );
        } catch (Throwable $exception) {
            // Lock marker is best effort; status calculation still protects access.
        }
    }

    private static function globalExpiresAt(): ?DateTimeImmutable
    {
        try {
            $row = fetch_one('SELECT MAX(subscription_expires_at) AS expires_at FROM units');
        } catch (Throwable $exception) {
            return null;
        }

        return self::dateOrNull($row['expires_at'] ?? null);
    }

    private static function dateOrNull($value): ?DateTimeImmutable
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable $exception) {
            return null;
        }
    }
}
