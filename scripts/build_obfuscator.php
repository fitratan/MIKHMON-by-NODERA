<?php
/**
 * NODERA PROPRIETARY COMPILER & OBFUSCATION BUILDER
 * (c) 2026 NODERA. All rights reserved.
 * 
 * Compiles PHP source code into multi-layer obfuscated, compressed,
 * and anti-tamper protected runtime binaries for Mikhmon Desktop & Cloud.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only\n");
}

$baseDir = dirname(__DIR__);
$mikhmonDir = $baseDir . '/mikhmon';
$srcBackupDir = $baseDir . '/mikhmon-src';

if (!is_dir($srcBackupDir)) {
    mkdir($srcBackupDir, 0755, true);
}

echo "========================================================\n";
echo "  NODERA CODE PROTECTION & OBFUSCATOR BUILDER v2.0     \n";
echo "========================================================\n\n";

function obfuscatePhpFile(string $sourceCode, string $description = ''): string {
    // 1. Strip comments and extra whitespace using php_strip_whitespace temporary file
    $tmpFile = tempnam(sys_get_temp_dir(), 'ndr_src_');
    file_put_contents($tmpFile, $sourceCode);
    $stripped = php_strip_whitespace($tmpFile);
    @unlink($tmpFile);

    // Remove opening <?php tag from stripped code to prepare for eval
    $cleanCode = preg_replace('/^<\?(php)?/i', '', trim($stripped));
    $cleanCode = preg_replace('/\?>$/', '', trim($cleanCode));

    // 2. Compress payload using raw deflate
    $compressed = gzdeflate($cleanCode, 9);
    if ($compressed === false) {
        throw new RuntimeException("Gzdeflate failed on source code.");
    }

    // 3. Generate dynamic 16-byte cryptographically secure XOR key
    $key = random_bytes(16);
    $keyLen = strlen($key);

    // 4. Encrypt compressed stream with XOR
    $cipher = '';
    $compLen = strlen($compressed);
    for ($i = 0; $i < $compLen; $i++) {
        $cipher .= chr(ord($compressed[$i]) ^ ord($key[$i % $keyLen]));
    }

    // 5. Calculate SHA-256 integrity checksum
    $checksum = hash('sha256', $cipher);

    $b64Cipher = base64_encode($cipher);
    $b64Key = base64_encode($key);

    // 6. Generate random variable identifiers
    $vCipher = '$_ndr_' . substr(md5(random_bytes(8)), 0, 8);
    $vKey    = '$_ndr_' . substr(md5(random_bytes(8)), 0, 8);
    $vKLen   = '$_ndr_' . substr(md5(random_bytes(8)), 0, 8);
    $vDec    = '$_ndr_' . substr(md5(random_bytes(8)), 0, 8);
    $vIdx    = '$_ndr_' . substr(md5(random_bytes(8)), 0, 8);
    $vHash   = '$_ndr_' . substr(md5(random_bytes(8)), 0, 8);

    $banner = "<?php\n"
        . "/**\n"
        . " * ============================================================================\n"
        . " * NODERA DIGITAL NETWORK — " . (!empty($description) ? strtoupper($description) : "PROPRIETARY COMPONENT") . "\n"
        . " * SECURE RUNTIME BINARY & CLOUD-LOCKED ENGINE\n"
        . " * ----------------------------------------------------------------------------\n"
        . " * (c) 2026 NODERA. All rights reserved.\n"
        . " * This file contains encrypted intellectual property. Any unauthorized\n"
        . " * decompilation, reverse engineering, or modification is strictly prohibited.\n"
        . " * ============================================================================\n"
        . " */\n";

    $stub = $banner
        . "{$vCipher}=base64_decode('{$b64Cipher}');\n"
        . "{$vKey}=base64_decode('{$b64Key}');\n"
        . "{$vHash}='{$checksum}';\n"
        . "if(hash('sha256',{$vCipher})!=={$vHash}){die('NODERA Security: Integrity verification failed (0x8F).');}\n"
        . "{$vKLen}=strlen({$vKey});\n"
        . "{$vDec}='';\n"
        . "for({$vIdx}=0;{$vIdx}<strlen({$vCipher});{$vIdx}++){\n"
        . "    {$vDec}.=chr(ord({$vCipher}[{$vIdx}])^ord({$vKey}[{$vIdx}%{$vKLen}]));\n"
        . "}\n"
        . "eval(gzinflate({$vDec}));\n";

    return $stub;
}

// List of target proprietary files to compile & protect
$targets = [
    // 1. Bandwidth Management & Smart FUP Engine
    'bandwidth/fup_helper.php'         => 'Nodera FUP & Traffic Control Engine',
    'bandwidth/fup_settings.php'       => 'Nodera FUP Profile Rules Configurator',
    'bandwidth/interface_traffic.php'  => 'Nodera Multi-Interface Bandwidth Engine',
    'bandwidth/top_voucher.php'        => 'Nodera Top-Tier Voucher & Bandwidth Leaderboard',
    'bandwidth/action.php'             => 'Nodera Real-Time Rate Limiter & Kick Engine',
    'traffic/trafficmonitor.php'       => 'Nodera Real-Time Interface Traffic Monitor',

    // 2. Multi-Payment Gateway & NODERA Pay
    'include/noderapay.php'            => 'NODERA Pay QRIS Dynamic Gateway',

    // 3. Warung Reseller Helper
    'include/warung_helper.php'        => 'Nodera Warung Reseller & Settlement Helper',

    // 4. WhatsApp & Telegram Gateway Engines
    'include/whatsapp_helper.php'      => 'Nodera WhatsApp Dispatcher & Gateway Helper',
    'include/whatsapp.php'             => 'Nodera WhatsApp Controller & Interface',
    'include/telegram_helper.php'      => 'Nodera Telegram Bot Helper & Manual ACC Dispatcher',
    'include/telegram.php'             => 'Nodera Telegram Bot Controller & Interface',
    'telegram_webhook.php'             => 'Nodera Telegram Webhook & Interactive Bot Router',
];

$successCount = 0;

foreach ($targets as $relPath => $desc) {
    $fullPath = $mikhmonDir . '/' . $relPath;
    $backupPath = $srcBackupDir . '/' . $relPath;

    // Special source for noderapay
    if ($relPath === 'include/noderapay.php' && file_exists($mikhmonDir . '/include/noderapay_source.php')) {
        $sourceCode = file_get_contents($mikhmonDir . '/include/noderapay_source.php');
    } elseif (file_exists($backupPath)) {
        // If backup already exists, use source backup
        $sourceCode = file_get_contents($backupPath);
    } elseif (file_exists($fullPath)) {
        $sourceCode = file_get_contents($fullPath);
    } else {
        echo "[-] Skipping missing file: {$relPath}\n";
        continue;
    }

    // Save pristine clean source to backup directory
    $backupSubdir = dirname($backupPath);
    if (!is_dir($backupSubdir)) {
        mkdir($backupSubdir, 0755, true);
    }
    file_put_contents($backupPath, $sourceCode);

    // Generate protected binary
    echo "[*] Obfuscating: {$relPath} ({$desc})...\n";
    $obfuscated = obfuscatePhpFile($sourceCode, $desc);
    file_put_contents($fullPath, $obfuscated);

    // Verify syntax
    $lintOut = [];
    $lintCode = 0;
    exec("php -l " . escapeshellarg($fullPath) . " 2>&1", $lintOut, $lintCode);
    if ($lintCode === 0) {
        echo "    [+] Success & Valid Syntax (PHP Lint OK)\n";
        $successCount++;
    } else {
        echo "    [!] Lint Error on {$relPath}: " . implode("\n", $lintOut) . "\n";
    }
}

echo "\n========================================================\n";
echo "  COMPLETED: {$successCount} proprietary files protected!\n";
echo "  Pristine source preserved in: {$srcBackupDir}\n";
echo "========================================================\n";
