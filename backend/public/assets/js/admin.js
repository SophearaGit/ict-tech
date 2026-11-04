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
      throw new Error(err.error || err.message || `Request failed (${res.status})`);
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
let brands = [];             // [{id, name, productCount}] from /admin/brands, kept sorted by name
let renamingBrandId = null;  // the Brands-table row that is currently in rename mode

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
  loadBrands();
}

function refreshAll() {
  updateStats();
  renderRecentTable();
  renderAlertsPanel();
  renderProductsTable();
  renderInventoryTable();
  updateLowBadge();
  if (currentView === 'brands') renderBrandsTable();
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

/* ── Contact messages ── */
let messages = [];
let messagesLoaded = false;

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}

// For values placed inside a double-quoted HTML attribute (escapeHtml leaves quotes alone).
function escapeAttr(str) {
  return escapeHtml(str).replace(/"/g, '&quot;');
}

function formatMessageDate(iso) {
  return new Date(iso).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}

function renderMessagesList() {
  const list = document.getElementById('messages-list');
  const empty = document.getElementById('messages-empty');
  if (!list) return;

  if (messages.length === 0) {
    list.innerHTML = '';
    empty.classList.remove('hidden');
    return;
  }
  empty.classList.add('hidden');

  list.innerHTML = messages.map(m => `
    <div class="px-6 py-5">
      <div class="flex flex-wrap items-baseline justify-between gap-2 mb-1.5">
        <p class="font-semibold text-gray-900 dark:text-white">${escapeHtml(m.name)}
          <span class="font-normal text-gray-400 text-sm">&lt;${escapeHtml(m.email)}&gt;</span>
        </p>
        <span class="text-xs text-gray-400">${formatMessageDate(m.created_at)}</span>
      </div>
      ${m.subject ? `<p class="text-xs font-semibold text-indigo-500 uppercase tracking-wide mb-1.5">${escapeHtml(m.subject)}</p>` : ''}
      <p class="text-sm text-gray-600 dark:text-gray-300 whitespace-pre-line">${escapeHtml(m.message)}</p>
    </div>`).join('');
}

function loadMessages() {
  apiFetch('/admin/messages')
    .then(data => { messages = data; renderMessagesList(); })
    .catch(() => toast('Failed to load messages.'));
}

/* ── Brands (managed list: the Brands page, plus the brand selects in the product form and edit modal) ── */
function sortBrands() {
  brands.sort((a, b) => a.name.localeCompare(b.name, undefined, { sensitivity: 'base', numeric: true }));
}

// Server messages can contain a brand name, and toast() writes HTML, so escape them.
function toastError(e, fallback) {
  toast(escapeHtml((e && e.message) || fallback || 'Something went wrong.'));
}

function loadBrands() {
  return apiFetch('/admin/brands')
    .then(data => { brands = data; renderBrandSelects(); renderBrandsTable(); })
    .catch(() => toast('Failed to load brands.'));
}

// Rebuild both brand selects from `brands`, keeping whatever is currently selected.
// Only the Add Product form's select ends with "+ Add new brand…".
function renderBrandSelects() {
  const options = brands.map(b => `<option value="${b.id}">${escapeHtml(b.name)}</option>`).join('');
  [['f-brand', true], ['m-brand', false]].forEach(([id, withNew]) => {
    const select = document.getElementById(id);
    if (!select) return;
    const current = select.value;
    select.innerHTML = `<option value="">Select brand…</option>${options}`
      + (withNew ? '<option value="__new__">+ Add new brand…</option>' : '');
    if ([...select.options].some(o => o.value === current)) select.value = current;
  });
}

function onBrandChange(select) {
  const adding = select.value === '__new__';
  document.getElementById('new-brand-box').classList.toggle('hidden', !adding);
  if (adding) document.getElementById('new-brand-name').focus();
}

function cancelNewBrand() {
  const select = document.getElementById('f-brand');
  if (select.value === '__new__') select.value = '';
  document.getElementById('new-brand-name').value = '';
  document.getElementById('new-brand-box').classList.add('hidden');
}

// Inline "+ Add new brand…" in the product form: create it, then select it.
function saveNewBrand() {
  const name = document.getElementById('new-brand-name').value.trim();
  if (!name) { toast('Type the new brand name first.'); return; }

  apiFetch('/admin/brands', { method: 'POST', body: JSON.stringify({ name }) })
    .then(created => {
      brands.push(created);
      sortBrands();
      renderBrandSelects();
      renderBrandsTable();
      document.getElementById('f-brand').value = String(created.id);
      cancelNewBrand();
      toast('Brand added!');
    })
    .catch(e => toastError(e, 'Failed to add brand.'));
}

function renderBrandsTable() {
  const tbody = document.getElementById('brands-tbody');
  const empty = document.getElementById('brands-empty');
  if (!tbody) return;

  empty.classList.toggle('hidden', brands.length > 0);
  const btn = 'px-2.5 py-1 rounded-lg text-xs font-semibold transition-colors';
  tbody.innerHTML = brands.map(b => {
    const renaming = b.id === renamingBrandId;
    const nameCell = renaming
      ? `<input id="brand-rename-input" type="text" maxlength="50" value="${escapeAttr(b.name)}"
           onkeydown="if (event.key === 'Enter') saveRenameBrand(${b.id}); if (event.key === 'Escape') cancelRenameBrand()"
           class="form-inp w-full max-w-xs bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5 text-sm"/>`
      : escapeHtml(b.name);
    const actions = renaming
      ? `<button onclick="saveRenameBrand(${b.id})" class="${btn} bg-indigo-500 hover:bg-indigo-600 text-white">Save</button>
         <button onclick="cancelRenameBrand()" class="${btn} bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-neutral-600 dark:hover:bg-neutral-500 dark:text-white">Cancel</button>`
      : `<button onclick="startRenameBrand(${b.id})" class="${btn} bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-neutral-600 dark:hover:bg-neutral-500 dark:text-white">Rename</button>
         <button onclick="deleteBrand(${b.id})" class="${btn} bg-red-50 hover:bg-red-100 text-red-600">Delete</button>`;
    return `
      <tr class="border-t border-gray-100 dark:border-neutral-600">
        <td class="px-5 py-3.5 text-sm font-medium text-gray-900 dark:text-white">${nameCell}</td>
        <td class="px-3 py-3.5 text-sm text-gray-500 dark:text-white">${b.productCount}</td>
        <td class="px-3 py-3.5"><div class="flex gap-2">${actions}</div></td>
      </tr>`;
  }).join('');

  if (renamingBrandId !== null) {
    const input = document.getElementById('brand-rename-input');
    if (input) { input.focus(); input.select(); }
  }
}

function addBrand() {
  const input = document.getElementById('brand-add-name');
  const name = input.value.trim();
  if (!name) { toast('Type a brand name.'); return; }

  apiFetch('/admin/brands', { method: 'POST', body: JSON.stringify({ name }) })
    .then(created => {
      brands.push(created);
      sortBrands();
      input.value = '';
      renderBrandsTable();
      renderBrandSelects();
      toast('Brand added!');
    })
    .catch(e => toastError(e, 'Failed to add brand.'));
}

function startRenameBrand(id) {
  renamingBrandId = id;
  renderBrandsTable();
}

function cancelRenameBrand() {
  renamingBrandId = null;
  renderBrandsTable();
}

function saveRenameBrand(id) {
  const name = document.getElementById('brand-rename-input').value.trim();
  if (!name) { toast('Type a brand name.'); return; }

  apiFetch(`/admin/brands/${id}`, { method: 'PUT', body: JSON.stringify({ name }) })
    .then(updated => {
      const b = brands.find(x => x.id === id);
      if (b) Object.assign(b, updated);
      sortBrands();
      renamingBrandId = null;
      // Products carry the brand name, so the product/inventory tables must show the new one too.
      products.forEach(p => { if (p.brandId === id) p.brand = updated.name; });
      renderBrandSelects();
      refreshAll();
      renderBrandsTable();
      toast('Brand renamed!');
    })
    .catch(e => toastError(e, 'Failed to rename brand.'));
}

function deleteBrand(id) {
  apiFetch(`/admin/brands/${id}`, { method: 'DELETE' })
    .then(() => {
      brands = brands.filter(b => b.id !== id);
      renderBrandsTable();
      renderBrandSelects();
      toast('Brand deleted.');
    })
    .catch(e => toastError(e, 'Failed to delete brand.'));
}

/* ── Orders (real orders placed in the store — this view was the "History" placeholder) ── */
let orders = [];
let ordersLoaded = false;

const ORDER_STATUS_BADGE = { pending: 'badge-low', paid: 'badge-paid', delivered: 'badge-in', cancelled: 'badge-out' };
const ORDER_PAYMENT_LABEL = { aba: 'ABA KHQR', cod: 'Cash on delivery' };
// Same transitions the server allows (ChangeOrderStatus); the server stays the authority.
const ORDER_NEXT_STATUSES = { pending: ['paid', 'delivered', 'cancelled'], paid: ['delivered', 'cancelled'] };
const ORDER_ACTION_LABEL = { paid: 'Mark paid', delivered: 'Mark delivered', cancelled: 'Cancel' };

function loadOrders() {
  apiFetch('/admin/orders')
    .then(data => { orders = data; ordersLoaded = true; renderHistoryTable(); })
    .catch(() => toast('Failed to load orders.'));
}

function renderHistoryTable() {
  const empty = document.getElementById('history-empty');
  const tbody = document.getElementById('history-tbody');
  if (!tbody || !ordersLoaded) return;

  let list = orders;
  if (searchQ) {
    const q = searchQ.toLowerCase();
    list = list.filter(o => [o.number, o.customer && o.customer.name, o.customer && o.customer.email, ...o.items.map(i => i.name)]
      .some(v => (v || '').toLowerCase().includes(q)));
  }

  if (list.length === 0) {
    tbody.innerHTML = '';
    document.getElementById('history-empty-title').textContent = orders.length === 0 ? 'No orders yet' : 'No matching orders';
    document.getElementById('history-empty-hint').textContent = orders.length === 0
      ? 'Orders placed in the store will show up here.'
      : 'Try adjusting your search.';
    empty.classList.remove('hidden');
    return;
  }
  empty.classList.add('hidden');

  // Every customer-supplied value (names, email, phone, address, product names) goes through escapeHtml.
  tbody.innerHTML = list.map(o => {
    const actions = (ORDER_NEXT_STATUSES[o.status] || []).map(s => `
      <button onclick="changeOrderStatus(${Number(o.id)}, '${s}', this)"
        class="text-xs font-semibold ${s === 'cancelled' ? 'bg-red-50 text-red-600 hover:bg-red-100' : 'bg-indigo-50 text-indigo-600 hover:bg-indigo-100'} px-2.5 py-1.5 rounded-lg transition-colors disabled:opacity-50 whitespace-nowrap">
        ${ORDER_ACTION_LABEL[s]}
      </button>`).join('');
    const customer = o.customer
      ? `<p class="font-medium text-gray-900 dark:text-white">${escapeHtml(o.customer.name)}</p>
         <p class="text-xs text-gray-400">${escapeHtml(o.customer.email)}</p>`
      : `<p class="font-medium text-gray-400 italic">Deleted account</p>`;
    return `
    <tr class="trow border-b border-gray-50 dark:border-neutral-600 last:border-0 align-top">
      <td class="px-5 py-3 text-sm font-semibold text-gray-700 dark:text-white whitespace-nowrap">${escapeHtml(o.number)}</td>
      <td class="px-3 py-3 text-sm">
        ${customer}
        <p class="text-xs text-gray-500 dark:text-gray-300 mt-1">${escapeHtml(o.phone)}</p>
        <p class="text-xs text-gray-500 dark:text-gray-300 whitespace-pre-line">${escapeHtml(o.address)}</p>
      </td>
      <td class="px-3 py-3 text-sm text-gray-700 dark:text-white hidden sm:table-cell">
        ${o.items.map(i => `<p>${Number(i.quantity)} × ${escapeHtml(i.name)}</p>`).join('')}
      </td>
      <td class="px-3 py-3 text-sm font-semibold text-red-500 whitespace-nowrap">$${Number(o.total).toFixed(2)}</td>
      <td class="px-3 py-3 text-sm text-gray-700 dark:text-white hidden md:table-cell">${escapeHtml(ORDER_PAYMENT_LABEL[o.payment_method] || o.payment_method)}</td>
      <td class="px-3 py-3"><span class="${ORDER_STATUS_BADGE[o.status] || 'badge-low'} text-[10px] font-semibold px-2 py-0.5 rounded-full capitalize">${escapeHtml(o.status)}</span></td>
      <td class="px-3 py-3 text-sm text-gray-400 hidden md:table-cell">${formatMessageDate(o.created_at)}</td>
      <td class="px-3 py-3"><div class="flex flex-wrap gap-1.5">${actions}</div></td>
    </tr>`;
  }).join('');
}

function changeOrderStatus(id, status, btn) {
  if (btn) btn.disabled = true;
  apiFetch(`/admin/orders/${id}`, { method: 'PUT', body: JSON.stringify({ status }) })
    .then(res => {
      const i = orders.findIndex(o => o.id === id);
      if (i !== -1) orders[i] = res.order;
      renderHistoryTable();
      toast(`${escapeHtml(res.order.number)} marked ${status}`);
      if (status === 'cancelled') init(); // stock went back up — refresh the product/inventory numbers
    })
    .catch(e => {
      if (btn) btn.disabled = false;
      toast(escapeHtml(e.message));
    });
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
  renderHistoryTable();
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
  setSelectValue('m-brand', p.brandId);
  setSelectValue('m-category', p.category); // a legacy category that is not in the list leaves the placeholder
  document.getElementById('edit-modal').classList.remove('hidden');
}

function closeModal() {
  document.getElementById('edit-modal').classList.add('hidden');
  editingId = null;
}

// Select `value` in the <select> with this id if it has such an option, otherwise show its placeholder.
function setSelectValue(id, value) {
  const select = document.getElementById(id);
  select.value = [...select.options].some(o => o.value === String(value)) ? String(value) : '';
}

function saveModal() {
  const p = products.find(x => x.id === editingId);
  if (!p) return;
  const brandId = parseInt(document.getElementById('m-brand').value);
  const category = document.getElementById('m-category').value;
  if (isNaN(brandId) || !category) { toast('Choose a brand and a category.'); return; }
  const payload = {
    name: document.getElementById('m-name').value.trim() || p.name,
    price: parseFloat(document.getElementById('m-price').value) || p.price,
    qty: parseInt(document.getElementById('m-qty').value) || 0,
    desc: document.getElementById('m-desc').value.trim(),
    trending: document.getElementById('m-trending').checked,
    brand_id: brandId,
    category,
  };
  apiFetch(`/admin/products/${editingId}`, { method: 'PUT', body: JSON.stringify(payload) })
    .then(updated => {
      Object.assign(p, updated);
      refreshAll();
      closeModal();
      toast('Product updated successfully!');
    })
    .catch(e => toastError(e, 'Failed to save changes.'));
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
  ['dashboard','products','inventory','add','history','messages','brands','report'].forEach(v => {
    document.getElementById(`view-${v}`).classList.toggle('hidden', v !== view);
    const nav = document.getElementById(`nav-${v}`);
    if (nav) nav.classList.toggle('active', v === view);
  });
  currentView = view;
  const titles = {dashboard:'Dashboard',products:'Products',inventory:'Inventory',add:'Add Product',history:'Orders',messages:'Messages',brands:'Brands',report:'Sales Report'};
  document.getElementById('page-title').textContent = titles[view] || '';
  document.getElementById('topbar-search').classList.toggle('hidden', !['products','inventory','history'].includes(view));

  if (view === 'add' && !editingId) resetAddForm();
  if (view === 'messages' && !messagesLoaded) { messagesLoaded = true; loadMessages(); }
  if (view === 'history') loadOrders(); // reload on every open so new orders appear
  if (view === 'brands') { renamingBrandId = null; renderBrandsTable(); loadBrands(); } // reload so product counts are current
  if (view === 'report' && typeof initSalesReport === 'function') initSalesReport(); // sales-report.js loads after this file

  // Auto-close the mobile sidebar after navigating so its overlay doesn't
  // keep blocking the view that was just opened.
  if (window.innerWidth < 1024 && typeof closeSidebar === 'function') closeSidebar();
}

function resetAddForm() {
  document.getElementById('edit-id').value = '';
  document.getElementById('f-name').value = '';
  document.getElementById('f-brand').value = '';
  cancelNewBrand();
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
  const brandId  = parseInt(document.getElementById('f-brand').value); // NaN for the placeholder or "+ Add new brand…"
  const category = document.getElementById('f-category').value;
  const price    = parseFloat(document.getElementById('f-price').value);
  const qty      = parseInt(document.getElementById('f-qty').value);
  const desc     = document.getElementById('f-desc').value.trim();
  const trending = document.getElementById('f-trending').checked;
  const errEl    = document.getElementById('form-error');

  if (!name || isNaN(brandId) || !category || isNaN(price) || isNaN(qty)) {
    errEl.textContent = 'Please fill in all required fields (name, brand, category, price, qty).';
    errEl.classList.remove('hidden');
    return;
  }
  errEl.classList.add('hidden');

  const payload = {name, brand_id: brandId, category, price, qty, desc, trending};

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
      .catch(e => {
        errEl.textContent = e.message || 'Failed to save product. Please try again.';
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
      .catch(e => {
        errEl.textContent = e.message || 'Failed to add product. Please try again.';
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
    if (localStorage.getItem('theme') === 'dark') {
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
