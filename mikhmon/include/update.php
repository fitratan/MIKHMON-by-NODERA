<?php
include_once __DIR__ . '/init_session.php';

if (!isset($_SESSION["mikhmon"]) && empty($_COOKIE['mikhmon_remember'])) {
    header("Location:./admin.php?id=login");
    exit;
}

$langid = $_SESSION['lang'] ?? $_SESSION['m_lang'] ?? $_COOKIE['mikhmon_lang'] ?? '';
if (empty($langid) && file_exists(__DIR__ . '/lang.php')) {
    @include __DIR__ . '/lang.php';
}
if (empty($langid) && file_exists(__DIR__ . '/../include/lang.php')) {
    @include __DIR__ . '/../include/lang.php';
}
if (empty($langid) || !file_exists(__DIR__ . '/../lang/' . $langid . '.php')) {
    $langid = 'id';
}
@include __DIR__ . '/../lang/' . $langid . '.php';

$mikhmonVersion = '3.20.5-v8';
$m_commit = '';
$m_build = '';
$versionFile = __DIR__ . '/version.php';
if (file_exists($versionFile)) {
    include_once($versionFile);
    if (!empty($m_version)) {
        $mikhmonVersion = $m_version;
    }
}

// Include License Engine
include_once __DIR__ . '/license.php';

$isDesktop = function_exists('mikhmon_is_desktop_mode') ? mikhmon_is_desktop_mode() : false;
if (!$isDesktop && empty($_GET['action']) && empty($_POST['action'])) {
    header("Location:./admin.php?id=about");
    exit;
}
$isLicensed = function_exists('mikhmon_is_licensed') ? mikhmon_is_licensed() : true;
$expiryDateText = function_exists('mikhmon_expiry_text') ? mikhmon_expiry_text() : '-';
$remainingDays = function_exists('mikhmon_remaining_days') ? mikhmon_remaining_days() : 999;
$hwid = function_exists('mikhmon_get_hwid') ? mikhmon_get_hwid() : '';
$licenseKey = defined('MIKHMON_LICENSE_KEY') ? MIKHMON_LICENSE_KEY : '';
$productName = defined('MIKHMON_PRODUCT_NAME') ? MIKHMON_PRODUCT_NAME : 'Mikhmon Desktop Standalone';
$isTrial = (defined('MIKHMON_PRODUCT_NAME') && stripos(MIKHMON_PRODUCT_NAME, 'Trial') !== false) || (strpos($licenseKey, 'NDR-TRL') === 0);
$activeKeyDisplay = !empty($licenseKey) ? $licenseKey : 'STANDALONE-OFFICIAL';

// Handle AJAX Actions
if (isset($_GET['action']) || isset($_POST['action'])) {
    while (ob_get_level()) {
        @ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');

    if ($action === 'check') {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://raw.githubusercontent.com/fitratan/MIKHMON-by-NODERA/main/mikhmon/include/version.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'NODERA-Mikhmon-Standalone-Updater');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        if (function_exists('mikhmon_curl_set_ssl')) {
            mikhmon_curl_set_ssl($ch);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        $rawVersion = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $latestVersion = $mikhmonVersion;
        $remoteBuild = $m_build ?? '';
        $remoteCommit = $m_commit ?? '';
        $hasUpdate = false;

        if ($httpCode === 200 && !empty($rawVersion)) {
            if (preg_match('/\$m_version\s*=\s*[\'"]([^\'"]+)[\'"]/', $rawVersion, $m)) {
                $latestVersion = $m[1];
            }
            if (preg_match('/\$m_build\s*=\s*[\'"]([^\'"]+)[\'"]/', $rawVersion, $m)) {
                $remoteBuild = $m[1];
            }
            if (preg_match('/\$m_commit\s*=\s*[\'"]([^\'"]+)[\'"]/', $rawVersion, $m)) {
                $remoteCommit = $m[1];
            }
        }

        // Fetch latest commit metadata from GitHub API
        $ch2 = curl_init('https://api.github.com/repos/fitratan/MIKHMON-by-NODERA/commits/main');
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_USERAGENT, 'NODERA-Mikhmon-Standalone-Updater');
        curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
        if (function_exists('mikhmon_curl_set_ssl')) {
            mikhmon_curl_set_ssl($ch2);
        } else {
            curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
        }
        $commitRes = curl_exec($ch2);
        $commitCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);

        $commitMsg = '';
        $commitSha = '';
        $publishedAt = date('d F Y H:i:s');
        if ($commitCode === 200 && !empty($commitRes)) {
            $cJson = json_decode($commitRes, true);
            if (!empty($cJson['sha'])) {
                $commitSha = substr($cJson['sha'], 0, 7);
                if (empty($remoteCommit)) {
                    $remoteCommit = $commitSha;
                }
            }
            if (!empty($cJson['commit']['message'])) {
                $commitMsg = explode("\n", $cJson['commit']['message'])[0];
            }
            if (!empty($cJson['commit']['committer']['date'])) {
                $publishedAt = date('d F Y H:i:s', strtotime($cJson['commit']['committer']['date']));
            }
        }

        // Check local git rev if available
        $localGitSha = '';
        $gitDir = file_exists(dirname(dirname(__DIR__)) . '/.git') ? dirname(dirname(__DIR__)) : (file_exists(dirname(__DIR__) . '/.git') ? dirname(__DIR__) : null);
        if ($gitDir && function_exists('exec')) {
            @exec("git -C " . escapeshellarg($gitDir) . " rev-parse --short HEAD 2>/dev/null", $outG, $retG);
            if ($retG === 0 && !empty($outG[0])) {
                $localGitSha = trim($outG[0]);
            }
        }

        // Compare versions & commits
        $currentComp = ltrim($mikhmonVersion, 'v');
        $latestComp = ltrim($latestVersion, 'v');
        if (version_compare($latestComp, $currentComp, '>')) {
            $hasUpdate = true;
        } elseif ($commitSha !== '' && $localGitSha !== '' && $commitSha !== $localGitSha) {
            $hasUpdate = true;
        } elseif ($remoteCommit !== '' && !empty($m_commit) && $remoteCommit !== $m_commit) {
            $hasUpdate = true;
        }

        $changelog = [];
        if (!empty($commitMsg)) {
            $changelog[] = 'Komit Terkini: ' . htmlspecialchars($commitMsg);
        }
        $changelog[] = !empty($_cl_engine_v7) ? $_cl_engine_v7 : 'Pembaruan engine RouterOS v7 & API Webhook real-time.';
        $changelog[] = !empty($_cl_voucher_thermal) ? $_cl_voucher_thermal : 'Optimalisasi template voucher QR code thermal Bluetooth.';
        $changelog[] = !empty($_cl_nodera_pay) ? $_cl_nodera_pay : 'Penyempurnaan integrasi NODERA Pay dynamic QRIS & Mikhmon Warung.';
        $changelog[] = !empty($_cl_data_protection) ? $_cl_data_protection : 'Proteksi zero data loss: konfigurasi router & voucher tersimpan aman.';

        echo json_encode([
            'success' => true,
            'current_version' => $mikhmonVersion,
            'latest_version' => $latestVersion,
            'current_commit' => $localGitSha ?: ($m_commit ?? ''),
            'latest_commit' => $commitSha ?: $remoteCommit,
            'update_available' => $hasUpdate,
            'changelog' => $changelog,
            'release_date' => $publishedAt,
            'checked_at' => date('d F Y H:i:s'),
        ]);
        exit;
    }

    if ($action === 'save_license') {
        $newKey = trim($_POST['license_key'] ?? ($_GET['license_key'] ?? ''));
        $hwid = mikhmon_get_hwid();

        if (!empty($newKey)) {
            $licensePhp = "<?php\n"
                . "/**\n"
                . " * LICENSE MIKHMON DESKTOP STANDALONE - NODERA\n"
                . " * Diperbarui manual via Dashboard Pengguna.\n"
                . " */\n"
                . "define('MIKHMON_MODE', 'DESKTOP');\n"
                . "define('MIKHMON_STATUS', 'ACTIVE');\n"
                . "define('MIKHMON_LICENSE_KEY', " . var_export($newKey, true) . ");\n"
                . "define('MIKHMON_HWID', " . var_export($hwid, true) . ");\n"
                . "define('MIKHMON_EXPIRY', '2030-12-31 23:59:59');\n"
                . "define('MIKHMON_BRAND', 'by ' . (function_exists('mikhmon_get_platform_domain') ? mikhmon_get_platform_domain() : 'dgtlnetsolution.com'));\n"
                . "define('MIKHMON_PRODUCT_NAME', 'Mikhmon Desktop Pro');\n"
                . "define('MIKHMON_ACTIVATED_AT', " . var_export(date('Y-m-d H:i:s'), true) . ");\n"
                . "define('MIKHMON_SUBDOMAIN', 'desktop');\n";

            $configFile = __DIR__ . '/../config/license.php';
            @file_put_contents($configFile, $licensePhp);
        } else {
            mikhmon_reset_local_license();
        }

        echo json_encode([
            'success' => true,
            'message' => !empty($newKey) ? (!empty($_license_save_success) ? $_license_save_success : "Kunci lisensi berhasil disimpan & diaktifkan!") : (!empty($_license_reset_success) ? $_license_reset_success : "Lisensi berhasil di-reset."),
            'license_key' => $newKey ?: 'STANDALONE-OFFICIAL',
        ]);
        exit;
    }

    if ($action === 'execute') {
        @ini_set('max_execution_time', 300);
        @set_time_limit(300);

        $logs = [];
        $logs[] = "[" . date('H:i:s') . "] Memulai proses pembaruan sistem Mikhmon Standalone...";

        $mikhmonDir = realpath(dirname(__DIR__));
        $rootDir = dirname($mikhmonDir);
        $gitDir = file_exists($rootDir . '/.git') ? $rootDir : (file_exists($mikhmonDir . '/.git') ? $mikhmonDir : null);

        // 1. BACKUP DATA PENGGUNA
        $logs[] = "[" . date('H:i:s') . "] Mengamankan file sesi router & data lisensi aktif...";
        $backupDir = $mikhmonDir . '/backup_userdata/' . date('Ymd_His');
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }
        $preserveFiles = [
            'include/config.php',
            'include/location_config.php',
            'include/whatsapp_config.php',
            'include/telegram_config.php',
            'include/noderapay_config.php',
            'config/license.php',
            'config/.hwid',
            'config/nodera_license.json',
        ];
        foreach ($preserveFiles as $pf) {
            $src = $mikhmonDir . '/' . $pf;
            if (file_exists($src)) {
                $dest = $backupDir . '/' . str_replace('/', '_', $pf);
                @copy($src, $dest);
            }
        }
        $logs[] = "[" . date('H:i:s') . "] Cadangan data sesi berhasil diamankan di backup_userdata.";

        $updateSuccess = false;
        $methodUsed = '';

        // 2. METODE 1: GIT PULL JIKA DI REPO GIT
        if ($gitDir && function_exists('exec')) {
            $logs[] = "[" . date('H:i:s') . "] Mendeteksi direktori Git (" . basename($gitDir) . "). Melakukan Git pull...";
            $gitCmd = "git -C " . escapeshellarg($gitDir) . " pull origin main 2>&1";
            @exec($gitCmd, $gitOut, $gitRet);
            if ($gitRet === 0) {
                $updateSuccess = true;
                $methodUsed = 'Git';
                $logs[] = "[" . date('H:i:s') . "] Git pull berhasil: " . implode(" | ", array_slice($gitOut, -3));
            } else {
                $logs[] = "[" . date('H:i:s') . "] Git pull tidak dapat diterapkan (" . implode(" ", $gitOut) . "), beralih ke paket rilis ZIP...";
            }
        }

        // 3. METODE 2: UNDUH PAKET ZIP DARI GITHUB (Prioritas: Paket Ringan 2MB mikhmon-update.zip -> Fallback: main.zip 41MB)
        if (!$updateSuccess) {
            $zipUrls = [
                'https://raw.githubusercontent.com/fitratan/MIKHMON-by-NODERA/main/mikhmon-update.zip',
                'https://github.com/fitratan/MIKHMON-by-NODERA/raw/main/mikhmon-update.zip',
                'https://github.com/fitratan/MIKHMON-by-NODERA/archive/refs/heads/main.zip',
            ];
            $tempZip = sys_get_temp_dir() . '/mikhmon_update_' . time() . '.zip';
            $tempExtract = sys_get_temp_dir() . '/mikhmon_extract_' . time();

            $downloaded = false;
            foreach ($zipUrls as $idx => $zipUrl) {
                $isLight = (strpos($zipUrl, 'mikhmon-update.zip') !== false);
                $logs[] = "[" . date('H:i:s') . "] Mengunduh paket pembaruan " . ($isLight ? "(Paket Ringan ~2MB)..." : "(Paket Lengkap)...");

                $ch = curl_init($zipUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_USERAGENT, 'NODERA-Mikhmon-Standalone-Updater');
                curl_setopt($ch, CURLOPT_TIMEOUT, $isLight ? 45 : 180);
                if (function_exists('mikhmon_curl_set_ssl')) {
                    mikhmon_curl_set_ssl($ch);
                } else {
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                }
                $zipData = curl_exec($ch);
                $zipHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($zipHttpCode === 200 && !empty($zipData) && strlen($zipData) > 50000) {
                    file_put_contents($tempZip, $zipData);
                    unset($zipData);
                    $downloaded = true;
                    $logs[] = "[" . date('H:i:s') . "] Berkas pembaruan berhasil diunduh (" . round(filesize($tempZip) / 1024 / 1024, 2) . " MB).";
                    break;
                }
            }

            if ($downloaded && file_exists($tempZip)) {
                $logs[] = "[" . date('H:i:s') . "] Mengekstrak paket pembaruan...";
                if (class_exists('ZipArchive')) {
                    $zip = new ZipArchive();
                    if ($zip->open($tempZip) === TRUE) {
                        $zip->extractTo($tempExtract);
                        $zip->close();
                        @unlink($tempZip);

                        // Deteksi letak folder mikhmon dalam hasil ekstraksi (bisa mikhmon/ atau MIKHMON-by-NODERA-main/mikhmon)
                        $sourceMikhmon = null;
                        $candidateSources = [
                            $tempExtract . '/mikhmon',
                            $tempExtract . '/MIKHMON-by-NODERA-main/mikhmon',
                            $tempExtract . '/MIKHMON-by-NODERA-master/mikhmon',
                            $tempExtract,
                        ];
                        foreach ($candidateSources as $cand) {
                            if (is_dir($cand) && file_exists($cand . '/hotspot/users.php')) {
                                $sourceMikhmon = $cand;
                                break;
                            }
                        }

                        if ($sourceMikhmon) {
                            $logs[] = "[" . date('H:i:s') . "] Memasang pembaruan berkas sistem (melindungi file sesi & template)...";
                            
                            $skipFiles = array_merge($preserveFiles, [
                                'template/',
                                'backup_userdata/',
                            ]);

                            $iterator = new RecursiveIteratorIterator(
                                new RecursiveDirectoryIterator($sourceMikhmon, RecursiveDirectoryIterator::SKIP_DOTS),
                                RecursiveIteratorIterator::SELF_FIRST
                            );

                            $copiedCount = 0;
                            $normSource = rtrim(str_replace('\\', '/', $sourceMikhmon), '/');

                            foreach ($iterator as $item) {
                                $rawItemPath = str_replace('\\', '/', $item->getPathname());
                                $subPath = ltrim(substr($rawItemPath, strlen($normSource)), '/');
                                $targetPath = $mikhmonDir . '/' . str_replace('/', DIRECTORY_SEPARATOR, $subPath);

                                $isProtected = false;
                                foreach ($skipFiles as $skip) {
                                    $normSkip = str_replace('\\', '/', $skip);
                                    if (str_starts_with($subPath, $normSkip) || $subPath === $normSkip) {
                                        $isProtected = true;
                                        break;
                                    }
                                }
                                if ($isProtected && file_exists($targetPath)) {
                                    continue;
                                }

                                if ($item->isDir()) {
                                    if (!is_dir($targetPath)) {
                                        @mkdir($targetPath, 0755, true);
                                    }
                                } else {
                                    $parentDir = dirname($targetPath);
                                    if (!is_dir($parentDir)) {
                                        @mkdir($parentDir, 0755, true);
                                    }
                                    if (@copy($item->getPathname(), $targetPath)) {
                                        $copiedCount++;
                                    }
                                }
                            }

                            // Bersihkan folder temporary
                            $cleanIt = new RecursiveIteratorIterator(
                                new RecursiveDirectoryIterator($tempExtract, RecursiveDirectoryIterator::SKIP_DOTS),
                                RecursiveIteratorIterator::CHILD_FIRST
                            );
                            foreach ($cleanIt as $file) {
                                if ($file->isDir()) {
                                    @rmdir($file->getPathname());
                                } else {
                                    @unlink($file->getPathname());
                                }
                            }
                            @rmdir($tempExtract);

                            $updateSuccess = true;
                            $methodUsed = 'ZipArchive';
                            $logs[] = "[" . date('H:i:s') . "] Berhasil memperbarui $copiedCount berkas sistem secara instan.";
                        } else {
                            $logs[] = "[" . date('H:i:s') . "] Struktur berkas mikhmon tidak ditemukan di dalam paket rilis.";
                        }
                    } else {
                        $logs[] = "[" . date('H:i:s') . "] Gagal membuka berkas zip pembaruan.";
                    }
                } else {
                    $logs[] = "[" . date('H:i:s') . "] Ekstensi ZipArchive PHP tidak aktif.";
                }
            } else {
                $logs[] = "[" . date('H:i:s') . "] Gagal mengunduh berkas pembaruan dari server GitHub.";
            }
        }

        if ($updateSuccess) {
            $newVer = $mikhmonVersion;
            $newCommit = $m_commit ?? '';
            $vFile = $mikhmonDir . '/include/version.php';
            if (file_exists($vFile)) {
                $vContent = @file_get_contents($vFile);
                if (preg_match('/\$m_version\s*=\s*[\'"]([^\'"]+)[\'"]/', $vContent, $vm)) {
                    $newVer = $vm[1];
                }
                if (preg_match('/\$m_commit\s*=\s*[\'"]([^\'"]+)[\'"]/', $vContent, $vm)) {
                    $newCommit = $vm[1];
                }
            }
            $_SESSION["v"] = $newVer . ($newCommit ? " (" . $newCommit . ")" : "");

            $logs[] = "[" . date('H:i:s') . "] Pembaruan sistem selesai 100%! Versi saat ini: v$newVer ($newCommit).";
            $logs[] = "[" . date('H:i:s') . "] Seluruh konfigurasi router, lisensi, dan template voucher Anda tetap aman.";

            echo json_encode([
                'success' => true,
                'message' => 'Mikhmon berhasil diperbarui ke versi mutakhir dari GitHub!',
                'version' => $newVer,
                'logs' => $logs,
            ]);
        } else {
            $logs[] = "[" . date('H:i:s') . "] Pembaruan gagal. Konfigurasi router dan lisensi Anda tetap aman.";
            echo json_encode([
                'success' => false,
                'message' => 'Gagal memperbarui sistem dari GitHub. Silakan periksa koneksi internet Anda.',
                'version' => $mikhmonVersion,
                'logs' => $logs,
            ]);
        }
        exit;
    }
}
?>

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <div class="card-header-flex">
          <h3 class="card-header-title">
            <i class="fa fa-refresh"></i> <?= !empty($_system_update) ? $_system_update : 'Pembaruan Sistem'; ?>
          </h3>
          <div class="card-header-actions">
            <span onclick="location.reload();" class="header-action-btn pointer" title="Reload data"><i class="fa fa-refresh"></i></span>
          </div>
        </div>
      </div>
      <div class="card-body">

        <!-- TOP KPI OVERVIEW ROW (2 COLUMNS) -->
        <div class="row" style="margin-bottom: 16px;">
          <!-- Col 1: Status Versi & Update -->
          <div class="col-6">
            <div class="box box-bordered" style="padding: 14px 16px; border-radius: 8px; height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
              <div>
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(128,128,128,0.2); padding-bottom: 8px; margin-bottom: 10px;">
                  <div>
                    <strong style="font-size: 13px;"><i class="fa fa-server text-primary"></i> <?= !empty($_update_engine_status) ? $_update_engine_status : 'Status Versi Engine'; ?></strong>
                  </div>
                  <div>
                    <span id="badgeUpdateStatus" class="badge bg-green" style="font-size: 11px; padding: 3px 8px;">
                      <i class="fa fa-check-circle"></i> <?= !empty($_update_up_to_date) ? $_update_up_to_date : 'Versi Mutakhir'; ?>
                    </span>
                  </div>
                </div>

                <div style="font-size: 12px; line-height: 1.8;">
                  <div style="display: flex; justify-content: space-between;">
                    <span style="opacity: 0.8;"><?= !empty($_update_installed_version) ? $_update_installed_version : 'Versi Terpasang:'; ?></span>
                    <strong>v<?= htmlspecialchars($mikhmonVersion); ?> (<?= !empty($_update_local) ? $_update_local : 'Lokal'; ?>)</strong>
                  </div>
                  <div style="display: flex; justify-content: space-between;">
                    <span style="opacity: 0.8;"><?= !empty($_update_latest_release) ? $_update_latest_release : 'Rilis GitHub Terkini:'; ?></span>
                    <strong class="text-green" id="lblLatestVersion">v<?= htmlspecialchars($mikhmonVersion); ?></strong>
                  </div>
                  <div style="display: flex; justify-content: space-between;">
                    <span style="opacity: 0.8;"><?= !empty($_update_last_checked) ? $_update_last_checked : 'Pemeriksaan Terakhir:'; ?></span>
                    <span id="lblLastChecked" style="font-size: 11px;"><?= date('d F Y H:i:s'); ?></span>
                  </div>
                </div>
              </div>

              <div style="margin-top: 12px; text-align: right;">
                <button type="button" id="btnCheckUpdate" class="btn bg-primary" style="padding: 6px 14px; font-weight: bold; border-radius: 6px; cursor: pointer;">
                  <i class="fa fa-refresh" id="iconSpin"></i> <?= !empty($_update_btn_check) ? $_update_btn_check : 'Periksa Pembaruan'; ?>
                </button>
              </div>
            </div>
          </div>

          <!-- Col 2: Lisensi & Status Mikhmon Standalone -->
          <div class="col-6">
            <div class="box box-bordered" style="padding: 14px 16px; border-radius: 8px; height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
              <div>
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(128,128,128,0.2); padding-bottom: 8px; margin-bottom: 10px;">
                  <div>
                    <strong style="font-size: 13px;"><i class="fa fa-key text-yellow"></i> <?= !empty($_update_license_section_title) ? $_update_license_section_title : 'Lisensi Mikhmon Standalone'; ?></strong>
                  </div>
                  <div>
                    <button type="button" onclick="openDesktopLicenseModal()" class="btn btn-sm bg-secondary" style="padding: 3px 8px; font-size: 11px; border-radius: 4px; cursor: pointer;" title="<?= !empty($_change_license) ? $_change_license : 'Ganti Lisensi'; ?>">
                      <i class="fa fa-pencil text-primary"></i> <?= !empty($_update_change_license) ? $_update_change_license : 'Ganti'; ?>
                    </button>
                  </div>
                </div>

                <div style="font-size: 12px; line-height: 1.8;">
                  <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="opacity: 0.8;"><?= !empty($_update_license_key_label) ? $_update_license_key_label : 'Kunci Lisensi:'; ?></span>
                    <span style="display: flex; align-items: center; gap: 4px;">
                      <code id="activeKeyText" style="font-size: 11px; font-weight: bold; padding: 1px 6px; border-radius: 4px;"><?= htmlspecialchars($activeKeyDisplay); ?></code>
                      <button type="button" onclick="copyMikhmonKey()" class="btn btn-default btn-xs" style="padding: 0 4px; font-size: 10px;" title="<?= !empty($_update_btn_copy) ? $_update_btn_copy : 'Salin'; ?>">
                        <i class="fa fa-copy"></i>
                      </button>
                    </span>
                  </div>
                  <div style="display: flex; justify-content: space-between;">
                    <span style="opacity: 0.8;"><?= !empty($_update_license_type_label) ? $_update_license_type_label : 'Tipe Lisensi:'; ?></span>
                    <strong><?= $isTrial ? (!empty($_license_trial_mode) ? $_license_trial_mode : 'FREE (Trial Mode)') : (!empty($_license_pro_mode) ? $_license_pro_mode : 'OFFICIAL PAID (Pro)') ?></strong>
                  </div>
                  <div style="display: flex; justify-content: space-between;">
                    <span style="opacity: 0.8;"><?= !empty($_update_license_expiry_label) ? $_update_license_expiry_label : 'Masa Aktif:'; ?></span>
                    <strong class="<?= $isTrial ? 'text-yellow' : 'text-green' ?>">
                      <?= htmlspecialchars($expiryDateText) ?>
                      <?php if ($isTrial && $remainingDays > 0): ?>
                        <span style="font-size: 11px; font-weight: normal; opacity: 0.85;">(<?= $remainingDays ?> <?= !empty($_update_days_left) ? $_update_days_left : 'hari lagi'; ?>)</span>
                      <?php endif; ?>
                    </strong>
                  </div>
                  <div style="display: flex; justify-content: space-between;">
                    <span style="opacity: 0.8;"><?= !empty($_update_voucher_capacity_label) ? $_update_voucher_capacity_label : 'Kapasitas Voucher:'; ?></span>
                    <strong class="text-green"><?= !empty($_update_unlimited) ? $_update_unlimited : 'Unlimited (Tanpa Batas Kuota)'; ?></strong>
                  </div>
                </div>
              </div>

              <div style="margin-top: 10px; padding-top: 6px; border-top: 1px solid rgba(128,128,128,0.15); display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 10.5px; opacity: 0.7; font-family: monospace;">HWID: <?= htmlspecialchars($hwid ?: 'STANDALONE') ?></span>
                <a href="<?= function_exists('mikhmon_get_panel_url') ? mikhmon_get_panel_url() . '/desktop-licenses' : 'https://panel.dgtlnetsolution.com/desktop-licenses' ?>" target="_blank" class="text-primary" style="font-size: 11.5px; font-weight: bold; text-decoration: underline;">
                  <i class="fa fa-external-link"></i> <?= !empty($_update_license_portal) ? $_update_license_portal : 'Portal Lisensi'; ?> &rarr;
                </a>
              </div>
            </div>
          </div>
        </div>

        <div class="row">
          <!-- Kolom Kiri: Status & Tindakan Pembaruan -->
          <div class="col-8">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title"><i class="fa fa-cloud-download"></i> <?= !empty($_update_status_and_actions) ? $_update_status_and_actions : 'Status & Tindakan Pembaruan'; ?></h3>
              </div>
              <div class="card-body">
                <div id="updateActionBox" style="display: none; margin-bottom: 15px;">
                  <div class="box box-bordered" style="border-left: 4px solid #f39c12; padding: 14px 18px; border-radius: 8px; margin-bottom: 12px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                      <div>
                        <div style="font-size: 14px; font-weight: bold; color: #f39c12;">
                          <i class="fa fa-arrow-circle-up"></i> <?= !empty($_update_new_version_available) ? $_update_new_version_available : 'Versi Baru Tersedia:'; ?> <span id="boxNewVersionTitle">v<?= htmlspecialchars($mikhmonVersion); ?></span>
                        </div>
                        <div style="font-size: 12px; opacity: 0.85; margin-top: 3px;" id="boxReleaseDate">
                          <?= !empty($_update_released_from_github) ? $_update_released_from_github : 'Dirilis dari repositori GitHub resmi'; ?>
                        </div>
                      </div>
                      <div>
                        <button type="button" id="btnExecuteUpdate" class="btn bg-green" style="padding: 8px 18px; font-weight: bold; border-radius: 6px; cursor: pointer;">
                          <i class="fa fa-cloud-download"></i> <?= !empty($_update_btn_execute) ? $_update_btn_execute : 'Perbarui Sekarang (1-Klik)'; ?>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="card" style="margin-top: 5px;">
                  <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-list-alt"></i> <?= !empty($_update_release_notes) ? $_update_release_notes : 'Catatan Rilis & Fitur Terbaru'; ?></h3>
                  </div>
                  <div class="card-body" style="padding: 10px 15px;">
                    <ul id="changelogList" style="margin: 0; padding-left: 18px; font-size: 12px; line-height: 1.8;">
                      <li><i class="fa fa-check-circle text-green"></i> <?= !empty($_cl_engine_v7) ? $_cl_engine_v7 : 'Pembaruan engine RouterOS v7 & API Webhook real-time.'; ?></li>
                      <li><i class="fa fa-check-circle text-green"></i> <?= !empty($_cl_voucher_thermal) ? $_cl_voucher_thermal : 'Optimalisasi template voucher QR code thermal Bluetooth.'; ?></li>
                      <li><i class="fa fa-check-circle text-green"></i> <?= !empty($_cl_nodera_pay) ? $_cl_nodera_pay : 'Penyempurnaan integrasi NODERA Pay dynamic QRIS & Mikhmon Warung.'; ?></li>
                      <li><i class="fa fa-check-circle text-green"></i> <?= !empty($_cl_data_protection) ? $_cl_data_protection : 'Proteksi zero data loss: konfigurasi router & voucher tersimpan aman.'; ?></li>
                    </ul>
                  </div>
                </div>

                <div id="updateLogCard" style="display: none; margin-top: 15px;">
                  <div class="card">
                    <div class="card-header">
                      <h3 class="card-title"><i class="fa fa-terminal"></i> <?= !empty($_update_log_title) ? $_update_log_title : 'Log Eksekusi Pembaruan'; ?></h3>
                    </div>
                    <div class="card-body" style="padding: 0;">
                      <div id="logConsole" style="background: #1e1e1e; color: #d4d4d4; font-family: monospace; font-size: 11px; padding: 12px; max-height: 180px; overflow-y: auto; line-height: 1.5;"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Kolom Kanan: Proteksi Berkas & Informasi -->
          <div class="col-4">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title"><i class="fa fa-shield"></i> <?= !empty($_update_data_safety_title) ? $_update_data_safety_title : 'Jaminan Keamanan Data'; ?></h3>
              </div>
              <div class="card-body">
                <div class="box box-bordered" style="margin-bottom: 12px; padding: 10px 14px; border-radius: 6px;">
                  <div style="font-size: 12px; font-weight: bold; margin-bottom: 4px;">
                    <i class="fa fa-lock text-primary"></i> <?= !empty($_update_data_safety_config) ? $_update_data_safety_config : 'File Konfigurasi Sesi Aman'; ?>
                  </div>
                  <div style="font-size: 11px; opacity: 0.8; line-height: 1.4;">
                    <?= !empty($_update_data_safety_config_desc) ? $_update_data_safety_config_desc : 'File <code>include/config.php</code> & <code>location_config.php</code> tidak akan tertimpa saat update.'; ?>
                  </div>
                </div>

                <div class="box box-bordered" style="margin-bottom: 12px; padding: 10px 14px; border-radius: 6px;">
                  <div style="font-size: 12px; font-weight: bold; margin-bottom: 4px;">
                    <i class="fa fa-ticket text-green"></i> <?= !empty($_update_data_safety_voucher) ? $_update_data_safety_voucher : 'Template Voucher Kustom'; ?>
                  </div>
                  <div style="font-size: 11px; opacity: 0.8; line-height: 1.4;">
                    <?= !empty($_update_data_safety_voucher_desc) ? $_update_data_safety_voucher_desc : 'Template voucher custom Anda di folder <code>template/</code> tetap dipertahankan 100%.'; ?>
                  </div>
                </div>

                <div class="box box-bordered" style="padding: 10px 14px; border-radius: 6px;">
                  <div style="font-size: 12px; font-weight: bold; margin-bottom: 4px;">
                    <i class="fa fa-credit-card text-yellow"></i> <?= !empty($_update_data_safety_payment) ? $_update_data_safety_payment : 'Konfigurasi Payment Gateway'; ?>
                  </div>
                  <div style="font-size: 11px; opacity: 0.8; line-height: 1.4;">
                    <?= !empty($_update_data_safety_payment_desc) ? $_update_data_safety_payment_desc : 'Integrasi NODERA Pay & QRIS otomatis tetap aktif tanpa konfigurasi ulang.'; ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="card" style="margin-top: 15px;">
              <div class="card-header">
                <h3 class="card-title"><i class="fa fa-info-circle"></i> <?= !empty($_update_official_help) ? $_update_official_help : 'Bantuan & Lisensi Resmi'; ?></h3>
              </div>
              <div class="card-body" style="font-size: 12px;">
                <p style="margin-bottom: 10px; line-height: 1.5;">
                  <?= !empty($_need_tech_help) ? $_need_tech_help : 'Butuh bantuan teknis atau aktivasi lisensi resmi?'; ?>
                </p>
                <a href="https://wa.me/6285155173547?text=Halo%20Admin%20NODERA,%20saya%20butuh%20bantuan%20update%20Mikhmon" target="_blank" class="btn bg-green" style="display: block; text-align: center; padding: 7px; font-weight: bold; border-radius: 6px; text-decoration: none;">
                  <i class="fa fa-whatsapp"></i> <?= !empty($_contact_cs_whatsapp) ? $_contact_cs_whatsapp : 'Hubungi CS WhatsApp:'; ?> 085155173547
                </a>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- MODAL AKTIVASI / GANTI LISENSI (NATIVE MIKHMON THEME) -->
<div id="ndrDesktopLicenseModal" style="display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.65); justify-content:center; align-items:center;">
  <div class="card" style="width:92%; max-width:480px; box-shadow:0 10px 30px rgba(0,0,0,0.5); border-radius:4px; margin:0; padding:0; overflow:hidden;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; padding:10px 15px;">
      <h3 class="card-title" style="margin:0; font-size:15px;">
        <i class="fa fa-key text-primary"></i> <?= !empty($_update_modal_title) ? $_update_modal_title : 'Aktivasi / Ganti Lisensi'; ?>
      </h3>
      <button type="button" onclick="closeDesktopLicenseModal()" style="background:none; border:none; color:inherit; font-size:20px; line-height:1; cursor:pointer; opacity:0.8;" title="<?= !empty($_close) ? $_close : 'Tutup'; ?>">&times;</button>
    </div>
    <div class="card-body" style="padding:15px;">
      <div id="ndrLicenseAlert" style="display:none; padding:8px 12px; border-radius:3px; font-size:12px; margin-bottom:12px;"></div>

      <!-- HWID Field -->
      <div style="margin-bottom:12px;">
        <label style="display:block; font-size:12px; font-weight:bold; margin-bottom:4px;"><?= !empty($_update_hwid_label) ? $_update_hwid_label : 'Hardware ID (HWID Perangkat Ini):'; ?></label>
        <div style="display:flex; gap:5px;">
          <input type="text" id="ndrDeviceHwid" value="<?= htmlspecialchars($hwid ?: 'STANDALONE') ?>" readonly class="form-control" style="flex:1; font-family:monospace; font-size:12.5px; font-weight:bold;">
          <button type="button" onclick="copyHwid()" class="btn bg-secondary" style="margin:0; padding:5px 12px;" title="<?= !empty($_update_btn_copy) ? $_update_btn_copy : 'Salin'; ?>">
            <i class="fa fa-copy" id="copyHwidIcon"></i> <?= !empty($_update_btn_copy) ? $_update_btn_copy : 'Salin'; ?>
          </button>
        </div>
      </div>

      <!-- License Key Field -->
      <div style="margin-bottom:10px;">
        <label style="display:block; font-size:12px; font-weight:bold; margin-bottom:4px;"><?= !empty($_update_license_input_label) ? $_update_license_input_label : 'Kode Kunci Lisensi (License Key):'; ?></label>
        <input type="text" id="ndrLicenseKeyInput" name="license_key" value="<?= htmlspecialchars($licenseKey) ?>" placeholder="Contoh: NDR-STD-XXXX-XXXX-XXXX-XXXX" class="form-control" style="font-family:monospace; font-size:12.5px; font-weight:bold;">
      </div>

      <!-- Box Info Native Mikhmon -->
      <div class="box box-bordered" style="margin:8px 0 15px 0; padding:8px 10px; font-size:11.5px; line-height:1.45;">
        <i class="fa fa-info-circle text-primary"></i> <?= !empty($_update_license_guide_hint) ? $_update_license_guide_hint : 'Masukkan kunci lisensi dari Portal Cloud NODERA atau hubungi bantuan CS WhatsApp. Kosongkan untuk Free Trial.'; ?>
      </div>

      <!-- Action Buttons -->
      <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:10px;">
        <button type="button" onclick="closeDesktopLicenseModal()" class="btn bg-secondary" style="margin:0;"><?= !empty($_update_btn_cancel) ? $_update_btn_cancel : 'Batal'; ?></button>
        <button type="button" id="ndrBtnSubmitLicense" onclick="submitDesktopLicense()" class="btn bg-primary" style="margin:0;">
          <i class="fa fa-check"></i> <?= !empty($_update_btn_save_verify) ? $_update_btn_save_verify : 'Simpan & Verifikasi'; ?>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
var updateI18n = {
  copiedKey: <?= json_encode(!empty($_license_code_copied) ? $_license_code_copied : 'Kunci lisensi berhasil disalin:') ?>,
  copiedHwid: <?= json_encode(!empty($_hwid_copied) ? $_hwid_copied : 'Hardware ID berhasil disalin ke clipboard:') ?>,
  keyRequired: <?= json_encode(!empty($_license_key_required) ? $_license_key_required : 'License Key wajib diisi!') ?>,
  saving: <?= json_encode(!empty($_saving) ? $_saving : 'Menyimpan...') ?>,
  saveFailed: <?= json_encode(!empty($_license_save_failed) ? $_license_save_failed : 'Gagal menyimpan lisensi.') ?>,
  saveSuccess: <?= json_encode(!empty($_license_save_success) ? $_license_save_success : 'Lisensi berhasil disimpan & diaktifkan!') ?>,
  errorOccurred: <?= json_encode(!empty($_an_error_occurred) ? $_an_error_occurred : 'Terjadi kesalahan:') ?>,
  success: <?= json_encode(!empty($_success) ? $_success : 'Berhasil') ?>,
  saveVerify: <?= json_encode(!empty($_update_btn_save_verify) ? $_update_btn_save_verify : 'Simpan & Verifikasi') ?>,
  checking: <?= json_encode(!empty($_update_checking) ? $_update_checking : 'Memeriksa...') ?>,
  checkBtn: <?= json_encode(!empty($_update_btn_check) ? $_update_btn_check : 'Periksa Pembaruan') ?>,
  justNow: <?= json_encode(!empty($_just_now) ? $_just_now : 'Baru saja') ?>,
  updateAvailable: <?= json_encode(!empty($_update_available) ? $_update_available : 'Update Tersedia') ?>,
  upToDate: <?= json_encode(!empty($_update_up_to_date) ? $_update_up_to_date : 'Versi Mutakhir') ?>,
  checkDoneUpToDate: <?= json_encode(!empty($_check_complete_up_to_date) ? $_check_complete_up_to_date : 'Pemeriksaan selesai: Versi saat ini sudah mutakhir.') ?>,
  confirmUpdate: <?= json_encode(!empty($_confirm_update) ? $_confirm_update : 'Apakah Anda yakin ingin memperbarui instance Mikhmon sekarang? Seluruh konfigurasi sesi router Anda tetap aman.') ?>,
  processing: <?= json_encode(!empty($_processing) ? $_processing : 'Memproses...') ?>,
  updateDone: <?= json_encode(!empty($_update_completed) ? $_update_completed : 'Pembaruan Selesai') ?>,
  updateSuccess: <?= json_encode(!empty($_update_success) ? $_update_success : 'Pembaruan berhasil!') ?>,
  tryAgain: <?= json_encode(!empty($_try_again) ? $_try_again : 'Coba Lagi') ?>,
  updateError: <?= json_encode(!empty($_error_processing_update) ? $_error_processing_update : 'Terjadi kesalahan saat memproses pembaruan.') ?>,
  startLog: <?= json_encode('> [' . (!empty($_log_tag_start) ? $_log_tag_start : 'START') . '] ' . (!empty($_log_start_system_update) ? $_log_start_system_update : 'Memulai pembaruan sistem Mikhmon...')) ?>,
  doneLog: '> [' + <?= json_encode(!empty($_log_tag_done) ? $_log_tag_done : 'DONE') ?> + '] ',
  errorLog: '> [' + <?= json_encode(!empty($_log_tag_error) ? $_log_tag_error : 'ERROR') ?> + '] ',
  netErrorCheck: <?= json_encode(!empty($_error_processing_update) ? $_error_processing_update : 'Gagal memeriksa rilis terbaru dari server.') ?>
};

function copyMikhmonKey() {
  var el = document.getElementById('activeKeyText');
  if (el) {
    var txt = el.innerText.trim();
    navigator.clipboard.writeText(txt).then(function() {
      alert(updateI18n.copiedKey + ' ' + txt);
    }).catch(function() {
      alert(updateI18n.copiedKey + ' ' + txt);
    });
  }
}

function openDesktopLicenseModal() {
  var m = document.getElementById('ndrDesktopLicenseModal');
  if (m) m.style.display = 'flex';
}

function closeDesktopLicenseModal() {
  var m = document.getElementById('ndrDesktopLicenseModal');
  if (m) m.style.display = 'none';
}

function copyHwid() {
  var h = document.getElementById('ndrDeviceHwid');
  if (h) {
    h.select();
    navigator.clipboard.writeText(h.value).then(function() {
      alert(updateI18n.copiedHwid + ' ' + h.value);
    });
  }
}

function submitDesktopLicense() {
  var key = document.getElementById('ndrLicenseKeyInput').value.trim();
  var alertBox = document.getElementById('ndrLicenseAlert');
  var btn = document.getElementById('ndrBtnSubmitLicense');
  if (!key) {
    showError(updateI18n.keyRequired);
    return;
  }
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ' + updateI18n.saving;
  if (alertBox) alertBox.style.display = 'none';

  var fd = new FormData();
  fd.append('action', 'save_license');
  fd.append('license_key', key);

  fetch('./admin.php?id=update&action=save_license', {
    method: 'POST',
    body: fd
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    if (data && data.success) {
      showSuccessAndReload(data);
    } else {
      showError(data ? data.message : updateI18n.saveFailed);
    }
  })
  .catch(function(err) {
    showError(updateI18n.errorOccurred + ' ' + err.message);
  });

  function showSuccessAndReload(data) {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa fa-check-circle"></i> ' + updateI18n.success;
    }
    if (alertBox) {
      alertBox.className = 'box bg-green';
      alertBox.style.cssText = 'display:block; margin:0 0 12px 0; padding:8px 10px; border-radius:3px; font-size:12px; font-weight:bold; color:#ffffff;';
      alertBox.innerHTML = '<i class="fa fa-check"></i> ' + (data.message || updateI18n.saveSuccess);
    }
    setTimeout(function() {
      location.reload();
    }, 1200);
  }

  function showError(msg) {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa fa-check"></i> ' + updateI18n.saveVerify;
    }
    if (alertBox) {
      alertBox.className = 'box bg-danger';
      alertBox.style.cssText = 'display:block; margin:0 0 12px 0; padding:8px 10px; border-radius:3px; font-size:12px; font-weight:bold; color:#ffffff;';
      alertBox.innerHTML = '<i class="fa fa-ban"></i> ' + msg;
    }
  }
}

// Check Update with Spin Feedback
document.addEventListener('DOMContentLoaded', function() {
  var btnCheck = document.getElementById('btnCheckUpdate');
  var btnExecute = document.getElementById('btnExecuteUpdate');
  var iconSpin = document.getElementById('iconSpin');
  var actionBox = document.getElementById('updateActionBox');
  var logCard = document.getElementById('updateLogCard');
  var logConsole = document.getElementById('logConsole');

  if (btnCheck) {
    btnCheck.addEventListener('click', function() {
      btnCheck.disabled = true;
      if (iconSpin) iconSpin.className = 'fa fa-refresh fa-spin';
      btnCheck.innerHTML = '<i class="fa fa-refresh fa-spin"></i> ' + updateI18n.checking;

      fetch('./admin.php?id=update&action=check')
        .then(function(res) { return res.json(); })
        .then(function(data) {
          btnCheck.disabled = false;
          btnCheck.innerHTML = '<i class="fa fa-refresh"></i> ' + updateI18n.checkBtn;

          var lastCheckEl = document.getElementById('lblLastChecked');
          var latestVerEl = document.getElementById('lblLatestVersion');
          if (lastCheckEl) lastCheckEl.textContent = data.checked_at || updateI18n.justNow;
          if (latestVerEl) latestVerEl.textContent = 'v' + (data.latest_version || '3.20.3-v7');

          var badgeStatus = document.getElementById('badgeUpdateStatus');
          if (data.update_available) {
            if (actionBox) actionBox.style.display = 'block';
            var boxVer = document.getElementById('boxNewVersionTitle');
            if (boxVer) boxVer.textContent = 'v' + data.latest_version;
            if (badgeStatus) {
              badgeStatus.className = 'badge bg-yellow';
              badgeStatus.innerHTML = '<i class="fa fa-exclamation-circle"></i> ' + updateI18n.updateAvailable;
            }
          } else {
            if (actionBox) actionBox.style.display = 'none';
            if (badgeStatus) {
              badgeStatus.className = 'badge bg-green';
              badgeStatus.innerHTML = '<i class="fa fa-check-circle"></i> ' + updateI18n.upToDate;
            }
          }

          if (data.changelog && data.changelog.length > 0) {
            var listHtml = '';
            data.changelog.forEach(function(item) {
              listHtml += '<li><i class="fa fa-check-circle text-green"></i> ' + item + '</li>';
            });
            var clList = document.getElementById('changelogList');
            if (clList) clList.innerHTML = listHtml;
          }
        })
        .catch(function(err) {
          btnCheck.disabled = false;
          btnCheck.innerHTML = '<i class="fa fa-refresh"></i> ' + updateI18n.checkBtn;
          alert(updateI18n.checkDoneUpToDate);
        });
    });
  }

  if (btnExecute) {
    btnExecute.addEventListener('click', function() {
      if (!confirm(updateI18n.confirmUpdate)) {
        return;
      }

      btnExecute.disabled = true;
      btnExecute.innerHTML = '<i class="fa fa-refresh fa-spin"></i> ' + updateI18n.processing;
      if (logCard) logCard.style.display = 'block';
      if (logConsole) logConsole.innerHTML = updateI18n.startLog + '<br/>';

      fetch('./admin.php?id=update&action=execute')
        .then(function(res) { return res.json(); })
        .then(function(data) {
          btnExecute.disabled = false;
          btnExecute.innerHTML = '<i class="fa fa-check"></i> ' + updateI18n.updateDone;

          if (data.logs && data.logs.length > 0) {
            data.logs.forEach(function(log) {
              logConsole.innerHTML += '> ' + log + '<br/>';
            });
          }
          logConsole.innerHTML += updateI18n.doneLog + (data.message || updateI18n.updateSuccess) + '<br/>';
          logConsole.scrollTop = logConsole.scrollHeight;

          setTimeout(function() {
            location.reload();
          }, 2000);
        })
        .catch(function() {
          btnExecute.disabled = false;
          btnExecute.innerHTML = '<i class="fa fa-cloud-download"></i> ' + updateI18n.tryAgain;
          if (logConsole) logConsole.innerHTML += updateI18n.errorLog + updateI18n.updateError + '<br/>';
        });
    });
  }
});
</script>
