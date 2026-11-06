# Managed brands and category select for admin products — design

Date: 2026-11-04
Status: design approved in conversation; this spec awaits the owner's review.
App: `backend/` (Laravel 13, MySQL `ict_tech`, Blade + plain JS in `public/assets/js`).

## 1. Problem

The admin "Add Product" form lets the admin **type** the brand and **pick** a
category from a list that actually contains brand names (Apple, Sony, …). Real
categories are `phones`, `earphones`, `powerbanks`, `laptops` (`config/shop.php`),
and `/shop/{category}` queries `products.category` for an exact match. Results in
the local data (the live database has not been inspected):

- Product #27 ("18 pro max") has `category = "Apple"`, so it cannot appear on any
  category page, and `brand = "apple"` (lowercase) while every other Apple
  product has `Apple`.
- "Jtrust" is spelled `JTrust` in the form, `Jtrust` in the data and `jtrust` in
  `ShopController::ALL_BRANDS`.
- The `/shop` brand buttons are a hard-coded list; category pages build theirs
  from whatever brand text exists, so one typo becomes a clickable bogus brand.
- The edit modal has no brand or category fields, so a wrong value cannot be
  fixed after creation.
- The server accepts any string for `brand` and `category`, and the form hides
  the server's error text behind "Failed to add product."
- There are no tests for the admin product endpoints.

## 2. Goal

Brands are a managed list, like in a normal online shop. The admin picks a brand
and a category from lists and types only the product name. The server refuses
anything not in those lists. The shop's brand buttons come from the database.

Success criteria:
1. A product cannot be saved without an existing brand and a real category.
2. `apple` and `Apple` cannot both exist as brands.
3. Renaming a brand changes it everywhere (admin tables, shop cards, filters).
4. A brand that still has products cannot be deleted.
5. `/shop` and `/shop/{category}` show only brands that have products (in that
   category for the latter).
6. Every existing product ends up linked to a brand (26 locally); old orders are
   unchanged.
7. The full test suite is green.

## 3. Decisions (agreed with the owner)

1. A `brands` table replaces the free-text brand. Brands are managed on a new
   admin **Brands** page and can also be added inline from the product form.
2. The old `products.brand` text column is **removed** (single source of truth).
   The migration is reversible.
3. Categories stay in `config/shop.php` (fixed list; adding one needs a route and
   menu change anyway). The admin only selects from them.
4. `order_items.brand` stays text: a snapshot of the name at purchase time (see
   the orders spec). Renaming a brand does not rewrite past orders.
5. Image upload, type/badge/specs fields, draft toggle, stock display and
   variants are **out of scope** (image upload gets its own spec and plan next).

## 4. Data model

`brands`
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| name | string(50), unique | stored as typed (trimmed) |
| created_at, updated_at | timestamps | |

Name rules (create and rename): required, string, trimmed, max 50, characters
limited to letters, numbers, space, `-` and `.` (`/^[\p{L}\p{N} .\-]+$/u`), and
unique **ignoring case**. The case-insensitive check is done in the validation
rule (`lower(name)` comparison, ignoring the brand being renamed) so it behaves
the same on MySQL and on the SQLite test database. Renaming `apple` → `Apple`
on the same brand is allowed.

`products`: add `brand_id` (unsigned bigint, FK → `brands.id`, `restrictOnDelete`,
NOT NULL after backfill); drop `brand`.

Migrations (three files, in order):
1. Create `brands`; add `products.brand_id` as nullable.
2. Backfill: group existing `products.brand` values case-insensitively after
   trimming; the brand's name is the spelling used by the most products (ties →
   the spelling on the lowest product id); insert the brands; set every
   product's `brand_id`. Names are copied as they are (no charset check).
   Safe to fail: a name longer than 50 characters stops the migration before
   anything is written, with a message naming it (the admin shortens it and runs
   `migrate` again); all writes run in one transaction; and a name the database
   already treats as an existing brand (for example `Café` and `Cafe` under
   MySQL's collation) joins that brand instead of failing.
3. Make `brand_id` NOT NULL, add the foreign key, drop `products.brand`.

`down()` of each step reverses it; reversing step 3 re-adds `brand` and refills
it from `brands.name`.

Models: new `Brand` (`name` fillable; `products()` hasMany). `Product`: fillable
swaps `brand` for `brand_id`; new `brand()` belongsTo. Because `$product->brand`
is now the relationship, every reader changes to `$product->brand->name` and
the queries eager-load `brand`: `ShopController` (index, category, transform),
`FavoriteController`, `Admin\ProductController` (index, store, update, transform)
and `PlaceOrder` (the snapshot copy; its locked product query loads `brand`).
`ProductSeeder` creates its brands with `firstOrCreate` and sets `brand_id`
(for fresh installs only — never run it against the existing local database).

## 5. Admin brand endpoints (`admin` middleware)

- `GET /admin/brands` → `[{id, name, productCount}]`, ordered by name
  (case-insensitive).
- `POST /admin/brands` `{name}` → `201` the brand `{id, name, productCount: 0}`.
- `PUT /admin/brands/{brand}` `{name}` → `200` the brand.
- `DELETE /admin/brands/{brand}` → `200 {ok:true}`; if the brand has products →
  `422 {ok:false, error:"<Name> still has <n> products. Move or delete them first."}`.

Validation failures keep Laravel's JSON shape (`message`, `errors`); the admin
`apiFetch` already shows `err.error || err.message`.

## 6. Admin product endpoints (changes)

- `store`: `brand_id` required, `exists:brands,id`; `category` required,
  `Rule::in(array_keys(config('shop.categories')))`. The `brand` text field is no
  longer accepted.
- `update`: same rules with `sometimes`, so quantity-only requests (restock,
  +/- buttons) keep working for products whose old category is invalid.
- Response shape (`transform`): `brand` stays the brand **name** string, plus new
  `brandId` (int); `category` stays the slug. Existing admin code that reads
  `p.brand` keeps working.

## 7. Admin UI

- Sidebar gets a **Brands** item and a `view-brands` section; `showView` and its
  titles list include it. `init()` loads products and brands.
- **Brands view:** an "Add brand" input and button on top; a table of Name,
  Products (count), Rename and Delete. Rename edits the name in place (no browser
  `prompt()`). Delete on a brand with products shows the server's message.
- **Add Product form:** `f-brand` becomes a `<select>` filled from the loaded
  brands, with a last option "+ Add new brand…" that reveals a small inline input
  with Save/Cancel; Save posts to `/admin/brands`, adds the brand to every brand
  select and selects it. `f-category` becomes a select rendered in Blade from
  `config('shop.categories')` (value = slug, label = label). Name is the only
  free-text field of the three. The payload sends `brand_id` and `category`. On
  failure the red box shows the server's message instead of the generic text.
- **Edit modal:** gains `m-brand` and `m-category` selects, preselected from the
  product. A product with a category that is not in the list (like #27) shows the
  "Select category…" placeholder, and Save asks the admin to choose before it
  sends anything. The modal sends `brand_id` and `category` with the other
  fields and shows the server's message on failure.
- The Brand column in the products/inventory tables keeps showing the brand name.

## 8. Shop (customer side)

- `ShopController::index`: the brand pills are the brands that have at least one
  product (ordered by name), shaped `{id: lowercase name, label: name}`; the
  hard-coded `ALL_BRANDS` constant is removed.
- `ShopController::category`: same shape, limited to brands with products in that
  category (as today, now through the relationship).
- Product JSON keeps `brand` as the name string, so `shop.js`, `script.js` and
  the cart/favorites code do not change. Pill ids are the lowercase name, unique
  because names are unique ignoring case and restricted to safe characters.
- Visible change: a brand with no products no longer shows a pill on `/shop`.

## 9. Testing

All tests run on in-memory SQLite (`phpunit.xml`); none touch MySQL.

- `AdminBrandsTest`: list with counts; create; duplicate ignoring case rejected;
  bad characters rejected; rename (including case-only rename of itself); delete
  empty brand; delete blocked with the message; guests/non-admins refused.
- `AdminProductsTest` (new — nothing covers these endpoints today): store with
  valid `brand_id` + category; unknown `brand_id` and unknown category rejected;
  update; quantity-only update of a product with an invalid category succeeds;
  response has `brand` (name) and `brandId`.
- `ShopBrandsTest`: `/shop` pills list only brands that have products; a category
  page lists only that category's brands; product JSON carries the brand name.
- `BrandsMigrationTest`: roll back the three brand migrations, insert legacy rows
  with text brands (`Apple`, `apple`, `Sony`), migrate forward, assert two brands
  (`Apple` wins by count), every product linked, names merged; this also
  exercises `down()`.
- Existing tests: `MakesShopData::makeProduct` and `OrderModelsTest` switch from
  `'brand' => 'Acme'` to a created brand; checkout/orders tests must still pass
  and still snapshot the brand name on `order_items`.
- Development order: tests first, watching them fail, then the code (TDD).

## 10. Rollout

- The migration drops a column. **Back up the live database before running
  `php artisan migrate` on the server.**
- Locally: run `php artisan migrate` only. Never `db:seed` (it would duplicate
  every product).
- Products with an invalid category (local #27, and any similar ones live) are
  fixed afterwards in the admin edit modal, which now requires a valid choice.
  Whether #27 is test data to delete instead is the owner's call.
- The old static frontend (root `shop.html` and its `products-data.js` with its
  own hard-coded brands) is not part of the Laravel app and is left untouched.
