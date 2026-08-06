<?php
/**
 * Breadcrumb Component
 * 
 * @param array $items Array of ['label' => 'Name', 'url' => '/path'] (url can be empty for active item)
 */
function renderBreadcrumb($items = []) {
    if (empty($items)) return;
    ?>
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb bg-transparent p-0 mb-0" style="font-family: 'Outfit', sans-serif; font-size: 0.875rem;">
            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/dashboard" class="text-decoration-none text-muted"><i class="bi bi-house-door me-1"></i> Dashboard</a></li>
            <?php foreach ($items as $index => $item): ?>
                <?php if (!empty($item['url']) && $index < count($items) - 1): ?>
                    <li class="breadcrumb-item"><a href="<?php echo htmlspecialchars($item['url']); ?>" class="text-decoration-none text-muted"><?php echo htmlspecialchars($item['label']); ?></a></li>
                <?php else: ?>
                    <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page"><?php echo htmlspecialchars($item['label']); ?></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ol>
    </nav>
    <?php
}
