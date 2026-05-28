<?php

/**
 * Security Service
 * Handles rate limiting, brute force protection, IP whitelisting, and security checks
 */

class SecurityService
{
    private static ?SecurityService $instance = null;
    private string $clientIp;
    private array $allowedIps;
    private bool $rateLimitEnabled;
    private int $rateLimitRequests;
    private int $rateLimitWindow;
    private bool $bruteForceEnabled;
    private int $bruteForceMaxAttempts;
    private int $bruteForceLockoutTime;
    private int $bruteForceDecayTime;

    private function __construct()
    {
        $this->clientIp = $this->getClientIp();
        $this->allowedIps = $this->parseAllowedIps();
        $this->rateLimitEnabled = env('RATE_LIMIT_ENABLED', 'true') === 'true';
        $this->rateLimitRequests = (int) env('RATE_LIMIT_REQUESTS', '60');
        $this->rateLimitWindow = (int) env('RATE_LIMIT_WINDOW', '60');
        $this->bruteForceEnabled = env('BRUTE_FORCE_ENABLED', 'true') === 'true';
        $this->bruteForceMaxAttempts = (int) env('BRUTE_FORCE_MAX_ATTEMPTS', '5');
        $this->bruteForceLockoutTime = (int) env('BRUTE_FORCE_LOCKOUT_TIME', '900');
        $this->bruteForceDecayTime = (int) env('BRUTE_FORCE_DECAY_TIME', '300');
    }

    public static function getInstance(): SecurityService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get client IP address
     */
    private function getClientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        // Check for proxy headers
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'HTTP_X_REAL_IP'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                break;
            }
        }
        
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
    }

    /**
     * Parse allowed IPs from environment
     */
    private function parseAllowedIps(): array
    {
        $allowed = env('ALLOWED_IPS', '127.0.0.1,::1,192.168.1.0/24,10.0.0.0/8');
        return array_map('trim', explode(',', $allowed));
    }

    /**
     * Check if IP is allowed
     */
    public function isIpAllowed(): bool
    {
        // If in local environment, allow all local IPs
        if (env('APP_ENV', 'local') === 'local') {
            if ($this->isLocalIp($this->clientIp)) {
                return true;
            }
        }
        
        // Check exact match
        if (in_array($this->clientIp, $this->allowedIps, true)) {
            return true;
        }
        
        // Check CIDR ranges
        foreach ($this->allowedIps as $allowed) {
            if (str_contains($allowed, '/')) {
                if ($this->isIpInCidr($this->clientIp, $allowed)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Check if IP is local
     */
    private function isLocalIp(string $ip): bool
    {
        return in_array($ip, ['127.0.0.1', '::1', 'localhost'], true) ||
               str_starts_with($ip, '192.168.') ||
               str_starts_with($ip, '10.') ||
               str_starts_with($ip, '172.16.');
    }

    /**
     * Check if IP is in CIDR range
     */
    private function isIpInCidr(string $ip, string $cidr): bool
    {
        if (str_contains($cidr, ':')) {
            // IPv6 CIDR
            return $this->isIpInCidrV6($ip, $cidr);
        }
        
        // IPv4 CIDR
        list($network, $mask) = explode('/', $cidr);
        $ipLong = ip2long($ip);
        $networkLong = ip2long($network);
        $maskLong = -1 << (32 - (int) $mask);
        
        return ($ipLong & $maskLong) === ($networkLong & $maskLong);
    }

    /**
     * Check if IPv6 is in CIDR range
     */
    private function isIpInCidrV6(string $ip, string $cidr): bool
    {
        // Simplified IPv6 CIDR check
        // For full implementation, use inet_pton and bitwise operations
        list($network, $mask) = explode('/', $cidr);
        $ipBin = inet_pton($ip);
        $networkBin = inet_pton($network);
        
        if ($ipBin === false || $networkBin === false) {
            return false;
        }
        
        $maskInt = (int) $mask;
        $maskBytes = (int) floor($maskInt / 8);
        $maskBits = $maskInt % 8;
        
        for ($i = 0; $i < $maskBytes; $i++) {
            if ($ipBin[$i] !== $networkBin[$i]) {
                return false;
            }
        }
        
        if ($maskBits > 0) {
            $maskByte = 0xFF << (int) (8 - $maskBits);
            if ((ord($ipBin[$maskBytes]) & $maskByte) !== (ord($networkBin[$maskBytes]) & $maskByte)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Check rate limit
     */
    public function checkRateLimit(): bool
    {
        if (!$this->rateLimitEnabled) {
            return true;
        }

        $key = 'rate_limit_' . md5($this->clientIp);
        $current = $_SESSION[$key] ?? ['count' => 0, 'time' => time()];
        
        // Reset if window expired
        if (time() - $current['time'] > $this->rateLimitWindow) {
            $current = ['count' => 0, 'time' => time()];
        }
        
        $current['count']++;
        $_SESSION[$key] = $current;
        
        if ($current['count'] > $this->rateLimitRequests) {
            $this->logSecurityEvent('rate_limit_exceeded');
            return false;
        }
        
        return true;
    }

    /**
     * Check brute force attempts
     */
    public function checkBruteForce(string $identifier = 'login'): bool
    {
        if (!$this->bruteForceEnabled) {
            return true;
        }

        $key = 'brute_force_' . md5($this->clientIp . '_' . $identifier);
        $attempts = $_SESSION[$key] ?? ['count' => 0, 'time' => time()];
        
        // Decay attempts over time
        $timeDiff = time() - $attempts['time'];
        if ($timeDiff > $this->bruteForceDecayTime) {
            $decay = floor($timeDiff / $this->bruteForceDecayTime);
            $attempts['count'] = max(0, $attempts['count'] - $decay);
            $attempts['time'] = time();
        }
        
        // Check if locked out
        if ($attempts['count'] >= $this->bruteForceMaxAttempts) {
            $lockoutEnd = $attempts['time'] + $this->bruteForceLockoutTime;
            if (time() < $lockoutEnd) {
                $this->logSecurityEvent('brute_force_locked', $identifier);
                return false;
            }
            // Reset after lockout period
            $attempts = ['count' => 0, 'time' => time()];
        }
        
        $_SESSION[$key] = $attempts;
        return true;
    }

    /**
     * Record failed attempt
     */
    public function recordFailedAttempt(string $identifier = 'login'): void
    {
        if (!$this->bruteForceEnabled) {
            return;
        }

        $key = 'brute_force_' . md5($this->clientIp . '_' . $identifier);
        $attempts = $_SESSION[$key] ?? ['count' => 0, 'time' => time()];
        $attempts['count']++;
        $attempts['time'] = time();
        $_SESSION[$key] = $attempts;
        
        $this->logSecurityEvent('failed_attempt', $identifier);
    }

    /**
     * Reset brute force attempts on successful login
     */
    public function resetBruteForce(string $identifier = 'login'): void
    {
        $key = 'brute_force_' . md5($this->clientIp . '_' . $identifier);
        unset($_SESSION[$key]);
    }

    /**
     * Validate input against common attack patterns
     */
    public function sanitizeInput(string $input): string
    {
        // Remove null bytes
        $input = str_replace("\0", '', $input);
        
        // Check for SQL injection patterns
        $patterns = [
            '/union\s+select/i',
            '/select\s+.*\s+from/i',
            '/insert\s+into/i',
            '/delete\s+from/i',
            '/update\s+.*\s+set/i',
            '/drop\s+table/i',
            '/create\s+table/i',
            '/alter\s+table/i',
            '/exec\s*\(/i',
            '/eval\s*\(/i',
            '/system\s*\(/i',
            '/shell_exec\s*\(/i',
            '/passthru\s*\(/i',
            '/base64_decode\s*\(/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                $this->logSecurityEvent('sql_injection_attempt');
                return '';
            }
        }
        
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Check for XSS in input
     */
    public function hasXSS(string $input): bool
    {
        $xssPatterns = [
            '/<script[^>]*>.*?<\/script>/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i',
            '/document\.cookie/i',
            '/window\.location/i',
        ];
        
        foreach ($xssPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                $this->logSecurityEvent('xss_attempt');
                return true;
            }
        }
        
        return false;
    }

    /**
     * Log security events
     */
    private function logSecurityEvent(string $event, string $details = ''): void
    {
        $logFile = __DIR__ . '/../storage/logs/security.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $message = sprintf(
            "[%s] IP: %s | Event: %s | Details: %s | UA: %s\n",
            $timestamp,
            $this->clientIp,
            $event,
            $details,
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        );
        
        file_put_contents($logFile, $message, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get security headers
     */
    public static function getSecurityHeaders(): array
    {
        return [
            'X-Frame-Options' => env('SECURITY_FRAME_OPTIONS', 'SAMEORIGIN'),
            'X-XSS-Protection' => env('SECURITY_XSS_PROTECTION', '1; mode=block'),
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
        ];
    }

    /**
     * Apply security headers
     */
    public static function applySecurityHeaders(): void
    {
        foreach (self::getSecurityHeaders() as $header => $value) {
            header("$header: $value");
        }
        
        // Remove server signature
        header_remove('Server');
        header_remove('X-Powered-By');
    }

    /**
     * Validate CSRF token
     */
    public static function validateCsrf(): bool
    {
        if (env('CSRF_ENABLED', 'true') !== 'true') {
            return true;
        }
        
        $tokenName = env('CSRF_TOKEN_NAME', 'csrf_token');
        $headers = getallheaders();
        $headerToken = $headers['X-CSRF-Token'] ?? $headers['X-CSRF-TOKEN'] ?? '';
        $postToken = $_POST[$tokenName] ?? '';
        
        $token = $headerToken ?: $postToken;
        
        return $token === csrf_token();
    }
}
