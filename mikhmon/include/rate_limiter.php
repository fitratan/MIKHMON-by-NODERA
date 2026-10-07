<?php
/**
 * MIKHMON Engine — IP & Session Rate Limiter
 * by NODERA (nodera.id)
 * 
 * Provides lightweight, atomic sliding-window rate limiting
 * to prevent DoS, brute-force enumeration, and spam bot attacks.
 */

if (!defined('MIKHMON_RATE_LIMITER_LOADED')) {
    define('MIKHMON_RATE_LIMITER_LOADED', true);
}

if (!function_exists('mikhmon_get_client_ip')) {
    /**
     * Get real client IP address safely, respecting Cloudflare & trusted proxies.
     */
    function mikhmon_get_client_ip() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Check Cloudflare header
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $cfIp = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
            if (filter_var($cfIp, FILTER_VALIDATE_IP)) {
                return $cfIp;
            }
        }

        // Check Forwarded-For header
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $first = trim($parts[0] ?? '');
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }

        // Check Real-IP header
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $realIp = trim($_SERVER['HTTP_X_REAL_IP']);
            if (filter_var($realIp, FILTER_VALIDATE_IP)) {
                return $realIp;
            }
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
    }
}

if (!function_exists('mikhmon_check_rate_limit')) {
    /**
     * Check if request exceeds rate limit within sliding time window.
     * 
     * @param string $action Action name (e.g. 'create_order', 'check_status')
     * @param int $maxRequests Maximum allowed requests in window
     * @param int $windowSeconds Sliding window in seconds
     * @param string|null $identifier Optional custom identifier (defaults to client IP)
     * @return bool True if allowed, False if limit exceeded
     */
    function mikhmon_check_rate_limit($action, $maxRequests = 10, $windowSeconds = 60, $identifier = null) {
        $ip = $identifier ?: mikhmon_get_client_ip();
        $now = time();

        // 1. Storage directory resolution
        $currentDir = __DIR__;
        $configDir = dirname($currentDir) . '/config';
        if (!file_exists($configDir) && file_exists($currentDir . '/../config')) {
            $configDir = $currentDir . '/../config';
        }
        $limitDir = $configDir . '/.rate_limits';

        $canUseFiles = false;
        if (is_dir($configDir) && is_writable($configDir)) {
            if (!is_dir($limitDir)) {
                @mkdir($limitDir, 0755, true);
                @file_put_contents($limitDir . '/.htaccess', "Deny from all\n", LOCK_EX);
                @file_put_contents($limitDir . '/index.html', "", LOCK_EX);
            }
            if (is_dir($limitDir) && is_writable($limitDir)) {
                $canUseFiles = true;
            }
        }

        // 2. File-based atomic sliding window
        if ($canUseFiles) {
            $safeKey = md5($action . '_' . $ip);
            $limitFile = $limitDir . '/' . $safeKey . '.json';

            $timestamps = [];
            if (file_exists($limitFile)) {
                $raw = @file_get_contents($limitFile);
                if (!empty($raw)) {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $timestamps = $decoded;
                    }
                }
            }

            // Filter out expired timestamps
            $cutoff = $now - $windowSeconds;
            $validTimestamps = [];
            foreach ($timestamps as $ts) {
                if ($ts > $cutoff) {
                    $validTimestamps[] = (int)$ts;
                }
            }

            if (count($validTimestamps) >= $maxRequests) {
                return false;
            }

            // Append current timestamp and save
            $validTimestamps[] = $now;
            @file_put_contents($limitFile, json_encode($validTimestamps), LOCK_EX);

            // Probabilistic garbage collection (1 in 50 requests)
            if (mt_rand(1, 50) === 1) {
                mikhmon_gc_rate_limits($limitDir, $windowSeconds * 2);
            }

            return true;
        }

        // 3. Session-based fallback if filesystem is not writable
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (!isset($_SESSION['_rate_limits'])) {
            $_SESSION['_rate_limits'] = [];
        }

        $sessionTimestamps = $_SESSION['_rate_limits'][$action] ?? [];
        $cutoff = $now - $windowSeconds;
        $validSession = [];
        foreach ($sessionTimestamps as $ts) {
            if ($ts > $cutoff) {
                $validSession[] = (int)$ts;
            }
        }

        if (count($validSession) >= $maxRequests) {
            return false;
        }

        $validSession[] = $now;
        $_SESSION['_rate_limits'][$action] = $validSession;
        return true;
    }
}

if (!function_exists('mikhmon_gc_rate_limits')) {
    /**
     * Garbage collector for expired rate limit cache files.
     */
    function mikhmon_gc_rate_limits($limitDir, $maxAge = 300) {
        if (!is_dir($limitDir)) return;
        $files = @glob($limitDir . '/*.json');
        if (empty($files)) return;

        $now = time();
        foreach ($files as $file) {
            if (file_exists($file)) {
                $mtime = @filemtime($file);
                if ($mtime && ($now - $mtime) > $maxAge) {
                    @unlink($file);
                }
            }
        }
    }
}

if (!function_exists('mikhmon_enforce_rate_limit')) {
    /**
     * Enforce rate limit, sending HTTP 429 JSON response on violation.
     */
    function mikhmon_enforce_rate_limit($action, $maxRequests = 10, $windowSeconds = 60) {
        if (!mikhmon_check_rate_limit($action, $maxRequests, $windowSeconds)) {
            http_response_code(429);
            header('Content-Type: application/json; charset=utf-8');
            header('Retry-After: ' . $windowSeconds);
            echo json_encode([
                'success' => false,
                'status'  => 'rate_limited',
                'message' => 'Terlalu banyak permintaan (Rate limit exceeded). Silakan tunggu beberapa saat sebelum mencoba kembali.'
            ]);
            exit;
        }
    }
}
