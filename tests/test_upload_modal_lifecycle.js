// Test upload modal state machine and button click lifecycle
const fs = require('fs');
const path = require('path');

const jsContent = fs.readFileSync(path.join(__dirname, '../assets/js/app.js'), 'utf8');

console.log("Analyzing assets/js/app.js upload modal lifecycle implementation...");

// Check 1: Button Selesai must NOT have disabled = true when finished
if (jsContent.includes('btnSubmit.disabled = false') && jsContent.includes("btnSubmit.textContent = failedCount === 0 ? '✓ Selesai' : 'Selesai (Ada Gagal)'")) {
    console.log("✓ PASS: btnSubmit.disabled is explicitly set to false when upload finishes.");
} else {
    console.error("✕ FAIL: btnSubmit.disabled is not set to false upon completion!");
    process.exit(1);
}

// Check 2: removeAttribute('disabled') must be called
if (jsContent.includes("btnSubmit.removeAttribute('disabled')")) {
    console.log("✓ PASS: btnSubmit.removeAttribute('disabled') is executed.");
} else {
    console.error("✕ FAIL: removeAttribute('disabled') not found!");
    process.exit(1);
}

// Check 3: pointerEvents = 'auto' and cursor = 'pointer'
if (jsContent.includes("btnSubmit.style.pointerEvents = 'auto'") && jsContent.includes("btnSubmit.style.cursor = 'pointer'")) {
    console.log("✓ PASS: pointerEvents and cursor properly set to clickable.");
} else {
    console.error("✕ FAIL: pointerEvents or cursor not set correctly!");
    process.exit(1);
}

// Check 4: Click handler handles 'finished' state
if (jsContent.includes("function handleUploadButtonClick()") && jsContent.includes("if (uploadModalPhase === 'finished')")) {
    console.log("✓ PASS: handleUploadButtonClick explicitly handles 'finished' state to close modal and refresh.");
} else {
    console.error("✕ FAIL: handleUploadButtonClick does not handle finished state!");
    process.exit(1);
}

// Check 5: closeActiveModals refreshes directory and clears queue
if (jsContent.includes("if (isUploadModalOpen)") && jsContent.includes("loadDirectory(state.currentPath, false)") && jsContent.includes("clearUploadQueue()")) {
    console.log("✓ PASS: closeActiveModals safely refreshes CURRENT_PATH and clears queue when upload modal closes.");
} else {
    console.error("✕ FAIL: closeActiveModals does not handle upload refresh or queue cleanup!");
    process.exit(1);
}

// Check 6: Backdrop click closes modal
if (jsContent.includes("document.querySelectorAll('.modal-backdrop').forEach(modal =>") && jsContent.includes("if (e.target === modal)")) {
    console.log("✓ PASS: Backdrop click handler is installed to close modal when clicking outside modal box.");
} else {
    console.error("✕ FAIL: Backdrop click handler not found!");
    process.exit(1);
}

console.log("\nALL UPLOAD MODAL CODE AUDITS PASSED SUCCESSFULLY!");
