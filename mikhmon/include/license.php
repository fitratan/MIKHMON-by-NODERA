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
        $hasCloudMarker = file_exists(__DIR__ . '/../.cloud_mode') || file_exists(__DIR__ . '/.cloud_mode');
        if ($hasCloudMarker) {
            return false;
        }
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
        $host = strtolower(explode(':', $host)[0]);
        $isLocal = in_array($host, ['127.0.0.1', 'localhost', '::1'])
            || (isset($_SERVER['SERVER_ADDR']) && in_array($_SERVER['SERVER_ADDR'], ['127.0.0.1', '::1']));
        return $isLocal;
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
        $prodName = $data['product_name'] ?? 'Mikhmon Desktop Standalone';
        $nowStr = date('Y-m-d H:i:s');

        $licensePhp = "<?php\n"
            . "/**\n"
            . " * LICENSE MIKHMON DESKTOP STANDALONE — NODERA\n"
            . " * Teraktivasi resmi via Cloud Panel NODERA (panel.dgtlnetsolution.com).\n"
            . " */\n"
            . "define('MIKHMON_MODE', 'DESKTOP');\n"
            . "define('MIKHMON_STATUS', 'ACTIVE');\n"
            . "define('MIKHMON_LICENSE_KEY', " . var_export($licenseKey, true) . ");\n"
            . "define('MIKHMON_HWID', " . var_export($hwid, true) . ");\n"
            . "define('MIKHMON_EXPIRY', " . var_export($expFormatted, true) . ");\n"
            . "define('MIKHMON_BRAND', 'by dgtlnetsolution.com');\n"
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

// Auto-sync license from Electron storage or local json if config/license.php missing
if (!function_exists('mikhmon_sync_license_from_storage')) {
    function mikhmon_sync_license_from_storage(): void {
        $lFile = __DIR__ . '/../config/license.php';
        if (file_exists($lFile) && filesize($lFile) > 100) {
            return;
        }
        $jsonCandidates = [
            __DIR__ . '/../config/nodera_license.json',
            getenv('APPDATA') ? (getenv('APPDATA') . '/Mikhmon Desktop by NODERA/nodera_license.json') : '',
            getenv('HOME') ? (getenv('HOME') . '/.config/Mikhmon Desktop by NODERA/nodera_license.json') : '',
        ];
        foreach ($jsonCandidates as $cand) {
            if (!empty($cand) && file_exists($cand)) {
                $json = @json_decode(@file_get_contents($cand), true);
                if (is_array($json) && !empty($json['license_key'])) {
                    mikhmon_save_desktop_license_data($json);
                    return;
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

if (!defined('MIKHMON_BRAND')) {
    define('MIKHMON_BRAND', 'by dgtlnetsolution.com');
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
            $isUnlicensed = (!defined('MIKHMON_LICENSE_KEY') || empty(trim(MIKHMON_LICENSE_KEY)) || !defined('MIKHMON_STATUS') || in_array(strtoupper(MIKHMON_STATUS), ['UNLICENSED', 'INACTIVE'], true));
            
            // Auto-claim 7-day trial on first run if never attempted
            if ($isUnlicensed) {
                $trialMarker = __DIR__ . '/../config/.trial_attempted';
                if (!file_exists($trialMarker)) {
                    @touch($trialMarker);
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
            $currentHwid = mikhmon_get_hwid();
            if (empty(MIKHMON_HWID) || strcasecmp(trim(MIKHMON_HWID), trim($currentHwid)) !== 0) {
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

        $endpoints = [
            'https://panel.dgtlnetsolution.com/api/desktop-license/verify',
            'https://digitalnet.dgtlnetsolution.com/api/desktop-license/verify',
            'https://panel.dgtlnetsolution.com/api/v1/desktop-license/verify',
        ];

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
                'message' => 'Gagal terhubung ke Cloud License Server. Pastikan koneksi internet aktif.',
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
            . "define('MIKHMON_EXPIRY', '');\n"
            . "define('MIKHMON_BRAND', 'by NODERA');\n"
            . "define('MIKHMON_PRODUCT_NAME', 'Mikhmon Standalone');\n"
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

        $endpoints = [
            'https://panel.dgtlnetsolution.com/api/desktop-license/activate',
            'https://digitalnet.dgtlnetsolution.com/api/desktop-license/activate',
            'https://panel.dgtlnetsolution.com/api/v1/desktop-license/activate',
        ];

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

        $endpoints = [
            'https://panel.dgtlnetsolution.com/api/desktop-license/trial',
            'https://digitalnet.dgtlnetsolution.com/api/desktop-license/trial',
            'https://panel.dgtlnetsolution.com/api/v1/desktop-license/trial',
        ];

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

        // Offline Fallback for first launch
        $lang = $_SESSION['lang'] ?? $_COOKIE['mikhmon_lang'] ?? 'id';
        $expFormatted = date('Y-m-d H:i:s', time() + (7 * 86400));
        $licenseKey = 'NDR-TRL-' . strtoupper(substr(md5($hwid . 'NODERA_TRIAL_2026'), 0, 12));
        $prodName = ($lang === 'fr' ? 'Mikhmon Desktop (Essai 7 Jours)' : ($lang === 'en' ? 'Mikhmon Desktop (7-Day Trial)' : 'Mikhmon Desktop (Trial 7 Hari)'));

        $data = [
            'license_key'  => $licenseKey,
            'hwid'         => $hwid,
            'status'       => 'ACTIVE',
            'product_name' => $prodName,
            'expires_at'   => $expFormatted,
        ];

        return mikhmon_save_desktop_license_data($data);
    }
}
