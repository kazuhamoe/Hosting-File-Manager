<?php
/**
 * Test: ConfigManager & UI System Configuration (config.php & storage/settings.json)
 */

define('APP_INIT', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/Security.php';
require_once __DIR__ . '/../app/Logger.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/ConfigManager.php';

$passed = 0;
$failed = 0;

function assertTest(bool $cond, string $title, string $detail = '') {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] $title" . ($detail ? " - $detail" : "") . "\n";
    } else {
        $failed++;
        echo "  [FAIL] $title" . ($detail ? " - $detail" : "") . "\n";
    }
}

echo "======================================================================\n";
echo "  TEST: SYSTEM CONFIGURATION VIA UI (ConfigManager)\n";
echo "======================================================================\n\n";

// 1. Baca konfigurasi awal
$initial = ConfigManager::getAllSettings();
assertTest(isset($initial['allowed_root']), '1. getAllSettings returns allowed_root', $initial['allowed_root']);
assertTest(isset($initial['session_timeout']), '2. getAllSettings returns session_timeout', (string)$initial['session_timeout']);
assertTest(isset($initial['max_upload_size_mb']), '3. getAllSettings returns max_upload_size_mb', (string)$initial['max_upload_size_mb']);
assertTest(isset($initial['show_disk_usage']), '4. getAllSettings returns show_disk_usage', $initial['show_disk_usage'] ? 'true' : 'false');
assertTest(isset($initial['server_limits']), '5. getAllSettings returns server_limits');

// 2. Verifikasi penolakan jika password konfirmasi salah
$resWrongPass = ConfigManager::saveSettings([
    'max_upload_size_mb' => 300
], 'wrong_password_123');
assertTest($resWrongPass['success'] === false, '6. Rejects save with incorrect password', $resWrongPass['message']);

// 3. Verifikasi penolakan direktori root yang tidak valid
$resBadRoot = ConfigManager::saveSettings([
    'allowed_root' => 'Z:\\path\\yang\\pasti\\tidak\\ada\\12345'
], 'admin123');
assertTest($resBadRoot['success'] === false, '7. Rejects invalid non-existent root path', $resBadRoot['message']);

// 4. Sukses menyimpan konfigurasi baru
$resSave = ConfigManager::saveSettings([
    'allowed_root'       => $initial['default_root'],
    'max_upload_size_mb' => 350,
    'session_timeout'    => 604800,
    'show_disk_usage'    => true,
    'disk_quota_mb'      => 10240
], 'admin123');
assertTest($resSave['success'] === true, '8. Successfully saves valid system settings', $resSave['message']);

// 5. Cek apakah storage/settings.json terbentuk dan terbaca
$settingsFile = ConfigManager::getSettingsFilePath();
assertTest(file_exists($settingsFile), '9. storage/settings.json exists on disk');

$updated = ConfigManager::getAllSettings();
assertTest($updated['max_upload_size_mb'] === 350, '10. max_upload_size_mb updated to 350 MB');
assertTest($updated['session_timeout'] === 604800, '11. session_timeout updated to 604800 (7 days)');
assertTest($updated['show_disk_usage'] === true, '12. show_disk_usage updated to true');
assertTest($updated['disk_quota_mb'] === 10240, '13. disk_quota_mb updated to 10240 MB');

// 6. Kembalikan pengaturan ke default agar bersih
$resRevert = ConfigManager::saveSettings([
    'allowed_root'       => $initial['default_root'],
    'max_upload_size_mb' => 200,
    'session_timeout'    => 2592000,
    'show_disk_usage'    => false,
    'disk_quota_mb'      => 0
], 'admin123');
assertTest($resRevert['success'] === true, '14. Successfully reverts settings to default');

$reverted = ConfigManager::getAllSettings();
assertTest($reverted['max_upload_size_mb'] === 200, '15. max_upload_size_mb restored to 200 MB');
assertTest($reverted['session_timeout'] === 2592000, '16. session_timeout restored to 2592000 (30 days)');
assertTest($reverted['show_disk_usage'] === false, '17. show_disk_usage restored to false');

// Hapus file settings.json tes jika ingin kembali murni default
if (file_exists($settingsFile)) {
    @unlink($settingsFile);
}

echo "\n======================================================================\n";
echo "  HASIL: $passed PASSED, $failed FAILED\n";
echo "======================================================================\n";

if ($failed > 0) exit(1);
