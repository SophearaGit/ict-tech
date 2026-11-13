/* ══════════════════════════════════════════════
   Sales Analytics — the dashboard's month view
   One month at a time: a drop-down with this month and the 11 before it. The numbers are the Sales
   Report's own answer, GET /admin/reports/sales?period=month&date=YYYY-MM-01, so the dashboard and
   the Sales Report page can never disagree (cancelled orders are already left out by the server).
   Two answers are used: the chosen month, and the month before it for the "more / less than" line.
   A failed request is said plainly ("Analytics unavailable"); the page never shows made-up numbers.
   Uses apiFetch and escapeHtml (admin.js), so this file loads after it. sales-report.js uses
   formatAnalyticsMoney from here.
══════════════════════════════════════════════ */

let dashMonth = '';        // 'YYYY-MM' the admin picked; '' = this month, whatever month the shop's clock says it is
let dashCurrentKey = '';   // this month as 'YYYY-MM', learned from the server's first answer
let dashRequestId = 0;     // a slow answer that was overtaken by a newer request is ignored

const CHANGE_TONES = {
  up: ['text-green-700', 'dark:text-green-400'],
  down: ['text-red-700', 'dark:text-red-400'],
  none: ['text-gray-500', 'dark:text-gray-300'],
};

function formatAnalyticsMoney(amount) {
  return '$' + amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/* ── Months as 'YYYY-MM' text: plain number maths, so the browser's clock and timezone never shift a month ── */
function monthKeyBefore(key) {
  const [y, m] = key.split('-').map(Number);
  return m === 1 ? `${y - 1}-12` : `${y}-${String(m - 1).padStart(2, '0')}`;
}

function monthLabel(key, withYear = true) {
  const [y, m] = key.split('-').map(Number);
  return new Date(y, m - 1, 1).toLocaleDateString('en-US', withYear ? { month: 'long', year: 'numeric' } : { month: 'long' });
}

function recentMonthKeys(currentKey, count = 12) {
  const keys = [currentKey];
  while (keys.length < count) keys.push(monthKeyBefore(keys[keys.length - 1]));
  return keys;
}

/* ── Revenue compared with the month before ── */
// Whole cents, so a dollar total never picks up float noise. Words as well as the arrow, never colour alone.
function revenueChange(current, previous, previousName) {
  const now = Math.round(current * 100);
  const before = Math.round(previous * 100);
  if (before === 0) return { text: 'Nothing to compare with', tone: 'none' };
  if (now === before) return { text: `Same as ${previousName}`, tone: 'none' };
  const pct = Math.round(Math.abs(now - before) / before * 100);
  if (pct === 0) return { text: `About the same as ${previousName}`, tone: 'none' };
  const up = now > before;
  return { text: `${up ? '▲' : '▼'} ${pct.toLocaleString('en-US')}% ${up ? 'more' : 'less'} than ${previousName}`, tone: up ? 'up' : 'down' };
}

// A month that is still running is compared with the SAME DAYS of the month before ("the first 13 days of October"),
// not with all of it: otherwise the 13th of a month would always look like a big drop. A finished month is compared in full.
function revenueComparison(shown, previousData, shownKey, throughDay) {
  const previousKey = monthKeyBefore(shownKey);
  const month = monthLabel(previousKey, previousKey.slice(0, 4) !== shownKey.slice(0, 4)); // the year only when it differs
  let name = month;
  let previousRevenue = previousData.totals.revenue;
  if (throughDay !== null && throughDay < previousData.days.length) {
    previousRevenue = previousData.days.slice(0, throughDay).reduce((sum, d) => sum + Math.round(d.revenue * 100), 0) / 100;
    name = throughDay === 1 ? `the first day of ${month}` : `the first ${throughDay} days of ${month}`;
  }
  return revenueChange(shown.totals.revenue, previousRevenue, name);
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
    const shownKey = current.from.slice(0, 7);
    if (!wantedKey) dashCurrentKey = shownKey;

    let previous = null;
    try { previous = await fetchSalesMonth(monthKeyBefore(shownKey)); } catch (e) { /* only the comparison line is lost */ }

    if (requestId !== dashRequestId) return;
    // For "this month" the server's date is today in shop time; its day number says how far the month has got.
    renderDashboardSales(current, previous, shownKey, wantedKey ? null : Number(current.date.slice(8, 10)));
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

function setRevenueChange(text, tone) {
  const el = document.getElementById('kpi-revenue-change');
  el.classList.remove(...Object.values(CHANGE_TONES).flat());
  el.classList.add(...CHANGE_TONES[tone]);
  el.textContent = text || ' '; // a non-breaking space keeps the card's height steady
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

function renderDashboardLoading() {
  ['kpi-revenue', 'kpi-orders', 'kpi-average'].forEach(id => setDashText(id, '…'));
  ['kpi-orders-note', 'kpi-average-note'].forEach(id => setDashText(id, ' '));
  setRevenueChange('', 'none');
  setBestSellersState('loading');
}

function renderDashboardUnavailable() {
  ['kpi-revenue', 'kpi-orders', 'kpi-average'].forEach(id => setDashText(id, '—'));
  ['kpi-orders-note', 'kpi-average-note'].forEach(id => setDashText(id, ' '));
  setRevenueChange('', 'none');
  setDashText('best-sellers-title', 'Best sellers');
  setBestSellersState('unavailable');
}

function renderDashboardSales(current, previous, shownKey, throughDay) {
  const label = monthLabel(shownKey);
  const running = throughDay !== null; // this month is not finished yet
  const t = current.totals;

  renderMonthOptions(shownKey);

  setDashText('kpi-revenue', formatAnalyticsMoney(t.revenue));
  const change = previous
    ? revenueComparison(current, previous, shownKey, throughDay)
    : { text: 'Could not compare with the month before', tone: 'none' };
  setRevenueChange(change.text, change.tone);

  setDashText('kpi-orders', t.orders.toLocaleString('en-US'));
  setDashText('kpi-orders-note', `${running ? 'so far in' : 'in'} ${label}`);
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
