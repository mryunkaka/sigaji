<?php

require __DIR__ . '/../bootstrap/app.php';
Auth::require();
verify_csrf();

if (empty($_FILES['file']['tmp_name'])) {
    json_response(['success' => false, 'message' => 'File belum dipilih.'], 422);
}

$originalName = $_FILES['file']['name'] ?? 'absensi';
$target = ensure_storage_path('imports/' . date('Ymd_His') . '_' . basename($originalName));

if (!move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
    json_response(['success' => false, 'message' => 'Gagal menyimpan file upload.'], 500);
}

try {
    $summary = AttendanceImportService::import($target, (int) Auth::unitId());
    $message = 'Import selesai. Berhasil: ' . $summary['imported'] . ', dilewati: ' . $summary['skipped'] . '.';
    if (!empty($summary['errors'])) {
        $message .= ' Contoh error: ' . $summary['errors'][0];
    }
    json_response([
        'success' => true,
        'message' => $message,
        'reloadSection' => 'absensi',
    ]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 422);
}
