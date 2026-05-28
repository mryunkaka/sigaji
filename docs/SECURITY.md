# Security Configuration Guide

This document explains the security features implemented in SIGAJI and how to configure them.

## Overview

SIGAJI includes comprehensive security measures to protect against common web attacks:
- **Anti-DDoS Protection**: Rate limiting and request size restrictions
- **Anti-Brute Force Protection**: Login attempt tracking and account lockout
- **Anti-Inurl/Dork Protection**: SQL injection and XSS pattern blocking
- **IP Whitelisting**: Control access based on IP addresses
- **Security Headers**: HTTP security headers for XSS, clickjacking, etc.

## Configuration

### 1. Environment Variables (.env)

Copy `.env.example` to `.env` and configure the following settings:

```bash
# Application Settings
APP_NAME=SIGAJI Native
APP_URL=http://localhost:8082/sigaji
APP_ENV=local
APP_DEBUG=false
APP_KEY=generate-32-character-random-key-here

# Database Settings
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=hark8423_gaji
DB_USER=root
DB_PASS=your_password_here

# Security Settings
# Rate limiting (requests per minute)
RATE_LIMIT_ENABLED=true
RATE_LIMIT_REQUESTS=60
RATE_LIMIT_WINDOW=60

# Brute Force Protection
BRUTE_FORCE_ENABLED=true
BRUTE_FORCE_MAX_ATTEMPTS=5
BRUTE_FORCE_LOCKOUT_TIME=900
BRUTE_FORCE_DECAY_TIME=300

# Session Security
SESSION_LIFETIME=7200
SESSION_HTTP_ONLY=true
SESSION_SECURE_ONLY=false
SESSION_SAME_SITE=Lax

# CSRF Protection
CSRF_ENABLED=true
CSRF_TOKEN_NAME=csrf_token

# Allowed IPs for local access (comma separated)
# For C:\Server access, add the server IP or use CIDR notation
ALLOWED_IPS=127.0.0.1,::1,192.168.1.0/24,10.0.0.0/8

# Trusted Proxies (comma separated)
TRUSTED_PROXIES=

# Security Headers
SECURITY_FRAME_OPTIONS=SAMEORIGIN
SECURITY_XSS_PROTECTION=1; mode=block
SECURITY_CONTENT_TYPE_NOSNIFF=true
SECURITY_REFERRER_POLICY=strict-origin-when-cross-origin
```

### 2. Local Server Access (C:\Server)

To allow access from C:\Server, configure the `ALLOWED_IPS` setting:

**Option 1: Allow specific IP**
```bash
ALLOWED_IPS=127.0.0.1,::1,192.168.1.100
```

**Option 2: Allow entire local network (CIDR)**
```bash
ALLOWED_IPS=127.0.0.1,::1,192.168.1.0/24
```

**Option 3: Allow all private networks**
```bash
ALLOWED_IPS=127.0.0.1,::1,192.168.0.0/16,10.0.0.0/8,172.16.0.0/12
```

### 3. Security Features

#### Anti-DDoS Protection
- **Rate Limiting**: Limits requests per IP to prevent flooding
- **Request Size Limit**: Maximum 10MB per request
- **User Agent Blocking**: Blocks known malicious bots and scanners
- **Empty User Agent Blocking**: Rejects requests without user agent

#### Anti-Brute Force Protection
- **Attempt Tracking**: Tracks failed login attempts per IP and username
- **Account Lockout**: Temporarily locks after max failed attempts
- **Decay Mechanism**: Attempt count decreases over time
- **Configuration**: 
  - `BRUTE_FORCE_MAX_ATTEMPTS`: Maximum failed attempts (default: 5)
  - `BRUTE_FORCE_LOCKOUT_TIME`: Lockout duration in seconds (default: 900 = 15 min)
  - `BRUTE_FORCE_DECAY_TIME`: Time before attempts decay (default: 300 = 5 min)

#### Anti-Inurl/Dork Protection
- **SQL Injection Blocking**: Blocks common SQL injection patterns
- **XSS Protection**: Blocks cross-site scripting attempts
- **Directory Traversal**: Blocks path traversal attacks
- **File Upload Protection**: Blocks access to sensitive file types
- **Query String Filtering**: Blocks malicious query parameters

#### IP Whitelisting
- **Local Environment**: Automatically allows all local IPs in `APP_ENV=local`
- **CIDR Support**: Supports CIDR notation for IP ranges
- **IPv4 and IPv6**: Full support for both IP versions

#### Security Headers
- **X-Frame-Options**: Prevents clickjacking
- **X-XSS-Protection**: Enables browser XSS filter
- **X-Content-Type-Options**: Prevents MIME sniffing
- **Referrer-Policy**: Controls referrer information
- **Content-Security-Policy**: Restricts resource loading
- **Permissions-Policy**: Controls browser features

## Security Logs

Security events are logged to `storage/logs/security.log`:
- Rate limit violations
- Brute force attempts
- SQL injection attempts
- XSS attempts
- Failed login attempts

Monitor this log regularly for suspicious activity.

## Best Practices

1. **Generate a strong APP_KEY**: Use a random 32-character string
2. **Set APP_DEBUG=false** in production
3. **Use strong database passwords**
4. **Regularly review security logs**
5. **Keep dependencies updated**
6. **Use HTTPS in production** (set `SESSION_SECURE_ONLY=true`)
7. **Restrict ALLOWED_IPS** to known IPs only
8. **Regularly backup database and storage**

## Testing Security

To test security features:

1. **Rate Limiting**: Make rapid requests to exceed limit
2. **Brute Force**: Attempt multiple failed logins
3. **SQL Injection**: Try SQL injection patterns in inputs
4. **XSS**: Try XSS payloads in inputs
5. **IP Blocking**: Access from blocked IP

All attempts should be logged and blocked.

## Troubleshooting

### Locked out due to brute force?
- Wait for lockout period to expire (default 15 minutes)
- Or clear session data in `storage/sessions/`

### Rate limit exceeded?
- Wait for rate limit window to expire (default 60 seconds)
- Or adjust `RATE_LIMIT_REQUESTS` in .env

### IP blocked?
- Check `ALLOWED_IPS` in .env
- Verify your IP address
- Add your IP to the allowed list

## Additional Security Measures

For production deployment, consider:

1. **Web Application Firewall (WAF)**: ModSecurity or Cloudflare
2. **DDoS Protection Service**: Cloudflare, AWS Shield
3. **SSL/TLS Certificate**: Enable HTTPS
4. **Regular Security Audits**: Penetration testing
5. **Backup Strategy**: Regular automated backups
6. **Monitoring**: Real-time security monitoring
7. **Fail2Ban**: Additional brute force protection at server level

## Support

For security issues or questions, review the security logs and configuration settings.
