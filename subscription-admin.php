<?php

require __DIR__ . '/bootstrap/app.php';

$pin = (string) env('SUBSCRIPTION_ADMIN_PIN', '814128');
$sessionKey = 'subscription_admin_unlocked';
$message = '';
$error = '';

if (is_post()) {
    verify_csrf();
    $action = (string) request_value('action', '');

    if ($action === 'pin_login') {
        if (hash_equals($pin, (string) request_value('pin', ''))) {
            $_SESSION[$sessionKey] = true;
            $message = 'Akses subscription admin terbuka.';
        } else {
            $error = 'PIN tidak sesuai.';
        }
    } elseif ($action === 'pin_logout') {
        unset($_SESSION[$sessionKey]);
        redirect_to('subscription-admin.php');
    } elseif (!empty($_SESSION[$sessionKey])) {
        $notes = trim((string) request_value('admin_notes', ''));
        if ($action === 'set_expiry') {
            $expiresAt = trim((string) request_value('expires_at', ''));
            $result = SubscriptionService::setGlobalExpiry($expiresAt, $notes);
        } elseif ($action === 'disable_subscription') {
            $result = SubscriptionService::disableGlobal($notes);
        } elseif ($action === 'approve_request') {
            $result = SubscriptionService::approve((int) request_value('request_id'), (int) (Auth::id() ?? 0), $notes);
        } elseif ($action === 'reject_request') {
            $result = SubscriptionService::reject((int) request_value('request_id'), (int) (Auth::id() ?? 0), $notes);
        } else {
            $result = ['success' => false, 'message' => 'Aksi tidak valid.'];
        }

        if (!empty($result['success'])) {
            $message = $result['message'];
        } else {
            $error = $result['message'] ?? 'Aksi gagal diproses.';
        }
    }
}

$unlocked = !empty($_SESSION[$sessionKey]);
$status = $unlocked ? SubscriptionService::globalStatus() : null;
$requests = $unlocked ? SubscriptionService::pendingRequests() : [];
$expiresValue = '';
if ($status && $status['expires_at']) {
    $expiresValue = date('Y-m-d\TH:i', strtotime($status['expires_at']));
}
if ($expiresValue === '') {
    $expiresValue = date('Y-m-d\TH:i', strtotime('+1 month'));
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title>Subscription Admin - <?= e(env('APP_NAME', 'SIGAJI Native')) ?></title>
    <link rel="stylesheet" href="<?= e(asset_url('assets/app.css')) ?>">
</head>

<body class="min-h-screen overflow-auto">
    <main class="min-h-screen overflow-y-auto px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-6xl space-y-5">
            <section class="rounded-[32px] border border-slate-200/80 bg-white/95 p-6 shadow-[0_20px_60px_rgba(15,23,42,.06)]">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Subscription Admin</p>
                        <h1 class="mt-2 text-3xl font-semibold text-slate-900">Kontrol Subscription</h1>
                        <p class="mt-2 text-sm text-slate-500">Halaman PIN untuk cek status, validasi pembayaran, mengatur tanggal/jam, dan mematikan akses subscription global.</p>
                    </div>
                    <?php if ($unlocked): ?>
                        <form method="post">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="pin_logout">
                            <?= ui_button('Kunci Halaman', ['type' => 'submit', 'variant' => 'secondary']) ?>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if ($message !== ''): ?>
                    <div class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($message) ?></div>
                <?php endif; ?>
                <?php if ($error !== ''): ?>
                    <div class="mt-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div>
                <?php endif; ?>
            </section>

            <?php if (!$unlocked): ?>
                <section class="mx-auto max-w-md rounded-[32px] border border-slate-200/80 bg-white/95 p-6 shadow-[0_20px_60px_rgba(15,23,42,.06)]">
                    <form method="post" class="space-y-4">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="pin_login">
                        <?= ui_input('pin', 'PIN Akses*', '', 'password', ['required' => 'required', 'inputmode' => 'numeric', 'autocomplete' => 'current-password']) ?>
                        <?= ui_button('Buka Halaman Subscription', ['type' => 'submit', 'variant' => 'primary', 'class' => 'w-full']) ?>
                    </form>
                </section>
            <?php else: ?>
                <section class="grid gap-5 lg:grid-cols-[1fr_1fr]">
                    <div class="rounded-[32px] border border-slate-200/80 bg-white/95 p-6 shadow-[0_20px_60px_rgba(15,23,42,.06)]">
                        <h2 class="text-lg font-semibold text-slate-900">Status Global</h2>
                        <div class="mt-4 rounded-[24px] border <?= $status['active'] ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' ?> px-4 py-4">
                            <p class="text-sm font-semibold"><?= $status['active'] ? 'Aktif' : 'Terkunci' ?></p>
                            <p class="mt-2 text-sm"><?= e($status['expires_at'] ? ('Aktif sampai ' . format_date_id($status['expires_at'], true)) : 'Belum ada tanggal aktif.') ?></p>
                        </div>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <?php foreach ($status['units'] as $unit): ?>
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                    <p class="text-sm font-semibold text-slate-900"><?= e($unit['nama_unit']) ?></p>
                                    <p class="mt-1 text-xs text-slate-500"><?= e($unit['subscription_expires_at'] ? format_date_id($unit['subscription_expires_at'], true) : 'Belum aktif') ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="space-y-5">
                        <section class="rounded-[32px] border border-slate-200/80 bg-white/95 p-6 shadow-[0_20px_60px_rgba(15,23,42,.06)]">
                            <h2 class="text-lg font-semibold text-slate-900">Atur Tanggal dan Jam</h2>
                            <form method="post" class="mt-4 grid gap-4">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="set_expiry">
                                <?= ui_input('expires_at', 'Aktif Sampai*', $expiresValue, 'datetime-local', ['required' => 'required']) ?>
                                <?= ui_input('admin_notes', 'Catatan', '', 'text', ['placeholder' => 'Opsional']) ?>
                                <?= ui_button('Simpan Tanggal Subscription', ['type' => 'submit', 'variant' => 'success', 'class' => 'w-full']) ?>
                            </form>
                        </section>

                        <section class="rounded-[32px] border border-rose-200 bg-white/95 p-6 shadow-[0_20px_60px_rgba(15,23,42,.06)]">
                            <h2 class="text-lg font-semibold text-slate-900">Matikan Subscription</h2>
                            <p class="mt-2 text-sm text-slate-500">Aksi ini mengunci semua unit sampai subscription diaktifkan lagi.</p>
                            <form method="post" class="mt-4 grid gap-4" onsubmit="return confirm('Matikan subscription dan kunci semua unit?')">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="disable_subscription">
                                <?= ui_input('admin_notes', 'Alasan / Catatan', '', 'text', ['placeholder' => 'Opsional']) ?>
                                <?= ui_button('Matikan Subscription', ['type' => 'submit', 'variant' => 'danger', 'class' => 'w-full']) ?>
                            </form>
                        </section>
                    </div>
                </section>

                <section class="rounded-[32px] border border-slate-200/80 bg-white/95 p-6 shadow-[0_20px_60px_rgba(15,23,42,.06)]">
                    <h2 class="text-lg font-semibold text-slate-900">Validasi Pembayaran</h2>
                    <div class="mt-5 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Unit</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Pengirim</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Durasi</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Bukti</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                <?php if ($requests === []): ?>
                                    <tr>
                                        <td colspan="6" class="px-4 py-8 text-center text-slate-500">Belum ada pengajuan subscription.</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td class="px-4 py-3"><?= e($request['nama_unit']) ?></td>
                                        <td class="px-4 py-3">
                                            <?= e($request['requested_by'] ?: '-') ?>
                                            <div class="text-xs text-slate-400"><?= e($request['contact'] ?: '-') ?></div>
                                        </td>
                                        <td class="px-4 py-3"><?= e($request['duration_label']) ?></td>
                                        <td class="px-4 py-3"><a class="font-semibold text-sky-600 hover:text-sky-700" href="<?= e($request['proof_path']) ?>" target="_blank" rel="noopener">Lihat Bukti</a></td>
                                        <td class="px-4 py-3">
                                            <?= ui_badge(ucfirst((string) $request['status']), $request['status'] === 'approved' ? 'emerald' : ($request['status'] === 'rejected' ? 'rose' : 'amber')) ?>
                                            <div class="mt-1 text-xs text-slate-400"><?= e(format_date_id($request['created_at'], true)) ?></div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <?php if ($request['status'] === 'pending'): ?>
                                                <div class="grid min-w-[220px] gap-2">
                                                    <form method="post" class="grid gap-2">
                                                        <?= csrf_input() ?>
                                                        <input type="hidden" name="action" value="approve_request">
                                                        <input type="hidden" name="request_id" value="<?= e((string) $request['id']) ?>">
                                                        <input type="text" name="admin_notes" placeholder="Catatan approve" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100">
                                                        <?= ui_button('Setujui', ['type' => 'submit', 'variant' => 'success', 'class' => 'w-full']) ?>
                                                    </form>
                                                    <form method="post" class="grid gap-2">
                                                        <?= csrf_input() ?>
                                                        <input type="hidden" name="action" value="reject_request">
                                                        <input type="hidden" name="request_id" value="<?= e((string) $request['id']) ?>">
                                                        <input type="text" name="admin_notes" placeholder="Catatan tolak" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-rose-400 focus:ring-4 focus:ring-rose-100">
                                                        <?= ui_button('Tolak', ['type' => 'submit', 'variant' => 'danger', 'class' => 'w-full']) ?>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-xs text-slate-400">Selesai</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </main>
</body>

</html>
