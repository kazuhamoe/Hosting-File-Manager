<?php
/**
 * Standalone One-Click Updater for Hosting File Manager
 * Digunakan jika File Manager di hosting masih menjalankan kode lama yang memblokir ekstraksi update.
 */
@ini_set("display_errors", "1");
error_reporting(E_ALL);

$dir = __DIR__;
$zipNames = ["hosting-file-manager.zip", "release.zip", "filemanager.zip", "update.zip"];
$targetZip = null;

foreach ($zipNames as $name) {
    if (file_exists($dir . DIRECTORY_SEPARATOR . $name)) {
        $targetZip = $dir . DIRECTORY_SEPARATOR . $name;
        break;
    }
}

// Tindakan hapus updater sendiri jika diminta
if (isset($_GET["done"])) {
    @unlink(__FILE__);
    header("Location: ./");
    exit;
}

$extracted = false;
$errorMsg = "";
$count = 0;

if ($targetZip !== null && class_exists("ZipArchive")) {
    $zip = new ZipArchive();
    $res = $zip->open($targetZip);
    if ($res === true) {
        // Ekstrak semua file langsung ke direktori saat ini
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            // Jangan timpa credentials.json jika sudah ada
            if (file_exists($dir . DIRECTORY_SEPARATOR . $entry) && strcasecmp(basename($entry), "credentials.json") === 0) {
                continue;
            }
            $zip->extractTo($dir, $entry);
            $count++;
        }
        $zip->close();
        $extracted = true;
    } else {
        $errorMsg = "Gagal membuka file ZIP (Error code: " . $res . ").";
    }
} elseif (!class_exists("ZipArchive")) {
    $errorMsg = "Ekstensi PHP ZipArchive tidak aktif di hosting ini.";
} else {
    $errorMsg = "Berkas ZIP (hosting-file-manager.zip) tidak ditemukan di folder " . htmlspecialchars($dir);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Updater — Hosting File Manager</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 32px; max-width: 520px; width: 100%; box-shadow: 0 12px 30px rgba(0,0,0,0.5); text-align: center; }
        .icon-success { width: 56px; height: 56px; fill: #10b981; margin-bottom: 16px; }
        .icon-error { width: 56px; height: 56px; fill: #ef4444; margin-bottom: 16px; }
        h1 { font-size: 20px; margin: 0 0 10px; color: #fff; }
        p { font-size: 14px; color: #94a3b8; line-height: 1.6; margin: 0 0 20px; }
        .btn { display: inline-block; background: #0284c7; color: #fff; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: 600; font-size: 14px; transition: background 0.15s; }
        .btn:hover { background: #0369a1; }
        .btn-success { background: #10b981; }
        .btn-success:hover { background: #059669; }
        code { background: #0f172a; padding: 3px 8px; border-radius: 4px; color: #38bdf8; font-size: 13px; }
    </style>
</head>
<body>
    <div class="card">
        <?php if ($extracted): ?>
            <svg class="icon-success" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
            <h1>Update Berhasil Dipasang!</h1>
            <p>Sebanyak <strong><?= $count ?></strong> berkas telah berhasil diperbarui langsung ke server hosting Anda.<br>Fix Ekstrak ZIP, Tombol Settings, dan Sesi 30 Hari kini aktif.</p>
            <a href="?done=1" class="btn btn-success">Buka File Manager Sekarang</a>
        <?php else: ?>
            <svg class="icon-error" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
            <h1>Update Belum Selesai</h1>
            <p><?= htmlspecialchars($errorMsg) ?></p>
            <p style="font-size:12px;">Pastikan berkas <code>hosting-file-manager.zip</code> berada di folder yang sama dengan <code>updater.php</code>.</p>
            <a href="updater.php" class="btn">Coba Lagi</a>
        <?php endif; ?>
    </div>
</body>
</html>
