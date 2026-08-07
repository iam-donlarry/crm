<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';

if (!hasPermission('view_products')) {
    header("Location: " . BASE_URL . "/dashboard");
    exit();
}

require_once __DIR__ . '/../../includes/header.php';

$stmt = $pdo->query("SELECT * FROM products WHERE is_archived = 0 ORDER BY created_at DESC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid px-0">
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <?php renderBreadcrumb([['label' => 'Products', 'url' => '']]); ?>
            <h2 class="fw-bold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">Products & Services Catalog</h2>
            <p class="text-muted small mb-0">Manage items, pricing, inventory stock, and catalog categories.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if (hasPermission('download_products_template')): ?>
                <a href="<?php echo BASE_URL; ?>/assets/templates/products_template.csv" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm" download>
                    <i data-lucide="download" class="lucide-icon me-1"></i> Template
                </a>
            <?php endif; ?>
            <?php if (hasPermission('export_products')): ?>
                <button type="button" class="btn btn-outline-primary rounded-pill px-3 shadow-sm" onclick="exportTableToCSV('productsTable', 'products_export.csv')">
                    <i data-lucide="download-cloud" class="lucide-icon me-1"></i> Export CSV
                </button>
            <?php endif; ?>
            <?php if (hasPermission('add_products')): ?>
                <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="offcanvas" data-bs-target="#productOffcanvas" onclick="resetProductForm()">
                    <i data-lucide="plus" class="lucide-icon me-1"></i> Add Product
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Catalog Items', count($products), 'box', 'primary', 'Active inventory'); ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php 
                $activeCount = count(array_filter($products, fn($p) => $p['status'] === 'active'));
                renderStatCard('Active Items', $activeCount, 'check-circle', 'success'); 
            ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php 
                $lowStockCount = count(array_filter($products, fn($p) => $p['stock'] < 10));
                renderStatCard('Low Stock Items', $lowStockCount, 'alert-triangle', 'warning', 'Re-order needed'); 
            ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php 
                $catCount = count(array_unique(array_column($products, 'category')));
                renderStatCard('Categories', $catCount ?: 1, 'layers', 'info'); 
            ?>
        </div>
    </div>

    <!-- Table Container -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="p-3 border-bottom d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2" style="background: #f8fafc;">
                <div class="input-group input-group-sm" style="max-width: 320px;">
                    <span class="input-group-text bg-white border-end-0"><i data-lucide="search" class="lucide-icon text-muted"></i></span>
                    <input type="text" id="searchFilter" class="form-control border-start-0" placeholder="Search catalog by name or code..." onkeyup="filterProducts()">
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="productsTable" style="font-family: 'Outfit', sans-serif;">
                    <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <tr>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">SKU / Code</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Product Name</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Category</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Unit Price</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Stock Qty</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Status</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold text-end" style="font-size: 0.75rem;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted small">
                                    <i data-lucide="inbox" class="lucide-icon d-block mb-2 text-muted mx-auto"></i>
                                    No products found in catalog.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $p): ?>
                                <tr>
                                    <td class="py-3 px-4"><span class="badge badge-soft-secondary"><?php echo htmlspecialchars($p['code']); ?></span></td>
                                    <td class="py-3 px-4 fw-semibold text-dark"><?php echo htmlspecialchars($p['name']); ?></td>
                                    <td class="py-3 px-4 text-muted"><?php echo htmlspecialchars($p['category'] ?: 'General'); ?></td>
                                    <td class="py-3 px-4 fw-bold text-primary"><?php echo htmlspecialchars($p['currency']) . ' ' . number_format($p['price'], 2); ?></td>
                                    <td class="py-3 px-4 fw-semibold"><?php echo number_format($p['stock']); ?> <?php echo htmlspecialchars($p['unit'] ?: 'pcs'); ?></td>
                                    <td class="py-3 px-4">
                                        <?php if ($p['status'] === 'active'): ?>
                                            <span class="badge badge-soft-success rounded-pill">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-soft-secondary rounded-pill">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-end">
                                        <div class="dropdown">
                                            <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 dropdown-toggle shadow-sm" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.8rem; font-family: 'Outfit', sans-serif;">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 text-small">
                                                <?php if (hasPermission('edit_products')): ?>
                                                    <li>
                                                        <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="#" onclick="editProduct(<?php echo $p['id']; ?>); return false;">
                                                            <i data-lucide="edit-3" class="lucide-icon text-muted"></i> Edit Item Details
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                <?php if (hasPermission('archive_products') || hasPermission('delete_products')): ?>
                                                    <li><hr class="dropdown-divider my-1"></li>
                                                    <li>
                                                        <a class="dropdown-item py-2 text-danger d-flex align-items-center gap-2" href="#" onclick="archiveProduct(<?php echo $p['id']; ?>); return false;">
                                                            <i data-lucide="trash-2" class="lucide-icon text-danger"></i> Archive Item
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top p-3" id="productsPagination"></div>
        </div>
    </div>
</div>

<!-- 50% WIDTH OFFCANVAS SLIDER FOR NEW / EDIT PRODUCT -->
<div class="offcanvas offcanvas-end offcanvas-half" id="productOffcanvas" tabindex="-1" aria-labelledby="productOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title fw-bold text-dark" id="productOffcanvasTitle" style="font-family: 'Outfit', sans-serif;">Add Catalog Item</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <form id="productForm" onsubmit="saveProduct(event)">
        <div class="offcanvas-body p-4">
            <input type="hidden" id="productId" name="id">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">SKU / Item Code</label>
                    <input type="text" id="prodCode" name="code" class="form-control" placeholder="PRD-1001">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Product / Service Name <span class="text-danger">*</span></label>
                    <input type="text" id="prodName" name="name" class="form-control" placeholder="e.g. Software License" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Category</label>
                    <input type="text" id="prodCategory" name="category" class="form-control" placeholder="Software, Hardware, Services">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Unit Measurement</label>
                    <input type="text" id="prodUnit" name="unit" class="form-control" placeholder="pcs, hours, license, month">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Unit Price <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" id="prodPrice" name="price" class="form-control" placeholder="150.00" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Currency</label>
                    <select id="prodCurrency" name="currency" class="form-select">
                        <option value="USD" selected>USD ($)</option>
                        <option value="NGN">NGN (₦)</option>
                        <option value="GBP">GBP (£)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Available Stock Qty</label>
                    <input type="number" id="prodStock" name="stock" class="form-control" value="0">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Status</label>
                    <select id="prodStatus" name="status" class="form-select">
                        <option value="active" selected>Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">Product Description / Specifications</label>
                    <textarea id="prodDescription" name="description" class="form-control" rows="3" placeholder="Full product specifications..."></textarea>
                </div>
            </div>
        </div>
        <div class="offcanvas-footer gap-2 d-flex justify-content-end">
            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="offcanvas">Cancel</button>
            <button type="submit" class="btn btn-primary rounded-pill px-4">Save Product</button>
        </div>
    </form>
</div>

<script>
function resetProductForm() {
    document.getElementById('productForm').reset();
    document.getElementById('productId').value = '';
    document.getElementById('productOffcanvasTitle').textContent = 'Add Catalog Item';
}

function saveProduct(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(document.getElementById('productForm')).entries());
    fetch('<?php echo BASE_URL; ?>/ajax/products/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            showToast(res.message, 'success');
            setTimeout(() => window.location.reload(), 600);
        } else {
            showToast(res.message || 'Save failed', 'danger');
        }
    })
    .catch(err => showToast('Error processing request', 'danger'));
}

function editProduct(id) {
    fetch('<?php echo BASE_URL; ?>/ajax/products/get?id=' + id)
    .then(res => res.json())
    .then(res => {
        if (res.success && res.data) {
            const p = res.data;
            document.getElementById('productId').value = p.id;
            document.getElementById('prodCode').value = p.code || '';
            document.getElementById('prodName').value = p.name || '';
            document.getElementById('prodCategory').value = p.category || '';
            document.getElementById('prodUnit').value = p.unit || '';
            document.getElementById('prodPrice').value = p.price || 0;
            document.getElementById('prodCurrency').value = p.currency || 'USD';
            document.getElementById('prodStock').value = p.stock || 0;
            document.getElementById('prodStatus').value = p.status || 'active';
            document.getElementById('prodDescription').value = p.description || '';
            document.getElementById('productOffcanvasTitle').textContent = 'Edit Catalog Item';
            new bootstrap.Offcanvas(document.getElementById('productOffcanvas')).show();
        }
    });
}

async function archiveProduct(id) {
    const confirmed = await window.appConfirm({
        title: 'Archive Catalog Item',
        message: 'Are you sure you want to archive this product item from the active catalog?',
        confirmText: 'Yes, Archive Item',
        cancelText: 'Cancel',
        isDanger: true
    });
    if (!confirmed) return;

    fetch('<?php echo BASE_URL; ?>/ajax/products/delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            showToast(res.message, 'success');
            setTimeout(() => window.location.reload(), 600);
        } else {
            showToast(res.message, 'danger');
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    initTablePagination('productsTable', 'productsPagination', 'searchFilter', 25);
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
