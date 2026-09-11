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

## 43. Fitur Tambahan Setelah MVP

*(Direncanakan untuk rilis berikutnya setelah MVP stabil)*
- Edit text file & CHMOD / Permission editor
- Change owner/group
- Upload via URL / Remote download
- Buat file ZIP baru di server
- Ekstraksi TAR / GZIP
- Duplicate file
- Bulk operations (Select all, Bulk delete, Bulk move, Bulk download)
- Storage usage widget & Recent files / Favorites
- Dark mode & Keyboard shortcuts
- File comparison
- Image / Video / Audio preview
