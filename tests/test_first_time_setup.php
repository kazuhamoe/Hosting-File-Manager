<?php
/**
 * Test Suite: First-Time Setup Wizard (Create Password on Initial Install)
 */

define('APP_INIT', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/Logger.php';
require_once __DIR__ . '/../app/Auth.php';

echo "======================================================================\n";
echo "  TEST: FIRST-TIME SETUP WIZARD (CREATE PASSWORD ON INSTALLATION)\n";
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

$credsFile = defined('CREDENTIALS_FILE') ? CREDENTIALS_FILE : (STORAGE_PATH . '/credentials.json');
$backupFile = $credsFile . '.setup_test_bak';

// Backup existing credentials.json if present
if (file_exists($credsFile)) {
    copy($credsFile, $backupFile);
    unlink($credsFile);
}

try {
    // 1. Check isSetupRequired returns true when credentials.json does not exist
    assertTest("1. isSetupRequired() is true on fresh install", Auth::isSetupRequired() === true);

    // 2. Test validation: Short username (< 3 chars)
    $resShortUser = Auth::setupInitialCredentials('ab', 'password123', 'password123');
    assertTest("2. Rejects username under 3 characters", $resShortUser['success'] === false, $resShortUser['message']);

    // 3. Test validation: Invalid username format (spaces/special symbols)
    $resInvalidUser = Auth::setupInitialCredentials('admin user!', 'password123', 'password123');
    assertTest("3. Rejects invalid characters in username", $resInvalidUser['success'] === false, $resInvalidUser['message']);

    // 4. Test validation: Short password (< 5 chars)
    $resShortPass = Auth::setupInitialCredentials('superadmin', '1234', '1234');
    assertTest("4. Rejects password under 5 characters", $resShortPass['success'] === false, $resShortPass['message']);

    // 5. Test validation: Password confirmation mismatch
    $resMismatch = Auth::setupInitialCredentials('superadmin', 'password123', 'different456');
    assertTest("5. Rejects password confirmation mismatch", $resMismatch['success'] === false, $resMismatch['message']);

    // 6. Test successful initial setup
    $resSuccess = Auth::setupInitialCredentials('cloudadmin', 'SuperSecure123!', 'SuperSecure123!');
    assertTest("6. Successful initial setup returns success=true", $resSuccess['success'] === true, $resSuccess['message']);

    // 7. Test credentials file is created
    assertTest("7. storage/credentials.json exists after setup", file_exists($credsFile));

    // 8. Test isSetupRequired is now false
    assertTest("8. isSetupRequired() is now false after setup", Auth::isSetupRequired() === false);

    // 9. Test rejection of re-setup attempts (security against re-setup attacks)
    $resReSetup = Auth::setupInitialCredentials('hacker', 'hackedpass', 'hackedpass');
    assertTest("9. Re-setup attempt rejected once already configured", $resReSetup['success'] === false, $resReSetup['message']);

    // 10. Test login with newly created credentials
    Auth::logout();
    $resLoginSuccess = Auth::login('cloudadmin', 'SuperSecure123!');
    assertTest("10. Login with new credentials succeeds", $resLoginSuccess['success'] === true, $resLoginSuccess['message']);

    // 11. Test login with wrong password fails
    Auth::logout();
    $resWrongPass = Auth::login('cloudadmin', 'WrongPass!');
    assertTest("11. Login with wrong password fails", $resWrongPass['success'] === false);

    // 12. Test login with default admin/admin123 fails (since cloudadmin was created)
    $resDefaultFail = Auth::login('admin', 'admin123');
    assertTest("12. Login with old default credentials fails", $resDefaultFail['success'] === false);

} finally {
    // Restore original credentials backup or initialize admin/admin123 for regression tests
    if (file_exists($backupFile)) {
        rename($backupFile, $credsFile);
    } else {
        // Restore standard test credentials admin / admin123
        Auth::setupInitialCredentials('admin', 'admin123', 'admin123');
    }
}

// 13. Check that restored credentials work
Auth::logout();
$resRestored = Auth::login('admin', 'admin123');
assertTest("13. Restored admin/admin123 credentials active for regression testing", $resRestored['success'] === true);

// 14. Check index.php does NOT contain default password hints or autofills
$indexContent = file_get_contents(__DIR__ . '/../index.php');
$hasDefaultUserHint = strpos($indexContent, 'placeholder="admin"') !== false;
$hasDefaultPassHint = strpos($indexContent, 'placeholder="Password hosting"') !== false;
assertTest("14. Login form does not display default username placeholder", !$hasDefaultUserHint);
assertTest("15. Login form does not display default password placeholder", !$hasDefaultPassHint);

echo "======================================================================\n";
echo "  HASIL: {$passed} PASSED, {$failed} FAILED\n";
echo "======================================================================\n";

exit($failed === 0 ? 0 : 1);
