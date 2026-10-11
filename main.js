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
        features: res.data?.features,
      };
      saveLicense(licenseData);
      syncLicenseToPhp(licenseData);
      return { valid: true, data: res.data };
    }

    if (res && res.code === 'HWID_MISMATCH') {
      return { valid: false, message: res.message || 'HWID tidak cocok.' };
    }
  } catch (err) {
    // Offline Grace Period (3 hari)
    const saved = readSavedLicense();
    if (saved && saved.license_key === licenseKey && saved.hwid === hwid) {
      const lastVerified = new Date(saved.last_verified_at || 0).getTime();
      const now = Date.now();
      const diffDays = (now - lastVerified) / (1000 * 60 * 60 * 24);
      if (diffDays <= 3) {
        syncLicenseToPhp(saved);
        return { valid: true, offline: true, data: saved };
      }
    }
    return { valid: false, message: 'Gagal menghubungi server aktivasi NODERA.' };
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

ipcMain.handle('open-external', (event, url) => {
  shell.openExternal(url);
});

ipcMain.handle('close-app', () => {
  app.quit();
});

// App Lifecycle
app.whenReady().then(() => {
  getHardwareId();
  startPhpServer(serverPort);
  createMainWindow();

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) createMainWindow();
  });
});

app.on('window-all-closed', () => {
  if (phpProcess) {
    phpProcess.kill();
  }
  if (process.platform !== 'darwin') {
    app.quit();
  }
});

app.on('will-quit', () => {
  if (phpProcess) {
    phpProcess.kill();
  }
});
