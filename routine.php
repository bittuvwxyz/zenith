<?php
/**
 * Daily Routine Page
 *
 * Organize daily recurring rhythms into structured Morning, Afternoon,
 * Evening, and Night blocks with completion tracking and full CRUD.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/routines.php';

$user = requireAuth();
$userId = (int) $user['id'];

$today = date('Y-m-d');
$progress = getDailyRoutineProgress($userId, $today);

$blocks = [
    'morning'   => ['title' => 'Morning Routine', 'icon' => 'sun', 'color' => '#f59e0b', 'desc' => 'Prime your mindset and energy.'],
    'afternoon' => ['title' => 'Afternoon Focus', 'icon' => 'clock', 'color' => '#3b82f6', 'desc' => 'Deep work and execution blocks.'],
    'evening'   => ['title' => 'Evening Wind-Down', 'icon' => 'moon', 'color' => '#8b5cf6', 'desc' => 'Decompress, reflect, and disengage.'],
    'night'     => ['title' => 'Night Prep', 'icon' => 'lock', 'color' => '#06b6d4', 'desc' => 'Set up tomorrow for success.'],
];

$pageTitle = 'Daily Routine';
$pageSubtitle = 'Design structured daily rhythm across morning, afternoon, evening, and night.';
$activeNav = 'routine';

require_once __DIR__ . '/includes/header.php';
?>

<!-- 1. Inline Quick Add Routine Step Bar -->
<div class="quick-add-card">
    <form method="POST" action="routine-action.php" style="display: flex; flex-direction: column; gap: 12px;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">

        <div style="display: flex; gap: 10px; align-items: center;">
            <input type="text" name="title" class="form-control" placeholder="Add a routine step... (e.g. 500ml water, stretch, plan top 3 priorities)" required autofocus style="font-size: 1rem; padding: 12px 16px;">
            <button type="submit" class="btn btn-primary" style="padding: 12px 24px; font-weight: 700; flex-shrink: 0;">
                <?= renderIcon('plus') ?> Add Step
            </button>
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
            <div style="display: flex; align-items: center; gap: 6px;">
                <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Time Block:</span>
                <select name="time_block" class="form-select" style="padding: 6px 10px; font-size: 0.84rem; width: auto;">
                    <option value="morning" selected>Morning</option>
                    <option value="afternoon">Afternoon</option>
                    <option value="evening">Evening</option>
                    <option value="night">Night</option>
                </select>
            </div>

            <div style="display: flex; align-items: center; gap: 6px;">
                <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Est. Time:</span>
                <input type="number" name="estimated_minutes" class="form-control" value="15" min="1" max="180" style="padding: 6px 10px; font-size: 0.84rem; width: 80px;">
                <span style="font-size: 0.8rem; color: var(--text-muted);">mins</span>
            </div>

            <button type="button" class="btn btn-ghost btn-sm" data-open-modal="createRoutineModal" style="margin-left: auto;">
                + Detailed Routine Step
            </button>
        </div>
    </form>
</div>

<!-- 2. Today's Progress Overview Card -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 20px;">
        <div style="flex: 1; min-width: 260px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span style="font-weight: 700; color: var(--text-primary); font-size: 0.98rem;">Today's Routine Execution</span>
                <span style="font-weight: 700; color: var(--accent-success); font-size: 0.98rem;"><?= $progress['percent'] ?>% Completed</span>
            </div>
            <div class="progress-bar-bg" style="height: 10px; border-radius: var(--radius-full); overflow: hidden;">
                <div class="progress-bar-fill" style="width: <?= $progress['percent'] ?>%; transition: width 0.4s ease;"></div>
            </div>
            <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 6px;">
                <?= $progress['completed'] ?> of <?= $progress['total'] ?> routine steps completed today (<?= date('l, M j') ?>)
            </div>
        </div>

        <button type="button" class="btn btn-primary btn-sm" data-open-modal="createRoutineModal">
            <?= renderIcon('plus') ?> Add Step Modal
        </button>
    </div>
</div>

<!-- 3. Routine Blocks Grid -->
<div class="grid-2">
    <?php foreach ($blocks as $blockKey => $b): ?>
        <?php
        $routinesInBlock = getRoutines($userId, $blockKey, $today);
        ?>
        <div class="card">
            <div class="card-header" style="padding: 14px 20px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="color: <?= $b['color'] ?>; font-size: 1.2rem;">
                        <?= renderIcon($b['icon']) ?>
                    </span>
                    <div>
                        <h3 class="card-title" style="font-size: 1rem;"><?= $b['title'] ?></h3>
                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?= $b['desc'] ?></div>
                    </div>
                </div>
                <span class="badge" style="background: rgba(255,255,255,0.06); color: var(--text-muted);">
                    <?= count($routinesInBlock) ?> items
                </span>
            </div>

            <div class="card-body" style="padding: 14px 20px;">
                <?php if (empty($routinesInBlock)): ?>
                    <p style="font-size: 0.85rem; color: var(--text-muted); text-align: center; padding: 20px 0;">
                        No steps added for this time block yet.
                    </p>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <?php foreach ($routinesInBlock as $r): ?>
                            <div class="task-item" style="padding: 10px 14px; margin-bottom: 0; display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                                <div style="display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0;">
                                    <button type="button"
                                            class="task-checkbox routine-check-btn <?= $r['is_completed_today'] ? 'checked' : '' ?>"
                                            data-routine-id="<?= (int)$r['id'] ?>"
                                            aria-label="Toggle routine completion for <?= escape($r['title']) ?>"
                                            title="Click to check in">
                                        <?= $r['is_completed_today'] ? '✓' : '' ?>
                                    </button>

                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-weight: 500; font-size: 0.92rem; color: var(--text-primary); <?= $r['is_completed_today'] ? 'text-decoration: line-through; color: var(--text-muted);' : '' ?> word-break: break-word;">
                                            <?= escape($r['title']) ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">
                                            ⏱ <?= (int)$r['estimated_minutes'] ?> mins
                                        </div>
                                    </div>
                                </div>

                                <!-- Actions: Edit & Delete -->
                                <div style="display: flex; align-items: center; gap: 4px; flex-shrink: 0;">
                                    <button type="button"
                                            class="btn-icon edit-routine-btn"
                                            data-routine-id="<?= (int)$r['id'] ?>"
                                            data-title="<?= htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-time-block="<?= htmlspecialchars($r['time_block'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-minutes="<?= (int)$r['estimated_minutes'] ?>"
                                            title="Edit Routine Step">
                                        <?= renderIcon('edit') ?>
                                    </button>

                                    <form method="POST" action="routine-action.php" onsubmit="return confirm('Delete this routine step?');" style="margin: 0;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="routine_id" value="<?= (int)$r['id'] ?>">
                                        <button type="submit" class="btn-icon" style="color: var(--accent-danger);" title="Delete Step">
                                            <?= renderIcon('trash') ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Create Routine Step Modal -->
<div id="createRoutineModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="createRoutineTitle">
    <div class="modal-dialog">
        <form method="POST" action="routine-action.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">

            <div class="modal-header">
                <h3 id="createRoutineTitle" class="card-title"><?= renderIcon('clock') ?> Add Routine Step</h3>
                <button type="button" class="btn-icon" data-close-modal="createRoutineModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="r_title">Step Title *</label>
                    <input type="text" id="r_title" name="title" class="form-control" placeholder="e.g. 500ml water, review daily priorities, 10-min stretch" required>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label" for="r_block">Time Block</label>
                        <select id="r_block" name="time_block" class="form-select">
                            <option value="morning">Morning</option>
                            <option value="afternoon">Afternoon</option>
                            <option value="evening">Evening</option>
                            <option value="night">Night</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="r_min">Estimated Minutes</label>
                        <input type="number" id="r_min" name="estimated_minutes" class="form-control" value="15" min="1" max="180">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="createRoutineModal">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Step</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Routine Step Modal -->
<div id="editRoutineModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="editRoutineTitle">
    <div class="modal-dialog">
        <form method="POST" action="routine-action.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" id="edit_routine_id" name="routine_id" value="">

            <div class="modal-header">
                <h3 id="editRoutineTitle" class="card-title"><?= renderIcon('edit') ?> Edit Routine Step</h3>
                <button type="button" class="btn-icon" data-close-modal="editRoutineModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="edit_routine_title">Step Title *</label>
                    <input type="text" id="edit_routine_title" name="title" class="form-control" required>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label" for="edit_routine_block">Time Block</label>
                        <select id="edit_routine_block" name="time_block" class="form-select">
                            <option value="morning">Morning</option>
                            <option value="afternoon">Afternoon</option>
                            <option value="evening">Evening</option>
                            <option value="night">Night</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_routine_min">Estimated Minutes</label>
                        <input type="number" id="edit_routine_min" name="estimated_minutes" class="form-control" min="1" max="180">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="editRoutineModal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Step</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
