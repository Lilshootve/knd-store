/**
 * KND Labs - Lazy-load recent jobs after DOMContentLoaded.
 * Call LabsLazyHistory.load({ tool: 'text2img', limit: 5, toolLabel: 'Canvas', hasProviderFilter: true })
 * or LabsLazyHistory.load({ tool: 'upscale', limit: 5, toolLabel: 'Upscale', hasProviderFilter: false })
 */
(function() {
  'use strict';

  var RECENT_JOBS_LIMIT = 5;
  var FALLBACK_MSG = 'Could not load recent jobs. You can still generate.';

  function formatDate(str) {
    try {
      var d = new Date(str);
      return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    } catch (e) { return ''; }
  }

  function showFallback(placeholderEl, gridEl) {
    var msg = '<p class="knd-muted small mb-0">' + FALLBACK_MSG + '</p>';
    if (placeholderEl) { placeholderEl.style.display = ''; placeholderEl.innerHTML = msg; }
    if (gridEl) gridEl.innerHTML = msg;
  }

  function load(cfg) {
    var tool = cfg.tool || 'text2img';
    var limit = Math.min(50, Math.max(1, parseInt(cfg.limit, 10) || RECENT_JOBS_LIMIT));
    var toolLabel = cfg.toolLabel || 'Canvas';
    var hasProviderFilter = !!cfg.hasProviderFilter;
    var provider = (typeof cfg.provider === 'string') ? cfg.provider : '';

    var url = '/api/labs/jobs.php?tool=' + encodeURIComponent(tool) + '&limit=' + limit;
    if (provider) url += '&provider=' + encodeURIComponent(provider);

    var t0 = performance.now();
    if (typeof performance !== 'undefined' && performance.mark) {
      performance.mark('labs-recent-jobs-fetch-start');
    }

    fetch(url, { credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        var t1 = performance.now();
        if (typeof performance !== 'undefined' && performance.mark) {
          performance.mark('labs-recent-jobs-fetch-end');
          try { performance.measure('labs-recent-jobs-query', 'labs-recent-jobs-fetch-start', 'labs-recent-jobs-fetch-end'); } catch (e) {}
        }
        if (typeof console !== 'undefined' && console.log) {
          console.log('[Labs] recent jobs fetch+parse: ' + (t1 - t0).toFixed(0) + 'ms');
        }
        if (!d.ok || !d.data || !d.data.jobs) return;
        var jobs = d.data.jobs;

        var listEl = document.getElementById('labs-recent-list');
        var listParent = listEl ? listEl.parentElement : null;
        var filterEl = document.getElementById('labs-recent-filter');
        var gridEl = document.getElementById('labs-recent-creations-grid');
        var gridParent = gridEl ? gridEl.parentElement : null;

        var placeholderEl = document.getElementById('labs-history-sidebar-placeholder');
        if (jobs.length === 0) {
          if (placeholderEl) {
            placeholderEl.style.display = '';
            placeholderEl.innerHTML = '<p class="knd-muted small mb-0">Submit to generate</p>';
          }
          if (listEl) listEl.style.display = 'none';
          if (gridParent && gridEl) {
            gridEl.innerHTML = '<p class="knd-muted small mb-0">Generate your first image to see it here.</p>';
          }
          return;
        }
        if (placeholderEl) placeholderEl.style.display = 'none';
        if (listEl) listEl.style.display = '';

        if (listEl && listParent) {
          var filterHtml = '';
          if (hasProviderFilter && tool === 'text2img' && !document.getElementById('labs-recent-filter')) {
            filterHtml = '<select id="labs-recent-filter" class="knd-select form-select form-select-sm mb-3" style="width:100%;">' +
              '<option value="" ' + (provider === '' ? 'selected' : '') + '>All</option>' +
              '<option value="local" ' + (provider === 'local' ? 'selected' : '') + '>Local</option>' +
              '<option value="runpod" ' + (provider === 'runpod' ? 'selected' : '') + '>RunPod</option>' +
              '<option value="failed">Failed</option></select>';
            if (placeholderEl) placeholderEl.insertAdjacentHTML('beforebegin', filterHtml);
            else if (listParent) listParent.insertAdjacentHTML('beforeend', filterHtml);
            var sel = document.getElementById('labs-recent-filter');
            if (sel) sel.addEventListener('change', function() {
              var v = sel.value;
              window.location.href = window.location.pathname + (v ? '?provider=' + encodeURIComponent(v) : '');
            });
          }
          var ul = document.createElement('ul');
          ul.className = 'list-unstyled mb-0';
          ul.id = 'labs-recent-list';
          jobs.slice(0, RECENT_JOBS_LIMIT).forEach(function(j) {
            var jid = j.job_id || j.id;
            var imgUrl = j.image_url || ('/api/labs/image.php?job_id=' + jid);
            var status = j.status || 'pending';
            var sc = status === 'done' ? 'success' : (status === 'failed' ? 'danger' : 'warning');
            var li = document.createElement('li');
            li.className = 'labs-recent-item d-flex align-items-center justify-content-between py-2 border-bottom border-secondary flex-wrap gap-2';
            li.setAttribute('data-job-id', jid);
            li.setAttribute('data-provider', j.provider || '');
            li.setAttribute('data-status', status);
            var thumb = (status === 'done' && imgUrl)
              ? '<div class="labs-recent-thumb" style="position:relative;"><img src="' + imgUrl + '" alt="" class="rounded" style="width:48px;height:48px;object-fit:cover;" loading="lazy" decoding="async" onerror="this.parentElement.classList.add(\'labs-img-error\')"></div><a href="' + imgUrl + '" class="btn btn-sm btn-outline-success" target="_blank" download><i class="fas fa-download"></i></a>'
              : '';
            li.innerHTML = '<span class="text-white-50 small">' + formatDate(j.created_at) + '</span>' +
              '<span class="badge bg-' + sc + '">' + status + '</span>' + thumb +
              '<button type="button" class="btn btn-sm btn-outline-secondary labs-view-details" data-job-id="' + jid + '" data-tool="' + (j.tool || '').replace(/"/g, '&quot;') + '">View details</button>';
            ul.appendChild(li);
          });
          var oldList = document.getElementById('labs-recent-list');
          if (oldList) {
            oldList.innerHTML = ul.innerHTML;
            oldList.id = 'labs-recent-list';
            oldList.className = ul.className;
          } else if (listParent) listParent.appendChild(ul);
        }

        if (gridEl && gridParent) {
          var tRender0 = performance.now();
          gridEl.innerHTML = '';
          jobs.slice(0, RECENT_JOBS_LIMIT).forEach(function(j) {
            var jid = j.job_id || j.id;
            var imgUrl = j.image_url || ('/api/labs/image.php?job_id=' + jid);
            var status = j.status || 'pending';
            var statusClass = status === 'done' ? 'knd-badge-success' : (status === 'failed' ? 'knd-badge--danger' : 'knd-badge--warning');
            var imgHtml = (status === 'done' && imgUrl)
              ? '<img src="' + imgUrl + '" alt="" loading="lazy" decoding="async" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';"><span class="knd-showcase-card__placeholder" style="display:none"><i class="fas fa-image"></i></span>'
              : '<span class="knd-showcase-card__placeholder"><i class="fas fa-image"></i></span>';
            var card = document.createElement('div');
            card.className = 'knd-showcase-card labs-creation-card';
            card.setAttribute('data-job-id', jid);
            card.innerHTML = '<div class="knd-showcase-card__img">' + imgHtml + '</div>' +
              '<div class="knd-showcase-card__body">' +
              '<div class="d-flex justify-content-between align-items-center mb-1">' +
              '<span class="knd-showcase-card__title">' + toolLabel + '</span>' +
              '<span class="knd-badge ' + statusClass + '">' + status + '</span></div>' +
              '<div class="knd-showcase-card__meta">' + formatDate(j.created_at) + '</div>' +
              '<button type="button" class="btn btn-sm knd-btn-secondary mt-2 w-100 labs-view-details" data-job-id="' + jid + '" data-tool="' + (j.tool || '').replace(/"/g, '&quot;') + '"><i class="fas fa-info-circle me-1"></i>Details</button>' +
              '</div>';
            gridEl.appendChild(card);
          });
          var tRender1 = performance.now();
          if (typeof console !== 'undefined' && console.log) {
            console.log('[Labs] recent jobs render: ' + (tRender1 - tRender0).toFixed(0) + 'ms, items: ' + jobs.length);
          }
        }
      })
      .catch(function(err) {
        var tErr = performance.now();
        if (typeof console !== 'undefined' && console.warn) {
          console.warn('[Labs] recent jobs failed:', (tErr - t0).toFixed(0) + 'ms', err);
        }
        var ph = document.getElementById('labs-history-sidebar-placeholder');
        var gr = document.getElementById('labs-recent-creations-grid');
        showFallback(ph, gr);
      });
  }

  window.LabsLazyHistory = { load: load };
})();
