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
