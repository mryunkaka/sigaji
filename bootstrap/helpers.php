<?php

function load_env(string $path): void
{
    static $loaded = false;

    if ($loaded || !is_file($path)) {
        return;
    }

    $loaded = true;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ($value !== '' && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function env(string $key, ?string $default = null): ?string
{
    return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money($value): string
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

function format_date_id(?string $value, bool $withTime = false): string
{
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return (string) $value;
    }

    $months = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    $day = date('j', $timestamp);
    $month = $months[(int) date('n', $timestamp)] ?? date('F', $timestamp);
    $year = date('Y', $timestamp);

    if ($withTime) {
        return $day . ' ' . $month . ' ' . $year . ' ' . date('H:i', $timestamp);
    }

    return $day . ' ' . $month . ' ' . $year;
}

function now_string(): string
{
    return date('Y-m-d H:i:s');
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(24));
    }

    return $_SESSION['_csrf'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$token || !hash_equals($_SESSION['_csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('CSRF token mismatch.');
    }
}

function redirect_to(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function is_post(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function request_value(string $key, $default = null)
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/../config/database.php';
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['name'],
        $config['charset']
    );

    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function fetch_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetch_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function execute_query(string $sql, array $params = []): bool
{
    $stmt = db()->prepare($sql);
    return $stmt->execute($params);
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ensure_storage_path(string $relativePath): string
{
    $full = __DIR__ . '/../storage/' . ltrim($relativePath, '/');
    $dir = dirname($full);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $full;
}

function option_html(array $options, $selected = null): string
{
    $html = '';
    foreach ($options as $value => $label) {
        $isSelected = (string) $value === (string) $selected ? ' selected' : '';
        $html .= '<option value="' . e((string) $value) . '"' . $isSelected . '>' . e((string) $label) . '</option>';
    }
    return $html;
}

function asset_url(string $path): string
{
    $path = ltrim($path, '/');
    $fullPath = __DIR__ . '/../public/' . $path;
    $version = is_file($fullPath) ? filemtime($fullPath) : time();
    return $path . '?v=' . $version;
}
