<?php
/**
 * Text2Img form - workspace layout: main col (prompt) + params col (presets, negative, conditioning, quality, model, advanced, private).
 * All IDs and name attributes preserved for kndlabs.js.
 */
?>
<form id="labs-comfy-form" class="labs-form ln-t2i-form" method="post" action="#" onsubmit="return false;">
  <input type="hidden" name="tool" value="text2img">
  <div class="ln-t2i-grid">
    <div class="ln-t2i-main-col">
      <div class="ln-t2i-meta">
        <span id="labs-cost-label" class="ln-t2i-cost"><?php echo t('labs.cost_label', 'Cost: 3 KP'); ?></span>
      </div>
      <div class="ln-t2i-prompt-block">
        <label class="form-label ln-t2i-label" for="labs-prompt-input"><?php echo t('ai.text2img.prompt'); ?></label>
        <textarea name="prompt" class="knd-textarea form-control text-white ln-t2i-prompt-input" id="labs-prompt-input" rows="4" maxlength="500" placeholder="Describe the image..."></textarea>
        <button type="button" class="btn btn-link btn-sm text-white-50 p-0 mt-1" id="labs-use-last-prompt-btn" style="display:none;"><i class="fas fa-history me-1"></i><?php echo t('labs.use_last_prompt', 'Use last prompt'); ?></button>
        <div class="form-text text-white-50 small" id="labs-prompt-hint"></div>
      </div>
    </div>
    <aside class="ln-t2i-params-col">
      <div class="ln-t2i-params-panel">
        <div class="ln-t2i-param-group">
          <label class="ln-t2i-param-label"><?php echo t('labs.presets'); ?></label>
          <div class="d-flex flex-wrap gap-2">
            <button type="button" class="knd-chip preset-btn" data-prompt="Game character concept, fantasy armor, portrait" data-negative="ugly, blurry, deformed" data-steps="25" data-cfg="7.5" data-sampler="dpmpp_2m" data-width="1024" data-height="1024"><?php echo t('labs.preset_game', 'Game concept'); ?></button>
            <button type="button" class="knd-chip preset-btn" data-prompt="Anime portrait, detailed face, soft lighting" data-negative="ugly, blurry, bad anatomy" data-steps="25" data-cfg="7.5" data-sampler="euler" data-width="1024" data-height="1024"><?php echo t('labs.preset_anime', 'Anime portrait'); ?></button>
            <button type="button" class="knd-chip preset-btn" data-prompt="Landscape, mountains, sunset, atmospheric" data-negative="ugly, blurry, people" data-steps="25" data-cfg="7" data-sampler="dpmpp_2m" data-width="1024" data-height="768"><?php echo t('labs.preset_landscape', 'Landscape'); ?></button>
          </div>
          <div class="form-text text-white-50 small mt-1" id="labs-preset-summary"></div>
        </div>
        <div class="ln-t2i-param-group">
          <label class="ln-t2i-param-label" for="labs-negative-input"><?php echo t('labs.negative_prompt', 'Negative prompt'); ?></label>
          <input type="text" name="negative_prompt" class="knd-input form-control text-white" id="labs-negative-input" maxlength="500" value="ugly, blurry, low quality" placeholder="ugly, blurry, low quality">
          <div class="d-flex flex-wrap gap-2 mt-2" id="preset-neg-btns">
            <button type="button" class="knd-chip preset-neg-btn" data-value="ugly, blurry, low quality"><?php echo t('labs.neg_default', 'Default'); ?></button>
            <button type="button" class="knd-chip preset-neg-btn" data-value="text, watermark, signature"><?php echo t('labs.neg_text', 'No text'); ?></button>
            <button type="button" class="knd-chip preset-neg-btn" data-value="bad anatomy, extra limbs, mutated"><?php echo t('labs.neg_anatomy', 'Anatomy'); ?></button>
            <button type="button" class="knd-chip preset-neg-btn" data-value="disfigured, deformed, cartoon"><?php echo t('labs.neg_realistic', 'Realistic'); ?></button>
          </div>
        </div>
        <div class="ln-t2i-param-group">
          <h6 class="text-white-50 mb-2 small ln-t2i-param-label"><?php echo t('labs.cond_section', 'Reference / Conditioning'); ?></h6>
          <div class="form-check form-switch mb-2">
            <input type="checkbox" name="ipadapter_enabled" id="ipadapter-enabled" class="form-check-input" value="1">
            <label for="ipadapter-enabled" class="form-check-label text-white-50 small"><?php echo t('labs.ipadapter_toggle', 'Enable IPAdapter (style reference)'); ?></label>
          </div>
          <div id="ipadapter-fields" class="ms-3 mb-2" style="display:none;">
            <div class="mb-2">
              <label class="form-label text-white-50 small"><?php echo t('labs.ref_image', 'Reference image'); ?></label>
              <input type="file" name="ipadapter_image" id="ipadapter-image" accept="image/jpeg,image/jpg,image/png,image/webp" class="knd-input form-control form-control-sm text-white">
              <div class="form-text text-white-50 small"><?php echo t('labs.ref_help', 'Required when enabled'); ?></div>
            </div>
            <div class="row g-2 mb-2">
              <div class="col-4">
                <label class="form-label text-white-50 small"><?php echo t('labs.weight', 'Weight'); ?></label>
                <input type="number" name="ipadapter_weight" class="knd-input form-control form-control-sm text-white" value="0.70" min="0" max="1.20" step="0.05">
              </div>
              <div class="col-4">
                <label class="form-label text-white-50 small">start_at</label>
                <input type="number" name="ipadapter_start_at" class="knd-input form-control form-control-sm text-white" value="0" min="0" max="1" step="0.05">
              </div>
              <div class="col-4">
                <label class="form-label text-white-50 small">end_at</label>
                <input type="number" name="ipadapter_end_at" class="knd-input form-control form-control-sm text-white" value="1" min="0" max="1" step="0.05">
              </div>
            </div>
            <div class="mb-2">
              <label class="form-label text-white-50 small"><?php echo t('labs.mode', 'Mode'); ?></label>
              <select name="ipadapter_mode" id="ipadapter-mode" class="knd-select form-select form-select-sm text-white">
                <option value="balanced" selected>Balanced</option>
                <option value="style">Style</option>
                <option value="composition">Composition</option>
              </select>
            </div>
          </div>
          <div class="form-check form-switch mb-2">
            <input type="checkbox" name="controlnet_enabled" id="controlnet-enabled" class="form-check-input" value="1">
            <label for="controlnet-enabled" class="form-check-label text-white-50 small"><?php echo t('labs.controlnet_toggle', 'Enable ControlNet (pose/edges)'); ?></label>
          </div>
          <div id="controlnet-fields" class="ms-3 mb-2" style="display:none;">
            <div class="mb-2">
              <label class="form-label text-white-50 small"><?php echo t('labs.control_image', 'Control image'); ?></label>
              <input type="file" name="controlnet_image" id="controlnet-image" accept="image/jpeg,image/jpg,image/png,image/webp" class="knd-input form-control form-control-sm text-white">
              <div class="form-text text-white-50 small"><?php echo t('labs.control_help', 'Required when enabled'); ?></div>
            </div>
            <div class="row g-2 mb-2">
              <div class="col-4">
                <label class="form-label text-white-50 small"><?php echo t('labs.strength', 'Strength'); ?></label>
                <input type="number" name="controlnet_strength" class="knd-input form-control form-control-sm text-white" value="0.75" min="0" max="1.20" step="0.05">
              </div>
              <div class="col-4">
                <label class="form-label text-white-50 small">start_at</label>
                <input type="number" name="controlnet_start_at" class="knd-input form-control form-control-sm text-white" value="0" min="0" max="1" step="0.05">
              </div>
              <div class="col-4">
                <label class="form-label text-white-50 small">end_at</label>
                <input type="number" name="controlnet_end_at" class="knd-input form-control form-control-sm text-white" value="0.80" min="0" max="1" step="0.05">
              </div>
            </div>
            <div class="mb-2">
              <label class="form-label text-white-50 small"><?php echo t('labs.control_mode', 'Control mode'); ?></label>
              <select name="controlnet_control_mode" id="controlnet-control-mode" class="knd-select form-select form-select-sm text-white">
                <option value="balanced" selected>Balanced</option>
                <option value="prompt_strict">Prompt strict</option>
                <option value="control_strict">Control strict</option>
              </select>
            </div>
          </div>
        </div>
        <div class="ln-t2i-param-group">
          <label class="ln-t2i-param-label"><?php echo t('ai.text2img.mode_label', 'Quality'); ?></label>
          <select name="quality" id="labs-quality-select" class="knd-select form-select text-white">
            <option value="standard" selected>Standard (3 KP)</option>
            <option value="high">High (6 KP)</option>
          </select>
        </div>
        <div class="ln-t2i-param-group">
          <label class="ln-t2i-param-label"><?php echo t('labs.model', 'Model'); ?></label>
          <select name="model" id="labs-model-select" class="knd-select form-select form-select-sm text-white">
            <option value="sd_xl_base">SD XL Base 1.0</option>
            <option value="sd_xl_refiner">SD XL Refiner 1.0</option>
            <option value="juggernaut_ragnarok">Juggernaut XL Ragnarok</option>
            <option value="juggernaut_v8" selected>Juggernaut XL v8</option>
            <option value="pornmaster_asian">PornMaster Asian SDXL</option>
            <option value="waiANINSFWPONY">PONY XL v1.30</option>
            <option value="waiNSFW_v120">Illustrious v1.20</option>
            <option value="waiNSFW_v150">Illustrious v1.50</option>
          </select>
        </div>
        <div class="ln-t2i-param-group">
          <button type="button" class="btn btn-link btn-sm text-white-50 p-0" id="labs-advanced-toggle" data-bs-toggle="collapse" data-bs-target="#labs-advanced">
            <i class="fas fa-chevron-down me-1"></i><?php echo t('labs.advanced', 'Advanced'); ?>
          </button>
          <div class="collapse mt-2" id="labs-advanced">
            <div class="row g-2 mb-2">
              <div class="col-6">
                <label class="form-label text-white-50 small"><?php echo t('labs.seed', 'Seed'); ?></label>
                <input type="number" name="seed" class="knd-input form-control form-control-sm text-white" placeholder="Random">
              </div>
              <div class="col-6">
                <label class="form-label text-white-50 small"><?php echo t('labs.steps', 'Steps'); ?></label>
                <input type="number" name="steps" class="knd-input form-control form-control-sm text-white" value="30" min="1" max="100">
              </div>
              <div class="col-6">
                <label class="form-label text-white-50 small"><?php echo t('labs.cfg', 'CFG'); ?></label>
                <input type="number" name="cfg" class="knd-input form-control form-control-sm text-white" value="6" min="1" max="30" step="0.5">
              </div>
              <div class="col-6">
                <label class="form-label text-white-50 small"><?php echo t('labs.sampler', 'Sampler'); ?></label>
                <select name="sampler_name" class="knd-select form-select form-select-sm text-white">
                  <option value="euler">Euler</option>
                  <option value="euler_ancestral">Euler Ancestral</option>
                  <option value="heun">Heun</option>
                  <option value="dpm_2">DPM 2</option>
                  <option value="dpm_2_ancestral">DPM 2 Ancestral</option>
                  <option value="lms">LMS</option>
                  <option value="dpmpp_2m" selected>DPM++ 2M</option>
                  <option value="dpmpp_2m_sde">DPM++ 2M SDE</option>
                  <option value="dpmpp_sde">DPM++ SDE</option>
                  <option value="ddim">DDIM</option>
                  <option value="lcm">LCM</option>
                  <option value="uni_pc">UniPC</option>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label text-white-50 small"><?php echo t('labs.scheduler', 'Scheduler'); ?></label>
                <select name="scheduler" class="knd-select form-select form-select-sm text-white">
                  <option value="normal">Normal</option>
                  <option value="karras" selected>Karras</option>
                  <option value="exponential">Exponential</option>
                  <option value="sgm_uniform">SGM Uniform</option>
                  <option value="simple">Simple</option>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label text-white-50 small"><?php echo t('labs.denoise', 'Denoise'); ?></label>
                <input type="number" name="denoise" class="knd-input form-control form-control-sm text-white" value="1" min="0.01" max="1" step="0.01">
              </div>
              <div class="col-6">
                <label class="form-label text-white-50 small"><?php echo t('labs.width', 'Width'); ?></label>
                <select name="width" id="labs-width-select" class="knd-select form-select form-select-sm text-white">
                  <option value="256">256</option>
                  <option value="512" selected>512</option>
                  <option value="768">768</option>
                  <option value="1024">1024</option>
                  <option value="1152">1152</option>
                  <option value="1280">1280</option>
                  <option value="1536">1536</option>
                  <option value="2048">2048</option>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label text-white-50 small"><?php echo t('labs.height', 'Height'); ?></label>
                <select name="height" id="labs-height-select" class="knd-select form-select form-select-sm text-white">
                  <option value="256">256</option>
                  <option value="512" selected>512</option>
                  <option value="768">768</option>
                  <option value="1024">1024</option>
                  <option value="1152">1152</option>
                  <option value="1280">1280</option>
                  <option value="1536">1536</option>
                  <option value="2048">2048</option>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label text-white-50 small"><?php echo t('labs.batch', 'Batch'); ?></label>
                <select name="batch_size" class="knd-select form-select form-select-sm text-white">
                  <option value="1" selected>1</option>
                  <option value="2">2</option>
                  <option value="3">3</option>
                  <option value="4">4</option>
                </select>
              </div>
            </div>
          </div>
        </div>
        <div class="ln-t2i-param-group ln-t2i-privacy">
          <div class="form-check">
            <input type="checkbox" name="private_image" id="labs-private-check" class="form-check-input" value="1">
            <label for="labs-private-check" class="form-check-label text-white-50 small"><?php echo t('labs.private_toggle', 'Keep this generation private'); ?></label>
          </div>
          <p class="text-white-50 small mb-0 mt-1" id="labs-microcopy-private"><?php echo t('labs.images_stored', 'Your images are private. Stored for 30 days.'); ?></p>
        </div>
      </div>
    </aside>
  </div>
</form>
