const { app, BrowserWindow, ipcMain, shell, dialog } = require('electron');
const path = require('path');
const fs = require('fs');
const http = require('http');
const { spawn, execSync } = require('child_process');
const axios = require('axios');
const { machineIdSync } = require('node-machine-id');

// Global Configurations
const APP_NAME = 'Mikhmon Desktop by NODERA';
const API_BASE_URL = process.env.NODERA_LICENSE_API || 'https://digitalnet.dgtlnetsolution.com/api/v1/desktop/license';
const LICENSE_FILE = path.join(app.getPath('userData'), 'nodera_license.json');

let mainWindow = null;
let phpProcess = null;
let serverPort = 8788;
let currentHwid = null;

// Generate / Retrieve Hardware ID (Motherboard UUID)
function getHardwareId() {
  if (currentHwid) return currentHwid;
  try {
    currentHwid = machineIdSync({ original: true });
  } catch (e) {
    try {
      if (process.platform === 'win32') {
        const out = execSync('wmic csproduct get uuid', { encoding: 'utf8' });
        currentHwid = out.split('\n')[1].trim();
      } else if (process.platform === 'darwin') {
        const out = execSync("ioreg -rd1 -c IOPlatformExpertDevice | grep -E '(UUID)'", { encoding: 'utf8' });
        currentHwid = out.split('"')[3].trim();
      } else {
        currentHwid = fs.readFileSync('/etc/machine-id', 'utf8').trim();
      }
    } catch (err) {
      currentHwid = 'NDR-HWID-' + Buffer.from(require('os').hostname()).toString('hex').slice(0, 16);
    }
  }
  return currentHwid;
}

// License Storage Helpers
function readSavedLicense() {
  try {
    if (fs.existsSync(LICENSE_FILE)) {
      const data = JSON.parse(fs.readFileSync(LICENSE_FILE, 'utf8'));
      return data;
    }
  } catch (err) {
    console.error('Error reading license file:', err.message);
  }
  return null;
}

function saveLicense(data) {
  try {
    fs.writeFileSync(LICENSE_FILE, JSON.stringify(data, null, 2), 'utf8');
  } catch (err) {
    console.error('Error saving license file:', err.message);
  }
}

// Start Embedded Local PHP Server
function startPhpServer(port) {
  const mikhmonPath = app.isPackaged
    ? path.join(process.resourcesPath, 'mikhmon')
    : path.join(__dirname, 'mikhmon');

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

  phpProcess = spawn(phpBinary, phpArgs, {
    cwd: mikhmonPath,
    stdio: 'ignore',
  });

  phpProcess.on('error', (err) => {
    console.error('Failed to start PHP server:', err);
    dialog.showErrorBox('PHP Server Error', 'Gagal menjalankan engine PHP lokal. Pastikan PHP terinstall atau paket portable tersedia.');
  });
}

// Verify License with Cloud API
async function verifyLicenseOnline(licenseKey) {
  const hwid = getHardwareId();
  try {
    const res = await axios.post(`${API_BASE_URL}/verify`, {
      license_key: licenseKey,
      hwid: hwid,
    }, { timeout: 8000 });

    if (res.data && res.data.success) {
      saveLicense({
        license_key: licenseKey,
        hwid: hwid,
        status: 'ACTIVE',
        last_verified_at: new Date().toISOString(),
        expires_at: res.data.data?.expires_at,
        features: res.data.data?.features,
      });
      return { valid: true, data: res.data.data };
    }
  } catch (err) {
    // Offline Grace Period (3 hari)
    const saved = readSavedLicense();
    if (saved && saved.license_key === licenseKey && saved.hwid === hwid) {
      const lastVerified = new Date(saved.last_verified_at || 0).getTime();
      const now = Date.now();
      const diffDays = (now - lastVerified) / (1000 * 60 * 60 * 24);
      if (diffDays <= 3) {
        return { valid: true, offline: true, data: saved };
      }
    }
    return { valid: false, message: err.response?.data?.message || 'Gagal menghubungi server aktivasi.' };
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
    const res = await axios.post(`${API_BASE_URL}/activate`, {
      license_key: key,
      hwid: hwid,
      device_name: deviceName,
      os_info: osInfo,
    }, { timeout: 10000 });

    if (res.data && res.data.success) {
      saveLicense({
        license_key: key,
        hwid: hwid,
        status: 'ACTIVE',
        last_verified_at: new Date().toISOString(),
        expires_at: res.data.data?.expires_at,
        features: res.data.data?.features,
      });

      setTimeout(() => {
        loadMikhmonApp();
      }, 1200);

      return { success: true, data: res.data.data };
    }
    return { success: false, message: res.data?.message || 'Aktivasi gagal.' };
  } catch (err) {
    return {
      success: false,
      message: err.response?.data?.message || 'Gagal menghubungi server aktivasi NODERA.',
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
