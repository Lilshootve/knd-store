(function () {
    'use strict';

    var cfg = window.KND_CHARACTER_LAB || {};
    var endpoints = cfg.endpoints || {};

    var el = {
        form: document.getElementById('character-lab-form'),
        modeText: document.getElementById('mode-text'),
        modeImage: document.getElementById('mode-image'),
        modeTextImage: document.getElementById('mode-text-image'),
        modeRecent: document.getElementById('mode-recent'),
        modeInput: document.getElementById('cl-mode'),
        promptWrap: document.getElementById('cl-prompt-wrap'),
        prompt: document.getElementById('cl-prompt'),
        uploadWrap: document.getElementById('cl-upload-wrap'),
        recentWrap: document.getElementById('cl-recent-wrap'),
        recentGallery: document.getElementById('cl-recent-gallery'),
        dropzone: document.getElementById('cl-dropzone'),
        fileInput: document.getElementById('cl-file'),
        dropzoneContent: document.getElementById('cl-dropzone-content'),
        previewWrap: document.getElementById('cl-preview-wrap'),
        preview: document.getElementById('cl-preview'),
        removeImg: document.getElementById('cl-remove-img'),
        category: document.getElementById('cl-category'),
        sourceId: document.getElementById('cl-source-id'),
        sourceType: document.getElementById('cl-source-type'),
        submit: document.getElementById('cl-submit'),
        statusLabel: document.getElementById('cl-status-label'),
        statusBadge: document.getElementById('cl-status-badge'),
        progressBar: document.getElementById('cl-progress-bar'),
        conceptPreview: document.getElementById('cl-concept-preview'),
        conceptImg: document.getElementById('cl-concept-img'),
        viewerEmpty: document.getElementById('cl-viewer-empty'),
        modelViewer: document.getElementById('cl-model-viewer'),
        downloadWrap: document.getElementById('cl-download-wrap'),
        downloadGlb: document.getElementById('cl-download-glb'),
        error: document.getElementById('cl-error'),
        balance: document.getElementById('cl-balance')
    };

    var state = {
        mode: 'text',
        file: null,
        selectedRecent: null,
        pollTimer: null,
        currentJobId: null
    };

    function toast(msg, type) {
        if (window.kndToast) {
            window.kndToast(msg, type || 'info');
        } else if (type === 'error') {
            alert(msg);
        }
    }

    function setStatus(status) {
        var map = {
            idle: { label: 'Idle', badge: 'waiting', cls: 'secondary', progress: 0 },
            queued: { label: 'Queued', badge: 'queued', cls: 'info', progress: 15 },
            image_generating: { label: 'Concept image...', badge: 'generating', cls: 'info', progress: 30 },
            image_ready: { label: 'Concept ready', badge: 'ready', cls: 'info', progress: 50 },
            mesh_generating: { label: '3D mesh...', badge: 'generating', cls: 'warning', progress: 70 },
            mesh_ready: { label: 'Done', badge: 'done', cls: 'success', progress: 100 },
            partial_success: { label: 'Partial', badge: 'partial', cls: 'warning', progress: 100 },
            failed: { label: 'Failed', badge: 'failed', cls: 'danger', progress: 100 }
        };
        var s = map[status] || map.idle;
        if (el.statusLabel) el.statusLabel.textContent = s.label;
        if (el.statusBadge) { el.statusBadge.className = 'badge bg-' + s.cls; el.statusBadge.textContent = s.badge; }
        if (el.progressBar) el.progressBar.style.width = s.progress + '%';
    }

    function showError(msg) {
        if (el.error) {
            el.error.textContent = msg || 'Error';
            el.error.style.display = 'block';
        }
    }

    function clearError() {
        if (el.error) el.error.style.display = 'none';
    }

    function toggleModeUI() {
        var m = state.mode;
        if (el.promptWrap) el.promptWrap.style.display = (m === 'image') ? 'none' : 'block';
        if (el.uploadWrap) el.uploadWrap.style.display = (m === 'image' || m === 'text_image') ? 'block' : 'none';
        if (el.recentWrap) el.recentWrap.style.display = (m === 'recent_image') ? 'block' : 'none';
        if (m === 'recent_image') loadRecentImages();
    }

    function setFile(file) {
        if (!file) return;
        state.file = file;
        state.selectedRecent = null;
        if (el.preview && el.previewWrap && el.dropzoneContent) {
            el.preview.src = URL.createObjectURL(file);
            el.previewWrap.style.display = 'block';
            el.dropzoneContent.style.display = 'none';
        }
        if (el.sourceId) el.sourceId.value = '';
        if (el.sourceType) el.sourceType.value = '';
    }

    function resetFile() {
        state.file = null;
        if (el.fileInput) el.fileInput.value = '';
        if (el.preview) el.preview.src = '';
        if (el.previewWrap) el.previewWrap.style.display = 'none';
        if (el.dropzoneContent) el.dropzoneContent.style.display = 'block';
    }

    function selectRecent(id, source) {
        state.selectedRecent = { id: id, source: source };
        state.file = null;
        resetFile();
        if (el.sourceId) el.sourceId.value = String(id);
        if (el.sourceType) el.sourceType.value = source;
    }

    function loadRecentImages() {
        if (!el.recentGallery) return;
        el.recentGallery.innerHTML = '<p class="text-white-50 small mb-0"><i class="fas fa-spinner fa-spin me-1"></i>Loading...</p>';
        fetch((endpoints.recentImages || '/api/character-lab/recent-images.php') + '?limit=12', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var imgs = (res.data && res.data.images) ? res.data.images : [];
                if (!imgs.length) {
                    el.recentGallery.innerHTML = '<p class="text-white-50 small mb-0">No recent images.</p>';
                    return;
                }
                var html = imgs.map(function (img) {
                    var src = img.image_url || '';
                    var sel = (state.selectedRecent && state.selectedRecent.id === img.id && state.selectedRecent.source === img.source) ? ' border-primary' : '';
                    return '<div class="col-4 col-md-3"><div class="recent-thumb rounded overflow-hidden bg-secondary' + sel + '" data-id="' + img.id + '" data-source="' + (img.source || 'labs_job') + '" style="cursor:pointer; aspect-ratio:1; border:2px solid transparent;">' +
                        (src ? '<img src="' + src + '" alt="" class="w-100 h-100" style="object-fit:cover;">' : '<div class="w-100 h-100 d-flex align-items-center justify-content-center"><i class="fas fa-image text-white-50"></i></div>') +
                        '</div><small class="text-white-50 d-block text-truncate">' + (img.label || '') + '</small></div>';
                }).join('');
                el.recentGallery.innerHTML = html;
                el.recentGallery.querySelectorAll('.recent-thumb').forEach(function (node) {
                    node.addEventListener('click', function () {
                        selectRecent(parseInt(node.getAttribute('data-id'), 10), node.getAttribute('data-source'));
                        el.recentGallery.querySelectorAll('.recent-thumb').forEach(function (n) { n.classList.remove('border-primary'); });
                        node.classList.add('border-primary');
                    });
                });
            })
            .catch(function () {
                el.recentGallery.innerHTML = '<p class="text-white-50 small mb-0">Could not load recent images.</p>';
            });
    }

    function canSubmit() {
        if (state.mode === 'text') return el.prompt && el.prompt.value.trim().length > 0;
        if (state.mode === 'image' || state.mode === 'text_image') return !!state.file;
        if (state.mode === 'recent_image') return !!state.selectedRecent;
        return false;
    }

    function onSubmit() {
        if (!canSubmit()) {
            toast('Please fill in the required fields.', 'error');
            return;
        }
        clearError();
        var fd = new FormData();
        fd.append('mode', state.mode);
        fd.append('category', el.category ? el.category.value : 'human');
        fd.append('policy_mode', 'safe');
        if (state.mode !== 'image') {
            fd.append('prompt', el.prompt ? el.prompt.value.trim() : '');
        }
        if (state.mode === 'image' || state.mode === 'text_image') {
            fd.append('image', state.file);
            if (state.mode === 'text_image') fd.append('prompt', el.prompt ? el.prompt.value.trim() : '');
        }
        if (state.mode === 'recent_image' && state.selectedRecent) {
            fd.append('source_recent_job_id', String(state.selectedRecent.id));
            fd.append('source_recent_type', state.selectedRecent.source);
        }

        if (el.submit) el.submit.disabled = true;
        setStatus('queued');

        fetch(endpoints.create || '/api/character-lab/create.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok || !res.data) {
                    throw new Error((res.error && res.error.message) || 'Could not create job');
                }
                var job = res.data;
                state.currentJobId = job.public_id;
                resetFile();
                state.selectedRecent = null;
                if (el.sourceId) el.sourceId.value = '';
                if (el.sourceType) el.sourceType.value = '';
                if (el.balance && res.data.available_after != null) el.balance.innerHTML = '<i class="fas fa-coins me-1"></i>' + Number(res.data.available_after).toLocaleString() + ' KP';
                startPolling(job.public_id);
                toast('Job queued', 'success');
            })
            .catch(function (err) {
                setStatus('failed');
                showError(err.message || 'Submission failed');
                toast(err.message || 'Submission failed', 'error');
            })
            .finally(function () {
                if (el.submit) el.submit.disabled = false;
            });
    }

    function renderViewer(job) {
        var glbUrl = job.has_glb ? (endpoints.download + '?id=' + encodeURIComponent(job.public_id) + '&format=glb&inline=1') : null;
        var dlUrl = job.has_glb ? (endpoints.download + '?id=' + encodeURIComponent(job.public_id) + '&format=glb') : null;

        if (job.has_concept && job.concept_url && el.conceptPreview && el.conceptImg) {
            el.conceptPreview.style.display = 'block';
            el.conceptImg.src = job.concept_url;
        }

        if (job.has_glb && el.modelViewer && el.viewerEmpty) {
            el.modelViewer.style.display = 'block';
            el.modelViewer.setAttribute('src', glbUrl);
            el.viewerEmpty.style.display = 'none';
        } else {
            if (el.modelViewer) { el.modelViewer.style.display = 'none'; el.modelViewer.removeAttribute('src'); }
            if (el.viewerEmpty) el.viewerEmpty.style.display = 'flex';
        }

        if (el.downloadWrap && el.downloadGlb) {
            if (job.has_glb) {
                el.downloadWrap.style.display = 'block';
                el.downloadGlb.href = dlUrl;
                el.downloadGlb.classList.remove('disabled');
                el.downloadGlb.removeAttribute('aria-disabled');
            } else {
                el.downloadWrap.style.display = 'none';
            }
        }
    }

    function pollStatus() {
        if (!state.currentJobId) return;
        fetch((endpoints.status || '/api/character-lab/status.php') + '?id=' + encodeURIComponent(state.currentJobId), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok || !res.data) return;
                var job = res.data;
                setStatus(job.status);

                if (job.status === 'failed') {
                    showError(job.error_message || 'Generation failed');
                    stopPolling();
                } else if (job.status === 'mesh_ready' || job.status === 'partial_success') {
                    clearError();
                    renderViewer(job);
                    stopPolling();
                    toast('Generation complete', 'success');
                }
            })
            .catch(function () {});
    }

    function startPolling(id) {
        stopPolling();
        state.currentJobId = id;
        var t = setInterval(pollStatus, 3500);
        state.pollTimer = t;
        pollStatus();
    }

    function stopPolling() {
        if (state.pollTimer) {
            clearInterval(state.pollTimer);
            state.pollTimer = null;
        }
    }

    function bindEvents() {
        if (!el.form) return;

        [el.modeText, el.modeImage, el.modeTextImage, el.modeRecent].forEach(function (radio, i) {
            if (!radio) return;
            radio.addEventListener('change', function () {
                state.mode = ['text', 'image', 'text_image', 'recent_image'][i];
                if (el.modeInput) el.modeInput.value = state.mode;
                toggleModeUI();
            });
        });

        if (el.dropzone) {
            el.dropzone.addEventListener('click', function () { if (el.fileInput) el.fileInput.click(); });
            el.dropzone.addEventListener('dragover', function (e) { e.preventDefault(); el.dropzone.classList.add('is-dragover'); });
            el.dropzone.addEventListener('dragleave', function () { el.dropzone.classList.remove('is-dragover'); });
            el.dropzone.addEventListener('drop', function (e) {
                e.preventDefault();
                el.dropzone.classList.remove('is-dragover');
                var f = e.dataTransfer && e.dataTransfer.files ? e.dataTransfer.files[0] : null;
                if (f && /^image\/(jpeg|jpg|png|webp)$/i.test(f.type)) setFile(f);
            });
        }
        if (el.fileInput) el.fileInput.addEventListener('change', function () {
            var f = el.fileInput.files && el.fileInput.files[0];
            if (f) setFile(f);
        });
        if (el.removeImg) el.removeImg.addEventListener('click', function (e) { e.stopPropagation(); resetFile(); });

        el.form.addEventListener('submit', function (e) { e.preventDefault(); onSubmit(); });
        if (el.submit) el.submit.addEventListener('click', function (e) { e.preventDefault(); onSubmit(); });
    }

    bindEvents();
    setStatus('idle');
})();
