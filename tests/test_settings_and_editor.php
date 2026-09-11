<?php
define('APP_INIT', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/Logger.php';
require_once __DIR__ . '/../app/Auth.php';

echo "======================================================================\n";
echo "  TEST: 30-DAY SESSION, ADMIN SETTINGS & EDITOR LAYOUT\n";
echo "======================================================================\n";

$passed = 0;
$failed = 0;

function assertTest(string $name, bool $cond, string $details = '') {
    global $passed, $failed;
    if ($cond) {
        echo "[PASS] {$name}" . ($details ? " - {$details}" : "") . "\n";
        $passed++;
    } else {
        echo "[FAIL] {$name}" . ($details ? " - {$details}" : "") . "\n";
        $failed++;
    }
}

// 1. Test Session Timeout Constant
assertTest("1. Session Timeout is 30 Days", SESSION_TIMEOUT === 2592000, "SESSION_TIMEOUT = " . SESSION_TIMEOUT . " seconds");

// 2. Test Stored Credentials Initial Fallback
$creds = Auth::getStoredCredentials();
assertTest("2. Initial Credential Fallback", strtolower($creds['username']) === 'admin', "Username is " . $creds['username']);

// 3. Test Initial Login with admin123
$loginRes = Auth::login('admin', 'admin123');
assertTest("3. Login with baseline password admin123", $loginRes['success'] === true, $loginRes['message']);

// 4. Test Update Settings with wrong current password
$wrongPassRes = Auth::updateCredentials('wrongpassword', 'admin2', 'newpass123');
assertTest("4. Rejects update with incorrect current password", $wrongPassRes['success'] === false, $wrongPassRes['message']);

// 5. Test Update Settings with short new password
$shortPassRes = Auth::updateCredentials('admin123', 'admin2', '123');
assertTest("5. Rejects update with short new password", $shortPassRes['success'] === false, $shortPassRes['message']);

// 6. Test Successful Update Settings (Update to admin_test & testpass123)
$updateRes = Auth::updateCredentials('admin123', 'admin_test', 'testpass123');
assertTest("6. Updates credentials to admin_test & testpass123", $updateRes['success'] === true, $updateRes['message']);

// Check credentials.json on disk
$credsFile = defined('CREDENTIALS_FILE') ? CREDENTIALS_FILE : (STORAGE_PATH . '/credentials.json');
assertTest("7. credentials.json exists on disk", file_exists($credsFile), $credsFile);

// Check login with old password fails
Auth::logout();
$oldLogin = Auth::login('admin_test', 'admin123');
assertTest("8. Old password admin123 no longer works after update", $oldLogin['success'] === false);

// Check login with new password succeeds
$newLogin = Auth::login('admin_test', 'testpass123');
assertTest("9. New credentials login succeeds", $newLogin['success'] === true, $newLogin['message']);

// Revert credentials back to admin & admin123
$revertRes = Auth::updateCredentials('testpass123', 'admin', 'admin123');
assertTest("10. Revert credentials back to admin & admin123", $revertRes['success'] === true, $revertRes['message']);

// Verify login with admin & admin123 again
Auth::logout();
$recheckLogin = Auth::login('admin', 'admin123');
assertTest("11. Login with restored admin & admin123 succeeds", $recheckLogin['success'] === true);

// 12. Check CSS file for editor flex and button rules
$css = file_get_contents(__DIR__ . '/../assets/css/style.css');
assertTest("12. CSS contains #formEditor flex styling", strpos($css, '#formEditor') !== false && strpos($css, 'flex-direction: column') !== false);
assertTest("13. CSS contains .editor-modal-box.is-fullscreen", strpos($css, '.editor-modal-box.is-fullscreen') !== false);
assertTest("14. CSS contains .code-editor-textarea.is-wrapped", strpos($css, '.code-editor-textarea.is-wrapped') !== false);
assertTest("15. CSS contains .btn-settings", strpos($css, '.btn-settings') !== false);

// 16. Check index.php for settings modal and editor buttons
$indexHtml = file_get_contents(__DIR__ . '/../index.php');
assertTest("16. index.php contains modalSettings", strpos($indexHtml, 'id="modalSettings"') !== false);
assertTest("17. index.php contains btnOpenSettings", strpos($indexHtml, 'id="btnOpenSettings"') !== false);
assertTest("18. index.php contains btnEditorWrap", strpos($indexHtml, 'id="btnEditorWrap"') !== false);
assertTest("19. index.php contains btnEditorFullscreen", strpos($indexHtml, 'id="btnEditorFullscreen"') !== false);
assertTest("20. index.php contains update_settings route", strpos($indexHtml, "'update_settings'") !== false);

// 21. Check app.js for handlers
$appJs = file_get_contents(__DIR__ . '/../assets/js/app.js');
assertTest("21. app.js contains openSettingsModal", strpos($appJs, 'openSettingsModal') !== false);
assertTest("22. app.js contains handleSettingsSubmit", strpos($appJs, 'handleSettingsSubmit') !== false);
assertTest("23. app.js contains btnEditorWrap handler", strpos($appJs, 'btnEditorWrap') !== false);
assertTest("24. app.js contains btnEditorFullscreen handler", strpos($appJs, 'btnEditorFullscreen') !== false);

echo "======================================================================\n";
echo "  HASIL: {$passed} PASSED, {$failed} FAILED\n";
echo "======================================================================\n";

exit($failed === 0 ? 0 : 1);
