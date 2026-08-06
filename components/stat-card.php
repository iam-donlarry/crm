<?php
/**
 * Stat Card Component
 * 
 * @param string $title Metric label
 * @param string|int $value Metric numerical or text value
 * @param string $icon Lucide or Bootstrap icon name e.g. "funnel", "trophy", "list-check", "currency-dollar"
 * @param string $color Theme color: primary, success, info, warning, danger
 * @param string|null $subtext Optional context line e.g. "vs last month"
 */
function renderStatCard($title, $value, $icon = 'activity', $color = 'primary', $subtext = null) {
    $bgMap = [
        'primary' => 'background: #eef2ff; color: #12b1b0;',
        'success' => 'background: #ecfdf5; color: #10b981;',
        'info'    => 'background: #e0f2fe; color: #0284c7;',
        'warning' => 'background: #fffbeb; color: #f59e0b;',
        'danger'  => 'background: #fef2f2; color: #ef4444;'
    ];
    $iconStyle = $bgMap[$color] ?? $bgMap['primary'];
    ?>
    <div class="card border-0 shadow-sm h-100" style="border-radius: 14px; background: #ffffff; transition: transform 0.2s ease, box-shadow 0.2s ease;">
        <div class="card-body p-3 d-flex align-items-center justify-content-between">
            <div>
                <div class="text-uppercase fw-semibold text-muted mb-1" style="font-size: 0.725rem; letter-spacing: 0.05em; font-family: 'Outfit', sans-serif;">
                    <?php echo htmlspecialchars($title); ?>
                </div>
                <div class="h3 mb-0 fw-bold text-dark" style="font-family: 'Outfit', sans-serif;">
                    <?php echo htmlspecialchars($value); ?>
                </div>
                <?php if ($subtext): ?>
                    <div class="text-muted small mt-1" style="font-size: 0.775rem;">
                        <?php echo htmlspecialchars($subtext); ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; <?php echo $iconStyle; ?>">
                <i class="bi bi-<?php echo htmlspecialchars($icon); ?> fs-4"></i>
            </div>
        </div>
    </div>
    <?php
}
