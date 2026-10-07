package main

import (
	"flag"
	"os"
	"path/filepath"
	"strings"
	"syscall"
)

var (
	procCreateMutexW = kernel32DLL.NewProc("CreateMutexW")
	procFindWindowW  = user32DLL.NewProc("FindWindowW")
	procCloseHandle  = kernel32DLL.NewProc("CloseHandle")
)

const (
	AppMutexName = "NODERA_MIKHMON_DESKTOP_SINGLETON_MUTEX_2026"
)

func main() {
	serverOnly := flag.Bool("server-only", false, "Jalankan mode server tray legacy tanpa jendela WebView")
	flag.Parse()

	// 1. Single Instance Guard
	mName := utf16Ptr(AppMutexName)
	hMutex, _, _ := procCreateMutexW.Call(0, 1, uintptr(unsafePointer(mName)))
	lastErr, _, _ := kernel32DLL.NewProc("GetLastError").Call()
	if lastErr == 183 { // ERROR_ALREADY_EXISTS
		// Restore existing window if found
		cName := utf16Ptr("webview")
		existingHwnd, _, _ := procFindWindowW.Call(uintptr(unsafePointer(cName)), 0)
		if existingHwnd != 0 {
			procShowWindow.Call(existingHwnd, SW_RESTORE)
			procSetForegroundWindow.Call(existingHwnd)
		}
		return
	}
	defer procCloseHandle.Call(hMutex)

	// 2. Resolve Base Directory
	exePath, err := os.Executable()
	if err != nil {
		exePath = "."
	}
	baseDir := filepath.Dir(exePath)

	// 3. Mode Selection
	exeName := filepath.Base(exePath)
	if *serverOnly || strings.EqualFold(exeName, "MikhmonServer.exe") {
		hwid := GetHardwareID(baseDir)
		lic, _ := ReadSavedLicense(baseDir)
		sm := NewServerManager(baseDir, 8080, hwid)
		CreateAppWindow(baseDir, sm, lic, hwid)
		return
	}

	// Default: Run Standalone All-in-One Desktop App (Native WebView2 Window / Dedicated App Window)
	RunDesktopApp(baseDir)
}

func isRunningUnderWine() bool {
	ntdll := syscall.NewLazyDLL("ntdll.dll")
	return ntdll.NewProc("wine_get_version").Find() == nil
}

func fileExists(path string) bool {
	_, err := os.Stat(path)
	return err == nil
}
