/**
 * Zenith Productivity - Interactive Application Script
 *
 * Handles theme toggling, sidebar collapse, mobile drawer, accessible modals,
 * data-driven edit dialogs, mood selectors, and server-confirmed AJAX interactions.
 */

// Global modal functions available immediately
window.openModal = function (modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    const firstInput = modal.querySelector('input:not([type="hidden"]), select, textarea');
    if (firstInput) {
      setTimeout(() => firstInput.focus(), 50);
    }
  } else {
    console.warn('Modal not found:', modalId);
  }
};

window.closeModal = function (modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove('active');
    document.body.style.overflow = '';
  }
};

(function () {
  'use strict';

  // --- Theme Management ---
  const THEME_STORAGE_KEY = 'zenith_theme';

  function initTheme() {
    const savedTheme = localStorage.getItem(THEME_STORAGE_KEY) || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);
  }

  function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem(THEME_STORAGE_KEY, newTheme);
  }

  // --- Sidebar & Mobile Drawer ---
  const SIDEBAR_STORAGE_KEY = 'zenith_sidebar_collapsed';

  function initSidebar() {
    const isCollapsed = localStorage.getItem(SIDEBAR_STORAGE_KEY) === 'true';
    if (isCollapsed && window.innerWidth > 768) {
      document.body.classList.add('sidebar-collapsed');
    }

    const desktopToggle = document.querySelector('.desktop-sidebar-toggle');
    if (desktopToggle) {
      desktopToggle.addEventListener('click', () => {
        document.body.classList.toggle('sidebar-collapsed');
        const collapsed = document.body.classList.contains('sidebar-collapsed');
        localStorage.setItem(SIDEBAR_STORAGE_KEY, collapsed);
      });
    }

    const mobileToggle = document.querySelector('.mobile-menu-toggle');
    const sidebar = document.querySelector('.app-sidebar');
    const overlay = document.querySelector('.mobile-drawer-overlay');

    function openMobileDrawer() {
      if (sidebar) sidebar.classList.add('mobile-open');
      if (overlay) overlay.classList.add('active');
      document.body.style.overflow = 'hidden';
    }

    function closeMobileDrawer() {
      if (sidebar) sidebar.classList.remove('mobile-open');
      if (overlay) overlay.classList.remove('active');
      document.body.style.overflow = '';
    }

    if (mobileToggle) mobileToggle.addEventListener('click', openMobileDrawer);
    if (overlay) overlay.addEventListener('click', closeMobileDrawer);

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeMobileDrawer();
        document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
        document.body.style.overflow = '';
      }
    });
  }

  // --- Toast Notification Engine ---
  window.showToast = function (message, type = 'info', duration = 4000) {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      container.setAttribute('aria-live', 'polite');
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.setAttribute('role', 'status');

    let iconSvg = '●';
    if (type === 'success') iconSvg = '✓';
    else if (type === 'danger' || type === 'error') iconSvg = '✕';
    else if (type === 'warning') iconSvg = '⚠';

    toast.innerHTML = `
      <span style="font-weight: 800; color: inherit;">${iconSvg}</span>
      <div class="toast-msg">${escapeHtml(message)}</div>
      <button type="button" class="toast-close" aria-label="Close notification">&times;</button>
    `;

    container.appendChild(toast);

    const closeBtn = toast.querySelector('.toast-close');
    const removeToast = () => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateX(100%)';
      setTimeout(() => {
        if (toast.parentNode) toast.parentNode.removeChild(toast);
      }, 250);
    };

    closeBtn.addEventListener('click', removeToast);
    if (duration > 0) {
      setTimeout(removeToast, duration);
    }
  };

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.getAttribute('content');
    const input = document.querySelector('input[name="csrf_token"]');
    return input ? input.value : '';
  }

  // --- Interactive Delegations for CRUD & Modals ---
  function initInteractiveListeners() {
    document.addEventListener('click', async (e) => {
      // 1. Open Modal Trigger
      const openBtn = e.target.closest('[data-open-modal]');
      if (openBtn) {
        e.preventDefault();
        const modalId = openBtn.getAttribute('data-open-modal');
        window.openModal(modalId);
        return;
      }

      // 2. Close Modal Trigger
      const closeBtn = e.target.closest('[data-close-modal]');
      if (closeBtn) {
        e.preventDefault();
        const modalId = closeBtn.getAttribute('data-close-modal');
        window.closeModal(modalId);
        return;
      }

      // 3. Edit Task Button (Safe Data-Attribute Population)
      const editTaskBtn = e.target.closest('.edit-task-btn');
      if (editTaskBtn) {
        e.preventDefault();
        const taskId = editTaskBtn.getAttribute('data-task-id');
        const title = editTaskBtn.getAttribute('data-title');
        const description = editTaskBtn.getAttribute('data-description') || '';
        const priority = editTaskBtn.getAttribute('data-priority') || 'medium';
        const categoryId = editTaskBtn.getAttribute('data-category-id') || '';
        const dueDate = editTaskBtn.getAttribute('data-due-date') || '';
        const dueTime = editTaskBtn.getAttribute('data-due-time') || '';

        const idInput = document.getElementById('edit_task_id');
        const titleInput = document.getElementById('edit_task_title');
        const descInput = document.getElementById('edit_task_desc');
        const prioInput = document.getElementById('edit_task_priority');
        const catInput = document.getElementById('edit_task_cat');
        const dateInput = document.getElementById('edit_task_due_date');
        const timeInput = document.getElementById('edit_task_due_time');

        if (idInput) idInput.value = taskId;
        if (titleInput) titleInput.value = title;
        if (descInput) descInput.value = description;
        if (prioInput) prioInput.value = priority;
        if (catInput) catInput.value = categoryId;
        if (dateInput) dateInput.value = dueDate;
        if (timeInput) timeInput.value = dueTime ? dueTime.substring(0, 5) : '';

        window.openModal('editTaskModal');
        return;
      }

      // 4. Edit Habit Button
      const editHabitBtn = e.target.closest('.edit-habit-btn');
      if (editHabitBtn) {
        e.preventDefault();
        const habitId = editHabitBtn.getAttribute('data-habit-id');
        const title = editHabitBtn.getAttribute('data-title');
        const description = editHabitBtn.getAttribute('data-description') || '';
        const targetFreq = editHabitBtn.getAttribute('data-frequency') || 'daily';
        const color = editHabitBtn.getAttribute('data-color') || '#22c55e';
        const categoryId = editHabitBtn.getAttribute('data-category-id') || '';

        const idInput = document.getElementById('edit_habit_id');
        const titleInput = document.getElementById('edit_habit_title');
        const descInput = document.getElementById('edit_habit_desc');
        const freqInput = document.getElementById('edit_habit_freq');
        const colorInput = document.getElementById('edit_habit_color');
        const catInput = document.getElementById('edit_habit_cat');

        if (idInput) idInput.value = habitId;
        if (titleInput) titleInput.value = title;
        if (descInput) descInput.value = description;
        if (freqInput) freqInput.value = targetFreq;
        if (colorInput) colorInput.value = color;
        if (catInput) catInput.value = categoryId;

        window.openModal('editHabitModal');
        return;
      }

      // 5. Edit Journal Button
      const editJournalBtn = e.target.closest('.edit-journal-btn');
      if (editJournalBtn) {
        e.preventDefault();
        const entryId = editJournalBtn.getAttribute('data-entry-id');
        const title = editJournalBtn.getAttribute('data-title');
        const content = editJournalBtn.getAttribute('data-content') || '';
        const mood = editJournalBtn.getAttribute('data-mood') || 'neutral';
        const entryDate = editJournalBtn.getAttribute('data-entry-date') || '';
        const tags = editJournalBtn.getAttribute('data-tags') || '';
        const categoryId = editJournalBtn.getAttribute('data-category-id') || '';

        const idInput = document.getElementById('edit_journal_id');
        const titleInput = document.getElementById('edit_journal_title');
        const contentInput = document.getElementById('edit_journal_content');
        const moodInput = document.getElementById('edit_journal_mood');
        const dateInput = document.getElementById('edit_journal_date');
        const tagsInput = document.getElementById('edit_journal_tags');
        const catInput = document.getElementById('edit_journal_cat');

        if (idInput) idInput.value = entryId;
        if (titleInput) titleInput.value = title;
        if (contentInput) contentInput.value = content;
        if (moodInput) moodInput.value = mood;
        if (dateInput) dateInput.value = entryDate;
        if (tagsInput) tagsInput.value = tags;
        if (catInput) catInput.value = categoryId;

        // Select the right mood button in edit modal
        document.querySelectorAll('#editJournalModal .mood-btn').forEach(btn => {
          btn.classList.toggle('selected', btn.getAttribute('data-mood') === mood);
        });

        window.openModal('editJournalModal');
        return;
      }

      // 5b. Edit Routine Step Button
      const editRoutineBtn = e.target.closest('.edit-routine-btn');
      if (editRoutineBtn) {
        e.preventDefault();
        const routineId = editRoutineBtn.getAttribute('data-routine-id');
        const title = editRoutineBtn.getAttribute('data-title');
        const timeBlock = editRoutineBtn.getAttribute('data-time-block') || 'morning';
        const minutes = editRoutineBtn.getAttribute('data-minutes') || '15';

        const idInput = document.getElementById('edit_routine_id');
        const titleInput = document.getElementById('edit_routine_title');
        const blockInput = document.getElementById('edit_routine_block');
        const minInput = document.getElementById('edit_routine_min');

        if (idInput) idInput.value = routineId;
        if (titleInput) titleInput.value = title;
        if (blockInput) blockInput.value = timeBlock;
        if (minInput) minInput.value = minutes;

        window.openModal('editRoutineModal');
        return;
      }

      // 5c. Edit Category Button
      const editCategoryBtn = e.target.closest('.edit-category-btn');
      if (editCategoryBtn) {
        e.preventDefault();
        const categoryId = editCategoryBtn.getAttribute('data-category-id');
        const name = editCategoryBtn.getAttribute('data-name');
        const color = editCategoryBtn.getAttribute('data-color') || '#6366f1';

        const idInput = document.getElementById('edit_category_id');
        const nameInput = document.getElementById('edit_category_name');
        const colorInput = document.getElementById('edit_category_color');

        if (idInput) idInput.value = categoryId;
        if (nameInput) nameInput.value = name;
        if (colorInput) colorInput.value = color;

        window.openModal('editCategoryModal');
        return;
      }

      // 6. Mood Button Selector (in new/edit journal forms)
      const moodBtn = e.target.closest('.mood-btn');
      if (moodBtn) {
        e.preventDefault();
        const container = moodBtn.closest('.mood-selector');
        if (container) {
          container.querySelectorAll('.mood-btn').forEach(b => b.classList.remove('selected'));
          moodBtn.classList.add('selected');
          const moodValue = moodBtn.getAttribute('data-mood');
          const targetInput = container.querySelector('input[name="mood"]') ||
                              container.closest('form').querySelector('input[name="mood"]');
          if (targetInput) targetInput.value = moodValue;
        }
        return;
      }

      // 7. Interactive Task Completion Toggle (Server-Confirmed)
      const taskToggleBtn = e.target.closest('.task-checkbox-btn');
      if (taskToggleBtn) {
        e.preventDefault();
        const taskId = taskToggleBtn.getAttribute('data-task-id');
        const taskItem = taskToggleBtn.closest('.task-item');
        const isCurrentlyCompleted = taskToggleBtn.classList.contains('checked');
        const csrf = getCsrfToken();

        taskToggleBtn.disabled = true;

        try {
          const formData = new FormData();
          formData.append('csrf_token', csrf);
          formData.append('task_id', taskId);
          formData.append('action', isCurrentlyCompleted ? 'reopen' : 'complete');

          const response = await fetch('tasks-action.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
          });

          const data = await response.json();

          if (data.success) {
            if (data.status === 'completed') {
              taskToggleBtn.classList.add('checked');
              taskToggleBtn.innerHTML = '✓';
              if (taskItem) taskItem.classList.add('completed');
              showToast('Task completed!', 'success');
            } else {
              taskToggleBtn.classList.remove('checked');
              taskToggleBtn.innerHTML = '';
              if (taskItem) taskItem.classList.remove('completed');
              showToast('Task reopened.', 'info');
            }
          } else {
            showToast(data.error || 'Failed to update task.', 'danger');
          }
        } catch (err) {
          showToast('Network error while updating task.', 'danger');
        } finally {
          taskToggleBtn.disabled = false;
        }
        return;
      }

      // 8. Interactive Habit Completion Toggle (Server-Confirmed)
      const habitBtn = e.target.closest('.habit-check-btn');
      if (habitBtn) {
        e.preventDefault();
        const habitId = habitBtn.getAttribute('data-habit-id');
        const streakSpan = document.querySelector(`.habit-streak-${habitId}`);
        const csrf = getCsrfToken();

        habitBtn.disabled = true;

        try {
          const formData = new FormData();
          formData.append('csrf_token', csrf);
          formData.append('habit_id', habitId);
          formData.append('action', 'toggle');

          const response = await fetch('habits-action.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
          });

          const data = await response.json();

          if (data.success) {
            if (data.completed) {
              habitBtn.classList.add('completed');
              habitBtn.innerHTML = '✓';
              showToast('Habit completed for today! Streak maintained.', 'success');
            } else {
              habitBtn.classList.remove('completed');
              habitBtn.innerHTML = '';
              showToast('Habit check-in removed.', 'info');
            }
            if (streakSpan && data.streak !== undefined) {
              streakSpan.textContent = `${data.streak} Day Streak`;
            }
          } else {
            showToast(data.error || 'Failed to update habit status.', 'danger');
          }
        } catch (err) {
          showToast('Network error while updating habit.', 'danger');
        } finally {
          habitBtn.disabled = false;
        }
        return;
      }

      // 9. Interactive Routine Completion Toggle
      const routineBtn = e.target.closest('.routine-check-btn');
      if (routineBtn) {
        e.preventDefault();
        const routineId = routineBtn.getAttribute('data-routine-id');
        const csrf = getCsrfToken();

        routineBtn.disabled = true;

        try {
          const formData = new FormData();
          formData.append('csrf_token', csrf);
          formData.append('routine_id', routineId);
          formData.append('action', 'toggle');

          const response = await fetch('routine-action.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
          });

          const data = await response.json();

          if (data.success) {
            if (data.completed) {
              routineBtn.classList.add('checked');
              routineBtn.innerHTML = '✓';
              showToast('Routine step completed!', 'success');
            } else {
              routineBtn.classList.remove('checked');
              routineBtn.innerHTML = '';
              showToast('Routine step unchecked.', 'info');
            }
          } else {
            showToast(data.error || 'Failed to update routine.', 'danger');
          }
        } catch (err) {
          showToast('Network error while updating routine.', 'danger');
        } finally {
          routineBtn.disabled = false;
        }
        return;
      }
    });

    // Close modal on clicking backdrop
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
          overlay.classList.remove('active');
          document.body.style.overflow = '';
        }
      });
    });
  }

  // Initialize on DOM Ready
  document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initSidebar();
    initInteractiveListeners();

    // Theme Toggle Buttons
    document.querySelectorAll('.theme-toggle-btn').forEach(btn => {
      btn.addEventListener('click', toggleTheme);
    });

    // Display any server flash messages passed as data attributes
    const flashCarrier = document.getElementById('server-flash-messages');
    if (flashCarrier) {
      try {
        const raw = flashCarrier.getAttribute('data-flashes');
        if (raw) {
          const messages = JSON.parse(raw);
          if (Array.isArray(messages)) {
            messages.forEach(f => showToast(f.message, f.type));
          }
        }
      } catch (e) {
        console.error('Failed to parse flash messages', e);
      }
    }
  });

})();
