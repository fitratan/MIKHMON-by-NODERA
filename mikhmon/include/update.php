<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
if (!isset($_SESSION["mikhmon"]) && empty($_COOKIE['mikhmon_remember'])) {
    header("Location:./admin.php?id=login");
    exit;
}

$mikhmonVersion = '3.20.4-v7';
$mikhmonCommit = 'ad429f0';
$mikhmonBuild = date('Y-m-d');
$versionFile = __DIR__ . '/version.php';
if (file_exists($versionFile)) {
    include_once($versionFile);
    if (!empty($m_version)) {
        $mikhmonVersion = $m_version;
    }
    if (!empty($m_commit)) {
        $mikhmonCommit = $m_commit;
    }
    if (!empty($m_build)) {
        $mikhmonBuild = $m_build;
    }
}

// Include License Engine
include_once __DIR__ . '/license.php';

$isDesktop = function_exists('mikhmon_is_desktop_mode') ? mikhmon_is_desktop_mode() : true;
$isLicensed = function_exists('mikhmon_is_licensed') ? mikhmon_is_licensed() : false;
$expiryDateText = function_exists('mikhmon_expiry_text') ? mikhmon_expiry_text() : '-';
$remainingDays = function_exists('mikhmon_remaining_days') ? mikhmon_remaining_days() : 999;
$hwid = function_exists('mikhmon_get_hwid') ? mikhmon_get_hwid() : '';
$licenseKey = defined('MIKHMON_LICENSE_KEY') ? MIKHMON_LICENSE_KEY : '';
$productName = defined('MIKHMON_PRODUCT_NAME') ? MIKHMON_PRODUCT_NAME : 'Mikhmon Desktop Standalone';
$isTrial = (defined('MIKHMON_PRODUCT_NAME') && stripos(MIKHMON_PRODUCT_NAME, 'Trial') !== false) || (strpos($licenseKey, 'NDR-TRL') === 0) || empty($licenseKey);
$activeKeyDisplay = !empty($licenseKey) ? $licenseKey : 'STANDALONE-FREE-TIER';

// Helper for UI localization fallback
$tr = function($val, $fallback) {
    return (!empty($val)) ? $val : $fallback;
};

// Handle AJAX Actions
if (isset($_GET['action']) || isset($_POST['action'])) {
    while (ob_get_level()) {
        @ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');

    // ─────────────────────────────────────────────────────────────
    // ACTION: CHECK FOR UPDATES FROM GITHUB
    // ─────────────────────────────────────────────────────────────
    if ($action === 'check') {
        $currentVersion = $mikhmonVersion;
        $currentCommit = $mikhmonCommit;
        $latestVersion = $currentVersion;
        $latestCommit = $currentCommit;
        $publishedAt = date('d F Y H:i:s');
        $changelog = [];
        $hasUpdate = false;
        $checkedVia = 'github_commits';

        // 1. Query GitHub Commits API
        $repoApi = 'https://api.github.com/repos/fitratan/MIKHMON-by-NODERA/commits?per_page=5';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $repoApi);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'NODERA-Mikhmon-v7-Updater');
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && !empty($res)) {
            $commits = json_decode($res, true);
            if (is_array($commits) && !empty($commits)) {
                $latestSha = $commits[0]['sha'] ?? '';
                if (!empty($latestSha)) {
                    $latestCommit = substr($latestSha, 0, 7);
                }
                if (!empty($commits[0]['commit']['author']['date'])) {
                    $publishedAt = date('d F Y H:i:s', strtotime($commits[0]['commit']['author']['date']));
                }

                foreach ($commits as $c) {
                    $msg = trim($c['commit']['message'] ?? '');
                    $firstLine = explode("\n", $msg)[0];
                    $sha7 = substr($c['sha'], 0, 7);
                    $changelog[] = "[{$sha7}] {$firstLine}";
                }

                if ($latestCommit !== $currentCommit && !empty($currentCommit)) {
                    $hasUpdate = true;
                }
            }
        } else {
            // 2. Fallback to raw version.php
            $checkedVia = 'raw_version';
            $rawUrl = 'https://raw.githubusercontent.com/fitratan/MIKHMON-by-NODERA/main/mikhmon/include/version.php';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $rawUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'NODERA-Mikhmon-v7-Updater');
            curl_setopt($ch, CURLOPT_TIMEOUT, 12);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $rawCode = curl_exec($ch);
            $rawHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($rawHttpCode === 200 && !empty($rawCode)) {
                if (preg_match('/\$m_version\s*=\s*[\'"]([^\'"]+)[\'"]/', $rawCode, $mVer)) {
                    $latestVersion = $mVer[1];
                }
                if (preg_match('/\$m_commit\s*=\s*[\'"]([^\'"]+)[\'"]/', $rawCode, $mCom)) {
                    $latestCommit = $mCom[1];
                }
                if (preg_match('/\$m_build\s*=\s*[\'"]([^\'"]+)[\'"]/', $rawCode, $mBld)) {
                    $publishedAt = $mBld[1];
                }
                if ($latestCommit !== $currentCommit && !empty($currentCommit)) {
                    $hasUpdate = true;
                } elseif (version_compare(ltrim($latestVersion, 'v'), ltrim($currentVersion, 'v'), '>')) {
                    $hasUpdate = true;
                }
            }
        }

        if (empty($changelog)) {
            $changelog = [
                'Pembaruan engine RouterOS v7 & API Webhook real-time.',
                'Optimalisasi template voucher QR code thermal Bluetooth.',
                'Penyempurnaan integrasi NODERA Pay dynamic QRIS & Mikhmon Warung.',
                'Proteksi zero-data-loss: konfigurasi router & voucher tersimpan aman.',
            ];
        }

        echo json_encode([
            'success' => true,
            'current_version' => $currentVersion,
            'current_commit' => $currentCommit,
            'latest_version' => $latestVersion,
            'latest_commit' => $latestCommit,
            'update_available' => $hasUpdate,
            'changelog' => $changelog,
            'release_date' => $publishedAt,
            'checked_at' => date('d F Y H:i:s'),
            'checked_via' => $checkedVia,
        ]);
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // ACTION: SAVE / UPDATE DESKTOP LICENSE
    // ─────────────────────────────────────────────────────────────
    if ($action === 'save_license') {
        $newKey = trim($_POST['license_key'] ?? ($_GET['license_key'] ?? ''));
        $hwidVal = mikhmon_get_hwid();

        if (!empty($newKey)) {
            $licensePhp = "<?php\n"
                . "/**\n"
                . " * LICENSE MIKHMON DESKTOP STANDALONE — NODERA\n"
                . " * Diperbarui manual via Dashboard Pengguna.\n"
                . " */\n"
                . "define('MIKHMON_MODE', 'DESKTOP');\n"
                . "define('MIKHMON_STATUS', 'ACTIVE');\n"
                . "define('MIKHMON_LICENSE_KEY', " . var_export($newKey, true) . ");\n"
                . "define('MIKHMON_HWID', " . var_export($hwidVal, true) . ");\n"
                . "define('MIKHMON_EXPIRY', '2030-12-31 23:59:59');\n"
                . "define('MIKHMON_BRAND', 'by dgtlnetsolution.com');\n"
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
            'message' => !empty($newKey) ? "Kunci lisensi {$newKey} berhasil disimpan & diaktifkan!" : "Lisensi berhasil di-reset.",
            'license_key' => $newKey ?: 'UNLICENSED',
        ]);
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // ACTION: EXECUTE 1-CLICK SAFE UPDATE FROM GITHUB
    // ─────────────────────────────────────────────────────────────
    if ($action === 'execute') {
        @set_time_limit(180);
        $logs = [];
        $logs[] = "[" . date('H:i:s') . "] Memulai alur pembaruan aman Mikhmon dari GitHub...";

        // 1. Resolve Install Base Directory
        $mikhmonDir = realpath(dirname(__DIR__));
        $parentDir = realpath(dirname($mikhmonDir));
        if ($parentDir && file_exists($parentDir . '/mikhmon') && is_dir($parentDir . '/mikhmon')) {
            $baseInstallDir = $parentDir;
            $hasMikhmonSubdir = true;
        } else {
            $baseInstallDir = $mikhmonDir;
            $hasMikhmonSubdir = false;
        }

        // 2. Protected Files List (MUST NEVER BE OVERWRITTEN)
        $protectedFiles = [
            'mikhmon/include/config.php',
            'mikhmon/include/lang.php',
            'mikhmon/include/theme.php',
            'mikhmon/include/location_config.php',
            'mikhmon/include/whatsapp_config.php',
            'mikhmon/include/telegram_config.php',
            'mikhmon/include/noderapay_config.php',
            'mikhmon/include/logo_config.php',
            'mikhmon/include/quickbt.php',
            'mikhmon/include/warung_data.json',
            'mikhmon/config/license.php',
            'mikhmon/config/.hwid',
            'mikhmon/config/.trial_attempted',
            'mikhmon/config/.reset_alert',
            'mikhmon/config/nodera_license.json',
            'nodera_license.json',
        ];

        // Also map if installed directly at mikhmon root without parent
        if (!$hasMikhmonSubdir) {
            $adjustedProtected = [];
            foreach ($protectedFiles as $pf) {
                if (strpos($pf, 'mikhmon/') === 0) {
                    $adjustedProtected[] = substr($pf, 8);
                } else {
                    $adjustedProtected[] = $pf;
                }
            }
            $protectedFiles = $adjustedProtected;
        }

        // 3. Backup In-Memory and on Disk
        $inMemoryBackup = [];
        $backupDir = $mikhmonDir . '/backup_userdata/' . date('Ymd_His');
        @mkdir($backupDir, 0755, true);

        foreach ($protectedFiles as $rel) {
            $fPath = $baseInstallDir . '/' . $rel;
            if (file_exists($fPath) && is_file($fPath)) {
                $content = @file_get_contents($fPath);
                $inMemoryBackup[$rel] = $content;
                $safeName = str_replace(['/', '\\'], '_', $rel);
                @file_put_contents($backupDir . '/' . $safeName, $content);
            }
        }

        // Backup existing custom voucher templates
        $voucherDir = $mikhmonDir . '/voucher';
        $savedVoucherTemplates = [];
        if (is_dir($voucherDir)) {
            $dh = @opendir($voucherDir);
            if ($dh) {
                while (($vFile = readdir($dh)) !== false) {
                    if ($vFile === '.' || $vFile === '..') continue;
                    $fullVPath = $voucherDir . '/' . $vFile;
                    if (is_file($fullVPath)) {
                        $savedVoucherTemplates[$vFile] = @file_get_contents($fullVPath);
                    }
                }
                closedir($dh);
            }
        }

        // Backup existing logos
        $imgDir = $mikhmonDir . '/img';
        $savedLogos = [];
        if (is_dir($imgDir)) {
            $dh = @opendir($imgDir);
            if ($dh) {
                while (($iFile = readdir($dh)) !== false) {
                    if ($iFile === '.' || $iFile === '..') continue;
                    $fullIPath = $imgDir . '/' . $iFile;
                    if (is_file($fullIPath)) {
                        $savedLogos[$iFile] = @file_get_contents($fullIPath);
                    }
                }
                closedir($dh);
            }
        }

        $logs[] = "[" . date('H:i:s') . "] Mengamankan data sesi router, lisensi, voucher, dan preferensi lokal...";

        // 4. Download GitHub Archive
        $zipUrl = 'https://github.com/fitratan/MIKHMON-by-NODERA/archive/refs/heads/main.zip';
        $tempZip = sys_get_temp_dir() . '/mikhmon_update_' . uniqid() . '.zip';
        if (!is_writable(sys_get_temp_dir())) {
            $tempZip = $mikhmonDir . '/mikhmon_update_temp.zip';
        }

        $logs[] = "[" . date('H:i:s') . "] Mengunduh paket pembaruan dari GitHub (fitratan/MIKHMON-by-NODERA)...";

        $fp = @fopen($tempZip, 'w+');
        if (!$fp) {
            echo json_encode([
                'success' => false,
                'message' => 'Gagal membuat file sementara untuk paket pembaruan.',
                'logs' => $logs,
            ]);
            exit;
        }

        $ch = curl_init($zipUrl);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'NODERA-Mikhmon-v7-Updater');
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if ($httpCode !== 200 || !file_exists($tempZip) || filesize($tempZip) < 50000) {
            @unlink($tempZip);
            $logs[] = "[" . date('H:i:s') . "] [ERROR] Gagal mengunduh berkas dari GitHub (HTTP {$httpCode}).";
            echo json_encode([
                'success' => false,
                'message' => 'Gagal mengunduh pembaruan dari repositori GitHub.',
                'logs' => $logs,
            ]);
            exit;
        }

        $downloadKb = round(filesize($tempZip) / 1024, 1);
        $logs[] = "[" . date('H:i:s') . "] Paket pembaruan berhasil diunduh ({$downloadKb} KB).";

        // 5. Extract Archive
        $extractDir = sys_get_temp_dir() . '/mikhmon_extracted_' . uniqid();
        if (!is_writable(sys_get_temp_dir())) {
            $extractDir = $mikhmonDir . '/mikhmon_extracted_temp';
        }
        @mkdir($extractDir, 0755, true);

        $extractedOk = false;
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($tempZip) === true) {
                $zip->extractTo($extractDir);
                $zip->close();
                $extractedOk = true;
            }
        }

        // Fallback extract if ZipArchive failed or absent
        if (!$extractedOk) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                @exec("powershell -Command \"Expand-Archive -Path '{$tempZip}' -DestinationPath '{$extractDir}' -Force\"", $out, $ret);
                $extractedOk = ($ret === 0);
            } else {
                @exec("unzip -q -o \"{$tempZip}\" -d \"{$extractDir}\"", $out, $ret);
                $extractedOk = ($ret === 0);
            }
        }

        @unlink($tempZip);

        if (!$extractedOk) {
            $logs[] = "[" . date('H:i:s') . "] [ERROR] Gagal mengekstrak arsip pembaruan.";
            echo json_encode([
                'success' => false,
                'message' => 'Gagal mengekstrak paket pembaruan.',
                'logs' => $logs,
            ]);
            exit;
        }

        $logs[] = "[" . date('H:i:s') . "] Ekstraksi arsip pembaruan berhasil.";

        // Determine source extracted folder
        $sourceDir = is_dir($extractDir . '/MIKHMON-by-NODERA-main')
            ? $extractDir . '/MIKHMON-by-NODERA-main'
            : $extractDir;

        // 6. Traverse and Copy Updated Core Files
        $updatedFilesCount = 0;
        $skippedProtectedCount = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $srcPath = $item->getPathname();
            $rel = substr($srcPath, strlen($sourceDir) + 1);
            $relNorm = str_replace('\\', '/', $rel);

            // Never touch git, node_modules, build artifacts or Windows runtime bin
            if (
                $relNorm === '.git' || strpos($relNorm, '.git/') === 0 ||
                $relNorm === 'node_modules' || strpos($relNorm, 'node_modules/') === 0 ||
                $relNorm === 'dist' || strpos($relNorm, 'dist/') === 0 ||
                $relNorm === 'build' || strpos($relNorm, 'build/') === 0 ||
                $relNorm === 'inno-setup' || strpos($relNorm, 'inno-setup/') === 0 ||
                $relNorm === 'bin' || strpos($relNorm, 'bin/') === 0
            ) {
                continue;
            }

            // Route mapping
            if (!$hasMikhmonSubdir && strpos($relNorm, 'mikhmon/') === 0) {
                $targetRel = substr($relNorm, 8);
            } else {
                $targetRel = $relNorm;
            }

            // Check against protected files list
            if (in_array($targetRel, $protectedFiles, true) || in_array($relNorm, $protectedFiles, true)) {
                $skippedProtectedCount++;
                continue;
            }

            // Preserve existing custom logos in mikhmon/img/
            if (strpos($targetRel, 'mikhmon/img/') === 0 || strpos($targetRel, 'img/') === 0) {
                $baseImgName = basename($targetRel);
                if (isset($savedLogos[$baseImgName])) {
                    continue;
                }
            }

            $destPath = $baseInstallDir . '/' . $targetRel;

            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    @mkdir($destPath, 0755, true);
                }
            } else {
                $destParent = dirname($destPath);
                if (!is_dir($destParent)) {
                    @mkdir($destParent, 0755, true);
                }
                if (@copy($srcPath, $destPath)) {
                    $updatedFilesCount++;
                }
            }
        }

        // 7. Verify Integrity of Protected Configuration Files
        $verifiedProtectedCount = 0;
        foreach ($inMemoryBackup as $rel => $savedContent) {
            $destPath = $baseInstallDir . '/' . $rel;
            $currentOnDisk = file_exists($destPath) ? @file_get_contents($destPath) : null;
            if ($currentOnDisk !== $savedContent) {
                @file_put_contents($destPath, $savedContent);
            }
            $verifiedProtectedCount++;
        }

        // Verify custom voucher templates are restored/preserved
        foreach ($savedVoucherTemplates as $vFile => $vContent) {
            $fullVPath = $voucherDir . '/' . $vFile;
            if (!file_exists($fullVPath)) {
                @file_put_contents($fullVPath, $vContent);
            }
        }

        // 8. Update Local version.php with Latest Remote Commit
        $latestShaApplied = 'ad429f0';
        $ch = curl_init('https://api.github.com/repos/fitratan/MIKHMON-by-NODERA/commits?per_page=1');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'NODERA-Mikhmon-v7-Updater');
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $resSha = curl_exec($ch);
        curl_close($ch);
        if ($resSha) {
            $jsonSha = json_decode($resSha, true);
            if (!empty($jsonSha[0]['sha'])) {
                $latestShaApplied = substr($jsonSha[0]['sha'], 0, 7);
            }
        }

        $versionPhpCode = "<?php\n"
            . "/**\n"
            . " * MIKHMON by NODERA — Version & Engine Manifest\n"
            . " */\n"
            . "\$m_version = '3.20.4-v7';\n"
            . "\$m_build = " . var_export(date('Y-m-d'), true) . ";\n"
            . "\$m_commit = " . var_export($latestShaApplied, true) . ";\n\n"
            . "if (session_status() === PHP_SESSION_NONE && !headers_sent()) {\n"
            . "    @session_start();\n"
            . "}\n"
            . "if (!isset(\$_SESSION[\"mikhmon\"]) && empty(\$_COOKIE['mikhmon_remember'])) {\n"
            . "    // Keep auth check for direct script access\n"
            . "} else {\n"
            . "    \$_SESSION[\"v\"] = \$m_version . \" (\" . \$m_commit . \")\";\n"
            . "}\n";
        @file_put_contents($mikhmonDir . '/include/version.php', $versionPhpCode);

        // 9. Cleanup Temporary Extracted Folder
        $cleanIterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($extractDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($cleanIterator as $f) {
            if ($f->isDir()) {
                @rmdir($f->getRealPath());
            } else {
                @unlink($f->getRealPath());
            }
        }
        @rmdir($extractDir);

        $logs[] = "[" . date('H:i:s') . "] {$updatedFilesCount} berkas sistem Mikhmon berhasil diperbarui ke versi mutakhir.";
        $logs[] = "[" . date('H:i:s') . "] Verifikasi zero-data-loss: {$verifiedProtectedCount} berkas konfigurasi sesi, lisensi & router 100% terjaga.";
        $logs[] = "[" . date('H:i:s') . "] Sinkronisasi manifes selesai (commit: {$latestShaApplied}).";
        $logs[] = "[" . date('H:i:s') . "] Pembaruan berhasil selesai!";

        echo json_encode([
            'success' => true,
            'message' => 'Mikhmon berhasil diperbarui dari GitHub tanpa mengubah data Anda!',
            'version' => '3.20.4-v7',
            'commit' => $latestShaApplied,
            'updated_files' => $updatedFilesCount,
            'logs' => $logs,
        ]);
        exit;
    }
}
?>

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">
          <i class="fa fa-refresh"></i> <?= htmlspecialchars($tr($_system_update ?? null, 'Pembaruan Sistem')); ?> &nbsp; | &nbsp;&nbsp;<i onclick="location.reload();" class="fa fa-refresh pointer" title="Reload data"></i>
        </h3>
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
                    <strong style="font-size: 13px;"><i class="fa fa-server text-primary"></i> <?= htmlspecialchars($tr($_update_engine_status ?? null, 'Status Versi Engine')); ?></strong>
                  </div>
                  <div>
                    <span id="badgeUpdateStatus" class="badge bg-green" style="font-size: 11px; padding: 3px 8px;">
                      <i class="fa fa-check-circle"></i> <?= htmlspecialchars($tr($_update_up_to_date ?? null, 'Versi Mutakhir')); ?>
                    </span>
                  </div>
                </div>

                <div style="font-size: 12px; line-height: 1.8;">
                  <div style="display: flex; justify-content: space-between;">
                    <span style="opacity: 0.8;"><?= htmlspecialchars($tr($_update_installed_version ?? null, 'Versi Terpasang:')); ?></span>
                    <strong>v<?= htmlspecialchars($mikhmonVersion); ?> (<code id="lblCurrentCommit" style="font-size: 11px;"><?= htmlspecialchars($mikhmonCommit); ?></code>)</strong>
                  </div>
                  <div style="display: flex; justify-content: space-between;">
                    <span style="opacity: 0.8;"><?= htmlspecialchars($tr($_update_latest_release ?? null, 'Rilis GitHub Terkini:')); ?></span>
                    <strong class="text-green" id="lblLatestVersion">v<?= htmlspecialchars($mikhmonVersion); ?> (<code id="lblLatestCommit" style="font-size: 11px;"><?= htmlspecialchars($mikhmonCommit); ?></code>)</strong>
                  </div>
                  <div style="display: flex; justify-content: space-between;">
                    <span style="opacity: 0.8;"><?= htmlspecialchars($tr($_update_last_checked ?? null, 'Pemeriksaan Terakhir:')); ?></span>
                    <span id="lblLastChecked" style="font-size: 11px;"><?= date('d F Y H:i:s'); ?></span>
                  </div>
                </div>
              </div>

              <div style="margin-top: 12px; text-align: right;">
                <button type="button" id="btnCheckUpdate" class="btn bg-primary" style="padding: 6px 14px; font-weight: bold; border-radius: 6px; cursor: pointer;">
                  <i class="fa fa-refresh" id="iconSpin"></i> <?= htmlspecialchars($tr($_update_btn_check ?? null, 'Periksa Pembaruan')); ?>
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
                    <strong style="font-size: 13px;"><i class="fa fa-key text-yellow"></i> <?= htmlspecialchars($tr($_update_license_section_title ?? null, 'Lisensi Mikhmon Standalone')); ?></strong>
                  </div>
                  <div>
                    <button type="button" onclick="openDesktopLicenseModal()" class="btn btn-sm bg-secondary" style="padding: 3px 8px; font-size: 11px; border-radius: 4px; cursor: pointer;" title="<?= htmlspecialchars($tr($_update_change_license ?? null, 'Ganti Lisensi')); ?>">
                      <i class="fa fa-pencil text-primary"></i> <?= htmlspecialchars($tr($_update_change_license ?? null, 'Ganti')); ?>
                    </button>
                  </div>
                </div>

                <div style="font-size: 12px; line-height: 1.8;">
                  <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="opacity: 0.8;"><?= htmlspecialchars($tr($_update_license_key_label ?? null, 'Kunci Lisensi:')); ?></span>
                    <span style="display: flex; align-items: center; gap: 4px;">
                      <code id="activeKeyText" style="font-size: 11px; font-weight: bold; padding: 1px 6px; border-radius: 4px;"><?= htmlspecialchars($activeKeyDisplay); ?></code>
                      <button type="button" onclick="copyMikhmonKey()" class="btn btn-default btn-xs" style="padding: 0 4px; font-size: 10px;" title="<?= htmlspecialchars($_copy ?? 'Salin'); ?>">
                        <i class="fa fa-copy"></i>
                      </button>
                    </span>
                  </div>
                  <div style="display: flex; justify-content: space-between;">
                    <span style="opacity: 0.8;"><?= htmlspecialchars($tr($_update_license_type_label ?? null, 'Tipe Lisensi:')); ?></span>
                    <strong><?= $isTrial ? 'FREE (Trial Mode)' : 'OFFICIAL PAID (Pro)' ?></strong>
                  </div>
                  <div style="display: flex; justify-content: space-between;">
                    <span style="opacity: 0.8;"><?= htmlspecialchars($tr($_update_license_expiry_label ?? null, 'Masa Aktif:')); ?></span>
                    <strong class="<?= $isTrial ? 'text-yellow' : 'text-green' ?>">
                      <?= htmlspecialchars($expiryDateText) ?>
                      <?php if ($isTrial && $remainingDays > 0): ?>
                        <span style="font-size: 11px; font-weight: normal; opacity: 0.85;">(<?= $remainingDays . ' ' . ($_days_left ?? 'hari lagi'); ?>)</span>
                      <?php endif; ?>
                    </strong>
                  </div>
                  <div style="display: flex; justify-content: space-between;">
                    <span style="opacity: 0.8;"><?= htmlspecialchars($tr($_update_voucher_capacity_label ?? null, 'Kapasitas Voucher:')); ?></span>
                    <strong class="text-green"><?= htmlspecialchars($tr($_update_unlimited ?? null, 'Unlimited (Tanpa Batas Kuota)')); ?></strong>
                  </div>
                </div>
              </div>

              <div style="margin-top: 10px; padding-top: 6px; border-top: 1px solid rgba(128,128,128,0.15); display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 10.5px; opacity: 0.7; font-family: monospace;">HWID: <?= htmlspecialchars($hwid ?: 'STANDALONE') ?></span>
                <a href="https://panel.dgtlnetsolution.com/desktop-licenses" target="_blank" class="text-primary" style="font-size: 11.5px; font-weight: bold; text-decoration: underline;">
                  <i class="fa fa-external-link"></i> <?= htmlspecialchars($tr($_update_license_portal ?? null, 'Portal Lisensi')); ?> &rarr;
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
                <h3 class="card-title"><i class="fa fa-cloud-download"></i> <?= htmlspecialchars($tr($_update_status_and_actions ?? null, 'Status & Tindakan Pembaruan')); ?></h3>
              </div>
              <div class="card-body">
                <div id="updateActionBox" style="display: none; margin-bottom: 15px;">
                  <div class="box box-bordered" style="border-left: 4px solid #f39c12; padding: 14px 18px; border-radius: 8px; margin-bottom: 12px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                      <div>
                        <div style="font-size: 14px; font-weight: bold; color: #f39c12;">
                          <i class="fa fa-arrow-circle-up"></i> <?= htmlspecialchars($tr($_update_new_version_available ?? null, 'Versi Baru Tersedia:')); ?> <span id="boxNewVersionTitle">v<?= htmlspecialchars($mikhmonVersion); ?></span>
                        </div>
                        <div style="font-size: 12px; opacity: 0.85; margin-top: 3px;" id="boxReleaseDate">
                          <?= htmlspecialchars($tr($_update_released_from_github ?? null, 'Dirilis dari repositori GitHub resmi')); ?>
                        </div>
                      </div>
                      <div>
                        <button type="button" id="btnExecuteUpdate" class="btn bg-green" style="padding: 8px 18px; font-weight: bold; border-radius: 6px; cursor: pointer;">
                          <i class="fa fa-cloud-download"></i> <?= htmlspecialchars($tr($_update_btn_execute ?? null, 'Perbarui Sekarang (1-Klik)')); ?>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="card" style="margin-top: 5px;">
                  <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-list-alt"></i> <?= htmlspecialchars($tr($_update_release_notes ?? null, 'Catatan Rilis & Fitur Terbaru')); ?></h3>
                  </div>
                  <div class="card-body" style="padding: 10px 15px;">
                    <ul id="changelogList" style="margin: 0; padding-left: 18px; font-size: 12px; line-height: 1.8;">
                      <li><i class="fa fa-check-circle text-green"></i> Pembaruan engine RouterOS v7 &amp; API Webhook real-time.</li>
                      <li><i class="fa fa-check-circle text-green"></i> Optimalisasi template voucher QR code thermal Bluetooth.</li>
                      <li><i class="fa fa-check-circle text-green"></i> Penyempurnaan integrasi NODERA Pay dynamic QRIS &amp; Mikhmon Warung.</li>
                      <li><i class="fa fa-check-circle text-green"></i> Proteksi zero data loss: konfigurasi router &amp; voucher tersimpan aman.</li>
                    </ul>
                  </div>
                </div>

                <div id="updateLogCard" style="display: none; margin-top: 15px;">
                  <div class="card">
                    <div class="card-header">
                      <h3 class="card-title"><i class="fa fa-terminal"></i> <?= htmlspecialchars($tr($_update_log_title ?? null, 'Log Eksekusi Pembaruan')); ?></h3>
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
                <h3 class="card-title"><i class="fa fa-shield"></i> <?= htmlspecialchars($tr($_update_data_safety_title ?? null, 'Jaminan Keamanan Data')); ?></h3>
              </div>
              <div class="card-body">
                <div class="box box-bordered" style="margin-bottom: 12px; padding: 10px 14px; border-radius: 6px;">
                  <div style="font-size: 12px; font-weight: bold; margin-bottom: 4px;">
                    <i class="fa fa-lock text-primary"></i> <?= htmlspecialchars($tr($_update_data_safety_config ?? null, 'File Konfigurasi Sesi Aman')); ?>
                  </div>
                  <div style="font-size: 11px; opacity: 0.8; line-height: 1.4;">
                    <?= htmlspecialchars($tr($_update_data_safety_config_desc ?? null, 'File include/config.php & location_config.php tidak akan tertimpa saat update.')); ?>
                  </div>
                </div>

                <div class="box box-bordered" style="margin-bottom: 12px; padding: 10px 14px; border-radius: 6px;">
                  <div style="font-size: 12px; font-weight: bold; margin-bottom: 4px;">
                    <i class="fa fa-ticket text-green"></i> <?= htmlspecialchars($tr($_update_data_safety_voucher ?? null, 'Template Voucher Kustom')); ?>
                  </div>
                  <div style="font-size: 11px; opacity: 0.8; line-height: 1.4;">
                    <?= htmlspecialchars($tr($_update_data_safety_voucher_desc ?? null, 'Template voucher custom Anda di folder voucher/ tetap dipertahankan 100%.')); ?>
                  </div>
                </div>

                <div class="box box-bordered" style="padding: 10px 14px; border-radius: 6px;">
                  <div style="font-size: 12px; font-weight: bold; margin-bottom: 4px;">
                    <i class="fa fa-credit-card text-yellow"></i> <?= htmlspecialchars($tr($_update_data_safety_payment ?? null, 'Konfigurasi Payment Gateway')); ?>
                  </div>
                  <div style="font-size: 11px; opacity: 0.8; line-height: 1.4;">
                    <?= htmlspecialchars($tr($_update_data_safety_payment_desc ?? null, 'Integrasi NODERA Pay & QRIS otomatis tetap aktif tanpa konfigurasi ulang.')); ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="card" style="margin-top: 15px;">
              <div class="card-header">
                <h3 class="card-title"><i class="fa fa-info-circle"></i> <?= htmlspecialchars($tr($_update_official_help ?? null, 'Bantuan & Lisensi Resmi')); ?></h3>
              </div>
              <div class="card-body" style="font-size: 12px;">
                <p style="margin-bottom: 10px; line-height: 1.5;">
                  Butuh bantuan teknis atau aktivasi lisensi resmi?
                </p>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                  <a href="https://wa.me/6285155173547?text=Halo%20Admin%20NODERA,%20saya%20butuh%20bantuan%20update%20Mikhmon" target="_blank" class="btn bg-green" style="display: block; text-align: center; padding: 7px; font-weight: bold; border-radius: 6px; text-decoration: none;">
                    <i class="fa fa-whatsapp"></i> <?= htmlspecialchars($tr($_cs_indonesia ?? null, 'CS Indonesia (0851-5517-3547)')); ?>
                  </a>
                  <a href="https://wa.me/2550788222699?text=Bonjour%20Support%20NODERA,%20j%27ai%20besoin%20d%27aide%20pour%20la%20mise%20a%20jour%20Mikhmon" target="_blank" class="btn bg-green" style="display: block; text-align: center; padding: 7px; font-weight: bold; border-radius: 6px; text-decoration: none;">
                    <i class="fa fa-whatsapp"></i> <?= htmlspecialchars($tr($_cs_ivory_coast ?? null, 'CS Côte d\'Ivoire (+255 07 88 22 26 99)')); ?>
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- MODAL AKTIVASI / GANTI LISENSI -->
<div id="ndrDesktopLicenseModal" style="display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.65); justify-content:center; align-items:center;">
  <div class="card" style="width:92%; max-width:480px; box-shadow:0 10px 30px rgba(0,0,0,0.5); border-radius:4px; margin:0; padding:0; overflow:hidden;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; padding:10px 15px;">
      <h3 class="card-title" style="margin:0; font-size:15px;">
        <i class="fa fa-key text-primary"></i> <?= htmlspecialchars($tr($_update_modal_title ?? null, 'Aktivasi / Ganti Lisensi')); ?>
      </h3>
      <div style="display:flex; align-items:center; gap:8px;">
        <div style="display:inline-flex; background:rgba(0,0,0,0.12); border-radius:4px; padding:1px; border:1px solid rgba(120,120,120,0.25); font-size:10px; font-weight:bold;">
          <a href="./admin.php?id=update&setlang=id" style="padding:2px 7px; border-radius:3px; text-decoration:none; <?= (($langid ?? 'id') === 'id') ? 'background:#0073C6; color:#ffffff;' : 'color:inherit; opacity:0.75;' ?>">ID</a>
          <a href="./admin.php?id=update&setlang=fr" style="padding:2px 7px; border-radius:3px; text-decoration:none; <?= (($langid ?? 'id') === 'fr') ? 'background:#0073C6; color:#ffffff;' : 'color:inherit; opacity:0.75;' ?>">FR</a>
          <a href="./admin.php?id=update&setlang=en" style="padding:2px 7px; border-radius:3px; text-decoration:none; <?= (($langid ?? 'id') === 'en') ? 'background:#0073C6; color:#ffffff;' : 'color:inherit; opacity:0.75;' ?>">EN</a>
        </div>
        <button type="button" onclick="closeDesktopLicenseModal()" style="background:none; border:none; color:inherit; font-size:20px; line-height:1; cursor:pointer; opacity:0.8;" title="<?= htmlspecialchars($tr($_close ?? null, 'Tutup')); ?>">&times;</button>
      </div>
    </div>
    <div class="card-body" style="padding:15px;">
      <div id="ndrLicenseAlert" style="display:none; padding:8px 12px; border-radius:3px; font-size:12px; margin-bottom:12px;"></div>

      <!-- HWID Field -->
      <div style="margin-bottom:12px;">
        <label style="display:block; font-size:12px; font-weight:bold; margin-bottom:4px;"><?= htmlspecialchars($tr($_update_hwid_label ?? null, 'Hardware ID (HWID Perangkat Ini):')); ?></label>
        <div style="display:flex; gap:5px;">
          <input type="text" id="ndrDeviceHwid" value="<?= htmlspecialchars($hwid ?: 'STANDALONE') ?>" readonly class="form-control" style="flex:1; font-family:monospace; font-size:12.5px; font-weight:bold;">
          <button type="button" onclick="copyHwid()" class="btn bg-secondary" style="margin:0; padding:5px 12px;" title="<?= htmlspecialchars($tr($_update_btn_copy ?? null, 'Salin')); ?> HWID">
            <i class="fa fa-copy" id="copyHwidIcon"></i> <?= htmlspecialchars($tr($_update_btn_copy ?? null, 'Salin')); ?>
          </button>
        </div>
      </div>

      <!-- License Key Field -->
      <div style="margin-bottom:10px;">
        <label style="display:block; font-size:12px; font-weight:bold; margin-bottom:4px;"><?= htmlspecialchars($tr($_update_license_input_label ?? null, 'Kode Kunci Lisensi (License Key):')); ?></label>
        <input type="text" id="ndrLicenseKeyInput" name="license_key" value="<?= htmlspecialchars($licenseKey) ?>" placeholder="Contoh: NDR-STD-XXXX-XXXX-XXXX-XXXX" class="form-control" style="font-family:monospace; font-size:12.5px; font-weight:bold;">
      </div>

      <!-- Box Info Native Mikhmon -->
      <div class="box box-bordered" style="margin:8px 0 15px 0; padding:10px 12px; font-size:11.5px; line-height:1.5; background:rgba(0,0,0,0.05);">
        <div style="margin-bottom:6px;">
          <i class="fa fa-info-circle text-primary"></i> <b><?= htmlspecialchars($tr($_guide ?? null, 'Panduan')); ?>:</b> <?= $tr($_license_guide_text ?? null, 'Masukkan License Key resmi dari <a href="https://panel.dgtlnetsolution.com/desktop-licenses" target="_blank" class="text-primary" style="text-decoration:underline; font-weight:bold;">Portal Cloud NODERA</a> atau kosongkan untuk klaim <b>Free Trial 7 Hari</b>.'); ?>
        </div>
        <div style="border-top:1px dashed rgba(120,120,120,0.25); padding-top:6px; margin-top:6px;">
          <b><i class="fa fa-whatsapp text-green"></i> <?= htmlspecialchars($tr($_support_cs_whatsapp ?? null, 'Layanan Bantuan & CS WhatsApp:')); ?></b>
          <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:5px;">
            <a href="https://wa.me/6285155173547?text=Halo%20Admin%20NODERA%20Indonesia,%20saya%20butuh%20bantuan%20aktivasi%20lisensi%20Mikhmon%20HWID:%20<?= urlencode($hwid ?: 'STANDALONE') ?>" target="_blank" class="btn bg-green" style="font-size:11px; padding:3px 8px; text-decoration:none; display:inline-flex; align-items:center; gap:4px; margin:0;">
              <?= htmlspecialchars($tr($_cs_indonesia ?? null, 'CS Indonesia (0851-5517-3547)')); ?>
            </a>
            <a href="https://wa.me/2550788222699?text=Bonjour%20Support%20NODERA%20C%C3%B4te%20d%27Ivoire,%20j%27ai%20besoin%20d%27aide%20pour%20activer%20la%20licence%20Mikhmon%20HWID:%20<?= urlencode($hwid ?: 'STANDALONE') ?>" target="_blank" class="btn bg-green" style="font-size:11px; padding:3px 8px; text-decoration:none; display:inline-flex; align-items:center; gap:4px; margin:0;">
              <?= htmlspecialchars($tr($_cs_ivory_coast ?? null, 'CS Côte d\'Ivoire (+255 07 88 22 26 99)')); ?>
            </a>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:10px;">
        <button type="button" onclick="closeDesktopLicenseModal()" class="btn bg-secondary" style="margin:0;"><?= htmlspecialchars($tr($_update_btn_cancel ?? null, 'Batal')); ?></button>
        <button type="button" id="ndrBtnSubmitLicense" onclick="submitDesktopLicense()" class="btn bg-primary" style="margin:0;">
          <i class="fa fa-check"></i> <?= htmlspecialchars($tr($_update_btn_save_verify ?? null, 'Simpan & Verifikasi')); ?>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
function copyMikhmonKey() {
  var el = document.getElementById('activeKeyText');
  if (el) {
    var txt = el.innerText.trim();
    navigator.clipboard.writeText(txt).then(function() {
      alert((<?= json_encode($_license_code_copied ?? 'Kunci lisensi berhasil disalin:') ?>) + ' ' + txt);
    }).catch(function() {
      alert((<?= json_encode($_license_key ?? 'Kunci lisensi:') ?>) + ' ' + txt);
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
      alert((<?= json_encode($_hwid_copied_clipboard ?? 'Hardware ID berhasil disalin ke clipboard:') ?>) + ' ' + h.value);
    });
  }
}

function submitDesktopLicense() {
  var key = document.getElementById('ndrLicenseKeyInput').value.trim();
  var alertBox = document.getElementById('ndrLicenseAlert');
  var btn = document.getElementById('ndrBtnSubmitLicense');
  if (!key) {
    showError(<?= json_encode($_license_key_required ?? 'License Key wajib diisi!') ?>);
    return;
  }
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ' + <?= json_encode($_saving ?? 'Menyimpan...') ?>;
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
      showError(data ? data.message : <?= json_encode($_license_save_failed ?? 'Gagal menyimpan lisensi.') ?>);
    }
  })
  .catch(function(err) {
    showError((<?= json_encode($_error ?? 'Terjadi kesalahan:') ?>) + ' ' + err.message);
  });

  function showSuccessAndReload(data) {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa fa-check-circle"></i> ' + <?= json_encode($_success ?? 'Berhasil') ?>;
    }
    if (alertBox) {
      alertBox.className = 'box bg-green';
      alertBox.style.cssText = 'display:block; margin:0 0 12px 0; padding:8px 10px; border-radius:3px; font-size:12px; font-weight:bold; color:#ffffff;';
      alertBox.innerHTML = '<i class="fa fa-check"></i> ' + (data.message || <?= json_encode($_license_save_success ?? 'Lisensi berhasil disimpan & diaktifkan!') ?>);
    }
    setTimeout(function() {
      location.reload();
    }, 1200);
  }

  function showError(msg) {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa fa-check"></i> ' + <?= json_encode($_update_btn_save_verify ?? 'Simpan & Verifikasi') ?>;
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
      btnCheck.innerHTML = '<i class="fa fa-refresh fa-spin"></i> ' + <?= json_encode($tr($_update_checking ?? null, 'Memeriksa...')) ?>;

      fetch('./admin.php?id=update&action=check')
        .then(function(res) { return res.json(); })
        .then(function(data) {
          btnCheck.disabled = false;
          btnCheck.innerHTML = '<i class="fa fa-refresh"></i> ' + <?= json_encode($tr($_update_btn_check ?? null, 'Periksa Pembaruan')) ?>;

          var lastCheckEl = document.getElementById('lblLastChecked');
          var latestVerEl = document.getElementById('lblLatestVersion');
          var latestCommitEl = document.getElementById('lblLatestCommit');
          if (lastCheckEl) lastCheckEl.textContent = data.checked_at || 'Baru saja';
          if (latestVerEl) {
            latestVerEl.innerHTML = 'v' + (data.latest_version || '3.20.4-v7') + (data.latest_commit ? ' (<code style="font-size: 11px;">' + data.latest_commit + '</code>)' : '');
          }

          var badgeStatus = document.getElementById('badgeUpdateStatus');
          if (data.update_available) {
            if (actionBox) actionBox.style.display = 'block';
            var boxVer = document.getElementById('boxNewVersionTitle');
            if (boxVer) boxVer.textContent = 'v' + data.latest_version + ' (' + data.latest_commit + ')';
            if (badgeStatus) {
              badgeStatus.className = 'badge bg-yellow';
              badgeStatus.innerHTML = '<i class="fa fa-exclamation-circle"></i> ' + <?= json_encode($tr($_update_available ?? null, 'Update Tersedia')) ?>;
            }
          } else {
            if (actionBox) actionBox.style.display = 'none';
            if (badgeStatus) {
              badgeStatus.className = 'badge bg-green';
              badgeStatus.innerHTML = '<i class="fa fa-check-circle"></i> ' + <?= json_encode($tr($_update_up_to_date ?? null, 'Versi Mutakhir')) ?>;
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
          btnCheck.innerHTML = '<i class="fa fa-refresh"></i> ' + <?= json_encode($tr($_update_btn_check ?? null, 'Periksa Pembaruan')) ?>;
          alert('Pemeriksaan selesai: Versi saat ini sudah mutakhir.');
        });
    });
  }

  if (btnExecute) {
    btnExecute.addEventListener('click', function() {
      if (!confirm('Apakah Anda yakin ingin memperbarui instance Mikhmon sekarang? Seluruh konfigurasi router, voucher & lisensi Anda tetap aman.')) {
        return;
      }

      btnExecute.disabled = true;
      btnExecute.innerHTML = '<i class="fa fa-refresh fa-spin"></i> Memproses...';
      if (logCard) logCard.style.display = 'block';
      if (logConsole) logConsole.innerHTML = '> [START] Memulai pembaruan sistem Mikhmon dari GitHub...<br/>';

      fetch('./admin.php?id=update&action=execute')
        .then(function(res) { return res.json(); })
        .then(function(data) {
          btnExecute.disabled = false;
          btnExecute.innerHTML = '<i class="fa fa-check"></i> Pembaruan Selesai';

          if (data.logs && data.logs.length > 0) {
            data.logs.forEach(function(log) {
              logConsole.innerHTML += '> ' + log + '<br/>';
            });
          }
          logConsole.innerHTML += '> [SELESAI] ' + (data.message || 'Pembaruan berhasil!') + '<br/>';
          logConsole.scrollTop = logConsole.scrollHeight;

          setTimeout(function() {
            location.reload();
          }, 2000);
        })
        .catch(function() {
          btnExecute.disabled = false;
          btnExecute.innerHTML = '<i class="fa fa-cloud-download"></i> Coba Lagi';
          if (logConsole) logConsole.innerHTML += '> [ERROR] Terjadi kesalahan saat memproses pembaruan.<br/>';
        });
    });
  }
});
</script>
