# 📁 Hosting File Manager & ZIP Extractor

<p align="right">
  🌐 <b>Language:</b> <b>English</b> | <a href="README.id.md">Bahasa Indonesia</a>
</p>

[![PHP Version](https://img.shields.io/badge/PHP-7.4%20--%208.3%2B-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Zero Dependencies](https://img.shields.io/badge/Dependencies-Zero%20(Native%20PHP)-orange.svg)]()
[![Security: 100% Clean](https://img.shields.io/badge/Security-100%25%20Clean%20%26%20Auditable-brightgreen.svg)](SECURITY.md)
[![Zero Backdoor](https://img.shields.io/badge/Backdoor-Zero%20(No%20Telemetry)-blue.svg)](SECURITY.md)
[![Release](https://img.shields.io/badge/Release-v2.1.0-purple.svg)](../../releases/latest)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](CONTRIBUTING.md)

**Hosting File Manager** is a modern, lightweight, standalone web file manager built with **pure Native PHP**. It is specifically designed to manage web hosting files and directories (`public_html`, subdomains, VPS directories, etc.) directly from your browser without any dependency on cPanel API, MySQL database, Composer, or external frameworks.

Featuring an authentic cPanel-inspired interface, robust ZIP extraction & compression, an in-browser code editor, a **Recycle Bin**, a real-time **Activity Log Viewer**, persistent 30-day sessions, and an administrative settings UI.

<p align="center">
  <img src="screenshots/dashboard-dark.png" alt="Hosting File Manager Dark Mode" width="100%">
</p>

> [!IMPORTANT]
> ### 🛡️ Security & Transparency Guarantee: 100% Clean Code (No Backdoors / No Telemetry)
> Given the prevalence of malicious PHP scripts (*web shells / credential stealers*) on the web, we provide complete, verifiable transparency:
> - ❌ **NOT a Backdoor or Web Shell:** No hidden payloads, zero code obfuscation, and absolutely **no dangerous system execution functions** such as `eval()`, `base64_decode()`, `shell_exec()`, or `system()`.
> - ❌ **NO Data or cPanel Credential Theft:** This application performs **zero outgoing network calls (*no outgoing HTTP/cURL callbacks*)** and contains no telemetry. All your files and credentials remain 100% on your own server. It never requests, accesses, or touches your server's cPanel/WHM root credentials.
> - ✅ **100% Open Source & Auditable:** Written in clean, readable native PHP. Anyone can inspect and audit every line of code before deploying it to production.
> 
> **🔍 Quick Self-Audit Command (Verify for Yourself):**
> ```bash
> # Verify there are no dangerous execution functions across the codebase:
> grep -rnE "eval\(|shell_exec\(|system\(|passthru\(|base64_decode\(" app/ index.php
> # Result: 0 matches found (100% Clean & Safe)
> ```

---

## 🎯 What Is This For? (Use Cases & Problem Solved)

Many developers ask: *"My hosting already has a default cPanel File Manager, why should I use this?"*

Here are the **real-world problems and scenarios solved** by Hosting File Manager:

1. 👥 **Secure Client / Team File Access (Without Sharing Master cPanel Credentials)**  
   If you are a freelancer or agency, clients or junior team members often need to upload or tweak website files. Giving them master cPanel access is dangerous because they might accidentally delete MySQL databases, alter DNS records, or compromise email accounts. With this tool, you can deploy it to a dedicated subdomain (e.g., `files.clientdomain.com`) with isolated access restricted only to that website's directory.

2. ⚡ **Lightweight & Fast Alternative When cPanel Is Sluggish**  
   Default cPanel File Managers can feel heavy, slow to load, or frequently trigger annoying session timeouts while you are working. Hosting File Manager is built with **100% Native PHP without a database**, making it snappy, instant to load, and equipped with a 30-day persistent session.

3. 🖥️ **Web File Manager for VPS / Servers Without a Control Panel**  
   If you manage a VPS (Ubuntu, Debian, AlmaLinux) running a plain LEMP/LAMP stack (Nginx/Apache) without a paid control panel like cPanel or Plesk, managing files solely via terminal SSH and SFTP can be tedious. This tool gives you a full-featured, modern web GUI explorer out of the box.

4. 🔍 **Productivity Features Missing in Stock cPanel**  
   - **Find in Files:** Recursively search text strings or code snippets across dozens of files simultaneously with full *Regex* support, and jump directly to the matched line in the editor in 1 click.
   - **Recycle Bin (Trash):** Deleted files are not immediately lost forever; they go to Trash and can be restored back to their original paths with a single click.
   - **Activity Audit Log:** Transparent real-time record of who did what (uploads, renames, edits, extractions, deletions).
   - **Mobile Touch Friendly:** Responsive grid cards and haptic long-press context menus for easy mobile troubleshooting.

5. 🆘 **Emergency Recovery Access When cPanel Is Down or Ports Are Blocked**  
   When your cPanel dashboard is experiencing issues, license errors, or port 2083/2082 is blocked by corporate or campus firewalls, you can still manage your files over standard HTTP/HTTPS ports (80/443).

---

## ✨ Key Features

- **🖥️ Modern cPanel-Style Interface** — Responsive, clean dark/light themes, crisp SVG icons.
- **🌓 Dark / Light Theme Toggle** — 1-click switch, automatically saved in `localStorage`.
- **📊 Disk Usage / Quota Meter** — Real-time visual disk quota indicator (Green / Orange / Red).
- **⚡ Zero Dependencies** — 100% Native PHP. No Composer, Node.js, or database required.
- **📦 ZIP Extractor & Compressor** — Anti-Zip Slip security, conflict resolution (Overwrite / Skip / Rename).
- **🗑️ Recycle Bin / Trash** — Deletions are sent to Trash first. 1-click restore, permanent delete, empty trash. Real-time badge counter.
- **📜 Activity Log Viewer** — Detailed audit log: upload, rename, edit, trash, extract, etc. Filter by action & text with color-coded status badges.
- **🔍 Find in Files** — Fast recursive text/code search across files, Regex support, 1-click jump to editor.
- **🌈 In-Browser Code Editor** — Syntax highlighting for PHP, JS, HTML, CSS, SQL, Bash. Fullscreen, Word Wrap, Ctrl+S save.
- **⌨️ Desktop Keyboard Shortcuts** — F2 Rename, Del Delete, Ctrl+A Select All, Ctrl+F Search, Ctrl+Shift+F Find in Files.
- **📱 Responsive Mobile Experience** — Adaptive grid tiles, long-press context menus (haptic feedback), scrollable toolbars.
- **📑 1-Click Duplicate** — Instantly duplicate files or folders within the same directory.
- **🚀 Large File Uploads (> 100 MB)** — Multi-file uploads with real-time progress bars.
- **🔒 Server-Grade Security** — Path traversal protection, brute-force rate limiter, CSRF token validation, and audit logs.
- **⚙️ Web Admin Settings UI** — Modify username, password, allowed root directory, upload limit, and session timeout from the browser.
- **🌐 Smart cPanel Directory Detection** — Automatically detects parent directories when deployed on a subdomain.

---

## 📋 Server Requirements

| Component | Minimum Requirement |
|---|---|
| **PHP** | 7.4 / 8.0 / 8.1 / 8.2 / 8.3+ |
| **PHP Extensions** | `ext-zip`, `ext-session`, `ext-json` |
| **Web Server** | Apache / LiteSpeed / Nginx / IIS |
| **Database** | ❌ Not required (Zero Database) |

---

## 🚀 Quick Start Guide

> ⚠️ **IMPORTANT — Do NOT place this in an existing website directory!**
>
> Hosting File Manager uses `index.php` as its main entry point. If you extract it directly into `public_html/` where an existing `index.php` (such as WordPress, Laravel, etc.) already lives, your existing website file **will be overwritten and damaged**.
>
> ✅ **Always deploy into a dedicated subfolder or a separate subdomain** as illustrated below.

---

### 📁 Option A: Subfolder on Hosting *(Easiest)*

```
public_html/
├── index.php         ← Your main website (UNTOUCHED)
├── wp-content/       ← e.g. WordPress / Laravel
└── filemanager/      ← ✅ Extract Hosting File Manager HERE
    ├── index.php
    ├── config.php
    ├── app/
    ├── assets/
    └── storage/
```

**Steps:**
1. Download **`hosting-file-manager.zip`** from the [**Releases**](../../releases/latest) tab.
2. Upload it to your hosting via FTP or cPanel File Manager.
3. Extract it into `public_html/filemanager/`.
4. Open your browser: `https://yourdomain.com/filemanager/`.
5. Complete the **Setup Wizard** to create your administrator username and password.

> 💡 **Security tip:** Use an unguessable folder name, such as `/manage-X9K/` or `/cpanel-tools/`.

---

### 🌐 Option B: Dedicated Subdomain *(Recommended & Most Professional)*

```
yourdomain.com/           ← Your main website (UNTOUCHED)
manager.yourdomain.com/   ← ✅ Dedicated subdomain for File Manager
```

**Steps in cPanel:**
1. In cPanel, navigate to **Subdomains** → create a subdomain, e.g., `manager.yourdomain.com`.
2. Set the **Document Root** to: `/home/username/manager.yourdomain.com/`.
3. Upload and extract `hosting-file-manager.zip` into that document root folder.
4. Visit: `https://manager.yourdomain.com/`.
5. Complete the **Setup Wizard**.

**Subdomain Advantages:**
- ✅ Zero risk of file conflicts with other projects
- ✅ Independent SSL certificate
- ✅ Easy to disable or password-protect anytime
- ✅ Clean, memorable URL

---

### 🖥️ Option C: Localhost XAMPP / Laragon *(Development)*

```
C:\xampp\htdocs\
├── myproject\        ← Your project
└── filemanager\      ← ✅ Extract here
```

Access: `http://localhost/filemanager/`

---

### ⚡ Option D: Git Clone *(Developer)*

```bash
git clone https://github.com/kazuhamoe/Hosting-File-Manager.git filemanager
# Access: http://localhost/filemanager/
```

---

## 🔑 Authentication & Security Architecture

Hosting File Manager features an automated **First-Time Setup Wizard**:
- On initial launch, the user is presented with the *Administrator Setup* form to create their credentials.
- Passwords are encrypted with **Bcrypt** (`PASSWORD_BCRYPT`) and stored in `storage/credentials.json`.
- **Anti Re-Setup Lock:** Permanently locks setup mode once credentials are established.
- **Brute-Force Rate Limiter:** Protects against automated dictionary attacks.
- **CSRF Tokens:** Enforced across all mutating operations (delete, upload, edit, rename, move).

---

## ⚙️ Configuration (`config.php`)

```php
define('ALLOWED_ROOT', dirname(__DIR__));    // Boundary directory accessible by users
define('SESSION_TIMEOUT', 2592000);          // Session lifetime (seconds) — default 30 days
define('MAX_UPLOAD_SIZE', 200 * 1024 * 1024); // Maximum upload size (200 MB)
define('SHOW_DISK_USAGE', false);            // Display disk quota meter
define('AUTH_PASS_HASH', '');                // Empty = enable Setup Wizard
```

> 💡 Settings modified via the web UI are saved in `storage/settings.json` and persist across updates.

---

## 📂 Directory Structure

```
├── app/                  # Core PHP backend (Auth, FileManager, Security, ZipManager, Logger)
├── assets/
│   ├── css/style.css     # Responsive styles & themes
│   ├── js/app.js         # Frontend application engine
│   └── icons/            # SVG, ICO, and PNG favicons
├── storage/
│   ├── logs/audit.log    # Activity audit log
│   ├── trash/            # 🗑️ Recycle Bin storage
│   └── temp/             # Temporary files
├── index.php             # Application entry point
├── config.php            # Primary configuration
├── favicon.ico           # Browser fallback icon
└── updater.php           # 1-click update script
```

---

## 📦 Release History (Changelog)

### 🎉 v2.1.0 — September 19, 2026
- ✅ **PHP Server Limits Override:** Configure `upload_max_filesize`, `post_max_size`, and `memory_limit` directly from the UI via `.user.ini` and `.htaccess`.
- ✅ **Bypass Default Upload Caps:** Allows uploading files larger than default shared hosting limits (e.g. 10MB/2M up to 500MB, 1GB+, etc.).
- ✅ **Pre-populated Values:** Automatically reads and displays active PHP runtime values for seamless tuning.

### 🎉 v2.0.0 — September 12, 2026
- ✅ **Recycle Bin / Trash:** Soft delete, 1-click restore, badge counter, empty trash.
- ✅ **Activity Log Viewer:** Audit logs, text & action filters, color status badges, clear logs.
- ✅ **Enhanced Mobile UI/UX:** Grid cards, long-press haptic context menus, scrollable toolbars.
- ✅ **Find in Files:** Recursive text/code search across files, Regex support, jump to editor.
- ✅ **Keyboard Shortcuts:** F2, Del, Ctrl+A, Ctrl+F, Ctrl+Shift+F, Esc.
- ✅ **Config Settings Manager:** Edit runtime settings directly from the web UI.
- ✅ **Grid View Mode:** Toggleable card/tile view alongside standard table view.
- ✅ **Automatic 0777 Permissions:** Automatic permission setting on upload and extraction.
- ✅ 75 automated test assertions passed.

### v1.0.0 — Initial Release
- Core file management: upload, download, rename, delete, ZIP extraction/compression, code editor, dark mode.

---

## 🤝 Contributing

1. **Fork** the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'feat: add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a **Pull Request**

---

## 📄 License

Distributed under the [MIT License](LICENSE). Free for personal and commercial use.
