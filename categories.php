<?php
/**
 * Categories Page
 *
 * Manage color-coded categories across tasks, habits, and journals,
 * with full CRUD (Create, Read, Update, Delete).
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/categories.php';

$user = requireAuth();
$userId = (int) $user['id'];

$categories = getCategories($userId);

$pageTitle = 'Categories';
$pageSubtitle = 'Organize your tasks, habits, and journals with color tags and taxonomies.';
$activeNav = 'categories';

require_once __DIR__ . '/includes/header.php';
?>

<!-- 1. Inline Quick Add Category Bar -->
<div class="quick-add-card">
    <form method="POST" action="categories-action.php" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">

        <div style="flex: 1; min-width: 220px;">
            <input type="text" name="name" class="form-control" placeholder="Category name (e.g. Work, Health, Personal, Learning...)" required autofocus style="padding: 10px 14px; font-size: 0.95rem;">
        </div>

        <div style="display: flex; align-items: center; gap: 6px;">
            <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Color:</span>
            <input type="color" name="color" value="#6366f1" style="width: 38px; height: 38px; border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); cursor: pointer; padding: 0; background: transparent;">
        </div>

        <button type="submit" class="btn btn-primary" style="padding: 10px 22px; font-weight: 700; flex-shrink: 0;">
            <?= renderIcon('plus') ?> Add Category
        </button>
    </form>
</div>

<!-- 2. Header -->
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
    <div>
        <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--text-primary);">Category Workspace (<?= count($categories) ?>)</h2>
        <p style="font-size: 0.85rem; color: var(--text-muted);">Use these categories across To-Do tasks, Daily Habits, and Journal reflections.</p>
    </div>
    <button type="button" class="btn btn-primary btn-sm" data-open-modal="createCategoryModal">
        <?= renderIcon('plus') ?> New Category Modal
    </button>
</div>

<!-- 3. Category Grid -->
<?php if (empty($categories)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-state-icon"><?= renderIcon('folder') ?></div>
                <div class="empty-state-title">No categories yet</div>
                <p class="empty-state-desc">Categories help you segment work, personal goals, fitness, and learning.</p>
                <button type="button" class="btn btn-primary" data-open-modal="createCategoryModal">
                    <?= renderIcon('plus') ?> Create Category
                </button>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="grid-3">
        <?php foreach ($categories as $cat): ?>
            <div class="card" id="category-card-<?= (int)$cat['id'] ?>">
                <div class="card-body" style="display: flex; align-items: center; justify-content: space-between; padding: 18px 20px;">
                    <div style="display: flex; align-items: center; gap: 14px; flex: 1; min-width: 0;">
                        <div style="width: 44px; height: 44px; border-radius: var(--radius-md); background-color: <?= escape($cat['color']) ?>22; color: <?= escape($cat['color']) ?>; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                            <?= renderIcon('folder') ?>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-weight: 700; color: var(--text-primary); font-size: 1rem; word-break: break-word;">
                                <?= escape($cat['name']) ?>
                            </div>
                            <div style="font-size: 0.78rem; color: var(--text-muted); display: flex; align-items: center; gap: 6px; margin-top: 2px;">
                                <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: <?= escape($cat['color']) ?>;"></span>
                                <code><?= escape($cat['color']) ?></code>
                            </div>
                        </div>
                    </div>

                    <!-- Actions: Edit & Delete -->
                    <div style="display: flex; align-items: center; gap: 4px; flex-shrink: 0;">
                        <button type="button"
                                class="btn-icon edit-category-btn"
                                data-category-id="<?= (int)$cat['id'] ?>"
                                data-name="<?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>"
                                data-color="<?= htmlspecialchars($cat['color'] ?? '#6366f1', ENT_QUOTES, 'UTF-8') ?>"
                                title="Edit Category">
                            <?= renderIcon('edit') ?>
                        </button>

                        <form method="POST" action="categories-action.php" onsubmit="return confirm('Delete this category? Associated tasks, habits, and journals will remain without a category.');" style="margin: 0;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="category_id" value="<?= (int)$cat['id'] ?>">
                            <button type="submit" class="btn-icon" style="color: var(--accent-danger);" title="Delete Category">
                                <?= renderIcon('trash') ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Create Category Modal -->
<div id="createCategoryModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="createCategoryTitle">
    <div class="modal-dialog">
        <form method="POST" action="categories-action.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">

            <div class="modal-header">
                <h3 id="createCategoryTitle" class="card-title"><?= renderIcon('folder') ?> New Category</h3>
                <button type="button" class="btn-icon" data-close-modal="createCategoryModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="cat_name">Category Name *</label>
                    <input type="text" id="cat_name" name="name" class="form-control" placeholder="e.g. Work, Fitness, Side Projects" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="cat_color">Color Tag</label>
                    <input type="color" id="cat_color" name="color" value="#6366f1" style="height: 42px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); width: 100%; cursor: pointer;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="createCategoryModal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Category Modal -->
<div id="editCategoryModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="editCategoryTitle">
    <div class="modal-dialog">
        <form method="POST" action="categories-action.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" id="edit_category_id" name="category_id" value="">

            <div class="modal-header">
                <h3 id="editCategoryTitle" class="card-title"><?= renderIcon('edit') ?> Edit Category</h3>
                <button type="button" class="btn-icon" data-close-modal="editCategoryModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="edit_category_name">Category Name *</label>
                    <input type="text" id="edit_category_name" name="name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_category_color">Color Tag</label>
                    <input type="color" id="edit_category_color" name="color" value="#6366f1" style="height: 42px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); width: 100%; cursor: pointer;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="editCategoryModal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Category</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
