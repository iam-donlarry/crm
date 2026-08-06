<?php
// First check for required parameters
$dealId = $_GET['id'] ?? 0;
if (!$dealId) {
    header('Location: /crm/leads');
    exit;
}

// Then include other files
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
                            <li class="breadcrumb-item"><a href="leads.php">Leads</a></li>
                            <li class="breadcrumb-item active">Deal</li>
                        </ol>
                    </nav>
                    <h4 class="mb-1" id="dealTitle">Loading...</h4>
                    <div class="text-muted small">
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
                        <li><a class="dropdown-item" href="leads.php"><i class="bi bi-arrow-left"></i> Back to Leads</a></li>
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
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Sales Pipeline</h5>
                    <span class="badge bg-light text-dark" id="currentStageBadge">-</span>
                </div>
                <div class="card-body">
                    <div class="pipeline-progress mb-4">
                        <div class="progress" style="height: 12px;">
                            <div class="progress-bar" id="pipelineProgress" style="width: 0%"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-2 small">
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
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#value-prop">
                                <i class="bi bi-lightbulb"></i> Value Prop
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
                        <div class="tab-pane fade show active" id="value-prop">
                            <div class="stage-workspace">
                                <h6>Value Proposition Stage</h6>
                                <p class="text-muted">Define the unique value your solution provides</p>
                                <textarea class="form-control stage-notes" data-stage="value_proposition" rows="4" placeholder="Add notes about value proposition..."></textarea>
                                <button class="btn btn-sm btn-primary mt-2" onclick="saveStageNotes('value_proposition')">Save Notes</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Timeline -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Timeline</h5>
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
                        <label class="small text-muted">Valid Until Date</label>
                        <div class="input-group input-group-sm">
                            <input type="date" class="form-control" id="validUntilInput">
                            <button class="btn btn-outline-primary" onclick="updateValidUntil()">Update</button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="small text-muted">Terms & Conditions</label>
                        <textarea class="form-control" id="termsConditions" rows="4" placeholder="Add terms and conditions..."></textarea>
                        <button class="btn btn-sm btn-primary mt-2" onclick="saveTerms()">Save Terms</button>
                    </div>
                    
                    <div class="mb-3">
                        <label class="small text-muted">Source</label>
                        <div id="dealSource">-</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="small text-muted">Priority</label>
                        <div id="dealPriority">-</div>
                    </div>
                </div>
            </div>
            
            <!-- Quotes -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Quotes</h5>
                    <button class="btn btn-sm btn-success" onclick="generateQuote()">
                        <i class="bi bi-plus"></i> New
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
                        <a href="leads.php" class="btn btn-outline-secondary">
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
                    <label class="form-label">Notes</label>
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
});

function loadDeal() {
    fetch(`<?php echo BASE_URL; ?>/api/deals.php?action=get&id=${dealId}`)
        .then(response => response.json())
        .then(deal => {
            // Update header
            document.getElementById('dealTitle').textContent = deal.title;
            document.getElementById('dealNumber').textContent = deal.deal_number;
            document.getElementById('dealOwner').textContent = deal.owner_name;
            document.getElementById('createdDate').textContent = new Date(deal.created_at).toLocaleDateString();
            
            // Update stats
            document.getElementById('dealValue').textContent = '$' + parseFloat(deal.deal_value).toLocaleString(undefined, {minimumFractionDigits: 2});
            document.getElementById('dealProbability').textContent = deal.probability + '%';
            document.getElementById('expectedClose').textContent = deal.expected_close_date ? 
                new Date(deal.expected_close_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) : '-';
            document.getElementById('validUntil').textContent = deal.valid_until ? 
                new Date(deal.valid_until).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) : '-';
            
            // Update pipeline progress
            const stageIndex = stages.indexOf(deal.current_stage);
            const progress = ((stageIndex + 1) / stages.length) * 100;
            document.getElementById('pipelineProgress').style.width = progress + '%';
            document.getElementById('currentStageBadge').textContent = deal.current_stage.replace('_', ' ');
            
            // Update details
            document.getElementById('validUntilInput').value = deal.valid_until || '';
            document.getElementById('termsConditions').value = deal.terms_conditions || '';
            document.getElementById('dealSource').textContent = deal.source || 'Not specified';
            document.getElementById('dealPriority').innerHTML = `<span class="badge bg-${getPriorityColor(deal.priority)}">${deal.priority}</span>`;
            
            // Load timeline
            loadTimeline();
            
            // Load quotes
            loadQuotes();
            
            // Load stage notes
            loadStageNotes();
        })
        .catch(error => {
            console.error('Error loading deal:', error);
        });
}

function getPriorityColor(priority) {
    switch(priority) {
        case 'critical': return 'danger';
        case 'high': return 'warning';
        case 'medium': return 'primary';
        case 'low': return 'secondary';
        default: return 'light';
    }
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
            new_stage: newStage,
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
            document.getElementById('validUntil').textContent = new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error updating date', 'error');
    });
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

function loadTimeline() {
    fetch(`<?php echo BASE_URL; ?>/api/deals.php?action=get-activities&deal_id=${dealId}`)
        .then(response => response.json())
        .then(activities => {
            const timeline = document.getElementById('dealTimeline');
            timeline.innerHTML = activities.map(activity => `
                <div class="timeline-item mb-3">
                    <div class="d-flex">
                        <div class="activity-icon me-3">
                            <i class="bi bi-${getActivityIcon(activity.activity_type)}"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between">
                                <h6 class="mb-1">${activity.title}</h6>
                                <small class="text-muted">${new Date(activity.created_at).toLocaleDateString()}</small>
                            </div>
                            <p class="mb-1 small">${activity.description}</p>
                            <small class="text-muted">By ${activity.creator_name}</small>
                        </div>
                    </div>
                </div>
            `).join('');
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
                            <small class="text-muted">Valid until: ${new Date(quote.valid_until).toLocaleDateString()}</small>
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
            // Update each stage's notes textarea
            notes.forEach(note => {
                const textarea = document.querySelector(`textarea[data-stage="${note.stage}"]`);
                if (textarea) {
                    textarea.value = note.notes || '';
                }
            });
        })
        .catch(error => {
            console.error('Error loading stage notes:', error);
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
            showToast('Notes saved', 'success');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error saving notes', 'error');
    });
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

function viewQuote(quoteId) {
    showToast('Opening quote...', 'info');
}

function sendQuote(quoteId) {
    showToast('Sending quote...', 'info');
}

function closeDeal(status) {
    const message = status === 'won' ? 
        'Mark this deal as won?' : 
        'Mark this deal as lost?';
    
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
                showToast(`Deal marked as ${status}`, 'success');
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
}

.stage-workspace {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
}

.timeline-item {
    padding-bottom: 15px;
    border-bottom: 1px solid #e9ecef;
    margin-bottom: 15px;
}

.timeline-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e3f2fd;
    color: #1976d2;
    flex-shrink: 0;
}

.quote-item {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
}

.nav-tabs .nav-link {
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
}

.nav-tabs .nav-link.active {
    border-bottom: 3px solid #4361ee;
    font-weight: 600;
}
</style>

<?php require_once '../../includes/footer.php'; ?>
