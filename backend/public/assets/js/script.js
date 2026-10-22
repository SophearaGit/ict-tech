
// ─── DARK MODE SETUP ──────────────────────────────────────────
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
}

/* ══════════════════════════════════════════════════════════════════
    AUTH  (real accounts — session-backed via the Laravel API)
══════════════════════════════════════════════════════════════════ */
const Auth = (() => {
  function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
  }

  async function postJSON(url, body) {
    let res, data;
    try {
      res = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(body),
      });
      data = await res.json();
    } catch (e) {
      return { ok: false, error: 'Network error — please try again.' };
    }
    if (!res.ok && !data) return { ok: false, error: 'Something went wrong.' };
    if (data && data.csrf_token) {
      const meta = document.querySelector('meta[name="csrf-token"]');
      if (meta) meta.content = data.csrf_token;
    }
    return data;
  }

  async function register(name, email, password) {
    const result = await postJSON('/register', { name, email, password });
    if (result.ok) window.__CURRENT_USER__ = result.user;
    return result;
  }

  async function login(email, password) {
    const result = await postJSON('/login', { email, password });
    if (result.ok) window.__CURRENT_USER__ = result.user;
    return result;
  }

  async function logout() {
    const result = await postJSON('/logout', {});
    window.__CURRENT_USER__ = null;
    return result;
  }

  function getCurrentUser() {
    return window.__CURRENT_USER__ || null;
  }

  function isLoggedIn() {
    return !!getCurrentUser();
  }

  async function requestPasswordResetCode(email) {
    return postJSON('/forgot-password', { email });
  }

  async function resetPasswordWithCode(email, code, password) {
    return postJSON('/reset-password', { email, code, password });
  }

  return {
    register, login, logout, getCurrentUser, isLoggedIn,
    requestPasswordResetCode, resetPasswordWithCode,
  };
})();


/* ══════════════════════════════════════════════════════════════════
    FAVORITES  (signed-in only — real backend, saved products to buy later)
══════════════════════════════════════════════════════════════════ */
const Favorites = (() => {
  const ids = new Set(window.__FAVORITE_IDS__ || []);

  function isFavorited(id) {
    return ids.has(id);
  }

  function promptSignIn() {
    showToast('Sign in to save favorites');
    setTimeout(() => {
      const page = window.location.pathname || '/';
      window.location.href = `/account?redirect=${encodeURIComponent(page)}`;
    }, 900);
  }

  async function toggle(productId) {
    if (!Auth.isLoggedIn()) {
      promptSignIn();
      return { ok: false };
    }
    const meta = document.querySelector('meta[name="csrf-token"]');
    let res, data;
    try {
      res = await fetch('/favorites/toggle', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': meta?.content || '',
        },
        body: JSON.stringify({ product_id: productId }),
      });
      data = await res.json();
    } catch (e) {
      return { ok: false, error: 'Network error — please try again.' };
    }
    if (data.ok) {
      if (data.favorited) ids.add(productId); else ids.delete(productId);
    }
    return data;
  }

  return { isFavorited, toggle };
})();


/* ══════════════════════════════════════════════════════════════════
    CART  (localStorage, prices normalised to numbers)
══════════════════════════════════════════════════════════════════ */
const Cart = (() => {
  const KEY = 'novadrop_cart';
  let items = JSON.parse(localStorage.getItem(KEY) || '[]');

  function save() { localStorage.setItem(KEY, JSON.stringify(items)); }

  function add(product) {
    // Adding to cart requires an account — stash the item and send guests to create one
    if (!Auth.isLoggedIn()) {
      try { sessionStorage.setItem('novadrop_pending_cart_item', JSON.stringify(product)); } catch (e) {}
      showToast('Create an account to add items to your cart');
      setTimeout(() => {
        const page = window.location.pathname || '/';
        window.location.href = `/account?redirect=${encodeURIComponent(page)}`;
      }, 900);
      return;
    }
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


let _ndActivePage = null; // remembers the active nav tab across re-renders (e.g. after logout)

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str == null ? '' : str;
  return div.innerHTML;
}

function injectNav(activePage) {
  _ndActivePage = activePage;
  const pages = [
    { label: 'Home', href: 'index.html' },
    { label: 'Shop', href: 'shop.html' },
    { label: 'About', href: 'about.html' },
    { label: 'Contact', href: 'contact.html' },
  ];

  const links = pages.map(p => {
    const active = p.label.toLowerCase() === activePage;
    return `<a href="${p.href}" data-nav-link
      class="relative z-10 px-4 py-2 rounded-full text-lg font-medium transition-colors duration-200 ${active
        ? 'nav-active-pill text-gray-900 dark:text-white'
        : 'text-gray-500 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white'}">${p.label}</a>`;
  }).join('');

  const nav = document.getElementById('nd-nav');
  if (!nav) return;

  nav.innerHTML = `
    <div class="max-w-7xl mx-auto px-5 h-20 flex items-center justify-between">

      <div class="flex items-center gap-8">
        <a href="index.html" class="font-bold text-xl tracking-tight text-gray-900 dark:text-white flex-shrink-0">
          ICT<span class="text-indigo-500">Tech</span>
        </a>
        <div id="nav-links-row" class="hidden md:flex items-center gap-1 relative">
          <span id="nav-hover-pill"
            class="nav-hover-pill absolute rounded-full opacity-0 pointer-events-none"
            style="transition: left 0.35s cubic-bezier(0.34,1.56,0.64,1), top 0.35s cubic-bezier(0.34,1.56,0.64,1), width 0.35s cubic-bezier(0.34,1.56,0.64,1), height 0.35s cubic-bezier(0.34,1.56,0.64,1), opacity 0.2s ease;"></span>
          ${links}
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button onclick="toggleTheme()" aria-label="Toggle Dark Mode"
          class="glass-icon-btn p-2 rounded-xl border border-transparent transition-colors">
          <svg class="w-5 h-5 text-gray-700 dark:text-gray-300 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
          </svg>
          <svg class="w-5 h-5 text-gray-700 dark:text-gray-300 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
          </svg>
        </button>
        <!-- Cart -->
        <button onclick="toggleCart()" aria-label="Open cart"
          class="glass-icon-btn relative p-2 rounded-xl border border-transparent transition-colors">
          <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
              d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
          </svg>
          <span id="cart-badge"
            class="hidden absolute -top-1 -right-1 bg-indigo-500 text-white text-[10px] font-bold w-4 h-4 rounded-full flex items-center justify-center">0</span>
        </button>

        <!-- User / Account -->
        ${renderAccountNavControl()}

        <!-- Mobile hamburger -->
        <button onclick="toggleMobileMenu()" aria-label="Toggle menu"
          class="glass-icon-btn md:hidden p-2 rounded-xl border border-transparent transition-colors">
          <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
      </div>
    </div>

    <!-- Mobile menu -->
    <div id="mobile-menu"
      class="hidden md:hidden border-t border-gray-100 bg-white/90 dark:bg-neutral-900 backdrop-blur-md px-5 py-4 flex flex-col gap-3">
      ${pages.map(p => `
        <a href="${p.href}"
          class="text-sm font-medium py-1.5 transition-colors ${p.label.toLowerCase() === activePage ? 'text-gray-900 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'
    }">${p.label}</a>`).join('')}
    </div>
  `;

  renderCartBadge();
  initNavHoverPill();
}

/* Generic: makes `pill` glide behind whichever `itemSelector` child of
   `row` is hovered — the shared "liquid glass" sliding-pill behaviour
   used by the nav links and, in shop.js, the filter/category/brand pills. */
function bindHoverPill(row, pill, itemSelector) {
  if (!row || !pill) return;

  row.querySelectorAll(itemSelector).forEach(item => {
    item.addEventListener('mouseenter', () => {
      const rowRect = row.getBoundingClientRect();
      const itemRect = item.getBoundingClientRect();
      // Match the item's box exactly (not just left/width) — a static
      // top:0/bottom:0 on the pill made it stretch to the row's full height,
      // which is taller than the item whenever the row has its own padding,
      // so the pill's rounded ends never quite lined up with the item's.
      pill.style.left = (itemRect.left - rowRect.left) + 'px';
      pill.style.top = (itemRect.top - rowRect.top) + 'px';
      pill.style.width = itemRect.width + 'px';
      pill.style.height = itemRect.height + 'px';
      pill.style.opacity = '1';
    });
  });

  row.addEventListener('mouseleave', () => {
    pill.style.opacity = '0';
  });
}

/* Floating glass pill that glides behind whichever nav link is hovered */
function initNavHoverPill() {
  bindHoverPill(
    document.getElementById('nav-links-row'),
    document.getElementById('nav-hover-pill'),
    '[data-nav-link]'
  );
}

function toggleMobileMenu() {
  const menu = document.getElementById('mobile-menu');
  if (menu) menu.classList.toggle('hidden');
}

/* User icon: plain link to account.html when logged out, a small dropdown when logged in */
const userIconSVG = `
  <svg class="w-6 h-6 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
      d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4z
        M12 14c-3.31 0-6 2.69-6 6h12c0-3.31-2.69-6-6-6z"/>
  </svg>`;

function renderAccountNavControl() {
  const user = Auth.getCurrentUser();
  if (!user) {
    return `
      <a href="account.html" aria-label="Login or register"
        class="glass-icon-btn p-2 rounded-xl border border-transparent transition-colors inline-flex">
        ${userIconSVG}
      </a>`;
  }

  bindAccountMenuOutsideClick();
  return `
    <div class="relative">
      <button onclick="toggleAccountMenu(event)" aria-label="Account menu" aria-haspopup="true" aria-expanded="false"
        id="account-menu-btn"
        class="glass-icon-btn relative p-2 rounded-xl border border-transparent transition-colors inline-flex">
        ${userIconSVG}
        <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-green-500 rounded-full border border-white dark:border-neutral-900"></span>
      </button>
      <div id="account-menu"
        class="glass-panel hidden absolute right-0 mt-2 w-48 bg-white dark:bg-neutral-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-lg py-2 z-[95]">
        <div class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700 truncate">
          Signed in as <span class="font-medium text-gray-900 dark:text-white">${escapeHtml(user.username)}</span>
        </div>
        <button onclick="handleNavLogout()"
          class="w-full text-left px-4 py-2 text-sm text-red-500 hover:bg-gray-50 dark:hover:bg-neutral-700 transition-colors">
          Log out
        </button>
      </div>
    </div>`;
}

function toggleAccountMenu(e) {
  if (e) e.stopPropagation();
  const menu = document.getElementById('account-menu');
  const btn = document.getElementById('account-menu-btn');
  if (!menu) return;
  const willOpen = menu.classList.contains('hidden');
  menu.classList.toggle('hidden', !willOpen);
  if (btn) btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
}

function handleNavLogout() {
  Auth.logout();
  showToast('Logged out');
  injectNav(_ndActivePage);
}

let _accountMenuOutsideClickBound = false;
function bindAccountMenuOutsideClick() {
  if (_accountMenuOutsideClickBound) return;
  _accountMenuOutsideClickBound = true;
  document.addEventListener('click', (e) => {
    const menu = document.getElementById('account-menu');
    const btn = document.getElementById('account-menu-btn');
    if (!menu || menu.classList.contains('hidden')) return;
    if (menu.contains(e.target) || (btn && btn.contains(e.target))) return;
    menu.classList.add('hidden');
    if (btn) btn.setAttribute('aria-expanded', 'false');
  });
}


// CART DRAWER
function injectCartDrawer() {
  if (document.getElementById('cart-drawer')) return;
  const el = document.createElement('div');
  el.id = 'cart-drawer-wrapper';
  el.innerHTML = `
    <div id="cart-overlay" onclick="toggleCart()"
      class="fixed inset-0 bg-black/20 backdrop-blur-sm z-[88] hidden"></div>
    <div id="cart-drawer" class="glass-panel fixed top-0 right-0 h-full w-full sm:w-[400px] bg-white/95 backdrop-blur-xl z-[89] shadow-2xl translate-x-full transition-transform duration-300 flex flex-col dark:border-none dark:bg-neutral-800">
      <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100 dark:border-neutral-700">
        <h2 class="font-display font-bold text-lg text-gray-900 dark:text-white">Your Cart</h2>
        <button onclick="toggleCart()" aria-label="Close cart"
          class="p-2 rounded-xl hover:bg-gray-100 dark:hover:bg-neutral-700 transition-colors">
          <svg class="w-4 h-4 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
      <div id="cart-items"  class="flex-1 overflow-y-auto px-6 py-4 dark:bg-neutral-800 dark:text-white"></div>
      <div id="cart-footer" class="px-6 py-5 border-t border-gray-100 dark:bg-neutral-800 dark:border-neutral-700"></div>
    </div>`;
  document.body.appendChild(el);
}

let cartOpen = false;
function toggleCart() {
  cartOpen = !cartOpen;
  const drawer = document.getElementById('cart-drawer');
  const overlay = document.getElementById('cart-overlay');
  if (!drawer) return;
  drawer.classList.toggle('translate-x-full', !cartOpen);
  overlay.classList.toggle('hidden', !cartOpen);
  if (!cartOpen) {
    clearInterval(_checkoutTimerId);
    destroyCheckoutMap();
    _checkoutState = null;
  }
  renderCartDrawer();
}

/* ══════════════════════════════════════════════════════════════════
    CHECKOUT ("Confirm Booking")  —  phone + location → demo payment QR
    with a 10-minute countdown. Client-side only: no real payment
    gateway exists here, so the QR is clearly labelled as a demo.
══════════════════════════════════════════════════════════════════ */
// L.Icon.Default._getIconUrl() always prefixes its own auto-detected image
// path onto iconUrl/shadowUrl, even when they're already absolute — so
// mergeOptions() on Icon.Default just doubles the URL. A plain L.icon()
// (not Icon.Default) has no such prefixing, so build the pin icon that way.
const _checkoutPinIcon = (typeof L !== 'undefined') ? L.icon({
  iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
  iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
  shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
  iconSize: [25, 41],
  iconAnchor: [12, 41],
  popupAnchor: [1, -34],
  shadowSize: [41, 41],
}) : null;

let _checkoutState = null;   // null | 'contact' | 'payment' | 'expired' | 'success'
let _checkoutOrder = null;   // { id, phone, address, coords, total, expiresAt, paymentMethod }
let _checkoutCoords = null;  // { lat, lng } once shared
let _checkoutTimerId = null;
let _checkoutMap = null;     // Leaflet map instance, once the pin map is shown
let _checkoutMarker = null;  // Leaflet draggable marker
let _checkoutPaymentMethod = 'aba'; // 'aba' | 'cod' — chosen on the contact step

function startCheckout() {
  if (Cart.getItems().length === 0) return;
  destroyCheckoutMap();
  _checkoutState = 'contact';
  renderCartDrawer();
}

function cancelCheckout() {
  clearInterval(_checkoutTimerId);
  destroyCheckoutMap();
  _checkoutState = null;
  renderCartDrawer();
}

function destroyCheckoutMap() {
  if (_checkoutMap) _checkoutMap.remove();
  _checkoutMap = null;
  _checkoutMarker = null;
}

/* Shared by the initial GPS fix and every pin drag — keeps the address
   field, status text, and _checkoutCoords in sync with wherever the pin is. */
function applyCheckoutCoords(lat, lng, adjusted) {
  _checkoutCoords = { lat, lng };
  const addressField = document.getElementById('checkout-address');
  if (addressField) {
    addressField.value = `My current location (${lat.toFixed(5)}, ${lng.toFixed(5)})`;
  }
  const statusEl = document.getElementById('checkout-location-status');
  if (statusEl) {
    statusEl.innerHTML = adjusted
      ? `📍 Pin updated — this exact spot will be shared.`
      : `📍 Approximate location found — drag the pin below to match your exact spot.`;
    statusEl.className = 'text-xs text-green-600 dark:text-green-400 mt-1';
  }
}

function showCheckoutMap(lat, lng) {
  const mapEl = document.getElementById('checkout-map');
  if (!mapEl || typeof L === 'undefined') return;
  mapEl.classList.remove('hidden');

  if (!_checkoutMap) {
    _checkoutMap = L.map('checkout-map').setView([lat, lng], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 19,
    }).addTo(_checkoutMap);
    _checkoutMarker = L.marker([lat, lng], { draggable: true, icon: _checkoutPinIcon || undefined }).addTo(_checkoutMap);
    _checkoutMarker.on('dragend', () => {
      const pos = _checkoutMarker.getLatLng();
      applyCheckoutCoords(pos.lat, pos.lng, true);
    });
    setTimeout(() => _checkoutMap && _checkoutMap.invalidateSize(), 150);
  } else {
    _checkoutMap.setView([lat, lng], 16);
    _checkoutMarker.setLatLng([lat, lng]);
    _checkoutMap.invalidateSize();
  }
}

function shareCheckoutLocation() {
  const statusEl = document.getElementById('checkout-location-status');
  if (!navigator.geolocation) {
    if (statusEl) {
      statusEl.textContent = 'Geolocation is not supported on this device — please type your address below.';
      statusEl.className = 'text-xs text-red-500 mt-1';
    }
    return;
  }
  if (statusEl) {
    statusEl.textContent = 'Getting your location…';
    statusEl.className = 'text-xs text-gray-400 mt-1';
  }
  navigator.geolocation.getCurrentPosition(
    pos => {
      applyCheckoutCoords(pos.coords.latitude, pos.coords.longitude, false);
      showCheckoutMap(pos.coords.latitude, pos.coords.longitude);
    },
    err => {
      if (statusEl) {
        statusEl.textContent = `Could not get your location (${err.message}). Please type your address below.`;
        statusEl.className = 'text-xs text-red-500 mt-1';
      }
    },
    { enableHighAccuracy: true, timeout: 10000 }
  );
}

function handleCheckoutContactSubmit() {
  const phone = document.getElementById('checkout-phone')?.value.trim();
  const address = document.getElementById('checkout-address')?.value.trim();
  const phoneRe = /^[0-9+\-\s]{8,15}$/;

  if (!phone || !phoneRe.test(phone)) {
    _checkoutMsg('Please enter a valid phone number.', 'error');
    return;
  }
  if (!address) {
    _checkoutMsg('Please share your location or type your delivery address.', 'error');
    return;
  }

  const total = Cart.getItems().reduce((sum, i) => sum + (Number(i.price) || 0) * i.qty, 0);
  _checkoutOrder = {
    id: 'ORD' + Date.now().toString(36).toUpperCase(),
    phone, address,
    coords: _checkoutCoords,
    total,
    paymentMethod: _checkoutPaymentMethod,
  };
  destroyCheckoutMap();
  _checkoutState = 'payment';
  renderCartDrawer();
  // Cash on delivery has no online payment window to time out — the QR/countdown is ABA-only.
  if (_checkoutOrder.paymentMethod === 'aba') {
    startCheckoutCountdown();
  }
}

function startCheckoutCountdown() {
  clearInterval(_checkoutTimerId);
  const endsAt = Date.now() + 10 * 60 * 1000;
  _checkoutOrder.expiresAt = endsAt;

  const tick = () => {
    const remaining = endsAt - Date.now();
    if (remaining <= 0) {
      clearInterval(_checkoutTimerId);
      _checkoutState = 'expired';
      renderCartDrawer();
      return;
    }
    const timerEl = document.getElementById('checkout-timer');
    if (timerEl) {
      const m = Math.floor(remaining / 60000);
      const s = Math.floor((remaining % 60000) / 1000);
      timerEl.textContent = `${m}:${String(s).padStart(2, '0')} remaining`;
    }
  };
  tick();
  _checkoutTimerId = setInterval(tick, 1000);
}

function completeCheckoutPayment() {
  clearInterval(_checkoutTimerId);
  _checkoutState = 'success';
  renderCartDrawer();
  setTimeout(() => {
    Cart.clear();
    toggleCart(); // closes the drawer; toggleCart() resets checkout state
  }, 2200);
}

function _checkoutMsg(msg, type) {
  const el = document.getElementById('checkout-contact-message');
  if (!el) return;
  el.textContent = msg;
  el.className = `text-sm rounded-xl px-4 py-2.5 mb-4 ${type === 'error'
      ? 'bg-red-50 text-red-600 border border-red-100'
      : 'bg-green-50 text-green-700 border border-green-100'}`;
  el.classList.remove('hidden');
}

function checkoutMethodBtnClasses(active) {
  return `payment-method-btn flex flex-col items-center justify-center gap-1 py-3 rounded-xl border text-sm font-medium transition-colors ${
    active
      ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300 dark:border-indigo-400'
      : 'border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-600'
  }`;
}

function setCheckoutPaymentMethod(method) {
  _checkoutPaymentMethod = method;
  document.querySelectorAll('.payment-method-btn').forEach(btn => {
    btn.className = checkoutMethodBtnClasses(btn.dataset.paymentMethod === method);
  });
}

function checkoutContactFormHTML() {
  return `
    <div class="py-2">
      <h3 class="font-display font-bold text-lg text-gray-900 dark:text-white mb-1">Confirm Booking</h3>
      <p class="text-sm text-gray-400 mb-5">We need a few details to prepare your order.</p>
      <div id="checkout-contact-message" class="hidden"></div>
      <div class="flex flex-col gap-4">
        <div>
          <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1.5">Phone number</label>
          <input id="checkout-phone" type="tel" placeholder="012 345 678" autocomplete="tel"
            class="glass-input w-full p-3 border border-gray-200 dark:border-gray-700 rounded-xl text-sm outline-none dark:text-white dark:bg-neutral-800 dark:placeholder-gray-400"/>
        </div>
        <div>
          <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1.5">Payment method</label>
          <div class="grid grid-cols-2 gap-2">
            <button type="button" data-payment-method="aba" onclick="setCheckoutPaymentMethod('aba')"
              class="${checkoutMethodBtnClasses(_checkoutPaymentMethod === 'aba')}">
              <span class="text-xl" aria-hidden="true">🏦</span>
              <span>ABA KHQR</span>
            </button>
            <button type="button" data-payment-method="cod" onclick="setCheckoutPaymentMethod('cod')"
              class="${checkoutMethodBtnClasses(_checkoutPaymentMethod === 'cod')}">
              <span class="text-xl" aria-hidden="true">💵</span>
              <span>Cash on Delivery</span>
            </button>
          </div>
        </div>
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="block text-sm text-gray-500 dark:text-gray-400">Delivery location</label>
            <button type="button" onclick="shareCheckoutLocation()" class="text-xs font-medium text-indigo-500 hover:underline">Share my current location</button>
          </div>
          <div id="checkout-map" class="hidden w-full h-44 rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 mb-2"></div>
          <textarea id="checkout-address" rows="2" placeholder="Street, city, or share your location above"
            class="glass-input w-full p-3 border border-gray-200 dark:border-gray-700 rounded-xl text-sm outline-none dark:text-white dark:bg-neutral-800 dark:placeholder-gray-400 resize-none"></textarea>
          <p id="checkout-location-status" class="text-xs text-gray-400 mt-1"></p>
        </div>
      </div>
    </div>`;
}

function checkoutPaymentStepHTML() {
  const order = _checkoutOrder;

  if (order.paymentMethod === 'cod') {
    return `
      <div class="flex flex-col items-center text-center py-6">
        <div class="text-5xl mb-3" aria-hidden="true">💵</div>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Order <span class="font-semibold text-gray-800 dark:text-white">#${escapeHtml(order.id)}</span></p>
        <p class="text-2xl font-bold text-gray-900 dark:text-white mb-2">$${order.total.toFixed(2)}</p>
        <p class="text-sm text-gray-500 dark:text-gray-400 max-w-xs">Pay in cash to the delivery person when your order arrives.</p>
      </div>`;
  }

  const qrPayload = `ICTTECH-PAY|order:${order.id}|amount:${order.total.toFixed(2)}|phone:${order.phone}`;
  const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(qrPayload)}`;
  return `
    <div class="flex flex-col items-center text-center py-4">
      <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Order <span class="font-semibold text-gray-800 dark:text-white">#${escapeHtml(order.id)}</span></p>
      <div class="glass-panel bg-white p-4 rounded-2xl border border-gray-100 my-4">
        <img src="${qrUrl}" alt="ABA KHQR payment code" width="220" height="220" class="rounded-xl" />
      </div>
      <p class="text-2xl font-bold text-gray-900 dark:text-white mb-1">$${order.total.toFixed(2)}</p>
      <p id="checkout-timer" class="text-sm font-semibold text-indigo-600 dark:text-indigo-400 mb-2">10:00 remaining</p>
      <p class="text-xs text-gray-400 max-w-xs">Scan with your ABA Mobile app to pay. This is a demo QR — no real payment is processed.</p>
    </div>`;
}

function checkoutExpiredStepHTML() {
  return `
    <div class="flex flex-col items-center text-center py-10">
      <div class="text-4xl mb-3">⏰</div>
      <h3 class="font-bold text-gray-900 dark:text-white mb-1">Payment window expired</h3>
      <p class="text-sm text-gray-400 max-w-xs">Your 10-minute payment window has ended. Generate a new QR code to try again.</p>
    </div>`;
}

function checkoutSuccessStepHTML() {
  const order = _checkoutOrder;
  const codNote = order.paymentMethod === 'cod'
    ? `<p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Please have <strong>$${order.total.toFixed(2)}</strong> ready in cash.</p>`
    : '';
  return `
    <div class="flex flex-col items-center text-center py-10">
      <div class="text-5xl mb-3">✅</div>
      <h3 class="font-bold text-gray-900 dark:text-white mb-1">Booking confirmed!</h3>
      <p class="text-sm text-gray-400">We'll contact you at ${escapeHtml(order.phone)} to confirm delivery.</p>
      ${codNote}
    </div>`;
}

function renderCheckoutStep(itemsEl, footerEl) {
  const primaryBtnClasses = 'w-full text-white font-semibold py-3.5 rounded-2xl text-sm transition-all active:scale-[0.98]';
  const cancelBtnHTML = `<button onclick="cancelCheckout()" class="w-full text-center text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 mt-2 py-1">Cancel</button>`;

  if (_checkoutState === 'contact') {
    itemsEl.innerHTML = checkoutContactFormHTML();
    footerEl.innerHTML = `
      <button onclick="handleCheckoutContactSubmit()" class="${primaryBtnClasses} bg-indigo-500 hover:bg-indigo-600">Continue</button>
      <button onclick="cancelCheckout()" class="w-full text-center text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 mt-2 py-1">Back to cart</button>`;
  } else if (_checkoutState === 'payment') {
    itemsEl.innerHTML = checkoutPaymentStepHTML();
    const isCod = _checkoutOrder.paymentMethod === 'cod';
    footerEl.innerHTML = `
      <button onclick="completeCheckoutPayment()" class="${primaryBtnClasses} bg-green-600 hover:bg-green-700">${isCod ? 'Confirm Order' : "I've completed payment"}</button>
      ${cancelBtnHTML}`;
  } else if (_checkoutState === 'expired') {
    itemsEl.innerHTML = checkoutExpiredStepHTML();
    footerEl.innerHTML = `
      <button onclick="_checkoutState='payment'; renderCartDrawer(); startCheckoutCountdown();" class="${primaryBtnClasses} bg-indigo-500 hover:bg-indigo-600">Generate new QR</button>
      ${cancelBtnHTML}`;
  } else if (_checkoutState === 'success') {
    itemsEl.innerHTML = checkoutSuccessStepHTML();
    footerEl.innerHTML = '';
  }
}

/* ── Cart render ─────────────────────────────────────────────────── */
function renderCartDrawer() {
  const items = Cart.getItems();
  const itemsEl = document.getElementById('cart-items');
  const footerEl = document.getElementById('cart-footer');
  if (!itemsEl) return;

  if (_checkoutState) {
    renderCheckoutStep(itemsEl, footerEl);
    return;
  }

  if (items.length === 0) {
    itemsEl.innerHTML = `
      <div class="flex flex-col items-center justify-center h-full text-center py-16">
        <div class="text-5xl mb-4">🛍️</div>
        <p class="text-gray-500 dark:text-gray-400 text-sm">Your cart is empty.<br/>Start shopping to add items.</p>
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
      <div class="flex items-center gap-4 py-4 border-b border-gray-50 dark:border-neutral-700 last:border-0">
        <div class="w-16 h-16 rounded-xl bg-gray-50 dark:bg-neutral-700 border border-gray-100 dark:border-neutral-600 overflow-hidden flex-shrink-0 flex items-center justify-center">
          ${thumb}
        </div>
        <div class="flex-1 min-w-0">
          <p class="font-medium text-md text-gray-900 truncate dark:text-white">${item.name}</p>
          <p class="text-gray-400 text-sm uppercase tracking-wider dark:text-gray-400">${item.brand}</p>
          <p class="text-indigo-600 dark:text-indigo-400 font-semibold text-sm mt-0.5">
            $${Number(item.price).toLocaleString()}
          </p>
        </div>
        <!-- +/- controls -->
        <div class="flex items-center gap-1 bg-gray-50 dark:bg-neutral-700 rounded-lg p-1 border border-gray-100 dark:border-neutral-600 flex-shrink-0">
          <button onclick="Cart.remove(${index}); renderCartDrawer();" aria-label="Decrease quantity"
            class="w-7 h-7 flex items-center justify-center rounded-md hover:bg-white dark:hover:bg-neutral-600 hover:shadow-sm text-gray-600 dark:text-gray-300 transition-all active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
            </svg>
          </button>
          <span class="text-sm font-bold text-gray-700 dark:text-gray-200 w-5 text-center">${item.qty}</span>
          <button onclick="Cart.add(Cart.getItems()[${index}]); renderCartDrawer();" aria-label="Increase quantity"
            class="w-7 h-7 flex items-center justify-center rounded-md hover:bg-white dark:hover:bg-neutral-600 hover:shadow-sm text-gray-600 dark:text-gray-300 transition-all active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
          </button>
        </div>
      </div>`;
  }).join('');

  const shippingNote = total >= 75
    ? `<p class="text-xs text-green-600 dark:text-green-400 font-medium mb-3">✅ Free shipping applied!</p>`
    : `<p class="text-xs text-gray-400 mb-3">Add $${(75 - total).toFixed(2)} more for free shipping.</p>`;

  footerEl.innerHTML = `
    <div class="flex justify-between items-end mb-1">
      <span class="text-gray-500 dark:text-gray-400 text-sm">Subtotal</span>
      <span class="font-bold text-gray-900 text-xl dark:text-white">
        $${total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
      </span>
    </div>
    ${shippingNote}
    <button onclick="startCheckout()"
      class="w-full bg-indigo-500 hover:bg-indigo-600 text-white font-semibold py-3.5 rounded-2xl text-sm transition-all active:scale-[0.98]">
      Confirm Booking
    </button>`;
}


// INIT — single DOMContentLoaded, no duplicates (fixes B4)

document.addEventListener('DOMContentLoaded', () => {
  injectCartDrawer();
  renderCartBadge();
});