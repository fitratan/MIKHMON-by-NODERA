package main

import (
	"bytes"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"syscall"
	"time"
	"unsafe"
)

const (
	OfflineSecret = "NODERA_OFFLINE_SECRET_2026"
	PrimaryApi    = "https://panel.dgtlnetsolution.com/api/desktop-license"
	FallbackApi   = "https://digitalnet.dgtlnetsolution.com/api/desktop-license"
	V1Api         = "https://panel.dgtlnetsolution.com/api/v1/desktop-license"
)

type LicenseData struct {
	LicenseKey   string                 `json:"license_key"`
	ProductName  string                 `json:"product_name"`
	Status       string                 `json:"status"`
	Hwid         string                 `json:"hwid"`
	Signature    string                 `json:"signature"`
	DeviceName   string                 `json:"device_name"`
	ActivatedAt  string                 `json:"activated_at"`
	ExpiresAt    string                 `json:"expires_at"`
	DaysLeft     int                    `json:"days_left"`
	IsTrial      bool                   `json:"is_trial"`
	Features     map[string]interface{} `json:"features,omitempty"`
	SupportUrl   string                 `json:"support_url,omitempty"`
}

type ApiResponse struct {
	Success bool        `json:"success"`
	Code    string      `json:"code"`
	Message string      `json:"message"`
	Data    LicenseData `json:"data"`
}

// GetWindowsMachineGuid reads MachineGuid from Registry
func GetWindowsMachineGuid() string {
	var hKey syscall.Handle
	subKey, _ := syscall.UTF16PtrFromString(`SOFTWARE\Microsoft\Cryptography`)
	
	// KEY_READ = 0x20019, KEY_WOW64_64KEY = 0x0100
	err := syscall.RegOpenKeyEx(syscall.HKEY_LOCAL_MACHINE, subKey, 0, 0x20019|0x0100, &hKey)
	if err != nil {
		err = syscall.RegOpenKeyEx(syscall.HKEY_LOCAL_MACHINE, subKey, 0, 0x20019, &hKey)
		if err != nil {
			return ""
		}
	}
	defer syscall.RegCloseKey(hKey)

	valName, _ := syscall.UTF16PtrFromString(`MachineGuid`)
	var buf [256]uint16
	var bufLen uint32 = uint32(len(buf) * 2)
	var valType uint32

	err = syscall.RegQueryValueEx(hKey, valName, nil, &valType, (*byte)(unsafe.Pointer(&buf[0])), &bufLen)
	if err != nil {
		return ""
	}

	return syscall.UTF16ToString(buf[:])
}

// GetDocConfigDirs returns all config directories (both docRoot/config and baseDir/config)
func GetDocConfigDirs(mikhmonDir string) []string {
	var dirs []string
	docRoot := FindDocRoot(mikhmonDir)
	docConfig := filepath.Join(docRoot, "config")
	dirs = append(dirs, docConfig)

	baseConfig := filepath.Join(mikhmonDir, "config")
	if filepath.Clean(baseConfig) != filepath.Clean(docConfig) {
		dirs = append(dirs, baseConfig)
	}
	for _, d := range dirs {
		_ = os.MkdirAll(d, 0755)
	}
	return dirs
}

// GetHardwareID generates or reads persistent NDR-HWID-XXXX-XXXX-XXXX
func GetHardwareID(mikhmonDir string) string {
	configDirs := GetDocConfigDirs(mikhmonDir)

	// 1. Check existing saved .hwid file in any config dir
	for _, d := range configDirs {
		hwidFile := filepath.Join(d, ".hwid")
		if data, err := os.ReadFile(hwidFile); err == nil {
			saved := strings.TrimSpace(string(data))
			if strings.HasPrefix(saved, "NDR-HWID-") && len(saved) >= 21 {
				// Sync to all other config dirs
				for _, otherDir := range configDirs {
					_ = os.WriteFile(filepath.Join(otherDir, ".hwid"), []byte(saved), 0644)
				}
				return saved
			}
		}
	}

	// 2. Query Windows hardware identifiers
	guid := GetWindowsMachineGuid()
	computerName := os.Getenv("COMPUTERNAME")
	username := os.Getenv("USERNAME")

	raw := fmt.Sprintf("WIN-%s-%s-%s", guid, computerName, username)
	h := sha256.New()
	h.Write([]byte(raw))
	hash := strings.ToUpper(hex.EncodeToString(h.Sum(nil)))

	hwid := fmt.Sprintf("NDR-HWID-%s-%s-%s", hash[0:4], hash[4:8], hash[8:12])

	// 3. Persist to all config/.hwid
	for _, d := range configDirs {
		_ = os.WriteFile(filepath.Join(d, ".hwid"), []byte(hwid), 0644)
	}
	return hwid
}

// CheckClockGuard detects if Windows system clock was rolled back
func CheckClockGuard(mikhmonDir string) bool {
	configDirs := GetDocConfigDirs(mikhmonDir)
	now := time.Now().Unix()

	for _, d := range configDirs {
		guardFile := filepath.Join(d, ".clock_guard")
		if data, err := os.ReadFile(guardFile); err == nil {
			if last, err := strconv.ParseInt(strings.TrimSpace(string(data)), 10, 64); err == nil {
				if last > 0 && now < (last-300) {
					return false // Tampering detected
				}
			}
		}
		_ = os.WriteFile(guardFile, []byte(strconv.FormatInt(now, 10)), 0644)
	}
	return true
}

// VerifyLicenseSignature checks offline cryptographic HMAC signature
func VerifyLicenseSignature(lic LicenseData) bool {
	if lic.Signature == "" || lic.LicenseKey == "" {
		return false
	}
	hwid := lic.Hwid
	exp := lic.ExpiresAt
	if exp == "" {
		exp = "LIFETIME"
	}

	raw := fmt.Sprintf("%s|%s|%s|%s", lic.LicenseKey, hwid, exp, OfflineSecret)
	h := sha256.New()
	h.Write([]byte(raw))
	expected := hex.EncodeToString(h.Sum(nil))

	return strings.EqualFold(lic.Signature, expected)
}

// ReadSavedLicense reads local nodera_license.json
func ReadSavedLicense(mikhmonDir string) (*LicenseData, error) {
	configDirs := GetDocConfigDirs(mikhmonDir)
	for _, d := range configDirs {
		licPath := filepath.Join(d, "nodera_license.json")
		data, err := os.ReadFile(licPath)
		if err == nil {
			var lic LicenseData
			if err := json.Unmarshal(data, &lic); err == nil && lic.LicenseKey != "" {
				return &lic, nil
			}
		}
	}
	return nil, fmt.Errorf("no saved license found")
}

// SaveLicenseData saves license to both nodera_license.json and PHP config/license.php
func SaveLicenseData(mikhmonDir string, lic LicenseData) error {
	configDirs := GetDocConfigDirs(mikhmonDir)

	expFormatted := "0000-00-00"
	if lic.ExpiresAt != "" && lic.ExpiresAt != "LIFETIME" {
		if t, err := time.Parse(time.RFC3339, lic.ExpiresAt); err == nil {
			expFormatted = t.Format("2006-01-02 15:04:05")
		} else {
			expFormatted = lic.ExpiresAt
		}
	}

	rawExp := lic.ExpiresAt
	if rawExp == "" {
		rawExp = "LIFETIME"
	}

	prodName := lic.ProductName
	if prodName == "" {
		prodName = "Mikhmon Desktop Standalone"
	}

	phpCode := fmt.Sprintf(`<?php
/**
 * LICENSE MIKHMON DESKTOP STANDALONE — NODERA
 * Teraktivasi resmi via Cloud Panel NODERA (panel.dgtlnetsolution.com).
 */
define('MIKHMON_MODE', 'DESKTOP');
define('MIKHMON_STATUS', 'ACTIVE');
define('MIKHMON_LICENSE_KEY', '%s');
define('MIKHMON_HWID', '%s');
define('MIKHMON_EXPIRY', '%s');
define('MIKHMON_EXPIRY_RAW', '%s');
define('MIKHMON_SIGNATURE', '%s');
define('MIKHMON_BRAND', 'by dgtlnetsolution.com');
define('MIKHMON_PRODUCT_NAME', '%s');
define('MIKHMON_ACTIVATED_AT', '%s');
define('MIKHMON_SUBDOMAIN', 'desktop');
`,
		lic.LicenseKey,
		lic.Hwid,
		expFormatted,
		rawExp,
		lic.Signature,
		prodName,
		time.Now().Format("2006-01-02 15:04:05"),
	)

	jsonData, _ := json.MarshalIndent(lic, "", "  ")

	// Persist to all config directories (mikhmon/config and baseDir/config)
	for _, configDir := range configDirs {
		_ = os.WriteFile(filepath.Join(configDir, "nodera_license.json"), jsonData, 0644)
		_ = os.WriteFile(filepath.Join(configDir, ".hwid"), []byte(lic.Hwid), 0644)
		_ = os.WriteFile(filepath.Join(configDir, ".last_verified"), []byte(strconv.FormatInt(time.Now().Unix(), 10)), 0644)
		_ = os.WriteFile(filepath.Join(configDir, "license.php"), []byte(phpCode), 0644)
		_ = os.Remove(filepath.Join(configDir, ".reset_alert"))
		_ = os.Remove(filepath.Join(configDir, ".trial_expired"))
	}

	return nil
}

// CallLicenseApi executes request with multi-endpoint fallback
func CallLicenseApi(endpoint string, payload map[string]interface{}) (*ApiResponse, error) {
	apis := []string{PrimaryApi, FallbackApi, V1Api}
	jsonData, _ := json.Marshal(payload)

	client := &http.Client{Timeout: 10 * time.Second}
	var lastErr error

	for _, base := range apis {
		url := base + endpoint
		req, err := http.NewRequest("POST", url, bytes.NewBuffer(jsonData))
		if err != nil {
			continue
		}
		req.Header.Set("Content-Type", "application/json")
		req.Header.Set("Accept", "application/json")
		req.Header.Set("User-Agent", "NODERA-Mikhmon-Server-Exe/3.2")

		resp, err := client.Do(req)
		if err != nil {
			lastErr = err
			continue
		}
		body, _ := io.ReadAll(resp.Body)
		resp.Body.Close()

		var apiResp ApiResponse
		if err := json.Unmarshal(body, &apiResp); err == nil {
			return &apiResp, nil
		}
	}

	if lastErr != nil {
		return nil, lastErr
	}
	return nil, fmt.Errorf("tidak dapat menghubungi server lisensi NODERA")
}

// ActivateTrial triggers automatic 7-day free trial on first run
func ActivateTrial(mikhmonDir string) (*LicenseData, error) {
	hwid := GetHardwareID(mikhmonDir)
	computerName := os.Getenv("COMPUTERNAME")
	if computerName == "" {
		computerName = "Windows PC"
	}

	payload := map[string]interface{}{
		"hwid":        hwid,
		"device_name": computerName,
		"os_info":     "Windows 64-bit",
	}

	resp, err := CallLicenseApi("/trial", payload)
	if err != nil {
		return nil, err
	}

	if !resp.Success {
		return nil, fmt.Errorf("%s", resp.Message)
	}

	lic := resp.Data
	_ = SaveLicenseData(mikhmonDir, lic)
	return &lic, nil
}

// ActivateKey activates manual license key
func ActivateKey(mikhmonDir, key string) (*LicenseData, error) {
	key = strings.TrimSpace(key)
	if key == "" {
		return nil, fmt.Errorf("license key wajib diisi")
	}

	hwid := GetHardwareID(mikhmonDir)
	computerName := os.Getenv("COMPUTERNAME")
	if computerName == "" {
		computerName = "Windows PC"
	}

	payload := map[string]interface{}{
		"license_key": key,
		"hwid":        hwid,
		"device_name": computerName,
		"os_info":     "Windows 64-bit",
	}

	resp, err := CallLicenseApi("/activate", payload)
	if err != nil {
		return nil, err
	}

	if !resp.Success {
		return nil, fmt.Errorf("%s", resp.Message)
	}

	lic := resp.Data
	_ = SaveLicenseData(mikhmonDir, lic)
	return &lic, nil
}

// VerifyOnline checks current license validity against cloud API
func VerifyOnline(mikhmonDir string, key string) (*LicenseData, error) {
	hwid := GetHardwareID(mikhmonDir)
	payload := map[string]interface{}{
		"license_key": key,
		"hwid":        hwid,
		"device_name": os.Getenv("COMPUTERNAME"),
	}

	resp, err := CallLicenseApi("/verify", payload)
	if err != nil {
		return nil, err
	}

	if !resp.Success {
		return nil, fmt.Errorf("%s", resp.Message)
	}

	lic := resp.Data
	_ = SaveLicenseData(mikhmonDir, lic)
	return &lic, nil
}
