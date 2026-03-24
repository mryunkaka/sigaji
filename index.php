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

<?php endif; ?>
<script src="<?= e(asset_url('assets/app.js')) ?>" defer></script>
</body>
</html>
