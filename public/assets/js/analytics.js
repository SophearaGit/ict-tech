/* ══════════════════════════════════════════════
   Sales Analytics — the dashboard's month view
   One month at a time: a drop-down with this month and the 11 before it. The numbers are the Sales
   Report's own answer, GET /admin/reports/sales?period=month&date=YYYY-MM-01, so the dashboard and
   the Sales Report page can never disagree (cancelled orders are already left out by the server).
   Shows: orders, items sold, average order, and the 5 best sellers of the month.
   A failed request is said plainly ("Analytics unavailable"); the page never shows made-up numbers.
   Uses apiFetch and escapeHtml (admin.js), so this file loads after it. sales-report.js uses
   formatAnalyticsMoney from here.
══════════════════════════════════════════════ */

let dashMonth = '';        // 'YYYY-MM' the admin picked; '' = this month, whatever month the shop's clock says it is
let dashCurrentKey = '';   // this month as 'YYYY-MM', learned from the server's first answer
let dashRequestId = 0;     // a slow answer that was overtaken by a newer request is ignored

function formatAnalyticsMoney(amount) {
  return '$' + amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/* ── Months as 'YYYY-MM' text: plain number maths, so the browser's clock and timezone never shift a month ── */
function monthKeyBefore(key) {
  const [y, m] = key.split('-').map(Number);
  return m === 1 ? `${y - 1}-12` : `${y}-${String(m - 1).padStart(2, '0')}`;
}

function monthLabel(key) {
  const [y, m] = key.split('-').map(Number);
  return new Date(y, m - 1, 1).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
}

function recentMonthKeys(currentKey, count = 12) {
  const keys = [currentKey];
  while (keys.length < count) keys.push(monthKeyBefore(keys[keys.length - 1]));
  return keys;
}

// The 5 products with the most units sold (then the most money, then A to Z).
function bestSellers(products) {
  return [...products]
    .sort((a, b) => b.quantity - a.quantity || b.revenue - a.revenue || a.name.localeCompare(b.name))
    .slice(0, 5);
}

/* ── Loading ── */
function fetchSalesMonth(key) {
  const params = new URLSearchParams({ period: 'month' });
  if (key) params.set('date', `${key}-01`); // no date = this month in shop time
  return apiFetch(`/admin/reports/sales?${params}`);
}

// Also called by showView('dashboard'), so new orders show up whenever the admin comes back to the dashboard.
async function loadDashboardSales() {
  const requestId = ++dashRequestId;
  const wantedKey = dashMonth;
  try {
    const current = await fetchSalesMonth(wantedKey);
    if (requestId !== dashRequestId) return;
    const shownKey = current.from.slice(0, 7);
    if (!wantedKey) dashCurrentKey = shownKey;
    // "This month" has not finished yet; the server's own date (today in shop time) says so.
    renderDashboardSales(current, shownKey, !wantedKey);
  } catch (e) {
    if (requestId !== dashRequestId) return;
    renderDashboardUnavailable();
  }
}

function setDashboardMonth(value) {
  dashMonth = value === dashCurrentKey ? '' : value;
  renderDashboardLoading();
  loadDashboardSales();
}

/* ── Drawing ── */
function setDashText(id, text) {
  document.getElementById(id).textContent = text;
}

function setBestSellersState(state) { // 'rows' | 'empty' | 'unavailable' | 'loading'
  document.getElementById('best-sellers-empty').classList.toggle('hidden', state !== 'empty');
  document.getElementById('analytics-unavailable').classList.toggle('hidden', state !== 'unavailable');
  if (state !== 'rows') document.getElementById('best-sellers-tbody').innerHTML = '';
}

function renderMonthOptions(shownKey) {
  const select = document.getElementById('sales-month');
  const keys = recentMonthKeys(dashCurrentKey || shownKey);
  select.replaceChildren(...keys.map((key, i) => new Option(monthLabel(key) + (i === 0 ? ' (this month)' : ''), key)));
  select.value = shownKey;
}

function clearDashboardNotes() {
  ['kpi-orders-note', 'kpi-items-note', 'kpi-average-note'].forEach(id => setDashText(id, ' ')); // a non-breaking space keeps the card's height steady
}

function renderDashboardLoading() {
  ['kpi-orders', 'kpi-items', 'kpi-average'].forEach(id => setDashText(id, '…'));
  clearDashboardNotes();
  setBestSellersState('loading');
}

function renderDashboardUnavailable() {
  ['kpi-orders', 'kpi-items', 'kpi-average'].forEach(id => setDashText(id, '—'));
  clearDashboardNotes();
  setDashText('best-sellers-title', 'Best sellers');
  setBestSellersState('unavailable');
}

function renderDashboardSales(current, shownKey, running) {
  const label = monthLabel(shownKey);
  const when = `${running ? 'so far in' : 'in'} ${label}`;
  const t = current.totals;

  renderMonthOptions(shownKey);

  setDashText('kpi-orders', t.orders.toLocaleString('en-US'));
  setDashText('kpi-orders-note', when);
  setDashText('kpi-items', t.items.toLocaleString('en-US'));
  setDashText('kpi-items-note', when);
  setDashText('kpi-average', formatAnalyticsMoney(t.averageOrder));
  setDashText('kpi-average-note', t.orders > 0 ? `per order, ${label}` : `no orders in ${label}`);

  setDashText('best-sellers-title', `Best sellers — ${label}`);
  const top = bestSellers(current.products);
  if (top.length === 0) {
    setDashText('best-sellers-empty-title', running ? `No sales yet in ${label}` : `No sales in ${label}`);
    setBestSellersState('empty');
    return;
  }
  setBestSellersState('rows');
  const cell = 'px-3 py-3 text-sm text-gray-700 dark:text-white';
  document.getElementById('best-sellers-tbody').innerHTML = top.map((p, i) => `
    <tr class="border-t border-gray-100 dark:border-neutral-600">
      <td class="pl-4 sm:pl-5 pr-2 py-3 text-sm font-semibold text-gray-700 dark:text-white">${i + 1}</td>
      <td class="px-3 py-3 text-sm font-medium text-gray-900 dark:text-white">${escapeHtml(p.name)}</td>
      <td class="${cell} hidden sm:table-cell">${escapeHtml(p.brand)}</td>
      <td class="${cell} font-semibold">${p.quantity.toLocaleString('en-US')}</td>
      <td class="${cell}">${escapeHtml(formatAnalyticsMoney(p.revenue))}</td>
    </tr>`).join('');
}

loadDashboardSales();
