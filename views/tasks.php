<?php
require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Task Management</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#taskModal" onclick="openTaskModal()">
            <i class="bi bi-plus-circle"></i> Add New Task
        </button>
    </div>

    <!-- Tabs for Lead and Deal Tasks -->
    <ul class="nav nav-tabs mb-4" id="taskTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="lead-tasks-tab" data-bs-toggle="tab" data-bs-target="#lead-tasks" type="button" role="tab">
                <i class="bi bi-person"></i> Lead Tasks
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="deal-tasks-tab" data-bs-toggle="tab" data-bs-target="#deal-tasks" type="button" role="tab">
                <i class="bi bi-briefcase"></i> Deal Tasks
            </button>
        </li>
    </ul>

    <div class="tab-content" id="taskTabsContent">
        <!-- Lead Tasks Section -->
        <div class="tab-pane fade show active" id="lead-tasks" role="tabpanel">
            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Lead Tasks</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="totalLeadTasks">0</div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-list-task fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="pendingLeadTasks">0</div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-clock fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Completed</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="completedLeadTasks">0</div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-check-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <input type="text" class="form-control" id="searchLeadTasks" placeholder="Search tasks...">
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" id="filterLeadTaskType">
                                <option value="">All Types</option>
                                <option value="email">Email</option>
                                <option value="call">Call</option>
                                <option value="meeting">Meeting</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" id="filterLeadTaskStatus">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" id="filterLeadTaskPriority">
                                <option value="">All Priority</option>
                                <option value="high">High</option>
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-secondary w-100" onclick="resetLeadFilters()">
                                <i class="bi bi-x-circle"></i> Reset
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tasks Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Task ID</th>
                                    <th>Task</th>
                                    <th>Type</th>
                                    <th>Related Lead</th>
                                    <th>Date & Time</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="leadTasksTableBody">
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No lead tasks found. Create your first task!</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Deal Tasks Section -->
        <div class="tab-pane fade" id="deal-tasks" role="tabpanel">
            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Deal Tasks</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="totalDealTasks">0</div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-list-task fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="pendingDealTasks">0</div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-clock fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Completed</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="completedDealTasks">0</div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-check-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <input type="text" class="form-control" id="searchDealTasks" placeholder="Search tasks...">
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" id="filterDealTaskType">
                                <option value="">All Types</option>
                                <option value="email">Email</option>
                                <option value="call">Call</option>
                                <option value="meeting">Meeting</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" id="filterDealTaskStatus">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" id="filterDealTaskPriority">
                                <option value="">All Priority</option>
                                <option value="high">High</option>
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-secondary w-100" onclick="resetDealFilters()">
                                <i class="bi bi-x-circle"></i> Reset
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tasks Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Task ID</th>
                                    <th>Task</th>
                                    <th>Type</th>
                                    <th>Related Deal</th>
                                    <th>Date & Time</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="dealTasksTableBody">
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No deal tasks found. Create your first task!</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Task Modal -->
<div class="modal fade" id="taskModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="taskModalTitle">Add New Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="taskForm">
                    <input type="hidden" id="editTaskId">
                    <div class="row g-3">
                        <!-- Section Selection -->
                        <div class="col-md-6">
                            <label class="form-label">Section *</label>
                            <select class="form-select" id="taskSection" onchange="loadRelatedItems()" required>
                                <option value="lead">Lead Task</option>
                                <option value="deal">Deal Task</option>
                            </select>
                        </div>

                        <!-- Task Type -->
                        <div class="col-md-6">
                            <label class="form-label">Task Type *</label>
                            <select class="form-select" id="taskType" onchange="updateTaskFields()" required>
                                <option value="email">Email</option>
                                <option value="call">Call</option>
                                <option value="meeting">Meeting</option>
                            </select>
                        </div>

                        <!-- Related Item -->
                        <div class="col-12">
                            <label class="form-label">Related To *</label>
                            <select class="form-select" id="relatedItem" required>
                                <option value="">-- Select --</option>
                            </select>
                        </div>

                        <!-- Title -->
                        <div class="col-12">
                            <label class="form-label">Task Title *</label>
                            <input type="text" class="form-control" id="taskTitle" required>
                        </div>

                        <!-- Date & Time -->
                        <div class="col-md-6">
                            <label class="form-label">Date *</label>
                            <input type="date" class="form-control" id="taskDate" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Time *</label>
                            <input type="time" class="form-control" id="taskTime" required>
                        </div>

                        <!-- Priority & Status -->
                        <div class="col-md-6">
                            <label class="form-label">Priority</label>
                            <select class="form-select" id="taskPriority">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="taskStatus">
                                <option value="pending" selected>Pending</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>

                        <!-- Type-Specific Fields -->
                        <div id="emailFields" class="col-12">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Email Subject</label>
                                    <input type="text" class="form-control" id="emailSubject">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Recipients (comma-separated)</label>
                                    <input type="text" class="form-control" id="emailRecipients" placeholder="email1@example.com, email2@example.com">
                                </div>
                            </div>
                        </div>

                        <div id="callFields" class="col-12 d-none">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Call Type</label>
                                    <select class="form-select" id="callType">
                                        <option value="outbound">Outbound</option>
                                        <option value="inbound">Inbound</option>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="callPhone">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Call Purpose</label>
                                    <input type="text" class="form-control" id="callPurpose" placeholder="e.g., Follow-up, Demo">
                                </div>
                            </div>
                        </div>

                        <div id="meetingFields" class="col-12 d-none">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Location</label>
                                    <input type="text" class="form-control" id="meetingLocation" placeholder="e.g., Office, Zoom">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Duration (minutes)</label>
                                    <input type="number" class="form-control" id="meetingDuration" value="60">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Attendees</label>
                                    <input type="text" class="form-control" id="meetingAttendees" placeholder="Names separated by commas">
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="col-12">
                            <label class="form-label">Notes/Description</label>
                            <textarea class="form-control" id="taskDescription" rows="3"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveTask()">Save Task</button>
            </div>
        </div>
    </div>
</div>

<script>
// Global cache variables
let cachedTasks = [];
let cachedLeads = [];
let cachedDeals = [];

let currentEditIndex = null;

document.addEventListener('DOMContentLoaded', function() {
    loadData(); // Load all data once
    
    // Set default date to today
    document.getElementById('taskDate').valueAsDate = new Date();
    
    // Add filter event listeners
    document.getElementById('searchLeadTasks').addEventListener('input', () => filterTasks('lead'));
    document.getElementById('filterLeadTaskType').addEventListener('change', () => filterTasks('lead'));
    document.getElementById('filterLeadTaskStatus').addEventListener('change', () => filterTasks('lead'));
    document.getElementById('filterLeadTaskPriority').addEventListener('change', () => filterTasks('lead'));
    
    document.getElementById('searchDealTasks').addEventListener('input', () => filterTasks('deal'));
    document.getElementById('filterDealTaskType').addEventListener('change', () => filterTasks('deal'));
    document.getElementById('filterDealTaskStatus').addEventListener('change', () => filterTasks('deal'));
    document.getElementById('filterDealTaskPriority').addEventListener('change', () => filterTasks('deal'));
});

function loadData() {
    cachedTasks = JSON.parse(localStorage.getItem('crm_tasks') || '[]');
    cachedLeads = JSON.parse(localStorage.getItem('crm_leads') || '[]');
    cachedDeals = JSON.parse(localStorage.getItem('crm_deals') || '[]');
    
    loadTasks();
    loadRelatedItems();
}

function loadTasks() {
    // Use cached tasks
    displayTasks(cachedTasks, 'lead');
    displayTasks(cachedTasks, 'deal');
    updateStatistics();
}

function displayTasks(allTasks, section) {
    const tasks = allTasks.filter(t => t.section === section);
    const tbody = document.getElementById(section === 'lead' ? 'leadTasksTableBody' : 'dealTasksTableBody');
    
    if (tasks.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted">No ${section} tasks found. Create your first task!</td></tr>`;
        return;
    }
    
    tbody.innerHTML = tasks.map((task, index) => {
        const globalIndex = allTasks.findIndex(t => t.id === task.id);
        return `
            <tr>
                <td><span class="badge bg-secondary">${task.id || 'N/A'}</span></td>
                <td><strong>${task.title}</strong></td>
                <td>${getTaskTypeIcon(task.type)} ${task.type.charAt(0).toUpperCase() + task.type.slice(1)}</td>
                <td>${task.relatedName || '-'}</td>
                <td>${formatDateTime(task.date, task.time)}</td>
                <td><span class="badge bg-${getPriorityColor(task.priority)}">${task.priority}</span></td>
                <td><span class="badge bg-${getStatusColor(task.status)}">${task.status}</span></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="viewTask(${globalIndex})" title="View">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-info" onclick="editTask(${globalIndex})" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-success" onclick="toggleTaskStatus(${globalIndex})" title="Toggle Status">
                            <i class="bi bi-check2"></i>
                        </button>
                        <button class="btn btn-outline-warning" onclick="archiveTask(${globalIndex})" title="Archive">
                            <i class="bi bi-archive"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function updateStatistics() {
    const tasks = cachedTasks;
    
    // Lead stats
    const leadTasks = tasks.filter(t => t.section === 'lead');
    document.getElementById('totalLeadTasks').textContent = leadTasks.length;
    document.getElementById('pendingLeadTasks').textContent = leadTasks.filter(t => t.status === 'pending').length;
    document.getElementById('completedLeadTasks').textContent = leadTasks.filter(t => t.status === 'completed').length;
    
    // Deal stats
    const dealTasks = tasks.filter(t => t.section === 'deal');
    document.getElementById('totalDealTasks').textContent = dealTasks.length;
    document.getElementById('pendingDealTasks').textContent = dealTasks.filter(t => t.status === 'pending').length;
    document.getElementById('completedDealTasks').textContent = dealTasks.filter(t => t.status === 'completed').length;
}

function openTaskModal() {
    currentEditIndex = null;
    document.getElementById('taskModalTitle').textContent = 'Add New Task';
    document.getElementById('taskForm').reset();
    document.getElementById('editTaskId').value = '';
    document.getElementById('taskDate').valueAsDate = new Date();
    loadRelatedItems();
    updateTaskFields();
}

function loadRelatedItems() {
    const section = document.getElementById('taskSection').value;
    const select = document.getElementById('relatedItem');
    
    select.innerHTML = '<option value="">-- Select --</option>';
    
    if (section === 'lead') {
        const leads = cachedLeads;
        leads.forEach((lead, index) => {
            const option = document.createElement('option');
            option.value = index;
            option.textContent = `${lead.first_name} ${lead.last_name} - ${lead.company || 'No Company'}`;
            select.appendChild(option);
        });
    } else {
        const deals = cachedDeals;
        deals.forEach((deal, index) => {
            const option = document.createElement('option');
            option.value = index;
            option.textContent = deal.title || deal.dealId;
            select.appendChild(option);
        });
    }
}

function updateTaskFields() {
    const type = document.getElementById('taskType').value;
    
    document.getElementById('emailFields').classList.add('d-none');
    document.getElementById('callFields').classList.add('d-none');
    document.getElementById('meetingFields').classList.add('d-none');
    
    document.getElementById(type + 'Fields').classList.remove('d-none');
}

function generateTaskId(section) {
    const tasks = cachedTasks;
    const prefix = section === 'lead' ? 'LT' : 'DT';
    
    // Filter tasks by section to get section-specific IDs
    const sectionTasks = tasks.filter(t => t.section === section);
    
    if (sectionTasks.length === 0) return `${prefix}-001`;

    const maxId = sectionTasks.reduce((max, task) => {
        if (!task.id) return max;
        // Handle cases where ID might not be in expected format gracefully
        if (!task.id.startsWith(prefix + '-')) return max;
        
        const num = parseInt(task.id.split('-')[1]);
        return !isNaN(num) && num > max ? num : max;
    }, 0);
    
    return `${prefix}-${String(maxId + 1).padStart(3, '0')}`;
}

function saveTask() {
    const section = document.getElementById('taskSection').value;
    const type = document.getElementById('taskType').value;
    const relatedIndex = document.getElementById('relatedItem').value;
    
    if (!relatedIndex) {
        showToast('Please select a related lead or deal', 'warning');
        return;
    }
    
    // Get related name
    let relatedName = '';
    if (section === 'lead') {
        const lead = cachedLeads[relatedIndex];
        if (lead) relatedName = `${lead.first_name} ${lead.last_name}`;
    } else {
        const deal = cachedDeals[relatedIndex];
        if (deal) relatedName = deal.title || deal.dealId;
    }
    
    const task = {
        id: currentEditIndex !== null ? cachedTasks[currentEditIndex].id : generateTaskId(section),
        section: section,
        type: type,
        relatedId: relatedIndex,
        relatedName: relatedName,
        title: document.getElementById('taskTitle').value,
        date: document.getElementById('taskDate').value,
        time: document.getElementById('taskTime').value,
        priority: document.getElementById('taskPriority').value,
        status: document.getElementById('taskStatus').value,
        description: document.getElementById('taskDescription').value,
        createdAt: currentEditIndex !== null ? cachedTasks[currentEditIndex].createdAt : new Date().toISOString(),
        updatedAt: new Date().toISOString()
    };
    
    // Add type-specific fields
    if (type === 'email') {
        task.subject = document.getElementById('emailSubject').value;
        task.recipients = document.getElementById('emailRecipients').value;
    } else if (type === 'call') {
        task.callType = document.getElementById('callType').value;
        task.phone = document.getElementById('callPhone').value;
        task.purpose = document.getElementById('callPurpose').value;
    } else if (type === 'meeting') {
        task.location = document.getElementById('meetingLocation').value;
        task.duration = document.getElementById('meetingDuration').value;
        task.attendees = document.getElementById('meetingAttendees').value;
    }
    
    if (currentEditIndex !== null) {
        cachedTasks[currentEditIndex] = task;
        showToast('Task updated successfully', 'success');
    } else {
        cachedTasks.push(task);
        showToast('Task created successfully', 'success');
    }
    
    localStorage.setItem('tasks', JSON.stringify(cachedTasks));
    
    bootstrap.Modal.getInstance(document.getElementById('taskModal')).hide();
    loadTasks();
}

function viewTask(index) {
    const task = cachedTasks[index];
    
    let typeSpecific = '';
    if (task.type === 'email') {
        typeSpecific = `\nSubject: ${task.subject || 'N/A'}\nRecipients: ${task.recipients || 'N/A'}`;
    } else if (task.type === 'call') {
        typeSpecific = `\nCall Type: ${task.callType ? task.callType.charAt(0).toUpperCase() + task.callType.slice(1) : 'N/A'}\nPhone: ${task.phone || 'N/A'}\nPurpose: ${task.purpose || 'N/A'}`;
    } else if (task.type === 'meeting') {
        typeSpecific = `\nLocation: ${task.location || 'N/A'}\nDuration: ${task.duration || 'N/A'} min\nAttendees: ${task.attendees || 'N/A'}`;
    }
    
    // Create a more detailed message for the toast
   const taskDetails = `ID: ${task.id}\nTitle: ${task.title}\nType: ${task.type}\nRelated To: ${task.relatedName}\nDate: ${task.date}\nTime: ${task.time}\nPriority: ${task.priority}\nStatus: ${task.status}${typeSpecific}\n\nDescription:\n${task.description || 'No description'}`;
   
   // Show the task details in a more user-friendly way
   showToast(taskDetails, 'info');
}

function editTask(index) {
    currentEditIndex = index;
    const task = cachedTasks[index];
    
    document.getElementById('taskModalTitle').textContent = 'Edit Task';
    document.getElementById('editTaskId').value = task.id;
    document.getElementById('taskSection').value = task.section;
    loadRelatedItems();
    document.getElementById('relatedItem').value = task.relatedId;
    document.getElementById('taskType').value = task.type;
    updateTaskFields();
    document.getElementById('taskTitle').value = task.title;
    document.getElementById('taskDate').value = task.date;
    document.getElementById('taskTime').value = task.time;
    document.getElementById('taskPriority').value = task.priority;
    document.getElementById('taskStatus').value = task.status;
    document.getElementById('taskDescription').value = task.description || '';
    
    // Load type-specific fields
    if (task.type === 'email') {
        document.getElementById('emailSubject').value = task.subject || '';
        document.getElementById('emailRecipients').value = task.recipients || '';
    } else if (task.type === 'call') {
        document.getElementById('callType').value = task.callType || 'outbound';
        document.getElementById('callPhone').value = task.phone || '';
        document.getElementById('callPurpose').value = task.purpose || '';
    } else if (task.type === 'meeting') {
        document.getElementById('meetingLocation').value = task.location || '';
        document.getElementById('meetingDuration').value = task.duration || '60';
        document.getElementById('meetingAttendees').value = task.attendees || '';
    }
    
    const modal = new bootstrap.Modal(document.getElementById('taskModal'));
    modal.show();
}

function toggleTaskStatus(index) {
    cachedTasks[index].status = cachedTasks[index].status === 'pending' ? 'completed' : 'pending';
    cachedTasks[index].updatedAt = new Date().toISOString();
    localStorage.setItem('tasks', JSON.stringify(cachedTasks));
    showToast('Task status updated', 'success');
    loadTasks();
}

function archiveTask(index) {
    if (confirm('Are you sure you want to archive this task?')) {
        cachedTasks.splice(index, 1);
        localStorage.setItem('tasks', JSON.stringify(cachedTasks));
        showToast('Task archived successfully', 'success');
        loadTasks();
    }
}

function filterTasks(section) {
    const searchInput = section === 'lead' ? 'searchLeadTasks' : 'searchDealTasks';
    const typeFilter = section === 'lead' ? 'filterLeadTaskType' : 'filterDealTaskType';
    const statusFilter = section === 'lead' ? 'filterLeadTaskStatus' : 'filterDealTaskStatus';
    const priorityFilter = section === 'lead' ? 'filterLeadTaskPriority' : 'filterDealTaskPriority';
    
    const search = document.getElementById(searchInput).value.toLowerCase();
    const type = document.getElementById(typeFilter).value;
    const status = document.getElementById(statusFilter).value;
    const priority = document.getElementById(priorityFilter).value;
    
    const allTasks = cachedTasks;
    
    const filtered = allTasks.filter(task => {
        if (task.section !== section) return false;
        
        const matchesSearch = !search || 
            (task.title && task.title.toLowerCase().includes(search)) ||
            (task.relatedName && task.relatedName.toLowerCase().includes(search));
        const matchesType = !type || task.type === type;
        const matchesStatus = !status || task.status === status;
        const matchesPriority = !priority || task.priority === priority;
        
        return matchesSearch && matchesType && matchesStatus && matchesPriority;
    });
    
    displayTasks(filtered, section);
}

function resetLeadFilters() {
    document.getElementById('searchLeadTasks').value = '';
    document.getElementById('filterLeadTaskType').value = '';
    document.getElementById('filterLeadTaskStatus').value = '';
    document.getElementById('filterLeadTaskPriority').value = '';
    loadTasks();
}

function resetDealFilters() {
    document.getElementById('searchDealTasks').value = '';
    document.getElementById('filterDealTaskType').value = '';
    document.getElementById('filterDealTaskStatus').value = '';
    document.getElementById('filterDealTaskPriority').value = '';
    loadTasks();
}

function getTaskTypeIcon(type) {
    const icons = {
        'email': '📧',
        'call': '📞',
        'meeting': '🤝'
    };
    return icons[type] || '📋';
}

function getPriorityColor(priority) {
    const colors = {
        'high': 'danger',
        'medium': 'warning',
        'low': 'info'
    };
    return colors[priority] || 'secondary';
}

function getStatusColor(status) {
    const colors = {
        'pending': 'warning',
        'completed': 'success',
        'cancelled': 'secondary'
    };
    return colors[status] || 'secondary';
}

function formatDateTime(date, time) {
    if (!date) return '-';
    const d = new Date(date);
    const dateStr = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    return time ? `${dateStr} ${time}` : dateStr;
}


</script>

<style>
.table td {
    vertical-align: middle;
}

.btn-group-sm > .btn {
    padding: 0.25rem 0.5rem;
}

.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}

.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}

.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}
</style>

<?php require_once '../../includes/footer.php'; ?>

