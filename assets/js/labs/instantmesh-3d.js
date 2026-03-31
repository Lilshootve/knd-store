(function () {
    'use strict';

    var cfg = window.KND_INSTANTMESH || {};
    var endpoints = cfg.endpoints || {};

    var el = {
        form: document.getElementById('instantmesh-form'),
        dropzone: document.getElementById('instantmesh-dropzone'),
        fileInput: document.getElementById('instantmesh-file'),
        dropzoneContent: document.getElementById('instantmesh-dropzone-content'),
        previewWrap: document.getElementById('instantmesh-preview-wrap'),
        preview: document.getElementById('instantmesh-preview'),
        removeBtn: document.getElementById('instantmesh-remove'),
        submitBtn: document.getElementById('instantmesh-submit'),
        removeBg: document.getElementById('remove-bg'),
        seed: document.getElementById('seed'),
        outputFormat: document.getElementById('output-format'),
        statusLabel: document.getElementById('job-status-label'),
        statusBadge: document.getElementById('job-status-badge'),
        progressBar: document.getElementById('job-progress-bar'),
        error: document.getElementById('instantmesh-error'),
        modelViewer: document.getElementById('instantmesh-model-viewer'),
        viewerEmpty: document.getElementById('viewer-empty'),
        history: document.getElementById('instantmesh-history'),
        downloadGlb: document.getElementById('download-glb'),
        downloadObj: document.getElementById('download-obj'),
        metaDate: document.getElementById('meta-date'),
        metaSeed: document.getElementById('meta-seed'),
        metaRemoveBg: document.getElementById('meta-remove-bg'),
        metaTime: document.getElementById('meta-time')
    };

    var state = {
        file: null,
        pollTimer: null,
        currentJobId: null,
        startedAt: null
    };

    function toast(message, type) {
        if (window.kndToast) {
            window.kndToast(message, type || 'info');
            return;
        }
        if (type === 'error') alert(message);
    }

    function setStatus(status) {
        var map = {
            idle: { label: 'Idle', badge: 'waiting', cls: 'secondary', progress: 0 },
            queued: { label: 'Queued', badge: 'queued', cls: 'info', progress: 25 },
            processing: { label: 'Processing', badge: 'processing', cls: 'warning', progress: 65 },
            completed: { label: 'Completed', badge: 'completed', cls: 'success', progress: 100 },
            failed: { label: 'Failed', badge: 'failed', cls: 'danger', progress: 100 }
        };
        var current = map[status] || map.idle;

        el.statusLabel.textContent = current.label;
        el.statusBadge.className = 'badge text-bg-' + current.cls;
        el.statusBadge.textContent = current.badge;
        el.progressBar.style.width = current.progress + '%';
    }

    function clearError() {
        el.error.style.display = 'none';
        el.error.textContent = '';
    }

    function showError(message) {
        el.error.textContent = message || 'Unexpected error';
        el.error.style.display = 'block';
    }

    function setFile(file) {
        if (!file) return;
        state.file = file;
        el.preview.src = URL.createObjectURL(file);
        el.previewWrap.style.display = 'block';
        el.dropzoneContent.style.display = 'none';
        el.submitBtn.disabled = false;
        clearError();
    }

    function resetFile() {
        state.file = null;
        el.fileInput.value = '';
        el.preview.src = '';
        el.previewWrap.style.display = 'none';
        el.dropzoneContent.style.display = 'block';
        el.submitBtn.disabled = true;
    }

    function formatDate(value) {
        if (!value) return '—';
        var d = new Date(value.replace(' ', 'T'));
        if (Number.isNaN(d.getTime())) return value;
        return d.toLocaleString();
    }

    function updateMeta(job) {
        el.metaDate.textContent = formatDate(job.created_at || job.completed_at || '');
        el.metaSeed.textContent = job.seed !== undefined && job.seed !== null ? String(job.seed) : '—';
        el.metaRemoveBg.textContent = Number(job.remove_bg) === 1 ? 'Yes' : 'No';

        if (state.startedAt && (job.status === 'completed' || job.status === 'failed')) {
            var sec = Math.max(0, Math.round((Date.now() - state.startedAt) / 1000));
            el.metaTime.textContent = sec + 's';
        } else if (job.total_time_seconds) {
            el.metaTime.textContent = Math.round(job.total_time_seconds) + 's';
        }
    }

    function setDownloadButton(anchor, href, enabled) {
        if (!enabled || !href) {
            anchor.classList.add('disabled');
            anchor.setAttribute('aria-disabled', 'true');
            anchor.removeAttribute('href');
            return;
        }
        anchor.classList.remove('disabled');
        anchor.removeAttribute('aria-disabled');
        anchor.href = href;
    }

    function renderViewer(job) {
        var hasGlb = !!job.has_glb;
        var glbUrl = hasGlb ? endpoints.download + '?job_id=' + encodeURIComponent(job.public_id) + '&format=glb' : null;
        var glbInlineUrl = hasGlb ? glbUrl + '&inline=1' : null;
        var objUrl = job.has_obj ? endpoints.download + '?job_id=' + encodeURIComponent(job.public_id) + '&format=obj' : null;

        setDownloadButton(el.downloadGlb, glbUrl, hasGlb);
        setDownloadButton(el.downloadObj, objUrl, !!job.has_obj);

        if (hasGlb) {
            el.modelViewer.style.display = 'block';
            el.viewerEmpty.style.display = 'none';
            el.modelViewer.setAttribute('src', glbInlineUrl);
        } else {
            el.modelViewer.style.display = 'none';
            el.modelViewer.removeAttribute('src');
            el.viewerEmpty.style.display = 'flex';
        }
    }

    function pollStatus() {
        if (!state.currentJobId) return;

        fetch(endpoints.status + '?job_id=' + encodeURIComponent(state.currentJobId), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                if (!payload || !payload.ok || !payload.data) return;
                var job = payload.data;

                setStatus(job.status || 'idle');
                updateMeta(job);

                if (job.status === 'failed') {
                    showError(job.error_message || 'Generation failed');
                    stopPolling();
                    loadHistory();
                } else if (job.status === 'completed') {
                    clearError();
                    renderViewer(job);
                    stopPolling();
                    loadHistory();
                    toast('3D generation completed', 'success');
                }
            })
            .catch(function () {
                // Keep polling silently on intermittent network failures
            });
    }

    function startPolling(jobId) {
        stopPolling();
        state.currentJobId = jobId;
        state.startedAt = Date.now();
        state.pollTimer = setInterval(pollStatus, 3500);
        pollStatus();
    }

    function stopPolling() {
        if (state.pollTimer) {
            clearInterval(state.pollTimer);
            state.pollTimer = null;
        }
    }

    function onSubmit() {
        if (!state.file) {
            toast('Please upload an image first', 'error');
            return;
        }

        clearError();

        var fd = new FormData();
        fd.append('image', state.file);
        fd.append('remove_bg', el.removeBg.checked ? '1' : '0');
        fd.append('seed', String(el.seed.value || 42));
        fd.append('output_format', el.outputFormat.value || 'glb');

        el.submitBtn.disabled = true;
        setStatus('queued');

        fetch(endpoints.create, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                if (!payload || !payload.ok || !payload.data) {
                    var msg = payload && payload.error && payload.error.message ? payload.error.message : 'Could not create job';
                    throw new Error(msg);
                }
                var job = payload.data;
                state.currentJobId = job.public_id;
                resetFile();
                setStatus(job.status || 'queued');
                updateMeta(job);
                startPolling(job.public_id);
                toast('Job queued successfully', 'success');
            })
            .catch(function (err) {
                setStatus('failed');
                showError(err.message || 'Submission failed');
                toast(err.message || 'Submission failed', 'error');
                el.submitBtn.disabled = false;
            });
    }

    function renderHistoryCards(jobs) {
        if (!jobs || !jobs.length) {
            el.history.innerHTML = '<p class="knd-muted small mb-0">No generations yet. Drop an image and create your first 3D asset.</p>';
            return;
        }

        var html = jobs.map(function (job) {
            var thumb = job.preview_url || '';
            var badgeCls = job.status === 'completed' ? 'text-bg-success' : (job.status === 'failed' ? 'text-bg-danger' : 'text-bg-warning');

            return '' +
                '<article class="instantmesh-history-card" data-job-id="' + String(job.public_id || '') + '">' +
                (thumb ? '<img class="instantmesh-history-thumb" src="' + thumb + '" alt="thumbnail">' : '<div class="instantmesh-history-thumb"></div>') +
                '<div class="instantmesh-history-body">' +
                '<div class="d-flex justify-content-between align-items-center mb-2">' +
                '<span class="badge ' + badgeCls + '">' + String(job.status || 'queued') + '</span>' +
                '<small class="instantmesh-history-date">' + formatDate(job.created_at || '') + '</small>' +
                '</div>' +
                '<button type="button" class="btn btn-outline-light btn-sm w-100 instantmesh-history-cta" data-open-job="' + String(job.public_id || '') + '">View Details</button>' +
                '</div>' +
                '</article>';
        }).join('');

        el.history.innerHTML = html;
    }

    function loadHistory() {
        fetch(endpoints.history + '?limit=8', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                if (!payload || !payload.ok || !payload.data) return;
                renderHistoryCards(payload.data.jobs || []);
            })
            .catch(function () {
                // noop
            });
    }

    function bindEvents() {
        if (!el.form) return;

        el.dropzone.addEventListener('click', function () {
            el.fileInput.click();
        });

        el.dropzone.addEventListener('dragover', function (e) {
            e.preventDefault();
            el.dropzone.classList.add('is-dragover');
        });

        el.dropzone.addEventListener('dragleave', function () {
            el.dropzone.classList.remove('is-dragover');
        });

        el.dropzone.addEventListener('drop', function (e) {
            e.preventDefault();
            el.dropzone.classList.remove('is-dragover');
            var file = e.dataTransfer && e.dataTransfer.files ? e.dataTransfer.files[0] : null;
            if (file) setFile(file);
        });

        el.fileInput.addEventListener('change', function () {
            var file = el.fileInput.files && el.fileInput.files[0] ? el.fileInput.files[0] : null;
            if (file) setFile(file);
        });

        el.removeBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            resetFile();
        });

        el.form.addEventListener('submit', function (e) {
            e.preventDefault();
            onSubmit();
        });

        el.submitBtn.addEventListener('click', function () {
            onSubmit();
        });

        el.history.addEventListener('click', function (e) {
            var target = e.target;
            if (!(target instanceof HTMLElement)) return;
            var btn = target.closest('[data-open-job]');
            if (!btn) return;
            var jobId = btn.getAttribute('data-open-job');
            if (!jobId) return;
            state.currentJobId = jobId;
            setStatus('processing');
            startPolling(jobId);
        });
    }

    bindEvents();
    setStatus('idle');
    loadHistory();
})();
