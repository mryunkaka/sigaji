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
<div id="app-loader" class="app-loader">
    <div class="app-loader-card">
        <div class="app-loader-spinner">
            <span class="animate-spin"><?= ui_icon('arrow-path', 'h-7 w-7') ?></span>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Menyiapkan Halaman</p>
            <h2 class="mt-2 text-xl font-semibold text-slate-900">Memuat data dan asset</h2>
            <p id="app-loader-status" class="mt-2 text-sm text-slate-500">Memulai pemuatan aplikasi...</p>
        </div>
        <div class="w-full space-y-3">
            <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                <div id="app-loader-bar" class="app-loader-progress h-full rounded-full bg-slate-900" style="width: 0%"></div>
            </div>
            <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                <span>Progress</span>
                <span id="app-loader-percent">0%</span>
            </div>
        </div>
        <div class="w-full rounded-[24px] border border-slate-200 bg-slate-50/90 p-4 text-left">
            <p class="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Tahap Pemuatan</p>
            <div class="space-y-2.5 text-sm text-slate-600">
                <div id="loader-step-shell" class="loader-step">Menyiapkan shell aplikasi</div>
                <div id="loader-step-assets" class="loader-step">Memuat asset stylesheet dan script</div>
                <div id="loader-step-components" class="loader-step">Menyusun komponen antarmuka</div>
                <div id="loader-step-data" class="loader-step">Mengambil data halaman awal</div>
                <div id="loader-step-ready" class="loader-step">Finalisasi tampilan siap pakai</div>
            </div>
        </div>
    </div>
</div>
<?php if (!$user): ?>
    <main class="login-shell">
        <section class="login-single-wrap">
            <div class="login-single-card">
                <div class="text-center">
                    <p class="text-xl font-bold uppercase tracking-tight text-slate-900 sm:text-2xl">Sistem Penggajian</p>
                    <h2 class="mt-3 text-3xl font-semibold text-slate-900 sm:text-[4rem] sm:leading-none">Sign in</h2>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" class="mt-7 space-y-5">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="login">
                    <?= ui_input('login', 'Nama / Email*', request_value('login', ''), 'text', ['required' => 'required', 'autocomplete' => 'username']) ?>
                    <label class="block">
                        <span class="mb-2 block text-sm font-medium text-slate-700">Password*</span>
                        <div class="relative">
                            <input id="login-password" type="password" name="password" value="" required autocomplete="current-password" class="w-full rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3 pr-14 text-sm text-slate-900 outline-none transition focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100">
                            <button type="button" class="absolute inset-y-0 right-0 inline-flex w-12 items-center justify-center text-slate-400 transition hover:text-slate-600" data-toggle-password data-target="login-password" aria-label="Tampilkan password">
                                <span data-password-icon="show"><?= ui_icon('eye', 'h-5 w-5') ?></span>
                                <span data-password-icon="hide" class="hidden"><?= ui_icon('eye-slash', 'h-5 w-5') ?></span>
                            </button>
                        </div>
                    </label>
                    <?= ui_select('unit_id', 'Pilih Unit*', array_column($units, 'nama_unit', 'id'), request_value('unit_id', $units[0]['id'] ?? null), ['required' => 'required']) ?>
                    <div class="pt-2">
                        <?= ui_button('Sign in', ['type' => 'submit', 'variant' => 'warning', 'class' => 'w-full']) ?>
                    </div>
                </form>
            </div>
        </section>
    </main>
<?php else: ?>
    <div class="app-shell">
        <div class="sidebar-backdrop" data-sidebar-backdrop></div>

        <aside class="app-sidebar">
            <div class="app-sidebar-inner">
                <div class="flex items-start justify-between gap-3 rounded-[30px] bg-slate-900 p-5 text-white shadow-[0_24px_70px_rgba(15,23,42,.25)]">
                    <div>
                        <p class="text-xs font-semibold tracking-[0.35em] text-slate-300">UNIT AKTIF</p>
                        <h1 class="mt-4 text-3xl font-semibold leading-tight"><?= e($unitName) ?></h1>
                        <p class="mt-3 text-sm text-slate-300"><?= e($user['name']) ?> &middot; <?= e($user['role']) ?></p>
                    </div>
                    <button type="button" class="sidebar-toggle border-white/10 bg-white/10 text-white hover:bg-white/15" data-sidebar-close aria-label="Sembunyikan sidebar">
                        <?= ui_icon('x-mark', 'h-5 w-5') ?>
                    </button>
                </div>

                <nav class="sidebar-nav">
                    <button class="nav-link nav-pill active" data-section="dashboard"><?= ui_icon('home', 'h-5 w-5') ?> Dashboard</button>
                    <button class="nav-link nav-pill" data-section="absensi"><?= ui_icon('calendar', 'h-5 w-5') ?> Absensi</button>
                    <button class="nav-link nav-pill" data-section="validasi"><?= ui_icon('check-circle', 'h-5 w-5') ?> Validasi</button>
                    <button class="nav-link nav-pill" data-section="gaji"><?= ui_icon('banknotes', 'h-5 w-5') ?> Gaji</button>
                    <button class="nav-link nav-pill" data-section="users"><?= ui_icon('users', 'h-5 w-5') ?> User</button>
                    <button class="nav-link nav-pill" data-section="units"><?= ui_icon('building-office-2', 'h-5 w-5') ?> Unit</button>
                </nav>

                <div class="pt-1">
                    <a href="logout.php" class="inline-flex w-full items-center justify-center rounded-2xl bg-slate-100 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-200">Keluar</a>
                </div>
            </div>
        </aside>

        <main class="content-frame">
            <div class="soft-card mb-4 flex shrink-0 items-start justify-between gap-4 px-5 py-5 lg:mb-6 lg:px-8">
                <div class="min-w-0 flex items-start gap-3">
                    <button type="button" class="sidebar-toggle sidebar-toggle-mobile" data-sidebar-toggle aria-label="Tampilkan sidebar">
                        <?= ui_icon('bars-3', 'h-5 w-5') ?>
                    </button>
                    <button type="button" class="sidebar-toggle sidebar-toggle-desktop" data-sidebar-toggle aria-label="Tampilkan sidebar">
                        <?= ui_icon('bars-3', 'h-5 w-5') ?>
                    </button>
                    <div class="min-w-0">
                        <p class="text-sm text-slate-500">Panel operasional absensi dan penggajian</p>
                        <h2 id="page-title" class="mt-2 text-2xl font-semibold text-slate-900 lg:text-4xl">Dashboard</h2>
                    </div>
                </div>
                <div class="text-right text-sm text-slate-500">
                    <p><?= e(date('d F Y')) ?></p>
                    <p>Data unit aktif siap dikelola</p>
                </div>
            </div>

            <div class="content-scroll">
                <div id="toast" class="mb-4 hidden rounded-2xl px-4 py-3 text-sm font-medium"></div>
                <div id="page-content" class="space-y-6 pb-4"></div>
            </div>
        </main>
    </div>
<?php endif; ?>
<script src="<?= e(asset_url('assets/app.js')) ?>" defer></script>
</body>
</html>
