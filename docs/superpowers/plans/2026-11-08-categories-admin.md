# Categories Managed From the Admin Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move the shop's categories from `config/shop.php` into the database, with an admin Categories page (add, rename, icon, ON/OFF, delete when empty) and a shop that reads them from there.

**Architecture:** A `categories` table (seeded with the four current ones) and a `Category` model. Products keep pointing at a category by its unchanged code (`products.category` = `categories.slug`). `Product::visible()` (from the brands work) also hides products of an OFF category. One small class, `ShopCategories`, gives every customer-facing view its list (name, icon, code, derived description). The admin page and dropdowns are plain JS in a new `categories.js`, built like the Brands page.

**Tech Stack:** Laravel 13, PHP 8.5, MySQL (SQLite in tests), Blade, plain JS in `public/assets/js`, Tailwind CDN.

**Spec:** `docs/superpowers/specs/2026-11-08-categories-admin-design.md` (read it first; this plan only decides what the spec leaves open).

## Global Constraints

- Work on branch `backend`. **Commit after every task, adding only that task's files** (never `git add .`). Do not touch or commit `backend/resources/js/app.js`, `backend/resources/views/home.blade.php` or `backend-old-skeleton/`. Do not push; the owner decides that.
- Test-first for every behaviour: watch the new test fail for the right reason, then implement.
- `categories` columns: `id`, `slug` string(50) unique, `name` string(50) unique, `icon` string(16), `is_active` boolean NOT NULL default true, timestamps. The four seeded rows, in this order, all ON: `phones` / Phones / 📱, `earphones` / Earphones / 🎧, `powerbanks` / Power Banks / 🔋, `laptops` / Laptops / 💻. **`products.category` is not changed.**
- Code (slug): `Str::slug($name)` cut to 50 characters; if empty, `c` + 8 random hex characters; if taken, `-2`, `-3`… The slug is written once and never changed.
- Name rules (same as brands): required, string, at most 50, `/^[\p{L}\p{N} .\-]+$/u`, unique ignoring case via `lower(name)`; on rename only the new name is checked. Taken-name message: `That category already exists.`
- Icon rules: optional, at most 16 characters, no letters, digits, `<`, `>`, `&` or quotes (`/^[^\p{L}\p{N}<>&"'`]+$/u`), else `Use a single emoji, for example 📱.`; empty becomes `📦`.
- Category JSON everywhere in the admin: `{id, slug, name, icon, isActive, productCount}` (`productCount` counts `products.category = slug`, hidden products included). Errors stay `422 {message, errors}`; the delete rule is `422 {ok:false, error}`.
- Exact messages: new product on an OFF category: `That category is switched off.`; customers: `{product name} is no longer available.` (checkout), `This product is no longer available.` (favorite toggle). Toasts: `{name} is OFF. Its {n} product(s) are hidden from the shop.` (`product is` when n is 1, `products are` otherwise) and `{name} is ON again.`
- Description of a category: names of ON brands that have a visible product in it, alphabetical (case-insensitive), at most 4, joined with ` · `; empty string when none.
- Every migration reversible; no new composer/npm packages, `.env` settings or build steps. New admin UI works in dark mode at WCAG AA contrast (4.5:1 normal text) and light mode.
- Escape every category name, icon and code that reaches HTML or JS (`{{ }}` in Blade, `escapeHtml`/`escapeAttr` in JS, `@json` for data).

## Review Focus

Inputs the spec implies that no obvious test would cover; each is pinned by a test in the named task.

1. A customer opens `/shop/<code>` for an OFF category or a code that does not exist: a plain 404, never a 500. (Task 4)
2. Editing the stock of a product whose category is OFF (the edit window re-sends the category): must succeed. (Task 5)
3. An old product whose category code is not in the table at all (for example `Apple`): still visible in the shop, no error in the admin tables or in checkout. (Tasks 1, 4, 5)
4. A category icon or name carrying markup (`<script>`) or an emoji sequence: refused or shown as text everywhere, never executed. (Tasks 3, 6, 7)
5. Delete and rename versus products: delete is refused while any product uses the code even if that product is hidden (OFF brand), and a rename keeps the code so `/shop/<code>` and existing products keep working. (Task 3)

---

### Task 1: The table, the model and the visibility rule

**Files:**
- Create: `backend/database/migrations/2026_11_08_100000_create_categories_table.php`, `backend/app/Models/Category.php`
- Modify: `backend/app/Models/Product.php`
- Test: `backend/tests/Feature/CategoriesTableTest.php` (new; `DatabaseMigrations, MakesShopData` like `BrandStatusAndLogoColumnsTest`)

**Interfaces:**
- Produces: table `categories`; `App\Models\Category` (`#[Fillable(['slug','name','icon'])]`, `is_active` cast to bool and written with `forceFill`, `scopeActive`); `Product::visible()` now also excludes products whose `category` is a category that exists and is OFF (an unknown code stays visible).

- [ ] **Step 1: Write the failing tests:**
  - `test_the_four_current_categories_are_seeded_in_order_and_on`: `Category::orderBy('id')->get(['slug','name','icon','is_active'])` equals the four rows from Global Constraints, all `is_active === true`.
  - `test_a_new_category_is_on`: `Category::create(['slug'=>'x','name'=>'X','icon'=>'📦'])->fresh()->is_active === true`.
  - `test_active_scope_selects_only_categories_that_are_on`.
  - `test_visible_hides_products_of_an_off_category_but_not_of_an_unknown_one`: products in `phones` (made OFF), `earphones`, and `Apple` (not in the table): `Product::visible()` lists the earphones and the `Apple` product only.
  - `test_visible_still_hides_products_of_an_off_brand`: an OFF brand's product in an ON category is hidden.
  - `test_down_drops_the_table`: via `migrate:rollback --step=1` (not a hand-called `down()`, see the brands plan).
- [ ] **Step 2: Run** `php artisan test tests/Feature/CategoriesTableTest.php`. Expected: all FAIL (no table).
- [ ] **Step 3: Implement** the migration (create + insert the four rows), the model and the scope; `Product::scopeVisible` adds `whereNotIn('category', Category::where('is_active', false)->select('slug'))`.
- [ ] **Step 4: Run** the file, then the whole suite (`php artisan test`; `BrandsMigrationTest` must stay green: its helper rolls back until `brands` is gone). Expected: green.
- [ ] **Step 5: Commit** the migration, the two models, the test; message `Add the categories table and hide products of a switched-off category`.

### Task 2: One list for every customer-facing view

**Files:**
- Create: `backend/app/Support/ShopCategories.php`
- Test: `backend/tests/Feature/ShopCategoriesSupportTest.php` (new; `RefreshDatabase, MakesShopData`)

**Interfaces:**
- Consumes: `Category`, `Brand`, `Product::visible()` (Task 1 and the brands work).
- Produces: `App\Support\ShopCategories` with `active(): Illuminate\Support\Collection` (ON categories in creation order, each `['slug'=>string,'label'=>string,'icon'=>string,'desc'=>string]`; the key is named `label` so existing views need few edits), `find(string $slug): ?array` (same shape, ON categories only, else `null`) and `icons(): array<string,string>` (slug => icon for EVERY category, ON or OFF). Not cached between calls.

- [ ] **Step 1: Write the failing tests:**
  - `test_active_lists_on_categories_in_creation_order_with_label_icon_and_slug`.
  - `test_an_off_category_is_not_listed_and_find_returns_null_for_it_and_for_an_unknown_code`.
  - `test_icons_include_off_categories` (an old cart must still find its icon).
  - `test_description_is_the_on_brands_with_products_in_that_category`: brands Samsung, Apple, Anker in `phones`, a brand OFF, a brand with products only in `laptops`: description for `phones` is `Anker · Apple · Samsung` (alphabetical, only those three).
  - `test_description_shows_at_most_four_brands`: five brands give the first four alphabetically.
  - `test_description_is_empty_when_no_visible_product_has_that_category`.
- [ ] **Step 2: Run** the file. Expected: FAIL (class missing).
- [ ] **Step 3: Implement** `ShopCategories`. For the descriptions use ONE query: distinct `products.category` + `brands.name` joined on `brand_id` where `brands.is_active` is true, grouped in PHP, sorted case-insensitively, first four.
- [ ] **Step 4: Run** the file and the whole suite. Expected: green.
- [ ] **Step 5: Commit**; message `Add ShopCategories, the one category list for the shop`.

### Task 3: The admin API for categories

**Files:**
- Create: `backend/app/Http/Controllers/Admin/CategoryController.php`, `backend/app/Http/Controllers/Admin/Concerns/ValidatesUniqueNames.php`
- Modify: `backend/app/Models/Category.php` (`uniqueSlugFor`), `backend/app/Http/Controllers/Admin/BrandController.php` (use the shared trait), `backend/routes/web.php` (admin group)
- Test: `backend/tests/Feature/AdminCategoriesTest.php` (new; same `actingAsAdmin()` helper as `AdminBrandsTest`)

**Interfaces:**
- Consumes: `Category` (Task 1).
- Produces: routes `admin-categories.index|store|update|destroy` (`GET/POST /admin/categories`, `PUT/DELETE /admin/categories/{category}`); `Category::uniqueSlugFor(string $name): string` (static); trait method `uniqueNameRules(string $model, ?Model $ignoring, string $takenMessage): array` (the brand rules, parameterised); `BrandController::nameRules()` keeps its behaviour and message `That brand already exists.`; `PUT` accepts `name`, `icon` and/or `is_active` (at least one, else `422` on `name`) and never touches `slug`.

- [ ] **Step 1: Write the failing tests** (each with the exact assertions from the spec's section 6): list order and `productCount` counted by code (a hidden product counts); `POST` with and without an icon (`📦`); the code from `Gaming Mice` is `gaming-mice`, from a second `Gaming-Mice` is `gaming-mice-2`, from `ខ្មែរ` matches `/^c[0-9a-f]{8}$/`; duplicate name ignoring case and padding; bad characters in the name; icon `<script>`, `abc`, 17 characters all `422` with the exact icon message; rename keeps the code; icon change; switch OFF and ON (`is_active` boolean, `'maybe'` is `422`); `PUT []` is `422`; delete is refused with `{ok:false,error}` while a product (even one of an OFF brand) uses the code; delete works when empty; guest and customer are redirected to `admin-login`.
- [ ] **Step 2: Run** `php artisan test tests/Feature/AdminCategoriesTest.php tests/Feature/AdminBrandsTest.php`. Expected: the new tests FAIL, the brand tests pass.
- [ ] **Step 3: Implement** the controller (`index` with one grouped count query, `store`, `update`, `destroy`, `transform`), the trait (move the brand name rule into it and make `BrandController` use it), `uniqueSlugFor`, and the routes.
- [ ] **Step 4: Run** both files and the whole suite. Expected: green (the brand tests prove the refactor).
- [ ] **Step 5: Commit**; message `Add the admin API for categories`.

### Task 4: The shop reads its categories from the database

**Files:**
- Modify: `backend/routes/web.php` (drop the `whereIn` on `/shop/{category}`), `backend/app/Http/Controllers/ShopController.php`, `backend/app/Providers/AppServiceProvider.php` (a view composer for `layouts.app` sharing `$shopCategories`), `backend/resources/views/layouts/app.blade.php` (both menu loops), `backend/resources/views/shop.blade.php` (the four hard-coded cards and `CATEGORIES`), `backend/app/Actions/PlaceOrder.php`
- Test: `backend/tests/Feature/ShopCategoriesTest.php` (new; `RefreshDatabase, MakesShopData`)

**Interfaces:**
- Consumes: `ShopCategories::active()/find()` (Task 2), `Product::visible()` (Task 1).
- Produces: `ShopController::category(string $category)` answers `404` (`abort(404)`) unless `ShopCategories::find($category)` returns a row, and passes `category` as that row (keys `slug`, `label`, `icon`, `desc`, the same shape the view got before); `shop` receives `categories` (`ShopCategories::active()`) and renders the cards and `CATEGORIES` from it; the layout renders both menu loops from `$shopCategories`; the brand buttons list only ON brands that have a **visible** product in that view (`whereHas('products', fn ($q) => $q->visible()...)`), so a brand whose products are all in an OFF category has no button; `PlaceOrder` throws `OrderRejected("{name} is no longer available.")` for a product whose category is OFF, before the stock checks.

- [ ] **Step 1: Write the failing tests** in `ShopCategoriesTest`:
  - `test_the_menu_cards_and_tabs_come_from_the_database`: a new category `Gaming Mice` (🖱️) with an ON-brand product: `GET /` contains its name and `route('shop.category','gaming-mice')`; `GET /shop` has it in `viewData('categories')`, in the page as a card with `setCategory('gaming-mice')`, and the four old cards still render.
  - `test_a_renamed_category_keeps_its_address_and_shows_the_new_name`.
  - `test_an_off_category_disappears_everywhere_customers_look`: no menu entry, no card, not in `categories`, its products missing from `/shop`, from the favorites list, and `GET /shop/{code}` is `404`; switching it back ON restores all of that.
  - `test_an_unknown_category_address_is_404_not_500`: `/shop/nope` and `/shop/Apple`. (Review Focus 1)
  - `test_a_product_with_an_unknown_old_category_is_still_shown_in_the_shop`. (Review Focus 3)
  - `test_a_category_description_never_names_a_switched_off_brand`: the `Phones` card and menu text exclude an OFF brand.
  - `test_a_brand_whose_only_products_are_in_an_off_category_has_no_button`: on `/shop` and on a category page, `viewData('brands')` lacks it; it returns when the category is back ON.
  - `test_a_product_of_an_off_category_cannot_be_favorited_or_bought`: favorite toggle `422` with the exact message; checkout `422` `{name} is no longer available.`, `Order::count() === 0`, stock unchanged.
- [ ] **Step 2: Run** `php artisan test tests/Feature/ShopCategoriesTest.php`. Expected: the new tests FAIL.
- [ ] **Step 3: Implement** the routing, controller, composer, the two views (the cards become a `@foreach ($categories as $cat)` with escaped `{{ }}` values and `onclick="setCategory('{{ $cat['slug'] }}')"`, the slug being `[a-z0-9-]` by construction) and the `PlaceOrder` check (`Category::where('is_active', false)` slugs).
- [ ] **Step 4: Run** the file and the whole suite. Expected: green.
- [ ] **Step 5: Commit**; message `Make the shop read its categories from the database`.

### Task 5: Admin product rules for OFF categories

**Files:**
- Modify: `backend/app/Http/Controllers/Admin/ProductController.php` (`store`, `update`, `index`, `transform`; remove `categorySlugs()`)
- Test: `backend/tests/Feature/AdminProductsTest.php`

**Interfaces:**
- Consumes: `Category` (Task 1).
- Produces: category rules `['required'|'sometimes','string', Rule::exists('categories','slug'), categoryIsOn(?Product $editing = null)]` (the same "only check what changed" idea as `brandIsOn`); product JSON gains `categoryName` (`?string`, `null` for an unknown code) and `categoryActive` (`bool`, `true` for an unknown code); `index()` looks the categories up once, not per product.

- [ ] **Step 1: Write the failing tests** (add to `AdminProductsTest`; the existing `phones`, `Apple` and legacy-category tests must keep passing): `test_store_refuses_a_category_that_is_switched_off` (`422`, error on `category`, `That category is switched off.`); `test_a_product_whose_category_is_off_can_still_have_its_stock_and_price_changed` (PUT with the same `category`; Review Focus 2); `test_update_refuses_moving_a_product_to_a_category_that_is_off`; `test_product_json_carries_category_name_and_active` (ON, OFF and unknown code → `null`/`true`; Review Focus 3).
- [ ] **Step 2: Run** the file. Expected: the new tests FAIL.
- [ ] **Step 3: Implement** as described in Interfaces.
- [ ] **Step 4: Run** the file and the whole suite. Expected: green.
- [ ] **Step 5: Commit**; message `Stop new products joining a switched-off category`.

### Task 6: The Categories page, the dropdowns and the tags

**Files:**
- Create: `backend/public/assets/js/categories.js`
- Modify: `backend/resources/views/admin-view.blade.php` (sidebar item, `#view-categories`, the two category selects emptied, the "+ Add new category…" box, the new script tag after `sales-report.js`), `backend/public/assets/js/admin.js` (`showView` list/titles/hook, `openEdit`, product table cells), `backend/public/assets/css/admin-dark.css` (only if the Brands switch styles do not already cover it)
- Test: `backend/tests/Feature/AdminViewTest.php`

**Interfaces:**
- Consumes: `/admin/categories` (Task 3); product JSON `categoryName`/`categoryActive` (Task 5); from `admin.js`: `apiFetch`, `escapeHtml`, `escapeAttr`, `toast`, `toastError`, `products`, `refreshAll`, `setSelectValue`, `brandSwitchHtml`-style switch CSS classes (`brand-switch`).
- Produces (JS, `categories.js`): `categories` state; `loadCategories()` (also called once at the end of the file, because `admin.js` runs `init()` before this script loads); `renderCategorySelects()` (`#f-category` = ON categories + "+ Add new category…"; `#m-category` = ON categories plus the edited product's own category labelled `(off)` when it is OFF), `renderCategoriesTable()`, `addCategory()`, `toggleCategory(id)`, `startEditCategory(id)`, `saveEditCategory(id)`, `cancelEditCategory()`, `deleteCategory(id)`, `onCategoryChange(select)`, `saveNewCategory()`, `cancelNewCategory()`, `initCategories()` (called by `showView('categories')`); in `admin.js`: `categoryCellHtml(p)` (name, or the raw code when unknown, plus a `Category off` tag when `categoryActive === false`). Element ids: `nav-categories`, `view-categories`, `category-add-name`, `category-add-icon`, `categories-tbody`, `categories-empty`, `new-category-box`, `new-category-name`.
- Ruling made in this plan: the row actions are **Edit** (name and icon inline together, one Save/Cancel) and **Delete**, instead of two separate buttons; the spec's "Rename, Change icon" are both done by Edit.

- [ ] **Step 1: Write the failing tests** in `AdminViewTest`: the page contains `id="nav-categories"`, `showView('categories')`, `id="view-categories"`, `id="category-add-name"`, `id="category-add-icon"`, `id="categories-tbody"`, `id="new-category-box"`, `id="f-category"`, `id="m-category"`, and in order the headings `>Icon</th>`, `>Category</th>`, `>Code</th>`, `>Products</th>`, `>Status</th>`, `>Actions</th>`; `test_admin_view_renders_the_brands_page_and_the_category_selects` loses its two `<option value="phones">`/`powerbanks` assertions (the selects are filled by JS now; the options come from the API test) and `assertDontSee('<option value="phones">Phones</option>')` proves the old server-written options are gone.
- [ ] **Step 2: Run** `php artisan test tests/Feature/AdminViewTest.php`. Expected: the new/changed assertions FAIL.
- [ ] **Step 3: Implement** the markup and `categories.js` as in Interfaces; the table columns are Icon (large), Category, Code (small grey), Products, Status (the On/Off switch with its words, reusing the `brand-switch` classes), Actions. `toggleCategory` updates `products[].categoryActive` for that code and shows the exact toasts. Escape everything.
- [ ] **Step 4: Run** `AdminViewTest` and the whole suite. Expected: green.
- [ ] **Step 5: Verify in Chrome** (local server on a spare port, local admin login from the project memory): add a category with an emoji and without one; reject `<script>` as icon with the plain message; Edit changes name and icon and keeps the code; switch OFF/ON with the exact toasts; delete refused while a product uses it and works when empty; add a category from the Add Product form; the Add Product dropdown offers ON categories only and the edit window shows `(off)` for a product of an OFF category and saves its stock; the tables show the name and the `Category off` tag; light and dark mode and the contrast scan on `#view-categories` (no text under 4.5:1). Remove all test data. Fix anything found.
- [ ] **Step 6: Commit**; message `Add the Categories page to the admin`.

### Task 7: Icons in the shop scripts

**Files:**
- Modify: `backend/resources/views/layouts/app.blade.php` (define `window.CATEGORY_ICONS`), `backend/public/assets/js/shop.js` (product card fallback), `backend/public/assets/js/script.js` (cart drawer)
- Test: `backend/tests/Feature/ShopCategoriesTest.php`

**Interfaces:**
- Consumes: `ShopCategories::icons()` (Task 2).
- Produces: `window.CATEGORY_ICONS` = `{slug: icon}` for every category, on every page that uses the layout; both scripts use `(window.CATEGORY_ICONS || {})[category] || '📦'`.

- [ ] **Step 1: Write the failing tests:** `test_every_page_carries_the_category_icon_map`: `GET /` contains `window.CATEGORY_ICONS` with `phones` → 📱 and a new category's icon, and an OFF category is included. (Review Focus 4 for the escaping: the map is written with `@json`.)
- [ ] **Step 2: Run** the file. Expected: FAIL.
- [ ] **Step 3: Implement** the three edits.
- [ ] **Step 4: Run** the file and the whole suite. Expected: green.
- [ ] **Step 5: Verify in Chrome:** with a new category (icon 🖱️) and a product in it whose image fails to load, the shop card shows 🖱️ (not 💻); the cart drawer line for that product shows 🖱️; the four old categories keep their icons. Remove test data.
- [ ] **Step 6: Commit**; message `Show each category's own icon in the shop and the cart`.

### Task 8: Retire the settings list and verify the whole feature

**Files:**
- Modify: `backend/config/shop.php` (remove the `categories` key and its comment; keep `timezone` and `brand_logos`)

- [ ] **Step 1:** `grep -rn "shop.categories" backend/app backend/resources backend/routes backend/tests backend/config backend/database` must find nothing once the key is removed; fix any hit first.
- [ ] **Step 2:** remove the key, run `php artisan test`. Expected: all pass; record the count.
- [ ] **Step 3: Migration on real MySQL:** dump the local `ict_tech` database BEFORE migrating, load it into a scratch database, run `php artisan migrate --force` against the scratch copy only (`DB_DATABASE` for that one command), confirm four categories ON in order, every product's `category` unchanged, then `migrate:rollback --step=1` and `migrate` again, then drop the scratch database. Then migrate the real local database.
- [ ] **Step 4: End to end in Chrome** (admin and a customer in two separate browser profiles; a throwaway customer account that you remove afterwards): admin adds a category with an icon and a product in it; the customer sees the menu entry, the card, the tab, the product with the right icon; admin switches it OFF: the customer's menu, card, tab and products are gone, `/shop/<code>` is `404`, favorites hide it and checkout is refused with the exact message and nothing saved; ON again restores everything; rename keeps the address; delete is refused while the product exists and works after it is removed. Remove all test data; confirm no `Zz` rows remain.
- [ ] **Step 5:** `git status`: only the files named in this plan changed and the owner's unrelated edits are untouched.
- [ ] **Step 6: Commit** the config change; message `Retire the category list in the settings file`. Then report to the owner: what was verified, the test count, the update steps (backup, pull, migrate, `optimize:clear`), and anything not checked.
