# 🚨 ATURAN WAJIB AGENT: DISAMBIGUASI REPOSITORI NODERA ECOSYSTEM

Dokumen aturan ini dibuat agar setiap Agent AI (Antigravity / Gemini / Claude) **TIDAK KELIRU** membedakan antara repositori **BILLING** dan **MIKHMON STANDALONE**.

---

## 🗺️ Peta Repositori Utama & Peruntukannya

| Repositori & Path Direktori | Git Remote | Tipe & Peruntukan | JANGAN PERNAH SALAH |
| :--- | :--- | :--- | :--- |
| **`mikhmon-desktop-nodera`**<br>`/home/rimuru/Projects/mikhmon-desktop-nodera` | `git@github.com:fitratan/MIKHMON-by-NODERA.git` | **STANDALONE MIKHMON** (Desktop Electron & Portable PHP 8.2 Micro-Server) | Jika user menyebut **"mikhmon standalone"**, **"standalone mikhmon"**, atau **"mikhmon desktop"**, INI REPO-NYA! |
| **`Billing-RT-RW-NET-by-NODERA`**<br>`/home/rimuru/Projects/Billing-RT-RW-NET-by-NODERA` | `git@github.com:fitratan/Billing-RT-RW-NET-by-NODERA.git` | **BILLING RT/RW NET STANDALONE** (Laravel Standalone untuk tagihan ISP/rumahan) | **INI HANYA UNTUK BILLING LANGGANAN ISP/RT-RW NET!** BUKAN repositori standalone Mikhmon! |
| **`nodera-laravel`**<br>`/home/rimuru/Projects/nodera-laravel` | `git@github.com:fitratan/nodera-billing.git` | **SAAS CLOUD BILLING INDONESIA** (`panel.dgtlnetsolution.com`) | Cloud multi-tenant. Mikhmon di sini adalah template di `resources/mikhmon-template*` yang disinkronkan ke `public/hotspot-*`. |
| **`nodera-africa`**<br>`/home/rimuru/Projects/nodera-africa` | `git@github.com:fitratan/nodera-africa.git` | **SAAS CLOUD BILLING AFRIKA** (`panel.airnetsolution.com`) | Cloud multi-tenant khusus regional Afrika (Wave, Orange, MTN). |

---

## ⛔ PERATURAN MUTLAK AGENT (STRICT RULES)

1. **JANGAN PERNAH MENCAMPURADUKKAN BILLING DENGAN MIKHMON:**
   - Mikhmon Standalone berada di `/home/rimuru/Projects/mikhmon-desktop-nodera` (remote: `MIKHMON-by-NODERA.git`).
   - Billing Standalone berada di `/home/rimuru/Projects/Billing-RT-RW-NET-by-NODERA` (remote: `Billing-RT-RW-NET-by-NODERA.git`).
   - Ketika user mengatakan *"kalau sudah ketahuan penyakitnya yang standalone yang dibenerin"* terkait voucher atau Mikhmon, repo yang dimaksud adalah **`mikhmon-desktop-nodera`**, **BUKAN** `Billing-RT-RW-NET-by-NODERA`!

2. **LOKASI KODE MIKHMON PADA STANDALONE:**
   - Pada `mikhmon-desktop-nodera`, seluruh logic aplikasi web PHP berada di direktori `mikhmon/` (misal: `mikhmon/hotspot/sync_schedulers.php`, `mikhmon/hotspot/userprofile.php`, `mikhmon/include/`, dll).

3. **LOKASI KODE MIKHMON PADA CLOUD (nodera-laravel & nodera-africa):**
   - Template master berada di:
     - `resources/mikhmon-template/` (ROS 6)
     - `resources/mikhmon-template-v7/` (ROS 7)
   - Setiap perubahan template master HARUS diikuti perintah:
     `php artisan mikhmon:sync`
     untuk mendistribusikan perubahan ke seluruh direktori `public/hotspot-*`.

4. **GUARD TAMPILAN MIKHMON (MENCEGAH "BACKGROUND DOANG"):**
   - Arsitektur Mikhmon membungkus halaman dalam `<div class="main-container" style="display:none">`.
   - Script jQuery di footer baru membuka container tersebut (`fadeIn`).
   - Jika script PHP backend terkena timeout (`max_execution_time`) atau throw fatal error sebelum sampai footer, browser HANYA AKAN MENAMPILKAN BACKGROUND KOSONG.
   - **SOLUSI WAJIB**: Setiap action/halaman proses harus menyertakan unhide script instan:
     ```html
     <script>
         (function() {
             var mc = document.querySelector('.main-container');
             if (mc) mc.style.display = 'block';
             var ld = document.getElementById('loading');
             if (ld) ld.style.display = 'none';
             if (typeof $ !== 'undefined') {
                 $('.main-container').show();
                 $('#loading').hide();
             }
         })();
     </script>
     ```
   - Operasi MikroTik API massal (seperti hapus voucher expired) WAJIB menggunakan batch mode (`implode(',', $chunk)`) dan `try...catch (\Throwable $e)`.
