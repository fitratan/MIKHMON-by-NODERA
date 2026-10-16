# Mikhmon Thermal Accurate Hardware Connection & Topnav Status Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memperbaiki sistem deteksi koneksi printer thermal Bluetooth agar 100% akurat terhadap kondisi hardware fisik (tidak menampilkan "Terhubung/Hijau" saat printer mati), menyediakan 4 status visual badge topnav yang transparan, dan memberikan notifikasi yang ramah jika printer belum dinyalakan saat ingin mencetak voucher/struk.

**Architecture:** Sinkronisasi status stateful antara layer hardware fisik (Bluetooth RFCOMM Socket di Android & Web Bluetooth GATT di Web) dengan UI Mikhmon (Topnav Badge, Halaman Pengaturan Thermal, Cetak Voucher, Kasir Warung). Menghapus seluruh asumsi optimis palsu (optimistic UI) yang menganggap printer tersimpan = terhubung.

**Tech Stack:** JavaScript (ES6+), PHP 7.4-8.3, Kotlin (Android SDK 34 / Jetpack Compose), CSS3, ESC/POS.

---

## 1. Analisis Masalah & Sistem Saat Ini (Root Cause)

### Kenapa sebelumnya printer mati tetapi di topnav tertulis "Terhubung" / Berwarna Hijau?
1. **Optimistic UI False Positive di `mikhmon-thermal.js`**:
   - Di fungsi `updateGlobalPrinterBadge`, terdapat kode:
     ```javascript
     else if (name) {
       badge.style.background = '#059669'; // Hijau!
       textEl.textContent = name;
     }
     ```
     Jika nama printer pernah tersimpan di `localStorage`, badge topnav langsung diwarnai **HIJAU** meskipun status koneksi sebenarnya adalah `disconnected` atau `idle`.
2. **AutoConnect Menganggap Sukses Sebelum Handshake Hardware**:
   - Di `autoConnect()` pada Android bridge:
     ```javascript
     if (savedName) {
       self.deviceName = savedName;
       self.isConnected = true; // <-- ASUMSI SALAH!
       window.nodera.connectPrinter(savedAddr); // Hasil return diabaikan!
       self.notifyListeners('connected', ...);
     }
     ```
     Script langsung mengklaim `self.isConnected = true` dan membunyikan event `connected`, padahal printer fisiknya sedang mati dan socket di Android gagal tersambung.
3. **Hardcoded Initializer di `settings/thermalprinter.php`**:
   - Saat halaman pengaturan dimuat, script `$(document).ready` langsung memanggil `updateUiStatus('connected', ...)` hanya karena ada nama printer tersimpan di browser.

---

## 2. Rencana Arsitektur & Perilaku Sistem Baru

### 4 Status Resmi Indikator Printer (Badge Topnav & Halaman Pengaturan)

| Status | Warna Badge | Ikon | Label Teks | Arti & Kondisi |
|---|---|---|---|---|
| **ONLINE / READY** | Hijau (`#16a34a`) | `fa-check-circle` | `🟢 [Nama Printer]` | Socket Bluetooth aktif, hardware hidup, siap cetak 0ms |
| **OFFLINE / MATI** | Slate/Abu-abu (`#64748b`) | `fa-power-off` / `fa-ban` | `⚪ [Nama Printer] (Mati/Offline)` | Printer terdaftar tapi fisik mati / di luar jangkauan |
| **CONNECTING** | Amber/Kuning (`#d97706`) | `fa-circle-o-notch fa-spin` | `🟡 Menghubungkan...` | Sedang melakukan handshake / ping ke printer (1-2 detik) |
| **UNCONFIGURED** | Dark Slate (`#475569`) | `fa-print` | `🖨️ Printer Thermal` | Belum ada printer yang dipilih / dipasangkan sama sekali |

### Mekanisme Handshake Hardware Akurat
1. **Android Bridge (`window.nodera`)**:
   - `window.nodera.isPrinterConnected()` wajib menjadi acuan kebenaran utama (Single Source of Truth) untuk status socket fisik.
   - Panggilan `window.nodera.connectPrinter(addr)` harus bersifat asinkron/terverifikasi: jika mengembalikan `false` (karena printer mati), status di JavaScript diset menjadi `disconnected` / `offline`.
2. **Web Bluetooth (PC / Browser)**:
   - Menggunakan `device.gatt.connected`. Jika printer mati, `gatt.connect()` akan melempar exception, sehingga status diset ke `offline`.
3. **Graceful Action Saat Mencetak (Voucher & Warung)**:
   - Jika tombol cetak diklik saat printer offline, sistem tidak freeze atau diam, melainkan memunculkan peringatan instruktif:
     *"Printer [Nama] sedang tidak aktif/mati. Silakan nyalakan printer Bluetooth Anda terlebih dahulu."*

---

## 3. Rencana Tahapan Eksekusi (Tasks)

### Task 1: Perbaikan Driver JavaScript Core (`mikhmon/js/mikhmon-thermal.js`)
- [ ] Hapus seluruh logika optimistic status (tidak boleh mewarnai hijau jika hanya membaca nama dari storage).
- [ ] Ubah `autoConnect()` agar memeriksa status koneksi aktual ke Android / Web Bluetooth sebelum memicu event `connected`.
- [ ] Tambahkan status state baru: `'offline'` untuk membedakan antara printer yang sudah terdaftar tapi sedang mati vs printer yang belum pernah diatur (`'unconfigured'`).
- [ ] Perbaiki fungsi `updateGlobalPrinterBadge` untuk menampilkan 4 warna status (Hijau, Kuning, Abu-abu Offline, Dark Netral) secara akurat.
- [ ] Tambahkan helper `checkActualConnection()` yang bisa dipanggil berkala atau saat window/tab kembali aktif (`window.onfocus`).

### Task 2: Perbaikan Bridge Kotlin Android (`ThermalPrinterManager.kt` & `MikhmonWebViewScreen.kt`)
- [ ] Pastikan `connectPrinter(address)` menangani timeout socket dengan rapi tanpa memblokir thread UI.
- [ ] Tambahkan broadcast / callback dari Android ke WebView saat koneksi printer terputus secara mendadak (misalnya printer dimatikan di tengah sesi).

### Task 3: Sinkronisasi Halaman Pengaturan & Cetak
- [ ] Perbaiki [`settings/thermalprinter.php`](file:///home/rimuru/Projects/mikhmon-desktop-nodera/mikhmon/settings/thermalprinter.php): hapus override status `connected` palsu pada `$(document).ready`. Tampilkan status `Offline (Mati)` dengan tombol "Sambungkan" jika printer terdaftar tapi sedang mati.
- [ ] Perbaiki [`voucher/print.php`](file:///home/rimuru/Projects/mikhmon-desktop-nodera/mikhmon/voucher/print.php) dan [`warung.php`](file:///home/rimuru/Projects/mikhmon-desktop-nodera/mikhmon/warung.php) agar jika printer dalam status offline, tombol cetak memberikan peringatan *"Nyalakan printer Anda"* alih-alih mencoba mengirim data ke socket mati.

### Task 4: Sinkronisasi Seluruh Repository & Build
- [ ] Sinkronkan perubahan ke `mikhmon-desktop-nodera`.
- [ ] Sinkronkan template ke 3 repository Laravel SaaS:
  - `nodera-laravel` (jalankan `php artisan mikhmon:sync`)
  - `nodera-africa` (jalankan `php artisan mikhmon:sync`)
  - `nodera-tailadmin`
- [ ] Rebuild Android APK v3.3.7 bila terdapat update di layer native Kotlin dan uji verifikasi.
- [ ] Commit dan push semua perubahan ke git remote.
