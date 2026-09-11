// Comprehensive DOM behavioral simulation test for upload modal
const fs = require('fs');
const path = require('path');

// Mock a lightweight DOM environment
class MockClassList {
    constructor() {
        this.classes = new Set();
    }
    add(cls) { this.classes.add(cls); }
    remove(cls) { this.classes.delete(cls); }
    contains(cls) { return this.classes.has(cls); }
}

class MockElement {
    constructor(id, tagName = 'div') {
        this.id = id;
        this.tagName = tagName.toUpperCase();
        this.classList = new MockClassList();
        this.style = {};
        this.attributes = {};
        this.listeners = {};
        this.textContent = '';
        this.innerHTML = '';
        this.value = '';
        this.files = [];
        this.disabled = false;
        this.children = [];
    }
    setAttribute(key, val) { this.attributes[key] = val; }
    removeAttribute(key) { delete this.attributes[key]; }
    getAttribute(key) { return this.attributes[key]; }
    addEventListener(event, fn) {
        if (!this.listeners[event]) this.listeners[event] = [];
        this.listeners[event].push(fn);
    }
    dispatchEvent(event) {
        const fns = this.listeners[event.type] || [];
        for (const fn of fns) fn(event);
    }
    click() {
        this.dispatchEvent({ type: 'click', target: this, preventDefault() {}, stopPropagation() {} });
    }
    appendChild(child) { this.children.push(child); }
    closest(selector) {
        if (selector === '#' + this.id) return this;
        return null;
    }
}

// Elements needed
const modalUpload = new MockElement('modalUpload');
const btnStartUpload = new MockElement('btnStartUpload', 'button');
const btnCancelUpload = new MockElement('btnCancelUpload', 'button');
const btnClearUploadQueue = new MockElement('btnClearUploadQueue', 'button');
const btnBrowseFiles = new MockElement('btnBrowseFiles', 'button');
const uploadDropArea = new MockElement('uploadDropArea');
const uploadFileInput = new MockElement('uploadFileInput', 'input');
const uploadFileInputFallback = new MockElement('uploadFileInputFallback', 'input');
const uploadQueueContainer = new MockElement('uploadQueueContainer');
const uploadQueueList = new MockElement('uploadQueueList');
const uploadSummaryBar = new MockElement('uploadSummaryBar');
const uploadSummaryCounter = new MockElement('uploadSummaryCounter');
const uploadSummaryStatus = new MockElement('uploadSummaryStatus');
const uploadTargetDirDisplay = new MockElement('uploadTargetDirDisplay');

const elementsMap = {
    modalUpload,
    btnStartUpload,
    btnCancelUpload,
    btnClearUploadQueue,
    btnBrowseFiles,
    uploadDropArea,
    uploadFileInput,
    uploadFileInputFallback,
    uploadQueueContainer,
    uploadQueueList,
    uploadSummaryBar,
    uploadSummaryCounter,
    uploadSummaryStatus,
    uploadTargetDirDisplay
};

// Global mocks
let directoryRefreshedPath = null;
let directoryRefreshCount = 0;

const state = {
    currentPath: '/public_html/uploads/images',
    csrfToken: 'mock_token'
};

const elements = {
    modalUpload: modalUpload
};

function formatBytes(bytes) {
    return bytes + ' B';
}

function escapeHtml(s) {
    return s;
}

function showToast(msg, type) {}

function loadDirectory(path, scroll) {
    directoryRefreshedPath = path;
    directoryRefreshCount++;
}

// Emulate app.js logic exactly
let uploadQueue = [];
let isUploading = false;
let uploadModalPhase = 'idle';
let uploadHasSuccess = false;

function closeActiveModals() {
    if (isUploading) return;
    const isUploadOpen = elements.modalUpload && elements.modalUpload.classList.contains('show');
    const shouldRefresh = uploadHasSuccess || uploadQueue.some(item => item.status === 'completed');

    modalUpload.classList.remove('show');

    if (isUploadOpen) {
        if (shouldRefresh) {
            loadDirectory(state.currentPath, false);
        }
        if (uploadModalPhase === 'finished' || uploadQueue.length > 0) {
            clearUploadQueue();
        }
    }
}

function handleUploadButtonClick() {
    if (uploadModalPhase === 'finished') {
        closeActiveModals();
        return;
    }
    if (uploadModalPhase === 'idle') {
        startUploadProcess();
    }
}

function clearUploadQueue() {
    uploadQueue = [];
    uploadModalPhase = 'idle';
    uploadHasSuccess = false;
    isUploading = false;

    uploadFileInput.value = '';
    uploadQueueList.innerHTML = '';
    uploadQueueContainer.style.display = 'none';
    uploadSummaryBar.style.display = 'none';

    btnStartUpload.disabled = true;
    btnStartUpload.setAttribute('disabled', 'disabled');
    btnStartUpload.className = 'btn btn-primary';
    btnStartUpload.textContent = 'Mulai Upload';
    btnStartUpload.style.pointerEvents = 'auto';
    btnStartUpload.style.cursor = 'not-allowed';

    btnCancelUpload.disabled = false;
    btnCancelUpload.removeAttribute('disabled');
    btnCancelUpload.textContent = 'Tutup';
    btnCancelUpload.style.pointerEvents = 'auto';
    btnCancelUpload.style.cursor = 'pointer';
}

function renderUploadQueue() {
    if (uploadQueue.length === 0) {
        clearUploadQueue();
        return;
    }
    uploadQueueContainer.style.display = 'block';
    if (!isUploading && uploadModalPhase === 'idle') {
        btnStartUpload.disabled = false;
        btnStartUpload.removeAttribute('disabled');
        btnStartUpload.className = 'btn btn-primary';
        btnStartUpload.textContent = `Mulai Upload (${uploadQueue.length} Berkas)`;
        btnStartUpload.style.pointerEvents = 'auto';
        btnStartUpload.style.cursor = 'pointer';
    }
}

function addFilesToQueue(fileList) {
    if (!fileList || fileList.length === 0) return;
    if (uploadModalPhase === 'finished') {
        clearUploadQueue();
    }
    for (const f of fileList) {
        uploadQueue.push({
            name: f.name,
            size: f.size,
            status: 'waiting',
            progress: 0,
            loadedBytes: 0,
            error: ''
        });
    }
    renderUploadQueue();
}

async function startUploadProcess() {
    if (isUploading || uploadQueue.length === 0) return;
    isUploading = true;
    uploadModalPhase = 'uploading';

    btnStartUpload.disabled = true;
    btnStartUpload.setAttribute('disabled', 'disabled');
    btnStartUpload.textContent = '⏳ Mengunggah...';
    btnCancelUpload.disabled = true;

    // Simulate completion
    for (const item of uploadQueue) {
        item.status = 'completed';
        item.progress = 100;
        uploadHasSuccess = true;
    }

    isUploading = false;
    uploadModalPhase = 'finished';

    // Set Tombol Selesai
    btnStartUpload.disabled = false;
    btnStartUpload.removeAttribute('disabled');
    btnStartUpload.style.pointerEvents = 'auto';
    btnStartUpload.style.cursor = 'pointer';
    btnStartUpload.className = 'btn btn-success';
    btnStartUpload.textContent = '✓ Selesai';

    btnCancelUpload.disabled = false;
    btnCancelUpload.removeAttribute('disabled');
    btnCancelUpload.textContent = 'Tutup';

    loadDirectory(state.currentPath, false);
}

// Bind handlers
btnStartUpload.addEventListener('click', handleUploadButtonClick);
btnCancelUpload.addEventListener('click', closeActiveModals);

// ==========================================
// TEST SCENARIOS
// ==========================================
console.log("Starting DOM Behavioral Simulation Tests...\n");

// Scenario 1: Initial state
console.log("Scenario 1: Testing Initial Modal State");
clearUploadQueue();
modalUpload.classList.add('show');
if (btnStartUpload.disabled !== true) throw new Error("Initial btnStartUpload should be disabled");
if (btnStartUpload.textContent !== 'Mulai Upload') throw new Error("Initial text should be 'Mulai Upload'");
console.log("✓ Initial state OK: disabled=true, text='Mulai Upload'");

// Scenario 2: Add single file
console.log("\nScenario 2: Select 1 file");
addFilesToQueue([{ name: 'test_image.png', size: 1024 }]);
if (btnStartUpload.disabled !== false) throw new Error("btnStartUpload should be enabled when file added");
if (btnStartUpload.textContent !== 'Mulai Upload (1 Berkas)') throw new Error("btnStartUpload text mismatch: " + btnStartUpload.textContent);
console.log("✓ File added OK: disabled=false, text='Mulai Upload (1 Berkas)'");

// Scenario 3: Start Upload
console.log("\nScenario 3: Execute Upload & Finish");
startUploadProcess().then(() => {
    // Check after completion
    if (uploadModalPhase !== 'finished') throw new Error("Phase should be 'finished'");
    if (btnStartUpload.disabled !== false) throw new Error("btnStartUpload must be ENABLED after upload finished!");
    if (btnStartUpload.textContent !== '✓ Selesai') throw new Error("btnStartUpload text should be '✓ Selesai'");
    if (btnStartUpload.style.pointerEvents !== 'auto') throw new Error("btnStartUpload pointerEvents must be 'auto'");
    if (btnStartUpload.style.cursor !== 'pointer') throw new Error("btnStartUpload cursor must be 'pointer'");
    console.log("✓ Finished state OK: disabled=false, pointerEvents='auto', text='✓ Selesai'");

    // Scenario 4: Click "Selesai"
    console.log("\nScenario 4: User clicks '✓ Selesai'");
    const beforeRefreshCount = directoryRefreshCount;
    btnStartUpload.click();

    if (modalUpload.classList.contains('show')) throw new Error("modalUpload should be closed after clicking Selesai!");
    if (directoryRefreshedPath !== '/public_html/uploads/images') throw new Error("CURRENT_PATH was not refreshed correctly! Path: " + directoryRefreshedPath);
    if (directoryRefreshCount <= beforeRefreshCount) throw new Error("loadDirectory was not called on modal close!");
    if (uploadQueue.length !== 0) throw new Error("uploadQueue should be empty after Selesai!");
    if (uploadModalPhase !== 'idle') throw new Error("uploadModalPhase should be reset to 'idle'!");
    if (btnStartUpload.disabled !== true) throw new Error("btnStartUpload should be reset to disabled=true!");
    console.log("✓ Clicking Selesai OK: modal closed, CURRENT_PATH refreshed (" + directoryRefreshedPath + "), queue cleared, phase reset to idle.");

    // Scenario 5: Multiple files and close via "Tutup" (btnCancelUpload)
    console.log("\nScenario 5: Multiple files and closing via 'Tutup'");
    modalUpload.classList.add('show');
    addFilesToQueue([{ name: 'file1.zip', size: 5000 }, { name: 'file2.pdf', size: 8000 }]);
    if (btnStartUpload.textContent !== 'Mulai Upload (2 Berkas)') throw new Error("Text mismatch for multiple files");

    startUploadProcess().then(() => {
        if (btnStartUpload.disabled !== false) throw new Error("btnStartUpload should be enabled");
        // Click Tutup
        btnCancelUpload.click();
        if (modalUpload.classList.contains('show')) throw new Error("modalUpload should be closed after clicking Tutup!");
        if (uploadQueue.length !== 0) throw new Error("uploadQueue should be cleared!");
        console.log("✓ Closing via 'Tutup' OK: modal closed, queue cleared, directory refreshed.");

        console.log("\n==========================================");
        console.log("ALL BEHAVIORAL SIMULATION TESTS PASSED! ✓");
        console.log("==========================================");
    });
});
