<?php
/**
 * Modal Wrapper Component
 * 
 * @param string $id Modal element HTML ID
 * @param string $title Modal title text
 * @param callable $bodyCallback Callback function that outputs body content
 * @param string|null $footerButtons Optional HTML content for footer action buttons
 * @param string $size Modal size class: modal-sm, modal-lg, modal-xl or default empty
 */
function renderModal($id, $title, $bodyCallback, $footerButtons = null, $size = '') {
    ?>
    <div class="modal fade" id="<?php echo htmlspecialchars($id); ?>" tabindex="-1" aria-labelledby="<?php echo htmlspecialchars($id); ?>Label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered <?php echo htmlspecialchars($size); ?>">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-dark" id="<?php echo htmlspecialchars($id); ?>Label" style="font-family: 'Outfit', sans-serif;">
                        <?php echo htmlspecialchars($title); ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <?php if (is_callable($bodyCallback)) { call_user_func($bodyCallback); } ?>
                </div>
                <?php if ($footerButtons !== null): ?>
                    <div class="modal-footer border-0 pt-0 pb-4 px-4 gap-2">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                        <?php echo $footerButtons; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
