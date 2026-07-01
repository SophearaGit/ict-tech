/* ═══════════════════════════════════════════════════════════════════
    shop.js  —  NovaDrop Tech Shop Logic
    Depends on: products-data.js (loaded before this file)
═══════════════════════════════════════════════════════════════════ */

/* ── State ── */
let filteredList     = [...PRODUCTS];
let activeCategory   = "all";      // "all" | category slug
let activeBrand      = "all";      // "all" | brand id (lowercase)
let activeStatus     = "all";      // "all" | "trending"
let activePriceRange = "all";      // "all" | "under500" | "500to1000" | "above1000"
let currentSort      = "default";

/* ══════════════════════════════════════════════════════════════════
    CARD TEMPLATE
    Pure function — takes a product object, returns an HTML string.
    Accessible: uses <article>, role="img" aria-label on image area,
    meaningful alt text on <img>.
══════════════════════════════════════════════════════════════════ */
function productCardHTML(p, index) {
  const specBadges = p.specs
    .map(s => `<span class="inline-block bg-gray-100 text-gray-500 text-[11px] font-medium px-2 py-0.5 rounded-full">${s}</span>`)
    .join("");

  const trendingBadge = p.trending
    ? `<span class="absolute top-3 left-3 z-10 bg-indigo-500 text-white text-[10px] font-bold uppercase tracking-wide px-2.5 py-1 rounded-full">
        Trending
      </span>`
    : "";

  const labelBadge = p.badge
    ? `<span class="absolute top-3 right-3 z-10 bg-white/90 backdrop-blur-sm text-gray-700 text-[10px] font-bold uppercase tracking-wide px-2.5 py-1 rounded-full shadow-sm border border-gray-100">
        ${p.badge}
        </span>`
    : "";

  // Serialise for the cart button — escape quotes so it's safe in an attribute
  const productJSON = JSON.stringify(p).replace(/"/g, "&quot;");

  return `
    <article
      class="product-card dark:bg-neutral-800 dark:border-none group bg-white/70 backdrop-blur-md rounded-2xl border border-gray-100 shadow-sm overflow-hidden hover:shadow-md hover:-translate-y-1 transition-all duration-200 flex flex-col"
      style="animation-delay:${index * 40}ms"
      aria-label="${p.name} by ${p.brand}"
    >
      <!-- ── Image ── -->
      <div class="relative bg-gradient-to-br from-gray-50 to-gray-100 h-48 overflow-hidden flex-shrink-0">
        ${trendingBadge}
        ${labelBadge}
        <img
          src="${p.image}"
          alt="${p.imageAlt}"
          loading="lazy"
          class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-500"
          onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
        />
        <!-- Fallback if image fails -->
        <div class="absolute inset-0 hidden items-center justify-center text-6xl bg-gray-50" aria-hidden="true">
          ${p.category === "phones" ? "📱" : p.category === "earphones" ? "🎧" : p.category === "powerbanks" ? "🔋" : "💻"}
        </div>
      </div>

      <!-- ── Info ── -->
      <div class="p-4 flex flex-col flex-1">
        <!-- Brand · Type -->
        <div class="text-[10px] font-semibold uppercase tracking-widest text-indigo-400 mb-0.5">
          ${p.brand} · ${p.type}
        </div>

        <!-- Name -->
        <h3 class=" dark:text-white font-bold text-gray-900 text-md leading-snug mb-1.5 ">
          ${p.name}
        </h3>

        <!-- Description -->
        <p class=" dark:text-white text-gray-400 text-sm leading-relaxed mb-2.5 line-clamp-2 flex-1">
          ${p.desc}
        </p>

        <!-- Spec badges -->
        <div class="flex flex-wrap gap-1 mb-4 mt-3" aria-label="Key specifications">
          ${specBadges}
        </div>

        <!-- Price + CTA -->
        <div class="flex items-center justify-between mt-auto">
          <span class="font-bold text-gray-900 dark:text-white text-base" aria-label="Price: $${p.price}">
            $${p.price.toLocaleString()}
          </span>
          <button onclick="Cart.add(${productJSON})" aria-label="Add ${p.name} to cart" class="bg-gray-900 hover:bg-indigo-500 dark:hover:bg-indigo-500 dark:bg-neutral-900 focus:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-1 text-white text-xs font-semibold px-4 py-2 rounded-full transition-colors duration-200 active:scale-95" >
            Add to cart
          </button>
        </div>
      </div>
    </article>`;
}

/* ══════════════════════════════════════════════════════════════════
    RENDER PRODUCTS
══════════════════════════════════════════════════════════════════ */
function renderProducts(list) {
  const grid  = document.getElementById("product-grid");
  const count = document.getElementById("product-count");
  if (!grid) return;

  count && (count.textContent = `${list.length} product${list.length !== 1 ? "s" : ""}`);

  if (list.length === 0) {
    grid.innerHTML = `
      <div class="col-span-full flex flex-col items-center justify-center py-20 text-center" role="status">
        <div class="text-5xl mb-4" aria-hidden="true">🔍</div>
        <h3 class="text-gray-700 font-semibold text-lg mb-1">No products found</h3>
        <p class="text-gray-400 text-sm">Try a different brand, category, or search term.</p>
        <button onclick="clearAll()" class="mt-4 text-indigo-500 text-sm font-medium hover:underline focus:underline focus:outline-none">
          Clear all filters
        </button>
      </div>`;
    return;
  }

  grid.innerHTML = list.map((p, i) => productCardHTML(p, i)).join("");
}

/* ══════════════════════════════════════════════════════════════════
    FILTER & SORT PIPELINE
══════════════════════════════════════════════════════════════════ */
function applyFilters(searchQuery = "") {
  const q = searchQuery.toLowerCase().trim();

  let base = [...PRODUCTS];

  // 1. Category
  if (activeCategory !== "all") {
    base = base.filter(p => p.category === activeCategory);
  }

  // 2. Brand
  if (activeBrand !== "all") {
    base = base.filter(p => p.brand.toLowerCase() === activeBrand);
  }

  // 3. Trending toggle
  if (activeStatus === "trending") {
    base = base.filter(p => p.trending);
  }

  // 4. Price range
  if (activePriceRange !== "all") {
    base = base.filter(p => {
      if (activePriceRange === "under500")  return p.price < 500;
      if (activePriceRange === "500to1000") return p.price >= 500 && p.price <= 1000;
      if (activePriceRange === "above1000") return p.price > 1000;
      return true;
    });
  }

  // 5. Free-text search across name, brand, type, category, desc, specs
  if (q) {
    base = base.filter(p =>
      p.name.toLowerCase().includes(q)        ||
      p.brand.toLowerCase().includes(q)       ||
      p.type.toLowerCase().includes(q)        ||
      p.category.toLowerCase().includes(q)    ||
      p.desc.toLowerCase().includes(q)        ||
      p.specs.some(s => s.toLowerCase().includes(q))
    );
  }

  // 6. Sort
  base = sortList(base, currentSort);

  filteredList = base;
  renderProducts(filteredList);
}

function sortList(list, method) {
  const arr = [...list];
  if (method === "price-asc")  return arr.sort((a, b) => a.price - b.price);
  if (method === "price-desc") return arr.sort((a, b) => b.price - a.price);
  if (method === "name")       return arr.sort((a, b) => a.name.localeCompare(b.name));
  return arr; // "default" / featured order
}

/* ══════════════════════════════════════════════════════════════════
    PRICE FILTER
    Sets state and routes through applyFilters() so it composes with
    category, brand, trending, search, and sort — instead of
    rendering its own isolated result set.
══════════════════════════════════════════════════════════════════ */
function setPriceRange(range) {
  // Toggle off if already active
  activePriceRange = activePriceRange === range ? "all" : range;

  document.querySelectorAll(".price-pill").forEach(btn => {
    const active = btn.dataset.price === activePriceRange;
    btn.classList.toggle("bg-gray-900",     active);
    btn.classList.toggle("text-white",      active);
    btn.classList.toggle("hover:bg-",      active);
    btn.classList.toggle("border-gray-900", active);
    btn.classList.toggle("bg-white/70",    !active);
    btn.classList.toggle("text-gray-600",  !active);
    btn.classList.toggle("border-gray-200",!active);
    btn.setAttribute("aria-pressed", active ? "true" : "false");
  });

  applyFilters(document.getElementById("search-input")?.value || "");
}

/* ── Exposed sort function (called by the <select> in HTML) ── */
function sortProducts(method) {
  currentSort = method;
  applyFilters(document.getElementById("search-input")?.value || "");
}

/* ══════════════════════════════════════════════════════════════════
    CATEGORY FILTER
    Drives the category-strip tabs AND the filter state.
══════════════════════════════════════════════════════════════════ */
function setCategory(slug) {
  activeCategory = slug;

  // Update category tab UI
  document.querySelectorAll(".cat-tab").forEach(btn => {
    const active = btn.dataset.category === slug;
    btn.classList.toggle("bg-gray-900",     active);
    btn.classList.toggle("text-white",      active);
    btn.classList.toggle("border-gray-900", active);
    btn.classList.toggle("bg-white/70",    !active);
    btn.classList.toggle("text-gray-600",  !active);
    btn.classList.toggle("border-gray-200",!active);
    btn.setAttribute("aria-pressed", active ? "true" : "false");
  });

  // Reset brand when category changes
  activeBrand = "all";
  document.querySelectorAll(".brand-pill").forEach(btn => {
    setBrandPillState(btn, false);
  });

  applyFilters(document.getElementById("search-input")?.value || "");
}

/* ══════════════════════════════════════════════════════════════════
    BRAND FILTER
══════════════════════════════════════════════════════════════════ */
function setBrand(brandId) {
  // Toggle off if already active
  activeBrand = activeBrand === brandId ? "all" : brandId;

  document.querySelectorAll(".brand-pill").forEach(btn => {
    const active = btn.dataset.brand === activeBrand;
    setBrandPillState(btn, active);
    btn.setAttribute("aria-pressed", active ? "true" : "false");
  });

  applyFilters(document.getElementById("search-input")?.value || "");
}

function setBrandPillState(btn, active) {
  btn.classList.toggle("bg-indigo-500",   active);
  btn.classList.toggle("text-white",      active);
  btn.classList.toggle("border-indigo-500", active);
  btn.classList.toggle("bg-white/70",    !active);
  btn.classList.toggle("text-gray-600",  !active);
  btn.classList.toggle("border-gray-200",!active);
}

/* ══════════════════════════════════════════════════════════════════
    TRENDING TOGGLE (filter-pill with data-filter="trending")
══════════════════════════════════════════════════════════════════ */
function setFilter(filterVal) {
  activeStatus = activeStatus === filterVal ? "all" : filterVal;

  document.querySelectorAll(".filter-pill").forEach(btn => {
    const active = btn.dataset.filter === activeStatus;
    btn.classList.toggle("bg-gray-900",   active);
    btn.classList.toggle("text-white",    active);
    btn.classList.toggle("bg-white/70",  !active);
    btn.classList.toggle("text-gray-600",!active);
    btn.classList.toggle("border",       !active);
    btn.classList.toggle("border-gray-200", !active);
    btn.setAttribute("aria-pressed", active ? "true" : "false");
  });

  applyFilters(document.getElementById("search-input")?.value || "");
}

/* ══════════════════════════════════════════════════════════════════
    CLEAR ALL
══════════════════════════════════════════════════════════════════ */
function clearAll() {
  activeCategory   = "all";
  activeBrand      = "all";
  activeStatus     = "all";
  activePriceRange = "all";
  currentSort      = "default";

  const input = document.getElementById("search-input");
  if (input) input.value = "";

  document.querySelectorAll(".cat-tab").forEach(btn => {
    const isAll = btn.dataset.category === "all";
    btn.classList.toggle("bg-gray-900",     isAll);
    btn.classList.toggle("text-white",      isAll);
    btn.classList.toggle("border-gray-900", isAll);
    btn.classList.toggle("bg-white/70",    !isAll);
    btn.classList.toggle("text-gray-600",  !isAll);
    btn.classList.toggle("border-gray-200",!isAll);
  });

  document.querySelectorAll(".brand-pill").forEach(b => setBrandPillState(b, false));
  document.querySelectorAll(".filter-pill").forEach(b => {
    b.classList.remove("bg-gray-900","text-white");
    b.classList.add("bg-white/70","text-gray-600","border","border-gray-200");
  });
  document.querySelectorAll(".price-pill").forEach(b => {
    b.classList.remove("bg-gray-900","text-white","border-gray-900");
    b.classList.add("bg-white/70","text-gray-600","border-gray-200");
    b.setAttribute("aria-pressed", "false");
  });

  const sortEl = document.getElementById("sort-select");
  if (sortEl) sortEl.value = "default";

  applyFilters("");
}

function clearSearch() {
  const input = document.getElementById("search-input");
  if (input) input.value = "";
  applyFilters("");
}

/* ══════════════════════════════════════════════════════════════════
    DYNAMIC PILL BUILDER
    Reads CATEGORIES and BRANDS from products-data.js and
    injects the HTML into #category-tabs and #brand-pills.
══════════════════════════════════════════════════════════════════ */
function buildFilterUI() {
  // ── Category tabs ──────────────────────────────────────────────
  const catContainer = document.getElementById("category-tabs");
  if (catContainer) {
    const allTab = `
      <button
        data-category="all"
        onclick="setCategory('all')"
        aria-pressed="true"
        class="cat-tab text-sm font-bold px-4 py-2 rounded-full border transition-all duration-200 bg-gray-900 text-white border-gray-900 whitespace-nowrap dark:border-none" >All Products</button>`;
    // render cat button
    const catTabs = CATEGORIES.map(c => `
      <button
        data-category="${c.slug}"
        onclick="setCategory('${c.slug}')"
        aria-pressed="false"
        class="cat-tab text-sm font-bold px-4 py-2 rounded-full border transition-all duration-200 bg-white/70 text-gray-600 border-gray-200 hover:bg-gray-100 whitespace-nowrap flex items-center gap-1.5 dark:border-none"
      >
        <span aria-hidden="true">${c.icon}</span>${c.label}
      </button>`).join("");

    catContainer.innerHTML = allTab + catTabs;
  }

  // ── Brand pills ────────────────────────────────────────────────
  const brandContainer = document.getElementById("brand-pills");
  if (brandContainer) {
    brandContainer.innerHTML = BRANDS.map(b => `
      <button
        data-brand="${b.id}"
        onclick="setBrand('${b.id}')"
        aria-pressed="false"
        class="brand-pill text-sm font-bold px-4 py-2 rounded-full border transition-all duration-200 bg-white/70 text-gray-600 border-gray-200 hover:bg-gray-100 whitespace-nowrap dark:border-none"
      >${b.label}</button>`).join("");
  }
}

/* ══════════════════════════════════════════════════════════════════
    INIT
══════════════════════════════════════════════════════════════════ */
document.addEventListener("DOMContentLoaded", () => {
  buildFilterUI();

  const searchInput = document.getElementById("search-input");
  if (searchInput) {
    searchInput.addEventListener("input", e => applyFilters(e.target.value));
  }

  // Show all products on load (not trending-only, so everything is visible)
  applyFilters("");
});
