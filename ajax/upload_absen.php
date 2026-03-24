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
    json_response([
        'success' => true,
        'message' => $message,
        'reloadSection' => 'absensi',
    ]);
} catch (Throwable $e) {
    restore_error_handler();
    ob_end_clean();
    json_response(['success' => false, 'message' => $e->getMessage()], 422);
}
