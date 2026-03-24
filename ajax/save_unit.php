<?php

require __DIR__ . '/../bootstrap/app.php';
Auth::require();
verify_csrf();

$id = (int) request_value('id', 0);
if (trim((string) request_value('nama_unit')) === '') {
    json_response(['success' => false, 'message' => 'Nama unit wajib diisi.'], 422);
}

$logoPath = null;
if (!empty($_FILES['logo_unit']['tmp_name']) && is_uploaded_file($_FILES['logo_unit']['tmp_name'])) {
    $ext = strtolower(pathinfo((string) $_FILES['logo_unit']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed, true)) {
        json_response(['success' => false, 'message' => 'Format logo harus jpg, jpeg, png, atau webp.'], 422);
    }
    $dir = __DIR__ . '/../public/uploads/units';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $filename = 'unit-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target = $dir . '/' . $filename;
    move_uploaded_file($_FILES['logo_unit']['tmp_name'], $target);
    $logoPath = 'uploads/units/' . $filename;
}

$payload = [
    'nama_unit' => trim((string) request_value('nama_unit')),
    'alamat_unit' => trim((string) request_value('alamat_unit')),
    'no_hp_unit' => trim((string) request_value('no_hp_unit')),
    'updated_at' => now_string(),
];

if ($logoPath !== null) {
    $payload['logo_unit'] = $logoPath;
}

if ($id > 0) {
    $payload['id'] = $id;
    $sets = [];
    foreach ($payload as $key => $value) {
        if ($key !== 'id') {
            $sets[] = $key . ' = :' . $key;
        }
    }
    execute_query('UPDATE units SET ' . implode(', ', $sets) . ' WHERE id = :id', $payload);

    json_response([
        'success' => true,
        'message' => 'Unit berhasil diperbarui.',
        'reloadSection' => 'units',
        'closeModal' => request_value('modal_id'),
    ]);
}

$payload['created_at'] = now_string();
$columns = implode(', ', array_keys($payload));
$placeholders = ':' . implode(', :', array_keys($payload));
execute_query('INSERT INTO units (' . $columns . ') VALUES (' . $placeholders . ')', $payload);

json_response([
    'success' => true,
    'message' => 'Unit berhasil ditambahkan.',
    'reloadSection' => 'units',
    'closeModal' => request_value('modal_id'),
]);
