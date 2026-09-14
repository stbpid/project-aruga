# Profiling Tool Dialect Selection (English / Tagalog / Bisaya)

## Purpose

Interviewers currently use the profiling tool (`public/profiling.html` +
`public/js/profiling.js`) entirely in English. Field interviewers often work
with respondents who are more comfortable in Tagalog or Bisaya (Cebuano).
This feature lets the interviewer pick a dialect when logging in, and
renders all profiling-tool UI copy in that dialect for the rest of the
session.

## Scope

**In scope:**
- A dialect selector (English / Tagalog / Bisaya) added to the existing
  interviewer-code login screen (`index.html`, `#section-profiling`).
- Translating all profiling-tool UI copy: page headings, section headings,
  field labels/questions, placeholders, button text, helper/hint text,
  validation error messages, the step progress indicator, and the
  Step 11 review screen.
- Translating the descriptive `List_*` dropdown option labels sourced from
  `api/admin-router.php?action=options` (Religion, IP Group, Education,
  Disability, Illness, Extension, Occupation, Occupation Class, Materials,
  Tenure, Electricity, Water, Toilet, Garbage) — see "Dropdown option
  translation" below for how value vs. label is handled.
- Translating the small number of static, non-API dropdowns embedded
  directly in step HTML that are also descriptive (e.g. Step 9's service
  challenges list).
- Translating static text in `profiling.html` itself (header, logout
  modal, loading state).

**Explicitly out of scope (stays English / unchanged):**
- Location data (region/province/city/barangay) — official geographic
  names, never translated.
- The short, near-universal inline dropdowns that are hardcoded directly
  in `getFamilyMemberCardHTML()` and step markup rather than sourced from
  `globalData`: **Sex (Male/Female), Civil Status, Relationship-to-head,
  and Yes/No toggles.** These stay English-only.
- Data values submitted to the backend. The dialect only changes what is
  *displayed*; the value persisted via `collectFormData()` and sent to
  `beneficiaries-router.php` remains the canonical English string used
  today, so existing stored data and backend logic are unaffected.
- Server-side/backend error messages from `beneficiaries-router.php`.
  Known error messages get a translated mapping on the frontend; any
  unrecognized backend error text is shown as-is (English) rather than
  guessed at.
- Dashboard pages, admin tools, and every other part of the app outside
  the profiling tool.

## Approach

### 1. Dialect capture at login

Add a 3-option control (English / Tagalog / Bisaya) to `index.html`'s
`#section-profiling` block, near the interviewer code field. Default:
English.

On successful `submitForm()` in `index.js`, alongside the existing
`sessionStorage.setItem('interviewer_code', ...)` calls, add:

```js
sessionStorage.setItem('dialect', selectedDialect); // 'en' | 'tl' | 'ceb'
```

No backend change needed — the dialect is a pure client-side rendering
preference and is not sent to `auth-router.php`.

### 2. Translation dictionaries

New folder `public/js/i18n/` with three flat dictionary files:

- `en.js` — canonical English strings (also serves as the fallback and
  the authoritative list of every key that must exist).
- `tl.js` — Tagalog translations.
- `ceb.js` — Bisaya (Cebuano) translations.

Each file defines a global object, e.g.:

```js
// public/js/i18n/tl.js
window.I18N_TL = {
  step1_heading: 'Paunang Kwalipikasyon',
  step1_subtext: 'Kumpirmahin ang pagiging kasapi sa 4Ps...',
  member_title_head: 'Miyembro #{n} (Puno ng Pamilya)',
  member_title: 'Miyembro #{n}',
  err_field_required: '{field} ay kinakailangan',
  // ...
};
```

Keys are namespaced loosely by step (`step1_*`, `step4_*`, `review_*`,
`validation_*`, `dropdown_*`) so the mapping between a key and its origin
in `profiling.js` stays traceable. Placeholders use `{name}` tokens.

### 3. `i18n.js` runtime helper

New `public/js/i18n.js`, loaded after the three dictionaries and before
`profiling.js`:

```js
function t(key, vars) {
  const dialect = sessionStorage.getItem('dialect') || 'en';
  const dict = { en: window.I18N_EN, tl: window.I18N_TL, ceb: window.I18N_CEB }[dialect]
               || window.I18N_EN;
  let str = dict[key] ?? window.I18N_EN[key] ?? key; // fall back to EN, then the raw key
  if (vars) {
    for (const [k, v] of Object.entries(vars)) {
      str = str.replaceAll(`{${k}}`, v);
    }
  }
  return str;
}
```

Falling back to English (and ultimately to the raw key) means a missing
or not-yet-translated string never breaks rendering — it just shows
English or the key name, which is easy to spot during review.

`profiling.html` load order becomes:

```html
<script src="/js/i18n/en.js"></script>
<script src="/js/i18n/tl.js"></script>
<script src="/js/i18n/ceb.js"></script>
<script src="/js/i18n.js"></script>
<script src="/js/toast.js"></script>
<script src="/js/profiling.js"></script>
```

### 4. Refactoring `profiling.js` to use `t()`

- Every hardcoded string in `getStep1HTML()` … `getStep11HTML()` becomes
  `${t('key')}`.
- `generateReview()`'s duplicated label copies are replaced with calls to
  the *same* keys used by the step forms (e.g. the Step 1 heading key is
  reused in the review screen instead of a second hardcoded literal) —
  this removes the duplicate-source-of-truth problem found during
  research.
- `updateProgress()`'s `stepLabels` object and the
  `` `STEP ${n} OF 10` `` / `REVIEW` string become `t()` lookups
  (`t('step_of', {n})`, `t('step_review')`, etc.).
- `getFamilyMemberCardHTML()`'s `Member #${num}` / `Member #${num} (Head of
  Family)` title becomes `t('member_title', {n: num})` /
  `t('member_title_head', {n: num})`, and the duplicate copy in
  `removeMember()`'s renumbering logic is updated to call the same
  helper instead of re-literalizing the string.
- Validation messages: the shared helpers (`chkName`, `chkPhone`,
  `chkDropdownOther`, `chkToggleSpecify`, `chkMulti`, and the generic
  `${label} is required` family) are converted to message-template keys
  (`validation_required`, `validation_max_length`, `validation_specify`,
  etc.) taking the field label as a variable. The field `label` values
  themselves also become `t()` lookups instead of literals, so a single
  template produces correctly translated messages for any field.
- The Step 3 validator that derives its label from
  `id.replace('child-','').replace('-',' ')` is cleaned up to use an
  explicit label-key lookup instead, so it fits the same pattern as every
  other validator (flagged in research as the one inconsistent case).
- `calculateIncomeClass()` and `updateMultiSelect()`'s runtime-assigned
  strings (`"Below Minimum / Low Income"`, `"Select options..."`, etc.)
  become `t()` calls at the point of assignment.

### 5. Dropdown option translation (value vs. label split)

Today `populateSelect`/`populateMulti` use the API string as both the
`<option>` value and its visible text (`o.value = i; o.innerText = i;`).
To translate the label without changing the submitted value:

- Add a lookup table per translated list, keyed by the canonical English
  string (the value), e.g.:

  ```js
  // public/js/i18n/tl.js (excerpt)
  window.I18N_TL_DROPDOWNS = {
    List_Religion: {
      'No Religion': 'Walang Relihiyon',
      'Roman Catholic': 'Romano Katoliko',
      // ...
    },
    List_Materials: { /* ... */ },
    // one entry per translated List_*
  };
  ```

- `populateSelect(id, items, placeholder, listKey)` and `populateMulti(...)`
  gain an optional `listKey` argument. When present, the option's
  `innerText` is set via a small `translateOption(listKey, value)` helper
  that looks up the current dialect's table and falls back to the raw
  English value if no translation exists (e.g. respondent typed a custom
  "Others" value) — `value` itself is never changed.
- Call sites in `populateAllDropdowns()` pass the relevant `listKey`
  (`'List_Religion'`, `'List_Materials'`, etc.) for every translated
  list; the four excluded inline dropdowns (Sex, Civil Status,
  Relationship-to-head, Yes/No) are left calling the existing code path
  unchanged, so they keep rendering their current hardcoded English
  option markup with no `listKey`.
- The "Others (Specify)" special-case string (currently duplicated three
  times) becomes a single `t('option_others_specify')` call used by both
  `populateSelect` and `populateMulti`.
- No change to `api/admin-router.php` — the `List_*` arrays stay exactly
  as they are today (canonical English values); translation is purely a
  client-side rendering concern layered on top.

### 6. Static HTML text in `profiling.html`

Header brand text, the logout modal, the loading state, and the initial
(pre-JS) progress section text get `data-i18n="key"` attributes. A small
function in `i18n.js`, `applyStaticI18n()`, runs on `DOMContentLoaded`
before `loadAllSteps()` and sets `el.textContent = t(el.dataset.i18n)`
for every element with that attribute. `<html lang="en">` is updated to
the active dialect's language code (`en`/`tl`/`ceb` — note `ceb` is the
correct ISO 639 code for Cebuano/Bisaya) via a one-line
`document.documentElement.lang = dialect` in `i18n.js`.

### 7. Backend error message mapping

`submitAssessment()`'s catch block currently shows `error.message` (which
may be the raw backend string) directly. This is changed to look up the
backend's message against a small known-messages map
(`validation_backend_known_errors` in the dictionaries) and fall back to
a generic translated "Submission failed, please try again" message when
the backend string isn't recognized — so the interviewer never sees a
stray English sentence in an otherwise-Tagalog/Bisaya session, but we
also don't have to keep the frontend's error map in lockstep with every
possible backend message.

## Data flow summary

```
Login (index.html)
  → interviewer picks dialect
  → sessionStorage.dialect = 'tl' | 'ceb' | 'en'
  → redirect to /profiling

profiling.html loads
  → en.js / tl.js / ceb.js / i18n.js loaded
  → profiling.js DOMContentLoaded:
      applyStaticI18n()      (header, modal, loading text)
      loadAllSteps()         (getStepXHTML() calls now use t())
      updateProgress(1)      (uses t() for labels)

Throughout the session:
  every getStepXHTML(), generateReview(), validator, and dropdown
  populate call reads from t() / translateOption(), all keyed off
  sessionStorage.getItem('dialect') set once at login.
```

## Error handling

- Missing translation key → falls back to English string, then to the
  raw key string, never throws or renders blank.
- Missing/corrupt `sessionStorage.dialect` → treated as `'en'`.
- Unrecognized backend error message → generic translated fallback
  message shown instead of raw English text.

## Testing

No automated test suite exists in this project (per prior project
context — vanilla PHP/JS, no framework). Verification will be manual:
- Log in choosing each of the three dialects and step through all 11
  steps, confirming headings/labels/buttons/validation errors render in
  the chosen dialect.
- Confirm the four excluded dropdowns (Sex, Civil Status,
  Relationship-to-head, Yes/No) still show English options in every
  dialect.
- Confirm submitted data (checked via the review screen and, if
  possible, the resulting Supabase record) still contains the canonical
  English values regardless of dialect chosen.
- Confirm a deliberately-missing key falls back to English without
  breaking the page (manual smoke test by temporarily removing one key
  from `tl.js`).
