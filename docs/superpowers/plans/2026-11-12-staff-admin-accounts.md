# Staff Admin Accounts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an owner role and a staff role for admins: the owner adds staff from an Admins page, switches them ON/OFF and resets their password, and every admin can change their own password.

**Architecture:** One new `admins.role` column (`owner` | `staff`, default `staff`; every existing admin is promoted to owner by the migration). A small `owner` middleware guards three owner-only JSON routes inside the existing `admin` group; one extra route lets any admin change their own password. The UI is a new owner-only Admins page and a "My password" window, both in a new plain-JS file `admins.js`, built like the Categories page.

**Tech Stack:** Laravel 13, PHP 8.5, MySQL (SQLite in tests), Blade, plain JS in `public/assets/js`, Tailwind CDN.

**Spec:** `docs/superpowers/specs/2026-11-12-staff-admin-accounts-design.md` (read it first; this plan only decides what the spec leaves open).

## Global Constraints

- Work on branch `backend`. **Commit after every task, adding only that task's files** (never `git add .`). Do not touch or commit `backend/resources/js/app.js`, `backend/resources/views/home.blade.php` or `backend-old-skeleton/`. Do not push; the owner decides that.
- Test-first for every behaviour: watch the new test fail for the right reason, then implement. Run tests from `backend/`; `php artisan test` prints a JSON result in this environment (failures are under `failures`, errors under `error_details`).
- **Never run `php artisan db:seed`. Never read or change an email or password on any database except the throwaway rows you create.** The migration adds a column and sets `role`; it never touches `email` or `password`. The local test login (`vechakasysothonyeong@gmail.com` / `Local-Admin-2026`) is for the local database only.
- Roles: `Admin::ROLE_OWNER = 'owner'`, `Admin::ROLE_STAFF = 'staff'`. `role` is **not** mass-assignable; it is only ever set with `forceFill` (or by the migration default).
- Admin JSON everywhere: `{id, name, email, role, isActive, lastLogin, isYou}`; `lastLogin` is `last_login_at` formatted `j M Y, H:i` in `config('shop.timezone')`, or `null`.
- Rules: name required, at most 60 characters; email required, valid, at most 255, stored lower-case, unique ignoring case (`lower(email)`); password 8 to 72 characters. Errors stay `422 {message, errors}`.
- Exact messages: not an owner `403` `Only an owner can manage admins.`; target is an owner `403` `Owner accounts can't be changed here.`; duplicate email `That email is already used by another admin.`; wrong current password `Your current password is not correct.`; new equals current `The new password must be different from the current one.`. Toasts: `Staff admin added.`, `{name} is OFF and can no longer sign in.`, `{name} is ON again.`, `Password changed for {name}.`, `Your password was changed.`
- Every migration reversible; no new composer/npm packages, `.env` settings or build steps. New UI works in light and dark mode at WCAG AA contrast (4.5:1 normal text) and on a phone.
- Escape every name and email that reaches HTML or JS (`{{ }}` in Blade, `escapeHtml`/`escapeAttr` in JS; `toast()` writes HTML, so escape what goes into it).
- Browser checks: run `php artisan serve` on a spare port (for example 8765), drive it with the puppeteer-core + Chrome approach used in earlier sessions (throwaway scripts in the scratchpad, outside the repo), kill that listener by port afterwards, and never touch the owner's server on port 8000. Throwaway admins use emails `zz-*@example.test` and are deleted at the end (mysql CLI, password in the `MYSQL_PWD` environment variable).
- Commit messages end with `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.

## Review Focus

Inputs the spec implies that no obvious test would cover; each is pinned by a test or check in the named task.

1. A staff member who is already signed in is switched OFF: their next page request goes to the login page, and a fetch from the open admin page is treated as an expired session (not a blank page or a crash). (Task 2; Task 4 browser check)
2. An email typed with capitals or spaces (`  Dara@Example.TEST `) is stored lower-case and trimmed, and is refused as a duplicate of `dara@example.test`. (Task 2)
3. Forged requests: a staff member calling the Admins addresses directly; a create request that sends `role=owner`; an owner (including themselves) targeted by the update address. None changes anything. (Task 2)
4. A name carrying markup (`<i>Zed</i>`) is shown as plain text on the Admins page, in the sidebar and in toasts, never executed. (Task 4)
5. Password edges: exactly 8 and exactly 72 characters are accepted, 7 and 73 refused; a password in Khmer letters can be set and used to sign in; after changing their own password the admin stays signed in. (Tasks 2, 3)

---

### Task 1: The role column, the model and the test helpers

**Files:**
- Create: `backend/database/migrations/2026_11_12_100000_add_role_to_admins_table.php`, `backend/tests/Feature/AdminRoleMigrationTest.php`
- Modify: `backend/app/Models/Admin.php`, `backend/database/seeders/AdminSeeder.php`, `backend/tests/Concerns/MakesShopData.php`

**Interfaces:**
- Produces: `admins.role` string(10) NOT NULL default `staff`; `Admin::ROLE_OWNER`, `Admin::ROLE_STAFF`, `Admin::isOwner(): bool`; test helpers `makeAdmin(): Admin` (now an **owner**, same email and password as before) and `makeStaff(string $email = 'staff@example.test'): Admin` (role staff, password `S3cret-pass!`, both `->fresh()`).

- [ ] **Step 1: Write the failing tests** in `AdminRoleMigrationTest` (use `RefreshDatabase`; load the migration with `require` like `CopyLegacyAdminsMigrationTest` does): a new `Admin` created without a role is `staff` and `isOwner()` is false; `(new Admin(['name' => 'x', 'role' => 'owner']))->role` is null (not mass-assignable); with two admin rows inserted after the migration's own `down()`, running `up()` makes both `owner` and leaves each `password` hash byte-for-byte equal, and an admin inserted after `up()` is `staff`; `AdminSeeder` creates an owner.
- [ ] **Step 2: Run** `php artisan test tests/Feature/AdminRoleMigrationTest.php`. Expected: FAIL (no `role` column or constants).
- [ ] **Step 3: Implement** the migration (add the column, then `UPDATE admins SET role = 'owner'`; `down()` drops it), the model constants and `isOwner()`, the seeder's explicit owner role (`updateOrCreate` silently drops `role` because it is not mass-assignable, so set it with `forceFill(...)->save()` afterwards), and the two helpers (`makeAdmin` promotes with `forceFill(['role' => Admin::ROLE_OWNER])->save()` before `fresh()`).
- [ ] **Step 4: Run** the new file and the whole suite. Expected: green (every existing admin test now runs as an owner).
- [ ] **Step 5: Migrate the local dev database once** (`php artisan migrate`, from `backend/`), because the owner's server on port 8000 serves this working tree live. Check with the mysql CLI that the local admin row is `owner` and its password hash did not change (compare `MD5(password)` before and after).
- [ ] **Step 6: Commit**; message `Add the role column to admins`.

### Task 2: The owner-only middleware and the Admins API

**Files:**
- Create: `backend/app/Http/Middleware/EnsureAdminIsOwner.php`, `backend/app/Http/Controllers/Admin/AdminAccountController.php`, `backend/tests/Feature/AdminAccountsTest.php`
- Modify: `backend/bootstrap/app.php` (alias `owner`), `backend/routes/web.php` (a nested `owner` group inside the `admin` group)

**Interfaces:**
- Consumes: `Admin::isOwner()`, `makeAdmin()`, `makeStaff()` (Task 1).
- Produces: routes `admin-admins.index|store|update` (`GET/POST /admin/admins`, `PUT /admin/admins/{admin}`); `AdminAccountController::index|store|update` and a private `transform(Admin $admin, Request $request): array` producing the admin JSON; `store` returns `201` and always creates a staff admin (ON, `forceFill` role); `update` accepts `is_active` (boolean) and/or `password` (at least one, else `422`), refuses an owner target with `403` **before** validating, and never changes `name`, `email` or `role`.

- [ ] **Step 1: Write the failing tests** in `AdminAccountsTest` (helper `actingAsOwner()` / `actingAsStaff()` using one admin per test): guest is redirected to `admin-login` on all three addresses; staff get `403` with the exact message on all three; owner `index` lists oldest first with the exact JSON (`isYou` true only for the caller, `lastLogin` `null` or e.g. `12 Nov 2026, 10:30` for `2026-11-12 03:30:00` UTC); `store` returns `201`, role staff, ON, password stored hashed (`Hash::check`), email lower-cased and trimmed (`'  Dara@Example.TEST '`), a request that also sends `role=owner` still makes a staff admin, a duplicate in another letter case is `422` with the exact message on `email`, bad email / empty name / 61-character name / 7- and 73-character passwords are `422`, 8 and 72 characters are accepted, a Khmer password of 8 letters is accepted and can sign in; the new staff member signs in through `admin-login.attempt`, then `GET` on the admin view, products, orders, brands, categories, messages, analytics and the sales report all answer `200` while the three Admins addresses answer `403`; `update`: switching OFF makes that staff member's next request (acting as the same admin, fetched fresh) redirect to `admin-login` and a fresh sign-in fail, switching ON restores both, a password reset makes the old password fail and the new one work, both fields in one request work, `PUT []` is `422`, `is_active` `'maybe'` is `422`, a 7-character password is `422`, an owner target (another owner, and the caller themselves) is `403` with the exact message and changes nothing, an unknown id is `404`; there is no delete: `DELETE /admin/admins/{id}` answers `405` and the row is still there.
- [ ] **Step 2: Run** `php artisan test tests/Feature/AdminAccountsTest.php`. Expected: FAIL (routes missing).
- [ ] **Step 3: Implement** the middleware (`403` with `Only an owner can manage admins.`; the alias goes in `bootstrap/app.php` next to `admin`), the controller (email uniqueness with a `lower(email)` check, as the brand and category name rules do) and the routes.
- [ ] **Step 4: Run** the new file and the whole suite. Expected: green.
- [ ] **Step 5: Commit**; message `Add the owner-only Admins API`.

### Task 3: Every admin can change their own password

**Files:**
- Create: `backend/app/Http/Controllers/Admin/MyPasswordController.php`, `backend/tests/Feature/AdminMyPasswordTest.php`
- Modify: `backend/routes/web.php` (inside the `admin` group, outside the `owner` group)

**Interfaces:**
- Consumes: `makeAdmin()`, `makeStaff()` (Task 1).
- Produces: route `admin-account.password` (`PUT /admin/account/password`); `MyPasswordController::update(Request $request): JsonResponse` returning `{ok: true}`. Body `current_password`, `password`, `password_confirmation`; the new password is 8 to 72 characters, confirmed, and different from the current one (messages in Global Constraints).

- [ ] **Step 1: Write the failing tests** (run each for an owner and a staff member): wrong current password `422` with the exact message on `current_password`; 7 and 73 characters `422`; a repeat that does not match `422`; the same password as the current one `422` with the exact message; success returns `{ok: true}`, the old password no longer matches (`Hash::check`) and the new one does, the admin is **still signed in** on the next request, and after signing out the new password signs in through `admin-login.attempt` while the old one fails; a guest is redirected to `admin-login`; a switched-off admin is redirected.
- [ ] **Step 2: Run** `php artisan test tests/Feature/AdminMyPasswordTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement** the controller (the `current_password:admin` rule with the custom message; save through the model's hashed cast) and the route.
- [ ] **Step 4: Run** the new file and the whole suite. Expected: green.
- [ ] **Step 5: Commit**; message `Let every admin change their own password`.

### Task 4: The Admins page and the real role in the sidebar

**Files:**
- Create: `backend/public/assets/js/admins.js`
- Modify: `backend/resources/views/admin-view.blade.php` (role label, owner-only sidebar item `nav-admins` and `#view-admins`, the script tag after `categories.js`), `backend/public/assets/js/admin.js` (`showView`: add `admins` to the list and to `titles`, skip a view element that does not exist, call `initAdmins()` like `initCategories()`), `backend/tests/Feature/AdminViewTest.php`

**Interfaces:**
- Consumes: `/admin/admins` (Task 2); from `admin.js`: `apiFetch`, `escapeHtml`, `escapeAttr`, `toast`, `toastError`; the `brand-switch` classes for the On/Off switch.
- Produces (JS, `admins.js`): state `admins`; `loadAdmins()`, `initAdmins()` (called by `showView('admins')`; shows what it has, then reloads), `renderAdminsTable()`, `addAdmin()`, `toggleAdmin(id)`, `startResetPassword(id)`, `saveResetPassword(id)`, `cancelResetPassword()`, `toggleAddAdminPassword()` (Show/Hide). Element ids: `nav-admins`, `view-admins`, `admin-add-name`, `admin-add-email`, `admin-add-password`, `admin-add-password-toggle`, `admins-tbody`, `admin-reset-password`. Table columns in order: Name, Email, Role, Last sign-in (`Never` when `null`), Status, Actions. Owners show a "Owner" badge, no switch and no action; staff get the switch and **Reset password**.
- In Blade the sidebar label is `ucfirst(role)` ("Owner" or "Staff") instead of "Super Admin", and the Admins item and view are rendered only when `isOwner()`.

- [ ] **Step 1: Write the failing tests** in `AdminViewTest`: an owner's page has `id="nav-admins"`, `showView('admins')`, `id="view-admins"`, the ids above, in order the headings `>Name</th>`, `>Email</th>`, `>Role</th>`, `>Last sign-in</th>`, `>Status</th>`, `>Actions</th>`, `assets/js/admins.js`, the text `Owner`, and no `Super Admin`; a staff member's page has `Staff`, still has `id="nav-brands"`, and has neither `id="nav-admins"` nor `id="view-admins"`; a staff member named `<i>Zed</i>` appears escaped (`&lt;i&gt;Zed&lt;/i&gt;`) and never as `<i>Zed</i>` in the sidebar.
- [ ] **Step 2: Run** `php artisan test tests/Feature/AdminViewTest.php`. Expected: the new assertions FAIL.
- [ ] **Step 3: Implement** the markup, `admins.js` and the `showView` change as in Interfaces; the Password box has a Show/Hide button (hidden by default); the page works in dark mode (follow `admin-dark.css` and the Categories page classes).
- [ ] **Step 4: Run** `AdminViewTest` and the whole suite. Expected: green.
- [ ] **Step 5: Verify in Chrome** as the local owner: add a staff admin (also one named `<i>Zed</i>`, shown as plain text in the table, sidebar and toasts); a duplicate email (other letter case) shows the exact message; the new staff row shows `Never`, then a date after that staff member signs in; switch OFF and ON with the exact toasts; reset a password; the owner row has no switch. Then sign in as the staff member (separate browser context): no Admins item, label "Staff", every other page loads, **no script errors**; with the owner switching that staff member OFF while the staff page is open, the staff member's next click on a list shows the "session expired / log in again" message, not a blank page. Light mode, dark mode, phone width, and a contrast scan of `#view-admins` (no text under 4.5:1). Delete every `zz-*` admin. Fix anything found.
- [ ] **Step 6: Commit**; message `Add the Admins page for owners`.

### Task 5: The My password window, then check the whole feature

**Files:**
- Modify: `backend/resources/views/admin-view.blade.php` (a "My password" button beside the log-out button, the `#mypw-modal` window built like `#restock-modal`), `backend/public/assets/js/admins.js`, `backend/tests/Feature/AdminViewTest.php`

**Interfaces:**
- Consumes: `PUT /admin/account/password` (Task 3).
- Produces (JS): `openMyPassword()`, `closeMyPassword()`, `saveMyPassword()`. Element ids: `mypw-btn`, `mypw-modal`, `mypw-current`, `mypw-new`, `mypw-repeat`, `mypw-error` (the server's message is shown here as plain text; success closes the window and shows the toast `Your password was changed.`).

- [ ] **Step 1: Write the failing tests** in `AdminViewTest`: both an owner's and a staff member's page contain `id="mypw-btn"`, `id="mypw-modal"`, `id="mypw-current"`, `id="mypw-new"`, `id="mypw-repeat"`, `id="mypw-error"` and `assets/js/admins.js`.
- [ ] **Step 2: Run** `php artisan test tests/Feature/AdminViewTest.php`. Expected: the new assertions FAIL.
- [ ] **Step 3: Implement** the button, the window and the three functions (clear the three fields and the error each time the window opens; Enter in the last field saves).
- [ ] **Step 4: Run** the whole suite. Expected: green.
- [ ] **Step 5: Verify the whole feature in Chrome** end to end: owner adds a staff admin; staff signs in, opens My password, a wrong current password shows the exact message, a mismatched repeat and a short password show their messages, a correct change shows the toast and the staff member stays signed in; staff signs out and signs in with the new password; the old password fails; the owner resets that staff member's password and the staff member signs in with it; the owner switches them OFF and the staff member cannot sign in. Light, dark and phone width, no script errors, contrast scan of the window. Delete every `zz-*` admin; confirm the local owner's login still works and its password hash is unchanged.
- [ ] **Step 6: Commit**; message `Add the My password window for every admin`.
