<?php
/**
 * Shared Application Footer
 */
$currentUser = getCurrentUser();
$flashes = getFlashes();
?>
<?php if ($currentUser): ?>
        </main><!-- /.app-content -->
    </div><!-- /.main-wrapper -->
</div><!-- /.app-layout -->

<!-- Universal Quick Add Modal -->
<div id="quickAddModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="quickAddModalTitle">
    <div class="modal-dialog">
        <div class="modal-header">
            <h2 id="quickAddModalTitle" class="card-title"><?= renderIcon('plus') ?> Quick Create</h2>
            <button type="button" class="btn-icon" data-close-modal="quickAddModal" aria-label="Close modal">
                <?= renderIcon('x') ?>
            </button>
        </div>
        <div class="modal-body">
            <p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 20px;">
                Choose what you would like to quickly add to your workspace:
            </p>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                <a href="<?= url('todos.php?action=new') ?>" class="card" style="padding: 16px; text-decoration: none; display: flex; align-items: center; gap: 12px; transition: transform 0.2s;">
                    <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(59, 130, 246, 0.15); color: #3b82f6; display: flex; align-items: center; justify-content: center;">
                        <?= renderIcon('check-square') ?>
                    </div>
                    <div>
                        <div style="font-weight: 700; color: var(--text-primary); font-size: 0.95rem;">New Task</div>
                        <div style="font-size: 0.78rem; color: var(--text-muted);">To-do item with due date</div>
                    </div>
                </a>

                <a href="<?= url('habits.php?action=new') ?>" class="card" style="padding: 16px; text-decoration: none; display: flex; align-items: center; gap: 12px; transition: transform 0.2s;">
                    <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(16, 185, 129, 0.15); color: #10b981; display: flex; align-items: center; justify-content: center;">
                        <?= renderIcon('flame') ?>
                    </div>
                    <div>
                        <div style="font-weight: 700; color: var(--text-primary); font-size: 0.95rem;">New Habit</div>
                        <div style="font-size: 0.78rem; color: var(--text-muted);">Daily tracking & streaks</div>
                    </div>
                </a>

                <a href="<?= url('routine.php?action=new') ?>" class="card" style="padding: 16px; text-decoration: none; display: flex; align-items: center; gap: 12px; transition: transform 0.2s;">
                    <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: flex; align-items: center; justify-content: center;">
                        <?= renderIcon('clock') ?>
                    </div>
                    <div>
                        <div style="font-weight: 700; color: var(--text-primary); font-size: 0.95rem;">New Routine</div>
                        <div style="font-size: 0.78rem; color: var(--text-muted);">Morning/evening step</div>
                    </div>
                </a>

                <a href="<?= url('journal.php?action=new') ?>" class="card" style="padding: 16px; text-decoration: none; display: flex; align-items: center; gap: 12px; transition: transform 0.2s;">
                    <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(139, 92, 246, 0.15); color: #8b5cf6; display: flex; align-items: center; justify-content: center;">
                        <?= renderIcon('book') ?>
                    </div>
                    <div>
                        <div style="font-weight: 700; color: var(--text-primary); font-size: 0.95rem;">Journal Entry</div>
                        <div style="font-size: 0.78rem; color: var(--text-muted);">Reflections & mood</div>
                    </div>
                </a>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-close-modal="quickAddModal">Cancel</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Flash Message Carrier -->
<div id="server-flash-messages" data-flashes="<?= escape(json_encode($flashes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>" style="display:none;"></div>

<!-- Core JS -->
<script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
