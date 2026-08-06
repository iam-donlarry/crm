<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';

if (!hasPermission('view_products')) {
    header("Location: " . BASE_URL . "/dashboard");
    exit();
}

require_once __DIR__ . '/../../includes/header.php';

$stmt = $pdo->query("SELECT * FROM products WHERE is_archived = 0 ORDER BY name ASC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <?php renderBreadcrumb([['label' => 'Products', 'url' => '']]); ?>
            <h2 class="fw-bold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">Products & Services Catalog</h2>
            <p class="text-muted small mb-0">Manage product inventory, line item pricing, units, and categories.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if (hasPermission('add_products')): ?>
                <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#productModal" onclick="resetProductForm()">
                    <i class="bi bi-plus-lg me-1"></i> Add Product
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Total Catalog Items', count($products), 'box-seam', 'primary'); ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php 
                $activeCount = count(array_filter($products, fn($p) => $p['status'] === 'active'));
                renderStatCard('Active Items', $activeCount, 'check-circle', 'success'); 
            ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php 
                $totalStock = array_sum(array_column($products, 'stock'));
                renderStatCard('In-Stock Units', number_format($totalStock), 'layers', 'info'); 
            ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Base Currency', 'USD / NGN', 'currency-dollar', 'warning'); ?>
        </div>
    </div>

    <!-- Table Container -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-family: 'Outfit', sans-serif;">
                    <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <tr>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Code</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Product Name</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Category</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Price</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Stock</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Status</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold text-end" style="font-size: 0.75rem;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted small">No products found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $p): ?>
                                <tr>
                                    <td class="py-3 px-4"><span class="badge badge-soft-secondary"><?php echo htmlspecialchars($p['code'] ?: 'N/A'); ?></span></td>
                                    <td class="py-3 px-4 fw-semibold text-dark"><?php echo htmlspecialchars($p['name']); ?></td>
                                    <td class="py-3 px-4 text-muted"><?php echo htmlspecialchars($p['category'] ?: 'General'); ?></td>
                                    <td class="py-3 px-4 fw-semibold text-dark"><?php echo htmlspecialchars($p['currency']) . ' ' . number_format($p['price'], 2); ?></td>
                                    <td class="py-3 px-4 text-muted"><?php echo number_format($p['stock']); ?></td>
                                    <td class="py-3 px-4">
                                        <span class="badge rounded-pill <?php echo $p['status'] === 'active' ? 'badge-soft-success' : 'badge-soft-secondary'; ?>">
                                            <?php echo ucfirst($p['status']); ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-end">
                                        <?php if (hasPermission('edit_products')): ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-circle me-1" title="Edit" onclick="editProduct(<?php echo $p['id']; ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Product Modal Form -->
<div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-dark" id="productModalTitle" style="font-family: 'Outfit', sans-serif;">Add New Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="productForm" onsubmit="saveProduct(event)">
                <div class="modal-body p-4">
                    <input type="hidden" id="productId" name="id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Product Name</label>
                            <input type="text" id="prodName" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Product Code</label>
                            <input type="text" id="prodCode" name="code" class="form-control" placeholder="PRD-XXXX">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Unit Price</label>
                            <input type="number" step="0.01" id="prodPrice" name="price" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Currency</label>
                            <select id="prodCurrency" name="currency" class="form-select">
                                <option value="USD">USD ($)</option>
                                <option value="NGN">NGN (₦)</option>
                                <option value="GBP">GBP (£)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Category</label>
                            <input type="text" id="prodCategory" name="category" class="form-control" placeholder="Hardware, Software, Service...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Stock Quantity</label>
                            <input type="number" id="prodStock" name="stock" class="form-control" value="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Description</label>
                            <textarea id="prodDescription" name="description" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4 gap-2">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetProductForm() {
    document.getElementById('productForm').reset();
    document.getElementById('productId').value = '';
    document.getElementById('productModalTitle').textContent = 'Add New Product';
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
            showToast(res.message, 'danger');
        }
    });
}

function editProduct(id) {
    fetch('<?php echo BASE_URL; ?>/ajax/products/get?id=' + id)
    .then(res => res.json())
    .then(res => {
        if (res.success && res.data) {
            const p = res.data;
            document.getElementById('productId').value = p.id;
            document.getElementById('prodName').value = p.name || '';
            document.getElementById('prodCode').value = p.code || '';
            document.getElementById('prodPrice').value = p.price || 0;
            document.getElementById('prodCurrency').value = p.currency || 'USD';
            document.getElementById('prodCategory').value = p.category || '';
            document.getElementById('prodStock').value = p.stock || 0;
            document.getElementById('prodDescription').value = p.description || '';
            document.getElementById('productModalTitle').textContent = 'Edit Product';
            new bootstrap.Modal(document.getElementById('productModal')).show();
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
