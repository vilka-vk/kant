(function () {
  'use strict';

  var STATIC_TOKEN = 'STATIC_TOKEN_12345';
  var STORAGE_KEY = 'kant-locale';
  var DEFAULT_LOCALE = 'en';
  var HOME_MODULES_COUNT = 5;

  function isDefaultHome(pathname) {
    return pathname === '/' || /\/index\.html$/i.test(pathname) || /\/KANT\/?$/i.test(pathname);
  }

  function getBaseUrl() {
    return window.location.protocol === 'file:'
      ? (window.KANT_DIRECTUS_HOST || 'http://<SERVER_HOST_OR_IP>:8055')
      : '/api';
  }

  function supportedLocales() {
    var nodes = document.querySelectorAll('.lang-switcher__option[data-lang]');
    var set = {};
    var list = [];
    nodes.forEach(function (n) {
      var code = String(n.getAttribute('data-lang') || '').toLowerCase();
      if (code && !set[code]) {
        set[code] = true;
        list.push(code);
      }
    });
    return list.length ? list : [DEFAULT_LOCALE];
  }

  function getLocale() {
    var locales = supportedLocales();
    var urlLocale = (new URLSearchParams(window.location.search).get('lang') || '').toLowerCase();
    if (locales.indexOf(urlLocale) >= 0) return urlLocale;

    var stored = String(localStorage.getItem(STORAGE_KEY) || '').toLowerCase();
    if (locales.indexOf(stored) >= 0) return stored;

    var browser = String((navigator.languages && navigator.languages[0]) || navigator.language || '').toLowerCase();
    if (browser.indexOf('ru') === 0 && locales.indexOf('ru') >= 0) return 'ru';
    return DEFAULT_LOCALE;
  }

  function strip(html) {
    if (!html) return '';
    var div = document.createElement('div');
    div.innerHTML = String(html);
    return (div.textContent || '').trim();
  }

  function assetUrl(baseUrl, fileField) {
    if (!fileField) return '';
    if (typeof fileField === 'string') {
      if (fileField.indexOf('http://') === 0 || fileField.indexOf('https://') === 0) return fileField;
      return baseUrl + '/assets/' + encodeURIComponent(fileField);
    }
    if (fileField && fileField.id) return baseUrl + '/assets/' + encodeURIComponent(fileField.id);
    return '';
  }

  function request(baseUrl, collection, locale, params) {
    var p = new URLSearchParams();
    p.set('access_token', STATIC_TOKEN);
    p.set('sort', (params && params.sort) || 'id');
    p.set('limit', String((params && params.limit) || -1));
    p.set('fields', (params && params.fields) || '*.*');
    p.set('filter[locale][_eq]', locale);
    if (params && params.filters) {
      Object.keys(params.filters).forEach(function (k) { p.set(k, params.filters[k]); });
    }
    return fetch(baseUrl + '/items/' + collection + '?' + p.toString(), { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (json) { return Array.isArray(json.data) ? json.data : []; });
  }

  function withFallback(baseUrl, collection, locale, params) {
    return request(baseUrl, collection, locale, params).then(function (rows) {
      if (rows.length || locale === DEFAULT_LOCALE) return rows;
      return request(baseUrl, collection, DEFAULT_LOCALE, params);
    });
  }

  function applySiteSettings(baseUrl, settings) {
    if (!settings) return;
    var c = document.querySelector('.footer__copyright');
    if (c && settings.footer_copyright) c.innerHTML = settings.footer_copyright;
    var byLabel = {
      YouTube: settings.social_youtube_url,
      Twitter: settings.social_twitter_url,
      Instagram: settings.social_instagram_url,
      Facebook: settings.social_facebook_url
    };
    document.querySelectorAll('.footer__social').forEach(function (a) {
      var label = a.getAttribute('aria-label');
      if (label && byLabel[label]) a.setAttribute('href', byLabel[label]);
    });
  }

  function applyHero(baseUrl, hero) {
    if (!hero) return;
    var h1 = document.querySelector('.hero__headline');
    if (h1 && hero.title) h1.textContent = strip(hero.title);
    var sub = document.querySelector('.hero__subtitle');
    if (sub) {
      if (hero.subtitle) {
        sub.textContent = strip(hero.subtitle);
        sub.style.display = '';
      } else {
        sub.style.display = 'none';
      }
    }
    var bg = document.querySelector('.hero__bg');
    var image = assetUrl(baseUrl, hero.background_image);
    if (bg && image) bg.src = image;
  }

  function moduleCard(item) {
    var href = 'module-' + String(item.module_number || item.id) + '.html?slug=' + encodeURIComponent(item.slug || '');
    return '<a href="' + href + '" class="card-link perforated_row">' +
      '<p class="card-link__number text-card-number">' + (item.module_number || '') + '</p>' +
      '<div class="card-link__body"><div class="card-link__content">' +
      '<h3 class="card-link__title text-h3">' + strip(item.title || '') + '</h3>' +
      '<p class="card-link__description text-paragraph">' + (item.short_description || '') + '</p>' +
      '<div class="card-link__meta-action"><p class="card-link__meta text-paragraph">' +
      (item.list_duration_display ? ('Video ' + strip(item.list_duration_display) + '<br>') : '') +
      (item.formats ? strip(item.formats) + '<br>' : '') +
      '<strong>' + strip(item.languages || '') + '</strong>' +
      '</p><div class="card-link__action"><div class="card-link__action-label"><p>Learn<br>the module</p></div>' +
      '<span class="icon icon--md"><img src="assets/icons/arrow-right.svg" alt=""></span></div></div></div></div></a>';
  }

  function publicationTarget(baseUrl, p) {
    if (!!p.file === !!p.url) return '';
    return p.file ? assetUrl(baseUrl, p.file) : p.url;
  }

  function publicationCard(baseUrl, p) {
    var target = publicationTarget(baseUrl, p);
    if (!target) return '';
    return '<a href="' + target + '" class="publication-item">' +
      '<img class="publication-item__image" src="' + (assetUrl(baseUrl, p.cover_image) || 'assets/images/publication-3.svg') + '" alt="Publication cover">' +
      '<p class="publication-item__title text-paragraph-caps">' + strip(p.title || '') + '</p></a>';
  }

  function renderIndex(baseUrl, locale) {
    return Promise.all([
      withFallback(baseUrl, 'site_settings', locale, { limit: 1 }),
      withFallback(baseUrl, 'about_project', locale, { limit: 1 }),
      withFallback(baseUrl, 'modules', locale, { sort: 'order,id' }),
      withFallback(baseUrl, 'publications', locale, { sort: '-published_at,display_order,id' }),
      withFallback(baseUrl, 'authors', locale, { sort: 'display_order,id' })
    ]).then(function (res) {
      applySiteSettings(baseUrl, res[0][0]);
      var about = res[1][0];
      if (about) {
        var t = document.querySelector('#about .section__title');
        if (t && about.section_title) t.textContent = strip(about.section_title);
        var sticker = document.querySelector('.about__sticker-text');
        if (sticker && about.sticker_text) sticker.innerHTML = about.sticker_text;
        var modal = document.querySelector('#about-modal .modal__content');
        if (modal && about.modal_body) modal.innerHTML = '<p id="about-modal-title" class="text-paragraph">' + about.modal_body + '</p>';
      }

      var modulesNode = document.querySelector('#modules .modules__cards');
      if (modulesNode) modulesNode.innerHTML = res[2].slice(0, HOME_MODULES_COUNT).map(moduleCard).join('');

      var pubsNode = document.querySelector('#publications .publications');
      if (pubsNode) pubsNode.innerHTML = res[3].slice(0, 3).map(function (p) { return publicationCard(baseUrl, p); }).join('');

      var authorsNode = document.querySelector('.authors-slider');
      if (authorsNode) {
        authorsNode.innerHTML = res[4].map(function (a) {
          var full = strip(a.full_name || ((a.first_name || '') + ' ' + (a.last_name || '')));
          var parts = full.split(' ');
          var f = parts.shift() || '';
          var l = parts.join(' ');
          return '<div class="card-author"><div class="card-author__photo"><img src="' + assetUrl(baseUrl, a.photo) + '" alt="' + full + '">' +
            '<div class="card-author__name text-author-name"><p>' + f + '</p><p>' + l + '</p></div></div>' +
            '<div class="card-author__quote"><div style="display:flex;flex-direction:column;gap:16px;align-items:flex-end;">' +
            '<p class="card-author__bio">' + strip(a.affiliation || '') + '</p></div><div class="card-author__name text-author-name"><p>' + f + '</p><p>' + l + '</p></div></div></div>';
        }).join('');
      }
    });
  }

  function renderModules(baseUrl, locale) {
    return Promise.all([
      withFallback(baseUrl, 'site_settings', locale, { limit: 1 }),
      withFallback(baseUrl, 'hero_sections', locale, { limit: 1, filters: { 'filter[page_key][_eq]': 'modules' } }),
      withFallback(baseUrl, 'about_project', locale, { limit: 1 }),
      withFallback(baseUrl, 'modules', locale, { sort: 'order,id' })
    ]).then(function (res) {
      applySiteSettings(baseUrl, res[0][0]);
      applyHero(baseUrl, res[1][0]);
      var container = document.querySelector('#modules .modules__cards');
      if (container) container.innerHTML = res[3].map(moduleCard).join('');
    });
  }

  function renderPublications(baseUrl, locale) {
    return Promise.all([
      withFallback(baseUrl, 'site_settings', locale, { limit: 1 }),
      withFallback(baseUrl, 'hero_sections', locale, { limit: 1, filters: { 'filter[page_key][_eq]': 'publications' } }),
      withFallback(baseUrl, 'publication_types', locale, { sort: 'id' }),
      withFallback(baseUrl, 'publications', locale, { sort: 'display_order,-published_at,id' })
    ]).then(function (res) {
      applySiteSettings(baseUrl, res[0][0]);
      applyHero(baseUrl, res[1][0]);
      var tabs = document.querySelector('.publications-tabs');
      if (tabs) {
        tabs.innerHTML = '<button class="tab tab--active" type="button" data-tab="all">All</button>' +
          res[2].map(function (t) { return '<button class="tab" type="button" data-tab="' + strip(t.slug || '') + '">' + strip(t.name || '') + '</button>'; }).join('');
      }
      var list = document.querySelector('.publications--grid');
      if (list) {
        list.innerHTML = res[3].map(function (p) {
          var card = publicationCard(baseUrl, p);
          if (!card) return '';
          var typeSlug = p.type && p.type.slug ? strip(p.type.slug) : '';
          return card.replace('class="publication-item"', 'class="publication-item" data-category="' + typeSlug + '"');
        }).join('');
      }
    });
  }

  function renderModuleDetail(baseUrl, locale) {
    var query = new URLSearchParams(window.location.search);
    var slug = query.get('slug');
    var pathMatch = window.location.pathname.match(/module-(\d+)\.html/i);
    var filters = slug
      ? { 'filter[slug][_eq]': slug }
      : (pathMatch ? { 'filter[module_number][_eq]': pathMatch[1] } : null);
    if (!filters) return Promise.resolve();

    return Promise.all([
      withFallback(baseUrl, 'site_settings', locale, { limit: 1 }),
      withFallback(baseUrl, 'modules', locale, { limit: 1, filters: filters }),
      withFallback(baseUrl, 'module_transcripts', locale, { sort: 'order,id' }),
      withFallback(baseUrl, 'module_readings', locale, { sort: 'id', fields: '*,linked_publication.*' })
    ]).then(function (res) {
      applySiteSettings(baseUrl, res[0][0]);
      var module = res[1][0];
      if (!module) return;

      var heroTitle = document.querySelector('.module-hero__headline');
      if (heroTitle) heroTitle.textContent = strip(module.title || '');
      var heroKicker = document.querySelector('.module-hero__kicker');
      if (heroKicker) heroKicker.textContent = strip(module.hero_kicker || ('Module ' + (module.module_number || '')));

      var transcriptList = document.querySelector('#transcript-modal .modal__downloads');
      if (transcriptList) {
        transcriptList.innerHTML = res[2].filter(function (t) { return Number(t.module) === Number(module.id) && t.file; })
          .map(function (t) { return '<li><a href="' + assetUrl(baseUrl, t.file) + '" download>' + strip(t.display_name || 'Download transcript') + '</a></li>'; })
          .join('');
      }

      var readingsNode = document.querySelector('.module-publications');
      if (readingsNode) {
        readingsNode.innerHTML = res[3].filter(function (r) { return Number(r.module) === Number(module.id); }).map(function (r) {
          var linked = r.linked_publication || null;
          var title = r.custom_title || (linked && linked.title) || '';
          var target = r.custom_url || assetUrl(baseUrl, r.custom_file) || (linked ? publicationTarget(baseUrl, linked) : '');
          if (!target) return '';
          var cover = assetUrl(baseUrl, r.custom_cover_image) || (linked ? assetUrl(baseUrl, linked.cover_image) : '') || 'assets/images/publication-3.svg';
          return '<a href="' + target + '" class="publication-item"><img class="publication-item__image" src="' + cover + '" alt="Publication cover"><p class="publication-item__title text-paragraph-caps">' + strip(title) + '</p></a>';
        }).join('');
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var baseUrl = getBaseUrl();
    var locale = getLocale();
    var path = window.location.pathname;

    var runner = isDefaultHome(path) ? renderIndex(baseUrl, locale)
      : /\/modules\.html$/i.test(path) ? renderModules(baseUrl, locale)
      : /\/publications\.html$/i.test(path) ? renderPublications(baseUrl, locale)
      : /\/module-\d+\.html$/i.test(path) ? renderModuleDetail(baseUrl, locale)
      : Promise.resolve();

    runner.catch(function (err) {
      console.error('KANT Directus sync failed', err);
    });
  });
})();
