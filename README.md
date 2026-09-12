# 📁 Hosting File Manager & ZIP Extractor

[![PHP Version](https://img.shields.io/badge/PHP-7.4%20--%208.3%2B-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Zero Dependencies](https://img.shields.io/badge/Dependencies-Zero%20(Native%20PHP)-orange.svg)]()
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](CONTRIBUTING.md)

**Hosting File Manager** adalah web-based file manager standalone berbasis **PHP Native** yang dirancang khusus untuk mengelola file dan direktori hosting (`public_html`, subdomain, dsb.) langsung dari peramban (browser) tanpa ketergantungan pada cPanel API, database MySQL, ataupun framework eksternal.

Dilengkapi dengan antarmuka modern layaknya cPanel File Manager / Desktop Explorer, kompresi & ekstraksi ZIP yang tangguh (bebas false-positive), in-browser Code Editor, sesi login persisten 30 hari, serta antarmuka pengaturan akun (*Admin Settings*).

---

## ✨ Fitur Utama (Key Features)

- **🖥️ Tampilan cPanel-like Modern:** Desain padat, intuitif, cepat, responsif, dengan ikon SVG bersih untuk semua jenis berkas.
- **🌓 Mode Gelap / Terang (Dark / Light Theme Toggle):** Beralih tema secara mulus hanya dengan 1-klik, tersimpan di `localStorage` dan mendeteksi preferensi sistem.
- **📊 Disk Usage / Quota Meter Widget:** Indikator visual real-time kapasitas disk server bergaya cPanel dengan kode warna dinamis (Hijau, Oranye, Merah) dan fallback aman.
- **⚡ Zero Dependencies:** 100% PHP Native murni, tidak butuh Composer, Node.js, atau database. Tinggal upload langsung jalan.
- **📦 ZIP Extractor & Compressor Tangguh:**
  - Ekstrak berkas arsip `.zip` dengan opsi penanganan konflik (*Overwrite*, *Skip*, *Rename*).
  - Proteksi **Anti-Zip Slip** ketat tanpa memblokir berkas website sah (`index.php`, `.htaccess`, `config.php`, `app/`, dsb.).
  - Kompres multi-berkas atau folder langsung menjadi file `.zip`.
- **📑 1-Click Duplicate / Backup File:** Gandakan berkas atau folder secara instan di direktori yang sama (`_copy`, `_copy2`, dst.) dari toolbar atau klik kanan.
- **🚀 Upload Berkas Besar (> 100 MB):** Dukungan upload banyak file sekaligus (*multiple files*) dilengkapi real-time progress bar dan penanganan payload besar yang andal.
- **🌈 In-Browser Code Editor & Syntax Highlighting:**
  - Micro Syntax Highlighter bawaan (tanpa CDN/eksternal library) untuk PHP, JavaScript/JSON, HTML, CSS, SQL, dan Shell/Bash.
  - Pemilihan bahasa otomatis (*Auto-Detection*) dan selector manual.
  - Modal editor kode dengan nomor baris otomatis, **Layar Penuh (Fullscreen)**, dan **Toggle Word Wrap**.
  - Shortcut keyboard (`Ctrl+S` untuk simpan, `Tab` untuk indentasi 4 spasi, `Esc` untuk keluar).
- **🔒 Keamanan Kelas Hosting:**
  - Perlindungan terhadap Path Traversal (`../`, null bytes, Windows reserved devices).
  - Pembatasan filesystem kanonikal berbasis `ALLOWED_ROOT`.
  - Rate Limiter proteksi brute-force login & perlindungan CSRF Token.
  - Log audit aktivitas tersimpan aman di `storage/logs/audit.log`.
- **⚙️ Antarmuka Pengaturan Akun (Admin Settings):**
  - Ganti username dan password langsung dari UI web tanpa perlu mengedit kode konfigurasi.
- **🕒 Sesi Login Persisten (Stay Logged In 30 Hari):**
  - Menggunakan konfigurasi masa aktif sesi khusus agar tidak terputus saat berpindah tab atau menutup browser.
- **🌐 Deteksi Cerdas Direktori cPanel:**
  - Jika dipasang di subdomain (misal `/home/user/subdomain/`), sistem otomatis mendeteksi folder induk `/home/user/` agar Anda dapat mengelola seluruh folder `public_html/` website utama Anda!

---

## 📋 Persyaratan Server (Server Requirements)

| Komponen | Persyaratan Minimum |
|---|---|
| **PHP** | PHP 7.4, 8.0, 8.1, 8.2, 8.3+ |
| **Ekstensi PHP** | `ext-zip`, `ext-session`, `ext-json` (standar bawaan hosting) |
| **Web Server** | Apache / LiteSpeed / Nginx / IIS |
| **Database** | **Tidak membutuhkan database (Zero Database)** |

---

## 🚀 Panduan Pemasangan (Quick Start)

### Cara 1: Menggunakan Git Clone
```bash
git clone https://github.com/kazuhamoe/Hosting-File-Manager.git
cd Hosting-File-Manager
```

### Cara 2: Pemasangan Manual di Hosting / cPanel
1. Unduh rilis terbaru dalam format ZIP dari tab [Releases](../../releases).
2. Unggah file ZIP ke direktori subdomain atau folder hosting Anda (misal `public_html/filemanager`).
3. Ekstrak file arsip tersebut.
4. Buka alamat website Anda di browser (contoh: `https://domainanda.com/filemanager`).

---

## 🔑 Autentikasi & Keamanan (Authentication)

File Manager ini menerapkan sistem **First-Time Setup Wizard ("Create Password")**:
- **Setup Akun Pertama Kali:** Saat aplikasi baru diunggah ke server hosting dan dibuka di peramban, Anda akan langsung diarahkan ke form *Setup Administrator* untuk membuat username dan password pilihan Anda sendiri.
- **Form Login Bersih:** Halaman login tidak menampilkan placeholder atau petunjuk kredensial akun bawaan.
- **Penyimpanan Terenkripsi:** Password di-hash menggunakan standar industri Bcrypt (`PASSWORD_BCRYPT`) dan disimpan aman di `storage/credentials.json`.
- **Anti Re-Setup:** Rute inisialisasi akun otomatis dikunci secara permanen setelah akun pertama kali dibuat.
- **Pengaturan Akun (Settings):** Username dan password dapat diubah sewaktu-waktu dengan aman melalui menu **⚙️ Pengaturan (Settings)** di pojok kanan atas setelah Anda login.

---

## ⚙️ Konfigurasi (`config.php`)

Seluruh pengaturan sistem dapat disesuaikan pada berkas `config.php`:

```php
// Batas direktori yang boleh diakses (Security Boundary)
// Mengizinkan pengelolaan hingga root public_html / home hosting
define('ALLOWED_ROOT', dirname(__DIR__)); 

// Masa aktif sesi (detik) - default 30 hari (Stay Logged In)
define('SESSION_TIMEOUT', 2592000);

// Batas maksimal upload file (dalam bytes, contoh 200 MB)
define('MAX_UPLOAD_SIZE', 200 * 1024 * 1024);

// Hash password fallback (dikosongkan secara default untuk mengaktifkan wizard pembuatan akun)
define('AUTH_PASS_HASH', '');
```

---

## 📂 Struktur Direktori

```
├── app/                  # Logika inti PHP (Auth, FileManager, Security, ZipManager, Logger)
│   └── index.php         # Proteksi direct web access (403 Forbidden)
├── assets/
│   ├── css/style.css     # Antarmuka cPanel profesional (Responsive)
│   └── js/app.js         # Frontend engine (Ajax, multi-upload, context menu, editor)
├── storage/              # Direktori data aman (terproteksi .htaccess & index.php)
│   ├── logs/             # Catatan audit aktivitas
│   └── temp/             # Temporary files & session data
├── index.php             # Single entry point aplikasi
├── config.php            # File konfigurasi utama
├── updater.php           # Script standalone 1-klik untuk update di hosting
├── LICENSE               # Lisensi MIT
└── README.md             # Dokumentasi proyek
```

---

## 🤝 Kontribusi (Contributing)

Kontribusi terbuka untuk siapa saja!
1. **Fork** repositori ini
2. Buat branch fitur baru (`git checkout -b fitur-keren`)
3. Commit perubahan Anda (`git commit -m 'Menambahkan fitur keren'`)
4. Push ke branch Anda (`git push origin fitur-keren`)
5. Buat **Pull Request**

---

## 📄 Lisensi (License)

Proyek ini dirilis di bawah lisensi open source [MIT License](LICENSE). Siapapun bebas menggunakan, memodifikasi, dan mendistribusikan proyek ini untuk kebutuhan pribadi maupun komersial.
