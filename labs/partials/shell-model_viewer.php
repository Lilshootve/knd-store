<?php
/**
 * Model Viewer - integrated with 3D Lab and manual GLB upload.
 *
 * - My 3D Lab Creations: list from /api/labs/3d-lab/history.php, load GLB in viewer.
 * - Upload GLB: local file → blob URL → viewer (preview only; no backend save yet).
 * - Custom URL: optional paste GLB/ZIP URL.
 */

$mvHistoryUrl = '/api/labs/3d-lab/history.php';
?>

<div class="ln-t2i-workspace ln-tool-workspace">
  <header class="ln-t2i-header">
    <h1 class="ln-editor-title">
      <?php echo t('labs.model_viewer.title', 'Model Viewer'); ?>
    </h1>
    <p class="ln-editor-subtitle">
      View your 3D Lab creations or upload a GLB to preview. No credits required.
    </p>
  </header>

  <div class="ln-t2i-grid">
    <div class="ln-t2i-main-col">
      <div class="ln-t2i-canvas-zone">
        <div class="knd-canvas knd-panel-soft ln-t2i-preview-wrap">
          <div id="mv-shell-wrapper" class="labs-result-preview ln-t2i-preview" style="min-height: 360px;">
            <iframe
              id="mv-shell-iframe"
              src="about:blank"
              title="Model Viewer"
              style="width:100%;height:100%;border:0;border-radius:12px;background:#020617;display:none;"
            ></iframe>
            <div id="mv-shell-empty" class="labs-placeholder-tips">
              <i class="fas fa-cube ln-t2i-placeholder-icon"></i>
              <p class="text-white-50 mb-1">Model Viewer</p>
              <p class="text-white-50 small mb-0">
                Choose a creation from <strong>My 3D Lab Creations</strong> below, or <strong>Upload a GLB</strong> to preview.
              </p>
            </div>
          </div>

          <div class="ln-t2i-actions mt-3 d-flex flex-wrap gap-2">
            <a href="#" id="mv-shell-download" class="labs-action labs-action--primary disabled" aria-disabled="true" style="display:none;">
              <i class="fas fa-download"></i>
              <span id="mv-shell-download-label">Download</span>
            </a>
            <a href="#" id="mv-shell-open-full" class="labs-action labs-action--secondary disabled" aria-disabled="true" style="display:none;">
              <i class="fas fa-external-link-alt"></i>
              <span><?php echo t('labs.model_viewer.open_full', 'Open full viewer'); ?></span>
            </a>
          </div>

          <div id="mv-shell-error" class="alert alert-danger ln-t2i-error mt-3 mb-0" style="display:none;"></div>
        </div>
      </div>
    </div>

    <aside class="ln-t2i-params-col">
      <div class="ln-t2i-params-panel">
        <!-- My 3D Lab Creations -->
        <div class="ln-t2i-param-group mb-3">
          <label class="ln-t2i-param-label">
            <?php echo t('labs.model_viewer.my_creations', 'My 3D Lab Creations'); ?>
          </label>
          <div id="mv-3d-list" class="mv-creations-list">
            <p class="text-white-50 small mb-0"><i class="fas fa-spinner fa-spin me-1"></i>Loading…</p>
          </div>
        </div>

        <!-- Upload GLB -->
        <div class="ln-t2i-param-group mb-3">
          <label class="ln-t2i-param-label">
            <?php echo t('labs.model_viewer.upload_glb', 'Upload GLB'); ?>
          </label>
          <input type="file" id="mv-upload-glb" class="form-control form-control-sm knd-input text-white" accept=".glb,model/gltf-binary" style="font-size:12px;">
          <p id="mv-upload-filename" class="text-white-50 small mb-0 mt-1" style="display:none;"></p>
          <button type="button" id="mv-upload-clear" class="btn btn-sm btn-outline-secondary mt-1" style="display:none;">
            <i class="fas fa-times me-1"></i><?php echo t('labs.model_viewer.clear', 'Clear'); ?>
          </button>
        </div>

        <div class="ln-t2i-param-group">
          <label class="ln-t2i-param-label small"><?php echo t('labs.model_viewer.details', 'Details'); ?></label>
          <p id="mv-shell-details" class="text-white-50 small mb-0">Select a model or upload a GLB.</p>
        </div>
      </div>
    </aside>
  </div>
</div>

<script>
(function() {
  var BASE = (typeof window !== 'undefined' && window.location && window.location.origin) ? window.location.origin : '';
  var HISTORY_API = '<?php echo addslashes($mvHistoryUrl); ?>';
  var UPLOAD_API = '/api/labs/model-viewer/upload.php';
  var iframe = document.getElementById('mv-shell-iframe');
  var emptyState = document.getElementById('mv-shell-empty');
  var errorBox = document.getElementById('mv-shell-error');
  var downloadBtn = document.getElementById('mv-shell-download');
  var downloadLabel = document.getElementById('mv-shell-download-label');
  var openFullBtn = document.getElementById('mv-shell-open-full');
  var detailsEl = document.getElementById('mv-shell-details');
  var list3d = document.getElementById('mv-3d-list');
  var uploadInput = document.getElementById('mv-upload-glb');
  var uploadFilename = document.getElementById('mv-upload-filename');
  var uploadClear = document.getElementById('mv-upload-clear');
  var pageParams = new URLSearchParams(window.location.search || '');
  var source3dJobId = pageParams.get('source_3d_job_id');
  var sourceJobId = pageParams.get('source_job_id');

  function buildViewerUrl(opts) {
    var model = opts.model || opts.glb;
    if (!model) return '';
    var params = new URLSearchParams();
    params.set('model', model);
    if (opts.zip) params.set('zip', opts.zip);
    if (opts.title) params.set('title', opts.title);
    if (opts.description) params.set('description', opts.description || '');
    if (opts.thumb) params.set('thumb', opts.thumb);
    return '/viewer/index.html?' + params.toString();
  }

  function setViewer(url, downloadHref, downloadText, fullUrl, details, downloadFilename) {
    if (!url) {
      if (iframe) { iframe.src = 'about:blank'; iframe.style.display = 'none'; }
      if (emptyState) emptyState.style.display = 'block';
      if (downloadBtn) downloadBtn.style.display = 'none';
      if (openFullBtn) { openFullBtn.classList.add('disabled'); openFullBtn.setAttribute('aria-disabled', 'true'); openFullBtn.style.display = 'none'; }
    } else {
      if (emptyState) emptyState.style.display = 'none';
      if (iframe) {
        iframe.src = url;
        iframe.style.display = 'block';
      }
      if (openFullBtn) {
        if (fullUrl) {
          openFullBtn.style.display = 'inline-flex';
          openFullBtn.classList.remove('disabled');
          openFullBtn.removeAttribute('aria-disabled');
          openFullBtn.href = fullUrl;
          openFullBtn.target = '_blank';
          openFullBtn.rel = 'noopener noreferrer';
        } else {
          openFullBtn.classList.add('disabled');
          openFullBtn.setAttribute('aria-disabled', 'true');
          openFullBtn.style.display = 'none';
        }
      }
      if (downloadBtn && downloadLabel) {
        if (downloadHref && downloadText) {
          downloadBtn.style.display = 'inline-flex';
          downloadBtn.classList.remove('disabled');
          downloadBtn.removeAttribute('aria-disabled');
          downloadBtn.href = downloadHref;
          downloadBtn.setAttribute('download', downloadFilename || '');
          downloadLabel.textContent = downloadText;
        } else {
          downloadBtn.style.display = 'none';
        }
      }
    }
    if (detailsEl && details !== undefined) detailsEl.textContent = details || '';
    if (errorBox) { errorBox.textContent = ''; errorBox.style.display = 'none'; }
  }

  function clearError() {
    if (errorBox) { errorBox.textContent = ''; errorBox.style.display = 'none'; }
  }

  function showError(msg) {
    if (errorBox) { errorBox.textContent = msg; errorBox.style.display = 'block'; }
  }

  function loadSource3dJobIfAny() {
    if (!source3dJobId) return;
    fetch('/api/labs/3d-lab/status.php?id=' + encodeURIComponent(source3dJobId), { credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(res) {
        if (!res.ok || !res.data || !res.data.glb_url) return;
        var j = res.data;
        var glbUrl = (j.glb_url && j.glb_url.indexOf('/') === 0) ? (BASE + j.glb_url) : j.glb_url;
        var thumb = (j.preview_url && j.preview_url.indexOf('/') === 0) ? (BASE + j.preview_url) : j.preview_url;
        var title = (j.public_id || '3D Model');
        var viewerUrl = buildViewerUrl({ model: glbUrl, thumb: thumb, title: title, description: j.created_at ? ('Created ' + j.created_at) : '' });
        setViewer(viewerUrl, glbUrl, 'Download GLB', viewerUrl, j.created_at ? '3D Lab · ' + j.created_at : title);
      })
      .catch(function() {});
  }

  function loadSourceJobIfAny() {
    if (!sourceJobId) return;
    fetch('/api/labs/job.php?job_id=' + encodeURIComponent(sourceJobId), { credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(res) {
        if (!res.ok || !res.data) return;
        var j = res.data;
        var outputPath = (j.output_path || '').toLowerCase();
        if (!outputPath || outputPath.indexOf('.glb') === -1) return;
        var modelUrl = '/api/labs/image.php?job_id=' + encodeURIComponent(sourceJobId);
        var downloadUrl = modelUrl + '&download=1';
        var title = '3D Vertex Job #' + sourceJobId;
        var desc = j.created_at ? ('Created ' + j.created_at) : 'Generated model';
        var viewerUrl = buildViewerUrl({ model: modelUrl, title: title, description: desc });
        setViewer(viewerUrl, downloadUrl, 'Download GLB', viewerUrl, desc, 'knd_labs_' + sourceJobId + '.glb');
      })
      .catch(function() {});
  }

  // —— My 3D Lab Creations ——
  if (list3d) {
    fetch(HISTORY_API + '?limit=24', { credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(res) {
        var jobs = (res.ok && res.data && res.data.jobs) ? res.data.jobs : [];
        var withGlb = jobs.filter(function(j) { return j.glb_url; });
        if (withGlb.length === 0) {
          list3d.innerHTML = '<p class="text-white-50 small mb-0">No 3D creations yet. Create one in <a href="/labs?tool=3d_vertex" class="text-decoration-underline">3D Vertex</a>.</p>';
          return;
        }
        var html = '<ul class="list-unstyled mb-0">';
        withGlb.forEach(function(j) {
          var glbUrl = (j.glb_url && j.glb_url.indexOf('/') === 0) ? (BASE + j.glb_url) : j.glb_url;
          var thumb = (j.preview_url && j.preview_url.indexOf('/') === 0) ? (BASE + j.preview_url) : j.preview_url;
          var title = (j.title || j.public_id || 'Model').substring(0, 50);
          var viewerUrl = buildViewerUrl({ model: glbUrl, thumb: thumb, title: title, description: j.created_at ? ('Created ' + j.created_at) : '' });
          html += '<li class="mb-2">';
          html += '<button type="button" class="btn btn-sm w-100 d-flex align-items-center text-start mv-3d-item" data-glb="' + (glbUrl || '').replace(/"/g, '&quot;') + '" data-thumb="' + (thumb || '').replace(/"/g, '&quot;') + '" data-title="' + (title || '').replace(/"/g, '&quot;') + '" data-created="' + (j.created_at || '').replace(/"/g, '&quot;') + '">';
          if (thumb) html += '<img src="' + thumb.replace(/"/g, '&quot;') + '" alt="" width="40" height="40" class="rounded me-2 flex-shrink-0" style="object-fit:cover;">';
          else html += '<span class="me-2 flex-shrink-0 d-flex align-items-center justify-content-center rounded bg-dark" style="width:40px;height:40px;"><i class="fas fa-cube text-white-50"></i></span>';
          html += '<span class="text-truncate">' + (title || 'Model') + '</span>';
          html += '</button></li>';
        });
        html += '</ul>';
        list3d.innerHTML = html;
        list3d.querySelectorAll('.mv-3d-item').forEach(function(btn) {
          btn.addEventListener('click', function() {
            var glb = btn.getAttribute('data-glb') || '';
            var thumb = btn.getAttribute('data-thumb') || '';
            var title = btn.getAttribute('data-title') || '';
            var created = btn.getAttribute('data-created') || '';
            if (!glb) return;
            var viewerUrl = buildViewerUrl({ model: glb, thumb: thumb, title: title, description: created ? 'Created ' + created : '' });
            setViewer(viewerUrl, glb, 'Download GLB', viewerUrl, created ? '3D Lab · ' + created : title);
          });
        });
      })
      .catch(function() {
        list3d.innerHTML = '<p class="text-white-50 small mb-0">Could not load. <a href="/labs?tool=3d_vertex">Open 3D Vertex</a></p>';
      });
  }

  // —— Upload GLB ——
  if (uploadInput) {
    uploadInput.addEventListener('change', function() {
      clearError();
      var file = uploadInput.files && uploadInput.files[0];
      if (!file) {
        setViewer(null, null, null, null, 'Select a model or upload a GLB.');
        return;
      }
      var name = (file.name || '').toLowerCase();
      var ok = name.endsWith('.glb') || file.type === 'model/gltf-binary' || file.type === 'application/octet-stream';
      if (!ok) {
        showError('Please choose a .glb file.');
        uploadInput.value = '';
        return;
      }
      if (uploadFilename) { uploadFilename.textContent = file.name; uploadFilename.style.display = 'block'; }
      if (uploadClear) uploadClear.style.display = 'inline-block';

      var fd = new FormData();
      fd.append('glb_file', file);

      fetch(UPLOAD_API, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r) { return r.json().catch(function() { return { ok: false, error: { message: 'Invalid response.' } }; }); })
        .then(function(res) {
          if (!res.ok || !res.data || !res.data.url) {
            var msg = (res.error && res.error.message) ? res.error.message : 'Upload failed.';
            showError(msg);
            return;
          }
          var url = res.data.url;
          var abs = url.indexOf('/') === 0 ? (BASE + url) : url;
          var viewerUrl = buildViewerUrl({ model: abs, title: file.name });
          setViewer(viewerUrl, abs, 'Download GLB', viewerUrl, 'Uploaded: ' + file.name, file.name);
        })
        .catch(function() {
          showError('Upload failed.');
        });
    });
  }
  if (uploadClear) {
    uploadClear.addEventListener('click', function() {
      setViewer(null, null, null, null, 'Select a model or upload a GLB.');
      if (uploadInput) uploadInput.value = '';
      if (uploadFilename) { uploadFilename.textContent = ''; uploadFilename.style.display = 'none'; }
      if (uploadClear) uploadClear.style.display = 'none';
    });
  }

  // Ensure Model Viewer action buttons always work even if other handlers cancel clicks.
  if (openFullBtn) {
    openFullBtn.addEventListener('click', function(e) {
      if (!openFullBtn.href || openFullBtn.href === '#' ||
          openFullBtn.classList.contains('disabled') ||
          openFullBtn.getAttribute('aria-disabled') === 'true') {
        return;
      }
      e.preventDefault();
      e.stopPropagation();
      var href = openFullBtn.href;
      try {
        var w = window.open(href, '_blank', 'noopener');
        if (w) w.opener = null;
      } catch (err) {
        window.location.href = href;
      }
    });
  }

  if (downloadBtn) {
    downloadBtn.addEventListener('click', function(e) {
      if (!downloadBtn.href || downloadBtn.href === '#' ||
          downloadBtn.classList.contains('disabled') ||
          downloadBtn.getAttribute('aria-disabled') === 'true') {
        return;
      }
      e.preventDefault();
      e.stopPropagation();
      var href = downloadBtn.href;
      try {
        var w = window.open(href, '_blank');
        if (w) w.opener = null;
      } catch (err) {
        window.location.href = href;
      }
    });
  }

  loadSource3dJobIfAny();
  loadSourceJobIfAny();
})();
</script>
