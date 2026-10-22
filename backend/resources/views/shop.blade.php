@extends('layouts.app')

@section('title', $category ? $category['label'] . ' — ICTTech' : 'Shop Tech — NovaDrop')

@section('content')
<div class="pt-20">
  <!-- PAGE HEADER -->
  <div class="mt-6">
    <div class="max-w-7xl mx-auto px-5 py-10">
      <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
          @if ($category)
            <h1 class="sr-only">{{ $category['label'] }}</h1>
          @else
            <h1 class="font-bold text-3xl sm:text-5xl text-gray-900 mb-1 dark:text-gray-100 mb-5">Shop Tech</h1>
            <p class="text-gray-400 text-md dark:text-white mt-7">
              Phones · Earphones · Power Banks · Laptops the best brands, curated.
            </p>
          @endif
        </div>

        <!-- Search bar -->
        <div class="relative w-full sm:w-80" role="search">
          <label for="search-input" class="sr-only">Search products by name, brand, or type</label>
          <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
              fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          <input
            id="search-input"
            type="search"
            placeholder="Search brand, name, spec…"
            autocomplete="off"
            class="glass-input w-full pl-10 pr-10 py-3 bg-white/80 backdrop-blur-md border border-gray-200 rounded-2xl text-sm text-gray-800 placeholder-gray-400 transition-all"
          />
          <button
            onclick="clearSearch()"
            title="Clear search"
            aria-label="Clear search"
            class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-600 transition-colors"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
      </div>

      <!-- CATEGORY FILTER TABS (full shop only — a category page is already scoped, brands only) -->
      @unless ($category)
      <div class="mt-5">
        <p class="text-[12px] font-semibold uppercase tracking-widest text-gray-400 mb-2 dark:text-white">Category</p>
        <div id="category-tabs"
          class="pill-scroll flex gap-2 pb-1 relative"
          role="group"
          aria-label="Filter by category"
        ></div>
      </div>
      @endunless

      <!-- BRAND FILTER PILLS -->
      <div class="{{ $category ? 'mt-5' : 'mt-3' }}">
        <p class="text-[12px] font-semibold uppercase tracking-widest text-gray-400 mb-2 dark:text-white mb-5">Brand</p>

        {{-- On a category page at large widths, the brand pills and the Trending/Reset controls share one row. --}}
        <div class="flex flex-col gap-3 {{ $category ? 'lg:flex-row lg:items-center lg:gap-6' : '' }}">
          <div
            id="brand-pills"
            class="pill-scroll flex gap-2 pb-1 relative {{ $category ? 'lg:min-w-0' : '' }}"
            role="group"
            aria-label="Filter by brand"
          ></div>

          <!-- TRENDING TOGGLE -->
          <div class="flex items-center gap-2 flex-shrink-0 {{ $category ? 'lg:pb-1' : '' }}">
            <button
              data-filter="trending"
              onclick="setFilter('trending')"
              aria-pressed="false"
              class="filter-pill glass-chip text-xs font-semibold px-4 py-2 rounded-full border backdrop-blur-md transition-all duration-200 bg-white/50 text-gray-600 dark:text-gray-300 border-white/60"
            >
              🔥 Trending only
            </button>
            <button
              onclick="clearAll()"
              class="text-sm text-gray-400 hover:text-indigo-500 transition-colors px-2 py-2 focus:outline-none focus:underline"
              aria-label="Clear all filters and search"
            >
              Reset all
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- CATEGORY SHOWCASE CARDS (full shop only — a category page is already scoped) -->
  @unless ($category)
  <section aria-label="Browse by category" class="max-w-7xl mx-auto px-5 pt-8 pb-2">
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
      <button onclick="setCategory('phones')" aria-label="Shop Phones" class="cat-card glass-panel bg-white/70 backdrop-blur-md rounded-2xl border border-gray-100 shadow-sm p-4 text-left hover:border-indigo-200 dark:bg-neutral-800 dark:border-none ">
        <span class="text-3xl mb-2 block" aria-hidden="true">📱</span>
        <p class="font-bold text-sm text-gray-900 dark:text-white">Phones</p>
        <p class="text-sm text-gray-400 mt-0.5 dark:text-gray-700 dark:text-white">Apple · Samsung · Xiaomi</p>
      </button>
      <button onclick="setCategory('earphones')" aria-label="Shop Earphones and Headphones" class="cat-card glass-panel bg-white/70 backdrop-blur-md rounded-2xl border border-gray-100 shadow-sm p-4 text-left hover:border-indigo-200 dark:bg-neutral-800 dark:border-none">
        <span class="text-3xl mb-2 block" aria-hidden="true">🎧</span>
        <p class="font-bold text-sm text-gray-900 dark:text-white">Earphones</p>
        <p class="text-sm text-gray-400 mt-0.5 dark:text-gray-700 dark:text-white">Apple · Samsung · Sony · Anker</p>
      </button>
      <button onclick="setCategory('powerbanks')" aria-label="Shop Power Banks" class="cat-card glass-panel bg-white/70 backdrop-blur-md rounded-2xl border border-gray-100 shadow-sm p-4 text-left hover:border-indigo-200 dark:bg-neutral-800 dark:border-none">
        <span class="text-3xl mb-2 block" aria-hidden="true">🔋</span>
        <p class="font-bold text-sm text-gray-900 dark:text-white">Power Banks</p>
        <p class="text-sm text-gray-400 mt-0.5 dark:text-gray-700 dark:text-white">Anker · Xiaomi · Jtrust</p>
      </button>
      <button onclick="setCategory('laptops')" aria-label="Shop Laptops" class="cat-card glass-panel bg-white/70 backdrop-blur-md rounded-2xl border border-gray-100 shadow-sm p-4 text-left hover:border-indigo-200 dark:bg-neutral-800 dark:border-none">
        <span class="text-3xl mb-2 block" aria-hidden="true">💻</span>
        <p class="font-bold text-sm text-gray-900 dark:text-white">Laptops</p>
        <p class="text-sm text-gray-400 mt-0.5 dark:text-gray-700 dark:text-white">Apple · Asus · Dell</p>
      </button>
    </div>
  </section>
  @endunless

  <!-- PRODUCT GRID -->
  <main id="main-content" class="max-w-7xl mx-auto px-5 py-6 pb-20" aria-label="Product listing">
    <!-- Results bar -->
    <div class="flex flex-wrap items-center justify-between mb-5 gap-3">
      <p id="product-count" class="text-md text-gray-400 font-medium" role="status" aria-live="polite" aria-atomic="true"></p>
      <div class="flex flex-wrap items-center gap-3">
          <div class="flex items-center gap-2">
            <label for="sort-select" class="text-sm text-gray-400 whitespace-nowrap">Sort:</label>
            <select
              id="sort-select"
              onchange="sortProducts(this.value)"
              class="text-sm bg-white/80 border border-gray-200 rounded-xl px-3 py-1.5 text-gray-700 outline-none cursor-pointer focus:ring-2 focus:ring-indigo-300"
            >
              <option value="default">Featured</option>
              <option value="price-asc">Price: Low to High</option>
              <option value="price-desc">Price: High to Low</option>
              <option value="name">Name A–Z</option>
            </select>
          </div>
          <div class="flex items-center gap-2">
            <label for="price-select" class="text-sm text-gray-400 whitespace-nowrap">Price:</label>
            <select
              id="price-select"
              onchange="setPriceRange(this.value)"
              class="text-sm bg-white/80 border border-gray-200 rounded-xl px-3 py-1.5 text-gray-700 outline-none cursor-pointer focus:ring-2 focus:ring-indigo-300"
            >
              <option value="all">All Prices</option>
              <option value="under500">Under $500</option>
              <option value="500to1000">$500 – $1000</option>
              <option value="above1000">Above $1000</option>
            </select>
          </div>
      </div>
    </div>

    <!-- Grid -->
    <div id="product-grid" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5" aria-label="Products"></div>
  </main>
</div>

<!-- FOOTER -->
<footer class="bg-black py-8">
  <div class="max-w-7xl mx-auto px-5 flex flex-col sm:flex-row items-center justify-between gap-4 text-md text-gray-400">
    <span class="font-display font-bold text-white">
    ICT<span class="text-indigo-500">Tech</span>
    </span>
    <span>© 2025 ICTTech. All rights reserved.</span>
    <nav aria-label="Footer links" class="flex gap-5">
      <a href="#" class="text-gray-300 hover:text-white transition-colors">About</a>
      <a href="{{ route('contact') }}" class="text-gray-300 hover:text-white transition-colors">Contact</a>
      <a href="{{ route('privacy') }}" class="text-gray-300 hover:text-white transition-colors">Privacy</a>
    </nav>
  </div>
</footer>
@endsection

@section('pre-scripts')
<script>
  window.__FAVORITE_IDS__ = @json($favoriteIds);
</script>
@endsection

@section('extra-scripts')
@php
  $shopCategory = $category ? ['slug' => $category['slug']] : null;
@endphp
<script>
  const PRODUCTS = @json($products);
  const CATEGORIES = [
    @foreach (config('shop.categories') as $slug => $cat)
    { slug: @json($slug), label: @json($cat['label']), icon: @json($cat['icon']), desc: @json($cat['desc']) },
    @endforeach
  ];
  // Set on /shop/{category}: shop.js adds an "All brands" pill to the brand row.
  // null on the full /shop page.
  const SHOP_CATEGORY = @json($shopCategory);
  const BRANDS = @json($brands);
</script>
<script src="{{ asset('assets/js/shop.js') }}"></script>
@endsection
