<?php

require __DIR__ . '/../bootstrap/app.php';
$authUser = Auth::require();
verify_csrf();

// Single-door policy: semua aksi approve/reject/perpanjangan/matikan subscription
// HANYA boleh lewat subscription-admin.php (dengan PIN + rate-limit + brute-force
// protection tersendiri). Endpoint ini sengaja dinonaktifkan agar tidak ada pintu
// kedua yang bisa dibruteforce lewat sesi login user biasa/owner.
ActivityLogService::logCurrentUser(
    'subscription_action_blocked',
    'Percobaan aksi subscription lewat pintu aplikasi diblok. Hanya subscription-admin.php yang diizinkan.',
    ['subscription_action' => (string) request_value('subscription_action', '')],
    'subscription',
    null
);

json_response([
    'success' => false,
    'message' => 'Aksi subscription tidak bisa dilakukan dari sini. Hubungi admin untuk validasi lewat subscription-admin.php.',
], 403);
