<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';

requirePermission('view_deals');

$dealId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$docType = strtolower(trim($_GET['type'] ?? 'quote'));
if (!in_array($docType, ['quote', 'invoice', 'proposal'])) {
    $docType = 'quote';
}

if ($dealId <= 0) {
    die("Invalid Deal ID specified.");
}

// Fetch complete deal details
$stmt = $pdo->prepare("
    SELECT d.*, 
           c.display_name as customer_name, c.company as customer_company, c.address as customer_address, 
           c.city as customer_city, c.state as customer_state, c.zip as customer_zip, c.country as customer_country,
           c.email as customer_email, c.phone as customer_phone,
           l.display_name as lead_name, l.company as lead_company, l.email as lead_email, l.phone as lead_phone,
           COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), u.name, u.email) as assigned_to_name,
           u.email as assigned_to_email
    FROM deals d
    LEFT JOIN customers c ON d.customer_id = c.id
    LEFT JOIN leads l ON d.lead_id = l.id
    LEFT JOIN users u ON d.assigned_to = u.id
    WHERE d.id = ? AND d.is_archived = 0
");
$stmt->execute([$dealId]);
$deal = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$deal) {
    die("Opportunity / Deal record not found or has been archived.");
}

// Fetch Line Items
$itemStmt = $pdo->prepare("
    SELECT dp.*, p.code as product_code, p.unit as product_unit
    FROM deal_products dp
    LEFT JOIN products p ON dp.product_id = p.id
    WHERE dp.deal_id = ?
    ORDER BY dp.id ASC
");
$itemStmt->execute([$dealId]);
$lineItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

// Exact Company Details
$companySettings = [
    'name' => 'Baye Business Solutions Limited',
    'address' => '35 Awoniyi Elemo Street, Off Lateef Salami Street, Ajao Estate, Lagos',
    'email' => 'info@bayebusinesssolutions.com',
    'phone' => '+234 802 382 3823',
    'website' => 'https://bayebusinesssolutions.com/',
    'logo' => BASE_URL . '/assets/images/bbsllogo.webp'
];

// Currency & Value Calculations
$currCode = strtoupper(trim($deal['currency'] ?? 'USD'));
$currSymbol = (strpos($currCode, 'NGN') !== false || strpos($currCode, '₦') !== false) ? '₦' : '$';
$dealValue = floatval($deal['value'] ?? 0);

// Document Type Configurations
$typeConfigs = [
    'quote' => [
        'title' => 'COMMERCIAL QUOTATION',
        'sub' => 'Official Price Quote & Solution Scope',
        'code_prefix' => 'QT-',
        'color' => '#12b1b0',
        'accent_bg' => '#e6f7f7',
        'date_label' => 'Valid Until'
    ],
    'proposal' => [
        'title' => 'BUSINESS PROPOSAL',
        'sub' => 'Technical & Commercial Solution Proposal',
        'code_prefix' => 'PROP-',
        'color' => '#12b1b0',
        'accent_bg' => '#e6f7f7',
        'date_label' => 'Target Date'
    ],
    'invoice' => [
        'title' => 'COMMERCIAL INVOICE',
        'sub' => 'Proforma Commercial Tax Invoice',
        'code_prefix' => 'INV-',
        'color' => '#0d8584',
        'accent_bg' => '#e0f2f1',
        'date_label' => 'Payment Due'
    ]
];

$docConfig = $typeConfigs[$docType];
$docNumber = !empty($deal['quote_number']) ? $deal['quote_number'] : ($docConfig['code_prefix'] . str_pad($deal['id'], 5, '0', STR_PAD_LEFT));

$clientName = $deal['customer_name'] ?: $deal['lead_name'] ?: 'Valued Customer';
$clientCompany = $deal['customer_company'] ?: $deal['lead_company'] ?: '';
$clientEmail = $deal['customer_email'] ?: $deal['lead_email'] ?: '';
$clientPhone = $deal['customer_phone'] ?: $deal['lead_phone'] ?: '';
$clientAddress = trim(($deal['customer_address'] ?? '') . ' ' . ($deal['customer_city'] ?? '') . ' ' . ($deal['customer_state'] ?? '') . ' ' . ($deal['customer_country'] ?? ''));

$issueDate = date('F j, Y', strtotime($deal['created_at']));
$dueDate = !empty($deal['valid_until']) ? date('F j, Y', strtotime($deal['valid_until'])) : (!empty($deal['due_date']) ? date('F j, Y', strtotime($deal['due_date'])) : date('F j, Y', strtotime('+30 days', strtotime($deal['created_at']))));

// Calculate Subtotal & VAT
$subtotal = 0;
if (!empty($lineItems)) {
    foreach ($lineItems as $item) {
        $qty = floatval($item['quantity'] ?? 1);
        $price = floatval($item['price'] ?? 0);
        $subtotal += floatval($item['total'] ?? ($qty * $price));
    }
} else {
    $subtotal = $dealValue;
}
$vatAmount = $subtotal * 0.075;
$grandTotal = $subtotal + $vatAmount;

$isDualCurrency = (intval($deal['is_dual_currency']) === 1 && floatval($deal['exchange_rate']) > 0);
$secRate = floatval($deal['exchange_rate'] ?? 1);
$secCode = ($currCode === 'USD') ? 'NGN' : 'USD';
$secSymbol = ($secCode === 'NGN') ? '₦' : '$';

// Conversion Direction: Multiply if Primary is USD -> NGN, Divide if Primary is NGN -> USD
$convertSec = function($val) use ($currCode, $secRate) {
    if ($secRate <= 0) return $val;
    return ($currCode === 'USD') ? ($val * $secRate) : ($val / $secRate);
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($docConfig['title'] . ' - ' . $docNumber . ' - ' . $companySettings['name']); ?></title>
    
    <!-- CDN Fonts: Space Age & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.cdnfonts.com/css/space-age" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS & Lucide Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        @import url('https://fonts.cdnfonts.com/css/space-age');

        /* Base Typography: Outfit for everything */
        * {
            box-sizing: border-box;
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
        }

        /* Specific Rule: Company Name in Space Age */
        .company-space-age {
            font-family: 'Space Age', 'Space Grotesk', 'Outfit', sans-serif !important;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .mono-font {
            font-family: 'JetBrains Mono', 'Outfit', monospace !important;
        }

        body {
            background-color: #f8fafc;
            color: #0f172a;
            margin: 0;
            padding: 0;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* Floating Screen Action Bar (Hidden on print) */
        .screen-toolbar {
            position: fixed;
            top: 16px;
            right: 24px;
            z-index: 9999;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid #cbd5e1;
            padding: 8px 16px;
            border-radius: 50px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
        }

        /* Standard A4 Printable Canvas */
        .page-container {
            width: 210mm;
            max-width: 210mm;
            min-height: 297mm;
            margin: 30px auto 60px auto;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.05);
            padding: 35px 35px;
            position: relative;
            box-sizing: border-box;
        }

        .brand-top-bar {
            height: 6px;
            background: linear-gradient(90deg, #12b1b0 0%, #0d8584 100%);
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }

        .header-brand-logo {
            max-height: 65px;
            max-width: 220px;
            object-fit: contain;
        }

        .doc-badge {
            background-color: <?php echo $docConfig['accent_bg']; ?>;
            color: <?php echo $docConfig['color']; ?>;
            font-weight: 800;
            font-size: 0.75rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 6px 16px;
            border-radius: 50px;
            display: inline-block;
            border: 1px solid rgba(18, 177, 176, 0.2);
        }

        /* Explicit Flex & Grid Rules (prevents Bootstrap collapse in print engines) */
        .grid-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .flex-between-start {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .flex-between-end {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .info-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 18px 20px;
        }

        .table-custom {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
            table-layout: fixed;
        }

        .table-custom th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 0.685rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 8px 6px;
            border-bottom: 2px solid #cbd5e1;
            word-wrap: break-word;
        }

        .table-custom td {
            padding: 9px 6px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.775rem;
            color: #1e293b;
            word-wrap: break-word;
        }

        .grand-total-card {
            background: transparent;
            color: #0f172a;
            padding: 10px 0 0 0;
        }

        .signature-line {
            border-top: 1.5px dashed #cbd5e1;
            margin-top: 40px;
            width: 100%;
        }

        @page {
            size: A4 portrait;
            margin: 0;
        }

        @media print {
            html, body {
                width: 210mm;
                height: 297mm;
                background: #ffffff !important;
                color: #0f172a !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .no-print {
                display: none !important;
            }

            .page-container {
                width: 210mm !important;
                max-width: 210mm !important;
                min-height: 297mm !important;
                margin: 0 auto !important;
                padding: 25px 35px !important;
                border: none !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                box-sizing: border-box !important;
            }

            .brand-top-bar {
                border-radius: 0 !important;
            }

            .grid-2col {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                gap: 16px !important;
            }

            .flex-between-start {
                display: flex !important;
                justify-content: space-between !important;
                align-items: flex-start !important;
            }

            .flex-between-end {
                display: flex !important;
                justify-content: space-between !important;
                align-items: flex-end !important;
            }
        }
    </style>
</head>
<body>

    <!-- FLOATING ACTION BAR FOR SCREEN (Hidden on Print) -->
    <div class="screen-toolbar no-print d-flex align-items-center gap-2">
        <a href="<?php echo BASE_URL; ?>/modules/deals/index.php" class="btn btn-sm btn-light border rounded-pill px-3 fw-semibold">
            <i data-lucide="arrow-left" class="lucide-icon me-1" style="width: 14px; height: 14px;"></i> Pipeline
        </a>

        <div class="btn-group btn-group-sm p-0.5 bg-light border rounded-pill" role="group">
            <a href="?id=<?php echo $dealId; ?>&type=quote" class="btn btn-sm rounded-pill px-3 <?php echo ($docType === 'quote') ? 'btn-primary active' : 'btn-light text-secondary'; ?>">Quote</a>
            <a href="?id=<?php echo $dealId; ?>&type=invoice" class="btn btn-sm rounded-pill px-3 <?php echo ($docType === 'invoice') ? 'btn-primary active' : 'btn-light text-secondary'; ?>">Invoice</a>
            <a href="?id=<?php echo $dealId; ?>&type=proposal" class="btn btn-sm rounded-pill px-3 <?php echo ($docType === 'proposal') ? 'btn-primary active' : 'btn-light text-secondary'; ?>">Proposal</a>
        </div>

        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="recordSnapshot()">
            <i data-lucide="bookmark" class="lucide-icon me-1" style="width: 14px; height: 14px;"></i> Save Snapshot
        </button>

        <button type="button" class="btn btn-sm btn-primary rounded-pill px-4 fw-bold shadow-sm" style="background:#12b1b0; border-color:#12b1b0;" onclick="window.print()">
            <i data-lucide="printer" class="lucide-icon me-1" style="width: 15px; height: 15px;"></i> Print / Save PDF
        </button>
    </div>

    <!-- MAIN PRINT DOCUMENT CANVAS -->
    <div class="page-container">
        <!-- Top Gradient Brand Accent -->
        <div class="brand-top-bar"></div>

        <!-- HEADER: COMPANY LOGO, SPACE AGE TITLE & DETAILS -->
        <div class="flex-between-start mb-4 pb-3 border-bottom" style="gap: 15px;">
            <div style="flex: 1; max-width: 62%;">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <?php if (file_exists(__DIR__ . '/../../assets/images/bbsllogo.webp')): ?>
                        <img src="<?php echo $companySettings['logo']; ?>" alt="Logo" class="header-brand-logo">
                    <?php endif; ?>
                    <div>
                        <!-- Company Name in Space Age Font -->
                        <h3 class="company-space-age fw-bold mb-0" style="color: #12b1b0; font-size: 1.05rem; line-height: 1.25;">
                            BAYE BUSINESS SOLUTIONS LIMITED
                        </h3>
                        <span class="text-muted small fw-medium" style="font-size: 0.775rem;">Enterprise Technology &amp; Commercial Solutions</span>
                    </div>
                </div>

                <!-- Company Address & Contacts in Outfit Font -->
                <div class="text-secondary small lh-base" style="font-size: 0.825rem;">
                    <div><i data-lucide="map-pin" class="lucide-icon me-1 text-primary" style="width: 13px; height: 13px;"></i> <?php echo htmlspecialchars($companySettings['address']); ?></div>
                    <div class="mt-0.5">
                        <i data-lucide="phone" class="lucide-icon me-1 text-primary" style="width: 13px; height: 13px;"></i> <?php echo htmlspecialchars($companySettings['phone']); ?> &bull; 
                        <i data-lucide="mail" class="lucide-icon me-1 ms-1 text-primary" style="width: 13px; height: 13px;"></i> <?php echo htmlspecialchars($companySettings['email']); ?>
                    </div>
                    <div class="mt-0.5">
                        <i data-lucide="globe" class="lucide-icon me-1 text-primary" style="width: 13px; height: 13px;"></i> <a href="<?php echo htmlspecialchars($companySettings['website']); ?>" target="_blank" class="text-decoration-none text-secondary fw-semibold"><?php echo htmlspecialchars($companySettings['website']); ?></a>
                    </div>
                </div>
            </div>

            <!-- DOCUMENT META INFORMATION -->
            <div style="text-align: right; min-width: 36%;">
                <span class="doc-badge mb-2"><?php echo htmlspecialchars($docConfig['title']); ?></span>
                <h2 class="fw-bold text-dark mono-font mb-2" style="font-size: 1.6rem; color: #0f172a;"><?php echo htmlspecialchars($docNumber); ?></h2>
                <div class="text-muted small lh-base">
                    <div>Date Issued: <strong class="text-dark"><?php echo $issueDate; ?></strong></div>
                    <div><?php echo $docConfig['date_label']; ?>: <strong class="text-dark"><?php echo $dueDate; ?></strong></div>
                    <div class="mt-1">Billing Currency: <span class="badge bg-light text-dark border mono-font px-2 py-0.5 fw-semibold"><?php echo $currCode; ?></span></div>
                </div>
            </div>
        </div>

        <!-- CLIENT & OPPORTUNITY DETAILS GRID (2-Column Grid) -->
        <div class="grid-2col mb-4">
            <!-- Prepared For / Bill To -->
            <div class="info-card">
                <span class="text-uppercase text-muted fw-bold small d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.05em;">PREPARED FOR / BILLED TO</span>
                <h5 class="fw-bold text-dark mb-1" style="font-size: 1.1rem;"><?php echo htmlspecialchars($clientName); ?></h5>
                <?php if ($clientCompany): ?>
                    <div class="fw-semibold text-primary small mb-2"><?php echo htmlspecialchars($clientCompany); ?></div>
                <?php endif; ?>
                <div class="text-muted small lh-base">
                    <?php if ($clientAddress): ?>
                        <div><i data-lucide="map-pin" class="lucide-icon me-1 text-muted" style="width: 12px; height: 12px;"></i> <?php echo htmlspecialchars($clientAddress); ?></div>
                    <?php endif; ?>
                    <?php if ($clientEmail): ?>
                        <div><i data-lucide="mail" class="lucide-icon me-1 text-muted" style="width: 12px; height: 12px;"></i> <?php echo htmlspecialchars($clientEmail); ?></div>
                    <?php endif; ?>
                    <?php if ($clientPhone): ?>
                        <div><i data-lucide="phone" class="lucide-icon me-1 text-muted" style="width: 12px; height: 12px;"></i> <?php echo htmlspecialchars($clientPhone); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Opportunity & Account Reference -->
            <div class="info-card">
                <span class="text-uppercase text-muted fw-bold small d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.05em;">PROJECT / OPPORTUNITY REFERENCE</span>
                <h6 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($deal['title']); ?></h6>
                <div class="text-muted small lh-base mt-2">
                    <div class="mb-1">Account Manager: <strong class="text-dark"><?php echo htmlspecialchars($deal['assigned_to_name'] ?: 'Baye Sales Representative'); ?></strong></div>
                    <div class="mb-1">Pipeline Stage: <span class="badge bg-white text-dark border text-capitalize"><?php echo str_replace('_', ' ', $deal['stage']); ?></span></div>
                    <div>Payment Terms: <strong class="text-dark">Net 30 Days / Milestone Advance</strong></div>
                </div>
            </div>
        </div>

        <!-- SCOPE OF WORK SUMMARY (IF APPLICABLE) -->
        <?php if (!empty($deal['description'])): ?>
            <div class="mb-4 p-3 bg-light rounded-3 border">
                <span class="text-uppercase text-muted fw-bold small d-block mb-1" style="font-size: 0.7rem; letter-spacing: 0.05em;">SCOPE OF WORK &amp; SPECIFICATIONS</span>
                <p class="text-secondary small mb-0 lh-relaxed" style="white-space: pre-wrap; font-size: 0.85rem;"><?php echo htmlspecialchars($deal['description']); ?></p>
            </div>
        <?php endif; ?>

        <!-- LINE ITEMS BREAKDOWN TABLE -->
        <div class="mb-4" style="overflow-x: hidden;">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th style="width: <?php echo $isDualCurrency ? '4%' : '5%'; ?>;" class="text-center">#</th>
                        <th style="width: <?php echo $isDualCurrency ? '28%' : '47%'; ?>;">Item / Product Description</th>
                        <th style="width: <?php echo $isDualCurrency ? '6%' : '8%'; ?>;" class="text-center">Qty</th>
                        <th style="width: <?php echo $isDualCurrency ? '15%' : '20%'; ?>;" class="text-end">Unit Price (<?php echo $currCode; ?>)</th>
                        <th style="width: <?php echo $isDualCurrency ? '16%' : '20%'; ?>;" class="text-end">Total (<?php echo $currCode; ?>)</th>
                        <?php if ($isDualCurrency): ?>
                            <th style="width: 15%;" class="text-end">Unit Price (<?php echo $secCode; ?>)</th>
                            <th style="width: 16%;" class="text-end">Total (<?php echo $secCode; ?>)</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($lineItems)): ?>
                        <?php $idx = 1; foreach ($lineItems as $item): ?>
                            <?php 
                                $qty = floatval($item['quantity'] ?? 1);
                                $price = floatval($item['price'] ?? 0);
                                $itemTotal = floatval($item['total'] ?? ($qty * $price));
                                $prodTitle = !empty($item['product_name']) ? $item['product_name'] : ($item['product_code'] ? 'Product Code: ' . $item['product_code'] : 'Service Item');
                            ?>
                            <tr>
                                <td class="text-center text-muted fw-semibold"><?php echo $idx++; ?></td>
                                <td>
                                    <div class="fw-bold text-dark mb-0.5" style="font-size: 0.8rem;"><?php echo htmlspecialchars($prodTitle); ?></div>
                                    <?php if (!empty($item['description'])): ?>
                                        <div class="text-muted small" style="font-size: 0.725rem; line-height: 1.25;"><?php echo htmlspecialchars($item['description']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-semibold"><?php echo $qty; ?></td>
                                <td class="text-end mono-font" style="white-space: nowrap;"><?php echo $currSymbol . ' ' . number_format($price, 2); ?></td>
                                <td class="text-end mono-font fw-bold text-dark" style="white-space: nowrap;"><?php echo $currSymbol . ' ' . number_format($itemTotal, 2); ?></td>
                                <?php if ($isDualCurrency): ?>
                                    <td class="text-end mono-font text-muted" style="white-space: nowrap;"><?php echo $secSymbol . ' ' . number_format($convertSec($price), 2); ?></td>
                                    <td class="text-end mono-font fw-semibold text-secondary" style="white-space: nowrap;"><?php echo $secSymbol . ' ' . number_format($convertSec($itemTotal), 2); ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td class="text-center text-muted">1</td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($deal['title']); ?></div>
                                <div class="text-muted small">Standard commercial project delivery as outlined in proposal scope.</div>
                            </td>
                            <td class="text-center">1</td>
                            <td class="text-end mono-font" style="white-space: nowrap;"><?php echo $currSymbol . ' ' . number_format($dealValue, 2); ?></td>
                            <td class="text-end mono-font fw-bold text-dark" style="white-space: nowrap;"><?php echo $currSymbol . ' ' . number_format($dealValue, 2); ?></td>
                            <?php if ($isDualCurrency): ?>
                                <td class="text-end mono-font text-muted" style="white-space: nowrap;"><?php echo $secSymbol . ' ' . number_format($convertSec($dealValue), 2); ?></td>
                                <td class="text-end mono-font fw-semibold text-secondary" style="white-space: nowrap;"><?php echo $secSymbol . ' ' . number_format($convertSec($dealValue), 2); ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- TOTALS & SETTLEMENT DETAILS (2-Column Grid) -->
        <div class="grid-2col mb-4">
            <!-- Left: Bank Settlement Info -->
            <div class="info-card">
                <span class="text-uppercase text-muted fw-bold small d-block mb-2" style="font-size: 0.7rem; letter-spacing: 0.05em;">BANK PAYMENT DETAILS</span>
                <div class="small text-secondary lh-base" style="font-size: 0.825rem;">
                    <div>Account Name: <strong class="text-dark">Baye Business Solutions Limited</strong></div>
                    <div>Banker: <strong class="text-dark">Zenith Bank Plc</strong></div>
                    <div>Account Number (NGN): <strong class="text-dark">1018945201</strong></div>
                    <div>Swift / BIC Code: <strong class="text-dark">ZEIBNGLA</strong></div>
                    <div class="mt-2 text-muted" style="font-size: 0.75rem;">
                        Please reference document number <strong><?php echo htmlspecialchars($docNumber); ?></strong> on your payment remitting advice.
                    </div>
                </div>
            </div>

            <!-- Right: Subtotal, Tax & Grand Total -->
            <div class="info-card">
                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                    <span class="text-muted small fw-medium">Subtotal</span>
                    <div class="text-end">
                        <span class="fw-bold mono-font text-dark d-block"><?php echo $currSymbol . ' ' . number_format($subtotal, 2); ?></span>
                        <?php if ($isDualCurrency): ?>
                            <span class="small mono-font text-muted d-block" style="font-size: 0.775rem;"><?php echo $secSymbol . ' ' . number_format($convertSec($subtotal), 2); ?> (<?php echo $secCode; ?>)</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted small fw-medium">VAT (7.5%)</span>
                    <div class="text-end">
                        <span class="fw-semibold mono-font text-muted d-block"><?php echo $currSymbol . ' ' . number_format($vatAmount, 2); ?></span>
                        <?php if ($isDualCurrency): ?>
                            <span class="small mono-font text-muted d-block" style="font-size: 0.775rem;"><?php echo $secSymbol . ' ' . number_format($convertSec($vatAmount), 2); ?> (<?php echo $secCode; ?>)</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Grand Total (No background color) -->
                <div class="grand-total-card pt-2 mt-2 border-top border-2 d-flex justify-content-between align-items-center" style="border-top-color: #cbd5e1 !important;">
                    <div>
                        <span class="text-uppercase small fw-bold text-dark d-block" style="font-size: 0.75rem; letter-spacing: 0.05em;">TOTAL AMOUNT DUE</span>
                        <span class="text-muted small d-block" style="font-size: 0.725rem;">Inclusive of VAT (7.5%)</span>
                    </div>
                    <div class="text-end">
                        <h3 class="fw-bold mono-font mb-0" style="font-size: 1.45rem; color: #12b1b0; white-space: nowrap;">
                            <?php echo $currSymbol . ' ' . number_format($grandTotal, 2); ?>
                        </h3>
                        <?php if ($isDualCurrency): ?>
                            <div class="fw-bold mono-font text-secondary small" style="font-size: 0.9rem; white-space: nowrap;">
                                <?php echo $secSymbol . ' ' . number_format($convertSec($grandTotal), 2); ?> (<?php echo $secCode; ?> @ <?php echo number_format($secRate, 2); ?>)
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- TERMS & AUTHORIZED SIGNATURE (Flex Row) -->
        <div class="flex-between-end pt-3 border-top mt-2" style="gap: 20px;">
            <div style="flex: 1; max-width: 60%;">
                <span class="text-uppercase text-muted fw-bold small d-block mb-1" style="font-size: 0.7rem; letter-spacing: 0.05em;">COMMERCIAL TERMS &amp; CONDITIONS</span>
                <div class="text-muted small lh-base" style="font-size: 0.775rem;">
                    <?php echo !empty($deal['terms_conditions']) ? htmlspecialchars($deal['terms_conditions']) : '1. Quotation remains valid for 30 calendar days from issue date.<br>2. 50% advance payment required upon purchase order confirmation.<br>3. Products and deliverables remain property of Baye Business Solutions Limited until final payment settlement.'; ?>
                </div>
            </div>
            <div style="text-align: right; width: 230px;">
                <div style="font-weight: 700; font-size: 1.05rem; color: #0f172a; text-align: center;" class="mb-1">
                    <?php echo htmlspecialchars($deal['assigned_to_name'] ?: 'Authorized Signatory'); ?>
                </div>
                <div class="signature-line"></div>
                <span class="text-uppercase text-muted fw-bold d-block mt-1 text-center" style="font-size: 0.675rem; letter-spacing: 0.05em;">AUTHORIZED SIGNATURE</span>
                <span class="text-muted small d-block text-center" style="font-size: 0.7rem;"><?php echo htmlspecialchars($companySettings['name']); ?></span>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="text-center text-muted small pt-4 mt-4 border-top" style="font-size: 0.725rem;">
            Thank you for partnering with <strong>Baye Business Solutions Limited</strong> &bull; <?php echo htmlspecialchars($companySettings['website']); ?> &bull; Document Reference: <?php echo htmlspecialchars($docNumber); ?>
        </div>
    </div>

    <!-- SCRIPT FOR LUCIDE ICONS & SNAPSHOT -->
    <script>
        lucide.createIcons();

        function recordSnapshot() {
            const data = {
                deal_id: <?php echo $dealId; ?>,
                type: '<?php echo ucfirst($docType); ?>',
                document_number: '<?php echo htmlspecialchars($docNumber); ?>',
                content_snapshot: JSON.stringify({
                    docNumber: '<?php echo htmlspecialchars($docNumber); ?>',
                    docType: '<?php echo $docType; ?>',
                    companyName: '<?php echo addslashes($companySettings['name']); ?>',
                    clientName: '<?php echo addslashes($clientName); ?>',
                    grandTotal: '<?php echo $currSymbol . ' ' . number_format($grandTotal, 2); ?>',
                    created_at: new Date().toISOString()
                })
            };

            fetch('<?php echo BASE_URL; ?>/ajax/deals/documents.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    alert('Document Snapshot saved successfully in CRM deal documents history!');
                } else {
                    alert('Failed to save snapshot: ' + (res.message || 'Unknown error'));
                }
            })
            .catch(err => {
                console.error(err);
                alert('Error saving document snapshot.');
            });
        }
    </script>
</body>
</html>
