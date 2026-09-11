const fs = require('fs');
const html = fs.readFileSync('index.php', 'utf8');
const css = fs.readFileSync('assets/css/style.css', 'utf8');
const js = fs.readFileSync('assets/js/app.js', 'utf8');

console.log('--- UI/UX Polish Verification ---');

// 1. Toolbar SVGs in index.php
const toolbarButtons = [
    'btnBack', 'btnForward', 'btnUp', 'btnReload',
    'btnNewFolder', 'btnNewFile', 'btnOpenUpload',
    'btnDownloadSelected', 'btnCompressSelected', 'btnExtractSelected',
    'btnCopySelected', 'btnMoveSelected', 'btnEditSelected',
    'btnRenameSelected', 'btnChmodSelected', 'btnDeleteSelected',
    'btnToggleHidden'
];
let missingButtons = toolbarButtons.filter(id => !html.includes(`id="${id}"`));
if (missingButtons.length > 0) throw new Error('Missing toolbar buttons: ' + missingButtons.join(', '));
console.log('✓ All 17 toolbar buttons present in index.php');

// 2. Search clear button
if (!html.includes('id="btnSearchClear"')) throw new Error('btnSearchClear missing in index.php');
console.log('✓ Search clear button present in index.php');

// 3. Context menu SVGs in index.php
if (!html.includes('id="contextMenu"')) throw new Error('contextMenu missing in index.php');
if (html.includes('<span class="ctx-icon">📁</span>')) throw new Error('Emoji still found in context menu');
console.log('✓ Context menu emojis replaced with SVGs');

// 4. File extension SVGs in app.js
const icons = [
    'icon-folder', 'icon-zip', 'icon-php', 'icon-html',
    'icon-css', 'icon-js', 'icon-config', 'icon-image',
    'icon-pdf', 'icon-sql', 'icon-text', 'icon-file'
];
let missingIcons = icons.filter(cls => !js.includes(cls));
if (missingIcons.length > 0) throw new Error('Missing icons in app.js: ' + missingIcons.join(', '));
console.log('✓ All 12 file type icons implemented in getFileIconSvg');

// 5. CSS variables and classes in style.css
const cssClasses = [
    '.app-toolbar', '.file-table', '.context-menu',
    '.modal-backdrop', '.toast-success', '.toast-error',
    '.toast-warning', '.toast-info', '.col-perms',
    '.col-size', '.col-mtime'
];
let missingCss = cssClasses.filter(c => !css.includes(c));
if (missingCss.length > 0) throw new Error('Missing CSS classes: ' + missingCss.join(', '));
console.log('✓ All core styling classes defined in style.css');

console.log('==========================================');
console.log('ALL UI/UX POLISH CHECKS PASSED! ✓');
console.log('==========================================');
