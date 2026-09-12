# PRD — Hosting File Manager & ZIP Extractor

## 1. Tujuan

Membangun aplikasi web berbasis PHP native dengan satu entry point utama:
`index.php`

Fungsinya sebagai File Manager khusus hosting untuk memudahkan upload dan pengelolaan file tanpa harus login ke cPanel/File Manager hosting.

Sistem harus terasa seperti file manager hosting biasa, bukan dashboard SaaS atau tampilan yang terlihat dibuat AI.

### Prinsip Utama
- Simple
- Cepat
- Familiar seperti File Manager
- Tidak banyak halaman yang tidak diperlukan
- Fokus pada file
- Bisa upload ZIP
- Bisa extract ZIP langsung di hosting
- Bisa download file/folder
- Bisa membuat folder
- Bisa rename
- Bisa delete
- Bisa melihat informasi file
- Bisa berpindah directory
- Bisa menentukan lokasi upload/extract
- Tidak bergantung pada cPanel API
- Tidak menentukan struktur folder instalasi karena lokasi aplikasi ditentukan developer

---

## 2. Konsep Sistem

User membuka:
`https://domain.com/[lokasi-aplikasi]/index.php`

Kemudian:
```
Login
  ↓
File Manager
  ↓
Pilih folder
  ↓
Upload file / ZIP
  ↓
Extract / kelola file
```

Aplikasi bekerja langsung terhadap filesystem hosting yang telah ditentukan.

Contoh secara konsep:
```
Hosting
├── public_html/
├── domains/
├── backups/
├── storage/
└── folder lainnya
```

> **Catatan:** Aplikasi tidak boleh mengasumsikan struktur seperti di atas. Developer nantinya menentukan root/path yang boleh dikelola melalui konfigurasi.

---

## 3. Scope Utama

### A. Authentication
Sebelum File Manager dapat digunakan, user harus melewati login.

**Fitur:**
- Username
- Password
- Session authentication
- Logout
- Session timeout
- Proteksi terhadap akses langsung tanpa login

*Tidak perlu sistem register.*
*Tidak perlu multi-user pada versi awal.*

---

## 4. Dashboard = File Manager

Halaman utama bukan dashboard statistik. Setelah login, user langsung masuk ke: **File Manager**.

Tampilan harus menyerupai File Manager hosting konvensional.

**Contoh struktur UI:**
```
┌──────────────────────────────────────────────────────────────┐
│ File Manager                                      Logout     │
├──────────────────────────────────────────────────────────────┤
│ /public_html/                                                │
├──────────────────────────────────────────────────────────────┤
│ [+ Upload] [+ New Folder]                                    │
│                                                              │
│ Name              Type       Size       Modified       Action │
│ 📁 assets         Folder     -          ...            ...    │
│ 📁 images         Folder     -          ...            ...    │
│ 📄 index.php      PHP        12 KB      ...            ...    │
│ 📦 backup.zip     ZIP        25 MB      ...            ...    │
└──────────────────────────────────────────────────────────────┘
```

**Jangan menggunakan desain seperti:**
- Kartu statistik
- Gradient berlebihan
- Dashboard AI
- Grafik
- Icon terlalu ramai
- "Welcome back"
- Analytics
- Widget tidak berhubungan dengan file

Tujuannya harus benar-benar terasa seperti hosting File Manager.

---

## 5. Navigation / Breadcrumb

File Manager harus memiliki breadcrumb.

**Contoh:**
`Home / public_html / assets / images`

User dapat klik bagian breadcrumb untuk kembali ke folder sebelumnya.

**Harus mendukung:**
- Masuk folder
- Kembali
- Parent directory
- Root directory

---

## 6. File Listing

Setiap folder menampilkan isi directory dalam bentuk tabel.

**Kolom minimal:**

| Kolom | Fungsi |
| :--- | :--- |
| **Name** | Nama file/folder |
| **Type** | File/folder |
| **Size** | Ukuran |
| **Modified** | Waktu perubahan |
| **Permissions** | Permission jika tersedia |
| **Actions** | Operasi |

Folder harus dibedakan secara visual dari file.

**Contoh:**
- 📁 `images`
- 📁 `uploads`
- 📁 `backup`
- 📄 `index.php`
- 📄 `config.php`
- 📦 `website.zip`

---

## 7. Upload File

### Fitur Utama:
- **Upload single file**: User dapat memilih file dari komputer dan mengupload ke directory yang sedang dibuka.
- **Upload multiple files**: User dapat memilih beberapa file sekaligus.

**Contoh:**
```
Upload:
[ Choose Files ]

index.php
style.css
script.js
logo.png

[ Upload ]
```

---

## 8. Upload ZIP

ZIP harus diperlakukan sebagai file biasa saat upload.

**Contoh alur:**
```
Upload ZIP: website.zip → /public_html/
Setelah upload berhasil: 📦 website.zip
Kemudian user dapat memilih: Extract
```

---

## 9. Extract ZIP

Ini merupakan fitur inti.

User memilih `website.zip` kemudian klik **Extract**.

Sistem menampilkan pilihan lokasi:
```
Extract ZIP

File:
website.zip

Destination:
[ /public_html/              ]

[ Extract ]
```

User bisa extract ke directory yang sedang dibuka maupun lokasi lain yang diperbolehkan sistem.

**Contoh:**
- ZIP: `website.zip`
- Isi:
  ```
  website/
  ├── index.php
  ├── assets/
  └── config/
  ```
- Extract ke: `/public_html/`
- Hasil: `/public_html/website/`

---

## 10. Extract ZIP ke Folder Tertentu

Harus bisa:
`website.zip` → **Extract**

```
Destination:
/
├── public_html
├── backup
├── storage
└── website
```

User menentukan destination. Tidak boleh hanya extract otomatis ke lokasi ZIP.

---

## 11. ZIP Security

Karena aplikasi mempunyai akses filesystem hosting, fitur extract harus memiliki proteksi ketat.

**Wajib mencegah:**
1. **Path Traversal**:
   - ZIP berisi `../../config.php` tidak boleh menyebabkan file keluar dari destination yang dipilih.
   - Cegah `../../../` dan variasi path traversal lainnya.
2. **Symlink abuse**:
   - Jangan membiarkan ZIP membuat symbolic link yang dapat digunakan untuk keluar dari root/path yang diperbolehkan.
3. **Destination validation**:
   - Destination harus selalu divalidasi sebelum proses extract.

---

## 12. Download

User dapat mendownload file:
`index.php` → **Download** → Browser langsung mendownload file tersebut.

---

## 13. Download Folder

Untuk folder:
`/public_html/assets/` → **Download**

Sistem membuat ZIP sementara: `assets.zip` kemudian mengirimkannya ke user. Setelah selesai, file temporary harus dibersihkan.

---

## 14. Delete

Bisa delete:
- File
- Folder
- ZIP

Untuk keamanan harus ada konfirmasi:
> *Delete "backup.zip"?*  
> `[Cancel]` `[Delete]`

Untuk folder yang berisi file:
> *Folder ini berisi 128 file. Apakah yakin ingin menghapus?*

Tidak boleh langsung menghapus tanpa konfirmasi.

---

## 15. Rename

User dapat rename file/folder.

**Contoh:**
`website.zip` → **Rename**
```
New name:
[ website-v2.zip ]

[ Save ]
```
*Validasi nama file wajib dilakukan.*

---

## 16. New Folder

Tombol: `[+ New Folder]`

Modal:
```
Folder name:
[ uploads ]

[ Create ]
```
Setelah dibuat langsung muncul di listing.

---

## 17. File Information

User dapat memilih **Info** untuk menampilkan informasi file/folder.

**Contoh File Information:**
```
Name: index.php
Type: PHP File
Size: 24.8 KB
Location: /public_html/index.php
Modified: 11 September 2026 09:30
Permissions: 0644
Owner: ...
MIME: text/x-php
```

**Contoh Folder Information:**
```
Name: assets
Location: /public_html/assets
Items: 124
Size: 18.4 MB
Modified: ...
```

---

## 18. File Preview

Untuk file teks tertentu, sistem boleh menyediakan fitur **View**:
- `.txt`, `.css`, `.js`, `.html`, `.php`, `.json`, `.xml`, `.md`

> **PENTING:** PHP jangan dieksekusi untuk preview. Harus dibaca sebagai teks/plain text. Tidak boleh menjalankan kode PHP di dalam browser preview.

---

## 19. Search File

Pencarian sederhana:
`🔍 Search files...`

User mencari `index.php`, sistem menampilkan file yang cocok dalam directory yang sedang aktif.  
Default: Search current directory.

---

## 20. Sort

File listing dapat diurutkan berdasarkan:
- Name (↑ / ↓)
- Size (↑ / ↓)
- Modified (↑ / ↓)
- Type

---

## 21. Permission

Jika hosting mengizinkan, tampilkan permission:
- File: `-rw-r--r--` (`0644`)
- Folder: `0755`

Versi awal cukup menampilkan permission. Fitur chmod dapat dibuat sebagai fitur tambahan setelah versi utama stabil.

---

## 22. Drag & Drop Upload

Upload harus mendukung:
```
┌──────────────────────────────┐
│                              │
│  Drag & Drop files here      │
│                              │
│       atau                   │
│     [ Browse ]               │
│                              │
└──────────────────────────────┘
```
Tombol upload biasa tetap tersedia. Drag & drop bukan satu-satunya cara upload.

---

## 23. Progress Upload

Untuk file besar, tampilkan progress:
```
website.zip
Uploading...
████████████████░░░░ 82%
164 MB / 200 MB
```
Setelah selesai: `Upload complete`

---

## 24. Extract Progress

Jika ZIP besar:
```
Extracting...
██████████████░░░░░░ 72%
1,284 / 1,742 files
```
Jika memungkinkan, proses jangan membuat browser terlihat hang.

---

## 25. Conflict Handling

Saat extract terdapat file yang sudah ada:
```
File already exists:
index.php

What do you want to do?
○ Overwrite
○ Skip
○ Rename

☑ Apply this action to all conflicts
```

---

## 26. Context Menu

Menyediakan action menu (`⋮`):
- **Untuk ZIP:** Download, Rename, Extract, Info, Delete
- **Untuk Folder:** Open, Download, Rename, Move, Info, Delete
- **Untuk File Biasa:** View, Download, Rename, Copy, Move, Info, Delete

Action yang tidak relevan harus otomatis disembunyikan.

---

## 27. Copy & Move

- **Copy:** Copy → pilih destination
- **Move:** Move → pilih destination

User dapat memindahkan file/folder antar-directory yang diperbolehkan.

---

## 28. Path Selector

Untuk operasi **Extract**, **Copy**, dan **Move**, sediakan modal folder selector:
```
Select destination
📁 public_html
   📁 assets
   📁 images
   📁 uploads
📁 backup
📁 storage

[ Select this folder ]
```

---

## 29. Root Restriction

**Sangat Penting:** Aplikasi tidak boleh bebas mengakses seluruh filesystem server.
- Harus ada `BASE_PATH` atau mekanisme konfigurasi setara (`Allowed Root`).
- Semua operasi (`upload`, `download`, `extract`, `delete`, `rename`, `move`, `copy`, `preview`) harus divalidasi terhadap root tersebut.
- User tidak boleh menggunakan `../` untuk keluar dari root.

---

## 30. Proteksi File Sistem

Mencegah user menghapus file aplikasi File Manager itu sendiri.
Minimal proteksi terhadap:
- `index.php`
- File konfigurasi/security penting lainnya (`config.php`, dll.)

Daftar protected file ditentukan oleh developer.

---

## 31. UI Design

**Gaya yang diinginkan:** Hosting File Manager klasik/modern.
- **Bukan:** SaaS Dashboard, AI Dashboard, Admin Analytics.
- **Komponen UI:**
  - Sidebar optional
  - Toolbar sederhana
  - Breadcrumb
  - Table
  - Folder icon & File icon
  - Context menu
  - Modal confirmation
  - Toast notification
  - Responsive (Desktop-first, mobile usable)

---

## 32. Toolbar

**Contoh:**
`[ ← ] [ ↑ ]  |  [ + Upload ] [ + Folder ]  |  🔍 Search files...`

*Tidak perlu:* Total Files, Storage Used, Recent Activity, AI Assistant, Analytics, Performance widgets.

---

## 33. Notification

Pemberitahuan toast / alert:
- Sukses Upload: `✓ File uploaded successfully`
- Sukses Extract: `✓ ZIP extracted successfully`
- Sukses Delete: `✓ File deleted`
- Error: `✕ Unable to extract ZIP`

Pesan error harus jelas tanpa membocorkan path absolut atau informasi sensitif server.

---

## 34. Error Handling

Sistem harus menangani:
- Upload gagal / File terlalu besar / Disk penuh
- Permission denied
- ZIP corrupt / ZIP password protected
- Destination tidak valid / File tidak ditemukan / File sudah ada
- Directory tidak bisa dibaca / dihapus
- Session expired

---

## 35. Security Requirement

Security adalah fitur utama:
1. Session authentication & Session timeout
2. Password hashing (`password_hash()`, `password_verify()`)
3. Login rate limiting & Secure logout
4. CSRF protection & XSS protection
5. Path traversal protection & Secure path resolution
6. Upload validation & ZIP extraction validation
7. Permission validation
8. Jangan expose absolute server path ke user
9. Jangan menampilkan credential/configuration sensitif
10. Jangan execute uploaded file dari endpoint upload
11. Jangan mengeksekusi PHP/script saat preview

---

## 36. Upload Security

- Jangan hanya percaya extension (contoh: `gambar.jpg.php` harus diproses dengan aman).
- Sistem memungkinkan berbagai file hosting, tetapi endpoint upload tidak boleh mengeksekusi file.

---

## 37. ZIP Security (Pre-Extraction Checks)

Sebelum extract:
1. Baca daftar entry ZIP.
2. Validasi setiap path terhadap allowed root.
3. Tolak path traversal (`../`).
4. Tangani symlink dengan aman.
5. Periksa nama file abnormal.
6. Jangan overwrite protected files.
7. Extract hanya setelah seluruh validasi lolos.

---

## 38. Logging

Sediakan logging minimal untuk aktivitas penting:
```
[2026-09-11 10:20] UPLOAD website.zip -> /public_html/ [SUCCESS]
```
Aktivitas yang dicatat: `LOGIN`, `UPLOAD`, `EXTRACT`, `DELETE`, `MOVE`, `COPY`, `RENAME`.

---

## 39. Teknologi

- **Frontend:** HTML, CSS, JavaScript (Native, tanpa dependensi berat)
- **Backend:** PHP Native
- **Filesystem:** PHP Filesystem API
- **ZIP Engine:** PHP `ZipArchive`
- **Authentication:** PHP Session, `password_hash()`, `password_verify()`
- **Komunikasi:** AJAX untuk UX yang mulus

---

## 40. Struktur Aplikasi

- PRD sengaja tidak mengunci lokasi folder aplikasi. Developer bebas menentukan lokasi `index.php`.
- Seluruh aplikasi dapat bekerja dari `index.php` sebagai entry point dan UI utama.
- File pendukung (CSS, JS, PHP helper, config) boleh dipisah secara modular (tidak harus dipaksa 1 file).

---

## 41. Fitur MVP

1. Login & Logout
2. File Manager & Directory browsing
3. Breadcrumb navigation
4. Upload (Single & Multiple)
5. Upload ZIP & Extract ZIP
6. Destination selector
7. Download file & Download folder sebagai ZIP
8. New folder, Rename, Delete, Copy, Move
9. File info & Folder info
10. File preview (plain text)
11. Search (current directory) & Sort
12. Upload progress & Extract progress
13. Conflict handling
14. Security validation & Error handling
15. Responsive UI

---

## 42. Acceptance Criteria

1. **Upload:** Login → buka `/public_html` → upload `website.zip` → upload selesai → `website.zip` muncul di listing.
2. **Extract:** Pilih `website.zip` → Extract → pilih destination `/public_html` → Extract → folder/file hasil extract muncul.
3. **File Management:** Create folder → Rename → Upload → Move → Copy → Download → Delete berhasil tanpa cPanel.
4. **Security:** Percobaan path traversal (`../../config.php`) ditolak secara tegas.
5. **UX:** Familiar dan intuitif bagi pengguna cPanel File Manager tanpa butuh tutorial khusus.

---

## 43. Status Implementasi Fitur Tambahan

Semua fitur berikut telah berhasil diimplementasikan, diuji, dan tersedia di versi produksi:
- [x] Edit text file & CHMOD / Permission editor (Oktal & Matrix)
- [x] Buat file ZIP baru di server (Compress Archive)
- [x] Sesi Login 30 Hari & Pengaturan Kredensial Akun (Admin Settings UI)
- [x] 1-Click Duplicate / Backup Item
- [x] Bulk operations (Select all, Bulk delete, Bulk copy, Bulk move, Bulk download)
- [x] Storage / Disk usage meter widget (cPanel style)
- [x] Syntax Highlighting di Code Editor (Micro Tokenizer Engine)
- [x] Dark Mode / Light Mode Theme Toggle
- [x] Keyboard shortcuts (`Ctrl+S`, `Tab`, `Esc`)

---

## 44. Spesifikasi: Sesi Login 30 Hari & Pengaturan Akun Admin

1. **Sesi Persisten 30 Hari:**
   - Menggunakan `SESSION_TIMEOUT = 2592000` (30 hari) pada `config.php`.
   - Konfigurasi `session.gc_maxlifetime`, cookie params `lifetime`, `httponly`, dan `samesite: Lax`.
   - Mengatasi permasalahan session logout dini pada lingkungan shared hosting.

2. **Pengaturan Akun Admin (Admin Settings Modal):**
   - Modal UI dapat diakses melalui tombol **⚙️ Pengaturan** di pojok kanan atas.
   - Mengizinkan admin mengubah username login dan password baru.
   - Wajib memverifikasi password saat ini (*current password*) sebelum perubahan diterapkan.
   - Kredensial yang diperbarui disimpan secara aman di `storage/credentials.json` (terproteksi dari akses web langsung).

---

## 45. Spesifikasi: Disk Usage / Quota Meter Widget (cPanel Style)

1. **Tujuan:**
   - Menampilkan indikator visual kapasitas disk server secara langsung di top navbar layaknya widget cPanel / Web Hosting.

2. **Logika Backend (`FileManager::getDiskUsage`):**
   - Mengambil total kapasitas dan sisa ruang bebas melalui `disk_total_space(ALLOWED_ROOT)` dan `disk_free_space(ALLOWED_ROOT)`.
   - Menghitung persentase pemakaian (`percentage = (used / total) * 100`).
   - Format ukuran bytes manusiawi (`B`, `KB`, `MB`, `GB`, `TB`).
   - Fallback toleran jika fungsi disk dinonaktifkan di `php.ini` (`disable_functions`), mengembalikan `available: false` tanpa merusak antarmuka.

3. **Tampilan Frontend:**
   - Widget compact di sebelah badge username.
   - Progress bar dinamis dengan 3 tingkat warna status:
     - **Hijau (< 70%):** Kapasitas aman.
     - **Kuning / Oranye (70% - 90%):** Kapasitas mulai penuh (peringatan).
     - **Merah (> 90%):** Kapasitas kritis (segera bersihkan file).
   - Tooltip hover informatif menampilkan detail angka: Terpakai, Kapasitas Total, dan Ruang Bebas.

---

## 46. Spesifikasi: 1-Click Duplicate / Backup Berkas

1. **Tujuan:**
   - Memberikan cara termudah bagi developer / sysadmin untuk membuat salinan instan berkas atau direktori di folder yang sama sebelum melakukan modifikasi (contoh: `index_copy.php` atau `wp-config_copy.php`).

2. **Aturan Penamaan Duplikasi:**
   - Menggunakan format `[nama_asli]_copy.[ekstensi]`.
   - Jika sudah ada file `_copy`, sistem secara otomatis menggunakan suffix bertingkat: `[nama_asli]_copy2.[ekstensi]`, `_copy3`, dst.
   - Untuk folder: `[nama_folder]_copy/`, `_copy2/`, dst. secara rekursif menyalin seluruh isi folder.

3. **Integrasi Antarmuka:**
   - Tombol **Duplicate** di Action Toolbar (aktif otomatis saat item dipilih).
   - Menu klik kanan (Desktop Context Menu) **Duplikat (Duplicate)**.
   - Menu aksi dropdown per baris tabel.
   - Mendukung duplikasi banyak file sekaligus (*bulk duplication*).

4. **Keamanan:**
   - Melarang duplikasi berkas terproteksi sistem (`config.php`, `credentials.json`, audit log).
   - Melarang duplikasi direktori root.
   - Setiap aksi duplikasi dicatat ke log audit (`DUPLICATE`).

---

## 47. Spesifikasi: Syntax Highlighting di In-Browser Code Editor

1. **Tujuan:**
   - Mempermudah pengeditan script PHP, HTML, CSS, JavaScript, JSON, SQL, dan Shell script langsung dari browser tanpa silau atau bingung membaca kode panjang.

2. **Arsitektur Tanpa Dependensi (Zero Dependency Micro Highlighter):**
   - Menggunakan teknik overlay berkinerja tinggi: `<pre class="code-editor-pre"><code id="editorCode">` diposisikan di bawah `<textarea class="code-editor-textarea">`.
   - Textarea transparan dengan kursor caret putih menyala, sementara elemen `pre/code` merender token warna-warni secara real-time.
   - Sinkronisasi scroll horizontal dan vertikal 100% presisi (`scrollTop` & `scrollLeft`).
   - Tipografi terkalibrasi identik: font monospace (`Consolas, Monaco, "Courier New"`), line-height `1.5`, tab size `4`.

3. **Dukungan Bahasa Pemrograman:**
   - **PHP:** Tag pembuka/penutup, variabel `$` warna biru muda, keywords warna biru, string warna oranye bata, komentar warna hijau italic, function calls warna kuning muda.
   - **JavaScript / JSON:** Keywords, strings, numbers, comments, booleans, async/await.
   - **HTML / XML:** Tag elements, attribute names, attribute values, HTML comments.
   - **CSS:** Selectors, properties, hex colors, numeric units, media queries.
   - **SQL:** SQL reserved keywords (SELECT, INSERT, UPDATE, JOIN, dsb.), strings, numbers, comments.
   - **Shell / Bash:** Commands, variables, comments, arguments.

4. **Fitur Tambahan Editor:**
   - **Auto-Detection:** Mendeteksi bahasa pemrograman secara otomatis dari ekstensi file yang dibuka.
   - **Language Selector Dropdown:** Admin dapat mengubah manual bahasa highlight jika diinginkan.
   - **Toggle Highlight Button:** Tombol on/off untuk mematikan highlighting (misal untuk file data yang sangat besar).
   - **Safe Limiter:** Untuk berkas di atas 250 KB, highlighting beralih ke mode plain text untuk menjaga ketanggapan browser.

---

## 48. Spesifikasi: Dark Mode / Light Mode Theme System

1. **Tujuan:**
   - Menyediakan kenyamanan visual optimal bagi developer yang bekerja di lingkungan temaram (malam hari) maupun terang.

2. **Desain Sistem Tema:**
   - Mode Terang (Light Mode): Tampilan khas cPanel dengan background bersih `#f8fafc` dan tabel putih bergaris halus.
   - Mode Gelap (Dark Mode): Palet gelap modern terinspirasi oleh tema dark GitHub / VS Code (`#0b0f19` body, `#151d2f` cards/tables/modals, `#e2e8f0` typography, `#38bdf8` link accents).

3. **Interaksi & Persistensi:**
   - Tombol toggle tema di header navbar dengan animasi ikon Matahari / Bulan.
   - Pengaturan disimpan di `localStorage` peramban (`hfm_theme`) sehingga preferensi pengguna tetap terjaga saat me-refresh halaman atau membuka sesi berikutnya.
   - Deteksi otomatis preferensi sistem operasi pengguna (`prefers-color-scheme: dark`) saat pertama kali dibuka.
   - Transisi warna yang halus tanpa flicker (flash of unstyled content).

