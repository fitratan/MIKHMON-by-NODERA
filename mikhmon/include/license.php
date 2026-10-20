<?php
/**
 * LICENSE LOADER — MIKHMON by dgtlnetsolution.com
 * Handles desktop HWID license verification, 7-day free trial on first launch,
 * expiration date checking, and multi-tenant subscription gating.
 */
if (file_exists(__DIR__ . '/compatibility.php')) {
    include_once __DIR__ . '/compatibility.php';
}

if (!function_exists('mikhmon_is_desktop_mode')) {
    function mikhmon_is_desktop_mode(): bool {
        if (defined('MIKHMON_MODE') && MIKHMON_MODE === 'DESKTOP') {
            return true;
        }
        $hasCloudMarker = file_exists(__DIR__ . '/../.cloud_mode') || file_exists(__DIR__ . '/.cloud_mode') || file_exists(dirname(dirname(__DIR__)) . '/.cloud_mode');
        if ($hasCloudMarker) {
            return false;
        }
        return true;
    }
}

if (!function_exists('mikhmon_get_hwid')) {
    function mikhmon_get_hwid(): string {
        $envHwid = getenv('NODERA_DESKTOP_HWID');
        if (!empty($envHwid)) {
            return trim($envHwid);
        }
        $hwidFile = __DIR__ . '/../config/.hwid';
        if (file_exists($hwidFile)) {
            $saved = trim((string)@file_get_contents($hwidFile));
            if (!empty($saved) && str_starts_with($saved, 'NDR-HWID-')) {
                return $saved;
            }
        }
        // Fallback if standalone PHP started without Electron
        $raw = php_uname('s') . '-' . php_uname('n') . '-' . php_uname('m') . '-' . (getenv('COMPUTERNAME') ?: '') . '-' . (getenv('USER') ?: get_current_user());
        if (function_exists('disk_total_space')) {
            $raw .= '-' . @disk_total_space(__DIR__);
        }
        $hash = strtoupper(hash('sha256', $raw));
        $hwid = 'NDR-HWID-' . substr($hash, 0, 4) . '-' . substr($hash, 4, 4) . '-' . substr($hash, 8, 4);
        @file_put_contents($hwidFile, $hwid);
        return $hwid;
    }
}

if (!defined('NODERA_OFFLINE_SECRET_2026')) {
    define('NODERA_OFFLINE_SECRET_2026', 'NODERA_OFFLINE_SECRET_2026');
}

if (!function_exists('mikhmon_check_clock_guard')) {
    function mikhmon_check_clock_guard(): bool {
        $guardFile = __DIR__ . '/../config/.clock_guard';
        $now = time();
        if (file_exists($guardFile)) {
            $last = (int) @file_get_contents($guardFile);
            if ($last > 0 && $now < ($last - 300)) {
                // Jam sistem dimundurkan lebih dari 5 menit
                return false;
            }
        }
        @file_put_contents($guardFile, (string)$now);
        return true;
    }
}

if (!function_exists('mikhmon_verify_license_signature')) {
    function mikhmon_verify_license_signature(?string $key = null, ?string $hwid = null, ?string $exp = null, ?string $sig = null): bool {
        $key = $key ?: (defined('MIKHMON_LICENSE_KEY') ? MIKHMON_LICENSE_KEY : '');
        $hwid = $hwid ?: (defined('MIKHMON_HWID') ? MIKHMON_HWID : mikhmon_get_hwid());
        $sig = $sig ?: (defined('MIKHMON_SIGNATURE') ? MIKHMON_SIGNATURE : '');
        if (empty($sig) || empty($key)) {
            // Check nodera_license.json if available
            $jsonFile = __DIR__ . '/../config/nodera_license.json';
            if (empty($sig) && file_exists($jsonFile)) {
                $saved = @json_decode(@file_get_contents($jsonFile), true);
                if (!empty($saved['signature'])) {
                    $sig = $saved['signature'];
                }
            }
            if (empty($sig)) {
                return false;
            }
        }
        $rawExp = $exp ?: (defined('MIKHMON_EXPIRY_RAW') ? MIKHMON_EXPIRY_RAW : (defined('MIKHMON_EXPIRY') ? MIKHMON_EXPIRY : 'LIFETIME'));
        if (empty($rawExp) || $rawExp === '0000-00-00') {
            $rawExp = 'LIFETIME';
        }
        $expected1 = hash('sha256', $key . '|' . $hwid . '|' . $rawExp . '|NODERA_OFFLINE_SECRET_2026');
        if (hash_equals($expected1, $sig)) {
            return true;
        }
        // Fallback check against formatted expiry
        $formattedExp = defined('MIKHMON_EXPIRY') ? MIKHMON_EXPIRY : '';
        if (!empty($formattedExp) && $formattedExp !== 'LIFETIME' && $formattedExp !== '0000-00-00') {
            $expected2 = hash('sha256', $key . '|' . $hwid . '|' . $formattedExp . '|NODERA_OFFLINE_SECRET_2026');
            if (hash_equals($expected2, $sig)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('mikhmon_save_desktop_license_data')) {
    function mikhmon_save_desktop_license_data(array $data): array {
        if (empty($data)) {
            return ['success' => false, 'message' => 'Data lisensi kosong.'];
        }

        $licenseKey = trim((string)($data['license_key'] ?? ''));
        if (empty($licenseKey)) {
            return ['success' => false, 'message' => 'License Key tidak ditemukan.'];
        }

        $hwid = trim((string)($data['hwid'] ?? mikhmon_get_hwid()));
        $exp = $data['expires_at'] ?? '';
        $expFormatted = !empty($exp) ? date('Y-m-d H:i:s', strtotime($exp)) : '';
        $rawExp = $exp ?: 'LIFETIME';
        $signature = $data['signature'] ?? '';
        $prodName = $data['product_name'] ?? 'Mikhmon Desktop Standalone';
        $nowStr = date('Y-m-d H:i:s');

        $licensePhp = "<?php\n"
            . "/**\n"
            . " * LICENSE MIKHMON DESKTOP STANDALONE — NODERA\n"
            . " * Teraktivasi resmi via Cloud Panel NODERA.\n"
            . " */\n"
            . "define('MIKHMON_MODE', 'DESKTOP');\n"
            . "define('MIKHMON_STATUS', 'ACTIVE');\n"
            . "define('MIKHMON_LICENSE_KEY', " . var_export($licenseKey, true) . ");\n"
            . "define('MIKHMON_HWID', " . var_export($hwid, true) . ");\n"
            . "define('MIKHMON_EXPIRY', " . var_export($expFormatted, true) . ");\n"
            . "define('MIKHMON_EXPIRY_RAW', " . var_export($rawExp, true) . ");\n"
            . "define('MIKHMON_SIGNATURE', " . var_export($signature, true) . ");\n"
            . "define('MIKHMON_BRAND', 'by ' . (function_exists('mikhmon_get_platform_domain') ? mikhmon_get_platform_domain() : 'airnetsolution.com'));\n"
            . "define('MIKHMON_PRODUCT_NAME', " . var_export($prodName, true) . ");\n"
            . "define('MIKHMON_ACTIVATED_AT', " . var_export($nowStr, true) . ");\n"
            . "define('MIKHMON_SUBDOMAIN', 'desktop');\n";

        $configFile = __DIR__ . '/../config/license.php';
        @file_put_contents($configFile, $licensePhp);
        @file_put_contents(__DIR__ . '/../config/.hwid', $hwid);
        @file_put_contents(__DIR__ . '/../config/nodera_license.json', json_encode($data, JSON_PRETTY_PRINT));
        @file_put_contents(__DIR__ . '/../config/.last_verified', (string)time());

        // Hapus alert error reset jika ada
        $resetAlert = __DIR__ . '/../config/.reset_alert';
        if (file_exists($resetAlert)) {
            @unlink($resetAlert);
        }

        return [
            'success'      => true,
            'message'      => 'Lisensi berhasil disimpan dan diaktivasi!',
            'license_key'  => $licenseKey,
            'expires_at'   => $expFormatted ?: 'LIFETIME',
            'product_name' => $prodName,
        ];
    }
}

// Check if license is supplied via environment or Electron storage
$envLicKey = getenv('NODERA_LICENSE_KEY');
$envLicFile = getenv('NODERA_LICENSE_FILE');
$envLicStatus = getenv('NODERA_LICENSE_STATUS') ?: 'ACTIVE';
$envLicExp = getenv('NODERA_LICENSE_EXPIRY') ?: 'LIFETIME';
$envLicSig = getenv('NODERA_LICENSE_SIGNATURE') ?: '';
$envLicProd = getenv('NODERA_LICENSE_PRODUCT') ?: 'Mikhmon Desktop Standalone';

if (empty($envLicKey) && !empty($envLicFile) && file_exists($envLicFile)) {
    $fData = @json_decode(@file_get_contents($envLicFile), true);
    if (is_array($fData) && !empty($fData['license_key'])) {
        $envLicKey = $fData['license_key'];
        $envLicStatus = $fData['status'] ?? 'ACTIVE';
        $envLicExp = $fData['expires_at'] ?? 'LIFETIME';
        $envLicSig = $fData['signature'] ?? '';
        $envLicProd = $fData['product_name'] ?? 'Mikhmon Desktop Standalone';
    }
}

// Auto-sync license from Electron storage or local json if config/license.php missing
if (!function_exists('mikhmon_sync_license_from_storage')) {
    function mikhmon_sync_license_from_storage(): void {
        $lFile = __DIR__ . '/../config/license.php';
        $currentHwid = mikhmon_get_hwid();

        // Jika config/license.php sudah valid untuk HWID perangkat ini, tidak perlu timpa
        if (file_exists($lFile) && filesize($lFile) > 100) {
            $content = (string)@file_get_contents($lFile);
            if (strpos($content, "'MIKHMON_STATUS', 'ACTIVE'") !== false) {
                if (defined('MIKHMON_HWID') && !empty(MIKHMON_HWID) && strcasecmp(trim(MIKHMON_HWID), trim($currentHwid)) === 0) {
                    return;
                }
            }
        }

        $jsonCandidates = [
            getenv('NODERA_LICENSE_FILE') ?: '',
            __DIR__ . '/../config/nodera_license.json',
            getenv('APPDATA') ? (getenv('APPDATA') . '/mikhmon-desktop-nodera/nodera_license.json') : '',
            getenv('APPDATA') ? (getenv('APPDATA') . '/Mikhmon Desktop by NODERA/nodera_license.json') : '',
            getenv('HOME') ? (getenv('HOME') . '/.config/mikhmon-desktop-nodera/nodera_license.json') : '',
            getenv('HOME') ? (getenv('HOME') . '/.config/Mikhmon Desktop by NODERA/nodera_license.json') : '',
        ];
        foreach ($jsonCandidates as $cand) {
            if (!empty($cand) && file_exists($cand)) {
                $json = @json_decode(@file_get_contents($cand), true);
                if (is_array($json) && !empty($json['license_key'])) {
                    // Match HWID atau jika HWID cocok dengan perangkat saat ini
                    if (empty($json['hwid']) || strcasecmp(trim($json['hwid']), trim($currentHwid)) === 0) {
                        mikhmon_save_desktop_license_data($json);
                        return;
                    }
                }
            }
        }
    }
}

mikhmon_sync_license_from_storage();

$__licenseFile = __DIR__ . '/../config/license.php';
if (!file_exists($__licenseFile)) {
    $__licenseFile = __DIR__ . '/license_data.php';
}
if (file_exists($__licenseFile)) {
    @include_once $__licenseFile;
}

// Fallback define from environment if file did not define it
if (!empty($envLicKey) && (!defined('MIKHMON_LICENSE_KEY') || empty(MIKHMON_LICENSE_KEY))) {
    if (!defined('MIKHMON_MODE')) define('MIKHMON_MODE', 'DESKTOP');
    if (!defined('MIKHMON_STATUS')) define('MIKHMON_STATUS', $envLicStatus);
    if (!defined('MIKHMON_LICENSE_KEY')) define('MIKHMON_LICENSE_KEY', $envLicKey);
    if (!defined('MIKHMON_HWID')) define('MIKHMON_HWID', getenv('NODERA_DESKTOP_HWID') ?: mikhmon_get_hwid());
    $expFormatted = ($envLicExp !== 'LIFETIME' && !empty($envLicExp) && $envLicExp !== '0000-00-00') ? date('Y-m-d H:i:s', strtotime($envLicExp)) : '';
    if (!defined('MIKHMON_EXPIRY')) define('MIKHMON_EXPIRY', $expFormatted);
    if (!defined('MIKHMON_EXPIRY_RAW')) define('MIKHMON_EXPIRY_RAW', $envLicExp);
    if (!defined('MIKHMON_SIGNATURE')) define('MIKHMON_SIGNATURE', $envLicSig);
    if (!defined('MIKHMON_BRAND')) define('MIKHMON_BRAND', 'by ' . (function_exists('mikhmon_get_platform_domain') ? mikhmon_get_platform_domain() : 'dgtlnetsolution.com'));
    if (!defined('MIKHMON_PRODUCT_NAME')) define('MIKHMON_PRODUCT_NAME', $envLicProd);
    if (!defined('MIKHMON_ACTIVATED_AT')) define('MIKHMON_ACTIVATED_AT', date('Y-m-d H:i:s'));
    if (!defined('MIKHMON_SUBDOMAIN')) define('MIKHMON_SUBDOMAIN', 'desktop');
}

if (!function_exists('mikhmon_get_platform_domain')) {
    function mikhmon_get_platform_domain(): string {
        $host = !empty($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
        if (strpos($host, 'airnetsolution.com') !== false) {
            return 'airnetsolution.com';
        }
        if (strpos($host, 'dgtlnetsolution.com') !== false) {
            return 'dgtlnetsolution.com';
        }
        if (strpos($host, 'nodera.africa') !== false) {
            return 'nodera.africa';
        }
        if (strpos($host, 'nodera.ci') !== false) {
            return 'nodera.ci';
        }
        if (defined('MIKHMON_BRAND')) {
            if (strpos(MIKHMON_BRAND, 'airnetsolution.com') !== false) {
                return 'airnetsolution.com';
            }
            if (strpos(MIKHMON_BRAND, 'dgtlnetsolution.com') !== false) {
                return 'dgtlnetsolution.com';
            }
            if (strpos(MIKHMON_BRAND, 'nodera.africa') !== false) {
                return 'nodera.africa';
            }
        }
        $basePath = realpath(dirname(dirname(__DIR__)));
        if ($basePath && (strpos($basePath, 'nodera-africa') !== false || strpos($basePath, 'africa') !== false)) {
            return 'airnetsolution.com';
        }
        return 'dgtlnetsolution.com';
    }
}

if (!function_exists('mikhmon_get_panel_url')) {
    function mikhmon_get_panel_url(): string {
        return 'https://panel.' . mikhmon_get_platform_domain();
    }
}

if (!function_exists('mikhmon_get_api_url')) {
    function mikhmon_get_api_url(): string {
        return 'https://' . mikhmon_get_platform_domain();
    }
}

if (!function_exists('mikhmon_get_gateway_url')) {
    function mikhmon_get_gateway_url(): string {
        return 'https://gateway.' . mikhmon_get_platform_domain();
    }
}

if (!function_exists('mikhmon_get_wa_url')) {
    function mikhmon_get_wa_url(): string {
        return 'https://wa.' . mikhmon_get_platform_domain();
    }
}

if (!function_exists('mikhmon_get_shop_url')) {
    function mikhmon_get_shop_url(): string {
        return 'https://shop.' . mikhmon_get_platform_domain();
    }
}

if (!defined('MIKHMON_BRAND')) {
    define('MIKHMON_BRAND', 'by ' . mikhmon_get_platform_domain());
}
if (!defined('MIKHMON_SUBDOMAIN')) {
    $folder = basename(dirname(__DIR__));
    define('MIKHMON_SUBDOMAIN', $folder);
}

// Default constants if not defined in config/license.php
if (!defined('MIKHMON_STATUS')) {
    define('MIKHMON_STATUS', 'UNLICENSED');
}
if (!defined('MIKHMON_EXPIRY')) {
    define('MIKHMON_EXPIRY', '0000-00-00');
}
if (!defined('MIKHMON_LICENSE_KEY')) {
    define('MIKHMON_LICENSE_KEY', '');
}

if (!function_exists('mikhmon_expiry_ts')) {
    function mikhmon_expiry_ts(): ?int {
        $e = trim((string) MIKHMON_EXPIRY);
        if ($e === '' || $e === '0000-00-00' || $e === 'LIFETIME') return null;
        $t = strtotime($e);
        return $t ?: null;
    }
}

if (!function_exists('mikhmon_is_expired')) {
    function mikhmon_is_expired(): bool {
        if (defined('MIKHMON_STATUS') && in_array(strtoupper(MIKHMON_STATUS), ['SUSPENDED', 'DISABLED', 'BLOCKED', 'EXPIRED', 'UNLICENSED'], true)) {
            return true;
        }
        // Anti-Clock Tampering Guard (Deteksi jam Windows dimundurkan)
        if (!mikhmon_check_clock_guard()) {
            return true;
        }
        // Anti-Tamper Cryptographic Signature Verification
        if (mikhmon_is_desktop_mode()) {
            $hasSig = (defined('MIKHMON_SIGNATURE') && !empty(MIKHMON_SIGNATURE)) || file_exists(__DIR__ . '/../config/nodera_license.json');
            if ($hasSig && !mikhmon_verify_license_signature()) {
                return true;
            }
        }
        $t = mikhmon_expiry_ts();
        if ($t === null) {
            if (mikhmon_is_desktop_mode() && (!defined('MIKHMON_LICENSE_KEY') || empty(trim(MIKHMON_LICENSE_KEY)))) {
                return true;
            }
            return false;
        }
        return time() > ($t + 86399);
    }
}

if (!function_exists('mikhmon_is_suspended')) {
    function mikhmon_is_suspended(): bool {
        return defined('MIKHMON_STATUS') && in_array(strtoupper(MIKHMON_STATUS), ['SUSPENDED', 'DISABLED', 'BLOCKED'], true);
    }
}

if (!function_exists('mikhmon_expiry_text')) {
    function mikhmon_expiry_text(?string $langParam = null): string {
        $lang = $langParam ?: ($_SESSION['lang'] ?? $_COOKIE['mikhmon_lang'] ?? 'id');
        if (!in_array($lang, ['id', 'fr', 'en'])) {
            $lang = 'id';
        }
        if (mikhmon_is_desktop_mode() && (!defined('MIKHMON_LICENSE_KEY') || empty(trim(MIKHMON_LICENSE_KEY)) || MIKHMON_STATUS === 'UNLICENSED')) {
            if ($lang === 'fr') return 'Non Activé (Licence Requise)';
            if ($lang === 'en') return 'Unactivated (License Required)';
            return 'Belum Teraktivasi (Lisensi Diperlukan)';
        }
        if (mikhmon_is_suspended()) {
            if ($lang === 'fr') return 'Suspendu (Suspended)';
            if ($lang === 'en') return 'Suspended';
            return 'Ditangguhkan (Suspended)';
        }
        $t = mikhmon_expiry_ts();
        if ($t === null) {
            if ($lang === 'fr') return 'Permanent (À Vie)';
            if ($lang === 'en') return 'Permanent (Lifetime)';
            return 'Permanen (Lifetime)';
        }
        $diff = (int) ceil(($t + 86399 - time()) / 86400);
        if ($diff < 0) {
            if ($lang === 'fr') return 'Expiré (' . date('d M Y', $t) . ')';
            if ($lang === 'en') return 'Expired (' . date('d M Y', $t) . ')';
            return 'Kedaluwarsa (' . date('d M Y', $t) . ')';
        }
        if ($diff == 0) {
            if ($lang === 'fr') return 'Expire Aujourd\'hui (' . date('d M Y', $t) . ')';
            if ($lang === 'en') return 'Expires Today (' . date('d M Y', $t) . ')';
            return 'Berakhir Hari Ini (' . date('d M Y', $t) . ')';
        }
        if ($lang === 'fr') return $diff . ' Jours Restants (jusqu\'au ' . date('d M Y', $t) . ')';
        if ($lang === 'en') return $diff . ' Days Left (until ' . date('d M Y', $t) . ')';
        return $diff . ' Hari Lagi (s/d ' . date('d M Y', $t) . ')';
    }
}

if (!function_exists('mikhmon_get_license_expiry_text')) {
    function mikhmon_get_license_expiry_text(?string $langParam = null): string {
        return mikhmon_expiry_text($langParam);
    }
}

if (!function_exists('mikhmon_remaining_days')) {
    function mikhmon_remaining_days(): int {
        $t = mikhmon_expiry_ts();
        if ($t === null) {
            return 9999;
        }
        $diff = (int) ceil(($t + 86399 - time()) / 86400);
        return max(0, $diff);
    }
}

if (!function_exists('mikhmon_is_licensed')) {
    function mikhmon_is_licensed(): bool {
        // Desktop / Standalone mode validation
        if (mikhmon_is_desktop_mode()) {
            $currentHwid = mikhmon_get_hwid();
            $isUnlicensed = (!defined('MIKHMON_LICENSE_KEY') || empty(trim(MIKHMON_LICENSE_KEY)) || !defined('MIKHMON_STATUS') || in_array(strtoupper(MIKHMON_STATUS), ['UNLICENSED', 'INACTIVE'], true));
            $hwidMismatch = (defined('MIKHMON_HWID') && !empty(MIKHMON_HWID) && strcasecmp(trim(MIKHMON_HWID), trim($currentHwid)) !== 0);

            // Jika lisensi lokal belum aktif atau berbeda HWID (misal setelah download ulang / install ulang)
            if ($isUnlicensed || $hwidMismatch) {
                // 1. Cek penyimpanan lokal (AppData / nodera_license.json)
                mikhmon_sync_license_from_storage();

                $stillUnlicensed = (!defined('MIKHMON_LICENSE_KEY') || empty(trim(MIKHMON_LICENSE_KEY)) || !defined('MIKHMON_STATUS') || in_array(strtoupper(MIKHMON_STATUS), ['UNLICENSED', 'INACTIVE'], true));
                $stillMismatch = (defined('MIKHMON_HWID') && !empty(MIKHMON_HWID) && strcasecmp(trim(MIKHMON_HWID), trim($currentHwid)) !== 0);

                // 2. Hubungi Cloud Server secara otomatis untuk memulihkan trial/lisensi aktif terikat HWID
                if ($stillUnlicensed || $stillMismatch) {
                    $retryMarker = __DIR__ . '/../config/.last_hwid_query';
                    $lastQuery = file_exists($retryMarker) ? (int) @file_get_contents($retryMarker) : 0;
                    if (time() - $lastQuery > 10) {
                        @file_put_contents($retryMarker, (string)time());
                        if (function_exists('mikhmon_activate_trial_license')) {
                            $res = mikhmon_activate_trial_license();
                            if (!empty($res['success'])) {
                                $lFile = __DIR__ . '/../config/license.php';
                                if (file_exists($lFile)) {
                                    @include $lFile;
                                }
                                return true;
                            }
                        }
                    }
                }
            }

            if (!defined('MIKHMON_LICENSE_KEY') || empty(trim(MIKHMON_LICENSE_KEY))) {
                return false;
            }
            if (!defined('MIKHMON_STATUS') || strtoupper(MIKHMON_STATUS) !== 'ACTIVE') {
                return false;
            }
            if (!defined('MIKHMON_HWID') || empty(trim(MIKHMON_HWID))) {
                return false;
            }
            // Strict HWID matching (case-insensitive)
            if (strcasecmp(trim(MIKHMON_HWID), trim($currentHwid)) !== 0) {
                return false;
            }

            // Anti-Clock Tampering Guard Check
            if (!mikhmon_check_clock_guard()) {
                return false;
            }

            // Anti-Tamper Signature Check
            if (defined('MIKHMON_SIGNATURE') && !empty(MIKHMON_SIGNATURE) && !mikhmon_verify_license_signature()) {
                return false;
            }

            // Periodic Background Cloud Verification (setiap 6 jam sekali, bukan tiap page view)
            $verifyMarker = __DIR__ . '/../config/.last_verified';
            $lastVerified = file_exists($verifyMarker) ? (int) @file_get_contents($verifyMarker) : 0;
            if (time() - $lastVerified > (6 * 3600)) {
                $verifyRes = mikhmon_verify_desktop_license(MIKHMON_LICENSE_KEY, $currentHwid);
                if (!empty($verifyRes['success'])) {
                    @file_put_contents($verifyMarker, (string)time());
                } else {
                    if (isset($verifyRes['data']['status']) && in_array(strtoupper($verifyRes['data']['status']), ['SUSPENDED', 'DISABLED', 'BLOCKED', 'EXPIRED'], true)) {
                        @file_put_contents(__DIR__ . '/../config/.reset_alert', 'Lisensi Anda telah ditangguhkan/kedaluwarsa di Cloud Panel.');
                        return false;
                    }
                    if (isset($verifyRes['code']) && in_array($verifyRes['code'], ['HWID_MISMATCH', 'INVALID_KEY', 'LICENSE_SUSPENDED'], true)) {
                        @file_put_contents(__DIR__ . '/../config/.reset_alert', $verifyRes['message'] ?? 'Lisensi tidak valid.');
                        return false;
                    }
                }
            }

            return !mikhmon_is_expired();
        }

        // Cloud SaaS Mode
        if (defined('MIKHMON_STATUS') && in_array(strtoupper(MIKHMON_STATUS), ['SUSPENDED', 'DISABLED', 'BLOCKED', 'EXPIRED', 'UNLICENSED'], true)) {
            return false;
        }

        return !mikhmon_is_expired();
    }
}

if (!function_exists('mikhmon_verify_desktop_license')) {
    function mikhmon_verify_desktop_license(?string $licenseKey = null, ?string $hwid = null): array {
        if (!mikhmon_is_desktop_mode()) {
            return ['success' => true, 'message' => 'Cloud Mode Active'];
        }

        $key = $licenseKey ?: (defined('MIKHMON_LICENSE_KEY') ? MIKHMON_LICENSE_KEY : '');
        $hw = $hwid ?: mikhmon_get_hwid();

        if (empty($key)) {
            return ['success' => false, 'message' => 'Lisensi belum diisi.'];
        }

        $payload = [
            'license_key' => $key,
            'hwid'        => $hw,
            'device_name' => getenv('COMPUTERNAME') ?: (gethostname() ?: 'Mikhmon Desktop Client'),
            'os_info'     => php_uname('s') . ' ' . php_uname('r') . ' (' . php_uname('m') . ')',
        ];

        $endpoints = array_unique(array_filter([
            (function_exists('mikhmon_get_panel_url') ? mikhmon_get_panel_url() : 'https://panel.dgtlnetsolution.com') . '/api/desktop-license/verify',
            (function_exists('mikhmon_get_api_url') ? mikhmon_get_api_url() : 'https://dgtlnetsolution.com') . '/api/desktop-license/verify',
            (function_exists('mikhmon_get_panel_url') ? mikhmon_get_panel_url() : 'https://panel.dgtlnetsolution.com') . '/api/v1/desktop-license/verify',
            'https://panel.dgtlnetsolution.com/api/desktop-license/verify',
            'https://digitalnet.dgtlnetsolution.com/api/desktop-license/verify',
            'https://panel.airnetsolution.com/api/desktop-license/verify',
        ]));

        $response = null;
        foreach ($endpoints as $url) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: Mikhmon-Desktop-Client/1.0',
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            if (function_exists('mikhmon_curl_set_ssl')) {
                mikhmon_curl_set_ssl($ch);
            } else {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            }
            $raw = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($raw && ($httpCode === 200 || $httpCode === 403 || $httpCode === 404)) {
                $json = @json_decode($raw, true);
                if (is_array($json)) {
                    $response = $json;
                    break;
                }
            }
        }

        if (!$response) {
            // Offline Grace Period Check (Non-recursive)
            if (!mikhmon_check_clock_guard()) {
                return [
                    'success' => false,
                    'message' => 'Manipulasi jam sistem terdeteksi. Silakan sinkronkan waktu perangkat Anda.',
                ];
            }
            if (defined('MIKHMON_SIGNATURE') && !empty(MIKHMON_SIGNATURE) && !mikhmon_verify_license_signature()) {
                return [
                    'success' => false,
                    'message' => 'Integritas file lisensi rusak atau telah dimodifikasi.',
                ];
            }
            $isLocalActive = defined('MIKHMON_STATUS') && strtoupper(MIKHMON_STATUS) === 'ACTIVE' && !empty($key);
            $expTs = mikhmon_expiry_ts();
            $notExpired = ($expTs === null) || (time() <= ($expTs + 86399));
            if ($isLocalActive && $notExpired) {
                return [
                    'success' => true,
                    'offline' => true,
                    'message' => 'Mode Offline (Lisensi lokal aktif).',
                    'license_key' => $key,
                    'expires_at' => defined('MIKHMON_EXPIRY') ? MIKHMON_EXPIRY : '',
                ];
            }
            return [
                'success' => false,
                'message' => 'Gagal terhubung ke Cloud License Server dan masa aktif lisensi telah habis.',
            ];
        }

        return $response;
    }
}

if (!function_exists('mikhmon_reset_local_license')) {
    function mikhmon_reset_local_license(): bool {
        $licensePhp = "<?php\n"
            . "/**\n"
            . " * LICENSE MIKHMON DESKTOP STANDALONE — NODERA\n"
            . " * Belum diaktivasi / Reset.\n"
            . " */\n"
            . "define('MIKHMON_MODE', 'DESKTOP');\n"
            . "define('MIKHMON_STATUS', 'UNLICENSED');\n"
            . "define('MIKHMON_LICENSE_KEY', '');\n"
            . "define('MIKHMON_HWID', '');\n"
            . "define('MIKHMON_EXPIRY', '0000-00-00');\n"
            . "define('MIKHMON_EXPIRY_RAW', '0000-00-00');\n"
            . "define('MIKHMON_SIGNATURE', '');\n"
            . "define('MIKHMON_BRAND', 'by ' . (function_exists('mikhmon_get_platform_domain') ? mikhmon_get_platform_domain() : 'airnetsolution.com'));\n"
            . "define('MIKHMON_PRODUCT_NAME', 'Mikhmon Desktop Standalone');\n"
            . "define('MIKHMON_ACTIVATED_AT', '');\n"
            . "define('MIKHMON_SUBDOMAIN', 'desktop');\n";

        $configFile = __DIR__ . '/../config/license.php';
        @unlink(__DIR__ . '/../config/nodera_license.json');
        return (bool) @file_put_contents($configFile, $licensePhp);
    }
}

if (!function_exists('mikhmon_activate_desktop_license')) {
    function mikhmon_activate_desktop_license(string $licenseKey): array {
        $licenseKey = trim($licenseKey);
        $lang = $_SESSION['lang'] ?? $_COOKIE['mikhmon_lang'] ?? 'id';
        if (empty($licenseKey)) {
            $msgEmpty = ($lang === 'fr' ? 'La clé de licence est obligatoire.' : ($lang === 'en' ? 'License Key is required.' : 'Kunci lisensi wajib diisi.'));
            return ['success' => false, 'message' => $msgEmpty];
        }

        $hwid = mikhmon_get_hwid();
        $deviceName = getenv('COMPUTERNAME') ?: (gethostname() ?: 'Mikhmon Desktop Client');
        $osInfo = php_uname('s') . ' ' . php_uname('r') . ' (' . php_uname('m') . ')';

        $payload = [
            'license_key' => $licenseKey,
            'hwid'        => $hwid,
            'device_name' => $deviceName,
            'os_info'     => $osInfo,
        ];

        $endpoints = array_unique(array_filter([
            (function_exists('mikhmon_get_panel_url') ? mikhmon_get_panel_url() : 'https://panel.dgtlnetsolution.com') . '/api/desktop-license/activate',
            (function_exists('mikhmon_get_api_url') ? mikhmon_get_api_url() : 'https://dgtlnetsolution.com') . '/api/desktop-license/activate',
            (function_exists('mikhmon_get_panel_url') ? mikhmon_get_panel_url() : 'https://panel.dgtlnetsolution.com') . '/api/v1/desktop-license/activate',
            'https://panel.dgtlnetsolution.com/api/desktop-license/activate',
            'https://digitalnet.dgtlnetsolution.com/api/desktop-license/activate',
            'https://panel.airnetsolution.com/api/desktop-license/activate',
        ]));

        $response = null;
        $lastErr = '';

        foreach ($endpoints as $url) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: Mikhmon-Desktop-Client/1.0',
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            if (function_exists('mikhmon_curl_set_ssl')) {
                mikhmon_curl_set_ssl($ch);
            } else {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            }
            $raw = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($raw && ($httpCode === 200 || $httpCode === 403 || $httpCode === 404 || $httpCode === 422)) {
                $json = @json_decode($raw, true);
                if (is_array($json)) {
                    $response = $json;
                    break;
                }
            } else if ($err) {
                $lastErr = $err;
            }
        }

        if (!$response) {
            $msgNet = ($lang === 'fr' 
                ? 'Impossible de contacter le serveur de licence Cloud. Assurez-vous d\'une connexion Internet active. (' . ($lastErr ?: 'Timeout') . ')'
                : ($lang === 'en'
                    ? 'Failed to connect to Cloud License Server. Ensure internet connection is active. (' . ($lastErr ?: 'Timeout') . ')'
                    : 'Gagal terhubung ke Cloud License Server. Pastikan koneksi internet aktif. (' . ($lastErr ?: 'Timeout') . ')'));
            return [
                'success' => false,
                'message' => $msgNet,
            ];
        }

        if (empty($response['success'])) {
            $defaultFail = ($lang === 'fr' ? 'Échec de l\'activation de la licence.' : ($lang === 'en' ? 'License activation failed.' : 'Aktivasi lisensi gagal.'));
            return [
                'success' => false,
                'message' => $response['message'] ?? $defaultFail,
            ];
        }

        $defaultSuccess = ($lang === 'fr' ? 'Licence Desktop NODERA activée avec succès !' : ($lang === 'en' ? 'NODERA Desktop License activated successfully!' : 'Lisensi Desktop NODERA berhasil diaktivasi!'));
        $data = $response['data'] ?? [];
        $savedRes = mikhmon_save_desktop_license_data($data);
        $savedRes['message'] = $response['message'] ?? $defaultSuccess;
        return $savedRes;
    }
}

if (!function_exists('mikhmon_activate_trial_license')) {
    function mikhmon_activate_trial_license(): array {
        $hwid = mikhmon_get_hwid();
        $deviceName = getenv('COMPUTERNAME') ?: (gethostname() ?: 'Mikhmon Desktop Client');
        $osInfo = php_uname('s') . ' ' . php_uname('r') . ' (' . php_uname('m') . ')';

        $payload = [
            'hwid'        => $hwid,
            'device_name' => $deviceName,
            'os_info'     => $osInfo,
        ];

        $endpoints = array_unique(array_filter([
            (function_exists('mikhmon_get_panel_url') ? mikhmon_get_panel_url() : 'https://panel.dgtlnetsolution.com') . '/api/desktop-license/trial',
            (function_exists('mikhmon_get_api_url') ? mikhmon_get_api_url() : 'https://dgtlnetsolution.com') . '/api/desktop-license/trial',
            (function_exists('mikhmon_get_panel_url') ? mikhmon_get_panel_url() : 'https://panel.dgtlnetsolution.com') . '/api/v1/desktop-license/trial',
            'https://panel.dgtlnetsolution.com/api/desktop-license/trial',
            'https://digitalnet.dgtlnetsolution.com/api/desktop-license/trial',
            'https://panel.airnetsolution.com/api/desktop-license/trial',
        ]));

        $response = null;
        $lastErr = '';

        foreach ($endpoints as $url) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: Mikhmon-Desktop-Client/1.0',
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            if (function_exists('mikhmon_curl_set_ssl')) {
                mikhmon_curl_set_ssl($ch);
            } else {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            }
            $raw = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($raw && ($httpCode === 200 || $httpCode === 403 || $httpCode === 404 || $httpCode === 422)) {
                $json = @json_decode($raw, true);
                if (is_array($json)) {
                    $response = $json;
                    break;
                }
            } else if ($err) {
                $lastErr = $err;
            }
        }

        if ($response && !empty($response['success'])) {
            $data = $response['data'] ?? [];
            return mikhmon_save_desktop_license_data($data);
        }

        if ($response && isset($response['code']) && $response['code'] === 'TRIAL_ALREADY_USED') {
            $lang = $_SESSION['lang'] ?? $_COOKIE['mikhmon_lang'] ?? 'id';
            $msgTrialUsed = ($lang === 'fr' 
                ? 'Cet appareil a déjà utilisé la période d\'essai gratuit de 7 jours.' 
                : ($lang === 'en' 
                    ? 'This device has already used the 7-day free trial.' 
                    : 'Perangkat ini sudah pernah menggunakan masa percobaan 7 hari gratis.'));
            return [
                'success' => false,
                'message' => $response['message'] ?? $msgTrialUsed,
            ];
        }

        // Online connection is strictly required for 7-day free trial activation
        $lang = $_SESSION['lang'] ?? $_COOKIE['mikhmon_lang'] ?? 'id';
        $msgNetErr = ($lang === 'fr' 
            ? 'Impossible de contacter le serveur d\'activation NODERA pour la période d\'essai gratuit. Connexion Internet requise pour valider l\'appareil.' 
            : ($lang === 'en' 
                ? 'Failed to reach NODERA activation server for the 7-day free trial. Internet connection required to validate device.' 
                : 'Aktivasi masa percobaan 7 hari gratis memerlukan koneksi internet untuk validasi perangkat ke Cloud Panel NODERA.'));

        return [
            'success' => false,
            'message' => $msgNetErr . (!empty($lastErr) ? ' (' . $lastErr . ')' : ''),
        ];
    }
}

if (!function_exists('mikhmon_render_desktop_heartbeat_script')) {
    function mikhmon_render_desktop_heartbeat_script(): void {
        if (!mikhmon_is_desktop_mode()) return;
        $key = defined('MIKHMON_LICENSE_KEY') ? MIKHMON_LICENSE_KEY : '';
        $hwid = defined('MIKHMON_HWID') ? MIKHMON_HWID : '';
        if (empty($key) || empty($hwid)) return;
        ?>
        <script>
        (function() {
          var _ndrKey = <?= json_encode($key) ?>;
          var _ndrHwid = <?= json_encode($hwid) ?>;
          if (!_ndrKey || !_ndrHwid) return;

          function _ndrCheckLicenseHeartbeat() {
            var apis = [
              <?= json_encode(mikhmon_get_panel_url() . '/api/desktop-license/verify') ?>,
              <?= json_encode(mikhmon_get_api_url() . '/api/desktop-license/verify') ?>,
              'https://panel.dgtlnetsolution.com/api/desktop-license/verify',
              'https://digitalnet.dgtlnetsolution.com/api/desktop-license/verify',
              'https://panel.airnetsolution.com/api/desktop-license/verify'
            ];
            
            function tryApi(idx) {
              if (idx >= apis.length) return;
              fetch(apis[idx], {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ license_key: _ndrKey, hwid: _ndrHwid })
              })
              .then(function(res) { return res.json(); })
              .then(function(data) {
                if (data && data.success === false && (data.code === 'HWID_MISMATCH' || data.code === 'HWID_UNBOUND' || data.code === 'INVALID_KEY' || data.code === 'LICENSE_SUSPENDED' || data.code === 'LICENSE_EXPIRED')) {
                  var fd = new FormData();
                  fd.append('action', 'ajax_revoke_local_license');
                  fetch('./admin.php', { method: 'POST', body: fd })
                  .then(function() {
                    alert("PEMBERITAHUAN LISENSI NODERA:\n" + (data.message || "Masa aktif lisensi atau trial Anda telah berakhir di Cloud Panel."));
                    window.location.href = './admin.php?id=login';
                  });
                }
              })
              .catch(function() {
                tryApi(idx + 1);
              });
            }

            tryApi(0);
          }

          if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(_ndrCheckLicenseHeartbeat, 1500);
          } else {
            window.addEventListener('DOMContentLoaded', function() {
              setTimeout(_ndrCheckLicenseHeartbeat, 1500);
            });
          }
          setInterval(_ndrCheckLicenseHeartbeat, 600000);
        })();
        </script>
        <?php
    }
}

