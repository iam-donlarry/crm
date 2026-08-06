<?php
/**
 * Pagination Component
 * 
 * @param int $currentPage Active page number
 * @param int $totalPages Total number of pages
 * @param string $baseUrl Base URL for pagination links (uses query param page=N)
 */
function renderPagination($currentPage, $totalPages, $baseUrl) {
    if ($totalPages <= 1) return;
    
    // Helper to format page URL
    $getUrl = function($page) use ($baseUrl) {
        $delimiter = (strpos($baseUrl, '?') !== false) ? '&' : '?';
        return htmlspecialchars($baseUrl . $delimiter . 'p=' . $page);
    };
    ?>
    <nav class="d-flex justify-content-between align-items-center mt-3" aria-label="Page navigation">
        <div class="text-muted small" style="font-size: 0.8125rem;">
            Page <span class="fw-bold text-dark"><?php echo $currentPage; ?></span> of <span class="fw-bold text-dark"><?php echo $totalPages; ?></span>
        </div>
        <ul class="pagination pagination-sm mb-0 gap-1" style="font-family: 'Outfit', sans-serif;">
            <li class="page-item <?php echo ($currentPage <= 1) ? 'disabled' : ''; ?>">
                <a class="page-item-link btn btn-sm btn-outline-light text-dark border-0 rounded-circle" href="<?php echo $getUrl($currentPage - 1); ?>" aria-label="Previous">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i == 1 || $i == $totalPages || ($i >= $currentPage - 2 && $i <= $currentPage + 2)): ?>
                    <li class="page-item <?php echo ($i == $currentPage) ? 'active' : ''; ?>">
                        <a class="page-link rounded-circle border-0 <?php echo ($i == $currentPage) ? 'bg-primary text-white fw-bold' : 'text-dark'; ?>" href="<?php echo $getUrl($i); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php elseif ($i == 2 || $i == $totalPages - 1): ?>
                    <li class="page-item disabled"><span class="page-link border-0 text-muted">...</span></li>
                <?php endif; ?>
            <?php endfor; ?>
            
            <li class="page-item <?php echo ($currentPage >= $totalPages) ? 'disabled' : ''; ?>">
                <a class="page-item-link btn btn-sm btn-outline-light text-dark border-0 rounded-circle" href="<?php echo $getUrl($currentPage + 1); ?>" aria-label="Next">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        </ul>
    </nav>
    <?php
}
