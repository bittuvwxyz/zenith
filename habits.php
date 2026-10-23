<?php
/**
 * Habits Page
 *
 * Manage daily habits, track streaks, adopt templates, check in,
 * and perform complete CRUD (Create, Read, Update, Delete).
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/habits.php';
require_once __DIR__ . '/includes/categories.php';

$user = requireAuth();
$userId = (int) $user['id'];

$habits = getHabits($userId, false);
$templates = getHabitTemplates();
$categories = getCategories($userId);

$pageTitle = 'My Habits';
$pageSubtitle = 'Build consistency, track daily streaks, and achieve long-term compound growth.';
$activeNav = 'habits';

require_once __DIR__ . '/includes/header.php';
?>

<!-- 1. Inline Quick Add Habit Bar -->
<div class="quick-add-card">
    <form method="POST" action="habits-action.php" style="display: flex; flex-direction: column; gap: 12px;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">

        <div style="display: flex; gap: 10px; align-items: center;">
            <input type="text" name="title" class="form-control" placeholder="What habit are you cultivating? (e.g. Read 20 pages, 30 min morning run...)" required autofocus style="font-size: 1rem; padding: 12px 16px;">
            <button type="submit" class="btn btn-primary" style="padding: 12px 24px; font-weight: 700; flex-shrink: 0;">
                <?= renderIcon('plus') ?> Add Habit
            </button>
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
            <div style="display: flex; align-items: center; gap: 6px;">
                <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Frequency:</span>
                <select name="target_frequency" class="form-select" style="padding: 6px 10px; font-size: 0.84rem; width: auto;">
                    <option value="daily" selected>Daily</option>
                    <option value="weekly">Weekly</option>
                </select>
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

            <div style="display: flex; align-items: center; gap: 6px;">
                <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Color:</span>
                <input type="color" name="color" value="#10b981" style="width: 32px; height: 32px; border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); cursor: pointer; padding: 0; background: transparent;">
            </div>

            <div style="margin-left: auto; display: flex; gap: 8px;">
                <button type="button" class="btn btn-secondary btn-sm" data-open-modal="templatesModal">
                    <?= renderIcon('book') ?> Adopt Template
                </button>
                <button type="button" class="btn btn-ghost btn-sm" data-open-modal="createHabitModal">
                    + Add Description
                </button>
            </div>
        </div>
    </form>
</div>

<!-- 2. Header and Quick Stats Summary -->
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--text-primary);">Active Habits (<?= count($habits) ?>)</h2>
        <p style="font-size: 0.85rem; color: var(--text-muted);">Click the circle to check in today. Streaks calculate automatically.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <button type="button" class="btn btn-secondary btn-sm" data-open-modal="templatesModal">
            <?= renderIcon('book') ?> Habit Templates
        </button>
        <button type="button" class="btn btn-primary btn-sm" data-open-modal="createHabitModal">
            <?= renderIcon('plus') ?> Detailed Habit
        </button>
    </div>
</div>

<!-- 3. Habits Cards Grid -->
<?php if (empty($habits)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-state-icon"><?= renderIcon('flame') ?></div>
                <div class="empty-state-title">No habits created yet</div>
                <p class="empty-state-desc">Consistent small actions compound into massive results. Start by entering a habit above or choose from our curated templates.</p>
                <div style="display: flex; gap: 12px; justify-content: center;">
                    <button type="button" class="btn btn-secondary" data-open-modal="templatesModal">Browse Templates</button>
                    <button type="button" class="btn btn-primary" data-open-modal="createHabitModal"><?= renderIcon('plus') ?> Create Custom Habit</button>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="grid-2">
        <?php foreach ($habits as $h): ?>
            <div class="card habit-card-item" id="habit-card-<?= (int)$h['id'] ?>" style="display: flex; flex-direction: column; justify-content: space-between; border-left: 4px solid <?= escape($h['color'] ?? '#10b981') ?>;">
                <div class="card-body" style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px;">
                    <div style="display: flex; align-items: flex-start; gap: 14px; flex: 1; min-width: 0;">
                        <!-- Interactive Check-in Button -->
                        <button type="button"
                                class="habit-check-btn <?= $h['is_completed_today'] ? 'completed' : '' ?>"
                                data-habit-id="<?= (int)$h['id'] ?>"
                                aria-label="Toggle habit completion for <?= escape($h['title']) ?>"
                                title="<?= $h['is_completed_today'] ? 'Completed today! Click to undo.' : 'Click to check in today' ?>">
                            <?= $h['is_completed_today'] ? '✓' : '' ?>
                        </button>

                        <div style="flex: 1; min-width: 0;">
                            <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--text-primary); margin-bottom: 4px; word-break: break-word;">
                                <?= escape($h['title']) ?>
                            </h3>
                            <?php if (!empty($h['description'])): ?>
                                <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 10px; line-height: 1.5; white-space: pre-wrap;">
                                    <?= escape($h['description']) ?>
                                </p>
                            <?php endif; ?>
                            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px;">
                                <span class="badge badge-warning habit-streak-<?= (int)$h['id'] ?>" title="Current consecutive day streak">
                                    🔥 <?= (int)$h['streak'] ?> Day Streak
                                </span>
                                <span class="badge badge-info" title="Total lifetime check-ins">
                                    <?= (int)$h['total_completions'] ?> Total
                                </span>
                                <span class="badge" style="background: rgba(255,255,255,0.06); color: var(--text-muted); text-transform: capitalize;">
                                    <?= escape($h['target_frequency'] ?? 'daily') ?>
                                </span>
                                <?php if (!empty($h['category_name'])): ?>
                                    <span class="badge" style="background: rgba(255,255,255,0.08); color: <?= escape($h['category_color'] ?? '#fff') ?>;">
                                        <?= escape($h['category_name']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Actions: Edit & Delete -->
                    <div style="display: flex; align-items: center; gap: 4px; flex-shrink: 0;">
                        <button type="button"
                                class="btn-icon edit-habit-btn"
                                data-habit-id="<?= (int)$h['id'] ?>"
                                data-title="<?= htmlspecialchars($h['title'], ENT_QUOTES, 'UTF-8') ?>"
                                data-description="<?= htmlspecialchars($h['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                data-frequency="<?= htmlspecialchars($h['target_frequency'] ?? 'daily', ENT_QUOTES, 'UTF-8') ?>"
                                data-color="<?= htmlspecialchars($h['color'] ?? '#10b981', ENT_QUOTES, 'UTF-8') ?>"
                                data-category-id="<?= (int)($h['category_id'] ?? 0) ?>"
                                title="Edit Habit">
                            <?= renderIcon('edit') ?>
                        </button>

                        <form method="POST" action="habits-action.php" onsubmit="return confirm('Are you sure you want to delete this habit and all its logged history?');" style="margin: 0;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="habit_id" value="<?= (int)$h['id'] ?>">
                            <button type="submit" class="btn-icon" style="color: var(--accent-danger);" title="Delete Habit">
                                <?= renderIcon('trash') ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Create Habit Modal -->
<div id="createHabitModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="createHabitTitle">
    <div class="modal-dialog">
        <form method="POST" action="habits-action.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">

            <div class="modal-header">
                <h3 id="createHabitTitle" class="card-title"><?= renderIcon('flame') ?> Create New Habit</h3>
                <button type="button" class="btn-icon" data-close-modal="createHabitModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="habit_title">Habit Title *</label>
                    <input type="text" id="habit_title" name="title" class="form-control" placeholder="e.g. Read 20 pages, 30 min morning run" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="habit_desc">Description (Optional)</label>
                    <textarea id="habit_desc" name="description" class="form-textarea" placeholder="Why is this habit important to you? What is your trigger?"></textarea>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label" for="habit_target_freq">Target Frequency</label>
                        <select id="habit_target_freq" name="target_frequency" class="form-select">
                            <option value="daily" selected>Daily</option>
                            <option value="weekly">Weekly</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="habit_cat">Category</label>
                        <select id="habit_cat" name="category_id" class="form-select">
                            <option value="">None</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>"><?= escape($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="habit_color">Accent Color</label>
                    <input type="color" id="habit_color" name="color" value="#10b981" style="height: 42px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); width: 100%; cursor: pointer;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="createHabitModal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Habit</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Habit Modal -->
<div id="editHabitModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="editHabitTitle">
    <div class="modal-dialog">
        <form method="POST" action="habits-action.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" id="edit_habit_id" name="habit_id" value="">

            <div class="modal-header">
                <h3 id="editHabitTitle" class="card-title"><?= renderIcon('edit') ?> Edit Habit</h3>
                <button type="button" class="btn-icon" data-close-modal="editHabitModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="edit_habit_title">Habit Title *</label>
                    <input type="text" id="edit_habit_title" name="title" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_habit_desc">Description</label>
                    <textarea id="edit_habit_desc" name="description" class="form-textarea"></textarea>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label" for="edit_habit_freq">Target Frequency</label>
                        <select id="edit_habit_freq" name="target_frequency" class="form-select">
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_habit_cat">Category</label>
                        <select id="edit_habit_cat" name="category_id" class="form-select">
                            <option value="">None</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>"><?= escape($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_habit_color">Accent Color</label>
                    <input type="color" id="edit_habit_color" name="color" value="#10b981" style="height: 42px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); width: 100%; cursor: pointer;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="editHabitModal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Habit</button>
            </div>
        </form>
    </div>
</div>

<!-- Habit Templates Modal -->
<div id="templatesModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="templatesModalTitle">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 id="templatesModalTitle" class="card-title"><?= renderIcon('book') ?> Science-Backed Habit Templates</h3>
            <button type="button" class="btn-icon" data-close-modal="templatesModal">&times;</button>
        </div>
        <div class="modal-body" style="display: flex; flex-direction: column; gap: 14px; max-height: 60vh; overflow-y: auto;">
            <?php foreach ($templates as $idx => $tpl): ?>
                <form method="POST" action="habits-action.php" class="card" style="padding: 16px; display: flex; align-items: center; justify-content: space-between; gap: 14px; margin-bottom: 0;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="adopt_template">
                    <input type="hidden" name="template_index" value="<?= $idx ?>">
                    <div style="flex: 1;">
                        <div style="font-weight: 700; color: var(--text-primary); font-size: 0.95rem;">
                            <?= escape($tpl['title']) ?>
                        </div>
                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 2px;">
                            <?= escape($tpl['description']) ?>
                        </div>
                        <div style="font-size: 0.72rem; color: var(--accent-success); margin-top: 4px; font-weight: 600;">
                            Frequency: <?= escape(ucfirst($tpl['target_frequency'])) ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" style="flex-shrink: 0;">
                        Adopt Habit
                    </button>
                </form>
            <?php endforeach; ?>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-close-modal="templatesModal">Close</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
