@extends('layouts.app')

@section('title', 'Admin Dashboard — ICTTech')

@section('body-class', 'flex bg-gray-50 dark:bg-neutral-800')

@section('extra-styles')
<style>
  /* Sidebar */
  .sidebar{transition:transform 0.3s ease;}

  /* Table row hover */
  .trow{transition:background 0.15s;}
  .trow:hover{background:#f5f6ff;}
  .dark .trow:hover{background:#525252;}

  /* Status badge */
  .badge-in   {background:#dcfce7;color:#16a34a;}
  .badge-low  {background:#fef9c3;color:#ca8a04;}
  .badge-out  {background:#fee2e2;color:#dc2626;}

  /* Modal */
  .modal-backdrop{background:rgba(0,0,0,0.25);backdrop-filter:blur(4px);}
  @keyframes modalIn{from{opacity:0;transform:scale(0.96)translateY(8px)}to{opacity:1;transform:scale(1)translateY(0)}}
  .modal-card{animation:modalIn 0.2s ease both;max-height:90vh;overflow-y:auto;}

  /* Input focus */
  .form-inp:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,0.12);}
  .dark .form-inp{background:#525252;border-color:#525252;color:#fff;}
  .dark .form-inp::placeholder{color:#a3a3a3;}

  /* Toast */
  .toast-enter{animation:toastIn 0.3s ease both;}
  @keyframes toastIn{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}

  @keyframes spin{to{transform:rotate(360deg)}}
  .spin{animation:spin 1s linear infinite;}

  .tab-btn.active-tab{border-bottom:2px solid #6366f1;color:#4f46e5;font-weight:600;}

  /* Sidebar nav active state */
  .nav-item.active{background:#1f2937;color:#fff;}
  .dark .nav-item.active{background:#525252;}
</style>
@endsection

@section('content')
<!-- ══════════════════════════════════════════════
    SIDEBAR
══════════════════════════════════════════════ -->
<aside id="sidebar" class="sidebar glass-nav fixed dark:bg-neutral-800 dark:border-none top-0 left-0 h-full w-64 max-w-[85vw] bg-white border-r border-gray-100 shadow-sm z-40 flex flex-col -translate-x-full lg:translate-x-0">
  <!-- Logo -->
  <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between dark:border-none">
    <a href="{{ route('home') }}" class="flex items-center gap-2">
      <svg class="w-9 h-9 shrink-0" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <circle cx="20" cy="20" r="19" fill="white" stroke="url(#ictLogoRing)" stroke-width="2"/>
        <circle cx="20" cy="20" r="14.5" fill="none" stroke="#4f46e5" stroke-width="1"/>
        <text x="20" y="24" text-anchor="middle" font-family="Georgia, 'Times New Roman', serif" font-weight="700" font-size="12" fill="#4338ca">ICT</text>
        <defs>
          <linearGradient id="ictLogoRing" x1="0" y1="0" x2="40" y2="40" gradientUnits="userSpaceOnUse">
            <stop stop-color="#4338ca"/>
            <stop offset="1" stop-color="#3b82f6"/>
          </linearGradient>
        </defs>
      </svg>
      <span class="font-display font-bold text-gray-900 dark:text-white">ICT<span class="text-indigo-500">Tech</span></span>
    </a>
    <span class="text-[10px] bg-indigo-100 text-indigo-600 font-semibold px-2 py-0.5 rounded-full uppercase tracking-wide">Admin</span>
  </div>

  <!-- Nav -->
  <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1 dark:border-none">
    <button onclick="showView('dashboard')" id="nav-dashboard" class="nav-item w-full text-left flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-gray-700 hover:bg-gray-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:text-white focus:bg-gray-800 active:bg-gray-800 dark:text-white dark:focus:bg-neutral-600 dark:active:bg-neutral-900 dark:hover:bg-neutral-600">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      Dashboard
    </button>
    <button onclick="showView('products')" id="nav-products" class="nav-item w-full text-left flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-gray-700 dark:text-white dark:focus:bg-neutral-600 dark:active:bg-neutral-900 dark:hover:bg-neutral-600 hover:bg-gray-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 active:bg-gray-800 focus:text-white focus:bg-gray-800">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
      Products
    </button>
    <button onclick="showView('inventory')" id="nav-inventory" class="nav-item w-full text-left flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-gray-700 dark:text-white dark:focus:bg-neutral-600 dark:active:bg-neutral-900 dark:hover:bg-neutral-600 hover:bg-gray-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 active:bg-gray-800 focus:text-white focus:bg-gray-800">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      Inventory
      <span id="low-stock-badge" class="ml-auto bg-amber-400 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full hidden"></span>
    </button>
    <button onclick="showView('add')" id="nav-add" class="nav-item w-full text-left flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-gray-700 dark:text-white dark:focus:bg-neutral-600 dark:active:bg-neutral-900 dark:hover:bg-neutral-600 hover:bg-gray-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 active:bg-gray-800 focus:text-white focus:bg-gray-800">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Add Products
    </button>
    <a href="{{ route('admin-messages') }}" class="nav-item w-full text-left flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-gray-700 dark:text-white dark:focus:bg-neutral-600 dark:active:bg-neutral-900 dark:hover:bg-neutral-600 hover:bg-gray-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 active:bg-gray-800 focus:text-white focus:bg-gray-800">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
      Message
    </a>
    <a href="{{ route('shop') }}" target="_blank" class="nav-item w-full text-left flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-gray-700 dark:text-white dark:focus:bg-neutral-600 dark:active:bg-neutral-900 dark:hover:bg-neutral-600 hover:bg-gray-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 active:bg-gray-800 focus:text-white focus:bg-gray-800">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
      View
    </a>
    <button onclick="showView('history')" id="nav-history" class="nav-item w-full text-left flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-gray-700 dark:text-white dark:focus:bg-neutral-600 dark:active:bg-neutral-900 dark:hover:bg-neutral-600 hover:bg-gray-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 active:bg-gray-800 focus:text-white focus:bg-gray-800">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      History
    </button>
  </nav>

  <!-- User -->
  <div class="px-4 py-4 border-t border-gray-100 dark:border-none flex items-center justify-between">
    <div class="flex items-center gap-3">
      <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center text-sm font-bold text-indigo-600">{{ substr(Auth::user()->name, 0, 1) }}</div>
      <div>
        <p class="text-xs font-semibold text-gray-800 dark:text-white" id="admin-name">{{ Auth::user()->name }}</p>
        <p class="text-[10px] text-gray-400">Super Admin</p>
      </div>
    </div>
    <form method="POST" action="{{ route('admin-logout') }}">
      @csrf
      <button type="submit" title="Logout" class="glass-icon-btn border border-transparent p-1.5 rounded-lg transition-colors text-gray-400 hover:text-red-500">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      </button>
    </form>
  </div>
</aside>

<!-- Mobile sidebar overlay -->
<div id="mob-overlay" onclick="closeSidebar()" class="hidden fixed inset-0 bg-black/30 z-30 lg:hidden"></div>

<!-- ══════════════════════════════════════════════
    MAIN CONTENT
══════════════════════════════════════════════ -->
<div class="w-full lg:ml-64 flex flex-col dark:bg-neutral-800 dark:border-none">
  <!-- Top bar -->
  <div class="glass-nav sticky top-0 z-20 bg-white/90 backdrop-blur-md border-b border-gray-100 px-3 sm:px-6 py-3 sm:py-3.5 flex items-center justify-between gap-2 dark:bg-neutral-800 dark:border-none">
    <!-- Left -->
    <div class="flex items-center gap-2 sm:gap-3 min-w-0">
      <button onclick="toggleSidebar()" class="glass-icon-btn border border-transparent p-2 rounded-xl transition-colors shrink-0">
        <svg id="sidebar-btn" class="w-5 h-5 text-neutral-800 dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
      <h2 id="page-title" class="font-display font-bold text-base sm:text-lg text-gray-900 dark:text-white truncate">Dashboard</h2>
    </div>
    <!-- Right -->
    <div class="flex items-center gap-1.5 sm:gap-3 shrink-0">
      <div id="topbar-search" class="hidden md:flex items-center bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 gap-2">
        <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input id="table-search" type="text" placeholder="Search products…" oninput="filterTable(this.value)"
          class="bg-transparent text-xs text-gray-700 placeholder-gray-400 outline-none w-28 md:w-40"/>
      </div>
      <button onclick="showView('add')"
        class="flex items-center gap-1.5 bg-indigo-500 hover:bg-indigo-600 text-white text-xs font-semibold px-2.5 sm:px-4 py-2 rounded-xl transition-colors whitespace-nowrap">
        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
        <span class="hidden sm:inline">Add Product</span>
      </button>
      <button onclick="toggleTheme()" aria-label="Toggle Dark Mode"
          class="glass-icon-btn border border-transparent p-2 rounded-xl transition-colors shrink-0">
          <svg class="w-5 h-5 text-neutral-800 dark:text-gray-300 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
          </svg>
          <svg class="w-5 h-5 text-gray-700 dark:text-gray-300 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
          </svg>
      </button>
    </div>
  </div>

  <!-- Views -->
  <main class="flex-1 p-3 sm:p-5 lg:p-6 space-y-5 sm:space-y-6 dark:bg-neutral-800 min-w-0">

    <!-- ──── DASHBOARD VIEW ──── -->
    <div id="view-dashboard">
      <!-- Visitor & Sales Analytics -->
      <div class="mb-6">
        <h3 class="font-display font-bold text-sm text-gray-900 dark:text-white mb-3">Visitor &amp; Sales Analytics</h3>

        <!-- KPI cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 mb-4">
          <div class="glass-panel bg-white dark:bg-neutral-700 rounded-2xl border border-gray-100 dark:border-none shadow-sm p-4 sm:p-5">
            <div class="flex items-center justify-between mb-3">
              <span class="text-xs font-semibold text-gray-400 dark:text-gray-300 uppercase tracking-wide">Total Visitors</span>
              <div class="w-8 h-8 bg-indigo-50 dark:bg-neutral-600 rounded-lg flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              </div>
            </div>
            <p class="font-display font-bold text-2xl sm:text-3xl text-gray-900 dark:text-white" id="kpi-visitors">0</p>
            <p class="text-xs text-gray-400 dark:text-gray-300 mt-1">last 14 days</p>
          </div>
          <div class="glass-panel bg-white dark:bg-neutral-700 rounded-2xl border border-gray-100 dark:border-none shadow-sm p-4 sm:p-5">
            <div class="flex items-center justify-between mb-3">
              <span class="text-xs font-semibold text-gray-400 dark:text-gray-300 uppercase tracking-wide">Total Purchases</span>
              <div class="w-8 h-8 bg-green-50 dark:bg-neutral-600 rounded-lg flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m-10 0a1 1 0 100 2 1 1 0 000-2zm10 0a1 1 0 100 2 1 1 0 000-2z"/></svg>
              </div>
            </div>
            <p class="font-display font-bold text-2xl sm:text-3xl text-gray-900 dark:text-white" id="kpi-purchases">0</p>
            <p class="text-xs text-gray-400 dark:text-gray-300 mt-1">last 14 days</p>
          </div>
          <div class="glass-panel bg-white dark:bg-neutral-700 rounded-2xl border border-gray-100 dark:border-none shadow-sm p-4 sm:p-5">
            <div class="flex items-center justify-between mb-3">
              <span class="text-xs font-semibold text-gray-400 dark:text-gray-300 uppercase tracking-wide">Conversion Rate</span>
              <div class="w-8 h-8 bg-amber-50 dark:bg-neutral-600 rounded-lg flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
              </div>
            </div>
            <p class="font-display font-bold text-2xl sm:text-3xl text-gray-900 dark:text-white" id="kpi-conversion">0%</p>
            <p class="text-xs text-gray-400 dark:text-gray-300 mt-1">purchases ÷ visitors</p>
          </div>
        </div>

        <!-- Chart -->
        <div class="glass-panel bg-white dark:bg-neutral-700 rounded-2xl border border-gray-100 dark:border-none shadow-sm p-4 sm:p-5">
          <div class="flex items-center justify-between mb-4">
            <h4 class="font-display font-bold text-sm text-gray-900 dark:text-white">Visitors vs Purchases</h4>
            <div class="flex items-center gap-3 text-[10px] text-gray-400 dark:text-gray-300">
              <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-indigo-500 inline-block"></span>Visitors</span>
              <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500 inline-block"></span>Purchases</span>
            </div>
          </div>
          <div class="relative w-full" style="height:280px">
            <canvas id="analytics-chart"></canvas>
          </div>
        </div>
      </div>

      <!-- Stat cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
        <div class="glass-panel bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5 dark:bg-neutral-700 dark:border-none">
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide dark:text-white">Total Products</span>
            <div class="w-8 h-8 bg-indigo-50 rounded-lg flex items-center justify-center shrink-0">
              <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
            </div>
          </div>
          <p class="font-display font-bold text-2xl sm:text-3xl text-gray-900 dark:text-white" id="stat-total">0</p>
          <p class="text-xs text-gray-400 mt-1 dark:text-white">in catalog</p>
        </div>
        <div class="glass-panel bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5 dark:bg-neutral-700 dark:border-none">
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide dark:text-white">In Stock</span>
            <div class="w-8 h-8 bg-green-50 rounded-lg flex items-center justify-center shrink-0">
              <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </div>
          </div>
          <p class="font-display font-bold text-2xl sm:text-3xl text-gray-900 dark:text-white" id="stat-instock">0</p>
          <p class="text-xs text-gray-400 mt-1 dark:text-white">products available</p>
        </div>
        <div class="glass-panel bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5 dark:bg-neutral-700 dark:border-none">
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide dark:text-white">Low Stock</span>
            <div class="w-8 h-8 bg-amber-50 rounded-lg flex items-center justify-center shrink-0">
              <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.75-2.98L13.75 4a2 2 0 00-3.5 0L3.25 16.02A2 2 0 005.07 19z"/></svg>
            </div>
          </div>
          <p class="font-display font-bold text-2xl sm:text-3xl text-gray-900 dark:text-white" id="stat-low">0</p>
          <p class="text-xs text-gray-400 mt-1 dark:text-white">need restocking</p>
        </div>
        <div class="glass-panel bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5 dark:bg-neutral-700 dark:border-none">
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide dark:text-white">Out of Stock</span>
            <div class="w-8 h-8 bg-red-50 rounded-lg flex items-center justify-center shrink-0">
              <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </div>
          </div>
          <p class="font-display font-bold text-2xl sm:text-3xl text-gray-900 dark:text-white" id="stat-out">0</p>
          <p class="text-xs text-gray-400 mt-1 dark:text-white">out of stock</p>
        </div>
      </div>

      <!-- Recent + Alerts row -->
      <div class="grid lg:grid-cols-3 gap-4">
        <!-- Recent products table -->
        <div class="lg:col-span-2 glass-panel bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden dark:bg-neutral-700 dark:border-none">
          <div class="px-5 py-4 border-b border-gray-50 flex items-center justify-between">
            <h3 class="font-display font-bold text-sm text-gray-900 dark:text-white">Recent Products</h3>
            <button onclick="showView('products')" class="text-indigo-500 text-xs font-medium hover:underline">View all →</button>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead><tr class="text-left bg-gray-50/50 dark:bg-neutral-700">
                <th class="px-5 py-3 text-[10px] font-semibold text-gray-400 uppercase tracking-wide dark:text-white">Product</th>
                <th class="px-3 py-3 text-[10px] font-semibold text-gray-400 uppercase tracking-wide dark:text-white">Price</th>
                <th class="px-3 py-3 text-[10px] font-semibold text-gray-400 uppercase tracking-wide dark:text-white">Stock</th>
                <th class="px-3 py-3 text-[10px] font-semibold text-gray-400 uppercase tracking-wide dark:text-white">Status</th>
              </tr></thead>
              <tbody id="recent-tbody"></tbody>
            </table>
          </div>
        </div>

        <!-- Alerts panel -->
        <div class="glass-panel bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden dark:bg-neutral-700 dark:border-none">
          <div class="px-5 py-4 border-b border-gray-50">
            <h3 class="font-display font-bold text-sm text-gray-900 dark:text-white">Stock Alerts</h3>
          </div>
          <div id="alerts-panel" class="p-4 space-y-2 max-h-80 overflow-y-auto"></div>
        </div>
      </div>
    </div>

    <!-- ──── PRODUCTS VIEW ──── -->
    <div id="view-products" class="hidden">
      <!-- Tabs -->
      <div class="flex gap-0 border-b border-gray-200 mb-4">
        <button onclick="filterByStatus('all')" data-tab="all" class="tab-btn active-tab px-4 py-2.5 text-sm text-indigo-600 border-b-2 border-indigo-500 -mb-px">All</button>
        <button onclick="filterByStatus('in')" data-tab="in" class="tab-btn px-4 py-2.5 text-sm text-gray-500 hover:text-gray-700">In Stock</button>
        <button onclick="filterByStatus('low')" data-tab="low" class="tab-btn px-4 py-2.5 text-sm text-gray-500 hover:text-gray-700">Low Stock</button>
        <button onclick="filterByStatus('out')" data-tab="out" class="tab-btn px-4 py-2.5 text-sm text-gray-500 hover:text-gray-700">Out of Stock</button>
      </div>

      <div class="glass-panel bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden dark:bg-neutral-700 dark:border-none">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead><tr class="text-left bg-gray-50/50 dark:bg-neutral-700 border-b border-gray-100 dark:border-neutral-600">
              <th class="px-5 py-3.5 text-[10px] font-semibold text-gray-400 uppercase tracking-wide dark:text-white">Product</th>
              <th class="px-3 py-3.5 text-[10px] font-semibold text-gray-400 uppercase tracking-wide hidden sm:table-cell dark:text-white">Brand</th>
              <th class="px-3 py-3.5 text-[10px] font-semibold text-gray-400 uppercase tracking-wide hidden md:table-cell dark:text-white">Category</th>
              <th class="px-3 py-3.5 text-[10px] font-semibold text-gray-400 uppercase tracking-wide dark:text-white">Price</th>
              <th class="px-3 py-3.5 text-[10px] font-semibold text-gray-400 uppercase tracking-wide dark:text-white">Qty</th>
              <th class="px-3 py-3.5 text-[10px] font-semibold text-gray-400 uppercase tracking-wide dark:text-white">Status</th>
              <th class="px-3 py-3.5 text-[10px] font-semibold text-gray-400 uppercase tracking-wide dark:text-white">Actions</th>
            </tr></thead>
            <tbody id="products-tbody"></tbody>
          </table>
        </div>
        <div id="products-empty" class="hidden text-center py-16">
          <div class="text-4xl mb-3">📦</div>
          <p class="text-gray-500 dark:text-white font-medium">No products found</p>
          <p class="text-gray-400 dark:text-gray-300 text-sm mt-1">Try adjusting your filter or search.</p>
        </div>
      </div>
    </div>

    <!-- ──── INVENTORY VIEW ──── -->
    <div id="view-inventory" class="hidden">
      <div class="glass-panel bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden dark:bg-neutral-700 dark:border-none">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-neutral-600 flex items-center justify-between">
          <div>
            <h3 class="font-display font-bold text-gray-900 dark:text-white">Inventory Overview</h3>
            <p class="text-gray-400 dark:text-gray-300 text-xs mt-0.5">Click "Restock" on any item to update quantity</p>
          </div>
          <button onclick="showBulkRestock()" class="text-xs bg-indigo-50 text-indigo-600 font-semibold px-3 py-1.5 rounded-lg hover:bg-indigo-100 transition-colors">
            Bulk Restock All
          </button>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead><tr class="text-left bg-gray-50/50 dark:bg-neutral-700 border-b border-gray-100 dark:border-neutral-600">
              <th class="px-5 py-3 text-[10px] font-semibold text-gray-400 uppercase tracking-wide dark:text-white">Product</th>
              <th class="px-3 py-3 text-[10px] font-semibold text-gray-400 uppercase tracking-wide hidden sm:table-cell dark:text-white">Brand</th>
              <th class="px-3 py-3 text-[10px] font-semibold text-gray-400 uppercase tracking-wide dark:text-white">Current Qty</th>
              <th class="px-3 py-3 text-[10px] font-semibold text-gray-400 uppercase tracking-wide dark:text-white">Status</th>
              <th class="px-3 py-3 text-[10px] font-semibold text-gray-400 uppercase tracking-wide dark:text-white">Stock Bar</th>
              <th class="px-3 py-3 text-[10px] font-semibold text-gray-400 uppercase tracking-wide dark:text-white">Action</th>
            </tr></thead>
            <tbody id="inventory-tbody"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ──── ADD PRODUCT VIEW ──── -->
    <div id="view-add" class="hidden w-full flex justify-center items-center">
      <div class="glass-panel bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sm:p-7 w-full max-w-2xl dark:bg-neutral-700 dark:border-none">
        <h3 class="font-display font-bold text-xl text-gray-900 dark:text-white mb-6" id="form-title">Add New Product</h3>
        <input type="hidden" id="edit-id"/>

        <div class="grid sm:grid-cols-2 gap-5">
          <div class="sm:col-span-2">
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-1.5">Product Name *</label>
            <input id="f-name" type="text" placeholder="e.g. Iphone 17 pro max" class="form-inp w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition-all"/>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-1.5">Brand *</label>
            <input id="f-brand" type="text" placeholder="e.g. Apple" class="form-inp w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition-all"/>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-1.5">Category *</label>
            <select id="f-category" class="form-inp w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition-all cursor-pointer">
              <option value="">Select category…</option>
              <option value="Apple">Apple</option>
              <option value="Sony">Sony</option>
              <option value="JTrust">JTrust</option>
              <option value="Xiaomi">Xiaomi</option>
              <option value="Asus">Asus</option>
              <option value="Anker">Anker</option>
              <option value="Dell">Dell</option>
              <option value="Samsung">Samsung</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-1.5">Price *</label>
            <div class="relative">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
              <input id="f-price" type="number" min="0" step="0.01" placeholder="0.00" class="form-inp w-full bg-gray-50 border border-gray-200 rounded-xl pl-7 pr-4 py-2.5 text-sm transition-all"/>
            </div>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-1.5">Initial Quantity *</label>
            <input id="f-qty" type="number" min="0" placeholder="0" class="form-inp w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition-all"/>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-1.5">Description</label>
            <textarea id="f-desc" rows="3" placeholder="Brief product description…" class="form-inp w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm resize-none transition-all"></textarea>
          </div>
          <div class="flex items-center gap-2">
            <input id="f-trending" type="checkbox" class="w-4 h-4 accent-indigo-500 cursor-pointer"/>
            <label for="f-trending" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">Mark as Trending</label>
          </div>
        </div>

        <div id="form-error" class="hidden mt-4 bg-red-50 border border-red-100 text-red-600 text-xs rounded-xl px-4 py-2.5"></div>

        <div class="flex gap-3 mt-6">
          <button onclick="submitProductForm()" class="flex-1 bg-gray-900 hover:bg-indigo-600 text-white font-semibold py-3 rounded-xl text-sm transition-colors">
            <span id="form-btn-text">Add Product</span>
          </button>
          <button onclick="cancelEdit()" class="px-5 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl text-sm transition-colors">
            Cancel
          </button>
        </div>
      </div>
    </div>

    <!-- ──── HISTORY VIEW ──── -->
    <div id="view-history" class="hidden">
      <div class="glass-panel bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden dark:bg-neutral-700 dark:border-none">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead><tr class="text-left bg-sky-100 dark:bg-neutral-600">
              <th class="px-5 py-3.5 text-xs font-semibold text-gray-700 dark:text-white">No</th>
              <th class="px-3 py-3.5 text-xs font-semibold text-gray-700 dark:text-white">Products</th>
              <th class="px-3 py-3.5 text-xs font-semibold text-gray-700 dark:text-white hidden sm:table-cell">Branch</th>
              <th class="px-3 py-3.5 text-xs font-semibold text-gray-700 dark:text-white">QTY</th>
              <th class="px-3 py-3.5 text-xs font-semibold text-gray-700 dark:text-white">Price</th>
              <th class="px-3 py-3.5 text-xs font-semibold text-gray-700 dark:text-white">Total</th>
              <th class="px-3 py-3.5 text-xs font-semibold text-gray-700 dark:text-white hidden md:table-cell">Date</th>
            </tr></thead>
            <tbody id="history-tbody"></tbody>
          </table>
        </div>
        <div id="history-empty" class="hidden text-center py-16">
          <div class="text-4xl mb-3">🕘</div>
          <p class="text-gray-500 dark:text-white font-medium">No sales history yet</p>
          <p class="text-gray-400 dark:text-gray-300 text-sm mt-1">Try adjusting your search.</p>
        </div>
      </div>
    </div>

  </main>

</div>

<!-- ══════════════════════════════════════════════
      EDIT MODAL (quick inline qty / details edit)
══════════════════════════════════════════════ -->
<div id="edit-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 modal-backdrop">
  <div class="modal-card glass-panel bg-white dark:bg-neutral-700 rounded-2xl shadow-2xl w-full max-w-md p-7 border border-gray-100 dark:border-none">
    <div class="flex items-center justify-between mb-5">
      <h3 class="font-display font-bold text-lg text-gray-900 dark:text-white">Edit Product</h3>
      <button onclick="closeModal()" class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-neutral-600 transition-colors">
        <svg class="w-4 h-4 text-gray-500 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>

    <div class="space-y-4">
      <div>
        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wide mb-1">Product Name</label>
        <input id="m-name" type="text" class="form-inp w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm"/>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wide mb-1">Price ($)</label>
          <input id="m-price" type="number" min="0" step="0.01" class="form-inp w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm"/>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wide mb-1">Quantity</label>
          <input id="m-qty" type="number" min="0" class="form-inp w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm"/>
        </div>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wide mb-1">Description</label>
        <textarea id="m-desc" rows="2" class="form-inp w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm resize-none"></textarea>
      </div>
      <div class="flex items-center gap-2">
        <input id="m-trending" type="checkbox" class="w-4 h-4 accent-indigo-500 cursor-pointer"/>
        <label for="m-trending" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">Trending</label>
      </div>
    </div>

    <div class="flex gap-3 mt-6">
      <button onclick="saveModal()" class="flex-1 bg-indigo-500 hover:bg-indigo-600 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">Save Changes</button>
      <button onclick="closeModal()" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-neutral-600 dark:hover:bg-neutral-500 dark:text-white font-semibold rounded-xl text-sm transition-colors">Cancel</button>
    </div>
  </div>
</div>

<!-- Quick-restock modal -->
<div id="restock-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 modal-backdrop">
  <div class="modal-card glass-panel bg-white dark:bg-neutral-700 rounded-2xl shadow-2xl w-full max-w-sm p-6 border border-gray-100 dark:border-none">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-display font-bold text-gray-900 dark:text-white">Restock Product</h3>
      <button onclick="closeRestockModal()" class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-neutral-600 transition-colors">
        <svg class="w-4 h-4 text-gray-500 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <p class="text-sm text-gray-600 dark:text-gray-300 mb-1">Product: <strong id="rs-name" class="text-gray-900 dark:text-white"></strong></p>
    <p class="text-xs text-gray-400 dark:text-gray-300 mb-4">Current stock: <span id="rs-current"></span> units</p>
    <div class="space-y-3">
      <div>
        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wide mb-1">Set New Quantity</label>
        <input id="rs-qty" type="number" min="0" placeholder="Enter quantity…"
          class="form-inp w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm"/>
      </div>
      <div class="grid grid-cols-3 gap-2">
        <button onclick="setRestockQty(10)" class="bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-neutral-600 dark:hover:bg-neutral-500 dark:text-white text-xs font-semibold py-2 rounded-lg transition-colors">+10</button>
        <button onclick="setRestockQty(25)" class="bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-neutral-600 dark:hover:bg-neutral-500 dark:text-white text-xs font-semibold py-2 rounded-lg transition-colors">+30</button>
        <button onclick="setRestockQty(50)" class="bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-neutral-600 dark:hover:bg-neutral-500 dark:text-white text-xs font-semibold py-2 rounded-lg transition-colors">+50</button>
      </div>
    </div>
    <div class="flex gap-3 mt-5">
      <button onclick="confirmRestock()" class="flex-1 bg-green-600 hover:bg-green-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">Confirm Restock</button>
      <button onclick="closeRestockModal()" class="px-4 py-2.5 bg-gray-100 text-gray-700 dark:bg-neutral-600 dark:text-white font-semibold rounded-xl text-sm">Cancel</button>
    </div>
  </div>
</div>

<!-- Delete confirm modal -->
<div id="delete-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 modal-backdrop">
  <div class="modal-card glass-panel bg-white dark:bg-neutral-700 rounded-2xl shadow-2xl w-full max-w-sm p-6 border border-gray-100 dark:border-none text-center">
    <div class="w-14 h-14 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">🗑️</div>
    <h3 class="font-display font-bold text-lg text-gray-900 dark:text-white mb-2">Delete Product?</h3>
    <p class="text-gray-500 dark:text-gray-300 text-sm mb-5">This will permanently remove <strong id="del-name" class="dark:text-white"></strong> from your catalog.</p>
    <div class="flex gap-3">
      <button onclick="confirmDelete()" class="flex-1 bg-red-500 hover:bg-red-600 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">Delete</button>
      <button onclick="closeDeleteModal()" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-neutral-600 dark:hover:bg-neutral-500 dark:text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">Cancel</button>
    </div>
  </div>
</div>

<!-- Toast -->
<div id="admin-toast" class="fixed bottom-6 right-6 z-[200] pointer-events-none"></div>
@endsection

@section('extra-scripts')
<script>
  // toggleSidebar() lives in assets/js/admin.js (loaded below); closeSidebar()
  // is only used by the mobile overlay's onclick, so it stays here.
  function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mob-overlay');
    sidebar.classList.add('-translate-x-full');
    overlay.classList.add('hidden');
  }
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="{{ asset('assets/js/admin.js') }}"></script>
<script src="{{ asset('assets/js/analytics.js') }}"></script>
@endsection
