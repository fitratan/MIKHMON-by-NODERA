# MIKHMON Android Admin by NODERA

Aplikasi Android Standalone yang menjalankan **Engine Server PHP lokal** dan memuat antarmuka web **MIKHMON Desktop/Web resmi by NODERA** secara langsung di dalam aplikasi (WebView), dilengkapi proteksi lisensi perangkat (**Hardware-Locked**) dan cetak voucher langsung ke **Thermal Printer Bluetooth (ESC/POS)** maupun Android Print Manager.

---

## 🚀 Arsitektur Server Standalone (All-in-One APK)
1. **Embedded PHP Engine (ARM64)**:
   - Server PHP static standalone otomatis diekstrak dan dijalankan di latar belakang (`127.0.0.1:8080`) saat aplikasi dibuka.
   - Tidak memerlukan laptop, Termux, KSWEB, atau koneksi internet eksternal untuk menjalankan core server.
2. **Authentic Mikhmon Desktop Web Engine**:
   - Menjalankan seluruh berkas asli Mikhmon (`admin.php`, `index.php`, `buy.php`, `warung.php`, modul voucher, report, bandwidth limiter, dll).
   - Seluruh pengaturan router, template voucher, dan sesi tersimpan secara persisten di penyimpanan internal aplikasi.
3. **Full-Featured Android WebView**:
   - File picker untuk upload logo hotspot dan template voucher.
   - Integrasi cetak struk via Android Print Manager (`window.print()`) dan Intent printer (RawBT / QuickPrinter).
   - Bridge JavaScript (`window.nodera`) untuk sinkronisasi lisensi dan HWID.

---

## 🔒 Proteksi Lisensi (Anti-Piracy & Anti-Bocor)
Agar aplikasi tidak bisa disebar/dibajak sembarangan:
1. **Device Fingerprinting (HWID)**: Format unik `NDR-HWID-XXXX-XXXX-XXXX` yang terikat pada hardware perangkat (`ANDROID_ID`, `Build.BOARD`, `Build.MODEL`).
2. **Online Cloud Verification**: Terhubung otomatis ke API `https://panel.dgtlnetsolution.com/api/desktop-license`.
3. **Offline HMAC-SHA256 Signature**: Aktivasi offline menggunakan secret salt `NODERA_OFFLINE_SECRET_2026` sehingga validasi tetap jalan 100% tanpa internet di lokasi router.
4. **Trial 3 Hari**: Opsi uji coba gratis 3 hari per perangkat sebelum wajib membeli lisensi resmi.

---

## 🚀 Fitur Utama
1. **Direct RouterOS API Socket (Port 8728)**:
   - Komunikasi TCP langsung ke router tanpa perantara web server/PHP.
   - Kompatibel penuh dengan **MikroTik RouterOS v6** (MD5 challenge response) dan **RouterOS v7** (Direct plain login).
   - Telemetry real-time: CPU Load (%), Free RAM, Total RAM, Uptime, Versi ROS, dan Jumlah User Online.
2. **Generator Voucher Cepat**:
   - Pilihan profil user hotspot langsung dari MikroTik (`/ip/hotspot/user/profile`).
   - Mode `Username = Password` atau `Username & Password berbeda`.
   - Custom prefix, panjang karakter, limit waktu, dan harga voucher.
   - Batch voucher creation (`/ip/hotspot/user/add`).
3. **Cetak Struk Thermal Bluetooth (ESC/POS)**:
   - Koneksi Bluetooth SPP (`00001101-0000-1000-8000-00805F9B34FB`).
   - Kompatibel dengan semua printer thermal 58mm & 80mm (RPP02N, PT-210, MPT-II, POS-58, dll).
   - Format struk profesional dengan header hotspot, kode voucher besar & tebal, durasi, harga, dan URL login.
   - Fitur "Cetak Semua" atau cetak per voucher.
4. **Monitoring User Aktif & Kick**:
   - Melihat daftar pengguna yang sedang online beserta IP, MAC Address, Uptime, dan total data download/upload.
   - Tombol **Kick** satu klik untuk memutus paksa sesi user yang bandel.

---

## 📦 Lokasi File APK
File APK siap instal:
```bash
/home/rimuru/Projects/mikhmon-android-admin/release/MIKHMON-Admin-v3.2.0-Nodera.apk
```
Atau hasil build debug:
```bash
/home/rimuru/Projects/mikhmon-android-admin/app/build/outputs/apk/debug/app-debug.apk
```

---

## 🛠️ Cara Build Ulang
```bash
export JAVA_HOME=/home/rimuru/.local/jdk-17
export ANDROID_HOME=/home/rimuru/Android/Sdk
./gradlew assembleDebug
```
