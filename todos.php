<?php
/**
 * To-Do List Page
 *
 * Full CRUD: Inline Quick Add bar, modal editor, priority badges,
 * category tags, due dates, searching, filtering, and pagination.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/tasks.php';
require_once __DIR__ . '/includes/categories.php';

$user = requireAuth();
$userId = (int) $user['id'];

// Filters
$statusFilter = $_GET['status'] ?? '';
$priorityFilter = $_GET['priority'] ?? '';
$categoryFilter = $_GET['category_id'] ?? '';
$dueFilter = $_GET['due'] ?? '';
$searchFilter = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$filters = [
    'status'      => $statusFilter,
    'priority'    => $priorityFilter,
    'category_id' => $categoryFilter,
    'due'         => $dueFilter,
    'search'      => $searchFilter,
];

$taskData = getTasks($userId, $filters, $limit, $offset);
$tasks = $taskData['tasks'];
$total = $taskData['total'];
$totalPages = ceil($total / $limit);

$categories = getCategories($userId);

$pageTitle = 'To-Do List';
$pageSubtitle = 'Organize, prioritize, and accomplish your key deliverables.';
$activeNav = 'todos';

require_once __DIR__ . '/includes/header.php';
?>

<!-- 1. Prominent Inline Quick Add Task Bar -->
<div class="quick-add-card">
    <form method="POST" action="tasks-action.php" style="display: flex; flex-direction: column; gap: 12px;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">

        <div style="display: flex; gap: 10px; align-items: center;">
            <input type="text" name="title" class="form-control" placeholder="What needs to be done? Type task and press Enter..." required autofocus style="font-size: 1rem; padding: 12px 16px;">
            <button type="submit" class="btn btn-primary" style="padding: 12px 24px; font-weight: 700; flex-shrink: 0;">
                <?= renderIcon('plus') ?> Add Task
            </button>
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
            <div style="display: flex; align-items: center; gap: 6px;">
                <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Priority:</span>
                <select name="priority" class="form-select" style="padding: 6px 10px; font-size: 0.84rem; width: auto;">
                    <option value="medium" selected>Medium</option>
                    <option value="urgent">Urgent</option>
                    <option value="high">High</option>
                    <option value="low">Low</option>
                </select>
            </div>

            <div style="display: flex; align-items: center; gap: 6px;">
                <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Due:</span>
                <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d') ?>" style="padding: 6px 10px; font-size: 0.84rem; width: auto;">
            </div>

            <div style="display: flex; align-items: center; gap: 6px;">
                <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Category:</span>
                <select name="category_id" class="form-select" style="padding: 6px 10px; font-size: 0.84rem; width: auto;">
                    <option value="">No Category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>"><?= escape($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="button" class="btn btn-ghost btn-sm" data-open-modal="createTaskModal" style="margin-left: auto;">
                + Add Description &amp; Details
            </button>
        </div>
    </form>
</div>

<!-- 2. Search & Filter Bar -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 14px 20px;">
        <form method="GET" action="todos.php" style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px;">
            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px; flex: 1;">
                <div style="position: relative; min-width: 200px;">
                    <input type="text" name="search" class="form-control" placeholder="Search tasks..." value="<?= escape($searchFilter) ?>" style="padding-left: 36px; padding-top: 8px; padding-bottom: 8px;">
                    <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none;">
                        <?= renderIcon('search') ?>
                    </span>
                </div>

                <select name="status" class="form-select" style="width: auto; padding: 8px 12px;" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending Only</option>
                    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed Only</option>
                </select>

                <select name="due" class="form-select" style="width: auto; padding: 8px 12px;" onchange="this.form.submit()">
                    <option value="">Any Due Date</option>
                    <option value="today" <?= $dueFilter === 'today' ? 'selected' : '' ?>>Due Today</option>
                    <option value="upcoming" <?= $dueFilter === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                    <option value="overdue" <?= $dueFilter === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                </select>

                <select name="priority" class="form-select" style="width: auto; padding: 8px 12px;" onchange="this.form.submit()">
                    <option value="">Any Priority</option>
                    <option value="urgent" <?= $priorityFilter === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                    <option value="high" <?= $priorityFilter === 'high' ? 'selected' : '' ?>>High</option>
                    <option value="medium" <?= $priorityFilter === 'medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="low" <?= $priorityFilter === 'low' ? 'selected' : '' ?>>Low</option>
                </select>

                <select name="category_id" class="form-select" style="width: auto; padding: 8px 12px;" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>>
                            <?= escape($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn btn-secondary btn-sm"><?= renderIcon('filter') ?> Apply</button>
                <?php if (!empty($searchFilter) || !empty($statusFilter) || !empty($priorityFilter) || !empty($dueFilter) || !empty($categoryFilter)): ?>
                    <a href="todos.php" class="btn btn-ghost btn-sm">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- 3. Task List View -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><?= renderIcon('check-square') ?> Tasks (<?= $total ?>)</h2>
        <span style="font-size: 0.85rem; color: var(--text-muted);">
            Showing <?= count($tasks) ?> of <?= $total ?> deliverables
        </span>
    </div>
    <div class="card-body" style="padding: 16px;">
        <?php if (empty($tasks)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><?= renderIcon('check-square') ?></div>
                <div class="empty-state-title">No tasks found</div>
                <p class="empty-state-desc">You have no tasks matching this view. Use the quick add bar above to create one!</p>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <?php foreach ($tasks as $t): ?>
                    <div class="task-item <?= $t['status'] === 'completed' ? 'completed' : '' ?>" id="task-row-<?= (int)$t['id'] ?>">
                        <!-- Interactive Checkbox -->
                        <button type="button"
                                class="task-checkbox task-checkbox-btn <?= $t['status'] === 'completed' ? 'checked' : '' ?>"
                                data-task-id="<?= (int)$t['id'] ?>"
                                aria-label="Toggle task status">
                            <?= $t['status'] === 'completed' ? '✓' : '' ?>
                        </button>

                        <!-- Task Details -->
                        <div style="flex: 1; min-width: 0;">
                            <div class="task-title">
                                <?= escape($t['title']) ?>
                            </div>
                            <?php if (!empty($t['description'])): ?>
                                <p style="font-size: 0.84rem; color: var(--text-muted); margin-top: 3px; line-height: 1.4;">
                                    <?= escape($t['description']) ?>
                                </p>
                            <?php endif; ?>
                            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 6px;">
                                <?php if ($t['priority'] === 'urgent'): ?>
                                    <span class="badge badge-danger">Urgent</span>
                                <?php elseif ($t['priority'] === 'high'): ?>
                                    <span class="badge badge-warning">High</span>
                                <?php elseif ($t['priority'] === 'medium'): ?>
                                    <span class="badge badge-info">Medium</span>
                                <?php else: ?>
                                    <span class="badge" style="background: rgba(255,255,255,0.06); color: var(--text-muted);">Low</span>
                                <?php endif; ?>

                                <?php if (!empty($t['due_date'])): ?>
                                    <?php
                                    $isOverdue = (strtotime($t['due_date']) < strtotime('today') && $t['status'] === 'pending');
                                    ?>
                                    <span style="font-size: 0.78rem; font-weight: 600; color: <?= $isOverdue ? 'var(--accent-danger)' : 'var(--text-muted)' ?>; display: inline-flex; align-items: center; gap: 4px;">
                                        <?= renderIcon('calendar', 'icon-sm') ?>
                                        <?= $isOverdue ? 'Overdue: ' : 'Due ' ?><?= formatDate($t['due_date']) ?> <?= !empty($t['due_time']) ? substr($t['due_time'], 0, 5) : '' ?>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($t['category_name'])): ?>
                                    <span class="badge" style="background: rgba(255,255,255,0.08); color: <?= escape($t['category_color'] ?? '#fff') ?>;">
                                        <?= escape($t['category_name']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Actions (Edit & Delete) -->
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <button type="button"
                                    class="btn-icon edit-task-btn"
                                    data-task-id="<?= (int)$t['id'] ?>"
                                    data-title="<?= escape($t['title']) ?>"
                                    data-description="<?= escape($t['description'] ?? '') ?>"
                                    data-priority="<?= escape($t['priority']) ?>"
                                    data-category-id="<?= (int)($t['category_id'] ?? 0) ?>"
                                    data-due-date="<?= escape($t['due_date'] ?? '') ?>"
                                    data-due-time="<?= escape($t['due_time'] ?? '') ?>"
                                    title="Edit Task">
                                <?= renderIcon('edit') ?>
                            </button>

                            <form method="POST" action="tasks-action.php" onsubmit="return confirm('Delete this task permanently?');" style="margin: 0;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                                <button type="submit" class="btn-icon" style="color: var(--accent-danger);" title="Delete Task">
                                    <?= renderIcon('trash') ?>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div style="display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 24px;">
                    <?php if ($page > 1): ?>
                        <a href="todos.php?<?= http_build_query(array_merge($filters, ['page' => $page - 1])) ?>" class="btn btn-secondary btn-sm">&larr; Prev</a>
                    <?php endif; ?>

                    <span style="font-size: 0.85rem; color: var(--text-muted); padding: 0 8px;">
                        Page <?= $page ?> of <?= $totalPages ?>
                    </span>

                    <?php if ($page < $totalPages): ?>
                        <a href="todos.php?<?= http_build_query(array_merge($filters, ['page' => $page + 1])) ?>" class="btn btn-secondary btn-sm">Next &rarr;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal 1: Detailed Create Task Modal -->
<div id="createTaskModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="createTaskTitle">
    <div class="modal-dialog">
        <form method="POST" action="tasks-action.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">

            <div class="modal-header">
                <h3 id="createTaskTitle" class="card-title"><?= renderIcon('check-square') ?> New Task Deliverable</h3>
                <button type="button" class="btn-icon" data-close-modal="createTaskModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="task_title_modal">Task Title *</label>
                    <input type="text" id="task_title_modal" name="title" class="form-control" placeholder="What needs to be accomplished?" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="task_desc_modal">Detailed Description / Notes</label>
                    <textarea id="task_desc_modal" name="description" class="form-textarea" placeholder="Add steps, criteria, or context..."></textarea>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label" for="task_priority_modal">Priority</label>
                        <select id="task_priority_modal" name="priority" class="form-select">
                            <option value="urgent">Urgent</option>
                            <option value="high">High</option>
                            <option value="medium" selected>Medium</option>
                            <option value="low">Low</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="task_cat_modal">Category</label>
                        <select id="task_cat_modal" name="category_id" class="form-select">
                            <option value="">None</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>"><?= escape($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label" for="task_due_date_modal">Due Date</label>
                        <input type="date" id="task_due_date_modal" name="due_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="task_due_time_modal">Due Time (Optional)</label>
                        <input type="time" id="task_due_time_modal" name="due_time" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="createTaskModal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Task</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Edit Task Modal -->
<div id="editTaskModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="editTaskTitle">
    <div class="modal-dialog">
        <form method="POST" action="tasks-action.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" id="edit_task_id" name="task_id" value="">

            <div class="modal-header">
                <h3 id="editTaskTitle" class="card-title"><?= renderIcon('edit') ?> Edit Task</h3>
                <button type="button" class="btn-icon" data-close-modal="editTaskModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="edit_task_title">Title *</label>
                    <input type="text" id="edit_task_title" name="title" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_task_desc">Description</label>
                    <textarea id="edit_task_desc" name="description" class="form-textarea"></textarea>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label" for="edit_task_priority">Priority</label>
                        <select id="edit_task_priority" name="priority" class="form-select">
                            <option value="urgent">Urgent</option>
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_task_cat">Category</label>
                        <select id="edit_task_cat" name="category_id" class="form-select">
                            <option value="">None</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>"><?= escape($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label" for="edit_task_due_date">Due Date</label>
                        <input type="date" id="edit_task_due_date" name="due_date" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_task_due_time">Due Time</label>
                        <input type="time" id="edit_task_due_time" name="due_time" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="editTaskModal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
