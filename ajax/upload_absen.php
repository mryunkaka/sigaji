<?php

require __DIR__ . '/../bootstrap/app.php';
Auth::require();
verify_csrf();

ob_start();
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$importToken = trim((string) request_value('import_token', ''));
$allowCodeTakeover = request_value('allow_code_takeover', '0') === '1';
$temporaryPath = '';
$originalName = 'absensi';

if ($importToken !== '') {
    $metaPath = ensure_storage_path('imports/' . $importToken . '.json');
    if (!is_file($metaPath)) {
        restore_error_handler();
        ob_end_clean();
        json_response(['success' => false, 'message' => 'File import sementara tidak ditemukan. Upload ulang file absensi.'], 422);
    }

    $meta = json_decode((string) file_get_contents($metaPath), true);
    $temporaryPath = (string) ($meta['path'] ?? '');
    $originalName = (string) ($meta['original_name'] ?? 'absensi');

    if ($temporaryPath === '' || !is_file($temporaryPath)) {
        @unlink($metaPath);
        restore_error_handler();
        ob_end_clean();
        json_response(['success' => false, 'message' => 'File import sementara sudah tidak tersedia. Upload ulang file absensi.'], 422);
    }
} else {
    if (empty($_FILES['file']['tmp_name'])) {
        restore_error_handler();
        ob_end_clean();
        json_response(['success' => false, 'message' => 'File belum dipilih.'], 422);
    }

    $originalName = (string) ($_FILES['file']['name'] ?? 'absensi');
    $temporaryPath = (string) $_FILES['file']['tmp_name'];
}

try {
    $summary = AttendanceImportService::importUploadedFile($temporaryPath, $originalName, (int) Auth::unitId(), [
        'allow_code_takeover' => $allowCodeTakeover,
    ]);
    $message = 'Import selesai. Baru: ' . $summary['imported'] . ', update: ' . ($summary['updated'] ?? 0) . ', skip: ' . $summary['skipped'] . '.';
    if (!empty($summary['errors'])) {
        $message .= ' Contoh error: ' . $summary['errors'][0];
    }
    restore_error_handler();
    ob_end_clean();
    $reloadParams = null;
    if (!empty($summary['date_range']['start']) && !empty($summary['date_range']['end'])) {
        $periodEnd = new DateTimeImmutable((string) $summary['date_range']['end']);
        $reloadParams = [
            'month' => $periodEnd->format('n'),
            'year' => $periodEnd->format('Y'),
        ];
    }

    if ($importToken !== '') {
        $metaPath = ensure_storage_path('imports/' . $importToken . '.json');
        @unlink($temporaryPath);
        @unlink($metaPath);
    }

    json_response([
        'success' => true,
        'message' => $message,
        'reloadSection' => 'absensi',
        'reloadParams' => $reloadParams,
    ]);
} catch (AttendanceImportConflictException $e) {
    $token = $importToken;
    if ($token === '') {
        $token = bin2hex(random_bytes(16));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) ?: 'tmp';
        $storedPath = ensure_storage_path('imports/' . $token . '.' . $extension);
        if (!@move_uploaded_file($temporaryPath, $storedPath)) {
            if (!@copy($temporaryPath, $storedPath)) {
                restore_error_handler();
                ob_end_clean();
                json_response(['success' => false, 'message' => 'File import konflik tidak bisa disimpan sementara.'], 422);
            }
        }

        file_put_contents(
            ensure_storage_path('imports/' . $token . '.json'),
            json_encode([
                'path' => $storedPath,
                'original_name' => $originalName,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    restore_error_handler();
    ob_end_clean();
    json_response([
        'success' => false,
        'message' => $e->getMessage(),
        'confirmImportConflict' => [
            'token' => $token,
            'message' => $e->getMessage(),
        ],
    ], 409);
} catch (Throwable $e) {
    if ($importToken !== '') {
        $metaPath = ensure_storage_path('imports/' . $importToken . '.json');
        @unlink($temporaryPath);
        @unlink($metaPath);
    }
    restore_error_handler();
    ob_end_clean();
    json_response(['success' => false, 'message' => $e->getMessage()], 422);
}
