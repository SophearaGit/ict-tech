# Managed brands and category select — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the free-text product brand with a managed `brands` list (admin Brands page, brand and category selects in the product form), and build the shop's brand buttons from the database.

**Architecture:** A `brands` table and a required `products.brand_id` replace `products.brand` (three reversible migrations, with a case-insensitive backfill). `Product::brand()` is a belongsTo, so every reader switches to `$product->brand->name` and eager-loads it. A new admin `BrandController` manages brands. `config/shop.php` stays the only source of categories. The product JSON keeps `brand` as the name string, so `shop.js` does not change.

**Tech Stack:** Laravel 13, PHP 8.5, MySQL (dev/prod), SQLite in-memory (tests), PHPUnit class-style tests, Blade, plain JS (`public/assets/js/admin.js`).

**Spec:** `docs/superpowers/specs/2026-11-04-brands-and-categories-design.md`

## Global Constraints

- All commands run from `backend/`. Tests: `php artisan test --filter=<Name>`; a passing run prints `"result":"passed"`.
- Tests run on in-memory SQLite (`phpunit.xml`), TDD: write the test, watch it fail for the right reason, then implement.
- **Never** run `php artisan db:seed` on the local MySQL (it duplicates every product). Back up any real database before `migrate`. Do not push; the owner decides.
- Brand name rules (create and rename only): required, string, trimmed (Laravel's `TrimStrings` already trims), max 50, `/^[\p{L}\p{N} .\-]+$/u`, unique **ignoring case** via a `lower(name)` comparison that ignores the brand being renamed (so `apple` → `Apple` on the same brand is allowed). The backfill copies legacy names unchanged.
- Category validation: `Rule::in(array_keys(config('shop.categories')))`. Brand validation: `brand_id` required, integer, `exists:brands,id`.
- `$product->brand` is the relationship; read `$product->brand->name`. `order_items.brand` stays a text snapshot.
- Product JSON keeps `brand` (name string) and `category` (slug). Admin product JSON adds `brandId` (int). Shop and favorites JSON do not.
- Error shapes: validation failures use Laravel's JSON (`message`, `errors`). Brand delete blocked: `422 {"ok":false,"error":"<Name> still has <n> product(s). Move or delete them first."}` (use `Str::plural('product', $n)`).
- Test style: `Tests\Feature`, `RefreshDatabase`, `MakesShopData`, `$this->actingAs($this->makeAdmin(), 'admin')`; guests are redirected: `->assertRedirect(route('admin-login'))`.
- Every commit message ends with `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`. One commit per task.

## Review Focus

Failure modes the spec implies; each has a test in the task that owns the code.

1. Brand name padded with spaces or in different case (`"  APPLE "`) when `Apple` exists → 422, and a stored name is trimmed. (Task 2)
2. Deleting a brand that has products straight through the model, bypassing the controller → the database refuses (`restrictOnDelete`), so a product can never lose its brand. (Task 1)
3. Renaming a brand → shop JSON shows the new name, while an already-placed order keeps the old name on `order_items`. (Task 3)
4. A legacy brand outside the allowed characters (`Black & Decker`) survives the backfill, and can still be renamed to a valid name. (Tasks 1 and 2)
5. `brand_id` that is non-numeric (`"abc"`) or an array, or a category that is a brand name (`"Apple"`) → 422, never a 500. (Task 1)

---

### Task 1: Products link to brands (schema, models, product API)

**Files:**
- Create: `database/migrations/2026_11_04_100000_create_brands_table_and_add_brand_id_to_products.php`, `…_100100_backfill_product_brands.php`, `…_100200_require_brand_id_and_drop_products_brand.php`
- Create: `app/Models/Brand.php`
- Modify: `app/Models/Product.php`, `app/Http/Controllers/Admin/ProductController.php`, `app/Http/Controllers/ShopController.php`, `app/Http/Controllers/FavoriteController.php`, `app/Actions/PlaceOrder.php` (query at :30-34, snapshot at :76), `database/seeders/ProductSeeder.php`
- Modify tests: `tests/Concerns/MakesShopData.php`, `tests/Feature/OrderModelsTest.php:30`
- Create tests: `tests/Feature/BrandsMigrationTest.php`, `tests/Feature/AdminProductsTest.php`, `tests/Feature/FavoritesTest.php`, `tests/Feature/ProductSeederTest.php`

**Interfaces:**
- Produces: `App\Models\Brand` (`#[Fillable(['name'])]`, `products(): HasMany`); `Product::brand(): BelongsTo`, fillable `brand_id` replaces `brand`; `MakesShopData::makeBrand(string $name = 'Acme'): Brand` (first-or-create by name) and `makeProduct()` defaulting `brand_id` to `makeBrand()->id` unless the override sets `brand_id`. Later tasks use all of these.
- Product admin JSON: `{id (string), name, brand (name), brandId (int), type, category, price, qty, desc, specs, image, imageAlt, trending, badge}`.

- [ ] **Step 1: Write the failing tests.** First change `MakesShopData` (`makeBrand`, `makeProduct`) and `OrderModelsTest:30` (`$product->brand->name`). Then add:
  - `BrandsMigrationTest` (trait `DatabaseMigrations`, not `RefreshDatabase`, so the test can roll back and migrate forward with no wrapping transaction). A helper rolls back with `artisan('migrate:rollback', ['--step' => 3])` (asserts `Schema::hasColumn('products','brand')` and no `brands` table), inserts legacy rows with `DB::table('products')->insert(...)`, then `artisan('migrate')`.
    - `test_backfill_merges_case_variants_and_links_every_product`: rows `Apple`, `Apple`, `apple`, `Sony` → brands `['Apple','Sony']`, no product with null `brand_id`, the `apple` row's brand name is `Apple`, `products.brand` column gone.
    - `test_a_tie_goes_to_the_spelling_on_the_lowest_product_id`: `apple` (first id) + `Apple` → one brand named `apple`.
    - `test_names_are_trimmed_before_grouping`: `"  Sony "` + `Sony` → one brand `Sony`.
    - `test_legacy_names_outside_the_allowed_characters_are_kept`: `Black & Decker` → brand named exactly that.
    - `test_down_restores_the_text_column`: after a normal migrate, a brand `Dell` with a product; rollback `--step=3` → `products.brand` equals `Dell`.
    - `test_the_database_refuses_to_delete_a_brand_that_has_products`: `makeProduct()`; `Brand::first()->delete()` throws `Illuminate\Database\QueryException`; the product still exists.
  - `AdminProductsTest` (admin helper like `AdminOrdersTest`):
    - `test_store_creates_a_product_with_brand_and_category`: POST `route('admin-products.store')` `{name:'Galaxy S24', brand_id:<Samsung>, category:'phones', price:900, qty:5, desc:'x', trending:true}` → 201; json `brand` `'Samsung'`, `brandId` = the brand id, `category` `'phones'`; row saved with that `brand_id`.
    - `test_store_rejects_unknown_non_numeric_and_array_brand_ids`: `99999`, `'abc'`, `[1]` → 422 with an `errors.brand_id`, nothing saved.
    - `test_store_rejects_a_brand_name_as_category_and_a_missing_category`: `'Apple'` and omitted → 422 `errors.category`.
    - `test_update_changes_brand_and_category_and_returns_the_new_brand_name`.
    - `test_quantity_only_update_works_for_a_product_with_an_invalid_legacy_category`: product with `category 'Apple'`, PUT `{qty: 9}` → 200, `qty` 9, category still `'Apple'`.
    - `test_update_rejects_an_invalid_category`: PUT `{category:'Apple'}` → 422.
    - `test_index_lists_brand_name_and_brand_id`.
    - `test_guests_are_redirected_to_admin_login` (store, update, index).
  - `FavoritesTest::test_favorites_json_carries_the_brand_name`: customer favorites a product of brand `Acme` (insert into `favorites` via `$customer->favoriteProducts()->attach($product->id)`), `actingAs($customer)->getJson(route('favorites'))` → `0.brand` is `'Acme'`.
  - `ProductSeederTest::test_seeder_creates_brands_and_links_every_product`: `$this->seed(ProductSeeder::class)` → `Product::count()` 25, `Brand::count()` 8, no product without a brand.
- [ ] **Step 2: Run** `php artisan test` — the suite fails (missing `brands`/`Brand`/`brand_id`), not for syntax reasons.
- [ ] **Step 3: Migration 1** — create `brands` (`id`, `name` string(50) unique, timestamps) and add `products.brand_id` as `foreignId('brand_id')->nullable()->constrained('brands')->restrictOnDelete()`. `down()` drops the foreign key and column, then `brands`.
- [ ] **Step 4: Migration 2 (backfill)** — with `DB::table` only: read `id, brand` of every product, group by `mb_strtolower(trim($brand))`; per group the name is the trimmed spelling used by most products (tie → the spelling of the lowest product id); insert the brands with `now()` timestamps; set each product's `brand_id`. `down()` is a no-op (migration 3's `down()` restores the text).
- [ ] **Step 5: Migration 3** — `brand_id` becomes NOT NULL (`->nullable(false)->change()`); drop `products.brand`. `down()`: add `brand` string nullable, fill it with `update products set brand = (select name from brands where brands.id = products.brand_id)`, make `brand` NOT NULL, make `brand_id` nullable again.
- [ ] **Step 6: Models** — `Brand` and `Product::brand()` as in Interfaces. Do not add anything else.
- [ ] **Step 7: Readers** — add `->with('brand')` and use `->brand->name` in: `ShopController` (`index` and `category` queries, `transform`, the `category()` brand grouping/label; keep `ALL_BRANDS` for now), `FavoriteController` (the `favoriteProducts()` query and `transform`), `PlaceOrder` (the locked product query loads `brand`; the item snapshot is `$product->brand->name`).
- [ ] **Step 8: `Admin\ProductController`** — `index` eager-loads `brand`; `store` rules `brand_id` => `['bail','required','integer','exists:brands,id']`, `category` => `['required', Rule::in(array_keys(config('shop.categories')))]` (other fields unchanged), `Product::create` uses `brand_id`; `update` uses the same rules with `sometimes`; `store`/`update` return `transform($product->load('brand'))`; `transform` returns `brand` (name) and `brandId` (int).
- [ ] **Step 9: `ProductSeeder`** — `Brand::firstOrCreate(['name' => $row['brand']])`, replace `brand` with `brand_id` before `Product::create`. (Fresh installs only.)
- [ ] **Step 10: Run** `php artisan test` — everything passes (previous 68 tests plus the new ones).
- [ ] **Step 11: Commit** — `git add database app tests && git commit -m "Link products to a managed brands table"`.

---

### Task 2: Admin brand endpoints

**Files:**
- Create: `app/Http/Controllers/Admin/BrandController.php`, `tests/Feature/AdminBrandsTest.php`
- Modify: `routes/web.php` (inside the `admin` middleware group, after the products routes)

**Interfaces:**
- Consumes: `Brand`, `MakesShopData::makeBrand/makeProduct/makeAdmin` (Task 1).
- Produces: routes `admin-brands.index` (GET `/admin/brands`), `admin-brands.store` (POST), `admin-brands.update` (PUT `/admin/brands/{brand}`), `admin-brands.destroy` (DELETE). Brand JSON: `{id:int, name:string, productCount:int}`, list ordered by name case-insensitively.
- `BrandController::index(): JsonResponse`, `store(Request): JsonResponse` (201), `update(Request, Brand): JsonResponse`, `destroy(Brand): JsonResponse`; private `nameRules(?Brand $ignoring = null): array` holding the rules from Global Constraints.

- [ ] **Step 1: Write the failing tests** in `AdminBrandsTest`:
  - `test_index_lists_brands_with_product_counts_ordered_by_name`: brands `Sony` (0 products), `apple` (2), `Dell` (1) → names `['apple','Dell','Sony']`, counts `[2,1,0]`.
  - `test_store_creates_a_brand`: `{name:'Huawei'}` → 201 `{name:'Huawei', productCount:0}`.
  - `test_store_rejects_duplicates_ignoring_case_and_padding`: with `Apple` existing, `'apple'` and `'  APPLE '` → 422 `errors.name`; `Brand::count()` still 1.
  - `test_store_trims_the_stored_name`: `'  Oppo '` → stored `'Oppo'`.
  - `test_store_rejects_bad_names`: `''`, 51 characters, `'<b>x'`, `"O'Neil"`, `'a_b'` → 422.
  - `test_rename_allows_a_case_only_change_of_the_same_brand`: `apple` → `Apple` → 200; renaming `Sony` to `Apple` (other brand) → 422.
  - `test_a_legacy_brand_with_disallowed_characters_can_be_renamed_to_a_valid_name`: brand created with `Brand::create(['name'=>'Black & Decker'])`, rename to `Black and Decker` → 200.
  - `test_destroy_removes_an_empty_brand`: 200 `{ok:true}`, row gone.
  - `test_destroy_is_blocked_while_the_brand_has_products`: 2 products → 422 `ok false`, `error` `'Acme still has 2 products. Move or delete them first.'`; with 1 product the message says `1 product`; brand still exists.
  - `test_guests_and_customers_are_redirected_to_admin_login` (all four routes).
- [ ] **Step 2: Run** `php artisan test --filter=AdminBrandsTest` — fails (404/route not defined).
- [ ] **Step 3: Implement** the controller and routes. Case-insensitive uniqueness: a closure rule `Brand::whereRaw('lower(name) = ?', [mb_strtolower($value)])->when($ignoring, fn ($q) => $q->whereKeyNot($ignoring->id))->exists()`. List with `withCount('products')` then sort in PHP with `sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()`.
- [ ] **Step 4: Run** `php artisan test --filter=AdminBrandsTest` — passes.
- [ ] **Step 5: Commit** — `git commit -m "Add admin brand endpoints"` (add the files listed above).

---

### Task 3: Shop brand buttons from the database

**Files:**
- Modify: `app/Http/Controllers/ShopController.php` (remove `ALL_BRANDS`; `index` at :23, `category` at :40)
- Create: `tests/Feature/ShopBrandsTest.php`

**Interfaces:**
- Consumes: `Brand`, `Product::brand()`, `MakesShopData` (Task 1).
- Produces: view variable `brands` = list of `['id' => mb_strtolower($name), 'label' => $name]`, brands with at least one product (in that category for `category()`), ordered with `sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)`. Private `ShopController::brandPills(iterable $brands): array`.

- [ ] **Step 1: Write the failing tests** in `ShopBrandsTest` (use `$this->get(route('shop'))->assertViewHas('brands', fn ($b) => …)`):
  - `test_shop_lists_only_brands_that_have_products`: brands `Apple` (1 product), `Sony` (1), `Nokia` (0) → labels `['Apple','Sony']`, ids `['apple','sony']`.
  - `test_category_page_lists_only_that_categorys_brands`: Apple phone, Sony earphones → `route('shop.category', 'phones')` gives `['Apple']`.
  - `test_product_json_carries_the_brand_name`: `assertViewHas('products')` first item `brand` is `'Apple'`.
  - `test_renaming_a_brand_updates_the_shop_but_not_placed_orders`: place an order for an `Apple` product (`placeOrder`), rename the brand to `Apple Inc` through the model, shop JSON shows `Apple Inc`, `OrderItem` still has `brand` `'Apple'`.
- [ ] **Step 2: Run** `php artisan test --filter=ShopBrandsTest` — the first two fail (hard-coded brands), others may already pass.
- [ ] **Step 3: Implement** `brandPills` and use it in `index` (`Brand::whereHas('products')->get()`) and `category` (`whereHas('products', fn ($q) => $q->where('category', $category))`); delete `ALL_BRANDS`. `shop.js` and `shop.blade.php` do not change.
- [ ] **Step 4: Run** `php artisan test --filter=ShopBrandsTest`, then the whole suite — all pass.
- [ ] **Step 5: Commit** — `git commit -m "Build shop brand buttons from the brands table"`.

---

### Task 4: Admin UI — Brands page, product form, edit modal

**Files:**
- Modify: `resources/views/admin-view.blade.php` (sidebar nav at :81-92, new `view-brands` section next to `view-messages` at :412, add form `#f-brand`/`#f-category` at :359-376, edit modal `#edit-modal` at :456+)
- Modify: `public/assets/js/admin.js`
- Create: `tests/Feature/AdminViewTest.php`

**Interfaces:**
- Consumes: routes `admin-brands.*` (Task 2) and product JSON with `brandId` (Task 1).
- Produces (JS, in `admin.js`): state `let brands = []`; `loadBrands()` (called from `init()` together with products; both must be loaded before the first render); `renderBrandSelects()` rebuilds `#f-brand` (last option `value="__new__"` text `+ Add new brand…`) and `#m-brand` (no `__new__` option), keeping the current selection; `onBrandChange(select)` shows `#new-brand-box` (input `#new-brand-name`, Save, Cancel) when `__new__` is chosen; `saveNewBrand()` POSTs `/admin/brands`, pushes the result, re-renders, selects it; `cancelNewBrand()`; `renderBrandsTable()` (`#view-brands`: add input `#brand-add-name`, table rows with name via `escapeHtml`, product count, Rename, Delete); `addBrand()`, `startRenameBrand(id)`, `saveRenameBrand(id)`, `deleteBrand(id)` (shows the server's `error`/`message` in `toast`). `showView` and its `titles` get `'brands'` (`'Brands'`) and call `renderBrandsTable()`.
- Blade markup the test relies on: sidebar button `onclick="showView('brands')" id="nav-brands"`, section `id="view-brands"`, category options rendered as `<option value="{{ $slug }}">{{ $category['label'] }}</option>` from `config('shop.categories')` in both `#f-category` and `#m-category`, selects `id="m-brand"` and `id="m-category"`.

- [ ] **Step 1: Write the failing test** `AdminViewTest::test_admin_view_renders_the_brands_page_and_the_category_selects`: as admin `get(route('admin-view'))` → OK and `assertSee` (with `escape: false`) `id="view-brands"`, `showView('brands')`, `<option value="phones">Phones</option>`, `<option value="powerbanks">Power Banks</option>`, `id="m-brand"`, `id="m-category"`; `assertDontSee('<option value="Apple">', false)`.
- [ ] **Step 2: Run** `php artisan test --filter=AdminViewTest` — fails.
- [ ] **Step 3: Blade** — add the Brands sidebar item (after "Add Products"), the `view-brands` section (follow the Messages view's card styling), replace `#f-brand` with a `<select>` plus the hidden `#new-brand-box`, replace the brand options in `#f-category` with the config loop, add `#m-brand` and `#m-category` selects to the edit modal. Keep the existing Tailwind classes of neighbouring inputs.
- [ ] **Step 4: `admin.js`** — implement the Interfaces above, plus these behaviours:
  - `submitProductForm`: reads `f-brand` as an integer id; payload `{name, brand_id, category, price, qty, desc, trending}`; the catch block shows `e.message` (fall back to the old text); an empty brand list keeps the existing "fill in all required fields" message and the `+ Add new brand…` option is still available; `resetAddForm` resets `#f-brand` and hides `#new-brand-box`.
  - `openEdit`: set `#m-brand` to `p.brandId`; set `#m-category` to `p.category` only if that slug is one of the select's options, otherwise leave the placeholder.
  - `saveModal`: if no brand or no category is chosen, `toast('Choose a brand and a category.')` and send nothing; otherwise include `brand_id` and `category` in the PUT payload; the catch shows `e.message`.
  - `refreshAll()` also calls `renderBrandsTable()` when the Brands view is open.
- [ ] **Step 5: Run** `php artisan test --filter=AdminViewTest` (passes), `node --check public/assets/js/admin.js` (no output = valid), and the whole suite.
- [ ] **Step 6: Commit** — `git commit -m "Add Brands page and brand/category selects to the admin"`.

---

### Task 5: Migrate the local database and verify in the browser

**Files:** none (verification only; no code changes expected — if a defect is found, fix it in the owning task's files with a test).

- [ ] **Step 1: Dry-run on a copy.** Dump the local DB to the scratchpad directory (password from `backend/.env` via `MYSQL_PWD`; never print it): `mysqldump -u root -h 127.0.0.1 ict_tech > <scratchpad>/ict_tech_before.sql`. Create database `ict_tech_brands_check`, load the dump into it, run `DB_DATABASE=ict_tech_brands_check php artisan migrate --force`. Expected: 3 migrations run; `select name from brands order by name` is `Anker, Apple, Asus, Dell, Jtrust, Samsung, Sony, Xiaomi` (8, with `apple` merged into `Apple`); `select count(*) from products where brand_id is null` is 0; `products` has no `brand` column. Then `DB_DATABASE=ict_tech_brands_check php artisan migrate:rollback --step=3 --force` and confirm `products.brand` is filled again. Drop `ict_tech_brands_check`.
- [ ] **Step 2: Migrate the real local DB** (only after Step 1 passed and the dump file is non-empty): `php artisan migrate --force`. Re-run the two queries from Step 1 against `ict_tech`. Product count stays 26.
- [ ] **Step 3: Full suite and node check:** `php artisan test` passes; confirm MySQL row counts (`products` 26, `orders` 0) are unchanged afterwards (tests must not touch MySQL).
- [ ] **Step 4: Browser walk-through** with `php -S 127.0.0.1:8765 -t public public/index.php` started in the background from `backend/` (the owner supplies the admin login, or does these checks themselves), then stopped by killing the `php` process that listens on port 8765. Check: (a) sidebar shows **Brands**; the table lists 8 brands with counts; (b) add brand `Huawei`, try `huawei` (shows the duplicate error), rename it, delete it; (c) delete `Apple` shows "Apple still has N products…"; (d) Add Products: Brand is a dropdown ending in `+ Add new brand…`, Category lists Phones/Earphones/Power Banks/Laptops; add a product via the inline new-brand box and it appears on `/shop` and under `/shop/phones`; (e) edit modal shows Brand and Category; product #27 shows the "Select category…" placeholder and refuses to save until one is chosen (do **not** change #27 yourself — report it to the owner); (f) `/shop` brand buttons list only brands with products; (g) cart → checkout still works for a customer (an order saves with the brand name).
- [ ] **Step 5: Report** to the owner: what passed, the backup file path, that nothing was pushed, and the reminder from spec section 10 — back up the **live** database before running `php artisan migrate` there.
