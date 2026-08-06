<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';

if (!hasPermission('view_tasks')) {
    header("Location: " . BASE_URL . "/dashboard");
    exit();
}

require_once __DIR__ . '/../../includes/header.php';

$stmt = $pdo->query("SELECT t.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), u.name, u.email) as assigned_to_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id WHERE t.is_archived = 0 ORDER BY t.due_date ASC");
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$userStmt = $pdo->query("SELECT id, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))), ''), name, email) as name FROM users WHERE is_archived = 0 ORDER BY name ASC");
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <?php renderBreadcrumb([['label' => 'Tasks', 'url' => '']]); ?>
            <h2 class="fw-bold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">Tasks & Follow-ups</h2>
            <p class="text-muted small mb-0">Schedule activities, calls, emails, and upcoming todos.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if (hasPermission('add_tasks')): ?>
                <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="offcanvas" data-bs-target="#taskOffcanvas" onclick="resetTaskForm()">
                    <i data-lucide="plus" class="lucide-icon me-1"></i> New Task
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Total Tasks', count($tasks), 'check-square', 'primary'); ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php 
                $pendingCount = count(array_filter($tasks, fn($t) => $t['status'] === 'pending'));
                renderStatCard('Pending Action', $pendingCount, 'clock', 'warning', 'Due / Upcoming'); 
            ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php 
                $completedCount = count(array_filter($tasks, fn($t) => $t['status'] === 'completed'));
                renderStatCard('Completed Tasks', $completedCount, 'check-circle-2', 'success'); 
            ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php 
                $highPriorityCount = count(array_filter($tasks, fn($t) => $t['priority'] === 'high' && $t['status'] !== 'completed'));
                renderStatCard('High Priority', $highPriorityCount, 'alert-triangle', 'danger', 'Attention needed'); 
            ?>
        </div>
    </div>

    <!-- Table Container -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-family: 'Outfit', sans-serif;">
                    <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <tr>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Title</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Type</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Priority</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Due Date</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Status</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Assigned To</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold text-end" style="font-size: 0.75rem;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tasks)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted small">
                                    <i data-lucide="inbox" class="lucide-icon d-block mb-2 text-muted mx-auto"></i>
                                    No tasks found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tasks as $t): ?>
                                <?php
                                    $prioBadge = [
                                        'high' => 'badge-soft-danger',
                                        'medium' => 'badge-soft-warning',
                                        'low' => 'badge-soft-info'
                                    ][$t['priority']] ?? 'badge-soft-secondary';
                                ?>
                                <tr>
                                    <td class="py-3 px-4 fw-semibold text-dark"><?php echo htmlspecialchars($t['title']); ?></td>
                                    <td class="py-3 px-4 text-muted"><span class="badge badge-soft-secondary"><?php echo ucfirst($t['type']); ?></span></td>
                                    <td class="py-3 px-4"><span class="badge rounded-pill <?php echo $prioBadge; ?>"><?php echo ucfirst($t['priority']); ?></span></td>
                                    <td class="py-3 px-4 text-muted"><?php echo $t['due_date'] ? date('M j, Y H:i', strtotime($t['due_date'])) : '-'; ?></td>
                                    <td class="py-3 px-4">
                                        <?php if ($t['status'] === 'completed'): ?>
                                            <span class="badge badge-soft-success rounded-pill"><i data-lucide="check" class="lucide-icon me-1"></i> Completed</span>
                                        <?php else: ?>
                                            <span class="badge badge-soft-warning rounded-pill">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-muted"><?php echo htmlspecialchars($t['assigned_to_name'] ?: 'Unassigned'); ?></td>
                                    <td class="py-3 px-4 text-end">
                                        <div class="dropdown">
                                            <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 dropdown-toggle shadow-sm" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.8rem; font-family: 'Outfit', sans-serif;">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 text-small">
                                                <?php if ($t['status'] !== 'completed'): ?>
                                                    <li>
                                                        <a class="dropdown-item py-2 text-success d-flex align-items-center gap-2" href="#" onclick="toggleTaskStatus(<?php echo $t['id']; ?>, 'completed'); return false;">
                                                            <i data-lucide="check-circle-2" class="lucide-icon text-success"></i> Mark Complete
                                                        </a>
                                                    </li>
                                                <?php else: ?>
                                                    <li>
                                                        <a class="dropdown-item py-2 text-warning d-flex align-items-center gap-2" href="#" onclick="toggleTaskStatus(<?php echo $t['id']; ?>, 'pending'); return false;">
                                                            <i data-lucide="rotate-ccw" class="lucide-icon text-warning"></i> Re-open Task
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                <?php if (hasPermission('edit_tasks')): ?>
                                                    <li><hr class="dropdown-divider my-1"></li>
                                                    <li>
                                                        <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="#" onclick="editTask(<?php echo $t['id']; ?>); return false;">
                                                            <i data-lucide="edit-3" class="lucide-icon text-muted"></i> Edit Task
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
        </div>
    </div>
</div>

<!-- 50% WIDTH OFFCANVAS SLIDER FOR NEW / EDIT TASK -->
<div class="offcanvas offcanvas-end offcanvas-half" id="taskOffcanvas" tabindex="-1" aria-labelledby="taskOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title fw-bold text-dark" id="taskOffcanvasTitle" style="font-family: 'Outfit', sans-serif;">New Task & Activity</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <form id="taskForm" onsubmit="saveTask(event)">
        <div class="offcanvas-body p-4">
            <input type="hidden" id="taskId" name="id">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label small fw-semibold">Task Title <span class="text-danger">*</span></label>
                    <input type="text" id="taskTitle" name="title" class="form-control" placeholder="e.g. Schedule demo call with Acme Inc" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Task Type</label>
                    <select id="taskType" name="type" class="form-select">
                        <option value="todo">To-Do</option>
                        <option value="call">Call</option>
                        <option value="email">Email</option>
                        <option value="meeting">Meeting</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Priority Level</label>
                    <select id="taskPriority" name="priority" class="form-select">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Due Date & Time</label>
                    <input type="datetime-local" id="taskDueDate" name="due_date" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Assigned Staff</label>
                    <select id="taskAssignedTo" name="assigned_to" class="form-select">
                        <option value="">Select Staff</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">Description & Notes</label>
                    <textarea id="taskDescription" name="description" class="form-control" rows="3" placeholder="Task notes and instructions..."></textarea>
                </div>
            </div>
        </div>
        <div class="offcanvas-footer gap-2 d-flex justify-content-end">
            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="offcanvas">Cancel</button>
            <button type="submit" class="btn btn-primary rounded-pill px-4">Save Task</button>
        </div>
    </form>
</div>

<script>
function resetTaskForm() {
    document.getElementById('taskForm').reset();
    document.getElementById('taskId').value = '';
    document.getElementById('taskOffcanvasTitle').textContent = 'New Task & Activity';
}

function saveTask(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(document.getElementById('taskForm')).entries());
    fetch('<?php echo BASE_URL; ?>/ajax/tasks/save', {
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

function toggleTaskStatus(id, status) {
    fetch('<?php echo BASE_URL; ?>/ajax/tasks/status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ task_id: id, status: status })
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

function editTask(id) {
    fetch('<?php echo BASE_URL; ?>/ajax/tasks/get?id=' + id)
    .then(res => res.json())
    .then(res => {
        if (res.success && res.data) {
            const t = res.data;
            document.getElementById('taskId').value = t.id;
            document.getElementById('taskTitle').value = t.title || '';
            document.getElementById('taskType').value = t.type || 'todo';
            document.getElementById('taskPriority').value = t.priority || 'medium';
            document.getElementById('taskDueDate').value = t.due_date ? t.due_date.replace(' ', 'T') : '';
            document.getElementById('taskAssignedTo').value = t.assigned_to || '';
            document.getElementById('taskDescription').value = t.description || '';
            document.getElementById('taskOffcanvasTitle').textContent = 'Edit Task';
            new bootstrap.Offcanvas(document.getElementById('taskOffcanvas')).show();
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
