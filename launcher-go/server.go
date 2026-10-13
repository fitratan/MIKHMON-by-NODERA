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
	Port      int
	Process   *exec.Cmd
	IsRunning bool
	LocalIP   string
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

// EnsureTlsCaConfigured ensures php.ini has curl.cainfo configured
func EnsureTlsCaConfigured(phpBin, baseDir string) {
	phpDir := filepath.Dir(phpBin)
	iniPath := filepath.Join(phpDir, "php.ini")

	caCandidates := []string{
		filepath.Join(phpDir, "cacert.pem"),
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

	if caPath == "" {
		return
	}

	if data, err := os.ReadFile(iniPath); err == nil {
		content := string(data)
		if !strings.Contains(content, "curl.cainfo") {
			addition := fmt.Sprintf("\r\ncurl.cainfo = \"%s\"\r\nopenssl.cafile = \"%s\"\r\n", caPath, caPath)
			_ = os.WriteFile(iniPath, []byte(content+addition), 0644)
		}
	}
}

// FindAvailablePort tests if defaultPort is free or finds the next open port
func FindAvailablePort(defaultPort int) int {
	for p := defaultPort; p < defaultPort+20; p++ {
		ln, err := net.Listen("tcp", fmt.Sprintf("127.0.0.1:%d", p))
		if err == nil {
			ln.Close()
			return p
		}
	}
	return defaultPort
}

// NewServerManager initializes manager
func NewServerManager(baseDir string, defaultPort int) *ServerManager {
	phpBin := FindPhpBinary(baseDir)
	docRoot := FindDocRoot(baseDir)
	EnsureTlsCaConfigured(phpBin, baseDir)

	return &ServerManager{
		PhpBinary: phpBin,
		DocRoot:   docRoot,
		Port:      FindAvailablePort(defaultPort),
		LocalIP:   GetLocalLANIP(),
	}
}

// Start launches php.exe without any console window
func (sm *ServerManager) Start() error {
	if sm.IsRunning && sm.Process != nil {
		return nil
	}

	if sm.PhpBinary == "" {
		return fmt.Errorf("PHP executable tidak ditemukan di folder bin\\php-win atau sistem")
	}

	// Double check port availability
	sm.Port = FindAvailablePort(sm.Port)

	cmd := exec.Command(sm.PhpBinary, "-S", fmt.Sprintf("0.0.0.0:%d", sm.Port), "-t", sm.DocRoot)
	// CREATE_NO_WINDOW = 0x08000000, CREATE_NEW_PROCESS_GROUP = 0x00000200
	cmd.SysProcAttr = &syscall.SysProcAttr{
		CreationFlags: 0x08000000 | 0x00000200,
	}

	if err := cmd.Start(); err != nil {
		return err
	}

	sm.Process = cmd
	sm.IsRunning = true

	// Wait 300ms to confirm server socket is listening
	time.Sleep(300 * time.Millisecond)
	return nil
}

// Stop cleanly terminates the PHP process
func (sm *ServerManager) Stop() {
	if sm.Process != nil && sm.Process.Process != nil {
		_ = sm.Process.Process.Kill()
		sm.Process = nil
	}
	sm.IsRunning = false
}
