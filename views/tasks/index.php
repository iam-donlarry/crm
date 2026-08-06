<?php
// API Handling Logic
if (isset($_GET['api']) && $_GET['api'] === 'true') {
    require_once '../../includes/config.php';
    require_once '../../includes/utils.php';
    require_once '../../includes/auth.php';

    header('Content-Type: application/json');
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        sendResponse(false, 'Unauthorized');
    }

    $method = $_SERVER['REQUEST_METHOD'];

    if (isset($_GET['action'])) {
        if ($_GET['action'] === 'export') {
            if (!hasPermission('export_tasks')) {
                sendResponse(false, 'Forbidden: You do not have permission to export tasks.');
                exit;
            }
            handleExport($pdo);
            exit;
        }
        if ($_GET['action'] === 'template') {
            if (!hasPermission('download_tasks_template')) {
                sendResponse(false, 'Forbidden: You do not have permission to download the tasks template.');
                exit;
            }
            handleTemplate($pdo);
            exit;
        }
        if ($_GET['action'] === 'import' && $method === 'POST') {
            if (!hasPermission('import_tasks')) {
                sendResponse(false, 'Forbidden: You do not have permission to import tasks.');
                exit;
            }
            handleImport($pdo);
            exit;
        }
    }

    switch ($method) {
            case 'GET':
                if (!hasPermission('view_activities')) sendResponse(false, 'Access Denied');
                handleGet($pdo);
                break;
            case 'POST':
                if (!hasPermission('add_activities')) sendResponse(false, 'Access Denied');
                handlePost($pdo);
                break;
            case 'PUT':
                if (!hasPermission('edit_activities')) sendResponse(false, 'Access Denied');
                handlePut($pdo);
                break;
            case 'DELETE':
                if (!hasPermission('delete_activities')) {
                    sendResponse(false, 'Forbidden: You do not have permission to delete tasks.');
                }
                handleDelete($pdo);
                break;
        default:
            http_response_code(405);
            sendResponse(false, 'Method Not Allowed');
    }
    exit;
}

function handleExport($pdo) {
    if (!hasPermission('export_tasks')) {
        sendResponse(false, 'Forbidden: You do not have permission to export tasks.');
        exit;
    }
    ob_clean();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="tasks_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Title', 'Type', 'Status', 'Priority', 'Due Date', 'Related Type', 'Related Name', 'Created At']);
    
    $ids = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];
    
    $where = ["1=1"];
    $params = [];
    
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $where[] = "t.id IN ($placeholders)";
        $params = array_map('intval', $ids);
    }
    
    $sql = "
        SELECT 
            t.id, t.title, t.type, t.status, t.priority, t.due_date, 
            t.related_to_type,
            CASE 
                WHEN t.related_to_type = 'lead' THEN CONCAT(l.first_name, ' ', l.last_name)
                WHEN t.related_to_type = 'deal' THEN d.title
                ELSE ''
            END as related_name,
            t.created_at
        FROM tasks t
        LEFT JOIN leads l ON t.related_to_type = 'lead' AND t.related_to_id = l.id
        LEFT JOIN deals d ON t.related_to_type = 'deal' AND t.related_to_id = d.id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY t.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

function handleTemplate($pdo) {
    if (!hasPermission('download_tasks_template')) {
        sendResponse(false, 'Forbidden: You do not have permission to download the tasks template.');
        exit;
    }
    ob_clean();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="tasks_template.csv"');

    $output = fopen('php://output', 'w');
    // Headers: ID, Title, Type, Status, Priority, Due Date, Related Type, Related Name, Created At
    fputcsv($output, ['ID', 'Title', 'Type', 'Status', 'Priority', 'Due Date', 'Related Type', 'Related Name', 'Created At']);
    fclose($output);
    exit;
}

function handleImport($pdo) {
    if (!hasPermission('import_tasks')) {
        sendResponse(false, 'Forbidden: You do not have permission to import tasks.');
        exit;
    }
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        sendResponse(false, 'File upload failed');
        return;
    }

    $file = fopen($_FILES['file']['tmp_name'], 'r');
    if (!$file) {
        sendResponse(false, 'Cannot open file');
        return;
    }

    fgetcsv($file); // Skip header
    $success = 0;
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO tasks (title, description, type, status, priority, due_date, related_to_type, related_to_id, assigned_to) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        while (($row = fgetcsv($file)) !== false) {
             // Expect: ID, Title, Type, Status, Priority, Due Date ...
             if(count($row) < 2) continue;
             
             // New Mapping: 0=ID, 1=Title, 2=Type, 3=Status, 4=Priority, 5=Due Date, 6=Related Type, 7=Related Name, 8=Created At
             $title = $row[1] ?? 'New Task';
             $type = $row[2] ?? 'todo';
             $status = $row[3] ?? 'pending';
             $priority = $row[4] ?? 'medium';
             $dueDate = $row[5] ?? null;
             $relatedType = $row[6] ?? null;
             $relatedName = $row[7] ?? null;
             
             $relatedId = null;
             
             // Try to resolve related ID
             if ($relatedType === 'lead' && $relatedName) {
                 // Try exact name match
                 $stmtLead = $pdo->prepare("SELECT id FROM leads WHERE CONCAT(first_name, ' ', last_name) = ? OR company = ? LIMIT 1");
                 $stmtLead->execute([$relatedName, $relatedName]);
                 $lead = $stmtLead->fetch();
                 if ($lead) $relatedId = $lead['id'];
             } elseif ($relatedType === 'deal' && $relatedName) {
                 // Try title match
                 $stmtDeal = $pdo->prepare("SELECT id FROM deals WHERE title = ? LIMIT 1");
                 $stmtDeal->execute([$relatedName]);
                 $deal = $stmtDeal->fetch();
                 if ($deal) $relatedId = $deal['id'];
             }

             $stmt->execute([
                 $title, 
                 'Imported Task', // Description
                 $type, $status, $priority, $dueDate, 
                 $relatedType, $relatedId, 
                 $_SESSION['user_id']
             ]);
             $success++;
        }
        $pdo->commit();
        sendResponse(true, "Imported $success tasks");
    } catch(Exception $e) {
        $pdo->rollBack();
        sendResponse(false, 'Import error: ' . $e->getMessage());
    }
}

function handleGet($pdo) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    try {
        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmt->execute([$id]);
            $task = $stmt->fetch();
            if ($task) sendResponse(true, '', $task);
            else sendResponse(false, 'Task not found');
        } else {
            $archived = isset($_GET['archived']) && $_GET['archived'] === 'true' ? 1 : 0;
            
            // Visibility Logic
            $where = ["t.is_archived = ?"];
            $params = [$archived];

            $isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
            if (!$isAdmin) {
                $where[] = "t.assigned_to = ?";
                $params[] = $_SESSION['user_id'];
            }

            $sql = "SELECT t.*, CONCAT(u.first_name, ' ', u.last_name) as assigned_to_name 
                    FROM tasks t 
                    LEFT JOIN users u ON t.assigned_to = u.id 
                    WHERE " . implode(" AND ", $where) . "
                    ORDER BY t.due_date ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $tasks = $stmt->fetchAll();
            sendResponse(true, '', $tasks);
        }
    } catch (PDOException $e) {
        sendResponse(false, 'Database Error: ' . $e->getMessage());
    }
}

function handlePost($pdo) {
    $data = getJsonInput();
    if (empty($data['title']) || empty($data['status'])) {
        sendResponse(false, 'Title and Status are required');
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO tasks (title, description, type, status, priority, due_date, related_to_type, related_to_id, assigned_to) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $description = $data['description'] ?? '';
        if ($data['type'] == 'email') {
            $description .= "\n\rSubject: " . ($data['subject']??'') . "\nRecipients: " . ($data['recipients']??'');
        } elseif ($data['type'] == 'call') {
            $description .= "\n\rCall Type: " . ($data['callType']??'') . "\nPhone: " . ($data['phone']??'') . "\nPurpose: " . ($data['purpose']??'');
        } elseif ($data['type'] == 'meeting') {
            $description .= "\n\rLocation: " . ($data['location']??'') . "\nDuration: " . ($data['duration']??'') . "\nAttendees: " . ($data['attendees']??'');
        }

        $stmt->execute([
            $data['title'],
            $description,
            $data['type'] ?? 'todo',
            $data['status'],
            $data['priority'] ?? 'medium',
            $data['due_date'] ?? null,
            $data['related_to_type'] ?? null,
            $data['related_to_id'] ?? null,
            $data['assigned_to'] ?? $_SESSION['user_id']
        ]);
        sendResponse(true, 'Task created', ['id' => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        sendResponse(false, 'Database Error: ' . $e->getMessage());
    }
}

function handlePut($pdo) {
    $data = getJsonInput();
    if (empty($data['id'])) sendResponse(false, 'Task ID required');

    $fields = [];
    $values = [];
    $allowed = ['title', 'description', 'type', 'status', 'priority', 'due_date', 'related_to_type', 'related_to_id', 'assigned_to', 'is_archived'];

    foreach ($allowed as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = ?";
            $values[] = $data[$field];
        }
    }

    if (empty($fields)) sendResponse(false, 'No fields to update');
    
    $values[] = $data['id'];
    $sql = "UPDATE tasks SET " . implode(', ', $fields) . " WHERE id = ?";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        sendResponse(true, 'Task updated');
    } catch (PDOException $e) {
        sendResponse(false, 'Database Error: ' . $e->getMessage());
    }
}

function handleDelete($pdo) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    if (!$id) sendResponse(false, 'ID required');

    try {
        $stmt = $pdo->prepare("UPDATE tasks SET is_archived = 1 WHERE id = ?");
        $stmt->execute([$id]);
        sendResponse(true, 'Task archived');
    } catch (PDOException $e) {
        sendResponse(false, 'Database Error: ' . $e->getMessage());
    }
}

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Task Management</h2>
        <div>
            <button class="btn btn-outline-secondary" id="toggleSelectionMode" onclick="toggleSelectionMode()">
                <i class="bi bi-check2-square"></i> Select
            </button>
            <button class="btn btn-primary" onclick="openTaskModal()">
                <i class="bi bi-plus-circle"></i> Add New Task
            </button>
            <button class="btn btn-outline-secondary ms-2" id="toggleArchiveBtn" onclick="toggleArchiveView()">
                <i class="bi bi-archive"></i> Show Archived
            </button>
            <button class="btn btn-outline-success" onclick="exportTasks()">
                <i class="bi bi-file-earmark-arrow-down"></i> Export
            </button>
            <button class="btn btn-outline-info" onclick="downloadTasksTemplate()">
                <i class="bi bi-file-earmark-spreadsheet"></i> Template
            </button>
             <button class="btn btn-outline-primary" onclick="document.getElementById('importFile').click()">
                <i class="bi bi-file-earmark-arrow-up"></i> Import
            </button>
            <input type="file" id="importFile" accept=".csv" style="display: none;" onchange="importTasks(this)">
        </div>
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
                    <!-- Lead Bulk Actions -->
                    <div id="bulkActionsToolbar-lead" class="card shadow mb-3 d-none">
                        <div class="card-body py-2 d-flex justify-content-between align-items-center bg-light border-bottom">
                            <div>
                                <span class="fw-bold text-primary"><span id="selectedCount-lead">0</span> selected</span>
                            </div>
                            <div class="btn-group gap-2">
                                <button class="btn btn-outline-success btn-sm" onclick="bulkExportTasks('lead')">
                                    <i class="bi bi-file-earmark-arrow-down"></i> Export Selected
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="bulkArchiveTasks('lead')">
                                    <i class="bi bi-archive"></i> Archive Selected
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 40px;" class="table-checkbox-col">
                                        <input type="checkbox" class="form-check-input" id="selectAll-lead" onclick="toggleSelectAllTasks('lead')">
                                    </th>
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
                    <!-- Deal Bulk Actions -->
                    <div id="bulkActionsToolbar-deal" class="card shadow mb-3 d-none">
                        <div class="card-body py-2 d-flex justify-content-between align-items-center bg-light border-bottom">
                            <div>
                                <span class="fw-bold text-primary"><span id="selectedCount-deal">0</span> selected</span>
                            </div>
                            <div class="btn-group gap-2">
                                <button class="btn btn-outline-success btn-sm" onclick="bulkExportTasks('deal')">
                                    <i class="bi bi-file-earmark-arrow-down"></i> Export Selected
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="bulkArchiveTasks('deal')">
                                    <i class="bi bi-archive"></i> Archive Selected
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 40px;" class="table-checkbox-col">
                                        <input type="checkbox" class="form-check-input" id="selectAll-deal" onclick="toggleSelectAllTasks('deal')">
                                    </th>
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

let viewingArchived = false;

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
    document.getElementById('filterDealTaskStatus').addEventListener('change', () => filterTasks('deal'));
    document.getElementById('filterDealTaskPriority').addEventListener('change', () => filterTasks('deal'));
});

function exportTasks() {
    window.location.href = '?api=true&action=export';
}

function downloadTasksTemplate() {
    window.location.href = '?api=true&action=template';
}

function importTasks(input) {
    if (input.files.length === 0) return;
    
    const formData = new FormData();
    formData.append('file', input.files[0]);
    
    fetch('?api=true&action=import', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if(res.success) {
            showToast(res.message, 'success');
            loadData();
        } else {
            showToast('Error: ' + res.message, 'error');
        }
        input.value = ''; // Reset
    })
    .catch(e => {
        showToast('Upload failed', 'error');
        console.error(e);
        input.value = '';
    });
}

async function loadData() {
    try {
        const [tasksRes, leadsRes, dealsRes] = await Promise.all([
            fetch(`?api=true&archived=${viewingArchived}`),
            fetch('../leads/?api=true'),
            fetch('../deals/?api=true') 
        ]);

        cachedTasks = (await tasksRes.json()).data || [];
        cachedLeads = (await leadsRes.json()).data || [];
        cachedDeals = (await dealsRes.json()).data || [];
        
        loadTasks();
        loadRelatedItems();
    } catch (e) {
        console.error('Failed to load data', e);
        showToast('Failed to load data: ' + e.message, 'error');
    }
}

function updateArchiveToggleUI() {
    const btn = document.getElementById('toggleArchiveBtn');
    if (btn) {
        if (viewingArchived) {
            btn.innerHTML = '<i class="bi bi-arrow-left-circle"></i> Show Active';
            btn.classList.replace('btn-outline-secondary', 'btn-secondary');
        } else {
            btn.innerHTML = '<i class="bi bi-archive"></i> Show Archived';
            btn.classList.replace('btn-secondary', 'btn-outline-secondary');
        }
    }
}

function toggleArchiveView() {
    viewingArchived = !viewingArchived;
    loadData();
}

function loadTasks() {
    displayTasks(cachedTasks, 'lead');
    displayTasks(cachedTasks, 'deal');
    updateStatistics();
    updateArchiveToggleUI();
}

// ... displayTasks remains same ...

function loadRelatedItems() {
    const section = document.getElementById('taskSection').value;
    const select = document.getElementById('relatedItem');
    select.innerHTML = '<option value="">-- Select --</option>';
    
    let items = [];
    if (section === 'lead') {
        items = cachedLeads;
    } else {
        items = cachedDeals;
    }
    
    items.forEach((item, index) => {
        const option = document.createElement('option');
        option.value = index; // We use index to reference cache
        
        if (section === 'lead') {
            option.textContent = `${item.first_name || ''} ${item.last_name || ''} - ${item.company || 'No Company'}`;
        } else {
            option.textContent = `${item.title || 'Untitled Deal'} (DEAL-${String(item.id).padStart(3, '0')})`;
        }
        
        select.appendChild(option);
    });
}

function updateTaskFields() {
    const type = document.getElementById('taskType').value;
    document.getElementById('emailFields').classList.add('d-none');
    document.getElementById('callFields').classList.add('d-none');
    document.getElementById('meetingFields').classList.add('d-none');
    
    if (type === 'email') document.getElementById('emailFields').classList.remove('d-none');
    else if (type === 'call') document.getElementById('callFields').classList.remove('d-none');
    else if (type === 'meeting') document.getElementById('meetingFields').classList.remove('d-none');
}

function toggleSelectAllTasks(section) {
    const isChecked = document.getElementById(`selectAll-${section}`).checked;
    document.querySelectorAll(`.task-checkbox-${section}`).forEach(cb => {
        cb.checked = isChecked;
    });
    updateBulkActionUI(section);
}

function updateBulkActionUI(section) {
    const selected = document.querySelectorAll(`.task-checkbox-${section}:checked`);
    const toolbar = document.getElementById(`bulkActionsToolbar-${section}`);
    const selectAll = document.getElementById(`selectAll-${section}`);
    const totalCheckboxes = document.querySelectorAll(`.task-checkbox-${section}`);
    
    // Update Select All State
    if (selected.length === totalCheckboxes.length && totalCheckboxes.length > 0) {
        selectAll.checked = true;
        selectAll.indeterminate = false;
    } else if (selected.length > 0) {
        selectAll.checked = false;
        selectAll.indeterminate = true;
    } else {
        selectAll.checked = false;
        selectAll.indeterminate = false;
    }

    // Toggle Toolbar
    if (selected.length > 0) {
        toolbar.classList.remove('d-none');
        document.getElementById(`selectedCount-${section}`).textContent = selected.length;
    } else {
        toolbar.classList.add('d-none');
    }
}

async function bulkArchiveTasks(section) {
    const selected = Array.from(document.querySelectorAll(`.task-checkbox-${section}:checked`)).map(cb => {
        // We need the ACTUAL ID, not the global index
        return cb.getAttribute('data-id');
    });
    
    if (selected.length === 0) return;
    
    if (confirm(`Are you sure you want to archive ${selected.length} tasks?`)) {
        let successCount = 0;
        
        for (const id of selected) {
            try {
                const res = await fetch(`?api=true&id=${id}`, { method: 'DELETE' });
                 const result = await res.json();
                if (result.success) successCount++;
            } catch (e) { console.error(e); }
        }
        
        if (successCount > 0) {
            showToast(`${successCount} tasks archived`, 'success');
            loadData();
        } else {
            showToast('Failed to archive selected tasks', 'error');
        }
    }
}

function displayTasks(tasks, section) {
    const tbody = document.getElementById(section === 'lead' ? 'leadTasksTableBody' : 'dealTasksTableBody');
    tbody.innerHTML = '';
    
    // Reset Bulk UI
    const selectAll = document.getElementById(`selectAll-${section}`);
    if(selectAll) selectAll.checked = false;
    updateBulkActionUI(section);

    const filteredTasks = tasks.filter(t => t.related_to_type === section || t.section === section); 
    
    if (filteredTasks.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center text-muted">No ${section} tasks found.</td></tr>`;
        return;
    }
    
    filteredTasks.forEach((task, index) => {
        // Find global index in cachedTasks for editing
        const globalIndex = cachedTasks.indexOf(task);
        
        // Resolve related name if not present
        let relatedName = task.relatedName || 'Unknown';
        if (!task.relatedName) {
            if (section === 'lead' && task.related_to_id) {
                const l = cachedLeads.find(l => l.id == task.related_to_id);
                if (l) relatedName = `${l.first_name} ${l.last_name}`;
            } else if (section === 'deal' && task.related_to_id) {
                const d = cachedDeals.find(d => d.id == task.related_to_id);
                if (d) relatedName = d.title;
            }
        }
        
        const tr = document.createElement('tr');
        tr.style.cursor = 'pointer';
        tr.onclick = (e) => {
             // Prevent if text selection
            if (window.getSelection().toString()) return;
            viewTask(globalIndex); 
        };

        tr.innerHTML = `
            <td onclick="event.stopPropagation()" class="table-checkbox-col">
                <input type="checkbox" class="form-check-input task-checkbox-${section}" data-id="${task.id}" value="${globalIndex}" onchange="updateBulkActionUI('${section}')">
            </td>
            <td>#${task.id || '-'}</td>
            <td>${task.title}</td>
            <td>${getTaskTypeIcon(task.type)} ${task.type}</td>
            <td>${relatedName}</td>
            <td>${formatDateTime(task.due_date || task.date, task.time)}</td>
            <td><span class="badge bg-${getPriorityColor(task.priority)}">${task.priority}</span></td>
            <td><span class="badge bg-${getStatusColor(task.status)}">${task.status}</span></td>
            <td>
                <div class="btn-group btn-group-sm">
                    ${viewingArchived ? 
                        `<button class="btn btn-success" title="Restore" onclick="event.stopPropagation(); restoreTask(${globalIndex})"><i class="bi bi-arrow-counterclockwise"></i></button>`
                        :
                        `<button class="btn btn-primary" onclick="event.stopPropagation(); editTask(${globalIndex})"><i class="bi bi-pencil"></i></button>
                         <button class="btn btn-success" onclick="event.stopPropagation(); toggleTaskStatus(${globalIndex})"><i class="bi bi-check-lg"></i></button>
                         <button class="btn btn-warning" title="Archive" onclick="event.stopPropagation(); archiveTask(${globalIndex})"><i class="bi bi-archive"></i></button>`
                    }
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

async function saveTask() {
    const section = document.getElementById('taskSection').value;
    const type = document.getElementById('taskType').value;
    const relatedIndex = document.getElementById('relatedItem').value;
    
    if (!relatedIndex) {
        showToast('Please select a related lead or deal', 'warning');
        return;
    }
    
    let relatedName = '';
    let relatedEntityId = '';
    if (section === 'lead') {
        // Here specific logic depends on what relatedIndex is. 
        // In loadRelatedItems we set option.value = index (of array).
        // Since we refetched array, index maps to cachedLeads[index].
        const lead = cachedLeads[relatedIndex];
        if (lead) {
            relatedName = `${lead.first_name} ${lead.last_name}`;
            relatedEntityId = lead.id;
        }
    } else {
        const deal = cachedDeals[relatedIndex];
        if (deal) {
            relatedName = deal.title || deal.dealId;
            relatedEntityId = deal.id;
        }
    }
    
    const taskData = {
        section: section,
        type: type,
        related_id: relatedEntityId, // Changed from related_to_id to match common practice or keep consistent
        related_to_type: section,
        related_to_id: relatedEntityId, // Using this for backend compatibility
        relatedName: relatedName,
        title: document.getElementById('taskTitle').value,
        due_date: document.getElementById('taskDate').value + ' ' + document.getElementById('taskTime').value,
        priority: document.getElementById('taskPriority').value,
        status: document.getElementById('taskStatus').value,
        description: document.getElementById('taskDescription').value,
    };
    
    // ... Type specific fields ...
     if (type === 'email') {
        taskData.subject = document.getElementById('emailSubject').value;
        taskData.recipients = document.getElementById('emailRecipients').value;
    } else if (type === 'call') {
        taskData.callType = document.getElementById('callType').value;
        taskData.phone = document.getElementById('callPhone').value;
        taskData.purpose = document.getElementById('callPurpose').value;
    } else if (type === 'meeting') {
        taskData.location = document.getElementById('meetingLocation').value;
        taskData.duration = document.getElementById('meetingDuration').value;
        taskData.attendees = document.getElementById('meetingAttendees').value;
    }

    const id = document.getElementById('editTaskId').value;
    let url = '?api=true';
    let method = 'POST';
    
    if (id) {
        taskData.id = id;
        method = 'PUT';
    }

    try {
        const res = await fetch(url, {
            method: method,
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(taskData)
        });
        const result = await res.json();
        
        if (result.success) {
            showToast(result.message, 'success');
            if (taskModalInstance) taskModalInstance.hide();
            else {
                 const modalEl = document.getElementById('taskModal');
                 const instance = bootstrap.Modal.getInstance(modalEl);
                 if (instance) instance.hide();
            }
            loadData(); // Reload all
        } else {
            showToast(result.message, 'error');
        }
    } catch (e) {
        showToast('Error saving task: ' + e.message, 'error');
    }
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

// ... displayTasks remains same ...

function openTaskModal() {
    document.getElementById('taskForm').reset();
    document.getElementById('taskModalTitle').textContent = 'Add New Task';
    document.getElementById('editTaskId').value = '';
    
    // Default to today
    document.getElementById('taskDate').valueAsDate = new Date();
    
    // Default section to lead and load items
    document.getElementById('taskSection').value = 'lead';
    loadRelatedItems();
    updateTaskFields();
    
    const modalEl = document.getElementById('taskModal');
    if (!taskModalInstance) {
        taskModalInstance = new bootstrap.Modal(modalEl);
    }
    taskModalInstance.show();
}

// Global modal instance variable
let taskModalInstance = null;

function editTask(index) {
    currentEditIndex = index;
    const task = cachedTasks[index];
    
    document.getElementById('taskModalTitle').textContent = 'Edit Task';
    document.getElementById('editTaskId').value = task.id;
    document.getElementById('taskSection').value = task.section || task.related_to_type || 'lead';
    
    // Refresh the dropdown options based on the section
    loadRelatedItems();
    
    // Find the index of the related item in the cache
    let relatedIndex = '';
    const relatedId = task.related_to_id;
    const relatedType = task.section || task.related_to_type || 'lead';
    
    if (relatedId) {
        if (relatedType === 'lead') {
            relatedIndex = cachedLeads.findIndex(l => l.id == relatedId);
        } else {
            relatedIndex = cachedDeals.findIndex(d => d.id == relatedId);
        }
    }
    
    // Set the dropdown value (which is the array index)
    if (relatedIndex !== -1) {
        document.getElementById('relatedItem').value = relatedIndex;
    }
    
    document.getElementById('taskType').value = task.type;
    updateTaskFields();
    
    document.getElementById('taskTitle').value = task.title;
    
    // Parse date and time if stored separately or as datetime
    // DB has due_date datetime. API might split it or return it whole.
    // formatDateTime helps display, but for input:
    if (task.due_date) {
        const dt = new Date(task.due_date);
        document.getElementById('taskDate').value = dt.toISOString().split('T')[0];
        document.getElementById('taskTime').value = dt.toTimeString().split(' ')[0].substring(0, 5);
    }
    
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
    
    const modalEl = document.getElementById('taskModal');
    if (!taskModalInstance) {
        taskModalInstance = new bootstrap.Modal(modalEl);
    }
    taskModalInstance.show();
}

function toggleTaskStatus(index) {
    cachedTasks[index].status = cachedTasks[index].status === 'pending' ? 'completed' : 'pending';
    cachedTasks[index].updatedAt = new Date().toISOString();
    localStorage.setItem('crm_tasks', JSON.stringify(cachedTasks));
    showToast('Task status updated', 'success');
    loadTasks();
}

async function archiveTask(index) {
    if (!confirm('Are you sure you want to archive this task?')) return;
    
    // In API mode, index helps us find ID but usually we pass ID directly. 
    // displayTasks uses globalIndex which maps to cachedTasks[index].
    const task = cachedTasks[index];
    if (!task || !task.id) return;

    try {
        const res = await fetch(`?api=true&id=${task.id}`, { method: 'DELETE' });
        const result = await res.json();
        
        if (result.success) {
            showToast('Task archived', 'success');
            loadData();
        } else {
            showToast(result.message, 'error');
        }
    } catch (e) {
        showToast('Error archiving task: ' + e.message, 'error');
    }
}

async function restoreTask(index) {
    if (!confirm('Are you sure you want to restore this task?')) return;
    
    const task = cachedTasks[index];
    if (!task || !task.id) return;

    try {
        const res = await fetch('?api=true', {
            method: 'PUT',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id: task.id, is_archived: 0})
        });
        const result = await res.json();
        
        if (result.success) {
            showToast('Task restored', 'success');
            loadData();
        } else {
            showToast(result.message, 'error');
        }
    } catch (e) {
        showToast('Error restoring task: ' + e.message, 'error');
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

function updateStatistics() {
    const leadTasks = cachedTasks.filter(t => t.section === 'lead' || t.related_to_type === 'lead');
    const dealTasks = cachedTasks.filter(t => t.section === 'deal' || t.related_to_type === 'deal');
    
    // Lead Stats
    document.getElementById('totalLeadTasks').textContent = leadTasks.length;
    document.getElementById('pendingLeadTasks').textContent = leadTasks.filter(t => t.status === 'pending').length;
    document.getElementById('completedLeadTasks').textContent = leadTasks.filter(t => t.status === 'completed').length;
    
    // Deal Stats
    document.getElementById('totalDealTasks').textContent = dealTasks.length;
    document.getElementById('pendingDealTasks').textContent = dealTasks.filter(t => t.status === 'pending').length;
    document.getElementById('completedDealTasks').textContent = dealTasks.filter(t => t.status === 'completed').length;
}

function formatDateTime(date, time) {
    if (!date) return '-';
    const d = new Date(date);
    const dateStr = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    return time ? `${dateStr} ${time}` : dateStr;
}


function bulkExportTasks(type) {
    const selected = Array.from(document.querySelectorAll(`.task-checkbox-${type}:checked`)).map(cb => cb.value);
    if (selected.length === 0) return;
    window.location.href = `?api=true&action=export&ids=${selected.join(',')}`;
}

let selectionModeActive = false;

function toggleSelectionMode() {
    selectionModeActive = !selectionModeActive;
    const leadTable = document.getElementById('leadTasksTable');
    const dealTable = document.getElementById('dealTasksTable');
    const btn = document.getElementById('toggleSelectionMode');

    if (selectionModeActive) {
        if(leadTable) leadTable.classList.remove('selection-mode-off');
        if(dealTable) dealTable.classList.remove('selection-mode-off');
        btn.classList.replace('btn-outline-secondary', 'btn-secondary');
        btn.innerHTML = '<i class="bi bi-x-circle"></i> Cancel Select';
    } else {
        if(leadTable) leadTable.classList.add('selection-mode-off');
        if(dealTable) dealTable.classList.add('selection-mode-off');
        btn.classList.replace('btn-secondary', 'btn-outline-secondary');
        btn.innerHTML = '<i class="bi bi-check2-square"></i> Select';

        // Reset selections
        ['lead', 'deal'].forEach(section => {
            const selectAll = document.getElementById(`selectAll-${section}`);
            if(selectAll) selectAll.checked = false;
            document.querySelectorAll(`.task-checkbox-${section}`).forEach(cb => cb.checked = false);
            updateBulkActionUI(section);
        });
    }
}
</script>

<style>
.selection-mode-off .table-checkbox-col {
    display: none;
}

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

