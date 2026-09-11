/**
 * Hosting File Manager - Full cPanel-like Frontend Controller
 * Menangani navigasi, AJAX request, multi-select, bulk operations,
 * editor berkas, CHMOD matrix, ZIP compress/extract, upload progress,
 * folder tree selector, dan context menu desktop.
 */

(function () {
    'use strict';

    // Application State
    const state = {
        currentPath: '/',
        history: [],
        forwardHistory: [],
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        sortField: 'name',
        sortOrder: 'asc',
        items: [],
        selectedDestination: '/',
        selectedPaths: new Set(),
        showHidden: sessionStorage.getItem('hfm_show_hidden') === '1',
        contextTarget: null, // item object or null (blank area)
        editorDirty: false,
        editorOriginalContent: '',
        compressItems: []
    };

    // DOM Elements Cache
    const elements = {
        tableBody: document.getElementById('fileTableBody'),
        breadcrumbs: document.getElementById('breadcrumbsContainer'),
        searchInput: document.getElementById('searchInput'),
        btnSearchClear: document.getElementById('btnSearchClear'),
        btnBack: document.getElementById('btnBack'),
        btnForward: document.getElementById('btnForward'),
        btnUp: document.getElementById('btnUp'),
        btnReload: document.getElementById('btnReload'),
        btnToggleHidden: document.getElementById('btnToggleHidden'),
        selectAllCheckbox: document.getElementById('selectAllCheckbox'),
        // Toolbar selection action buttons
        btnDownloadSelected: document.getElementById('btnDownloadSelected'),
        btnCompressSelected: document.getElementById('btnCompressSelected'),
        btnExtractSelected: document.getElementById('btnExtractSelected'),
        btnCopySelected: document.getElementById('btnCopySelected'),
        btnMoveSelected: document.getElementById('btnMoveSelected'),
        btnEditSelected: document.getElementById('btnEditSelected'),
        btnRenameSelected: document.getElementById('btnRenameSelected'),
        btnChmodSelected: document.getElementById('btnChmodSelected'),
        btnDeleteSelected: document.getElementById('btnDeleteSelected'),
        // Status bar elements
        statusTotalItems: document.getElementById('statusTotalItems'),
        statusSelectedItems: document.getElementById('statusSelectedItems'),
        statusHiddenHint: document.getElementById('statusHiddenHint'),
        statusCurrentPathHint: document.getElementById('statusCurrentPathHint'),
        toastContainer: document.getElementById('toastContainer'),
        // Modals
        modalUpload: document.getElementById('modalUpload'),
        modalFile: document.getElementById('modalFile'),
        modalFolder: document.getElementById('modalFolder'),
        modalRename: document.getElementById('modalRename'),
        modalDelete: document.getElementById('modalDelete'),
        modalExtract: document.getElementById('modalExtract'),
        modalCopyMove: document.getElementById('modalCopyMove'),
        modalInfo: document.getElementById('modalInfo'),
        modalPreview: document.getElementById('modalPreview'),
        modalEditor: document.getElementById('modalEditor'),
        modalCompress: document.getElementById('modalCompress'),
        modalChmod: document.getElementById('modalChmod'),
        modalSettings: document.getElementById('modalSettings'),
        btnOpenSettings: document.getElementById('btnOpenSettings'),
        headerUserBadge: document.getElementById('headerUserBadge'),
        contextMenu: document.getElementById('contextMenu'),
        // Code Editor Elements
        editorContent: document.getElementById('editorContent'),
        editorFilePath: document.getElementById('editorFilePath'),
        editorFilePathDisplay: document.getElementById('editorFilePathDisplay'),
        editorModalTitle: document.getElementById('editorModalTitle'),
        editorDirtyBadge: document.getElementById('editorDirtyBadge'),
        editorStatLines: document.getElementById('editorStatLines'),
        editorStatChars: document.getElementById('editorStatChars'),
        editorSaveStatus: document.getElementById('editorSaveStatus'),
        editorWarningBox: document.getElementById('editorWarningBox'),
        btnSaveEditor: document.getElementById('btnSaveEditor'),
        btnCancelEditor: document.getElementById('btnCancelEditor'),
        btnEditorClose: document.getElementById('btnEditorClose'),
        btnEditorWrap: document.getElementById('btnEditorWrap'),
        btnEditorFullscreen: document.getElementById('btnEditorFullscreen'),
        // CHMOD Elements
        chmodOctalInput: document.getElementById('chmodOctalInput'),
        chmodRwxDisplay: document.getElementById('chmodRwxDisplay')
    };

    // --------------------------------------------------------------------------
    // Initializer
    // --------------------------------------------------------------------------
    document.addEventListener('DOMContentLoaded', () => {
        let initialPath = '/';
        if (window.location.hash && window.location.hash.includes('dir=')) {
            const match = window.location.hash.match(/dir=([^&]+)/);
            if (match) {
                try {
                    initialPath = decodeURIComponent(match[1]);
                } catch (e) {}
            }
        } else {
            const saved = sessionStorage.getItem('hfm_current_path');
            if (saved && saved.startsWith('/')) {
                initialPath = saved;
            }
        }

        updateToggleHiddenButton();
        loadDirectory(initialPath, false);
        setupEventListeners();

        // Browser Back / Forward hash navigation
        window.addEventListener('hashchange', () => {
            if (window.location.hash && window.location.hash.includes('dir=')) {
                const match = window.location.hash.match(/dir=([^&]+)/);
                if (match) {
                    try {
                        const targetDir = decodeURIComponent(match[1]);
                        if (targetDir !== state.currentPath) {
                            loadDirectory(targetDir, false);
                        }
                    } catch (e) {}
                }
            }
        });
    });

    // --------------------------------------------------------------------------
    // Event Listeners Setup
    // --------------------------------------------------------------------------
    function setupEventListeners() {
        // Navigation buttons
        elements.btnReload?.addEventListener('click', () => loadDirectory(state.currentPath, false));
        elements.btnUp?.addEventListener('click', navigateUp);
        elements.btnBack?.addEventListener('click', navigateBack);
        elements.btnForward?.addEventListener('click', navigateForward);

        // Toggle Hidden Files
        elements.btnToggleHidden?.addEventListener('click', () => {
            state.showHidden = !state.showHidden;
            sessionStorage.setItem('hfm_show_hidden', state.showHidden ? '1' : '0');
            updateToggleHiddenButton();
            loadDirectory(state.currentPath, false);
        });

        // Search input & clear button
        elements.searchInput?.addEventListener('input', handleSearch);
        elements.btnSearchClear?.addEventListener('click', () => {
            if (elements.searchInput) {
                elements.searchInput.value = '';
                elements.searchInput.focus();
            }
            if (elements.btnSearchClear) {
                elements.btnSearchClear.style.display = 'none';
            }
            renderTable(state.items);
        });

        // Select All Checkbox in Table Header
        elements.selectAllCheckbox?.addEventListener('change', handleSelectAll);

        // Toolbar Selection Action Buttons
        elements.btnDownloadSelected?.addEventListener('click', handleDownloadSelected);
        elements.btnCompressSelected?.addEventListener('click', handleCompressSelected);
        elements.btnExtractSelected?.addEventListener('click', handleExtractSelected);
        elements.btnCopySelected?.addEventListener('click', handleCopySelected);
        elements.btnMoveSelected?.addEventListener('click', handleMoveSelected);
        elements.btnEditSelected?.addEventListener('click', handleEditSelected);
        elements.btnRenameSelected?.addEventListener('click', handleRenameSelected);
        elements.btnChmodSelected?.addEventListener('click', handleChmodSelected);
        elements.btnDeleteSelected?.addEventListener('click', handleDeleteSelected);

        // Create & Upload Triggers
        document.getElementById('btnNewFolder')?.addEventListener('click', () => {
            const display = document.getElementById('createFolderTargetDisplay');
            if (display) display.textContent = state.currentPath;
            openModal(elements.modalFolder);
            setTimeout(() => document.getElementById('inputFolderName')?.focus(), 50);
        });
        document.getElementById('btnNewFile')?.addEventListener('click', () => {
            const display = document.getElementById('createFileTargetDisplay');
            if (display) display.textContent = state.currentPath;
            openModal(elements.modalFile);
            setTimeout(() => document.getElementById('inputFileName')?.focus(), 50);
        });
        document.getElementById('btnOpenUpload')?.addEventListener('click', () => {
            const display = document.getElementById('uploadTargetDirDisplay');
            if (display) display.textContent = state.currentPath;
            clearUploadQueue();
            loadUploadLimitsDiagnostic();
            openModal(elements.modalUpload);
        });

        // Close modal buttons
        document.querySelectorAll('.modal-close, .btn-modal-cancel').forEach(btn => {
            if (btn.id !== 'btnEditorClose' && btn.id !== 'btnCancelEditor') {
                btn.addEventListener('click', closeActiveModals);
            }
        });

        // Close modal when clicking outside modal box (backdrop click)
        document.querySelectorAll('.modal-backdrop').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    if (modal.id === 'modalEditor') {
                        handleEditorClose();
                    } else {
                        closeActiveModals();
                    }
                }
            });
        });

        // Editor Close buttons (with unsaved changes check)
        elements.btnEditorClose?.addEventListener('click', handleEditorClose);
        elements.btnCancelEditor?.addEventListener('click', handleEditorClose);

        // Close dropdown and context menu on click outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.action-dropdown')) {
                closeAllDropdowns();
            }
            if (!e.target.closest('#contextMenu')) {
                hideContextMenu();
            }
        });

        // Keyboard shortcuts: ESC to close modals / context menu
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (elements.contextMenu && elements.contextMenu.style.display !== 'none') {
                    hideContextMenu();
                    return;
                }
                if (elements.modalEditor && elements.modalEditor.classList.contains('show')) {
                    handleEditorClose();
                    return;
                }
                closeActiveModals();
                closeAllDropdowns();
            }
        });

        // Form Submit Handlers
        document.getElementById('formNewFolder')?.addEventListener('submit', handleNewFolder);
        document.getElementById('formNewFile')?.addEventListener('submit', handleNewFile);
        document.getElementById('formRename')?.addEventListener('submit', handleRename);
        document.getElementById('formDelete')?.addEventListener('submit', handleDelete);
        document.getElementById('formExtract')?.addEventListener('submit', handleExtract);
        document.getElementById('formCopyMove')?.addEventListener('submit', handleCopyMove);
        document.getElementById('formCompress')?.addEventListener('submit', handleCompress);
        document.getElementById('formChmod')?.addEventListener('submit', handleChmod);
        document.getElementById('formEditor')?.addEventListener('submit', handleEditorSave);
        document.getElementById('formSettings')?.addEventListener('submit', handleSettingsSubmit);

        // Admin Settings Trigger
        elements.btnOpenSettings?.addEventListener('click', openSettingsModal);

        // Upload Handlers
        setupUploadEvents();

        // Code Editor Events
        setupEditorEvents();

        // Permissions (CHMOD) Matrix Change Events
        setupChmodEvents();

        // Context Menu Setup
        setupContextMenuEvents();

        // Table Sorting Header Clicks
        document.querySelectorAll('.file-table th[data-sort]').forEach(th => {
            th.addEventListener('click', () => {
                const field = th.getAttribute('data-sort');
                if (state.sortField === field) {
                    state.sortOrder = state.sortOrder === 'asc' ? 'desc' : 'asc';
                } else {
                    state.sortField = field;
                    state.sortOrder = 'asc';
                }
                updateSortHeaders();
                loadDirectory(state.currentPath, false);
            });
        });
    }

    // --------------------------------------------------------------------------
    // Directory Loading & Rendering
    // --------------------------------------------------------------------------
    async function loadDirectory(path, pushHistory = true) {
        showLoadingState();

        const url = `?action=list&path=${encodeURIComponent(path)}&show_hidden=${state.showHidden ? 1 : 0}&sort=${state.sortField}&order=${state.sortOrder}`;
        const data = await requestApi(url);

        if (!data.success) {
            showToast(data.message || 'Gagal memuat direktori.', 'error');
            if (path !== '/' && state.currentPath !== '/') {
                loadDirectory('/', false);
            }
            return;
        }

        if (pushHistory && state.currentPath !== data.current_path) {
            state.history.push(state.currentPath);
            state.forwardHistory = [];
        }

        state.currentPath = data.current_path;
        state.items = data.items || [];
        state.selectedPaths.clear();

        // Update navigation button states
        updateNavigationButtons();

        // Save active directory to sessionStorage and URL hash
        try {
            sessionStorage.setItem('hfm_current_path', state.currentPath);
            const expectedHash = `#dir=${encodeURIComponent(state.currentPath)}`;
            if (window.location.hash !== expectedHash) {
                window.location.hash = expectedHash;
            }
        } catch (e) {}

        renderBreadcrumbs(data.breadcrumbs || []);
        renderTable(state.items);
        updateSelectionUI();

        if (elements.searchInput) {
            elements.searchInput.value = '';
        }
    }

    function updateNavigationButtons() {
        if (elements.btnBack) {
            elements.btnBack.disabled = state.history.length === 0;
        }
        if (elements.btnForward) {
            elements.btnForward.disabled = state.forwardHistory.length === 0;
        }
        if (elements.btnUp) {
            elements.btnUp.disabled = state.currentPath === '/' || state.currentPath === '';
        }
    }

    function updateToggleHiddenButton() {
        if (!elements.btnToggleHidden) return;
        const eyeSvg = `<svg class="btn-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
        if (state.showHidden) {
            elements.btnToggleHidden.classList.remove('btn-secondary');
            elements.btnToggleHidden.classList.add('btn-primary');
            elements.btnToggleHidden.innerHTML = `${eyeSvg}<span>Hidden (On)</span>`;
        } else {
            elements.btnToggleHidden.classList.remove('btn-primary');
            elements.btnToggleHidden.classList.add('btn-secondary');
            elements.btnToggleHidden.innerHTML = `${eyeSvg}<span>Hidden</span>`;
        }
    }

    function renderBreadcrumbs(crumbs) {
        if (!elements.breadcrumbs) return;
        elements.breadcrumbs.innerHTML = '';

        crumbs.forEach((crumb, idx) => {
            const isLast = idx === crumbs.length - 1;
            const span = document.createElement('span');

            let iconPrefix = '';
            if (idx === 0) {
                iconPrefix = `<svg class="breadcrumb-icon" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>`;
            } else {
                iconPrefix = `<svg class="breadcrumb-icon" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>`;
            }

            if (isLast) {
                span.className = 'breadcrumb-item active';
                span.innerHTML = `${iconPrefix}<span>${escapeHtml(crumb.name)}</span>`;
            } else {
                span.className = 'breadcrumb-item';
                span.innerHTML = `${iconPrefix}<span>${escapeHtml(crumb.name)}</span>`;
                span.addEventListener('click', () => loadDirectory(crumb.path));
            }

            elements.breadcrumbs.appendChild(span);

            if (!isLast) {
                const sep = document.createElement('span');
                sep.className = 'breadcrumb-separator';
                sep.innerHTML = `<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>`;
                elements.breadcrumbs.appendChild(sep);
            }
        });
    }

    function getFileIconSvg(item) {
        if (item.is_dir) {
            return `<svg class="item-icon icon-folder" viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>`;
        }
        
        const ext = (item.extension || (item.name ? item.name.split('.').pop() : '') || '').toLowerCase();
        
        if (item.is_zip || ['zip', 'tar', 'gz', 'bz2', '7z', 'rar', 'tgz'].includes(ext)) {
            return `<svg class="item-icon icon-zip" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>`;
        }
        
        if (['php', 'phtml', 'php7', 'php8'].includes(ext)) {
            return `<svg class="item-icon icon-php" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>`;
        }
        
        if (['html', 'htm', 'shtml'].includes(ext)) {
            return `<svg class="item-icon icon-html" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="10 13 8 15 10 17"/><polyline points="14 13 16 15 14 17"/></svg>`;
        }
        
        if (['css', 'scss', 'sass', 'less'].includes(ext)) {
            return `<svg class="item-icon icon-css" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg>`;
        }
        
        if (['js', 'mjs', 'cjs', 'ts', 'jsx', 'tsx'].includes(ext)) {
            return `<svg class="item-icon icon-js" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M10 18v-3a1 1 0 011-1h1a1 1 0 011 1v3"/></svg>`;
        }
        
        if (['json', 'xml', 'yaml', 'yml', 'ini', 'conf', 'config', 'env', 'htaccess'].includes(ext)) {
            return `<svg class="item-icon icon-config" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><circle cx="12" cy="14" r="2"/></svg>`;
        }
        
        if (['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico', 'bmp', 'avif'].includes(ext)) {
            return `<svg class="item-icon icon-image" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>`;
        }
        
        if (ext === 'pdf') {
            return `<svg class="item-icon icon-pdf" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M9 13h2a1.5 1.5 0 011.5 1.5v0a1.5 1.5 0 01-1.5 1.5H9v-3z"/></svg>`;
        }
        
        if (['sql', 'db', 'sqlite'].includes(ext)) {
            return `<svg class="item-icon icon-sql" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>`;
        }
        
        if (['txt', 'md', 'markdown', 'log'].includes(ext) || item.is_text || item.is_editable) {
            return `<svg class="item-icon icon-text" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/></svg>`;
        }
        
        return `<svg class="item-icon icon-file" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>`;
    }

    function renderTable(items) {
        if (!elements.tableBody) return;
        elements.tableBody.innerHTML = '';

        if (elements.selectAllCheckbox) {
            elements.selectAllCheckbox.checked = false;
            elements.selectAllCheckbox.indeterminate = false;
        }

        if (items.length === 0) {
            elements.tableBody.innerHTML = `
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>
                            <p class="empty-state-title">Direktori ini kosong</p>
                            <p class="empty-state-subtitle">Unggah berkas atau buat folder baru untuk memulai.</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        items.forEach(item => {
            const tr = document.createElement('tr');
            tr.setAttribute('data-path', item.virtual_path);
            if (item.is_hidden) tr.classList.add('is-hidden-item');
            if (state.selectedPaths.has(item.virtual_path)) tr.classList.add('selected');

            const iconSvg = getFileIconSvg(item);
            const badgeProtected = item.is_protected ? `<span class="item-badge-protected">PROTECTED</span>` : '';
            const isChecked = state.selectedPaths.has(item.virtual_path);

            tr.innerHTML = `
                <td style="text-align: center; width: 38px;">
                    <input type="checkbox" class="row-checkbox" data-path="${escapeHtml(item.virtual_path)}" ${isChecked ? 'checked' : ''}>
                </td>
                <td>
                    <div class="item-name-cell" data-path="${escapeHtml(item.virtual_path)}" data-dir="${item.is_dir}">
                        ${iconSvg}
                        <span class="item-title">${escapeHtml(item.name)}</span>
                        ${badgeProtected}
                    </div>
                </td>
                <td class="col-type">${escapeHtml(item.type)}</td>
                <td class="col-size">${escapeHtml(item.size_human)}</td>
                <td class="col-mtime">${escapeHtml(item.mtime_human)}</td>
                <td class="col-perms"><code title="${escapeHtml(item.perms_rwx || '')}">${escapeHtml(item.perms_octal)}</code></td>
                <td class="col-actions">
                    <div class="action-dropdown">
                        <button class="btn-icon btn-action-trigger" title="Action Menu">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                        </button>
                        <div class="dropdown-menu">
                            ${buildActionMenuItems(item)}
                        </div>
                    </div>
                </td>
            `;

            // Row checkbox toggle
            const checkbox = tr.querySelector('.row-checkbox');
            checkbox.addEventListener('change', (e) => {
                e.stopPropagation();
                if (checkbox.checked) {
                    state.selectedPaths.add(item.virtual_path);
                    tr.classList.add('selected');
                } else {
                    state.selectedPaths.delete(item.virtual_path);
                    tr.classList.remove('selected');
                }
                updateSelectionUI();
            });

            // Row click (whitespace or other cells) toggles selection
            tr.addEventListener('click', (e) => {
                if (e.target.closest('.row-checkbox') || e.target.closest('.action-dropdown') || e.target.closest('.item-name-cell')) {
                    return;
                }
                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change'));
            });

            // Open folder / view file / edit file on name click
            const nameCell = tr.querySelector('.item-name-cell');
            nameCell.addEventListener('click', (e) => {
                if (e.target.closest('.row-checkbox')) return;
                if (item.is_dir) {
                    loadDirectory(item.virtual_path);
                } else if (item.is_editable) {
                    openEditorModal(item.virtual_path);
                } else if (item.is_text) {
                    openPreviewModal(item.virtual_path);
                } else {
                    // Binary files toggle selection
                    checkbox.checked = !checkbox.checked;
                    checkbox.dispatchEvent(new Event('change'));
                }
            });

            // Right-click context menu on row
            tr.addEventListener('contextmenu', (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (!state.selectedPaths.has(item.virtual_path)) {
                    state.selectedPaths.clear();
                    state.selectedPaths.add(item.virtual_path);
                    document.querySelectorAll('.file-table tr.selected').forEach(r => r.classList.remove('selected'));
                    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
                    tr.classList.add('selected');
                    checkbox.checked = true;
                    updateSelectionUI();
                }
                showContextMenu(e.clientX, e.clientY, item);
            });

            // Action dropdown toggle
            const trigger = tr.querySelector('.btn-action-trigger');
            const dropdown = tr.querySelector('.dropdown-menu');
            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                const isShown = dropdown.classList.contains('show');
                closeAllDropdowns();
                if (!isShown) dropdown.classList.add('show');
            });

            // Bind dropdown actions
            dropdown.querySelectorAll('[data-action]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    closeAllDropdowns();
                    handleActionClick(btn.getAttribute('data-action'), item);
                });
            });

            elements.tableBody.appendChild(tr);
        });
    }

    function buildActionMenuItems(item) {
        let menu = '';

        if (item.is_dir) {
            menu += `<button class="dropdown-item" data-action="open"><svg class="dropdown-item-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg><span>Buka Folder</span></button>`;
            menu += `<button class="dropdown-item" data-action="download-folder"><svg class="dropdown-item-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg><span>Download (ZIP)</span></button>`;
        } else {
            if (item.is_editable) {
                menu += `<button class="dropdown-item" data-action="edit"><svg class="dropdown-item-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg><span>Edit Berkas</span></button>`;
            }
            if (item.is_text) {
                menu += `<button class="dropdown-item" data-action="preview"><svg class="dropdown-item-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg><span>Pratinjau (View)</span></button>`;
            }
            menu += `<button class="dropdown-item" data-action="download-file"><svg class="dropdown-item-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg><span>Download</span></button>`;
            if (item.is_zip) {
                menu += `<button class="dropdown-item" data-action="extract"><svg class="dropdown-item-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/><line x1="12" y1="11" x2="12" y2="17"/><polyline points="9 14 12 11 15 14"/></svg><span>Ekstrak ZIP</span></button>`;
            }
        }

        menu += `<div class="dropdown-divider"></div>`;

        menu += `<button class="dropdown-item" data-action="compress"><svg class="dropdown-item-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg><span>Kompres ke ZIP</span></button>`;

        if (!item.is_protected) {
            menu += `<button class="dropdown-item" data-action="rename"><svg class="dropdown-item-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg><span>Ganti Nama</span></button>`;
            menu += `<button class="dropdown-item" data-action="copy"><svg class="dropdown-item-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg><span>Salin ke (Copy)</span></button>`;
            menu += `<button class="dropdown-item" data-action="move"><svg class="dropdown-item-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><line x1="20" y1="4" x2="8.12" y2="15.88"/><line x1="14.47" y1="14.48" x2="20" y2="20"/><line x1="8.12" y1="8.12" x2="12" y2="12"/></svg><span>Pindahkan ke (Move)</span></button>`;
            menu += `<button class="dropdown-item" data-action="chmod"><svg class="dropdown-item-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg><span>Hak Akses</span></button>`;
        }

        menu += `<button class="dropdown-item" data-action="info"><svg class="dropdown-item-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg><span>Informasi Berkas</span></button>`;

        if (!item.is_protected) {
            menu += `<div class="dropdown-divider"></div>`;
            menu += `<button class="dropdown-item text-danger" data-action="delete"><svg class="dropdown-item-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg><span>Hapus</span></button>`;
        }

        return menu;
    }

    function handleActionClick(action, item) {
        if (!item && action !== 'refresh' && action !== 'new_file' && action !== 'new_folder' && action !== 'upload') return;

        switch (action) {
            case 'open':
                if (item && item.is_dir) loadDirectory(item.virtual_path);
                break;
            case 'download':
            case 'download-file':
            case 'download-folder':
                if (item.is_dir) {
                    window.location.href = `?action=download_folder&path=${encodeURIComponent(item.virtual_path)}`;
                } else {
                    window.location.href = `?action=download&path=${encodeURIComponent(item.virtual_path)}`;
                }
                break;
            case 'edit':
                openEditorModal(item.virtual_path);
                break;
            case 'preview':
                openPreviewModal(item.virtual_path);
                break;
            case 'extract':
                openExtractModal(item);
                break;
            case 'compress':
                openCompressModal([item.virtual_path]);
                break;
            case 'chmod':
                openChmodModal(item);
                break;
            case 'rename':
                openRenameModal(item);
                break;
            case 'delete':
                openDeleteModal(item);
                break;
            case 'copy':
                openCopyMoveModal(item, 'copy');
                break;
            case 'move':
                openCopyMoveModal(item, 'move');
                break;
            case 'info':
                openInfoModal(item.virtual_path);
                break;
        }
    }

    // --------------------------------------------------------------------------
    // Multi-Select & Selection UI State
    // --------------------------------------------------------------------------
    function handleSelectAll(e) {
        const isChecked = e.target.checked;
        state.selectedPaths.clear();

        document.querySelectorAll('.row-checkbox').forEach(cb => {
            cb.checked = isChecked;
            const path = cb.getAttribute('data-path');
            const tr = cb.closest('tr');
            if (isChecked && path) {
                state.selectedPaths.add(path);
                tr?.classList.add('selected');
            } else {
                tr?.classList.remove('selected');
            }
        });

        updateSelectionUI();
    }

    function updateSelectionUI() {
        const count = state.selectedPaths.size;
        const total = state.items.length;

        // Header Checkbox
        if (elements.selectAllCheckbox) {
            if (count === 0) {
                elements.selectAllCheckbox.checked = false;
                elements.selectAllCheckbox.indeterminate = false;
            } else if (count === total && total > 0) {
                elements.selectAllCheckbox.checked = true;
                elements.selectAllCheckbox.indeterminate = false;
            } else {
                elements.selectAllCheckbox.checked = false;
                elements.selectAllCheckbox.indeterminate = true;
            }
        }

        // Single selected item check
        const singlePath = count === 1 ? Array.from(state.selectedPaths)[0] : null;
        const singleItem = singlePath ? state.items.find(i => i.virtual_path === singlePath) : null;

        // Toolbar Buttons State
        if (elements.btnDownloadSelected) elements.btnDownloadSelected.disabled = (count === 0);
        if (elements.btnCompressSelected) elements.btnCompressSelected.disabled = (count === 0);
        if (elements.btnCopySelected) elements.btnCopySelected.disabled = (count === 0);
        if (elements.btnMoveSelected) elements.btnMoveSelected.disabled = (count === 0);
        if (elements.btnDeleteSelected) elements.btnDeleteSelected.disabled = (count === 0 || (singleItem && singleItem.is_protected));

        if (elements.btnExtractSelected) {
            elements.btnExtractSelected.disabled = !(count === 1 && singleItem && singleItem.is_zip);
        }
        if (elements.btnEditSelected) {
            elements.btnEditSelected.disabled = !(count === 1 && singleItem && (singleItem.is_editable || singleItem.is_text));
        }
        if (elements.btnRenameSelected) {
            elements.btnRenameSelected.disabled = !(count === 1 && singleItem && !singleItem.is_protected);
        }
        if (elements.btnChmodSelected) {
            elements.btnChmodSelected.disabled = !(count === 1 && singleItem && !singleItem.is_protected);
        }

        // Status Bar
        if (elements.statusTotalItems) elements.statusTotalItems.textContent = `${total} item`;
        if (elements.statusSelectedItems) elements.statusSelectedItems.textContent = `${count} item dipilih`;
        if (elements.statusHiddenHint) elements.statusHiddenHint.textContent = `Berkas tersembunyi: ${state.showHidden ? 'Ditampilkan' : 'Disembunyikan'}`;
        if (elements.statusCurrentPathHint) elements.statusCurrentPathHint.textContent = state.currentPath;
    }

    // --------------------------------------------------------------------------
    // Toolbar Selection Action Handlers
    // --------------------------------------------------------------------------
    function handleDownloadSelected() {
        const paths = Array.from(state.selectedPaths);
        if (paths.length === 0) return;
        if (paths.length === 1) {
            const item = state.items.find(i => i.virtual_path === paths[0]);
            if (item && item.is_dir) {
                window.location.href = `?action=download_folder&path=${encodeURIComponent(paths[0])}`;
            } else {
                window.location.href = `?action=download&path=${encodeURIComponent(paths[0])}`;
            }
        } else {
            handleBulkDownload();
        }
    }

    function handleCompressSelected() {
        const paths = Array.from(state.selectedPaths);
        if (paths.length > 0) openCompressModal(paths);
    }

    function handleExtractSelected() {
        const paths = Array.from(state.selectedPaths);
        if (paths.length === 1) {
            const item = state.items.find(i => i.virtual_path === paths[0]);
            if (item && item.is_zip) openExtractModal(item);
        }
    }

    function handleCopySelected() {
        const paths = Array.from(state.selectedPaths);
        if (paths.length === 1) {
            const item = state.items.find(i => i.virtual_path === paths[0]);
            if (item) openCopyMoveModal(item, 'copy');
        } else if (paths.length > 1) {
            openBulkCopyMoveModal('copy');
        }
    }

    function handleMoveSelected() {
        const paths = Array.from(state.selectedPaths);
        if (paths.length === 1) {
            const item = state.items.find(i => i.virtual_path === paths[0]);
            if (item) openCopyMoveModal(item, 'move');
        } else if (paths.length > 1) {
            openBulkCopyMoveModal('move');
        }
    }

    function handleEditSelected() {
        const paths = Array.from(state.selectedPaths);
        if (paths.length === 1) {
            openEditorModal(paths[0]);
        }
    }

    function handleRenameSelected() {
        const paths = Array.from(state.selectedPaths);
        if (paths.length === 1) {
            const item = state.items.find(i => i.virtual_path === paths[0]);
            if (item && !item.is_protected) openRenameModal(item);
        }
    }

    function handleChmodSelected() {
        const paths = Array.from(state.selectedPaths);
        if (paths.length === 1) {
            const item = state.items.find(i => i.virtual_path === paths[0]);
            if (item && !item.is_protected) openChmodModal(item);
        }
    }

    function handleDeleteSelected() {
        const paths = Array.from(state.selectedPaths);
        if (paths.length === 1) {
            const item = state.items.find(i => i.virtual_path === paths[0]);
            if (item && !item.is_protected) openDeleteModal(item);
        } else if (paths.length > 1) {
            openBulkDeleteModal();
        }
    }

    // --------------------------------------------------------------------------
    // Navigation Handlers (Back, Forward, Up)
    // --------------------------------------------------------------------------
    function navigateBack() {
        if (state.history.length > 0) {
            const prev = state.history.pop();
            state.forwardHistory.push(state.currentPath);
            loadDirectory(prev, false);
        }
    }

    function navigateForward() {
        if (state.forwardHistory.length > 0) {
            const next = state.forwardHistory.pop();
            state.history.push(state.currentPath);
            loadDirectory(next, false);
        }
    }

    function navigateUp() {
        if (state.currentPath === '/' || state.currentPath === '') return;
        const parts = state.currentPath.split('/').filter(Boolean);
        parts.pop();
        const upPath = '/' + parts.join('/');
        loadDirectory(upPath);
    }

    function showLoadingState() {
        if (!elements.tableBody) return;
        elements.tableBody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; padding: 24px; color: #64748b;">
                    Memuat berkas...
                </td>
            </tr>
        `;
    }

    function closeAllDropdowns() {
        document.querySelectorAll('.dropdown-menu.show').forEach(el => el.classList.remove('show'));
    }

    function handleSearch(e) {
        const query = (elements.searchInput?.value || '').toLowerCase().trim();
        if (elements.btnSearchClear) {
            elements.btnSearchClear.style.display = query ? 'inline-flex' : 'none';
        }
        if (!query) {
            renderTable(state.items);
            return;
        }

        const filtered = state.items.filter(item => item.name.toLowerCase().includes(query));
        renderTable(filtered);
    }

    function updateSortHeaders() {
        document.querySelectorAll('.file-table th[data-sort]').forEach(th => {
            const field = th.getAttribute('data-sort');
            const arrowSpan = th.querySelector('.sort-arrow');
            if (arrowSpan) arrowSpan.remove();

            if (state.sortField === field) {
                const arrow = document.createElement('span');
                arrow.className = 'sort-arrow';
                arrow.textContent = state.sortOrder === 'asc' ? ' ↑' : ' ↓';
                th.appendChild(arrow);
            }
        });
    }

    // --------------------------------------------------------------------------
    // In-Browser Code Editor Controller
    // --------------------------------------------------------------------------
    function setupEditorEvents() {
        const textarea = elements.editorContent;
        if (!textarea) return;

        // Dirty checking and stats updating
        textarea.addEventListener('input', () => {
            updateEditorStats();
            const isDirty = textarea.value !== state.editorOriginalContent;
            state.editorDirty = isDirty;
            if (elements.editorDirtyBadge) {
                elements.editorDirtyBadge.style.display = isDirty ? 'inline-block' : 'none';
            }
        });

        // Tab Key: Insert 4 spaces without losing focus
        textarea.addEventListener('keydown', (e) => {
            if (e.key === 'Tab') {
                e.preventDefault();
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                textarea.value = textarea.value.substring(0, start) + '    ' + textarea.value.substring(end);
                textarea.selectionStart = textarea.selectionEnd = start + 4;
                textarea.dispatchEvent(new Event('input'));
            }

            // Keyboard Save: Ctrl + S or Cmd + S
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                e.preventDefault();
                handleEditorSave(e);
            }
        });

        // Toggle Word Wrap
        elements.btnEditorWrap?.addEventListener('click', () => {
            const isWrapped = textarea.classList.toggle('is-wrapped');
            elements.btnEditorWrap.classList.toggle('active', isWrapped);
            textarea.setAttribute('wrap', isWrapped ? 'soft' : 'off');
        });

        // Toggle Fullscreen
        elements.btnEditorFullscreen?.addEventListener('click', () => {
            const modalBox = elements.modalEditor?.querySelector('.editor-modal-box');
            if (modalBox) {
                const isFs = modalBox.classList.toggle('is-fullscreen');
                elements.btnEditorFullscreen.classList.toggle('active', isFs);
                elements.btnEditorFullscreen.textContent = isFs ? 'Normal' : 'Layar Penuh';
            }
        });
    }

    function updateEditorStats() {
        const text = elements.editorContent?.value || '';
        const lines = text.length === 0 ? 0 : text.split('\n').length;
        const chars = text.length;
        if (elements.editorStatLines) elements.editorStatLines.textContent = `Baris: ${lines}`;
        if (elements.editorStatChars) elements.editorStatChars.textContent = `Karakter: ${chars}`;
    }

    async function openEditorModal(virtualPath) {
        if (!elements.modalEditor) return;

        if (elements.editorFilePath) elements.editorFilePath.value = virtualPath;
        if (elements.editorFilePathDisplay) elements.editorFilePathDisplay.textContent = virtualPath;
        if (elements.editorModalTitle) elements.editorModalTitle.textContent = `Editor: ${virtualPath.split('/').pop()}`;
        if (elements.editorContent) elements.editorContent.value = 'Memuat isi berkas...';
        if (elements.editorSaveStatus) elements.editorSaveStatus.textContent = '';
        if (elements.editorWarningBox) elements.editorWarningBox.style.display = 'none';
        if (elements.editorDirtyBadge) elements.editorDirtyBadge.style.display = 'none';

        openModal(elements.modalEditor);

        const data = await requestApi(`?action=edit_load&path=${encodeURIComponent(virtualPath)}`);

        if (!data.success) {
            showToast(data.message || 'Gagal membaca berkas.', 'error');
            closeActiveModals();
            return;
        }

        if (elements.editorContent) {
            elements.editorContent.value = data.content;
            state.editorOriginalContent = data.content;
            state.editorDirty = false;
            updateEditorStats();
            elements.editorContent.focus();
        }

        if (data.size > 1024 * 1024 && elements.editorWarningBox) {
            elements.editorWarningBox.style.display = 'block';
            elements.editorWarningBox.textContent = `Perhatian: Berkas ini berukuran relatif besar (${data.size_human}). Penyuntingan berkas besar dapat memakan memori browser.`;
        }
    }

    function handleEditorClose() {
        if (state.editorDirty) {
            const discard = confirm('Anda memiliki perubahan yang belum disimpan. Yakin ingin menutup editor tanpa menyimpan?');
            if (!discard) return;
        }
        state.editorDirty = false;
        const modalBox = elements.modalEditor?.querySelector('.editor-modal-box');
        if (modalBox) modalBox.classList.remove('is-fullscreen');
        if (elements.btnEditorFullscreen) {
            elements.btnEditorFullscreen.classList.remove('active');
            elements.btnEditorFullscreen.textContent = 'Layar Penuh';
        }
        if (elements.modalEditor) elements.modalEditor.classList.remove('show');
    }

    async function handleEditorSave(e) {
        if (e && e.preventDefault) e.preventDefault();

        const path = elements.editorFilePath?.value;
        const content = elements.editorContent?.value ?? '';
        const btnSave = elements.btnSaveEditor;

        if (!path) return;

        if (btnSave) {
            btnSave.disabled = true;
            btnSave.textContent = 'Menyimpan...';
        }
        if (elements.editorSaveStatus) {
            elements.editorSaveStatus.textContent = 'Menyimpan...';
        }

        const formData = new FormData();
        formData.append('csrf_token', state.csrfToken);
        formData.append('path', path);
        formData.append('content', content);

        const data = await requestApi('?action=edit_save', {
            method: 'POST',
            body: formData
        });

        if (btnSave) {
            btnSave.disabled = false;
            btnSave.textContent = 'Simpan Perubahan';
        }

        if (data.success) {
            state.editorDirty = false;
            state.editorOriginalContent = content;
            if (elements.editorDirtyBadge) elements.editorDirtyBadge.style.display = 'none';
            if (elements.editorSaveStatus) {
                elements.editorSaveStatus.innerHTML = '<span style="color: #16a34a; font-weight: 600;">✓ Tersimpan</span>';
                setTimeout(() => { if (elements.editorSaveStatus) elements.editorSaveStatus.textContent = ''; }, 3000);
            }
            showToast(data.message || 'Berkas berhasil disimpan.', 'success');
        } else {
            if (elements.editorSaveStatus) {
                elements.editorSaveStatus.innerHTML = `<span style="color: #dc2626;">✕ ${escapeHtml(data.message)}</span>`;
            }
            showToast(data.message || 'Gagal menyimpan berkas.', 'error');
        }
    }

    // --------------------------------------------------------------------------
    // Admin Settings Controller (Change Username & Password)
    // --------------------------------------------------------------------------
    async function openSettingsModal() {
        const inputUser = document.getElementById('settingsUsername');
        const inputCurPass = document.getElementById('settingsCurrentPassword');
        const inputNewPass = document.getElementById('settingsNewPassword');
        const inputConfPass = document.getElementById('settingsConfirmPassword');
        const alertEl = document.getElementById('settingsAlert');
        const btnSubmit = document.getElementById('btnSubmitSettings');

        if (inputCurPass) inputCurPass.value = '';
        if (inputNewPass) inputNewPass.value = '';
        if (inputConfPass) inputConfPass.value = '';
        if (alertEl) {
            alertEl.style.display = 'none';
            alertEl.className = 'alert';
            alertEl.textContent = '';
        }
        if (btnSubmit) {
            btnSubmit.disabled = false;
            btnSubmit.textContent = 'Simpan Perubahan';
        }

        openModal(elements.modalSettings);

        // Fetch current settings
        const data = await requestApi('?action=get_settings');
        if (data && data.success && inputUser) {
            inputUser.value = data.username || 'admin';
        }
        setTimeout(() => inputCurPass?.focus(), 50);
    }
    window.hfmOpenSettingsModal = openSettingsModal;
    window.openSettingsModal = openSettingsModal;

    async function handleSettingsSubmit(e) {
        e.preventDefault();
        const inputUser = document.getElementById('settingsUsername');
        const inputCurPass = document.getElementById('settingsCurrentPassword');
        const inputNewPass = document.getElementById('settingsNewPassword');
        const inputConfPass = document.getElementById('settingsConfirmPassword');
        const alertEl = document.getElementById('settingsAlert');
        const btnSubmit = document.getElementById('btnSubmitSettings');

        const newUsername = inputUser?.value.trim() || '';
        const currentPassword = inputCurPass?.value || '';
        const newPassword = inputNewPass?.value || '';
        const confirmPassword = inputConfPass?.value || '';

        const showAlert = (msg, isSuccess = false) => {
            if (!alertEl) return;
            alertEl.className = isSuccess ? 'alert alert-success' : 'alert alert-danger';
            alertEl.textContent = msg;
            alertEl.style.display = 'block';
        };

        if (!currentPassword) {
            showAlert('Password saat ini wajib diisi untuk verifikasi keamanan.');
            return;
        }

        if (newUsername.length < 3) {
            showAlert('Username harus minimal 3 karakter.');
            return;
        }

        if (newPassword !== '') {
            if (newPassword.length < 5) {
                showAlert('Password baru harus minimal 5 karakter.');
                return;
            }
            if (newPassword !== confirmPassword) {
                showAlert('Konfirmasi password baru tidak cocok!');
                return;
            }
        }

        if (btnSubmit) {
            btnSubmit.disabled = true;
            btnSubmit.textContent = 'Menyimpan...';
        }

        const formData = new FormData();
        formData.append('csrf_token', state.csrfToken);
        formData.append('current_password', currentPassword);
        formData.append('new_username', newUsername);
        formData.append('new_password', newPassword);

        const data = await requestApi('?action=update_settings', {
            method: 'POST',
            body: formData
        });

        if (btnSubmit) {
            btnSubmit.disabled = false;
            btnSubmit.textContent = 'Simpan Perubahan';
        }

        if (data && data.success) {
            showToast(data.message || 'Pengaturan akun berhasil disimpan.', 'success');
            if (elements.headerUserBadge) {
                elements.headerUserBadge.textContent = `User: ${data.username}`;
            }
            closeActiveModals();
        } else {
            showAlert(data?.message || 'Gagal memperbarui pengaturan.');
            showToast(data?.message || 'Gagal memperbarui pengaturan.', 'error');
        }
    }

    // --------------------------------------------------------------------------
    // Permissions (CHMOD) Matrix Controller
    // --------------------------------------------------------------------------
    function setupChmodEvents() {
        const checkboxes = document.querySelectorAll('.chmod-check');
        checkboxes.forEach(cb => {
            cb.addEventListener('change', () => syncChmodFromCheckboxes());
        });

        elements.chmodOctalInput?.addEventListener('input', (e) => {
            const val = e.target.value.trim();
            if (/^0?[0-7]{3}$/.test(val)) {
                syncChmodFromOctal(val);
            }
        });
    }

    function openChmodModal(item) {
        document.getElementById('chmodItemPath').value = item.virtual_path;
        document.getElementById('chmodItemName').textContent = item.name;

        const octal = item.perms_octal || '0644';
        syncChmodFromOctal(octal);

        openModal(elements.modalChmod);
    }

    function syncChmodFromOctal(octalStr) {
        const clean = octalStr.padStart(4, '0').slice(-3);
        const u = parseInt(clean[0], 8) || 0;
        const g = parseInt(clean[1], 8) || 0;
        const w = parseInt(clean[2], 8) || 0;

        document.getElementById('chmod_u_r').checked = (u & 4) !== 0;
        document.getElementById('chmod_u_w').checked = (u & 2) !== 0;
        document.getElementById('chmod_u_x').checked = (u & 1) !== 0;

        document.getElementById('chmod_g_r').checked = (g & 4) !== 0;
        document.getElementById('chmod_g_w').checked = (g & 2) !== 0;
        document.getElementById('chmod_g_x').checked = (g & 1) !== 0;

        document.getElementById('chmod_w_r').checked = (w & 4) !== 0;
        document.getElementById('chmod_w_w').checked = (w & 2) !== 0;
        document.getElementById('chmod_w_x').checked = (w & 1) !== 0;

        if (elements.chmodOctalInput) elements.chmodOctalInput.value = '0' + clean;
        if (elements.chmodRwxDisplay) elements.chmodRwxDisplay.textContent = computeRwxString(u, g, w);
    }

    function syncChmodFromCheckboxes() {
        const u = (document.getElementById('chmod_u_r')?.checked ? 4 : 0) +
                  (document.getElementById('chmod_u_w')?.checked ? 2 : 0) +
                  (document.getElementById('chmod_u_x')?.checked ? 1 : 0);

        const g = (document.getElementById('chmod_g_r')?.checked ? 4 : 0) +
                  (document.getElementById('chmod_g_w')?.checked ? 2 : 0) +
                  (document.getElementById('chmod_g_x')?.checked ? 1 : 0);

        const w = (document.getElementById('chmod_w_r')?.checked ? 4 : 0) +
                  (document.getElementById('chmod_w_w')?.checked ? 2 : 0) +
                  (document.getElementById('chmod_w_x')?.checked ? 1 : 0);

        const octal = `0${u}${g}${w}`;
        if (elements.chmodOctalInput) elements.chmodOctalInput.value = octal;
        if (elements.chmodRwxDisplay) elements.chmodRwxDisplay.textContent = computeRwxString(u, g, w);
    }

    function computeRwxString(u, g, w) {
        const toRwx = (val) => ((val & 4 ? 'r' : '-') + (val & 2 ? 'w' : '-') + (val & 1 ? 'x' : '-'));
        return '-' + toRwx(u) + toRwx(g) + toRwx(w);
    }

    async function handleChmod(e) {
        e.preventDefault();
        const path = document.getElementById('chmodItemPath').value;
        const mode = elements.chmodOctalInput?.value.trim() || '0755';
        const btnSubmit = document.getElementById('btnSubmitChmod');

        if (btnSubmit) {
            btnSubmit.disabled = true;
            btnSubmit.textContent = 'Memproses...';
        }

        const formData = new FormData();
        formData.append('csrf_token', state.csrfToken);
        formData.append('path', path);
        formData.append('mode', mode);

        const data = await requestApi('?action=chmod', { method: 'POST', body: formData });

        if (btnSubmit) {
            btnSubmit.disabled = false;
            btnSubmit.textContent = 'Ubah Hak Akses';
        }

        if (data.success) {
            showToast(data.message, 'success');
            closeActiveModals();
            loadDirectory(state.currentPath, false);
        } else {
            showToast(data.message || 'Gagal mengubah hak akses.', 'error');
        }
    }

    // --------------------------------------------------------------------------
    // ZIP Compress Controller (Single / Multi-Item)
    // --------------------------------------------------------------------------
    async function openCompressModal(paths) {
        if (!paths || paths.length === 0) {
            showToast('Pilih setidaknya satu berkas atau folder untuk dikompres.', 'warning');
            return;
        }

        state.compressItems = paths;
        const listContainer = document.getElementById('compressItemsList');
        const zipNameInput = document.getElementById('compressZipNameInput');
        const destInput = document.getElementById('compressDestInput');
        const hintEl = document.getElementById('compressCurrentPathHint');

        if (listContainer) {
            listContainer.innerHTML = paths.map(p => `<div>📦 ${escapeHtml(p.split('/').pop() || p)} <span style="color: #94a3b8; font-size: 11px;">(${escapeHtml(p)})</span></div>`).join('');
        }

        // Default ZIP filename
        let defaultName = 'archive.zip';
        if (paths.length === 1) {
            const base = paths[0].split('/').filter(Boolean).pop() || 'archive';
            defaultName = base.replace(/\.[^/.]+$/, '') + '.zip';
        }
        if (zipNameInput) zipNameInput.value = defaultName;
        if (destInput) destInput.value = state.currentPath;
        if (hintEl) hintEl.textContent = state.currentPath;

        state.selectedDestination = state.currentPath;
        await renderDestinationTree('compressTreeContainer', state.currentPath);

        openModal(elements.modalCompress);
        setTimeout(() => zipNameInput?.focus(), 50);
    }

    async function handleCompress(e) {
        e.preventDefault();
        const paths = state.compressItems;
        const zipName = document.getElementById('compressZipNameInput')?.value.trim() || 'archive.zip';
        const destInput = document.getElementById('compressDestInput');
        const destination = (destInput ? destInput.value.trim() : '') || state.currentPath;
        const btnSubmit = document.getElementById('btnStartCompress');

        if (!paths || paths.length === 0) return;

        if (btnSubmit) {
            btnSubmit.disabled = true;
            btnSubmit.textContent = 'Mengompres...';
        }

        const formData = new FormData();
        formData.append('csrf_token', state.csrfToken);
        formData.append('items', JSON.stringify(paths));
        formData.append('zip_name', zipName);
        formData.append('destination', destination);

        const data = await requestApi('?action=compress', {
            method: 'POST',
            body: formData
        });

        if (btnSubmit) {
            btnSubmit.disabled = false;
            btnSubmit.textContent = 'Kompres Sekarang';
        }

        if (data.success) {
            showToast(data.message, 'success');
            closeActiveModals();
            loadDirectory(state.currentPath, false);
        } else {
            showToast(data.message || 'Kompresi arsip gagal.', 'error');
        }
    }

    // --------------------------------------------------------------------------
    // Bulk Actions (Delete, Copy, Move, Download)
    // --------------------------------------------------------------------------
    function openBulkDeleteModal() {
        const paths = Array.from(state.selectedPaths);
        if (paths.length === 0) return;

        document.getElementById('deleteItemPath').value = JSON.stringify(paths);
        const titleEl = document.getElementById('deleteModalTitle');
        const promptEl = document.getElementById('deleteItemPrompt');
        const listEl = document.getElementById('deleteMultiItemsContainer');
        const warningBox = document.getElementById('deleteWarningBox');

        if (titleEl) titleEl.textContent = `Hapus Sekaligus (${paths.length} Item)`;
        if (promptEl) promptEl.textContent = `Apakah Anda yakin ingin menghapus permanen ${paths.length} item berikut?`;
        if (listEl) {
            listEl.style.display = 'block';
            listEl.innerHTML = paths.map(p => `<div>🗑️ ${escapeHtml(p.split('/').pop() || p)}</div>`).join('');
        }
        if (warningBox) warningBox.style.display = 'none';

        openModal(elements.modalDelete);
    }

    async function openBulkCopyMoveModal(mode) {
        const paths = Array.from(state.selectedPaths);
        if (paths.length === 0) return;

        const titleEl = document.getElementById('copyMoveModalTitle');
        const btnSubmit = document.getElementById('btnSubmitCopyMove');
        const modeInput = document.getElementById('copyMoveMode');
        const sourceInput = document.getElementById('copyMoveSourcePath');
        const listEl = document.getElementById('copyMoveMultiItemsContainer');

        modeInput.value = mode;
        sourceInput.value = JSON.stringify(paths);

        if (mode === 'copy') {
            titleEl.textContent = `Salin ${paths.length} Item Terpilih ke:`;
            btnSubmit.textContent = 'Salin Semua ke Sini';
        } else {
            titleEl.textContent = `Pindahkan ${paths.length} Item Terpilih ke:`;
            btnSubmit.textContent = 'Pindahkan Semua ke Sini';
        }

        if (listEl) {
            listEl.style.display = 'block';
            listEl.innerHTML = paths.map(p => `<div>📋 ${escapeHtml(p.split('/').pop() || p)}</div>`).join('');
        }

        const destInput = document.getElementById('copyMoveDestInput');
        const hintEl = document.getElementById('copyMoveCurrentPathHint');
        if (destInput) destInput.value = state.currentPath;
        if (hintEl) hintEl.textContent = state.currentPath;

        state.selectedDestination = state.currentPath;
        await renderDestinationTree('copyMoveTreeContainer', state.currentPath);
        openModal(elements.modalCopyMove);
    }

    function handleBulkDownload() {
        const paths = Array.from(state.selectedPaths);
        if (paths.length === 0) return;
        window.location.href = `?action=bulk_download&paths=${encodeURIComponent(JSON.stringify(paths))}`;
    }

    // --------------------------------------------------------------------------
    // Desktop Context Menu Setup
    // --------------------------------------------------------------------------
    function setupContextMenuEvents() {
        const menu = elements.contextMenu;
        if (!menu) return;

        // Context menu for background/empty area of table container
        const tableContainer = document.querySelector('.file-table-container');
        tableContainer?.addEventListener('contextmenu', (e) => {
            if (e.target.closest('tbody tr')) return; // Already handled by row
            e.preventDefault();
            showContextMenu(e.clientX, e.clientY, null);
        });

        // Context menu item actions
        menu.querySelectorAll('.context-menu-item').forEach(itemEl => {
            itemEl.addEventListener('click', (e) => {
                e.stopPropagation();
                hideContextMenu();
                const action = itemEl.getAttribute('data-action');
                const target = state.contextTarget;

                if (!target) {
                    // Empty area actions
                    if (action === 'new_file') document.getElementById('btnNewFile')?.click();
                    else if (action === 'new_folder') document.getElementById('btnNewFolder')?.click();
                    else if (action === 'upload') document.getElementById('btnOpenUpload')?.click();
                    else if (action === 'refresh') loadDirectory(state.currentPath, false);
                    return;
                }

                // Item actions
                handleActionClick(action, target);
            });
        });
    }

    function showContextMenu(x, y, item) {
        const menu = elements.contextMenu;
        if (!menu) return;

        state.contextTarget = item;

        // Items for Single Item vs Empty Space
        const isItem = item !== null;
        const isDir = isItem && item.is_dir;
        const isText = isItem && item.is_text;
        const isEditable = isItem && item.is_editable;
        const isZip = isItem && item.is_zip;
        const isProtected = isItem && item.is_protected;

        // Toggle visibility of context options based on target
        const setDisp = (id, show) => {
            const el = document.getElementById(id);
            if (el) el.style.display = show ? 'flex' : 'none';
        };

        // File/Folder items
        setDisp('ctxOpen', isItem && isDir);
        setDisp('ctxDownload', isItem);
        setDisp('ctxPreview', isItem && !isDir && isText);
        setDisp('ctxEdit', isItem && !isDir && isEditable);
        setDisp('ctxExtract', isItem && !isDir && isZip);
        setDisp('ctxDiv1', isItem);
        setDisp('ctxCompress', isItem);
        setDisp('ctxCopy', isItem && !isProtected);
        setDisp('ctxMove', isItem && !isProtected);
        setDisp('ctxRename', isItem && !isProtected);
        setDisp('ctxChmod', isItem && !isProtected);
        setDisp('ctxInfo', isItem);
        setDisp('ctxDiv2', isItem && !isProtected);
        setDisp('ctxDelete', isItem && !isProtected);

        // Blank space items
        setDisp('ctxNewFile', !isItem);
        setDisp('ctxNewFolder', !isItem);
        setDisp('ctxUpload', !isItem);
        setDisp('ctxRefresh', !isItem);

        menu.style.display = 'block';

        // Clamp to viewport
        const winWidth = window.innerWidth;
        const winHeight = window.innerHeight;
        const menuWidth = menu.offsetWidth || 210;
        const menuHeight = menu.offsetHeight || 280;

        let posX = x;
        let posY = y;

        if (posX + menuWidth > winWidth) posX = Math.max(10, winWidth - menuWidth - 12);
        if (posY + menuHeight > winHeight) posY = Math.max(10, winHeight - menuHeight - 12);

        menu.style.left = `${posX}px`;
        menu.style.top = `${posY}px`;
    }

    function hideContextMenu() {
        if (elements.contextMenu) {
            elements.contextMenu.style.display = 'none';
        }
    }

    // --------------------------------------------------------------------------
    // Single Item Operations (New File, New Folder, Rename, Delete, Copy, Move, Extract)
    // --------------------------------------------------------------------------
    async function handleNewFile(e) {
        e.preventDefault();
        const inputName = document.getElementById('inputFileName');
        const inputContent = document.getElementById('inputFileContent');
        const fileName = inputName?.value.trim();
        const content = inputContent?.value || '';
        if (!fileName) return;

        const formData = new FormData();
        formData.append('csrf_token', state.csrfToken);
        formData.append('target_dir', state.currentPath);
        formData.append('file_name', fileName);
        formData.append('content', content);

        const data = await requestApi('?action=create_file', { method: 'POST', body: formData });

        if (data.success) {
            showToast(data.message, 'success');
            closeActiveModals();
            if (inputName) inputName.value = '';
            if (inputContent) inputContent.value = '';
            loadDirectory(state.currentPath, false);
        } else {
            showToast(data.message, 'error');
        }
    }

    async function handleNewFolder(e) {
        e.preventDefault();
        const input = document.getElementById('inputFolderName');
        const name = input?.value.trim();
        if (!name) return;

        const formData = new FormData();
        formData.append('csrf_token', state.csrfToken);
        formData.append('target_dir', state.currentPath);
        formData.append('folder_name', name);

        const data = await requestApi('?action=mkdir', { method: 'POST', body: formData });

        if (data.success) {
            showToast(data.message, 'success');
            closeActiveModals();
            input.value = '';
            loadDirectory(state.currentPath, false);
        } else {
            showToast(data.message, 'error');
        }
    }

    function openRenameModal(item) {
        document.getElementById('renameItemPath').value = item.virtual_path;
        const inputName = document.getElementById('renameNewName');
        inputName.value = item.name;
        openModal(elements.modalRename);
        setTimeout(() => inputName.select(), 50);
    }

    async function handleRename(e) {
        e.preventDefault();
        const path = document.getElementById('renameItemPath').value;
        const newName = document.getElementById('renameNewName').value.trim();
        if (!newName) return;

        const formData = new FormData();
        formData.append('csrf_token', state.csrfToken);
        formData.append('path', path);
        formData.append('new_name', newName);

        const data = await requestApi('?action=rename', { method: 'POST', body: formData });

        if (data.success) {
            showToast(data.message, 'success');
            closeActiveModals();
            loadDirectory(state.currentPath, false);
        } else {
            showToast(data.message, 'error');
        }
    }

    async function openDeleteModal(item) {
        document.getElementById('deleteItemPath').value = item.virtual_path;
        const titleEl = document.getElementById('deleteModalTitle');
        const promptEl = document.getElementById('deleteItemPrompt');
        const listEl = document.getElementById('deleteMultiItemsContainer');
        const warningBox = document.getElementById('deleteWarningBox');

        if (titleEl) titleEl.textContent = 'Konfirmasi Hapus';
        if (promptEl) promptEl.innerHTML = `Apakah Anda yakin ingin menghapus <strong>${escapeHtml(item.name)}</strong>?`;
        if (listEl) listEl.style.display = 'none';

        if (item.is_dir) {
            warningBox.style.display = 'block';
            warningBox.textContent = 'Menghitung isi folder...';
            const stats = await requestApi(`?action=folder_stats&path=${encodeURIComponent(item.virtual_path)}`);
            if (stats && stats.total_items !== undefined) {
                warningBox.textContent = `Peringatan: Folder ini berisi ${stats.total_items} file dan folder (${stats.size_human}). Seluruh isinya akan dihapus permanen!`;
            } else {
                warningBox.textContent = 'Peringatan: Seluruh isi folder ini akan dihapus permanen!';
            }
        } else {
            warningBox.style.display = 'none';
        }

        openModal(elements.modalDelete);
    }

    async function handleDelete(e) {
        e.preventDefault();
        const pathRaw = document.getElementById('deleteItemPath').value;

        // Cek jika bulk delete (JSON array)
        if (pathRaw.startsWith('[')) {
            let paths = [];
            try { paths = JSON.parse(pathRaw); } catch (err) {}
            const formData = new FormData();
            formData.append('csrf_token', state.csrfToken);
            formData.append('paths', JSON.stringify(paths));

            const data = await requestApi('?action=bulk_delete', { method: 'POST', body: formData });
            if (data.success) {
                showToast(data.message, 'success');
                closeActiveModals();
                loadDirectory(state.currentPath, false);
            } else {
                showToast(data.message || 'Gagal menghapus berkas.', 'error');
            }
            return;
        }

        // Single delete
        const formData = new FormData();
        formData.append('csrf_token', state.csrfToken);
        formData.append('path', pathRaw);

        const data = await requestApi('?action=delete', { method: 'POST', body: formData });

        if (data.success) {
            showToast(data.message, 'success');
            closeActiveModals();
            loadDirectory(state.currentPath, false);
        } else {
            showToast(data.message, 'error');
        }
    }

    async function openCopyMoveModal(item, mode) {
        const titleEl = document.getElementById('copyMoveModalTitle');
        const btnSubmit = document.getElementById('btnSubmitCopyMove');
        const modeInput = document.getElementById('copyMoveMode');
        const sourceInput = document.getElementById('copyMoveSourcePath');
        const listEl = document.getElementById('copyMoveMultiItemsContainer');

        modeInput.value = mode;
        sourceInput.value = item.virtual_path;
        if (listEl) listEl.style.display = 'none';

        if (mode === 'copy') {
            titleEl.textContent = `Salin "${item.name}" ke Direktori:`;
            btnSubmit.textContent = 'Salin ke Sini';
        } else {
            titleEl.textContent = `Pindahkan "${item.name}" ke Direktori:`;
            btnSubmit.textContent = 'Pindahkan ke Sini';
        }

        const destInput = document.getElementById('copyMoveDestInput');
        const hintEl = document.getElementById('copyMoveCurrentPathHint');
        if (destInput) destInput.value = state.currentPath;
        if (hintEl) hintEl.textContent = state.currentPath;

        state.selectedDestination = state.currentPath;
        await renderDestinationTree('copyMoveTreeContainer', state.currentPath);
        openModal(elements.modalCopyMove);
    }

    async function handleCopyMove(e) {
        e.preventDefault();
        const mode = document.getElementById('copyMoveMode').value;
        const sourceRaw = document.getElementById('copyMoveSourcePath').value;
        const destInput = document.getElementById('copyMoveDestInput');
        const dest = (destInput ? destInput.value.trim() : '') || state.currentPath;

        // Cek jika bulk copy/move (JSON array)
        if (sourceRaw.startsWith('[')) {
            let paths = [];
            try { paths = JSON.parse(sourceRaw); } catch (err) {}
            const action = mode === 'copy' ? 'bulk_copy' : 'bulk_move';

            const formData = new FormData();
            formData.append('csrf_token', state.csrfToken);
            formData.append('paths', JSON.stringify(paths));
            formData.append('destination', dest);

            const data = await requestApi(`?action=${action}`, { method: 'POST', body: formData });
            if (data.success) {
                showToast(data.message, 'success');
                closeActiveModals();
                loadDirectory(state.currentPath, false);
            } else {
                showToast(data.message || 'Gagal memproses berkas.', 'error');
            }
            return;
        }

        // Single copy/move
        const action = mode === 'copy' ? 'copy' : 'move';
        const formData = new FormData();
        formData.append('csrf_token', state.csrfToken);
        formData.append('source', sourceRaw);
        formData.append('destination', dest);

        const data = await requestApi(`?action=${action}`, { method: 'POST', body: formData });

        if (data.success) {
            showToast(data.message, 'success');
            closeActiveModals();
            loadDirectory(state.currentPath, false);
        } else {
            showToast(data.message, 'error');
        }
    }

    async function openExtractModal(item) {
        document.getElementById('extractZipName').textContent = item.name;
        document.getElementById('extractZipPath').value = item.virtual_path;

        const destInput = document.getElementById('extractDestInput');
        const hintEl = document.getElementById('extractCurrentPathHint');
        if (destInput) destInput.value = state.currentPath;
        if (hintEl) hintEl.textContent = state.currentPath;

        state.selectedDestination = state.currentPath;
        await renderDestinationTree('extractTreeContainer', state.currentPath);

        openModal(elements.modalExtract);
    }

    async function handleExtract(e) {
        e.preventDefault();
        const zipPath = document.getElementById('extractZipPath').value;
        const destInput = document.getElementById('extractDestInput');
        const destPath = (destInput ? destInput.value.trim() : '') || state.currentPath;
        const conflict = document.querySelector('input[name="conflict_strategy"]:checked')?.value || 'overwrite';
        const btnSubmit = document.getElementById('btnStartExtract');

        if (btnSubmit) {
            btnSubmit.disabled = true;
            btnSubmit.textContent = 'Mengekstrak...';
        }

        try {
            const formData = new FormData();
            formData.append('csrf_token', state.csrfToken);
            formData.append('zip_path', zipPath);
            formData.append('dest_path', destPath);
            formData.append('conflict', conflict);

            const data = await requestApi('?action=extract', {
                method: 'POST',
                body: formData
            });

            if (data.success) {
                showToast(data.message, 'success');
                closeActiveModals();
                loadDirectory(state.currentPath, false);
            } else {
                showToast(data.message || 'Ekstraksi gagal.', 'error');
            }
        } catch (err) {
            showToast('Terjadi kesalahan jaringan saat ekstraksi.', 'error');
        } finally {
            if (btnSubmit) {
                btnSubmit.disabled = false;
                btnSubmit.textContent = 'Ekstrak';
            }
        }
    }

    async function openInfoModal(virtualPath) {
        openModal(elements.modalInfo);
        const contentDiv = document.getElementById('infoModalContent');
        contentDiv.innerHTML = '<p style="color: #64748b;">Memuat informasi...</p>';

        const data = await requestApi(`?action=info&path=${encodeURIComponent(virtualPath)}`);

        if (!data.success) {
            contentDiv.innerHTML = `<p class="alert alert-danger">${escapeHtml(data.message)}</p>`;
            return;
        }

        let html = '<table class="info-table">';
        html += `<tr><td>Nama</td><td><strong>${escapeHtml(data.name)}</strong></td></tr>`;
        html += `<tr><td>Tipe</td><td>${escapeHtml(data.type)}</td></tr>`;
        html += `<tr><td>Lokasi</td><td><code>${escapeHtml(data.virtual_path)}</code></td></tr>`;

        if (data.is_dir) {
            html += `<tr><td>Jumlah Item</td><td>${data.items_count} berkas & folder</td></tr>`;
            html += `<tr><td>Total Ukuran</td><td>${escapeHtml(data.size_human)}</td></tr>`;
        } else {
            html += `<tr><td>Ukuran</td><td>${escapeHtml(data.size_human)} (${data.size_raw.toLocaleString()} bytes)</td></tr>`;
            html += `<tr><td>MIME Type</td><td><code>${escapeHtml(data.mime || '-')}</code></td></tr>`;
        }

        html += `<tr><td>Waktu Modifikasi</td><td>${escapeHtml(data.modified)}</td></tr>`;
        html += `<tr><td>Hak Akses (Permissions)</td><td><code>${escapeHtml(data.permissions)}</code> (${escapeHtml(data.octal || '')})</td></tr>`;
        html += '</table>';

        contentDiv.innerHTML = html;
    }

    async function openPreviewModal(virtualPath) {
        openModal(elements.modalPreview);
        const titleEl = document.getElementById('previewModalTitle');
        const codeEl = document.getElementById('previewModalCode');
        titleEl.textContent = 'Memuat pratinjau...';
        codeEl.textContent = '';

        const data = await requestApi(`?action=preview&path=${encodeURIComponent(virtualPath)}`);

        if (!data.success) {
            titleEl.textContent = 'Gagal Memuat';
            codeEl.textContent = data.message || 'File tidak dapat dipratinjau.';
            return;
        }

        titleEl.textContent = `${data.name} (${data.size_human})`;
        // Teks disajikan murni tanpa interpretasi HTML/PHP
        codeEl.textContent = data.content;
    }

    // --------------------------------------------------------------------------
    // Folder Tree Selector for Extract / Copy / Move / Compress
    // --------------------------------------------------------------------------
    async function renderDestinationTree(containerId, activePath) {
        const container = document.getElementById(containerId);
        if (!container) return;
        container.innerHTML = '<div style="padding: 6px; color: #64748b;">Memuat folder tree...</div>';

        const data = await requestApi('?action=folder_tree');
        if (!data.success && !data.tree) {
            container.innerHTML = '<div style="padding: 6px; color: #b91c1c;">Gagal memuat folder.</div>';
            return;
        }

        container.innerHTML = '';
        const rootNode = {
            name: 'Root (/)',
            path: '/',
            children: data.tree || []
        };

        const ul = document.createElement('div');
        buildTreeNodeDom(rootNode, ul, activePath, (selectedPath) => {
            state.selectedDestination = selectedPath;
            const destInput = container.closest('form')?.querySelector('.selected-dest-input');
            if (destInput) destInput.value = selectedPath;
        });

        container.appendChild(ul);
    }

    function buildTreeNodeDom(node, parentEl, activePath, onSelect) {
        const itemDiv = document.createElement('div');
        itemDiv.className = 'tree-node' + (node.path === activePath ? ' selected' : '');
        itemDiv.innerHTML = `<span>📁</span> <span>${escapeHtml(node.name)}</span>`;

        itemDiv.addEventListener('click', (e) => {
            e.stopPropagation();
            parentEl.closest('.tree-container')?.querySelectorAll('.tree-node').forEach(el => el.classList.remove('selected'));
            itemDiv.classList.add('selected');
            onSelect(node.path);
        });

        parentEl.appendChild(itemDiv);

        if (node.children && node.children.length > 0) {
            const childrenWrap = document.createElement('div');
            childrenWrap.className = 'tree-children';
            node.children.forEach(child => {
                buildTreeNodeDom(child, childrenWrap, activePath, onSelect);
            });
            parentEl.appendChild(childrenWrap);
        }
    }

    // --------------------------------------------------------------------------
    // Upload Engine (Real Per-File Progress, Queue, Drag-Drop, Multi-Format)
    // --------------------------------------------------------------------------
    let uploadQueue = [];
    let isUploading = false;
    let uploadModalPhase = 'idle'; // 'idle' | 'uploading' | 'finished'
    let uploadHasSuccess = false;

    function setupUploadEvents() {
        const dropZone = document.getElementById('uploadDropArea');
        const fileInput = document.getElementById('uploadFileInput');
        const fileInputFallback = document.getElementById('uploadFileInputFallback');
        const btnBrowse = document.getElementById('btnBrowseFiles');
        const btnClear = document.getElementById('btnClearUploadQueue');
        const btnSubmit = document.getElementById('btnStartUpload');

        // Klik tombol browse utama di drop area
        btnBrowse?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (uploadModalPhase === 'finished') {
                clearUploadQueue();
            }
            if (fileInput) {
                fileInput.value = '';
                fileInput.click();
            }
        });

        // Klik pada seluruh kotak dropzone (kecuali tombol di dalamnya)
        dropZone?.addEventListener('click', (e) => {
            if (e.target.closest('#btnBrowseFiles') || e.target.closest('input')) return;
            if (uploadModalPhase === 'finished') {
                clearUploadQueue();
            }
            if (fileInput) {
                fileInput.value = '';
                fileInput.click();
            }
        });

        // Event change input berkas
        fileInput?.addEventListener('change', () => {
            if (fileInput.files && fileInput.files.length > 0) {
                addFilesToQueue(fileInput.files);
                fileInput.value = '';
            }
        });

        // Event change input berkas fallback sistem
        fileInputFallback?.addEventListener('change', () => {
            if (fileInputFallback.files && fileInputFallback.files.length > 0) {
                addFilesToQueue(fileInputFallback.files);
                fileInputFallback.value = '';
            }
        });

        // Tombol bersihkan antrean
        btnClear?.addEventListener('click', () => {
            if (!isUploading) {
                clearUploadQueue();
            }
        });

        // Tombol submit upload / tombol Selesai
        btnSubmit?.addEventListener('click', handleUploadButtonClick);

        // Drag & Drop pada kotak dropzone
        if (dropZone) {
            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropZone.classList.add('dragover');
                });
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropZone.classList.remove('dragover');
                });
            });

            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                    addFilesToQueue(e.dataTransfer.files);
                }
            });
        }

        // Drag & Drop berkas langsung ke jendela File Manager
        window.addEventListener('dragover', (e) => e.preventDefault());
        window.addEventListener('drop', (e) => {
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                if (!e.target.closest('#uploadDropArea')) {
                    e.preventDefault();
                    const display = document.getElementById('uploadTargetDirDisplay');
                    if (display) display.textContent = state.currentPath;
                    openModal(elements.modalUpload);
                    addFilesToQueue(e.dataTransfer.files);
                }
            }
        });
    }

    function handleUploadButtonClick() {
        if (uploadModalPhase === 'finished') {
            // Tombol "Selesai" diklik: tutup modal, bersihkan antrean, dan refresh direktori aktif
            closeActiveModals();
            return;
        }

        if (uploadModalPhase === 'idle') {
            startUploadProcess();
        }
    }

    function addFilesToQueue(fileList) {
        if (!fileList || fileList.length === 0) return;

        // Jika upload batch sebelumnya sudah berstatus selesai, bersihkan antrean lama untuk batch baru
        if (uploadModalPhase === 'finished') {
            clearUploadQueue();
        }

        for (let i = 0; i < fileList.length; i++) {
            const file = fileList[i];
            const already = uploadQueue.some(q => q.name === file.name && q.size === file.size);
            if (!already) {
                uploadQueue.push({
                    id: uploadQueue.length,
                    file: file,
                    name: file.name,
                    size: file.size,
                    status: 'waiting', // waiting | uploading | completed | failed
                    progress: 0,
                    loadedBytes: 0,
                    error: ''
                });
            }
        }

        renderUploadQueue();
    }

    function renderUploadQueue() {
        const queueContainer = document.getElementById('uploadQueueContainer');
        const queueTitle = document.getElementById('uploadQueueTitle');
        const queueList = document.getElementById('uploadQueueList');
        const btnSubmit = document.getElementById('btnStartUpload');
        const btnClear = document.getElementById('btnClearUploadQueue');
        const summaryBar = document.getElementById('uploadSummaryBar');

        if (!queueContainer || !queueList) return;

        if (uploadQueue.length === 0) {
            queueContainer.style.display = 'none';
            if (summaryBar) summaryBar.style.display = 'none';
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.setAttribute('disabled', 'disabled');
                btnSubmit.className = 'btn btn-primary';
                btnSubmit.textContent = 'Mulai Upload';
                btnSubmit.style.pointerEvents = 'auto';
                btnSubmit.style.cursor = 'not-allowed';
                btnSubmit.removeAttribute('title');
            }
            if (btnClear) btnClear.style.display = 'none';
            return;
        }

        queueContainer.style.display = 'block';
        if (btnClear) btnClear.style.display = isUploading ? 'none' : 'inline-block';
        if (btnSubmit && !isUploading && uploadModalPhase === 'idle') {
            btnSubmit.disabled = false;
            btnSubmit.removeAttribute('disabled');
            btnSubmit.className = 'btn btn-primary';
            btnSubmit.textContent = `Mulai Upload (${uploadQueue.length} Berkas)`;
            btnSubmit.style.pointerEvents = 'auto';
            btnSubmit.style.cursor = 'pointer';
        }

        const totalBytes = uploadQueue.reduce((acc, cur) => acc + cur.size, 0);
        if (queueTitle) {
            queueTitle.textContent = `Daftar Berkas Terpilih (${uploadQueue.length} berkas - ${formatBytes(totalBytes)})`;
        }

        queueList.innerHTML = '';
        uploadQueue.forEach((item, index) => {
            const card = document.createElement('div');
            card.className = `upload-file-card ${item.status === 'uploading' ? 'is-uploading' : ''} ${item.status === 'completed' ? 'is-completed' : ''} ${item.status === 'failed' ? 'is-failed' : ''}`;
            card.id = `uploadCard-${index}`;

            const ext = item.name.split('.').pop().toLowerCase();
            let iconText = '📄';
            if (ext === 'zip' || ext === 'tar' || ext === 'gz') iconText = '📦';
            else if (['php', 'js', 'html', 'css', 'json', 'sql', 'xml'].includes(ext)) iconText = '💻';
            else if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico'].includes(ext)) iconText = '🖼️';
            else if (['pdf', 'doc', 'docx', 'txt', 'md'].includes(ext)) iconText = '📑';

            let badgeHtml = '';
            if (item.status === 'waiting') {
                badgeHtml = `<span class="upload-badge badge-waiting" id="uploadBadge-${index}">Waiting...</span>`;
            } else if (item.status === 'uploading') {
                badgeHtml = `<span class="upload-badge badge-uploading" id="uploadBadge-${index}">${item.progress}%</span>`;
            } else if (item.status === 'completed') {
                badgeHtml = `<span class="upload-badge badge-success" id="uploadBadge-${index}">100% ✓</span>`;
            } else if (item.status === 'failed') {
                badgeHtml = `<span class="upload-badge badge-error" id="uploadBadge-${index}">✕ Failed</span>`;
            }

            card.innerHTML = `
                <div class="upload-card-header">
                    <div class="upload-card-name-group">
                        <span class="upload-card-icon">${iconText}</span>
                        <span class="upload-card-name" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</span>
                        <span class="upload-card-size">(${formatBytes(item.size)})</span>
                    </div>
                    <div class="upload-card-status">
                        ${badgeHtml}
                    </div>
                </div>
                <div class="upload-card-progress-track">
                    <div class="upload-card-progress-bar" id="uploadProgressBar-${index}" style="width: ${item.progress}%;"></div>
                </div>
                <div class="upload-card-footer">
                    <span class="upload-card-bytes" id="uploadProgressBytes-${index}">${formatBytes(item.loadedBytes)} / ${formatBytes(item.size)}</span>
                    <span class="upload-card-pct" id="uploadProgressPct-${index}">${item.progress}%</span>
                </div>
                <div class="upload-card-error" id="uploadCardError-${index}" style="display: ${item.error ? 'block' : 'none'};">
                    ${escapeHtml(item.error)}
                </div>
            `;

            queueList.appendChild(card);
        });
    }

    function clearUploadQueue() {
        uploadQueue = [];
        uploadModalPhase = 'idle';
        uploadHasSuccess = false;
        isUploading = false;

        const fileInput = document.getElementById('uploadFileInput');
        const fileInputFallback = document.getElementById('uploadFileInputFallback');
        const queueContainer = document.getElementById('uploadQueueContainer');
        const queueList = document.getElementById('uploadQueueList');
        const btnSubmit = document.getElementById('btnStartUpload');
        const btnClear = document.getElementById('btnClearUploadQueue');
        const btnCancel = document.getElementById('btnCancelUpload');
        const summaryBar = document.getElementById('uploadSummaryBar');

        if (fileInput) fileInput.value = '';
        if (fileInputFallback) fileInputFallback.value = '';
        if (queueList) queueList.innerHTML = '';
        if (queueContainer) queueContainer.style.display = 'none';
        if (summaryBar) summaryBar.style.display = 'none';

        if (btnSubmit) {
            btnSubmit.disabled = true;
            btnSubmit.setAttribute('disabled', 'disabled');
            btnSubmit.className = 'btn btn-primary';
            btnSubmit.textContent = 'Mulai Upload';
            btnSubmit.style.pointerEvents = 'auto';
            btnSubmit.style.cursor = 'not-allowed';
            btnSubmit.removeAttribute('title');
        }
        if (btnCancel) {
            btnCancel.disabled = false;
            btnCancel.removeAttribute('disabled');
            btnCancel.textContent = 'Tutup';
            btnCancel.style.pointerEvents = 'auto';
            btnCancel.style.cursor = 'pointer';
        }
        if (btnClear) btnClear.style.display = 'none';
    }

    async function startUploadProcess() {
        if (isUploading || uploadQueue.length === 0) return;

        isUploading = true;
        uploadModalPhase = 'uploading';
        const btnSubmit = document.getElementById('btnStartUpload');
        const btnClear = document.getElementById('btnClearUploadQueue');
        const btnCancel = document.getElementById('btnCancelUpload');
        const summaryBar = document.getElementById('uploadSummaryBar');
        const summaryCounter = document.getElementById('uploadSummaryCounter');
        const summaryStatus = document.getElementById('uploadSummaryStatus');

        if (btnSubmit) {
            btnSubmit.disabled = true;
            btnSubmit.setAttribute('disabled', 'disabled');
            btnSubmit.style.pointerEvents = 'none';
            btnSubmit.style.cursor = 'not-allowed';
            btnSubmit.innerHTML = '⏳ Mengunggah...';
        }
        if (btnCancel) {
            btnCancel.disabled = true;
            btnCancel.setAttribute('disabled', 'disabled');
            btnCancel.style.pointerEvents = 'none';
            btnCancel.style.cursor = 'not-allowed';
        }
        if (btnClear) btnClear.style.display = 'none';
        if (summaryBar) summaryBar.style.display = 'flex';

        let completedCount = 0;
        let failedCount = 0;
        const totalFiles = uploadQueue.length;

        if (summaryCounter) {
            summaryCounter.textContent = `0 / ${totalFiles} completed`;
        }
        if (summaryStatus) {
            summaryStatus.textContent = 'Memulai proses upload...';
        }

        // Upload secara sekuensial (satu per satu) untuk progress real
        for (let i = 0; i < uploadQueue.length; i++) {
            const item = uploadQueue[i];
            if (item.status === 'completed') {
                completedCount++;
                continue;
            }

            item.status = 'uploading';
            updateCardToUploading(i);
            if (summaryStatus) {
                summaryStatus.textContent = `Mengunggah: ${item.name}...`;
            }

            const res = await uploadSingleFileXHR(item, i);

            if (res.success) {
                item.status = 'completed';
                item.progress = 100;
                item.loadedBytes = item.size;
                completedCount++;
                uploadHasSuccess = true;
                updateCardToCompleted(i);
            } else {
                item.status = 'failed';
                item.error = res.message || 'Upload gagal.';
                failedCount++;
                updateCardToFailed(i, item.error);
            }

            if (summaryCounter) {
                summaryCounter.textContent = `${completedCount} / ${totalFiles} completed`;
            }
        }

        isUploading = false;
        uploadModalPhase = 'finished';

        // --------------------------------------------------------------------------
        // Status Selesai: Aktifkan Tombol "Selesai" dan Tombol "Tutup"
        // --------------------------------------------------------------------------
        if (btnSubmit) {
            btnSubmit.disabled = false;
            btnSubmit.removeAttribute('disabled');
            btnSubmit.style.pointerEvents = 'auto';
            btnSubmit.style.cursor = 'pointer';
            btnSubmit.className = failedCount === 0 ? 'btn btn-success' : 'btn btn-primary';
            btnSubmit.textContent = failedCount === 0 ? '✓ Selesai' : 'Selesai (Ada Gagal)';
            btnSubmit.title = 'Klik untuk menutup dan menyegarkan tampilan berkas';
        }
        if (btnCancel) {
            btnCancel.disabled = false;
            btnCancel.removeAttribute('disabled');
            btnCancel.style.pointerEvents = 'auto';
            btnCancel.style.cursor = 'pointer';
            btnCancel.textContent = 'Tutup';
        }

        if (failedCount === 0) {
            showToast(`✓ Upload complete: Seluruh ${completedCount} file berhasil diunggah.`, 'success');
            if (summaryStatus) {
                summaryStatus.innerHTML = `<span style="color: #16a34a; font-weight: 600;">✓ Upload complete (${completedCount} file)</span>`;
            }
        } else {
            showToast(`${completedCount} berhasil, ${failedCount} file gagal diunggah.`, 'warning');
            if (summaryStatus) {
                summaryStatus.innerHTML = `<span style="color: #dc2626; font-weight: 600;">✕ Upload selesai: ${completedCount} berhasil, ${failedCount} gagal</span>`;
            }
        }

        // Refresh direktori aktif (CURRENT_PATH) agar file yang baru saja diupload langsung muncul di background
        loadDirectory(state.currentPath, false);
    }

    async function loadUploadLimitsDiagnostic() {
        const data = await requestApi('?action=upload_limits');
        if (data && data.success && data.limits) {
            const l = data.limits;
            const elUpload = document.getElementById('lblUploadMaxFilesize');
            const elPost = document.getElementById('lblPostMaxSize');
            const elMem = document.getElementById('lblMemoryLimit');
            const elStatus = document.getElementById('lblUploadLimitStatus');
            if (elUpload) elUpload.textContent = l.upload_max_filesize;
            if (elPost) elPost.textContent = l.post_max_size;
            if (elMem) elMem.textContent = l.memory_limit;
            if (elStatus) {
                if (l.can_upload_101mb) {
                    elStatus.innerHTML = '<span style="color: #16a34a; font-weight: 600;">✓ Mendukung berkas besar (&ge; 100 MB)</span>';
                } else {
                    elStatus.innerHTML = `<span style="color: #ea580c; font-weight: 600;" title="${escapeHtml(l.warning || '')}">⚠️ Batas server: maks ${l.effective_limit_human}</span>`;
                }
            }
        }
    }

    function uploadSingleFileXHR(item, index) {
        return new Promise((resolve) => {
            const formData = new FormData();
            formData.append('csrf_token', state.csrfToken);
            formData.append('target_dir', state.currentPath);
            formData.append('file', item.file);

            const xhr = new XMLHttpRequest();
            // Sertakan target_dir dan action di URL sebagai fallback jika payload POST ditolak oleh server
            const uploadUrl = `?action=upload&target_dir=${encodeURIComponent(state.currentPath)}`;
            xhr.open('POST', uploadUrl, true);

            // Set Header Keamanan & Integritas
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('X-CSRF-Token', state.csrfToken);
            xhr.setRequestHeader('X-Target-Dir', state.currentPath);

            const bar = document.getElementById(`uploadProgressBar-${index}`);
            const pctEl = document.getElementById(`uploadProgressPct-${index}`);
            const bytesEl = document.getElementById(`uploadProgressBytes-${index}`);
            const badge = document.getElementById(`uploadBadge-${index}`);

            // REAL transfer progress event
            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable && item.status === 'uploading') {
                    item.loadedBytes = e.loaded;
                    if (e.loaded < e.total) {
                        const pct = Math.min(99, Math.round((e.loaded / e.total) * 100));
                        item.progress = pct;
                        if (bar) bar.style.width = pct + '%';
                        if (pctEl) pctEl.textContent = pct + '%';
                        if (bytesEl) bytesEl.textContent = `${formatBytes(e.loaded)} / ${formatBytes(e.total)}`;
                        if (badge) badge.textContent = pct + '%';
                    } else {
                        // 100% data terkirim ke server, menunggu validasi dan penulisan disk oleh server
                        item.progress = 100;
                        if (bar) bar.style.width = '100%';
                        if (pctEl) pctEl.textContent = '100%';
                        if (bytesEl) bytesEl.textContent = `${formatBytes(e.total)} / ${formatBytes(e.total)} (Memproses di server...)`;
                        if (badge) {
                            badge.className = 'upload-badge badge-uploading';
                            badge.textContent = 'Menyimpan...';
                        }
                    }
                }
            };

            xhr.onload = async () => {
                if (xhr.status === 401) {
                    showToast('Sesi Anda telah kedaluwarsa. Mengalihkan ke login...', 'error');
                    setTimeout(() => window.location.reload(), 1200);
                    resolve({ success: false, message: 'Sesi kedaluwarsa.' });
                    return;
                }

                let res = null;
                try {
                    res = JSON.parse(xhr.responseText);
                } catch (err) {
                    // Respons bukan format JSON murni (mungkin HTML error / notice server)
                }

                if (res && typeof res === 'object') {
                    if (res.success) {
                        resolve({ success: true, data: res });
                    } else {
                        resolve({ success: false, message: res.message || 'Server menolak berkas.', data: res });
                    }
                    return;
                }

                // Jika respons bukan JSON, cek apakah file sebenarnya sudah tersimpan di server (Requirement 5)
                if (item.loadedBytes >= item.size) {
                    try {
                        const checkUrl = `?action=check_file&dir=${encodeURIComponent(state.currentPath)}&name=${encodeURIComponent(item.name)}&size=${item.size}`;
                        const checkRes = await requestApi(checkUrl);
                        if (checkRes && checkRes.exists && checkRes.matches_size) {
                            resolve({
                                success: true,
                                verified_on_server: true,
                                message: `Berkas '${item.name}' terverifikasi telah berhasil tersimpan di server (${checkRes.size_human}).`
                            });
                            return;
                        }
                    } catch (checkErr) {}
                }

                // Ekstraksi pesan error yang ramah dan informatif tanpa membocorkan path sensitif
                let safeMsg = '';
                if (xhr.status === 413) {
                    safeMsg = `Ukuran berkas (${formatBytes(item.size)}) melebihi batas upload server (HTTP 413 Payload Too Large).`;
                } else if (xhr.status === 500) {
                    safeMsg = 'Server mengalami kesalahan internal saat memproses berkas (HTTP 500).';
                } else if (xhr.status === 504 || xhr.status === 408) {
                    safeMsg = 'Batas waktu server habis saat memproses berkas besar (Timeout).';
                } else {
                    const text = xhr.responseText || '';
                    const stripped = text.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
                    if (stripped.includes('POST Content-Length') || stripped.includes('exceeds the limit')) {
                        safeMsg = `Ukuran berkas (${formatBytes(item.size)}) melebihi batas post_max_size konfigurasi server.`;
                    } else if (stripped.length > 0 && stripped.length < 250) {
                        safeMsg = stripped;
                    } else {
                        safeMsg = `Server mengembalikan respons tidak terduga (HTTP ${xhr.status || 'Error'}).`;
                    }
                }

                resolve({ success: false, message: safeMsg });
            };

            xhr.onerror = () => {
                resolve({ success: false, message: 'Koneksi jaringan terputus saat mengunggah berkas.' });
            };

            xhr.onabort = () => {
                resolve({ success: false, message: 'Proses upload dibatalkan.' });
            };

            xhr.send(formData);
        });
    }

    function updateCardToUploading(index) {
        const card = document.getElementById(`uploadCard-${index}`);
        const badge = document.getElementById(`uploadBadge-${index}`);
        if (card) {
            card.classList.remove('is-completed', 'is-failed');
            card.classList.add('is-uploading');
        }
        if (badge) {
            badge.className = 'upload-badge badge-uploading';
            badge.textContent = '0%';
        }
    }

    function updateCardToCompleted(index) {
        const item = uploadQueue[index];
        const card = document.getElementById(`uploadCard-${index}`);
        const badge = document.getElementById(`uploadBadge-${index}`);
        const bar = document.getElementById(`uploadProgressBar-${index}`);
        const pctEl = document.getElementById(`uploadProgressPct-${index}`);
        const bytesEl = document.getElementById(`uploadProgressBytes-${index}`);

        if (card) {
            card.classList.remove('is-uploading');
            card.classList.add('is-completed');
        }
        if (badge) {
            badge.className = 'upload-badge badge-success';
            badge.textContent = '100% ✓';
        }
        if (bar) bar.style.width = '100%';
        if (pctEl) pctEl.textContent = '100%';
        if (bytesEl && item) bytesEl.textContent = `${formatBytes(item.size)} / ${formatBytes(item.size)}`;
    }

    function updateCardToFailed(index, errorMsg) {
        const card = document.getElementById(`uploadCard-${index}`);
        const badge = document.getElementById(`uploadBadge-${index}`);
        const errEl = document.getElementById(`uploadCardError-${index}`);

        if (card) {
            card.classList.remove('is-uploading');
            card.classList.add('is-failed');
        }
        if (badge) {
            badge.className = 'upload-badge badge-error';
            badge.textContent = '✕ Failed';
        }
        if (errEl) {
            errEl.style.display = 'block';
            errEl.textContent = errorMsg;
        }
    }

    // --------------------------------------------------------------------------
    // UI Helpers & Utilities
    // --------------------------------------------------------------------------
    function openModal(modal) {
        if (!modal) return;
        closeActiveModals();
        modal.classList.add('show');
    }

    function closeActiveModals() {
        if (isUploading) {
            showToast('Unggahan sedang berlangsung. Harap tunggu hingga selesai.', 'warning');
            return;
        }

        const isUploadModalOpen = elements.modalUpload && elements.modalUpload.classList.contains('show');
        const shouldRefresh = uploadHasSuccess || uploadQueue.some(item => item.status === 'completed');

        document.querySelectorAll('.modal-backdrop.show').forEach(m => m.classList.remove('show'));

        const modalBox = elements.modalEditor?.querySelector('.editor-modal-box');
        if (modalBox) modalBox.classList.remove('is-fullscreen');
        if (elements.btnEditorFullscreen) {
            elements.btnEditorFullscreen.classList.remove('active');
            elements.btnEditorFullscreen.textContent = 'Layar Penuh';
        }

        if (isUploadModalOpen) {
            if (shouldRefresh) {
                loadDirectory(state.currentPath, false);
            }
            if (uploadModalPhase === 'finished' || uploadQueue.length > 0) {
                clearUploadQueue();
            }
        }
    }

    function showToast(message, type = 'success') {
        if (!elements.toastContainer) return;
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        let iconSvg = '';
        if (type === 'success') {
            iconSvg = `<svg class="toast-svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`;
        } else if (type === 'error') {
            iconSvg = `<svg class="toast-svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>`;
        } else if (type === 'warning') {
            iconSvg = `<svg class="toast-svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`;
        } else {
            iconSvg = `<svg class="toast-svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>`;
        }

        toast.innerHTML = `<span class="toast-icon-wrap">${iconSvg}</span><span class="toast-msg">${escapeHtml(message)}</span>`;

        elements.toastContainer.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('toast-hiding');
            setTimeout(() => toast.remove(), 250);
        }, 3600);
    }

    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0 || !bytes) return '0 B';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    async function requestApi(url, options = {}) {
        try {
            const res = await fetch(url, options);
            if (res.status === 401) {
                showToast('Sesi Anda telah kedaluwarsa. Mengalihkan ke login...', 'error');
                setTimeout(() => window.location.reload(), 1200);
                return { success: false, message: 'Session expired' };
            }
            return await res.json();
        } catch (err) {
            return { success: false, message: 'Terjadi kesalahan koneksi server.' };
        }
    }

})();
