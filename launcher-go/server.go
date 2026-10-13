package main

import (
	"fmt"
	"net"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"syscall"
	"time"
	"unsafe"
)

var (
	shell32DLL           = syscall.NewLazyDLL("shell32.dll")
	procShellExecuteW    = shell32DLL.NewProc("ShellExecuteW")
	procShellNotifyIconW = shell32DLL.NewProc("Shell_NotifyIconW")
)

type ServerManager struct {
	PhpBinary string
	DocRoot   string
	BaseDir   string
	Port      int
	Process   *exec.Cmd
	IsRunning bool
	LocalIP   string
	Hwid      string
}

// OpenBrowser opens the URL in the user's default Windows browser
func OpenBrowser(url string) error {
	verb, _ := syscall.UTF16PtrFromString("open")
	file, _ := syscall.UTF16PtrFromString(url)
	procShellExecuteW.Call(0, uintptr(unsafePointer(verb)), uintptr(unsafePointer(file)), 0, 0, 1)
	return nil
}

func unsafePointer(p *uint16) uintptr {
	return uintptr(unsafe.Pointer(p))
}

// FindPhpBinary locates portable or system PHP executable
func FindPhpBinary(baseDir string) string {
	candidates := []string{
		filepath.Join(baseDir, "bin", "php-win", "php.exe"),
		filepath.Join(baseDir, "php", "php.exe"),
		filepath.Join(baseDir, "..", "bin", "php-win", "php.exe"),
		filepath.Join(baseDir, "..", "php", "php.exe"),
		filepath.Join(baseDir, "bin", "php", "php.exe"),
		`C:\xampp\php\php.exe`,
		`C:\laragon\bin\php\php.exe`,
	}

	for _, c := range candidates {
		if fi, err := os.Stat(c); err == nil && !fi.IsDir() {
			return c
		}
	}

	if sysPhp, err := exec.LookPath("php.exe"); err == nil {
		return sysPhp
	}
	if sysPhp, err := exec.LookPath("php"); err == nil {
		return sysPhp
	}

	return ""
}

// FindDocRoot locates Mikhmon document root
func FindDocRoot(baseDir string) string {
	candidates := []string{
		filepath.Join(baseDir, "mikhmon"),
		filepath.Join(baseDir, "..", "mikhmon"),
		baseDir,
	}

	for _, c := range candidates {
		idx := filepath.Join(c, "index.php")
		if fi, err := os.Stat(idx); err == nil && !fi.IsDir() {
			return c
		}
	}
	return baseDir
}

// GetLocalLANIP retrieves primary LAN IP address
func GetLocalLANIP() string {
	addrs, err := net.InterfaceAddrs()
	if err != nil {
		return "127.0.0.1"
	}
	for _, a := range addrs {
		if ipNet, ok := a.(*net.IPNet); ok && !ipNet.IP.IsLoopback() {
			if ipNet.IP.To4() != nil {
				ipStr := ipNet.IP.String()
				if strings.HasPrefix(ipStr, "192.168.") || strings.HasPrefix(ipStr, "10.") || strings.HasPrefix(ipStr, "172.") {
					return ipStr
				}
			}
		}
	}
	return "127.0.0.1"
}

// ConfigurePhpIni ensures php.ini has absolute paths for extension_dir and TLS CA certificates
func ConfigurePhpIni(phpBin, baseDir string) {
	phpDir := filepath.Dir(phpBin)
	iniPath := filepath.Join(phpDir, "php.ini")
	extDir := filepath.Join(phpDir, "ext")

	caCandidates := []string{
		filepath.Join(phpDir, "cacert.pem"),
		filepath.Join(baseDir, "cacert.pem"),
		filepath.Join(baseDir, "mikhmon", "cacert.pem"),
		filepath.Join(baseDir, "resources", "certs", "cacert.pem"),
		filepath.Join(baseDir, "..", "resources", "certs", "cacert.pem"),
	}

	var caPath string
	for _, c := range caCandidates {
		if _, err := os.Stat(c); err == nil {
			caPath = c
			break
		}
	}

	data, err := os.ReadFile(iniPath)
	if err != nil {
		return
	}
	content := string(data)

	extDirClean := filepath.Clean(extDir)
	caPathClean := filepath.Clean(caPath)

	lines := strings.Split(content, "\n")
	var newLines []string
	extDirFound := false

	for _, line := range lines {
		trimmed := strings.TrimSpace(line)
		if strings.HasPrefix(trimmed, "extension_dir") {
			newLines = append(newLines, fmt.Sprintf("extension_dir = \"%s\"", extDirClean))
			extDirFound = true
		} else if strings.HasPrefix(trimmed, "curl.cainfo") && caPathClean != "" {
			newLines = append(newLines, fmt.Sprintf("curl.cainfo = \"%s\"", caPathClean))
		} else if strings.HasPrefix(trimmed, "openssl.cafile") && caPathClean != "" {
			newLines = append(newLines, fmt.Sprintf("openssl.cafile = \"%s\"", caPathClean))
		} else {
			newLines = append(newLines, line)
		}
	}
	if !extDirFound {
		newLines = append(newLines, fmt.Sprintf("extension_dir = \"%s\"", extDirClean))
	}

	_ = os.WriteFile(iniPath, []byte(strings.Join(newLines, "\n")), 0644)
}

// FindAvailablePort tests if defaultPort is free or finds the next open port
func FindAvailablePort(defaultPort int) int {
	for p := defaultPort; p < defaultPort+30; p++ {
		ln, err := net.Listen("tcp", fmt.Sprintf("0.0.0.0:%d", p))
		if err == nil {
			ln.Close()
			return p
		}
	}
	return defaultPort
}

// NewServerManager initializes manager
func NewServerManager(baseDir string, defaultPort int, hwid string) *ServerManager {
	phpBin := FindPhpBinary(baseDir)
	docRoot := FindDocRoot(baseDir)
	ConfigurePhpIni(phpBin, baseDir)

	return &ServerManager{
		PhpBinary: phpBin,
		DocRoot:   docRoot,
		BaseDir:   baseDir,
		Port:      FindAvailablePort(defaultPort),
		LocalIP:   GetLocalLANIP(),
		Hwid:      hwid,
	}
}

// Start launches php.exe without any console window and verifies socket is ready
func (sm *ServerManager) Start() error {
	if sm.IsRunning && sm.Process != nil {
		return nil
	}

	if sm.PhpBinary == "" {
		return fmt.Errorf("PHP executable tidak ditemukan di folder bin\\php-win atau sistem")
	}

	// Double check port availability
	sm.Port = FindAvailablePort(sm.Port)

	phpDir := filepath.Dir(sm.PhpBinary)
	iniPath := filepath.Join(phpDir, "php.ini")
	extDir := filepath.Join(phpDir, "ext")

	// Ensure php.ini is up to date
	ConfigurePhpIni(sm.PhpBinary, sm.BaseDir)

	args := []string{}
	if _, err := os.Stat(iniPath); err == nil {
		args = append(args, "-c", iniPath)
	}
	args = append(args,
		"-d", fmt.Sprintf("extension_dir=%s", extDir),
		"-S", fmt.Sprintf("0.0.0.0:%d", sm.Port),
		"-t", sm.DocRoot,
	)

	cmd := exec.Command(sm.PhpBinary, args...)
	cmd.Dir = sm.DocRoot

	// Redirect stdout & stderr to php_server.log in baseDir
	logPath := filepath.Join(sm.BaseDir, "php_server.log")
	if logFile, err := os.OpenFile(logPath, os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0666); err == nil {
		cmd.Stdout = logFile
		cmd.Stderr = logFile
	}

	// Set execution environment
	cmd.Env = append(os.Environ(),
		fmt.Sprintf("PHPRC=%s", phpDir),
		fmt.Sprintf("PATH=%s;%s", phpDir, os.Getenv("PATH")),
		fmt.Sprintf("NODERA_DESKTOP_HWID=%s", sm.Hwid),
	)

	// CREATE_NO_WINDOW = 0x08000000, CREATE_NEW_PROCESS_GROUP = 0x00000200
	cmd.SysProcAttr = &syscall.SysProcAttr{
		CreationFlags: 0x08000000 | 0x00000200,
	}

	if err := cmd.Start(); err != nil {
		return fmt.Errorf("gagal memulai engine PHP: %w", err)
	}

	sm.Process = cmd
	sm.IsRunning = true

	// Verify server socket is actually listening before returning
	var lastDialErr error
	for i := 0; i < 20; i++ {
		time.Sleep(100 * time.Millisecond)
		conn, err := net.DialTimeout("tcp", fmt.Sprintf("127.0.0.1:%d", sm.Port), 200*time.Millisecond)
		if err == nil {
			conn.Close()
			return nil
		}
		lastDialErr = err

		// Check if process crashed early
		if cmd.Process != nil {
			// On Windows, checking state
			if cmd.ProcessState != nil && cmd.ProcessState.Exited() {
				sm.IsRunning = false
				return fmt.Errorf("PHP server berhenti seketika (exit code %d). Cek log di php_server.log", cmd.ProcessState.ExitCode())
			}
		}
	}

	// If loop finished and still not connected
	return fmt.Errorf("server PHP belum merespon di port %d: %v", sm.Port, lastDialErr)
}

// Stop cleanly terminates the PHP process
func (sm *ServerManager) Stop() {
	if sm.Process != nil && sm.Process.Process != nil {
		_ = sm.Process.Process.Kill()
		sm.Process = nil
	}
	sm.IsRunning = false
}
