<?php
/**
 * Character Lab - Form component
 * Prompt, image upload, recent picker, category, KP cost
 */
if (!defined('CHARACTER_LAB_KP_COST')) {
    $kpCost = 25;
} else {
    $kpCost = (int) CHARACTER_LAB_KP_COST;
}
$categories = CHARACTER_LAB_CATEGORIES ?? [
    'human' => 'Human',
    'humanoid' => 'Humanoid',
    'fantasy' => 'Fantasy Character',
    'mascot' => 'Mascot',
    'creature' => 'Light Creature',
    'mecha' => 'Light Mecha',
];
?>
<div class="character-lab-form glass-card-neon p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="text-white mb-0">Character Lab</h4>
        <span class="ai-balance-badge" id="cl-balance"><i class="fas fa-coins me-1"></i><?php echo number_format($balance ?? 0); ?> KP</span>
    </div>
    <p class="text-white-50 small mb-2">
        <span class="badge bg-success me-1">Stylized Game-Ready</span>
        Cost: <strong id="cl-kp-cost"><?php echo (int) $kpCost; ?></strong> KP
    </p>
    <p class="text-warning small mb-3">
        <i class="fas fa-shield-alt me-1"></i>Safe mode: single full-body character, no celebrity likeness, no copyrighted content.
    </p>

    <form id="character-lab-form" enctype="multipart/form-data" onsubmit="return false;">
        <input type="hidden" name="mode" id="cl-mode" value="text">
        <input type="hidden" name="source_recent_job_id" id="cl-source-id" value="">
        <input type="hidden" name="source_recent_type" id="cl-source-type" value="">

        <div class="mb-3" id="cl-prompt-wrap">
            <label class="form-label text-white-50"><?php echo t('ai.text2img.prompt', 'Prompt'); ?></label>
            <textarea name="prompt" id="cl-prompt" class="form-control bg-dark text-white" rows="3" maxlength="2000"
                placeholder="Describe your character: e.g. female elf warrior, blue armor, silver hair"></textarea>
            <div class="form-text text-white-50 small">Single character, full body. We'll optimize for 3D.</div>
        </div>

        <div class="mb-3" id="cl-upload-wrap" style="display:none;">
            <label class="form-label text-white-50">Image upload</label>
            <div id="cl-dropzone" class="character-lab-dropzone rounded border border-secondary p-4 text-center">
                <input type="file" id="cl-file" name="image" accept="image/jpeg,image/jpg,image/png,image/webp" hidden>
                <div id="cl-dropzone-content">
                    <i class="fas fa-cloud-upload-alt fa-2x text-white-50 mb-2"></i>
                    <p class="mb-1 text-white-50">Drop image or click</p>
                    <small class="text-white-50">JPG, PNG, WEBP · max 10MB</small>
                </div>
                <div id="cl-preview-wrap" style="display:none;">
                    <img id="cl-preview" alt="Preview" class="img-fluid rounded" style="max-height:200px;">
                    <button type="button" id="cl-remove-img" class="btn btn-sm btn-outline-danger mt-2"><i class="fas fa-times"></i> Remove</button>
                </div>
            </div>
        </div>

        <div class="mb-3" id="cl-recent-wrap" style="display:none;">
            <label class="form-label text-white-50">Pick from recent</label>
            <div id="cl-recent-gallery" class="row g-2">
                <p class="text-white-50 small mb-0"><i class="fas fa-spinner fa-spin me-1"></i>Loading...</p>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label text-white-50 small">Category</label>
            <select name="category" id="cl-category" class="form-select form-select-sm bg-dark text-white">
                <?php foreach ($categories as $key => $label): ?>
                <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $key === 'human' ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="btn-group mb-3" role="group">
            <input type="radio" class="btn-check" name="mode-radio" id="mode-text" value="text" checked>
            <label class="btn btn-outline-secondary btn-sm" for="mode-text">Text only</label>
            <input type="radio" class="btn-check" name="mode-radio" id="mode-image" value="image">
            <label class="btn btn-outline-secondary btn-sm" for="mode-image">Image only</label>
            <input type="radio" class="btn-check" name="mode-radio" id="mode-text-image" value="text_image">
            <label class="btn btn-outline-secondary btn-sm" for="mode-text-image">Text + Image</label>
            <input type="radio" class="btn-check" name="mode-radio" id="mode-recent" value="recent_image">
            <label class="btn btn-outline-secondary btn-sm" for="mode-recent">Recent</label>
        </div>

        <button type="submit" id="cl-submit" class="btn btn-neon-primary w-100">
            <i class="fas fa-user-astronaut me-2"></i><?php echo t('ai.character.create', 'Create Character'); ?>
        </button>
    </form>
</div>
