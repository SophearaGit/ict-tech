/* ─── NovaDrop Global Script ─────────────────────────────────────── */
/* ---------- Cart state (shared across pages) ---------- */
const Cart = (() => {
  const KEY = 'novadrop_cart';
  let items = JSON.parse(localStorage.getItem(KEY) || '[]');

  function save() { localStorage.setItem(KEY, JSON.stringify(items)); }

  function add(product) {
    const existing = items.find(i => i.name === product.name);
    if (existing) { existing.qty++; } else { items.push({ ...product, qty: 1 }); }
    save();
    renderCartBadge();
    showToast(`${product.name} added to cart`);
  }
  function remove(index) {
    if (items[index]) {
      if (items[index].qty > 1) {
        items[index].qty--;   // decrease quantity
      } else {
        items.splice(index, 1); // remove item completely
      }
      save();
      renderCartBadge();
    }
  }


  function getCount() { return items.reduce((s, i) => s + i.qty, 0); }

  function getItems() { return [...items]; }

  function clear() { items = []; save(); renderCartBadge(); }

  return { add, getCount, getItems, clear, remove };
})();

/* ---------- Cart badge ---------- */
function renderCartBadge() {
  const badge = document.getElementById('cart-badge');
  if (!badge) return;
  const count = Cart.getCount();
  badge.textContent = count;
  badge.classList.toggle('hidden', count === 0);
}

/* ---------- Toast ---------- */
function showToast(msg) {
  let toast = document.getElementById('nd-toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'nd-toast';
    toast.className = 'fixed bottom-6 left-1/2 -translate-x-1/2 z-[200] bg-gray-900 text-white text-sm px-5 py-3 rounded-full shadow-lg transition-all duration-300 opacity-0 pointer-events-none';
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

/* ---------- Inject Nav ---------- */
function injectNav(activePage) {
  const pages = [
    { label: 'Home',    href: 'index.html'   },
    { label: 'Shop',    href: 'shop.html'    },
    { label: 'About',   href: 'about.html'   },
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

      <!-- Logo -->
      <a href="index.html" class="font-display font-bold text-xl tracking-tight text-gray-900">
        ICT<span class="text-indigo-500">Tech</span>
      </a>

      <!-- Desktop links -->
      <div class="hidden md:flex items-center gap-8">${links}</div>

      <!-- Right side -->
      <div class="flex items-center gap-4">
        <!-- Cart -->
        <button id="cart-btn" onclick="toggleCart()"
          class="relative p-2 rounded-xl hover:bg-gray-100 transition-colors">
          <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
              d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
          </svg>
          <span id="cart-badge"
            class="hidden absolute -top-1 -right-1 bg-indigo-500 text-white text-[10px] font-bold w-4 h-4 rounded-full flex items-center justify-center">0</span>
        </button>
        <!-- User --> 
        <button id="user-btn" onclick="toggleAuth()"
          class="relative p-2 rounded-xl hover:bg-gray-100 transition-colors">
          <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <!-- User Icon -->
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
            d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 
            1.79-4 4 1.79 4 4 4zm0 2c-3.31 0-6 2.69-6 
            6h12c0-3.31-2.69-6-6-6z"/>
          </svg>
          <span id="cart-badge" class="hidden absolute -top-1 -right-1 bg-indigo-500 text-white text-[10px] font-bold w-4 h-4 rounded-full flex items-center justify-center">0</span>
        </button>
        <!-- Mobile menu button -->
        <button id="mobile-menu-btn" onclick="toggleMobileMenu()"
          class="md:hidden p-2 rounded-xl hover:bg-gray-100 transition-colors">
          <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
      </div>
    </div>

    <!-- Mobile menu -->
    <div id="mobile-menu" class="hidden md:hidden border-t border-gray-100 bg-white/90 backdrop-blur-md px-5 py-4 flex flex-col gap-3">
      ${pages.map(p => `<a href="${p.href}"
        class="text-sm font-medium py-1.5 ${p.label.toLowerCase()===activePage?'text-gray-900':'text-gray-500 hover:text-gray-900'} transition-colors">${p.label}</a>`).join('')}
    </div>
  `;

  renderCartBadge();
}

function toggleMobileMenu() {
  const menu = document.getElementById('mobile-menu');
  if (menu) menu.classList.toggle('hidden');
}

/* ---------- Cart Drawer ---------- */
function injectCartDrawer() {
  if (document.getElementById('cart-drawer')) return;
  const el = document.createElement('div');
  el.id = 'cart-drawer-wrapper';
  el.innerHTML = `
    <!-- Overlay -->
    <div id="cart-overlay" onclick="toggleCart()"
      class="fixed inset-0 bg-black/20 backdrop-blur-sm z-[90] hidden"></div>

    <!-- Drawer -->
    <div id="cart-drawer"
      class="fixed top-0 right-0 h-full w-full sm:w-[380px] bg-white/95 backdrop-blur-xl z-[100] shadow-2xl
      translate-x-full transition-transform duration-300 flex flex-col">
      <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100">
        <h2 class="font-display font-bold text-lg text-gray-900">Your Cart</h2>
        <button onclick="toggleCart()" class="p-2 rounded-xl hover:bg-gray-100 transition-colors">
          <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
      <div id="cart-items" class="flex-1 overflow-y-auto px-6 py-4"></div>
      <div id="cart-footer" class="px-6 py-5 border-t border-gray-100"></div>
    </div>
  `;
  document.body.appendChild(el);
}
function injectAuthDrawer() {
  if (document.getElementById('auth-drawer')) return;
  const el = document.createElement('div');
  el.id = 'auth-drawer-wrapper';
  el.innerHTML = `
    <!-- Overlay -->
    <div id="auth-overlay" onclick="toggleAuth()"
      class="fixed inset-0 bg-black/30 backdrop-blur-sm z-[90] hidden"></div>

    <!-- Drawer -->
    <div id="auth-drawer"
      class="fixed top-0 right-0 h-full w-full sm:w-[380px] bg-white/95 backdrop-blur-xl z-[100] shadow-2xl
      translate-x-full transition-transform duration-300 flex flex-col">
      <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100">
        <h2 class="font-bold text-lg text-gray-900">Login / Register</h2>
        <button id="close-auth" class="p-2 rounded-xl hover:bg-gray-100 transition-colors">
          <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
      <div class="p-6 flex flex-col gap-4">
        <input type="email" placeholder="Email" class="p-2 border rounded">
        <input type="password" placeholder="Password" class="p-2 border rounded">
        <button class="bg-indigo-600 text-white py-2 rounded hover:bg-indigo-800">Login</button>
        <p class="text-sm text-gray-500">Don’t have an account? <a href="#" onclick="openRegister()" class="text-blue-600">Register</a></p>
      </div>
    </div>
        <div id="register-box" class="fixed inset-0 bg-black/50 flex items-center justify-center">
        <div class="bg-white p-6 rounded-xl shadow-lg w-96">
            <div class="flex space-between justify-between items-center items-center ">
                <h2 class="text-xl font-bold mb-5 mt-4">Register</h2>
                <button onclick="closeRegister()" class="text-lg mb-3 mr-3">
                    ❌
                </button>
            </div>
            <form>
                <input type="text" placeholder="Username" class="w-full border p-2 mb-3 rounded">
                <input type="email" placeholder="Email" class="w-full border p-2 mb-3 rounded">
                <input type="password" placeholder="Password" class="w-full border p-2 mb-3 rounded">
                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded mt-5">Sign Up</button>
        </form>
        </div>
    </div>
  `;
  document.body.appendChild(el);

  // Attach close button listener after injection
  document.getElementById('close-auth').addEventListener('click', toggleAuth);
}
function openRegister() {
  document.getElementById('register-box').classList.remove('hidden');
  document.getElementById('register-box').classList.add('z-[100]');
  //jisrvmkfmvk,emc 
}
function closeRegister(){
  document.getElementById('register-box').classList.add('hidden');
}


let cartOpen = false;
function toggleCart() {
  cartOpen = !cartOpen;
  const drawer = document.getElementById('cart-drawer');
  const overlay = document.getElementById('cart-overlay');
  if (!drawer) return;
  drawer.classList.toggle('translate-x-full', !cartOpen);
  overlay.classList.toggle('hidden', !cartOpen);
  renderCartDrawer();
}

// User authentication
let authOpen = false;

function toggleAuth() {
  authOpen = !authOpen;
  const drawer = document.getElementById('auth-drawer');   // your login/register drawer
  const overlay = document.getElementById('auth-overlay'); // background overlay
  if (!drawer) return;

  // Slide drawer in/out
  drawer.classList.toggle('translate-x-full', !authOpen);

  // Show/hide overlay
  overlay.classList.toggle('hidden', !authOpen);

  // Optional: render login/register content dynamically
  renderAuthDrawer();
}
document.addEventListener('DOMContentLoaded', () => {
  injectCartDrawer();
  injectAuthDrawer();
  renderCartBadge();
});


// Button + Close
const userBtn = document.getElementById('user-btn');
const closeAuth = document.getElementById('close-auth');

userBtn.addEventListener('click', toggleAuth);
closeAuth.addEventListener('click', toggleAuth);


function renderCartDrawer() {
  const items = Cart.getItems();
  const itemsEl = document.getElementById('cart-items');
  const footerEl = document.getElementById('cart-footer');
  if (!itemsEl) return;

  // 1. Empty Cart State
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

  // 2. Calculate Total (Fixed: i.price is already a number)
  const total = items.reduce((sum, item) => sum + (item.price * item.qty), 0);
  
  // 3. Render Cart Items with + / - controls and Product Images
  itemsEl.innerHTML = items.map((item, index) => `
  <div class="flex items-center gap-4 py-4 border-b border-gray-50 last:border-0">
    
    <div class="w-16 h-16 rounded-xl bg-gray-50 flex items-center justify-center flex-shrink-0 overflow-hidden border border-gray-100">
      <img src="${item.image}" alt="${item.name}" class="w-full h-full object-cover" onerror="this.style.display='none'" />
    </div>

    <div class="flex-1 min-w-0">
      <p class="font-medium text-sm text-gray-900 truncate">${item.name}</p>
      <p class="text-gray-400 text-[10px] uppercase tracking-wider mb-1">${item.brand}</p>
      <p class="text-indigo-600 font-semibold text-sm">$${item.price.toLocaleString()}</p>
    </div>

    <div class="flex items-center gap-2 bg-gray-50 rounded-lg p-1 border border-gray-100 shadow-sm">
      <button onclick="Cart.remove(${index}); renderCartDrawer(); renderCartBadge();"
        class="w-7 h-7 flex items-center justify-center rounded-md hover:bg-white hover:shadow-sm text-gray-600 transition-all active:scale-95"
        aria-label="Decrease quantity">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
        </svg>
      </button>
      
      <span class="text-sm font-bold text-gray-700 w-5 text-center">${item.qty}</span>
      
      <button onclick="Cart.add(Cart.getItems()[${index}]); renderCartDrawer(); renderCartBadge();"
        class="w-7 h-7 flex items-center justify-center rounded-md hover:bg-white hover:shadow-sm text-gray-600 transition-all active:scale-95"
        aria-label="Increase quantity">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
      </button>
    </div>
  </div>
`).join('');

  // 4. Render Footer with Total Price
  footerEl.innerHTML = `
    <div class="flex justify-between items-end mb-4">
      <span class="text-gray-500 text-sm">Subtotal</span>
      <span class="font-bold text-gray-900 text-xl tracking-tight">$${total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
    </div>
    <button onclick="Cart.clear(); renderCartDrawer(); renderCartBadge(); showToast('Checkout initialized!');"
      class="w-full bg-indigo-500 hover:bg-indigo-600 focus:ring-4 focus:ring-indigo-100 text-white font-semibold py-3.5 rounded-2xl text-sm transition-all shadow-sm active:scale-[0.98]">
      Proceed to Checkout
    </button>
    <p class="text-center text-xs text-gray-400 mt-3 font-medium">Free shipping on all orders over $75</p>
  `;
}

/* ---------- Init ---------- */
document.addEventListener('DOMContentLoaded', () => {
  injectCartDrawer();
  renderCartBadge();
});
