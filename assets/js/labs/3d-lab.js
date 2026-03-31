(function () {
    'use strict';

    var cfg = window.KND_3D_LAB || {};
    var ep = cfg.endpoints || {};
    var state = { mode: 'image', file: null, selectedRecent: null, pollTimer: null, currentJobId: null, glbBlobUrl: null, viewerErrorHandler: null, uploadedGlbFile: null };

    var el = {
        form: document.getElementById('labs-3d-form'),
        modeSelect: document.getElementById('l3d-mode-select'),
        modeInput: document.getElementById('l3d-mode'),
        promptWrap: document.getElementById('l3d-prompt-wrap'),
        negativeWrap: document.getElementById('l3d-negative-wrap'),
        uploadWrap: document.getElementById('l3d-upload-wrap'),
        recentWrap: document.getElementById('l3d-recent-wrap'),
        recentGrid: document.getElementById('l3d-recent-grid'),
        dropzone: document.getElementById('l3d-dropzone'),
        fileInput: document.getElementById('l3d-file'),
        dropzoneContent: document.getElementById('l3d-dropzone-content'),
        previewWrap: document.getElementById('l3d-preview-wrap'),
        preview: document.getElementById('l3d-preview'),
        removeImg: document.getElementById('l3d-remove-img'),
        sourceId: document.getElementById('l3d-source-id'),
        sourceType: document.getElementById('l3d-source-type'),
        submit: document.getElementById('l3d-submit'),
        placeholder: document.getElementById('l3d-placeholder'),
        viewerWrap: document.getElementById('l3d-viewer-wrap'),
        modelViewer: document.getElementById('l3d-model-viewer'),
        resultActions: document.getElementById('l3d-result-actions'),
        downloadBtn: document.getElementById('l3d-download'),
        viewModelBtn: document.getElementById('l3d-view-model'),
        statusPanel: document.getElementById('l3d-status-panel'),
        statusText: document.getElementById('l3d-status-text'),
        errorEl: document.getElementById('l3d-error'),
        balance: document.getElementById('l3d-balance'),
        historyPlaceholder: document.getElementById('l3d-history-placeholder'),
        historyList: document.getElementById('l3d-history-list'),
        recentCreationsGrid: document.getElementById('l3d-recent-creations-grid'),
        viewerImageWrap: document.getElementById('l3d-image-preview-wrap'),
        viewerImage: document.getElementById('l3d-viewer-image'),
        viewImageBtn: document.getElementById('l3d-view-image'),
    };

    function toast(msg, type) {
        if (window.kndToast) window.kndToast(msg, type || 'info');
        else if (type === 'error') alert(msg);
    }

    var emptyEl = document.getElementById('l3d-placeholder-empty');
    var loadingEl = document.getElementById('l3d-placeholder-loading');
    var placeholderStatusEl = document.getElementById('l3d-placeholder-status-text');

    function updateStepper(step) {
        var dots = document.querySelectorAll('#l3d-placeholder-loading .labs-stepper-dot');
        var steps = ['queued', 'picked', 'generating', 'done'];
        dots.forEach(function (d, i) {
            d.classList.remove('active', 'current');
            if (steps[i] === step) d.classList.add('current');
            else if (steps.indexOf(step) > i) d.classList.add('active');
        });
    }

    function setStatus(s, text) {
        if (el.statusPanel) el.statusPanel.style.display = s ? 'block' : 'none';
        if (el.statusText) el.statusText.textContent = text || (s ? 'Processing...' : '');
        if (el.placeholder) el.placeholder.style.display = 'block';
        if (s && el.viewerWrap) el.viewerWrap.style.display = 'none';
        if (emptyEl) emptyEl.classList.toggle('d-none', !!s);
        if (loadingEl) loadingEl.classList.toggle('d-none', !s);
        if (placeholderStatusEl && s) placeholderStatusEl.textContent = text || 'Processing...';
        if (s) {
            var step = (text || '').toLowerCase().indexOf('queued') >= 0 ? 'queued' : ((text || '').toLowerCase().indexOf('processing') >= 0 ? 'generating' : 'picked');
            updateStepper(step);
        }
    }

    function showError(msg) {
        if (el.errorEl) { el.errorEl.textContent = msg || 'Error'; el.errorEl.style.display = 'block'; }
    }

    function clearError() {
        if (el.errorEl) el.errorEl.style.display = 'none';
    }

    function toggleModeUI() {
        var m = state.mode;
        if (m === 'text') m = 'image';
        if (el.promptWrap) el.promptWrap.style.display = (m === 'image') ? 'none' : 'block';
        if (el.negativeWrap) el.negativeWrap.style.display = (m === 'image') ? 'none' : 'block';
        if (el.uploadWrap) el.uploadWrap.style.display = (m === 'image' || m === 'text_image') ? 'block' : 'none';
        if (el.recentWrap) el.recentWrap.style.display = (m === 'recent') ? 'block' : 'none';
        if (m === 'recent') loadRecentForPick();
        updateViewImageButton();
    }

    function setFile(file) {
        if (!file || !file.type) return;
        var isImage = file.type.startsWith('image/');
        var isGlb = file.type === 'model/gltf-binary' || (file.name && file.name.toLowerCase().endsWith('.glb'));
        if (!isImage && !isGlb) return;
        state.file = isImage ? file : null;
        state.uploadedGlbFile = isGlb ? file : null;
        state.selectedRecent = null;
        if (isGlb) {
            if (el.previewWrap && el.dropzoneContent) {
                el.previewWrap.style.display = 'block';
                el.dropzoneContent.style.display = 'none';
                if (el.preview) el.preview.style.display = 'none';
                var glbLabel = el.previewWrap.querySelector('.l3d-file-label');
                if (!glbLabel) {
                    glbLabel = document.createElement('span');
                    glbLabel.className = 'l3d-file-label text-white-50 small d-block mb-2';
                    el.previewWrap.insertBefore(glbLabel, el.previewWrap.firstChild);
                }
                glbLabel.textContent = file.name + ' (' + (file.size / 1024).toFixed(0) + ' KB)';
                glbLabel.style.display = 'block';
            }
            showGlbInViewer(file);
        } else {
            if (el.preview && el.previewWrap && el.dropzoneContent) {
                el.preview.style.display = 'block';
                var glbLabel = el.previewWrap.querySelector('.l3d-file-label');
                if (glbLabel) glbLabel.style.display = 'none';
                el.preview.onerror = function () { el.preview.alt = 'Preview failed'; };
                var reader = new FileReader();
                reader.onload = function () {
                    el.preview.onerror = null;
                    el.preview.src = reader.result;
                    el.preview.alt = 'Preview';
                    el.previewWrap.style.display = 'block';
                    el.dropzoneContent.style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        }
        if (el.sourceId) el.sourceId.value = '';
        updateViewImageButton();
    }

    function showGlbInViewer(file) {
        if (!file || !el.modelViewer || !el.viewerWrap) return;
        var url = URL.createObjectURL(file);
        if (state.glbBlobUrl) URL.revokeObjectURL(state.glbBlobUrl);
        state.glbBlobUrl = url;
        if (el.placeholder) el.placeholder.style.display = 'none';
        el.viewerWrap.style.display = 'block';
        if (el.viewerImageWrap) { el.viewerImageWrap.classList.add('d-none'); el.viewerImageWrap.classList.remove('d-flex'); }
        if (el.modelViewer) { el.modelViewer.style.display = 'block'; el.modelViewer.setAttribute('src', url); }
        if (el.resultActions) el.resultActions.style.display = 'none';
        el.viewerWrap.querySelectorAll('.l3d-viewer-err').forEach(function (n) { n.remove(); });
    }

    function resetFile() {
        state.file = null;
        state.uploadedGlbFile = null;
        if (state.glbBlobUrl) { URL.revokeObjectURL(state.glbBlobUrl); state.glbBlobUrl = null; }
        if (el.fileInput) el.fileInput.value = '';
        if (el.preview) { el.preview.src = ''; el.preview.alt = 'Preview'; el.preview.style.display = 'block'; }
        var glbLabel = el.previewWrap && el.previewWrap.querySelector('.l3d-file-label');
        if (glbLabel) glbLabel.style.display = 'none';
        if (el.previewWrap) el.previewWrap.style.display = 'none';
        if (el.dropzoneContent) el.dropzoneContent.style.display = 'block';
        updateViewImageButton();
    }

    function selectRecent(id, previewUrl) {
        state.selectedRecent = { id: id, preview_url: previewUrl || null };
        state.file = null;
        resetFile();
        if (el.sourceId) el.sourceId.value = String(id);
        if (el.sourceType) el.sourceType.value = '3d_lab';
        updateViewImageButton();
    }

    function canSubmit() {
        if (state.mode === 'text') return false;
        if (state.mode === 'text_image') {
            var p = document.getElementById('l3d-prompt');
            if (p && !p.value.trim()) return false;
            return !!state.file;
        }
        if (state.mode === 'image') return !!state.file;
        if (state.mode === 'recent') return !!state.selectedRecent;
        return false;
    }

    function canViewImage() {
        if (state.file) return true;
        if (state.uploadedGlbFile) return true;
        if (state.selectedRecent && state.selectedRecent.preview_url) return true;
        return false;
    }

    function getImageUrlForViewer() {
        if (state.uploadedGlbFile) return null;
        if (state.file && el.preview && el.preview.src) return el.preview.src;
        if (state.selectedRecent && state.selectedRecent.preview_url) {
            var base = (typeof window !== 'undefined' && window.location && window.location.origin) ? window.location.origin : '';
            var url = state.selectedRecent.preview_url;
            return url.indexOf('/') === 0 ? base + url : url;
        }
        return null;
    }

    function updateViewImageButton() {
        if (el.viewImageBtn) {
            el.viewImageBtn.style.display = canViewImage() ? 'inline-block' : 'none';
        }
    }

    function preloadSourceJobImage() {
        var params = new URLSearchParams(window.location.search || '');
        var sourceJobId = params.get('source_job_id');
        if (!sourceJobId || !el.fileInput) return;

        fetch('/api/labs/image.php?job_id=' + encodeURIComponent(sourceJobId) + '&t=' + Date.now(), { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) throw new Error('Could not load source image');
                return r.blob();
            })
            .then(function (blob) {
                var mime = (blob && blob.type && blob.type.indexOf('image/') === 0) ? blob.type : 'image/png';
                var f = new File([blob], 'source-' + sourceJobId + '.png', { type: mime });
                var dt = new DataTransfer();
                dt.items.add(f);
                el.fileInput.files = dt.files;
                state.mode = 'image';
                if (el.modeSelect) el.modeSelect.value = 'image';
                if (el.modeInput) el.modeInput.value = 'image';
                toggleModeUI();
                setFile(f);
            })
            .catch(function () {});
    }

    function showImageInViewer() {
        if (state.uploadedGlbFile) { showGlbInViewer(state.uploadedGlbFile); return; }
        var url = getImageUrlForViewer();
        if (!url || !el.viewerWrap || !el.viewerImageWrap || !el.viewerImage) return;
        if (el.placeholder) el.placeholder.style.display = 'none';
        el.viewerWrap.style.display = 'block';
        el.viewerImage.src = url;
        el.viewerImageWrap.classList.remove('d-none');
        el.viewerImageWrap.classList.add('d-flex');
        if (el.modelViewer) { el.modelViewer.style.display = 'none'; el.modelViewer.removeAttribute('src'); }
        if (el.resultActions) el.resultActions.style.display = 'none';
        el.viewerWrap.querySelectorAll('.l3d-viewer-err').forEach(function (n) { n.remove(); });
    }

    function loadRecentForPick() {
        if (!el.recentGrid) return;
        el.recentGrid.innerHTML = '<p class="text-white-50 small mb-0"><i class="fas fa-spinner fa-spin me-1"></i>Loading...</p>';
        fetch((ep.history || '/api/labs/3d-lab/history.php') + '?limit=12', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var jobs = (res.data && res.data.jobs) ? res.data.jobs : [];
                jobs = jobs.filter(function (j) { return j.status === 'completed' && (j.preview_url || j.glb_url); });
                if (!jobs.length) {
                    el.recentGrid.innerHTML = '<p class="text-white-50 small mb-0">No completed creations yet.</p>';
                    return;
                }
                var base = (typeof window !== 'undefined' && window.location && window.location.origin) ? window.location.origin : '';
                var html = jobs.map(function (j) {
                    var sel = state.selectedRecent && state.selectedRecent.id === j.id ? ' selected' : '';
                    var prevSrc = j.preview_url ? (j.preview_url.indexOf('/') === 0 ? base + j.preview_url : j.preview_url) : '';
                    var fullPreview = j.preview_url ? (j.preview_url.indexOf('/') === 0 ? base + j.preview_url : j.preview_url) : '';
                    var dataPreview = fullPreview ? (' data-preview-url="' + fullPreview.replace(/"/g, '&quot;') + '"') : '';
                    return '<div class="col-4 col-md-3"><div class="labs-3d-recent-card p-1' + sel + '" data-id="' + j.id + '"' + dataPreview + ' style="cursor:pointer; aspect-ratio:1;">' +
                        (prevSrc ? '<img src="' + prevSrc + '" alt="" class="w-100 h-100" style="object-fit:cover;" loading="lazy">' : '<div class="w-100 h-100 bg-dark d-flex align-items-center justify-content-center"><i class="fas fa-cube text-white-50"></i></div>') +
                        '</div><small class="text-white-50 d-block text-truncate">' + (j.title || '') + '</small></div>';
                }).join('');
                el.recentGrid.innerHTML = html;
                el.recentGrid.querySelectorAll('.labs-3d-recent-card').forEach(function (n) {
                    n.addEventListener('click', function () {
                        var id = parseInt(n.getAttribute('data-id'), 10);
                        var prevUrl = n.getAttribute('data-preview-url') || '';
                        selectRecent(id, prevUrl);
                        el.recentGrid.querySelectorAll('.labs-3d-recent-card').forEach(function (x) { x.classList.remove('selected'); });
                        n.classList.add('selected');
                    });
                });
            })
            .catch(function () {
                el.recentGrid.innerHTML = '<p class="text-white-50 small mb-0">Could not load.</p>';
            });
    }

    function loadHistory() {
        fetch((ep.history || '/api/labs/3d-lab/history.php') + '?limit=8', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var jobs = (res.data && res.data.jobs) ? res.data.jobs : [];
                if (el.historyPlaceholder) el.historyPlaceholder.style.display = 'none';
                if (el.historyList) {
                    el.historyList.style.display = jobs.length ? 'block' : 'none';
                    el.historyList.innerHTML = jobs.length ? jobs.slice(0, 5).map(function (j) {
                        return '<li class="mb-2"><a href="#" class="text-white-50 small l3d-open-job" data-id="' + j.public_id + '">' + (j.title || j.public_id) + '</a></li>';
                    }).join('') : '<li class="text-white-50 small">No creations yet</li>';
                }
                if (el.recentCreationsGrid) {
                    if (!jobs.length) {
                        el.recentCreationsGrid.innerHTML = '<p class="knd-muted small mb-0">No 3D creations yet.</p>';
                    } else {
                        var base = (typeof window !== 'undefined' && window.location && window.location.origin) ? window.location.origin : '';
                        el.recentCreationsGrid.innerHTML = jobs.map(function (j) {
                            var prevSrc = j.preview_url ? (j.preview_url.indexOf('/') === 0 ? base + j.preview_url : j.preview_url) : '';
                            var prev = prevSrc ? '<img src="' + prevSrc + '" alt="" class="w-100 h-100" style="object-fit:cover;" loading="lazy">' : '<div class="w-100 h-100 d-flex align-items-center justify-content-center"><i class="fas fa-cube fa-2x text-white-50"></i></div>';
                            var dlHref = j.glb_url ? (base + (j.glb_url.indexOf('/') === 0 ? j.glb_url : '/' + j.glb_url)) : '';
                            var dlAttrs = dlHref ? ' href="' + dlHref + '" download="3d-lab-' + (j.public_id || '') + '.glb"' : ' class="btn btn-sm btn-success disabled" aria-disabled="true"';
                            var dlBtn = dlHref ? '<a ' + dlAttrs + ' class="btn btn-sm btn-outline-success l3d-dl-job"><i class="fas fa-download me-1"></i>Download GLB</a>' : '<span class="btn btn-sm btn-outline-secondary disabled">Download</span>';
                            return '<div class="knd-showcase-card" data-job-id="' + j.public_id + '"><div class="knd-showcase-card__img" style="aspect-ratio:1;">' + prev + '</div><div class="knd-showcase-card__body p-2"><div class="small text-white-50 text-truncate">' + (j.title || '') + '</div><div class="d-flex flex-wrap gap-1 mt-2"><a href="#" class="btn btn-sm btn-outline-light l3d-view-job labs-view-details" data-id="' + j.public_id + '" data-job-id="' + j.public_id + '" data-tool="3d"><i class="fas fa-eye me-1"></i>View in viewer</a>' + dlBtn + '</div></div></div>';
                        }).join('');
                    }
                }
                document.querySelectorAll('.l3d-open-job').forEach(function (a) {
                    a.addEventListener('click', function (e) { e.preventDefault(); openJob(a.getAttribute('data-id')); });
                });
                document.querySelectorAll('.l3d-view-job').forEach(function (a) {
                    a.addEventListener('click', function (e) {
                        e.preventDefault();
                        var id = a.getAttribute('data-id') || a.getAttribute('data-job-id');
                        if (document.getElementById('labs-details-drawer') && window.KNDLabs && typeof window.KNDLabs.openJobViewer === 'function') {
                            e.stopPropagation();
                            window.KNDLabs.openJobViewer(id, '3d');
                            return;
                        }
                        openJob(id);
                    });
                });
            })
            .catch(function () {
                if (el.historyPlaceholder) el.historyPlaceholder.innerHTML = '<p class="knd-muted small mb-0">Could not load.</p>';
            });
    }

    function openJob(id) {
        state.currentJobId = id;
        pollStatus();
    }

    function renderViewer(job) {
        if (!job.has_glb) return;
        var base = (typeof window !== 'undefined' && window.location && window.location.origin) ? window.location.origin : '';
        var glbPath = (ep.download || '/api/labs/3d-lab/download.php') + '?id=' + encodeURIComponent(job.public_id) + '&format=glb&inline=1';
        var glbUrl = glbPath.indexOf('/') === 0 ? base + glbPath : glbPath;
        var dlPath = (ep.download || '/api/labs/3d-lab/download.php') + '?id=' + encodeURIComponent(job.public_id) + '&format=glb';
        var dlUrl = dlPath.indexOf('/') === 0 ? base + dlPath : dlPath;
        if (el.placeholder) el.placeholder.style.display = 'none';
        if (el.viewerWrap) el.viewerWrap.style.display = 'block';
        if (el.viewerImageWrap) { el.viewerImageWrap.classList.add('d-none'); el.viewerImageWrap.classList.remove('d-flex'); }
        if (el.modelViewer) el.modelViewer.style.display = 'block';
        if (el.resultActions) { el.resultActions.style.display = 'block'; }
        if (el.downloadBtn) { el.downloadBtn.href = dlUrl; el.downloadBtn.classList.remove('disabled'); }
        if (el.viewModelBtn) {
            el.viewModelBtn.href = '/labs?tool=model_viewer&source_3d_job_id=' + encodeURIComponent(job.public_id);
            el.viewModelBtn.classList.remove('disabled');
            el.viewModelBtn.removeAttribute('aria-disabled');
        }
        if (!el.modelViewer) return;

        function setModelSrc(url) {
            var mv = el.modelViewer;
            function apply() {
                mv.removeAttribute('src');
                setTimeout(function () { mv.setAttribute('src', url); }, 0);
            }
            if (typeof customElements !== 'undefined' && customElements.whenDefined) {
                customElements.whenDefined('model-viewer').then(apply);
            } else {
                apply();
            }
        }

        function onViewerError() {
            if (el.viewerWrap && !el.viewerWrap.querySelector('.l3d-viewer-err')) {
                var msg = document.createElement('p');
                msg.className = 'text-warning small mt-2 l3d-viewer-err';
                msg.textContent = 'Could not load 3D model. Try downloading the GLB.';
                el.viewerWrap.appendChild(msg);
            }
        }

        if (state.glbBlobUrl) { URL.revokeObjectURL(state.glbBlobUrl); state.glbBlobUrl = null; }
        if (state.viewerErrorHandler) {
            el.modelViewer.removeEventListener('error', state.viewerErrorHandler);
        }
        state.viewerErrorHandler = onViewerError;
        el.modelViewer.addEventListener('error', state.viewerErrorHandler);
        el.viewerWrap.querySelectorAll('.l3d-viewer-err').forEach(function (n) { n.remove(); });

        fetch(glbUrl, { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.blob() : Promise.reject(new Error('Failed to load GLB')); })
            .then(function (blob) {
                state.glbBlobUrl = URL.createObjectURL(blob);
                setModelSrc(state.glbBlobUrl);
            })
            .catch(function () {
                setModelSrc(glbUrl);
            });
    }

    function pollStatus() {
        if (!state.currentJobId) return;
        fetch((ep.status || '/api/labs/3d-lab/status.php') + '?id=' + encodeURIComponent(state.currentJobId), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok || !res.data) return;
                var job = res.data;
                setStatus(true, job.status === 'queued' ? 'Queued...' : (job.status === 'processing' ? 'Processing...' : job.status));

                if (job.status === 'failed') {
                    setStatus(false);
                    showError(job.error_message || 'Generation failed');
                    stopPolling();
                } else if (job.status === 'completed') {
                    clearError();
                    setStatus(false);
                    renderViewer(job);
                    stopPolling();
                    loadHistory();
                    toast('3D model ready', 'success');
                }
            })
            .catch(function () {});
    }

    function startPolling(id) {
        stopPolling();
        state.currentJobId = id;
        state.pollTimer = setInterval(pollStatus, 3500);
        pollStatus();
    }

    function stopPolling() {
        if (state.pollTimer) { clearInterval(state.pollTimer); state.pollTimer = null; }
    }

    function onSubmit(cancelPrevious) {
        if (!canSubmit()) {
            toast('Please fill required fields.', 'error');
            return;
        }
        clearError();
        var delegatedRetry = false;
        var fd = new FormData();
        fd.append('mode', state.mode);
        fd.append('category', (document.getElementById('l3d-category') || {}).value || 'Stylized Asset');
        fd.append('style', (document.getElementById('l3d-style') || {}).value || 'Stylized');
        fd.append('quality', (document.getElementById('l3d-quality') || {}).value || 'Standard');
        fd.append('prompt', (document.getElementById('l3d-prompt') || {}).value || '');
        fd.append('negative_prompt', (document.getElementById('l3d-negative') || {}).value || '');
        if (cancelPrevious) fd.append('cancel_previous', '1');
        if (state.mode === 'image' || state.mode === 'text_image') fd.append('image', state.file);
        if (state.mode === 'recent' && state.selectedRecent) {
            fd.append('source_recent_job_id', String(state.selectedRecent.id));
            fd.append('source_recent_type', '3d_lab');
        }

        if (el.submit) el.submit.disabled = true;
        setStatus(true, cancelPrevious ? 'Cancelling previous job...' : 'Queuing...');

        fetch(ep.create || '/api/labs/3d-lab/create.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok) {
                    var code = (res.error && res.error.code) || '';
                    var msg = (res.error && res.error.message) || 'Could not create job';
                    if (code === 'ACTIVE_JOB_EXISTS' && !cancelPrevious) {
                        if (confirm(msg + '\n\nCancel previous job and start a new one?')) {
                            delegatedRetry = true;
                            onSubmit(true);
                            return;
                        }
                        setStatus(false);
                        showError(msg);
                        return;
                    }
                    throw new Error(msg);
                }
                if (!res.data) throw new Error('Could not create job');
                var job = res.data;
                state.currentJobId = job.public_id;
                resetFile();
                state.selectedRecent = null;
                if (el.sourceId) el.sourceId.value = '';
                if (el.balance && res.data.available_after != null) el.balance.textContent = Number(res.data.available_after).toLocaleString();
                startPolling(job.public_id);
                toast('Job queued', 'success');
            })
            .catch(function (err) {
                setStatus(false);
                showError(err.message || 'Submission failed');
                toast(err.message, 'error');
            })
            .finally(function () {
                if (!delegatedRetry && el.submit) el.submit.disabled = false;
            });
    }

    function bindEvents() {
        if (!el.form) return;

        if (el.modeSelect) {
            el.modeSelect.addEventListener('change', function () {
                var val = el.modeSelect.value;
                if (val === 'text') return;
                state.mode = val;
                if (el.modeInput) el.modeInput.value = state.mode;
                toggleModeUI();
            });
        }

        if (el.dropzone) {
            el.dropzone.addEventListener('click', function () { if (el.fileInput) el.fileInput.click(); });
            el.dropzone.addEventListener('dragover', function (e) { e.preventDefault(); el.dropzone.classList.add('is-dragover'); });
            el.dropzone.addEventListener('dragleave', function () { el.dropzone.classList.remove('is-dragover'); });
            el.dropzone.addEventListener('drop', function (e) {
                e.preventDefault();
                el.dropzone.classList.remove('is-dragover');
                var f = e.dataTransfer && e.dataTransfer.files ? e.dataTransfer.files[0] : null;
                if (f) {
                    var ok = /^image\/(jpeg|jpg|png|webp)$/i.test(f.type) || f.type === 'model/gltf-binary' || (f.name && f.name.toLowerCase().endsWith('.glb'));
                    if (ok) setFile(f);
                }
            });
        }
        if (el.fileInput) el.fileInput.addEventListener('change', function () {
            var f = el.fileInput.files && el.fileInput.files[0];
            if (f) {
                var ok = /^image\/(jpeg|jpg|png|webp)$/i.test(f.type) || f.type === 'model/gltf-binary' || (f.name && f.name.toLowerCase().endsWith('.glb'));
                if (ok) setFile(f);
            }
        });
        if (el.removeImg) el.removeImg.addEventListener('click', function (e) { e.stopPropagation(); resetFile(); });

        el.form.addEventListener('submit', function (e) { e.preventDefault(); onSubmit(); });
        if (el.submit) el.submit.addEventListener('click', function (e) { e.preventDefault(); onSubmit(); });
        if (el.viewImageBtn) el.viewImageBtn.addEventListener('click', function (e) { e.preventDefault(); showImageInViewer(); });

        // Ensure Download GLB button always triggers a download, even if other handlers cancel clicks.
        if (el.downloadBtn) {
            el.downloadBtn.addEventListener('click', function (e) {
                var href = el.downloadBtn.getAttribute('href');
                var disabled = el.downloadBtn.classList.contains('disabled') || el.downloadBtn.getAttribute('aria-disabled') === 'true';
                if (!href || href === '#' || disabled) return;
                e.preventDefault();
                e.stopPropagation();
                try {
                    var w = window.open(href, '_blank');
                    if (w) w.opener = null;
                } catch (err) {
                    window.location.href = href;
                }
            });
        }

        if (el.viewModelBtn) {
            el.viewModelBtn.addEventListener('click', function (e) {
                var href = el.viewModelBtn.getAttribute('href');
                var disabled = el.viewModelBtn.classList.contains('disabled') || el.viewModelBtn.getAttribute('aria-disabled') === 'true';
                if (!href || href === '#' || disabled) return;
                e.preventDefault();
                e.stopPropagation();
                window.location.href = href;
            });
        }

        var fsBtn = document.getElementById('l3d-fullscreen');
        if (fsBtn) fsBtn.addEventListener('click', function () {
            var target = (el.viewerImageWrap && !el.viewerImageWrap.classList.contains('d-none')) ? el.viewerImageWrap : el.modelViewer;
            if (target && target.requestFullscreen) target.requestFullscreen();
        });
    }

    bindEvents();
    toggleModeUI();
    preloadSourceJobImage();
    loadHistory();
    updateViewImageButton();
})();
