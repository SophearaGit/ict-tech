# Brands: on/off switch and logo — design

Date: 2026-11-06
Status: design approved in conversation; this spec awaits the owner's review.
App: `backend/` (Laravel 13, MySQL `ict_tech`, Blade + plain JS in `public/assets/js`).
Part 1 of 3 of the owner's request of 2026-11-06. The other two get their own specs:
the sales-report brand filter, and categories managed from the admin.

## 1. Problem

A brand can only be deleted, and only when it has no products. There is no way to stop
selling a brand for a while and bring it back later, so the owner is tempted to delete
brands they only want to pause. Brands are also just a name: there is nowhere to put a logo.

## 2. Goal

Each brand gets an **ON/OFF switch** and an optional **logo**, both managed on the admin
Brands page.

Success criteria:
1. The admin can switch any brand OFF and ON again from the Brands page, instantly, with
   no data lost either way. All existing brands start ON.
2. A brand that is OFF, and all of its products, are invisible to customers: the shop page,
   the category pages, the favorites list and checkout.
3. A brand that is OFF cannot be picked for a new product, but its existing products stay
   fully editable in the admin (stock, price, name, everything).
4. The admin can add a logo when adding a brand, and replace or remove it later. Only real
   PNG, JPG or WebP images under 800 KB are accepted. SVG and anything else is refused.
5. The logo shows on the Brands page and on the shop's brand buttons. A brand without a
   logo still looks fine.
6. Replacing or removing a logo, or deleting a brand, deletes the old file from disk.
7. Only an admin can change any of this.
8. The full test suite is green.

## 3. Decisions (agreed with the owner)

1. **OFF hides the brand and its products** from customers (chosen over "hide only the
   brand name"). The owner sees and manages everything as before.
2. **OFF deletes nothing.** The delete rule is unchanged: a brand can be deleted only when
   it has no products. Sales history is unaffected by the switch.
3. **The logo is optional.** PNG, JPG or WebP only. **No SVG**: an SVG file can carry
   scripts, and these files are shown to every visitor.
4. **Logos are shown in two places**: the admin Brands list and the shop's brand buttons.
   Not on product cards, not on the home page.
5. **Logo files are saved in `public/uploads/brands/`** with random names, not in Laravel's
   `storage/` folder. That avoids the `storage:link` command on the live server, which the
   owner's by-hand deploy does not have today.
6. **The "+ Add new brand…" shortcut inside the Add Product form stays name-only.** A logo
   is added on the Brands page afterwards.
7. **Changed after the design was approved: the logo size limit is 800 KB, not 2 MB.**
   The live site runs on nginx, whose default request limit is 1 MB, so a 2 MB logo would be
   rejected by the server before Laravel sees it, with an unhelpful error. 800 KB works with
   the defaults of both nginx and PHP, and a logo does not need more. No server settings
   have to be changed.

## 4. Design

### 4.1 Data

One reversible migration adds two columns to `brands`; nothing is rewritten or backfilled,
so it is safe on the live database:

| Column | Type | Meaning |
|---|---|---|
| `is_active` | boolean, NOT NULL, default `true` | the ON/OFF switch; every existing brand becomes ON |
| `logo` | string(255), nullable | the file name only (`3f9c…e1.png`), never a path |

`Brand` model: `is_active` and `logo` are written by the controller, not mass-assigned from
the request. `is_active` is cast to boolean. A `logoUrl` accessor returns the public URL
(`asset('uploads/brands/'.$logo)`) or `null`. A `scopeActive` scope selects ON brands.

### 4.2 Admin API (all behind the existing `admin` middleware)

| Method and path | Body | Result |
|---|---|---|
| `GET /admin/brands` | — | list; each brand now also has `isActive` and `logoUrl` |
| `POST /admin/brands` | multipart: `name`, optional `logo` | `201` brand; the brand and its logo are saved together or not at all |
| `PUT /admin/brands/{brand}` | JSON: `name` and/or `is_active` (at least one) | `200` brand. Renaming works exactly as before; the name rules apply only when a name is sent |
| `POST /admin/brands/{brand}/logo` | multipart: `logo` | `200` brand with the new logo; the old file is deleted |
| `DELETE /admin/brands/{brand}/logo` | — | `200` brand without a logo; the file is deleted |
| `DELETE /admin/brands/{brand}` | — | unchanged (`422` while it still has products); now also deletes the logo file |

Brand JSON shape: `{id, name, productCount, isActive, logoUrl}`. Errors keep today's
shapes: `422 {message, errors}` for validation, `{ok:false, error}` for the delete rule.

### 4.3 Logo rules and storage

Validation: a real uploaded file, content type `png`, `jpg`/`jpeg` or `webp` **detected from
the file's content, not its name** (PHP's `fileinfo`, installed), at most **800 KB**, at least
32 x 32 and at most 4000 x 4000 pixels (read with `getimagesize`, which needs no image
library). A file that fails gets a plain message such as "The logo must be a PNG, JPG or WebP
image under 800 KB."

Storage: the file is saved as 32 random characters plus the extension that matches the
detected type (never the uploaded name). The folder comes from `config('shop.brand_logos.dir')`
(default `public_path('uploads/brands')`), created on first use, so tests can point it at a
temporary folder. Order of operations on replace: save the new file, update the database,
then delete the old file, so a failure at any step can leave at worst an orphan file, never a
brand pointing at a missing one. Deleting a file that is already gone is not an error.

There is **no resizing or cropping on the server**: PHP here has no image-processing library
(`gd` / `imagick` are not installed) and the live server may not either. The size and
dimension limits do that job, and the page shows logos as round crops with CSS.

`/public/uploads` is added to `.gitignore`: uploaded logos are data, not code, and must not
be overwritten or deleted by a `git pull`.

### 4.4 Who can see what (customer side)

A new `Product::scopeVisible` selects products whose brand is ON. It is used in every place a
customer can see or order a product:

1. `ShopController::index` and `::category` list only visible products.
2. The shop's brand buttons list only ON brands that have at least one (visible) product in
   that view. Each button gets `logo` (the URL or `null`) next to `id` and `label`.
3. `FavoriteController::index` lists only visible favorites. A favorite of an OFF brand stays
   saved and comes back when the brand is switched ON.
4. `FavoriteController::toggle` refuses a product of an OFF brand: `422`
   "This product is no longer available."
5. `PlaceOrder` rejects the whole order, saving nothing and leaving stock untouched, when any
   product's brand is OFF: "{product name} is no longer available." This covers carts that
   were filled before the brand was switched off.

Nothing else in the customer side reads products (the home page does not).

### 4.5 Admin side: products and forms

1. `ProductController` product JSON gains `brandActive` (boolean).
2. **Adding a product** needs an ON brand (`422` "That brand is switched off." otherwise).
3. **Editing a product** may keep its current brand even when it is OFF. This matters because
   the edit window always sends the brand back along with the new stock or price. Moving a
   product *to* an OFF brand is refused. (Same "only check what changed" rule as the
   product-name check.)
4. The Add Product dropdown lists ON brands only. The edit window's dropdown lists ON brands
   plus the product's own brand if it is OFF, labelled "(off)".
5. The product and inventory tables show a small "Brand off" tag beside the brand name for
   products of an OFF brand. Dashboard counts do not change: stock is stock.

### 4.6 Admin UI (Brands page)

- **Add form:** name, an optional "Choose logo" file picker with a small preview, and Add Brand.
- **Table columns:** Logo (round thumbnail, or the brand's first letter on a tinted circle when
  there is none), Brand, Products, Status, Actions.
- **Status** is a switch (`role="switch"`, `aria-checked`) with the words On / Off beside it,
  so it never relies on colour alone. An OFF row is greyed. Switching shows a toast, for example
  "Apple is OFF. Its 7 products are hidden from the shop." or "Apple is ON again."
  There is no confirm dialog: the switch is instant and fully reversible.
- **Actions:** Rename, Change logo (opens the file picker and uploads at once), Remove logo
  (only when there is one), Delete (as before).
- `apiFetch` (admin.js) learns to send a `FormData` body (no JSON `Content-Type` header) and to
  turn a `413` into "That file is too big. Logos must be under 800 KB." instead of the
  generic reply.
- New styling follows the dark theme in `admin-dark.css` and keeps every text/background pair
  at WCAG AA contrast.

### 4.7 Shop UI

In `shop.js`, a brand button with a `logo` shows a small round image (20 px, empty `alt`,
lazy-loaded) before the name. A button without one is unchanged. The URL is built by the server
from a random file name it generated itself, never from anything the user typed.

## 5. Out of scope

- Logos on product cards or the home page; product picture upload.
- SVG, cropping, resizing or any other image editing.
- The sales-report brand filter and the category management (their own specs).
- Hiding a brand from the admin: the admin always sees every brand.

## 6. Testing

Test-first, in the existing style (`MakesShopData` helpers). New or extended tests:

- **Migration:** both columns exist; a brand created without them is ON with no logo.
- **`AdminBrandsTest`:** switch OFF and ON; the JSON carries `isActive` and `logoUrl`;
  `PUT` with neither field is `422`; renaming still works and ignores the switch.
- **New `AdminBrandLogoTest`:** add with and without a logo; refuse a GIF, an SVG, a text file
  renamed `.png`, a file over 800 KB, an image under 32 px; replace deletes the old file;
  remove clears it; deleting a brand deletes its file; a guest is redirected to admin login.
  Tests point `shop.brand_logos.dir` at a temporary folder and clean it up, so the real
  `public/uploads` is never touched.
- **`ShopBrandsTest`:** an OFF brand has no button, its products are missing from `/shop` and
  `/shop/{category}`; a logo URL reaches the button data; a brand with no logo has `null`.
- **`FavoritesTest`:** a hidden favorite is not listed and returns when the brand is ON;
  toggling it is `422`.
- **`CheckoutTest`:** an order containing an OFF-brand product is rejected, saves nothing and
  leaves stock unchanged, even if other items in the order are fine.
- **`AdminProductsTest`:** cannot add a product to an OFF brand; can still change stock and
  price of a product whose brand is OFF; cannot move a product to an OFF brand.

What a feature test cannot show is checked in Chrome on the real pages: the Brands page in
light and dark mode (switch, logo thumbnails, upload, remove), the "Brand off" tags, and the
shop's brand buttons and hidden products.

## 7. Deployment notes (for the owner's by-hand update)

1. Back up the database first. Never touch production credentials.
2. `git pull --ff-only origin backend`, then `php artisan migrate --force`, then
   `php artisan optimize:clear`.
3. **One-time:** make sure the web server's user can write to `public/uploads` (create
   `public/uploads/brands` if it is missing). Without that, adding a logo fails with a clear
   message and everything else keeps working.
4. No new packages, no `.env` settings, no build step, no nginx or PHP settings.

## 8. Known limits

- Logos are stored at the size they were uploaded (up to 800 KB); the server does not shrink them.
- Switching a brand OFF while a customer already has its product in their cart: the cart is
  refused at checkout with "…is no longer available", not before.
- If a brand is renamed, sales that were already recorded keep the brand name they were sold
  under (this is how the sales report already works).
