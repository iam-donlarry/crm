<?php
/**
 * Button Component
 * 
 * @param string $label Button label
 * @param string $variant Color variant e.g. primary, outline-primary, secondary, danger, success
 * @param string|null $icon Icon name e.g. "plus-lg", "pencil", "trash"
 * @param string $type Button HTML type e.g. "button", "submit", "a"
 * @param string|null $href URL if type is "a"
 * @param string $extraClasses Additional CSS classes
 * @param string $extraAttrs Extra HTML attributes e.g. data-bs-toggle="modal"
 */
function renderButton($label, $variant = 'primary', $icon = null, $type = 'button', $href = null, $extraClasses = '', $extraAttrs = '') {
    $iconHtml = $icon ? '<i class="bi bi-' . htmlspecialchars($icon) . ' me-1"></i> ' : '';
    $classes = 'btn btn-' . htmlspecialchars($variant) . ' rounded-pill px-4 shadow-sm ' . htmlspecialchars($extraClasses);
    
    if ($type === 'a' || $href !== null) {
        ?>
        <a href="<?php echo htmlspecialchars($href ?? '#'); ?>" class="<?php echo $classes; ?>" <?php echo $extraAttrs; ?>>
            <?php echo $iconHtml . htmlspecialchars($label); ?>
        </a>
        <?php
    } else {
        ?>
        <button type="<?php echo htmlspecialchars($type); ?>" class="<?php echo $classes; ?>" <?php echo $extraAttrs; ?>>
            <?php echo $iconHtml . htmlspecialchars($label); ?>
        </button>
        <?php
    }
}
