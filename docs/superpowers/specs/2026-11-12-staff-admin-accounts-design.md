# Staff admin accounts — design

Date: 2026-11-12
Status: design approved in conversation (owner chose option A, "staff can use everything except the
Admins page"); this spec awaits the owner's review.
App: `backend/` (Laravel 13, MySQL `ict_tech`, Blade + plain JS in `public/assets/js`).
Follows the admin-page pattern of the Brands and Categories pages (`2026-11-06-…`, `2026-11-08-…`).

## 1. Problem

There is only one kind of admin. Anyone who can sign in can do everything, and the only way to add an
admin is a programmer editing the database. The owner wants to give staff their own email and
password so staff can work on products and stock, without sharing the owner's login. The sidebar
also says "Super Admin" for everybody, typed by hand.

## 2. Goal

Two roles, **owner** and **staff**. The owner creates staff accounts from a new **Admins** page, and
can switch them ON/OFF and reset their password. Every admin can change their own password.

Success criteria:
1. Every admin that exists today becomes an owner, with the same email and the same password.
2. The owner can add a staff admin (name, email, password of at least 8 characters) and that person
   can sign in at `/admin/login` straight away.
3. Staff can use everything the admin has today **except** the Admins page: products, stock,
   brands, categories, orders, messages, dashboard, sales report.
4. Staff cannot open the Admins page or call its addresses. The server refuses (not only the menu hiding it).
5. The owner can switch a staff admin OFF and ON again. OFF takes effect on that person's next click
   and they cannot sign in until ON again.
6. The owner can set a new password for a staff admin; the old one stops working.
7. Any admin can change their own password by giving the current one.
8. No admin can be deleted from the app, and an owner account cannot be changed from the Admins page.
9. The sidebar shows the real role ("Owner" or "Staff").
10. The full test suite is green.

## 3. Decisions (agreed with the owner)

1. **Two roles only** (owner, staff). No finer permissions now.
2. **Staff see everything except Admins** (option A). Hiding the sales money from staff was offered
   as option B and not chosen; it can be added later.
3. **Only staff are created in the app.** New accounts are always staff. Nobody can create or promote
   an owner from the page; owners are the existing admins. (Simpler, and it means no admin can lock
   another owner out.)
4. **Owners cannot be edited on the Admins page**: no switch, no password reset. This also covers
   "you cannot switch yourself off" and "you cannot switch off the last owner", because owners can
   never be switched off there. An owner changes their own password with "My password".
5. **No delete.** Switch OFF instead, so history and the last-sign-in date are kept.
6. **The owner types the password** for a new staff member and tells them (a Show/Hide button lets
   the owner read what they typed). Staff then change it themselves with "My password".
7. **Production logins are never touched.** The migration only adds a column; it never reads or
   changes a password.

## 4. Design

### 4.1 Data

One reversible migration `2026_11_12_100000_add_role_to_admins_table`:
- adds `admins.role` string(10), NOT NULL, default `staff`;
- then sets `role = 'owner'` for every row already in `admins` (so today's admins keep full access);
- `down()` drops the column.

`Admin` model: constants `ROLE_OWNER = 'owner'`, `ROLE_STAFF = 'staff'`, method `isOwner(): bool`.
`role` is **not** in `#[Fillable]` (a request can never set it); the controller assigns it
explicitly. `AdminSeeder` sets `role` to owner for the account it creates (local use only; it is
never run on the live site).

### 4.2 Permission layer

- New middleware `EnsureAdminIsOwner`, alias `owner`, used only on the three Admins routes. A
  non-owner gets `403`. It runs inside the existing `admin` group, so a signed-out or switched-off
  admin is still redirected to the login page first.
- The existing `admin` middleware already refuses a switched-off admin on every request, and the
  login already refuses `is_active = false`; nothing changes there.

### 4.3 Admin API (inside the `admin` group)

| Method and path | Who | Body | Result |
|---|---|---|---|
| `GET /admin/admins` | owner | — | list, oldest first; each `{id, name, email, role, isActive, lastLogin, isYou}` (`lastLogin` is text in shop time, or `null`) |
| `POST /admin/admins` | owner | `name`, `email`, `password` | `201` the new admin, role staff, ON |
| `PUT /admin/admins/{admin}` | owner | `is_active` (boolean) and/or `password` (at least one) | `200` the admin; `403 {message}` when the target is an owner |
| `PUT /admin/account/password` | any admin | `current_password`, `password`, `password_confirmation` | `200 {ok:true}` |

Rules:
- **name**: required, at most 60 characters, trimmed.
- **email**: required, a valid email, at most 255 characters, stored trimmed and in lower case,
  unique among admins ignoring case (checked with `lower(email)` so it behaves the same on MySQL
  and on the SQLite test database).
- **password** (create, reset, change): 8 to 72 characters (72 is the most the hashing accepts).
  Always stored hashed (the model already hashes it).
- **my password**: `current_password` must match; the new one must differ from the current one and
  match `password_confirmation`. A wrong current password gives `422` with a message on
  `current_password`.
- Validation errors keep Laravel's `422 {message, errors}` shape.

### 4.4 Admin UI

- **Sidebar:** an "Admins" item after Categories, **rendered only for owners**. The label under
  the name shows "Owner" or "Staff" instead of "Super Admin". A "My password" button sits next to
  the log-out button.
- **Admins page** (`#view-admins`, rendered only for owners; `showView` must cope when it is absent):
  - Add form: Name, Email, Password (with Show/Hide), Add Staff.
  - Table: Name, Email, Role (badge), Last sign-in ("Never" when empty), Status (ON/OFF switch with
    the words, like Brands; owners show "Owner" and no switch), Actions (Reset password, staff only).
  - A switch shows a toast, for example "Dara is OFF and can no longer sign in." / "Dara is ON again."
- **My password box:** a small window like the Restock one: current password, new password, repeat
  new password, Save.
- New file `public/assets/js/admins.js` holds both (the Admins page and the My password box); it
  uses `apiFetch`, `toast` and `toastError` from `admin.js`. Every value from the server goes in as
  text, never as HTML. New styling follows `admin-dark.css` at WCAG AA contrast, light and dark,
  and fits a phone.

## 5. Out of scope

- Deleting admins, creating or promoting owners from the app, finer permissions, hiding sales money
  from staff, forgotten-password emails for admins, two-step login, an activity log.
- **A password change or reset does not log out a session that is already open elsewhere.** To cut
  someone off at once, switch them OFF.
- **Noticed, not part of this work:** the admin login has no limit on wrong attempts (the customer
  checkout has one). Recommended as a small follow-up now that more people can hold a login.

## 6. Testing

New `AdminAccountsTest` plus additions to existing tests:
1. Migration: with an admin row in place, run the migration's own `down()` then `up()`; the row is an
   owner and its password hash is byte-for-byte the same.
2. Staff get `403` on all three Admins addresses; an owner gets `200`/`201`; a signed-out visitor is
   redirected to the login page.
3. Create: role is always staff even when the request sends `role`; duplicate email in another
   letter case is refused; a short or too long password is refused; the stored password is hashed;
   the new staff member can sign in and open products, orders, brands, categories, messages,
   analytics and the sales report, and still gets `403` on Admins.
4. Switch OFF: the staff member's next request goes to the login page and a fresh login fails; ON
   restores access. Reset: the old password fails and the new one works. An owner target gives
   `403`; an unknown id gives `404`.
5. My password: wrong current, too short, same as current, and mismatched repeat are all refused;
   success makes the old password fail and the new one work, for both an owner and a staff member.
6. Page: an owner's admin page has the Admins item, the Admins view and the label "Owner"; a staff
   member's page has neither the item nor the view and says "Staff".
7. The existing legacy-admin copy test and the admin login test stay green.
8. Browser check (Chrome, light, dark, phone width) of the Admins page and My password box.

## 7. Deploying

One more migration (`2026_11_12_100000`), so the live update runs three migrations instead of two
(brands, categories, roles). Back up the database first, as before. Afterwards every current admin is
an owner; sign in once to check, then add a staff member. Never run `db:seed`.
