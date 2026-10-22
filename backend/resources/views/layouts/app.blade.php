<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>@yield('title', 'ICT — Technology')</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          fontFamily: { display: ['sans-serif'], body: ['sans-serif'] }
        }
      }
    }
  </script>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link href="{{ asset('assets/css/glass.css') }}" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" rel="stylesheet" />
  <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
  <style>
    body {
      font-family: sans-serif;
    }
    h1, h2, h3, h4, h5, nav {
      font-family: sans-serif;
    }
    html, body {
      overflow-x: hidden;
      max-width: 100vw;
    }
    .marquee-wrapper {
      display: flex;
      overflow: hidden;
      flex-wrap: nowrap;
      width: 100%;
      -webkit-mask-image: linear-gradient(to right, transparent 0%, black 150px, black calc(100% - 150px), transparent 100%);
      mask-image: linear-gradient(to right, transparent 0%, black 150px, black calc(100% - 150px), transparent 100%);
    }
    .marquee-group {
      display: flex;
      align-items: center;
      flex-shrink: 0;
      gap: 1rem;
      padding-right: 1rem;
      animation: scrollMarquee 25s linear infinite;
    }
    @keyframes scrollMarquee {
      0% { transform: translateX(0); }
      100% { transform: translateX(-100%); }
    }
    html {
      scroll-behavior: smooth;
    }
    .hero-overlay {
      background: linear-gradient(135deg, rgba(255, 255, 255, 0.92) 0%, rgba(245, 245, 250, 0.75) 50%, rgba(238, 242, 255, 0.65) 100%);
    }
    .fade-up {
      opacity: 0;
      transform: translateY(24px);
      transition: opacity 0.55s ease, transform 0.55s ease;
    }
    .fade-up.visible {
      opacity: 1;
      transform: translateY(0);
    }
    .brand-pill {
      transition: all 0.2s;
    }
    /* Card entrance animation */
    @keyframes cardIn {
      from { opacity: 0; transform: translateY(16px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .product-card { animation: cardIn 0.35s ease both; }
    #search-input:focus {
      outline: none;
      box-shadow: 0 0 0 3px rgba(99,102,241,0.18);
      border-color: #a5b4fc;
    }
    .pill-scroll {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      scrollbar-width: none;
    }
    .pill-scroll::-webkit-scrollbar { display: none; }
    .product-card:hover { box-shadow: 0 8px 32px rgba(99,102,241,0.10), 0 2px 8px rgba(0,0,0,0.06); }
    .cat-card { transition: all 0.2s ease; }
    .cat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(99,102,241,0.12); }
    .form-input:focus {
      outline: none;
      border-color: #6366f1;
      box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
    }
    .input-field:focus {
      outline: none;
      border-color: #6366f1;
      box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
    }
  </style>
  @yield('extra-styles')
</head>
<body class="@yield('body-class', 'bg-gray-50 text-gray-900 dark:bg-neutral-900 dark:text-gray-100 antialiased transition-colors duration-200')">
  
  <!-- ─── GLOBAL NAV ─── -->
  @unless(request()->routeIs('admin-view') || request()->routeIs('admin-login'))
  <header id="nd-nav" class="glass-nav fixed top-0 inset-x-0 z-50 bg-white/80 dark:bg-neutral-900 backdrop-blur-md border-b border-gray-100/80 dark:border-gray-800 shadow-sm transition-colors">
    <nav class="max-w-7xl mx-auto px-5 py-4 flex items-center justify-between">
      <div class="flex items-center gap-8">
        <a href="{{ route('home') }}" class="text-2xl font-bold text-gray-900 dark:text-white flex-shrink-0">
          ICT<span class="text-indigo-500">Tech</span>
        </a>
        <div id="nav-links-row" class="hidden md:flex items-center gap-1 relative">
          <span id="nav-hover-pill"
            class="nav-hover-pill absolute rounded-full opacity-0 pointer-events-none"
            style="transition: left 0.35s cubic-bezier(0.34,1.56,0.64,1), top 0.35s cubic-bezier(0.34,1.56,0.64,1), width 0.35s cubic-bezier(0.34,1.56,0.64,1), height 0.35s cubic-bezier(0.34,1.56,0.64,1), opacity 0.2s ease;"></span>
          @php
            $shopCategories = config('shop.categories');
            $activeCategory = request()->route('category');
            $navLinks = [
              ['label' => 'Home', 'href' => route('home'), 'active' => request()->routeIs('home')],
              ['label' => 'Shop', 'href' => route('shop'), 'active' => request()->routeIs('shop', 'shop.category'), 'dropdown' => true],
              ['label' => 'Contact', 'href' => route('contact'), 'active' => request()->routeIs('contact')],
              ['label' => 'Admin', 'href' => route('admin-login'), 'active' => request()->routeIs('admin-login') || request()->routeIs('admin-view')],
            ];
          @endphp
          @foreach ($navLinks as $link)
            @php
              $linkClass = 'relative z-10 px-4 py-2 rounded-full text-lg font-medium transition-colors duration-200 '
                . ($link['active']
                  ? 'nav-active-pill text-gray-900 dark:text-white'
                  : 'text-gray-500 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white');
            @endphp
            @if (! empty($link['dropdown']))
              {{-- "Shop" is only a menu trigger, not a link: hover (or keyboard focus) reveals the category
                   menu, and the categories inside it are the only things to click. On touch devices, where
                   there is no hover, a tap toggles it (data-open, see toggleShopMenu script below).
                   The pt-3 on the panel wrapper bridges the gap below the trigger so the pointer can
                   travel into the menu. --}}
              <div id="shop-menu" class="relative group" data-open="false">
                <button type="button" data-nav-link data-shop-trigger aria-haspopup="true" aria-expanded="false"
                  class="{{ $linkClass }} inline-flex items-center gap-1.5 cursor-default">
                  {{ $link['label'] }}
                  <svg class="w-3.5 h-3.5 transition-transform duration-200 [@media(hover:hover)]:group-hover:rotate-180 group-focus-within:rotate-180 group-data-[open=true]:rotate-180"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M19 9l-7 7-7-7"/>
                  </svg>
                </button>
                <div class="absolute left-0 top-full pt-3 w-72 z-50 invisible opacity-0 -translate-y-1
                            [@media(hover:hover)]:group-hover:visible [@media(hover:hover)]:group-hover:opacity-100 [@media(hover:hover)]:group-hover:translate-y-0
                            group-focus-within:visible group-focus-within:opacity-100 group-focus-within:translate-y-0
                            group-data-[open=true]:visible group-data-[open=true]:opacity-100 group-data-[open=true]:translate-y-0
                            transition duration-150 ease-out">
                  <div class="nav-dropdown rounded-2xl p-2" role="menu" aria-label="Shop by category">
                    @foreach ($shopCategories as $slug => $cat)
                      <a href="{{ route('shop.category', $slug) }}" role="menuitem"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors hover:bg-indigo-50 focus:bg-indigo-50 focus:outline-none dark:hover:bg-white/10 dark:focus:bg-white/10 {{ $activeCategory === $slug ? 'bg-indigo-50/80 dark:bg-white/10' : '' }}">
                        <span class="text-2xl leading-none" aria-hidden="true">{{ $cat['icon'] }}</span>
                        <span class="flex flex-col min-w-0">
                          <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $cat['label'] }}</span>
                          <span class="text-xs text-gray-400 truncate">{{ $cat['desc'] }}</span>
                        </span>
                      </a>
                    @endforeach
                  </div>
                </div>
              </div>
            @else
              <a href="{{ $link['href'] }}" data-nav-link class="{{ $linkClass }}">{{ $link['label'] }}</a>
            @endif
          @endforeach
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button onclick="toggleTheme()" aria-label="Toggle Dark Mode" class="glass-icon-btn border border-transparent p-2 rounded-xl transition-colors">
          <svg class="w-5 h-5 text-neutral-800 dark:text-gray-300 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
          </svg>
          <svg class="w-5 h-5 text-gray-700 dark:text-gray-300 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
          </svg>
        </button>
        <a href="{{ route('favorites') }}" aria-label="My Favorites"
          class="glass-icon-btn p-2 rounded-xl border border-transparent transition-colors inline-flex">
          <svg class="w-5 h-5 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>
          </svg>
        </a>
        <button onclick="toggleCart()" aria-label="Open cart"
          class="glass-icon-btn relative p-2 rounded-xl border border-transparent transition-colors">
          <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
              d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
          </svg>
          <span id="cart-badge"
            class="hidden absolute -top-1 -right-1 bg-indigo-500 text-white text-[10px] font-bold w-4 h-4 rounded-full flex items-center justify-center">0</span>
        </button>
        <div id="account-slot">
          <a href="{{ route('account') }}" aria-label="Account" class="glass-icon-btn border border-transparent p-2 rounded-xl transition-colors inline-flex">
            <svg class="w-5 h-5 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4z
                  M12 14c-3.31 0-6 2.69-6 6h12c0-3.31-2.69-6-6-6z"/>
            </svg>
          </a>
        </div>
        <button onclick="toggleMobileMenu()" aria-label="Toggle menu"
          class="glass-icon-btn md:hidden p-2 rounded-xl border border-transparent transition-colors">
          <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
      </div>
    </nav>

    <div id="mobile-menu"
      class="hidden md:hidden border-t border-gray-100 bg-white/90 dark:bg-neutral-900 backdrop-blur-md px-5 py-4 flex flex-col gap-3">
      @foreach ($navLinks as $link)
        @if (! empty($link['dropdown']))
          {{-- Shop is a heading here, not a link — its categories (below) are the destinations. --}}
          <span class="text-sm font-medium py-1.5 {{ $link['active'] ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400' }}">{{ $link['label'] }}</span>
        @else
          <a href="{{ $link['href'] }}"
            class="text-sm font-medium py-1.5 transition-colors {{ $link['active']
              ? 'text-gray-900 dark:text-white'
              : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}">{{ $link['label'] }}</a>
        @endif
        @if (! empty($link['dropdown']))
          <div class="flex flex-col gap-1 pl-4 -mt-1 border-l border-gray-200 dark:border-white/10">
            @foreach ($shopCategories as $slug => $cat)
              <a href="{{ route('shop.category', $slug) }}"
                class="text-sm py-1 transition-colors {{ $activeCategory === $slug
                  ? 'text-indigo-500 font-medium'
                  : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}">
                <span aria-hidden="true">{{ $cat['icon'] }}</span> {{ $cat['label'] }}
              </a>
            @endforeach
          </div>
        @endif
      @endforeach
    </div>
  </header>
  @endunless

  @yield('content')

  <!-- Scripts -->
  <script>
    window.__CURRENT_USER__ = @json(auth()->check() ? ['name' => auth()->user()->name, 'email' => auth()->user()->email] : null);
  </script>
  @yield('pre-scripts')
  <script src="{{ asset('assets/js/script.js') }}"></script>
  <script>
    initNavHoverPill();

    function renderBladeAccountSlot() {
      const slot = document.getElementById('account-slot');
      if (!slot || typeof renderAccountNavControl !== 'function') return;
      slot.innerHTML = renderAccountNavControl()
        .replace('handleNavLogout()', 'bladeNavLogout()')
        .replace('href="account.html"', 'href="{{ route('account') }}"');
    }

    document.addEventListener('DOMContentLoaded', renderBladeAccountSlot);

    // Shop menu: on hover-capable devices CSS (group-hover / focus-within) does everything and a click
    // on "Shop" intentionally does nothing. Touch devices have no hover, so there a tap toggles it.
    (function () {
      const menu = document.getElementById('shop-menu');
      if (!menu) return;
      const trigger = menu.querySelector('[data-shop-trigger]');
      const setOpen = open => {
        menu.dataset.open = open ? 'true' : 'false';
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
      };
      const noHover = window.matchMedia('(hover: none)');

      // A mouse/touch press must not focus the button, otherwise focus-within would keep the menu
      // open after the pointer leaves (or after a second tap meant to close it). Keyboard focus
      // via Tab is unaffected.
      trigger.addEventListener('mousedown', e => e.preventDefault());

      trigger.addEventListener('click', () => {
        if (noHover.matches) setOpen(menu.dataset.open !== 'true');
      });
      document.addEventListener('click', e => {
        if (!menu.contains(e.target)) setOpen(false);
      });
      document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { setOpen(false); trigger.blur(); }
      });
    })();

    async function bladeNavLogout() {
      await Auth.logout();
      window.location.href = '{{ route('home') }}';
    }
  </script>
  @yield('extra-scripts')
</body>
</html>
