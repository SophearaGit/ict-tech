@extends('layouts.app')

@section('title', 'My Account — ICTTech')

@section('body-class', 'bg-gray-50 dark:bg-neutral-900')

@section('content')
<main class="min-h-screen bg-gray-50 dark:bg-neutral-900 flex items-center justify-center px-4 pt-24 pb-16 transition-colors">
  <div class="card-in w-full max-w-md">
    <!-- Logo -->
    <div class="text-center mb-8">
      <a href="{{ route('home') }}" class="inline-flex items-center gap-2">
        <span class="font-display font-bold text-4xl text-gray-900 dark:text-white">ICT<span class="text-indigo-500">Tech</span></span>
      </a>
    </div>

    <!-- Card (state rendered by JS) -->
    <div id="account-card" class="glass-panel bg-white/80 dark:bg-neutral-800 backdrop-blur-md rounded-3xl border border-gray-100 dark:border-none shadow-sm p-8">
    </div>

    <p class="text-center text-sm text-gray-400 mt-8">
      <a href="{{ route('home') }}" class="hover:text-gray-600 dark:hover:text-white transition-colors">← Back to store</a>
    </p>
  </div>
</main>
@endsection

@section('extra-scripts')
<script>
  /* Resolve a safe same-site redirect target from ?redirect= (falls back to shop) */
  function getContinueHref() {
    const redirect = new URLSearchParams(window.location.search).get('redirect');
    return redirect && /^\/[\w\-\/]*$/.test(redirect) ? redirect : '{{ route('shop') }}';
  }

  /* If a guest was bounced here from "Add to cart", finish that action now */
  function consumePendingCartItem() {
    const pending = sessionStorage.getItem('novadrop_pending_cart_item');
    if (!pending) return null;
    sessionStorage.removeItem('novadrop_pending_cart_item');
    try {
      const product = JSON.parse(pending);
      Cart.add(product);
      return product.name;
    } catch (e) {
      return null;
    }
  }

  function renderAccountPage() {
    const user = Auth.getCurrentUser();
    user ? renderWelcome(user) : renderLoginView();
  }

  function renderWelcome(user) {
    const card = document.getElementById('account-card');
    const addedItemName = consumePendingCartItem();
    card.innerHTML = `
      <div class="text-center">
        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-indigo-100 dark:bg-indigo-500/20 flex items-center justify-center text-2xl font-bold text-indigo-600">
          ${escapeHtml(user.username.charAt(0).toUpperCase())}
        </div>
        <h1 class="font-display font-bold text-2xl text-gray-900 dark:text-white mb-1">Welcome back, ${escapeHtml(user.username)}</h1>
        <p class="text-gray-400 text-sm mb-6">${escapeHtml(user.email)}</p>
        ${addedItemName ? `
          <div class="bg-green-50 border border-green-100 text-green-700 text-sm rounded-xl px-4 py-3 mb-6 text-left">
            ✅ <strong>${escapeHtml(addedItemName)}</strong> was added to your cart.
          </div>` : ''}
        <div class="flex flex-col gap-3">
          <a href="${getContinueHref()}"
            class="bg-gray-900 hover:bg-indigo-600 text-white font-semibold py-3 rounded-xl text-sm transition-all duration-200 hover:-translate-y-0.5">
            Continue Shopping
          </a>
          <button onclick="Auth.logout(); renderAccountPage();"
            class="text-sm text-gray-500 hover:text-red-500 dark:text-gray-400 dark:hover:text-red-400 transition-colors py-2">
            Log out
          </button>
        </div>
      </div>`;
  }

  function authMsg(msg, type) {
    const el = document.getElementById('auth-message');
    if (!el) return;
    el.textContent = msg;
    el.className = `text-sm rounded-xl px-4 py-2.5 mb-4 ${type === 'success'
        ? 'bg-green-50 text-green-700 border border-green-100'
        : 'bg-red-50 text-red-600 border border-red-100'}`;
    el.classList.remove('hidden');
  }

  function fieldClasses() {
    return 'input-field w-full p-3 border border-gray-200 dark:border-gray-700 rounded-xl text-sm outline-none transition-all dark:text-white dark:bg-neutral-800 dark:placeholder-gray-400';
  }

  function renderLoginView() {
    const card = document.getElementById('account-card');
    card.innerHTML = `
      <div id="auth-message" class="hidden"></div>
      <h1 class="font-display font-bold text-xl text-gray-900 dark:text-white mb-6 text-center">Login</h1>
      <div class="flex flex-col gap-4">
        <div>
          <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1.5">Email</label>
          <input id="login-email" type="email" placeholder="you@example.com" autocomplete="email" class="${fieldClasses()}"/>
        </div>
        <div>
          <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1.5">Password</label>
          <input id="login-password" type="password" placeholder="••••••••" autocomplete="current-password"
            onkeydown="if(event.key==='Enter') handleLoginSubmit()" class="${fieldClasses()}"/>
        </div>
        <button onclick="handleLoginSubmit()"
          class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-xl text-sm transition-colors mt-2">
          Login
        </button>
        <p class="text-sm text-center text-gray-500">
          Don't have an account?
          <a href="#" onclick="renderRegisterView(event)" class="text-indigo-500 font-medium hover:underline">Create one</a>
        </p>
      </div>`;
    document.getElementById('login-email')?.focus();
  }

  function renderRegisterView(e) {
    if (e) e.preventDefault();
    const card = document.getElementById('account-card');
    card.innerHTML = `
      <div id="auth-message" class="hidden"></div>
      <h1 class="font-display font-bold text-xl text-gray-900 dark:text-white mb-6 text-center">Create account</h1>
      <div class="flex flex-col gap-4">
        <div>
          <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1.5">Username</label>
          <input id="register-username" type="text" placeholder="username" autocomplete="username" class="${fieldClasses()}"/>
        </div>
        <div>
          <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1.5">Email</label>
          <input id="register-email" type="email" placeholder="you@example.com" autocomplete="email" class="${fieldClasses()}"/>
        </div>
        <div>
          <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1.5">Password</label>
          <input id="register-password" type="password" placeholder="••••••••" autocomplete="new-password"
            onkeydown="if(event.key==='Enter') handleRegisterSubmit()" class="${fieldClasses()}"/>
        </div>
        <button onclick="handleRegisterSubmit()"
          class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-xl text-sm transition-colors mt-2">
          Create account
        </button>
        <p class="text-sm text-center text-gray-500">
          Already have an account?
          <a href="#" onclick="renderLoginView(); event.preventDefault();" class="text-indigo-500 font-medium hover:underline">Login</a>
        </p>
      </div>`;
    document.getElementById('register-username')?.focus();
  }

  function handleLoginSubmit() {
    const email = document.getElementById('login-email')?.value.trim();
    const password = document.getElementById('login-password')?.value;
    if (!email || !password) { authMsg('Please fill in both fields.', 'error'); return; }
    const result = Auth.login(email, password);
    if (!result.ok) { authMsg(result.error, 'error'); return; }
    renderAccountPage();
  }

  function handleRegisterSubmit() {
    const username = document.getElementById('register-username')?.value.trim();
    const email = document.getElementById('register-email')?.value.trim();
    const password = document.getElementById('register-password')?.value;
    if (!username || !email || !password) { authMsg('Please fill in all fields.', 'error'); return; }
    if (password.length < 4) { authMsg('Password must be at least 4 characters.', 'error'); return; }
    const result = Auth.register(username, email, password);
    if (!result.ok) { authMsg(result.error, 'error'); return; }
    renderAccountPage();
  }

  renderAccountPage();
</script>
@endsection
