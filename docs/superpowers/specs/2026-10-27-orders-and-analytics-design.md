# Real orders, admin Orders view and real sales analytics — design

Date: 2026-10-27
Status: design approved in conversation; this spec awaits the owner's review.
App: `backend/` (Laravel 13, MySQL `ict_tech`, Blade + plain JS in `public/assets/js`).

## 1. Problem

Checkout is cosmetic. `script.js` collects phone, address, map pin and payment
method, invents an order id in the browser (`'ORD' + Date.now()...`), shows
"success" and empties the cart. **No request reaches the server and no order is
stored**, yet the customer is told the order worked. Totals are computed in the
browser from cart prices, so they can be tampered with. The admin dashboard
shows 13 hard-coded "Sales history" rows and a chart built from hard-coded
numbers (`analytics.js`), which an admin could mistake for real data.

## 2. Goal

When a logged-in customer completes checkout, a real order is saved at
server-side prices and stock goes down. The admin sees and manages real orders
(replacing the fake history). The customer sees their own orders on `/account`.
The dashboard chart and cards show real sales.

## 3. Decisions (agreed with the owner)

1. **No payment gateway.** ABA stays a demo QR. Every order is saved `pending`;
   the admin marks it paid / delivered / cancelled by hand. A gateway (e.g. ABA
   PayWay) is a separate future project.
2. **Stock** is reduced when the order is placed, inside a database transaction
   with row locks. Cancelling restores stock exactly once. Insufficient stock
   refuses the whole checkout and saves nothing.
3. **Price snapshot.** Each order item keeps the product name, brand and unit
   price at purchase time.
4. **Analytics** show real orders and order value per day for the last 14 days.
   The "visitors" series and the conversion rate are removed (nothing records
   visits).
5. **The order is created at the final confirm click**, not when the QR/payment
   step appears, so an abandoned ABA QR never holds stock.
6. "Order value" = sum of `total` over orders whose status is not `cancelled`
   (pending orders count).

## 4. Data model

New migrations (no backfill; existing data is untouched).

`orders`
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| user_id | FK users, nullable, `nullOnDelete` | orders outlive a deleted account |
| number | string, unique, nullable until set | `sprintf('ORD-%06d', id)`, set in the same transaction as the insert |
| status | string, default `pending` | `pending`, `paid`, `delivered`, `cancelled` |
| payment_method | string | `aba` or `cod` |
| phone | string | |
| address | text | |
| lat, lng | decimal(10,7), nullable | map pin if shared |
| total | decimal(10,2) | computed on the server |
| created_at, updated_at | timestamps | index on `created_at` and on `status` |

`order_items`
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| order_id | FK orders, `cascadeOnDelete` | |
| product_id | FK products, nullable, `nullOnDelete` | deleting a product keeps history |
| name, brand | string | snapshot |
| unit_price | decimal(10,2) | snapshot |
| quantity | unsigned int | |

Models: `Order` (belongsTo user, hasMany items), `OrderItem` (belongsTo order,
belongsTo product), `User::orders()`. Money is computed in integer cents
(`(int) round(price * 100)`) and stored as decimal, never accumulated as floats.

## 5. Checkout — `POST /checkout` (customer)

Auth: the `web` guard (logged-in customer) else `401 {ok:false,error:"Please sign in."}`.
Rate limit: `throttle:10,1`. CSRF: existing `X-CSRF-TOKEN` header from `script.js`.

Request (the browser sends **no prices**):
```
phone           required, regex /^[0-9+\-\s]{8,15}$/   (same rule as the client)
address         required, string, max 500
payment_method  required, in: aba, cod
lat             nullable, numeric, -90..90
lng             nullable, numeric, -180..180
items           required, array, 1..50 entries
items.*.product_id  required, integer, exists:products,id
items.*.quantity    required, integer, 1..99
```
Duplicate `product_id`s are merged (quantities summed) before processing.

Success `200`:
```
{ ok: true, order: { number, status, payment_method, total,
    items: [ { name, brand, unit_price, quantity } ] } }
```
Failure `422 {ok:false, error:"<first message>"}`: validation errors; unknown
product ("One of the items in your cart is no longer available."); short stock
("Only 2 left of <name>." / "<name> is out of stock.").

Logic lives in an action class `App\Actions\PlaceOrder`. In one
`DB::transaction`: load the requested products `ORDER BY id` with
`lockForUpdate()`; verify `qty >= requested` for every line (else abort, nothing
written); build items from **current DB price and name**; insert the order and
items; set `number`; decrement each product's `qty`.

Browser (`script.js`): `completeCheckoutPayment()` becomes async. It POSTs the
cart (`product_id`, `quantity` only) plus the contact/payment fields, disables
its button while waiting, and on success shows the success step using the
**server's** order number and total, then clears the cart. On failure it stays
on the current step and shows the server's error message. The browser-made
`ORD…` id is removed.

## 6. Orders for the admin — `/admin/orders` (admin guard)

- `GET /admin/orders` → JSON, newest first, latest 200 (no pagination in v1).
  Each order: number, status, payment_method, total, phone, address, lat/lng,
  created_at, customer `{name,email}` (null if the account is gone), items.
- `PUT /admin/orders/{order}` body `{status}` (`paid|delivered|cancelled`).
  Allowed transitions: `pending → paid | delivered | cancelled`;
  `paid → delivered | cancelled`. `delivered` and `cancelled` are final.
  Anything else → `422 {ok:false,error:"..."}`.
  Logic in `App\Actions\ChangeOrderStatus`: in a transaction, re-read the order
  with `lockForUpdate()` (so a double click cannot cancel twice), validate the
  transition, and on `cancelled` add each item's `quantity` back to its product
  (skipped when the product no longer exists). Returns the updated order.
- UI: the admin sidebar item and view "History" become **Orders**. `admin.js`
  drops `historyRecords` and renders the fetched orders: order number, customer,
  items summary, total, payment method, status badge, date, and action buttons
  (Mark paid / Mark delivered / Cancel) shown only for allowed transitions.
  The existing search box filters by number, customer or item name.

## 7. Orders for the customer — `GET /orders`

`web` guard; `401` JSON when logged out. Returns the user's own orders only
(`$request->user()->orders()`), newest first, latest 50, same shape as section 5
plus `created_at`. `/account`'s signed-in "Welcome back" card gets a "My orders"
list rendered from this endpoint (empty state: "No orders yet").

## 8. Real analytics — `GET /admin/analytics/summary` (admin guard)

Returns `{ labels: [14 ISO dates], orders: [14 ints], revenue: [14 numbers] }`
for the last 14 days ending today, zero-filled, excluding `cancelled`. Days are
bucketed in `Asia/Phnom_Penh` (rows fetched for the window and grouped in PHP;
the app timezone itself stays UTC). `analytics.js` `fetchAnalyticsData()` calls
this endpoint; the chart plots orders and order value on two y-axes; the summary
cards become total orders, total order value and average order value (14 days).
A fetch failure shows an "Analytics unavailable" message instead of fake data.

## 9. Tests (written first, PHPUnit, in-memory SQLite)

Checkout: ignores any browser-sent price; saves order+items with DB prices;
decrements stock; short stock → 422 and nothing saved; unknown product → 422;
merges duplicate lines; logged-out → 401; invalid phone → 422; snapshot survives
a later price change and product deletion.
Status: valid transitions; invalid transitions rejected; cancel restores stock;
second cancel does not restore again; non-admin and logged-out blocked.
Customer list: only own orders; logged-out → 401.
Analytics: 14 zero-filled buckets, cancelled excluded, Phnom Penh day boundary,
admin-only.

## 10. Files

New: `app/Models/Order.php`, `OrderItem.php`; `app/Actions/PlaceOrder.php`,
`ChangeOrderStatus.php`; `app/Http/Controllers/CheckoutController.php`,
`CustomerOrderController.php`, `Admin/OrderController.php`,
`Admin/AnalyticsController.php`; two migrations; test files.
Changed: `routes/web.php`; `app/Models/User.php`; `public/assets/js/script.js`,
`admin.js`, `analytics.js`; `resources/views/account.blade.php`,
`admin-view.blade.php`.

## 11. Out of scope

Payment gateway; emails/notifications; refunds; editing orders; automatic expiry
of unpaid orders (an unpaid submitted ABA order holds its stock until cancelled);
delivery tracking; invoices; pagination; visit tracking.

## 12. Deployment

Run `php artisan migrate` on the live server. Carts already sitting in customers'
browsers may hold items that no longer exist; checkout reports this clearly.
