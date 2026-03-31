<?php
/**
 * Text2Img form - main column: meta (cost/balance) + prompt block.
 * All IDs/names preserved for kndlabs.js.
 */
?>
<div class="ln-t2i-meta">
  <span id="labs-cost-label" class="ln-t2i-cost"><?php echo t('labs.cost_label', 'Cost: 3 KP'); ?></span>
</div>
<div class="ln-t2i-prompt-block">
  <label class="form-label ln-t2i-label" for="labs-prompt-input"><?php echo t('ai.text2img.prompt'); ?></label>
  <textarea name="prompt" class="knd-textarea form-control text-white ln-t2i-prompt-input" id="labs-prompt-input" rows="4" maxlength="500" placeholder="Describe the image..."></textarea>
  <button type="button" class="btn btn-link btn-sm text-white-50 p-0 mt-1" id="labs-use-last-prompt-btn" style="display:none;"><i class="fas fa-history me-1"></i><?php echo t('labs.use_last_prompt', 'Use last prompt'); ?></button>
  <div class="form-text text-white-50 small" id="labs-prompt-hint"></div>
</div>
