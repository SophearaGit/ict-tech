/* ── Auth guard ── */
if (sessionStorage.getItem('nd_admin') !== 'true') {
  window.location.href = 'admin-login.html';
}
document.getElementById('admin-name').textContent = sessionStorage.getItem('nd_admin_user') || 'Admin';

/* ── Default product seed (from shop.js data, now with qty) ── */
const PRODUCTS = [

  /* ─────────────── PHONES ─────────────── */
  {
    id: "ph-01",
    name: "iPhone 15 Pro Max",
    qty: 3,
    brand: "Apple",
    type: "Smartphone",
    category: "phones",
    price: 1199,
    desc: "Titanium design with A17 Pro chip, 48MP ProRAW camera system, and USB-C.",
    specs: ["A17 Pro", "48MP", "USB-C", "Titanium"],
    imageAlt: "iPhone 15 Pro Max in natural titanium finish on a clean white surface",
    trending: true,
    badge: "New"
  },
  {
    id: "ph-02",
    name: "iPhone 14",
    qty: 2,
    brand: "Apple",
    type: "Smartphone",
    category: "phones",
    price: 799,
    desc: "Supercharged A15 Bionic chip, Crash Detection, and all-day battery life.",
    specs: ["A15 Bionic", "12MP", "5G", "Face ID"],
    image: "https://images.unsplash.com/photo-1667372335937-d03be6fb0a9c?w=600&q=80",
    imageAlt: "iPhone 14 in midnight color displayed on gradient background",
    trending: false,
    badge: null
  },
  {
    id: "ph-03",
    name: "Galaxy S24 Ultra",
    qty: 2,
    brand: "Samsung",
    type: "Smartphone",
    category: "phones",
    price: 1299,
    desc: "200MP camera, built-in S Pen, Snapdragon 8 Gen 3, and 5000mAh battery.",
    specs: ["200MP", "S Pen", "Snapdragon 8 Gen 3", "AI"],
    image: "https://images.unsplash.com/photo-1706220612290-b9b8ce0a6f59?w=600&q=80",
    imageAlt: "Samsung Galaxy S24 Ultra with S Pen against dark background",
    trending: true,
    badge: "Hot"
  },
  {
    id: "ph-04",
    name: "Galaxy A54 5G",
    qty: 2,
    brand: "Samsung",
    type: "Smartphone",
    category: "phones",
    price: 449,
    desc: "50MP OIS camera, 5000mAh battery, and IP67 water resistance at a great price.",
    specs: ["50MP OIS", "5000mAh", "IP67", "5G"],
    image: "https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?w=600&q=80",
    imageAlt: "Samsung Galaxy A54 5G in awesome violet color",
    trending: false,
    badge: "Sale"
  },
  {
    id: "ph-05",
    name: "Xiaomi 14 Ultra",
    qty: 2,
    brand: "Xiaomi",
    type: "Smartphone",
    category: "phones",
    price: 999,
    desc: "Leica Summilux optics, 1-inch Sony sensor, 90W HyperCharge, Snapdragon 8 Gen 3.",
    specs: ["Leica optics", "1\" sensor", "90W charge", "Snapdragon 8 Gen 3"],
    image: "https://images.unsplash.com/photo-1585060544812-6b45742d762f?w=600&q=80",
    imageAlt: "Xiaomi flagship smartphone showing camera module on clean background",
    trending: true,
    badge: "New"
  },
  {
    id: "ph-06",
    name: "Xiaomi Redmi Note 13 Pro",
    qty: 2,
    brand: "Xiaomi",
    type: "Smartphone",
    category: "phones",
    price: 329,
    desc: "200MP camera, 5000mAh, 67W fast charge — exceptional mid-range value.",
    specs: ["200MP", "5000mAh", "67W", "AMOLED"],
    image: "https://images.unsplash.com/photo-1574944985070-8f3ebc6b79d2?w=600&q=80",
    imageAlt: "Xiaomi Redmi smartphone with gradient back design",
    trending: false,
    badge: null
  },

  /* ─────────────── EARPHONES / HEADPHONES ─────────────── */
  {
    id: "ep-01",
    name: "AirPods Pro 2",
    qty: 2,
    brand: "Apple",
    type: "TWS Earbuds",
    category: "earphones",
    price: 249,
    desc: "Adaptive Audio, H2 chip, up to 30 hours total listening with case charging.",
    specs: ["Adaptive ANC", "H2 chip", "30hr total", "MagSafe case"],
    image: "https://images.unsplash.com/photo-1603351154351-5e2d0600bb77?w=600&q=80",
    imageAlt: "Apple AirPods Pro 2 in white charging case on light background",
    trending: true,
    badge: "Best Seller"
  },
  {
    id: "ep-02",
    name: "AirPods Max",
    qty: 2,
    brand: "Apple",
    type: "Over-Ear Headphones",
    category: "earphones",
    price: 549,
    desc: "Computational audio, custom acoustic mesh canopy, up to 20-hour battery.",
    specs: ["Spatial Audio", "ANC", "20hr battery", "Aluminium cups"],
    image: "https://images.unsplash.com/photo-1613040809024-b4ef7ba99bc3?w=600&q=80",
    imageAlt: "Apple AirPods Max over-ear headphones in silver on a minimal background",
    trending: false,
    badge: null
  },
  {
    id: "ep-03",
    name: "Galaxy Buds3 Pro",
    qty: 2,
    brand: "Samsung",
    type: "TWS Earbuds",
    category: "earphones",
    price: 229,
    desc: "Blade-shaped fit, 360° Audio, intelligent ANC adapts to your environment.",
    specs: ["360° Audio", "ANC", "30hr total", "Galaxy AI"],
    image: "https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=600&q=80",
    imageAlt: "Samsung Galaxy Buds in small charging case on colored background",
    trending: true,
    badge: "New"
  },
  {
    id: "ep-04",
    name: "Sony WH-1000XM5",
    qty: 2,
    brand: "Sony",
    type: "Over-Ear Headphones",
    category: "earphones",
    price: 349,
    desc: "Industry-leading noise cancellation with 8 mics, 30-hour battery, multipoint.",
    specs: ["8-mic ANC", "30hr", "LDAC", "Multipoint"],
    image: "https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&q=80",
    imageAlt: "Sony WH-1000XM5 over-ear headphones in black on a white surface",
    trending: true,
    badge: "Top Rated"
  },
  {
    id: "ep-05",
    name: "Sony WF-1000XM5",
    qty: 2,
    brand: "Sony",
    type: "TWS Earbuds",
    category: "earphones",
    price: 299,
    desc: "World's smallest ANC earbuds with QN2e chip, LDAC, and 36-hour total life.",
    specs: ["QN2e chip", "LDAC", "36hr total", "IPX4"],
    image: "https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?w=600&q=80",
    imageAlt: "Sony WF-1000XM5 earbuds with charging case on gradient background",
    trending: false,
    badge: null
  },
  {
    id: "ep-06",
    name: "Anker Soundcore Q45",
    qty: 2,
    brand: "Anker",
    type: "Over-Ear Headphones",
    category: "earphones",
    price: 79,
    desc: "Adaptive ANC, 50-hour playtime, Hi-Res Audio — premium sound at a budget price.",
    specs: ["Adaptive ANC", "50hr", "Hi-Res", "Foldable"],
    image: "https://images.unsplash.com/photo-1484704849700-f032a568e944?w=600&q=80",
    imageAlt: "Anker wireless headphones in black on a wood table",
    trending: false,
    badge: "Value Pick"
  },
  {
    id: "ep-07",
    name: "Anker Soundcore Liberty 4",
    qty: 2,
    brand: "Anker",
    type: "TWS Earbuds",
    category: "earphones",
    price: 99,
    desc: "LDAC Hi-Res wireless, spatial audio, heart-rate monitoring, 9hr single charge.",
    specs: ["LDAC", "Spatial Audio", "Heart Rate", "9hr"],
    image: "https://images.unsplash.com/photo-1558089687-f282ffcbc126?w=600&q=80",
    imageAlt: "Anker Soundcore Liberty earbuds in charging case",
    trending: true,
    badge: null
  },

  /* ─────────────── POWERBANKS ─────────────── */
  {
    id: "pb-01",
    name: "Anker 733 GaNPrime",
    qty: 2,
    brand: "Anker",
    type: "Power Bank",
    category: "powerbanks",
    price: 89,
    desc: "65W GaN charger + 10,000mAh bank in one compact device. Charge laptop and phone simultaneously.",
    specs: ["65W PD", "10,000mAh", "GaN", "2-in-1"],
    image: "https://images.unsplash.com/photo-1609091839311-d5365f9ff1c5?w=600&q=80",
    imageAlt: "Anker GaN power bank charger in black on a desk",
    trending: true,
    badge: "Best Seller"
  },
  {
    id: "pb-02",
    name: "Anker 737 Power Bank",
    qty: 2,
    brand: "Anker",
    type: "Power Bank",
    category: "powerbanks",
    price: 129,
    desc: "26,800mAh with 140W output — recharge your MacBook in under 2 hours.",
    specs: ["140W output", "26,800mAh", "2× USB-C", "Smart display"],
    image: "https://images.unsplash.com/photo-1563208958-71f7b485dd77?w=600&q=80",
    imageAlt: "Large capacity Anker power bank with digital display",
    trending: false,
    badge: null
  },
  {
    id: "pb-03",
    name: "Xiaomi 33W Power Bank 20000",
    qty: 2,
    brand: "Xiaomi",
    type: "Power Bank",
    category: "powerbanks",
    price: 39,
    desc: "20,000mAh, 33W two-way fast charge, dual USB-A + USB-C output.",
    specs: ["33W", "20,000mAh", "Dual output", "USB-C in/out"],
    image: "https://images.unsplash.com/photo-1585771724684-38269d6639fd?w=600&q=80",
    imageAlt: "Xiaomi slim power bank in white on a light background",
    trending: false,
    badge: "Sale"
  },
  {
    id: "pb-04",
    name: "Xiaomi 10,000 PD 22.5W",
    qty: 2,
    brand: "Xiaomi",
    type: "Power Bank",
    category: "powerbanks",
    price: 25,
    desc: "Pocket-sized 10,000mAh with 22.5W fast charging for phones and earbuds.",
    specs: ["22.5W", "10,000mAh", "Slim design", "LED indicator"],
    image: "https://images.unsplash.com/photo-1609091839311-d5365f9ff1c5?w=600&q=80",
    imageAlt: "Slim Xiaomi power bank showing LED charge indicator",
    trending: true,
    badge: null
  },
  {
    id: "pb-05",
    name: "Jtrust 20000mAh Pro",
    qty: 2,
    brand: "Jtrust",
    type: "Power Bank",
    category: "powerbanks",
    price: 45,
    desc: "20,000mAh with triple output ports, intelligent charge distribution and safety protection.",
    specs: ["20,000mAh", "3 outputs", "Auto-detect", "Safety OCP"],
    image: "https://images.unsplash.com/photo-1563208958-71f7b485dd77?w=600&q=80",
    imageAlt: "Jtrust power bank with multiple ports on neutral background",
    trending: false,
    badge: null
  },
  {
    id: "pb-06",
    name: "Jtrust Solar Power Bank 10000",
    qty: 2,
    brand: "Jtrust",
    type: "Solar Power Bank",
    category: "powerbanks",
    price: 35,
    desc: "Dual solar panels + 10,000mAh, waterproof IPX5, perfect for outdoors and travel.",
    specs: ["Solar panels", "10,000mAh", "IPX5", "LED torch"],
    image: "https://images.unsplash.com/photo-1616348436168-de43ad0db179?w=600&q=80",
    imageAlt: "Solar power bank with panel visible on outdoor background",
    trending: false,
    badge: "Eco"
  },

  /* ─────────────── LAPTOPS ─────────────── */
  {
    id: "lt-01",
    name: "MacBook Pro 14\" M3 Pro",
    qty: 3,
    brand: "Apple",
    type: "Laptop",
    category: "laptops",
    price: 1999,
    desc: "M3 Pro chip with 18GB memory, 18-hour battery, Liquid Retina XDR display.",
    specs: ["M3 Pro", "18GB RAM", "512GB SSD", "18hr battery"],
    image: "https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=600&q=80",
    imageAlt: "MacBook Pro 14 inch open on a clean desk showing Retina display",
    trending: true,
    badge: "New"
  },
  {
    id: "lt-02",
    name: "MacBook Air 15\" M3",
    qty: 2,
    brand: "Apple",
    type: "Laptop",
    category: "laptops",
    price: 1299,
    desc: "Fanless M3 design, 15.3\" Liquid Retina, up to 18-hour battery in 1.51kg.",
    specs: ["M3 chip", "8GB RAM", "256GB SSD", "Fanless"],
    image: "https://images.unsplash.com/photo-1611186871348-b1ce696e52c9?w=600&q=80",
    imageAlt: "MacBook Air 15 inch in starlight finish on a minimalist desk",
    trending: true,
    badge: null
  },
  {
    id: "lt-03",
    name: "ASUS ROG Zephyrus G16",
    qty: 3,
    brand: "Asus",
    type: "Gaming Laptop",
    category: "laptops",
    price: 2499,
    desc: "RTX 4090, Intel Core Ultra 9, 240Hz OLED display, MUX Switch, 16\" powerhouse.",
    specs: ["RTX 4090", "Core Ultra 9", "240Hz OLED", "32GB DDR5"],
    image: "https://images.unsplash.com/photo-1593642632559-0c6d3fc62b89?w=600&q=80",
    imageAlt: "ASUS ROG gaming laptop with RGB keyboard backlight on dark surface",
    trending: true,
    badge: "Hot"
  },
  {
    id: "lt-04",
    name: "ASUS ZenBook 14 OLED",
    qty: 2,
    brand: "Asus",
    type: "Ultrabook",
    category: "laptops",
    price: 899,
    desc: "2.8K OLED display, Intel Core Ultra 7, 32GB LPDDR5, all-day battery in 1.2kg.",
    specs: ["2.8K OLED", "Core Ultra 7", "32GB RAM", "1.2kg"],
    image: "https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=600&q=80",
    imageAlt: "ASUS ZenBook slim ultrabook open showing OLED display",
    trending: false,
    badge: null
  },
  {
    id: "lt-05",
    name: "Dell XPS 15 OLED",
    qty: 3,
    brand: "Dell",
    type: "Laptop",
    category: "laptops",
    price: 1799,
    desc: "3.5K OLED touchscreen, Intel Core i9, RTX 4060, CNC machined aluminium chassis.",
    specs: ["3.5K OLED", "Core i9", "RTX 4060", "32GB DDR5"],
    image: "https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?w=600&q=80",
    imageAlt: "Dell XPS 15 laptop with slim bezels and OLED display in use",
    trending: false,
    badge: null
  },
  {
    id: "lt-06",
    name: "Dell Inspiron 16 Plus",
    qty: 2,
    brand: "Dell",
    type: "Laptop",
    category: "laptops",
    price: 999,
    desc: "16\" 2.5K display, Intel Core i7, 16GB RAM — the reliable everyday powerhouse.",
    specs: ["2.5K display", "Core i7", "16GB RAM", "512GB SSD"],
    image: "https://images.unsplash.com/photo-1525547719571-a2d4ac8945e2?w=600&q=80",
    imageAlt: "Dell Inspiron 16 laptop open on a desk with bright display",
    trending: false,
    badge: "Sale"
  }
];


/* ── Storage helpers ── */
const LS_KEY = 'nd_products';
function loadProducts() {
  const raw = localStorage.getItem(LS_KEY);
  return raw ? JSON.parse(raw) : null;
}
function saveProducts(list) {
  localStorage.setItem(LS_KEY, JSON.stringify(list));
}

function initProducts() {
  if (!loadProducts()) saveProducts(PRODUCTS);
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
  initProducts();
  products = loadProducts();
  refreshAll();
}

function refreshAll() {
  products = loadProducts();
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
// MAINTAINING
/* ── Dashboard recent table ── */
function renderRecentTable() {
  const tbody = document.getElementById('recent-tbody');
  const recent = [...products].slice(-6).reverse();
  tbody.innerHTML = recent.map(p => `
    <tr class="trow border-b border-gray-50 last:border-0">
      <td class="px-5 py-3 flex items-center gap-3">
        <span class="text-sm font-medium text-gray-800 truncate max-w-[200px]">${p.name}</span>
      </td>
      <td class="px-3 py-3 text-sm font-semibold text-gray-900">$${p.price}</td>
      <td class="px-3 py-3 text-sm text-gray-600">${p.qty}</td>
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
    <tr class="trow border-b border-gray-50 last:border-0">
      <td class="px-5 py-3.5">
        <div class="flex items-center gap-3">
          <span class="text-base">${p.emoji || "📦"}</span>
          <div>
            <p class="text-sm font-medium text-gray-900">${p.name}</p>
            ${p.trending ? `<span class="text-[10px] text-indigo-500 font-semibold">🔥 Trending</span>` : ''}
          </div>
        </div>
      </td>
      <td class="px-3 py-3.5 text-sm text-gray-500 hidden sm:table-cell">${p.brand}</td>
      <td class="px-3 py-3.5 text-sm text-gray-500 hidden md:table-cell">${p.category}</td>
      <td class="px-3 py-3.5 text-sm font-semibold text-gray-900">$${p.price}</td>
      <td class="px-3 py-3.5">
        <div class="flex items-center gap-2">
          <span class="text-sm font-medium text-gray-700 w-8 text-right">${p.qty}</span>
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
    <tr class="trow border-b border-gray-50 last:border-0">
      <td class="px-5 py-3.5">
        <div class="flex items-center gap-2">
          <p class="text-sm font-medium text-gray-800">${p.name}</p>
        </div>
      </td>
      <td class="px-3 py-3.5 text-sm text-gray-500 hidden sm:table-cell">${p.brand}</td>
      <td class="px-3 py-3.5 text-sm font-bold text-gray-900">${p.qty}</td>
      <td class="px-3 py-3.5">${statusBadge(p)}</td>
      <td class="px-3 py-3.5 w-36 hidden md:table-cell">
        <div class="bg-gray-100 rounded-full h-2 overflow-hidden">
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
  p.qty = Math.max(0, p.qty + delta);
  saveProducts(products);
  refreshAll();
  toast(`${p.name} qty → ${p.qty}`);
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
  p.name = document.getElementById('m-name').value.trim() || p.name;
  p.price = parseFloat(document.getElementById('m-price').value) || p.price;
  p.qty = parseInt(document.getElementById('m-qty').value) || 0;
  p.desc = document.getElementById('m-desc').value.trim();
  p.trending = document.getElementById('m-trending').checked;
  saveProducts(products);
  refreshAll();
  closeModal();
  toast('Product updated successfully!');
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
  p.qty = newQty;
  saveProducts(products);
  refreshAll();
  closeRestockModal();
  toast(`✅ ${p.name} restocked to ${newQty} units`);
}

function showBulkRestock() {
  const outOfStock = products.filter(p => statusOf(p) === 'out');
  if (outOfStock.length === 0) { toast('No out-of-stock items to restock!'); return; }
  outOfStock.forEach(p => { p.qty = 20; });
  saveProducts(products);
  refreshAll();
  toast(`✅ Restocked ${outOfStock.length} items to 20 units each`);
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
  products = products.filter(x => x.id !== deleteId);
  saveProducts(products);
  refreshAll();
  closeDeleteModal();
  toast('Product deleted.');
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
}

function resetAddForm() {
  document.getElementById('edit-id').value = '';
  document.getElementById('f-name').value = '';
  document.getElementById('f-brand').value = '';
  document.getElementById('f-category').value = '';
  document.getElementById('f-price').value = '';
  document.getElementById('f-qty').value = '';
  document.getElementById('f-emoji').value = '👟';
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
  const emoji    = document.getElementById('f-emoji').value.trim() || '👟';
  const desc     = document.getElementById('f-desc').value.trim();
  const trending = document.getElementById('f-trending').checked;
  const errEl    = document.getElementById('form-error');

  if (!name || !brand || !category || isNaN(price) || isNaN(qty)) {
    errEl.textContent = 'Please fill in all required fields (name, brand, category, price, qty).';
    errEl.classList.remove('hidden');
    return;
  }
  errEl.classList.add('hidden');

  if (editingId) {
    const p = products.find(x => x.id === editingId);
    Object.assign(p, {name, brand, category, price, qty, emoji, desc, trending});
    toast('Product updated!');
  } else {
    const newId = 'p' + Date.now();
    products.push({id:newId, name, brand, category, price, qty, emoji, desc, trending});
    toast('Product added to catalog!');
  }

  saveProducts(products);
  refreshAll();
  resetAddForm();
  showView('products');
}

/* ── Sidebar toggle (mobile) ── */
function toggleSidebar() {
  const s = document.getElementById('sidebar');
  const o = document.getElementById('mob-overlay');
  s.classList.toggle('-translate-x-full');
  o.classList.toggle('hidden');
}
const sidebar = document.getElementById('sidebar-btn');
sidebar.addEventListener('click', toggleSidebar);
/* ── Toast ── */
function toast(msg) {
  const el = document.getElementById('admin-toast');
  el.innerHTML = `<div class="toast-enter bg-gray-900 text-white text-sm px-5 py-3 rounded-full shadow-lg">${msg}</div>`;
  setTimeout(() => el.innerHTML = '', 2800);
}

/* ── Logout ── */
function logout() {
  sessionStorage.removeItem('nd_admin');
  sessionStorage.removeItem('nd_admin_user');
  window.location.href = 'admin-login.html';
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


localStorage.removeItem('nd_products');
initProducts();
/* ── Boot ── */
init();