<?php

final class Auth
{
    private const REMEMBER_COOKIE = 'sigaji_remember';
    private const NOTICE_COOKIE = 'sigaji_auth_notice';
    private const REMEMBER_DAYS = 365;
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;
        self::restoreFromRememberCookie();
        self::validateActiveSession();
    }

    public static function attempt(string $login, string $password, int $unitId): bool
    {
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'name';
        $user = fetch_one("SELECT * FROM users WHERE {$field} = :login LIMIT 1", [
            'login' => $login,
        ]);

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        if (($user['role'] ?? 'karyawan') !== 'owner' && (int) $user['unit_id'] !== $unitId) {
            return false;
        }

        if (($user['role'] ?? 'karyawan') === 'owner' && (int) $user['unit_id'] !== $unitId) {
            execute_query('UPDATE users SET unit_id = :unit_id, updated_at = :updated_at WHERE id = :id', [
                'unit_id' => $unitId,
                'updated_at' => now_string(),
                'id' => $user['id'],
            ]);
            $user['unit_id'] = $unitId;
        }

        $sessionToken = bin2hex(random_bytes(32));
        execute_query('UPDATE users SET session_login_token = :session_login_token, updated_at = :updated_at WHERE id = :id', [
            'session_login_token' => $sessionToken,
            'updated_at' => now_string(),
            'id' => $user['id'],
        ]);

        session_regenerate_id(true);
        $_SESSION['auth_user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'unit_id' => (int) $user['unit_id'],
            'session_login_token' => $sessionToken,
        ];
        self::setRememberCookie((int) $user['id'], $sessionToken);
        ActivityLogService::log(
            'login',
            'User berhasil login.',
            [
                'login' => $login,
                'unit_id' => (int) $user['unit_id'],
            ],
            (int) $user['id'],
            (int) $user['unit_id'],
            'user',
            (string) $user['id']
        );

        return true;
    }

    public static function check(): bool
    {
        self::boot();
        return !empty($_SESSION['auth_user']);
    }

    public static function user(): ?array
    {
        self::boot();
        return $_SESSION['auth_user'] ?? null;
    }

    public static function id(): ?int
    {
        return self::user()['id'] ?? null;
    }

    public static function unitId(): ?int
    {
        return self::user()['unit_id'] ?? null;
    }

    public static function require(): array
    {
        if (!self::check()) {
            if (expects_json()) {
                json_response([
                    'success' => false,
                    'message' => 'Sesi login berakhir. Silakan masuk ulang.',
                    'unauthenticated' => true,
                    'redirect' => 'index.php',
                ], 401);
            }
            redirect_to('index.php');
        }

        return self::user();
    }

    public static function logout(): void
    {
        $userId = (int) ($_SESSION['auth_user']['id'] ?? 0);
        $sessionToken = (string) ($_SESSION['auth_user']['session_login_token'] ?? '');

        if ($userId > 0 && $sessionToken !== '') {
            $record = fetch_one('SELECT session_login_token FROM users WHERE id = :id LIMIT 1', ['id' => $userId]);
            if (($record['session_login_token'] ?? '') === $sessionToken) {
                execute_query('UPDATE users SET session_login_token = NULL, updated_at = :updated_at WHERE id = :id', [
                    'updated_at' => now_string(),
                    'id' => $userId,
                ]);
                ActivityLogService::log(
                    'logout',
                    'User logout manual.',
                    [],
                    $userId,
                    (int) ($_SESSION['auth_user']['unit_id'] ?? 0) ?: null,
                    'user',
                    (string) $userId
                );
            }
        }

        unset($_SESSION['auth_user']);
        self::clearRememberCookie();
        session_regenerate_id(true);
    }

    public static function consumeNotice(): string
    {
        $message = trim((string) ($_COOKIE[self::NOTICE_COOKIE] ?? ''));
        if ($message !== '') {
            self::expireCookie(self::NOTICE_COOKIE);
        }
        return $message;
    }

    private static function restoreFromRememberCookie(): void
    {
        if (!empty($_SESSION['auth_user'])) {
            return;
        }

        $raw = (string) ($_COOKIE[self::REMEMBER_COOKIE] ?? '');
        if ($raw === '' || !str_contains($raw, '|')) {
            return;
        }

        [$userId, $token] = explode('|', $raw, 2);
        $userId = (int) $userId;
        $token = trim($token);
        if ($userId <= 0 || $token === '') {
            self::clearRememberCookie();
            return;
        }

        $user = fetch_one(
            'SELECT id, name, email, role, unit_id, session_login_token
             FROM users
             WHERE id = :id
             LIMIT 1',
            ['id' => $userId]
        );

        if (!$user || !hash_equals((string) ($user['session_login_token'] ?? ''), $token)) {
            self::clearRememberCookie();
            return;
        }

        $_SESSION['auth_user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'unit_id' => (int) $user['unit_id'],
            'session_login_token' => $token,
        ];
    }

    private static function validateActiveSession(): void
    {
        $auth = $_SESSION['auth_user'] ?? null;
        if (!$auth || empty($auth['id']) || empty($auth['session_login_token'])) {
            return;
        }

        $record = fetch_one(
            'SELECT session_login_token
             FROM users
             WHERE id = :id
             LIMIT 1',
            ['id' => (int) $auth['id']]
        );

        $currentToken = (string) ($record['session_login_token'] ?? '');
        $sessionToken = (string) $auth['session_login_token'];
        if ($currentToken !== '' && hash_equals($currentToken, $sessionToken)) {
            return;
        }

        unset($_SESSION['auth_user']);
        self::clearRememberCookie();
        self::setNotice('Anda telah logout karena ada login di device berbeda.');
        ActivityLogService::log(
            'forced_logout',
            'Session logout karena login di device berbeda.',
            [],
            (int) ($auth['id'] ?? 0) ?: null,
            (int) ($auth['unit_id'] ?? 0) ?: null,
            'user',
            (string) ($auth['id'] ?? '')
        );
    }

    private static function setRememberCookie(int $userId, string $token): void
    {
        self::setCookie(self::REMEMBER_COOKIE, $userId . '|' . $token, time() + (86400 * self::REMEMBER_DAYS));
    }

    private static function clearRememberCookie(): void
    {
        self::expireCookie(self::REMEMBER_COOKIE);
    }

    private static function setNotice(string $message): void
    {
        self::setCookie(self::NOTICE_COOKIE, $message, time() + 300);
    }

    private static function expireCookie(string $name): void
    {
        self::setCookie($name, '', time() - 3600);
    }

    private static function setCookie(string $name, string $value, int $expires): void
    {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie($name, $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
