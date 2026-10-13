package main

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"syscall"
	"time"

	webview2 "github.com/jchv/go-webview2"
)

var (
	procMessageBoxWApp = user32DLL.NewProc("MessageBoxW")
)

func showAppDialog(title, message string, iconType uint) int {
	tPtr, _ := syscall.UTF16PtrFromString(title)
	mPtr, _ := syscall.UTF16PtrFromString(message)
	ret, _, _ := procMessageBoxWApp.Call(0, uintptr(unsafePointer(mPtr)), uintptr(unsafePointer(tPtr)), uintptr(iconType))
	return int(ret)
}

// RunDesktopApp initializes and starts the standalone Mikhmon Desktop application
func RunDesktopApp(baseDir string) {
	// 1. Hardware ID & Anti-clock rollback
	hwid := GetHardwareID(baseDir)
	if !CheckClockGuard(baseDir) {
		showAppDialog("Peringatan Keamanan Sistem", "Manipulasi waktu atau pemunduran jam Windows terdeteksi. Silakan cocokkan waktu sistem Anda.", 0x00000030)
	}

	// 2. Initialize Server Manager
	sm := NewServerManager(baseDir, 8080, hwid)
	defer sm.Stop()

	// 3. Setup Persistent WebView2 Data Path in %APPDATA%\NoderaMikhmon
	appData := os.Getenv("APPDATA")
	if appData == "" {
		appData = baseDir
	}
	dataPath := filepath.Join(appData, "NoderaMikhmon", "webview_cache")
	_ = os.MkdirAll(dataPath, 0755)

	// 4. Create Native WebView2 Window
	w := webview2.NewWithOptions(webview2.WebViewOptions{
		Debug:     false,
		DataPath:  dataPath,
		AutoFocus: true,
		WindowOptions: webview2.WindowOptions{
			Title:  "MIKHMON by NODERA — MikroTik Hotspot & Kasir Warung",
			Width:  1240,
			Height: 820,
			IconId: 1,
			Center: true,
		},
	})

	// Fallback if WebView2 runtime is missing on old Windows machines
	if w == nil {
		res := showAppDialog(
			"Microsoft Edge WebView2 Diperlukan",
			"Aplikasi membutuhkan Microsoft Edge WebView2 Runtime untuk berjalan secara mandiri.\n\nKlik OK untuk mengunduh runtime resmi dari Microsoft (Evergreen Bootstrapper).",
			0x00000001|0x00000030, // MB_OKCANCEL | MB_ICONWARNING
		)
		if res == 1 { // IDOK
			_ = OpenBrowser("https://go.microsoft.com/fwlink/p/?LinkId=2124703")
		}
		// Fallback: start server and open browser
		if err := sm.Start(); err == nil {
			_ = OpenBrowser(fmt.Sprintf("http://127.0.0.1:%d/admin.php?id=sessions", sm.Port))
			// Keep process alive
			select {}
		}
		return
	}
	defer w.Destroy()

	// 5. Check License State
	savedLic, err := ReadSavedLicense(baseDir)
	isLicensed := false
	if err == nil && savedLic != nil && savedLic.Status == "ACTIVE" {
		if VerifyLicenseSignature(*savedLic) {
			isLicensed = true
		}
	}

	// Auto trial on first run if never attempted
	trialMarker := filepath.Join(baseDir, "mikhmon", "config", ".trial_attempted")
	if !isLicensed && !fileExists(trialMarker) {
		_ = os.WriteFile(trialMarker, []byte(time.Now().Format(time.RFC3339)), 0644)
		if trialLic, err := ActivateTrial(baseDir); err == nil && trialLic != nil {
			savedLic = trialLic
			isLicensed = true
		}
	}

	// 6. Bind Native Go APIs to JavaScript in WebView
	w.Bind("getHwid", func() (string, error) {
		return hwid, nil
	})

	w.Bind("getLicenseInfo", func() (map[string]interface{}, error) {
		lic, err := ReadSavedLicense(baseDir)
		if err != nil || lic == nil {
			return map[string]interface{}{
				"is_licensed": false,
				"status":      "UNREGISTERED",
				"hwid":        hwid,
			}, nil
		}
		active := lic.Status == "ACTIVE" && VerifyLicenseSignature(*lic)
		return map[string]interface{}{
			"is_licensed":  active,
			"status":       lic.Status,
			"license_key":  lic.LicenseKey,
			"product_name": lic.ProductName,
			"expires_at":   lic.ExpiresAt,
			"is_trial":     lic.IsTrial,
			"days_left":    lic.DaysLeft,
			"hwid":         lic.Hwid,
			"lan_url":      fmt.Sprintf("http://%s:%d", sm.LocalIP, sm.Port),
			"local_url":    fmt.Sprintf("http://127.0.0.1:%d", sm.Port),
		}, nil
	})

	w.Bind("activateKey", func(key string) (map[string]interface{}, error) {
		lic, err := ActivateKey(baseDir, key)
		if err != nil {
			return map[string]interface{}{
				"success": false,
				"message": err.Error(),
			}, nil
		}

		// Ensure PHP server is running
		if !sm.IsRunning {
			if err := sm.Start(); err != nil {
				return map[string]interface{}{
					"success": false,
					"message": fmt.Sprintf("Gagal menyalakan web server internal: %v", err),
				}, nil
			}
		}

		return map[string]interface{}{
			"success":    true,
			"message":    "Aktivasi Lisensi Berhasil!",
			"target_url": fmt.Sprintf("http://127.0.0.1:%d/admin.php?id=sessions", sm.Port),
			"data":       lic,
		}, nil
	})

	w.Bind("activateTrial", func() (map[string]interface{}, error) {
		lic, err := ActivateTrial(baseDir)
		if err != nil {
			return map[string]interface{}{
				"success": false,
				"message": err.Error(),
			}, nil
		}

		if !sm.IsRunning {
			if err := sm.Start(); err != nil {
				return map[string]interface{}{
					"success": false,
					"message": fmt.Sprintf("Gagal menyalakan web server internal: %v", err),
				}, nil
			}
		}

		return map[string]interface{}{
			"success":    true,
			"message":    "Trial 7 Hari Berhasil Diaktifkan!",
			"target_url": fmt.Sprintf("http://127.0.0.1:%d/admin.php?id=sessions", sm.Port),
			"data":       lic,
		}, nil
	})

	w.Bind("openExternal", func(targetUrl string) (bool, error) {
		_ = OpenBrowser(targetUrl)
		return true, nil
	})

	w.Bind("openAdmin", func() (bool, error) {
		w.Navigate(fmt.Sprintf("http://127.0.0.1:%d/admin.php?id=sessions", sm.Port))
		return true, nil
	})

	w.Bind("openWarung", func() (bool, error) {
		w.Navigate(fmt.Sprintf("http://127.0.0.1:%d/warung.php", sm.Port))
		return true, nil
	})

	w.Bind("getLanInfo", func() (map[string]interface{}, error) {
		return map[string]interface{}{
			"lan_ip":     sm.LocalIP,
			"port":       sm.Port,
			"warung_url": fmt.Sprintf("http://%s:%d/warung.php", sm.LocalIP, sm.Port),
			"admin_url":  fmt.Sprintf("http://%s:%d/admin.php?id=sessions", sm.LocalIP, sm.Port),
		}, nil
	})

	// 7. Inject Navigation & Bridge Scripts
	w.Init(getInjectedDesktopScript(sm.Port, sm.LocalIP, hwid))

	// 8. Launch Appropriate View
	if isLicensed {
		if err := sm.Start(); err != nil {
			showAppDialog("Peringatan Server", fmt.Sprintf("Gagal memulai PHP web server: %v", err), 0x00000010)
		}
		w.Navigate(fmt.Sprintf("http://127.0.0.1:%d/admin.php?id=sessions", sm.Port))
	} else {
		// Render In-App Activation Page
		w.SetHtml(getActivationHtml(hwid))
	}

	// 9. Run Native Message Loop
	w.Run()
}

// Injected JavaScript for In-App Toolbar, Keyboard Shortcuts, and External Link Interception
func getInjectedDesktopScript(port int, lanIp, hwid string) string {
	return fmt.Sprintf(`
(function() {
  // Global NODERA Bridge
  window.nodera = {
    hwid: %s,
    lanIp: %s,
    port: %d,
    openExternal: function(url) {
      if (window.openExternal) window.openExternal(url);
      else window.open(url, '_blank');
    },
    openAdmin: function() {
      if (window.openAdmin) window.openAdmin();
      else window.location.href = './admin.php?id=sessions';
    },
    openWarung: function() {
      if (window.openWarung) window.openWarung();
      else window.location.href = './warung.php';
    }
  };

  // Keyboard Shortcuts
  window.addEventListener('keydown', function(e) {
    if (e.key === 'F5' || (e.ctrlKey && e.key.toLowerCase() === 'r')) {
      e.preventDefault();
      window.location.reload();
    }
    if (e.ctrlKey && e.key === '1') {
      e.preventDefault();
      window.location.href = './admin.php?id=sessions';
    }
    if (e.ctrlKey && e.key === '2') {
      e.preventDefault();
      window.location.href = './warung.php';
    }
  });

  // Intercept External Links to Open in Default Browser
  document.addEventListener('click', function(e) {
    const a = e.target.closest('a');
    if (a && a.href) {
      const href = a.href;
      const isInternal = href.startsWith('http://127.0.0.1') || 
                         href.startsWith('http://localhost') || 
                         href.startsWith('http://' + window.nodera.lanIp);
      if (!isInternal && (href.startsWith('http://') || href.startsWith('https://'))) {
        e.preventDefault();
        window.nodera.openExternal(href);
      }
    }
  }, true);

  // Floating Quick-Bar for Switching between Hotspot & Warung
  function setupDesktopBar() {
    if (document.getElementById('nodera-desktop-bar')) return;
    if (window.location.pathname.includes('buy.php') || window.location.pathname.includes('callback.php')) return;

    const style = document.createElement('style');
    style.id = 'nodera-desktop-style';
    style.textContent = ` + "`" + `
      @media print {
        #nodera-desktop-bar, .nodera-modal-root { display: none !important; }
      }
      #nodera-desktop-bar {
        position: fixed;
        top: 10px;
        right: 16px;
        z-index: 9999999;
        display: flex;
        align-items: center;
        gap: 6px;
        background: rgba(15, 23, 42, 0.92);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.12);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);
        padding: 4px 6px;
        border-radius: 9999px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        font-size: 11.5px;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        user-select: none;
      }
      #nodera-desktop-bar:hover {
        background: rgba(15, 23, 42, 0.98);
        border-color: rgba(99, 102, 241, 0.45);
        box-shadow: 0 12px 28px -4px rgba(79, 70, 229, 0.25);
      }
      .ndr-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 11px;
        border-radius: 9999px;
        border: none;
        background: transparent;
        color: #94a3b8;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none !important;
        font-size: 11.5px;
        line-height: 1;
        transition: all 0.15s ease;
      }
      .ndr-btn:hover {
        background: rgba(255, 255, 255, 0.08);
        color: #f8fafc;
      }
      .ndr-btn.active {
        background: #4f46e5;
        color: #ffffff;
        box-shadow: 0 2px 8px rgba(79, 70, 229, 0.4);
      }
      .ndr-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #10b981;
        box-shadow: 0 0 8px #10b981;
        margin: 0 4px;
      }
    ` + "`" + `;
    document.head.appendChild(style);

    const bar = document.createElement('div');
    bar.id = 'nodera-desktop-bar';

    const isWarung = window.location.pathname.includes('warung.php');
    
    bar.innerHTML = ` + "`" + `
      <div class="ndr-dot" title="Server Standalone Aktif di Port ` + fmt.Sprintf("%d", port) + `"></div>
      <a href="./admin.php?id=sessions" class="ndr-btn ${!isWarung ? 'active' : ''}" title="Dashboard Hotspot MikroTik (Ctrl+1)">
        <span>🚀 Hotspot</span>
      </a>
      <a href="./warung.php" class="ndr-btn ${isWarung ? 'active' : ''}" title="Portal Kasir Warung (Ctrl+2)">
        <span>🏪 Warung</span>
      </a>
      <button class="ndr-btn" onclick="showNoderaInfoModal()" title="Info Lisensi & Akses Jaringan LAN">
        <span>🔑 Info</span>
      </button>
      <button class="ndr-btn" onclick="window.location.reload()" title="Muat Ulang Halaman (F5)">
        <span>🔄</span>
      </button>
    ` + "`" + `;
    document.body.appendChild(bar);
  }

  // Info Modal for License and LAN Access
  window.showNoderaInfoModal = async function() {
    let existing = document.getElementById('nodera-info-modal');
    if (existing) { existing.remove(); return; }

    let lic = { is_licensed: true, status: 'ACTIVE', hwid: window.nodera.hwid, lan_url: 'http://' + window.nodera.lanIp + ':' + window.nodera.port };
    if (window.getLicenseInfo) {
      try { lic = await window.getLicenseInfo(); } catch(e){}
    }

    const modal = document.createElement('div');
    modal.id = 'nodera-info-modal';
    modal.className = 'nodera-modal-root';
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.65);backdrop-filter:blur(6px);z-index:99999999;display:flex;align-items:center;justify-content:center;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;';
    modal.innerHTML = ` + "`" + `
      <div style="background:#0f172a;border:1px solid rgba(255,255,255,0.15);border-radius:18px;max-width:460px;width:92%%;padding:24px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.7);color:#f8fafc;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
          <h3 style="margin:0;font-size:16px;font-weight:700;">MIKHMON Desktop Standalone</h3>
          <button onclick="document.getElementById('nodera-info-modal').remove()" style="background:none;border:none;color:#94a3b8;cursor:pointer;font-size:18px;line-height:1;">✕</button>
        </div>
        <div style="background:#1e293b;padding:12px;border-radius:10px;margin-bottom:12px;font-size:12.5px;">
          <div style="color:#94a3b8;margin-bottom:4px;">Status Lisensi:</div>
          <div style="font-weight:700;color:#10b981;">✓ ${lic.status || 'AKTIF'} ${lic.is_trial ? '(TRIAL)' : ''}</div>
          <div style="color:#94a3b8;margin-top:8px;margin-bottom:4px;">Hardware ID (HWID):</div>
          <div style="font-family:monospace;font-weight:600;color:#cbd5e1;">${lic.hwid}</div>
        </div>
        <div style="background:#1e293b;padding:12px;border-radius:10px;margin-bottom:16px;font-size:12.5px;">
          <div style="color:#94a3b8;margin-bottom:4px;">Akses Kasir Warung via HP (Satu WiFi):</div>
          <div style="font-family:monospace;color:#38bdf8;font-weight:600;word-break:break-all;">${lic.lan_url}/warung.php</div>
          <div style="font-size:11px;color:#64748b;margin-top:4px;">Buka link di atas lewat browser HP mitra warung yang terhubung ke WiFi lokal yang sama.</div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;">
          <button onclick="window.nodera.openExternal('https://panel.dgtlnetsolution.com/desktop-licenses')" style="background:#4f46e5;color:#fff;border:none;padding:8px 14px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;">Buka Cloud Portal</button>
          <button onclick="document.getElementById('nodera-info-modal').remove()" style="background:#334155;color:#f8fafc;border:none;padding:8px 14px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;">Tutup</button>
        </div>
      </div>
    ` + "`" + `;
    document.body.appendChild(modal);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupDesktopBar);
  } else {
    setupDesktopBar();
  }
})();
`, jsonEscape(hwid), jsonEscape(lanIp), port)
}

func jsonEscape(s string) string {
	b, _ := json.Marshal(s)
	return string(b)
}

// In-App Activation Page HTML with Hardware ID & Cloud Activation
func getActivationHtml(hwid string) string {
	return fmt.Sprintf(`<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Aktivasi Lisensi — MIKHMON Desktop Standalone by NODERA</title>
  <style>
    :root {
      --bg: #0b0f19;
      --card: #111827;
      --border: rgba(255, 255, 255, 0.08);
      --primary: #4f46e5;
      --primary-hover: #4338ca;
      --text: #f8fafc;
      --text-muted: #94a3b8;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background: radial-gradient(circle at 50%% 0%%, #1e1b4b 0%%, var(--bg) 70%%);
      color: var(--text);
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      user-select: none;
    }
    .container {
      background: rgba(17, 24, 39, 0.85);
      backdrop-filter: blur(20px);
      border: 1px solid var(--border);
      border-radius: 24px;
      max-width: 480px;
      width: 100%%;
      padding: 36px 32px;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
      text-align: center;
    }
    .badge-icon {
      width: 64px;
      height: 64px;
      margin: 0 auto 16px;
      background: linear-gradient(135deg, #4f46e5, #06b6d4);
      border-radius: 18px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 28px;
      box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.4);
    }
    h1 {
      font-size: 20px;
      font-weight: 800;
      letter-spacing: -0.02em;
      margin-bottom: 6px;
      color: #ffffff;
    }
    p.subtitle {
      font-size: 13px;
      color: var(--text-muted);
      line-height: 1.5;
      margin-bottom: 24px;
    }
    .hwid-box {
      background: #0f172a;
      border: 1px solid rgba(255, 255, 255, 0.06);
      border-radius: 14px;
      padding: 14px 16px;
      margin-bottom: 20px;
      text-align: left;
    }
    .hwid-label {
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: var(--text-muted);
      margin-bottom: 6px;
    }
    .hwid-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
    }
    .hwid-val {
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      font-size: 14px;
      font-weight: 700;
      color: #38bdf8;
      letter-spacing: 0.04em;
    }
    .copy-btn {
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.1);
      color: #e2e8f0;
      font-size: 11px;
      font-weight: 600;
      padding: 5px 10px;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.15s ease;
    }
    .copy-btn:hover {
      background: rgba(255, 255, 255, 0.12);
      color: #ffffff;
    }
    .form-group {
      text-align: left;
      margin-bottom: 20px;
    }
    label {
      display: block;
      font-size: 12px;
      font-weight: 600;
      color: #e2e8f0;
      margin-bottom: 8px;
    }
    input[type="text"] {
      width: 100%%;
      background: #0f172a;
      border: 1px solid rgba(255, 255, 255, 0.12);
      border-radius: 12px;
      padding: 12px 14px;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      font-size: 14px;
      color: #ffffff;
      outline: none;
      transition: border-color 0.2s ease;
      text-transform: uppercase;
    }
    input[type="text"]:focus {
      border-color: #6366f1;
      box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
    }
    .btn-submit {
      width: 100%%;
      background: linear-gradient(135deg, var(--primary), var(--primary-hover));
      border: none;
      border-radius: 12px;
      color: #ffffff;
      font-size: 14px;
      font-weight: 700;
      padding: 13px;
      cursor: pointer;
      box-shadow: 0 4px 14px rgba(79, 70, 229, 0.4);
      transition: all 0.2s ease;
      margin-bottom: 12px;
    }
    .btn-submit:hover {
      opacity: 0.95;
      transform: translateY(-1px);
    }
    .btn-submit:disabled {
      opacity: 0.5;
      cursor: not-allowed;
      transform: none;
    }
    .btn-trial {
      width: 100%%;
      background: transparent;
      border: 1px solid rgba(255, 255, 255, 0.12);
      border-radius: 12px;
      color: #cbd5e1;
      font-size: 13px;
      font-weight: 600;
      padding: 11px;
      cursor: pointer;
      transition: all 0.15s ease;
    }
    .btn-trial:hover {
      background: rgba(255, 255, 255, 0.05);
      color: #ffffff;
    }
    .alert {
      padding: 10px 14px;
      border-radius: 10px;
      font-size: 12px;
      margin-bottom: 16px;
      display: none;
      text-align: left;
    }
    .alert-error {
      background: rgba(239, 68, 68, 0.15);
      border: 1px solid rgba(239, 68, 68, 0.3);
      color: #fca5a5;
    }
    .alert-success {
      background: rgba(16, 185, 129, 0.15);
      border: 1px solid rgba(16, 185, 129, 0.3);
      color: #86efac;
    }
    .footer-links {
      margin-top: 24px;
      font-size: 12px;
      color: var(--text-muted);
      display: flex;
      justify-content: center;
      gap: 16px;
    }
    .footer-links a {
      color: #818cf8;
      text-decoration: none;
      cursor: pointer;
    }
    .footer-links a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="badge-icon">⚡</div>
    <h1>MIKHMON Standalone</h1>
    <p class="subtitle">MikroTik Hotspot Management & Kasir Warung<br>Terintegrasi dengan Cloud Licensing NODERA</p>

    <div id="alertBox" class="alert"></div>

    <div class="hwid-box">
      <div class="hwid-label">Hardware ID Perangkat (HWID)</div>
      <div class="hwid-row">
        <span class="hwid-val" id="hwidText">%s</span>
        <button class="copy-btn" id="copyBtn" onclick="copyHWID()">Salin</button>
      </div>
    </div>

    <div class="form-group">
      <label for="licKey">Kunci Lisensi Desktop (License Key)</label>
      <input type="text" id="licKey" placeholder="NDR-LIVE-XXXX-XXXX-XXXX" autocomplete="off" spellcheck="false">
    </div>

    <button class="btn-submit" id="btnActivate" onclick="doActivate()">🔑 Aktivasi Lisensi Sekarang</button>
    <button class="btn-trial" id="btnTrial" onclick="doTrial()">⏱️ Mulai Trial 7 Hari Gratis</button>

    <div class="footer-links">
      <a onclick="window.nodera.openExternal('https://panel.dgtlnetsolution.com/desktop-licenses')">Beli Lisensi di Portal ➔</a>
      <a onclick="window.nodera.openExternal('https://dgtlnetsolution.com')">Bantuan & CS</a>
    </div>
  </div>

  <script>
    function copyHWID() {
      const txt = document.getElementById('hwidText').innerText;
      navigator.clipboard.writeText(txt).then(() => {
        const btn = document.getElementById('copyBtn');
        btn.innerText = 'Tersalin!';
        setTimeout(() => btn.innerText = 'Salin', 2000);
      });
    }

    function showAlert(msg, type) {
      const b = document.getElementById('alertBox');
      b.className = 'alert ' + (type === 'success' ? 'alert-success' : 'alert-error');
      b.innerText = msg;
      b.style.display = 'block';
    }

    async function doActivate() {
      const key = document.getElementById('licKey').value.trim();
      if (!key) {
        showAlert('Silakan masukkan Kunci Lisensi Anda.', 'error');
        return;
      }

      const btn = document.getElementById('btnActivate');
      btn.disabled = true;
      btn.innerText = 'Memverifikasi Lisensi...';

      try {
        const res = await window.activateKey(key);
        if (res && res.success) {
          showAlert('Aktivasi Berhasil! Mengalihkan ke Mikhmon...', 'success');
          setTimeout(() => {
            window.location.href = res.target_url;
          }, 800);
        } else {
          showAlert(res && res.message ? res.message : 'Kunci lisensi tidak valid atau ditolak oleh server.', 'error');
          btn.disabled = false;
          btn.innerText = '🔑 Aktivasi Lisensi Sekarang';
        }
      } catch (err) {
        showAlert('Gagal menghubungi service aktivasi: ' + err, 'error');
        btn.disabled = false;
        btn.innerText = '🔑 Aktivasi Lisensi Sekarang';
      }
    }

    async function doTrial() {
      const btn = document.getElementById('btnTrial');
      btn.disabled = true;
      btn.innerText = 'Mendaftarkan Trial...';

      try {
        const res = await window.activateTrial();
        if (res && res.success) {
          showAlert('Trial Berhasil Diaktifkan! Membuka Mikhmon...', 'success');
          setTimeout(() => {
            window.location.href = res.target_url;
          }, 800);
        } else {
          showAlert(res && res.message ? res.message : 'Trial sudah pernah digunakan pada komputer ini.', 'error');
          btn.disabled = false;
          btn.innerText = '⏱️ Mulai Trial 7 Hari Gratis';
        }
      } catch (err) {
        showAlert('Gagal mengaktifkan trial: ' + err, 'error');
        btn.disabled = false;
        btn.innerText = '⏱️ Mulai Trial 7 Hari Gratis';
      }
    }
  </script>
</body>
</html>`, hwid)
}
