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

function format_date_id(?string $value, bool $withTime = false, bool $withDayName = false): string
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
    $days = [
        'Sunday' => 'Minggu',
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
    ];

    $day = date('j', $timestamp);
    $month = $months[(int) date('n', $timestamp)] ?? date('F', $timestamp);
    $year = date('Y', $timestamp);
    $dayName = $days[date('l', $timestamp)] ?? date('l', $timestamp);
    $dateText = $withDayName ? ($dayName . ', ' . $day . ' ' . $month . ' ' . $year) : ($day . ' ' . $month . ' ' . $year);

    if ($withTime) {
        return $dateText . ' ' . date('H:i', $timestamp);
    }

    return $dateText;
}

function closing_period_range(?DateTimeInterface $reference = null): array
{
    $base = $reference;
    if (!$base instanceof DateTimeInterface) {
        $base = new DateTimeImmutable('today');
    } elseif (!$base instanceof DateTimeImmutable) {
        $base = DateTimeImmutable::createFromInterface($base);
    }

    $closingEndBase = ((int) $base->format('d') >= 26)
        ? $base->modify('first day of this month')
        : $base->modify('first day of last month');

    $end = $closingEndBase->setDate(
        (int) $closingEndBase->format('Y'),
        (int) $closingEndBase->format('m'),
        25
    );

    $closingStartBase = $end->modify('first day of last month');
    $start = $closingStartBase->setDate(
        (int) $closingStartBase->format('Y'),
        (int) $closingStartBase->format('m'),
        26
    );

    return [
        'start' => $start->format('Y-m-d'),
        'end' => $end->format('Y-m-d'),
    ];
}

function terbilang_id(int $value): string
{
    $value = abs($value);
    $words = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

    if ($value < 12) {
        return $words[$value];
    }

    if ($value < 20) {
        return terbilang_id($value - 10) . ' belas';
    }

    if ($value < 100) {
        return trim(terbilang_id((int) floor($value / 10)) . ' puluh ' . terbilang_id($value % 10));
    }

    if ($value < 200) {
        return trim('seratus ' . terbilang_id($value - 100));
    }

    if ($value < 1000) {
        return trim(terbilang_id((int) floor($value / 100)) . ' ratus ' . terbilang_id($value % 100));
    }

    if ($value < 2000) {
        return trim('seribu ' . terbilang_id($value - 1000));
    }

    if ($value < 1000000) {
        return trim(terbilang_id((int) floor($value / 1000)) . ' ribu ' . terbilang_id($value % 1000));
    }

    if ($value < 1000000000) {
        return trim(terbilang_id((int) floor($value / 1000000)) . ' juta ' . terbilang_id($value % 1000000));
    }

    if ($value < 1000000000000) {
        return trim(terbilang_id((int) floor($value / 1000000000)) . ' miliar ' . terbilang_id($value % 1000000000));
    }

    return trim(terbilang_id((int) floor($value / 1000000000000)) . ' triliun ' . terbilang_id($value % 1000000000000));
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
    $sessionToken = (string) ($_SESSION['_csrf'] ?? '');
    $tokens = [];

    foreach ([$_POST['_csrf'] ?? null, $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null] as $candidate) {
        if (is_string($candidate) && $candidate !== '') {
            $tokens[] = $candidate;
        }
    }

    $isValid = $sessionToken !== '';
    if ($isValid) {
        $isValid = false;
        foreach ($tokens as $token) {
            if (hash_equals($sessionToken, $token)) {
                $isValid = true;
                break;
            }
        }
    }

    if ($isValid) {
        return;
    }

    if (expects_json()) {
        json_response([
            'success' => false,
            'message' => 'Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.',
        ], 419);
    }

    http_response_code(419);
    exit('CSRF token mismatch.');
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

function request_json_value(string $key, array $default = []): array
{
    $raw = request_value($key, '');
    if (!is_string($raw) || trim($raw) === '') {
        return $default;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : $default;
}

function request_int_list(string $key): array
{
    $raw = (string) request_value($key, '');
    if ($raw === '') {
        return [];
    }

    return array_values(array_unique(array_filter(array_map('intval', explode(',', $raw)))));
}

function expects_json(): bool
{
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $requestUri = str_replace('\\', '/', strtolower((string) ($_SERVER['REQUEST_URI'] ?? '')));

    return str_contains($accept, 'application/json')
        || $requestedWith === 'xmlhttprequest'
        || str_contains($requestUri, '/ajax/');
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

function public_asset_path(?string $path): ?string
{
    $path = trim((string) $path);
    if ($path === '') {
        return null;
    }

    $normalized = str_replace('\\', '/', $path);
    $candidates = [
        __DIR__ . '/../public/' . ltrim($normalized, '/'),
        __DIR__ . '/../public/storage/' . ltrim($normalized, '/'),
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}
