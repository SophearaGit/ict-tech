# Categories managed from the admin — design

Date: 2026-11-08
Status: design approved in conversation; this spec awaits the owner's review.
App: `backend/` (Laravel 13, MySQL `ict_tech`, Blade + plain JS in `public/assets/js`).
Part 3 of 3 of the owner's request of 2026-11-06. Part 1 (brand on/off switch and logo) is built;
part 2 (the sales-report brand filter) gets its own spec. This part follows the same shape as
`2026-11-06-brands-active-and-logo-design.md` and reuses its ideas (visibility rule, "only check
what changed", the admin page pattern).

## 1. Problem

The four categories (Phones, Earphones, Power Banks, Laptops) are written in `config/shop.php`.
The owner cannot add one, rename one, or hide one without a programmer editing code. The same list
is read in many places (shop menu, shop page, admin forms, product checks, page addresses), and two
scripts have the category emoji written into them, so a new category would show a generic box. The
category descriptions ("Apple · Samsung · Xiaomi") are fixed text and keep naming a brand that has
been switched off.

## 2. Goal

A **Categories** page in the admin, built like the Brands page: add a category with a name and an
icon, rename it, change its icon, switch it ON/OFF, and delete it when nothing uses it. The whole
shop reads the list from the database.

Success criteria:
1. The admin can add, rename, re-icon, switch ON/OFF and delete (when empty) a category from the
   admin, and the Add Product form can add one on the spot ("+ Add new category…").
2. The four existing categories keep their names and icons, start ON, and no product changes.
3. A category that is OFF, and all of its products, are invisible to customers: the shop page, its
   category page, favorites and checkout. The admin still sees and edits everything.
4. A category that is OFF cannot be picked for a new product, but its existing products stay fully
   editable.
5. Customers' category menu, category tabs, category cards and `/shop/{category}` pages come from the
   database. An unknown or OFF category address answers "not found".
6. A new category shows its own icon wherever the site draws one (shop product cards, cart drawer),
   not a generic box.
7. The description under each category lists only brands that are ON and really have products in it.
8. Only an admin can change any of this.
9. The full test suite is green.

## 3. Decisions (agreed with the owner)

1. **A category has a name, an icon and an ON/OFF switch**, like a brand with a logo (the icon is an
   emoji, not an uploaded picture).
2. **OFF hides the category and its products** from customers, exactly like a brand.
3. **Products keep pointing at their category by its short code** (the `slug`, for example `phones`).
   `products.category` does not change, so no product is rewritten and the live update is safe.
   (Rejected: a numeric `category_id` like brands, which would rewrite every product and every odd
   old value for little gain.)
4. **A category's code is made from its name once and never changes.** Renaming changes only what
   people read, so `/shop/phones` keeps working after "Phones" becomes "Mobiles".
5. **Delete only when no product uses it**, the same rule as brands. OFF is the way to pause one.
6. **No reordering.** Categories appear in the order they were created (the four existing ones first).
7. **The "+ Add new category…" shortcut in the Add Product form stays name-only** (default icon 📦);
   the icon is set on the Categories page afterwards, as with brand logos.

## 4. Design

### 4.1 Data

One reversible migration creates `categories` and copies in the four current categories (names and
icons exactly as in `config/shop.php`, all ON, in their current order). It changes no product.

| Column | Type | Meaning |
|---|---|---|
| `id` | bigint | order of creation = order shown |
| `slug` | string(50), unique | the code products use (`phones`); written once, never changed |
| `name` | string(50), unique | what people read ("Power Banks") |
| `icon` | string(16) | an emoji, default `📦` |
| `is_active` | boolean, NOT NULL, default true | the ON/OFF switch |

`Category` model: `is_active` cast to boolean; `scopeActive`; `products()` is not a relation (products
join by `products.category = categories.slug`), the controller counts them with a query. The settings
`config/shop.php` loses its `categories` list (the migration holds the four defaults), and keeps the
timezone and the brand-logo settings.

**Slug rule:** `Str::slug($name)` (lowercase a-z, 0-9, hyphen), cut to 50 characters. When that is
empty (for example a Khmer name) the code is `c` plus 8 random hex characters. When the code is
already taken (for example "Power-Banks" next to "Power Banks"), `-2`, `-3`… is added.

### 4.2 Admin API (behind the existing `admin` middleware)

| Method and path | Body | Result |
|---|---|---|
| `GET /admin/categories` | — | list in creation order; each `{id, slug, name, icon, isActive, productCount}` |
| `POST /admin/categories` | JSON: `name`, optional `icon` | `201` category |
| `PUT /admin/categories/{category}` | JSON: `name`, `icon` and/or `is_active` (at least one) | `200` category; the `slug` is never touched |
| `DELETE /admin/categories/{category}` | — | `200 {ok:true}`; `422 {ok:false, error}` while any product uses its slug |

Rules: **name** is required, at most 50 characters, only letters (any language), digits, spaces,
`.` and `-`, and unique ignoring case (the same rule and check as brands, via `lower(name)`, so it
behaves the same on MySQL and on the SQLite test database; on rename only the new name is checked).
**icon** is at most 16 characters with no letters, digits, `<`, `>`, `&` or quotes, otherwise
"Use a single emoji, for example 📱." An empty icon becomes `📦`. **is_active** is a boolean.
Validation errors stay `422 {message, errors}`.

### 4.3 Who can see what (customer side)

`Product::scopeVisible` (from the brands work) gets a second condition: the product's category is not
a category that exists and is OFF. A product whose category is not in the table at all (an old odd
value such as `Apple`) stays visible exactly as it is today. It is used everywhere a customer reads
products, so the shop pages, favorites and checkout follow with no further change except the two
points below.

1. **Routes and controller.** `/shop/{category}` no longer limits the address to a fixed list when
   the app starts; `ShopController::category` looks the code up (`Category::active()` by `slug`) and
   answers 404 for an unknown or OFF code. The brand buttons on that page are the ON brands that have
   a visible product in that category (as now).
2. **The list of categories** (name, icon, code, description) comes from one small class,
   `App\Support\ShopCategories`, used by the menu in `layouts/app.blade.php`, the category cards and
   tabs in `shop.blade.php` (`CATEGORIES`), and the admin. It returns ON categories in order. The
   **description** is the names of ON brands that have a visible product in that category,
   alphabetical, at most four, joined with " · " (empty when none).
3. **Icons in scripts.** The layout defines one site-wide map `window.CATEGORY_ICONS = {code: icon}`
   for every category (ON or OFF, so an old cart still shows the right icon). `shop.js` (product card
   fallback) and `script.js` (cart drawer) read it and fall back to `📦`.
4. **`PlaceOrder`** needs no new rule: it already refuses a product that is no longer visible; the
   brand check there becomes "not visible" (brand OFF or category OFF) with the same message
   `{name} is no longer available.` The favorites toggle uses the same visibility check.

### 4.4 Admin side: products and forms

1. Admin product JSON gains `categoryName` (the category's name, or `null` when its code is unknown)
   and `categoryActive` (boolean; `true` when unknown). The products and inventory tables show the
   name instead of the raw code, and a small "Category off" tag beside it when OFF (like "Brand off").
2. **Adding a product** needs an ON category: `422` "That category is switched off." (an unknown code
   keeps today's plain "invalid" refusal).
3. **Editing a product** may keep the category it already has, even when that category is OFF: the
   edit window re-sends the category with every save. Moving it *to* an OFF category is refused, and
   an unknown code is refused exactly as today.
4. Both dropdowns (Add Product, Edit) are filled by JavaScript from `/admin/categories`, like the
   brand ones: ON categories only, plus the product's own category labelled "(off)" in the edit window.
   Add Product also ends with "+ Add new category…".

### 4.5 Admin UI (Categories page)

A new sidebar item "Categories" (next to Brands) and a page built like the Brands page:
- **Add form:** name, an icon box (shows the default 📦; the admin types or pastes an emoji), Add Category.
- **Table columns:** Icon (shown large), Category, Code (small grey, so the code behind the address is visible),
  Products, Status (On/Off switch with the words, like brands), Actions: Rename, Change icon, Delete.
- Switching shows a toast, for example "Laptops is OFF. Its 6 products are hidden from the shop." and
  "Laptops is ON again."
- New styling follows the dark theme in `admin-dark.css`, at WCAG AA contrast.

## 5. Out of scope

- Reordering categories, category pictures, sub-categories.
- The sales-report brand filter (its own spec).
- Changing how products are searched or how brand buttons work beyond what 4.3 says.
- Rewriting old products with an unknown category.

## 6. Testing

Test-first, in the existing style (`MakesShopData`). New or extended tests:

- **Migration:** the table and the four default rows exist in the right order, all ON, with the exact
  names and icons; `down()` drops it.
- **New `AdminCategoriesTest`:** list order and product counts (counted by code); add with and without
  an icon (default 📦); code from the name, the collision suffix, and the non-Latin fallback; duplicate
  name ignoring case and padding; bad characters; bad icon (letters, `<script>`, too long); rename keeps
  the code; icon change; switch OFF/ON; update with nothing to change is `422`; delete blocked while a
  product uses it and allowed when empty; a guest or customer is redirected to admin login.
- **New `ShopCategoriesTest`:** an OFF category has no menu entry, no tab or card, its products are
  missing from `/shop` and favorites, `/shop/{its-code}` and an unknown code are 404; a brand switched
  off no longer appears in a category's description; a product with an unknown old category is still
  shown; checkout refuses a product of an OFF category with nothing saved; back ON everything returns.
- **`AdminProductsTest`:** cannot add a product to an OFF category; can still change stock and price of
  a product whose category is OFF; cannot move one to an OFF category; product JSON carries
  `categoryName` and `categoryActive`.
- **`AdminViewTest`:** the Categories page and the two dropdown ids are present (the dropdown options
  are no longer written into the page by the server, so the old option assertions move to the API test).

What a feature test cannot show is checked in Chrome: the Categories page in light and dark mode (add
with an emoji, rename, switch, delete), the dropdowns and "Category off" tags, and as a customer the
menu, tabs, cards and a new category's icon on product cards and in the cart drawer.

## 7. Deployment notes (for the owner's by-hand update)

1. Back up the database first. Never touch production credentials.
2. Two read-only checks on the live database first (phpMyAdmin is enough):
   - `SELECT category, COUNT(*) FROM products GROUP BY category;` expects only `phones`, `earphones`,
     `powerbanks`, `laptops`, all lower-case. Any other value (an old `Apple`-style code) still works: it stays
     visible in the shop and is shown by its raw code in the admin.
   - `SHOW FULL COLUMNS FROM products WHERE Field = 'category';` expects the collation `utf8mb4_unicode_ci`,
     the same as the new `categories.slug`.
3. Every customer page (the home page too) now reads the `categories` table for its menu, so the site must
   never run the new code without the migration. Put it in maintenance mode around the update:
   `php artisan down`, then `git pull --ff-only origin backend`, then `php artisan migrate --force`, then
   `php artisan optimize:clear` (the settings file lost its category list, so a stale config cache must be
   cleared), then `php artisan up`.
4. Going back (if `migrate` fails or something looks wrong): `php artisan down`, `git checkout <the commit you
   were on before the pull>`, `php artisan optimize:clear`, `php artisan up`. Leave the `categories` table in
   place; the old code simply ignores it. `php artisan migrate:rollback --step=1` also works, but it deletes
   every category the admin added.
5. No new folder, package, `.env` setting or build step. This part needs no write permission.

## 8. Known limits

- A renamed category keeps its old address and code (by design, so links never break).
- The description shows at most four brand names, alphabetical, instead of the hand-picked order the
  settings file had.
- A product with an unknown old category keeps showing its raw code in the admin tables.
- Two admins adding the same category name in the same split second can both pass the check; the
  unique index on the name then refuses the second with a plain error rather than a duplicate.
