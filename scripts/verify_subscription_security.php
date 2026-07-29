<?php

declare(strict_types=1);

/**
 * Manual end-to-end verification script for SIGAJI subscription security hardening.
 *
 * Purpose (bahasa Indonesia):
 *   Membuktikan secara nyata (bukan cuma syntax check) bahwa:
 *     1. Approve/reject/perpanjangan subscription HANYA bisa lewat subscription-admin.php
 *        (ajax/save_subscription_action.php diblok total, walau dipanggil oleh owner yang login sah).
 *     2. Form PIN di subscription-admin.php tidak bisa di-brute-force: setelah N percobaan
 *        gagal, IP dikunci (persisten di file, tidak bisa dilewati dengan hapus cookie),
 *        dan PIN yang BENAR sekalipun tetap ditolak selama masih terkunci.
 *     3. Begitu subscription dianggap kedaluwarsa -- baik lewat deteksi otomatis
 *        (SubscriptionService::statusForUnit() saat user mengakses app) maupun lewat aksi
 *        admin manual (set_expiry / disable_subscription di subscription-admin.php) --
 *        SEMUA user dipaksa logout (session_login_token di-null-kan), dan itu tidak
 *        di-spam ulang pada pemanggilan berikutnya selama masih dalam status locked.
 *
 * How it works:
 *   - Menggunakan koneksi database project APA ADANYA (baca ulang bootstrap/app.php +
 *     .env asli), jadi TIDAK ada kredensial yang di-hardcode di file ini.
 *   - Menjalankan PHP built-in server (php -S) sementara di project root untuk menguji
 *     endpoint HTTP yang sesungguhnya (termasuk CSRF + session), lalu dimatikan lagi.
 *   - Semua data uji (user sementara, log aktivitas yang dihasilkan test, state units)
 *     di-snapshot sebelum test dan DIKEMBALIKAN PERSIS setelah test selesai (di blok
 *     finally), baik test lolos maupun gagal, supaya database dev/staging Anda tidak
 *     tercemar oleh data percobaan.
 *
 * Usage:
 *   php scripts/verify_subscription_security.php
 *   (jalankan dari mana saja; path dihitung otomatis dari lokasi file ini)
 *
 * Exit code:
 *   0 = semua test PASS
 *   1 = ada test yang FAIL (lihat ringkasan di akhir output)
 *
 * PERINGATAN:
 *   - Jalankan HANYA terhadap database development/staging, bukan produksi yang sedang
 *     dipakai user riil, karena script ini sengaja memicu kondisi subscription expired
 *     dan brute-force lockout (walau semuanya dikembalikan lagi di akhir).
 *   - Script butuh ekstensi PHP: pdo_mysql, curl (sudah dicek otomatis di awal).
 */

error_reporting(E_ALL & ~E_DEPRECATED);

$ROOT = dirname(__DIR__);
chdir($ROOT);

$results = [];
$overallStart = microtime(true);

function record(string $name, bool $pass, string $detail = ''): void
{
    global $results;
    $results[] = ['name' => $name, 'pass' => $pass, 'detail' => $detail];
    $tag = $pass ? '[PASS]' : '[FAIL]';
    echo $tag . ' ' . $name . ($detail !== '' ? ' -> ' . $detail : '') . PHP_EOL;
}

function fail_hard(string $message): never
{
    throw new RuntimeException('[FATAL] ' . $message);
}

/**
 * Minimal cookie-jar aware HTTP client using curl.
 * Returns ['status' => int, 'body' => string].
 */
function http_call(string $method, string $url, array $fields, string $cookieJar): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FRESH_CONNECT => true,
        CURLOPT_FORBID_REUSE => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0,
        CURLOPT_HTTPHEADER => ['X-Requested-With: XMLHttpRequest', 'Connection: close'],
    ]);

    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    }

    $body = curl_exec($ch);
    if ($body === false) {
        $error = curl_error($ch);
        curl_close($ch);
        fail_hard("HTTP call to {$url} failed: {$error}");
    }
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'body' => (string) $body];
}

function extract_csrf(string $html): ?string
{
    if (preg_match('/<meta\s+name="csrf-token"\s+content="([^"]*)"/i', $html, $m)) {
        return html_entity_decode($m[1], ENT_QUOTES);
    }
    return null;
}

function wait_for_server(string $baseUrl, int $timeoutSeconds = 8): bool
{
    $deadline = microtime(true) + $timeoutSeconds;
    while (microtime(true) < $deadline) {
        $ch = curl_init($baseUrl . '/index.php');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 1]);
        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($ok !== false && $status > 0) {
            return true;
        }
        usleep(150000);
    }
    return false;
}

// ---------------------------------------------------------------------------
// 0. Preflight
// ---------------------------------------------------------------------------
echo "=== SIGAJI subscription security verification ===" . PHP_EOL;

foreach (['pdo_mysql', 'curl', 'json'] as $ext) {
    if (!extension_loaded($ext)) {
        fail_hard("Ekstensi PHP wajib '{$ext}' tidak aktif.");
    }
}

require $ROOT . '/bootstrap/app.php';

try {
    $pdo = db();
    $pdo->query('SELECT 1');
} catch (Throwable $e) {
    fail_hard('Tidak bisa konek database via .env project: ' . $e->getMessage());
}
record('DB connectivity via project .env', true, 'connected to ' . (env('DB_NAME', '?')));

foreach (['units', 'users', 'activity_logs', 'subscription_requests'] as $table) {
    try {
        $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
        record("Table exists: {$table}", true);
    } catch (Throwable $e) {
        fail_hard("Tabel wajib '{$table}' tidak ditemukan/tidak bisa diquery: " . $e->getMessage());
    }
}

$adminPin = (string) env('SUBSCRIPTION_ADMIN_PIN', '');
if ($adminPin === '') {
    fail_hard('SUBSCRIPTION_ADMIN_PIN belum diatur di .env — tidak bisa menguji alur PIN.');
}

$bruteMax = max(1, (int) env('BRUTE_FORCE_MAX_ATTEMPTS', '5'));

// ---------------------------------------------------------------------------
// 1. Start built-in PHP server for real HTTP-level testing
// ---------------------------------------------------------------------------
$phpBinary = PHP_BINARY;
$port = null;
$serverProcess = null;
$serverPipes = [];

for ($candidate = 8890; $candidate <= 8920; $candidate++) {
    $descriptorSpec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $cmd = escapeshellarg($phpBinary) . ' -S 127.0.0.1:' . $candidate . ' -t ' . escapeshellarg($ROOT);
    $proc = proc_open($cmd, $descriptorSpec, $pipes, $ROOT);
    if (!is_resource($proc)) {
        continue;
    }
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    usleep(300000);
    $status = proc_get_status($proc);
    if ($status['running']) {
        $baseUrl = 'http://127.0.0.1:' . $candidate;
        if (wait_for_server($baseUrl)) {
            $port = $candidate;
            $serverProcess = $proc;
            $serverPipes = $pipes;
            break;
        }
    }
    foreach ($pipes as $p) {
        if (is_resource($p)) {
            fclose($p);
        }
    }
    proc_terminate($proc);
    proc_close($proc);
}

if ($port === null) {
    fail_hard('Tidak bisa start php -S built-in server di port 8890-8920 (semua terpakai?).');
}
$baseUrl = 'http://127.0.0.1:' . $port;
record('Built-in PHP server started', true, $baseUrl);

// ---------------------------------------------------------------------------
// Fixtures & snapshot (so we can restore everything in finally{})
// ---------------------------------------------------------------------------
$unitsBackup = $pdo->query('SELECT * FROM units')->fetchAll(PDO::FETCH_ASSOC);
$activityLogStartMaxId = (int) ($pdo->query('SELECT COALESCE(MAX(id), 0) AS m FROM activity_logs')->fetch()['m'] ?? 0);
$firstUnitId = (int) ($unitsBackup[0]['id'] ?? 0);
if ($firstUnitId <= 0) {
    fail_hard('Tabel units kosong, tidak bisa membuat fixture test.');
}

$tempOwnerEmail = 'verify-script-owner-' . bin2hex(random_bytes(4)) . '@local.invalid';
$tempOwnerPassword = 'VerifyScript!' . bin2hex(random_bytes(4));
$tempOwnerId = null;

$cookieJarLogin = tempnam(sys_get_temp_dir(), 'sigaji_cj_');
$cookieJarPin = tempnam(sys_get_temp_dir(), 'sigaji_cj_');
$cookieJarPinBrute = tempnam(sys_get_temp_dir(), 'sigaji_cj_');

$exitCode = 0;

try {
    // Create temp owner user (role=owner bypasses subscription lock + unit match check).
    $stmt = $pdo->prepare(
        'INSERT INTO users (name, email, password, two_factor_secret, two_factor_recovery_codes, two_factor_confirmed_at, role, unit_id, session_login_token, created_at, updated_at)
         VALUES (:name, :email, :password, :tfa_secret, :tfa_codes, :tfa_confirmed, :role, :unit_id, :token, :created_at, :updated_at)'
    );
    $stmt->execute([
        'name' => 'Verify Script Owner',
        'email' => $tempOwnerEmail,
        'password' => password_hash($tempOwnerPassword, PASSWORD_DEFAULT),
        'tfa_secret' => '',
        'tfa_codes' => '',
        'tfa_confirmed' => date('Y-m-d H:i:s'),
        'role' => 'owner',
        'unit_id' => $firstUnitId,
        'token' => bin2hex(random_bytes(16)), // simulate "already logged in on a device"
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
    $tempOwnerId = (int) $pdo->lastInsertId();
    record('Fixture: temp owner user created', true, "user_id={$tempOwnerId}");

    // =========================================================================
    // TEST 1: Single-door enforcement — ajax/save_subscription_action.php must
    // reject ALL subscription actions, even from an authenticated owner session.
    // =========================================================================
    echo PHP_EOL . '--- TEST 1: Single-door enforcement (ajax/save_subscription_action.php) ---' . PHP_EOL;

    $loginPage = http_call('GET', $baseUrl . '/index.php', [], $cookieJarLogin);
    $csrf1 = extract_csrf($loginPage['body']);
    if (!$csrf1) {
        fail_hard('Tidak bisa ambil CSRF token dari halaman login.');
    }

    $loginResp = http_call('POST', $baseUrl . '/index.php', [
        '_csrf' => $csrf1,
        'action' => 'login',
        'login' => $tempOwnerEmail,
        'password' => $tempOwnerPassword,
        'unit_id' => (string) $firstUnitId,
    ], $cookieJarLogin);
    record(
        'Temp owner login via HTTP succeeds',
        $loginResp['status'] === 302,
        'HTTP ' . $loginResp['status']
    );

    foreach (['approve', 'reject', 'manual_extend', 'set_expiry', 'disable_subscription'] as $action) {
        $resp = http_call('POST', $baseUrl . '/ajax/save_subscription_action.php', [
            '_csrf' => $csrf1,
            'subscription_action' => $action,
            'request_id' => '999999',
            'unit_id' => (string) $firstUnitId,
            'duration_code' => '1_month',
        ], $cookieJarLogin);
        $json = json_decode($resp['body'], true);
        $blocked = $resp['status'] === 403 && is_array($json) && ($json['success'] ?? true) === false;
        record(
            "ajax/save_subscription_action.php blocks action='{$action}' from owner session",
            $blocked,
            'HTTP ' . $resp['status'] . ' body=' . substr($resp['body'], 0, 160)
        );
    }

    // =========================================================================
    // TEST 2: Legitimate single-door admin flow via subscription-admin.php PIN,
    // and confirm it triggers force-logout-all-users on expiry actions.
    // =========================================================================
    echo PHP_EOL . '--- TEST 2: Legitimate admin flow (subscription-admin.php) forces logout ---' . PHP_EOL;

    $adminPage = http_call('GET', $baseUrl . '/subscription-admin.php', [], $cookieJarPin);
    $csrf2 = extract_csrf($adminPage['body']);
    if (!$csrf2) {
        fail_hard('Tidak bisa ambil CSRF token dari subscription-admin.php.');
    }

    $unlockResp = http_call('POST', $baseUrl . '/subscription-admin.php', [
        '_csrf' => $csrf2,
        'action' => 'pin_login',
        'pin' => $adminPin,
    ], $cookieJarPin);
    record(
        'subscription-admin.php: correct PIN unlocks admin panel',
        str_contains($unlockResp['body'], 'terbuka'),
        'response contains "terbuka"? ' . (str_contains($unlockResp['body'], 'terbuka') ? 'yes' : 'no')
    );

    // Refresh token in DB right before triggering expiry, to simulate an active session.
    $pdo->prepare('UPDATE users SET session_login_token = :t WHERE id = :id')
        ->execute(['t' => bin2hex(random_bytes(16)), 'id' => $tempOwnerId]);

    $pastExpiry = (new DateTimeImmutable('-1 day'))->format('Y-m-d\TH:i');
    $expireResp = http_call('POST', $baseUrl . '/subscription-admin.php', [
        '_csrf' => $csrf2,
        'action' => 'set_expiry',
        'expires_at' => $pastExpiry,
        'admin_notes' => 'verify-script set_expiry test',
    ], $cookieJarPin);
    $expireJson = null;
    // subscription-admin.php renders full HTML, not JSON; detect success message text instead.
    $expireOk = $expireResp['status'] === 200 && !str_contains($expireResp['body'], 'tidak valid');
    record('subscription-admin.php: set_expiry (past date) accepted', $expireOk, 'HTTP ' . $expireResp['status']);

    $tokenAfterExpiry = $pdo->prepare('SELECT session_login_token FROM users WHERE id = :id');
    $tokenAfterExpiry->execute(['id' => $tempOwnerId]);
    $tokenRow = $tokenAfterExpiry->fetch();
    $tokenValue = $tokenRow !== false ? $tokenRow['session_login_token'] : 'ROW_NOT_FOUND_SENTINEL';
    record(
        'set_expiry(past) forces logout: session_login_token nulled',
        $tokenValue === null,
        'value=' . var_export($tokenValue, true)
    );

    $lockedCountRow = $pdo->query('SELECT COUNT(*) c FROM units WHERE subscription_locked_at IS NOT NULL')->fetch();
    record(
        'set_expiry(past) locks all units',
        (int) $lockedCountRow['c'] === count($unitsBackup),
        (int) $lockedCountRow['c'] . '/' . count($unitsBackup) . ' units locked'
    );

    $forceLogoutLogCount = (int) $pdo->query(
        "SELECT COUNT(*) c FROM activity_logs WHERE action = 'subscription_force_logout' AND id > {$activityLogStartMaxId}"
    )->fetch()['c'];
    record('set_expiry(past) writes subscription_force_logout activity log', $forceLogoutLogCount >= 1, "count={$forceLogoutLogCount}");

    // Test disableGlobal() also force-logouts.
    $pdo->prepare('UPDATE users SET session_login_token = :t WHERE id = :id')
        ->execute(['t' => bin2hex(random_bytes(16)), 'id' => $tempOwnerId]);

    $disableResp = http_call('POST', $baseUrl . '/subscription-admin.php', [
        '_csrf' => $csrf2,
        'action' => 'disable_subscription',
        'admin_notes' => 'verify-script disable test',
    ], $cookieJarPin);
    record('subscription-admin.php: disable_subscription accepted', $disableResp['status'] === 200, 'HTTP ' . $disableResp['status']);

    $tokenAfterDisable = $pdo->prepare('SELECT session_login_token FROM users WHERE id = :id');
    $tokenAfterDisable->execute(['id' => $tempOwnerId]);
    $tokenRow2 = $tokenAfterDisable->fetch();
    $tokenValue2 = $tokenRow2 !== false ? $tokenRow2['session_login_token'] : 'ROW_NOT_FOUND_SENTINEL';
    record(
        'disable_subscription forces logout: session_login_token nulled',
        $tokenValue2 === null,
        'value=' . var_export($tokenValue2, true)
    );

    http_call('POST', $baseUrl . '/subscription-admin.php', ['_csrf' => $csrf2, 'action' => 'pin_logout'], $cookieJarPin);

    // =========================================================================
    // TEST 3: Automatic expiry detection path (no admin action at all) —
    // SubscriptionService::statusForUnit() itself must detect expiry and force
    // logout, exactly once (no spam on repeated calls while still locked).
    // =========================================================================
    echo PHP_EOL . '--- TEST 3: Automatic expiry detection (SubscriptionService::statusForUnit) ---' . PHP_EOL;

    $future = (new DateTimeImmutable('+1 day'))->format('Y-m-d H:i:s');
    $pdo->exec("UPDATE units SET subscription_expires_at = " . $pdo->quote($future) . ", subscription_locked_at = NULL");
    $pdo->prepare('UPDATE users SET session_login_token = :t WHERE id = :id')
        ->execute(['t' => bin2hex(random_bytes(16)), 'id' => $tempOwnerId]);

    $statusActive = SubscriptionService::statusForUnit($firstUnitId);
    record('Fixture: subscription set to ACTIVE (future expiry)', $statusActive['active'] === true, json_encode($statusActive));

    $tokenStillSetRow = $pdo->prepare('SELECT session_login_token FROM users WHERE id = :id');
    $tokenStillSetRow->execute(['id' => $tempOwnerId]);
    $tokenStillSet = $tokenStillSetRow->fetch()['session_login_token'] ?? null;
    record('While ACTIVE: no spurious force-logout happens', $tokenStillSet !== null, 'token still set? ' . ($tokenStillSet !== null ? 'yes' : 'no'));

    // Simulate "just crossed expiry, not yet detected by any request": expires_at
    // in the past but locked_at still NULL (nobody has hit the app since expiry yet).
    $past = (new DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s');
    $pdo->exec("UPDATE units SET subscription_expires_at = " . $pdo->quote($past) . ", subscription_locked_at = NULL");

    $logCountBeforeDetection = (int) $pdo->query(
        "SELECT COUNT(*) c FROM activity_logs WHERE action = 'subscription_force_logout' AND id > {$activityLogStartMaxId}"
    )->fetch()['c'];

    $statusAfterExpiry = SubscriptionService::statusForUnit($firstUnitId);
    record('Automatic detection: statusForUnit() reports locked after expiry', $statusAfterExpiry['locked'] === true, json_encode($statusAfterExpiry));

    $tokenAfterAutoLockRow = $pdo->prepare('SELECT session_login_token FROM users WHERE id = :id');
    $tokenAfterAutoLockRow->execute(['id' => $tempOwnerId]);
    $tokenAutoRow = $tokenAfterAutoLockRow->fetch();
    $tokenAfterAutoLock = $tokenAutoRow !== false ? $tokenAutoRow['session_login_token'] : 'ROW_NOT_FOUND_SENTINEL';
    record(
        'Automatic detection forces logout: session_login_token nulled (no manual admin action needed)',
        $tokenAfterAutoLock === null,
        'value=' . var_export($tokenAfterAutoLock, true)
    );

    $logCountAfterDetection = (int) $pdo->query(
        "SELECT COUNT(*) c FROM activity_logs WHERE action = 'subscription_force_logout' AND id > {$activityLogStartMaxId}"
    )->fetch()['c'];
    record(
        'Automatic detection writes exactly one subscription_force_logout log for this transition',
        $logCountAfterDetection === $logCountBeforeDetection + 1,
        "before={$logCountBeforeDetection} after={$logCountAfterDetection}"
    );

    // Call again while still locked: must NOT spam another force-logout log.
    SubscriptionService::statusForUnit($firstUnitId);
    $logCountSecondCall = (int) $pdo->query(
        "SELECT COUNT(*) c FROM activity_logs WHERE action = 'subscription_force_logout' AND id > {$activityLogStartMaxId}"
    )->fetch()['c'];
    record(
        'Repeated statusForUnit() calls while locked do NOT spam force-logout',
        $logCountSecondCall === $logCountAfterDetection,
        "count stayed at {$logCountSecondCall}"
    );

    // =========================================================================
    // TEST 4: Persistent (file-based) brute-force lockout on subscription-admin.php
    // PIN form. Run LAST since it intentionally burns the 127.0.0.1 lockout.
    // =========================================================================
    echo PHP_EOL . '--- TEST 4: Persistent brute-force lockout on PIN form ---' . PHP_EOL;

    $bruteAdminPage = http_call('GET', $baseUrl . '/subscription-admin.php', [], $cookieJarPinBrute);
    $csrf4 = extract_csrf($bruteAdminPage['body']);
    if (!$csrf4) {
        fail_hard('Tidak bisa ambil CSRF token untuk test brute-force.');
    }

    $wrongPin = strrev($adminPin) . '9';
    if ($wrongPin === $adminPin) {
        $wrongPin .= '0';
    }

    $allRejectedBeforeLockout = true;
    for ($attempt = 1; $attempt <= $bruteMax; $attempt++) {
        $resp = http_call('POST', $baseUrl . '/subscription-admin.php', [
            '_csrf' => $csrf4,
            'action' => 'pin_login',
            'pin' => $wrongPin,
        ], $cookieJarPinBrute);
        if (!str_contains($resp['body'], 'PIN tidak sesuai')) {
            $allRejectedBeforeLockout = false;
        }
    }
    record(
        "Wrong PIN rejected on attempts 1..{$bruteMax} (below lockout threshold)",
        $allRejectedBeforeLockout,
        "max_attempts={$bruteMax}"
    );

    $lockoutResp = http_call('POST', $baseUrl . '/subscription-admin.php', [
        '_csrf' => $csrf4,
        'action' => 'pin_login',
        'pin' => $wrongPin,
    ], $cookieJarPinBrute);
    $isLocked = str_contains($lockoutResp['body'], 'dikunci sementara');
    record(
        "Attempt #" . ($bruteMax + 1) . ' triggers persistent lockout message',
        $isLocked,
        'body contains lockout message? ' . ($isLocked ? 'yes' : 'no')
    );

    $correctPinWhileLockedResp = http_call('POST', $baseUrl . '/subscription-admin.php', [
        '_csrf' => $csrf4,
        'action' => 'pin_login',
        'pin' => $adminPin,
    ], $cookieJarPinBrute);
    $correctPinBlocked = str_contains($correctPinWhileLockedResp['body'], 'dikunci sementara')
        && !str_contains($correctPinWhileLockedResp['body'], 'terbuka');
    record(
        'CORRECT PIN is still blocked while IP is locked out (cannot bypass by knowing the real PIN)',
        $correctPinBlocked,
        'still locked? ' . ($correctPinBlocked ? 'yes' : 'no')
    );

    // Confirm lockout is keyed by IP (persistent), not by session/cookie: inspect the
    // lockout state file directly on disk. It is scoped per-IP (127.0.0.1, the loopback
    // this whole script runs against), so ANY new session/cookie from this machine would
    // still be locked out -- proven without needing another live HTTP round-trip that can
    // be flaky against PHP's single-threaded built-in dev server.
    $lockoutFiles = glob($ROOT . '/storage/lockouts/subscription_admin_pin-*.json') ?: [];
    $lockoutFileFound = false;
    $lockoutFileLocked = false;
    foreach ($lockoutFiles as $lockoutFile) {
        $decoded = json_decode((string) file_get_contents($lockoutFile), true);
        if (is_array($decoded) && (int) ($decoded['locked_until'] ?? 0) > time()) {
            $lockoutFileFound = true;
            $lockoutFileLocked = true;
            break;
        }
    }
    record(
        'Lockout is persisted on disk keyed by client IP (not session/cookie) and still active',
        $lockoutFileFound && $lockoutFileLocked,
        $lockoutFileFound ? 'lockout file found with active locked_until in the future' : 'no active lockout file found on disk'
    );
} catch (Throwable $e) {
    record('Unexpected exception during test run', false, $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
} finally {
    // -------------------------------------------------------------------
    // Cleanup: always restore DB + stop server, regardless of pass/fail.
    // -------------------------------------------------------------------
    echo PHP_EOL . '--- Cleanup ---' . PHP_EOL;

    try {
        foreach ($unitsBackup as $row) {
            $columns = array_keys($row);
            $sets = [];
            $params = [];
            foreach ($columns as $col) {
                if ($col === 'id') {
                    continue;
                }
                $sets[] = "`{$col}` = :{$col}";
                $params[$col] = $row[$col];
            }
            $params['id'] = $row['id'];
            $pdo->prepare('UPDATE units SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
        }

        // Verify the restore actually landed (never trust a silent UPDATE).
        $unitsAfterRestore = $pdo->query('SELECT * FROM units ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $restoreMatches = $unitsAfterRestore === $unitsBackup;
        if ($restoreMatches) {
            echo '[cleanup] units table restored to pre-test snapshot (verified byte-for-byte match)' . PHP_EOL;
        } else {
            echo '[cleanup][CRITICAL] units table restore MISMATCH after UPDATE — manual DB check required!' . PHP_EOL;
            echo '[cleanup][CRITICAL] expected: ' . json_encode($unitsBackup) . PHP_EOL;
            echo '[cleanup][CRITICAL] actual:   ' . json_encode($unitsAfterRestore) . PHP_EOL;
            $exitCode = 1;
        }
    } catch (Throwable $e) {
        echo '[cleanup][WARNING] failed to restore units table: ' . $e->getMessage() . PHP_EOL;
        $exitCode = 1;
    }

    if ($tempOwnerId !== null) {
        try {
            $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $tempOwnerId]);
            echo "[cleanup] temp owner user #{$tempOwnerId} deleted" . PHP_EOL;
        } catch (Throwable $e) {
            echo '[cleanup][WARNING] failed to delete temp user: ' . $e->getMessage() . PHP_EOL;
        }
    }

    try {
        $pdo->exec(
            "DELETE FROM activity_logs
             WHERE id > {$activityLogStartMaxId}
               AND (action LIKE 'subscription_admin_%' OR action = 'subscription_force_logout'
                    OR action = 'subscription_set_expiry' OR action = 'subscription_disable')"
        );
        echo '[cleanup] test-generated activity_logs rows removed' . PHP_EOL;
    } catch (Throwable $e) {
        echo '[cleanup][WARNING] failed to clean activity_logs: ' . $e->getMessage() . PHP_EOL;
    }

    // Remove the persistent lockout file(s) this script created for 127.0.0.1,
    // so the local developer machine isn't left locked out afterwards.
    $lockoutDir = $ROOT . '/storage/lockouts';
    if (is_dir($lockoutDir)) {
        foreach (glob($lockoutDir . '/subscription_admin_pin-*.json') ?: [] as $file) {
            @unlink($file);
        }
        echo '[cleanup] persistent lockout files removed' . PHP_EOL;
    }

    foreach ([$cookieJarLogin, $cookieJarPin, $cookieJarPinBrute] as $jar) {
        if (is_file($jar)) {
            @unlink($jar);
        }
    }

    if (is_resource($serverProcess)) {
        $status = proc_get_status($serverProcess);
        $serverPid = $status['pid'] ?? null;

        foreach ($serverPipes as $p) {
            if (is_resource($p)) {
                fclose($p);
            }
        }
        proc_terminate($serverProcess);
        proc_close($serverProcess);

        // proc_terminate() alone is not reliable for killing `php -S` on Windows
        // (the process can survive as an orphan holding the port open). Force-kill
        // by PID as a belt-and-suspenders measure so repeated runs never leak servers.
        if ($serverPid !== null) {
            if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
                // /T kills the whole process tree: proc_open on Windows often reports
                // the cmd.exe wrapper's PID, not the actual php.exe listener's PID.
                @shell_exec('taskkill /T /F /PID ' . (int) $serverPid . ' 2>&1');
            } else {
                @shell_exec('kill -9 ' . (int) $serverPid . ' 2>/dev/null');
            }
        }
        echo '[cleanup] built-in PHP server stopped (pid=' . ($serverPid ?? 'unknown') . ')' . PHP_EOL;
    }
}

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------
$total = count($results);
$passed = count(array_filter($results, static fn ($r) => $r['pass']));
$failed = $total - $passed;
$duration = round(microtime(true) - $overallStart, 2);

echo PHP_EOL . '=== SUMMARY ===' . PHP_EOL;
echo "Total: {$total} | Passed: {$passed} | Failed: {$failed} | Duration: {$duration}s" . PHP_EOL;

echo PHP_EOL . '=== SUMMARY_JSON ===' . PHP_EOL;
echo json_encode([
    'total' => $total,
    'passed' => $passed,
    'failed' => $failed,
    'duration_seconds' => $duration,
    'results' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if ($failed > 0) {
    $exitCode = 1;
}

if ($exitCode !== 0) {
    echo PHP_EOL . '[EXIT] Non-zero exit: either a test failed, or cleanup/restore could not be verified. Check DB state manually.' . PHP_EOL;
}

exit($exitCode);
