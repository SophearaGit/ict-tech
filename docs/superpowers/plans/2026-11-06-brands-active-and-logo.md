# Brands: On/Off Switch and Logo Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give every brand an ON/OFF switch (OFF hides the brand and its products from customers) and an optional logo that is uploaded on the admin Brands page and shown on the shop's brand buttons.

**Architecture:** Two new columns on `brands` (`is_active`, `logo`). A `Product::visible()` scope is the one rule for "a customer may see this product", used by the shop, favorites and checkout. Logos are validated by content, saved by a small `BrandLogoStorage` class under `public/uploads/brands/`, and exposed as `logoUrl`. The admin page (plain JS) gets a status switch, logo upload and tags.

**Tech Stack:** Laravel 13, PHP 8.5 (no `gd`/`imagick`; `fileinfo`, `zlib`, `getimagesize` are available), MySQL (SQLite in tests), Blade, plain JS in `public/assets/js`, Tailwind CDN.

**Spec:** `docs/superpowers/specs/2026-11-06-brands-active-and-logo-design.md` (read it first; this plan only decides what the spec leaves open).

## Global Constraints

- Work on branch `backend`. **Commit after every task, adding only that task's files** (never `git add .`). Do not touch or commit `backend/resources/js/app.js`, `backend-old-skeleton/`, or `backend/resources/views/home.blade.php`. Do not push; the owner decides that.
- Test-first for every behaviour: watch the new test fail for the right reason, then implement.
- Logo files: PNG, JPG or WebP only, detected **from content** (never the file name); at most **800 KB**; at least **32x32** and at most **4000x4000** pixels; **no SVG**.
- Stored as **32 random characters + the extension matching the detected type**, in the folder `config('shop.brand_logos.dir')` (default `public_path('uploads/brands')`); `brands.logo` holds the file name only. `/public/uploads` is git-ignored.
- Brand JSON everywhere: `{id, name, productCount, isActive, logoUrl}`. Validation errors stay `422 {message, errors}`; the delete rule keeps `{ok:false, error}`.
- Exact messages: logo refused: `The logo must be a PNG, JPG or WebP image under 800 KB.`; bad pixel size: `The logo must be between 32 and 4000 pixels wide and tall.`; brand off on the product form: `That brand is switched off.`; favorite toggle: `This product is no longer available.`; checkout: `{product name} is no longer available.`; browser 413: `That file is too big. Logos must be under 800 KB.`
- Toasts: off: `{name} is OFF. Its {n} product(s) are hidden from the shop.` (`product` when n is 1); on: `{name} is ON again.`
- Every migration reversible; no data rewritten; no new composer/npm packages, `.env` settings or build steps.
- New admin UI must work in dark mode (`public/assets/css/admin-dark.css` palette) at WCAG AA contrast (4.5:1 for normal text), and in light mode unchanged.
- Test fixtures must not need `gd`: build PNGs by hand (Task 3 gives the helper) and use `UploadedFile::fake()->createWithContent()`.

## Review Focus

Inputs the spec implies that no obvious test would cover; each is pinned by a test in the named task.

1. Editing the stock of a product whose brand is OFF (the edit window always re-sends the brand): must succeed. (Task 7)
2. An SVG, a PHP script or a GIF named `logo.png`: refused, nothing saved. (Task 3)
3. A file PHP itself rejected for size (upload error code) and a request bigger than nginx allows (`413`, an HTML page, not JSON): both give a plain message, never "Unexpected token". (Tasks 3 and 8)
4. The old logo file is already missing when a logo is replaced, removed or its brand deleted: no error. (Task 4)
5. A cart or favorite saved before the brand was switched OFF: refused at checkout, hidden in favorites, and back when the brand is ON again. (Task 6)

---

### Task 1: Columns, model and the visibility rule

**Files:**
- Create: `backend/database/migrations/2026_11_06_100000_add_is_active_and_logo_to_brands_table.php`
- Modify: `backend/app/Models/Brand.php`, `backend/app/Models/Product.php`, `backend/config/shop.php`
- Test: `backend/tests/Feature/BrandStatusAndLogoColumnsTest.php` (new; uses `DatabaseMigrations, MakesShopData` like `BrandsMigrationTest`)

**Interfaces:**
- Produces: `brands.is_active` (boolean, NOT NULL, default true), `brands.logo` (string 255, nullable); `Brand::$is_active: bool`, `Brand::$logo: ?string`, `Brand::$logo_url: ?string` (accessor), `Brand::active()` scope; `Product::visible()` scope (products whose brand is ON); `config('shop.brand_logos')` = `['dir' => public_path('uploads/brands'), 'path' => 'uploads/brands']`. `Brand`'s `#[Fillable]` stays `['name']`; later tasks write the two new fields with `forceFill`.

- [ ] **Step 1: Write the failing tests**
  - `test_a_new_brand_is_on_with_no_logo`: `Brand::create(['name'=>'Acme'])->fresh()` has `is_active === true`, `logo === null`, `logo_url === null`.
  - `test_logo_url_points_into_the_uploads_folder`: after `forceFill(['logo'=>'abc123.png'])->save()`, `logo_url === asset('uploads/brands/abc123.png')`.
  - `test_active_scope_selects_only_brands_that_are_on`: one ON, one OFF brand; `Brand::active()->pluck('name')` is just the ON one.
  - `test_visible_scope_selects_only_products_of_brands_that_are_on`: products under an ON and an OFF brand; `Product::visible()->pluck('name')` lists only the ON brand's product.
  - `test_down_removes_both_columns`: `(require database_path('migrations/2026_11_06_100000_add_is_active_and_logo_to_brands_table.php'))->down()`, then `Schema::hasColumn('brands','is_active')` and `'logo'` are both false.
- [ ] **Step 2: Run** `php artisan test tests/Feature/BrandStatusAndLogoColumnsTest.php`. Expected: all FAIL (columns/scopes missing).
- [ ] **Step 3: Implement** the migration (`up` adds both columns; `down` drops them), the `Brand` cast + accessor + scope, `Product::scopeVisible(Builder $q): Builder` (`whereHas('brand', fn ($b) => $b->where('is_active', true))`), and the `shop.brand_logos` config key.
- [ ] **Step 4: Run** the same command. Expected: 5 passed. Then `php artisan test` (whole suite) stays green.
- [ ] **Step 5: Commit** `git add` the migration, the two models, `config/shop.php`, the new test; message `Add brand on/off and logo columns and the product visibility rule`.

### Task 2: The switch in the admin API

**Files:**
- Modify: `backend/app/Http/Controllers/Admin/BrandController.php` (`update`, `transform`)
- Test: `backend/tests/Feature/AdminBrandsTest.php`

**Interfaces:**
- Consumes: `Brand::$is_active`, `Brand::$logo_url` (Task 1).
- Produces: `PUT /admin/brands/{brand}` accepts JSON `name` and/or `is_active` (boolean) and returns the brand JSON; `transform()` returns `{id, name, productCount, isActive, logoUrl}` (used by every brand response).

- [ ] **Step 1: Write the failing tests** (add to `AdminBrandsTest`):
  - `test_a_brand_can_be_switched_off_and_on`: `PUT {is_active:false}` is `200` with `isActive:false` and the row is off; then `{is_active:true}` is back on.
  - `test_brand_json_carries_is_active_and_logo_url`: index and store responses include `isActive === true` and `logoUrl === null` for a new brand.
  - `test_update_needs_a_name_or_a_switch`: `PUT []` is `422` with an error on `name`.
  - `test_the_switch_must_be_a_boolean`: `PUT {is_active:'maybe'}` is `422` with an error on `is_active`.
  - `test_renaming_does_not_change_the_switch`: an OFF brand renamed is still OFF.
- [ ] **Step 2: Run** `php artisan test tests/Feature/AdminBrandsTest.php`. Expected: the 5 new tests FAIL, the existing ones pass.
- [ ] **Step 3: Implement** `update` so the name rules (existing `nameRules($brand)`) apply only when `name` is sent (prefix `sometimes`), `is_active` is `['sometimes','boolean']`, and when neither key is present throw a `ValidationException` on `name` ("Send a name or an on/off setting."); write `is_active` with `forceFill`. Extend `transform()`.
- [ ] **Step 4: Run** the file again. Expected: all pass.
- [ ] **Step 5: Commit** `AdminBrandsTest` + `BrandController`; message `Let the admin switch a brand on and off`.

### Task 3: Logo storage and adding a brand with a logo

**Files:**
- Create: `backend/app/Support/BrandLogoStorage.php`, `backend/tests/Concerns/MakesLogoFiles.php`, `backend/tests/Feature/AdminBrandLogoTest.php`
- Modify: `backend/app/Http/Controllers/Admin/BrandController.php` (`store`, new `logoRules()`), `backend/.gitignore` (add `/public/uploads`)

**Interfaces:**
- Produces: `App\Support\BrandLogoStorage::store(UploadedFile $file): string` (saves under `config('shop.brand_logos.dir')`, creating the folder; returns the stored file name `/^[a-z0-9]{32}\.(png|jpg|webp)$/`; the folder is read at call time) and `::delete(?string $name): void` (silent for null or a missing file); `BrandController::logoRules(): array`; `POST /admin/brands` accepts multipart `name` + optional `logo` (a JSON body with only `name` still works).
- Test helper trait `Tests\Concerns\MakesLogoFiles`: `pngBytes(int $w, int $h): string`, `fakeLogo(string $name = 'logo.png', int $w = 64, int $h = 64): UploadedFile` (via `UploadedFile::fake()->createWithContent`), `useTemporaryLogoFolder(): string` (points `shop.brand_logos.dir` at a fresh temp folder and deletes it when the test ends).

- [ ] **Step 1: Write the helper trait.** `pngBytes` is a hand-built PNG (no `gd`): signature `"\x89PNG\r\n\x1a\n"`, then chunks `IHDR` (`pack('NNCCCCC', $w, $h, 8, 2, 0, 0, 0)`), `IDAT` (`gzcompress(str_repeat("\0".str_repeat("\x40\x80\xc0", $w), $h))`) and `IEND`, each as `pack('N', len).type.data.pack('N', crc32(type.data))`. (Checked: `getimagesize` and `finfo` read it as `image/png`.)
- [ ] **Step 2: Write the failing tests** in `AdminBrandLogoTest` (`RefreshDatabase, MakesShopData, MakesLogoFiles`; `setUp` calls `useTemporaryLogoFolder()`; same `actingAsAdmin()` helper as `AdminBrandsTest`):
  - `test_a_brand_can_be_added_with_a_logo`: multipart `POST` is `201`; the stored name matches the pattern; the file exists in the temp folder; `logoUrl === asset('uploads/brands/'.$name)`; `brands.logo` equals `$name`.
  - `test_a_brand_can_still_be_added_without_a_logo`: `logoUrl` is `null`; temp folder empty.
  - `test_the_logo_is_checked_by_content_not_by_name` (data provider: an SVG text, `<?php echo 1;`, GIF bytes `GIF89a...`, each named `logo.png`): `422` with an error on `logo`; no brand created; no file written.
  - `test_wrong_sized_logos_are_refused` (data provider yields only a label: `over 800 KB`, `under 32 px`, `over 4000 px wide`; the test then builds the file: `UploadedFile::fake()->create('big.png', 900)`, `fakeLogo('s.png', 16, 16)`, `fakeLogo('w.png', 4001, 32)`; a provider cannot call the trait's instance method itself): `422` on `logo`, no brand created.
  - `test_a_file_php_rejected_for_size_gets_a_plain_message`: an `UploadedFile` built with error `UPLOAD_ERR_INI_SIZE` gives `422` whose `errors.logo.0` is `The logo must be a PNG, JPG or WebP image under 800 KB.`
- [ ] **Step 3: Run** `php artisan test tests/Feature/AdminBrandLogoTest.php`. Expected: all FAIL.
- [ ] **Step 4: Implement** `BrandLogoStorage` (random 32 chars; extension from `$file->extension()`, which comes from content sniffing; `File::ensureDirectoryExists`; `move`), `logoRules()` (`nullable`, `file`, `mimes:png,jpg,jpeg,webp`, `max:800`, `dimensions:min_width=32,min_height=32,max_width=4000,max_height=4000`) with the exact messages from Global Constraints for `logo.file|uploaded|mimes|max` and `logo.dimensions`, and `store`: validate `name` and `logo`, create the brand and save the file inside `DB::transaction`; if anything throws after the file was written, delete the file and rethrow.
- [ ] **Step 5: Run** the file and then the whole suite. Expected: green.
- [ ] **Step 6: Commit** the storage class, trait, test, controller, `.gitignore`; message `Let the admin add a brand with a logo`.

### Task 4: Replace, remove and clean up logos

**Files:**
- Modify: `backend/routes/web.php` (admin group), `backend/app/Http/Controllers/Admin/BrandController.php` (`storeLogo`, `destroyLogo`, `destroy`)
- Test: `backend/tests/Feature/AdminBrandLogoTest.php`

**Interfaces:**
- Consumes: `BrandLogoStorage`, `logoRules()` (Task 3).
- Produces: `POST /admin/brands/{brand}/logo` (name `admin-brands.logo.store`, multipart `logo`, required) and `DELETE /admin/brands/{brand}/logo` (name `admin-brands.logo.destroy`), both returning the brand JSON; `destroy` also deletes the logo file.

- [ ] **Step 1: Write the failing tests** (add to `AdminBrandLogoTest`):
  - `test_a_logo_can_be_replaced_and_the_old_file_is_deleted`: after two uploads only the second file is in the folder and `logoUrl` points at it.
  - `test_a_refused_replacement_keeps_the_old_logo`: upload a text file named `x.png`; `422`; the old file and `brands.logo` are unchanged.
  - `test_a_logo_can_be_removed`: `DELETE` is `200` with `logoUrl:null`; file gone; column null.
  - `test_a_logo_whose_file_is_already_gone_can_be_replaced_removed_and_its_brand_deleted`: delete the file by hand, then each of the three requests succeeds (`200`, `200`, `200 {ok:true}`).
  - `test_deleting_a_brand_deletes_its_logo_file`.
  - `test_renaming_a_brand_keeps_its_logo`.
  - `test_guests_and_customers_are_redirected_for_the_logo_routes`: both new routes redirect to `admin-login`.
- [ ] **Step 2: Run** the file. Expected: new tests FAIL (routes missing).
- [ ] **Step 3: Implement** the routes and actions. Replace order per spec section 4.3: store the new file, update the row, then delete the old file (ignore a missing one); `destroyLogo` clears the column then deletes the file; `destroy` keeps the product-count rule and deletes the file after the row.
- [ ] **Step 4: Run** the file and the whole suite. Expected: green.
- [ ] **Step 5: Commit** routes, controller, test; message `Let the admin replace, remove and clean up brand logos`.

### Task 5: Shop pages hide OFF brands; brand buttons carry the logo

**Files:**
- Modify: `backend/app/Http/Controllers/ShopController.php` (`index`, `category`, `brandPills`)
- Test: `backend/tests/Feature/ShopBrandsTest.php`

**Interfaces:**
- Consumes: `Product::visible()`, `Brand::active()`, `Brand::$logo_url` (Task 1).
- Produces: each brand button is `{id, label, logo}` where `logo` is the URL or `null`; only ON brands that have a visible product in that view get a button.

- [ ] **Step 1: Write the failing tests:**
  - `test_an_off_brand_has_no_button_and_none_of_its_products_show`: an OFF brand with a product and an ON brand with a product; on `/shop` and on `/shop/phones`, `viewData('products')` has only the ON brand's product name and `viewData('brands')` labels only the ON brand.
  - `test_switching_a_brand_back_on_brings_its_products_back`.
  - `test_the_button_data_carries_the_logo_url_or_null`: one brand with a logo name set, one without; the first button's `logo === asset('uploads/brands/<name>')`, the other's `=== null`.
- [ ] **Step 2: Run** `php artisan test tests/Feature/ShopBrandsTest.php`. Expected: new tests FAIL.
- [ ] **Step 3: Implement**: use `Product::visible()` for both product lists; build brand lists from `Brand::active()` with the existing `whereHas('products', ...)` conditions; add `'logo' => $brand->logo_url` in `brandPills`.
- [ ] **Step 4: Run** the file and the whole suite. Expected: green.
- [ ] **Step 5: Commit**; message `Hide switched-off brands from the shop pages`.

### Task 6: Favorites and checkout refuse hidden products

**Files:**
- Modify: `backend/app/Http/Controllers/FavoriteController.php` (`index`, `toggle`), `backend/app/Actions/PlaceOrder.php`
- Test: `backend/tests/Feature/FavoritesTest.php`, `backend/tests/Feature/CheckoutTest.php`

**Interfaces:**
- Consumes: `Product::visible()`, `Brand::$is_active` (Task 1).
- Produces: favorites list shows only visible products; toggling a hidden product is `422 {ok:false, error:'This product is no longer available.'}`; `PlaceOrder` throws `OrderRejected("{name} is no longer available.")` when any wanted product's brand is OFF, before the stock checks and before anything is saved.

- [ ] **Step 1: Write the failing tests:**
  - `FavoritesTest::test_favorites_of_an_off_brand_are_hidden_and_come_back_when_it_is_on`: the favorites JSON is empty while OFF, the row stays in `favorites`, and the product is listed again after the brand is switched ON.
  - `FavoritesTest::test_a_product_of_an_off_brand_cannot_be_favorited`: `422` with the exact error; nothing attached.
  - `CheckoutTest::test_a_product_of_an_off_brand_is_refused_and_nothing_is_saved`: `assertExactJson(['ok'=>false,'error'=>'Test Phone is no longer available.'])`, `Order::count() === 0`, stock unchanged.
  - `CheckoutTest::test_one_off_brand_product_refuses_the_whole_order`: a cart with one good product and one OFF-brand product is refused, and the good product's stock is unchanged.
- [ ] **Step 2: Run** both files. Expected: the 4 new tests FAIL.
- [ ] **Step 3: Implement**: `favoriteProducts()->visible()` in `index`; in `toggle`, after validation load the product with its brand and return the `422` when the brand is OFF; in `PlaceOrder`, next to the existing "no longer available" existence check, reject any product whose `brand->is_active` is false using the message above.
- [ ] **Step 4: Run** both files and the whole suite. Expected: green.
- [ ] **Step 5: Commit**; message `Stop customers favoriting or buying products of a switched-off brand`.

### Task 7: Admin product rules for OFF brands

**Files:**
- Modify: `backend/app/Http/Controllers/Admin/ProductController.php` (`store`, `update`, `transform`)
- Test: `backend/tests/Feature/AdminProductsTest.php`

**Interfaces:**
- Produces: product JSON gains `brandActive: bool`; a `brandIsOn(?Product $editing = null): Closure` validation rule (same "only check what changed" idea as `nameIsNew`).

- [ ] **Step 1: Write the failing tests:**
  - `test_store_refuses_a_brand_that_is_switched_off`: `422` with an error on `brand_id`, message `That brand is switched off.`; no product created.
  - `test_a_product_whose_brand_is_off_can_still_have_its_stock_and_price_changed`: `PUT` with the same `brand_id`, same `name`, new `qty` and `price` is `200` and saved. (Review Focus 1)
  - `test_update_refuses_moving_a_product_to_a_brand_that_is_off`: `422` on `brand_id`; the product keeps its brand.
  - `test_product_json_carries_brand_active`: index returns `brandActive` `true` and `false` for ON/OFF brand products.
- [ ] **Step 2: Run** `php artisan test tests/Feature/AdminProductsTest.php`. Expected: new tests FAIL.
- [ ] **Step 3: Implement** `brandIsOn` after the existing `exists:brands,id` check: pass when `$editing` already has that brand, otherwise fail with the exact message when the brand is OFF; add `brandActive` to `transform`.
- [ ] **Step 4: Run** the file and the whole suite. Expected: green.
- [ ] **Step 5: Commit**; message `Stop new products joining a switched-off brand`.

### Task 8: The Brands page (switch, logo, add form)

**Files:**
- Modify: `backend/public/assets/js/admin.js` (`apiFetch`, the Brands section), `backend/resources/views/admin-view.blade.php` (`#view-brands`), `backend/public/assets/css/admin-dark.css` (switch and thumbnail colours only if the existing palette does not already cover them)
- Test: `backend/tests/Feature/AdminViewTest.php`

**Interfaces:**
- Consumes: the brand JSON and routes from Tasks 2 to 4 (`/admin/brands`, `/admin/brands/{id}`, `/admin/brands/{id}/logo`).
- Produces (JS): `apiFetch` sends a `FormData` body without a JSON `Content-Type` and turns a `413` into the exact browser message; `brandLogoHtml(b): string` (round thumbnail, or the first letter on a tinted circle); `toggleBrand(id)`, `pickBrandLogo(id)`, `uploadBrandLogo(id, file)`, `removeBrandLogo(id)`, `previewNewBrandLogo(input)`; `addBrand()` posts `FormData(name, logo?)`; `brands` entries now have `isActive`, `logoUrl`. Element ids: `brand-add-logo` (file input), `brand-add-preview`, `brand-logo-input` (hidden input for Change logo).

- [ ] **Step 1: Write the failing test** `AdminViewTest::test_admin_view_renders_the_brand_switch_and_logo_controls`: the page contains `id="brand-add-logo"`, `id="brand-add-preview"`, `id="brand-logo-input"` and the table headings `Logo`, `Status`.
- [ ] **Step 2: Run** `php artisan test tests/Feature/AdminViewTest.php`. Expected: the new test FAILS.
- [ ] **Step 3: Implement the markup and JS** per spec 4.6: the add form (name, "Choose logo (optional)", preview); columns Logo, Brand, Products, Status, Actions; the Status cell is a `button role="switch" aria-checked` with the words On/Off beside it, OFF rows greyed; actions Rename, Change logo, Remove logo (only when one exists), Delete. `toggleBrand` calls `PUT {is_active}` and shows the exact toasts from Global Constraints (n = `productCount`), then refreshes `brands`, the selects and the product tables. Client-side pre-check of file type and size (under 800 KB) for quick feedback; the server stays the authority. Escape every server value (`escapeHtml`/`escapeAttr`).
- [ ] **Step 4: Verify `apiFetch`** with a throwaway Node script outside the repo that stubs `fetch` and runs the real `apiFetch` source: a `FormData` body sends no `Content-Type`; a `413` with an HTML body rejects with the exact browser message; a JSON body still sends `application/json`; the existing session-expired cases still pass. Expected: all OK.
- [ ] **Step 5: Run** `AdminViewTest` and the whole suite. Expected: green.
- [ ] **Step 6: Verify in Chrome** (local server on a spare port, local admin login from the project memory): add a brand with a logo; Change logo; Remove logo; switch a brand OFF and ON (toast text, greyed row); a too-big file and a `.txt` renamed `.png` both show a plain message; light and dark mode; run the contrast scan used for the dark theme (no text under 4.5:1). Fix anything found.
- [ ] **Step 7: Commit** the four files; message `Add the on/off switch and logo upload to the Brands page`.

### Task 9: Brand dropdowns and "Brand off" tags

**Files:**
- Modify: `backend/public/assets/js/admin.js` (`renderBrandSelects`, `saveModal`/`openEdit` select setup, product and inventory row renderers)

**Interfaces:**
- Consumes: `brands[].isActive` (Task 8), product JSON `brandActive` and `brandId` (Task 7).
- Produces: the Add Product dropdown (`#f-brand`) lists ON brands only; the edit dropdown (`#m-brand`) lists ON brands plus the product's own brand labelled `(off)` when it is OFF; product and inventory tables show a small `Brand off` tag beside the brand name when `brandActive` is false.

- [ ] **Step 1: Write the check first** as a throwaway Chrome script outside the repo that, with one brand switched OFF (through the real admin API on the local server), asserts: `#f-brand` has no option for it; opening Edit on one of its products shows `#m-brand` with that brand selected and labelled `(off)`; the Products and Inventory tables show the tag for those rows only. Run it. Expected: FAIL.
- [ ] **Step 2: Implement** the three changes; toggling a brand (Task 8) must also update `products[].brandActive` so the tags change without a reload.
- [ ] **Step 3: Run** the check again. Expected: PASS. Save an edit to an OFF-brand product's stock in the UI and confirm it succeeds (Review Focus 1, end to end). Run the whole suite.
- [ ] **Step 4: Commit**; message `Offer only switched-on brands for new products and tag the rest`.

### Task 10: Shop brand buttons show the logo

**Files:**
- Modify: `backend/public/assets/js/shop.js` (`buildFilterUI`, brand pill markup)

**Interfaces:**
- Consumes: `BRANDS` entries `{id, label, logo}` (Task 5).
- Produces: a brand button with a `logo` shows a 20 px round image (`alt=""`, `loading="lazy"`) before the label (button uses `inline-flex items-center gap-2`); one without is unchanged.

- [ ] **Step 1: Write the check first**: a Chrome script that loads `/shop` with one brand that has a logo (upload through the admin API) and one without, and asserts the first button contains an `img` that loaded (natural width > 0) and the second contains none; also that a brand switched OFF has no button and its products are not on the page. Expected before the change: FAIL.
- [ ] **Step 2: Implement** the button markup change. The `logo` value comes from the server (a generated file name); still insert it through an attribute-safe template (no user text).
- [ ] **Step 3: Run** the check. Expected: PASS. Look at the shop in light and dark mode and at phone width (buttons must still wrap cleanly). Run the whole suite.
- [ ] **Step 4: Commit**; message `Show brand logos on the shop's brand buttons`.

### Task 11: Verify the whole feature before anyone updates the live site

**Files:** none changed (fixes found here go back to the task that owns them, with a test).

- [ ] **Step 1: Full suite:** `php artisan test`. Expected: all pass; record the count.
- [ ] **Step 2: Migration on real MySQL:** dump the local `ict_tech` database to a scratch database, run `php artisan migrate --force` against the scratch copy (point `DB_DATABASE` at it for that one command only), confirm every existing brand has `is_active = 1` and `logo IS NULL`, run `migrate:rollback --step=1` and `migrate` again, then drop the scratch database. The real local database is untouched.
- [ ] **Step 3: End to end in Chrome:** as admin add a brand with a logo, switch it OFF; as a customer confirm the brand and its products are gone from `/shop`, a category page and favorites, and checkout is refused for a cart holding one; switch it ON and confirm everything returns; delete the brand and confirm its file is removed from `public/uploads/brands`. Remove any test data you created.
- [ ] **Step 4: Check `git status`:** only the files named in this plan changed, `public/uploads` is not tracked, and the owner's unrelated edits are still uncommitted and untouched.
- [ ] **Step 5: Report** to the owner: what was verified, the test count, the one-time server step (a writable `public/uploads/brands`), and anything not checked.
