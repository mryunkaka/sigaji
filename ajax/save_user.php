<?php

require __DIR__ . '/../bootstrap/app.php';
$authUser = Auth::require();
verify_csrf();

$id = (int) request_value('id', 0);
$unitId = (int) request_value('unit_id', $authUser['unit_id']);
$email = trim((string) request_value('email'));

if ($email === '' || trim((string) request_value('name')) === '') {
    json_response(['success' => false, 'message' => 'Nama dan email wajib diisi.'], 422);
}

$existing = fetch_one(
    'SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1',
    ['email' => $email, 'id' => $id]
);

if ($existing) {
    json_response(['success' => false, 'message' => 'Email sudah dipakai user lain.'], 422);
}

$fotoPath = null;
if (!empty($_FILES['foto']['tmp_name']) && is_uploaded_file($_FILES['foto']['tmp_name'])) {
    $ext = strtolower(pathinfo((string) $_FILES['foto']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed, true)) {
        json_response(['success' => false, 'message' => 'Format foto harus jpg, jpeg, png, atau webp.'], 422);
    }
    $dir = __DIR__ . '/../public/uploads/users';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $filename = 'user-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target = $dir . '/' . $filename;
    move_uploaded_file($_FILES['foto']['tmp_name'], $target);
    $fotoPath = 'uploads/users/' . $filename;
}

$payload = [
    'name' => trim((string) request_value('name')),
    'email' => $email,
    'no_hp' => trim((string) request_value('no_hp')),
    'alamat' => trim((string) request_value('alamat')),
    'tempat_lahir' => trim((string) request_value('tempat_lahir')),
    'tanggal_lahir' => request_value('tanggal_lahir') ?: null,
    'jenis_kelamin' => request_value('jenis_kelamin') ?: null,
    'agama' => request_value('agama') ?: null,
    'status_perkawinan' => request_value('status_perkawinan') ?: null,
    'nik' => trim((string) request_value('nik')),
    'npwp' => trim((string) request_value('npwp')),
    'jabatan' => trim((string) request_value('jabatan')),
    'toleransi_terlambat_menit' => request_value('toleransi_terlambat_menit', '') === '' ? null : max(0, (int) request_value('toleransi_terlambat_menit')),
    'role' => request_value('role', 'karyawan'),
    'unit_id' => $unitId,
    'tanggal_bergabung' => request_value('tanggal_bergabung') ?: null,
    'updated_at' => now_string(),
];

if ($fotoPath !== null) {
    $payload['foto'] = $fotoPath;
}

$password = (string) request_value('password', '');

if ($id > 0) {
    $record = fetch_one('SELECT * FROM users WHERE id = :id AND unit_id = :unit_id LIMIT 1', ['id' => $id, 'unit_id' => $authUser['unit_id']]);
    if (!$record) {
        json_response(['success' => false, 'message' => 'User tidak ditemukan pada unit aktif.'], 404);
    }

    if ($password !== '') {
        $payload['password'] = password_hash($password, PASSWORD_DEFAULT);
    }

    $sets = [];
    foreach ($payload as $key => $value) {
        $sets[] = $key . ' = :' . $key;
    }
    $payload['id'] = $id;
    execute_query('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id', $payload);

    json_response([
        'success' => true,
        'message' => 'User berhasil diperbarui.',
        'reloadSection' => 'users',
        'closeModal' => request_value('modal_id'),
    ]);
}

if ($password === '') {
    json_response(['success' => false, 'message' => 'Password wajib diisi saat membuat user baru.'], 422);
}

$payload['password'] = password_hash($password, PASSWORD_DEFAULT);
$payload['created_at'] = now_string();

$columns = implode(', ', array_keys($payload));
$placeholders = ':' . implode(', :', array_keys($payload));
execute_query('INSERT INTO users (' . $columns . ') VALUES (' . $placeholders . ')', $payload);
$newId = (int) db()->lastInsertId();
PayrollService::ensureMasterGaji($newId);

json_response([
    'success' => true,
    'message' => 'User berhasil ditambahkan.',
    'reloadSection' => 'users',
    'closeModal' => request_value('modal_id'),
]);
