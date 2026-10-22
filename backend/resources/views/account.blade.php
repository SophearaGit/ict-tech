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
    @if (session('auth_error'))
      authMsg(@json(session('auth_error')), 'error');
    @endif
  }

  function renderWelcome(user) {
    const card = document.getElementById('account-card');
    const addedItemName = consumePendingCartItem();
    card.innerHTML = `
      <div class="text-center">
        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-indigo-100 dark:bg-indigo-500/20 flex items-center justify-center text-2xl font-bold text-indigo-600">
          ${escapeHtml(user.name.charAt(0).toUpperCase())}
        </div>
        <h1 class="font-display font-bold text-2xl text-gray-900 dark:text-white mb-1">Welcome back, ${escapeHtml(user.name)}</h1>
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
          <button onclick="handleLogoutClick()"
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

  /* Carries the ?redirect= continue-target through the Google OAuth round trip */
  function googleAuthHref() {
    const redirect = new URLSearchParams(window.location.search).get('redirect');
    const safe = redirect && /^\/[\w\-\/]*$/.test(redirect) ? redirect : '';
    return safe ? `{{ route('google.redirect') }}?redirect=${encodeURIComponent(safe)}` : `{{ route('google.redirect') }}`;
  }

  function googleButtonHTML() {
    return `
      <a href="${googleAuthHref()}"
        class="flex items-center justify-center gap-2.5 w-full border border-gray-200 dark:border-gray-700 rounded-xl py-3 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-neutral-800 hover:bg-gray-50 dark:hover:bg-neutral-700 transition-colors">
        <svg class="w-4 h-4" viewBox="0 0 48 48" aria-hidden="true">
          <path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/>
          <path fill="#FF3D00" d="M6.306 14.691l6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"/>
          <path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238A11.91 11.91 0 0124 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z"/>
          <path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303a12.04 12.04 0 01-4.087 5.571l.003-.002 6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"/>
        </svg>
        Continue with Google
      </a>
      <div class="flex items-center gap-3 my-4">
        <div class="flex-1 h-px bg-gray-200 dark:bg-gray-700"></div>
        <span class="text-xs text-gray-400">or</span>
        <div class="flex-1 h-px bg-gray-200 dark:bg-gray-700"></div>
      </div>`;
  }

  function renderLoginView() {
    const card = document.getElementById('account-card');
    card.innerHTML = `
      <div id="auth-message" class="hidden"></div>
      <h1 class="font-display font-bold text-xl text-gray-900 dark:text-white mb-6 text-center">Login</h1>
      ${googleButtonHTML()}
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
          &middot;
          <a href="#" onclick="renderForgotPasswordView(event)" class="text-indigo-500 font-medium hover:underline">Forgot password?</a>
        </p>
      </div>`;
    document.getElementById('login-email')?.focus();
  }

  function renderForgotPasswordView(e) {
    if (e) e.preventDefault();
    const card = document.getElementById('account-card');
    card.innerHTML = `
      <div id="auth-message" class="hidden"></div>
      <h1 class="font-display font-bold text-xl text-gray-900 dark:text-white mb-1 text-center">Forgot password</h1>
      <p class="text-sm text-center text-gray-400 mb-6">Enter your account email and we'll send a 6-digit code to it.</p>
      <div class="flex flex-col gap-4">
        <div>
          <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1.5">Email</label>
          <input id="forgot-email" type="email" placeholder="you@example.com" autocomplete="email"
            onkeydown="if(event.key==='Enter') handleSendResetCodeSubmit()" class="${fieldClasses()}"/>
        </div>
        <button id="send-code-btn" onclick="handleSendResetCodeSubmit()"
          class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-xl text-sm transition-colors mt-2">
          Send code
        </button>
        <p class="text-sm text-center text-gray-500">
          <a href="#" onclick="renderLoginView(); event.preventDefault();" class="text-indigo-500 font-medium hover:underline">← Back to login</a>
        </p>
      </div>`;
    document.getElementById('forgot-email')?.focus();
  }

  function renderResetWithCodeView(email) {
    const card = document.getElementById('account-card');
    card.innerHTML = `
      <div id="auth-message" class="hidden"></div>
      <h1 class="font-display font-bold text-xl text-gray-900 dark:text-white mb-1 text-center">Enter your code</h1>
      <p class="text-sm text-center text-gray-400 mb-6">We sent a 6-digit code to <strong>${escapeHtml(email)}</strong>. It expires in 15 minutes.</p>
      <div class="flex flex-col gap-4">
        <input type="hidden" id="reset-email" value="${escapeHtml(email)}"/>
        <div>
          <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1.5">Code</label>
          <input id="reset-code" type="text" inputmode="numeric" maxlength="6" placeholder="123456" autocomplete="one-time-code" class="${fieldClasses()} text-center tracking-[0.4em]"/>
        </div>
        <div>
          <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1.5">New password</label>
          <input id="reset-password" type="password" placeholder="••••••••" autocomplete="new-password" class="${fieldClasses()}"/>
        </div>
        <div>
          <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1.5">Confirm new password</label>
          <input id="reset-password-confirm" type="password" placeholder="••••••••" autocomplete="new-password"
            onkeydown="if(event.key==='Enter') handleResetWithCodeSubmit()" class="${fieldClasses()}"/>
        </div>
        <button onclick="handleResetWithCodeSubmit()"
          class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-xl text-sm transition-colors mt-2">
          Reset password
        </button>
        <p class="text-sm text-center text-gray-500">
          <a href="#" onclick="renderForgotPasswordView(event)" class="text-indigo-500 font-medium hover:underline">Didn't get a code? Try again</a>
        </p>
      </div>`;
    document.getElementById('reset-code')?.focus();
  }

  function renderRegisterView(e) {
    if (e) e.preventDefault();
    const card = document.getElementById('account-card');
    card.innerHTML = `
      <div id="auth-message" class="hidden"></div>
      <h1 class="font-display font-bold text-xl text-gray-900 dark:text-white mb-6 text-center">Create account</h1>
      ${googleButtonHTML()}
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

  async function handleLoginSubmit() {
    const email = document.getElementById('login-email')?.value.trim();
    const password = document.getElementById('login-password')?.value;
    if (!email || !password) { authMsg('Please fill in both fields.', 'error'); return; }
    const result = await Auth.login(email, password);
    if (!result.ok) { authMsg(result.error, 'error'); return; }
    renderAccountPage();
  }

  async function handleRegisterSubmit() {
    const username = document.getElementById('register-username')?.value.trim();
    const email = document.getElementById('register-email')?.value.trim();
    const password = document.getElementById('register-password')?.value;
    if (!username || !email || !password) { authMsg('Please fill in all fields.', 'error'); return; }
    if (password.length < 4) { authMsg('Password must be at least 4 characters.', 'error'); return; }
    const result = await Auth.register(username, email, password);
    if (!result.ok) { authMsg(result.error, 'error'); return; }
    renderAccountPage();
  }

  async function handleLogoutClick() {
    await Auth.logout();
    renderAccountPage();
  }

  async function handleSendResetCodeSubmit() {
    const email = document.getElementById('forgot-email')?.value.trim();
    if (!email) { authMsg('Please enter your email.', 'error'); return; }
    const btn = document.getElementById('send-code-btn');
    if (btn) { btn.disabled = true; btn.textContent = 'Sending…'; }
    const result = await Auth.requestPasswordResetCode(email);
    if (!result.ok) {
      authMsg(result.error, 'error');
      if (btn) { btn.disabled = false; btn.textContent = 'Send code'; }
      return;
    }
    renderResetWithCodeView(email);
    authMsg('Code sent — check your inbox.', 'success');
  }

  async function handleResetWithCodeSubmit() {
    const email = document.getElementById('reset-email')?.value.trim();
    const code = document.getElementById('reset-code')?.value.trim();
    const password = document.getElementById('reset-password')?.value;
    const confirm = document.getElementById('reset-password-confirm')?.value;
    if (!email || !code || !password || !confirm) { authMsg('Please fill in all fields.', 'error'); return; }
    if (password.length < 4) { authMsg('Password must be at least 4 characters.', 'error'); return; }
    if (password !== confirm) { authMsg('Passwords do not match.', 'error'); return; }
    const result = await Auth.resetPasswordWithCode(email, code, password);
    if (!result.ok) { authMsg(result.error, 'error'); return; }
    renderLoginView();
    authMsg('Password updated — you can now log in.', 'success');
  }

  renderAccountPage();
</script>
@endsection
