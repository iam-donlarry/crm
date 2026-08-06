<?php
/**
 * Table Component
 * 
 * @param array $headers Array of header labels e.g. ['Name', 'Email', 'Status', 'Actions']
 * @param array $rows Array of row arrays matching headers
 * @param string $emptyMessage Message to display if rows array is empty
 */
function renderTable($headers = [], $rows = [], $emptyMessage = 'No records found.') {
    ?>
    <div class="table-responsive rounded-3 border bg-white shadow-sm" style="border-color: #f1f5f9 !important;">
        <table class="table table-hover align-middle mb-0" style="font-family: 'Outfit', sans-serif;">
            <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                <tr>
                    <?php foreach ($headers as $header): ?>
                        <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem; letter-spacing: 0.05em;">
                            <?php echo htmlspecialchars($header); ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="<?php echo count($headers); ?>" class="text-center py-5 text-muted small">
                            <i class="bi bi-inbox fs-3 d-block mb-2 text-muted"></i>
                            <?php echo htmlspecialchars($emptyMessage); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                                <td class="py-3 px-4" style="font-size: 0.875rem;">
                                    <?php echo $cell; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
