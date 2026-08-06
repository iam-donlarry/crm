<?php
/**
 * Empty State Component
 * 
 * @param string $title Headline message
 * @param string $description Subtitle description
 * @param string $icon Icon name e.g. "inbox", "search", "folder-x"
 * @param string|null $actionUrl Optional action button link
 * @param string|null $actionLabel Optional action button label
 * @param string|null $modalTarget Optional data-bs-target modal id
 */
function renderEmptyState($title, $description, $icon = 'inbox', $actionUrl = null, $actionLabel = null, $modalTarget = null) {
    ?>
    <div class="text-center py-5 px-3 my-4 bg-white rounded-3 border" style="border-color: #f1f5f9 !important;">
        <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px; background: #f8fafc; color: #94a3b8;">
            <i class="bi bi-<?php echo htmlspecialchars($icon); ?> fs-2"></i>
        </div>
        <h5 class="fw-bold text-dark mb-1" style="font-family: 'Outfit', sans-serif;"><?php echo htmlspecialchars($title); ?></h5>
        <p class="text-muted small mx-auto mb-4" style="max-width: 420px; font-size: 0.875rem;"><?php echo htmlspecialchars($description); ?></p>
        <?php if ($actionLabel): ?>
            <?php if ($modalTarget): ?>
                <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="<?php echo htmlspecialchars($modalTarget); ?>">
                    <i class="bi bi-plus-lg me-1"></i> <?php echo htmlspecialchars($actionLabel); ?>
                </button>
            <?php elseif ($actionUrl): ?>
                <a href="<?php echo htmlspecialchars($actionUrl); ?>" class="btn btn-primary rounded-pill px-4 shadow-sm">
                    <i class="bi bi-plus-lg me-1"></i> <?php echo htmlspecialchars($actionLabel); ?>
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}
