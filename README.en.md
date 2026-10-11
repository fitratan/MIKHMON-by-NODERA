# MIKHMON by NODERA (Desktop & Cloud Standalone Edition)

<p align="center">
  <img src="assets/logo.png" alt="MIKHMON by NODERA" width="180"/>
</p>

<p align="center">
  <b>Language / Langue :</b>
  <a href="README.md"><b>🇮🇩 Bahasa Indonesia</b></a> |
  <a href="README.fr.md"><b>🇫🇷 Français (Côte d'Ivoire / Afrique)</b></a> |
  <a href="README.en.md"><b>🇬🇧 English</b></a>
</p>

<p align="center">
  <a href="https://panel.dgtlnetsolution.com/desktop-licenses"><img src="https://img.shields.io/badge/Platform-Windows%2010%20%7C%2011%20%7C%20Linux%20VPS-0052CC.svg?style=for-the-badge&logo=windows" alt="Platform"></a>
  <a href="https://panel.dgtlnetsolution.com"><img src="https://img.shields.io/badge/Engine-PHP%208.2%20Micro--Server-777BB4.svg?style=for-the-badge&logo=php" alt="Engine"></a>
  <a href="https://panel.dgtlnetsolution.com/desktop-licenses"><img src="https://img.shields.io/badge/Status-Official%20Release-00B074.svg?style=for-the-badge" alt="Status"></a>
  <a href="https://panel.dgtlnetsolution.com"><img src="https://img.shields.io/badge/Security-Cloud%20HWID%20Locked-E02424.svg?style=for-the-badge" alt="Security"></a>
</p>

---

## About MIKHMON by NODERA

**MIKHMON by NODERA** is the next-generation MikroTik hotspot management and billing application, modernly architected and optimized by **NODERA Digital Network**.

Unlike standard legacy Mikhmon (v3/v4) which only serves as a basic API interface for printing vouchers, **MIKHMON by NODERA** is a complete commercial ecosystem: Point of Sale (POS) reseller module, automated mobile payments, multi-provider WhatsApp Gateway, interactive Telegram notifications, and a portable self-contained runtime requiring no XAMPP or third-party web servers.

This software is internationally deployed across Southeast Asia and West Africa (including Côte d'Ivoire).

---

## Comparison: MIKHMON by NODERA vs Legacy Mikhmon (v3/v4)

| Feature / Parameter | Legacy Mikhmon (v3 / v4) | MIKHMON by NODERA (Desktop & VPS Standalone) |
| :--- | :--- | :--- |
| **Server & Runtime** | Requires manual web server / XAMPP setup | Built-in **Portable Micro-Server (PHP 8.2)**, 1-click execution without XAMPP |
| **Reseller POS Module** | None | **Retail POS & Reseller Module** with balance deposits and receipt printing |
| **Automated Payments** | None / Unstable third-party scripts | **Automated Payments & Online Portals** with instant voucher generation upon payment |
| **WhatsApp Gateway** | None | **Multi-Provider WhatsApp Gateway** sends credentials directly to buyers |
| **Telegram Notifications** | Basic plain text alerts | **Interactive Telegram Bot**: sale alerts, revenue recaps, action buttons & expiration alerts |
| **Expired Voucher Alerts** | None | **Real-time Expired Voucher Alert** delivered directly to Telegram technicians |
| **RouterOS Support** | Limited / Frequent errors on RouterOS v7 | **Full RouterOS v6 & v7 Support** with accelerated API connection handling |
| **Thermal Printing** | Standard browser formatting | **Direct USB & Bluetooth Thermal Printing (58mm/80mm)** with crisp layout |
| **License Protection** | Unmanaged / Insecure | **Cloud-Managed HWID License**: self-service HWID resets via client portal |
| **Internationalization** | Indonesian only | **Multilingual (French, Indonesian, English)** with localized West Africa support |

---

## Key Features

### 1. Reseller POS & Retail Agent Module
- Delegate voucher sales to neighborhood kiosks, grocery stores, and local agents.
- Clean, fast POS interface accessible from smartphones, tablets, or laptops.
- Pre-funded reseller balances with automatic profit margin calculation.
- Instant thermal receipt printing directly from the cashier device.

### 2. Multi-Provider WhatsApp Gateway
- Automatically delivers voucher code, username, password, and validity period directly to the customer's WhatsApp upon order completion.
- Broadcasts network maintenance announcements and expiration reminders.

### 3. Interactive Telegram Bot & Expiration Alerts
- **Real-time Sales Alerts**: Immediate notification upon each voucher sale.
- **Voucher Expiration Alerts**: Instant alert when a voucher expires on MikroTik.
- **Daily Recaps**: Automated revenue and hotspot performance summaries.

### 4. Standardized Thermal Printing
- Formatted voucher templates designed specifically for **58mm** and **80mm** thermal printers.
- Supports batch printing with auto-login QR Codes (*Scan to Connect*).
- Customizable branding: logos, watermarks, and footer notices.

### 5. Multi-Router Architecture (ROS v6 & ROS v7)
- Manage multiple remote MikroTik branch routers from a centralized desktop console.
- Supports standard and custom API ports with secured connection tunnels.
- Monitor live traffic bandwidth, interface charts, and DHCP leases in real time.

### 6. Hardware-Bound Licensing & Self-Service HWID
- Protected by cloud-verified Hardware ID (HWID) cryptographic validation.
- **Self-Service HWID Reset**: When transferring to a new computer or laptop, reset your hardware binding directly from the Member Portal without waiting for administrative approval.

---

## Installation & Quick Start

### A. Windows 10 / 11 Installation (Desktop Standalone)
1. **Download the Package**:
   - Download the official ZIP archive: **[Download Mikhmon-Desktop.zip](https://github.com/fitratan/MIKHMON-by-NODERA/archive/refs/heads/main.zip)**
   - Or clone via Git: `git clone https://github.com/fitratan/MIKHMON-by-NODERA.git`
2. **Extract Files**:
   - Extract the entire ZIP archive to your preferred directory (recommended: `C:\Mikhmon-Desktop`).
3. **Launch the App**:
   - Double-click **`Start-Mikhmon.bat`**.
   - The micro-server starts automatically and launches your browser at `http://127.0.0.1:8080`.
4. **First-Time Login**:
   - Default credentials: Username `nodera`, Password `nodera`.
5. **Automatic 7-Day Free Trial**:
   - New installations automatically include an unrestricted **7-Day Free Trial**. All modules are immediately unlocked.

---

### B. Linux VPS / Cloud Server Deployment (Ubuntu, Debian, AlmaLinux)

```bash
# 1. Navigate to server web root
cd /var/www/html

# 2. Download and extract source code
wget https://github.com/fitratan/MIKHMON-by-NODERA/archive/refs/heads/main.zip
unzip main.zip
mv MIKHMON-by-NODERA-main mikhmon
rm main.zip

# 3. Configure folder permissions
chown -R www-data:www-data mikhmon
chmod -R 755 mikhmon
```
Access via web browser: `http://YOUR-VPS-IP/mikhmon/login`

---

## Default Login Credentials

| Parameter | Default Value | Description |
| :--- | :--- | :--- |
| **Local URL** | `http://127.0.0.1:8080` | Built-in PHP 8.2 micro-server local port |
| **Username** | `nodera` | Primary administrator account |
| **Password** | `nodera` | Default factory password |

---

## Official Licensing & Support Contacts

Official **Mikhmon Desktop Standalone** licenses can be purchased and managed at:

- **Customer Portal**: [panel.dgtlnetsolution.com](https://panel.dgtlnetsolution.com)
- **WhatsApp Support (Côte d'Ivoire)**: **+255 07 88 22 26 99**
- **WhatsApp Support (Indonesia)**: **0851-5517-3547**
- **Telegram**: [@nullneko_exe](https://t.me/nullneko_exe)

---

## System Requirements

- **Operating System**: Windows 10 / Windows 11 (64-bit) or Linux Server
- **Processor**: Dual Core 1.5 GHz or higher
- **RAM**: 2 GB minimum (4 GB recommended)
- **Storage**: 500 MB available free disk space
- **Internet Connection**: Required during initial license activation and gateway synchronization

---

<p align="center">
  <b>NODERA</b><br>
  Digital Network Solution<br>
  <i>Copyright &copy; 2026 NODERA. All Rights Reserved.</i>
</p>
