# 🛡️ Kebijakan Keamanan & Transparansi Kode (Security Policy)

## 1. Jaminan Bebas Backdoor & Anti-Pencurian Data (No Backdoor Guarantee)

Kami menyadari bahwa di dunia web hosting dan komunitas pengembang PHP, banyak skrip file manager pihak ketiga yang disusupi *web shell*, *backdoor*, atau skrip pencuri data (*stealer*). 

**Hosting File Manager** dibangun dengan integritas, transparansi, dan standar keamanan tinggi:

| Pertanyaan / Kekhawatiran | Fakta pada Hosting File Manager |
|---|---|
| **Apakah ada Backdoor / Web Shell?** | ❌ **TIDAK ADA.** Tidak ada fungsi berbahaya seperti `eval()`, `base64_decode()`, `shell_exec()`, `system()`, `passthru()`, `popen()`, atau `proc_open()`. |
| **Apakah script ini mencuri data cPanel?** | ❌ **TIDAK PERNAH.** Script ini tidak pernah meminta, mengakses, membaca, ataupun menyimpan password/kredensial cPanel maupun WHM Anda. |
| **Apakah ada Telemetri / Phone-Home?** | ❌ **TIDAK ADA.** Tidak ada panggilan HTTP keluar (*outgoing network request*) ke server pihak ketiga mana pun. Seluruh operasi bersifat lokal di server Anda. |
| **Apakah kodenya terenkripsi (Obfuscated)?** | ❌ **TIDAK.** 100% berkas adalah PHP murni yang mudah dibaca (*clean, human-readable code*). |
| **Apakah aman dipasang di hosting klien?** | ✅ **SANGAT AMAN.** Dilindungi pembatasan `ALLOWED_ROOT`, enkripsi password Bcrypt, Token CSRF, dan Rate Limiter login. |

---

## 2. Cara Mengaudit Kode Secara Mandiri (Self-Audit Guide)

Kami mendorong setiap pengguna dan sysadmin untuk memeriksa keaslian dan keamanan kode kami sebelum memasangnya di server produksi:

### Audit Fungsi Eksekusi Perintah & Obfuscation:
Jalankan perintah berikut di terminal Linux/Git Bash/macOS:
```bash
# Memeriksa keberadaan fungsi eksekusi sistem berbahaya:
grep -rnE "(eval|shell_exec|exec|system|passthru|popen|proc_open|base64_decode)\s*\(" app/ index.php
```
👉 **Hasil:** `0 temuan` (Tidak ada satu pun fungsi eksekusi berbahaya di dalam aplikasi).

### Audit Panggilan Jaringan Keluar (Outgoing HTTP):
```bash
# Memeriksa keberadaan cURL atau fungsi stream HTTP remote:
grep -rnE "(curl_init|file_get_contents\s*\(\s*['\"]https?://)" app/ index.php
```
👉 **Hasil:** Tidak ada panggilan keluar ke server remote eksternal.

---

## 3. Fitur Keamanan Bawaan (Built-in Security Features)

1. **Enkripsi Kredensial Bcrypt:** Password administrator di-hash menggunakan algoritma Bcrypt standar industri (`PASSWORD_BCRYPT`) dan disimpan di folder terproteksi `storage/credentials.json`.
2. **Brute-Force Rate Limiting:** Sistem secara otomatis memblokir alamat IP yang gagal login lebih dari 5 kali berturut-turut untuk mencegah serangan tebak password (*dictionary/brute-force attacks*).
3. **Perlindungan Token CSRF:** Setiap operasi manipulasi berkas (hapus, buat, upload, rename, edit) dilindungi dengan validasi token CSRF unik per sesi.
4. **Boundary Filesystem Kanonikal (`ALLOWED_ROOT`):** Seluruh path berkas disanitasi terhadap serangan *Path Traversal* (`../`, null-byte injection, Windows reserved names). Operasi dibatasi ketat hanya di dalam direktori yang Anda izinkan.
5. **Anti-Zip Slip:** Pemeriksaan integritas path saat mengekstrak arsip ZIP mencegah ekstraksi berkas berbahaya ke luar direktori target.
6. **Proteksi Direktori Data:** Folder `storage/` dan `app/` dilindungi oleh berkas `.htaccess` dan `index.php` pembatas untuk mencegah akses langsung via URL peramban (HTTP 403 Forbidden).

---

## 4. Pelaporan Kerentanan (Reporting a Vulnerability)

Jika Anda menemukan celah keamanan (*vulnerability*) atau memiliki saran perbaikan keamanan, mohon laporkan secara bertanggung jawab:
- Kirimkan laporan melalui fitur [GitHub Security Advisories](https://github.com/kazuhamoe/Hosting-File-Manager/security/advisories) atau melalui pesan langsung di GitHub.
- Mohon sertakan langkah-langkah reproduksi (*proof of concept*) yang jelas agar kami dapat segera merilis patch perbaikan.

---

<details>
<summary><b>English Version (Click to Expand)</b></summary>

### Security & Anti-Malware Policy
**Hosting File Manager** is committed to 100% transparency and clean code.
- **Zero Backdoors / Webshells:** No `eval()`, `base64_decode()`, `shell_exec()`, or hidden execution functions.
- **Zero Telemetry / Callbacks:** Absolutely no outgoing network calls to external servers. Your data stays entirely on your own server.
- **No cPanel Credential Theft:** The application operates strictly on standard PHP filesystem permissions within `ALLOWED_ROOT` and never touches your hosting/cPanel credentials.
- **100% Open Source & Auditable:** All code is written in readable native PHP with no obfuscation. Anyone can inspect every single line before deployment.

</details>
