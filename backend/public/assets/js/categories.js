/* ── Categories (admin): the Categories page, plus the category selects in the product form and edit window ──
   Loaded after admin.js, which runs its own init() first, so this file loads its list itself at the end.
   Uses from admin.js: apiFetch, escapeHtml, escapeAttr, toast, toastError, products, refreshAll,
   setSelectValue, editingId (the product open in the edit window). */

let categories = [];              // [{id, slug, name, icon, isActive, productCount}] from /admin/categories, in creation order
let editingCategoryId = null;     // the Categories-table row that is currently in edit mode (name + icon)

function loadCategories() {
  return apiFetch('/admin/categories')
    .then(data => { categories = data; renderCategorySelects(); renderCategoriesTable(); })
    .catch(() => toast('Failed to load categories.'));
}

// Called by showView('categories'): show what we have, then reload so product counts are current.
function initCategories() {
  editingCategoryId = null;
  renderCategoriesTable();
  loadCategories();
}

// Rebuild both category selects from `categories`, keeping whatever is currently selected.
// Only the Add Product form's select ends with "+ Add new category…".
function renderCategorySelects() {
  // A product can only join a category that is ON. The edit window also keeps the product's own category
  // when that category is OFF (labelled "(off)"), because saving sends the category back and must keep working.
  const onOptions = categories.filter(c => c.isActive)
    .map(c => `<option value="${escapeAttr(c.slug)}">${escapeHtml(c.name)}</option>`).join('');
  [['f-category', true], ['m-category', false]].forEach(([id, withNew]) => {
    const select = document.getElementById(id);
    if (!select) return;
    let options = onOptions;
    if (id === 'm-category' && editingId !== null) {
      const own = products.find(x => x.id === editingId);
      const ownCategory = own && categories.find(c => c.slug === own.category);
      if (ownCategory && !ownCategory.isActive) {
        options += `<option value="${escapeAttr(ownCategory.slug)}">${escapeHtml(ownCategory.name)} (off)</option>`;
      }
    }
    const current = select.value;
    select.innerHTML = `<option value="">Select category…</option>${options}`
      + (withNew ? '<option value="__new__">+ Add new category…</option>' : '');
    if ([...select.options].some(o => o.value === current)) select.value = current;
  });
}

function onCategoryChange(select) {
  const adding = select.value === '__new__';
  document.getElementById('new-category-box').classList.toggle('hidden', !adding);
  if (adding) document.getElementById('new-category-name').focus();
}

function cancelNewCategory() {
  const select = document.getElementById('f-category');
  if (select.value === '__new__') select.value = '';
  document.getElementById('new-category-name').value = '';
  document.getElementById('new-category-box').classList.add('hidden');
}

// Inline "+ Add new category…" in the product form: name only (the default icon 📦; change it on the Categories page).
function saveNewCategory() {
  const name = document.getElementById('new-category-name').value.trim();
  if (!name) { toast('Type the new category name first.'); return; }

  apiFetch('/admin/categories', { method: 'POST', body: JSON.stringify({ name }) })
    .then(created => {
      categories.push(created);
      renderCategorySelects();
      renderCategoriesTable();
      document.getElementById('f-category').value = created.slug;
      cancelNewCategory();
      toast('Category added!');
    })
    .catch(e => toastError(e, 'Failed to add category.'));
}

// The switch carries the words On/Off as well as its position, so it never relies on colour alone.
// It reuses the Brands page's switch styles (.brand-switch).
function categorySwitchHtml(c) {
  const on = c.isActive;
  return `<button type="button" role="switch" aria-checked="${on}" aria-label="${escapeAttr(c.name)} is shown in the shop" onclick="toggleCategory(${c.id})"
      class="brand-switch ${on ? 'is-on' : 'is-off'} inline-flex items-center gap-2 rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
      <span class="brand-switch-track"><span class="brand-switch-knob"></span></span>
      <span class="text-xs font-semibold text-gray-700 dark:text-white">${on ? 'On' : 'Off'}</span>
    </button>`;
}

function renderCategoriesTable() {
  const tbody = document.getElementById('categories-tbody');
  const empty = document.getElementById('categories-empty');
  if (!tbody) return;

  empty.classList.toggle('hidden', categories.length > 0);
  const btn = 'px-2.5 py-1 rounded-lg text-xs font-semibold transition-colors';
  const soft = `${btn} bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-neutral-600 dark:hover:bg-neutral-500 dark:text-white`;
  const inputClass = 'form-inp bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5 text-sm';
  tbody.innerHTML = categories.map(c => {
    const editing = c.id === editingCategoryId;
    const iconCell = editing
      ? `<input id="category-edit-icon" type="text" maxlength="32" value="${escapeAttr(c.icon)}" aria-label="Icon"
           onkeydown="if (event.key === 'Enter') saveEditCategory(${c.id}); if (event.key === 'Escape') cancelEditCategory()"
           class="${inputClass} w-20 text-center text-lg"/>`
      : `<span class="text-2xl leading-none" aria-hidden="true">${escapeHtml(c.icon)}</span>`;
    const nameCell = editing
      ? `<input id="category-edit-name" type="text" maxlength="50" value="${escapeAttr(c.name)}" aria-label="Category name"
           onkeydown="if (event.key === 'Enter') saveEditCategory(${c.id}); if (event.key === 'Escape') cancelEditCategory()"
           class="${inputClass} w-full max-w-xs"/>`
      : escapeHtml(c.name);
    const actions = editing
      ? `<button onclick="saveEditCategory(${c.id})" class="${btn} bg-indigo-600 hover:bg-indigo-700 text-white">Save</button>
         <button onclick="cancelEditCategory()" class="${soft}">Cancel</button>`
      : `<button onclick="startEditCategory(${c.id})" class="${soft}">Edit</button>
         <button onclick="deleteCategory(${c.id})" class="${btn} bg-red-50 hover:bg-red-100 text-red-700 dark:text-red-300">Delete</button>`;
    // An OFF category reads as muted text (not a faded row: fading would drop the contrast below AA).
    const nameClass = c.isActive ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-300';
    return `
      <tr class="border-t border-gray-100 dark:border-neutral-600">
        <td class="px-5 py-3">${iconCell}</td>
        <td class="px-3 py-3.5 text-sm font-medium ${nameClass}">${nameCell}${c.isActive ? '' : ' <span class="ml-1 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 dark:bg-neutral-600 dark:text-white">Off</span>'}</td>
        <td class="px-3 py-3.5 text-xs font-mono text-gray-600 dark:text-gray-300">${escapeHtml(c.slug)}</td>
        <td class="px-3 py-3.5 text-sm text-gray-500 dark:text-white">${c.productCount}</td>
        <td class="px-3 py-3.5">${categorySwitchHtml(c)}</td>
        <td class="px-3 py-3.5"><div class="flex flex-wrap gap-2">${actions}</div></td>
      </tr>`;
  }).join('');

  if (editingCategoryId !== null) {
    const input = document.getElementById('category-edit-name');
    if (input) { input.focus(); input.select(); }
  }
}

function addCategory() {
  const nameInput = document.getElementById('category-add-name');
  const iconInput = document.getElementById('category-add-icon');
  const name = nameInput.value.trim();
  if (!name) { toast('Type a category name.'); return; }

  apiFetch('/admin/categories', { method: 'POST', body: JSON.stringify({ name, icon: iconInput.value.trim() }) })
    .then(created => {
      categories.push(created);
      nameInput.value = '';
      iconInput.value = '';
      renderCategoriesTable();
      renderCategorySelects();
      toast('Category added!');
    })
    .catch(e => toastError(e, 'Failed to add category.'));
}

function startEditCategory(id) {
  editingCategoryId = id;
  renderCategoriesTable();
}

function cancelEditCategory() {
  editingCategoryId = null;
  renderCategoriesTable();
}

// Edit changes the name and the icon together (the code never changes, so addresses and products keep working).
function saveEditCategory(id) {
  const name = document.getElementById('category-edit-name').value.trim();
  const icon = document.getElementById('category-edit-icon').value.trim();
  if (!name) { toast('Type a category name.'); return; }

  apiFetch(`/admin/categories/${id}`, { method: 'PUT', body: JSON.stringify({ name, icon }) })
    .then(updated => {
      const c = categories.find(x => x.id === id);
      if (c) Object.assign(c, updated);
      editingCategoryId = null;
      // Products carry the category's name, so the product table must show the new one too.
      products.forEach(p => { if (p.category === updated.slug) p.categoryName = updated.name; });
      renderCategoriesTable();
      renderCategorySelects();
      refreshAll();
      toast('Category updated!');
    })
    .catch(e => toastError(e, 'Failed to update category.'));
}

function toggleCategory(id) {
  const c = categories.find(x => x.id === id);
  if (!c) return;

  apiFetch(`/admin/categories/${id}`, { method: 'PUT', body: JSON.stringify({ is_active: !c.isActive }) })
    .then(updated => {
      Object.assign(c, updated);
      products.forEach(p => { if (p.category === updated.slug) p.categoryActive = updated.isActive; });
      renderCategoriesTable();
      renderCategorySelects();
      refreshAll();
      const n = updated.productCount;
      toast(updated.isActive
        ? `${escapeHtml(updated.name)} is ON again.`
        : `${escapeHtml(updated.name)} is OFF. Its ${n} ${n === 1 ? 'product is' : 'products are'} hidden from the shop.`);
    })
    .catch(e => toastError(e, 'Failed to change the category.'));
}

function deleteCategory(id) {
  apiFetch(`/admin/categories/${id}`, { method: 'DELETE' })
    .then(() => {
      categories = categories.filter(c => c.id !== id);
      renderCategoriesTable();
      renderCategorySelects();
      toast('Category deleted.');
    })
    .catch(e => toastError(e, 'Failed to delete category.'));
}

loadCategories();
