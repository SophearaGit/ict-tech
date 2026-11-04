# Sales report for the admin (day / week / month, CSV and print) — design

Date: 2026-11-04
Status: design approved in conversation; this spec awaits the owner's review.
App: `backend/` (Laravel 13, MySQL `ict_tech`, Blade + plain JS in `public/assets/js`).
Builds on the branch `brands-and-categories` (both change the admin sidebar and
`admin.js`, so this work is stacked on it instead of branching from `main`).

## 1. Problem

The admin can see a fixed 14-day chart (`GET /admin/analytics/summary`) and a raw
list of the latest 200 orders. There is no way to ask "how much did we sell last
week / last month?", no per-week or per-month totals, no record of what sold, and
nothing to download or print for the books.

## 2. Goal

A **Sales Report** page in the admin: pick Day, Week or Month and a date; see
totals, a breakdown and what sold; download the sales record as a CSV file; print
the page. No database changes.

Success criteria:
1. For any chosen day, week or month the totals (orders, items sold, revenue,
   average order) are correct and use the same rule as the dashboard chart.
2. Day boundaries follow shop time (Phnom Penh), not UTC.
3. A "what sold" table shows product, brand, quantity and revenue.
4. The CSV has one row per item sold, opens correctly in Excel, and contains no
   customer personal information.
5. The page prints cleanly (no sidebar or buttons).
6. Only an admin can reach the report or the download.
7. The full test suite is green.

## 3. Decisions (agreed with the owner)

1. **A sale is any order that is not cancelled** (pending, paid and delivered
   count). This is the dashboard's rule, so both screens agree.
2. **Shop time** is `Asia/Phnom_Penh` (no daylight saving). The app and database
   stay on UTC. The timezone moves from a private constant in
   `AnalyticsController` to `config/shop.php` (`'timezone'`), and both read it.
3. **Week** is Monday to Sunday. **Month** is a calendar month.
4. The **server** computes the report in PHP, in one class used by both the
   screen and the CSV, so they cannot disagree. Money is summed in whole cents.
5. CSV and print only. PDF is "Print → Save as PDF". Out of scope: profit, tax,
   custom date ranges, comparison with the previous period, charts for this
   report, previous/next buttons, scheduled emails, caching.
6. Scale note: a period's orders are loaded and summed in PHP (as the dashboard
   does). This is fine for thousands of orders per month; move to SQL
   aggregation only if a month ever exceeds about 10,000 orders.

## 4. Period rules

Request values: `period` (`day`, `week` or `month`) and an optional `date`
(`Y-m-d`). If `date` is missing it is **today in shop time**. The resolved date is
returned so the page can show it.

| period | from | to (inclusive) |
|---|---|---|
| day | the date | the date |
| week | the Monday of the date's week | the following Sunday |
| month | the 1st of the date's month | the last day of that month |

Orders are selected with `created_at >= from 00:00 (shop time) converted to UTC`
and `created_at < (to + 1 day) 00:00 (shop time) converted to UTC`, and
`status != 'cancelled'`. An order is counted on the day it was **placed**, in shop
time (an order at 00:30 Phnom Penh on the 5th belongs to the 5th even though it is
the 4th in UTC). Future dates are allowed and give zeros.

Validation: `period` required and one of the three; `date` optional, `Y-m-d`, a
real date, between `2000-01-01` and `2100-12-31`. A failure returns `422` JSON
with Laravel's default shape (`message`, `errors`).

## 5. Report data — `GET /admin/reports/sales?period=…&date=…` (admin)

`200` JSON:
```
{
  period: "week", date: "2026-11-04", from: "2026-11-02", to: "2026-11-08",
  totals:  { orders, items, revenue, averageOrder },
  days:    [ { date: "2026-11-02", orders, items, revenue } … one per day of the period ],
  orders:  [ { number, date: "Y-m-d", time: "H:i", status, payment_method, items, total } … ],
  products:[ { name, brand, quantity, revenue } … ]
}
```
- `items` = sum of item quantities (in `totals`, in each `days` entry and in each
  `orders` entry); `revenue` = sum of order `total`;
  `averageOrder` = revenue / orders rounded to cents, `0` when there are no
  orders. All money is `float` converted from whole cents.
- `days` has one entry for every day of the period, including days with no sales
  (zeros). A `day` period has one entry.
- `orders` is filled **only when `period` is `day`** (otherwise `[]`), oldest
  first (by `created_at`, then id); `date`/`time` are in shop time.
- `products` groups the order items by the exact (`name`, `brand`) text snapshot
  stored on the item (so renamed or deleted products still report correctly);
  `revenue` = sum of `unit_price × quantity` in cents. Sorted by revenue, then
  quantity, then name (descending, descending, ascending).

## 6. CSV — `GET /admin/reports/sales/export?period=…&date=…` (admin)

Same validation. `200` with `Content-Type: text/csv; charset=UTF-8` and
`Content-Disposition: attachment; filename="sales-<period>-<from>-to-<to>.csv"`
(for a day, `<from>` and `<to>` are the same date).

- Body starts with a UTF-8 byte-order mark so Excel reads accents and Khmer.
- Header row: `Date,Time,Order,Status,Payment,Product,Brand,Quantity,Unit price,Line total`.
- One row per order item, ordered by the order's `created_at`, then order id,
  then item id. `Date` and `Time` are shop time; `Payment` is the stored code
  (`aba`/`cod`); money has two decimals with a dot and no thousands separator.
- No customer name, email, phone or address, and no order total column.
- Text cells (`Order`, `Status`, `Payment`, `Product`, `Brand`) that start with
  `=`, `+`, `-`, `@`, a tab or a carriage return are prefixed with `'` so
  spreadsheets cannot run them as formulas. Quoting follows RFC 4180
  (`fputcsv`), so commas, quotes and line breaks inside names are safe.
- A period with no sales returns the BOM and the header row only.

## 7. Server structure

- `App\Reports\SalesReport` — resolves the period (section 4), loads the orders
  once, and produces the JSON array (`summary()`) and the CSV rows. One purpose:
  turn (period, date) into report data. No HTTP in it.
- `App\Http\Controllers\Admin\SalesReportController` — `show(Request)` and
  `export(Request)`; validates, calls `SalesReport`, returns JSON or a streamed
  download.
- Routes inside the existing `admin` middleware group: `admin-reports.sales` and
  `admin-reports.sales.export`.
- `config/shop.php`: add `'timezone' => 'Asia/Phnom_Penh'`; `AnalyticsController`
  reads it instead of its private constant (behaviour unchanged).

## 8. Admin UI

- Sidebar item **Sales Report** (after Orders) and a `view-report` section;
  `showView` and its titles get `report`. Opening the view loads the report for
  the selected period (default Week, today).
- Controls: Day / Week / Month buttons; one date input (`type="date"` for Day and
  Week, `type="month"` for Month — a month sends the 1st); **Download CSV** and
  **Print** buttons.
- Content: four cards (Orders, Items sold, Revenue, Average order); a **By day**
  table (Week and Month only); an **Orders** table (Day only: number, time,
  payment, status badge, items, total); a **What sold** table (product, brand,
  quantity, revenue). An empty period shows zeros and "No sales in this period".
- The heading shows the period in words (for example "Week 2 Nov – 8 Nov 2026")
  and is the printed title.
- New file `public/assets/js/sales-report.js`, loaded after `analytics.js`
  (following that file's pattern); it reuses `apiFetch`, `escapeHtml`,
  `ORDER_STATUS_BADGE` and `ORDER_PAYMENT_LABEL` from `admin.js`. All values are
  escaped before they go into `innerHTML`.
- **Print:** `@media print` rules hide the sidebar, top bar, mobile overlay and the
  report controls and show only the report; tables are not clipped.
- Download CSV navigates to the export URL with the current period and date
  (the browser sends the admin session cookie).

## 9. Testing

All on in-memory SQLite; tests first (TDD). Orders are made with the existing
`placeOrder` helper and then given an explicit `created_at`.

- `SalesReportTest` (JSON): day, week and month totals; shop-time boundaries (an
  order at 23:30 and one at 00:30 Phnom Penh around midnight, including one that is
  the previous day in UTC); Monday vs Sunday; first and last day of a month;
  cancelled excluded and pending included; exact cents (for example 3 × 0.10 and
  0.20 summed); average rounded to cents and zero when empty; `days` has every day
  with zeros for quiet days; `orders` only for `day`, oldest first; `products`
  grouped by name + brand, summed, sorted, and still present after the product was
  deleted; default `date` is today in shop time; invalid `period`, bad or
  impossible `date`, and out-of-range `date` give 422; guests and customers are
  redirected to admin login.
- `SalesReportCsvTest`: headers and filename; BOM; header row; one row per item in
  the right order and columns; no customer data; formula-prefix neutralised;
  commas, quotes and line breaks in names quoted correctly; empty period gives
  header only; cancelled excluded; same validation and guest redirect.
- `AdminViewTest`: the page contains `nav-report`, `view-report` and the control
  ids. `AdminAnalyticsTest` must still pass after the timezone moves to config.
- `node --check` on `sales-report.js`; a handler-name cross-check as before.

## 10. Rollout

- No migrations and no new dependencies.
- The admin JavaScript has no automated tests; the owner clicks through once:
  switch Day/Week/Month and change the date, compare a week's total with the
  Orders page, download the CSV and open it in Excel, and use Print preview.
- After deploy admins should hard-refresh once (the admin scripts are not
  cache-busted).
