<?php
/**
 * Daily Journal Page
 *
 * Mindful reflections, mood tracking, categories, tags, private notes,
 * and full CRUD operations (Create, Read, Update, Delete).
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/journal.php';
require_once __DIR__ . '/includes/categories.php';

$user = requireAuth();
$userId = (int) $user['id'];

$moodFilter = $_GET['mood'] ?? '';
$categoryFilter = $_GET['category_id'] ?? '';
$searchFilter = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$filters = [
    'mood'        => $moodFilter,
    'category_id' => $categoryFilter,
    'search'      => $searchFilter,
];

$journalData = getJournalEntries($userId, $filters, $limit, $offset);
$entries = $journalData['entries'];
$total = $journalData['total'];
$totalPages = ceil($total / $limit);

$categories = getCategories($userId);

$pageTitle = 'Daily Journal';
$pageSubtitle = 'A mindful space for daily reflections, lessons learned, and emotional well-being.';
$activeNav = 'journal';

$moodIcons = [
    'great'   => ['icon' => '🌟', 'label' => 'Great', 'color' => '#10b981'],
    'good'    => ['icon' => '😊', 'label' => 'Good', 'color' => '#3b82f6'],
    'neutral' => ['icon' => '😐', 'label' => 'Neutral', 'color' => '#f59e0b'],
    'bad'     => ['icon' => '😔', 'label' => 'Tough', 'color' => '#ec4899'],
    'awful'   => ['icon' => '🌧️', 'label' => 'Struggling', 'color' => '#ef4444'],
];

require_once __DIR__ . '/includes/header.php';
?>

<!-- 1. Dedicated Reflection Composer Card -->
<div class="quick-add-card" style="padding: 22px;">
    <form method="POST" action="journal-action.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="mood" value="good">

        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="color: var(--accent-primary); font-size: 1.2rem;"><?= renderIcon('book') ?></span>
                <span style="font-weight: 700; font-size: 1.05rem; color: var(--text-primary);">Write Today's Reflection</span>
            </div>
            <div style="font-size: 0.82rem; color: var(--text-muted);">
                <?= date('l, F j, Y') ?>
            </div>
        </div>

        <div class="form-group" style="margin-bottom: 14px;">
            <input type="text" name="title" class="form-control" placeholder="What is on your mind today? (Headline or core theme...)" required style="font-size: 1.02rem; padding: 12px 16px;">
        </div>

        <div class="form-group" style="margin-bottom: 14px;">
            <textarea name="content" class="form-textarea" style="min-height: 120px; font-size: 0.95rem; line-height: 1.6;" placeholder="What went well today? What challenges arose? What are you grateful for?" required></textarea>
        </div>

        <div style="display: flex; flex-direction: column; gap: 14px; margin-top: 14px;">
            <!-- Mood Selector -->
            <div>
                <label class="form-label" style="margin-bottom: 6px;">How are you feeling?</label>
                <div class="mood-selector">
                    <?php foreach ($moodIcons as $k => $m): ?>
                        <button type="button" class="mood-btn <?= $k === 'good' ? 'selected' : '' ?>" data-mood="<?= $k ?>">
                            <span><?= $m['icon'] ?></span> <?= $m['label'] ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Details: Date, Category, Tags, and Save Button -->
            <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; padding-top: 10px; border-top: 1px solid var(--border-subtle);">
                <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center; flex: 1;">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Date:</span>
                        <input type="date" name="entry_date" class="form-control" value="<?= date('Y-m-d') ?>" style="padding: 6px 10px; font-size: 0.84rem; width: auto;">
                    </div>

                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Category:</span>
                        <select name="category_id" class="form-select" style="padding: 6px 10px; font-size: 0.84rem; width: auto;">
                            <option value="">Personal</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>"><?= escape($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display: flex; align-items: center; gap: 6px; flex: 1; min-width: 200px;">
                        <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Tags:</span>
                        <input type="text" name="tags" class="form-control" placeholder="mindset, gratitude, wins (comma separated)" style="padding: 6px 10px; font-size: 0.84rem;">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="padding: 10px 24px; font-weight: 700; flex-shrink: 0;">
                    <?= renderIcon('plus') ?> Save Reflection
                </button>
            </div>
        </div>
    </form>
</div>

<!-- 2. Search & Filter Bar -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 14px 20px;">
        <form method="GET" action="journal.php" style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px;">
            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px; flex: 1;">
                <div style="position: relative; min-width: 220px; flex: 1;">
                    <input type="text" name="search" class="form-control" placeholder="Search reflections or tags..." value="<?= escape($searchFilter) ?>" style="padding-left: 36px; padding-top: 8px; padding-bottom: 8px;">
                    <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none;">
                        <?= renderIcon('search') ?>
                    </span>
                </div>

                <select name="mood" class="form-select" style="width: auto; padding: 8px 12px;" onchange="this.form.submit()">
                    <option value="">All Moods</option>
                    <?php foreach ($moodIcons as $k => $m): ?>
                        <option value="<?= $k ?>" <?= $moodFilter === $k ? 'selected' : '' ?>>
                            <?= $m['icon'] ?> <?= $m['label'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="category_id" class="form-select" style="width: auto; padding: 8px 12px;" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>>
                            <?= escape($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn btn-secondary btn-sm"><?= renderIcon('filter') ?> Filter</button>
                <?php if (!empty($searchFilter) || !empty($moodFilter) || !empty($categoryFilter)): ?>
                    <a href="journal.php" class="btn btn-ghost btn-sm">Clear</a>
                <?php endif; ?>
            </div>

            <button type="button" class="btn btn-primary btn-sm" data-open-modal="createJournalModal">
                <?= renderIcon('plus') ?> New Entry Modal
            </button>
        </form>
    </div>
</div>

<!-- 3. Entries Feed -->
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
    <h2 style="font-size: 1.2rem; font-weight: 700; color: var(--text-primary);">Past Reflections (<?= $total ?>)</h2>
</div>

<?php if (empty($entries)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-state-icon"><?= renderIcon('book') ?></div>
                <div class="empty-state-title">No journal entries found</div>
                <p class="empty-state-desc">Capture your thoughts, daily learnings, and mood above to build a personalized timeline of your mental journey.</p>
            </div>
        </div>
    </div>
<?php else: ?>
    <div style="display: flex; flex-direction: column; gap: 16px;">
        <?php foreach ($entries as $e): ?>
            <?php
            $moodMeta = $moodIcons[$e['mood']] ?? $moodIcons['neutral'];
            ?>
            <div class="card" id="journal-entry-<?= (int)$e['id'] ?>">
                <div class="card-header" style="padding: 16px 20px;">
                    <div style="display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0;">
                        <span style="font-size: 1.6rem; line-height: 1;" title="Mood: <?= $moodMeta['label'] ?>"><?= $moodMeta['icon'] ?></span>
                        <div style="flex: 1; min-width: 0;">
                            <h3 class="card-title" style="font-size: 1.05rem; word-break: break-word;"><?= escape($e['title']) ?></h3>
                            <div style="font-size: 0.82rem; color: var(--text-muted); display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 2px;">
                                <span><?= formatDate($e['entry_date'], 'l, F j, Y') ?></span>
                                <span class="badge" style="background: rgba(255,255,255,0.06); color: <?= $moodMeta['color'] ?>; font-size: 0.72rem;">
                                    <?= $moodMeta['label'] ?>
                                </span>
                                <?php if (!empty($e['category_name'])): ?>
                                    <span>&bull;</span>
                                    <span style="color: <?= escape($e['category_color'] ?? '#fff') ?>; font-weight: 600;">
                                        <?= escape($e['category_name']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Actions: Edit & Delete -->
                    <div style="display: flex; align-items: center; gap: 4px; flex-shrink: 0;">
                        <button type="button"
                                class="btn-icon edit-journal-btn"
                                data-entry-id="<?= (int)$e['id'] ?>"
                                data-title="<?= htmlspecialchars($e['title'], ENT_QUOTES, 'UTF-8') ?>"
                                data-content="<?= htmlspecialchars($e['content'], ENT_QUOTES, 'UTF-8') ?>"
                                data-mood="<?= htmlspecialchars($e['mood'] ?? 'neutral', ENT_QUOTES, 'UTF-8') ?>"
                                data-entry-date="<?= htmlspecialchars($e['entry_date'], ENT_QUOTES, 'UTF-8') ?>"
                                data-tags="<?= htmlspecialchars($e['tags'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                data-category-id="<?= (int)($e['category_id'] ?? 0) ?>"
                                title="Edit Journal Entry">
                            <?= renderIcon('edit') ?>
                        </button>

                        <form method="POST" action="journal-action.php" onsubmit="return confirm('Are you sure you want to delete this journal reflection?');" style="margin: 0;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="entry_id" value="<?= (int)$e['id'] ?>">
                            <button type="submit" class="btn-icon" style="color: var(--accent-danger);" title="Delete Entry">
                                <?= renderIcon('trash') ?>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card-body" style="padding: 16px 20px;">
                    <div style="font-size: 0.95rem; color: var(--text-secondary); line-height: 1.7; white-space: pre-wrap; word-break: break-word;">
                        <?= escape($e['content']) ?>
                    </div>

                    <?php if (!empty($e['tags'])): ?>
                        <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 16px; padding-top: 12px; border-top: 1px solid var(--border-subtle);">
                            <?php foreach (explode(',', $e['tags']) as $tag): ?>
                                <?php if (trim($tag)): ?>
                                    <a href="journal.php?search=<?= urlencode(trim($tag)) ?>" class="badge" style="background: rgba(255,255,255,0.06); color: var(--text-muted); text-decoration: none;">
                                        #<?= escape(trim($tag)) ?>
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div style="display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 24px;">
            <?php if ($page > 1): ?>
                <a href="journal.php?<?= http_build_query(array_merge($filters, ['page' => $page - 1])) ?>" class="btn btn-secondary btn-sm">&larr; Prev</a>
            <?php endif; ?>

            <span style="font-size: 0.85rem; color: var(--text-muted); padding: 0 8px;">
                Page <?= $page ?> of <?= $totalPages ?> (Total: <?= $total ?>)
            </span>

            <?php if ($page < $totalPages): ?>
                <a href="journal.php?<?= http_build_query(array_merge($filters, ['page' => $page + 1])) ?>" class="btn btn-secondary btn-sm">Next &rarr;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Create Journal Modal -->
<div id="createJournalModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="createJournalTitle">
    <div class="modal-dialog" style="max-width: 620px;">
        <form method="POST" action="journal-action.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="mood" value="good">

            <div class="modal-header">
                <h3 id="createJournalTitle" class="card-title"><?= renderIcon('book') ?> New Journal Reflection</h3>
                <button type="button" class="btn-icon" data-close-modal="createJournalModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="j_modal_title">Title *</label>
                    <input type="text" id="j_modal_title" name="title" class="form-control" placeholder="Today's headline or focus..." required>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label" for="j_modal_date">Date</label>
                        <input type="date" id="j_modal_date" name="entry_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="j_modal_cat">Category</label>
                        <select id="j_modal_cat" name="category_id" class="form-select">
                            <option value="">Personal</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>"><?= escape($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Mood</label>
                    <div class="mood-selector">
                        <?php foreach ($moodIcons as $k => $m): ?>
                            <button type="button" class="mood-btn <?= $k === 'good' ? 'selected' : '' ?>" data-mood="<?= $k ?>">
                                <span><?= $m['icon'] ?></span> <?= $m['label'] ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="j_modal_content">Reflection & Thoughts *</label>
                    <textarea id="j_modal_content" name="content" class="form-textarea" style="min-height: 150px;" placeholder="What went well today? What challenges arose? What are you grateful for?" required></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="j_modal_tags">Tags (comma separated)</label>
                    <input type="text" id="j_modal_tags" name="tags" class="form-control" placeholder="mindset, gratitude, wins">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="createJournalModal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Entry</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Journal Modal -->
<div id="editJournalModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="editJournalTitle">
    <div class="modal-dialog" style="max-width: 620px;">
        <form method="POST" action="journal-action.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" id="edit_journal_id" name="entry_id" value="">
            <input type="hidden" id="edit_journal_mood" name="mood" value="neutral">

            <div class="modal-header">
                <h3 id="editJournalTitle" class="card-title"><?= renderIcon('edit') ?> Edit Reflection</h3>
                <button type="button" class="btn-icon" data-close-modal="editJournalModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="edit_journal_title">Title *</label>
                    <input type="text" id="edit_journal_title" name="title" class="form-control" required>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label" for="edit_journal_date">Date</label>
                        <input type="date" id="edit_journal_date" name="entry_date" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_journal_cat">Category</label>
                        <select id="edit_journal_cat" name="category_id" class="form-select">
                            <option value="">Personal</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>"><?= escape($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Mood</label>
                    <div class="mood-selector">
                        <?php foreach ($moodIcons as $k => $m): ?>
                            <button type="button" class="mood-btn" data-mood="<?= $k ?>">
                                <span><?= $m['icon'] ?></span> <?= $m['label'] ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_journal_content">Reflection & Thoughts *</label>
                    <textarea id="edit_journal_content" name="content" class="form-textarea" style="min-height: 150px;" required></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_journal_tags">Tags (comma separated)</label>
                    <input type="text" id="edit_journal_tags" name="tags" class="form-control" placeholder="mindset, gratitude, wins">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal="editJournalModal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Entry</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
