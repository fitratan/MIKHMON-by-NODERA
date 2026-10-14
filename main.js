const { app, BrowserWindow, ipcMain, shell, dialog } = require('electron');
const path = require('path');
const fs = require('fs');
const http = require('http');
const { spawn, execSync } = require('child_process');
const axios = require('axios');
const { machineIdSync } = require('node-machine-id');

// Global Configurations
const APP_NAME = 'Mikhmon Desktop by NODERA';
const PRIMARY_API = 'https://panel.dgtlnetsolution.com/api/desktop-license';
const FALLBACK_API = 'https://digitalnet.dgtlnetsolution.com/api/desktop-license';
const LICENSE_FILE = path.join(app.getPath('userData'), 'nodera_license.json');

let mainWindow = null;
let phpProcess = null;
let serverPort = 8788;
let currentHwid = null;

function getMikhmonPath() {
  return app.isPackaged
    ? path.join(process.resourcesPath, 'mikhmon')
    : path.join(__dirname, 'mikhmon');
}

function getMikhmonConfigDir() {
  const cfgDir = path.join(getMikhmonPath(), 'config');
  if (!fs.existsSync(cfgDir)) {
    try {
      fs.mkdirSync(cfgDir, { recursive: true });
    } catch (e) {
      console.error('Error creating config directory:', e.message);
    }
  }
  return cfgDir;
}

// Generate / Retrieve Standard Unified Hardware ID (NDR-HWID-XXXX-XXXX-XXXX)
function getHardwareId() {
  if (currentHwid) return currentHwid;

  // 1. Prioritize reading saved .hwid in mikhmon/config/.hwid
  try {
    const hwidFile = path.join(getMikhmonConfigDir(), '.hwid');
    if (fs.existsSync(hwidFile)) {
      const savedHwid = fs.readFileSync(hwidFile, 'utf8').trim();
      if (savedHwid && savedHwid.startsWith('NDR-HWID-')) {
        currentHwid = savedHwid;
        return currentHwid;
      }
    }
  } catch (e) {}

  // 2. Hardware fingerprint collection
  let raw = '';
  try {
    raw = machineIdSync({ original: true });
  } catch (e) {
    try {
      if (process.platform === 'win32') {
        const out = execSync('wmic csproduct get uuid', { encoding: 'utf8' });
        raw = out.split('\n')[1].trim();
      } else if (process.platform === 'darwin') {
        const out = execSync("ioreg -rd1 -c IOPlatformExpertDevice | grep -E '(UUID)'", { encoding: 'utf8' });
        raw = out.split('"')[3].trim();
      } else {
        raw = fs.readFileSync('/etc/machine-id', 'utf8').trim();
      }
    } catch (err) {
      raw = require('os').hostname() + '-' + require('os').platform();
    }
  }

  const crypto = require('crypto');
  const hash = crypto.createHash('sha256').update(raw).digest('hex').toUpperCase();
  currentHwid = 'NDR-HWID-' + hash.slice(0, 4) + '-' + hash.slice(4, 8) + '-' + hash.slice(8, 12);

  // Persist to mikhmon/config/.hwid
  try {
    const hwidFile = path.join(getMikhmonConfigDir(), '.hwid');
    fs.writeFileSync(hwidFile, currentHwid, 'utf8');
  } catch (e) {}

  return currentHwid;
}

const OFFLINE_SECRET = 'NODERA_OFFLINE_SECRET_2026';

// Anti-Tampering: Cryptographic HMAC Signature Verification (Anti-Edit Notepad)
function verifyLicenseSignature(licenseData) {
  if (!licenseData || !licenseData.signature) return false;
  const key = licenseData.license_key || '';
  const hwid = licenseData.hwid || '';
  const exp = licenseData.expires_at || 'LIFETIME';
  const crypto = require('crypto');
  const expected = crypto.createHash('sha256')
    .update(`${key}|${hwid}|${exp}|${OFFLINE_SECRET}`)
    .digest('hex');
  return licenseData.signature === expected;
}

// Anti-Tampering: Anti-Clock Rollback Guard (Deteksi Pemunduran Jam Windows)
function checkClockTampering() {
  const configDir = getMikhmonConfigDir();
  const guardPath = path.join(configDir, '.clock_guard');
  const now = Math.floor(Date.now() / 1000);
  try {
    if (fs.existsSync(guardPath)) {
      const last = parseInt(fs.readFileSync(guardPath, 'utf8'), 10);
      if (!isNaN(last) && now < (last - 300)) {
        console.warn('Clock tampering detected! System clock is earlier than last recorded timestamp.');
        return false;
      }
    }
    fs.writeFileSync(guardPath, String(now), 'utf8');
  } catch (e) {}
  return true;
}

// License Storage & PHP Synchronization Helpers
function syncLicenseToPhp(licenseData) {
  try {
    const configDir = getMikhmonConfigDir();
    const licensePhpPath = path.join(configDir, 'license.php');
    const licenseKey = licenseData.license_key || '';
    const hwid = licenseData.hwid || getHardwareId();
    let expiry = '0000-00-00';
    if (licenseData.expires_at) {
      const d = new Date(licenseData.expires_at);
      if (!isNaN(d.getTime())) {
        expiry = d.toISOString().slice(0, 19).replace('T', ' ');
      }
    }
    const prodName = licenseData.product_name || 'Mikhmon Desktop Standalone';
    const nowStr = new Date().toISOString().slice(0, 19).replace('T', ' ');
    const signature = licenseData.signature || '';

    const content = `<?php
/**
 * LICENSE MIKHMON DESKTOP STANDALONE — NODERA
 * Teraktivasi resmi via Cloud Panel NODERA (panel.dgtlnetsolution.com).
 */
define('MIKHMON_MODE', 'DESKTOP');
define('MIKHMON_STATUS', 'ACTIVE');
define('MIKHMON_LICENSE_KEY', ${JSON.stringify(licenseKey)});
define('MIKHMON_HWID', ${JSON.stringify(hwid)});
define('MIKHMON_EXPIRY', ${JSON.stringify(expiry)});
define('MIKHMON_EXPIRY_RAW', ${JSON.stringify(licenseData.expires_at || 'LIFETIME')});
define('MIKHMON_SIGNATURE', ${JSON.stringify(signature)});
define('MIKHMON_BRAND', 'by dgtlnetsolution.com');
define('MIKHMON_PRODUCT_NAME', ${JSON.stringify(prodName)});
define('MIKHMON_ACTIVATED_AT', ${JSON.stringify(nowStr)});
define('MIKHMON_SUBDOMAIN', 'desktop');
`;
    fs.writeFileSync(licensePhpPath, content, 'utf8');
    fs.writeFileSync(path.join(configDir, '.hwid'), hwid, 'utf8');
    fs.writeFileSync(path.join(configDir, 'nodera_license.json'), JSON.stringify(licenseData, null, 2), 'utf8');
    fs.writeFileSync(path.join(configDir, '.last_verified'), String(Math.floor(Date.now() / 1000)), 'utf8');

    // Hapus alert error reset jika ada
    const alertPath = path.join(configDir, '.reset_alert');
    if (fs.existsSync(alertPath)) {
      try { fs.unlinkSync(alertPath); } catch (e) {}
    }
  } catch (err) {
    console.error('Failed to sync license to PHP config:', err);
  }
}

function readSavedLicense() {
  try {
    if (fs.existsSync(LICENSE_FILE)) {
      const data = JSON.parse(fs.readFileSync(LICENSE_FILE, 'utf8'));
      if (data && data.license_key) return data;
    }
  } catch (err) {
    console.error('Error reading license file:', err.message);
  }

  // Fallback to mikhmon/config/nodera_license.json
  try {
    const configDir = getMikhmonConfigDir();
    const licJsonPath = path.join(configDir, 'nodera_license.json');
    if (fs.existsSync(licJsonPath)) {
      const data = JSON.parse(fs.readFileSync(licJsonPath, 'utf8'));
      if (data && data.license_key) {
        saveLicense(data);
        return data;
      }
    }
  } catch (e) {}

  return null;
}

function saveLicense(data) {
  try {
    fs.writeFileSync(LICENSE_FILE, JSON.stringify(data, null, 2), 'utf8');
  } catch (err) {
    console.error('Error saving license file:', err.message);
  }
}

// Call Cloud Licensing API with automatic fallback
async function callLicenseApi(endpoint, payload) {
  const apis = [PRIMARY_API, FALLBACK_API];
  let lastError = null;

  for (const baseUrl of apis) {
    try {
      const res = await axios.post(`${baseUrl}${endpoint}`, payload, {
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'User-Agent': 'Mikhmon-Desktop-App/1.0',
        },
        timeout: 10000,
      });
      if (res.data) {
        return res.data;
      }
    } catch (err) {
      lastError = err;
      if (err.response && err.response.data) {
        return err.response.data;
      }
    }
  }

  throw lastError || new Error('Gagal menghubungi server aktivasi NODERA.');
}

// Start Embedded Local PHP Server
function startPhpServer(port) {
  const mikhmonPath = getMikhmonPath();

  // Cari executable PHP (Windows portable php.exe atau sistem PHP)
  let phpBinary = 'php';
  const candidatePaths = [
    path.join(process.resourcesPath, 'bin', 'php-win', 'php.exe'),
    path.join(__dirname, 'bin', 'php-win', 'php.exe'),
    path.join(process.resourcesPath, 'php', 'php.exe'),
    path.join(__dirname, 'php', 'php.exe'),
  ];

  for (const p of candidatePaths) {
    if (fs.existsSync(p)) {
      phpBinary = p;
      break;
    }
  }

  const iniPath = path.join(path.dirname(phpBinary), 'php.ini');
  const phpArgs = fs.existsSync(iniPath)
    ? ['-c', iniPath, '-S', `127.0.0.1:${port}`, '-t', mikhmonPath]
    : ['-S', `127.0.0.1:${port}`, '-t', mikhmonPath];

  console.log(`Starting PHP server on port ${port} pointing to ${mikhmonPath} using ${phpBinary}...`);

  const hwid = getHardwareId();
  phpProcess = spawn(phpBinary, phpArgs, {
    cwd: mikhmonPath,
    stdio: 'ignore',
    env: {
      ...process.env,
      NODERA_DESKTOP_HWID: hwid,
    },
  });

  phpProcess.on('error', (err) => {
    console.error('Failed to start PHP server:', err);
    dialog.showErrorBox('PHP Server Error', 'Gagal menjalankan engine PHP lokal. Pastikan PHP terinstall atau paket portable tersedia.');
  });
}

// Verify License with Cloud API & Grace Period
async function verifyLicenseOnline(licenseKey) {
  const hwid = getHardwareId();

  // Anti-Clock Rollback Guard Check
  if (!checkClockTampering()) {
    return { valid: false, message: 'Manipulasi jam sistem terdeteksi. Silakan sinkronkan waktu perangkat Anda.' };
  }

  try {
    const res = await callLicenseApi('/verify', {
      license_key: licenseKey,
      hwid: hwid,
    });

    if (res && res.success) {
      const licenseData = {
        license_key: licenseKey,
        hwid: hwid,
        status: 'ACTIVE',
        product_name: res.data?.product_name || 'Mikhmon Desktop Standalone',
        last_verified_at: new Date().toISOString(),
        expires_at: res.data?.expires_at,
        signature: res.data?.signature || '',
        features: res.data?.features,
      };
      saveLicense(licenseData);
      syncLicenseToPhp(licenseData);
      return { valid: true, data: res.data };
    }

    if (res && res.code === 'LICENSE_EXPIRED') {
      const saved = readSavedLicense() || {};
      saved.status = 'EXPIRED';
      saveLicense(saved);
      return { valid: false, expired: true, message: res.message || 'Masa aktif lisensi telah berakhir.' };
    }

    if (res && res.code === 'HWID_MISMATCH') {
      return { valid: false, message: res.message || 'HWID tidak cocok.' };
    }
  } catch (err) {
    // Offline Grace Period & Strict Expiration Enforcement
    const saved = readSavedLicense();
    if (saved && saved.license_key === licenseKey && saved.hwid === hwid) {
      // 1. Anti-Tamper: Signature Check
      if (saved.signature && !verifyLicenseSignature(saved)) {
        console.warn('License signature mismatch! Possible manual file tampering.');
        return { valid: false, message: 'Integritas file lisensi rusak atau telah dimodifikasi.' };
      }

      // 2. Strict Expiration Date Check (Trial 7 Hari & Lisensi Berbatas Waktu)
      if (saved.expires_at && saved.expires_at !== 'LIFETIME') {
        const expTime = new Date(saved.expires_at).getTime();
        const now = Date.now();
        const endOfDayBuffer = saved.expires_at.length <= 10 ? 86399000 : 0;
        if (!isNaN(expTime) && now > (expTime + endOfDayBuffer)) {
          saved.status = 'EXPIRED';
          saveLicense(saved);
          return { valid: false, expired: true, message: 'Masa aktif lisensi atau trial 7 hari Anda telah berakhir.' };
        }
      }

      // 3. Grace Period Check (Maksimal 3 hari sejak heartbeat/verifikasi online terakhir)
      const lastVerified = new Date(saved.last_verified_at || 0).getTime();
      const now = Date.now();
      const diffDays = (now - lastVerified) / (1000 * 60 * 60 * 24);
      if (diffDays >= 0 && diffDays <= 3) {
        syncLicenseToPhp(saved);
        return { valid: true, offline: true, data: saved };
      }
    }
    return { valid: false, message: 'Gagal menghubungi server aktivasi NODERA dan masa toleransi offline telah habis.' };
  }

  return { valid: false, message: 'Lisensi tidak valid.' };
}

// Create Main Application Window
function createMainWindow() {
  mainWindow = new BrowserWindow({
    width: 1280,
    height: 820,
    minWidth: 980,
    minHeight: 650,
    title: APP_NAME,
    icon: path.join(__dirname, 'build', 'icon.png'),
    backgroundColor: '#0B132B',
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      nodeIntegration: false,
      contextIsolation: true,
      webSecurity: true,
    },
    autoHideMenuBar: true,
  });

  // Handle external link opening
  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    shell.openExternal(url);
    return { action: 'deny' };
  });

  // Handle Web Bluetooth device discovery in Electron Desktop
  mainWindow.webContents.on('select-bluetooth-device', (event, deviceList, callback) => {
    event.preventDefault();
    if (deviceList && deviceList.length > 0) {
      const printer = deviceList.find(d => {
        const name = (d.deviceName || '').toLowerCase();
        return name.includes('print') || name.includes('pos') || name.includes('rpp') || name.includes('mtp') || name.includes('pt') || name.includes('thermal');
      }) || deviceList[0];
      callback(printer.deviceId);
    }
  });

  checkLicenseAndRoute();
}

async function checkLicenseAndRoute() {
  const saved = readSavedLicense();
  if (saved && saved.license_key) {
    const check = await verifyLicenseOnline(saved.license_key);
    if (check.valid) {
      loadMikhmonApp();
      return;
    }
  }
  loadActivationScreen();
}

function loadActivationScreen() {
  mainWindow.loadFile(path.join(__dirname, 'activation.html'));
}

function loadMikhmonApp() {
  // Tunggu PHP server siap
  const checkUrl = `http://127.0.0.1:${serverPort}`;
  const req = http.get(checkUrl, () => {
    mainWindow.loadURL(`http://127.0.0.1:${serverPort}/index.php`);
  });
  req.on('error', () => {
    setTimeout(() => {
      mainWindow.loadURL(`http://127.0.0.1:${serverPort}/index.php`);
    }, 800);
  });
}

// IPC Handlers
ipcMain.handle('get-hwid', () => getHardwareId());

ipcMain.handle('get-license-info', () => readSavedLicense());

ipcMain.handle('activate-license', async (event, key) => {
  const hwid = getHardwareId();
  const osInfo = `${process.platform} ${require('os').release()} (${require('os').arch()})`;
  const deviceName = require('os').hostname();

  try {
    const res = await callLicenseApi('/activate', {
      license_key: key,
      hwid: hwid,
      device_name: deviceName,
      os_info: osInfo,
    });

    if (res && res.success) {
      const licenseData = {
        license_key: key,
        hwid: hwid,
        status: 'ACTIVE',
        product_name: res.data?.product_name || 'Mikhmon Desktop Standalone',
        last_verified_at: new Date().toISOString(),
        expires_at: res.data?.expires_at,
        signature: res.data?.signature || '',
        features: res.data?.features,
      };
      saveLicense(licenseData);
      syncLicenseToPhp(licenseData);

      setTimeout(() => {
        loadMikhmonApp();
      }, 1000);

      return { success: true, message: res.message, data: res.data };
    }
    return { success: false, message: res?.message || 'Aktivasi gagal. Periksa kembali license key Anda.' };
  } catch (err) {
    return {
      success: false,
      message: err.message || 'Gagal menghubungi server aktivasi NODERA.',
    };
  }
});

ipcMain.handle('activate-trial', async () => {
  const hwid = getHardwareId();
  const osInfo = `${process.platform} ${require('os').release()} (${require('os').arch()})`;
  const deviceName = require('os').hostname();

  try {
    const res = await callLicenseApi('/trial', {
      hwid: hwid,
      device_name: deviceName,
      os_info: osInfo,
    });

    if (res && res.success) {
      const licenseData = {
        license_key: res.data?.license_key,
        hwid: hwid,
        status: 'ACTIVE',
        product_name: res.data?.product_name || 'Mikhmon Desktop (Trial 7 Hari)',
        last_verified_at: new Date().toISOString(),
        expires_at: res.data?.expires_at,
        signature: res.data?.signature || '',
        is_trial: true,
        features: res.data?.features,
      };
      saveLicense(licenseData);
      syncLicenseToPhp(licenseData);

      setTimeout(() => {
        loadMikhmonApp();
      }, 1000);

      return { success: true, message: res.message, data: res.data };
    }
    return { success: false, message: res?.message || 'Gagal mengklaim Free Trial.' };
  } catch (err) {
    return {
      success: false,
      message: 'Gagal menghubungi server aktivasi NODERA. Pastikan koneksi internet aktif.',
    };
  }
});

ipcMain.handle('verify-license', async () => {
  const saved = readSavedLicense();
  if (!saved || !saved.license_key) {
    return { valid: false, message: 'Tidak ada lisensi tersimpan.' };
  }
  return await verifyLicenseOnline(saved.license_key);
});

ipcMain.handle('open-external', (event, url) => {
  shell.openExternal(url);
});

ipcMain.handle('close-app', () => {
  app.quit();
});

// Periodic Watchdog for License Expiry (Auto-kick when trial/subscription expires)
let licenseWatchdogTimer = null;
function startLicenseWatchdog() {
  if (licenseWatchdogTimer) clearInterval(licenseWatchdogTimer);
  // Check every 10 minutes
  licenseWatchdogTimer = setInterval(async () => {
    const saved = readSavedLicense();
    if (!saved || !saved.license_key) {
      if (mainWindow && !mainWindow.isDestroyed()) {
        loadActivationScreen();
      }
      return;
    }

    const check = await verifyLicenseOnline(saved.license_key);
    if (!check.valid) {
      console.warn('License watchdog check failed:', check.message);
      if (mainWindow && !mainWindow.isDestroyed()) {
        loadActivationScreen();
      }
    }
  }, 10 * 60 * 1000);
}

// App Lifecycle
app.whenReady().then(() => {
  getHardwareId();
  startPhpServer(serverPort);
  createMainWindow();
  startLicenseWatchdog();

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) createMainWindow();
  });
});

app.on('window-all-closed', () => {
  if (licenseWatchdogTimer) {
    clearInterval(licenseWatchdogTimer);
  }
  if (phpProcess) {
    phpProcess.kill();
  }
  if (process.platform !== 'darwin') {
    app.quit();
  }
});

app.on('will-quit', () => {
  if (licenseWatchdogTimer) {
    clearInterval(licenseWatchdogTimer);
  }
  if (phpProcess) {
    phpProcess.kill();
  }
});
