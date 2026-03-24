<?php

final class Auth
{
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

        $_SESSION['auth_user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'unit_id' => (int) $user['unit_id'],
        ];

        return true;
    }

    public static function check(): bool
    {
        return !empty($_SESSION['auth_user']);
    }

    public static function user(): ?array
    {
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
            redirect_to('index.php');
        }

        return self::user();
    }

    public static function logout(): void
    {
        unset($_SESSION['auth_user']);
    }
}
