
/* ══════════════════════════════════════════════════════════════════
    CART  (localStorage, prices normalised to numbers)
══════════════════════════════════════════════════════════════════ */
const Cart = (() => {
  const KEY = 'novadrop_cart';
  let items = JSON.parse(localStorage.getItem(KEY) || '[]');

  function save() { localStorage.setItem(KEY, JSON.stringify(items)); }

  function add(product) {
    const existing = items.find(i => i.id === product.id);
    if (existing) {
      existing.qty++;
    } else {
      // B7 — normalise price to a number so maths always works
      const numericPrice = typeof product.price === 'string'
        ? parseFloat(product.price.replace(/[^0-9.]/g, ''))
        : Number(product.price);
      items.push({ ...product, price: numericPrice, qty: 1 });
    }
    save();
    renderCartBadge();
    showToast(`${product.name} added to cart`);
  }

  function remove(index) {
    if (!items[index]) return;
    if (items[index].qty > 1) {
      items[index].qty--;
    } else {
      items.splice(index, 1);
    }
    save();
    renderCartBadge();
  }

  function getCount() { return items.reduce((s, i) => s + i.qty, 0); }
  function getItems() { return [...items]; }
  function clear() { items = []; save(); renderCartBadge(); }

  return { add, remove, getCount, getItems, clear };
})();


// CART BADGE
function renderCartBadge() {
  const badge = document.getElementById('cart-badge'); // B2 — only one badge now
  if (!badge) return;
  const count = Cart.getCount();
  badge.textContent = count;
  badge.classList.toggle('hidden', count === 0);
}


//  TOAST
function showToast(msg) {
  let toast = document.getElementById('nd-toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'nd-toast';
    toast.className = [
      'fixed bottom-6 left-1/2 -translate-x-1/2 z-[200]',
      'bg-gray-900 text-white text-sm px-5 py-3 rounded-full shadow-lg',
      'transition-all duration-300 opacity-0 pointer-events-none'
    ].join(' ');
    document.body.appendChild(toast);
  }
  toast.textContent = msg;
  toast.classList.remove('opacity-0');
  toast.classList.add('opacity-100');
  clearTimeout(toast._timer);
  toast._timer = setTimeout(() => {
    toast.classList.remove('opacity-100');
    toast.classList.add('opacity-0');
  }, 2500);
}


function injectNav(activePage) {
  const pages = [
    { label: 'Home', href: 'index.html' },
    { label: 'Shop', href: 'shop.html' },
    { label: 'About', href: 'about.html' },
    { label: 'Contact', href: 'contact.html' },
  ];

  const links = pages.map(p => {
    const active = p.label.toLowerCase() === activePage;
    return `<a href="${p.href}"
      class="text-sm font-medium transition-colors ${active
        ? 'text-gray-900 border-b-2 border-gray-900 pb-0.5'
        : 'text-gray-500 hover:text-gray-900'}">${p.label}</a>`;
  }).join('');

  const nav = document.getElementById('nd-nav');
  if (!nav) return;

  nav.innerHTML = `
    <div class="max-w-7xl mx-auto px-5 h-16 flex items-center justify-between">

      <a href="index.html" class="font-display font-bold text-xl tracking-tight text-gray-900">
        ICT<span class="text-indigo-500">Tech</span>
      </a>

      <div class="hidden md:flex items-center gap-8">${links}</div>

      <div class="flex items-center gap-2">

        <!-- Cart — B2: only this button gets cart-badge -->
        <button onclick="toggleCart()" aria-label="Open cart"
          class="relative p-2 rounded-xl hover:bg-gray-100 transition-colors">
          <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
              d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
          </svg>
          <span id="cart-badge"
            class="hidden absolute -top-1 -right-1 bg-indigo-500 text-white text-[10px] font-bold w-4 h-4 rounded-full flex items-center justify-center">0</span>
        </button>

        <!-- User / Auth — B2: NO duplicate cart-badge here -->
        <button onclick="toggleAuth()" aria-label="Login or register"
          class="p-2 rounded-xl hover:bg-gray-100 transition-colors">
          <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
              d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4z
                M12 14c-3.31 0-6 2.69-6 6h12c0-3.31-2.69-6-6-6z"/>
          </svg>
        </button>

        <!-- Mobile hamburger -->
        <button onclick="toggleMobileMenu()" aria-label="Toggle menu"
          class="md:hidden p-2 rounded-xl hover:bg-gray-100 transition-colors">
          <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
      </div>
    </div>

    <!-- Mobile menu -->
    <div id="mobile-menu"
      class="hidden md:hidden border-t border-gray-100 bg-white/90 backdrop-blur-md px-5 py-4 flex flex-col gap-3">
      ${pages.map(p => `
        <a href="${p.href}"
          class="text-sm font-medium py-1.5 transition-colors ${p.label.toLowerCase() === activePage ? 'text-gray-900' : 'text-gray-500 hover:text-gray-900'
    }">${p.label}</a>`).join('')}
    </div>
  `;

  renderCartBadge();
}

function toggleMobileMenu() {
  const menu = document.getElementById('mobile-menu');
  if (menu) menu.classList.toggle('hidden');
}


// CART DRAWER
function injectCartDrawer() {
  if (document.getElementById('cart-drawer')) return;
  const el = document.createElement('div');
  el.id = 'cart-drawer-wrapper';
  el.innerHTML = `
    <div id="cart-overlay" onclick="toggleCart()"
      class="fixed inset-0 bg-black/20 backdrop-blur-sm z-[88] hidden"></div>
    <div id="cart-drawer"
      class="fixed top-0 right-0 h-full w-full sm:w-[400px] bg-white/95 backdrop-blur-xl z-[89] shadow-2xl
              translate-x-full transition-transform duration-300 flex flex-col">
      <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100">
        <h2 class="font-display font-bold text-lg text-gray-900">Your Cart</h2>
        <button onclick="toggleCart()" aria-label="Close cart"
          class="p-2 rounded-xl hover:bg-gray-100 transition-colors">
          <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
      <div id="cart-items"  class="flex-1 overflow-y-auto px-6 py-4"></div>
      <div id="cart-footer" class="px-6 py-5 border-t border-gray-100"></div>
    </div>`;
  document.body.appendChild(el);
}

let cartOpen = false;
function toggleCart() {
  if (authOpen) toggleAuth(); // B9 — close auth before opening cart
  cartOpen = !cartOpen;
  const drawer = document.getElementById('cart-drawer');
  const overlay = document.getElementById('cart-overlay');
  if (!drawer) return;
  drawer.classList.toggle('translate-x-full', !cartOpen);
  overlay.classList.toggle('hidden', !cartOpen);
  renderCartDrawer();
}

/* ── Cart render ─────────────────────────────────────────────────── */
function renderCartDrawer() {
  const items = Cart.getItems();
  const itemsEl = document.getElementById('cart-items');
  const footerEl = document.getElementById('cart-footer');
  if (!itemsEl) return;

  if (items.length === 0) {
    itemsEl.innerHTML = `
      <div class="flex flex-col items-center justify-center h-full text-center py-16">
        <div class="text-5xl mb-4">🛍️</div>
        <p class="text-gray-500 text-sm">Your cart is empty.<br/>Start shopping to add items.</p>
        <a href="shop.html" onclick="toggleCart()"
          class="mt-5 bg-indigo-500 text-white text-sm font-semibold px-5 py-2.5 rounded-full hover:bg-indigo-600 transition-colors">
          Browse Shop
        </a>
      </div>`;
    footerEl.innerHTML = '';
    return;
  }

  const total = items.reduce((sum, i) => sum + (Number(i.price) || 0) * i.qty, 0);

  itemsEl.innerHTML = items.map((item, index) => {
    // B8 — use item.image (URL) not item.emoji (undefined on tech products)
    const categoryIcon =
      item.category === 'phones' ? '📱' :
        item.category === 'earphones' ? '🎧' :
          item.category === 'powerbanks' ? '🔋' :
            item.category === 'laptops' ? '💻' : '📦';

    const thumb = item.image
      ? `<img src="${item.image}" alt="${item.imageAlt || item.name}"
           class="w-full h-full object-cover object-center rounded-xl"
           onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
         <span class="hidden w-full h-full items-center justify-center text-2xl">${categoryIcon}</span>`
      : `<span class="text-2xl">${categoryIcon}</span>`;

    return `
      <div class="flex items-center gap-4 py-4 border-b border-gray-50 last:border-0">
        <div class="w-16 h-16 rounded-xl bg-gray-50 border border-gray-100 overflow-hidden flex-shrink-0 flex items-center justify-center">
          ${thumb}
        </div>
        <div class="flex-1 min-w-0">
          <p class="font-medium text-sm text-gray-900 truncate">${item.name}</p>
          <p class="text-gray-400 text-[10px] uppercase tracking-wider">${item.brand}</p>
          <p class="text-indigo-600 font-semibold text-sm mt-0.5">
            $${Number(item.price).toLocaleString()}
          </p>
        </div>
        <!-- +/- controls -->
        <div class="flex items-center gap-1 bg-gray-50 rounded-lg p-1 border border-gray-100 flex-shrink-0">
          <button onclick="Cart.remove(${index}); renderCartDrawer();" aria-label="Decrease quantity"
            class="w-7 h-7 flex items-center justify-center rounded-md hover:bg-white hover:shadow-sm text-gray-600 transition-all active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
            </svg>
          </button>
          <span class="text-sm font-bold text-gray-700 w-5 text-center">${item.qty}</span>
          <button onclick="Cart.add(Cart.getItems()[${index}]); renderCartDrawer();" aria-label="Increase quantity"
            class="w-7 h-7 flex items-center justify-center rounded-md hover:bg-white hover:shadow-sm text-gray-600 transition-all active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
          </button>
        </div>
      </div>`;
  }).join('');

  const shippingNote = total >= 75
    ? `<p class="text-xs text-green-600 font-medium mb-3">✅ Free shipping applied!</p>`
    : `<p class="text-xs text-gray-400 mb-3">Add $${(75 - total).toFixed(2)} more for free shipping.</p>`;

  footerEl.innerHTML = `
    <div class="flex justify-between items-end mb-1">
      <span class="text-gray-500 text-sm">Subtotal</span>
      <span class="font-bold text-gray-900 text-xl">
        $${total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
      </span>
    </div>
    ${shippingNote}
    <button onclick="Cart.clear(); renderCartDrawer(); showToast('Checkout initialised!');"
      class="w-full bg-indigo-500 hover:bg-indigo-600 text-white font-semibold py-3.5 rounded-2xl text-sm transition-all active:scale-[0.98]">
      Proceed to Checkout
    </button>`;
}


// AUTH DRAWER
function injectAuthDrawer() {
  if (document.getElementById('auth-drawer')) return;
  const el = document.createElement('div');
  el.id = 'auth-drawer-wrapper';
  el.innerHTML = `
    <div id="auth-overlay" onclick="toggleAuth()"
      class="fixed inset-0 bg-black/30 backdrop-blur-sm z-[90] hidden"></div>
    <div id="auth-drawer"
      class="fixed top-0 right-0 h-full w-full sm:w-[380px] bg-white/95 backdrop-blur-xl z-[91] shadow-2xl
              translate-x-full transition-transform duration-300 flex flex-col">
      <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100">
        <h2 id="auth-drawer-title" class="font-bold text-lg text-gray-900">Login</h2>
        <button onclick="toggleAuth()" aria-label="Close"
          class="p-2 rounded-xl hover:bg-gray-100 transition-colors">
          <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
      <div id="auth-drawer-body" class="flex-1 overflow-y-auto p-6"></div>
    </div>`;
  document.body.appendChild(el);
}

let authOpen = false;
function toggleAuth() {
  if (cartOpen) toggleCart();
  authOpen = !authOpen;
  const drawer = document.getElementById('auth-drawer');
  const overlay = document.getElementById('auth-overlay');
  if (!drawer) return;
  drawer.classList.toggle('translate-x-full', !authOpen);
  overlay.classList.toggle('hidden', !authOpen);
  if (authOpen) renderAuthDrawer();
}

function renderAuthDrawer() {
  document.getElementById('auth-drawer-title').textContent = 'Login';
  const body = document.getElementById('auth-drawer-body');
  if (!body) return;
  body.innerHTML = `
    <div class="flex flex-col gap-4">
      <div id="auth-message" class="hidden text-sm rounded-xl px-4 py-2.5"></div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Email</label>
        <input id="auth-email" type="email" placeholder="you@example.com"
          class="w-full p-3 border border-gray-200 rounded-xl text-sm outline-none
                focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 transition-all"/>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Password</label>
        <input id="auth-password" type="password" placeholder="••••••••"
          class="w-full p-3 border border-gray-200 rounded-xl text-sm outline-none
                focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 transition-all"
          onkeydown="if(event.key==='Enter') handleLogin()"/>
      </div>
      <button onclick="handleLogin()"
        class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-xl text-sm transition-colors">
        Login
      </button>
      <p class="text-sm text-center text-gray-500">
        Don't have an account?
        <a href="#" onclick="showRegisterPanel(event)"
          class="text-indigo-500 font-medium hover:underline">Register</a>
      </p>
    </div>`;
}

function showRegisterPanel(e) {
  e.preventDefault();
  document.getElementById('auth-drawer-title').textContent = 'Create account';
  const body = document.getElementById('auth-drawer-body');
  if (!body) return;
  body.innerHTML = `
    <div class="flex flex-col gap-4">
      <div id="auth-message" class="hidden text-sm rounded-xl px-4 py-2.5"></div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Full name</label>
        <input id="auth-name" type="text" placeholder="Your name"
          class="w-full p-3 border border-gray-200 rounded-xl text-sm outline-none
                focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 transition-all"/>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Email</label>
        <input id="auth-email" type="email" placeholder="you@example.com"
          class="w-full p-3 border border-gray-200 rounded-xl text-sm outline-none
                focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 transition-all"/>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Password</label>
        <input id="auth-password" type="password" placeholder="••••••••"
          class="w-full p-3 border border-gray-200 rounded-xl text-sm outline-none
                focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 transition-all"
          onkeydown="if(event.key==='Enter') handleRegister()"/>
      </div>
      <button onclick="handleRegister()"
        class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-xl text-sm transition-colors">
        Create account
      </button>
      <p class="text-sm text-center text-gray-500">
        Already have an account?
        <a href="#" onclick="renderAuthDrawer(); event.preventDefault()"
          class="text-indigo-500 font-medium hover:underline">Login</a>
      </p>
    </div>`;
}
const CREDENTIALS = { email: 'admin@gmail.com', password: 'admin123' };
function handleLogin() {
  const email = document.getElementById('auth-email')?.value.trim();
  const password = document.getElementById('auth-password')?.value;
  if (!email || !password) { _authMsg('Please fill in both fields.', 'error'); return; }
  if (email == CREDENTIALS.email || password == CREDENTIALS.password) {
    sessionStorage.setItem('nd_admin', 'true');
    sessionStorage.setItem('nd_admin_user', email);
    window.location.href = 'admin.html';
  } else {
    _authMsg('Invalid credentials.', 'error');
    document.getElementById('auth-password').value = '';
  }
}

function handleRegister() {
  const name = document.getElementById('auth-name')?.value.trim();
  const email = document.getElementById('auth-email')?.value.trim();
  const password = document.getElementById('auth-password')?.value;
  if (!name || !email || !password) { _authMsg('Please fill in all fields.', 'error'); return; }
  _authMsg(`Account created! Welcome, ${name} 🎉`, 'success');
  setTimeout(() => toggleAuth(), 1400);
}

function _authMsg(msg, type) {
  const el = document.getElementById('auth-message');
  if (!el) return;
  el.textContent = msg;
  el.className = `text-sm rounded-xl px-4 py-2.5 ${type === 'success'
      ? 'bg-green-50 text-green-700 border border-green-100'
      : 'bg-red-50 text-red-600 border border-red-100'
    }`;
  el.classList.remove('hidden');
}

// INIT — single DOMContentLoaded, no duplicates (fixes B4)

document.addEventListener('DOMContentLoaded', () => {
  injectCartDrawer();
  injectAuthDrawer();
  renderCartBadge();
});