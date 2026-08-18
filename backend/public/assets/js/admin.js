/* ── API helper (CSRF-aware fetch against the real products backend) ── */
function apiFetch(url, options = {}) {
  const token = document.querySelector('meta[name="csrf-token"]')?.content;
  return fetch(url, {
    ...options,
    headers: {
      'Accept': 'application/json',
      'X-CSRF-TOKEN': token,
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...(options.headers || {}),
    },
  }).then(async res => {
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || `Request failed (${res.status})`);
    }
    return res.status === 204 ? null : res.json();
  });
}

/* ── State ── */
let products = [];
let currentView = 'dashboard';
let tableFilter = 'all';
let searchQ = '';
let editingId = null;
let restockId = null;
let deleteId = null;

/* ── Status helpers ── */
function statusOf(p) {
  if (p.qty === 0) return 'out';
  if (p.qty <= 10) return 'low';
  return 'in';
}
function statusBadge(p) {
  const s = statusOf(p);
  if (s === 'out') return `<span class="badge-out text-[10px] font-semibold px-2 py-0.5 rounded-full">Out of Stock</span>`;
  if (s === 'low') return `<span class="badge-low text-[10px] font-semibold px-2 py-0.5 rounded-full">Low Stock</span>`;
  return `<span class="badge-in text-[10px] font-semibold px-2 py-0.5 rounded-full">In Stock</span>`;
}

/* ── Init ── */
function init() {
  apiFetch('/admin/products')
    .then(data => {
      products = data;
      refreshAll();
    })
    .catch(() => toast('Failed to load products.'));
}

function refreshAll() {
  updateStats();
  renderRecentTable();
  renderAlertsPanel();
  renderProductsTable();
  renderInventoryTable();
  updateLowBadge();
}

/* ── Stats ── */
function updateStats() {
  const total = products.length;
  const inS   = products.filter(p => statusOf(p) === 'in').length;
  const low   = products.filter(p => statusOf(p) === 'low').length;
  const out   = products.filter(p => statusOf(p) === 'out').length;
  document.getElementById('stat-total').textContent   = total;
  document.getElementById('stat-instock').textContent = inS;
  document.getElementById('stat-low').textContent     = low;
  document.getElementById('stat-out').textContent     = out;
}

function updateLowBadge() {
  const cnt = products.filter(p => statusOf(p) !== 'in').length;
  const b = document.getElementById('low-stock-badge');
  b.textContent = cnt;
  b.classList.toggle('hidden', cnt === 0);
}

/* ── Dashboard recent table ── */
function renderRecentTable() {
  const tbody = document.getElementById('recent-tbody');
  const recent = [...products].slice(-6).reverse();
  tbody.innerHTML = recent.map(p => `
    <tr class="trow border-b border-gray-50 dark:border-neutral-600 last:border-0">
      <td class="px-5 py-3 flex items-center gap-3">
        <span class="text-sm font-medium text-gray-800 dark:text-white truncate max-w-[200px]">${p.name}</span>
      </td>
      <td class="px-3 py-3 text-sm font-semibold text-gray-900 dark:text-white">$${p.price}</td>
      <td class="px-3 py-3 text-sm text-gray-600 dark:text-white">${p.qty}</td>
      <td class="px-3 py-3">${statusBadge(p)}</td>
    </tr>`).join('');
}

/* ── Alerts panel ── */
function renderAlertsPanel() {
  const panel = document.getElementById('alerts-panel');
  const alerts = products.filter(p => statusOf(p) !== 'in');
  if (alerts.length === 0) {
    panel.innerHTML = `<div class="text-center py-8 text-gray-400 text-sm">✅ All products are well stocked!</div>`;
    return;
  }
  panel.innerHTML = alerts.map(p => `
    <div class="flex items-center justify-between gap-2 bg-gray-50 rounded-xl px-3 py-2.5">
      <div class="flex items-center gap-2 min-w-0">
        <span class="text-base">${p.emoji || "📦"}</span>
        <div class="min-w-0">
          <p class="text-xs font-semibold text-gray-800 truncate">${p.name}</p>
          <p class="text-[10px] text-gray-400">${p.qty} left</p>
        </div>
      </div>
      <button onclick="openRestock('${p.id}')"
        class="flex-shrink-0 text-[10px] font-semibold bg-indigo-100 text-indigo-600 hover:bg-indigo-200 px-2.5 py-1 rounded-lg transition-colors">
        Restock
      </button>
    </div>`).join('');
}

/* ── Products table ── */
function renderProductsTable() {
  let list = [...products];
  if (tableFilter !== 'all') list = list.filter(p => statusOf(p) === tableFilter);
  if (searchQ) {
    const q = searchQ.toLowerCase();
    list = list.filter(p => p.name.toLowerCase().includes(q) || p.brand.toLowerCase().includes(q) || p.category.toLowerCase().includes(q));
  }

  const empty = document.getElementById('products-empty');
  const tbody = document.getElementById('products-tbody');

  if (list.length === 0) {
    tbody.innerHTML = '';
    empty.classList.remove('hidden');
    return;
  }
  empty.classList.add('hidden');

  tbody.innerHTML = list.map(p => `
    <tr class="trow border-b border-gray-50 dark:border-neutral-600 last:border-0">
      <td class="px-5 py-3.5">
        <div class="flex items-center gap-3">
          <span class="text-base">${p.emoji || "📦"}</span>
          <div>
            <p class="text-sm font-medium text-gray-900 dark:text-white">${p.name}</p>
            ${p.trending ? `<span class="text-[10px] text-indigo-500 font-semibold">🔥 Trending</span>` : ''}
          </div>
        </div>
      </td>
      <td class="px-3 py-3.5 text-sm text-gray-500 dark:text-white hidden sm:table-cell">${p.brand}</td>
      <td class="px-3 py-3.5 text-sm text-gray-500 dark:text-white hidden md:table-cell">${p.category}</td>
      <td class="px-3 py-3.5 text-sm font-semibold text-gray-900 dark:text-white">$${p.price}</td>
      <td class="px-3 py-3.5">
        <div class="flex items-center gap-2">
          <span class="text-sm font-medium text-gray-700 dark:text-white w-8 text-right">${p.qty}</span>
          <div class="flex gap-1">
            <button onclick="quickQty('${p.id}',-1)" title="Remove 1"
              class="w-6 h-6 rounded-md bg-gray-100 hover:bg-red-100 hover:text-red-600 text-gray-600 flex items-center justify-center text-sm font-bold transition-colors">−</button>
            <button onclick="quickQty('${p.id}',1)" title="Add 1"
              class="w-6 h-6 rounded-md bg-gray-100 hover:bg-green-100 hover:text-green-600 text-gray-600 flex items-center justify-center text-sm font-bold transition-colors">+</button>
          </div>
        </div>
      </td>
      <td class="px-3 py-3.5">${statusBadge(p)}</td>
      <td class="px-3 py-3.5">
        <div class="flex items-center gap-1.5">
          <button onclick="openEdit('${p.id}')" title="Edit"
            class="p-1.5 rounded-lg hover:bg-indigo-50 hover:text-indigo-600 text-gray-400 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
          </button>
          <button onclick="openRestock('${p.id}')" title="Restock"
            class="p-1.5 rounded-lg hover:bg-green-50 hover:text-green-600 text-gray-400 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          </button>
          <button onclick="openDelete('${p.id}')" title="Delete"
            class="p-1.5 rounded-lg hover:bg-red-50 hover:text-red-500 text-gray-400 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
          </button>
        </div>
      </td>
    </tr>`).join('');
}

/* ── Inventory table ── */
function renderInventoryTable() {
  const tbody = document.getElementById('inventory-tbody');
  const max = Math.max(...products.map(p => p.qty), 50);
  tbody.innerHTML = [...products].sort((a,b) => a.qty - b.qty).map(p => {
    const pct = Math.round((p.qty / max) * 100);
    const barColor = statusOf(p) === 'out' ? 'bg-red-400' : statusOf(p) === 'low' ? 'bg-amber-400' : 'bg-green-400';
    return `
    <tr class="trow border-b border-gray-50 dark:border-neutral-600 last:border-0">
      <td class="px-5 py-3.5">
        <div class="flex items-center gap-2">
          <p class="text-sm font-medium text-gray-800 dark:text-white">${p.name}</p>
        </div>
      </td>
      <td class="px-3 py-3.5 text-sm text-gray-500 dark:text-white hidden sm:table-cell">${p.brand}</td>
      <td class="px-3 py-3.5 text-sm font-bold text-gray-900 dark:text-white">${p.qty}</td>
      <td class="px-3 py-3.5">${statusBadge(p)}</td>
      <td class="px-3 py-3.5 w-36 hidden md:table-cell">
        <div class="bg-gray-100 dark:bg-neutral-600 rounded-full h-2 overflow-hidden">
          <div class="${barColor} h-2 rounded-full transition-all duration-500" style="width:${pct}%"></div>
        </div>
      </td>
      <td class="px-3 py-3.5">
        <button onclick="openRestock('${p.id}')"
          class="text-xs font-semibold bg-indigo-50 text-indigo-600 hover:bg-indigo-100 px-3 py-1.5 rounded-lg transition-colors">
          Restock
        </button>
      </td>
    </tr>`;
  }).join('');
}

/* ── Quick qty ── */
function quickQty(id, delta) {
  const p = products.find(x => x.id === id);
  if (!p) return;
  const newQty = Math.max(0, p.qty + delta);
  apiFetch(`/admin/products/${id}`, { method: 'PUT', body: JSON.stringify({ qty: newQty }) })
    .then(updated => {
      Object.assign(p, updated);
      refreshAll();
      toast(`${p.name} qty → ${p.qty}`);
    })
    .catch(() => toast('Failed to update quantity.'));
}

/* ── Filter table ── */
function filterByStatus(status) {
  tableFilter = status;
  document.querySelectorAll('.tab-btn').forEach(b => {
    const active = b.dataset.tab === status;
    b.classList.toggle('active-tab', active);
    b.classList.toggle('text-indigo-600', active);
    b.classList.toggle('text-gray-500', !active);
  });
  renderProductsTable();
}

function filterTable(q) {
  searchQ = q;
  renderProductsTable();
  renderInventoryTable();
}

/* ── Edit modal ── */
function openEdit(id) {
  editingId = id;
  const p = products.find(x => x.id === id);
  document.getElementById('m-name').value = p.name;
  document.getElementById('m-price').value = p.price;
  document.getElementById('m-qty').value = p.qty;
  document.getElementById('m-desc').value = p.desc || '';
  document.getElementById('m-trending').checked = p.trending;
  document.getElementById('edit-modal').classList.remove('hidden');
}

function closeModal() {
  document.getElementById('edit-modal').classList.add('hidden');
  editingId = null;
}

function saveModal() {
  const p = products.find(x => x.id === editingId);
  if (!p) return;
  const payload = {
    name: document.getElementById('m-name').value.trim() || p.name,
    price: parseFloat(document.getElementById('m-price').value) || p.price,
    qty: parseInt(document.getElementById('m-qty').value) || 0,
    desc: document.getElementById('m-desc').value.trim(),
    trending: document.getElementById('m-trending').checked,
  };
  apiFetch(`/admin/products/${editingId}`, { method: 'PUT', body: JSON.stringify(payload) })
    .then(updated => {
      Object.assign(p, updated);
      refreshAll();
      closeModal();
      toast('Product updated successfully!');
    })
    .catch(() => toast('Failed to save changes.'));
}

/* ── Restock modal ── */
function openRestock(id) {
  restockId = id;
  const p = products.find(x => x.id === id);
  document.getElementById('rs-name').textContent = p.name;
  document.getElementById('rs-current').textContent = p.qty;
  document.getElementById('rs-qty').value = p.qty;
  document.getElementById('restock-modal').classList.remove('hidden');
}

function closeRestockModal() {
  document.getElementById('restock-modal').classList.add('hidden');
  restockId = null;
}

function setRestockQty(add) {
  const p = products.find(x => x.id === restockId);
  if (!p) return;
  document.getElementById('rs-qty').value = p.qty + add;
}

function confirmRestock() {
  const p = products.find(x => x.id === restockId);
  if (!p) return;
  const newQty = parseInt(document.getElementById('rs-qty').value);
  if (isNaN(newQty) || newQty < 0) return;
  apiFetch(`/admin/products/${restockId}`, { method: 'PUT', body: JSON.stringify({ qty: newQty }) })
    .then(updated => {
      Object.assign(p, updated);
      refreshAll();
      closeRestockModal();
      toast(`✅ ${p.name} restocked to ${newQty} units`);
    })
    .catch(() => toast('Failed to restock.'));
}

function showBulkRestock() {
  const outOfStock = products.filter(p => statusOf(p) === 'out');
  if (outOfStock.length === 0) { toast('No out-of-stock items to restock!'); return; }
  Promise.all(outOfStock.map(p =>
    apiFetch(`/admin/products/${p.id}`, { method: 'PUT', body: JSON.stringify({ qty: 20 }) })
      .then(updated => Object.assign(p, updated))
  )).then(() => {
    refreshAll();
    toast(`✅ Restocked ${outOfStock.length} items to 20 units each`);
  }).catch(() => toast('Some items failed to restock.'));
}

/* ── Delete modal ── */
function openDelete(id) {
  deleteId = id;
  const p = products.find(x => x.id === id);
  document.getElementById('del-name').textContent = p.name;
  document.getElementById('delete-modal').classList.remove('hidden');
}

function closeDeleteModal() {
  document.getElementById('delete-modal').classList.add('hidden');
  deleteId = null;
}

function confirmDelete() {
  apiFetch(`/admin/products/${deleteId}`, { method: 'DELETE' })
    .then(() => {
      products = products.filter(x => x.id !== deleteId);
      refreshAll();
      closeDeleteModal();
      toast('Product deleted.');
    })
    .catch(() => toast('Failed to delete product.'));
}

/* ── Add product form ── */
function showView(view) {
  ['dashboard','products','inventory','add'].forEach(v => {
    document.getElementById(`view-${v}`).classList.toggle('hidden', v !== view);
    const nav = document.getElementById(`nav-${v}`);
    if (nav) nav.classList.toggle('active', v === view);
  });
  currentView = view;
  const titles = {dashboard:'Dashboard',products:'Products',inventory:'Inventory',add:'Add Product'};
  document.getElementById('page-title').textContent = titles[view] || '';
  document.getElementById('topbar-search').classList.toggle('hidden', view !== 'products' && view !== 'inventory');

  if (view === 'add' && !editingId) resetAddForm();

  // Auto-close the mobile sidebar after navigating so its overlay doesn't
  // keep blocking the view that was just opened.
  if (window.innerWidth < 1024 && typeof closeSidebar === 'function') closeSidebar();
}

function resetAddForm() {
  document.getElementById('edit-id').value = '';
  document.getElementById('f-name').value = '';
  document.getElementById('f-brand').value = '';
  document.getElementById('f-category').value = '';
  document.getElementById('f-price').value = '';
  document.getElementById('f-qty').value = '';
  document.getElementById('f-desc').value = '';
  document.getElementById('f-trending').checked = false;
  document.getElementById('form-title').textContent = 'Add New Product';
  document.getElementById('form-btn-text').textContent = 'Add Product';
  document.getElementById('form-error').classList.add('hidden');
  editingId = null;
}

function cancelEdit() {
  resetAddForm();
  showView('products');
}

function submitProductForm() {
  const name     = document.getElementById('f-name').value.trim();
  const brand    = document.getElementById('f-brand').value.trim();
  const category = document.getElementById('f-category').value;
  const price    = parseFloat(document.getElementById('f-price').value);
  const qty      = parseInt(document.getElementById('f-qty').value);
  const desc     = document.getElementById('f-desc').value.trim();
  const trending = document.getElementById('f-trending').checked;
  const errEl    = document.getElementById('form-error');

  if (!name || !brand || !category || isNaN(price) || isNaN(qty)) {
    errEl.textContent = 'Please fill in all required fields (name, brand, category, price, qty).';
    errEl.classList.remove('hidden');
    return;
  }
  errEl.classList.add('hidden');

  const payload = {name, brand, category, price, qty, desc, trending};

  if (editingId) {
    apiFetch(`/admin/products/${editingId}`, { method: 'PUT', body: JSON.stringify(payload) })
      .then(updated => {
        const p = products.find(x => x.id === editingId);
        if (p) Object.assign(p, updated);
        toast('Product updated!');
        refreshAll();
        resetAddForm();
        showView('products');
      })
      .catch(() => {
        errEl.textContent = 'Failed to save product. Please try again.';
        errEl.classList.remove('hidden');
      });
  } else {
    apiFetch('/admin/products', { method: 'POST', body: JSON.stringify(payload) })
      .then(created => {
        products.push(created);
        toast('Product added to catalog!');
        refreshAll();
        resetAddForm();
        showView('products');
      })
      .catch(() => {
        errEl.textContent = 'Failed to add product. Please try again.';
        errEl.classList.remove('hidden');
      });
  }
}

/* ── Sidebar toggle (mobile) ── */
function toggleSidebar() {
  const s = document.getElementById('sidebar');
  const o = document.getElementById('mob-overlay');
  s.classList.toggle('-translate-x-full');
  o.classList.toggle('hidden');
}
/* ── Toast ── */
function toast(msg) {
  const el = document.getElementById('admin-toast');
  el.innerHTML = `<div class="toast-enter bg-gray-900 text-white text-sm px-5 py-3 rounded-full shadow-lg">${msg}</div>`;
  setTimeout(() => el.innerHTML = '', 2800);
}

/* ── Fix sidebar on desktop ── */
window.addEventListener('resize', () => {
  if (window.innerWidth >= 1024) {
    document.getElementById('sidebar').classList.remove('-translate-x-full');
    document.getElementById('mob-overlay').classList.add('hidden');
  }
});
if (window.innerWidth < 1024) {
  document.getElementById('sidebar').classList.add('-translate-x-full');
}

  // Dark theme toggle
    function initTheme() {
    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
  }
  initTheme(); // Run immediately to prevent flash of wrong theme

  function toggleTheme() {
    document.documentElement.classList.toggle('dark');
    if (document.documentElement.classList.contains('dark')) {
      localStorage.setItem('theme', 'dark');
    } else {
      localStorage.setItem('theme', 'light');
    }
    if (typeof window.refreshAnalyticsTheme === 'function') window.refreshAnalyticsTheme();
  }

/* ── Boot ── */
init();
