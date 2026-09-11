# Contributing to Hosting File Manager

Terima kasih telah tertarik untuk berkontribusi pada Hosting File Manager! Proyek ini bersifat open-source dan menyambut baik setiap kontribusi dari komunitas.

## Cara Berkontribusi

1. **Laporkan Bug / Ajukan Fitur:**
   - Gunakan [GitHub Issues](../../issues) untuk melaporkan kendala atau memberikan saran fitur baru.
   - Sertakan langkah-langkah reproduksi bug, versi PHP, dan lingkungan web server (Apache/LiteSpeed/Nginx).

2. **Kirimkan Pull Request (PR):**
   - Fork repositori ini ke akun GitHub Anda.
   - Buat branch baru dari branch `main`:
     ```bash
     git checkout -b fitur/nama-fitur
     ```
   - Lakukan perubahan dengan tetap memperhatikan:
     - Kompatibilitas dengan PHP 7.4 hingga PHP 8.3+.
     - Tidak menggunakan pustaka eksternal yang membebani (Zero Dependencies principle).
     - Menjaga standar keamanan ketat (Anti-Zip Slip, validasi path canonical, sanitasi input).
   - Pastikan seluruh unit test berjalan sukses:
     ```bash
     php tests/test_all_31_actions.php
     php tests/test_zip_extract_comprehensive.php
     php tests/test_settings_and_editor.php
     ```
   - Commit dan kirimkan Pull Request ke branch `main`.

## Pedoman Kode
- Gunakan PSR-12 style formatting sederhana.
- Hindari ketergantungan framework.
- Jangan menyimpan kredensial atau token nyata di repositori.
