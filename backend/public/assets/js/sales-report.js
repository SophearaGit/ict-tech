/* ══════════════════════════════════════════════
   Sales Report — admin page (day / week / month)
   Data: GET /admin/reports/sales?period=…&date=…           → JSON
   CSV:  GET /admin/reports/sales/export?period=…&date=…    → file download
   Reuses apiFetch, escapeHtml, ORDER_STATUS_BADGE and ORDER_PAYMENT_LABEL (admin.js) and
   formatAnalyticsMoney (analytics.js), so this file loads after both. Every value that comes
   from the server is escaped before it goes into innerHTML, and a failed request clears the
   numbers — the page never shows made-up ones.
══════════════════════════════════════════════ */

let reportPeriod = 'week';
let reportRequestId = 0; // a slow answer that was overtaken by a newer request is ignored

const REPORT_BTN_ACTIVE = ['bg-white', 'text-gray-900', 'shadow-sm', 'dark:bg-neutral-800', 'dark:text-white'];
const REPORT_BTN_IDLE = ['text-gray-500', 'dark:text-gray-300'];
const REPORT_CELL = 'px-3 py-3 text-sm text-gray-700 dark:text-white';
const REPORT_ROW = 'border-t border-gray-100 dark:border-neutral-600';

// Called by showView('report') every time the page is opened, so new orders show up.
function initSalesReport() {
  renderReportPeriodButtons();
  loadSalesReport();
}

function setReportPeriod(period) {
  reportPeriod = period;
  renderReportPeriodButtons();
  loadSalesReport();
}

function renderReportPeriodButtons() {
  ['day', 'week', 'month'].forEach(p => {
    const btn = document.getElementById(`report-period-${p}`);
    btn.classList.remove(...REPORT_BTN_ACTIVE, ...REPORT_BTN_IDLE);
    btn.classList.add(...(p === reportPeriod ? REPORT_BTN_ACTIVE : REPORT_BTN_IDLE));
    btn.setAttribute('aria-pressed', p === reportPeriod ? 'true' : 'false');
  });
  // A month is picked with a month box; Day and Week use a date box (any date in the week).
  document.getElementById('report-date').classList.toggle('hidden', reportPeriod === 'month');
  document.getElementById('report-month').classList.toggle('hidden', reportPeriod !== 'month');
}

// "period=week&date=2026-11-04" — the date is left out when the box is empty, and the
// server then uses today in shop time. A month sends the 1st of that month.
function reportQuery() {
  const params = new URLSearchParams({ period: reportPeriod });
  let date;
  if (reportPeriod === 'month') {
    const month = document.getElementById('report-month').value;
    date = month ? `${month}-01` : '';
  } else {
    date = document.getElementById('report-date').value;
  }
  if (date) params.set('date', date);
  return params.toString();
}

async function loadSalesReport() {
  const requestId = ++reportRequestId;
  const errorBox = document.getElementById('report-error');

  try {
    const data = await apiFetch(`/admin/reports/sales?${reportQuery()}`);
    if (requestId !== reportRequestId) return;
    errorBox.classList.add('hidden');
    renderSalesReport(data);
  } catch (e) {
    if (requestId !== reportRequestId) return;
    clearSalesReport();
    showReportError(e);
  }
}

// An expired session gets a plain sentence and a link back to the login page. Only fixed markup
// is ever put into innerHTML here; any other error text from the server goes in as plain text.
function showReportError(e) {
  const box = document.getElementById('report-error');
  if (e && e.sessionExpired) {
    box.innerHTML = 'Your session has expired. <a href="/admin/login" class="underline font-semibold">Log in again</a> to see the report.';
  } else {
    box.textContent = (e && e.message) || 'Could not load the sales report.';
  }
  box.classList.remove('hidden');
}

// "2026-11-04" → text, using the browser's calendar only (no timezone maths: the server already
// worked out the shop-time dates).
function reportDateText(iso, options) {
  const [y, m, d] = iso.split('-').map(Number);
  return new Date(y, m - 1, d).toLocaleDateString('en-GB', options);
}

function reportTitle(data) {
  if (data.period === 'month') {
    return 'Sales report — ' + reportDateText(data.from, { month: 'long', year: 'numeric' });
  }
  if (data.period === 'day') {
    return 'Sales report — ' + reportDateText(data.from, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
  }
  const sameYear = data.from.slice(0, 4) === data.to.slice(0, 4);
  const start = reportDateText(data.from, sameYear ? { day: 'numeric', month: 'short' } : { day: 'numeric', month: 'short', year: 'numeric' });
  return `Sales report — Week ${start} – ${reportDateText(data.to, { day: 'numeric', month: 'short', year: 'numeric' })}`;
}

function renderSalesReport(data) {
  document.getElementById('report-title').textContent = reportTitle(data);
  document.getElementById('report-date').value = data.date;
  document.getElementById('report-month').value = data.date.slice(0, 7);

  const t = data.totals;
  document.getElementById('report-orders').textContent = t.orders;
  document.getElementById('report-items').textContent = t.items;
  document.getElementById('report-revenue').textContent = formatAnalyticsMoney(t.revenue);
  document.getElementById('report-average').textContent = formatAnalyticsMoney(t.averageOrder);

  const hasSales = t.orders > 0;
  document.getElementById('report-empty').classList.toggle('hidden', hasSales);
  document.getElementById('report-days-card').classList.toggle('hidden', !hasSales || data.period === 'day');
  document.getElementById('report-orders-card').classList.toggle('hidden', !hasSales || data.period !== 'day');
  document.getElementById('report-products-card').classList.toggle('hidden', !hasSales);

  document.getElementById('report-days-tbody').innerHTML = data.days.map(d => `
    <tr class="${REPORT_ROW}">
      <td class="px-5 py-3 text-sm font-medium text-gray-900 dark:text-white">${escapeHtml(reportDateText(d.date, { weekday: 'short', day: 'numeric', month: 'short' }))}</td>
      <td class="${REPORT_CELL}">${escapeHtml(String(d.orders))}</td>
      <td class="${REPORT_CELL}">${escapeHtml(String(d.items))}</td>
      <td class="${REPORT_CELL}">${escapeHtml(formatAnalyticsMoney(d.revenue))}</td>
    </tr>`).join('');

  document.getElementById('report-orders-tbody').innerHTML = data.orders.map(o => `
    <tr class="${REPORT_ROW}">
      <td class="px-5 py-3 text-sm font-medium text-gray-900 dark:text-white">${escapeHtml(o.number)}</td>
      <td class="${REPORT_CELL}">${escapeHtml(o.time)}</td>
      <td class="${REPORT_CELL}">${escapeHtml(ORDER_PAYMENT_LABEL[o.payment_method] || o.payment_method)}</td>
      <td class="px-3 py-3"><span class="${ORDER_STATUS_BADGE[o.status] || 'badge-low'} text-[10px] font-semibold px-2 py-0.5 rounded-full capitalize">${escapeHtml(o.status)}</span></td>
      <td class="${REPORT_CELL}">${escapeHtml(String(o.items))}</td>
      <td class="${REPORT_CELL}">${escapeHtml(formatAnalyticsMoney(o.total))}</td>
    </tr>`).join('');

  document.getElementById('report-products-tbody').innerHTML = data.products.map(p => `
    <tr class="${REPORT_ROW}">
      <td class="px-5 py-3 text-sm font-medium text-gray-900 dark:text-white">${escapeHtml(p.name)}</td>
      <td class="${REPORT_CELL}">${escapeHtml(p.brand)}</td>
      <td class="${REPORT_CELL}">${escapeHtml(String(p.quantity))}</td>
      <td class="${REPORT_CELL}">${escapeHtml(formatAnalyticsMoney(p.revenue))}</td>
    </tr>`).join('');
}

// After a failed request: no numbers, no tables (never stale or invented values).
function clearSalesReport() {
  ['report-orders', 'report-items', 'report-revenue', 'report-average'].forEach(id => {
    document.getElementById(id).textContent = '—';
  });
  ['report-empty', 'report-days-card', 'report-orders-card', 'report-products-card'].forEach(id => {
    document.getElementById(id).classList.add('hidden');
  });
  ['report-days-tbody', 'report-orders-tbody', 'report-products-tbody'].forEach(id => {
    document.getElementById(id).innerHTML = '';
  });
}

// The browser downloads the file (it sends the admin session cookie with the request).
function downloadSalesReportCsv() {
  window.location.href = `/admin/reports/sales/export?${reportQuery()}`;
}

function printSalesReport() {
  window.print();
}
