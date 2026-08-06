<?php
require_once '../../includes/header.php';
$dealId = $_GET['id'] ?? null;
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2><?php echo $dealId !== null ? 'Edit Deal' : 'Create New Deal'; ?></h2>
            <p class="text-muted mb-0" id="dealIdDisplay">Deal ID: <strong>--</strong></p>
        </div>
        <div>
            <button class="btn btn-info me-2" onclick="openPrintModal()" id="printBtn" style="display:none;">
                <i class="bi bi-printer"></i> Print
            </button>
            <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Deals</a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Deal Basic Info -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Deal Information</h6>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Deal Title *</label>
                            <input type="text" class="form-control" id="dealTitle" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Expected Value *</label>
                            <input type="number" class="form-control" id="expectedValue" step="0.01" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Valid Until</label>
                            <input type="date" class="form-control" id="validUntil">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="dueDate">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Currency</label>
                            <select class="form-select" id="currencySelect" onchange="updateCurrencySymbol()">
                                <option value="1" data-symbol="$">USD</option>
                                <option value="2" data-symbol="£">GBP</option>
                                <option value="3" data-symbol="₦">NGN</option>
                                <option value="4" data-symbol="€">EUR</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Terms & Conditions</label>
                        <textarea class="form-control" id="terms" rows="4"></textarea>
                    </div>
                </div>
            </div>

            <!-- Line Items with Full Features -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Line Items / Products</h6>
                </div>
                <div class="card-body">
                    <!-- Dual Currency Section -->
                    <div class="mb-4 p-3 border rounded bg-light">
                        <div class="d-flex align-items-center gap-3">
                            <input class="form-check-input" type="checkbox" id="dualCurrencyCheck" onchange="toggleDualCurrency()" 
                                   style="width: 1.5rem; height: 1.5rem; border: 2.5px solid #0d6efd; border-radius: 0.25rem; cursor: pointer; margin: 0;">
                            <label class="fw-bold mb-0" for="dualCurrencyCheck" style="cursor: pointer; font-size: 1rem;">
                                Dual Currency Mode
                            </label>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="openExchangeRateModal()">
                                <i class="bi bi-currency-exchange"></i>
                            </button>
                            <small id="customRateIndicator" class="text-muted d-none">
                                <i class="bi bi-info-circle"></i> Custom rates
                            </small>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6 d-none" id="secondaryCurrencyDiv">
                            <label class="form-label">Secondary Currency</label>
                            <select class="form-select" id="secCurrencySelect" onchange="calculateTotals()">
                                <option value="1">USD</option>
                                <option value="2">GBP</option>
                                <option value="3">NGN</option>
                                <option value="4">EUR</option>
                            </select>
                        </div>
                    </div>

                    <div id="noProductsAlert" class="alert alert-warning d-none mb-3">
                        <i class="bi bi-exclamation-triangle"></i> No products available. 
                        <a href="../documents/product.php">Create products first</a>
                    </div>

                    <!-- Compact Exchange Rate Info -->
                    <div class="alert d-flex align-items-center justify-content-between py-2 mb-3" style="background: linear-gradient(135deg, #12b1b0 0%, #0d8f8e 100%); border: none; margin-bottom: 1rem !important;">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-currency-exchange text-white me-2" style="font-size: 1.2rem;"></i>
                            <small class="text-white mb-0 fw-bold">Exchange Rate:</small>
                        </div>
                        <div class="text-end">
                            <strong class="text-white">$1 = ₦<span id="defaultRateDisplay">1,550.00</span></strong>
                            <small class="text-white-50 d-block" id="rateStatus" style="font-size: 0.7rem;">Default Rate</small>
                        </div>
                    </div>

                    <table class="table table-bordered" id="itemsTable">
                        <thead>
                            <tr>
                                <th width="18%">Product Name</th>
                                <th width="22%">Details</th>
                                <th width="8%">Qty</th>
                                <th width="10%">Unit Price (<span id="primaryCurrencyCode">USD</span>)</th>
                                <th width="12%">Total (<span id="primaryCurrencyCodeTotal">USD</span>)</th>
                                <th width="10%" class="secondary-currency-col d-none">Unit Price (<span class="sec-currency-code">GBP</span>)</th>
                                <th width="12%" class="secondary-currency-col d-none">Total (<span class="sec-currency-code">GBP</span>)</th>
                                <th width="3%"></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="text-end fw-bold">Subtotal:</td>
                                <td class="fw-bold"><span id="subtotalDisplay">$0.00</span></td>
                                <td class="secondary-currency-col d-none"></td>
                                <td class="secondary-currency-col d-none fw-bold"><span id="secondarySubtotalDisplay">0.00</span></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="4" class="text-end fw-bold">VAT (7.5%):</td>
                                <td><span id="vatDisplay">$0.00</span></td>
                                <td class="secondary-currency-col d-none"></td>
                                <td class="secondary-currency-col d-none"><span id="secondaryVatDisplay">0.00</span></td>
                                <td></td>
                            </tr>
                            <tr class="table-light">
                                <td colspan="4" class="text-end fw-bold">Total (Inc. VAT):</td>
                                <td class="fw-bold text-primary"><span id="totalDisplay">$0.00</span></td>
                                <td class="secondary-currency-col d-none"></td>
                                <td class="secondary-currency-col d-none fw-bold text-primary"><span id="secondaryTotalDisplay">0.00</span></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addItem()">
                        <i class="bi bi-plus"></i> Add Item
                    </button>
                </div>
            </div>
        </div>

        <!-- Right Column: Pipeline -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Pipeline Stage</h6>
                </div>
                <div class="card-body">
                    <select class="form-select mb-3" id="currentStage" onchange="updateStageInfo()">
                        <option value="value_proposition">1. Value Proposition</option>
                        <option value="proposal_quote">2. Proposal / Quote</option>
                        <option value="negotiation_review">3. Negotiation / Review</option>
                        <option value="qualification">4. Qualification</option>
                        <option value="closed_won">5. Closed Won</option>
                        <option value="closed_lost">5. Closed Lost</option>
                    </select>
                    <div class="progress mb-3" style="height: 20px;">
                        <div class="progress-bar" id="stageProgress" style="width: 11%">11%</div>
                    </div>
                    <div id="stageDescription" class="text-muted small mb-3"></div>
                    <label class="form-label">Stage Notes</label>
                    <textarea class="form-control mb-3" id="stageNotes" rows="4"></textarea>
                    <button class="btn btn-primary btn-sm w-100 mb-2" onclick="moveToNextStage()">
                        <i class="bi bi-arrow-right-circle"></i> Move to Next Stage
                    </button>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Deal Status</h6>
                </div>
                <div class="card-body">
                    <select class="form-select mb-3" id="dealStatus">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-body">
                    <button class="btn btn-primary w-100 btn-lg" onclick="saveDeal()">
                        <i class="bi bi-save"></i> Save Deal
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print Modal -->
<div class="modal fade" id="printModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-printer"></i> Print Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Document Type</label>
                    <select class="form-select" id="docType" onchange="updatePrintPreview()">
                        <option value="quote">Quote</option>
                        <option value="proposal">Proposal</option>
                        <option value="invoice">Invoice</option>
                    </select>
                </div>
                <hr>
                <div id="printPreview" style="background: white; padding: 40px; min-height: 400px;">
                    <!-- Print content will be generated here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printDocument()">
                    <i class="bi bi-printer-fill"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Exchange Rate Modal -->
<div class="modal fade" id="exchangeRateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-currency-exchange"></i> Exchange Rate Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3"><i class="bi bi-info-circle"></i> Custom rates for this deal only</p>
                <div class="mb-3">
                    <label class="form-label">USD to GBP</label>
                    <input type="number" class="form-control" id="rateGBP" step="0.0001" min="0" placeholder="0.79">
                    <small class="text-muted">1 USD = ? GBP</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">USD to NGN</label>
                    <input type="number" class="form-control" id="rateNGN" step="0.01" min="0" placeholder="1550.00">
                    <small class="text-muted">1 USD = ? NGN</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">USD to EUR</label>
                    <input type="number" class="form-control" id="rateEUR" step="0.0001" min="0" placeholder="0.92">
                    <small class="text-muted">1 USD = ? EUR</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="resetRatesToDefault()">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset to Default
                </button>
                <button type="button" class="btn btn-primary" onclick="applyCustomRates()" data-bs-dismiss="modal">
                    <i class="bi bi-check-lg"></i> Apply Rates
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const dealId = <?php echo $dealId !== null ? $dealId : 'null'; ?>;

const DealsAPI = {
    url: 'index.php?api=true',
    async get(id) {
        const res = await fetch(`${this.url}&id=${id}`);
        return await res.json();
    },
    async create(data) {
        const res = await fetch(this.url, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        return await res.json();
    },
    async update(data) {
        const res = await fetch(this.url, {
            method: 'PUT',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        return await res.json();
    }
};
let currentDeal = null;
let currentDealId = '';
let currentSymbol = '$';
let currencyRates = { usd: 1.00, gbp: 0.79, ngn: 1550.00, eur: 0.92 };
let defaultRates = { usd: 1.00, gbp: 0.79, ngn: 1550.00, eur: 0.92 };
let usingCustomRates = false;

const stageDescriptions = {
    value_proposition: "Define the unique value your solution provides to the client.",
    proposal_quote: "Create proposal and generate quote with pricing details.",
    negotiation_review: "Negotiate terms, handle objections, and conduct reviews.",
    qualification: "Final qualifications - credit check, contract signing, payment setup.",
    closed_won: "Deal successfully closed and won!",
    closed_lost: "Deal was lost. Document learnings for future improvement."
};

const stageOrder = ['value_proposition', 'proposal_quote', 'negotiation_review', 'qualification', 'closed_won', 'closed_lost'];

// Replace the initialization to be defensive
document.removeEventListener && document.removeEventListener('DOMContentLoaded', null); // safe no-op

let cachedProducts = [];

document.addEventListener('DOMContentLoaded', async function() {
	try {
        await loadProducts(); // Fetch products from API first
		checkProductAvailability();
		if (dealId !== null) {
			loadDeal();
		} else {
			addItem();
		}
		updateStageInfo();
	} catch (err) {
		// Prevent initialization errors from freezing UI
		console.error('Initialization error on deals/create:', err);
        showToast('Error loading page data: ' + err.message, 'error');
		// Best-effort fallbacks (silently set safe defaults)
		try { document.getElementById('subtotalDisplay').innerText = currentSymbol + ' 0.00'; } catch(e){}
		try { document.getElementById('vatDisplay').innerText = currentSymbol + ' 0.00'; } catch(e){}
		try { document.getElementById('totalDisplay').innerText = currentSymbol + ' 0.00'; } catch(e){}
	}
});

async function loadProducts() {
    try {
        const response = await fetch('../products/?api=true');
        const result = await response.json();
        if (result.success) {
            cachedProducts = result.data;
        } else {
            console.error('Failed to load products API:', result.message);
            // Fallback to local storage if API fails, though ideally API should source of truth
            cachedProducts = CRMStorage.get(CRMStorage.KEYS.PRODUCTS) || []; 
        }
    } catch (e) {
        console.error('Error fetching products:', e);
        cachedProducts = CRMStorage.get(CRMStorage.KEYS.PRODUCTS) || [];
    }
}

async function loadDeal() {
    try {
        const result = await DealsAPI.get(dealId);
        
        if (!result.success || !result.data) {
            showToast('Deal not found!', 'error');
            return;
        }
        
        currentDeal = result.data;
        
        // Display and generate Deal ID
        currentDealId = 'DEAL-' + String(currentDeal.id).padStart(3, '0');
        document.getElementById('dealIdDisplay').innerHTML = `Deal ID: <strong>${currentDealId}</strong>`;
        document.getElementById('printBtn').style.display = 'inline-block';
        
        document.getElementById('dealTitle').value = currentDeal.title || '';
        document.getElementById('expectedValue').value = currentDeal.value || '';
        document.getElementById('validUntil').value = currentDeal.valid_until || '';
        document.getElementById('dueDate').value = ''; 
        document.getElementById('description').value = ''; 

        document.getElementById('currentStage').value = currentDeal.stage || 'value_proposition';
        document.getElementById('dealStatus').value = currentDeal.status || 'active';
        
        if (currentDeal.lineItems && currentDeal.lineItems.length > 0) {
            currentDeal.lineItems.forEach(item => {
                addItemWithData({
                    productId: item.product_id,
                    productName: item.product_name,
                    description: item.description,
                    quantity: item.quantity,
                    price: item.price,
                    total: item.total
                });
            });
        } else {
            addItem();
        }
        
        updateStageInfo();
        calculateTotals();
    } catch (e) {
        console.error('Error loading deal:', e);
        showToast('Error loading deal from server', 'error');
    }
}

function checkProductAvailability() {
    const products = cachedProducts;
    const alert = document.getElementById('noProductsAlert');
    if (products.length === 0 && alert) {
        alert.classList.remove('d-none');
    } else if (alert) {
        alert.classList.add('d-none');
    }
}

function addItem() {
    const tbody = document.querySelector('#itemsTable tbody');
    const products = cachedProducts;
    
    // Warn but allow adding manual items if needed, or strictly enforce? 
    // User said "not converting", implying items might be missing.
    // If we have no products, we can't show dropdown options.
    
    const tr = document.createElement('tr');
    const isDualCurrency = document.getElementById('dualCurrencyCheck').checked;
    const secondaryDisplayClass = isDualCurrency ? '' : 'd-none';
    
    const productOptions = products.map(product => {
        // Handle API product structure (unit instead of sku?)
        const code = product.code || product.sku || ''; 
        const displayName = code ? `${product.name} (${code})` : product.name;
        // API might return price as string or number
        return `<option value="${product.id}" data-name="${product.name}" data-price="${product.price}" data-description="${product.description || ''}">${displayName}</option>`;
    }).join('');

    tr.innerHTML = `
        <td>
            <select class="form-select product-select" onchange="selectProductFromDropdown(this)" required>
                <option value="">-- Select Product --</option>
                ${productOptions}
            </select>
            <input type="hidden" class="product-name-hidden">
        </td>
        <td><textarea class="form-control product-details" rows="2" readonly style="resize: none; background-color: #f8f9fa; font-size: 0.85rem;"></textarea></td>
        <td><input type="number" class="form-control qty-input" value="1" min="1" onchange="calculateRow(this)"></td>
        <td><input type="number" class="form-control product-price" step="0.01" readonly style="background-color: #f8f9fa;"></td>
        <td><input type="text" class="form-control total-input" readonly style="background-color: #f8f9fa;"></td>
        <td class="secondary-currency-col ${secondaryDisplayClass}"><input type="text" class="form-control sec-unit-price" readonly style="background-color: #f8f9fa;"></td>
        <td class="secondary-currency-col ${secondaryDisplayClass}"><input type="text" class="form-control sec-total" readonly style="background-color: #f8f9fa;"></td>
        <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove(); calculateTotals()"><i class="bi bi-trash"></i></button></td>
    `;
    tbody.appendChild(tr);
}

function addItemWithData(item) {
    const tbody = document.querySelector('#itemsTable tbody');
    const tr = document.createElement('tr');
    const isDualCurrency = document.getElementById('dualCurrencyCheck').checked;
    const secondaryDisplayClass = isDualCurrency ? '' : 'd-none';
    const products = cachedProducts;

    const productOptions = products.map(product => {
        const code = product.code || product.sku || ''; 
        const displayName = code ? `${product.name} (${code})` : product.name;
        // Select logic
        // If item has productId, match it. If not, maybe match name?
        // Lead conversion passes productName.
        const selected = (item.productId && item.productId == product.id) || (item.productName && item.productName === product.name) ? 'selected' : '';
        return `<option value="${product.id}" data-name="${product.name}" data-price="${product.price}" data-description="${product.description || ''}" ${selected}>${displayName}</option>`;
    }).join('');
    
    // If no match found but we have data, we just show "Selected: X" ? 
    // Or just putting the option there. 
    // If product list is valid, we constructed options.
    
    tr.innerHTML = `
        <td>
            <select class="form-select product-select" onchange="selectProductFromDropdown(this)">
                <option value="">-- Select Product --</option>
                ${productOptions}
            </select>
            <input type="hidden" class="product-name-hidden" value="${item.productName || item.description}">
        </td>
        <td><textarea class="form-control product-details" rows="2" readonly style="resize: none; background-color: #f8f9fa; font-size: 0.85rem;">${item.description || ''}</textarea></td>
        <td><input type="number" class="form-control qty-input" value="${item.quantity || 1}" min="1" onchange="calculateRow(this)"></td>
        <td><input type="number" class="form-control product-price" value="${item.price || 0}" step="0.01" readonly style="background-color: #f8f9fa;"></td>
        <td><input type="text" class="form-control total-input" value="${item.total || 0}" readonly style="background-color: #f9f9fa;"></td>
        <td class="secondary-currency-col ${secondaryDisplayClass}"><input type="text" class="form-control sec-unit-price" readonly style="background-color: #f9f9fa;"></td>
        <td class="secondary-currency-col ${secondaryDisplayClass}"><input type="text" class="form-control sec-total" readonly style="background-color: #f9f9fa;"></td>
        <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove(); calculateTotals()"><i class="bi bi-trash"></i></button></td>
    `;
    tbody.appendChild(tr);
    // calculateRow(tr.querySelector('.qty-input')); // Don't recalculate immediately if we trust item.total, but better to recalc to ensure consistency?
    // Conversion sets total, but let's sync up.
    calculateRow(tr.querySelector('.qty-input'));
}

function selectProductFromDropdown(selectElement) {
    const row = selectElement.closest('tr');
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    
    if (!selectedOption.value) {
        row.querySelector('.product-name-hidden').value = '';
        row.querySelector('.product-details').value = '';
        row.querySelector('.product-price').value = '';
        row.querySelector('.total-input').value = '';
        return;
    }
    
    const productName = selectedOption.getAttribute('data-name');
    const productPrice = parseFloat(selectedOption.getAttribute('data-price'));
    const productDescription = selectedOption.getAttribute('data-description');
    
    row.querySelector('.product-name-hidden').value = productName;
    row.querySelector('.product-details').value = productDescription || 'No description';
    row.querySelector('.product-price').value = productPrice.toFixed(2);
    
    calculateRow(row.querySelector('.product-price'));
}

function updateCurrencySymbol() {
    const select = document.getElementById('currencySelect');
    const option = select.options[select.selectedIndex];
    currentSymbol = option.getAttribute('data-symbol');
    
    // Update primary currency code in table headers
    const currencyCode = option.textContent; // USD, GBP, NGN, or EUR
    const primaryCodeElements = document.querySelectorAll('#primaryCurrencyCode, #primaryCurrencyCodeTotal');
    primaryCodeElements.forEach(el => el.textContent = currencyCode);
    
    calculateTotals();
}

function toggleDualCurrency() {
    const check = document.getElementById('dualCurrencyCheck');
    const div = document.getElementById('secondaryCurrencyDiv');
    const secondaryCols = document.querySelectorAll('.secondary-currency-col');
    
    if (check.checked) {
        div.classList.remove('d-none');
        secondaryCols.forEach(col => col.classList.remove('d-none'));
    } else {
        div.classList.add('d-none');
        secondaryCols.forEach(col => col.classList.add('d-none'));
    }
    calculateTotals();
}

function calculateRow(input) {
    const tr = input.closest('tr');
    const qty = parseFloat(tr.querySelector('.qty-input').value) || 0;
    const price = parseFloat(tr.querySelector('.product-price').value) || 0;
    const total = qty * price;
    tr.querySelector('.total-input').value = total.toFixed(2);
    calculateTotals();
}

function calculateTotals() {
    let subtotal = 0;
    document.querySelectorAll('.total-input').forEach(input => {
        subtotal += parseFloat(input.value) || 0;
    });
    
    const vat = subtotal * 0.075;
    const total = subtotal + vat;
    
    document.getElementById('subtotalDisplay').innerText = currentSymbol + ' ' + subtotal.toFixed(2);
    document.getElementById('vatDisplay').innerText = currentSymbol + ' ' + vat.toFixed(2);
    document.getElementById('totalDisplay').innerText = currentSymbol + ' ' + total.toFixed(2);
    
    const dualCurrencyCheck = document.getElementById('dualCurrencyCheck');
    if (dualCurrencyCheck && dualCurrencyCheck.checked) {
        updateSecondaryCurrencyTotal(subtotal, vat, total);
    }
}

function updateSecondaryCurrencyTotal(primarySubtotal, primaryVat, primaryTotal) {
    const secCurrencySelect = document.getElementById('secCurrencySelect');
    if (!secCurrencySelect || secCurrencySelect.selectedIndex < 0) return;

    const selectedCurrencyCode = secCurrencySelect.options[secCurrencySelect.selectedIndex].text;
    
    let conversionRate = 1.00;
    let currencySymbol = '$';
    
    if (selectedCurrencyCode.includes('GBP')) {
        conversionRate = currencyRates.gbp || 0.79;
        currencySymbol = '£';
    } else if (selectedCurrencyCode.includes('NGN')) {
        conversionRate = currencyRates.ngn || 1550.00;
        currencySymbol = '₦';
    } else if (selectedCurrencyCode.includes('USD')) {
        conversionRate = 1.00;
        currencySymbol = '$';
    } else if (selectedCurrencyCode.includes('EUR')) {
        conversionRate = 0.92;
        currencySymbol = '€';
    }
    
    document.querySelectorAll('.sec-currency-code').forEach(el => el.textContent = selectedCurrencyCode);

    const rows = document.querySelectorAll('#itemsTable tbody tr');
    rows.forEach(tr => {
        const primaryPriceInput = tr.querySelector('.product-price');
        const primaryTotalInput = tr.querySelector('.total-input');
        const secUnitPriceInput = tr.querySelector('.sec-unit-price');
        const secTotalInput = tr.querySelector('.sec-total');
        
        if (primaryPriceInput && primaryTotalInput) {
            const primaryPrice = parseFloat(primaryPriceInput.value) || 0;
            const primaryTotal = parseFloat(primaryTotalInput.value) || 0;
            
            const secondaryUnitPrice = primaryPrice * conversionRate;
            const secondaryTotal = primaryTotal * conversionRate;
            
            if (secUnitPriceInput) {
                secUnitPriceInput.value = currencySymbol + secondaryUnitPrice.toFixed(2);
            }
            if (secTotalInput) {
                secTotalInput.value = currencySymbol + secondaryTotal.toFixed(2);
            }
        }
    });
    
    const secondarySubtotal = primarySubtotal * conversionRate;
    const secondaryVat = primaryVat * conversionRate;
    const secondaryTotal = primaryTotal * conversionRate;
    
    const secSubEl = document.getElementById('secondarySubtotalDisplay');
    const secVatEl = document.getElementById('secondaryVatDisplay');
    const secTotalEl = document.getElementById('secondaryTotalDisplay');

    if (secSubEl) secSubEl.textContent = currencySymbol + secondarySubtotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (secVatEl) secVatEl.textContent = currencySymbol + secondaryVat.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (secTotalEl) secTotalEl.textContent = currencySymbol + secondaryTotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function openExchangeRateModal() {
    document.getElementById('rateGBP').value = currencyRates.gbp;
    document.getElementById('rateNGN').value = currencyRates.ngn;
    document.getElementById('rateEUR').value = currencyRates.eur;
    
    const modal = new bootstrap.Modal(document.getElementById('exchangeRateModal'));
    modal.show();
}

function applyCustomRates() {
    const gbp = parseFloat(document.getElementById('rateGBP').value);
    const ngn = parseFloat(document.getElementById('rateNGN').value);
    const eur = parseFloat(document.getElementById('rateEUR').value);
    
    let updated = false;
    
    if (!isNaN(gbp) && gbp > 0) {
        currencyRates.gbp = gbp;
        updated = true;
    }
    if (!isNaN(ngn) && ngn > 0) {
        currencyRates.ngn = ngn;
        updated = true;
    }
    if (!isNaN(eur) && eur > 0) {
        currencyRates.eur = eur;
        updated = true;
    }
    
    if (updated) {
        usingCustomRates = true;
        updateRateIndicator();
        calculateTotals();
    }
}

function resetRatesToDefault() {
    currencyRates.gbp = defaultRates.gbp;
    currencyRates.ngn = defaultRates.ngn;
    currencyRates.eur = defaultRates.eur;
    
    document.getElementById('rateGBP').value = defaultRates.gbp;
    document.getElementById('rateNGN').value = defaultRates.ngn;
    document.getElementById('rateEUR').value = defaultRates.eur;
    
    usingCustomRates = false;
    updateRateIndicator();
    calculateTotals();
}

function updateRateIndicator() {
    const indicator = document.getElementById('customRateIndicator');
    const rateDisplay = document.getElementById('defaultRateDisplay');
    const rateStatus = document.getElementById('rateStatus');
    
    if (usingCustomRates) {
        indicator.classList.remove('d-none');
        if (rateStatus) rateStatus.textContent = 'Custom Rate';
    } else {
        indicator.classList.add('d-none');
        if (rateStatus) rateStatus.textContent = 'Default Rate';
    }
    
    // Update the NGN rate display
    if (rateDisplay) {
        rateDisplay.textContent = currencyRates.ngn.toLocaleString('en-US', { 
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2 
        });
    }
}

function updateStageInfo() {
    const stage = document.getElementById('currentStage').value;
    const stageIndex = stageOrder.indexOf(stage);
    
    let progress = 0;
    
    // Special handling for closed deals
    if (stage === 'closed_won') {
        progress = 100; // Won deals are 100% complete
    } else if (stage === 'closed_lost') {
        progress = 0; // Lost deals show 0% (deal didn't progress)
    } else {
        // For active stages, calculate based on position (excluding closed stages)
        const activeStages = stageOrder.filter(s => s !== 'closed_won' && s !== 'closed_lost');
        const activeIndex = activeStages.indexOf(stage);
        progress = ((activeIndex + 1) / activeStages.length) * 100;
    }
    
    // Update progress bar with appropriate color
    const progressBar = document.getElementById('stageProgress');
    progressBar.style.width = progress + '%';
    progressBar.textContent = Math.round(progress) + '%';
    
    // Color code the progress bar
    progressBar.className = 'progress-bar';
    if (stage === 'closed_won') {
        progressBar.classList.add('bg-success');
    } else if (stage === 'closed_lost') {
        progressBar.classList.add('bg-danger');
    } else {
        progressBar.classList.add('bg-primary');
    }
    
    document.getElementById('stageDescription').textContent = stageDescriptions[stage];
    
    if (currentDeal && currentDeal.stageNotes) {
        document.getElementById('stageNotes').value = currentDeal.stageNotes[stage] || '';
    }
}

function moveToNextStage() {
    const currentStage = document.getElementById('currentStage').value;
    const currentIndex = stageOrder.indexOf(currentStage);
    
    // Allow progression up to qualification (index 3), but not beyond
    if (currentIndex < 3) {
        const nextStage = stageOrder[currentIndex + 1];
        document.getElementById('currentStage').value = nextStage;
        updateStageInfo();
        showToast('Moved to next stage: ' + stageDescriptions[nextStage], 'success');
    } else {
        showToast('Use "Mark as Won" or "Mark as Lost" to close the deal', 'info');
    }
}

function markAsWon() {
    document.getElementById('dealStatus').value = 'won';
    document.getElementById('currentStage').value = 'closed_won';
    updateStageInfo();
    showToast('Deal marked as WON!', 'success');
}

function markAsLost() {
    if (confirm('Are you sure you want to mark this deal as LOST?')) {
        document.getElementById('dealStatus').value = 'lost';
        document.getElementById('currentStage').value = 'closed_lost';
        updateStageInfo();
        showToast('Deal marked as LOST', 'warning');
    }
}

async function saveDeal() {
    const title = document.getElementById('dealTitle').value;
    const value = document.getElementById('expectedValue').value;
    
    if (!title || !value) {
        showToast('Please fill in Deal Title and Expected Value', 'warning');
        return;
    }
    
    const lineItems = [];
    document.querySelectorAll('#itemsTable tbody tr').forEach(tr => {
        const productName = tr.querySelector('.product-name-hidden').value;
        const desc = tr.querySelector('.product-details').value;
        const qty = tr.querySelector('.qty-input').value;
        const price = tr.querySelector('.product-price').value;
        const total = tr.querySelector('.total-input').value;
        
        // Get productId from select
        const select = tr.querySelector('.product-select');
        const productId = select.value || null;
        
        if (productName || desc || qty || price) {
            lineItems.push({
                productId: productId,
                productName: productName,
                description: desc,
                quantity: qty,
                price: price,
                total: total
            });
        }
    });
    
    const currentStage = document.getElementById('currentStage').value;
    
    const dealData = {
        title: title,
        value: value,
        stage: currentStage,
        valid_until: document.getElementById('validUntil').value,
        status: document.getElementById('dealStatus').value,
        lineItems: lineItems,
        lead_id: document.getElementById('dealLead') ? document.getElementById('dealLead').value : null // If lead select exists
    };
    
    if (dealId !== null) {
        dealData.id = dealId;
        const result = await DealsAPI.update(dealData);
        if (result.success) showToast('Deal updated successfully!', 'success');
        else showToast('Error updating deal: ' + result.message, 'error');
    } else {
        const result = await DealsAPI.create(dealData);
        if (result.success) {
             showToast('Deal created successfully!', 'success');
             setTimeout(() => window.location.href = 'index.php', 1000);
        }
        else showToast('Error creating deal: ' + result.message, 'error');
    }
}

// Generate Deal ID
function generateDealId(index) {
    const paddedNumber = String(index + 1).padStart(3, '0');
    return `DEAL-${paddedNumber}`;
}

// Print Modal Functions
function openPrintModal() {
    const modal = new bootstrap.Modal(document.getElementById('printModal'));
    modal.show();
    updatePrintPreview();
}

function updatePrintPreview() {
    const docType = document.getElementById('docType').value;
    const preview = document.getElementById('printPreview');
    
    const title = document.getElementById('dealTitle').value || 'Untitled Deal';
    const description = document.getElementById('description').value || 'No description';
    const terms = document.getElementById('terms').value || 'No terms specified';
    const total = document.getElementById('totalDisplay').textContent;
    const validUntil = document.getElementById('validUntil').value || 'Not specified';
    const dueDate = document.getElementById('dueDate').value || 'Not specified';
    
    // Collect line items
    let lineItemsHTML = '';
    document.querySelectorAll('#itemsTable tbody tr').forEach(tr => {
        const productName = tr.querySelector('.product-name-hidden').value || 'N/A';
        const qty = tr.querySelector('.qty-input').value || 0;
        const price = tr.querySelector('.product-price').value || 0;
        const lineTotal = tr.querySelector('.total-input').value || 0;
        
        if (productName && productName !== 'N/A') {
            lineItemsHTML += `
                <tr>
                    <td style="padding: 12px; border: 1px solid #dee2e6;">${productName}</td>
                    <td style="padding: 12px; text-align: center; border: 1px solid #dee2e6;">${qty}</td>
                    <td style="padding: 12px; text-align: right; border: 1px solid #dee2e6;">${currentSymbol}${parseFloat(price).toFixed(2)}</td>
                    <td style="padding: 12px; text-align: right; border: 1px solid #dee2e6;"><strong>${currentSymbol}${parseFloat(lineTotal).toFixed(2)}</strong></td>
                </tr>
            `;
        }
    });
    
    const subtotal = document.getElementById('subtotalDisplay').textContent;
    const vat = document.getElementById('vatDisplay').textContent;
    
    let documentTitle = '';
    let documentIntro = '';
    let documentColor = '';
    
    if (docType === 'quote') {
        documentTitle = 'QUOTATION';
        documentIntro = 'Please find below our quotation for your requirements:';
        documentColor = '#0d6efd';
    } else if (docType === 'proposal') {
        documentTitle = 'BUSINESS PROPOSAL';
        documentIntro = 'We are pleased to submit this proposal for your consideration:';
        documentColor = '#198754';
    } else if (docType === 'invoice') {
        documentTitle = 'INVOICE';
        documentIntro = 'Please find below the invoice for services/products provided:';
        documentColor = '#dc3545';
    }
    
    const currentDate = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    
    preview.innerHTML = `
        <div style="max-width: 800px; margin: 0 auto; font-family: Arial, sans-serif;">
            <div style="text-align: center; margin-bottom: 30px; border-bottom: 3px solid ${documentColor}; padding-bottom: 20px;">
                <h1 style="margin: 0; font-size: 32px; color: ${documentColor};">${documentTitle}</h1>
                <p style="margin: 5px 0; color: #666;">Deal ID: ${currentDealId || 'N/A'}</p>
                <p style="margin: 5px 0; color: #666;">Date: ${currentDate}</p>
            </div>
            
            <div style="margin-bottom: 30px; padding: 20px; background-color: #f8f9fa; border-left: 4px solid ${documentColor};">
                <h3 style="color: #333; margin-top: 0;">${title}</h3>
                <p style="color: #666; line-height: 1.6; margin-bottom: 10px;">${documentIntro}</p>
                <p style="color: #666; line-height: 1.6; white-space: pre-wrap;">${description}</p>
            </div>
            
            ${lineItemsHTML ? `
            <div style="margin-bottom: 30px;">
                <h4 style="color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px;">Items / Services</h4>
                <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                    <thead>
                        <tr style="background-color: ${documentColor}; color: white;">
                            <th style="padding: 12px; text-align: left; border: 1px solid ${documentColor};">Item</th>
                            <th style="padding: 12px; text-align: center; border: 1px solid ${documentColor}; width: 100px;">Qty</th>
                            <th style="padding: 12px; text-align: right; border: 1px solid ${documentColor}; width: 120px;">Unit Price</th>
                            <th style="padding: 12px; text-align: right; border: 1px solid ${documentColor}; width: 120px;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${lineItemsHTML}
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="padding: 12px; text-align: right; border: 1px solid #dee2e6;"><strong>Subtotal:</strong></td>
                            <td style="padding: 12px; text-align: right; border: 1px solid #dee2e6;"><strong>${subtotal}</strong></td>
                        </tr>
                        <tr>
                            <td colspan="3" style="padding: 12px; text-align: right; border: 1px solid #dee2e6;"><strong>VAT (7.5%):</strong></td>
                            <td style="padding: 12px; text-align: right; border: 1px solid #dee2e6;"><strong>${vat}</strong></td>
                        </tr>
                        <tr style="background-color: #f8f9fa;">
                            <td colspan="3" style="padding: 12px; text-align: right; border: 1px solid #dee2e6; font-size: 16px;"><strong>GRAND TOTAL:</strong></td>
                            <td style="padding: 12px; text-align: right; border: 1px solid #dee2e6; font-size: 18px; color: ${documentColor};"><strong>${total}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            ` : '<div style="padding: 20px; background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; margin-bottom: 30px;"><p style="margin: 0; color: #856404;"><strong>Note:</strong> No line items added yet. Add products to this deal to include them in the ${documentTitle.toLowerCase()}.</p></div>'}
            
            <div style="margin-bottom: 20px; padding: 15px; background-color: #e7f3ff; border-radius: 4px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <div>
                        <p style="margin: 5px 0; color: #666;"><strong>Valid Until:</strong> ${validUntil}</p>
                        <p style="margin: 5px 0; color: #666;"><strong>Payment Due:</strong> ${dueDate}</p>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #eee;">
                <h4 style="color: #333;">Terms & Conditions</h4>
                <p style="color: #666; line-height: 1.6; white-space: pre-wrap;">${terms}</p>
            </div>
            
            <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; color: #999; font-size: 12px;">
                <p>This ${documentTitle.toLowerCase()} was generated on ${currentDate}</p>
                <p>Thank you for your business!</p>
            </div>
        </div>
    `;
}

function printDocument() {
	try {
		const printContents = document.getElementById('printPreview').innerHTML;
		// Open a new window and write print contents into it
		const printWindow = window.open('', '_blank', 'width=900,height=700');
		if (!printWindow) {
			// Popup blocked - fallback to alert and stop
			showToast('Unable to open print window (popup blocked). Please allow popups and try again.', 'warning');
			return;
		}

		// Build a minimal printable document, include Bootstrap CSS from current page if available
		let bootstrapStyles = '';
		// Attempt to copy stylesheets from the opener (best-effort)
		try {
			Array.from(document.querySelectorAll('link[rel="stylesheet"], style')).forEach(node => {
				bootstrapStyles += node.outerHTML;
			});
		} catch (e) {
			// ignore style copy failures
		}

		printWindow.document.open();
		printWindow.document.write(`
			<!doctype html>
			<html>
				<head>
					<meta charset="utf-8">
					<title>Print - Deal</title>
					${bootstrapStyles}
					<style>body{margin:20px; font-family: Arial, sans-serif; color:#333;}</style>
				</head>
				<body>
					${printContents}
				</body>
			</html>
		`);
		printWindow.document.close();
		printWindow.focus();

		// Delay slightly to allow rendering in the new window before print
		setTimeout(() => {
			try {
				printWindow.print();
			} catch (e) {
				console.error('Print error:', e);
				showToast('Print failed. See console for details.', 'warning');
			}
			// Do not attempt to restore the original page here; printing in a separate window leaves the current page intact
			try { printWindow.close(); } catch (e) {}
		}, 500);
	} catch (err) {
		console.error('printDocument error:', err);
		showToast('An error occurred while preparing the document for printing.', 'danger');
	}
}

function getToastContainer() {
    let container = document.getElementById('app-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'app-toast-container';
        // container should not block pointer events for the page; individual toasts will be interactive
        container.style.position = 'fixed';
        container.style.bottom = '0';
        container.style.right = '0';
        container.style.padding = '12px';
        container.style.zIndex = '9999';
        container.style.pointerEvents = 'none'; // important: allow clicks through to page
        document.body.appendChild(container);
    }
    return container;
}

// Robust toast builder compatible with Bootstrap 5 and with a safe non-blocking container
function showToast(message, type = 'info') {
    try {
        const typeMap = {
            'success': 'success',
            'info': 'info',
            'warning': 'warning',
            'danger': 'danger',
            'error': 'danger'
        };
        const bsType = typeMap[type] || 'secondary';

        const container = getToastContainer();

        // Limit concurrent toasts to 4 to avoid DOM buildup
        while (container.children.length >= 4) {
            container.removeChild(container.firstChild);
        }

        const toastWrapper = document.createElement('div');
        // make individual toast interactive while container remains pointer-events:none
        toastWrapper.style.pointerEvents = 'auto';
        toastWrapper.style.marginTop = '8px';
        toastWrapper.style.minWidth = '240px';

        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${bsType} border-0`;
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        toast.setAttribute('aria-atomic', 'true');
        toast.setAttribute('data-bs-autohide', 'true');
        toast.setAttribute('data-bs-delay', '3000');

        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body" style="word-break: break-word;">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" aria-label="Close"></button>
            </div>
        `;

        // Hook close button manually to avoid relying solely on data-bs attributes
        const closeBtn = toast.querySelector('.btn-close');
        closeBtn.addEventListener('click', () => {
            try {
                if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
                    try { bsToast.hide(); } catch(e){}
                } else {
                    toastWrapper.remove();
                }
            } catch(e){}
        });

        toastWrapper.appendChild(toast);
        container.appendChild(toastWrapper);

        // If bootstrap is present, use it; otherwise auto-remove after delay
        if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
            const bsToast = new bootstrap.Toast(toast);
            // remove the wrapper when toast is hidden
            toast.addEventListener('hidden.bs.toast', () => {
                try { toastWrapper.remove(); } catch(e){}
            });
            bsToast.show();
        } else {
            setTimeout(() => {
                try { toastWrapper.remove(); } catch(e){}
            }, 3000);
        }
    } catch (err) {
        // Ultimate fallback minimal UI message (should not block clicks)
        try {
            const minimal = document.createElement('div');
            minimal.style.position = 'fixed';
            minimal.style.bottom = '12px';
            minimal.style.right = '12px';
            minimal.style.background = '#333';
            minimal.style.color = 'white';
            minimal.style.padding = '8px 12px';
            minimal.style.borderRadius = '6px';
            minimal.style.zIndex = '9999';
            minimal.style.pointerEvents = 'auto';
            minimal.textContent = message;
            document.body.appendChild(minimal);
            setTimeout(() => { minimal.remove(); }, 3000);
        } catch (e) {
            console.error('showToast fallback failed', e);
        }
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>

