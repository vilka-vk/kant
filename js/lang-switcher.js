(function () {
  'use strict';

  var switchers = document.querySelectorAll('.lang-switcher');
  if (!switchers.length) return;
  var STORAGE_KEY = 'kant-locale';
  var localeConfig = window.KANT_LOCALES || {};
  var allOptions = document.querySelectorAll('.lang-switcher__option[data-lang]');
  if (!allOptions.length) return;
  var supportedLocales = Array.from(allOptions).map(function (option) {
    return String(option.getAttribute('data-lang')).toUpperCase();
  }).filter(function (locale, index, list) {
    return !!locale && list.indexOf(locale) === index;
  });
  if (!supportedLocales.length) return;
  var configuredDefault = String(localeConfig.defaultLocale || '').toUpperCase();
  var DEFAULT_LOCALE = supportedLocales.indexOf(configuredDefault) >= 0 ? configuredDefault : supportedLocales[0];
  var localeLabels = localeConfig.labels || {};
  var localeUi = localeConfig.ui || {};

  function normalizeLocale(value) {
    if (!value) return null;
    var locale = String(value).toUpperCase();
    return supportedLocales.indexOf(locale) >= 0 ? locale : null;
  }

  function getLocaleFromUrl() {
    var params = new URLSearchParams(window.location.search);
    return normalizeLocale(params.get('lang'));
  }

  function getInitialLocale() {
    var fromUrl = getLocaleFromUrl();
    if (fromUrl) return fromUrl;
    var fromStorage = normalizeLocale(localStorage.getItem(STORAGE_KEY));
    if (fromStorage) return fromStorage;

    var browserLocales = [];
    if (Array.isArray(navigator.languages) && navigator.languages.length) {
      browserLocales = navigator.languages.slice();
    } else if (navigator.language) {
      browserLocales = [navigator.language];
    }
    var hasRussian = browserLocales.some(function (value) {
      return String(value || '').toLowerCase().indexOf('ru') === 0;
    });
    if (!hasRussian) {
      var enLocale = normalizeLocale('EN');
      if (enLocale) return enLocale;
    }
    return DEFAULT_LOCALE;
  }

  function setLocaleInUrl(locale) {
    var url = new URL(window.location.href);
    if (locale === DEFAULT_LOCALE) {
      url.searchParams.delete('lang');
    } else {
      url.searchParams.set('lang', locale.toLowerCase());
    }
    window.history.replaceState({}, '', url.toString());
  }

  function applyLocale(locale) {
    document.documentElement.setAttribute('lang', locale.toLowerCase());
    localStorage.setItem(STORAGE_KEY, locale);
    setLocaleInUrl(locale);

    switchers.forEach(function (switcher) {
      var options = switcher.querySelectorAll('.lang-switcher__option');
      options.forEach(function (item) {
        var optionLocale = String(item.getAttribute('data-lang') || '').toUpperCase();
        var isActive = optionLocale === locale;
        item.classList.toggle('is-active', isActive);
        item.setAttribute('aria-pressed', String(isActive));
        if (localeLabels[optionLocale]) {
          item.setAttribute('title', localeLabels[optionLocale]);
        }
      });
    });

    document.querySelectorAll('[data-i18n-key]').forEach(function (node) {
      var key = String(node.getAttribute('data-i18n-key') || '');
      if (!key || !localeUi[key]) return;
      var value = localeUi[key][locale];
      if (value) {
        node.textContent = value;
      }
    });
  }

  switchers.forEach(function (switcher) {
    var options = switcher.querySelectorAll('.lang-switcher__option');
    if (!options.length) return;

    options.forEach(function (option) {
      option.addEventListener('click', function () {
        var selectedLocale = normalizeLocale(option.getAttribute('data-lang'));
        if (!selectedLocale) return;
        applyLocale(selectedLocale);
      });
    });
  });

  applyLocale(getInitialLocale());
})();
