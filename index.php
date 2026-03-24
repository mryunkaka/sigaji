<?php

require __DIR__ . '/bootstrap/app.php';

$error = '';

if (is_post() && request_value('action') === 'login') {
    verify_csrf();
    $login = trim((string) request_value('login'));
    $password = (string) request_value('password');
    $unitId = (int) request_value('unit_id');

    if ($login === '' || $password === '' || $unitId <= 0) {
        $error = 'Login, password, dan unit wajib diisi.';
    } elseif (Auth::attempt($login, $password, $unitId)) {
        redirect_to('index.php');
    } else {
        $error = 'Kredensial tidak valid atau unit tidak sesuai.';
    }
}

$units = fetch_all('SELECT id, nama_unit FROM units ORDER BY nama_unit');
$user = Auth::user();
$unitName = $user ? (fetch_one('SELECT nama_unit FROM units WHERE id = :id', ['id' => $user['unit_id']])['nama_unit'] ?? '-') : '-';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e(env('APP_NAME', 'SIGAJI Native')) ?></title>
    <link rel="stylesheet" href="<?= e(asset_url('assets/app.css')) ?>">
</head>
<body>
<?php if (!$user): ?>
    <main class="login-shell">
        <div class="login-card">
            <section class="login-hero">
                <div class="mx-auto flex h-full w-full max-w-xl flex-col">
                    <p class="inline-flex w-max rounded-full bg-white/10 px-4 py-2 text-xs font-semibold tracking-[0.3em] text-slate-100">SIGAJI NATIVE</p>
                    <h1 class="mt-8 max-w-2xl text-4xl font-semibold leading-tight xl:text-6xl">Mirror penggajian lama ke PHP Native dengan tampilan yang lebih tegas dan bersih.</h1>
                    <p class="mt-6 max-w-xl text-base leading-8 text-slate-300 xl:text-lg">Single page, sidebar tetap, upload absensi, validasi payroll, generate gaji, dan slip print tanpa Laravel atau Filament.</p>
                    <div class="mt-auto grid gap-4 pt-10 xl:grid-cols-2">
                        <div class="rounded-[30px] border border-white/10 bg-white/10 p-6">
                            <div class="mb-3 text-emerald-300"><?= ui_icon('calendar', 'h-6 w-6') ?></div>
                            <h2 class="text-xl font-semibold">Absensi</h2>
                            <p class="mt-2 text-sm text-slate-300">Upload file lalu sistem hitung hadir, izin, sakit, cuti, alfa, dan keterlambatan.</p>
                        </div>
                        <div class="rounded-[30px] border border-white/10 bg-white/10 p-6">
                            <div class="mb-3 text-sky-300"><?= ui_icon('banknotes', 'h-6 w-6') ?></div>
                            <h2 class="text-xl font-semibold">Payroll</h2>
                            <p class="mt-2 text-sm text-slate-300">Generate penggajian per periode sesuai rumus existing, lalu validasi manual seperlunya.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="flex h-full items-center justify-center bg-white/90 px-5 py-6 sm:px-8 lg:px-10">
                <div class="w-full max-w-xl rounded-[32px] border border-slate-200/70 bg-white/80 p-6 shadow-[0_22px_65px_rgba(15,23,42,.08)] backdrop-blur-sm sm:p-8 lg:p-10">
                    <div class="mb-8 flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold tracking-[0.3em] text-slate-500">LOGIN PANEL</p>
                            <h2 class="mt-4 text-3xl font-semibold text-slate-900">Masuk ke sistem</h2>
                            <p class="mt-2 text-sm leading-7 text-slate-500">Gunakan akun dari tabel `users` dan pilih unit aktif seperti flow lama.</p>
                        </div>
                        <div class="hidden rounded-[24px] bg-slate-100 px-4 py-3 text-sm font-medium text-slate-600 sm:block">PHP Native</div>
                    </div>

                    <?php if ($error !== ''): ?>
                        <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form method="post" class="mt-8 space-y-5">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="login">
                        <?= ui_input('login', 'Nama / Email', request_value('login', ''), 'text', ['required' => 'required', 'autocomplete' => 'username']) ?>
                        <?= ui_input('password', 'Password', '', 'password', ['required' => 'required', 'autocomplete' => 'current-password']) ?>
                        <?= ui_select('unit_id', 'Pilih Unit', array_column($units, 'nama_unit', 'id'), request_value('unit_id', $units[0]['id'] ?? null), ['required' => 'required']) ?>
                        <div class="pt-2">
                            <?= ui_button('Masuk', ['type' => 'submit', 'variant' => 'success']) ?>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </main>
<?php else: ?>
    <div class="app-shell">
        <aside class="app-sidebar">
            <div class="app-sidebar-inner">
                <div class="rounded-[30px] bg-slate-900 p-5 text-white shadow-[0_24px_70px_rgba(15,23,42,.25)]">
                    <p class="text-xs font-semibold tracking-[0.35em] text-slate-300">ACTIVE UNIT</p>
                    <h1 class="mt-4 text-3xl font-semibold leading-tight"><?= e($unitName) ?></h1>
                    <p class="mt-3 text-sm text-slate-300"><?= e($user['name']) ?> · <?= e($user['role']) ?></p>
                </div>

                <nav class="sidebar-nav">
                    <button class="nav-link nav-pill active" data-section="dashboard"><?= ui_icon('home', 'h-5 w-5') ?> Dashboard</button>
                    <button class="nav-link nav-pill" data-section="absensi"><?= ui_icon('calendar', 'h-5 w-5') ?> Absensi</button>
                    <button class="nav-link nav-pill" data-section="validasi"><?= ui_icon('check-circle', 'h-5 w-5') ?> Validasi</button>
                    <button class="nav-link nav-pill" data-section="gaji"><?= ui_icon('banknotes', 'h-5 w-5') ?> Gaji</button>
                </nav>

                <div class="pt-1">
                    <a href="logout.php" class="inline-flex w-full items-center justify-center rounded-2xl bg-slate-100 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-200">Keluar</a>
                </div>
            </div>
        </aside>

        <main class="content-frame">
            <div class="soft-card mb-4 flex shrink-0 items-start justify-between gap-4 px-5 py-5 lg:mb-6 lg:px-8">
                <div class="min-w-0">
                    <p class="text-sm text-slate-500">Sistem Penggajian Native</p>
                    <h2 id="page-title" class="mt-2 text-2xl font-semibold text-slate-900 lg:text-4xl">Dashboard</h2>
                </div>
                <div class="text-right text-sm text-slate-500">
                    <p><?= e(date('d F Y')) ?></p>
                    <p>AJAX + polling aktif</p>
                </div>
            </div>

            <div class="content-scroll">
                <div id="toast" class="mb-4 hidden rounded-2xl px-4 py-3 text-sm font-medium"></div>
                <div id="page-content" class="space-y-6 pb-4"></div>
            </div>
        </main>
    </div>

    <script src="<?= e(asset_url('assets/app.js')) ?>" defer></script>
<?php endif; ?>
</body>
</html>
