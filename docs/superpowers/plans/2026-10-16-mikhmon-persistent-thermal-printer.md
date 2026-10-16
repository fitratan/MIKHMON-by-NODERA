# Mikhmon Persistent Thermal Bluetooth Printer & Zero-Prompt Printing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menghilangkan masalah printer thermal Bluetooth yang terputus (lampu indikator LED mati) saat berpindah menu di Mikhmon, dan mengeliminasi pop-up dialog "pilih printer" yang berulang setiap kali mencetak voucher (Zero-Prompt 1-Click Print).

**Architecture:** Menerapkan arsitektur Persistent Bluetooth Connection dua lapis: (1) Long-lived RFCOMM Socket pada native Android APK (`ThermalPrinterManager.kt`) yang mempertahankan koneksi fisik aktif selama aplikasi terbuka sehingga lampu LED printer tetap menyala solid antar navigasi menu; (2) Global Thermal Engine di web Mikhmon (`include/headhtml.php`) dengan auto-reconnect transparan, status badge global di navbar, dan penghapusan fallback modal chooser pada `ensureConnected()`.

**Tech Stack:** Kotlin (Android Bluetooth RFCOMM SPP, Jetpack Compose, WebKit JavascriptInterface), Vanilla JavaScript (Web Bluetooth API, LocalStorage State, ESC/POS generator), PHP 7.4-8.3 (Mikhmon Core, HTML layout templates).

**Spec:** Direct User Request & Hardware Behavior Specification for Bluetooth Thermal Printers (Panda, VSC, Iware, Eppos, RPP02N, POS-5802, PT-210, MPT-II).

## Global Constraints

- Kompatibilitas 100% dengan Android Native APK (offline & online mode), Desktop Electron (Windows/Linux), dan Cloud Web Bluetooth (Chrome/Edge/Opera).
- Printer Bluetooth yang sudah disimpan di SharedPreferences / LocalStorage TIDAK BOLEH memunculkan dialog pemilihan perangkat saat tombol cetak ditekan.
- Tidak boleh memutus socket RFCOMM Bluetooth secara sengaja (`socket.close()`) setelah selesai mencetak satu/banyak voucher di Android.
- Harus menjaga status visual printer di navbar/topbar Mikhmon agar pengguna selalu tahu printer dalam kondisi Ready/Standby.
- Seluruh kamus 5 bahasa (`id`, `en`, `fr`, `es`, `tl`) tetap dipertahankan 100% konsisten tanpa breaking changes.

---

### Task 1: Persistent Bluetooth Connection di Android Native (`mikhmon-android-admin`)

**Files:**
- Modify: `/home/rimuru/Projects/mikhmon-android-admin/app/src/main/java/com/nodera/mikhmon/admin/bluetooth/ThermalPrinterManager.kt`
- Modify: `/home/rimuru/Projects/mikhmon-android-admin/app/src/main/java/com/nodera/mikhmon/admin/ui/MikhmonWebViewScreen.kt`

**Interfaces:**
- Consumes: BluetoothAdapter bonded devices, SharedPreferences (`mikhmon_printer_prefs`).
- Produces: `getOrConnectSocket(address: String?): BluetoothSocket`, `ensureConnected(): Boolean`, `isPrinterConnected(): Boolean`, persistent output stream without premature socket teardown.

- [ ] **Step 1: Modifikasi ThermalPrinterManager untuk Mengelola Persistent BluetoothSocket**
Tambahkan properti private `activeSocket: BluetoothSocket?` dan `activeOutputStream: OutputStream?` serta thread-safe lock. Implementasikan `getOrConnectSocket(targetAddress: String?)` yang memeriksa `activeSocket?.isConnected == true`. Jika sudah terhubung ke device yang sama, gunakan kembali socket yang ada tanpa reconnect (0ms). Jika belum, buka socket baru dan simpan referensinya.

- [ ] **Step 2: Hapus Pemutusan Socket di `printRawEscPosSync()` dan `printVouchers()`**
Hapus `socket?.close()` dan `outputStream?.close()` dari blok `finally` di `printRawEscPosSync()` dan `printVouchers()`. Pastikan hanya `outputStream.flush()` yang dipanggil setelah data ESC/POS dikirim. Dengan begitu, RFCOMM channel tetap OPEN dan lampu indikator LED pada printer thermal tetap menyala solid!

- [ ] **Step 3: Tambahkan Auto-Recovery dan Reconnect Transparan**
Tambahkan penanganan `IOException` saat `outputStream.write()`: jika socket terputus karena printer sempat dimatikan/out of range, tangkap error, bersihkan socket lama, coba sambungkan ulang 1x secara otomatis, lalu kirim ulang data.

- [ ] **Step 4: Tambahkan Method Bridge di MikhmonWebBridge**
Tambahkan `@JavascriptInterface fun isPrinterConnected(): Boolean` dan `@JavascriptInterface fun ensurePrinterConnected(): Boolean` di `MikhmonWebBridge` agar script JavaScript di webview dapat mengetahui status koneksi fisik secara real-time.

- [ ] **Step 5: Verifikasi Build Android**
Jalankan kompilasi `./gradlew assembleDebug` di `mikhmon-android-admin` untuk memastikan tidak ada kesalahan kompilasi atau thread safety issue.

---

### Task 2: Global Engine & Navigasi Antar Halaman di Web Mikhmon (`mikhmon-desktop-nodera`)

**Files:**
- Modify: `/home/rimuru/Projects/mikhmon-desktop-nodera/mikhmon/include/headhtml.php`
- Modify: `/home/rimuru/Projects/mikhmon-desktop-nodera/mikhmon/include/menu.php`
- Modify: `/home/rimuru/Projects/mikhmon-desktop-nodera/mikhmon/js/mikhmon-thermal.js`

**Interfaces:**
- Consumes: `localStorage.getItem('mikhmon_thermal_device_name')`, `localStorage.getItem('mikhmon_thermal_device_addr')`, `window.nodera`.
- Produces: `window.MikhmonThermal` siap di semua rute menu Mikhmon, status bar visual printer di topbar.

- [ ] **Step 1: Muat `mikhmon-thermal.js` Secara Global di `include/headhtml.php`**
Tambahkan script `mikhmon-thermal.js` di bagian `<head>` pada `include/headhtml.php` sehingga mesin thermal selalu aktif dan berjalan di SEMUA halaman (Dashboard, Users, Generate, Settings, Quick Print, Log, dll.), bukan hanya di halaman pengaturan atau cetak.

- [ ] **Step 2: Tambahkan Indikator Printer Thermal di Topbar/Navbar Mikhmon**
Di `include/headhtml.php` atau `include/menu.php`, tambahkan badge status printer mini di samping status router:
```html
<span id="globalPrinterBadge" class="badge" style="font-size: 11px; padding: 4px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;">
  <i class="fa fa-print"></i> <span id="globalPrinterText">Printer Ready</span>
</span>
```
Badge ini otomatis berwarna hijau jika printer tersimpan/terhubung, atau abu-abu jika belum ada printer.

- [ ] **Step 3: Inisialisasi Otomatis Non-Blocking pada Global Head**
Tambahkan listener global di `headhtml.php` yang membaca `localStorage` segera saat DOM dimuat. Jika ada printer tersimpan, langsung set status UI "Ready" dan jalankan background connect/ping tanpa menampilkan pop-up atau blocking overlay.

---

### Task 3: Zero-Prompt 1-Click Print Engine (`mikhmon-thermal.js` & `voucher/print.php`)

**Files:**
- Modify: `/home/rimuru/Projects/mikhmon-desktop-nodera/mikhmon/js/mikhmon-thermal.js`
- Modify: `/home/rimuru/Projects/mikhmon-desktop-nodera/mikhmon/voucher/print.php`
- Modify: `/home/rimuru/Projects/mikhmon-desktop-nodera/mikhmon/settings/thermalprinter.php`

**Interfaces:**
- Consumes: Saved printer identifiers (`device_id` / `device_addr` / `device_name`).
- Produces: Direct print execution without ever opening the device picker dialog unless explicitly requested by clicking "Ganti Printer".

- [ ] **Step 1: Refactor `ensureConnected()` di `mikhmon-thermal.js`**
Ubah alur `ensureConnected()`:
- Jika di Android (`isNoderaAndroid()`): langsung gunakan printer yang tersimpan di Android/LocalStorage. Jangan pernah panggil `prompt()` atau dialog chooser jika address/name sudah ada.
- Jika di Web Bluetooth: coba sambungkan ke device GATT yang sudah ada di memori atau via `getDevices()`. Jika perangkat butuh reconnect, lakukan di latar belakang.
- **KUNCI:** Jika gagal reconnect silent, JANGAN langsung memanggil `this.connect('printers')` yang membuka modal pemilih perangkat! Berikan notifikasi *"Menghubungkan ke [Nama Printer]..."* dan coba lagi pada klik user, bukan memunculkan pop-up daftar printer baru.

- [ ] **Step 2: Hilangkan Dialog Pemilihan Berulang pada Tombol Cetak di `voucher/print.php`**
Pastikan tombol "Cetak Semua (Bluetooth)" dan tombol "Cetak Struk" pada masing-masing voucher langsung memanggil perintah cetak tanpa meminta user memilih nomor/nama printer.

- [ ] **Step 3: Perbaiki Tampilan Awal Halaman `settings/thermalprinter.php`**
Di `settings/thermalprinter.php`, render status printer secara langsung dari saved state di `localStorage` sejak HTML selesai diparsing. Hilangkan kondisi "⚪ Belum Terhubung" sesaat yang membuat user mengira printer terputus saat baru membuka halaman pengaturan.

---

### Task 4: Sinkronisasi ke Seluruh Repositori & Release Build

**Files:**
- Repositori Desktop: `/home/rimuru/Projects/mikhmon-desktop-nodera`
- Repositori Android: `/home/rimuru/Projects/mikhmon-android-admin`
- Repositori SaaS: `/home/rimuru/Projects/nodera-laravel`, `/home/rimuru/Projects/nodera-africa`, `/home/rimuru/Projects/nodera-tailadmin`

- [ ] **Step 1: Salin Perubahan Web Mikhmon ke Seluruh Target SaaS**
Salin file `mikhmon-thermal.js`, `include/headhtml.php`, `include/menu.php`, `voucher/print.php`, dan `settings/thermalprinter.php` ke folder mikhmon di `nodera-laravel`, `nodera-africa`, dan `nodera-tailadmin`.

- [ ] **Step 2: Jalankan `mikhmon:sync` di Seluruh Repositori Laravel**
Eksekusi perintah Artisan `mikhmon:sync` untuk memperbarui seluruh deployment tenant hotspot yang aktif di ketiga repositori.

- [ ] **Step 3: Build Release APK v3.3.6 & Kirim ke Telegram**
Build `release/MIKHMON-Nodera-v3.3.6-Offline.apk` dengan Persistent Bluetooth Socket, lalu kirim ke Telegram user via script `send-telegram` dan notifikasi Hermes agent.

- [ ] **Step 4: Commit & Push ke Git Remote Semua Repositori**
Lakukan commit dan git push pada seluruh repository yang terlibat.
