# Profiling Tool Dialect Selection (English/Tagalog) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an interviewer choose English or Tagalog at login, and render all Project Aruga profiling-tool content (public/profiling.html + public/js/profiling.js) in the chosen language for the rest of the session.

**Architecture:** A tiny `t(key, vars)` lookup helper reads a per-session dialect from `sessionStorage` and returns a string from one of two flat JS dictionary objects (`window.I18N_EN`, `window.I18N_TL`), falling back to English then to the raw key if a translation is missing. `profiling.js`'s template-literal HTML builders, validators, and runtime string assignments are converted from hardcoded literals to `t()` calls. Dropdown option labels sourced from the API (`globalData.List_*`) get a separate per-list translation table keyed by the canonical English value, so the submitted *value* never changes — only the displayed *label* does.

**Tech Stack:** Vanilla JS, PHP (untouched by this feature), Tailwind (untouched), sessionStorage for session state (existing pattern in this codebase).

**Spec:** `docs/superpowers/specs/2026-09-14-profiling-dialect-i18n-design.md`

## Global Constraints

- Bisaya/Cebuano is deferred — only English (`en`) and Tagalog (`tl`) ship now. The dictionary/helper design must not preclude adding `ceb.js` later (per spec), but do not build it now.
- Submitted/stored data values must remain the canonical English strings used today — only displayed labels change. Never alter what `collectFormData()` sends to the backend.
- Excluded from translation entirely (per spec): location names (region/province/city/barangay), and these specific hardcoded dropdowns: Sex (Male/Female), Civil Status, Relationship-to-head (in `getFamilyMemberCardHTML`), and all Yes/No toggle option labels.
- All `List_*` API-sourced dropdowns ARE translated (Religion, IP, Education, Disability, Illness, Extension, Occupation, Occupation Class, Materials, Tenure, Electricity, Water, Toilet, Garbage) — label only, not value.
- A missing translation key must never break rendering — always falls back to English, then to the raw key string.
- No automated test framework exists in this project. Verification is manual: load the page in a browser (or reason carefully through the code) and confirm rendered text matches the chosen dialect.
- Do not touch `api/admin-router.php`, `api/auth-router.php`, `api/beneficiaries-router.php`, or any other backend file — this is a pure frontend rendering change (except the one small addition to `index.js`/`index.html` for the login picker, and known-error mapping in the submit catch block).
- Follow the existing code style in `profiling.js`: plain functions, template literals, `document.getElementById`, no build step, no modules/imports (everything is global via `<script>` tags).

---

## File Structure

**New files:**
- `public/js/i18n/en.js` — `window.I18N_EN` dictionary (canonical, authoritative key list) + `window.I18N_EN_DROPDOWNS` (per-list dropdown label maps, English = identity for consistency of shape).
- `public/js/i18n/tl.js` — `window.I18N_TL` dictionary + `window.I18N_TL_DROPDOWNS`.
- `public/js/i18n.js` — `t(key, vars)`, `translateOption(listKey, value)`, `applyStaticI18n()`, and dialect read/write helpers.

**Modified files:**
- `public/index.html` — add dialect picker to `#section-profiling`.
- `public/js/index.js` — capture and store chosen dialect on successful login.
- `public/profiling.html` — add `<script>` tags for the three new files (load order matters); add `data-i18n` attributes to static text (header, logout modal, loading state); set `<html lang>` dynamically.
- `public/js/profiling.js` — replace hardcoded strings with `t()`/`translateOption()` calls throughout (this is the bulk of the work, split into tasks by step/section below).

## Global Constraints (Key Naming Convention)

Use this exact naming scheme for every dictionary key added in every task, so keys are predictable and collision-free:

- Step headings/subtext: `step{N}_heading`, `step{N}_subtext`
- Section/subsection headings within a step: `step{N}_sec_{shortname}` (e.g. `step5_sec_housing`, `step3_sec_personal`)
- Field labels: `step{N}_lbl_{shortname}` (e.g. `step2_lbl_name`, `step5_lbl_materials`)
- Placeholders: `step{N}_ph_{shortname}`
- Buttons: `step{N}_btn_{shortname}` for step-specific text; shared/reused button text gets a non-numbered key (`btn_back`, `btn_yes`, `btn_no`, `btn_please_specify`, `btn_loading`, `btn_select_ellipsis`, `btn_no_match`)
- Validation messages/templates: `val_{shortname}` (shared templates) or `val_step{N}_{shortname}` (step-specific literals)
- Review screen: `review_{shortname}`
- Dropdown option translation tables: `I18N_TL_DROPDOWNS.List_Religion`, etc. (object per list, keyed by canonical English option string)

---

### Task 1: Create the i18n runtime helper and English dictionary skeleton

**Files:**
- Create: `public/js/i18n.js`
- Create: `public/js/i18n/en.js`

**Interfaces:**
- Produces: `window.I18N_EN` (object, string keys → string values, `{var}` placeholder tokens), `window.I18N_EN_DROPDOWNS` (object, list name → object mapping canonical option string → itself for English), global function `t(key, vars)`, global function `translateOption(listKey, value)`, global function `getDialect()`, global function `setDialect(code)`, global function `applyStaticI18n()`.
- Consumes: `sessionStorage` (existing global browser API, already used throughout this codebase).

This task establishes the mechanism with a minimal but real set of keys (the ones needed for the login/session plumbing and the shared boilerplate strings), so later tasks only need to *add* keys and *replace* call sites, never touch the core helper again.

- [ ] **Step 1: Create `public/js/i18n/en.js` with the shared/boilerplate keys**

```js
// public/js/i18n/en.js
window.I18N_EN = {
  // Shared boilerplate (reused across many steps)
  btn_back: 'Back',
  btn_yes: 'Yes',
  btn_no: 'No',
  ph_please_specify: 'Please specify',
  ph_loading: 'Loading...',
  ph_select_ellipsis: 'Select...',
  txt_no_match_found: 'No match found',
  txt_select_options_ellipsis: 'Select options...',
  option_others_specify: 'Others (Specify)',

  // Progress indicator
  step_of: 'STEP {n} OF 10',
  step_of_review: 'STEP REVIEW OF 10',

  // Step labels (progress bar)
  steplabel_1: 'Pre-Qualification',
  steplabel_2: 'Respondent Profile',
  steplabel_3: 'Child Profile',
  steplabel_4: 'Family Profile',
  steplabel_5: 'Socio Economic',
  steplabel_6: 'Health',
  steplabel_7: 'Education',
  steplabel_8: 'Economic Capacity',
  steplabel_9: 'Service Availment',
  steplabel_10: 'Assessment',
  steplabel_11: 'Review',

  // Static page chrome (profiling.html)
  chrome_brand: 'Project Aruga',
  chrome_end_session: 'End Session',
  chrome_exit: 'Exit',
  chrome_progress_heading: 'Registration Progress',
  chrome_loading_tool: 'Loading profiling tool...',
  modal_logout_title: 'End Session?',
  modal_logout_body1: 'Are you sure you want to end your session?',
  modal_logout_body2: 'All unsaved data will be lost.',
  modal_logout_cancel: 'Cancel',
  modal_logout_confirm: 'Yes, End Session',
};

window.I18N_EN_DROPDOWNS = {};
```

- [ ] **Step 2: Create `public/js/i18n.js` with the runtime helper**

```js
// public/js/i18n.js
// ============================================================================
// PROJECT ARUGA - I18N RUNTIME HELPER
// ============================================================================

function getDialect() {
  return sessionStorage.getItem('dialect') || 'en';
}

function setDialect(code) {
  sessionStorage.setItem('dialect', code);
}

function _dictFor(dialect) {
  if (dialect === 'tl' && window.I18N_TL) return window.I18N_TL;
  return window.I18N_EN;
}

function _dropdownDictFor(dialect) {
  if (dialect === 'tl' && window.I18N_TL_DROPDOWNS) return window.I18N_TL_DROPDOWNS;
  return window.I18N_EN_DROPDOWNS;
}

function t(key, vars) {
  const dialect = getDialect();
  const dict = _dictFor(dialect);
  let str = dict[key];
  if (str === undefined) str = window.I18N_EN[key];
  if (str === undefined) str = key;

  if (vars) {
    for (const k of Object.keys(vars)) {
      str = str.split('{' + k + '}').join(vars[k]);
    }
  }
  return str;
}

function translateOption(listKey, value) {
  const dialect = getDialect();
  const dropdowns = _dropdownDictFor(dialect);
  const list = dropdowns[listKey];
  if (list && list[value] !== undefined) return list[value];

  const enList = window.I18N_EN_DROPDOWNS[listKey];
  if (enList && enList[value] !== undefined) return enList[value];

  return value;
}

function applyStaticI18n() {
  document.documentElement.lang = getDialect();
  document.querySelectorAll('[data-i18n]').forEach(function(el) {
    el.textContent = t(el.getAttribute('data-i18n'));
  });
}
```

- [ ] **Step 3: Manually verify the helper loads without errors**

There's no test runner in this project, so verification is a manual browser check. Add temporary script tags to any existing page (or use `profiling.html` once Task 6 wires it up) — for now, verify syntactically by running:

```bash
node --check public/js/i18n.js
node --check public/js/i18n/en.js
```

Expected: both commands produce no output (syntax valid). `node --check` doesn't execute browser-global code (`window`), but it does confirm there are no JS syntax errors, which is what matters at this stage.

- [ ] **Step 4: Commit**

```bash
git add public/js/i18n.js public/js/i18n/en.js
git commit -m "$(cat <<'EOF'
Add i18n runtime helper and English dictionary skeleton

Introduces t()/translateOption()/applyStaticI18n() as the foundation for
profiling tool dialect support. No call sites converted yet.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: Create the Tagalog dictionary skeleton (mirrors Task 1's English keys)

**Files:**
- Create: `public/js/i18n/tl.js`

**Interfaces:**
- Produces: `window.I18N_TL` (same keys as `window.I18N_EN` from Task 1, Tagalog values), `window.I18N_TL_DROPDOWNS` (empty object for now — populated in later tasks as dropdown lists are converted).
- Consumes: nothing (static data file).

- [ ] **Step 1: Create `public/js/i18n/tl.js` translating every key from Task 1's `en.js`**

```js
// public/js/i18n/tl.js
window.I18N_TL = {
  // Shared boilerplate (reused across many steps)
  btn_back: 'Bumalik',
  btn_yes: 'Oo',
  btn_no: 'Hindi',
  ph_please_specify: 'Pakisaad',
  ph_loading: 'Naglo-load...',
  ph_select_ellipsis: 'Pumili...',
  txt_no_match_found: 'Walang natagpuan',
  txt_select_options_ellipsis: 'Pumili ng mga opsyon...',
  option_others_specify: 'Iba Pa (Tukuyin)',

  // Progress indicator
  step_of: 'HAKBANG {n} NG 10',
  step_of_review: 'REBYU NG 10',

  // Step labels (progress bar)
  steplabel_1: 'Kwalipikasyon',
  steplabel_2: 'Profile ng Respondent',
  steplabel_3: 'Profile ng Bata',
  steplabel_4: 'Profile ng Pamilya',
  steplabel_5: 'Sosyo-Ekonomiko',
  steplabel_6: 'Kalusugan',
  steplabel_7: 'Edukasyon',
  steplabel_8: 'Kapasidad Pang-ekonomiya',
  steplabel_9: 'Paggamit ng Serbisyo',
  steplabel_10: 'Pagtatasa',
  steplabel_11: 'Rebyu',

  // Static page chrome (profiling.html)
  chrome_brand: 'Project Aruga',
  chrome_end_session: 'Tapusin ang Sesyon',
  chrome_exit: 'Lumabas',
  chrome_progress_heading: 'Progreso ng Pagpaparehistro',
  chrome_loading_tool: 'Naglo-load ang profiling tool...',
  modal_logout_title: 'Tapusin ang Sesyon?',
  modal_logout_body1: 'Sigurado ka bang gusto mong tapusin ang iyong sesyon?',
  modal_logout_body2: 'Mawawala ang lahat ng hindi nai-save na datos.',
  modal_logout_cancel: 'Kanselahin',
  modal_logout_confirm: 'Oo, Tapusin ang Sesyon',
};

window.I18N_TL_DROPDOWNS = {};
```

- [ ] **Step 2: Verify syntax**

```bash
node --check public/js/i18n/tl.js
```

Expected: no output.

- [ ] **Step 3: Verify key parity between `en.js` and `tl.js`**

Run this Node one-liner to check both files declare the same top-level key set (catches typos/missing keys early — this check is cheap and worth repeating after every later task that edits these two files):

```bash
node -e "
const fs = require('fs');
function loadKeys(path) {
  const src = fs.readFileSync(path, 'utf8');
  const sandbox = { window: {} };
  new Function('window', src)(sandbox.window);
  return Object.keys(sandbox.window.I18N_EN || sandbox.window.I18N_TL || {}).sort();
}
const en = loadKeys('public/js/i18n/en.js');
const tl = loadKeys('public/js/i18n/tl.js');
const missingInTl = en.filter(k => !tl.includes(k));
const extraInTl = tl.filter(k => !en.includes(k));
console.log('Missing in tl.js:', missingInTl);
console.log('Extra in tl.js:', extraInTl);
"
```

Expected: `Missing in tl.js: []` and `Extra in tl.js: []`.

- [ ] **Step 4: Commit**

```bash
git add public/js/i18n/tl.js
git commit -m "$(cat <<'EOF'
Add Tagalog dictionary skeleton matching English key set

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Add dialect picker to login and wire up profiling.html script loading

**Files:**
- Modify: `public/index.html:74-83` (the `#section-profiling` form block)
- Modify: `public/js/index.js:58-157` (`submitForm()`)
- Modify: `public/profiling.html:178-179` (script tags) and `public/profiling.html:37-39, 55-58, 105-166` (static text → `data-i18n`)
- Modify: `public/js/profiling.js:47-102` (`DOMContentLoaded` handler) and `:232-257` (`updateProgress()`)

**Interfaces:**
- Consumes: `t()`, `applyStaticI18n()`, `getDialect()`, `setDialect()` from Task 1/2.
- Produces: `sessionStorage.getItem('dialect')` set at login, consumed by every later task's `t()` calls.

- [ ] **Step 1: Add the dialect picker markup to `index.html`**

In `public/index.html`, insert this block right after the closing `</div>` of the "agree" checkbox block (after line 72, before the `<label for="code">` on line 74):

```html
        <label class="block font-bold text-xs 2xl:text-sm mb-1 text-brand-dark">Language / Wika</label>
        <div class="grid grid-cols-2 gap-2 mb-3">
          <label class="flex items-center justify-center gap-1.5 h-9 rounded border border-gray-300 cursor-pointer text-xs sm:text-sm font-semibold text-gray-600 has-[:checked]:border-brand-blue has-[:checked]:bg-blue-50 has-[:checked]:text-brand-blue transition-all">
            <input type="radio" name="dialect" value="en" class="sr-only" checked>
            English
          </label>
          <label class="flex items-center justify-center gap-1.5 h-9 rounded border border-gray-300 cursor-pointer text-xs sm:text-sm font-semibold text-gray-600 has-[:checked]:border-brand-blue has-[:checked]:bg-blue-50 has-[:checked]:text-brand-blue transition-all">
            <input type="radio" name="dialect" value="tl" class="sr-only">
            Tagalog
          </label>
        </div>
```

- [ ] **Step 2: Capture the chosen dialect in `index.js`'s `submitForm()`**

In `public/js/index.js`, inside `submitForm()` find the success branch (around line 106-118, right before `sessionStorage.setItem('privacyAccepted', 'true');`). Add one line reading the checked radio and storing it:

```js
      const dialectInput = document.querySelector('input[name="dialect"]:checked');
      sessionStorage.setItem('dialect', dialectInput ? dialectInput.value : 'en');
```

Place it directly above the existing `sessionStorage.setItem('privacyAccepted', 'true');` line inside the `if (result.success && result.data) { ... }` block.

- [ ] **Step 3: Wire up script loading order in `profiling.html`**

In `public/profiling.html`, replace lines 178-179:

```html
  <script src="/js/toast.js"></script>
  <script src="/js/profiling.js"></script>
```

with:

```html
  <script src="/js/i18n/en.js"></script>
  <script src="/js/i18n/tl.js"></script>
  <script src="/js/i18n.js"></script>
  <script src="/js/toast.js"></script>
  <script src="/js/profiling.js"></script>
```

- [ ] **Step 4: Add `data-i18n` attributes to static text in `profiling.html`**

Replace these exact lines in `public/profiling.html`:

Line 38-39 (brand heading):
```html
        <h2 class="font-bold text-brand-dark text-sm sm:text-base leading-tight whitespace-nowrap">
          Project Aruga
        </h2>
```
becomes:
```html
        <h2 data-i18n="chrome_brand" class="font-bold text-brand-dark text-sm sm:text-base leading-tight whitespace-nowrap">
          Project Aruga
        </h2>
```

Lines 67-68 (End Session / Exit button text):
```html
          <span class="hidden sm:inline">End Session</span>
          <span class="sm:hidden">Exit</span>
```
becomes:
```html
          <span data-i18n="chrome_end_session" class="hidden sm:inline">End Session</span>
          <span data-i18n="chrome_exit" class="sm:hidden">Exit</span>
```

Line 120 (logout modal title):
```html
        <h3 class="text-lg font-bold text-gray-900 mb-2">End Session?</h3>
```
becomes:
```html
        <h3 data-i18n="modal_logout_title" class="text-lg font-bold text-gray-900 mb-2">End Session?</h3>
```

Lines 121-124 (logout modal body):
```html
        <p class="text-sm text-gray-500">
          Are you sure you want to end your session? <br>
          <span class="font-bold text-red-500">All unsaved data will be lost.</span>
        </p>
```
becomes:
```html
        <p class="text-sm text-gray-500">
          <span data-i18n="modal_logout_body1">Are you sure you want to end your session?</span> <br>
          <span data-i18n="modal_logout_body2" class="font-bold text-red-500">All unsaved data will be lost.</span>
        </p>
```

Line 132 (Cancel button):
```html
          Cancel
```
(the button on lines 129-133) — add the attribute to the `<button>` tag itself:
```html
        <button 
          onclick="hideLogoutModal()" 
          data-i18n="modal_logout_cancel"
          class="w-1/2 px-4 py-3 text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors border-r border-gray-100 focus:outline-none"
        >
          Cancel
        </button>
```

Line 134-139 (Yes, End Session button) similarly:
```html
        <button 
          onclick="confirmLogout()" 
          data-i18n="modal_logout_confirm"
          class="w-1/2 px-4 py-3 text-sm font-bold text-white bg-red-600 hover:bg-red-700 transition-colors focus:outline-none"
        >
          Yes, End Session
        </button>
```

Line 151 (Registration Progress heading):
```html
        <h2 class="font-bold text-brand-dark text-xs sm:text-sm">Registration Progress</h2>
```
becomes:
```html
        <h2 data-i18n="chrome_progress_heading" class="font-bold text-brand-dark text-xs sm:text-sm">Registration Progress</h2>
```

Line 165 (Loading profiling tool... text):
```html
        <p class="mt-4 text-gray-500">Loading profiling tool...</p>
```
becomes:
```html
        <p data-i18n="chrome_loading_tool" class="mt-4 text-gray-500">Loading profiling tool...</p>
```

- [ ] **Step 5: Call `applyStaticI18n()` at the start of profiling.js's `DOMContentLoaded` handler**

In `public/js/profiling.js`, inside the `document.addEventListener('DOMContentLoaded', async function() { ... })` block (starts at line 47), add `applyStaticI18n();` as the very first line of the function body, before the `dobEl` lookup:

```js
document.addEventListener('DOMContentLoaded', async function() {
  applyStaticI18n();

  // Set max date on DOB to today
  const dobEl = document.getElementById('child-dob');
```

- [ ] **Step 6: Convert `updateProgress()`'s hardcoded strings to `t()` calls**

Replace the entire function body at `public/js/profiling.js:232-257`:

```js
function updateProgress(step) {
  const bar = document.getElementById('progress-bar');
  const indicator = document.getElementById('step-indicator');
  const label = document.getElementById('step-label');

  const stepLabels = {
    1: t('steplabel_1'),
    2: t('steplabel_2'),
    3: t('steplabel_3'),
    4: t('steplabel_4'),
    5: t('steplabel_5'),
    6: t('steplabel_6'),
    7: t('steplabel_7'),
    8: t('steplabel_8'),
    9: t('steplabel_9'),
    10: t('steplabel_10'),
    11: t('steplabel_11')
  };

  let percent = (step / 11) * 100;
  if (step === 11) percent = 100;

  bar.style.width = percent + '%';
  indicator.innerText = step === 11 ? t('step_of_review') : t('step_of', { n: step });
  label.innerText = stepLabels[step] || '';
}
```

- [ ] **Step 7: Verify syntax of all modified/created files**

```bash
node --check public/js/index.js
node --check public/js/profiling.js
```

Expected: no output from either command.

- [ ] **Step 8: Manual browser verification**

Since there's no test framework, verify by hand:
1. Open `public/index.html` in a way that reaches your local/dev server (or open the file directly if the app supports it) and confirm the English/Tagalog radio picker renders correctly and defaults to English selected.
2. Log in through the profiling flow (or, if a live backend isn't reachable locally, inspect via browser devtools that `sessionStorage.setItem('dialect', ...)` fires correctly by checking Application > Session Storage after clicking submit).
3. On the profiling page, confirm the header brand text, End Session button, progress heading, and logout modal (trigger via the End Session button) all still show correct English text with no `[object Object]`, `undefined`, or raw key names visible.
4. Manually run `sessionStorage.setItem('dialect', 'tl')` in devtools console, refresh, and confirm the same elements now show the Tagalog text from Task 2's dictionary.

- [ ] **Step 9: Commit**

```bash
git add public/index.html public/js/index.js public/profiling.html public/js/profiling.js
git commit -m "$(cat <<'EOF'
Wire up dialect picker at login and static i18n on profiling page

Adds English/Tagalog radio choice to the profiling login screen, stores
it in sessionStorage, and converts the profiling page's static chrome
(header, logout modal, progress heading/labels) to use the new t()
lookup helper.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: Convert Step 1 (Pre-Qualification) and Step 2 (Respondent Profile)

**Files:**
- Modify: `public/js/profiling.js:323-378` (`getStep1HTML()`)
- Modify: `public/js/profiling.js:384-462` (`getStep2HTML()`)
- Modify: `public/js/i18n/en.js` (add step1/step2 keys)
- Modify: `public/js/i18n/tl.js` (add matching translations)

**Interfaces:**
- Consumes: `t()` from Task 1.
- Produces: keys `step1_heading`, `step1_subtext`, `step1_sec_membership_icon_heading`, `step1_sec_membership_subtext`, `step1_opt_yes_title`, `step1_opt_yes_sub`, `step1_opt_no_title`, `step1_opt_no_sub`, `step1_lbl_household_id`, `step1_ph_household_id`, `step1_helper_household_id`, `step1_btn_next`, `step2_heading`, `step2_subtext`, `step2_sec_heading`, `step2_lbl_name`, `step2_ph_name`, `step2_lbl_relationship`, `step2_ph_relationship`, `step2_lbl_email`, `step2_ph_email`, `step2_lbl_contact`, `step2_ph_contact`, `step2_btn_next`. Uses shared `btn_back` from Task 1.

- [ ] **Step 1: Add Step 1 and Step 2 keys to `public/js/i18n/en.js`**

Add inside the `window.I18N_EN = { ... }` object (append before the closing `};`):

```js
  // Step 1: Pre-Qualification
  step1_heading: 'Pre-Qualification',
  step1_subtext: 'Confirm 4Ps membership to help us coordinate your benefits.',
  step1_sec_heading: '4Ps Membership',
  step1_sec_subtext: 'Are you a member of the Pantawid Pamilyang Pilipino Program?',
  step1_opt_yes_title: 'Yes',
  step1_opt_yes_sub: 'I am a member of the 4Ps Program',
  step1_opt_no_title: 'No',
  step1_opt_no_sub: 'I am not a 4Ps member',
  step1_lbl_household_id: 'Household ID',
  step1_ph_household_id: 'Enter 13-18 character ID',
  step1_helper_household_id: 'Found on your 4Ps ID card.',
  step1_btn_next: 'Next: Respondent Profile',

  // Step 2: Respondent Profile
  step2_heading: 'Respondent Profile',
  step2_subtext: 'Provide your personal details as the individual completing this assessment.',
  step2_sec_heading: 'Respondent Profile',
  step2_lbl_name: 'Name of Respondent',
  step2_ph_name: 'Enter full name',
  step2_lbl_relationship: 'Relationship to the Child',
  step2_ph_relationship: 'Relationship to the Child',
  step2_lbl_email: 'Email Address',
  step2_ph_email: 'name@example.com',
  step2_lbl_contact: 'Contact Number',
  step2_ph_contact: '0912 345 6789',
  step2_btn_next: 'Next: Child Profile',
```

- [ ] **Step 2: Add matching Tagalog translations to `public/js/i18n/tl.js`**

```js
  // Step 1: Pre-Qualification
  step1_heading: 'Kwalipikasyon',
  step1_subtext: 'Kumpirmahin ang pagiging kasapi sa 4Ps upang matulungan kaming i-coordinate ang iyong mga benepisyo.',
  step1_sec_heading: 'Pagiging Kasapi sa 4Ps',
  step1_sec_subtext: 'Ikaw ba ay kasapi ng Pantawid Pamilyang Pilipino Program?',
  step1_opt_yes_title: 'Oo',
  step1_opt_yes_sub: 'Ako ay kasapi ng 4Ps Program',
  step1_opt_no_title: 'Hindi',
  step1_opt_no_sub: 'Hindi ako kasapi ng 4Ps',
  step1_lbl_household_id: 'Household ID',
  step1_ph_household_id: 'Ilagay ang 13-18 karakter na ID',
  step1_helper_household_id: 'Makikita sa iyong 4Ps ID card.',
  step1_btn_next: 'Susunod: Profile ng Respondent',

  // Step 2: Respondent Profile
  step2_heading: 'Profile ng Respondent',
  step2_subtext: 'Ibigay ang iyong personal na impormasyon bilang taong sumasagot sa pagtatasang ito.',
  step2_sec_heading: 'Profile ng Respondent',
  step2_lbl_name: 'Pangalan ng Respondent',
  step2_ph_name: 'Ilagay ang buong pangalan',
  step2_lbl_relationship: 'Kaugnayan sa Bata',
  step2_ph_relationship: 'Kaugnayan sa Bata',
  step2_lbl_email: 'Email Address',
  step2_ph_email: 'name@example.com',
  step2_lbl_contact: 'Numero ng Contact',
  step2_ph_contact: '0912 345 6789',
  step2_btn_next: 'Susunod: Profile ng Bata',
```

- [ ] **Step 3: Convert `getStep1HTML()` to use `t()`**

Replace `public/js/profiling.js:323-378` with:

```js
function getStep1HTML() {
  return `
    <div id="step-1" class="step-section w-full space-y-5">
      <div class="w-full text-left space-y-0.5">
        <h1 class="font-extrabold text-brand-dark text-xl sm:text-2xl">${t('step1_heading')}</h1>
        <p class="text-gray-500 text-xs sm:text-sm">${t('step1_subtext')}</p>
      </div>
      
      <section class="w-full bg-white rounded-xl border border-[#dce0e5] shadow-sm p-5 sm:p-6">
        <div class="mb-5 flex items-start gap-3">
          <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-[18px] text-brand-blue">assignment_ind</span>
          </div>
          <div>
            <h2 class="font-bold text-brand-dark text-base sm:text-lg leading-tight">${t('step1_sec_heading')}</h2>
            <p class="text-gray-500 text-xs sm:text-sm mt-0.5">${t('step1_sec_subtext')}</p>
          </div>
        </div>
        
        <fieldset class="space-y-3 mb-5">
          <label class="radio-card relative block w-full border border-gray-200 rounded-lg p-1 cursor-pointer hover:border-blue-300 transition-all select-none">
            <input type="radio" id="membership-yes" name="membership" value="Yes" class="peer sr-only" onchange="toggleId(true)" checked>
            <div class="p-2 flex flex-col border-transparent transition-all">
              <span class="font-bold text-brand-dark block text-xs sm:text-sm">${t('step1_opt_yes_title')}</span>
              <span class="text-[10px] sm:text-xs text-gray-500">${t('step1_opt_yes_sub')}</span>
            </div>
          </label>
          
          <label class="radio-card relative block w-full border border-gray-200 rounded-lg p-1 cursor-pointer hover:border-blue-300 transition-all select-none">
            <input type="radio" id="membership-no" name="membership" value="No" class="peer sr-only" onchange="toggleId(false)">
            <div class="p-2 flex flex-col border-transparent transition-all">
              <span class="font-bold text-brand-dark block text-xs sm:text-sm">${t('step1_opt_no_title')}</span>
              <span class="text-[10px] sm:text-xs text-gray-500">${t('step1_opt_no_sub')}</span>
            </div>
          </label>
        </fieldset>
        
        <div id="id-container" class="transition-opacity duration-300">
          <label for="household-id" class="block font-bold text-brand-dark text-xs sm:text-sm mb-1">${t('step1_lbl_household_id')} <span class="text-red-500">*</span></label>
          <div class="flex items-center gap-2 bg-white rounded border border-gray-300 px-3 py-1.5 h-9 focus-within:ring-1 focus-within:ring-brand-blue transition-all">
            <span class="material-symbols-outlined text-[16px] text-gray-400">badge</span>
            <input id="household-id" name="household-id" type="text" maxlength="18" oninput="this.value=this.value.slice(0,18)" class="w-full text-xs sm:text-sm outline-none text-gray-800 placeholder-gray-400 bg-transparent" placeholder="${t('step1_ph_household_id')}">
          </div>
          <p class="text-[10px] sm:text-xs text-brand-blue mt-1">${t('step1_helper_household_id')}</p>
        </div>
      </section>
      
      <div class="w-full flex justify-end pb-6">
        <button onclick="if(validateStep(1)) goToStep(2)" class="w-full sm:w-auto px-4 h-10 bg-brand-blue rounded-lg text-white font-bold text-xs sm:text-sm hover:bg-brand-blueHover shadow-md transition-all flex items-center justify-center gap-2">
          ${t('step1_btn_next')}
          <span class="w-6 h-6 rounded-md bg-white/20 flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[16px]">arrow_forward</span></span>
        </button>
      </div>
    </div>
  `;
}
```

Note: `<div class="w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm invalid:text-gray-400"` in the original file — check the file after Task 3 lands to confirm line numbers haven't drifted before applying this replacement; use the function body content (not line numbers) to locate the exact block to replace.

- [ ] **Step 4: Convert `getStep2HTML()` to use `t()`**

Replace `public/js/profiling.js:384-462` with:

```js
function getStep2HTML() {
  return `
    <div id="step-2" class="step-section hidden-step w-full space-y-5">
      <div class="w-full text-left space-y-0.5">
        <h1 class="font-extrabold text-brand-dark text-xl sm:text-2xl">${t('step2_heading')}</h1>
        <p class="text-gray-500 text-xs sm:text-sm">${t('step2_subtext')}</p>
      </div>
      
      <section class="w-full bg-white rounded-xl border border-[#dce0e5] shadow-sm p-5 sm:p-6">
        <div class="mb-8 flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-[18px] text-brand-blue">account_circle</span>
          </div>
          <h2 class="font-bold text-brand-dark text-base sm:text-lg">${t('step2_sec_heading')}</h2>
        </div>
        
        <div class="space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label for="resp-name" class="block font-bold text-brand-dark text-xs sm:text-sm mb-1">${t('step2_lbl_name')} <span class="text-red-500">*</span></label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <span class="material-symbols-outlined text-[16px] text-gray-400">person</span>
                </div>
                <input type="text" id="resp-name" name="resp-name" class="w-full h-9 pl-10 pr-3 rounded border border-gray-300 text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="${t('step2_ph_name')}">
              </div>
            </div>
            
            <div>
              <label for="dd-relationship-input" class="block font-bold text-brand-dark text-xs sm:text-sm mb-1">${t('step2_lbl_relationship')} <span class="text-red-500">*</span></label>
              <div class="relative" id="relationship-combobox">
                <input type="text" id="dd-relationship-input" name="dd-relationship-input" autocomplete="off"
                  class="google-dropdown-style w-full h-9 pl-3 pr-8 text-xs sm:text-sm outline-none bg-white text-gray-800 placeholder-gray-400"
                  placeholder="${t('step2_ph_relationship')}">
                <input type="hidden" id="dd-relationship" name="dd-relationship">
                <span class="material-symbols-outlined pointer-events-none select-none" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);font-size:18px;color:#9ca3af;">expand_more</span>
                <ul id="dd-relationship-list"
                  class="fixed z-50 bg-white border border-gray-300 rounded shadow-lg overflow-y-auto dropdown-scroll hidden text-xs sm:text-sm">
                </ul>
              </div>
            </div>
          </div>
          
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label for="resp-email" class="block font-bold text-brand-dark text-xs sm:text-sm mb-1">${t('step2_lbl_email')}</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <span class="material-symbols-outlined text-[16px] text-gray-400">mail</span>
                </div>
                <input type="email" id="resp-email" name="resp-email" class="w-full h-9 pl-10 pr-3 rounded border border-gray-300 text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="${t('step2_ph_email')}">
              </div>
            </div>
            
            <div>
              <label for="resp-contact" class="block font-bold text-brand-dark text-xs sm:text-sm mb-1">${t('step2_lbl_contact')}</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <span class="material-symbols-outlined text-[16px] text-gray-400">phone</span>
                </div>
                <input type="tel" id="resp-contact" name="resp-contact" maxlength="13" oninput="formatPhone(this)" class="w-full h-9 pl-10 pr-3 rounded border border-gray-300 text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="${t('step2_ph_contact')}">
              </div>
            </div>
          </div>
        </div>
      </section>
      
      <div class="w-full flex justify-between pb-6">
        <button onclick="goToStep(1)" class="px-4 h-10 bg-white border border-gray-300 rounded-lg text-gray-700 font-bold text-xs sm:text-sm hover:bg-gray-50 flex items-center gap-2 transition-all">
          <span class="material-symbols-outlined text-[16px]">arrow_back</span> ${t('btn_back')}
        </button>
        <button onclick="if(validateStep(2)) goToStep(3)" class="px-4 h-10 bg-brand-blue rounded-lg text-white font-bold text-xs sm:text-sm hover:bg-brand-blueHover flex items-center gap-2 shadow-md transition-all">
          ${t('step2_btn_next')}
          <span class="w-6 h-6 rounded-md bg-white/20 flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[16px]">arrow_forward</span></span>
        </button>
      </div>
    </div>
  `;
}
```

- [ ] **Step 5: Verify syntax**

```bash
node --check public/js/profiling.js
node --check public/js/i18n/en.js
node --check public/js/i18n/tl.js
```

Expected: no output from any command.

- [ ] **Step 6: Re-run the key-parity check from Task 2 Step 3**

```bash
node -e "
const fs = require('fs');
function loadKeys(path) {
  const src = fs.readFileSync(path, 'utf8');
  const sandbox = { window: {} };
  new Function('window', src)(sandbox.window);
  return Object.keys(sandbox.window.I18N_EN || sandbox.window.I18N_TL || {}).sort();
}
const en = loadKeys('public/js/i18n/en.js');
const tl = loadKeys('public/js/i18n/tl.js');
const missingInTl = en.filter(k => !tl.includes(k));
const extraInTl = tl.filter(k => !en.includes(k));
console.log('Missing in tl.js:', missingInTl);
console.log('Extra in tl.js:', extraInTl);
"
```

Expected: both arrays empty.

- [ ] **Step 7: Manual browser verification**

Load the profiling tool (with a valid session — either through the real login flow or by manually setting the required sessionStorage keys `session_id`, `interviewer_code`, `privacyAccepted` in devtools console, then navigating to `/profiling`). Confirm Step 1 and Step 2 render fully in English by default, and switch `sessionStorage.setItem('dialect','tl')` + refresh to confirm both steps render in Tagalog with no missing text or literal key names showing.

- [ ] **Step 8: Commit**

```bash
git add public/js/profiling.js public/js/i18n/en.js public/js/i18n/tl.js
git commit -m "$(cat <<'EOF'
Translate Step 1 (Pre-Qualification) and Step 2 (Respondent Profile)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 5: Convert Step 3 (Child Profile) — largest single step, includes translated dropdowns

**Files:**
- Modify: `public/js/profiling.js:468-672` (`getStep3HTML()`)
- Modify: `public/js/profiling.js:1673-1676` (`populateAllDropdowns()` calls for `dd-extension`, `dd-religion`, `dd-ip`, `dd-education`)
- Modify: `public/js/profiling.js:1689-1722` (`populateSelect()`)
- Modify: `public/js/profiling.js:1724-1777` (`populateMulti()` — confirm exact line range by reading the file, since research only captured call sites, not full body; the body is between `populateSelect()`'s end and `initRelationshipCombobox()`'s start at line ~1553, so search for `function populateMulti(` to find precise boundaries)
- Modify: `public/js/i18n/en.js`, `public/js/i18n/tl.js` (add step3 keys + dropdown tables for `List_Religion`, `List_IP`, `List_Education`)

**Interfaces:**
- Consumes: `t()`, `translateOption()` from Task 1.
- Produces: keys `step3_heading`, `step3_subtext`, `step3_sec_personal`, `step3_lbl_fname`, `step3_lbl_mname`, `step3_lbl_lname`, `step3_lbl_extension`, `step3_lbl_region`, `step3_lbl_province`, `step3_lbl_city`, `step3_lbl_barangay`, `step3_lbl_street`, `step3_ph_street`, `step3_lbl_contact`, `step3_ph_contact`, `step3_sec_demographics`, `step3_lbl_dob`, `step3_lbl_sex`, `step3_opt_male`, `step3_opt_female`, `step3_lbl_religion`, `step3_lbl_ip`, `step3_sec_condition_edu`, `step3_lbl_education`, `step3_lbl_disability`, `step3_lbl_illness`, `step3_btn_next`, plus generic `ph_select_region`, `ph_select_province`, `ph_select_city`, `ph_select_barangay` (reused later by `initializeLocationDropdowns`/`updateProvinces`/`updateCities`/`updateBarangays` in Task 9). `I18N_TL_DROPDOWNS.List_Religion`, `.List_IP`, `.List_Education` translation tables (canonical English value → Tagalog label).
- IMPORTANT: Per Global Constraints, Sex (Male/Female) stays English-only — do NOT wrap "Male"/"Female" option labels in `t()`; leave them as literal English strings. `step3_lbl_sex` (the field label "Sex") IS translated; the Male/Female *option values/labels themselves* are not.

- [ ] **Step 1: Add Step 3 keys to `public/js/i18n/en.js`**

```js
  // Step 3: Child Profile
  step3_heading: 'Child Profile',
  step3_subtext: "Provide the child's personal details, demographics, and specific health conditions.",
  step3_sec_personal: 'Personal Information',
  step3_lbl_fname: 'First Name',
  step3_lbl_mname: 'Middle Name',
  step3_lbl_lname: 'Last Name',
  step3_lbl_extension: 'Extension',
  step3_lbl_region: 'Region',
  step3_lbl_province: 'Province',
  step3_lbl_city: 'City/Municipality',
  step3_lbl_barangay: 'Barangay',
  step3_lbl_street: 'Street Address',
  step3_ph_street: 'House No., Street',
  step3_lbl_contact: 'Contact Number',
  step3_ph_contact_alt: '09XX XXX XXXX',
  step3_sec_demographics: 'Demographics',
  step3_lbl_dob: 'Date of Birth',
  step3_lbl_sex: 'Sex',
  step3_lbl_religion: 'Religion',
  step3_lbl_ip: 'IP Membership',
  step3_sec_condition_edu: 'Condition & Education',
  step3_lbl_education: 'Highest Educational Attainment',
  step3_lbl_disability: 'Disability or Special Needs (Select all that apply)',
  step3_lbl_illness: 'Critical Illness (Select all that apply)',
  step3_btn_next: 'Next: Family Profile',

  ph_select_region: 'Select Region',
  ph_select_province: 'Select Province',
  ph_select_city: 'Select City',
  ph_select_barangay: 'Select Barangay',
  ph_select_religion: 'Select Religion',
  ph_select_ip: 'Select IP Group',
  ph_select_education: 'Select Education',
  ph_none: 'None',
```

- [ ] **Step 2: Add matching keys to `public/js/i18n/tl.js`**

```js
  // Step 3: Child Profile
  step3_heading: 'Profile ng Bata',
  step3_subtext: 'Ibigay ang personal na detalye, demograpiko, at partikular na kondisyon sa kalusugan ng bata.',
  step3_sec_personal: 'Personal na Impormasyon',
  step3_lbl_fname: 'Pangalan',
  step3_lbl_mname: 'Gitnang Pangalan',
  step3_lbl_lname: 'Apelyido',
  step3_lbl_extension: 'Extension',
  step3_lbl_region: 'Rehiyon',
  step3_lbl_province: 'Probinsya',
  step3_lbl_city: 'Lungsod/Munisipalidad',
  step3_lbl_barangay: 'Barangay',
  step3_lbl_street: 'Address (Kalye)',
  step3_ph_street: 'Numero ng Bahay, Kalye',
  step3_lbl_contact: 'Numero ng Contact',
  step3_ph_contact_alt: '09XX XXX XXXX',
  step3_sec_demographics: 'Demograpiko',
  step3_lbl_dob: 'Petsa ng Kapanganakan',
  step3_lbl_sex: 'Kasarian',
  step3_lbl_religion: 'Relihiyon',
  step3_lbl_ip: 'Pagiging Kasapi sa IP',
  step3_sec_condition_edu: 'Kondisyon at Edukasyon',
  step3_lbl_education: 'Pinakamataas na Naabot na Edukasyon',
  step3_lbl_disability: 'Kapansanan o Espesyal na Pangangailangan (Piliin lahat na angkop)',
  step3_lbl_illness: 'Malubhang Sakit (Piliin lahat na angkop)',
  step3_btn_next: 'Susunod: Profile ng Pamilya',

  ph_select_region: 'Pumili ng Rehiyon',
  ph_select_province: 'Pumili ng Probinsya',
  ph_select_city: 'Pumili ng Lungsod',
  ph_select_barangay: 'Pumili ng Barangay',
  ph_select_religion: 'Pumili ng Relihiyon',
  ph_select_ip: 'Pumili ng Grupong IP',
  ph_select_education: 'Pumili ng Edukasyon',
  ph_none: 'Wala',
```

- [ ] **Step 3: Add dropdown translation tables to `public/js/i18n/tl.js`**

Append (outside `I18N_TL`, replacing the placeholder `window.I18N_TL_DROPDOWNS = {};` line from Task 2 with the populated version — note List_IP and List_Education tables added here; List_Disability/List_Illness/etc. are added in later tasks that touch those dropdowns):

```js
window.I18N_TL_DROPDOWNS = {
  List_Religion: {
    'No Religion': 'Walang Relihiyon',
    'Roman Catholic': 'Romano Katoliko',
    'Islam': 'Islam',
    'Iglesia ni Cristo': 'Iglesia ni Cristo',
    'Protestant': 'Protestante',
    'Born Again Christian': 'Born Again Kristiyano',
    'Buddhism': 'Budismo',
    'Hinduism': 'Hinduismo',
    'Others': 'Iba Pa',
  },
  List_IP: {
    'Not a member': 'Hindi Kasapi',
    'Aeta': 'Aeta',
    'Igorot': 'Igorot',
    'Lumad': 'Lumad',
    'Mangyan': 'Mangyan',
    'Tagbanua': 'Tagbanua',
    'Badjao': 'Badjao',
    "T'boli": "T'boli",
    'Manobo': 'Manobo',
    'Others': 'Iba Pa',
  },
  List_Education: {
    'No formal education': 'Walang pormal na edukasyon',
    'Elementary Undergraduate': 'Hindi Nakatapos ng Elementarya',
    'Elementary Graduate': 'Nakatapos ng Elementarya',
    'High School Undergraduate': 'Hindi Nakatapos ng High School',
    'High School Graduate': 'Nakatapos ng High School',
    'Senior High School Graduate': 'Nakatapos ng Senior High School',
    'College Undergraduate': 'Hindi Nakatapos ng Kolehiyo',
    'College Graduate': 'Nakatapos ng Kolehiyo',
    'Vocational/Technical': 'Bokasyunal/Teknikal',
    'Post Graduate': 'Post Graduate',
    'Others': 'Iba Pa',
  },
};
```

- [ ] **Step 4: Read the current file to find exact boundaries before editing**

Run:
```bash
grep -n "^function getStep3HTML\|^function populateSelect\|^function populateMulti\|^function initRelationshipCombobox" public/js/profiling.js
```

Use the reported line numbers (they may have shifted slightly from Tasks 3-4's edits) as the authoritative boundaries for the edits in the next two steps, rather than the approximate numbers in this plan's Files section.

- [ ] **Step 5: Convert `getStep3HTML()` to use `t()`**

Replace the function body (found via Step 4's grep) with:

```js
function getStep3HTML() {
  return `
    <div id="step-3" class="step-section hidden-step w-full space-y-5">
      <div class="w-full text-left space-y-0.5">
        <h1 class="font-extrabold text-brand-dark text-xl sm:text-2xl">${t('step3_heading')}</h1>
        <p class="text-gray-500 text-xs sm:text-sm">${t('step3_subtext')}</p>
      </div>
      
      <!-- Personal Information -->
      <section class="w-full bg-white rounded-xl border border-[#dce0e5] shadow-sm p-5 sm:p-6">
        <div class="mb-8 flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-[18px] text-brand-blue">face</span>
          </div>
          <h3 class="font-bold text-brand-dark text-base sm:text-lg">${t('step3_sec_personal')}</h3>
        </div>
        
        <div class="space-y-4">
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div>
              <label for="child-fname" class="block text-xs font-bold text-brand-dark mb-1">${t('step3_lbl_fname')} <span class="text-red-500">*</span></label>
              <input type="text" id="child-fname" name="child-fname" class="w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="${t('step3_lbl_fname')}">
            </div>
            <div>
              <label for="child-mname" class="block text-xs font-bold text-brand-dark mb-1">${t('step3_lbl_mname')}</label>
              <input type="text" id="child-mname" name="child-mname" class="w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="${t('step3_lbl_mname')}">
            </div>
            <div>
              <label for="child-lname" class="block text-xs font-bold text-brand-dark mb-1">${t('step3_lbl_lname')} <span class="text-red-500">*</span></label>
              <input type="text" id="child-lname" name="child-lname" class="w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="${t('step3_lbl_lname')}">
            </div>
            <div>
              <label for="dd-extension" class="block text-xs font-bold text-brand-dark mb-1">${t('step3_lbl_extension')}</label>
              <div class="relative">
                <select id="dd-extension" name="dd-extension" class="google-dropdown-style w-full h-9 pl-3 pr-8 text-xs sm:text-sm bg-white text-gray-800 invalid:text-gray-400">
                  <option value="" disabled selected>${t('ph_loading')}</option>
                </select>
              </div>
            </div>
          </div>
          
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label for="child-region" class="block text-xs font-bold text-brand-dark mb-1">${t('step3_lbl_region')} <span class="text-red-500">*</span></label>
              <div class="relative">
                <select id="child-region" name="child-region" class="google-dropdown-style w-full h-9 pl-3 pr-8 text-xs sm:text-sm bg-white text-gray-800 invalid:text-gray-400" onchange="updateProvinces()">
                  <option value="" disabled selected>${t('ph_select_region')}</option>
                </select>
              </div>
            </div>
            <div>
              <label for="child-province" class="block text-xs font-bold text-brand-dark mb-1">${t('step3_lbl_province')} <span class="text-red-500">*</span></label>
              <div class="relative">
                <select id="child-province" name="child-province" class="google-dropdown-style w-full h-9 pl-3 pr-8 text-xs sm:text-sm bg-white text-gray-800 invalid:text-gray-400" onchange="updateCities()" disabled>
                  <option value="" disabled selected>${t('ph_select_province')}</option>
                </select>
              </div>
            </div>
          </div>
          
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label for="child-city" class="block text-xs font-bold text-brand-dark mb-1">${t('step3_lbl_city')} <span class="text-red-500">*</span></label>
              <div class="relative">
                <select id="child-city" name="child-city" class="google-dropdown-style w-full h-9 pl-3 pr-8 text-xs sm:text-sm bg-white text-gray-800 invalid:text-gray-400" onchange="updateBarangays()" disabled>
                  <option value="" disabled selected>${t('ph_select_city')}</option>
                </select>
              </div>
            </div>
            <div>
              <label for="child-barangay" class="block text-xs font-bold text-brand-dark mb-1">${t('step3_lbl_barangay')} <span class="text-red-500">*</span></label>
              <div class="relative">
                <select id="child-barangay" name="child-barangay" class="google-dropdown-style w-full h-9 pl-3 pr-8 text-xs sm:text-sm bg-white text-gray-800 invalid:text-gray-400" disabled>
                  <option value="" disabled selected>${t('ph_select_barangay')}</option>
                </select>
              </div>
            </div>
          </div>
          
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label for="child-street" class="block text-xs font-bold text-brand-dark mb-1">${t('step3_lbl_street')} <span class="text-red-500">*</span></label>
              <input type="text" id="child-street" name="child-street" class="w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="${t('step3_ph_street')}">
            </div>
            <div>
              <label for="resp-contact" class="block text-xs font-bold text-brand-dark mb-1">${t('step3_lbl_contact')}</label>
              <input type="tel" id="child-contact" name="child-contact" maxlength="13" oninput="formatPhone(this)" class="w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="${t('step3_ph_contact_alt')}">
            </div>
          </div>
        </div>
      </section>
      
      <!-- Demographics -->
      <section class="w-full bg-white rounded-xl border border-[#dce0e5] shadow-sm p-5 sm:p-6">
        <div class="mb-8 flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-[18px] text-brand-blue">manage_accounts</span>
          </div>
          <h3 class="font-bold text-brand-dark text-base sm:text-lg">${t('step3_sec_demographics')}</h3>
        </div>
        
        <div class="space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label for="child-dob" class="block text-xs font-bold text-brand-dark mb-1">${t('step3_lbl_dob')} <span class="text-red-500">*</span></label>
              <div class="relative">
                <input type="date" id="child-dob" name="child-dob" class="w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm focus:ring-1 focus:ring-brand-blue outline-none text-gray-600">
              </div>
            </div>
            <div>
              <label for="sex-male" class="block text-xs font-bold text-brand-dark mb-1">${t('step3_lbl_sex')}</label>
              <div class="slide-toggle-container h-9 w-full sm:w-2/3">
                <div class="slide-toggle-slider"></div>
                <label for="sex-male" class="slide-toggle-label text-white" onclick="toggleBtn(this)">
                  <input type="radio" id="sex-male" name="sex" value="Male" class="hidden" checked> 
                  <span>Male</span>
                </label>
                <label for="sex-female" class="slide-toggle-label text-gray-500" onclick="toggleBtn(this)">
                  <input type="radio" id="sex-female" name="sex" value="Female" class="hidden"> 
                  <span>Female</span>
                </label>
              </div>
            </div>
          </div>
          
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label for="dd-religion" class="block text-xs font-bold text-brand-dark mb-1">${t('step3_lbl_religion')} <span class="text-red-500">*</span></label>
              <div class="relative">
                <select id="dd-religion" name="dd-religion" class="google-dropdown-style w-full h-9 pl-3 pr-8 text-xs sm:text-sm bg-white text-gray-800 invalid:text-gray-400" onchange="toggleOther(this, 'rel-other')">
                  <option value="" disabled selected>${t('ph_loading')}</option>
                </select>
              </div>
              <input id="rel-other" name="rel-other" type="text" class="mt-2 w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm hidden focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="${t('ph_please_specify')}">
            </div>
            <div>
              <label for="dd-ip" class="block text-xs font-bold text-brand-dark mb-1">${t('step3_lbl_ip')} <span class="text-red-500">*</span></label>
              <div class="relative">
                <select id="dd-ip" name="dd-ip" class="google-dropdown-style w-full h-9 pl-3 pr-8 text-xs sm:text-sm bg-white text-gray-800 invalid:text-gray-400" onchange="toggleOther(this, 'ip-other')">
                  <option value="" disabled selected>${t('ph_loading')}</option>
                </select>
              </div>
              <input id="ip-other" name="ip-other" type="text" class="mt-2 w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm hidden focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="${t('ph_please_specify')}">
            </div>
          </div>
        </div>
      </section>
      
      <!-- Condition & Education -->
      <section class="w-full bg-white rounded-xl border border-[#dce0e5] shadow-sm p-5 sm:p-6">
        <div class="mb-8 flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-[18px] text-brand-blue">school</span>
          </div>
          <h3 class="font-bold text-brand-dark text-base sm:text-lg">${t('step3_sec_condition_edu')}</h3>
        </div>
        
        <div class="space-y-4">
          <div>
            <label for="dd-education" class="block text-xs font-bold text-brand-dark mb-1">${t('step3_lbl_education')} <span class="text-red-500">*</span></label>
            <div class="relative">
              <select id="dd-education" name="dd-education" class="google-dropdown-style w-full h-9 pl-3 pr-8 text-xs sm:text-sm bg-white text-gray-800 invalid:text-gray-400" onchange="toggleOther(this, 'edu-other')">
                <option value="" disabled selected>${t('ph_loading')}</option>
              </select>
            </div>
            <input id="edu-other" name="edu-other" type="text" class="mt-2 w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm hidden focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="${t('ph_please_specify')}">
          </div>
          
          <div class="relative">
            <label for="dd-disability-btn" class="block text-xs font-bold text-brand-dark mb-1">${t('step3_lbl_disability')}</label>
            <button id="dd-disability-btn" type="button" onclick="toggleDropdown('dd-disability')" class="w-full h-9 px-3 text-left bg-white border border-gray-300 rounded focus:ring-1 focus:ring-brand-blue outline-none flex justify-between items-center text-xs sm:text-sm">
              <span id="disability-display" class="truncate text-gray-400">${t('txt_select_options_ellipsis')}</span>
              <span class="material-symbols-outlined text-[18px] text-gray-400 flex-shrink-0">expand_more</span>
            </button>
            <div id="dd-disability" class="hidden absolute z-10 w-full google-menu mt-1 max-h-60 overflow-y-auto dropdown-scroll">
              <!-- Will be populated by JavaScript -->
            </div>
          </div>
          
          <div class="relative">
            <label for="dd-illness-btn" class="block text-xs font-bold text-brand-dark mb-1">${t('step3_lbl_illness')}</label>
            <button id="dd-illness-btn" type="button" onclick="toggleDropdown('dd-illness')" class="w-full h-9 px-3 text-left bg-white border border-gray-300 rounded focus:ring-1 focus:ring-brand-blue outline-none flex justify-between items-center text-xs sm:text-sm">
              <span id="illness-display" class="truncate text-gray-400">${t('txt_select_options_ellipsis')}</span>
              <span class="material-symbols-outlined text-[18px] text-gray-400 flex-shrink-0">expand_more</span>
            </button>
            <div id="dd-illness" class="hidden absolute z-10 w-full google-menu mt-1 max-h-60 overflow-y-auto dropdown-scroll">
              <!-- Will be populated by JavaScript -->
            </div>
            <input id="illness-other-input" name="illness-other-input" type="text" class="mt-2 w-full h-9 px-3 rounded border border-gray-300 text-xs sm:text-sm hidden focus:ring-1 focus:ring-brand-blue outline-none placeholder-gray-400" placeholder="${t('ph_please_specify')}">
          </div>
        </div>
      </section>
      
      <div class="w-full flex justify-between pb-6">
        <button onclick="goToStep(2)" class="px-4 h-10 bg-white border border-gray-300 rounded-lg text-gray-700 font-bold text-xs sm:text-sm hover:bg-gray-50 flex items-center gap-2 transition-all">
          <span class="material-symbols-outlined text-[16px]">arrow_back</span> ${t('btn_back')}
        </button>
        <button onclick="if(validateStep(3)) goToStep(4)" class="px-4 h-10 bg-brand-blue rounded-lg text-white font-bold text-xs sm:text-sm hover:bg-brand-blueHover flex items-center gap-2 shadow-md transition-all">
          ${t('step3_btn_next')}
          <span class="w-6 h-6 rounded-md bg-white/20 flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[16px]">arrow_forward</span></span>
        </button>
      </div>
    </div>
  `;
}
```

Note: Male/Female option text (`<span>Male</span>` / `<span>Female</span>`) is left as literal English per the Global Constraints exclusion list — do not wrap these in `t()`.

- [ ] **Step 6: Add a `listKey` parameter to `populateSelect()` and translate its rendered labels**

Find the current `populateSelect` function (locate via the Step 4 grep) and replace it with:

```js
function populateSelect(id, items, placeholder, listKey) {
  const s = document.getElementById(id);
  if (!s) return;

  if (!items || items.length === 0) {
    s.innerHTML = `<option value="" disabled selected>${placeholder} (No data)</option>`;
    return;
  }

  s.innerHTML = `<option value="" disabled selected>${placeholder}</option>`;

  items.forEach(item => {
    const opt = document.createElement('option');
    const isOthers = item === 'Others' || item === 'Other' || item === 'Others (Specify)' || item === 'Other (specify)';
    opt.value = isOthers ? 'Others' : item;
    opt.innerText = isOthers ? t('option_others_specify') : (listKey ? translateOption(listKey, item) : item);
    s.appendChild(opt);
  });
}
```

(This assumes the original body used `s.innerHTML = ...` for the empty-state and per-item option population as described in the research inventory at lines 1696/1700/1707/1719 — read the actual current function body via the file first if it differs from this reconstruction, and adapt the replacement to preserve any other existing behavior not captured in this plan, such as `onchange` re-binding, while adding the `listKey`-driven label translation.)

- [ ] **Step 7: Update the four `populateSelect()` call sites in `populateAllDropdowns()` to pass `listKey` and translated placeholders**

Find lines matching (via `grep -n "populateSelect('dd-extension'\|populateSelect('dd-religion'\|populateSelect('dd-ip'\|populateSelect('dd-education'" public/js/profiling.js`) and replace:

```js
  populateSelect('dd-extension', globalData.List_Extension, t('ph_none'));
  populateSelect('dd-religion', globalData.List_Religion, t('ph_select_religion'), 'List_Religion');
  populateSelect('dd-ip', globalData.List_IP, t('ph_select_ip'), 'List_IP');
  populateSelect('dd-education', globalData.List_Education, t('ph_select_education'), 'List_Education');
```

(List_Extension values are Jr./Sr./II/III/IV/V/None — all universal short tokens; per the "basic dropdowns stay English" spirit these could arguably skip translation, but since `ph_none` is already a shared key from Step 1 of this task, pass it for the placeholder only and do not add a `List_Extension` entry to `I18N_TL_DROPDOWNS` — the fallback in `translateOption`/no `listKey` argument means it renders as-is, English, which is correct here since it wasn't called out for translation in the spec's scope and its values are not descriptive phrases.)

- [ ] **Step 8: Update `initializeLocationDropdowns()`, `updateProvinces()`, `updateCities()`, `updateBarangays()` placeholders**

Find each function (grep for `function initializeLocationDropdowns\|function updateProvinces\|function updateCities\|function updateBarangays`) and replace their hardcoded `'Select Region'`, `'Select Province'`, `'Select City'`, `'Select Barangay'` string literals with `t('ph_select_region')`, `t('ph_select_province')`, `t('ph_select_city')`, `t('ph_select_barangay')` respectively, at each of the locations identified in the research inventory (lines ~1784, ~1810-1812, ~1839-1840, ~1865). Do not translate the actual region/province/city/barangay names pulled from `locationData` — only these default/placeholder option strings.

- [ ] **Step 9: Verify syntax**

```bash
node --check public/js/profiling.js
node --check public/js/i18n/en.js
node --check public/js/i18n/tl.js
```

Expected: no output.

- [ ] **Step 10: Re-run key-parity check (Task 2 Step 3's script)**

Expected: both arrays empty.

- [ ] **Step 11: Manual browser verification**

With a valid session, navigate to Step 3. Confirm in English: region/province/city/barangay cascading selects still populate and cascade correctly (pick a region, confirm province populates, etc. — this exercises `updateProvinces`/`updateCities` which you just touched), Religion/IP/Education dropdowns show correctly, and Male/Female toggle still says "Male"/"Female" literally. Switch to `dialect=tl`, refresh, re-enter Step 1-2 quickly to reach Step 3 again, and confirm: all labels/headings are Tagalog, Religion/IP/Education dropdown *options* are now Tagalog (e.g. "Romano Katoliko"), but Male/Female toggle still reads "Male"/"Female" in English (per scope), and location dropdowns still show real place names unchanged.

- [ ] **Step 12: Commit**

```bash
git add public/js/profiling.js public/js/i18n/en.js public/js/i18n/tl.js
git commit -m "$(cat <<'EOF'
Translate Step 3 (Child Profile) including Religion/IP/Education dropdowns

Adds listKey-based label translation to populateSelect() while keeping
option values canonical. Sex (Male/Female) and location names remain
untranslated per spec.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 6: Convert Step 4 (Family Profile) and `getFamilyMemberCardHTML()`

**Files:**
- Modify: `public/js/profiling.js` — `getStep4HTML()`, `getFamilyMemberCardHTML()`, `removeMember()`, `toggleMemberVisibility()`, `addFirstFamilyMember()`, `addFamilyMember()` (grep each by name to find current line numbers before editing)
- Modify: `public/js/i18n/en.js`, `public/js/i18n/tl.js`

**Interfaces:**
- Consumes: `t()` from Task 1.
- Produces: keys `step4_heading`, `step4_subtext`, `step4_sec_family_size`, `step4_helper_family_size`, `step4_btn_add_member`, `step4_btn_next`, `member_title_head` (template with `{n}`), `member_title` (template with `{n}`), `member_btn_remove`, `member_btn_hide`, `member_btn_show`, `member_lbl_fullname`, `member_ph_fullname`, `member_lbl_relationship`, `member_lbl_solo_parent`, `member_lbl_claimant`, `member_lbl_civil_status`, `member_lbl_age`, `member_ph_age`, `member_lbl_sex`, `member_lbl_occupation`, `member_lbl_occ_class`, `member_lbl_disability`, `member_lbl_illness`, `ph_select_occupation`, `ph_select_class`. Shared `btn_back`, `btn_yes`, `btn_no`, `ph_loading`, `ph_select_ellipsis` reused from Task 1.
- IMPORTANT: Relationship-to-head options (Head/Spouse/Child/Parent/Sibling/Grandparent/Grandchild/Other Relative), Civil Status options (Single/Married/Widowed/Separated/Live-in), and Sex (Male/Female) stay English-only per Global Constraints — do not wrap these `<option>`/toggle-label texts in `t()`.

- [ ] **Step 1: Add Step 4 and family-card keys to `public/js/i18n/en.js`**

```js
  // Step 4: Family Profile
  step4_heading: 'Family Profile',
  step4_subtext: 'Provide details regarding family members living in the household.',
  step4_sec_family_size: 'Current Family Size',
  step4_helper_family_size: 'Auto-calculated based on listed members.',
  step4_btn_add_member: 'Add Another Family Member',
  step4_btn_next: 'Next: Socio Economic',

  member_title_head: 'Member #{n} (Head of Family)',
  member_title: 'Member #{n}',
  member_btn_remove: 'Remove',
  member_btn_hide: 'Hide',
  member_btn_show: 'Show',
  member_lbl_fullname: 'Full Name',
  member_ph_fullname: 'Enter Full Name',
  member_lbl_relationship: 'Relationship to Head',
  member_lbl_solo_parent: 'Solo Parent',
  member_lbl_claimant: 'Authorized Claimant',
  member_lbl_civil_status: 'Civil Status',
  member_lbl_age: 'Age',
  member_ph_age: 'Age',
  member_lbl_sex: 'Sex',
  member_lbl_occupation: 'Occupation',
  member_lbl_occ_class: 'Occupation Class',
  member_lbl_disability: 'Disability/Special Needs',
  member_lbl_illness: 'Critical Illness',
  ph_select_occupation: 'Select Occupation',
  ph_select_class: 'Select Class',
  ph_select_status: 'Select Status',
```

- [ ] **Step 2: Add matching keys to `public/js/i18n/tl.js`**

```js
  // Step 4: Family Profile
  step4_heading: 'Profile ng Pamilya',
  step4_subtext: 'Ibigay ang detalye ng mga miyembro ng pamilyang naninirahan sa bahay.',
  step4_sec_family_size: 'Kasalukuyang Sukat ng Pamilya',
  step4_helper_family_size: 'Awtomatikong nakukwenta batay sa nakalistang mga miyembro.',
  step4_btn_add_member: 'Magdagdag ng Ibang Miyembro ng Pamilya',
  step4_btn_next: 'Susunod: Sosyo-Ekonomiko',

  member_title_head: 'Miyembro #{n} (Puno ng Pamilya)',
  member_title: 'Miyembro #{n}',
  member_btn_remove: 'Alisin',
  member_btn_hide: 'Itago',
  member_btn_show: 'Ipakita',
  member_lbl_fullname: 'Buong Pangalan',
  member_ph_fullname: 'Ilagay ang Buong Pangalan',
  member_lbl_relationship: 'Kaugnayan sa Puno ng Pamilya',
  member_lbl_solo_parent: 'Solo Parent',
  member_lbl_claimant: 'Awtorisadong Claimant',
  member_lbl_civil_status: 'Katayuang Sibil',
  member_lbl_age: 'Edad',
  member_ph_age: 'Edad',
  member_lbl_sex: 'Kasarian',
  member_lbl_occupation: 'Trabaho',
  member_lbl_occ_class: 'Klasipikasyon ng Trabaho',
  member_lbl_disability: 'Kapansanan/Espesyal na Pangangailangan',
  member_lbl_illness: 'Malubhang Sakit',
  ph_select_occupation: 'Pumili ng Trabaho',
  ph_select_class: 'Pumili ng Klase',
  ph_select_status: 'Pumili ng Katayuan',
```

- [ ] **Step 3: Locate exact current function boundaries**

```bash
grep -n "^function getStep4HTML\|^function getFamilyMemberCardHTML\|^function removeMember\|^function toggleMemberVisibility\|^function addFirstFamilyMember\|^function addFamilyMember" public/js/profiling.js
```

- [ ] **Step 4: Convert `getStep4HTML()`**

Replace the function (found via Step 3) with:

```js
function getStep4HTML() {
  return `
    <div id="step-4" class="step-section hidden-step w-full space-y-5">
      <div class="w-full text-left space-y-0.5">
        <h1 class="font-extrabold text-brand-dark text-xl sm:text-2xl">${t('step4_heading')}</h1>
        <p class="text-gray-500 text-xs sm:text-sm">${t('step4_subtext')}</p>
      </div>
      
      <section class="w-full bg-blue-50 rounded-xl border border-blue-200 shadow-sm p-5 sm:p-6 flex items-center justify-between">
        <div>
          <h3 class="font-bold text-brand-dark text-base sm:text-lg">${t('step4_sec_family_size')}</h3>
          <p class="text-xs text-gray-500">${t('step4_helper_family_size')}</p>
        </div>
        <div class="w-20">
          <input id="total-family-size" name="total-family-size" type="number" readonly value="1" class="w-full h-12 text-center text-xl font-bold text-brand-blue bg-white rounded-lg border border-blue-100 outline-none">
        </div>
      </section>
      
      <div id="family-members-container" class="space-y-5">
        <!-- Family member cards will be added here -->
      </div>
      
      <button onclick="addFamilyMember()" class="w-full py-3 border-2 border-dashed border-brand-blue text-brand-blue rounded-xl font-bold text-sm hover:bg-blue-50 transition-colors flex items-center justify-center gap-2">
        <span class="material-symbols-outlined text-[20px]">person_add</span>
        ${t('step4_btn_add_member')}
      </button>
      
      <div class="w-full flex justify-between pb-6">
        <button onclick="goToStep(3)" class="px-4 h-10 bg-white border border-gray-300 rounded-lg text-gray-700 font-bold text-xs sm:text-sm hover:bg-gray-50 flex items-center gap-2 transition-all">
          <span class="material-symbols-outlined text-[16px]">arrow_back</span> ${t('btn_back')}
        </button>
        <button onclick="if(validateStep(4)) goToStep(5)" class="px-4 h-10 bg-brand-blue rounded-lg text-white font-bold text-xs sm:text-sm hover:bg-brand-blueHover flex items-center gap-2 shadow-md transition-all">
          ${t('step4_btn_next')}
          <span class="w-6 h-6 rounded-md bg-white/20 flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[16px]">arrow_forward</span></span>
        </button>
      </div>
    </div>
  `;
}
```

Note: read the actual current button/onclick text for "Next" beyond the `person_add` button before replacing — the research inventory did not capture the exact trailing `</div>` structure past line 716; preserve whatever back/next button markup currently exists (matching the same pattern as Steps 1-3) and only change the translatable text nodes, keeping all `onclick`/id/class attributes exactly as they are today.

- [ ] **Step 5: Convert `getFamilyMemberCardHTML(num, isHead)`**

Read the function's current body first (it's long — lines ~2274-2419 per research), then replace only the translatable text nodes, following this pattern (adapt to the exact current markup/attributes, changing only text content):

```js
function getFamilyMemberCardHTML(num, isHead) {
  const title = isHead ? t('member_title_head', { n: num }) : t('member_title', { n: num });
  return `
    <div class="member-card ..." id="member-card-${num}">
      <div class="...">
        <h3 class="...">${title}</h3>
        <button onclick="removeMember(${num})" class="...">${t('member_btn_remove')}</button>
        <button onclick="toggleMemberVisibility(${num})" class="..." id="member-toggle-btn-${num}">${t('member_btn_hide')}</button>
      </div>
      <label class="...">${t('member_lbl_fullname')} ...</label>
      <input ... placeholder="${t('member_ph_fullname')}">
      <label class="...">${t('member_lbl_relationship')} ...</label>
      <select id="dd-fam-rel-${num}" ...>
        ${isHead
          ? `<option value="Head" selected>Head</option>`
          : `<option value="Spouse" selected>Spouse</option>
             <option value="Spouse">Spouse</option>
             <option value="Child">Child</option>
             <option value="Parent">Parent</option>
             <option value="Sibling">Sibling</option>
             <option value="Grandparent">Grandparent</option>
             <option value="Grandchild">Grandchild</option>
             <option value="Other Relative">Other Relative</option>`}
      </select>
      <label class="...">${t('member_lbl_solo_parent')}</label>
      ... Yes/No toggle: keep as literal "Yes"/"No" text per Global Constraints ...
      <label class="...">${t('member_lbl_claimant')}</label>
      ... Yes/No toggle: keep as literal "Yes"/"No" text per Global Constraints ...
      <label class="...">${t('member_lbl_civil_status')}</label>
      <select id="dd-fam-civil-${num}" ...>
        <option value="" disabled selected>Select Status</option>
        <option value="Single">Single</option>
        <option value="Married">Married</option>
        <option value="Widowed">Widowed</option>
        <option value="Separated">Separated</option>
        <option value="Live-in">Live-in</option>
      </select>
      <label class="...">${t('member_lbl_age')}</label>
      <input ... placeholder="${t('member_ph_age')}">
      <label class="...">${t('member_lbl_sex')}</label>
      ... Male/Female toggle: keep as literal "Male"/"Female" text per Global Constraints ...
      <label class="...">${t('member_lbl_occupation')}</label>
      <select id="dd-fam-occ-${num}" ...><option value="" disabled selected>${t('ph_loading')}</option></select>
      <label class="...">${t('member_lbl_occ_class')}</label>
      <select id="dd-fam-class-${num}" ...><option value="" disabled selected>${t('ph_loading')}</option></select>
      <label class="...">${t('member_lbl_disability')}</label>
      <button id="dd-fam-dis-${num}-btn" ...><span id="disp-fam-dis-${num}">${t('ph_select_ellipsis')}</span></button>
      <div id="dd-fam-dis-${num}" ...>${t('ph_loading')}</div>
      <label class="...">${t('member_lbl_illness')}</label>
      <button id="dd-fam-ill-${num}-btn" ...><span id="disp-fam-ill-${num}">${t('ph_select_ellipsis')}</span></button>
      <div id="dd-fam-ill-${num}" ...>${t('ph_loading')}</div>
    </div>
  `;
}
```

**This is a template, not literal replacement text** — you must open the actual current function body, keep every existing attribute/id/class/onclick exactly as-is, and only swap the translatable text nodes shown above (headings, labels, buttons, placeholders) for the corresponding `t()` calls. Do not alter the Relationship-to-head, Civil Status, or Sex/Solo-Parent/Claimant Yes-No option text — those stay literal English per Global Constraints.

- [ ] **Step 6: Update `removeMember()`'s renumbering title text**

Find the two hardcoded title strings (`'Member #1 (Head of Family)'` and the `` `Member #${currentCount}` `` template) and replace with:

```js
t('member_title_head', { n: 1 })
```
and
```js
t('member_title', { n: currentCount })
```
respectively, at their exact existing locations within `removeMember()`.

- [ ] **Step 7: Update `toggleMemberVisibility()`'s Hide/Show text**

Replace the hardcoded `'Hide'` and `'Show'` string assignments with `t('member_btn_hide')` and `t('member_btn_show')` at their existing locations.

- [ ] **Step 8: Update `addFirstFamilyMember()` and `addFamilyMember()` placeholder arguments**

Replace `"Select Occupation"` → `t('ph_select_occupation')` and `"Select Class"` → `t('ph_select_class')` at all four call sites (two in each function) identified in the research inventory.

- [ ] **Step 9: Verify syntax**

```bash
node --check public/js/profiling.js
node --check public/js/i18n/en.js
node --check public/js/i18n/tl.js
```

- [ ] **Step 10: Re-run key-parity check**

Expected: both arrays empty.

- [ ] **Step 11: Manual browser verification**

Reach Step 4 with a valid session. Confirm: the first family member card (the head) renders with "Member #1 (Head of Family)" and correct English labels; clicking "Add Another Family Member" adds a second card titled "Member #2"; Relationship/Civil Status/Sex options remain in English; removing a member correctly renumbers remaining cards' titles. Switch to `dialect=tl`, refresh and re-navigate to Step 4, and confirm the same interactions now show Tagalog labels/buttons/titles while Relationship/Civil Status/Sex options remain English.

- [ ] **Step 12: Commit**

```bash
git add public/js/profiling.js public/js/i18n/en.js public/js/i18n/tl.js
git commit -m "$(cat <<'EOF'
Translate Step 4 (Family Profile) and family member card template

Relationship-to-head, Civil Status, and Sex options remain English per
scope. Member card titles use a parameterized member_title[_head] key.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 7: Convert Step 5 (Socio Economic) with Materials/Tenure/Electricity/Water/Toilet/Garbage dropdown translations

**Files:**
- Modify: `public/js/profiling.js` — `getStep5HTML()` (grep to find current lines; was 722-867)
- Modify: `public/js/profiling.js:1681-1686` region of `populateAllDropdowns()` (grep to confirm current lines)
- Modify: `public/js/i18n/en.js`, `public/js/i18n/tl.js`

**Interfaces:**
- Consumes: `t()`, `translateOption()`.
- Produces: keys `step5_heading`, `step5_subtext`, `step5_sec_housing`, `step5_lbl_materials`, `step5_lbl_tenure`, `step5_lbl_modifications`, `step5_lbl_electricity`, `step5_sec_water`, `step5_lbl_water`, `step5_sec_sanitation`, `step5_lbl_toilet`, `step5_lbl_toilet_access`, `step5_lbl_garbage`, `step5_btn_next`, plus `ph_select_materials`, `ph_select_tenure`, `ph_select_electricity`, `ph_select_water`, `ph_select_toilet`, `ph_select_garbage`. `I18N_TL_DROPDOWNS.List_Materials`, `.List_Tenure`, `.List_Electricity`, `.List_Water`, `.List_Toilet`, `.List_Garbage` translation tables. Reuses `btn_yes`/`btn_no`/`ph_please_specify`/`btn_back` from Task 1.
- IMPORTANT: The "Is the toilet accessible for the child?" Yes/No and "modifications" Yes/No are the excluded universal Yes/No toggles — keep literal.

- [ ] **Step 1: Read the actual current `List_Tenure`, `List_Water`, `List_Toilet`, `List_Garbage` full arrays**

```bash
grep -n "List_Tenure\|List_Electricity\|List_Water\|List_Toilet\|List_Garbage" -A 15 api/admin-router.php | head -100
```

Use this output as the authoritative source for building the `I18N_TL_DROPDOWNS` entries in Step 3 below — the plan's example values must match the real array contents exactly (some Toilet/Water options were truncated in earlier research and must be re-read here in full before translating).

- [ ] **Step 2: Add Step 5 keys to `public/js/i18n/en.js`**

```js
  // Step 5: Socio Economic
  step5_heading: 'Socio Economic',
  step5_subtext: "Detail the household's living conditions, assets, and financial resources.",
  step5_sec_housing: 'Housing Condition',
  step5_lbl_materials: 'What type of construction materials are the roofs and outer walls made of?',
  step5_lbl_tenure: 'What is the tenure status of the house and lot does the family have?',
  step5_lbl_modifications: "Are there any modifications in the house to accommodate the child's disability?",
  step5_lbl_electricity: 'What is the main source of electricity in the dwelling place?',
  step5_sec_water: 'Water Supply',
  step5_lbl_water: "What is your family's main source of water supply?",
  step5_sec_sanitation: 'Sanitation',
  step5_lbl_toilet: 'Main type of toilet facility',
  step5_lbl_toilet_access: 'Is the toilet accessible for the child?',
  step5_lbl_garbage: 'Main system of garbage disposal',
  step5_btn_next: 'Next: Health',

  ph_select_materials: 'Select Materials',
  ph_select_tenure: 'Select Status',
  ph_select_electricity: 'Select Source',
  ph_select_water: 'Select Water Source',
  ph_select_toilet: 'Select Toilet Type',
  ph_select_garbage: 'Select System',
```

- [ ] **Step 3: Add matching keys and dropdown tables to `public/js/i18n/tl.js`**

```js
  // Step 5: Socio Economic
  step5_heading: 'Sosyo-Ekonomiko',
  step5_subtext: 'Ilarawan ang kalagayan ng pamumuhay, ari-arian, at mapagkukunang pinansyal ng sambahayan.',
  step5_sec_housing: 'Kalagayan ng Tirahan',
  step5_lbl_materials: 'Anong uri ng materyales ang gamit sa bubong at panlabas na dingding?',
  step5_lbl_tenure: 'Ano ang katayuan ng pagmamay-ari ng bahay at lote ng pamilya?',
  step5_lbl_modifications: 'Mayroon bang mga pagbabago sa bahay upang maakma sa kapansanan ng bata?',
  step5_lbl_electricity: 'Ano ang pangunahing pinagkukunan ng kuryente sa tirahan?',
  step5_sec_water: 'Suplay ng Tubig',
  step5_lbl_water: 'Ano ang pangunahing pinagkukunan ng suplay ng tubig ng pamilya?',
  step5_sec_sanitation: 'Sanitasyon',
  step5_lbl_toilet: 'Pangunahing uri ng palikuran',
  step5_lbl_toilet_access: 'Naa-access ba ng bata ang palikuran?',
  step5_lbl_garbage: 'Pangunahing sistema ng pagtatapon ng basura',
  step5_btn_next: 'Susunod: Kalusugan',

  ph_select_materials: 'Pumili ng Materyales',
  ph_select_tenure: 'Pumili ng Katayuan',
  ph_select_electricity: 'Pumili ng Pinagkukunan',
  ph_select_water: 'Pumili ng Pinagkukunan ng Tubig',
  ph_select_toilet: 'Pumili ng Uri ng Palikuran',
  ph_select_garbage: 'Pumili ng Sistema',
```

```js
// merge into the existing window.I18N_TL_DROPDOWNS object from Task 5:
  List_Materials: {
    'Strong materials (Concrete, brick, stone)': 'Matibay na materyales (Konkreto, tisa, bato)',
    'Light materials (Wood, bamboo, nipa)': 'Magaan na materyales (Kahoy, kawayan, nipa)',
    'Mixed strong and light materials': 'Halong matibay at magaan na materyales',
    'Salvaged/Makeshift materials': 'Salbaheng/Pansamantalang materyales',
    'Others': 'Iba Pa',
  },
  List_Tenure: {
    'Own house and lot': 'Sariling bahay at lote',
    'Own house, rented lot': 'Sariling bahay, inuupahang lote',
    'Rent house and lot': 'Umuupa ng bahay at lote',
    'Rent-free with consent of owner': 'Walang bayad, may pahintulot ng may-ari',
    'Informal settler': 'Impormal na nanininirahan',
    'Others': 'Iba Pa',
  },
  List_Electricity: {
    'Electricity from distribution company': 'Kuryente mula sa distribution company',
    'Community electricity system': 'Sistema ng kuryente ng komunidad',
    'Solar panel': 'Solar panel',
    'Generator set': 'Generator set',
    'Kerosene lamp/Candles': 'Ilawang gaas/Kandila',
    'No electricity': 'Walang kuryente',
    'Others': 'Iba Pa',
  },
  List_Water: {
    'Own use, faucet, community water system': 'Sariling gamit, gripo, water system ng komunidad',
    'Own use, faucet, NAWASA/Water district': 'Sariling gamit, gripo, NAWASA/Water district',
    'Own use, tubed/piped deep well': 'Sariling gamit, tubo/deep well',
    'Shared, faucet, community water system': 'Ibinabahagi, gripo, water system ng komunidad',
    'Shared, faucet, NAWASA/Water district': 'Ibinabahagi, gripo, NAWASA/Water district',
    'Shared, tubed/piped deep well': 'Ibinabahagi, tubo/deep well',
    'Public tap/standpipe': 'Pampublikong gripo',
    'Tubed/piped shallow well': 'Mababaw na balon (tubo)',
    'Dug/open well': 'Hukay/bukas na balon',
    'Spring, lake, river, rain': 'Bukal, lawa, ilog, ulan',
    'Peddler, bottled water': 'Tagabenta, tubig na de-boteMedya',
    'Others': 'Iba Pa',
  },
  List_Toilet: {
    // populate exactly from the Step 1 grep output — read the real full list
    // from api/admin-router.php before filling this in; do not guess entries
    // not seen in that output.
  },
  List_Garbage: {
    // same — populate from the real array read in Step 1 of this task.
  },
```

Fill in `List_Toilet` and `List_Garbage` using the exact strings retrieved from the Step 1 grep command — translate each into natural Tagalog following the style of the other tables (formal but plain-language, matching DSWD field-worker terminology).

- [ ] **Step 4: Locate and convert `getStep5HTML()`**

```bash
grep -n "^function getStep5HTML" public/js/profiling.js
```

Replace the function's translatable text nodes (headings, field-question labels, section headings, next/back buttons, and the "Select X" default option text and "Please specify" placeholders) with the corresponding `t()` calls from Steps 2-3 above, following the exact same conversion pattern used in Task 5's `getStep3HTML()` conversion — open the current function body and swap only text content, keeping all ids/classes/onclick handlers unchanged. Leave the two Yes/No toggle pairs (`modifications` and `toilet accessible`) as literal `Yes`/`No` text per Global Constraints.

- [ ] **Step 5: Update the six `populateSelect()` calls for Materials/Tenure/Electricity/Water/Toilet/Garbage**

Find the calls (grep `populateSelect('dd-materials'\|populateSelect('dd-tenure'\|populateSelect('dd-electricity'\|populateSelect('dd-water'\|populateSelect('dd-toilet'\|populateSelect('dd-garbage'`) and update each to pass the translated placeholder and its `listKey`:

```js
  populateSelect('dd-materials', globalData.List_Materials, t('ph_select_materials'), 'List_Materials');
  populateSelect('dd-tenure', globalData.List_Tenure, t('ph_select_tenure'), 'List_Tenure');
  populateSelect('dd-electricity', globalData.List_Electricity, t('ph_select_electricity'), 'List_Electricity');
  populateSelect('dd-water', globalData.List_Water, t('ph_select_water'), 'List_Water');
  populateSelect('dd-toilet', globalData.List_Toilet, t('ph_select_toilet'), 'List_Toilet');
  populateSelect('dd-garbage', globalData.List_Garbage, t('ph_select_garbage'), 'List_Garbage');
```

- [ ] **Step 6: Verify syntax**

```bash
node --check public/js/profiling.js
node --check public/js/i18n/en.js
node --check public/js/i18n/tl.js
```

- [ ] **Step 7: Re-run key-parity check**

Expected: both arrays empty.

- [ ] **Step 8: Manual browser verification**

Reach Step 5 in English, confirm all six dropdowns populate with correct English descriptive text and the two Yes/No toggles work. Switch to `dialect=tl`, refresh, re-navigate to Step 5, confirm headings/labels/placeholders are Tagalog and dropdown options show translated Tagalog phrases, with Yes/No toggles still in English.

- [ ] **Step 9: Commit**

```bash
git add public/js/profiling.js public/js/i18n/en.js public/js/i18n/tl.js
git commit -m "$(cat <<'EOF'
Translate Step 5 (Socio Economic) and its six List_* dropdown labels

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 8: Convert Step 6 (Health) and Step 7 (Education)

**Files:**
- Modify: `public/js/profiling.js` — `getStep6HTML()`, `getStep7HTML()` (grep for current lines)
- Modify: `public/js/i18n/en.js`, `public/js/i18n/tl.js`

**Interfaces:**
- Consumes: `t()`.
- Produces: keys `step6_heading`, `step6_subtext`, `step6_sec_general`, `step6_lbl_vaccinations`, `step6_lbl_ongoing_condition`, `step6_sec_expenses`, `step6_lbl_food`, `step6_lbl_medication`, `step6_lbl_therapy`, `step6_lbl_hygiene`, `step6_lbl_assistive`, `step6_lbl_other_health`, `step6_sec_total_expense`, `step6_helper_total_expense`, `step6_sec_access`, `step6_lbl_availed_6mo`, `step6_lbl_facility_accessible`, `step6_lbl_barriers`, `step6_btn_next`, and `step7_heading`, `step7_subtext`, `step7_sec_status`, `step7_lbl_enrolled`, `step7_lbl_grade`, `step7_ph_grade`, `step7_lbl_why_not`, `step7_sec_accessibility`, `step7_lbl_school_accessible`, `step7_lbl_sped`, `step7_lbl_learning_support`, `step7_btn_next`. Reuses shared `btn_yes`/`btn_no`/`ph_please_specify`/`btn_back`.

- [ ] **Step 1: Add Step 6 and Step 7 keys to `public/js/i18n/en.js`**

```js
  // Step 6: Health
  step6_heading: 'Health',
  step6_subtext: "Provide information on the child's medical condition and healthcare accessibility.",
  step6_sec_general: 'General Health',
  step6_lbl_vaccinations: 'Has the child received all recommended vaccinations?',
  step6_lbl_ongoing_condition: 'Does the child have any ongoing health conditions?',
  step6_sec_expenses: 'Monthly Health Expenses',
  step6_lbl_food: 'Food',
  step6_lbl_medication: 'Medication',
  step6_lbl_therapy: 'Therapy',
  step6_lbl_hygiene: 'Hygiene-related needs',
  step6_lbl_assistive: 'Assistive Device Maint.',
  step6_lbl_other_health: 'Other health needs',
  step6_sec_total_expense: 'Total Health Expense',
  step6_helper_total_expense: 'Sum of all monthly costs',
  step6_sec_access: 'Access to Health Services',
  step6_lbl_availed_6mo: 'Has the child availed health services in the past 6 months?',
  step6_lbl_facility_accessible: 'Is the health facility accessible for the child?',
  step6_lbl_barriers: 'Are there any barriers to accessing health care services?',
  step6_btn_next: 'Next: Education',

  // Step 7: Education
  step7_heading: 'Education',
  step7_subtext: "Identify the child's schooling status and the physical accessibility of their learning environment.",
  step7_sec_status: 'Educational Status',
  step7_lbl_enrolled: 'Is the child currently enrolled in school?',
  step7_lbl_grade: 'Grade/Year Level',
  step7_ph_grade: 'Enter Grade/Year Level',
  step7_lbl_why_not: 'Why not?',
  step7_sec_accessibility: 'School Accessibility',
  step7_lbl_school_accessible: 'Is the school equipped with physically accessibility features?',
  step7_lbl_sped: 'Are there special education programs available?',
  step7_lbl_learning_support: 'Does the child receive any learning support?',
  step7_btn_next: 'Next: Economic Capacity',
```

- [ ] **Step 2: Add matching keys to `public/js/i18n/tl.js`**

```js
  // Step 6: Health
  step6_heading: 'Kalusugan',
  step6_subtext: 'Ibigay ang impormasyon tungkol sa kondisyong medikal ng bata at accessibility ng healthcare.',
  step6_sec_general: 'Pangkalahatang Kalusugan',
  step6_lbl_vaccinations: 'Natanggap ba ng bata ang lahat ng inirerekomendang bakuna?',
  step6_lbl_ongoing_condition: 'May mga patuloy na kondisyon ba sa kalusugan ang bata?',
  step6_sec_expenses: 'Buwanang Gastos sa Kalusugan',
  step6_lbl_food: 'Pagkain',
  step6_lbl_medication: 'Gamot',
  step6_lbl_therapy: 'Therapy',
  step6_lbl_hygiene: 'Pangangailangan sa Kalinisan',
  step6_lbl_assistive: 'Pagpapanatili ng Assistive Device',
  step6_lbl_other_health: 'Ibang pangangailangang pangkalusugan',
  step6_sec_total_expense: 'Kabuuang Gastos sa Kalusugan',
  step6_helper_total_expense: 'Kabuuan ng lahat ng buwanang gastos',
  step6_sec_access: 'Access sa mga Serbisyong Pangkalusugan',
  step6_lbl_availed_6mo: 'Nagamit ba ng bata ang serbisyong pangkalusugan sa nakalipas na 6 na buwan?',
  step6_lbl_facility_accessible: 'Naa-access ba ng bata ang health facility?',
  step6_lbl_barriers: 'May mga hadlang ba sa pag-access sa serbisyong pangkalusugan?',
  step6_btn_next: 'Susunod: Edukasyon',

  // Step 7: Education
  step7_heading: 'Edukasyon',
  step7_subtext: 'Tukuyin ang katayuan sa pag-aaral ng bata at ang physical accessibility ng kanilang kapaligiran sa pag-aaral.',
  step7_sec_status: 'Katayuan sa Edukasyon',
  step7_lbl_enrolled: 'Kasalukuyan bang naka-enroll ang bata sa paaralan?',
  step7_lbl_grade: 'Baitang/Antas',
  step7_ph_grade: 'Ilagay ang Baitang/Antas',
  step7_lbl_why_not: 'Bakit hindi?',
  step7_sec_accessibility: 'Accessibility ng Paaralan',
  step7_lbl_school_accessible: 'May mga accessibility feature ba ang paaralan?',
  step7_lbl_sped: 'May available bang special education program?',
  step7_lbl_learning_support: 'Nakatatanggap ba ang bata ng anumang learning support?',
  step7_btn_next: 'Susunod: Kapasidad Pang-ekonomiya',
```

- [ ] **Step 3: Locate and convert `getStep6HTML()` and `getStep7HTML()`**

```bash
grep -n "^function getStep6HTML\|^function getStep7HTML" public/js/profiling.js
```

Convert both functions' translatable text nodes (headings, subtexts, section headings, field-question labels, helper text, placeholders, next/back buttons) to `t()` calls using the keys added above, following the same conversion pattern as prior tasks — open each function body, keep every id/class/onclick unchanged, swap only text content. All Yes/No toggle pairs in both steps stay literal English per Global Constraints.

- [ ] **Step 4: Verify syntax**

```bash
node --check public/js/profiling.js
node --check public/js/i18n/en.js
node --check public/js/i18n/tl.js
```

- [ ] **Step 5: Re-run key-parity check**

Expected: both arrays empty.

- [ ] **Step 6: Manual browser verification**

Reach Steps 6 and 7 in English, confirm all field questions/labels/expense inputs render correctly. Switch to `dialect=tl`, refresh, re-navigate, confirm Tagalog text renders correctly with Yes/No toggles remaining English.

- [ ] **Step 7: Commit**

```bash
git add public/js/profiling.js public/js/i18n/en.js public/js/i18n/tl.js
git commit -m "$(cat <<'EOF'
Translate Step 6 (Health) and Step 7 (Education)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 9: Convert Step 8 (Economic Capacity) and Step 9 (Service Availment, including its inline challenges dropdown)

**Files:**
- Modify: `public/js/profiling.js` — `getStep8HTML()`, `getStep9HTML()`, `calculateIncomeClass()` (grep for current lines)
- Modify: `public/js/i18n/en.js`, `public/js/i18n/tl.js`

**Interfaces:**
- Consumes: `t()`.
- Produces: keys `step8_heading`, `step8_subtext`, `step8_sec_financial`, `step8_lbl_income_source`, `step8_ph_income_source`, `step8_lbl_monthly_income`, `step8_sec_income_class`, `step8_ph_income_class_initial`, `step8_income_class_low`, `step8_income_class_middle`, `step8_income_class_high`, `step8_sec_employment`, `step8_lbl_employed`, `step8_btn_next`, and `step9_heading`, `step9_subtext`, `step9_sec_social`, `step9_lbl_financial_assistance`, `step9_lbl_aware_services`, `step9_lbl_availed_services`, `step9_sec_barriers`, `step9_lbl_challenges`, `ph_select_challenge`, `challenge_lack_awareness`, `challenge_financial`, `challenge_distance`, `challenge_requirements`, `challenge_none`, `step9_btn_next`. This is the one place `calculateIncomeClass()`'s runtime strings get converted too.

- [ ] **Step 1: Add Step 8/9 keys to `public/js/i18n/en.js`**

```js
  // Step 8: Economic Capacity
  step8_heading: 'Economic Capacity',
  step8_subtext: "Assess the household's financial resources and the employment status of family members.",
  step8_sec_financial: 'Financial Information',
  step8_lbl_income_source: 'What is the primary source of income for the family?',
  step8_ph_income_source: 'e.g., Employment, Business, Remittance',
  step8_lbl_monthly_income: 'How much is the approximate monthly income of the family?',
  step8_sec_income_class: 'Income Classification',
  step8_ph_income_class_initial: 'Enter income to see classification',
  step8_income_class_low: 'Below Minimum / Low Income',
  step8_income_class_middle: 'Middle Income',
  step8_income_class_high: 'Above Moderate / Upper Income',
  step8_sec_employment: 'Employment',
  step8_lbl_employed: 'Are the parents/guardians employed or have entrepreneurial activities?',
  step8_btn_next: 'Next: Service Availment',

  // Step 9: Service Availment
  step9_heading: 'Service Availment',
  step9_subtext: 'Identify the government or private sector services the child has previously accessed.',
  step9_sec_social: 'Social Services',
  step9_lbl_financial_assistance: 'Does the family receive any form of financial assistance?',
  step9_lbl_aware_services: 'Is the family aware of available social services for children with disabilities?',
  step9_lbl_availed_services: 'Has the family availed of any services?',
  step9_sec_barriers: 'Barriers to Service Availment',
  step9_lbl_challenges: 'What are the challenges faced in availing these services?',
  ph_select_challenge: 'Select Challenge',
  challenge_lack_awareness: 'Lack of awareness',
  challenge_financial: 'Financial constraints',
  challenge_distance: 'Distance/Transportation',
  challenge_requirements: 'Requirements/Documents',
  challenge_none: 'None',
  step9_btn_next: 'Next: Assessment',
```

- [ ] **Step 2: Add matching keys to `public/js/i18n/tl.js`**

```js
  // Step 8: Economic Capacity
  step8_heading: 'Kapasidad Pang-ekonomiya',
  step8_subtext: 'Suriin ang mapagkukunang pinansyal ng sambahayan at katayuan sa trabaho ng mga miyembro ng pamilya.',
  step8_sec_financial: 'Impormasyong Pinansyal',
  step8_lbl_income_source: 'Ano ang pangunahing pinagmumulan ng kita ng pamilya?',
  step8_ph_income_source: 'hal., Trabaho, Negosyo, Remittance',
  step8_lbl_monthly_income: 'Magkano ang tinatayang buwanang kita ng pamilya?',
  step8_sec_income_class: 'Klasipikasyon ng Kita',
  step8_ph_income_class_initial: 'Ilagay ang kita upang makita ang klasipikasyon',
  step8_income_class_low: 'Mababa sa Minimum / Mababang Kita',
  step8_income_class_middle: 'Katamtamang Kita',
  step8_income_class_high: 'Mataas sa Katamtaman / Mataas na Kita',
  step8_sec_employment: 'Trabaho',
  step8_lbl_employed: 'Ang mga magulang/tagapag-alaga ba ay may trabaho o negosyo?',
  step8_btn_next: 'Susunod: Paggamit ng Serbisyo',

  // Step 9: Service Availment
  step9_heading: 'Paggamit ng Serbisyo',
  step9_subtext: 'Tukuyin ang mga serbisyo mula sa gobyerno o pribadong sektor na naranasang ma-access ng bata.',
  step9_sec_social: 'Mga Serbisyong Panlipunan',
  step9_lbl_financial_assistance: 'Tumatanggap ba ang pamilya ng anumang uri ng tulong pinansyal?',
  step9_lbl_aware_services: 'Alam ba ng pamilya ang mga magagamit na serbisyong panlipunan para sa mga batang may kapansanan?',
  step9_lbl_availed_services: 'Nagamit na ba ng pamilya ang anumang serbisyo?',
  step9_sec_barriers: 'Mga Hadlang sa Paggamit ng Serbisyo',
  step9_lbl_challenges: 'Ano ang mga hamong kinakaharap sa paggamit ng mga serbisyong ito?',
  ph_select_challenge: 'Pumili ng Hamon',
  challenge_lack_awareness: 'Kakulangan sa kaalaman',
  challenge_financial: 'Limitasyong pinansyal',
  challenge_distance: 'Distansya/Transportasyon',
  challenge_requirements: 'Mga Kinakailangan/Dokumento',
  challenge_none: 'Wala',
  step9_btn_next: 'Susunod: Pagtatasa',
```

- [ ] **Step 3: Locate and convert `getStep8HTML()`**

```bash
grep -n "^function getStep8HTML" public/js/profiling.js
```

Convert its translatable text nodes to `t()` calls using the Step 8 keys above, following the same pattern as prior tasks. The Yes/No toggle for "employed" stays literal English.

- [ ] **Step 4: Locate and convert `getStep9HTML()`, including the inline challenges `<select>`**

```bash
grep -n "^function getStep9HTML" public/js/profiling.js
```

Convert headings/labels/placeholders to `t()`. For the inline hardcoded `<select id="service-challenges">` block (not sourced from `globalData`), replace its literal `<option>` text with `t()` calls while preserving the exact `value="..."` attributes (values must stay canonical English so `collectFormData()` is unaffected):

```html
<select id="service-challenges" ...>
  <option value="" disabled selected>${t('ph_select_challenge')}</option>
  <option value="Lack of awareness">${t('challenge_lack_awareness')}</option>
  <option value="Financial constraints">${t('challenge_financial')}</option>
  <option value="Distance/Transportation">${t('challenge_distance')}</option>
  <option value="Requirements/Documents">${t('challenge_requirements')}</option>
  <option value="Others (Specify)">${t('option_others_specify')}</option>
  <option value="None">${t('challenge_none')}</option>
</select>
```

All three Yes/No toggle pairs in Step 9 stay literal English.

- [ ] **Step 5: Convert `calculateIncomeClass()`'s runtime strings**

```bash
grep -n "^function calculateIncomeClass" public/js/profiling.js
```

Replace the four hardcoded string assignments (`"Enter income to see classification"`, `"Below Minimum / Low Income"`, `"Middle Income"`, `"Above Moderate / Upper Income"`) with `t('step8_ph_income_class_initial')`, `t('step8_income_class_low')`, `t('step8_income_class_middle')`, `t('step8_income_class_high')` respectively, at each exact assignment location.

- [ ] **Step 6: Verify syntax**

```bash
node --check public/js/profiling.js
node --check public/js/i18n/en.js
node --check public/js/i18n/tl.js
```

- [ ] **Step 7: Re-run key-parity check**

Expected: both arrays empty.

- [ ] **Step 8: Manual browser verification**

Reach Step 8, type a monthly income figure, and confirm the income classification text updates live and correctly in English. Reach Step 9, confirm the challenges dropdown shows correct English option text and that selecting an option and re-checking the underlying `<select>` value (via devtools) still shows the canonical English value (e.g. `"Lack of awareness"`, not a Tagalog string) even when in Tagalog mode. Switch to `dialect=tl`, refresh, re-navigate, and confirm Step 8/9 render Tagalog labels and income classification text, and the challenges dropdown shows Tagalog option *labels* while retaining English option *values*.

- [ ] **Step 9: Commit**

```bash
git add public/js/profiling.js public/js/i18n/en.js public/js/i18n/tl.js
git commit -m "$(cat <<'EOF'
Translate Step 8 (Economic Capacity) and Step 9 (Service Availment)

Includes calculateIncomeClass() runtime strings and the inline service
challenges dropdown, whose option values stay canonical English while
labels translate.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 10: Convert Step 10 (Assessment) and Step 11 shell (`getStep11HTML()`)

**Files:**
- Modify: `public/js/profiling.js` — `getStep10HTML()`, `getStep11HTML()` (grep for current lines)
- Modify: `public/js/i18n/en.js`, `public/js/i18n/tl.js`

**Interfaces:**
- Consumes: `t()`.
- Produces: keys `step10_heading`, `step10_subtext`, `step10_sec_notes`, `step10_lbl_strengths`, `step10_ph_strengths`, `step10_lbl_assessment`, `step10_ph_assessment`, `step10_lbl_recommendations`, `step10_ph_recommendations`, `step10_sec_readiness`, `readiness_severe_title`, `readiness_severe_desc`, `readiness_moderate_title`, `readiness_moderate_desc`, `readiness_low_title`, `readiness_low_desc`, `readiness_stable_title`, `readiness_stable_desc`, `step10_btn_review`, `step11_heading`, `step11_subtext`, `step11_btn_edit`, `step11_btn_submit`.
- IMPORTANT: These four `readiness_*` keys are the single source of truth also reused by `generateReview()` in Task 11 (per the spec's note about eliminating the duplicate `readinessMap` in `generateReview`) — get the exact English wording right here since Task 11 depends on it. Note the research found a wording discrepancy between `getStep10HTML()`'s "Low: No immediate action needed, but regular monitoring" and `generateReview()`'s `readinessMap` "Low: No immediate action needed, but monitor regularly" — standardize on the `getStep10HTML()` wording ("regular monitoring") as canonical, since it's the source the user actually sees when picking the option.

- [ ] **Step 1: Add Step 10/11 keys to `public/js/i18n/en.js`**

```js
  // Step 10: Assessment
  step10_heading: 'General Observations and Recommendations',
  step10_subtext: "Provide a summary of the child's situation and specific steps for intervention.",
  step10_sec_notes: 'Assessment Notes',
  step10_lbl_strengths: 'Strengths',
  step10_ph_strengths: 'Enter key strengths...',
  step10_lbl_assessment: 'Assessment',
  step10_ph_assessment: 'Provide assessment details...',
  step10_lbl_recommendations: 'Recommended Actions/Interventions',
  step10_ph_recommendations: 'Suggest interventions...',
  step10_sec_readiness: 'Readiness Score',
  readiness_severe_title: 'Severe: Immediate intervention needed',
  readiness_severe_desc: 'The well-being of the child and family is at a critical level, requiring urgent and immediate intervention.',
  readiness_moderate_title: 'Moderate: Address within a short period',
  readiness_moderate_desc: 'The well-being shows significant areas of concern that need to be addressed in the short term.',
  readiness_low_title: 'Low: No immediate action needed, but regular monitoring',
  readiness_low_desc: "The child and family's well-being is generally adequate, with some minor issues requiring attention.",
  readiness_stable_title: 'Stable: Meets all needs effectively',
  readiness_stable_desc: "The child and family's well-being is satisfactory and functioning as expected.",
  step10_btn_review: 'Review Assessment',

  // Step 11: Review
  step11_heading: 'Review Assessment',
  step11_subtext: 'Please verify all information before final submission.',
  step11_btn_edit: 'Edit Forms',
  step11_btn_submit: 'Submit Assessment',
```

- [ ] **Step 2: Add matching keys to `public/js/i18n/tl.js`**

```js
  // Step 10: Assessment
  step10_heading: 'Pangkalahatang Obserbasyon at Rekomendasyon',
  step10_subtext: 'Ibigay ang buod ng kalagayan ng bata at partikular na hakbang para sa interbensyon.',
  step10_sec_notes: 'Mga Tala sa Pagtatasa',
  step10_lbl_strengths: 'Mga Kalakasan',
  step10_ph_strengths: 'Ilagay ang mga pangunahing kalakasan...',
  step10_lbl_assessment: 'Pagtatasa',
  step10_ph_assessment: 'Ibigay ang detalye ng pagtatasa...',
  step10_lbl_recommendations: 'Inirerekomendang Aksyon/Interbensyon',
  step10_ph_recommendations: 'Imungkahi ang mga interbensyon...',
  step10_sec_readiness: 'Readiness Score',
  readiness_severe_title: 'Malubha: Kailangan ng agarang interbensyon',
  readiness_severe_desc: 'Ang kagalingan ng bata at pamilya ay nasa kritikal na antas, kailangan ng agaran at mabilisang interbensyon.',
  readiness_moderate_title: 'Katamtaman: Tutugunan sa loob ng maikling panahon',
  readiness_moderate_desc: 'Ang kagalingan ay nagpapakita ng mahahalagang bahagi ng alalahanin na kailangang tugunan sa maikling panahon.',
  readiness_low_title: 'Mababa: Walang kailangang agarang aksyon, ngunit regular na subaybayan',
  readiness_low_desc: 'Ang kagalingan ng bata at pamilya ay sapat, may ilang menor na isyu na nangangailangan ng pansin.',
  readiness_stable_title: 'Matatag: Natutugunan lahat ng pangangailangan nang epektibo',
  readiness_stable_desc: 'Ang kagalingan ng bata at pamilya ay kasiya-siya at umaandar nang inaasahan.',
  step10_btn_review: 'Suriin ang Pagtatasa',

  // Step 11: Review
  step11_heading: 'Suriin ang Pagtatasa',
  step11_subtext: 'Pakisuri ang lahat ng impormasyon bago ang huling pagsusumite.',
  step11_btn_edit: 'I-edit ang mga Form',
  step11_btn_submit: 'Isumite ang Pagtatasa',
```

- [ ] **Step 3: Locate and convert `getStep10HTML()` and `getStep11HTML()`**

```bash
grep -n "^function getStep10HTML\|^function getStep11HTML" public/js/profiling.js
```

Convert both functions' translatable text nodes to `t()` calls using the keys above, following the same conversion pattern as prior tasks. `getStep11HTML()` is short (just a shell — heading, subtext, and two buttons that trigger `generateReview()`), so this should be a small, direct swap.

- [ ] **Step 4: Verify syntax**

```bash
node --check public/js/profiling.js
node --check public/js/i18n/en.js
node --check public/js/i18n/tl.js
```

- [ ] **Step 5: Re-run key-parity check**

Expected: both arrays empty.

- [ ] **Step 6: Manual browser verification**

Reach Step 10, confirm the four readiness score cards show correct English titles/descriptions and the notes textareas show correct placeholders. Click through to Step 11 and confirm the shell heading/buttons render (the review content itself isn't converted until Task 11, so it may still show English or partial content at this point — that's expected). Switch to `dialect=tl`, refresh, re-navigate, and confirm Step 10's readiness cards and Step 11's shell render Tagalog text.

- [ ] **Step 7: Commit**

```bash
git add public/js/profiling.js public/js/i18n/en.js public/js/i18n/tl.js
git commit -m "$(cat <<'EOF'
Translate Step 10 (Assessment) and Step 11 shell

Establishes the readiness_* keys as the single source of truth for
readiness score wording, to be reused by generateReview() next.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 11: Convert `generateReview()` (Step 11's dynamic review content) and validation messages

**Files:**
- Modify: `public/js/profiling.js` — `generateReview()`, `validateStep()`, `chkDropdownOther()`, `chkToggleSpecify()`, `chkName()`, `chkPhone()`, and all `validateStepN()` functions (grep for current lines)
- Modify: `public/js/i18n/en.js`, `public/js/i18n/tl.js`

**Interfaces:**
- Consumes: `t()` — specifically reuses `readiness_severe_title`/`readiness_moderate_title`/`readiness_low_title`/`readiness_stable_title` from Task 10 for the review screen's readiness display, and reuses every `stepN_lbl_*`/`stepN_sec_*` key already defined in Tasks 4-10 wherever `generateReview()` currently duplicates that label text, instead of creating new review-only keys for duplicated labels.
- Produces: new keys only for review-screen text that has no earlier equivalent: `review_heading`, `review_subtext`, `review_group_1` .. `review_group_10` (the numbered section headers), `review_no_members`, `review_member_fallback` (template with `{n}`), `review_yes_prefix` (template with `{detail}`), `review_no_fallback`, `review_enrolled_yes` (template with `{grade}`), `review_enrolled_no` (template with `{reason}`). Also produces the shared validation templates: `val_required` (template `{field}`), `val_min_length` (template `{field}`,`{n}`), `val_max_length` (template `{field}`,`{n}`), `val_please_select` (template `{field}`), `val_please_specify` (template `{field}`), `val_letters_only` (template `{field}`), `val_fix_errors`, `val_fix_errors_title`, plus every field-label key referenced by a validator (e.g. `fieldlbl_household_id`, `fieldlbl_relationship`, etc.) and every step-specific literal validation message (e.g. `val_step1_hh_required`, `val_step3_dob_required`, etc. — full list below).

This is the second-largest task. Do it in the order given: shared validator templates first (highest leverage), then per-step validators, then `generateReview()` last (since it reuses many keys from earlier tasks plus the new `review_*` keys).

- [ ] **Step 1: Add validation template and field-label keys to `public/js/i18n/en.js`**

```js
  // Shared validation message templates
  val_required: '{field} is required',
  val_min_length: '{field} must be at least {n} characters',
  val_max_length: '{field} must not exceed {n} characters',
  val_please_select: 'Please select {field}',
  val_please_specify: 'Please specify {field}',
  val_letters_only: '{field} can only contain letters, spaces, hyphens, and apostrophes',
  val_fix_errors: 'Please fix the highlighted errors before proceeding.',
  val_fix_errors_title: 'Validation Error',
  val_phone_format: '{field} must be in format: 09XX XXX XXXX',

  // Field label tokens used inside validation messages (kept distinct
  // from display labels above so a validator can name a field even if
  // its on-screen label text differs slightly in phrasing)
  fieldlbl_household_id: 'Household ID',
  fieldlbl_name: 'Name of Respondent',
  fieldlbl_first_name: 'First Name',
  fieldlbl_last_name: 'Last Name',
  fieldlbl_middle_name: 'Middle Name',
  fieldlbl_street_address: 'Street address',
  fieldlbl_contact_number: 'Contact number',
  fieldlbl_date_of_birth: 'Date of birth',
  fieldlbl_religion: 'religion',
  fieldlbl_ip_group: 'IP group',
  fieldlbl_ip_status: 'IP membership status',
  fieldlbl_education: 'educational attainment',
  fieldlbl_disability: 'disability/special need',
  fieldlbl_illness: 'critical illness',
  fieldlbl_housing_materials: 'housing materials',
  fieldlbl_tenure_status: 'tenure status',
  fieldlbl_modification_details: 'modification details',
  fieldlbl_electricity_source: 'electricity source',
  fieldlbl_water_source: 'water source',
  fieldlbl_toilet_type: 'toilet type',
  fieldlbl_garbage_system: 'garbage disposal system',
  fieldlbl_health_condition_details: 'health condition details',
  fieldlbl_services_availed_6mo: 'services availed in 6 months',
  fieldlbl_healthcare_barrier_details: 'healthcare barrier details',
  fieldlbl_accessibility_feature_details: 'accessibility feature details',
  fieldlbl_sped_details: 'SPED program details',
  fieldlbl_learning_support_details: 'learning support details',
  fieldlbl_employment_details: 'employment details',
  fieldlbl_financial_assistance_details: 'financial assistance details',
  fieldlbl_service_awareness_details: 'service awareness details',
  fieldlbl_availed_services_details: 'availed services details',
  fieldlbl_service_challenge: 'service challenge',
  fieldlbl_strengths: 'Strengths',
  fieldlbl_assessment: 'Assessment',
  fieldlbl_recommendations: 'Recommendations',
  fieldlbl_region: 'region',
  fieldlbl_province: 'province',
  fieldlbl_city: 'city',
  fieldlbl_barangay: 'barangay',

  // Step-specific literal validation messages (not covered by the shared templates)
  val_step1_hh_required: 'Household ID is required for 4Ps members',
  val_step1_hh_length: 'Household ID must be 13 to 18 characters',
  val_step2_relationship: 'Please select a relationship',
  val_step2_email_invalid: 'Please enter a valid email (e.g., name@example.com)',
  val_step2_email_length: 'Email must not exceed 255 characters',
  val_step2_contact_format: 'Contact number must be 11 digits starting with 09',
  val_step3_middle_name_length: 'Middle Name must not exceed 100 characters',
  val_step3_middle_name_letters: 'Middle Name can only contain letters, spaces, and hyphens',
  val_step3_street_required: 'Street address is required',
  val_step3_street_min: 'Street address must be at least 5 characters',
  val_step3_street_max: 'Street address must not exceed 255 characters',
  val_step3_contact_format: 'Contact number must be 11 digits starting with 09',
  val_step3_dob_required: 'Date of birth is required',
  val_step3_dob_future: 'Date of birth cannot be in the future',
  val_step3_disability_required: 'Please select at least one disability/special need',
  val_step3_illness_required: 'Please select at least one critical illness (or select "None")',
  val_step3_illness_specify: 'Please specify the critical illness',
  val_step4_member_name_required: 'Member #{n}: Full name is required',
  val_step4_member_name_min: 'Member #{n}: Name must be at least 2 characters',
  val_step4_member_name_letters: 'Member #{n}: Name can only contain letters, spaces, and hyphens',
  val_step4_member_relationship: 'Member #{n}: Relationship is required',
  val_step4_member_civil_status: 'Member #{n}: Civil status is required',
  val_step4_member_age_required: 'Member #{n}: Age is required',
  val_step4_member_age_range: 'Member #{n}: Age must be 0–150',
  val_step4_member_occupation: 'Member #{n}: Occupation is required',
  val_step4_member_occ_class: 'Member #{n}: Occupation class is required',
  val_step4_member_disability: 'Member #{n}: Select at least one disability/special need',
  val_step4_member_illness: 'Member #{n}: Select at least one critical illness',
  val_step7_grade_required: 'Grade/Year level is required for enrolled children',
  val_step7_grade_max: 'Grade/Year level must not exceed 50 characters',
  val_step7_reason_required: 'Please provide a reason for not being enrolled',
  val_step7_reason_max: 'Reason must not exceed 500 characters',
  val_step8_income_source_required: 'Primary income source is required',
  val_step8_income_source_min: 'Income source must be at least 3 characters',
  val_step8_income_source_max: 'Income source must not exceed 255 characters',
  val_step8_income_required: 'Monthly income is required',
  val_step8_income_whole_number: 'Monthly income must be a whole number',
  val_step10_min_length_10: '{field} must be at least 10 characters',
  val_step10_max_length_2000: '{field} must not exceed 2000 characters',
  val_step10_readiness_required: 'Please select a readiness score',

  // Submission
  val_submission_failed: 'Submission failed',
  val_submission_error_body: 'Could not reach the server. Please try again.',
  val_submission_error_title: 'Submission Error',
```

- [ ] **Step 2: Add matching keys to `public/js/i18n/tl.js`**

```js
  // Shared validation message templates
  val_required: 'Kinakailangan ang {field}',
  val_min_length: 'Ang {field} ay dapat hindi bababa sa {n} na karakter',
  val_max_length: 'Ang {field} ay hindi dapat lumagpas sa {n} na karakter',
  val_please_select: 'Pumili ng {field}',
  val_please_specify: 'Pakisaad ang {field}',
  val_letters_only: 'Ang {field} ay dapat mga letra, espasyo, gitling, at kudlit lamang',
  val_fix_errors: 'Pakiayos ang mga naka-highlight na error bago magpatuloy.',
  val_fix_errors_title: 'Error sa Validation',
  val_phone_format: 'Ang {field} ay dapat nasa format na: 09XX XXX XXXX',

  fieldlbl_household_id: 'Household ID',
  fieldlbl_name: 'Pangalan ng Respondent',
  fieldlbl_first_name: 'Pangalan',
  fieldlbl_last_name: 'Apelyido',
  fieldlbl_middle_name: 'Gitnang Pangalan',
  fieldlbl_street_address: 'Address (kalye)',
  fieldlbl_contact_number: 'Numero ng contact',
  fieldlbl_date_of_birth: 'Petsa ng kapanganakan',
  fieldlbl_religion: 'relihiyon',
  fieldlbl_ip_group: 'grupong IP',
  fieldlbl_ip_status: 'katayuan sa pagiging kasapi sa IP',
  fieldlbl_education: 'naabot na edukasyon',
  fieldlbl_disability: 'kapansanan/espesyal na pangangailangan',
  fieldlbl_illness: 'malubhang sakit',
  fieldlbl_housing_materials: 'materyales ng bahay',
  fieldlbl_tenure_status: 'katayuan ng pagmamay-ari',
  fieldlbl_modification_details: 'detalye ng pagbabago',
  fieldlbl_electricity_source: 'pinagkukunan ng kuryente',
  fieldlbl_water_source: 'pinagkukunan ng tubig',
  fieldlbl_toilet_type: 'uri ng palikuran',
  fieldlbl_garbage_system: 'sistema ng pagtatapon ng basura',
  fieldlbl_health_condition_details: 'detalye ng kondisyon sa kalusugan',
  fieldlbl_services_availed_6mo: 'serbisyong nagamit sa 6 buwan',
  fieldlbl_healthcare_barrier_details: 'detalye ng hadlang sa healthcare',
  fieldlbl_accessibility_feature_details: 'detalye ng accessibility feature',
  fieldlbl_sped_details: 'detalye ng SPED program',
  fieldlbl_learning_support_details: 'detalye ng learning support',
  fieldlbl_employment_details: 'detalye ng trabaho',
  fieldlbl_financial_assistance_details: 'detalye ng tulong pinansyal',
  fieldlbl_service_awareness_details: 'detalye ng kamalayan sa serbisyo',
  fieldlbl_availed_services_details: 'detalye ng nagamit na serbisyo',
  fieldlbl_service_challenge: 'hamon sa serbisyo',
  fieldlbl_strengths: 'Mga Kalakasan',
  fieldlbl_assessment: 'Pagtatasa',
  fieldlbl_recommendations: 'Mga Rekomendasyon',
  fieldlbl_region: 'rehiyon',
  fieldlbl_province: 'probinsya',
  fieldlbl_city: 'lungsod',
  fieldlbl_barangay: 'barangay',

  val_step1_hh_required: 'Kinakailangan ang Household ID para sa mga miyembro ng 4Ps',
  val_step1_hh_length: 'Ang Household ID ay dapat 13 hanggang 18 na karakter',
  val_step2_relationship: 'Pumili ng kaugnayan',
  val_step2_email_invalid: 'Maglagay ng tamang email (hal., name@example.com)',
  val_step2_email_length: 'Ang email ay hindi dapat lumagpas sa 255 na karakter',
  val_step2_contact_format: 'Ang numero ng contact ay dapat 11 digit na nagsisimula sa 09',
  val_step3_middle_name_length: 'Ang Gitnang Pangalan ay hindi dapat lumagpas sa 100 na karakter',
  val_step3_middle_name_letters: 'Ang Gitnang Pangalan ay dapat mga letra, espasyo, at gitling lamang',
  val_step3_street_required: 'Kinakailangan ang address (kalye)',
  val_step3_street_min: 'Ang address ay dapat hindi bababa sa 5 na karakter',
  val_step3_street_max: 'Ang address ay hindi dapat lumagpas sa 255 na karakter',
  val_step3_contact_format: 'Ang numero ng contact ay dapat 11 digit na nagsisimula sa 09',
  val_step3_dob_required: 'Kinakailangan ang petsa ng kapanganakan',
  val_step3_dob_future: 'Ang petsa ng kapanganakan ay hindi maaaring nasa hinaharap',
  val_step3_disability_required: 'Pumili ng kahit isang kapansanan/espesyal na pangangailangan',
  val_step3_illness_required: 'Pumili ng kahit isang malubhang sakit (o piliin ang "Wala")',
  val_step3_illness_specify: 'Pakisaad ang malubhang sakit',
  val_step4_member_name_required: 'Miyembro #{n}: Kinakailangan ang buong pangalan',
  val_step4_member_name_min: 'Miyembro #{n}: Ang pangalan ay dapat hindi bababa sa 2 na karakter',
  val_step4_member_name_letters: 'Miyembro #{n}: Ang pangalan ay dapat mga letra, espasyo, at gitling lamang',
  val_step4_member_relationship: 'Miyembro #{n}: Kinakailangan ang kaugnayan',
  val_step4_member_civil_status: 'Miyembro #{n}: Kinakailangan ang katayuang sibil',
  val_step4_member_age_required: 'Miyembro #{n}: Kinakailangan ang edad',
  val_step4_member_age_range: 'Miyembro #{n}: Ang edad ay dapat 0–150',
  val_step4_member_occupation: 'Miyembro #{n}: Kinakailangan ang trabaho',
  val_step4_member_occ_class: 'Miyembro #{n}: Kinakailangan ang klasipikasyon ng trabaho',
  val_step4_member_disability: 'Miyembro #{n}: Pumili ng kahit isang kapansanan/espesyal na pangangailangan',
  val_step4_member_illness: 'Miyembro #{n}: Pumili ng kahit isang malubhang sakit',
  val_step7_grade_required: 'Kinakailangan ang baitang/antas para sa mga naka-enrol na bata',
  val_step7_grade_max: 'Ang baitang/antas ay hindi dapat lumagpas sa 50 na karakter',
  val_step7_reason_required: 'Magbigay ng dahilan kung bakit hindi naka-enrol',
  val_step7_reason_max: 'Ang dahilan ay hindi dapat lumagpas sa 500 na karakter',
  val_step8_income_source_required: 'Kinakailangan ang pangunahing pinagmumulan ng kita',
  val_step8_income_source_min: 'Ang pinagmumulan ng kita ay dapat hindi bababa sa 3 na karakter',
  val_step8_income_source_max: 'Ang pinagmumulan ng kita ay hindi dapat lumagpas sa 255 na karakter',
  val_step8_income_required: 'Kinakailangan ang buwanang kita',
  val_step8_income_whole_number: 'Ang buwanang kita ay dapat buong numero',
  val_step10_min_length_10: 'Ang {field} ay dapat hindi bababa sa 10 na karakter',
  val_step10_max_length_2000: 'Ang {field} ay hindi dapat lumagpas sa 2000 na karakter',
  val_step10_readiness_required: 'Pumili ng readiness score',

  val_submission_failed: 'Nabigo ang pagsusumite',
  val_submission_error_body: 'Hindi ma-reach ang server. Pakisubukang muli.',
  val_submission_error_title: 'Error sa Pagsusumite',
```

- [ ] **Step 3: Convert the shared validator helpers**

```bash
grep -n "^function chkDropdownOther\|^function chkToggleSpecify\|^function chkName\|^function chkPhone\|^function chkMulti\|^function validateStep\b" public/js/profiling.js
```

For each of `chkDropdownOther`, `chkToggleSpecify`, `chkName`, `chkPhone`, replace their hardcoded template-literal error strings with `t()` calls, e.g. (adapt to each function's actual current variable names):

```js
// chkDropdownOther, inside its body:
showFieldError(id, t('val_please_select', { field: label }));
// ...
showFieldError(id, t('val_please_specify', { field: label }));
// ...
showFieldError(id, t('val_max_length', { field: label, n: 255 }));
```

```js
// chkToggleSpecify:
showElemError(el, errId, t('val_please_specify', { field: label }));
// ...
showElemError(el, errId, t('val_max_length', { field: label, n: maxLen }));
```

```js
// chkName:
if (!val) return t('val_required', { field: label });
if (val.length < min) return t('val_min_length', { field: label, n: min });
if (val.length > max) return t('val_max_length', { field: label, n: max });
// letters-only check:
return t('val_letters_only', { field: label });
```

```js
// chkPhone:
if (!val) return t('val_required', { field: label });
// format check:
return t('val_phone_format', { field: label });
```

Preserve each function's existing control flow, parameter names, and return/side-effect mechanism (some use `return`, some call `showFieldError`/`showElemError` directly) — only replace the string literal being returned or passed.

- [ ] **Step 4: Convert `validateStep()`'s generic toast message**

```js
toast.warning(t('val_fix_errors'), t('val_fix_errors_title'));
```

at its existing call site.

- [ ] **Step 5: Convert each `validateStepN()` function's literal messages and label arguments**

For `validateStep1()` through `validateStep10()`, replace every hardcoded string identified in the research inventory with the corresponding `t()` call from Step 1/2's key lists above. Two important non-mechanical fixes required here:

**Step 3's derived-label anti-pattern** — replace this line (found at the location identified in research as validateStep3, handling region/province/city/barangay select validation):
```js
showFieldError(id, `Please select a ${id.replace('child-','').replace('-',' ')}`);
```
with an explicit lookup instead of deriving text from the DOM id:
```js
const fieldLabelKeys = {
  'child-region': 'fieldlbl_region',
  'child-province': 'fieldlbl_province',
  'child-city': 'fieldlbl_city',
  'child-barangay': 'fieldlbl_barangay',
};
showFieldError(id, t('val_please_select', { field: t(fieldLabelKeys[id]) }));
```
(Place the `fieldLabelKeys` object either as a local const inside the validator or, if the same id-to-field-name mapping is checked elsewhere in the function already, reuse whatever loop/array of ids already exists there rather than introducing a second one — read the actual current code structure before inserting this.)

**validateStep4's member-numbered messages** — replace each `` `Member #${n}: ...` `` template with the matching `val_step4_member_*` key and `{n}` variable, e.g.:
```js
errors.push(t('val_step4_member_name_required', { n }));
```

**validateStep2's `chkName` call** — where `chkName(nameVal, 'Name of Respondent', ...)` is called, change the literal label argument to `t('fieldlbl_name')`, and similarly for every other `chkName`/`chkPhone`/`chkDropdownOther`/`chkToggleSpecify` call site across all `validateStepN()` functions — replace the literal label string argument (e.g. `'housing materials'`, `'First Name'`, `'service challenge'`) with the matching `t('fieldlbl_*')` call so the label itself is translated before being interpolated into the shared templates.

For every other literal validation string not covered by the shared templates (e.g. `'Household ID is required for 4Ps members'`, `'Please select a relationship'`, `'Date of birth cannot be in the future'`, etc.), replace with the matching `val_step{N}_*` key from Step 1/2's list, called as plain `t('val_stepN_xyz')` (no variables needed for these).

- [ ] **Step 6: Convert `submitAssessment()`'s error text**

```bash
grep -n "^function submitAssessment" public/js/profiling.js
```

Replace:
```js
throw new Error(result.message || 'Submission failed');
```
with:
```js
throw new Error(result.message || t('val_submission_failed'));
```

And replace:
```js
window.toast.error(error.message || 'Could not reach the server. Please try again.', 'Submission Error');
```
with:
```js
window.toast.error(error.message || t('val_submission_error_body'), t('val_submission_error_title'));
```

(Per the spec, `error.message` itself may be a raw backend string when the backend *did* return a `message` field — that case is intentionally left as-is here; only the fallback strings are translated. A full backend-error-code mapping is out of scope for this plan per the Global Constraints — flag to the user as a possible follow-up if raw backend messages are observed during manual testing.)

- [ ] **Step 7: Add `review_*` keys to `public/js/i18n/en.js`**

```js
  review_heading: 'Assessment Review',
  review_subtext: 'Please verify all information before final submission',
  review_group_1: '1. Pre-Qualification',
  review_group_2: '2. Respondent Profile',
  review_group_3: '3. Child Profile',
  review_group_4: '4. Family Profile',
  review_group_5: '5. Socio Economic',
  review_group_6: '6. Health',
  review_group_7: '7. Education',
  review_group_8: '8. Economic Capacity',
  review_group_9: '9. Service Availment',
  review_group_10: '10. Assessment Notes',
  review_lbl_4ps_member: '4Ps Member',
  review_lbl_dob_sex: 'Date of Birth / Sex',
  review_lbl_contact_number: 'Contact Number',
  review_lbl_total_family_size: 'Total Family Size',
  review_no_members: 'No family members added',
  review_member_fallback: 'Member {n}',
  review_yes_prefix: 'Yes - {detail}',
  review_no_fallback: 'No',
  review_enrolled_yes: 'Yes - Grade/Year: {grade}',
  review_enrolled_no: 'No - Reason: {reason}',
  review_lbl_total_health_expense: 'Total Monthly Health Expense',
  review_helper_total_health_expense: 'Sum of all health-related costs',
  review_lbl_income_classification: 'Income Classification',
```

- [ ] **Step 8: Add matching `review_*` keys to `public/js/i18n/tl.js`**

```js
  review_heading: 'Rebyu ng Pagtatasa',
  review_subtext: 'Pakisuri ang lahat ng impormasyon bago ang huling pagsusumite',
  review_group_1: '1. Kwalipikasyon',
  review_group_2: '2. Profile ng Respondent',
  review_group_3: '3. Profile ng Bata',
  review_group_4: '4. Profile ng Pamilya',
  review_group_5: '5. Sosyo-Ekonomiko',
  review_group_6: '6. Kalusugan',
  review_group_7: '7. Edukasyon',
  review_group_8: '8. Kapasidad Pang-ekonomiya',
  review_group_9: '9. Paggamit ng Serbisyo',
  review_group_10: '10. Mga Tala sa Pagtatasa',
  review_lbl_4ps_member: 'Miyembro ng 4Ps',
  review_lbl_dob_sex: 'Petsa ng Kapanganakan / Kasarian',
  review_lbl_contact_number: 'Numero ng Contact',
  review_lbl_total_family_size: 'Kabuuang Sukat ng Pamilya',
  review_no_members: 'Walang idinagdag na miyembro ng pamilya',
  review_member_fallback: 'Miyembro {n}',
  review_yes_prefix: 'Oo - {detail}',
  review_no_fallback: 'Hindi',
  review_enrolled_yes: 'Oo - Baitang/Antas: {grade}',
  review_enrolled_no: 'Hindi - Dahilan: {reason}',
  review_lbl_total_health_expense: 'Kabuuang Buwanang Gastos sa Kalusugan',
  review_helper_total_health_expense: 'Kabuuan ng lahat ng gastos na may kinalaman sa kalusugan',
  review_lbl_income_classification: 'Klasipikasyon ng Kita',
```

- [ ] **Step 9: Convert `generateReview()`**

```bash
grep -n "^function generateReview" public/js/profiling.js
```

Read the full current function body (it's ~480 lines per research). Convert it in this order:

1. Replace the `readinessMap` object literal (research: lines ~2821-2824) with one that reads from the Task 10 keys instead of hardcoding its own copy:
```js
const readinessMap = {
  severe: t('readiness_severe_title'),
  moderate: t('readiness_moderate_title'),
  low: t('readiness_low_title'),
  stable: t('readiness_stable_title'),
};
```
(Match whatever the actual object's keys are named in the current code — e.g. they may be keyed by the radio `value` attributes like `'severe'`/`'moderate'`/`'low'`/`'stable'`, confirm by reading the code — this removes the wording-discrepancy duplicate flagged in Task 10.)

2. Replace the review screen's own heading/subtext (research: lines ~2840-2841) with `t('review_heading')` / `t('review_subtext')`.

3. Replace every numbered section group header (`'1. Pre-Qualification'` through `'10. Assessment Notes'`) with `t('review_group_1')` through `t('review_group_10')`.

4. Replace every field-label string in the review screen that duplicates a label already defined in an earlier task with a call to that *same* key rather than inventing a new `review_*` key — e.g. `'Household ID'` → `t('step1_lbl_household_id')`, `'Name'` → `t('step2_lbl_name')` (note: review screen abbreviates "Name of Respondent" to "Name" — check the actual current text; if it truly is the shorter "Name", either reuse `step2_lbl_name` if acceptable or add one small `review_lbl_name: 'Name'` / `'Pangalan'` key pair — prefer reuse unless the shortened wording matters for layout, in which case add the minimal extra key), `'Relationship'` → `t('step2_lbl_relationship')`, `'Email'` → `t('step2_lbl_email')`, `'Full Name'` → `t('member_lbl_fullname')`, `'Personal Information'` → `t('step3_sec_personal')`, `'Housing Condition'` → `t('step5_sec_housing')`, `'Construction Materials'` → `t('step5_lbl_materials')` (or a shorter `review_lbl_construction_materials` key if the review screen's phrasing genuinely differs — check first), and so on for every field label the research inventory flagged as a duplicate of step-form text. Use the `review_lbl_*` keys added in Steps 7-8 only for labels that have no earlier equivalent (4Ps Member, Date of Birth/Sex combined label, Contact Number as a standalone review row, Total Family Size, Total Monthly Health Expense + its helper text, Income Classification).

5. Replace the empty-state text `'No family members added'` with `t('review_no_members')`.

6. Replace the member-card fallback name template (`'Member ' + (index + 1)`) with `t('review_member_fallback', { n: index + 1 })`.

7. Replace every `` `Yes - ${detail}` `` / bare `'No'` pattern (there are ~10 occurrences per research, at lines ~3058, 3105, 3125, 3133, 3164, 3168, 3172, 3211, 3231, 3235, 3239) with `t('review_yes_prefix', { detail })` / `t('review_no_fallback')` respectively, at each site, substituting in that call site's actual `detail` variable name.

8. Replace the enrollment-specific pair (research line ~3154) with `t('review_enrolled_yes', { grade })` / `t('review_enrolled_no', { reason })`, substituting the actual variable names used at that site.

9. Leave the `toLocaleDateString('en-US', ...)` locale argument (research line ~2913) unchanged — per this plan's Global Constraints, date formatting localization is not in scope; only the surrounding label text should be translated, not the date format itself.

- [ ] **Step 10: Verify syntax**

```bash
node --check public/js/profiling.js
node --check public/js/i18n/en.js
node --check public/js/i18n/tl.js
```

- [ ] **Step 11: Re-run key-parity check**

Expected: both arrays empty.

- [ ] **Step 12: Manual browser verification — this is the most important check in the whole plan**

Complete an entire run-through of the profiling tool from Step 1 to Step 11 in English:
1. Fill in each step with valid data.
2. Deliberately trigger at least 5 different validation errors across different steps (e.g. leave Household ID blank on Step 1, enter an invalid email on Step 2, leave a family member's name blank on Step 4, leave Strengths blank on Step 10) and confirm each error message renders correctly in English with the right field name substituted in.
3. Reach Step 11 and confirm the review screen shows every section correctly with real data reflected (names, addresses, selected options, Yes/No answers with details, the readiness score you picked).
4. Repeat the entire run-through with `sessionStorage.setItem('dialect','tl')` set from the start (set it before Step 1, e.g. via devtools right after landing on `/profiling`, or by re-doing the login flow if a Tagalog option was wired at login) — confirm every validation error and the full review screen render correctly in Tagalog, with numeric/name placeholders (like `Member #2`, "must be at least 10 characters") correctly substituted.
5. Confirm the readiness score text on the review screen exactly matches what was shown on Step 10 (verifying the deduplication in Step 9.1 worked).

- [ ] **Step 13: Commit**

```bash
git add public/js/profiling.js public/js/i18n/en.js public/js/i18n/tl.js
git commit -m "$(cat <<'EOF'
Translate generateReview() and all validation messages

Deduplicates readiness score wording between Step 10 and the review
screen by having generateReview() read from the same readiness_* keys.
Fixes the Step 3 id-derived validation label to use an explicit lookup
instead of parsing the DOM id string.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 12: Convert remaining scattered runtime strings (combobox/multiselect empty states, "Others" dropdown labels in populateMulti, Disability/Illness dropdown translations)

**Files:**
- Modify: `public/js/profiling.js` — `initRelationshipCombobox()`, `initGoogleSelects()`, `updateMultiSelect()`, `populateMulti()` (grep for current lines)
- Modify: `public/js/i18n/en.js`, `public/js/i18n/tl.js`

**Interfaces:**
- Consumes: `t()`, `translateOption()`.
- Produces: no new keys beyond what Task 1 already defined (`txt_no_match_found`, `ph_select_ellipsis`, `txt_select_options_ellipsis`, `option_others_specify`) — this task only wires up remaining call sites to those existing keys, plus adds `I18N_TL_DROPDOWNS.List_Disability` and `.List_Illness` translation tables since `populateMulti()` is converted here.

- [ ] **Step 1: Add `List_Disability` and `List_Illness` translation tables to `public/js/i18n/tl.js`**

Merge into the existing `window.I18N_TL_DROPDOWNS` object:

```js
  List_Disability: {
    'None': 'Wala',
    'Visual Disability': 'Kapansanan sa Paningin',
    'Hearing Disability': 'Kapansanan sa Pandinig',
    'Speech and Language Impairment': 'Kapansanan sa Pananalita at Wika',
    'Orthopedic / Physical Disability': 'Orthopedic / Pisikal na Kapansanan',
    'Mental / Intellectual Disability': 'Mental / Intelektwal na Kapansanan',
    'Learning Disability': 'Kapansanan sa Pag-aaral',
    'Psychosocial Disability': 'Kapansanang Sikolohikal-Panlipunan',
    'Disability Resulting from a Chronic Illness': 'Kapansanang Dulot ng Malalang Sakit',
    'Multiple Disabilities': 'Maramihang Kapansanan',
    'Other (specify)': 'Iba Pa (tukuyin)',
  },
  List_Illness: {
    'None': 'Wala',
    'Cancer': 'Kanser',
    'Heart Disease': 'Sakit sa Puso',
    'Kidney Disease': 'Sakit sa Bato',
    'Diabetes': 'Diabetes',
    'Respiratory Disease': 'Sakit sa Paghinga',
    'Neurological Disorder': 'Kaguluhan sa Neurolohiya',
    'Blood Disorder': 'Kaguluhan sa Dugo',
    'Chronic Illness': 'Malalang Sakit',
    'Others': 'Iba Pa',
  },
```

- [ ] **Step 2: Locate and read `populateMulti()`'s exact current body**

```bash
grep -n "^function populateMulti" public/js/profiling.js
```

- [ ] **Step 3: Add a `listKey` parameter to `populateMulti()` mirroring Task 5's `populateSelect()` change**

Add a `listKey` parameter to the function signature, and wherever the function currently sets each checkbox/option's display label from the raw `item` string, change it to `listKey ? translateOption(listKey, item) : item` (mirroring the `populateSelect` pattern from Task 5 Step 6). Wherever the function currently hardcodes the "Others (Specify)" label (research flagged this at two locations inside `populateMulti`, mirroring the two in `populateSelect`), replace with `t('option_others_specify')`.

- [ ] **Step 4: Update every `populateMulti()` call site to pass the correct `listKey`**

```bash
grep -n "populateMulti(" public/js/profiling.js
```

For every call passing `globalData.List_Disability`, add `'List_Disability'` as the new trailing argument; for every call passing `globalData.List_Illness`, add `'List_Illness'`. This includes the calls in `populateAllDropdowns()` (dd-disability, dd-illness) and the four calls in `addFirstFamilyMember()`/`addFamilyMember()` (dd-fam-dis-1/dd-fam-ill-1 and their `${memberCount}` templated equivalents).

- [ ] **Step 5: Convert `updateMultiSelect()`'s runtime placeholder**

```bash
grep -n "^function updateMultiSelect" public/js/profiling.js
```

Replace the hardcoded `"Select options..."` assignment with `t('txt_select_options_ellipsis')` at its exact location.

- [ ] **Step 6: Convert the "No match found" empty-state text in `initRelationshipCombobox()` and `initGoogleSelects()`**

```bash
grep -n "^function initRelationshipCombobox\|^function initGoogleSelects" public/js/profiling.js
```

Replace both occurrences of the hardcoded `'No match found'` string with `t('txt_no_match_found')` at their exact locations, and the `'Select...'` fallback in `initGoogleSelects()` with `t('ph_select_ellipsis')`.

- [ ] **Step 7: Verify syntax**

```bash
node --check public/js/profiling.js
node --check public/js/i18n/en.js
node --check public/js/i18n/tl.js
```

- [ ] **Step 8: Re-run key-parity check**

Expected: both arrays empty.

- [ ] **Step 9: Manual browser verification**

In English: on Step 2, type a partial relationship into the combobox that matches nothing and confirm "No match found" still appears; on Step 3/4, open the Disability and Critical Illness multi-select dropdowns and confirm labels are correct English descriptive phrases and the "Select options..." placeholder shows before any selection. Switch to `dialect=tl`, refresh, re-navigate to the same points, and confirm: combobox empty-state now reads the Tagalog "Walang natagpuan", multi-select placeholder reads the Tagalog "Pumili ng mga opsyon...", and Disability/Illness option labels are now Tagalog while their underlying stored values (check via devtools/collected form data) remain the canonical English strings.

- [ ] **Step 10: Commit**

```bash
git add public/js/profiling.js public/js/i18n/tl.js
git commit -m "$(cat <<'EOF'
Translate Disability/Illness multi-select labels and remaining runtime
empty-state strings (combobox, multi-select placeholder)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 13: Final full-suite verification and cleanup pass

**Files:**
- No new modifications expected; this task verifies the cumulative result of Tasks 1-12 and fixes any stragglers found.

**Interfaces:**
- Consumes: everything produced by Tasks 1-12.
- Produces: a verified-complete feature ready for the user to review.

- [ ] **Step 1: Full-repository search for any remaining untranslated user-facing strings in `profiling.js`**

Run a broad grep to catch anything missed by the per-task passes:

```bash
grep -n '"[A-Z][a-z]\{3,\}' public/js/profiling.js | grep -v "t(" | grep -v "class=" | grep -v "console\." | head -80
```

Review the output by hand — this is a noisy heuristic (it will also flag legitimate non-UI strings like CSS class names, API endpoint paths, or DOM attribute values), but any genuinely user-facing English phrase found here that isn't wrapped in `t(...)` is a gap to fix using the same pattern as the task that should have covered it.

- [ ] **Step 2: Full-repository search for the same in `profiling.html`**

```bash
grep -n '>[A-Z][a-z]\{3,\}' public/profiling.html | grep -v "data-i18n"
```

Confirm nothing user-facing is left unattributed; if found, add the missing `data-i18n` attribute and corresponding dictionary keys following Task 3's pattern.

- [ ] **Step 3: Verify syntax of every touched file one final time**

```bash
node --check public/js/i18n.js
node --check public/js/i18n/en.js
node --check public/js/i18n/tl.js
node --check public/js/index.js
node --check public/js/profiling.js
```

Expected: no output from any command.

- [ ] **Step 4: Final key-parity check between `en.js` and `tl.js`**

Re-run the Task 2 Step 3 script one last time. Expected: both arrays empty.

- [ ] **Step 5: Full manual regression pass**

Run through the entire profiling tool twice more, once fully in English and once fully in Tagalog, from the login dialect picker through final submission (or as far as submission can be tested without a live backend — at minimum through the Step 11 review screen). Confirm:
- No `undefined`, `[object Object]`, or raw dictionary key names appear anywhere on screen in either language.
- The four excluded dropdowns (Sex, Civil Status, Relationship-to-head, and every Yes/No toggle) show English text in both dialect modes.
- Location dropdowns (region/province/city/barangay) show unmodified place names in both dialect modes.
- Switching `dialect` mid-session (without restarting) and calling `goToStep()` to re-render a step reflects the new language immediately (this validates that `t()` is called fresh on every render rather than cached at load time).

- [ ] **Step 6: Fix any issues found in Steps 1-5**

If any gaps are found, fix them directly following the established `t()`/`translateOption()` conversion pattern from earlier tasks — do not introduce a different mechanism.

- [ ] **Step 7: Commit final fixes (only if Step 6 found anything to fix)**

```bash
git add -A
git commit -m "$(cat <<'EOF'
Fix remaining untranslated strings found in final i18n regression pass

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

If Step 6 found nothing to fix, skip this commit — there is nothing to commit.

---

## Post-Implementation Note for the User

Bisaya/Cebuano was explicitly deferred per your instruction. To add it later: create `public/js/i18n/ceb.js` mirroring the exact key set in `en.js`/`tl.js` (the key-parity check script from Task 2 Step 3 can be adapted to compare against `ceb.js` too), add it to the `_dictFor`/`_dropdownDictFor` lookups in `i18n.js`, add a third radio option to the login picker from Task 3, and update `docs/superpowers/specs/2026-09-14-profiling-dialect-i18n-design.md` to reflect Bisaya shipping. No structural changes to `profiling.js` would be needed — every `t()`/`translateOption()` call site already reads whatever dialect is in `sessionStorage`.

Also flagged during planning but explicitly out of scope for this plan (per the spec): raw backend error messages from `beneficiaries-router.php` are not mapped/translated — only the frontend's own fallback error text is. If real backend error strings are observed in English during Tagalog-mode testing, that's expected per current scope, not a bug in this implementation.
