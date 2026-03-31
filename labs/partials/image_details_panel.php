<?php
/**
 * Reusable Image Details panel for Labs tool pages.
 * Populated via JS when job data is available.
 */
?>
<div id="labs-image-details-panel" class="labs-image-details-panel mt-4 px-3" style="display:none;">
  <div class="labs-metadata-header"><i class="fas fa-info-circle me-1"></i><?php echo t('labs.image_details', 'Image Details'); ?></div>
  <div id="labs-image-details-body" class="small">
    <div class="labs-metadata-grid" id="labs-details-rows"></div>
  </div>
  <div id="labs-image-details-actions" class="mt-2 d-flex flex-wrap gap-1" style="display:none !important;"></div>
</div>
