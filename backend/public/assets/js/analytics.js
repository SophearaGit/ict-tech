/* ══════════════════════════════════════════════
   Visitor & Sales Analytics — dashboard module
   Data layer is isolated in fetchAnalyticsData().
   Swap its body for a real API call once the PHP/Laravel
   backend is ready, e.g.:
     const res = await fetch('/api/analytics/summary');
     return await res.json();
   Expected shape: { labels: string[], visitors: number[], purchases: number[] }
══════════════════════════════════════════════ */

async function fetchAnalyticsData() {
  const visitors  = [200, 250, 300, 280, 320, 400, 380, 350, 420, 390, 450, 470, 430, 500];
  const purchases = [20, 30, 25, 28, 35, 45, 40, 38, 48, 42, 50, 55, 47, 60];

  const labels = [];
  const start = new Date();
  start.setDate(start.getDate() - (visitors.length - 1));
  for (let i = 0; i < visitors.length; i++) {
    const d = new Date(start);
    d.setDate(d.getDate() + i);
    labels.push(d.toLocaleDateString('en-US', { month: 'long', day: 'numeric' }));
  }

  return { labels, visitors, purchases };
}

function computeAnalyticsSummary({ visitors, purchases }) {
  const totalVisitors = visitors.reduce((a, b) => a + b, 0);
  const totalPurchases = purchases.reduce((a, b) => a + b, 0);
  const conversionRate = totalVisitors > 0 ? (totalPurchases / totalVisitors) * 100 : 0;
  return { totalVisitors, totalPurchases, conversionRate };
}

function renderAnalyticsKpis({ totalVisitors, totalPurchases, conversionRate }) {
  document.getElementById('kpi-visitors').textContent = totalVisitors.toLocaleString();
  document.getElementById('kpi-purchases').textContent = totalPurchases.toLocaleString();
  document.getElementById('kpi-conversion').textContent = conversionRate.toFixed(1) + '%';
}

let analyticsChart = null;
function renderAnalyticsChart({ labels, visitors, purchases }) {
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
          label: 'Visitors',
          data: visitors,
          borderColor: '#6366f1',
          backgroundColor: 'rgba(99,102,241,0.12)',
          tension: 0.35,
          fill: true,
          pointRadius: 3,
          pointHoverRadius: 5,
          borderWidth: 2
        },
        {
          label: 'Purchases',
          data: purchases,
          borderColor: '#22c55e',
          backgroundColor: 'rgba(34,197,94,0.12)',
          tension: 0.35,
          fill: true,
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
        y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, font: { size: 10 } } }
      }
    }
  });
}

let lastAnalyticsData = null;
async function initAnalyticsDashboard() {
  const data = await fetchAnalyticsData();
  lastAnalyticsData = data;
  renderAnalyticsKpis(computeAnalyticsSummary(data));
  renderAnalyticsChart(data);
}

window.refreshAnalyticsTheme = function () {
  if (lastAnalyticsData) renderAnalyticsChart(lastAnalyticsData);
};

initAnalyticsDashboard();
