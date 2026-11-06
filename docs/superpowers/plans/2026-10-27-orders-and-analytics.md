# Real Orders, Admin Orders View and Real Sales Analytics Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the cosmetic checkout, fake sales history and fake analytics with real, server-priced orders that the admin manages and the customer can see.

**Architecture:** Two new tables (`orders`, `order_items`). Two action classes hold the business rules (`PlaceOrder`, `ChangeOrderStatus`); thin controllers expose JSON endpoints; the existing vanilla-JS pages (`script.js`, `admin.js`, `analytics.js`, `account.blade.php`) call them. Stock changes happen in DB transactions with row locks.

**Tech Stack:** Laravel 13 / PHP 8.5 (`^8.3`), MySQL (dev DB `ict_tech`), PHPUnit 12 on in-memory SQLite, Blade + plain JS, Chart.js.

**Spec:** `docs/superpowers/specs/2026-10-27-orders-and-analytics-design.md`

All commands run from `C:\Users\Dell\ict-tech\backend` unless stated. A test run prints one JSON line; "passes" means `"result":"passed"`.

## Global Constraints

- Orders are placed only by a logged-in customer (`web` guard); admin endpoints sit in the existing `admin` middleware group (`admin` guard).
- The browser sends **no prices**; the server uses current `products.price` and `products.name`.
- Money: integer cents internally (`(int) round($price * 100)`), stored `decimal(10,2)`; never sum floats.
- Statuses exactly: `pending`, `paid`, `delivered`, `cancelled`. Payment methods exactly: `aba`, `cod`.
- Transitions: `pending → paid | delivered | cancelled`; `paid → delivered | cancelled`; `delivered`/`cancelled` final.
- Phone rule `/^[0-9+\-\s]{8,15}$/`; address required, max 500; items 1..50; quantity integer 1..99; lat -90..90; lng -180..180.
- `POST /checkout` is `throttle:10,1`. Admin list returns latest 200; customer list latest 50.
- JSON errors use `{ "ok": false, "error": "<message>" }` (existing convention). Logged-out customer endpoints: `401 {ok:false,error:"Please sign in."}`.
- Analytics: last 14 days, zero-filled, `cancelled` excluded, days bucketed in `Asia/Phnom_Penh` (app timezone stays UTC).
- Follow `app/Models/User.php` style: `#[Fillable([...])]` attribute, `casts()` method. Tests: `RefreshDatabase`, `test_` method names.
- Commits are local only on branch `feature/orders`; **never push**, and **never `git add -A`/`.`** — `backend-old-skeleton/` holds a `.env` and must stay uncommitted. Add explicit paths.

## Review Focus

Failure modes the spec implies; each is pinned by a test or manual check in the task named.

1. HTML in customer-supplied text (address, phone, names) must display as plain text in the admin Orders table and "My orders" — Tasks 5, 6 (manual payload check; both build rows with `innerHTML`).
2. Double-clicking the confirm button must create exactly one order — Task 3 (in-flight guard + manual check).
3. Odd input: quantity `0`/`100`/`1.5`/`"abc"`, whitespace-only address, unknown payment method, empty cart → `422`, never `500` — Task 2.
4. Order lists must not break after the product or the customer account was deleted — Tasks 4, 6.
5. Status `"refunded"`/empty and unknown order ids → `422`/`404`, never `500` — Task 4.

---

### Task 0: Branch and baseline commits

**Files:** none changed; git only.

- [ ] **Step 1:** `php artisan test` → passes (20 tests).
- [ ] **Step 2:** From the repo root run `git status --short`. Expected: only the admin-table work (`backend/app/Models/Admin.php`, two `create/copy_*admins*` migrations, `config/auth.php`, `AdminAuthController.php`, `EnsureUserIsAdmin.php`, `admin-view.blade.php`, `AdminSeeder.php`, three `tests/Feature/*Admin*|CopyLegacy*` tests), `docs/`, and `?? backend-old-skeleton/`. If anything else appears, stop and ask.
- [ ] **Step 3:** `git switch -c feature/orders`; commit the admin-table files by explicit path ("Add admins table and move admin login to it"); commit `docs/superpowers` ("Add orders and analytics spec and plan"). Verify `git status --short` shows only `?? backend-old-skeleton/`.

### Task 1: Orders schema, models, test helpers

**Files:**
- Create: `database/migrations/2026_10_27_160000_create_orders_table.php`, `…160100_create_order_items_table.php`, `app/Models/Order.php`, `app/Models/OrderItem.php`, `tests/Concerns/MakesShopData.php`, `tests/Feature/OrderModelsTest.php`
- Modify: `app/Models/User.php` (add `orders()`)

**Interfaces:**
- Produces: `App\Models\Order` with `const STATUS_PENDING|STATUS_PAID|STATUS_DELIVERED|STATUS_CANCELLED`, `const STATUSES`, `user(): BelongsTo`, `items(): HasMany`; fillable `user_id, number, status, payment_method, phone, address, lat, lng, total`; `total` cast `decimal:2`. `App\Models\OrderItem` with `order()`, `product()` (both `BelongsTo`); fillable `order_id, product_id, name, brand, unit_price, quantity`. `User::orders(): HasMany`.
- Produces test helpers (trait `Tests\Concerns\MakesShopData`): `makeProduct(array $overrides = []): Product` (defaults name `Test Phone`, brand `Acme`, category `phones`, price `100.00`, qty `5`), `makeCustomer(array $overrides = []): User`, `makeAdmin(): Admin` (created then `->fresh()`).

- [ ] **Step 1: Write failing tests** in `OrderModelsTest` (use the trait):
```php
test_order_item_keeps_its_snapshot_when_the_product_is_deleted   // item name 'Test Phone', unit_price '100.00' remain; product_id null
test_order_survives_deleting_the_customer                        // order exists, user_id null
test_deleting_an_order_deletes_its_items                         // OrderItem::count() === 0
```
- [ ] **Step 2:** Run `php artisan test --filter=OrderModelsTest` → fails (`Order` class missing).
- [ ] **Step 3:** Create the two migrations per spec §4 (FKs: `orders.user_id` nullable `nullOnDelete`; `order_items.order_id` `cascadeOnDelete`, `product_id` nullable `nullOnDelete`; `number` unique nullable; default status `pending`; indexes on `orders.status` and `orders.created_at`), the models, the trait and `User::orders()`.
- [ ] **Step 4:** Run the filter → passes. If a FK assertion fails on SQLite, check `foreign_key_constraints` in `config/database.php`. Run `php artisan test` → all pass.
- [ ] **Step 5:** Commit explicit paths ("Add orders and order_items tables and models").

### Task 2: Checkout endpoint (`POST /checkout`)

**Files:**
- Create: `app/Exceptions/OrderRejected.php`, `app/Actions/PlaceOrder.php`, `app/Http/Controllers/CheckoutController.php`, `tests/Feature/CheckoutTest.php`
- Modify: `routes/web.php`, `app/Models/Order.php` (add `toSummary()`)

**Interfaces:**
- Consumes: Task 1 models and `MakesShopData`.
- Produces: `OrderRejected extends RuntimeException` (message is safe to show the customer). `PlaceOrder::handle(User $user, array $data): Order` where `$data` has `phone, address, payment_method, lat, lng, items` (`items` = list of `['product_id'=>int,'quantity'=>int]`). `Order::toSummary(): array` → `['number','status','payment_method','total'(float),'created_at'(ISO 8601),'items'=>[['name','brand','unit_price'(float),'quantity']]]`. Route `POST /checkout` named `checkout`, `throttle:10,1`; success `200 {ok:true, order: toSummary()}`.

- [ ] **Step 1: Write failing tests** in `CheckoutTest` (customer posts as `actingAs($customer)->postJson('/checkout', …)`):
```php
test_order_uses_database_prices_and_ignores_browser_prices  // item qty 2 of price 100 plus extra keys price=>1,unit_price=>1 → response total 200.0; order_items.unit_price '100.00'
test_checkout_decrements_stock                              // qty 5, buy 2 → 3
test_short_stock_refuses_everything                         // A qty 5 ×1, B qty 1 ×2 → 422 'Only 1 left of <B name>.'; Order::count()===0; A qty still 5
test_out_of_stock_product_is_refused                        // qty 0 → 422 '<name> is out of stock.'
test_duplicate_lines_are_merged_before_the_stock_check      // qty 4: [A×2,A×3] → 422; qty 5 → 200 with one item of quantity 5
test_unknown_product_is_refused                             // product_id 999999 → 422 'One of the items in your cart is no longer available.'
test_logged_out_checkout_is_401                             // {ok:false,error:'Please sign in.'}
test_first_order_number_is_ORD_000001                       // response order.number === 'ORD-000001'
test_snapshot_survives_price_change_and_product_deletion    // after placing: raise price to 999 and delete product → item still name/'100.00'
test_odd_input_is_a_422_not_a_500    // dataset: phone '12ab'; items []; quantity 0, 100, 1.5, 'abc'; address '   '; payment_method 'paypal'
test_checkout_is_rate_limited                               // 10 invalid posts then the 11th → 429
```
- [ ] **Step 2:** Run `--filter=CheckoutTest` → fails (route/class missing).
- [ ] **Step 3: Implement.** `CheckoutController::store(Request): JsonResponse` validates with `Validator::make` (rules from Global Constraints; no `exists` rule, so an unknown product reaches `PlaceOrder` for its own message), returns 401/422 in the project JSON shape, calls `PlaceOrder`, maps `OrderRejected` to `422`. `PlaceOrder::handle` runs one `DB::transaction`: merge duplicate lines, load products `orderBy('id')->lockForUpdate()`, check existence then `qty`, build items from current DB name/brand/price in cents, create order, set `number` to `sprintf('ORD-%06d', $order->id)`, decrement stock, return the order with items loaded.
- [ ] **Step 4:** Run the filter → passes; run `php artisan test` → all pass.
- [ ] **Step 5:** Commit ("Add server-side checkout").

### Task 3: Browser checkout calls the server

**Files:** Modify `public/assets/js/script.js` (`handleCheckoutContactSubmit` ~589, payment step ~720–745, `completeCheckoutPayment` ~644, `checkoutSuccessStepHTML` ~756).

**Interfaces:** Consumes the Task 2 contract. Produces: `_checkoutOrder.number` (string) and `.total` (number) set from the server response.

- [ ] **Step 1:** Remove the browser-made `id: 'ORD' + Date.now()…` from `_checkoutOrder`; remove the "Order #…" line from the payment step and the `order:${order.id}|` part of the QR payload (the number now exists only after the server creates the order); success step shows `#${escapeHtml(order.number)}` and the server total.
- [ ] **Step 2:** Make `completeCheckoutPayment()` `async`: return early if `_checkoutSubmitting` is true, set it and disable the confirm button; `POST /checkout` (JSON, `X-CSRF-TOKEN` via the existing `csrfToken()`) with `phone, address, payment_method, lat, lng` and `items = Cart.getItems().map(i => ({product_id: i.id, quantity: i.qty}))`. On `ok`: store `number`/`total`, then the existing success flow (success step, `Cart.clear()`, close drawer). On `401`: redirect to `/account?redirect=<page>` (existing pattern). Otherwise `_checkoutMsg(error, 'error')`, stay on the step. Always clear `_checkoutSubmitting` in `finally`.
- [ ] **Step 3: Manual check** (no JS test runner exists; run `php artisan serve --port=8010`, log in as a customer): (a) COD checkout of 2 units → success shows `ORD-00000N`; `orders`, `order_items` rows exist and `products.qty` fell by 2; (b) double-click "Confirm Order" quickly → exactly one new order; (c) edit the cart price in `localStorage` to `1` → success total is still the DB price; (d) set that product's `qty` to 0 in MySQL → error shown, drawer stays on the step.
- [ ] **Step 4:** Commit ("Send checkout to the server").

### Task 4: Admin orders API

**Files:**
- Create: `app/Actions/ChangeOrderStatus.php`, `app/Http/Controllers/Admin/OrderController.php`, `tests/Feature/AdminOrdersTest.php`
- Modify: `routes/web.php` (inside the `admin` group), `app/Models/Order.php` (add `toAdminArray()`)

**Interfaces:**
- Consumes: `OrderRejected`, `Order::toSummary()`, helpers.
- Produces: `ChangeOrderStatus::handle(Order $order, string $status): Order` (throws `OrderRejected` on a disallowed transition). `Order::toAdminArray(): array` = `toSummary()` plus `id, phone, address, lat, lng, customer => ['name','email']|null`. Routes `GET /admin/orders` (`admin-orders.index`, JSON array, newest first, max 200) and `PUT /admin/orders/{order}` body `{status}` (`admin-orders.update`) → `{ok:true, order: toAdminArray()}`.

- [ ] **Step 1: Write failing tests** in `AdminOrdersTest` (place orders through `PlaceOrder`):
```php
test_index_lists_newest_first_with_items_and_customer
test_index_still_works_after_the_product_and_the_customer_were_deleted   // customer === null; items keep snapshot
test_logged_out_and_customer_sessions_are_redirected_to_admin_login
test_allowed_transitions_succeed     // dataset: pending→paid, pending→delivered, pending→cancelled, paid→delivered, paid→cancelled
test_disallowed_transitions_are_422  // dataset: paid→pending, delivered→cancelled, cancelled→paid, delivered→paid
test_unknown_status_is_422_and_unknown_order_is_404   // 'refunded', '' → 422; /admin/orders/9999 → 404
test_cancelling_restores_stock_exactly_once   // qty 5, order 2 → 3; cancel → 5; second cancel → 422, still 5
test_cancelling_with_a_deleted_product_does_not_fail
```
- [ ] **Step 2:** Run `--filter=AdminOrdersTest` → fails.
- [ ] **Step 3: Implement.** `ChangeOrderStatus` holds `const TRANSITIONS` and in a transaction re-reads the order with `lockForUpdate()`, validates, saves, and on `cancelled` increments each item's product `qty` by `quantity` (skip null `product_id`). Controller validates `status` with `Validator::make` (`in:paid,delivered,cancelled`) and maps `OrderRejected` to `422`.
- [ ] **Step 4:** Run the filter → passes; `php artisan test` → all pass.
- [ ] **Step 5:** Commit ("Add admin orders API").

### Task 5: Admin Orders view

**Files:** Modify `public/assets/js/admin.js` (history block ~261–312, `showView` ~456–465, boot), `resources/views/admin-view.blade.php` (History nav button ~90, `#view-history` table header ~422–445).

**Interfaces:** Consumes `GET /admin/orders` and `PUT /admin/orders/{id}` (Task 4) through the existing `apiFetch` helper.

- [ ] **Step 1:** Rename the visible label "History" → "Orders" (nav button text, `titles` map in `showView`, empty-state text). Keep the element ids (`nav-history`, `view-history`, `history-tbody`, `history-empty`) unchanged.
- [ ] **Step 2:** Delete `historyRecords`; keep `let orders = []` loaded via `apiFetch('/admin/orders')` when the view opens. Replace table headers with Order, Customer, Items, Total, Payment, Status, Date, Actions. `renderHistoryTable()` renders rows; the topbar search matches order number, customer name/email, item names. **Every customer-supplied value goes through `escapeHtml` (admin.js:220).**
- [ ] **Step 3:** Action buttons appear only for allowed transitions (Mark paid / Mark delivered / Cancel); a click `PUT`s the new status, updates the row from the response, shows the existing toast; server errors are shown, not swallowed.
- [ ] **Step 4: Manual check** (as admin; place an order as a customer with address `<img src=x onerror=alert(1)>`): the address displays literally and no alert fires; Mark paid updates the badge; Cancel restores the product `qty` in MySQL; delivered/cancelled rows show no buttons; with no orders the empty state shows.
- [ ] **Step 5:** Commit ("Replace fake sales history with real orders").

### Task 6: Customer orders (`GET /orders` + My orders on /account)

**Files:**
- Create: `app/Http/Controllers/CustomerOrderController.php`, `tests/Feature/CustomerOrdersTest.php`
- Modify: `routes/web.php`, `resources/views/account.blade.php` (signed-in "Welcome back" card, ~line 66)

**Interfaces:** Consumes `Order::toSummary()`, `User::orders()`. Produces `GET /orders` (name `orders`) → JSON list of `toSummary()`, newest first, max 50.

- [ ] **Step 1: Write failing tests:**
```php
test_customer_sees_only_their_own_orders_newest_first
test_logged_out_request_is_401_json            // {ok:false,error:'Please sign in.'}
test_list_still_works_after_a_product_was_deleted   // items keep snapshot name
```
- [ ] **Step 2:** Run `--filter=CustomerOrdersTest` → fails. **Step 3:** Implement the controller (`$request->user()->orders()->with('items')->latest()->limit(50)`) and route; run → passes.
- [ ] **Step 4:** In `account.blade.php`, add a "My orders" section to the welcome card: `fetch('/orders', {headers:{Accept:'application/json'}})`, render number, date, status, total and item names with `escapeHtml`; empty state "No orders yet". **Manual check:** place an order → it appears; the `<img onerror>` address/name payload from Task 5 never executes here.
- [ ] **Step 5:** Run `php artisan test` → all pass; commit ("Show customers their orders").

### Task 7: Real analytics, then final verification

**Files:**
- Create: `app/Http/Controllers/Admin/AnalyticsController.php`, `tests/Feature/AdminAnalyticsTest.php`
- Modify: `routes/web.php`, `public/assets/js/analytics.js` (`fetchAnalyticsData`, `computeAnalyticsSummary`, `renderAnalyticsKpis`, `renderAnalyticsChart`), `resources/views/admin-view.blade.php` (KPI cards, heading)

**Interfaces:** Produces `GET /admin/analytics/summary` (`admin-analytics.summary`) → `{labels:[14 'Y-m-d'], orders:[14 int], revenue:[14 float]}`, last label = today in Phnom Penh.

- [ ] **Step 1: Write failing tests** (`$this->travelTo('2026-03-15 18:30:00')` UTC = 2026-03-16 01:30 Phnom Penh):
```php
test_returns_fourteen_zero_filled_days_ending_today_in_phnom_penh   // labels[13]==='2026-03-16', labels[0]==='2026-03-03'; no orders → all zeros
test_day_boundary_follows_phnom_penh_time   // order at 2026-03-15 16:59:59 UTC counts on '2026-03-15'; at 17:00:00 UTC on '2026-03-16'
test_cancelled_orders_are_excluded_and_pending_are_counted   // revenue is the sum of totals
test_orders_older_than_fourteen_days_are_excluded
test_non_admin_is_redirected_to_admin_login
```
- [ ] **Step 2:** Run `--filter=AdminAnalyticsTest` → fails. **Step 3:** Implement: fetch non-cancelled orders since the window start (converted to UTC), bucket in PHP with `->setTimezone('Asia/Phnom_Penh')->toDateString()`, sum revenue in integer cents; run → passes.
- [ ] **Step 4:** Update `analytics.js`: `fetchAnalyticsData()` fetches the endpoint and, on failure, shows "Analytics unavailable" in the chart area (no fake numbers); chart = orders (left axis) and order value (right axis); KPI ids `kpi-visitors/purchases/conversion` → `kpi-orders/revenue/average` in the markup and JS (total orders, total order value, average order value); heading → "Sales Analytics (last 14 days)". **Manual check:** dashboard shows real numbers matching MySQL; block the request in DevTools → the unavailable message shows.
- [ ] **Step 5: Final verification:** `php artisan test` → all pass; `php artisan migrate:status` → both new migrations `Ran` in MySQL (run `php artisan migrate` if pending); `php artisan route:list --path=checkout` and `--path=admin` list the new routes; `git status --short` shows only `?? backend-old-skeleton/`.
- [ ] **Step 6:** Commit ("Show real sales analytics").
