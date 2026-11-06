/* ══════════════════════════════════════════════
   Sales Analytics — dashboard module (real orders, last 14 days)
   Data comes from GET /admin/analytics/summary, which returns
     { labels: 'Y-m-d'[14], orders: number[14], revenue: number[14] }
   Cancelled orders are already excluded by the server. When the request
   fails the dashboard says so — it never shows made-up numbers.
══════════════════════════════════════════════ */

async function fetchAnalyticsData() {
  const res = await fetch('/admin/analytics/summary', { headers: { 'Accept': 'application/json' } });
  if (!res.ok) throw new Error(`Analytics request failed (${res.status})`);
  const data = await res.json();

  const labels = data.labels.map(iso => {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('en-US', { month: 'long', day: 'numeric' });
  });
  return { labels, orders: data.orders, revenue: data.revenue };
}

function computeAnalyticsSummary({ orders, revenue }) {
  const totalOrders = orders.reduce((a, b) => a + b, 0);
  // Add whole cents so the dollar total doesn't pick up float noise.
  const totalRevenue = revenue.reduce((a, b) => a + Math.round(b * 100), 0) / 100;
  const averageOrder = totalOrders > 0 ? totalRevenue / totalOrders : 0;
  return { totalOrders, totalRevenue, averageOrder };
}

function formatAnalyticsMoney(amount) {
  return '$' + amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function renderAnalyticsKpis({ totalOrders, totalRevenue, averageOrder }) {
  document.getElementById('kpi-orders').textContent = totalOrders.toLocaleString();
  document.getElementById('kpi-revenue').textContent = formatAnalyticsMoney(totalRevenue);
  document.getElementById('kpi-average').textContent = formatAnalyticsMoney(averageOrder);
}

let analyticsChart = null;
function renderAnalyticsChart({ labels, orders, revenue }) {
  const canvas = document.getElementById('analytics-chart');
  if (!canvas || typeof Chart === 'undefined') return;

  const isDark = document.documentElement.classList.contains('dark');
  const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.05)';
  const tickColor = isDark ? '#d1d5db' : '#6b7280';

  if (analyticsChart) analyticsChart.destroy();
  analyticsChart = new Chart(canvas, {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label: 'Orders',
          data: orders,
          yAxisID: 'y',
          borderColor: '#6366f1',
          backgroundColor: 'rgba(99,102,241,0.12)',
          tension: 0.35,
          fill: true,
          pointRadius: 3,
          pointHoverRadius: 5,
          borderWidth: 2
        },
        {
          label: 'Order value ($)',
          data: revenue,
          yAxisID: 'y1',
          borderColor: '#22c55e',
          backgroundColor: 'rgba(34,197,94,0.12)',
          tension: 0.35,
          fill: false,
          pointRadius: 3,
          pointHoverRadius: 5,
          borderWidth: 2
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: { backgroundColor: '#111827', padding: 10, cornerRadius: 8, boxPadding: 4 }
      },
      scales: {
        x: { grid: { display: false }, ticks: { color: tickColor, font: { size: 10 } } },
        y: {
          position: 'left',
          beginAtZero: true,
          grid: { color: gridColor },
          ticks: { color: tickColor, font: { size: 10 }, precision: 0 }
        },
        y1: {
          position: 'right',
          beginAtZero: true,
          grid: { drawOnChartArea: false },
          ticks: { color: tickColor, font: { size: 10 }, callback: v => '$' + v }
        }
      }
    }
  });
}

function setAnalyticsUnavailable(unavailable) {
  const canvas = document.getElementById('analytics-chart');
  const message = document.getElementById('analytics-unavailable');
  if (canvas) canvas.classList.toggle('hidden', unavailable);
  if (message) message.classList.toggle('hidden', !unavailable);
}

let lastAnalyticsData = null;
async function initAnalyticsDashboard() {
  let data;
  try {
    data = await fetchAnalyticsData();
  } catch (e) {
    lastAnalyticsData = null;
    if (analyticsChart) { analyticsChart.destroy(); analyticsChart = null; }
    setAnalyticsUnavailable(true);
    ['kpi-orders', 'kpi-revenue', 'kpi-average'].forEach(id => {
      document.getElementById(id).textContent = '—';
    });
    return;
  }

  lastAnalyticsData = data;
  setAnalyticsUnavailable(false);
  renderAnalyticsKpis(computeAnalyticsSummary(data));
  renderAnalyticsChart(data);
}

window.refreshAnalyticsTheme = function () {
  if (lastAnalyticsData) renderAnalyticsChart(lastAnalyticsData);
};

initAnalyticsDashboard();
