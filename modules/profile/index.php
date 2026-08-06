<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/login");
    exit();
}

require_once __DIR__ . '/../../includes/header.php';

$userId = $_SESSION['user_id'];

// Fetch user account details with robust name resolution
$stmt = $pdo->prepare("
    SELECT u.*, 
           COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), u.name, u.email) as full_display_name,
           r.name as role_name 
    FROM users u 
    LEFT JOIN roles r ON u.role_id = r.id 
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch Personal CRM Mini-Metrics
$assignedLeadsCount = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE assigned_to = ? AND is_archived = 0");
$assignedLeadsCount->execute([$userId]);
$myLeads = $assignedLeadsCount->fetchColumn();

$assignedDealsCount = $pdo->prepare("SELECT COUNT(*) FROM deals WHERE assigned_to = ? AND is_archived = 0");
$assignedDealsCount->execute([$userId]);
$myDeals = $assignedDealsCount->fetchColumn();

$wonDealsCount = $pdo->prepare("SELECT COUNT(*) FROM deals WHERE assigned_to = ? AND stage = 'closed_won' AND is_archived = 0");
$wonDealsCount->execute([$userId]);
$myWonDeals = $wonDealsCount->fetchColumn();

$wonRevenueSum = $pdo->prepare("SELECT SUM(value) FROM deals WHERE assigned_to = ? AND stage = 'closed_won' AND is_archived = 0");
$wonRevenueSum->execute([$userId]);
$myWonRevenue = $wonRevenueSum->fetchColumn() ?: 0;

// Fetch Recent Deals assigned to this user
$recentMyDealsStmt = $pdo->prepare("SELECT title, value, stage, currency, due_date FROM deals WHERE assigned_to = ? AND is_archived = 0 ORDER BY created_at DESC LIMIT 5");
$recentMyDealsStmt->execute([$userId]);
$myRecentDeals = $recentMyDealsStmt->fetchAll(PDO::FETCH_ASSOC);

// User Permissions
$userPermissions = $_SESSION['permissions'] ?? [];
?>

<div class="container-fluid px-0">
    <!-- Header & Breadcrumb -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <?php renderBreadcrumb([['label' => 'My Profile', 'url' => '']]); ?>
            <h2 class="fw-bold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">Account & Profile Settings</h2>
            <p class="text-muted small mb-0">Manage personal details, security credentials, and view your pipeline contributions.</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Column: User Identity & Mini-Metrics Card -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 text-center p-4 bg-white mb-4">
                <!-- Brand Teal Avatar -->
                <div class="rounded-circle avatar-brand text-white fw-bold d-inline-flex align-items-center justify-content-center mb-3 mx-auto shadow-sm" style="width: 84px; height: 84px; font-size: 2.2rem;">
                    <?php echo strtoupper(substr($user['full_display_name'] ?? 'U', 0, 1)); ?>
                </div>

                <h4 class="fw-bold text-dark mb-1" style="font-family: 'Outfit', sans-serif;">
                    <?php echo htmlspecialchars($user['full_display_name']); ?>
                </h4>
                <p class="text-muted small mb-2"><?php echo htmlspecialchars($user['email']); ?></p>

                <div class="d-flex align-items-center justify-content-center gap-2 mb-3">
                    <span class="badge badge-soft-primary rounded-pill px-3 py-1.5 fw-semibold" style="font-size: 0.8rem;">
                        <i data-lucide="shield-check" class="lucide-icon me-1"></i>
                        <?php echo htmlspecialchars($user['role_name'] ?: $user['role']); ?>
                    </span>
                    <span class="badge badge-soft-success rounded-pill px-2.5 py-1.5 fw-semibold" style="font-size: 0.8rem;">Active</span>
                </div>

                <p class="text-muted small border-top pt-3 mb-3">
                    <i data-lucide="calendar" class="lucide-icon me-1"></i> Member since <?php echo date('M Y', strtotime($user['created_at'])); ?>
                </p>

                <!-- Personal Mini-Metrics Box -->
                <div class="p-3 bg-light rounded-3 text-start border">
                    <span class="text-uppercase text-muted fw-bold small d-block mb-2" style="font-size: 0.675rem; letter-spacing: 0.05em;">My CRM Contributions</span>
                    <div class="row g-2 text-center">
                        <div class="col-4 border-end">
                            <span class="text-muted small d-block" style="font-size: 0.725rem;">Leads</span>
                            <span class="fw-bold text-dark fs-6"><?php echo number_format($myLeads); ?></span>
                        </div>
                        <div class="col-4 border-end">
                            <span class="text-muted small d-block" style="font-size: 0.725rem;">Won Deals</span>
                            <span class="fw-bold text-success fs-6"><?php echo number_format($myWonDeals); ?></span>
                        </div>
                        <div class="col-4">
                            <span class="text-muted small d-block" style="font-size: 0.725rem;">Revenue</span>
                            <span class="fw-bold text-primary" style="font-size: 0.85rem;">$<?php echo number_format($myWonRevenue, 0); ?></span>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Right Column: Tabbed Account Workspace -->
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm rounded-3 bg-white">
                <div class="card-header bg-white border-bottom p-3">
                    <!-- Workspace Nav Tabs -->
                    <ul class="nav nav-pills card-header-pills gap-2" id="profileTabs" role="tablist" style="font-family: 'Outfit', sans-serif;">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link btn-sm rounded-pill active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details-panel" type="button" role="tab">
                                <i data-lucide="user" class="lucide-icon me-1"></i> Personal Details
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link btn-sm rounded-pill" id="security-tab" data-bs-toggle="tab" data-bs-target="#security-panel" type="button" role="tab">
                                <i data-lucide="key-round" class="lucide-icon me-1"></i> Security & Password
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link btn-sm rounded-pill" id="pipeline-tab" data-bs-toggle="tab" data-bs-target="#pipeline-panel" type="button" role="tab">
                                <i data-lucide="briefcase" class="lucide-icon me-1"></i> My Pipeline
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link btn-sm rounded-pill" id="permissions-tab" data-bs-toggle="tab" data-bs-target="#permissions-panel" type="button" role="tab">
                                <i data-lucide="shield" class="lucide-icon me-1"></i> Access Rights
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="card-body p-4">
                    <div class="tab-content" id="profileTabContent">
                        
                        <!-- TAB 1: PERSONAL DETAILS -->
                        <div class="tab-pane fade show active" id="details-panel" role="tabpanel">
                            <h5 class="fw-bold text-dark mb-1" style="font-family: 'Outfit', sans-serif;">Personal Information</h5>
                            <p class="text-muted small mb-4">Update your profile name and contact details.</p>

                            <div id="profileAlert" class="alert alert-danger py-2 px-3 rounded-3 small d-none" role="alert"></div>

                            <form id="profileDetailsForm" onsubmit="saveProfileDetails(event)">
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">First Name</label>
                                        <input type="text" id="profFirstName" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Last Name</label>
                                        <input type="text" id="profLastName" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Email Address (Primary Login)</label>
                                        <input type="email" class="form-control bg-light" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly title="Email cannot be changed directly">
                                        <span class="text-muted" style="font-size: 0.725rem;">Contact system administrator to modify email.</span>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Phone Number</label>
                                        <input type="text" id="profPhone" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="+234 ...">
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" id="saveDetailsBtn" class="btn btn-primary rounded-pill px-4 shadow-sm">
                                        <i data-lucide="check" class="lucide-icon me-1"></i> Save Profile
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- TAB 2: SECURITY & PASSWORD -->
                        <div class="tab-pane fade" id="security-panel" role="tabpanel">
                            <h5 class="fw-bold text-dark mb-1" style="font-family: 'Outfit', sans-serif;">Security & Password</h5>
                            <p class="text-muted small mb-4">Update your account password to maintain system security.</p>

                            <div id="securityAlert" class="alert alert-danger py-2 px-3 rounded-3 small d-none" role="alert"></div>

                            <form id="securityForm" onsubmit="saveSecurityPassword(event)">
                                <div class="row g-3 mb-4" style="max-width: 540px;">
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Current Password</label>
                                        <input type="password" id="currentPassword" name="current_password" class="form-control" placeholder="••••••••" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">New Password</label>
                                        <input type="password" id="newPassword" name="new_password" class="form-control" placeholder="Minimum 6 characters" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Confirm New Password</label>
                                        <input type="password" id="confirmPassword" name="confirm_password" class="form-control" placeholder="Repeat new password" required>
                                    </div>
                                </div>

                                <button type="submit" id="saveSecurityBtn" class="btn btn-primary rounded-pill px-4 shadow-sm">
                                    <i data-lucide="key" class="lucide-icon me-1"></i> Update Password
                                </button>
                            </form>
                        </div>

                        <!-- TAB 3: MY ASSIGNED PIPELINE -->
                        <div class="tab-pane fade" id="pipeline-panel" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h5 class="fw-bold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">My Opportunities</h5>
                                    <p class="text-muted small mb-0">Recent deals directly assigned to you.</p>
                                </div>
                                <a href="<?php echo BASE_URL; ?>/deals" class="btn btn-sm btn-outline-primary rounded-pill px-3">View All Deals</a>
                            </div>

                            <div class="table-responsive border rounded-3">
                                <table class="table table-hover align-middle mb-0" style="font-family: 'Outfit', sans-serif;">
                                    <thead style="background: #f8fafc;">
                                        <tr>
                                            <th class="py-2 px-3 small text-muted">Deal Title</th>
                                            <th class="py-2 px-3 small text-muted">Value</th>
                                            <th class="py-2 px-3 small text-muted">Stage</th>
                                            <th class="py-2 px-3 small text-muted">Target Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($myRecentDeals)): ?>
                                            <tr><td colspan="4" class="text-center py-4 text-muted small">No deals currently assigned to you.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($myRecentDeals as $md): ?>
                                                <tr>
                                                    <td class="py-2.5 px-3 fw-semibold text-dark"><?php echo htmlspecialchars($md['title']); ?></td>
                                                    <td class="py-2.5 px-3 fw-bold text-primary"><?php echo htmlspecialchars($md['currency']) . ' ' . number_format($md['value'], 2); ?></td>
                                                    <td class="py-2.5 px-3">
                                                        <span class="badge rounded-pill <?php echo $md['stage'] === 'closed_won' ? 'badge-soft-success' : 'badge-soft-info'; ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $md['stage'])); ?>
                                                        </span>
                                                    </td>
                                                    <td class="py-2.5 px-3 text-muted small"><?php echo $md['due_date'] ? date('M j, Y', strtotime($md['due_date'])) : '-'; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TAB 4: ACCESS RIGHTS & PERMISSIONS -->
                        <div class="tab-pane fade" id="permissions-panel" role="tabpanel">
                            <h5 class="fw-bold text-dark mb-1" style="font-family: 'Outfit', sans-serif;">Role Access Rights</h5>
                            <p class="text-muted small mb-3">System security permissions granted to your account role (<?php echo htmlspecialchars($user['role_name'] ?: $user['role']); ?>).</p>

                            <div class="d-flex flex-wrap gap-2">
                                <?php if (empty($userPermissions)): ?>
                                    <span class="badge badge-soft-secondary rounded-pill p-2">Standard User Access</span>
                                <?php else: ?>
                                    <?php foreach ($userPermissions as $perm): ?>
                                        <span class="badge badge-soft-primary rounded-pill px-3 py-2 fw-semibold" style="font-size: 0.8rem;">
                                            <i data-lucide="check-circle" class="lucide-icon me-1 text-primary"></i>
                                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $perm))); ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function saveProfileDetails(e) {
    e.preventDefault();
    const alertBox = document.getElementById('profileAlert');
    const submitBtn = document.getElementById('saveDetailsBtn');
    alertBox.classList.add('d-none');

    const firstName = document.getElementById('profFirstName').value.trim();
    const lastName = document.getElementById('profLastName').value.trim();
    const phone = document.getElementById('profPhone').value.trim();

    submitBtn.disabled = true;

    fetch('<?php echo BASE_URL; ?>/ajax/profile/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ first_name: firstName, last_name: lastName, phone: phone })
    })
    .then(res => res.json())
    .then(res => {
        submitBtn.disabled = false;
        if (res.success) {
            showToast(res.message, 'success');
            setTimeout(() => window.location.reload(), 600);
        } else {
            alertBox.textContent = res.message;
            alertBox.classList.remove('d-none');
        }
    })
    .catch(err => {
        submitBtn.disabled = false;
        showToast('Error connecting to server', 'danger');
    });
}

function saveSecurityPassword(e) {
    e.preventDefault();
    const alertBox = document.getElementById('securityAlert');
    const submitBtn = document.getElementById('saveSecurityBtn');
    alertBox.classList.add('d-none');

    const firstName = document.getElementById('profFirstName').value.trim();
    const lastName = document.getElementById('profLastName').value.trim();
    const currentPassword = document.getElementById('currentPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;

    submitBtn.disabled = true;

    fetch('<?php echo BASE_URL; ?>/ajax/profile/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            first_name: firstName,
            last_name: lastName,
            current_password: currentPassword,
            new_password: newPassword,
            confirm_password: confirmPassword
        })
    })
    .then(res => res.json())
    .then(res => {
        submitBtn.disabled = false;
        if (res.success) {
            showToast(res.message, 'success');
            document.getElementById('securityForm').reset();
        } else {
            alertBox.textContent = res.message;
            alertBox.classList.remove('d-none');
        }
    })
    .catch(err => {
        submitBtn.disabled = false;
        showToast('Error connecting to server', 'danger');
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
