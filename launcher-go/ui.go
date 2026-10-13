package main

import (
	"fmt"
	"runtime"
	"syscall"
	"time"
	"unsafe"
)

var (
	user32DLL   = syscall.NewLazyDLL("user32.dll")
	kernel32DLL = syscall.NewLazyDLL("kernel32.dll")
	gdi32DLL    = syscall.NewLazyDLL("gdi32.dll")

	procRegisterClassExW   = user32DLL.NewProc("RegisterClassExW")
	procCreateWindowExW     = user32DLL.NewProc("CreateWindowExW")
	procDefWindowProcW      = user32DLL.NewProc("DefWindowProcW")
	procShowWindow          = user32DLL.NewProc("ShowWindow")
	procUpdateWindow        = user32DLL.NewProc("UpdateWindow")
	procGetMessageW         = user32DLL.NewProc("GetMessageW")
	procTranslateMessage    = user32DLL.NewProc("TranslateMessage")
	procDispatchMessageW    = user32DLL.NewProc("DispatchMessageW")
	procPostQuitMessage     = user32DLL.NewProc("PostQuitMessage")
	procPostMessageW        = user32DLL.NewProc("PostMessageW")
	procSendMessageW        = user32DLL.NewProc("SendMessageW")
	procSetWindowTextW      = user32DLL.NewProc("SetWindowTextW")
	procGetWindowTextW      = user32DLL.NewProc("GetWindowTextW")
	procDestroyWindow       = user32DLL.NewProc("DestroyWindow")
	procMessageBoxW         = user32DLL.NewProc("MessageBoxW")
	procCreatePopupMenu     = user32DLL.NewProc("CreatePopupMenu")
	procAppendMenuW         = user32DLL.NewProc("AppendMenuW")
	procTrackPopupMenu      = user32DLL.NewProc("TrackPopupMenu")
	procDestroyMenu         = user32DLL.NewProc("DestroyMenu")
	procGetCursorPos        = user32DLL.NewProc("GetCursorPos")
	procSetForegroundWindow = user32DLL.NewProc("SetForegroundWindow")
	procLoadIconW           = user32DLL.NewProc("LoadIconW")
	procLoadCursorW         = user32DLL.NewProc("LoadCursorW")
	procGetSystemMetrics    = user32DLL.NewProc("GetSystemMetrics")

	procGetModuleHandleW = kernel32DLL.NewProc("GetModuleHandleW")
	procCreateFontW      = gdi32DLL.NewProc("CreateFontW")
)

const (
	WM_DESTROY      = 0x0002
	WM_CLOSE        = 0x0010
	WM_COMMAND      = 0x0111
	WM_SYSCOMMAND   = 0x0112
	WM_APP          = 0x8000
	WM_TRAYICON     = WM_APP + 1
	WM_RBUTTONUP    = 0x0205
	WM_LBUTTONUP    = 0x0202
	WM_LBUTTONDBLCLK= 0x0203
	SC_MINIMIZE     = 0xF020

	WS_OVERLAPPED   = 0x00000000
	WS_CAPTION      = 0x00C00000
	WS_SYSMENU      = 0x00080000
	WS_MINIMIZEBOX  = 0x00020000
	WS_VISIBLE      = 0x10000000
	WS_CHILD        = 0x40000000
	WS_TABSTOP      = 0x00010000
	WS_BORDER       = 0x00800000

	SW_HIDE       = 0
	SW_SHOWNORMAL = 1
	SW_SHOW       = 5
	SW_RESTORE    = 9

	NIM_ADD     = 0x00000000
	NIM_MODIFY  = 0x00000001
	NIM_DELETE  = 0x00000002
	NIF_MESSAGE = 0x00000001
	NIF_ICON    = 0x00000002
	NIF_TIP     = 0x00000004
	NIF_INFO    = 0x00000010
	NIIF_INFO   = 0x00000001

	MF_STRING    = 0x00000000
	MF_SEPARATOR = 0x00000800

	MB_OK              = 0x00000000
	MB_ICONINFORMATION = 0x00000040
	MB_ICONWARNING     = 0x00000030
	MB_ICONERROR       = 0x00000010

	ID_BTN_OPEN      = 1001
	ID_BTN_TOGGLE    = 1002
	ID_BTN_ACTIVATE  = 1003
	ID_BTN_MINIMIZE  = 1004

	ID_TRAY_OPEN     = 2001
	ID_TRAY_SHOW     = 2002
	ID_TRAY_RESTART  = 2003
	ID_TRAY_STOP     = 2004
	ID_TRAY_LICENSE  = 2005
	ID_TRAY_EXIT     = 2006
)

type WNDCLASSEXW struct {
	cbSize        uint32
	style         uint32
	lpfnWndProc   uintptr
	cbClsExtra    int32
	cbWndExtra    int32
	hInstance     uintptr
	hIcon         uintptr
	hCursor       uintptr
	hbrBackground uintptr
	lpszMenuName  *uint16
	lpszClassName *uint16
	hIconSm       uintptr
}

type POINT struct {
	X int32
	Y int32
}

type MSG struct {
	hwnd    uintptr
	message uint32
	wParam  uintptr
	lParam  uintptr
	time    uint32
	pt      POINT
}

type NOTIFYICONDATAW struct {
	cbSize           uint32
	hWnd             uintptr
	uID              uint32
	uFlags           uint32
	uCallbackMessage uint32
	hIcon            uintptr
	szTip            [128]uint16
	dwState          uint32
	dwStateMask      uint32
	szInfo           [256]uint16
	uTimeoutOrVersion uint32
	szInfoTitle      [64]uint16
	dwInfoFlags      uint32
	guidItem         [16]byte
	hBalloonIcon     uintptr
}

var (
	appHwnd        uintptr
	hFontNormal    uintptr
	hFontBold      uintptr
	hFontHeader    uintptr
	hAppIcon       uintptr
	trayData       NOTIFYICONDATAW

	lblStatus      uintptr
	lblUrlLocal    uintptr
	lblUrlLAN      uintptr
	lblLicense     uintptr
	lblHwid        uintptr
	btnToggle      uintptr

	globalSM       *ServerManager
	globalLic      *LicenseData
	globalBaseDir  string
	globalHwid     string
)

func utf16Ptr(s string) *uint16 {
	p, _ := syscall.UTF16PtrFromString(s)
	return p
}

func showMsg(title, text string, iconType uint32) {
	procMessageBoxW.Call(appHwnd, uintptr(unsafe.Pointer(utf16Ptr(text))), uintptr(unsafe.Pointer(utf16Ptr(title))), uintptr(iconType))
}

func setupFonts() {
	segoe := utf16Ptr("Segoe UI")
	hFontNormal, _, _ = procCreateFontW.Call(15, 0, 0, 0, 400, 0, 0, 0, 1, 0, 0, 0, 0, uintptr(unsafe.Pointer(segoe)))
	hFontBold, _, _ = procCreateFontW.Call(15, 0, 0, 0, 700, 0, 0, 0, 1, 0, 0, 0, 0, uintptr(unsafe.Pointer(segoe)))
	hFontHeader, _, _ = procCreateFontW.Call(19, 0, 0, 0, 800, 0, 0, 0, 1, 0, 0, 0, 0, uintptr(unsafe.Pointer(segoe)))
}

func setControlFont(hwnd uintptr, font uintptr) {
	procSendMessageW.Call(hwnd, 0x0030, font, 1) // WM_SETFONT
}

func initTrayIcon(hwnd uintptr) {
	trayData.cbSize = uint32(unsafe.Sizeof(trayData))
	trayData.hWnd = hwnd
	trayData.uID = 1
	trayData.uFlags = NIF_MESSAGE | NIF_ICON | NIF_TIP
	trayData.uCallbackMessage = WM_TRAYICON
	trayData.hIcon = hAppIcon

	tip := "NODERA Mikhmon Server (127.0.0.1:8080)"
	copyUtf16Tip(&trayData.szTip, tip)

	procShellNotifyIconW.Call(NIM_ADD, uintptr(unsafe.Pointer(&trayData)))
}

func updateTrayTip(tip string) {
	copyUtf16Tip(&trayData.szTip, tip)
	procShellNotifyIconW.Call(NIM_MODIFY, uintptr(unsafe.Pointer(&trayData)))
}

func showTrayBalloon(title, message string) {
	trayData.uFlags |= NIF_INFO
	copyUtf16Info(&trayData.szInfoTitle, title)
	copyUtf16Info256(&trayData.szInfo, message)
	trayData.dwInfoFlags = NIIF_INFO
	procShellNotifyIconW.Call(NIM_MODIFY, uintptr(unsafe.Pointer(&trayData)))
}

func removeTrayIcon() {
	procShellNotifyIconW.Call(NIM_DELETE, uintptr(unsafe.Pointer(&trayData)))
}

func copyUtf16Tip(dst *[128]uint16, src string) {
	chars, _ := syscall.UTF16FromString(src)
	copy(dst[:], chars)
}

func copyUtf16Info(dst *[64]uint16, src string) {
	chars, _ := syscall.UTF16FromString(src)
	copy(dst[:], chars)
}

func copyUtf16Info256(dst *[256]uint16, src string) {
	chars, _ := syscall.UTF16FromString(src)
	copy(dst[:], chars)
}

func showTrayMenu(hwnd uintptr) {
	hMenu, _, _ := procCreatePopupMenu.Call()
	defer procDestroyMenu.Call(hMenu)

	procAppendMenuW.Call(hMenu, MF_STRING, ID_TRAY_OPEN, uintptr(unsafe.Pointer(utf16Ptr("🌐 Buka Mikhmon"))))
	procAppendMenuW.Call(hMenu, MF_STRING, ID_TRAY_SHOW, uintptr(unsafe.Pointer(utf16Ptr("📋 Tampilkan Jendela Kontrol"))))
	procAppendMenuW.Call(hMenu, MF_SEPARATOR, 0, 0)
	procAppendMenuW.Call(hMenu, MF_STRING, ID_TRAY_LICENSE, uintptr(unsafe.Pointer(utf16Ptr("🔑 Informasi Lisensi & HWID"))))
	procAppendMenuW.Call(hMenu, MF_STRING, ID_TRAY_RESTART, uintptr(unsafe.Pointer(utf16Ptr("🔄 Restart Server"))))
	procAppendMenuW.Call(hMenu, MF_STRING, ID_TRAY_STOP, uintptr(unsafe.Pointer(utf16Ptr("🛑 Hentikan Server"))))
	procAppendMenuW.Call(hMenu, MF_SEPARATOR, 0, 0)
	procAppendMenuW.Call(hMenu, MF_STRING, ID_TRAY_EXIT, uintptr(unsafe.Pointer(utf16Ptr("❌ Keluar"))))

	var pt POINT
	procGetCursorPos.Call(uintptr(unsafe.Pointer(&pt)))
	procSetForegroundWindow.Call(hwnd)
	procTrackPopupMenu.Call(hMenu, 0x0002|0x0020, uintptr(pt.X), uintptr(pt.Y), 0, hwnd, 0)
}

func updateUIState() {
	if globalSM.IsRunning {
		procSetWindowTextW.Call(lblStatus, uintptr(unsafe.Pointer(utf16Ptr(fmt.Sprintf("● STATUS: BERJALAN AKTIF (Port %d)", globalSM.Port)))))
		procSetWindowTextW.Call(lblUrlLocal, uintptr(unsafe.Pointer(utf16Ptr(fmt.Sprintf("http://127.0.0.1:%d", globalSM.Port)))))
		procSetWindowTextW.Call(lblUrlLAN, uintptr(unsafe.Pointer(utf16Ptr(fmt.Sprintf("http://%s:%d", globalSM.LocalIP, globalSM.Port)))))
		procSetWindowTextW.Call(btnToggle, uintptr(unsafe.Pointer(utf16Ptr("🛑 Hentikan Server"))))
		updateTrayTip(fmt.Sprintf("Mikhmon Server: Aktif (Port %d)", globalSM.Port))
	} else {
		procSetWindowTextW.Call(lblStatus, uintptr(unsafe.Pointer(utf16Ptr("○ STATUS: SERVER BERHENTI"))))
		procSetWindowTextW.Call(btnToggle, uintptr(unsafe.Pointer(utf16Ptr("🚀 Jalankan Server"))))
		updateTrayTip("Mikhmon Server: Berhenti")
	}

	licText := "Lisensi: BELUM TERAKTIVASI"
	if globalLic != nil && globalLic.Status == "ACTIVE" {
		if globalLic.IsTrial {
			licText = fmt.Sprintf("Lisensi: TRIAL (%d Hari Lagi)", globalLic.DaysLeft)
		} else {
			exp := globalLic.ExpiresAt
			if exp == "" || exp == "LIFETIME" {
				licText = "Lisensi: AKTIF (LIFETIME RESMI)"
			} else {
				licText = fmt.Sprintf("Lisensi: AKTIF (s/d %s)", exp[:10])
			}
		}
	}
	procSetWindowTextW.Call(lblLicense, uintptr(unsafe.Pointer(utf16Ptr(licText))))
}

func toggleServer() {
	if globalSM.IsRunning {
		globalSM.Stop()
	} else {
		if err := globalSM.Start(); err != nil {
			showMsg("Gagal Menjalankan Server", fmt.Sprintf("Error: %v", err), MB_ICONERROR)
		}
	}
	updateUIState()
}

func openMikhmonUrl() {
	if !globalSM.IsRunning {
		if err := globalSM.Start(); err != nil {
			showMsg("Gagal Menjalankan Server", fmt.Sprintf("Error: %v", err), MB_ICONERROR)
			return
		}
		updateUIState()
	}
	url := fmt.Sprintf("http://127.0.0.1:%d/admin.php?id=sessions", globalSM.Port)
	_ = OpenBrowser(url)
}

func promptActivationDialog() {
	info := fmt.Sprintf(
		"HARDWARE ID PERANGKAT ANDA:\n%s\n\n"+
			"Status: %s\n"+
			"Kunci Lisensi: %s\n\n"+
			"Untuk aktivasi atau perpanjangan lisensi:\n"+
			"1. Kunjungi Cloud Panel: https://panel.dgtlnetsolution.com\n"+
			"2. Daftarkan HWID perangkat di atas untuk mendapatkan License Key.\n"+
			"3. Buka halaman aktivasi di browser?",
		globalHwid,
		func() string {
			if globalLic != nil && globalLic.Status == "ACTIVE" {
				return globalLic.ProductName
			}
			return "Belum Teraktivasi"
		}(),
		func() string {
			if globalLic != nil && globalLic.LicenseKey != "" {
				return globalLic.LicenseKey
			}
			return "-"
		}(),
	)

	res, _, _ := procMessageBoxW.Call(
		appHwnd,
		uintptr(unsafe.Pointer(utf16Ptr(info))),
		uintptr(unsafe.Pointer(utf16Ptr("Informasi Lisensi Mikhmon Desktop"))),
		0x00000004|0x00000040, // MB_YESNO | MB_ICONINFORMATION
	)

	if res == 6 { // IDYES
		_ = OpenBrowser(fmt.Sprintf("http://127.0.0.1:%d/activation.html", globalSM.Port))
	}
}

func wndProc(hwnd uintptr, msg uint32, wParam, lParam uintptr) uintptr {
	switch msg {
	case WM_SYSCOMMAND:
		if wParam&0xFFF0 == SC_MINIMIZE {
			procShowWindow.Call(hwnd, SW_HIDE)
			showTrayBalloon("NODERA Mikhmon Server", fmt.Sprintf("Server berjalan di background pada port %d. Klik icon tray untuk membuka kembali.", globalSM.Port))
			return 0
		}

	case WM_COMMAND:
		ctrlId := int(wParam & 0xFFFF)
		switch ctrlId {
		case ID_BTN_OPEN:
			openMikhmonUrl()
		case ID_BTN_TOGGLE:
			toggleServer()
		case ID_BTN_ACTIVATE:
			promptActivationDialog()
		case ID_BTN_MINIMIZE:
			procShowWindow.Call(hwnd, SW_HIDE)
			showTrayBalloon("NODERA Mikhmon Server", "Aplikasi disembunyikan ke System Tray.")
		case ID_TRAY_OPEN:
			openMikhmonUrl()
		case ID_TRAY_SHOW:
			procShowWindow.Call(hwnd, SW_RESTORE)
			procSetForegroundWindow.Call(hwnd)
		case ID_TRAY_LICENSE:
			promptActivationDialog()
		case ID_TRAY_RESTART:
			globalSM.Stop()
			time.Sleep(300 * time.Millisecond)
			_ = globalSM.Start()
			updateUIState()
			showTrayBalloon("Server Direstart", fmt.Sprintf("Mikhmon aktif kembali pada port %d.", globalSM.Port))
		case ID_TRAY_STOP:
			globalSM.Stop()
			updateUIState()
		case ID_TRAY_EXIT:
			globalSM.Stop()
			removeTrayIcon()
			procDestroyWindow.Call(hwnd)
		}
		return 0

	case WM_TRAYICON:
		switch lParam {
		case WM_RBUTTONUP:
			showTrayMenu(hwnd)
		case WM_LBUTTONDBLCLK, WM_LBUTTONUP:
			procShowWindow.Call(hwnd, SW_RESTORE)
			procSetForegroundWindow.Call(hwnd)
		}
		return 0

	case WM_CLOSE:
		// Menyembunyikan ke tray saat tombol X ditekan, bukan langsung quit
		procShowWindow.Call(hwnd, SW_HIDE)
		showTrayBalloon("Mikhmon Berjalan di Background", "Aplikasi masih aktif di tray. Klik kanan icon tray lalu pilih 'Keluar' untuk menutup sepenuhnya.")
		return 0

	case WM_DESTROY:
		globalSM.Stop()
		removeTrayIcon()
		procPostQuitMessage.Call(0)
		return 0
	}

	r, _, _ := procDefWindowProcW.Call(hwnd, uintptr(msg), wParam, lParam)
	return r
}

func CreateAppWindow(baseDir string, sm *ServerManager, lic *LicenseData, hwid string) {
	runtime.LockOSThread()
	globalBaseDir = baseDir
	globalSM = sm
	globalLic = lic
	globalHwid = hwid

	hInstance, _, _ := procGetModuleHandleW.Call(0)
	className := utf16Ptr("NODERA_MIKHMON_SERVER_NATIVE")

	// Load Embedded Icon if available, else standard App icon
	hAppIcon, _, _ = procLoadIconW.Call(hInstance, 1)
	if hAppIcon == 0 {
		hAppIcon, _, _ = procLoadIconW.Call(0, 32512) // IDI_APPLICATION
	}
	hCursor, _, _ := procLoadCursorW.Call(0, 32512) // IDC_ARROW

	var wc WNDCLASSEXW
	wc.cbSize = uint32(unsafe.Sizeof(wc))
	wc.style = 0x0003 // CS_HREDRAW | CS_VREDRAW
	wc.lpfnWndProc = syscall.NewCallback(wndProc)
	wc.hInstance = hInstance
	wc.hIcon = hAppIcon
	wc.hCursor = hCursor
	wc.hbrBackground = 16 // COLOR_BTNFACE + 1
	wc.lpszClassName = className
	wc.hIconSm = hAppIcon

	procRegisterClassExW.Call(uintptr(unsafe.Pointer(&wc)))

	// Window Dimensions & Screen Centering
	winW, winH := 450, 390
	scrW, _, _ := procGetSystemMetrics.Call(0) // SM_CXSCREEN
	scrH, _, _ := procGetSystemMetrics.Call(1) // SM_CYSCREEN
	posX := (int(scrW) - winW) / 2
	posY := (int(scrH) - winH) / 2

	windowTitle := utf16Ptr("Mikhmon Server by NODERA v3.2")
	style := uint32(WS_OVERLAPPED | WS_CAPTION | WS_SYSMENU | WS_MINIMIZEBOX)

	appHwnd, _, _ = procCreateWindowExW.Call(
		0x00040000, // WS_EX_APPWINDOW
		uintptr(unsafe.Pointer(className)),
		uintptr(unsafe.Pointer(windowTitle)),
		uintptr(style),
		uintptr(posX), uintptr(posY),
		uintptr(winW), uintptr(winH),
		0, 0, hInstance, 0,
	)

	setupFonts()

	// 1. Header Area
	hHeader, _, _ := procCreateWindowExW.Call(
		0, uintptr(unsafe.Pointer(utf16Ptr("STATIC"))),
		uintptr(unsafe.Pointer(utf16Ptr("NODERA Mikhmon Desktop Server"))),
		uintptr(WS_CHILD|WS_VISIBLE),
		24, 18, 400, 26,
		appHwnd, 0, hInstance, 0,
	)
	setControlFont(hHeader, hFontHeader)

	hSub, _, _ := procCreateWindowExW.Call(
		0, uintptr(unsafe.Pointer(utf16Ptr("STATIC"))),
		uintptr(unsafe.Pointer(utf16Ptr("MikroTik Hotspot Management Engine • Standalone Edition"))),
		uintptr(WS_CHILD|WS_VISIBLE),
		24, 46, 400, 20,
		appHwnd, 0, hInstance, 0,
	)
	setControlFont(hSub, hFontNormal)

	// 2. Status Box
	lblStatus, _, _ = procCreateWindowExW.Call(
		0, uintptr(unsafe.Pointer(utf16Ptr("STATIC"))),
		uintptr(unsafe.Pointer(utf16Ptr("● STATUS: MEMULAI..."))),
		uintptr(WS_CHILD|WS_VISIBLE),
		24, 78, 400, 22,
		appHwnd, 0, hInstance, 0,
	)
	setControlFont(lblStatus, hFontBold)

	lblUrlStatic, _, _ := procCreateWindowExW.Call(
		0, uintptr(unsafe.Pointer(utf16Ptr("STATIC"))),
		uintptr(unsafe.Pointer(utf16Ptr("Akses Komputer Lokal :"))),
		uintptr(WS_CHILD|WS_VISIBLE),
		24, 108, 160, 20,
		appHwnd, 0, hInstance, 0,
	)
	setControlFont(lblUrlStatic, hFontNormal)

	lblUrlLocal, _, _ = procCreateWindowExW.Call(
		0, uintptr(unsafe.Pointer(utf16Ptr("STATIC"))),
		uintptr(unsafe.Pointer(utf16Ptr("http://127.0.0.1:8080"))),
		uintptr(WS_CHILD|WS_VISIBLE),
		185, 108, 230, 20,
		appHwnd, 0, hInstance, 0,
	)
	setControlFont(lblUrlLocal, hFontBold)

	lblLanStatic, _, _ := procCreateWindowExW.Call(
		0, uintptr(unsafe.Pointer(utf16Ptr("STATIC"))),
		uintptr(unsafe.Pointer(utf16Ptr("Akses Jaringan LAN :"))),
		uintptr(WS_CHILD|WS_VISIBLE),
		24, 132, 160, 20,
		appHwnd, 0, hInstance, 0,
	)
	setControlFont(lblLanStatic, hFontNormal)

	lblUrlLAN, _, _ = procCreateWindowExW.Call(
		0, uintptr(unsafe.Pointer(utf16Ptr("STATIC"))),
		uintptr(unsafe.Pointer(utf16Ptr("http://127.0.0.1:8080"))),
		uintptr(WS_CHILD|WS_VISIBLE),
		185, 132, 230, 20,
		appHwnd, 0, hInstance, 0,
	)
	setControlFont(lblUrlLAN, hFontBold)

	// 3. Hardware ID & License Information
	lblHwidStatic, _, _ := procCreateWindowExW.Call(
		0, uintptr(unsafe.Pointer(utf16Ptr("STATIC"))),
		uintptr(unsafe.Pointer(utf16Ptr("Hardware ID (HWID)   :"))),
		uintptr(WS_CHILD|WS_VISIBLE),
		24, 160, 160, 20,
		appHwnd, 0, hInstance, 0,
	)
	setControlFont(lblHwidStatic, hFontNormal)

	lblHwid, _, _ = procCreateWindowExW.Call(
		0, uintptr(unsafe.Pointer(utf16Ptr("STATIC"))),
		uintptr(unsafe.Pointer(utf16Ptr(hwid))),
		uintptr(WS_CHILD|WS_VISIBLE),
		185, 160, 230, 20,
		appHwnd, 0, hInstance, 0,
	)
	setControlFont(lblHwid, hFontNormal)

	lblLicense, _, _ = procCreateWindowExW.Call(
		0, uintptr(unsafe.Pointer(utf16Ptr("STATIC"))),
		uintptr(unsafe.Pointer(utf16Ptr("Lisensi: Memeriksa..."))),
		uintptr(WS_CHILD|WS_VISIBLE),
		24, 186, 390, 22,
		appHwnd, 0, hInstance, 0,
	)
	setControlFont(lblLicense, hFontBold)

	// 4. Action Buttons
	btnOpen, _, _ := procCreateWindowExW.Call(
		0, uintptr(unsafe.Pointer(utf16Ptr("BUTTON"))),
		uintptr(unsafe.Pointer(utf16Ptr("🌐 Buka Mikhmon Web"))),
		uintptr(WS_CHILD|WS_VISIBLE|WS_TABSTOP|0x00000001), // BS_DEFPUSHBUTTON
		24, 222, 190, 38,
		appHwnd, uintptr(ID_BTN_OPEN), hInstance, 0,
	)
	setControlFont(btnOpen, hFontBold)

	btnToggle, _, _ = procCreateWindowExW.Call(
		0, uintptr(unsafe.Pointer(utf16Ptr("BUTTON"))),
		uintptr(unsafe.Pointer(utf16Ptr("🛑 Hentikan Server"))),
		uintptr(WS_CHILD|WS_VISIBLE|WS_TABSTOP),
		224, 222, 190, 38,
		appHwnd, uintptr(ID_BTN_TOGGLE), hInstance, 0,
	)
	setControlFont(btnToggle, hFontNormal)

	btnActivate, _, _ := procCreateWindowExW.Call(
		0, uintptr(unsafe.Pointer(utf16Ptr("BUTTON"))),
		uintptr(unsafe.Pointer(utf16Ptr("🔑 Info / Aktivasi Lisensi"))),
		uintptr(WS_CHILD|WS_VISIBLE|WS_TABSTOP),
		24, 268, 190, 34,
		appHwnd, uintptr(ID_BTN_ACTIVATE), hInstance, 0,
	)
	setControlFont(btnActivate, hFontNormal)

	btnMin, _, _ := procCreateWindowExW.Call(
		0, uintptr(unsafe.Pointer(utf16Ptr("BUTTON"))),
		uintptr(unsafe.Pointer(utf16Ptr("📥 Sembunyikan ke Tray"))),
		uintptr(WS_CHILD|WS_VISIBLE|WS_TABSTOP),
		224, 268, 190, 34,
		appHwnd, uintptr(ID_BTN_MINIMIZE), hInstance, 0,
	)
	setControlFont(btnMin, hFontNormal)

	// Footer copyright
	hFooter, _, _ := procCreateWindowExW.Call(
		0, uintptr(unsafe.Pointer(utf16Ptr("STATIC"))),
		uintptr(unsafe.Pointer(utf16Ptr("Copyright © 2026 NODERA (dgtlnetsolution.com). All rights reserved."))),
		uintptr(WS_CHILD|WS_VISIBLE),
		24, 316, 400, 18,
		appHwnd, 0, hInstance, 0,
	)
	setControlFont(hFooter, hFontNormal)

	// Start Server automatically
	if err := globalSM.Start(); err != nil {
		showMsg("Peringatan PHP Runtime", fmt.Sprintf("Gagal memulai PHP web server: %v\nPastikan folder bin\\php-win tersedia.", err), MB_ICONERROR)
	}

	initTrayIcon(appHwnd)
	updateUIState()

	// Show Window
	procShowWindow.Call(appHwnd, SW_SHOW)
	procUpdateWindow.Call(appHwnd)

	// Balloon Notification
	showTrayBalloon("Mikhmon Server by NODERA", fmt.Sprintf("Server aktif di http://127.0.0.1:%d", globalSM.Port))

	// Message Loop
	var msg MSG
	for {
		r, _, _ := procGetMessageW.Call(uintptr(unsafe.Pointer(&msg)), 0, 0, 0)
		if int32(r) <= 0 {
			break
		}
		procTranslateMessage.Call(uintptr(unsafe.Pointer(&msg)))
		procDispatchMessageW.Call(uintptr(unsafe.Pointer(&msg)))
	}
}
