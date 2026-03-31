/**
 * KND Labs - shared JS for tool pages
 */
var KNDLabs = (function() {
    var pollInterval = null;
    var currentJobId = null;
    var config = {};

    function updateBalance(kp) {
        if (config.balanceEl) config.balanceEl.textContent = parseInt(kp, 10).toLocaleString() + ' KP';
    }

    function showStatus(jobId) {
        currentJobId = jobId;
        var panel = document.getElementById('labs-status-panel');
        var text = document.getElementById('labs-status-text');
        var preview = document.getElementById('labs-result-preview');
        var actions = document.getElementById('labs-result-actions');
        var errorEl = document.getElementById('labs-error-msg');
        if (panel) panel.style.display = 'block';
        if (actions) actions.style.display = 'none';
        if (errorEl) errorEl.style.display = 'none';
        if (text) text.textContent = 'Processing...';

        if (pollInterval) clearInterval(pollInterval);
        pollInterval = setInterval(function() {
            fetch('/api/ai/status.php?job_id=' + encodeURIComponent(jobId))
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (!d.ok) return;
                    var st = d.data.status;
                    if (text) text.textContent = st.charAt(0).toUpperCase() + st.slice(1);

                    if (st === 'failed') {
                        if (pollInterval) { clearInterval(pollInterval); pollInterval = null; }
                        if (panel) panel.style.display = 'none';
                        if (errorEl) {
                            errorEl.textContent = d.data.error_message || 'Failed';
                            errorEl.style.display = 'block';
                        }
                        currentJobId = null;
                    } else if (st === 'completed') {
                        if (pollInterval) { clearInterval(pollInterval); pollInterval = null; }
                        if (panel) panel.style.display = 'none';
                        if (preview) {
                            preview.innerHTML = '<img src="/api/ai/preview.php?job_id=' + encodeURIComponent(jobId) + '" alt="Result" class="img-fluid rounded" style="max-height:400px;">';
                        }
                        var dlBtn = document.getElementById('labs-download-btn');
                        if (dlBtn) {
                            dlBtn.href = '/api/ai/download.php?job_id=' + encodeURIComponent(jobId);
                            dlBtn.target = '_blank';
                        }
                        if (actions) actions.style.display = 'block';
                        currentJobId = null;
                    }
                    if (d.data.available_after !== undefined) updateBalance(d.data.available_after);
                })
                .catch(function() {});
        }, 3000);
    }

    function submitForm(formEl) {
        var fd = new FormData(formEl);
        var submitBtn = formEl.querySelector('[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        fetch('/api/ai/submit.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (submitBtn) submitBtn.disabled = false;
                if (d.ok && d.data.job_id) {
                    if (d.data.available_after !== undefined) updateBalance(d.data.available_after);
                    formEl.reset();
                    var preview = document.getElementById('labs-result-preview');
                    if (preview) preview.innerHTML = '<div class="ai-spinner"><i class="fas fa-cog fa-spin fa-2x"></i></div><p class="text-white-50 mt-2 mb-0">Processing...</p>';
                    showStatus(d.data.job_id);
                } else {
                    var msg = (d.error && d.error.message) ? d.error.message : 'Error';
                    if (typeof kndToast !== 'undefined') kndToast(msg, 'error');
                    else alert(msg);
                }
            })
            .catch(function() {
                if (submitBtn) submitBtn.disabled = false;
                if (typeof kndToast !== 'undefined') kndToast('Network error', 'error');
                else alert('Network error');
            });
    }

    function bindForm() {
        var form = document.getElementById(config.formId);
        if (!form) return;

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var prompt = form.querySelector('[name="prompt"]');
            if (prompt && (!prompt.value || prompt.value.trim().length === 0)) {
                if (typeof kndToast !== 'undefined') kndToast('Prompt is required', 'error');
                else alert('Prompt is required');
                return;
            }
            var imageInput = form.querySelector('[name="image"]');
            if (imageInput && config.jobType === 'upscale' && (!imageInput.files || !imageInput.files.length)) {
                if (typeof kndToast !== 'undefined') kndToast('Image is required', 'error');
                else alert('Image is required');
                return;
            }
            submitForm(form);
        });

        document.querySelectorAll('.preset-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var p = form.querySelector('[name="prompt"]');
                if (p) p.value = btn.getAttribute('data-prompt') || '';
            });
        });

        var retryBtn = document.getElementById('labs-retry-btn');
        if (retryBtn) {
            retryBtn.addEventListener('click', function() {
                var preview = document.getElementById('labs-result-preview');
                var actions = document.getElementById('labs-result-actions');
                if (preview) preview.innerHTML = '<i class="fas fa-image fa-3x text-white-50 mb-3"></i><p class="text-white-50 mb-0">Submit to generate</p>';
                if (actions) actions.style.display = 'none';
            });
        }
    }

    return {
        init: function(cfg) {
            config = cfg || {};
            config.balanceEl = config.balanceEl ? document.querySelector(config.balanceEl) : null;
            bindForm();
        }
    };
})();
