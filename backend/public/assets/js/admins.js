/* ── Admins (owner only): the Admins page — add staff, switch them ON/OFF, reset their password ──
   Loaded after admin.js. Uses from admin.js: apiFetch, escapeHtml, escapeAttr, toast, toastError.
   The page itself is only sent to owners, and the server refuses everybody else on these addresses too.
   Every name and email comes from the server and goes into the page as text, never as markup. */

let admins = [];                 // [{id, name, email, role, isActive, lastLogin, isYou}] from /admin/admins, oldest first
let resettingAdminId = null;     // the staff row that is showing the "new password" box

function loadAdmins() {
  return apiFetch('/admin/admins')
    .then(data => { admins = data; renderAdminsTable(); })
    .catch(e => toastError(e, 'Failed to load admins.'));
}

// Called by showView('admins'): show what we have, then reload so last sign-in times are current.
function initAdmins() {
  resettingAdminId = null;
  renderAdminsTable();
  loadAdmins();
}

// Show/Hide for a password box. The owner types a password for somebody else, so being able to read it matters.
function togglePasswordField(inputId, button) {
  const input = document.getElementById(inputId);
  if (!input) return;
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  button.textContent = show ? 'Hide' : 'Show';
  button.setAttribute('aria-pressed', show ? 'true' : 'false');
}

function toggleAddAdminPassword() {
  togglePasswordField('admin-add-password', document.getElementById('admin-add-password-toggle'));
}

// The switch carries the words On/Off as well as its position, so it never relies on colour alone.
// It reuses the Brands page's switch styles (.brand-switch).
function adminSwitchHtml(a) {
  const on = a.isActive;
  return `<button type="button" role="switch" aria-checked="${on}" aria-label="${escapeAttr(a.name)} can sign in" onclick="toggleAdmin(${a.id})"
      class="brand-switch ${on ? 'is-on' : 'is-off'} inline-flex items-center gap-2 rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
      <span class="brand-switch-track"><span class="brand-switch-knob"></span></span>
      <span class="text-xs font-semibold text-gray-700 dark:text-white">${on ? 'On' : 'Off'}</span>
    </button>`;
}

function renderAdminsTable() {
  const tbody = document.getElementById('admins-tbody');
  if (!tbody) return; // staff are never sent the Admins page

  const btn = 'px-2.5 py-1 rounded-lg text-xs font-semibold transition-colors';
  const soft = `${btn} bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-neutral-600 dark:hover:bg-neutral-500 dark:text-white`;
  const inputClass = 'form-inp bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5 text-sm';
  tbody.innerHTML = admins.map(a => {
    const owner = a.role === 'owner';
    const resetting = a.id === resettingAdminId;
    const you = a.isYou ? ' <span class="ml-1 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 dark:bg-neutral-600 dark:text-white">You</span>' : '';
    const off = !a.isActive ? ' <span class="ml-1 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 dark:bg-neutral-600 dark:text-white">Off</span>' : '';
    const roleBadge = owner
      ? '<span class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full bg-indigo-100 text-indigo-800 dark:bg-indigo-500/30 dark:text-white">Owner</span>'
      : '<span class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full bg-gray-100 text-gray-700 dark:bg-neutral-600 dark:text-white">Staff</span>';

    // An owner can never be switched off or reset here, so their row has no switch and no actions.
    const status = owner ? '<span class="text-xs text-gray-600 dark:text-gray-300">Always on</span>' : adminSwitchHtml(a);
    let actions = '';
    if (!owner && resetting) {
      actions = `<div class="flex flex-wrap items-center gap-2">
          <input id="admin-reset-password" type="password" maxlength="72" placeholder="New password" aria-label="New password for ${escapeAttr(a.name)}" autocomplete="new-password"
            onkeydown="if (event.key === 'Enter') saveResetPassword(${a.id}); if (event.key === 'Escape') cancelResetPassword()"
            class="${inputClass} w-44"/>
          <button type="button" onclick="togglePasswordField('admin-reset-password', this)" aria-pressed="false" class="${soft}">Show</button>
          <button onclick="saveResetPassword(${a.id})" class="${btn} bg-indigo-600 hover:bg-indigo-700 text-white">Save</button>
          <button onclick="cancelResetPassword()" class="${soft}">Cancel</button>
        </div>`;
    } else if (!owner) {
      actions = `<button onclick="startResetPassword(${a.id})" class="${soft}">Reset password</button>`;
    }

    // An OFF admin reads as muted text (not a faded row: fading would drop the contrast below AA).
    const nameClass = a.isActive ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-300';
    return `
      <tr class="border-t border-gray-100 dark:border-neutral-600">
        <td class="px-5 py-3.5 text-sm font-medium whitespace-nowrap ${nameClass}">${escapeHtml(a.name)}${you}${off}</td>
        <td class="px-3 py-3.5 text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap">${escapeHtml(a.email)}</td>
        <td class="px-3 py-3.5">${roleBadge}</td>
        <td class="px-3 py-3.5 text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap">${a.lastLogin ? escapeHtml(a.lastLogin) : 'Never'}</td>
        <td class="px-3 py-3.5">${status}</td>
        <td class="px-3 py-3.5">${actions}</td>
      </tr>`;
  }).join('');

  if (resettingAdminId !== null) {
    const input = document.getElementById('admin-reset-password');
    if (input) input.focus();
  }
}

function addAdmin() {
  const nameInput = document.getElementById('admin-add-name');
  const emailInput = document.getElementById('admin-add-email');
  const passwordInput = document.getElementById('admin-add-password');
  const name = nameInput.value.trim();
  const email = emailInput.value.trim();
  const password = passwordInput.value;
  if (!name) { toast('Type the staff member’s name.'); return; }
  if (!email) { toast('Type their email.'); return; }
  if (!password) { toast('Type a password for them.'); return; }

  apiFetch('/admin/admins', { method: 'POST', body: JSON.stringify({ name, email, password }) })
    .then(created => {
      admins.push(created);
      nameInput.value = '';
      emailInput.value = '';
      passwordInput.value = '';
      passwordInput.type = 'password'; // never leave a typed password showing
      const toggle = document.getElementById('admin-add-password-toggle');
      toggle.textContent = 'Show';
      toggle.setAttribute('aria-pressed', 'false');
      renderAdminsTable();
      toast('Staff admin added.');
    })
    .catch(e => toastError(e, 'Failed to add the admin.'));
}

function toggleAdmin(id) {
  const a = admins.find(x => x.id === id);
  if (!a) return;

  apiFetch(`/admin/admins/${id}`, { method: 'PUT', body: JSON.stringify({ is_active: !a.isActive }) })
    .then(updated => {
      Object.assign(a, updated);
      renderAdminsTable();
      toast(updated.isActive
        ? `${escapeHtml(updated.name)} is ON again.`
        : `${escapeHtml(updated.name)} is OFF and can no longer sign in.`);
    })
    .catch(e => toastError(e, 'Failed to change the admin.'));
}

function startResetPassword(id) {
  resettingAdminId = id;
  renderAdminsTable();
}

function cancelResetPassword() {
  resettingAdminId = null;
  renderAdminsTable();
}

function saveResetPassword(id) {
  const password = document.getElementById('admin-reset-password').value;
  if (!password) { toast('Type the new password.'); return; }

  apiFetch(`/admin/admins/${id}`, { method: 'PUT', body: JSON.stringify({ password }) })
    .then(updated => {
      const a = admins.find(x => x.id === id);
      if (a) Object.assign(a, updated);
      resettingAdminId = null;
      renderAdminsTable();
      toast(`Password changed for ${escapeHtml(updated.name)}.`);
    })
    .catch(e => toastError(e, 'Failed to change the password.'));
}

/* ── My password (every admin, owner or staff) ──
   The server checks the current password. A session that is already open on another device stays open;
   to cut someone off, the owner switches them OFF. The three boxes are emptied whenever the window opens
   or closes, so a typed password never lingers on the page. */

let savingMyPassword = false;

function showMyPasswordError(message) {
  const el = document.getElementById('mypw-error');
  el.textContent = message; // plain text, never markup
  el.classList.toggle('hidden', !message);
}

// Empty the three boxes and hide them again, so the window never reopens with a password showing.
function clearMyPasswordFields() {
  ['mypw-current', 'mypw-new', 'mypw-repeat'].forEach(id => {
    const input = document.getElementById(id);
    input.value = '';
    input.type = 'password';
    const toggle = document.getElementById(id + '-toggle');
    toggle.textContent = 'Show';
    toggle.setAttribute('aria-pressed', 'false');
  });
}

function openMyPassword() {
  clearMyPasswordFields();
  showMyPasswordError('');
  document.getElementById('mypw-modal').classList.remove('hidden');
  // On a phone the sidebar overlay would sit over the window, so close it first.
  if (window.innerWidth < 1024 && typeof closeSidebar === 'function') closeSidebar();
  document.getElementById('mypw-current').focus();
}

function closeMyPassword() {
  clearMyPasswordFields();
  showMyPasswordError('');
  document.getElementById('mypw-modal').classList.add('hidden');
}

function saveMyPassword() {
  if (savingMyPassword) return;
  const current = document.getElementById('mypw-current').value;
  const next = document.getElementById('mypw-new').value;
  const repeat = document.getElementById('mypw-repeat').value;
  if (!current) { showMyPasswordError('Type your current password.'); return; }
  if (!next) { showMyPasswordError('Type a new password.'); return; }
  if (next !== repeat) { showMyPasswordError('The two new passwords do not match.'); return; }

  savingMyPassword = true;
  apiFetch('/admin/account/password', {
    method: 'PUT',
    body: JSON.stringify({ current_password: current, password: next, password_confirmation: repeat }),
  })
    .then(() => { closeMyPassword(); toast('Your password was changed.'); })
    .catch(e => showMyPasswordError((e && e.message) || 'Could not change the password.'))
    .finally(() => { savingMyPassword = false; });
}

// Escape closes the window from any of its boxes.
document.getElementById('mypw-modal').addEventListener('keydown', e => { if (e.key === 'Escape') closeMyPassword(); });
