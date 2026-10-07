<?php
/**
 * NODERA MIKHMON - Unified Multi-Tenant Session Initializer
 * Ensures consistent session naming (MIKH_<hash>) and cookie path across all endpoints
 * (root, admin, process, hotspot, status, voucher, dashboard, report, settings, etc.)
 */
if (!function_exists('mikhmon_init_session')) {
    function mikhmon_init_session() {
        // Mikhmon root directory is always the parent directory of include/
        $includeDir = __DIR__;
        $mikhRoot = dirname($includeDir);
        $instanceHash = substr(md5($mikhRoot), 0, 10);

        if (session_status() === PHP_SESSION_ACTIVE) {
            if (!isset($_SESSION["mikhmon"]) && !empty($_COOKIE['mikhmon_remember'])) {
                mikhmon_restore_remember_session();
            }
            return;
        }

        @session_name('MIKH_' . $instanceHash);

        // Calculate base cookie path relative to web root
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $scriptDir = rtrim(dirname($scriptName), '/');
        $subdirs = ['/process', '/status', '/hotspot', '/voucher', '/dashboard', '/report', '/system', '/settings', '/traffic', '/dhcp', '/include', '/bandwidth', '/ppp'];
        $baseCookiePath = $scriptDir;
        foreach ($subdirs as $sd) {
            if (str_ends_with($baseCookiePath, $sd)) {
                $baseCookiePath = substr($baseCookiePath, 0, -strlen($sd));
                break;
            }
        }
        $cookiePath = !empty($baseCookiePath) ? $baseCookiePath : '/';

        @ini_set('session.cookie_path', $cookiePath);
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                    (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) || 
                    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        if (PHP_VERSION_ID >= 70300) {
            @ini_set('session.cookie_samesite', 'Lax');
        }
        @ini_set('session.cookie_httponly', '1');
        if ($isSecure) {
            @ini_set('session.cookie_secure', '1');
        }
        @session_start();

        // Restore remember-me session if session variable was lost or expired
        if (!isset($_SESSION["mikhmon"]) && !empty($_COOKIE['mikhmon_remember'])) {
            mikhmon_restore_remember_session();
        }
    }
}

if (!function_exists('mikhmon_restore_remember_session')) {
    function mikhmon_restore_remember_session() {
        if (empty($_COOKIE['mikhmon_remember'])) {
            return;
        }
        $mikhRoot = dirname(__DIR__);
        $cfgFile = $mikhRoot . '/include/config.php';
        $readCfgFile = $mikhRoot . '/include/readcfg.php';
        if (file_exists($cfgFile) && file_exists($readCfgFile)) {
            @include_once($cfgFile);
            @include_once($readCfgFile);
            $decodedRemember = @base64_decode($_COOKIE['mikhmon_remember']);
            if ($decodedRemember && strpos($decodedRemember, '|') !== false) {
                list($rUser, $rHash) = explode('|', $decodedRemember, 2);
                $expectedHash = hash('sha256', ($useradm ?? '') . ':' . ($passadm ?? '') . ':mikhmon_nodera_auth');
                if (!empty($useradm) && $rUser === $useradm && hash_equals($expectedHash, $rHash)) {
                    if (!(function_exists('mikhmon_is_expired') && mikhmon_is_expired()) && !(function_exists('mikhmon_is_suspended') && mikhmon_is_suspended())) {
                        $_SESSION["mikhmon"] = $useradm;
                    }
                }
            }
        }
    }
}

mikhmon_init_session();
