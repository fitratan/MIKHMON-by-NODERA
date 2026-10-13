package main

import (
	"fmt"
	"os"
	"path/filepath"
	"time"
)

var (
	procCreateMutexW = kernel32DLL.NewProc("CreateMutexW")
	procFindWindowW  = user32DLL.NewProc("FindWindowW")
)

const (
	AppMutexName = "NODERA_MIKHMON_SERVER_SINGLETON_MUTEX_2026"
)

func main() {
	// 1. Single Instance Guard (Prevent multiple Mikhmon servers on the same port)
	mName := utf16Ptr(AppMutexName)
	hMutex, _, _ := procCreateMutexW.Call(0, 1, uintptr(unsafePointer(mName)))
	lastErr, _, _ := kernel32DLL.NewProc("GetLastError").Call()
	if lastErr == 183 { // ERROR_ALREADY_EXISTS
		// Restore existing window if found
		cName := utf16Ptr("NODERA_MIKHMON_SERVER_NATIVE")
		existingHwnd, _, _ := procFindWindowW.Call(uintptr(unsafePointer(cName)), 0)
		if existingHwnd != 0 {
			procShowWindow.Call(existingHwnd, SW_RESTORE)
			procSetForegroundWindow.Call(existingHwnd)
		}
		// Also open browser to default Mikhmon port
		_ = OpenBrowser("http://127.0.0.1:8080/admin.php?id=sessions")
		return
	}
	defer procCloseHandle.Call(hMutex)

	// 2. Resolve Base Directory
	exePath, err := os.Executable()
	if err != nil {
		exePath = "."
	}
	baseDir := filepath.Dir(exePath)

	// 3. Hardware Fingerprint (HWID)
	hwid := GetHardwareID(baseDir)

	// 4. Anti-Clock Rollback Guard
	if !CheckClockGuard(baseDir) {
		showMsg("Peringatan Keamanan Sistem", "Manipulasi waktu atau pemunduran jam Windows terdeteksi. Silakan cocokkan waktu sistem Anda.", MB_ICONWARNING)
	}

	// 5. License Verification & Auto-Trial
	lic, err := ReadSavedLicense(baseDir)
	isLicensed := false

	if err == nil && lic != nil && lic.Status == "ACTIVE" {
		if VerifyLicenseSignature(*lic) {
			isLicensed = true
		}
	}

	// If unlicensed, auto-activate 7-day trial on first run
	trialMarker := filepath.Join(baseDir, "mikhmon", "config", ".trial_attempted")
	if !isLicensed && !fileExists(trialMarker) {
		_ = os.WriteFile(trialMarker, []byte(time.Now().Format(time.RFC3339)), 0644)
		if trialLic, err := ActivateTrial(baseDir); err == nil && trialLic != nil {
			lic = trialLic
			isLicensed = true
		}
	}

	// 6. Initialize Server Manager
	sm := NewServerManager(baseDir, 8080)

	// Auto open browser 1.2s after server start
	go func() {
		time.Sleep(1200 * time.Millisecond)
		if sm.IsRunning {
			url := fmt.Sprintf("http://127.0.0.1:%d/admin.php?id=sessions", sm.Port)
			_ = OpenBrowser(url)
		}
	}()

	// 7. Launch Win32 GUI & System Tray Loop
	CreateAppWindow(baseDir, sm, lic, hwid)
}

var procCloseHandle = kernel32DLL.NewProc("CloseHandle")

func fileExists(path string) bool {
	_, err := os.Stat(path)
	return err == nil
}
