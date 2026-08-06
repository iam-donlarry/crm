<?php
// Get deal ID from URL - must check BEFORE including header
$dealId = $_GET['id'] ?? 0;
if (!$dealId) {
    header('Location: ../../index.php');
    exit;
}

require_once '../../includes/header.php';
?>

<div class="deal-container">
    <!-- Deal Header -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-2">
                            <li class="breadcrumb-item"><a href="index.php">Leads</a></li>
                            <li class="breadcrumb-item active">Deal</li>
                        </ol>
                    </nav>
                    <div class="d-flex align-items-center">
                        <h4 class="mb-0 me-3" id="dealTitle">Loading...</h4>
                        <span class="badge bg-light text-dark" id="dealStageBadge">-</span>
                    </div>
                    <div class="text-muted small mt-1">
                        <span class="me-3"><i class="bi bi-hash"></i> <span id="dealNumber">-</span></span>
                        <span class="me-3"><i class="bi bi-person"></i> <span id="dealOwner">-</span></span>
                        <span><i class="bi bi-calendar"></i> Created: <span id="createdDate">-</span></span>
                    </div>
                </div>
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-gear"></i> Actions
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" onclick="generateQuote()"><i class="bi bi-file-earmark-text"></i> Generate Quote</a></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#stageModal"><i class="bi bi-arrow-right-circle"></i> Move Stage</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-success" href="#" onclick="closeDeal('won')"><i class="bi bi-check-circle"></i> Mark as Won</a></li>
                        <li><a class="dropdown-item text-danger" href="#" onclick="closeDeal('lost')"><i class="bi bi-x-circle"></i> Mark as Lost</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="index.php"><i class="bi bi-arrow-left"></i> Back to Leads</a></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Deal Stats -->
        <div class="card-body py-3">
            <div class="row text-center">
                <div class="col">
                    <div class="deal-stat">
                        <div class="stat-value" id="dealValue">$0</div>
                        <div class="stat-label">Deal Value</div>
                    </div>
                </div>
                <div class="col">
                    <div class="deal-stat">
                        <div class="stat-value" id="dealProbability">0%</div>
                        <div class="stat-label">Win Probability</div>
                    </div>
                </div>
                <div class="col">
                    <div class="deal-stat">
                        <div class="stat-value" id="expectedClose">-</div>
                        <div class="stat-label">Expected Close</div>
                    </div>
                </div>
                <div class="col">
                    <div class="deal-stat">
                        <div class="stat-value" id="validUntil">-</div>
                        <div class="stat-label">Quote Valid Until</div>
                    </div>
                </div>
                <div class="col">
                    <div class="deal-stat">
                        <div class="stat-value" id="daysInStage">0</div>
                        <div class="stat-label">Days in Stage</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Left Column: Pipeline & Stages -->
        <div class="col-lg-8">
            <!-- Pipeline Progress -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="pipeline-progress mb-4">
                        <div class="progress" style="height: 12px;">
                            <div class="progress-bar" id="pipelineProgress" style="width: 0%"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-2 small text-muted">
                            <span>Value Prop</span>
                            <span>Proposal</span>
                            <span>Pricing</span>
                            <span>Quote</span>
                            <span>Negotiation</span>
                            <span>Review</span>
                            <span>Qualification</span>
                            <span>Closed</span>
                        </div>
                    </div>
                    
                    <!-- Stage Tabs -->
                    <ul class="nav nav-tabs" id="stageTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#value-proposition">
                                <i class="bi bi-lightbulb"></i> Value Proposition
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#proposal">
                                <i class="bi bi-file-text"></i> Proposal
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pricing">
                                <i class="bi bi-tag"></i> Pricing
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#quote">
                                <i class="bi bi-receipt"></i> Quote
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#negotiation">
                                <i class="bi bi-chat-dots"></i> Negotiation
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#review">
                                <i class="bi bi-check-circle"></i> Review
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#qualification">
                                <i class="bi bi-shield-check"></i> Qualification
                            </button>
                        </li>
                    </ul>
                    
                    <!-- Stage Content -->
                    <div class="tab-content mt-4" id="stageContent">
                        <!-- Each stage tab will be loaded dynamically -->
                    </div>
                </div>
            </div>
            
            <!-- Timeline -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Timeline & Activities</h5>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#activityModal">
                        <i class="bi bi-plus"></i> Add Activity
                    </button>
                </div>
                <div class="card-body">
                    <div class="timeline" id="dealTimeline">
                        <!-- Timeline will be loaded via JS -->
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column: Details & Actions -->
        <div class="col-lg-4">
            <!-- Deal Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Deal Details</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="small text-muted mb-1">Valid Until Date</label>
                        <div class="input-group input-group-sm">
                            <input type="date" class="form-control" id="validUntilInput">
                            <button class="btn btn-outline-primary" type="button" onclick="updateValidUntil()">Update</button>
                        </div>
                        <small class="text-muted" id="daysRemaining"></small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="small text-muted mb-1">Terms & Conditions</label>
                        <textarea class="form-control" id="termsConditions" rows="4" placeholder="Add terms and conditions..."></textarea>
                        <button class="btn btn-sm btn-primary mt-2" onclick="saveTerms()">Save Terms</button>
                    </div>
                    
                    <div class="mb-3">
                        <label class="small text-muted mb-1">Source</label>
                        <div id="dealSource" class="fw-medium">-</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="small text-muted mb-1">Priority</label>
                        <div id="dealPriority">-</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="small text-muted mb-1">Lead Source</label>
                        <div id="leadInfo">-</div>
                    </div>
                </div>
            </div>
            
            <!-- Quotes -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Quotes</h5>
                    <button class="btn btn-sm btn-success" onclick="generateQuote()">
                        <i class="bi bi-plus"></i> New Quote
                    </button>
                </div>
                <div class="card-body">
                    <div id="quotesList">
                        <!-- Quotes loaded via JS -->
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#activityModal">
                            <i class="bi bi-plus-circle"></i> Add Activity
                        </button>
                        <button class="btn btn-outline-success" onclick="generateQuote()">
                            <i class="bi bi-file-earmark-text"></i> Generate Quote
                        </button>
                        <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#stageModal">
                            <i class="bi bi-arrow-right-circle"></i> Move Stage
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Leads
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<div class="modal fade" id="stageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Move to Stage</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">New Stage</label>
                    <select class="form-select" id="newStage">
                        <option value="value_proposition">Value Proposition</option>
                        <option value="proposal">Proposal</option>
                        <option value="pricing">Pricing</option>
                        <option value="quote">Quote</option>
                        <option value="negotiation">Negotiation</option>
                        <option value="review">Review</option>
                        <option value="qualification">Qualification</option>
                        <option value="closed_won">Closed Won</option>
                        <option value="closed_lost">Closed Lost</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Stage Notes</label>
                    <textarea class="form-control" id="stageChangeNotes" rows="3" placeholder="Add notes about this stage change..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="moveToStage()">Move Stage</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="activityModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Activity</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="activityForm">
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select class="form-select" id="activityType">
                            <option value="call">Phone Call</option>
                            <option value="email">Email</option>
                            <option value="meeting">Meeting</option>
                            <option value="note">Note</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" class="form-control" id="activityTitle" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="activityDescription" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="addActivity()">Add Activity</button>
            </div>
        </div>
    </div>
</div>

<script>
const dealId = <?php echo $dealId; ?>;
const stages = [
    'value_proposition', 'proposal', 'pricing', 'quote', 
    'negotiation', 'review', 'qualification', 'closed_won', 'closed_lost'
];

document.addEventListener('DOMContentLoaded', function() {
    loadDeal();
    loadStageTabs();
});

function loadDeal() {
    fetch(`<?php echo BASE_URL; ?>/api/deals.php?action=get&id=${dealId}`)
        .then(response => response.json())
        .then(deal => {
            // Update header
            document.getElementById('dealTitle').textContent = deal.title;
            document.getElementById('dealNumber').textContent = deal.deal_number;
            document.getElementById('dealOwner').textContent = deal.assigned_to_name || 'Unassigned';
            document.getElementById('createdDate').textContent = formatDate(deal.created_at);
            document.getElementById('dealStageBadge').textContent = formatStageName(deal.current_stage);
            document.getElementById('dealStageBadge').className = `badge bg-${getStageColor(deal.current_stage)}`;
            
            // Update stats
            document.getElementById('dealValue').textContent = '$' + parseFloat(deal.expected_value || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
            document.getElementById('dealProbability').textContent = deal.probability + '%';
            document.getElementById('expectedClose').textContent = deal.expected_close_date ? 
                formatDate(deal.expected_close_date) : '-';
            document.getElementById('validUntil').textContent = deal.valid_until ? 
                formatDate(deal.valid_until) : '-';
            document.getElementById('daysInStage').textContent = deal.days_in_stage || 0;
            
            // Update pipeline progress
            const stageIndex = stages.indexOf(deal.current_stage);
            const progress = ((stageIndex + 1) / stages.length) * 100;
            document.getElementById('pipelineProgress').style.width = progress + '%';
            
            // Update details
            document.getElementById('validUntilInput').value = deal.valid_until || '';
            document.getElementById('termsConditions').value = deal.terms || '';
            document.getElementById('dealSource').textContent = deal.source || 'Not specified';
            document.getElementById('dealPriority').innerHTML = `<span class="badge bg-${getPriorityColor(deal.priority)}">${deal.priority}</span>`;
            document.getElementById('leadInfo').innerHTML = deal.lead_name ? 
                `<a href="leads.php?view=lead&id=${deal.lead_id}">${deal.lead_name}</a>` : 'Converted from lead';
            
            // Update days remaining
            updateDaysRemaining(deal.valid_until);
            
            // Load timeline
            loadTimeline();
            
            // Load quotes
            loadQuotes();
            
            // Load stage notes
            loadStageNotes();
            
            // Set active tab based on current stage
            setActiveStageTab(deal.current_stage);
        })
        .catch(error => {
            console.error('Error loading deal:', error);
            showToast('Error loading deal details', 'error');
        });
}

function loadStageTabs() {
    const stageConfig = {
        'value_proposition': {
            title: 'Value Proposition',
            icon: 'bi-lightbulb',
            checklist: [
                'Identify client needs and pain points',
                'Research competitive landscape',
                'Define unique value proposition',
                'Create value statement'
            ],
            placeholder: 'Document client needs, competitive analysis, and your unique value proposition...'
        },
        'proposal': {
            title: 'Proposal',
            icon: 'bi-file-text',
            checklist: [
                'Create proposal document',
                'Outline solution architecture',
                'Define deliverables and timeline',
                'Set success metrics'
            ],
            placeholder: 'Add proposal details, solution outline, and delivery timeline...'
        },
        'pricing': {
            title: 'Pricing',
            icon: 'bi-tag',
            checklist: [
                'Calculate costs and margins',
                'Determine pricing strategy',
                'Prepare pricing breakdown',
                'Define payment terms'
            ],
            placeholder: 'Document pricing calculations, discounts, and payment terms...'
        },
        'quote': {
            title: 'Quote',
            icon: 'bi-receipt',
            checklist: [
                'Generate quote document',
                'Set valid until date',
                'Add terms and conditions',
                'Review and approve quote'
            ],
            placeholder: 'Add quote details, validity period, and special terms...'
        },
        'negotiation': {
            title: 'Negotiation',
            icon: 'bi-chat-dots',
            checklist: [
                'Review client feedback',
                'Prepare counter-offer',
                'Schedule negotiation meetings',
                'Document concessions'
            ],
            placeholder: 'Record negotiation points, counter-offers, and agreements...'
        },
        'review': {
            title: 'Review',
            icon: 'bi-check-circle',
            checklist: [
                'Legal review completion',
                'Technical review completion',
                'Compliance checklist',
                'Final approval'
            ],
            placeholder: 'Document review outcomes, approvals, and pending items...'
        },
        'qualification': {
            title: 'Qualification',
            icon: 'bi-shield-check',
            checklist: [
                'Credit check completion',
                'Contract signing',
                'Payment setup',
                'Onboarding preparation'
            ],
            placeholder: 'Record qualification steps, contract details, and handoff information...'
        }
    };
    
    const content = document.getElementById('stageContent');
    content.innerHTML = '';
    
    Object.entries(stageConfig).forEach(([stageId, config]) => {
        const tabPane = document.createElement('div');
        tabPane.className = `tab-pane fade ${stageId === 'value_proposition' ? 'show active' : ''}`;
        tabPane.id = stageId.replace('_', '-');
        tabPane.innerHTML = `
            <div class="stage-workspace">
                <h5><i class="${config.icon}"></i> ${config.title}</h5>
                <p class="text-muted">Stage-specific information and actions</p>
                
                <div class="mb-4">
                    <h6>Stage Checklist</h6>
                    <div class="stage-checklist">
                        ${config.checklist.map((item, index) => `
                            <div class="checklist-item" data-item="${stageId}-${index}">
                                <input type="checkbox" class="form-check-input" id="check-${stageId}-${index}">
                                <label class="form-check-label" for="check-${stageId}-${index}">${item}</label>
                            </div>
                        `).join('')}
                    </div>
                </div>
                
                <div class="mb-4">
                    <h6>Stage Notes</h6>
                    <textarea class="form-control stage-notes" data-stage="${stageId}" rows="4" placeholder="${config.placeholder}"></textarea>
                    <button class="btn btn-sm btn-primary mt-2" onclick="saveStageNotes('${stageId}')">
                        <i class="bi bi-save"></i> Save Notes
                    </button>
                </div>
                
                <div class="stage-actions">
                    <button class="btn btn-outline-primary btn-sm" onclick="viewStageHistory('${stageId}')">
                        <i class="bi bi-clock-history"></i> View History
                    </button>
                    ${stageId !== 'qualification' ? `
                        <button class="btn btn-success btn-sm" onclick="moveToSpecificStage('${getNextStage(stageId)}')">
                            <i class="bi bi-arrow-right-circle"></i> Move to Next Stage
                        </button>
                    ` : ''}
                </div>
            </div>
        `;
        content.appendChild(tabPane);
    });
}

function setActiveStageTab(stage) {
    const tabId = stage.replace('_', '-');
    const tabButton = document.querySelector(`[data-bs-target="#${tabId}"]`);
    if (tabButton) {
        // Remove active from all tabs
        document.querySelectorAll('#stageTabs .nav-link').forEach(tab => {
            tab.classList.remove('active');
        });
        // Add active to current tab
        tabButton.classList.add('active');
        
        // Show the corresponding tab content
        document.querySelectorAll('.tab-pane').forEach(pane => {
            pane.classList.remove('show', 'active');
        });
        document.getElementById(tabId).classList.add('show', 'active');
    }
}

function getNextStage(currentStage) {
    const currentIndex = stages.indexOf(currentStage);
    return currentIndex < stages.length - 3 ? stages[currentIndex + 1] : currentStage; // Don't go beyond qualification
}

function moveToSpecificStage(stage) {
    document.getElementById('newStage').value = stage;
    new bootstrap.Modal(document.getElementById('stageModal')).show();
}

function generateQuote() {
    fetch(`<?php echo BASE_URL; ?>/api/deals.php?action=generate-quote`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            deal_id: dealId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Quote generated successfully', 'success');
            loadQuotes();
        } else {
            showToast(data.message || 'Error generating quote', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error generating quote', 'error');
    });
}

function moveToStage() {
    const newStage = document.getElementById('newStage').value;
    const notes = document.getElementById('stageChangeNotes').value;
    
    fetch(`<?php echo BASE_URL; ?>/api/deals.php?action=update-stage`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            deal_id: dealId,
            stage: newStage,
            notes: notes
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Stage updated successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('stageModal')).hide();
            loadDeal(); // Reload deal data
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error updating stage', 'error');
    });
}

function updateValidUntil() {
    const date = document.getElementById('validUntilInput').value;
    
    fetch(`<?php echo BASE_URL; ?>/api/deals.php?action=update-valid-until`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            deal_id: dealId,
            valid_until: date
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Valid until date updated', 'success');
            document.getElementById('validUntil').textContent = formatDate(date);
            updateDaysRemaining(date);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error updating date', 'error');
    });
}

function updateDaysRemaining(dateString) {
    if (!dateString) return;
    
    const date = new Date(dateString);
    const today = new Date();
    const diffTime = date - today;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    const daysElement = document.getElementById('daysRemaining');
    if (diffDays < 0) {
        daysElement.textContent = 'Expired ' + Math.abs(diffDays) + ' days ago';
        daysElement.className = 'text-danger';
    } else if (diffDays === 0) {
        daysElement.textContent = 'Expires today';
        daysElement.className = 'text-danger';
    } else if (diffDays <= 7) {
        daysElement.textContent = diffDays + ' days remaining';
        daysElement.className = 'text-warning';
    } else {
        daysElement.textContent = diffDays + ' days remaining';
        daysElement.className = 'text-success';
    }
}

function saveTerms() {
    const terms = document.getElementById('termsConditions').value;
    
    fetch(`<?php echo BASE_URL; ?>/api/deals.php?action=save-terms`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            deal_id: dealId,
            terms: terms
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Terms saved successfully', 'success');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error saving terms', 'error');
    });
}

function addActivity() {
    const type = document.getElementById('activityType').value;
    const title = document.getElementById('activityTitle').value;
    const description = document.getElementById('activityDescription').value;
    
    if (!title) {
        showToast('Please enter a title', 'warning');
        return;
    }
    
    fetch(`<?php echo BASE_URL; ?>/api/deals.php?action=add-activity`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            deal_id: dealId,
            activity_type: type,
            title: title,
            description: description
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Activity added successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('activityModal')).hide();
            loadTimeline();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error adding activity', 'error');
    });
}

function saveStageNotes(stage) {
    const notes = document.querySelector(`textarea[data-stage="${stage}"]`).value;
    
    fetch(`<?php echo BASE_URL; ?>/api/deals.php?action=save-stage-notes`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            deal_id: dealId,
            stage: stage,
            notes: notes
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Stage notes saved', 'success');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error saving notes', 'error');
    });
}

function loadTimeline() {
    fetch(`<?php echo BASE_URL; ?>/api/deals.php?action=get-activities&deal_id=${dealId}`)
        .then(response => response.json())
        .then(activities => {
            const timeline = document.getElementById('dealTimeline');
            timeline.innerHTML = activities.length > 0 ? activities.map(activity => `
                <div class="timeline-item mb-3">
                    <div class="d-flex">
                        <div class="activity-icon me-3 ${activity.activity_type}">
                            <i class="bi bi-${getActivityIcon(activity.activity_type)}"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between">
                                <h6 class="mb-1">${activity.title}</h6>
                                <small class="text-muted">${formatDate(activity.created_at)}</small>
                            </div>
                            <p class="mb-1 small">${activity.description}</p>
                            <small class="text-muted">By ${activity.creator_name}</small>
                        </div>
                    </div>
                </div>
            `).join('') : '<p class="text-muted text-center py-4">No activities yet</p>';
        })
        .catch(error => {
            console.error('Error loading timeline:', error);
        });
}

function loadQuotes() {
    fetch(`<?php echo BASE_URL; ?>/api/deals.php?action=get-quotes&deal_id=${dealId}`)
        .then(response => response.json())
        .then(quotes => {
            const quotesList = document.getElementById('quotesList');
            quotesList.innerHTML = quotes.length > 0 ? quotes.map(quote => `
                <div class="quote-item mb-3 p-3 border rounded">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h6 class="mb-1">${quote.quote_number}</h6>
                            <small class="text-muted">Valid until: ${formatDate(quote.valid_until)}</small>
                        </div>
                        <span class="badge bg-${getQuoteStatusColor(quote.status)}">${quote.status}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <strong class="text-primary">$${parseFloat(quote.total).toLocaleString(undefined, {minimumFractionDigits: 2})}</strong>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="viewQuote(${quote.id})">View</button>
                            <button class="btn btn-outline-success" onclick="sendQuote(${quote.id})">Send</button>
                        </div>
                    </div>
                </div>
            `).join('') : '<p class="text-muted text-center">No quotes yet</p>';
        })
        .catch(error => {
            console.error('Error loading quotes:', error);
        });
}

function loadStageNotes() {
    fetch(`<?php echo BASE_URL; ?>/api/deals.php?action=get-stage-notes&deal_id=${dealId}`)
        .then(response => response.json())
        .then(notes => {
            notes.forEach(note => {
                const textarea = document.querySelector(`textarea[data-stage="${note.stage}"]`);
                if (textarea) {
                    textarea.value = note.notes || '';
                }
                
                // Load checklist items
                if (note.checklist) {
                    const checklist = JSON.parse(note.checklist);
                    Object.entries(checklist).forEach(([itemId, isChecked]) => {
                        const checkbox = document.querySelector(`#check-${itemId}`);
                        if (checkbox) {
                            checkbox.checked = isChecked;
                        }
                    });
                }
            });
        })
        .catch(error => {
            console.error('Error loading stage notes:', error);
        });
}

function viewStageHistory(stage) {
    // In a real implementation, this would show stage history modal
    showToast(`Viewing history for ${formatStageName(stage)} stage`, 'info');
}

function viewQuote(quoteId) {
    window.open(`quote-view.php?id=${quoteId}`, '_blank');
}

function sendQuote(quoteId) {
    if (confirm('Send this quote to the client?')) {
        showToast('Sending quote...', 'info');
        // API call to send quote would go here
    }
}

function closeDeal(status) {
    const message = status === 'won' ? 
        'Mark this deal as won? This will move it to Closed Won stage.' : 
        'Mark this deal as lost? This will move it to Closed Lost stage.';
    
    if (confirm(message)) {
        fetch(`<?php echo BASE_URL; ?>/api/deals.php?action=close-deal`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                deal_id: dealId,
                status: status
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(`Deal marked as ${status === 'won' ? 'Won' : 'Lost'}`, 'success');
                setTimeout(() => {
                    window.location.href = 'leads.php';
                }, 1000);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error closing deal', 'error');
        });
    }
}

// Utility functions
function formatStageName(stage) {
    return stage.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
}

function getStageColor(stage) {
    const colors = {
        'value_proposition': 'primary',
        'proposal': 'info',
        'pricing': 'warning',
        'quote': 'success',
        'negotiation': 'secondary',
        'review': 'purple',
        'qualification': 'orange',
        'closed_won': 'success',
        'closed_lost': 'danger'
    };
    return colors[stage] || 'secondary';
}

function getPriorityColor(priority) {
    const colors = {
        'critical': 'danger',
        'high': 'warning',
        'medium': 'primary',
        'low': 'secondary'
    };
    return colors[priority] || 'secondary';
}

function getActivityIcon(type) {
    const icons = {
        'call': 'telephone',
        'email': 'envelope',
        'meeting': 'calendar-event',
        'note': 'journal-text'
    };
    return icons[type] || 'circle';
}

function getQuoteStatusColor(status) {
    const colors = {
        'draft': 'secondary',
        'sent': 'primary',
        'viewed': 'info',
        'accepted': 'success',
        'rejected': 'danger',
        'expired': 'warning'
    };
    return colors[status] || 'secondary';
}

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}


</script>

<style>
.deal-container {
    max-width: 1400px;
    margin: 0 auto;
}

.deal-stat {
    padding: 10px;
}

.deal-stat .stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.deal-stat .stat-label {
    font-size: 0.8rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.pipeline-progress .progress-bar {
    background: linear-gradient(90deg, #4361ee, #3a0ca3, #7209b7, #4cc9f0, #f72585, #ff9e00, #06d6a0);
    border-radius: 6px;
}

.stage-workspace {
    background: #f8f9fa;
    padding: 25px;
    border-radius: 10px;
    border: 1px solid #e9ecef;
}

.stage-checklist {
    background: white;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
}

.checklist-item {
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    align-items: center;
}

.checklist-item:last-child {
    border-bottom: none;
}

.checklist-item .form-check-input {
    margin-right: 10px;
}

.stage-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e9ecef;
}

.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e9ecef;
}

.timeline-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -30px;
    top: 0;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #4361ee;
    border: 2px solid white;
    box-shadow: 0 0 0 3px #4361ee;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.activity-icon.call { background: #e3f2fd; color: #1976d2; }
.activity-icon.email { background: #f3e5f5; color: #7b1fa2; }
.activity-icon.meeting { background: #e8f5e9; color: #388e3c; }
.activity-icon.note { background: #fff3e0; color: #f57c00; }

.quote-item {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    transition: all 0.2s;
}

.quote-item:hover {
    border-color: #4361ee;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.nav-tabs .nav-link {
    padding: 0.75rem 1rem;
    font-size: 0.9rem;
    border: none;
    border-bottom: 3px solid transparent;
}

.nav-tabs .nav-link.active {
    border-bottom: 3px solid #4361ee;
    font-weight: 600;
    color: #4361ee;
    background: transparent;
}

.tab-content {
    background: white;
    border: 1px solid #dee2e6;
    border-top: none;
    border-radius: 0 0 8px 8px;
    padding: 0;
}

/* Color classes */
.bg-primary { background-color: #4361ee !important; }
.bg-info { background-color: #3a0ca3 !important; }
.bg-warning { background-color: #7209b7 !important; }
.bg-success { background-color: #4cc9f0 !important; }
.bg-secondary { background-color: #f72585 !important; }
.bg-purple { background-color: #ff9e00 !important; }
.bg-orange { background-color: #06d6a0 !important; }

@media (max-width: 992px) {
    .deal-stat .stat-value {
        font-size: 1.2rem;
    }
    
    .nav-tabs {
        overflow-x: auto;
        flex-wrap: nowrap;
    }
    
    .nav-tabs .nav-item {
        white-space: nowrap;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>
