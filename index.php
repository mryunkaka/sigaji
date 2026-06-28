<?php

/**
 * Local-dev front controller fallback.
 * Some localhost servers route every request to index.php, so dispatch known
 * project endpoints manually before rendering the shell page.
 */
$requestPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
$requestPath = '/' . ltrim(str_replace('\\', '/', $requestPath), '/');
$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/.');

if ($scriptDir !== '' && $scriptDir !== '/' && str_starts_with($requestPath, $scriptDir . '/')) {
    $requestPath = substr($requestPath, strlen($scriptDir));
    $requestPath = $requestPath === '' ? '/' : $requestPath;
}

$serveStaticFile = static function (string $absolutePath): never {
    if (!is_file($absolutePath)) {
        http_response_code(404);
        exit('File tidak ditemukan.');
    }

    $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'text/javascript; charset=UTF-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'pdf' => 'application/pdf',
    ];

    header('Content-Type: ' . ($mimeTypes[$extension] ?? 'application/octet-stream'));
    header('Content-Length: ' . (string) filesize($absolutePath));
    readfile($absolutePath);
    exit;
};

$dispatchPhp = static function (string $relativePath): never {
    $target = realpath(__DIR__ . '/' . ltrim($relativePath, '/'));
    if ($target === false || !is_file($target)) {
        http_response_code(404);
        exit('Halaman tidak ditemukan.');
    }

    require $target;
    exit;
};

if ($requestPath !== '/' && $requestPath !== '/index.php') {
    if (str_starts_with($requestPath, '/assets/')) {
        $assetPath = realpath(__DIR__ . '/public' . $requestPath);
        if ($assetPath !== false && str_starts_with(str_replace('\\', '/', $assetPath), str_replace('\\', '/', realpath(__DIR__ . '/public/assets') ?: ''))) {
            $serveStaticFile($assetPath);
        }
    }

    if (str_starts_with($requestPath, '/uploads/')) {
        $uploadPath = realpath(__DIR__ . '/public' . $requestPath);
        if ($uploadPath !== false && str_starts_with(str_replace('\\', '/', $uploadPath), str_replace('\\', '/', realpath(__DIR__ . '/public/uploads') ?: ''))) {
            $serveStaticFile($uploadPath);
        }
    }

    if (str_starts_with($requestPath, '/ajax/')) {
        $dispatchPhp($requestPath);
    }

    if (in_array($requestPath, ['/logout.php', '/print_slip.php', '/print_laporan.php', '/activity-log.php', '/subscription-admin.php'], true)) {
        $dispatchPhp($requestPath);
    }
}

require __DIR__ . '/bootstrap/app.php';

$error = '';
$authNotice = Auth::consumeNotice();
$subscriptionNotice = '';
$subscriptionError = '';
$units = fetch_all('SELECT id, nama_unit FROM units ORDER BY nama_unit');

if (is_post() && request_value('action') === 'subscription_request') {
    verify_csrf();
    $result = SubscriptionService::submitRequest($_POST, $_FILES['proof_file'] ?? []);
    if ($result['success']) {
        $subscriptionNotice = $result['message'];
    } else {
        $subscriptionError = $result['message'];
    }
}

if (is_post() && request_value('action') === 'login') {
    verify_csrf();
    
    // Initialize security service
    $security = SecurityService::getInstance();
    
    // Check rate limiting
    if (!$security->checkRateLimit()) {
        $error = 'Terlalu banyak permintaan. Silakan coba lagi dalam beberapa menit.';
    } else {
        // Check brute force protection
        $loginIdentifier = trim((string) request_value('login'));
        if (!$security->checkBruteForce($loginIdentifier)) {
            $error = 'Terlalu banyak percobaan login gagal. Akun dikunci sementara. Silakan coba lagi dalam ' . floor(env('BRUTE_FORCE_LOCKOUT_TIME', '900') / 60) . ' menit.';
        } else {
            $login = $loginIdentifier;
            $password = (string) request_value('password');
            $unitId = (int) request_value('unit_id');

            // Sanitize inputs
            $login = $security->sanitizeInput($login);
            
            if ($security->hasXSS($login) || $security->hasXSS($password)) {
                $error = 'Input tidak valid.';
                $security->recordFailedAttempt($loginIdentifier);
            } elseif ($login === '' || $password === '' || $unitId <= 0) {
                $error = 'Login, password, dan unit wajib diisi.';
            } elseif (Auth::attempt($login, $password, $unitId)) {
                $security->resetBruteForce($loginIdentifier);
                $loginUser = Auth::user();
                if (($loginUser['role'] ?? '') === 'owner' && !SubscriptionService::isActiveForUnit((int) $loginUser['unit_id'])) {
                    redirect_to('index.php#settings');
                }
                redirect_to('index.php');
            } else {
                $security->recordFailedAttempt($loginIdentifier);
                $error = 'Kredensial tidak valid atau unit tidak sesuai.';
            }
        }
    }
}

$user = Auth::user();
$unitName = $user ? (fetch_one('SELECT nama_unit FROM units WHERE id = :id', ['id' => $user['unit_id']])['nama_unit'] ?? '-') : '-';
$selectedSubscriptionUnit = (int) request_value('unit_id', $units[0]['id'] ?? 0);
$showOwnerLogin = request_value('admin') === '1' || (is_post() && request_value('action') === 'login');
$hasActiveUnit = false;
$selectedSubscriptionStatus = null;
foreach ($units as $unitOption) {
    $status = SubscriptionService::statusForUnit((int) $unitOption['id']);
    if ((int) $unitOption['id'] === $selectedSubscriptionUnit) {
        $selectedSubscriptionStatus = $status;
    }
    if ($status['active']) {
        $hasActiveUnit = true;
    }
}
$subscriptionGateActive = !$user
    && ($selectedSubscriptionStatus['locked'] ?? false)
    && (
        !$hasActiveUnit
        || request_value('action') === 'subscription_request'
        || (request_value('action') === 'login' && $error !== '')
        || request_value('renew') === '1'
    );
$durationOptions = SubscriptionService::durationOptions();
$currentSubscriptionStatus = $user ? SubscriptionService::statusForUnit((int) $user['unit_id']) : null;
$subscriptionLockedForCurrentUser = $user && ($currentSubscriptionStatus['locked'] ?? false);
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
    <?php if ($subscriptionGateActive): ?>
        <main class="login-shell">
            <section class="login-single-wrap">
                <div class="login-single-card max-w-[680px]">
                    <div class="text-center">
                        <p class="text-sm font-semibold uppercase tracking-[0.28em] text-amber-600">Subscription Jatuh Tempo</p>
                        <h2 class="mt-3 text-3xl font-semibold text-slate-900 sm:text-5xl">Perpanjangan Akses</h2>
                        <p class="mt-4 text-sm text-slate-500">Mulai tanggal 28 jam 00:00, aplikasi tidak bisa diakses oleh semua unit sampai subscription diperpanjang.</p>
                    </div>

                    <?php if ($subscriptionNotice !== ''): ?>
                        <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($subscriptionNotice) ?></div>
                    <?php endif; ?>
                    <?php if ($subscriptionError !== ''): ?>
                        <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($subscriptionError) ?></div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data" class="mt-7 grid gap-4 md:grid-cols-2">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="subscription_request">
                        <?= ui_select('unit_id', 'Unit*', array_column($units, 'nama_unit', 'id'), $selectedSubscriptionUnit, ['required' => 'required']) ?>
                        <?= ui_select('duration_code', 'Waktu Berlangganan*', $durationOptions, request_value('duration_code', '1_month'), ['required' => 'required']) ?>
                        <?= ui_input('requested_by', 'Nama Pengirim', request_value('requested_by', ''), 'text', ['placeholder' => 'Nama user / admin']) ?>
                        <?= ui_input('contact', 'No. HP / Kontak', request_value('contact', ''), 'text', ['placeholder' => 'Kontak untuk konfirmasi']) ?>
                        <label class="block md:col-span-2">
                            <span class="mb-2 block text-sm font-medium text-slate-700">Foto / File Bukti Pembayaran*</span>
                            <input type="file" name="proof_file" accept="image/jpeg,image/png,image/webp,application/pdf" required class="w-full rounded-2xl border border-slate-200 bg-slate-50/90 px-4 py-3 text-sm text-slate-900 outline-none transition file:mr-4 file:rounded-xl file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100">
                            <span class="mt-2 block text-xs text-slate-500">Format JPG, PNG, WEBP, atau PDF. Maksimal 5 MB.</span>
                        </label>
                        <div class="md:col-span-2">
                            <?= ui_button('Kirim Bukti Pembayaran', ['type' => 'submit', 'variant' => 'warning', 'class' => 'w-full', 'icon' => 'arrow-up-tray']) ?>
                        </div>
                    </form>

                    <?php if ($showOwnerLogin): ?>
                        <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                            <?php if ($authNotice !== ''): ?>
                                <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700"><?= e($authNotice) ?></div>
                            <?php endif; ?>
                            <?php if ($error !== ''): ?>
                                <div class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div>
                            <?php endif; ?>
                            <form method="post" class="grid gap-4">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="login">
                                <?= ui_input('login', 'Nama / Email Owner*', request_value('login', ''), 'text', ['required' => 'required', 'autocomplete' => 'username']) ?>
                                <?= ui_input('password', 'Password*', '', 'password', ['required' => 'required', 'autocomplete' => 'current-password']) ?>
                                <?= ui_select('unit_id', 'Unit Validasi*', array_column($units, 'nama_unit', 'id'), $selectedSubscriptionUnit, ['required' => 'required']) ?>
                                <?= ui_button('Masuk ke Setting Perpanjangan', ['type' => 'submit', 'variant' => 'primary', 'class' => 'w-full']) ?>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    <?php elseif (!$user): ?>
        <main class="login-shell">
            <section class="login-single-wrap">
                <div class="login-single-card">
                    <div class="text-center">
                        <p class="text-xl font-bold uppercase tracking-tight text-slate-900 sm:text-2xl">Sistem Penggajian</p>
                        <h2 class="mt-3 text-3xl font-semibold text-slate-900 sm:text-[4rem] sm:leading-none">Sign in</h2>
                    </div>

                    <?php if ($authNotice !== ''): ?>
                        <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700"><?= e($authNotice) ?></div>
                    <?php endif; ?>

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
                        <?php if ($subscriptionLockedForCurrentUser): ?>
                            <p class="mt-3 rounded-2xl bg-amber-400/15 px-3 py-2 text-xs font-semibold text-amber-100">Subscription perlu diperpanjang</p>
                        <?php endif; ?>
                        </div>
                        <button type="button" class="sidebar-toggle border-white/10 bg-white/10 text-white hover:bg-white/15" data-sidebar-close aria-label="Sembunyikan sidebar">
                            <?= ui_icon('x-mark', 'h-5 w-5') ?>
                        </button>
                    </div>

                    <nav class="sidebar-nav">
                        <?php if (!$subscriptionLockedForCurrentUser): ?>
                            <button class="nav-link nav-pill active" data-section="dashboard"><?= ui_icon('home', 'h-5 w-5') ?> Dashboard</button>
                            <button class="nav-link nav-pill" data-section="absensi"><?= ui_icon('calendar', 'h-5 w-5') ?> Absensi</button>
                            <button class="nav-link nav-pill" data-section="validasi"><?= ui_icon('check-circle', 'h-5 w-5') ?> Validasi</button>
                            <button class="nav-link nav-pill" data-section="gaji"><?= ui_icon('banknotes', 'h-5 w-5') ?> Gaji</button>
                            <button class="nav-link nav-pill" data-section="users"><?= ui_icon('users', 'h-5 w-5') ?> User</button>
                            <button class="nav-link nav-pill" data-section="units"><?= ui_icon('building-office-2', 'h-5 w-5') ?> Unit</button>
                        <?php endif; ?>
                        <button class="nav-link nav-pill <?= $subscriptionLockedForCurrentUser ? 'active' : '' ?>" data-section="settings"><?= ui_icon('cog-6-tooth', 'h-5 w-5') ?> Setting</button>
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
                            <p class="text-sm text-slate-500"><?= $subscriptionLockedForCurrentUser ? 'Panel perpanjangan subscription' : 'Panel operasional absensi dan penggajian 2' ?></p>
                            <h2 id="page-title" class="mt-2 text-2xl font-semibold text-slate-900 lg:text-4xl"><?= $subscriptionLockedForCurrentUser ? 'Setting' : 'Dashboard' ?></h2>
                        </div>
                    </div>
                    <div class="text-right text-sm text-slate-500">
                        <p><?= e(date('d F Y')) ?></p>
                        <p><?= $subscriptionLockedForCurrentUser ? 'Validasi pembayaran untuk membuka akses' : 'Data unit aktif siap dikelola' ?></p>
                    </div>
                </div>

                <div class="content-scroll">
                    <div id="toast" class="mb-4 hidden rounded-2xl px-4 py-3 text-sm font-medium"></div>
                    <div id="page-content" class="space-y-6 pb-4"></div>
                </div>
            </main>
        </div>
    <?php endif; ?>
    <?php if ($subscriptionLockedForCurrentUser): ?>
        <script>
            localStorage.setItem('sigaji.active.section', 'settings');
            if (!window.location.hash) {
                window.location.hash = 'settings';
            }
        </script>
    <?php endif; ?>
    <script src="<?= e(asset_url('assets/app.js')) ?>" defer></script>
</body>

</html>
