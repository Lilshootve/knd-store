/**
 * KND Labs - ComfyUI frontend (Orbitron vibe)
 * Shared logic for text2img, upscale, character-lab.
 */
(function() {
  'use strict';

  var POLL_INTERVAL = 2000;
  var POLL_MAX_COUNT = 150;
  var API_GENERATE = '/api/labs/generate.php';
  var API_STATUS = '/api/labs/status.php';
  var API_JOBS = '/api/labs/jobs.php';
  var API_JOB = '/api/labs/job.php';
  var API_IMAGE = '/api/labs/image.php';
  var API_PRICING = '/api/labs/pricing.php';
  var API_PREFERENCE = '/api/labs/preference.php';
  var API_3D_STATUS = '/api/labs/3d-lab/status.php';
  var API_3D_DOWNLOAD = '/api/labs/3d-lab/download.php';
  var API_CHARACTER_STATUS = '/api/character-lab/status.php';
  var API_CHARACTER_DOWNLOAD = '/api/character-lab/download.php';
  var BASE_URL = (typeof window !== 'undefined' && window.location && window.location.origin) ? window.location.origin : '';
  var UI_STATES = ['idle', 'queued', 'processing', 'done', 'failed'];

  var KNDLabs = {
    config: {},
    pollTimer: null,
    currentJobId: null,
    lastJobSeed: null,
    lastJobPayload: null,
    pricing: null,

    init: function(cfg) {
      this.config = cfg || {};
      this.config.balanceEl = this.config.balanceEl
        ? document.querySelector(this.config.balanceEl)
        : null;
      var self = this;
      function doBind() {
        self.bindForm();
          self.bindPresets();
          self.bindPresetsNegative();
          self.bindRetry();
          self.bindRegenerate();
          self.bindVariations();
          self.bindPricingUpdates();
          self.bindPromptValidation();
          self.bindAdvancedToggle();
          self.bindViewDetails();
          self.bindLabsActionLinks();
          self.bindRecentFilter();
          self.bindPrivateCheck();
          self.bindUseLastPrompt();
          self.bindBackgroundMotion();
          self.updateCostLabel();
          self.updateBalanceAfter();
          self.updateSubmitButton();
      }
      if (typeof window !== 'undefined' && window.KND_PRICING) {
        self.pricing = window.KND_PRICING;
        doBind();
      } else {
        fetch(API_PRICING, { credentials: 'same-origin' })
          .then(function(r) { return r.json(); })
          .then(function(d) {
            if (d.ok && d.data) self.pricing = d.data;
            doBind();
          })
          .catch(function() { doBind(); });
      }
    },

    bindUseLastPrompt: function() {
      var self = this;
      var btn = document.getElementById('labs-use-last-prompt-btn');
      if (!btn) return;
      btn.addEventListener('click', function() {
        if (!self.lastJobPayload || !self.lastJobPayload.prompt) return;
        var form = document.getElementById(self.config.formId || 'labs-comfy-form');
        if (!form) return;
        var promptEl = form.querySelector('[name="prompt"]');
        var negEl = form.querySelector('[name="negative_prompt"]');
        if (promptEl) promptEl.value = self.lastJobPayload.prompt || '';
        if (negEl && self.lastJobPayload.negative_prompt) negEl.value = self.lastJobPayload.negative_prompt;
      });
    },

    getCost: function() {
      var p = this.pricing;
      var key = this.config.pricingKey || 'text2img';
      if (!p) return key === 'text2img' ? 3 : (key === 'upscale' ? 5 : (key === '3d_vertex' ? 20 : 15));
      if (key === 'text2img') {
        var q = document.getElementById(this.config.qualitySelectId);
        var qual = q ? q.value : 'standard';
        return (p.text2img && p.text2img[qual]) || 3;
      }
      if (key === 'upscale') {
        var s = document.getElementById(this.config.scaleSelectId);
        var scale = s ? s.value : '2';
        return (p.upscale && p.upscale[scale + 'x']) || (scale === '4' ? 8 : 5);
      }
      if (key === 'remove_bg') return (p.remove_bg && p.remove_bg.base) || 5;
      if (key === 'consistency') return (p.consistency && p.consistency.base) || 5;
      if (key === '3d_vertex') {
        var qv = document.getElementById('labs-vertex-quality');
        var qualv = qv ? qv.value : 'standard';
        return (p['3d_vertex'] && p['3d_vertex'][qualv]) || (qualv === 'high' ? 30 : 20);
      }
      return (p.character && p.character.base) || 15;
    },

    updateCostLabel: function() {
      var el = document.getElementById(this.config.costLabelId);
      if (el) el.textContent = 'Cost: ' + this.getCost() + ' KP';
    },

    updateBalanceAfter: function() {
      var el = document.getElementById('labs-balance-after');
      if (!el) return;
      var bal = 0;
      var balEl = this.config.balanceEl ? (typeof this.config.balanceEl === 'string' ? document.querySelector(this.config.balanceEl) : this.config.balanceEl) : null;
      if (balEl) {
        var m = balEl.textContent.match(/[\d,]+/);
        if (m) bal = parseInt(m[0].replace(/,/g, ''), 10) || 0;
      }
      var cost = this.getCost();
      var after = Math.max(0, bal - cost);
      el.textContent = 'Balance after: ' + after.toLocaleString() + ' KP';
    },

    bindPricingUpdates: function() {
      var self = this;
      var q = document.getElementById(this.config.qualitySelectId);
      if (q) q.addEventListener('change', function() { self.updateCostLabel(); self.updateBalanceAfter(); });
      var s = document.getElementById(this.config.scaleSelectId);
      if (s) s.addEventListener('change', function() { self.updateCostLabel(); self.updateBalanceAfter(); });
    },

    updateBalance: function(kp) {
      var sel = this.config.balanceEl;
      var el = typeof sel === 'string' ? document.querySelector(sel) : sel;
      var val = parseInt(kp, 10).toLocaleString();
      if (el) el.innerHTML = '<i class="fas fa-coins me-1"></i>' + val + ' KP';
      var studioBal = document.getElementById('knd-labs-studio-balance');
      if (studioBal) studioBal.textContent = val;
    },

    showStatus: function(jobId) {
      this.currentJobId = jobId;
      var panel = document.getElementById('labs-status-panel');
      var text = document.getElementById('labs-status-text');
      var preview = document.getElementById('labs-result-preview');
      var actions = document.getElementById('labs-result-actions');
      var errorEl = document.getElementById('labs-error-msg');

      if (panel) panel.style.display = 'block';
      if (actions) actions.style.display = 'none';
      if (errorEl) errorEl.style.display = 'none';
      if (text) text.textContent = 'Processing...';
      this.setUiState('queued');
      this.setPreviewState('processing');
      this.updateStepper('queued');

      var self = this;
      var pollCount = 0;
      function poll() {
        pollCount++;
        if (pollCount > POLL_MAX_COUNT) {
          self.stopPoll();
          self.setUiState('failed');
          self.setPreviewState('failed');
          if (panel) panel.style.display = 'none';
          if (errorEl) {
            errorEl.textContent = 'Taking too long. Ensure ComfyUI is running and config/comfyui.php has COMFYUI_BASE_URL set.';
            errorEl.style.display = 'block';
          }
          self.currentJobId = null;
          return;
        }
        fetch(API_STATUS + '?job_id=' + encodeURIComponent(jobId))
          .then(function(r) { return r.json(); })
          .then(function(d) {
            if (!d.ok || !d.data) return;
            var st = d.data.status;
            var msg = st.charAt(0).toUpperCase() + st.slice(1);
            var stage = d.data.stage || st;
            self.updateStepper(stage);
            if (st === 'queued') {
              self.setUiState('queued');
              var pos = d.data.queue_position;
              var eta = d.data.eta_seconds;
              msg = 'En cola';
              if (typeof pos === 'number') msg += ' (# ' + pos + ')';
              if (typeof eta === 'number' && eta > 0) {
                var m = Math.floor(eta / 60);
                var s = eta % 60;
                msg += ' ~ETA ' + (m > 0 ? m + 'm ' : '') + s + 's';
              }
            } else if (st === 'processing') {
              self.setUiState('processing');
              msg = 'Generando…';
            }
            if (text) text.textContent = msg;

            if (st === 'failed') {
              self.stopPoll();
              self.setUiState('failed');
              self.setPreviewState('failed');
              if (panel) panel.style.display = 'none';
              if (errorEl) {
                errorEl.textContent = d.data.error_message || 'Failed';
                errorEl.style.display = 'block';
              }
              self.currentJobId = null;
            } else if (st === 'done' || st === 'completed') {
              self.stopPoll();
              if (panel) panel.style.display = 'none';
              self.setUiState('done');
              self.setPreviewState('done');
              self.updateStepper('done');
              var proxyPreview = API_IMAGE + '?job_id=' + encodeURIComponent(jobId);
              var proxyDownload = API_IMAGE + '?job_id=' + encodeURIComponent(jobId) + '&download=1';
              var useInputUrl = '/labs?tool=upscale&source_job_id=' + encodeURIComponent(jobId);
              if (preview) {
                var tool = self.config.jobType || 'text2img';
                var mode = 'style';
                if (tool === '3d_vertex') {
                  preview.innerHTML = '<model-viewer src="' + proxyPreview + '" camera-controls auto-rotate exposure="0.9" style="width:100%;height:400px;background:var(--bg-main);border-radius:12px;"></model-viewer>';
                } else {
                  preview.innerHTML = '<img src="' + proxyPreview + '" alt="Result" class="img-fluid rounded" style="max-height:400px;" data-job-id="' + jobId + '" data-job-tool="' + tool + '" data-job-mode="' + mode + '">';
                }
                window.requestAnimationFrame(function() {
                  preview.classList.add('labs-result-ready');
                });
              }
              var dlBtn = document.getElementById('labs-download-btn');
              if (dlBtn) {
                dlBtn.href = proxyDownload;
                dlBtn.download = (self.config.jobType === '3d_vertex') ? 'knd-labs-output.glb' : 'knd-labs-output.png';
              }
              var useBtn = document.getElementById('labs-use-input-btn');
              if (useBtn) useBtn.href = useInputUrl;
              var removeBgBtn = document.getElementById('labs-remove-bg-btn');
              if (removeBgBtn) removeBgBtn.href = '/labs?tool=remove-bg&source_job_id=' + encodeURIComponent(jobId);
              var send3dBtn = document.getElementById('labs-send-3d-btn');
              if (send3dBtn) send3dBtn.href = '/labs?tool=3d_vertex&source_job_id=' + encodeURIComponent(jobId);
              var viewModelBtn = document.getElementById('labs-view-model-btn');
              if (viewModelBtn && self.config.jobType === '3d_vertex') {
                viewModelBtn.href = '/labs?tool=model_viewer&source_job_id=' + encodeURIComponent(jobId);
              }
              if (actions) actions.style.display = 'block';
              if (d.data.available_after !== undefined) self.updateBalance(d.data.available_after);
              self.updateBalanceAfter();
              self.refreshRecentLists();
              self.currentJobId = null;
              self.lastJobId = jobId;
              var imgEl = preview ? preview.querySelector('img[data-job-id]') : null;
              fetch(API_JOB + '?job_id=' + encodeURIComponent(jobId), { credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(jobData) {
                  if (jobData.ok && jobData.data) {
                    self.lastJobSeed = jobData.data.seed;
                    self.lastJobPayload = jobData.data;
                    var useLastBtn = document.getElementById('labs-use-last-prompt-btn');
                    if (useLastBtn && jobData.data.prompt) { useLastBtn.style.display = 'inline-block'; }
                    if (imgEl && jobData.data.tool === 'consistency' && jobData.data.mode) {
                      imgEl.setAttribute('data-job-mode', jobData.data.mode);
                    }
                    self.renderImageDetails(jobData.data);
                    if (typeof self.onJobDone === 'function') self.onJobDone(jobId, jobData.data);
                  }
                });
            }
          })
          .catch(function() {});
      }

      this.stopPoll();
      poll();
      this.pollTimer = setInterval(poll, POLL_INTERVAL);
    },

    refreshRecentLists: function() {
      if (!window.LabsLazyHistory || typeof window.LabsLazyHistory.load !== 'function') return;
      var tool = this.config.jobType || 'text2img';
      var labels = {
        'text2img': 'Canvas',
        'upscale': 'Upscale',
        'remove-bg': 'Remove Background',
        'consistency': 'Consistency',
        'texture': 'Texture Lab',
        '3d_vertex': '3D Vertex',
        'character': 'Character Lab'
      };
      var hasProviderFilter = tool === 'text2img';
      window.LabsLazyHistory.load({
        tool: tool,
        limit: 5,
        toolLabel: labels[tool] || 'Labs',
        hasProviderFilter: hasProviderFilter
      });
    },

    stopPoll: function() {
      if (this.pollTimer) {
        clearInterval(this.pollTimer);
        this.pollTimer = null;
      }
    },

    setUiState: function(state) {
      var app = document.getElementById('ln-app') || document.body;
      if (!app) return;
      UI_STATES.forEach(function(s) {
        app.classList.remove('labs-ui-' + s);
      });
      if (UI_STATES.indexOf(state) >= 0) {
        app.classList.add('labs-ui-' + state);
      } else {
        app.classList.add('labs-ui-idle');
      }
    },

    setPreviewState: function(state) {
      var preview = document.getElementById('labs-result-preview');
      if (!preview) return;
      UI_STATES.forEach(function(s) {
        preview.classList.remove('is-' + s);
      });
      if (UI_STATES.indexOf(state) >= 0) preview.classList.add('is-' + state);
      preview.classList.remove('labs-result-ready');
      if (state === 'done') preview.classList.add('labs-result-ready');
    },

    /**
     * Restore empty-state HTML from <template id="knd-labs-studio-placeholder-tmpl"> when present (per-tool copy from PHP).
     */
    restorePlaceholderPreview: function() {
      var preview = document.getElementById('labs-result-preview');
      if (!preview) return;
      var tmpl = document.getElementById('knd-labs-studio-placeholder-tmpl');
      if (tmpl && tmpl.innerHTML && String(tmpl.innerHTML).trim() !== '') {
        preview.innerHTML = tmpl.innerHTML;
      } else {
        preview.innerHTML = '<div id="labs-placeholder-tips" class="labs-placeholder-tips"><i class="fas fa-lightbulb fa-2x text-white-50 mb-2"></i><p class="text-white-50 mb-1 small">Use 1 subject + 1 style + 1 lighting</p><p class="text-white-50 mb-0 small">e.g. &quot;Warrior, oil painting, golden hour&quot;</p></div>';
      }
      this.setPreviewState('idle');
      this.setUiState('idle');
    },

    updateStepper: function(stage) {
      var dots = document.querySelectorAll('.labs-stepper-dot');
      var steps = ['queued', 'picked', 'generating', 'done'];
      var idx = steps.indexOf(stage);
      if (idx < 0) idx = 0;
      dots.forEach(function(dot, i) {
        var s = dot.getAttribute('data-step');
        var i = steps.indexOf(s);
        dot.classList.toggle('active', i <= idx);
        dot.classList.toggle('current', s === stage);
      });
      if (stage === 'queued') this.setUiState('queued');
      else if (stage === 'picked' || stage === 'generating') this.setUiState('processing');
      else if (stage === 'done') this.setUiState('done');
    },

    buildConsistencyFormData: function(formEl) {
      var fd = new FormData();
      fd.set('mode', (formEl.querySelector('[name="mode"]')?.value || 'style'));
      fd.set('reference_source', (formEl.querySelector('[name="reference_source"]:checked')?.value || 'upload'));
      var refJob = formEl.querySelector('[name="reference_job_id"]');
      if (refJob && refJob.value) fd.set('reference_job_id', refJob.value);
      var refFile = formEl.querySelector('[name="reference_image"]');
      if (refFile && refFile.files && refFile.files[0]) fd.set('reference_image', refFile.files[0]);
      fd.set('base_prompt', (formEl.querySelector('[name="base_prompt"]')?.value || '').trim());
      fd.set('scene_prompt', (formEl.querySelector('[name="scene_prompt"]')?.value || '').trim());
      fd.set('negative_prompt', (formEl.querySelector('[name="negative_prompt"]')?.value || 'ugly, blurry, low quality'));
      fd.set('lock_seed', formEl.querySelector('[name="lock_seed"]')?.checked ? '1' : '0');
      fd.set('inherit_model', formEl.querySelector('[name="inherit_model"]')?.checked ? '1' : '0');
      fd.set('inherit_resolution', formEl.querySelector('[name="inherit_resolution"]')?.checked ? '1' : '0');
      fd.set('inherit_sampling', formEl.querySelector('[name="inherit_sampling"]')?.checked ? '1' : '0');
      fd.set('width', formEl.querySelector('[name="width"]')?.value || 1024);
      fd.set('height', formEl.querySelector('[name="height"]')?.value || 1024);
      fd.set('steps', formEl.querySelector('[name="steps"]')?.value || 28);
      fd.set('cfg', formEl.querySelector('[name="cfg"]')?.value || 7);
      fd.set('sampler', formEl.querySelector('[name="sampler"]')?.value || 'dpmpp_2m');
      var seedEl = formEl.querySelector('[name="seed"]');
      if (seedEl && seedEl.value) fd.set('seed', seedEl.value);
      var modelEl = formEl.querySelector('[name="model"]');
      if (modelEl && modelEl.value) fd.set('model', modelEl.value);
      return fd;
    },

    submitForm: function(formEl) {
      if (this._submitting) return;
      this._submitting = true;

      var tool = this.config.jobType || formEl.querySelector('[name="tool"]')?.value || 'text2img';
      var apiUrl = API_GENERATE;
      var fd;
      if (tool === 'consistency' && this.config.apiConsistency) {
        fd = this.buildConsistencyFormData(formEl);
        apiUrl = this.config.apiConsistency;
      } else {
        fd = new FormData(formEl);
        fd.set('tool', tool);
      }

      var submitBtn = formEl.querySelector('[type="submit"]');
      if (submitBtn) submitBtn.disabled = true;

      var self = this;
      var preview = document.getElementById('labs-result-preview');
      this.setUiState('processing');
      this.setPreviewState('processing');
      if (preview) {
        preview.innerHTML = '<div class="labs-processing-anim">' +
          '<div class="labs-processing-rings">' +
          '<div class="labs-processing-scan"></div>' +
          '<div class="labs-processing-ring"></div><div class="labs-processing-ring"></div><div class="labs-processing-ring"></div>' +
          '<div class="labs-processing-orbit"><div class="labs-processing-dot"></div></div>' +
          '<div class="labs-processing-core"></div>' +
          '</div>' +
          '<div class="labs-processing-bar"><div class="labs-processing-bar-fill"></div></div>' +
          '<span class="labs-processing-label">Processing...</span>' +
          '</div>';
      }

        fetch(apiUrl, {
        method: 'POST',
        body: fd,
        cache: 'no-store',
        credentials: 'same-origin'
      })
        .then(function(r) {
          return r.json().catch(function() { return { ok: false, error: { message: 'Invalid response from server' } }; })
            .then(function(d) {
              if (r.status === 429 && d.error && d.error.code === 'RATE_LIMIT') {
                d.error.message = d.error.message || 'Too many active jobs. Wait for current ones to finish.';
              }
              return d;
            });
        })
        .then(function(d) {
          if (submitBtn) submitBtn.disabled = false;
          self._submitting = false;
            if (d.ok && d.data && d.data.job_id) {
            if (d.data.available_after !== undefined) self.updateBalance(d.data.available_after);
            if (self.config.keepFormOnSuccess !== false) { /* keep form (prompt, etc.) for variations */ } else { formEl.reset(); }
            self.updateCostLabel();
            self.updateBalanceAfter();
            self.updateSubmitButton();
            self.showStatus(d.data.job_id);
          } else {
            var msg = (d.error && d.error.message) ? d.error.message : 'Error';
            if (typeof kndToast !== 'undefined') kndToast(msg, 'error');
            else alert(msg);
            self.restorePlaceholderPreview();
            var errEl = document.getElementById('labs-error-msg');
            if (errEl) { errEl.textContent = msg; errEl.style.display = 'block'; }
          }
        })
        .catch(function(err) {
          if (submitBtn) submitBtn.disabled = false;
          self._submitting = false;
          self.restorePlaceholderPreview();
          var msg = (err && err.message) ? err.message : 'Network error. Check console.';
          if (typeof kndToast !== 'undefined') kndToast(msg, 'error');
          else alert(msg);
        });
    },

    bindForm: function() {
      var form = document.getElementById(this.config.formId || 'labs-comfy-form');
      if (!form) return;

      var self = this;
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var prompt = form.querySelector('[name="prompt"]');
        var textureMode = form.querySelector('[name="texture_mode"]') ? form.querySelector('[name="texture_mode"]').value : 'text';
        var skipPromptCheck = self.config.jobType === 'upscale' || self.config.jobType === 'remove-bg' || self.config.jobType === '3d_vertex' || (self.config.jobType === 'texture' && textureMode === 'image');
        if (prompt && !skipPromptCheck) {
          if (!prompt.value || prompt.value.trim().length === 0) {
            if (typeof kndToast !== 'undefined') kndToast('Prompt is required', 'error');
            else alert('Prompt is required');
            return;
          }
        }
        var imageInput = form.querySelector('[name="image"]');
        if (imageInput && (self.config.jobType === 'upscale' || self.config.jobType === 'remove-bg' || self.config.jobType === '3d_vertex')) {
          if (!imageInput.files || !imageInput.files.length) {
            if (typeof kndToast !== 'undefined') kndToast('Image is required', 'error');
            else alert('Image is required');
            return;
          }
        }
        var ipEn = form.querySelector('[name="ipadapter_enabled"]');
        if (ipEn && ipEn.checked && self.config.jobType === 'text2img') {
          var ipImg = form.querySelector('[name="ipadapter_image"]');
          if (!ipImg || !ipImg.files || !ipImg.files.length) {
            if (typeof kndToast !== 'undefined') kndToast('Reference image required when IPAdapter is enabled', 'error');
            else alert('Reference image required when IPAdapter is enabled');
            return;
          }
        }
        var cnEn = form.querySelector('[name="controlnet_enabled"]');
        if (cnEn && cnEn.checked && self.config.jobType === 'text2img') {
          var cnImg = form.querySelector('[name="controlnet_image"]');
          if (!cnImg || !cnImg.files || !cnImg.files.length) {
            if (typeof kndToast !== 'undefined') kndToast('Control image required when ControlNet is enabled', 'error');
            else alert('Control image required when ControlNet is enabled');
            return;
          }
        }
        if (self.config.jobType === 'texture') {
          if (textureMode === 'image' || textureMode === 'image_prompt') {
            var texImg = form.querySelector('[name="image"]');
            if (!texImg || !texImg.files || !texImg.files.length) {
              if (typeof kndToast !== 'undefined') kndToast(textureMode === 'image' ? 'Image is required for Image to Texture.' : 'Image is required for Image + Prompt.', 'error');
              else alert('Image is required');
              return;
            }
          }
          if (textureMode === 'image_prompt' && (!prompt || !prompt.value || prompt.value.trim().length === 0)) {
            if (typeof kndToast !== 'undefined') kndToast('Prompt is required for Image + Prompt mode.', 'error');
            else alert('Prompt is required');
            return;
          }
        }
        if (self.config.jobType === 'consistency') {
          var refSource = form.querySelector('[name="reference_source"]:checked');
          var refVal = refSource ? refSource.value : 'upload';
          if (refVal === 'recent') {
            var refJob = form.querySelector('[name="reference_job_id"]');
            if (!refJob || !refJob.value || refJob.value === '') {
              if (typeof kndToast !== 'undefined') kndToast('Select a reference job from recent.', 'error');
              else alert('Select a reference job from recent.');
              return;
            }
          } else {
            var refFile = form.querySelector('[name="reference_image"]');
            if (!refFile || !refFile.files || !refFile.files.length) {
              if (typeof kndToast !== 'undefined') kndToast('Upload a reference image.', 'error');
              else alert('Upload a reference image.');
              return;
            }
          }
          var baseP = form.querySelector('[name="base_prompt"]');
          var sceneP = form.querySelector('[name="scene_prompt"]');
          var baseVal = baseP ? (baseP.value || '').trim() : '';
          var sceneVal = sceneP ? (sceneP.value || '').trim() : '';
          if (!baseVal && !sceneVal) {
            if (typeof kndToast !== 'undefined') kndToast('Base prompt or Scene prompt is required.', 'error');
            else alert('Base prompt or Scene prompt is required.');
            return;
          }
        }
        self.submitForm(form);
      });
    },

    bindPresets: function() {
      var form = document.getElementById(this.config.formId || 'labs-comfy-form');
      if (!form) return;
      var self = this;
      form.querySelectorAll('.preset-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
          var p = form.querySelector('[name="prompt"]');
          if (p) p.value = btn.getAttribute('data-prompt') || '';
          var n = form.querySelector('[name="negative_prompt"]');
          if (n) n.value = btn.getAttribute('data-negative') || n.value;
          var steps = form.querySelector('[name="steps"]');
          if (steps && btn.getAttribute('data-steps')) steps.value = btn.getAttribute('data-steps');
          var cfg = form.querySelector('[name="cfg"]');
          if (cfg && btn.getAttribute('data-cfg')) cfg.value = btn.getAttribute('data-cfg');
          var sampler = form.querySelector('[name="sampler_name"]');
          if (sampler && btn.getAttribute('data-sampler')) sampler.value = btn.getAttribute('data-sampler');
          var w = form.querySelector('[name="width"]');
          if (w && btn.getAttribute('data-width')) w.value = btn.getAttribute('data-width');
          var h = form.querySelector('[name="height"]');
          if (h && btn.getAttribute('data-height')) h.value = btn.getAttribute('data-height');
          var sum = document.getElementById('labs-preset-summary');
          if (sum) {
            var wv = btn.getAttribute('data-width') || '1024';
            var hv = btn.getAttribute('data-height') || '1024';
            var cfgv = btn.getAttribute('data-cfg') || '7.5';
            var stepsv = btn.getAttribute('data-steps') || '25';
            var samp = btn.getAttribute('data-sampler') || 'euler';
            sum.textContent = 'Preset: ' + wv + '\u00D7' + hv + ', CFG ' + cfgv + ', Steps ' + stepsv + ', ' + samp;
          }
          self.updateCostLabel();
          self.updateBalanceAfter();
          self.updateSubmitButton();
        });
      });
    },

    bindPresetsNegative: function() {
      var form = document.getElementById(this.config.formId || 'labs-comfy-form');
      if (!form) return;
      form.querySelectorAll('.preset-neg-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
          var n = form.querySelector('[name="negative_prompt"]');
          if (!n) return;
          var val = (btn.getAttribute('data-value') || '').trim();
          if (val) n.value = val;
        });
      });
    },

    bindPromptValidation: function() {
      var self = this;
      if (self.config.jobType === 'upscale' || self.config.jobType === 'remove-bg' || self.config.jobType === '3d_vertex') return;
      var prompt = document.getElementById('labs-prompt-input') || document.querySelector('[name="prompt"]');
      var hint = document.getElementById('labs-prompt-hint');
      function check() {
        var v = (prompt && prompt.value ? prompt.value : '').trim();
        if (hint) {
          if (v.length === 0) hint.textContent = 'Enter a prompt';
          else if (v.length < 10) hint.textContent = 'Add style, environment or lighting for better results';
          else hint.textContent = '';
        }
        self.recomputeGenerateState();
      }
      if (prompt) {
        prompt.addEventListener('input', check);
        prompt.addEventListener('change', check);
        prompt.addEventListener('keyup', check);
      }
      var model = document.getElementById('labs-model-select') || document.querySelector('#labs-comfy-form [name="model"]');
      var widthSel = document.getElementById('labs-width-select') || document.querySelector('#labs-comfy-form [name="width"]');
      var heightSel = document.getElementById('labs-height-select') || document.querySelector('#labs-comfy-form [name="height"]');
      [model, widthSel, heightSel].forEach(function(el) {
        if (el) {
          el.addEventListener('input', function() { self.recomputeGenerateState(); });
          el.addEventListener('change', function() { self.recomputeGenerateState(); });
        }
      });
      self.recomputeGenerateState();
    },

    recomputeGenerateState: function() {
      var form = document.getElementById(this.config.formId || 'labs-comfy-form');
      if (!form) return;
      var btn = form.querySelector('[type="submit"]') || document.getElementById('generateBtn') || document.getElementById('labs-submit-btn');
      if (this.config.jobType === 'upscale' || this.config.jobType === 'remove-bg' || this.config.jobType === '3d_vertex') {
        var img = form.querySelector('[name="image"]');
        var hasFile = img && img.files && img.files.length > 0;
        if (btn) btn.disabled = !hasFile;
        return;
      }
      // text2img: always enabled, validate on submit
      if (btn) btn.disabled = false;
    },

    updateSubmitButton: function() {
      this.recomputeGenerateState();
    },

    bindAdvancedToggle: function() {
      var adv = document.getElementById('labs-advanced');
      var toggle = document.getElementById('labs-advanced-toggle');
      if (!adv || !toggle) return;
      adv.addEventListener('shown.bs.collapse', function() {
        var icon = toggle.querySelector('i');
        if (icon) { icon.classList.remove('fa-chevron-down'); icon.classList.add('fa-chevron-up'); }
      });
      adv.addEventListener('hidden.bs.collapse', function() {
        var icon = toggle.querySelector('i');
        if (icon) { icon.classList.remove('fa-chevron-up'); icon.classList.add('fa-chevron-down'); }
      });
    },

    renderImageDetails: function(job) {
      if (!job) return;
      var panel = document.getElementById('labs-image-details-panel');
      var rowsEl = document.getElementById('labs-details-rows');
      var actionsEl = document.getElementById('labs-image-details-actions');
      if (!panel || !rowsEl) return;
      var rows = [];
      function addRow(label, value) {
        if (value != null && value !== '' && value !== '—') rows.push({ label: label, value: String(value) });
      }
      addRow('Tool', job.tool || '—');
      addRow('Status', job.status || '—');
      addRow('Model', job.model || '—');
      addRow('Seed', job.seed);
      addRow('Sampler', job.sampler_name || job.sampler || '—');
      addRow('Steps', job.steps);
      addRow('CFG', job.cfg);
      addRow('Resolution', (job.width && job.height) ? job.width + '\u00D7' + job.height : null);
      addRow('Created', job.created_at || '—');
      addRow('Job ID', job.job_id || '—');
      addRow('Cost', job.cost_kp ? job.cost_kp + ' KP' : null);
      if (job.tool === 'consistency') {
        addRow('Mode', job.mode || '—');
        addRow('Reference', job.reference_source || '—');
        addRow('Ref Job', job.reference_job_id);
      }
      rowsEl.innerHTML = rows.map(function(r) {
        var val = r.value.length > 40 ? r.value.substring(0, 37) + '...' : r.value;
        return '<div class="labs-metadata-item"><span class="labs-metadata-item__label">' + r.label + '</span><span class="labs-metadata-item__value">' + val + '</span></div>';
      }).join('');
      if (actionsEl) {
        var jid = job.job_id;
        var mode = (job.tool === 'consistency' && job.mode) ? job.mode : 'style';
        var actions = [];
        if (jid && (job.tool === 'text2img' || job.tool === 'consistency')) {
          actions.push('<a href="/labs?tool=consistency&reference_job_id=' + jid + '&mode=' + mode + '" class="btn btn-sm btn-outline-primary">' + (typeof t === 'function' ? t('labs.generate_variations', 'Generate Variations') : 'Generate Variations') + '</a>');
        }
        if (jid) actions.push('<a href="/labs?tool=upscale&source_job_id=' + jid + '" class="btn btn-sm btn-outline-secondary">Upscale</a>');
        actionsEl.innerHTML = actions.join(' ');
      }
      panel.style.display = 'block';
    },

    /**
     * Ensure all <a class="labs-action"> with valid href work on left-click (fix for
     * links that only worked via right-click "Open in new tab"). Applies to all shells.
     */
    bindLabsActionLinks: function() {
      document.addEventListener('click', function(e) {
        var link = e.target.closest('a.labs-action');
        if (!link || link.tagName !== 'A') return;
        var rawHref = link.getAttribute('href');
        if (!rawHref || rawHref === '#') return;
        if (link.classList.contains('disabled') || link.getAttribute('aria-disabled') === 'true') return;
        var href = link.href || rawHref;
        if (!href || href === '#' || href === window.location.origin + window.location.pathname + '#') return;
        e.preventDefault();
        e.stopPropagation();
        var openInNew = link.target === '_blank' || link.hasAttribute('download');
        try {
          if (openInNew) {
            var w = window.open(href, '_blank', 'noopener');
            if (w) w.opener = null;
          } else {
            window.location.href = href;
          }
        } catch (err) {
          window.location.href = href;
        }
      }, true);
    },

    bindViewDetails: function() {
      var self = this;
      var drawer = document.getElementById('labs-details-drawer');
      var backdrop = document.getElementById('labs-details-backdrop');
      var closeBtn = document.getElementById('labs-details-close');
      var drawerTitle = drawer ? drawer.querySelector('.knd-details-drawer__header h5') : null;

      function closeDrawer() {
        if (drawer) drawer.classList.remove('is-open');
        if (backdrop) backdrop.classList.remove('is-visible');
      }
      function openDrawer(title) {
        if (drawerTitle && title) drawerTitle.textContent = title;
        if (drawer) {
          drawer.classList.add('is-open');
          drawer.classList.add('is-animating-in');
          window.setTimeout(function() {
            drawer.classList.remove('is-animating-in');
          }, 260);
        }
        if (backdrop) backdrop.classList.add('is-visible');
      }

      function is3dJob(jobId, tool) {
        if (tool === '3d') return true;
        if (!jobId || typeof jobId !== 'string') return false;
        return /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(jobId.trim());
      }
      function isCharacterJob(jobId, tool) {
        if (tool === 'character') return true;
        return false;
      }

      function onEscape(e) {
        if (e.key === 'Escape' && drawer && drawer.classList.contains('is-open')) {
          closeDrawer();
        }
      }

      if (backdrop) backdrop.addEventListener('click', closeDrawer);
      if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
      document.addEventListener('keydown', onEscape);

      this._drawer = drawer;
      this._backdrop = backdrop;
      this._setDrawerTitle = function(t) { if (drawerTitle) drawerTitle.textContent = t || ''; };
      this.openJobViewer = function(jobId, toolType) {
        var body = document.getElementById('labs-details-body');
        if (!body) return;
        var jid = String(jobId || '');
        var tool = String(toolType || '').toLowerCase();
        if (!jid) return;
        self.setDrawerBodyLoading(body, true);
        if (self._drawer) self._drawer.classList.add('is-open');
        if (self._backdrop) self._backdrop.classList.add('is-visible');
        self._setDrawerTitle('View details');
        if (is3dJob(jid, tool)) {
          fetch(API_3D_STATUS + '?id=' + encodeURIComponent(jid), { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
              if (!d.ok || !d.data) { self.setDrawerBodyLoading(body, false, '<p class="text-white-50">Job not found.</p>'); return; }
              self.render3dJobInDrawer(body, d.data, jid, closeDrawer);
              self._setDrawerTitle('3D Lab');
            })
            .catch(function() { self.setDrawerBodyLoading(body, false, '<p class="text-danger small">Could not load job.</p>'); });
          return;
        }
        if (isCharacterJob(jid, tool)) {
          fetch(API_CHARACTER_STATUS + '?id=' + encodeURIComponent(jid), { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
              if (!d.ok || !d.data) { self.setDrawerBodyLoading(body, false, '<p class="text-white-50">Job not found.</p>'); return; }
              self.renderCharacterJobInDrawer(body, d.data, jid, closeDrawer);
              self._setDrawerTitle('Character Lab');
            })
            .catch(function() { self.setDrawerBodyLoading(body, false, '<p class="text-danger small">Could not load job.</p>'); });
          return;
        }
        fetch(API_JOB + '?job_id=' + encodeURIComponent(jid), { credentials: 'same-origin' })
          .then(function(r) { return r.json(); })
          .then(function(d) {
            if (!d.ok || !d.data) { self.setDrawerBodyLoading(body, false, '<p class="text-white-50">Job not found.</p>'); return; }
            self.renderComfyJobInDrawer(body, d.data, jid, closeDrawer);
            var toolLabel = (d.data.tool === 'text2img' ? 'Text2Img' : d.data.tool === 'upscale' ? 'Upscale' : d.data.tool === 'remove-bg' ? 'Remove Background' : d.data.tool === 'consistency' ? 'Consistency' : d.data.tool === 'texture' || d.data.tool === 'texture_seamless' ? 'Texture Lab' : d.data.tool === '3d_vertex' ? '3D Vertex' : d.data.tool || 'Job');
            self._setDrawerTitle(toolLabel);
          })
          .catch(function() { self.setDrawerBodyLoading(body, false, '<p class="text-danger small">Could not load job.</p>'); });
      };

      document.addEventListener('click', function(e) {
        if (e.target.closest('#site-header') || e.target.closest('.knd-nav-offcanvas')) {
          return;
        }
        var btn = e.target.closest('.labs-view-details') || e.target.closest('.ln-job-card');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        var jid = btn.getAttribute('data-job-id');
        var tool = (btn.getAttribute('data-tool') || '').toLowerCase();
        if (!jid) return;

        var body = document.getElementById('labs-details-body');
        if (!body) return;
        self.setDrawerBodyLoading(body, true);
        openDrawer(typeof window !== 'undefined' && window.t ? window.t('labs.view_details', 'View details') : 'View details');

        if (is3dJob(jid, tool)) {
          fetch(API_3D_STATUS + '?id=' + encodeURIComponent(jid), { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
              if (!d.ok || !d.data) { self.setDrawerBodyLoading(body, false, '<p class="text-white-50">Job not found.</p>'); return; }
              self.render3dJobInDrawer(body, d.data, jid, closeDrawer);
              openDrawer('3D Lab');
            })
            .catch(function() { self.setDrawerBodyLoading(body, false, '<p class="text-danger small">Could not load job.</p>'); });
          return;
        }
        if (isCharacterJob(jid, tool)) {
          fetch(API_CHARACTER_STATUS + '?id=' + encodeURIComponent(jid), { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
              if (!d.ok || !d.data) { self.setDrawerBodyLoading(body, false, '<p class="text-white-50">Job not found.</p>'); return; }
              self.renderCharacterJobInDrawer(body, d.data, jid, closeDrawer);
              openDrawer('Character Lab');
            })
            .catch(function() { self.setDrawerBodyLoading(body, false, '<p class="text-danger small">Could not load job.</p>'); });
          return;
        }

        fetch(API_JOB + '?job_id=' + encodeURIComponent(jid), { credentials: 'same-origin' })
          .then(function(r) { return r.json(); })
          .then(function(d) {
            if (!d.ok || !d.data) { self.setDrawerBodyLoading(body, false, '<p class="text-white-50">Job not found.</p>'); return; }
            self.renderComfyJobInDrawer(body, d.data, jid, closeDrawer);
            var toolLabel = (d.data.tool === 'text2img' ? 'Text2Img' : d.data.tool === 'upscale' ? 'Upscale' : d.data.tool === 'remove-bg' ? 'Remove Background' : d.data.tool === 'consistency' ? 'Consistency' : d.data.tool === 'texture' || d.data.tool === 'texture_seamless' ? 'Texture Lab' : d.data.tool === '3d_vertex' ? '3D Vertex' : d.data.tool || 'Job');
            openDrawer(toolLabel);
          })
          .catch(function() { self.setDrawerBodyLoading(body, false, '<p class="text-danger small">Could not load job.</p>'); });
      });
    },

    setDrawerBodyLoading: function(bodyEl, isLoading, fallbackHtml) {
      if (!bodyEl) return;
      bodyEl.classList.toggle('is-loading', !!isLoading);
      if (isLoading) {
        bodyEl.innerHTML = '' +
          '<div class="labs-job-viewer-loading labs-job-viewer-loading--skeleton">' +
            '<div class="labs-loading-skeleton labs-loading-skeleton--media"></div>' +
            '<div class="labs-loading-skeleton labs-loading-skeleton--line"></div>' +
            '<div class="labs-loading-skeleton labs-loading-skeleton--line short"></div>' +
          '</div>';
      } else if (fallbackHtml) {
        bodyEl.innerHTML = fallbackHtml;
      }
    },

    renderCharacterJobInDrawer: function(body, J, jid, closeDrawer) {
      var self = this;
      if (body) body.classList.remove('is-loading');
      var previewUrl = (J.preview_url && J.preview_url.indexOf('/') === 0) ? (BASE_URL + J.preview_url) : J.preview_url;
      var conceptUrl = (J.concept_url && J.concept_url.indexOf('/') === 0) ? (BASE_URL + J.concept_url) : J.concept_url;
      var glbUrl = J.has_glb ? (BASE_URL + API_CHARACTER_DOWNLOAD + '?id=' + encodeURIComponent(jid) + '&format=glb') : null;

      var html = '';
      html += '<div class="labs-job-viewer-preview knd-details-preview">';
      if (previewUrl || conceptUrl) {
        var imgSrc = previewUrl || conceptUrl;
        html += '<img src="' + self.esc(imgSrc) + '" alt="Preview" class="labs-job-viewer-img" style="width:100%;height:100%;object-fit:contain;">';
      } else {
        html += '<div class="d-flex align-items-center justify-content-center text-white-50" style="min-height:200px;"><i class="fas fa-user-astronaut fa-3x opacity-50"></i><span class="ms-2">No preview</span></div>';
      }
      html += '</div>';

      html += '<div class="knd-details-block"><div class="knd-details-block__title">Details</div>';
      html += '<div class="labs-job-viewer-meta">';
      html += '<div class="labs-job-viewer-meta__row"><span class="labs-job-viewer-meta__label">Tool</span><span class="labs-job-viewer-meta__value">Character Lab</span></div>';
      html += '<div class="labs-job-viewer-meta__row"><span class="labs-job-viewer-meta__label">Status</span><span class="labs-job-viewer-meta__value">' + self.esc(J.status || '') + '</span></div>';
      html += '<div class="labs-job-viewer-meta__row"><span class="labs-job-viewer-meta__label">Created</span><span class="labs-job-viewer-meta__value">' + self.esc(J.created_at || '') + '</span></div>';
      if (J.kp_cost) html += '<div class="labs-job-viewer-meta__row"><span class="labs-job-viewer-meta__label">Cost</span><span class="labs-job-viewer-meta__value">' + J.kp_cost + ' KP</span></div>';
      if (J.mode) html += '<div class="labs-job-viewer-meta__row"><span class="labs-job-viewer-meta__label">Mode</span><span class="labs-job-viewer-meta__value">' + self.esc(J.mode) + '</span></div>';
      if (J.error_message) html += '<div class="labs-job-viewer-meta__row"><span class="labs-job-viewer-meta__label">Error</span><span class="text-danger small">' + self.esc(J.error_message) + '</span></div>';
      html += '</div></div>';

      html += '<div class="knd-details-block"><div class="knd-details-block__title">Actions</div><div class="knd-details-actions">';
      if (glbUrl && J.has_glb) html += '<a href="' + self.esc(glbUrl) + '" class="btn btn-sm knd-btn-secondary" download="character-' + self.esc(jid) + '.glb"><i class="fas fa-download me-1"></i>Download GLB</a>';
      if (conceptUrl) html += '<a href="' + self.esc(conceptUrl) + '" class="btn btn-sm knd-btn-secondary" download><i class="fas fa-image me-1"></i>Download concept</a>';
      if (previewUrl) html += '<a href="' + self.esc(previewUrl) + '" class="btn btn-sm knd-btn-secondary" download><i class="fas fa-image me-1"></i>Download preview</a>';
      html += '</div></div>';

      body.innerHTML = html;
    },

    render3dJobInDrawer: function(body, J, jid, closeDrawer) {
      var self = this;
      if (body) body.classList.remove('is-loading');
      var previewUrl = (J.preview_url && J.preview_url.indexOf('/') === 0) ? (BASE_URL + J.preview_url) : J.preview_url;
      var glbUrl = (J.glb_url && J.glb_url.indexOf('/') === 0) ? (BASE_URL + J.glb_url) : J.glb_url;
      var hasGlb = !!J.has_glb && !!glbUrl;

      var html = '';
      html += '<div class="labs-job-viewer-preview knd-details-preview">';
      if (hasGlb) {
        html += '<div id="labs-drawer-3d-viewer" class="labs-drawer-3d-viewer" style="width:100%;height:280px;position:relative;background:var(--knd-bg-alt, #0b1220);border-radius:8px;">';
        html += '<div class="labs-drawer-3d-loading d-flex align-items-center justify-content-center h-100 text-white-50"><i class="fas fa-spinner fa-spin me-2"></i>Loading 3D…</div>';
        html += '</div>';
        if (previewUrl) html += '<img src="' + self.esc(previewUrl) + '" alt="Preview" class="labs-drawer-3d-preview-img mt-2 rounded" style="max-width:100%;max-height:120px;object-fit:contain;display:none;">';
      } else if (previewUrl) {
        html += '<img src="' + self.esc(previewUrl) + '" alt="Preview" class="w-100 h-100" style="object-fit:contain;">';
      } else {
        html += '<div class="d-flex align-items-center justify-content-center text-white-50" style="min-height:200px;"><i class="fas fa-cube fa-3x opacity-50"></i><span class="ms-2">No preview</span></div>';
      }
      html += '</div>';

      html += '<div class="knd-details-block"><div class="knd-details-block__title">Details</div>';
      html += '<div class="labs-job-viewer-meta">';
      html += '<div class="labs-job-viewer-meta__row"><span class="labs-job-viewer-meta__label">Tool</span><span class="labs-job-viewer-meta__value">3D Lab</span></div>';
      html += '<div class="labs-job-viewer-meta__row"><span class="labs-job-viewer-meta__label">Status</span><span class="labs-job-viewer-meta__value">' + self.esc(J.status || '') + '</span></div>';
      html += '<div class="labs-job-viewer-meta__row"><span class="labs-job-viewer-meta__label">Created</span><span class="labs-job-viewer-meta__value">' + self.esc(J.created_at || '') + '</span></div>';
      if (J.category) html += '<div class="labs-job-viewer-meta__row"><span class="labs-job-viewer-meta__label">Category</span><span class="labs-job-viewer-meta__value">' + self.esc(J.category) + '</span></div>';
      if (J.quality) html += '<div class="labs-job-viewer-meta__row"><span class="labs-job-viewer-meta__label">Quality</span><span class="labs-job-viewer-meta__value">' + self.esc(J.quality) + '</span></div>';
      if (J.error_message) html += '<div class="labs-job-viewer-meta__row"><span class="labs-job-viewer-meta__label">Error</span><span class="text-danger small">' + self.esc(J.error_message) + '</span></div>';
      html += '</div></div>';

      html += '<div class="knd-details-block"><div class="knd-details-block__title">Actions</div><div class="knd-details-actions">';
      if (hasGlb && glbUrl) html += '<a href="' + self.esc(glbUrl) + '" class="btn btn-sm knd-btn-secondary" download="3d-lab-' + self.esc(jid) + '.glb"><i class="fas fa-download me-1"></i>Download GLB</a>';
      if (previewUrl) html += '<a href="' + self.esc(previewUrl) + '" class="btn btn-sm knd-btn-secondary" download><i class="fas fa-image me-1"></i>Download preview</a>';
      html += '</div></div>';

      body.innerHTML = html;

      if (hasGlb && glbUrl) {
        var container = body.querySelector('#labs-drawer-3d-viewer');
        if (container) {
          var loadingEl = container.querySelector('.labs-drawer-3d-loading');
          var glbAbsUrl = glbUrl.indexOf('/') === 0 ? (BASE_URL + glbUrl) : glbUrl;
          fetch(glbAbsUrl, { credentials: 'same-origin' })
            .then(function(r) { return r.ok ? r.blob() : Promise.reject(); })
            .then(function(blob) {
              var url = URL.createObjectURL(blob);
              if (typeof customElements !== 'undefined' && customElements.whenDefined) {
                customElements.whenDefined('model-viewer').then(function() {
                  var mv = document.createElement('model-viewer');
                  mv.setAttribute('src', url);
                  mv.setAttribute('camera-controls', '');
                  mv.setAttribute('auto-rotate', '');
                  mv.setAttribute('interaction-prompt', 'none');
                  mv.style.width = '100%';
                  mv.style.height = '100%';
                  mv.style.minHeight = '280px';
                  if (loadingEl) loadingEl.remove();
                  container.appendChild(mv);
                });
              } else {
                if (loadingEl) loadingEl.textContent = '3D viewer not available';
              }
            })
            .catch(function() {
              if (loadingEl) loadingEl.innerHTML = '<span class="text-warning small">Could not load model. <a href="' + self.esc(glbUrl) + '" download>Download GLB</a></span>';
            });
        }
      }
    },

    renderComfyJobInDrawer: function(body, J, jid, closeDrawer) {
      var self = this;
      if (body) body.classList.remove('is-loading');
      var imgUrl = J.image_url || (API_IMAGE + '?job_id=' + encodeURIComponent(jid));
      var isGlb = !!(J.output_path && /\.glb$/i.test(J.output_path));
      var imgUrlEsc = imgUrl.replace(/"/g, '&quot;');
      var sizeStr = (J.width && J.height) ? J.width + '\u00D7' + J.height : '';
      var toolLabel = (J.tool === 'text2img' ? 'Text2Img' : J.tool === 'upscale' ? 'Upscale' : J.tool === 'remove-bg' ? 'Remove Background' : J.tool === 'consistency' ? 'Consistency' : J.tool === 'texture' || J.tool === 'texture_seamless' ? 'Texture Lab' : J.tool === '3d_vertex' ? '3D Vertex' : J.tool || '—');

      var html = '';
      html += '<div class="labs-job-viewer-preview knd-details-preview">';
      if (J.status === 'done' && imgUrl && isGlb) {
        html += '<model-viewer src="' + imgUrlEsc + '" camera-controls auto-rotate style="width:100%;height:100%;min-height:220px;background:var(--bg-main);"></model-viewer>';
      } else if (J.status === 'done' && imgUrl) {
        html += '<img src="' + imgUrlEsc + '" alt="Result" class="labs-job-viewer-img">';
      } else {
        html += '<div class="d-flex align-items-center justify-content-center text-white-50" style="min-height:220px;"><i class="fas fa-image fa-3x opacity-50"></i><span class="ms-2">' + (J.status || 'No preview') + '</span></div>';
      }
      html += '</div>';

      html += '<div class="knd-details-block"><div class="knd-details-block__title">Details</div>';
      html += '<div class="labs-job-viewer-meta">';
      html += '<div class="labs-job-viewer-meta__row"><span class="labs-job-viewer-meta__label">Tool</span><span class="labs-job-viewer-meta__value">' + toolLabel + '</span></div>';
      html += '<div class="labs-job-viewer-meta__row"><span class="labs-job-viewer-meta__label">Status</span><span class="labs-job-viewer-meta__value">' + self.esc(J.status || '') + '</span></div>';
      html += '<div class="labs-job-viewer-meta__row"><span class="labs-job-viewer-meta__label">Created</span><span class="labs-job-viewer-meta__value">' + self.esc(J.created_at || '') + '</span></div>';
      if (J.cost_kp) html += '<div class="labs-job-viewer-meta__row"><span class="labs-job-viewer-meta__label">Cost</span><span class="labs-job-viewer-meta__value">' + J.cost_kp + ' KP</span></div>';
      if (J.model) html += '<div class="labs-job-viewer-meta__row"><span class="labs-job-viewer-meta__label">Model</span><span class="labs-job-viewer-meta__value">' + self.esc(J.model) + '</span></div>';
      if (J.prompt) html += '<div class="labs-job-viewer-meta__row"><span class="labs-job-viewer-meta__label">Prompt</span><span class="labs-job-viewer-meta__value labs-job-viewer-meta__value--block">' + self.esc(J.prompt) + '</span></div>';
      if (J.negative_prompt) html += '<div class="labs-job-viewer-meta__row"><span class="labs-job-viewer-meta__label">Negative</span><span class="labs-job-viewer-meta__value labs-job-viewer-meta__value--block">' + self.esc(J.negative_prompt) + '</span></div>';
      if (J.tool === 'consistency' && (J.base_prompt || J.scene_prompt)) {
        if (J.base_prompt) html += '<div class="labs-job-viewer-meta__row"><span class="labs-job-viewer-meta__label">Base prompt</span><span class="labs-job-viewer-meta__value labs-job-viewer-meta__value--block">' + self.esc(J.base_prompt) + '</span></div>';
        if (J.scene_prompt) html += '<div class="labs-job-viewer-meta__row"><span class="labs-job-viewer-meta__label">Scene prompt</span><span class="labs-job-viewer-meta__value labs-job-viewer-meta__value--block">' + self.esc(J.scene_prompt) + '</span></div>';
      }
      if (J.tool === 'upscale' && (J.scale || J.upscale_model)) {
        html += '<div class="labs-job-viewer-meta__row"><span class="labs-job-viewer-meta__label">Scale</span><span class="labs-job-viewer-meta__value">' + (J.scale ? J.scale + 'x' : '—') + '</span></div>';
        if (J.upscale_model) html += '<div class="labs-job-viewer-meta__row"><span class="labs-job-viewer-meta__label">Model</span><span class="labs-job-viewer-meta__value">' + self.esc(J.upscale_model) + '</span></div>';
      }
      if (sizeStr) html += '<div class="labs-job-viewer-meta__row"><span class="labs-job-viewer-meta__label">Resolution</span><span class="labs-job-viewer-meta__value">' + sizeStr + '</span></div>';
      if (J.error_message) html += '<div class="labs-job-viewer-meta__row"><span class="labs-job-viewer-meta__label">Error</span><span class="text-danger small">' + self.esc(J.error_message) + '</span></div>';
      html += '</div></div>';

      html += '<div class="knd-details-block"><div class="knd-details-block__title">Actions</div><div class="knd-details-actions">';
      if (J.status === 'done' && (J.tool === 'text2img' || J.tool === 'consistency')) {
        var mode = (J.tool === 'consistency' && J.mode) ? J.mode : 'style';
        html += '<a href="/labs?tool=upscale&source_job_id=' + encodeURIComponent(jid) + '" class="btn btn-sm knd-btn-secondary"><i class="fas fa-search-plus me-1"></i>Send to Upscale</a>';
        if (J.tool === 'text2img') {
          html += '<a href="/labs?tool=remove-bg&source_job_id=' + encodeURIComponent(jid) + '" class="btn btn-sm knd-btn-secondary"><i class="fas fa-eraser me-1"></i>Remove Background</a>';
        }
        html += '<a href="/labs?tool=consistency&reference_job_id=' + encodeURIComponent(jid) + '&mode=' + encodeURIComponent(mode) + '" class="btn btn-sm knd-btn-secondary"><i class="fas fa-palette me-1"></i>Consistency</a>';
        html += '<a href="/labs?tool=consistency&reference_job_id=' + encodeURIComponent(jid) + '&mode=' + encodeURIComponent(mode) + '" class="btn btn-sm knd-btn-secondary"><i class="fas fa-images me-1"></i>Variations</a>';
      }
      if (J.status === 'done' && J.tool === 'texture') {
        html += '<a href="/labs?tool=upscale&source_job_id=' + encodeURIComponent(jid) + '" class="btn btn-sm knd-btn-secondary"><i class="fas fa-search-plus me-1"></i>Send to Upscale</a>';
      }
      if (J.status === 'done' && J.tool === 'remove-bg') {
        html += '<a href="/labs?tool=upscale&source_job_id=' + encodeURIComponent(jid) + '" class="btn btn-sm knd-btn-secondary"><i class="fas fa-search-plus me-1"></i>Send to Upscale</a>';
        html += '<a href="/labs?tool=3d_vertex&source_job_id=' + encodeURIComponent(jid) + '" class="btn btn-sm knd-btn-secondary"><i class="fas fa-cube me-1"></i>Send to 3D Vertex</a>';
      }
      if (J.status === 'done' && J.tool === 'upscale') {
        html += '<a href="/labs?tool=upscale&source_job_id=' + encodeURIComponent(jid) + '" class="btn btn-sm knd-btn-secondary"><i class="fas fa-search-plus me-1"></i>Use as input</a>';
      }
      if (J.status === 'done') {
        html += '<a href="' + imgUrlEsc + '" class="btn btn-sm knd-btn-secondary" download><i class="fas fa-download me-1"></i>Download</a>';
        if (J.tool === '3d_vertex' && isGlb) {
          html += '<a href="/labs?tool=model_viewer&source_job_id=' + encodeURIComponent(jid) + '" class="btn btn-sm knd-btn-secondary"><i class="fas fa-cube me-1"></i>View in Model Viewer</a>';
        }
      }
      if (J.tool === 'text2img' || J.tool === 'consistency' || J.tool === 'texture') {
        html += '<button type="button" class="btn btn-sm knd-btn-secondary labs-details-reuse" data-job-id="' + self.esc(jid) + '"><i class="fas fa-copy me-1"></i>Reuse Settings</button>';
      }
      var copyText = (J.tool === 'consistency' && (J.base_prompt || J.scene_prompt)) ? ((J.base_prompt || '') + '\n' + (J.scene_prompt || '')).trim() : (J.prompt || '');
      if (copyText) {
        var copyAttr = self.esc(copyText).replace(/&/g, '&amp;').replace(/"/g, '&quot;').substring(0, 2000);
        html += '<button type="button" class="btn btn-sm knd-btn-secondary labs-copy-prompt" data-prompt="' + copyAttr + '"><i class="fas fa-clipboard me-1"></i>Copy prompt</button>';
      }
      html += '</div></div>';

      body.innerHTML = html;
      body.querySelectorAll('.labs-copy-prompt').forEach(function(b) {
        b.addEventListener('click', function() {
          var p = b.getAttribute('data-prompt');
          if (p != null && typeof navigator !== 'undefined' && navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(p);
            if (typeof window.kndToast === 'function') window.kndToast('Copied to clipboard', 'success');
          }
        });
      });
      body.querySelectorAll('.labs-details-reuse').forEach(function(b) {
        b.addEventListener('click', function() {
          self.reuseJobSettings(jid, J.tool);
          closeDrawer();
        });
      });
      if (typeof this.renderImageDetails === 'function') this.renderImageDetails(J);
    },

    esc: function(s) {
      if (s == null) return '';
      var div = document.createElement('div');
      div.textContent = String(s);
      return div.innerHTML;
    },

    reuseJobSettings: function(jid, tool) {
      var self = this;
      fetch(API_JOB + '?job_id=' + jid, { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(d) {
          if (!d.ok || !d.data) return;
          var J = d.data;
          var form = document.getElementById(self.config.formId || 'labs-comfy-form');
          if (!form) return;
          var set = function(name, val) { var el = form.querySelector('[name="' + name + '"]'); if (el && val != null && val !== '') el.value = val; };
          if (tool === 'consistency') {
            set('base_prompt', J.base_prompt);
            set('scene_prompt', J.scene_prompt);
            set('negative_prompt', J.negative_prompt);
            set('mode', J.mode);
            set('width', J.width);
            set('height', J.height);
            set('seed', J.seed);
            set('steps', J.steps);
            set('cfg', J.cfg);
            set('sampler', J.sampler_name || J.sampler);
            set('model', J.model);
          } else if (tool === 'texture') {
            set('prompt', J.prompt);
            set('negative_prompt', J.negative_prompt);
            set('texture_mode', J.texture_mode || 'text');
            set('steps', J.steps);
            set('cfg', J.cfg);
            set('denoise', J.denoise);
            var modeEl = form.querySelector('[name="texture_mode"]');
            var modeChips = form.querySelectorAll('.texture-mode-chip');
            if (modeEl && modeChips.length) {
              modeEl.value = J.texture_mode || 'text';
              modeChips.forEach(function(ch) { ch.classList.toggle('active', ch.getAttribute('data-mode') === (J.texture_mode || 'text')); });
              var promptBlock = document.getElementById('labs-texture-prompt-block');
              var imageBlock = document.getElementById('labs-texture-image-block');
              if (promptBlock) promptBlock.style.display = (modeEl.value === 'image') ? 'none' : 'block';
              if (imageBlock) imageBlock.style.display = (modeEl.value === 'text') ? 'none' : 'block';
            }
            var seamlessEl = form.querySelector('[name="texture_seamless"]');
            if (seamlessEl) seamlessEl.checked = !!J.seamless;
          } else {
            set('prompt', J.prompt);
            set('negative_prompt', J.negative_prompt);
            set('model', J.model);
            set('seed', J.seed);
            set('steps', J.steps);
            set('cfg', J.cfg);
            set('width', J.width);
            set('height', J.height);
            set('sampler_name', J.sampler_name);
            set('scheduler', J.scheduler);
          }
          if (self.updateCostLabel) self.updateCostLabel();
          if (self.updateBalanceAfter) self.updateBalanceAfter();
        });
    },

    bindRecentFilter: function() {
      var sel = document.getElementById('labs-recent-filter');
      if (!sel) return;
      sel.addEventListener('change', function() {
        var v = sel.value;
        window.location.href = window.location.pathname + (v ? '?provider=' + encodeURIComponent(v) : '');
      });
    },

    bindPrivateCheck: function() {
      var cb = document.getElementById('labs-private-check');
      if (!cb) return;
      cb.addEventListener('change', function() {
        var fd = new FormData();
        fd.set('private', cb.checked ? '1' : '0');
        fetch(API_PREFERENCE, { method: 'POST', body: fd, credentials: 'same-origin' }).catch(function() {});
      });
    },

    bindRetry: function() {
      var self = this;
      var retryBtn = document.getElementById('labs-retry-btn');
      if (!retryBtn) return;
      retryBtn.addEventListener('click', function() {
        var actions = document.getElementById('labs-result-actions');
        self.restorePlaceholderPreview();
        if (actions) actions.style.display = 'none';
      });
    },

    bindRegenerate: function() {
      var self = this;
      var btn = document.getElementById('labs-regenerate-btn');
      if (!btn) return;
      btn.addEventListener('click', function() {
        var form = document.getElementById(self.config.formId || 'labs-comfy-form');
        if (!form) return;
        var seedEl = form.querySelector('[name="seed"]');
        if (seedEl && self.lastJobSeed != null) seedEl.value = self.lastJobSeed;
        self.submitForm(form);
      });
    },

    bindVariations: function() {
      var self = this;
      var btn = document.getElementById('labs-variations-btn');
      if (!btn) return;
      btn.addEventListener('click', function() {
        var form = document.getElementById(self.config.formId || 'labs-comfy-form');
        if (!form) return;
        var seedEl = form.querySelector('[name="seed"]');
        if (seedEl) {
          var current = parseInt(seedEl.value, 10);
          if (isNaN(current)) current = Math.floor(Math.random() * 2147483647);
          seedEl.value = (current + 1) % 2147483647;
        }
        self.submitForm(form);
      });
    },

    bindBackgroundMotion: function() {
      var app = document.getElementById('ln-app');
      if (!app || app.dataset.bgMotionBound === '1') return;
      var shouldReduce = typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (shouldReduce) return;

      app.dataset.bgMotionBound = '1';

      var pointerX = 0;
      var pointerY = 0;
      var scrollY = 0;
      var ticking = false;

      function clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }
      function paint() {
        ticking = false;
        var x = clamp(pointerX, -18, 18);
        var y = clamp(pointerY + (scrollY * -0.04), -20, 20);
        app.style.setProperty('--ln-bg-shift-x', x.toFixed(2) + 'px');
        app.style.setProperty('--ln-bg-shift-y', y.toFixed(2) + 'px');
      }
      function requestPaint() {
        if (ticking) return;
        ticking = true;
        window.requestAnimationFrame(paint);
      }

      window.addEventListener('pointermove', function(e) {
        var cx = window.innerWidth / 2;
        var cy = window.innerHeight / 2;
        pointerX = (e.clientX - cx) / 45;
        pointerY = (e.clientY - cy) / 45;
        requestPaint();
      }, { passive: true });

      window.addEventListener('scroll', function() {
        scrollY = window.scrollY || window.pageYOffset || 0;
        requestPaint();
      }, { passive: true });

      window.addEventListener('mouseleave', function() {
        pointerX = 0;
        pointerY = 0;
        requestPaint();
      }, { passive: true });

      app.classList.add('ln-bg-motion-ready');
      requestPaint();
    }
  };

  window.KNDLabs = KNDLabs;
})();
