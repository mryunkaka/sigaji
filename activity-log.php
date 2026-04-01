<?php

require __DIR__ . '/bootstrap/app.php';

$accessCode = trim((string) env('ACTIVITY_LOG_ACCESS_CODE', ''));
$hasAccess = !empty($_SESSION['activity_log_access']);
$error = '';

if ($accessCode === '') {
    $error = 'Kode akses activity log belum diatur di file .env.';
} elseif (is_post() && request_value('action') === 'unlock_activity_log') {
    verify_csrf();
    $submittedCode = trim((string) request_value('access_code', ''));
    if ($submittedCode !== '' && hash_equals($accessCode, $submittedCode)) {
        $_SESSION['activity_log_access'] = true;
        redirect_to('activity-log.php');
    }

    $error = 'Kode akses tidak valid.';
} elseif ((string) request_value('logout_access', '') === '1') {
    unset($_SESSION['activity_log_access']);
    redirect_to('activity-log.php');
}

$hasAccess = $accessCode !== '' && !empty($_SESSION['activity_log_access']);

$defaultEnd = new DateTimeImmutable('today');
$defaultStart = $defaultEnd->modify('-28 days');
$endDate = (string) request_value('end_date', $defaultEnd->format('Y-m-d'));
$startDate = (string) request_value('start_date', $defaultStart->format('Y-m-d'));

if ($hasAccess) {
    $end = DateTimeImmutable::createFromFormat('Y-m-d', $endDate) ?: $defaultEnd;
    $start = $end->modify('-28 days');

    $startDate = $start->format('Y-m-d');
    $endDate = $end->format('Y-m-d');

    $logs = fetch_all(
        'SELECT al.*, u.name AS user_name, un.nama_unit
         FROM activity_logs al
         LEFT JOIN users u ON u.id = al.user_id
         LEFT JOIN units un ON un.id = al.unit_id
         WHERE DATE(al.occurred_at) BETWEEN :start_date AND :end_date
         ORDER BY al.occurred_at DESC, al.id DESC',
        [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]
    );

    $summary = fetch_one(
        'SELECT
            COUNT(*) AS total_logs,
            COUNT(DISTINCT user_id) AS total_users,
            COUNT(CASE WHEN action = "heartbeat" THEN 1 END) AS active_minutes
         FROM activity_logs
         WHERE DATE(occurred_at) BETWEEN :start_date AND :end_date',
        [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]
    ) ?: [];

    $rows = '';
    foreach ($logs as $log) {
        $context = [];
        if (!empty($log['context_json'])) {
            $decoded = json_decode((string) $log['context_json'], true);
            if (is_array($decoded)) {
                $context = $decoded;
            }
        }

        $contextText = '';
        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            if ($key === 'minute_slot' && is_string($value)) {
                $value = str_replace('T', ' ', $value);
            }
            $contextText .= '<div><span class="font-medium text-slate-700">' . e((string) $key) . ':</span> ' . e((string) $value) . '</div>';
        }

        $rows .= '<tr>'
            . '<td class="px-4 py-3 align-top">' . e(format_date_id((string) $log['occurred_at'], true)) . '</td>'
            . '<td class="px-4 py-3 align-top">' . e((string) ($log['user_name'] ?: 'System/Anonim')) . '</td>'
            . '<td class="px-4 py-3 align-top">' . e((string) ($log['nama_unit'] ?: '-')) . '</td>'
            . '<td class="px-4 py-3 align-top">' . e((string) $log['action']) . '</td>'
            . '<td class="px-4 py-3 align-top">' . e((string) $log['description']) . ($contextText !== '' ? '<div class="mt-2 space-y-1 text-xs text-slate-500">' . $contextText . '</div>' : '') . '</td>'
            . '<td class="px-4 py-3 align-top">' . e((string) ($log['ip_address'] ?: '-')) . '</td>'
            . '</tr>';
    }

    $filterForm = '<form method="get" class="flex flex-col gap-4 lg:flex-row lg:flex-nowrap lg:items-end">'
        . '<div class="min-w-0 lg:w-[280px] xl:w-[320px]">' . ui_input('start_date', 'Tanggal Awal', $startDate, 'date', ['required' => 'required', 'readonly' => 'readonly']) . '</div>'
        . '<div class="min-w-0 lg:w-[280px] xl:w-[320px]">' . ui_input('end_date', 'Tanggal Akhir', $endDate, 'date', ['required' => 'required']) . '</div>'
        . '<div class="flex flex-nowrap items-end gap-3 lg:shrink-0">'
        . ui_button('Terapkan Filter', ['type' => 'submit', 'variant' => 'secondary'])
        . '<a href="activity-log.php?logout_access=1" class="inline-flex items-center justify-center rounded-2xl px-4 py-2.5 text-sm font-semibold text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-50">Keluar</a>'
        . '</div>'
        . '</form>';

    $tableHtml = '<div class="overflow-x-auto rounded-[30px] border border-slate-200/80 bg-white/95 shadow-[0_18px_48px_rgba(15,23,42,.06)]">'
        . '<div class="flex items-center justify-between gap-4 border-b border-slate-200 bg-slate-50/70 px-4 py-4">'
        . '<p class="text-sm font-medium text-slate-600">Log aktivitas pada rentang ' . e(format_date_id($startDate)) . ' - ' . e(format_date_id($endDate)) . '</p>'
        . '<p class="text-sm text-slate-500">Total ' . e((string) count($logs)) . ' data</p>'
        . '</div>'
        . '<div class="px-4 py-4">'
        . '<table id="activity-log-table" class="min-w-full divide-y divide-slate-200 display">'
        . '<thead class="bg-slate-50/85"><tr>'
        . '<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Waktu</th>'
        . '<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">User</th>'
        . '<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Unit</th>'
        . '<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Aksi</th>'
        . '<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Detail</th>'
        . '<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">IP</th>'
        . '</tr></thead>'
        . '<tbody class="divide-y divide-slate-100 text-sm text-slate-700">' . ($rows !== '' ? $rows : '<tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">Belum ada aktivitas pada rentang ini.</td></tr>') . '</tbody>'
        . '</table>'
        . '</div>'
        . '</div>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title>Activity Log</title>
    <link rel="stylesheet" href="<?= e(asset_url('assets/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('assets/activity-log.css')) ?>">
    <style>
        html, body {
            height: auto;
            overflow: auto;
        }
    </style>
</head>
<body class="min-h-screen overflow-y-auto bg-slate-100" style="overflow-y:auto;">
    <main class="mx-auto flex min-h-screen w-full max-w-7xl items-center justify-center px-4 py-6 sm:px-6 lg:px-8" style="min-height:100vh;">
        <?php if (!$hasAccess): ?>
            <div class="flex w-full items-center justify-center">
                <section class="w-full max-w-xl rounded-[32px] border border-slate-200/80 bg-white/95 p-6 shadow-[0_20px_60px_rgba(15,23,42,.06)]">
                    <div class="mb-6">
                        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Activity Log</p>
                        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Akses Dengan Kode</h1>
                        <p class="mt-2 text-sm text-slate-500">Halaman ini tidak memakai login user. Masukkan kode akses dari file `.env`.</p>
                    </div>
                    <?php if ($error !== ''): ?>
                        <div class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div>
                    <?php endif; ?>
                    <form method="post" class="space-y-4">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="unlock_activity_log">
                        <?= ui_input('access_code', 'Kode Akses', '', 'password', ['required' => 'required', 'autocomplete' => 'off']) ?>
                        <?= ui_button('Buka Activity Log', ['type' => 'submit', 'variant' => 'warning', 'class' => 'w-full']) ?>
                    </form>
                </section>
            </div>
        <?php else: ?>
            <section class="w-full rounded-[32px] border border-slate-200/80 bg-white/95 p-6 shadow-[0_20px_60px_rgba(15,23,42,.06)] self-start">
                <div class="flex flex-col gap-4 border-b border-slate-200 pb-6 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Activity Log</p>
                        <h1 class="mt-2 text-3xl font-semibold text-slate-900">Riwayat Aktivitas User</h1>
                        <p class="mt-2 text-sm text-slate-500">Menampilkan detail aksi user, klik tampilan, simpan, hapus, login, upload, generate, dan menit aktif.</p>
                    </div>
                </div>

                <div class="mt-6"><?= $filterForm ?></div>

                <div class="mt-6 grid gap-4 md:grid-cols-3">
                    <?= ui_stat('Total Aktivitas', (string) ($summary['total_logs'] ?? 0), 'Seluruh log pada rentang tanggal terpilih', 'sky') ?>
                    <?= ui_stat('User Aktif', (string) ($summary['total_users'] ?? 0), 'Jumlah user unik yang tercatat', 'emerald') ?>
                    <?= ui_stat('Menit Aktif', (string) ($summary['active_minutes'] ?? 0), 'Perkiraan menit aktif dari heartbeat', 'amber') ?>
                </div>

                <div class="mt-6">
                    <?= $tableHtml ?>
                </div>
            </section>
        <?php endif; ?>
    </main>
</body>
<script src="<?= e(asset_url('assets/activity-log.js')) ?>" defer></script>
</html>
