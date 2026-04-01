<?php

require __DIR__ . '/../bootstrap/app.php';
Auth::require();
verify_csrf();

ob_start();
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

if (empty($_FILES['file']['tmp_name'])) {
    restore_error_handler();
    ob_end_clean();
    json_response(['success' => false, 'message' => 'File belum dipilih.'], 422);
}

$originalName = (string) ($_FILES['file']['name'] ?? 'absensi');
$temporaryPath = (string) $_FILES['file']['tmp_name'];

try {
    $summary = AttendanceImportService::importUploadedFile($temporaryPath, $originalName, (int) Auth::unitId());
    $message = 'Import selesai. Baru: ' . $summary['imported'] . ', update: ' . ($summary['updated'] ?? 0) . ', skip: ' . $summary['skipped'] . '.';
    if (!empty($summary['errors'])) {
        $message .= ' Contoh error: ' . $summary['errors'][0];
    }
    restore_error_handler();
    ob_end_clean();
    $reloadParams = null;
    if (!empty($summary['date_range']['start']) && !empty($summary['date_range']['end'])) {
        $reloadParams = [
            'start_date' => (string) $summary['date_range']['start'],
            'end_date' => (string) $summary['date_range']['end'],
        ];
    }

    json_response([
        'success' => true,
        'message' => $message,
        'reloadSection' => 'absensi',
        'reloadParams' => $reloadParams,
    ]);
} catch (Throwable $e) {
    restore_error_handler();
    ob_end_clean();
    json_response(['success' => false, 'message' => $e->getMessage()], 422);
}
