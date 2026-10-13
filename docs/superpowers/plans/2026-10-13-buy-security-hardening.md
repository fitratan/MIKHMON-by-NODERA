# Security Hardening & Audit Protection Implementation Plan (buy.php & buy_process.php)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menutup seluruh celah keamanan (vulnerabilities) pada portal pembelian voucher hotspot online (`buy.php` dan `buy_process.php`), termasuk arbitrary file write, cross-tenant data leak, CSRF, rate-limit abuse, bot flooding, XSS, serta race conditions sehingga lolos audit keamanan secara penuh.

**Architecture:** Menerapkan arsitektur keamanan *defense-in-depth*:
1. Keamanan Transport & Strict SSL/TLS (Security Headers, HSTS, CURLOPT_SSL_VERIFYPEER = true, CA Bundle verification).
2. Sanitasi & Validasi Input Ketat (Strict Regex untuk `session`, `order_id`, `phone`).
3. Perlindungan CSRF & Same-Origin Verification.
4. Rate Limiting Terpadu berbasis IP & Sesi untuk mencegah DoS dan brute-force enumeration.
5. Anti-Bot Honeypot & Timing Guards.
6. Penghapusan celah file overwrite server (`include/lang.php`) dan isolasi direktori multi-tenant.
7. Atomic File Locking (`LOCK_EX`) pada berkas `orders_data.json`.
8. 2FA Privacy Verification saat pengecekan riwayat / pengambilan kredensial voucher.

**Tech Stack:** PHP 7.4 - 8.3, Vanilla JavaScript, Mikhmon Engine, Session & File-based Security Primitives.

**Spec:** Audit keamanan internal portal publik Mikhmon Standalone & Cloud Tenant.

## Global Constraints

- Kompatibel penuh dengan PHP 7.4 hingga PHP 8.3.
- Tanpa dependensi pihak ketiga baru (hanya menggunakan PHP native functions & session).
- Tidak merusak fungsionalitas UI portal pembelian di desktop maupun mobile.
- Semua file yang dimodifikasi wajib lolos verifikasi sintaks `php -l`.
- Perubahan wajib disinkronkan ke template terkait di `nodera-laravel` dan `nodera-africa`.

---

### Task 1: Strict SSL/TLS Verification, Security Headers, Safe Language Handling & Cross-Tenant Directory Isolation

**Files:**
- Modify: `mikhmon/buy.php:1-50`
- Modify: `mikhmon/buy_process.php:1-35`
- Modify: `mikhmon/buy_process.php:945-965`
- Modify: `mikhmon/buy_process.php:1515-1535`
- Modify: `buy_process.php` (root sync/clean)

**Interfaces:**
- Input: Request HTTP ke `buy.php` dan `buy_process.php`.
- Output: Response dengan header keamanan modern & HSTS; validasi sertifikat SSL wajib aktif (`VERIFYPEER = true`, `VERIFYHOST = 2`); penghapusan modifikasi berkas `include/lang.php`; isolasi order data hanya pada direktori lokal tenant.

- [x] **Step 1: Terapkan Strict SSL Verification pada Seluruh Panggilan cURL**
  - Pastikan TIDAK ADA `curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false)` atau `CURLOPT_SSL_VERIFYHOST, 0` di seluruh kode.
  - Setiap inisialisasi cURL eksternal (NODERA Pay, WijayaPay, WhatsApp, Telegram) wajib memanggil `mikhmon_curl_set_ssl($ch)` yang mengaitkan `cacert.pem`.
  - Jika URL endpoint gateway menggunakan protokol `http://`, otomatis paksa (*upgrade*) menjadi `https://` untuk mencegah sniffing API key / signature di jaringan publik.

- [x] **Step 2: Tambahkan HTTP Security Headers di awal `buy.php` dan `buy_process.php`**
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: SAMEORIGIN`
  - `X-XSS-Protection: 1; mode=block`
  - `Referrer-Policy: strict-origin-when-cross-origin`
  - Jika HTTPS aktif: `Strict-Transport-Security: max-age=31536000; includeSubDomains`
  - Pada `buy_process.php`: `Cache-Control: no-store, no-cache, must-revalidate, max-age=0`

- [x] **Step 3: Hapus Arbitrary File Write di `buy.php:36`**
  - Hapus pemanggilan `@file_put_contents($includeDir . '/lang.php', ...)` yang dapat dimanipulasi oleh pengunjung anonim.
  - Simpan preferensi bahasa hanya ke dalam `$_SESSION['lang']` dan cookie `mikhmon_lang` dengan flags `httponly=false; samesite=Lax`.

- [x] **Step 4: Hapus Sibling Directory Scanning di `buy_process.php:1520`**
  - Hapus baris `$siblingOrders = glob($parentDir . '/*/include/orders_data.json');` yang berpotensi membocorkan data transaksi dan nomor telepon antar tenant pada shared/cloud hosting.
  - Batasi pencarian order secara eksklusif hanya pada `$ordersFile` lokal tenant saat ini.

- [x] **Step 5: Uji sintaks PHP**
  - Run: `php -l mikhmon/buy.php && php -l mikhmon/buy_process.php && php -l buy_process.php`
  - Expected: `No syntax errors detected`

- [x] **Step 5: Commit perubahan Task 1**
  - Run: `git commit -m "security: add security headers, isolate tenant orders, remove lang.php file overwrite"`

---

### Task 2: Input Validation, Method Enforcement & CSRF Protection

**Files:**
- Modify: `mikhmon/buy.php`
- Modify: `mikhmon/buy_process.php`

**Interfaces:**
- Consumes: `$_SESSION['_buy_csrf']`, `$_SERVER['REQUEST_METHOD']`, `$_POST`, `$_SERVER['HTTP_X_CSRF_TOKEN']`
- Produces: Blokir request ilegal jika method tidak sesuai atau token CSRF tidak valid.

- [x] **Step 1: Tambahkan pembuatan CSRF token di `buy.php`**
  - Buat token jika belum ada: `if (empty($_SESSION['_buy_csrf'])) { $_SESSION['_buy_csrf'] = bin2hex(random_bytes(32)); }`
  - Inject meta tag pada `<head>`: `<meta name="csrf-token" content="<?= $_SESSION['_buy_csrf'] ?>">`
  - Inject CSRF token ke form submit & JavaScript AJAX header `X-CSRF-Token`.

- [x] **Step 2: Terapkan HTTP Method Enforcement pada `buy_process.php`**
  - Aksi state-changing (`create_qris`, `create_manual_order`, `cancel_order`, `notify_order_expired`) WAJIB menggunakan metode `POST`.
  - Jika aksi tersebut dipanggil via `GET`, kembalikan HTTP 405 Method Not Allowed:
    `echo json_encode(['success' => false, 'message' => 'Metode HTTP tidak diizinkan. Gunakan POST.']); exit;`

- [x] **Step 3: Validasi CSRF Token pada Aksi State-Changing di `buy_process.php`**
  - Periksa token dari header `HTTP_X_CSRF_TOKEN` atau payload `csrf_token`.
  - Gunakan `hash_equals($_SESSION['_buy_csrf'] ?? '', $token)` untuk mencegah timing attack.
  - Jika tidak cocok, kembalikan HTTP 403 Forbidden.

- [x] **Step 4: Sanitasi Parameter Input Utama**
  - Parameter `session`: validasi ketat dengan `preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $session)`.
  - Parameter `order_id`: validasi dengan regex `/^[A-Za-z0-9_\-]{8,40}$/`. Tolak jika mengandung karakter mencurigakan.
  - Parameter `phone`: validasi hanya angka setelah formatting internasional (8–16 digit).

- [x] **Step 5: Uji sintaks PHP**
  - Run: `php -l mikhmon/buy.php && php -l mikhmon/buy_process.php`
  - Expected: `No syntax errors detected`

- [x] **Step 6: Commit perubahan Task 2**
  - Run: `git commit -m "security: enforce POST method, implement CSRF token validation and sanitize input parameters"`

---

### Task 3: IP Rate Limiting & Anti-Bot Protection (Honeypot & Timing Guard)

**Files:**
- Create: `mikhmon/include/rate_limiter.php`
- Modify: `mikhmon/buy.php`
- Modify: `mikhmon/buy_process.php`

**Interfaces:**
- Consumes: IP Pengunjung (`$_SERVER['REMOTE_ADDR']`), Sesi Pengunjung.
- Produces: `mikhmon_check_rate_limit($action, $maxRequests, $windowSeconds): bool`

- [x] **Step 1: Buat modul `mikhmon/include/rate_limiter.php`**
  - Implementasikan algoritma sliding window berbasis atomic temporary file di direktori `config/.rate_limits/` atau session:
    - Limit `create_order` / `create_qris`: Maksimal 5 request per 60 detik per IP.
    - Limit `check_status` / `check_quota`: Maksimal 40 request per 60 detik per IP.
    - Limit `check_phone_vouchers`: Maksimal 10 request per 60 detik per IP.
  - Jika melebihi kuota, return HTTP 429 Too Many Requests:
    `echo json_encode(['success' => false, 'message' => 'Terlalu banyak permintaan. Silakan tunggu beberapa saat.']); exit;`

- [x] **Step 2: Tambahkan Honeypot Field pada Form Pembelian di `buy.php`**
  - Tambahkan input tersembunyi yang hanya diisi oleh spam bot otomatis:
    `<input type="text" name="_hp_website_url" value="" style="display:none !important;" tabindex="-1" autocomplete="off">`
  - Tambahkan timestamp pembuatan form `_hp_form_time`.

- [x] **Step 3: Validasi Honeypot & Timing Guard di `buy_process.php`**
  - Jika `!empty($postData['_hp_website_url'])`: Bot terdeteksi, tolak pesanan seketika tanpa memproses API / router.
  - Jika waktu submit `< 1.2 detik` dari `_hp_form_time`: Anggap submit otomatis oleh bot/script, tolak transaksi.

- [x] **Step 4: Uji sintaks PHP**
  - Run: `php -l mikhmon/include/rate_limiter.php && php -l mikhmon/buy_process.php`
  - Expected: `No syntax errors detected`

- [x] **Step 5: Commit perubahan Task 3**
  - Run: `git commit -m "security: implement IP rate limiter, honeypot bot trap, and submission timing guard"`

---

### Task 4: Voucher Privacy Protection & Atomic File Locking

**Files:**
- Modify: `mikhmon/buy_process.php:1665-1730`
- Modify: `mikhmon/include/order_helper.php:80-105`

**Interfaces:**
- Consumes: `order_id`, `phone` dari user input.
- Produces: Plaintext password voucher hanya ditampilkan jika pemohon dapat memverifikasi nomor telepon atau token sesi.

- [x] **Step 1: Terapkan 2-Factor Privacy Verification di `check_phone_vouchers`**
  - Saat pengguna mencari order via Order ID:
    - Wajibkan verifikasi 4 digit terakhir nomor telepon yang digunakan saat membeli, ATAU
    - Cek apakah order tersebut dibuat dalam sesi browser yang sama (`$_SESSION['last_created_orders']`).
  - Hal ini mencegah pihak ketiga yang menebak Order ID untuk mencuri username & password voucher aktif milik pelanggan lain.

- [x] **Step 2: Terapkan Atomic File Locking (`LOCK_EX`) di `order_helper.php`**
  - Di fungsi `mikhmon_save_order()` dan `mikhmon_get_orders()`:
    - Ganti `@file_put_contents($file, ...)` menjadi `file_put_contents($file, $json, LOCK_EX)`.
    - Ini mencegah *race conditions* dan *JSON file truncation* saat puluhan pesanan diproses secara bersamaan.

- [x] **Step 3: Uji sintaks PHP**
  - Run: `php -l mikhmon/include/order_helper.php && php -l mikhmon/buy_process.php`
  - Expected: `No syntax errors detected`

- [x] **Step 4: Commit perubahan Task 4**
  - Run: `git commit -m "security: add 2FA privacy check for voucher retrieval and atomic file locking for order storage"`

---

### Task 5: Sinkronisasi ke Repositori & Verifikasi Menyeluruh

**Files:**
- Sync to: `nodera-laravel/resources/mikhmon-template-v7/`
- Sync to: `nodera-laravel/resources/mikhmon-template/`
- Sync to: `nodera-africa/resources/mikhmon-template-v7/`
- Sync to: `nodera-africa/resources/mikhmon-template/`

- [x] **Step 1: Salin file yang telah diamankan ke template Laravel & Africa**
  - Salin `buy.php`, `buy_process.php`, `include/rate_limiter.php`, dan `include/order_helper.php`.

- [x] **Step 2: Jalankan validasi sintaks PHP menyeluruh di semua repositori**
  - Run: `php -l` pada seluruh berkas yang disinkronkan.

- [x] **Step 3: Commit dan Push ke GitHub**
  - Push branch `main` dan `master` di `mikhmon-desktop-nodera`, `nodera-laravel`, dan `nodera-africa`.
  - Verifikasi bahwa auto-deploy webhook dan notifikasi Telegram terpicu.
