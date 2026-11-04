# Sales report (day / week / month, CSV and print) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the admin a Sales Report page: pick Day, Week or Month and a date, see totals, a breakdown and what sold, download the sales record as CSV, and print it.

**Architecture:** One server class `App\Reports\SalesReport` resolves a (period, date) into shop-time boundaries, loads that period's non-cancelled orders once, and produces both the JSON summary and the CSV rows, so screen and download cannot disagree. A thin admin controller validates and returns JSON or a streamed CSV. A new `sales-report.js` renders the page; Blade gets a sidebar item, a `view-report` section and print rules. No database changes.

**Tech Stack:** Laravel 13, PHP 8.5, Carbon, PHPUnit class-style tests (SQLite in-memory), Blade, plain JS.

**Spec:** `docs/superpowers/specs/2026-11-04-sales-report-design.md`

## Global Constraints

- All commands run from `backend/`. Tests: `php artisan test --filter=<Name>`; a pass prints `"result":"passed"`. TDD: write the test, watch it fail for the right reason, then implement. Never `db:seed` the local MySQL. Do not push.
- Branch `sales-report` (stacked on `brands-and-categories`). End every commit message with `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`; one commit per task.
- A sale = an order whose `status` is not `cancelled` (pending, paid, delivered count). An order belongs to the day it was **placed**, in shop time.
- Shop time is `config('shop.timezone')` = `'Asia/Phnom_Penh'`; app and DB stay UTC. Convert query bounds with `->setTimezone(config('app.timezone'))` before binding (a Carbon bound in another timezone is formatted in that timezone). Range is half-open: `created_at >= from 00:00 local` and `< (to + 1 day) 00:00 local`.
- Week is Monday to Sunday (`startOfWeek(Carbon::MONDAY)`); month is the calendar month.
- Request: `period` required, one of `day|week|month`; `date` optional `Y-m-d`, a real date, between `2000-01-01` and `2100-12-31` (missing = today in shop time). On failure both endpoints return `422` with `{"message": <first error>, "errors": {...}}` whatever the `Accept` header.
- Money is summed in whole cents (`(int) round((float) $value * 100)`), output as floats (`cents / 100`). Item line = `unit_price × quantity` in cents. `averageOrder` = `round(revenue_cents / orders)` cents, `0` when no orders.
- JSON shape (spec section 5): `{period, date, from, to, totals:{orders,items,revenue,averageOrder}, days:[{date,orders,items,revenue}…every day of the period], orders:[{number,date,time,status,payment_method,items,total}… only when period is day, oldest first by created_at then id], products:[{name,brand,quantity,revenue}…]}`. `products` group by exact (`name`,`brand`) item text; sort revenue desc, quantity desc, name asc.
- CSV (spec section 6): UTF-8 BOM, header `Date,Time,Order,Status,Payment,Product,Brand,Quantity,Unit price,Line total`, one row per item ordered by order `created_at`, order id, item id; `Date` `Y-m-d` and `Time` `H:i` in shop time; `Payment` is the stored code; money `number_format($cents / 100, 2, '.', '')`; no customer data; text cells (`Order`, `Status`, `Payment`, `Product`, `Brand`) starting with `=`, `+`, `-`, `@`, tab or CR get a leading `'`. Filename `sales-<period>-<from>-to-<to>.csv`.
- Test style: `Tests\Feature`, `RefreshDatabase`, `MakesShopData`, `$this->actingAs($this->makeAdmin(), 'admin')` (create the admin once per test), guests redirected with `assertRedirect(route('admin-login'))`.

## Review Focus

1. Orders exactly on shop-time midnight: `2026-11-04 16:59:59 UTC` is still the 4th, `17:00:00 UTC` is the 5th; Sunday 23:59:59 vs Monday 00:00; month first and last instant. (Task 1)
2. Float noise: 3 × 0.10 sums to exactly 0.30; three orders of 3.33, 3.33, 3.34 average 3.33. (Task 1)
3. Product deleted after the sale still reported; the same product name under two brands stays two rows. (Task 1)
4. CSV cells with a formula prefix, a comma, a quote or a line break in the product name; customer name, email, phone and address never appear. (Task 2)
5. Zero-sales periods give zeros and a header-only CSV; `2026-02-30`, `2026-13-01`, `20261104`, `2026-11-04abc`, `1999-12-31`, `2101-01-01`, an array `date`, a missing or unknown `period` give 422, never 500. (Tasks 1 and 2)

---

### Task 1: Report data and JSON endpoint

**Files:**
- Create: `app/Reports/SalesReport.php`, `app/Http/Controllers/Admin/SalesReportController.php`, `tests/Feature/SalesReportTest.php`
- Modify: `config/shop.php` (add `'timezone' => 'Asia/Phnom_Penh'`), `app/Http/Controllers/Admin/AnalyticsController.php` (read `config('shop.timezone')` instead of its `SHOP_TIMEZONE` constant), `routes/web.php` (admin group), `tests/Concerns/MakesShopData.php`

**Interfaces:**
- Produces: `config('shop.timezone')`; `MakesShopData::makeSale(string $placedAtUtc, array $lines, string $status = Order::STATUS_PENDING, string $payment = 'cod'): Order` where each line is `['name'=>string,'brand'=>string,'price'=>string,'qty'=>int]`; it creates the order with `Order::unguarded` and `created_at` = `$placedAtUtc`, `total` = sum of lines, `number` = `sprintf('ORD-%06d', $order->id)`, and the items (`product_id` null).
- Produces: `App\Reports\SalesReport` with `public static function for(string $period, ?string $date): self` (inputs already validated; `null` date = today in shop time), `public function summary(): array` (the JSON shape above), `public function from(): CarbonImmutable`, `public function to(): CarbonImmutable` (shop-time start of the first and last day).
- Produces: `SalesReportController::show(Request): JsonResponse`; route `admin-reports.sales` = `GET /admin/reports/sales` in the `admin` middleware group.

- [ ] **Step 1: Write the failing tests** in `SalesReportTest` (helper `report(string $period, ?string $date = null)` calls `getJson(route('admin-reports.sales', …))->assertOk()->json()`). Reference data for the week tests (shop time = UTC+7): A `2026-11-04 16:59:59` UTC total 10.00 pending; B `2026-11-04 17:00:00` 20.00 paid; C `2026-11-08 16:59:59` 30.00 delivered; D `2026-11-08 17:00:00` 40.00 pending; E `2026-11-05 03:00:00` 50.00 cancelled; each one line (qty 1).
  - `test_week_totals_use_shop_time_and_monday_to_sunday_boundaries`: `week` + `2026-11-04` → `from` `2026-11-02`, `to` `2026-11-08`, orders 3, items 3, revenue 60.0, averageOrder 20.0; `days` has 7 entries; `days[2]` (4 Nov) 1 order 10.0, `days[3]` (5 Nov) 1 order 20.0, `days[6]` (8 Nov) 1 order 30.0, the other days all zeros; `orders` is `[]`.
  - `test_day_report_lists_orders_oldest_first_in_shop_time`: `day` + `2026-11-05` with orders at `2026-11-04 17:00:00` UTC and `2026-11-05 03:15:00` UTC and a cancelled one → `days` has 1 entry; `orders` times `['00:00','10:15']`, `date` `'2026-11-05'` on both, each with `number` (`ORD-…`), `status`, `payment_method`, `items`, `total`.
  - `test_month_covers_every_day_and_shop_time_month_edges`: `month` + `2028-02-10` → `from` `2028-02-01`, `to` `2028-02-29`, 29 days; `month` + `2026-11-15`: an order at `2026-10-31 17:00:00` UTC (1 Nov local) is in, one at `2026-11-30 17:00:00` UTC (1 Dec local) is out, 30 days.
  - `test_cancelled_is_excluded_and_pending_paid_delivered_count`.
  - `test_money_is_summed_in_whole_cents_and_the_average_is_rounded`: lines 0.10 × 3 and 0.20 × 1 in one order → totals revenue 0.5, products revenue 0.3 and 0.2; three orders 3.33, 3.33, 3.34 → averageOrder 3.33.
  - `test_empty_period_returns_zeros`: totals all 0 (average 0), every `days` entry zero, `orders` `[]`, `products` `[]`.
  - `test_products_are_grouped_by_name_and_brand_and_sorted`: `Phone X`/`Acme` 100.00 × 1 and × 2 (two orders), `Phone X`/`Other` 100.00 × 1, `Cable`/`Acme` 5.00 × 10 → `[Phone X Acme qty 3 rev 300, Phone X Other qty 1 rev 100, Cable Acme qty 10 rev 50]`; two rows with equal revenue and quantity sort by name ascending.
  - `test_a_product_deleted_after_the_sale_is_still_reported`: `travelTo('2026-11-04 10:00:00')`, `placeOrder(customer, makeProduct())`, delete the product → `day` report lists `Test Phone`/`Acme`.
  - `test_default_date_is_today_in_shop_time`: `travelTo('2026-11-04 18:30:00')` (5 Nov 01:30 local); `day` with no `date` → `date` `'2026-11-05'`, and an order placed `2026-11-04 17:10:00` UTC is included.
  - `test_invalid_requests_get_422_json` (data provider): missing `period`, `period=year`, `period=DAY`, `date` of `2026-02-30`, `2026-13-01`, `20261104`, `2026-11-04abc`, `1999-12-31`, `2101-01-01`, `date[]=x` → status 422 and JSON keys `message` and `errors`.
  - `test_guests_and_customers_are_redirected_to_admin_login`.
- [ ] **Step 2: Run** `php artisan test --filter=SalesReportTest` — fails with "Route [admin-reports.sales] not defined" (and `makeSale` missing), not a syntax error.
- [ ] **Step 3: `makeSale`** in `MakesShopData` as specified above (test helper only).
- [ ] **Step 4: Timezone config.** Add `'timezone' => 'Asia/Phnom_Penh'` to `config/shop.php`; in `AnalyticsController` replace the private constant with `config('shop.timezone')`. Run `php artisan test --filter=AdminAnalyticsTest` — still passes (it pins the 18:30 UTC = 01:30 next day boundary).
- [ ] **Step 5: Implement `SalesReport`** per Global Constraints: `for()` resolves from/to (`CarbonImmutable::createFromFormat('Y-m-d', …, tz)->startOfDay()`, week via `startOfWeek(Carbon::MONDAY)`/`endOfWeek`, month via `startOfMonth`/`endOfMonth`, then `startOfDay`); one query `Order::with('items')->where('status', '!=', Order::STATUS_CANCELLED)->where('created_at', '>=', $fromUtc)->where('created_at', '<', $toExclusiveUtc)->orderBy('created_at')->orderBy('id')->get()`; group by `$order->created_at->setTimezone(tz)->toDateString()` in PHP; cents arithmetic only. `summary()` builds `days` for every date of the period.
- [ ] **Step 6: Implement `SalesReportController`** with `show(Request)`: validate `$request->query()` with `Validator::make` (rules: `period` `required|in:day,week,month`; `date` `nullable|date_format:Y-m-d|after_or_equal:2000-01-01|before_or_equal:2100-12-31`; a real-date check — `date_format` accepts `2026-02-30`, so also compare `CarbonImmutable::createFromFormat('Y-m-d', $d)->format('Y-m-d') === $d`; reject non-string `date`), and on failure return `response()->json(['message' => $v->errors()->first(), 'errors' => $v->errors()], 422)`; keep the validation in one private method `validated(Request): array|JsonResponse` that Task 2's `export()` reuses. Add the route.
- [ ] **Step 7: Run** `php artisan test --filter=SalesReportTest` (passes), then the whole suite.
- [ ] **Step 8: Commit** — `git add app config routes tests && git commit -m "Add sales report data and JSON endpoint"`.

---

### Task 2: CSV download

**Files:**
- Modify: `app/Reports/SalesReport.php`, `app/Http/Controllers/Admin/SalesReportController.php`, `routes/web.php`
- Create: `tests/Feature/SalesReportCsvTest.php`

**Interfaces:**
- Consumes: everything Task 1 produces.
- Produces: `SalesReport::CSV_HEADER` (the ten column names, in order), `SalesReport::csvRows(): array` (a list of rows, each a list of ten strings, header excluded, formula-neutralised), `SalesReport::filename(): string`; `SalesReportController::export(Request): StreamedResponse|JsonResponse`; route `admin-reports.sales.export` = `GET /admin/reports/sales/export`.

- [ ] **Step 1: Write the failing tests** in `SalesReportCsvTest` (helper `download(period, date)` returns the test response; helper `parse(string $body): array` strips the BOM and reads rows with `fgetcsv` on `php://memory` so embedded line breaks parse), same reference orders A–E as Task 1 where useful:
  - `test_download_has_csv_headers_filename_and_bom`: week `2026-11-04` → `Content-Type` contains `text/csv; charset=UTF-8`, `Content-Disposition` is `attachment; filename="sales-week-2026-11-02-to-2026-11-08.csv"`, body starts with `"\xEF\xBB\xBF"`, first parsed row equals `SalesReport::CSV_HEADER` (`Date,Time,Order,Status,Payment,Product,Brand,Quantity,Unit price,Line total`); a day report is named `sales-day-2026-11-05-to-2026-11-05.csv`.
  - `test_one_row_per_item_with_shop_time_and_exact_cents`: one order placed `2026-11-05 03:15:00` UTC with items `Phone X`/`Acme` 100.00 × 2 and `Cable`/`Acme` 0.10 × 3 → rows `['2026-11-05','10:15','ORD-000001','pending','cod','Phone X','Acme','2','100.00','200.00']` and `[…,'Cable','Acme','3','0.10','0.30']`.
  - `test_rows_are_ordered_by_order_time_then_order_then_item`.
  - `test_no_customer_data_and_no_order_total_column`: an order placed through `placeOrder` for a customer with a distinctive name, email, phone `099 111 222` and address → none of those strings appear in the body; the header contains no `Total` except `Line total`.
  - `test_formula_prefixes_are_neutralised`: product names `=HYPERLINK("x")`, `+1`, `-2`, `@SUM(1)`, and names starting with a tab and with a carriage return → each cell starts with `'`; the name `Normal` is unchanged.
  - `test_commas_quotes_and_line_breaks_in_names_round_trip`: name `Cable, "USB-C"` + newline + `2m` parses back to the exact original.
  - `test_empty_period_returns_bom_and_header_only`.
  - `test_cancelled_orders_are_not_exported`.
  - `test_invalid_requests_get_422_json_and_guests_are_redirected` (same bad inputs as Task 1, a smaller set is enough: `period=year`, `date=2026-02-30`, `date[]=x`).
- [ ] **Step 2: Run** `php artisan test --filter=SalesReportCsvTest` — fails (route not defined), not a syntax error.
- [ ] **Step 3: Implement** `CSV_HEADER`, `csvRows()` and `filename()` in `SalesReport` over the orders it already loaded; neutralise with a private helper that prefixes `'` when the first character is one of `= + - @ \t \r`.
- [ ] **Step 4: Implement `export()`**: reuse `validated()`; `response()->streamDownload(...)` writing the BOM, the header and each row with `fputcsv($out, $row, ',', '"', '')` (the empty escape argument is required on PHP 8.4+), headers `Content-Type: text/csv; charset=UTF-8`, filename from `filename()`. Add the route.
- [ ] **Step 5: Run** `php artisan test --filter=SalesReportCsvTest` (passes), then the whole suite.
- [ ] **Step 6: Commit** — `git commit -m "Add sales report CSV download"` (add the files listed above).

---

### Task 3: Admin Sales Report page (screen and print)

**Files:**
- Modify: `resources/views/admin-view.blade.php` (anchors: the Orders sidebar button `showView('history')`; `<aside id="sidebar" …>`; `<div id="mob-overlay" …>`; the content wrapper `<div class="w-full lg:ml-64 flex flex-col …">`; the top bar `<div class="glass-nav sticky top-0 z-20 …">`; `<div id="admin-toast" …>`; the `view-history` section; the `@section('extra-styles')` block; the script tags at the end), `public/assets/js/admin.js` (`showView`), `tests/Feature/AdminViewTest.php`
- Create: `public/assets/js/sales-report.js`

**Interfaces:**
- Consumes: `GET /admin/reports/sales` (JSON shape in Global Constraints) and `GET /admin/reports/sales/export`; globals from `admin.js`/`analytics.js`: `apiFetch`, `escapeHtml`, `toast`, `ORDER_STATUS_BADGE`, `ORDER_PAYMENT_LABEL`, `formatAnalyticsMoney`.
- Produces (Blade ids the test relies on): sidebar `id="nav-report"` with `onclick="showView('report')"`; section `id="view-report"`; `id="report-title"`, `id="report-date"` (`type="date"`), `id="report-month"` (`type="month"`, hidden until Month), period buttons `id="report-period-day|week|month"`; cards `id="report-orders"`, `id="report-items"`, `id="report-revenue"`, `id="report-average"`; tables `id="report-days-tbody"`, `id="report-orders-tbody"`, `id="report-products-tbody"` inside cards `id="report-days-card"`, `id="report-orders-card"`, `id="report-products-card"`; `id="report-empty"`; `id="report-error"`; content wrapper `id="admin-main"`.
- Produces (JS in `sales-report.js`, loaded after `analytics.js`): `let reportPeriod = 'week'`; `initSalesReport()` (called by `showView('report')` every time the view opens); `setReportPeriod(period)`; `loadSalesReport()`; `renderSalesReport(data)`; `reportQuery(): string` (`period=…&date=…`; omits `date` when empty so the server uses today; for Month sends `<report-month>-01`); `downloadSalesReportCsv()` (sets `window.location` to the export URL with `reportQuery()`); `printSalesReport()` (`window.print()`). After each load the date inputs are set from `data.date`; the title reads like `Week 2 Nov – 8 Nov 2026` (parse `Y-m-d` with `new Date(y, m - 1, d)`; no timezone maths in the browser). Every server value goes through `escapeHtml` before `innerHTML`; a failed request shows the server message in `#report-error` and clears the numbers (never made-up values).
- `admin.js` `showView`: add `'report'` to the view list and `titles` (`report:'Sales Report'`) and `if (view === 'report' && typeof initSalesReport === 'function') initSalesReport();`.

- [ ] **Step 1: Write the failing test** `AdminViewTest::test_admin_view_renders_the_sales_report_page`: as admin `get(route('admin-view'))` → OK and `assertSee` (escape false) `id="nav-report"`, `showView('report')`, `id="view-report"`, `id="report-date"`, `id="report-month"`, `id="report-days-tbody"`, `id="report-orders-tbody"`, `id="report-products-tbody"`, `id="admin-main"`, `@media print`, `sales-report.js`.
- [ ] **Step 2: Run** `php artisan test --filter=AdminViewTest` — fails on the first missing id.
- [ ] **Step 3: Blade.** Add the **Sales Report** sidebar item after Orders; the `view-report` section after `view-history` (card styling like the Orders view; controls row with Day/Week/Month buttons, the two date inputs, Download CSV and Print buttons, all in a `no-print` wrapper; a `print-only` copy of the title above the cards); `id="admin-main"` on the content wrapper; `no-print` on the sidebar, mobile overlay, top bar, toast and the controls row; the `<script src="{{ asset('assets/js/sales-report.js') }}">` tag after `analytics.js`. In `extra-styles` add: `.print-only{display:none}` and an `@media print` block that hides `.no-print`, shows `.print-only`, sets `#admin-main{margin-left:0 !important}`, forces a white page background and dark text, and removes card shadows.
- [ ] **Step 4: `sales-report.js` and `showView`** as specified in Interfaces. Day shows the Orders card only; Week and Month show the By day card only; the What sold card always shows; with `totals.orders === 0` show `#report-empty` ("No sales in this period") and hide the three tables.
- [ ] **Step 5: Run** `php artisan test --filter=AdminViewTest` (passes); `node --check public/assets/js/sales-report.js public/assets/js/admin.js` (no output); the handler cross-check — every function named in an `onclick`/`onchange` in `admin-view.blade.php` is defined in `admin.js`, `analytics.js`, `sales-report.js` or inline in the Blade; then the whole suite.
- [ ] **Step 6: Commit** — `git commit -m "Add Sales Report page to the admin"`.

---

### Task 4: Verify on MySQL and hand over

**Files:** none (verification only; fix defects in the owning task's files with a test).

- [ ] **Step 1: Real-engine check on a scratch copy.** `mysqldump` the current `ict_tech` into the scratchpad directory (password from `backend/.env` through `MYSQL_PWD`; never print it), create database `ict_tech_report_check`, load the dump, insert orders A–E from Task 1 (each with one order item, `price` = total, qty 1, brand `B`, name `T`) with `mysql` using explicit `created_at` values. Write a throwaway PHP script in the scratchpad (outside the repo) that boots the app (`require backend/vendor/autoload.php`; `$app = require backend/bootstrap/app.php`; `$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap()`) and prints `SalesReport::for(…)->summary()` as JSON, run with `DB_DATABASE=ict_tech_report_check`. Expected: `week` `2026-11-04` → orders 3, revenue 60; `day` `2026-11-04` → 1 order, 10; `day` `2026-11-05` → 1 order, 20; `month` `2026-11` → 4 orders, revenue 100 (A–D; E is cancelled). Then drop the scratch database.
- [ ] **Step 2: Smoke on the real local DB** with `php -S 127.0.0.1:8765 -t public public/index.php` started in the background: guests get `302` on `/admin/reports/sales` and `/admin/reports/sales/export`; `/admin/login` is `200`. Stop the server by killing the process listening on port 8765.
- [ ] **Step 3: Full suite and node checks** pass; confirm local MySQL counts did not change (orders still 0).
- [ ] **Step 4: Owner checklist (browser, needs the admin login):** open Sales Report; switch Day/Week/Month and change the date; compare a week's total with the Orders page; download the CSV and open it in Excel (accents, one row per item, no customer details); Print preview shows only the report, no sidebar or buttons. After a deploy, admins hard-refresh once (the admin scripts are not cache-busted). Report that the owner must run this, and which parts were verified only by tests.
