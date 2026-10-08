const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('noderaDesktop', {
  getHwid: () => ipcRenderer.invoke('get-hwid'),
  getLicenseInfo: () => ipcRenderer.invoke('get-license-info'),
  activateLicense: (key) => ipcRenderer.invoke('activate-license', key),
  verifyLicense: () => ipcRenderer.invoke('verify-license'),
  openExternal: (url) => ipcRenderer.invoke('open-external', url),
  closeApp: () => ipcRenderer.invoke('close-app'),
});
