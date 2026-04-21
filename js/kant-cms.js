(function () {
  'use strict';

  var API_BASE = '/api';
  var DEFAULT_LOCALE = 'ru';
  var STORAGE_KEY = 'kant-locale';

  function currentLocale() {
    var params = new URLSearchParams(window.location.search);
    var urlLocale = (params.get('lang') || '').toLowerCase();
    if (urlLocale) return urlLocale;
    var stored = (localStorage.getItem(STORAGE_KEY) || '').toLowerCase();
    return stored || DEFAULT_LOCALE;
  }

  async function apiGet(path, locale) {
    var url = API_BASE + '/' + path + '?lang=' + encodeURIComponent(locale);
    var res = await fetch(url, { cache: 'no-store' });
    if (!res.ok) throw new Error('Request failed: ' + path);
    return res.json();
  }

  function setText(selector, value) {
    var el = document.querySelector(selector);
    if (el && value != null && value !== '') {
      el.textContent = value;
    }
  }

  function setHtml(selector, value) {
    var el = document.querySelector(selector);
    if (el && value != null && value !== '') {
      el.innerHTML = value;
    }
  }

  function setHref(selector, value) {
    var el = document.querySelector(selector);
    if (el && value) {
      el.setAttribute('href', value);
    }
  }

  function setImg(selector, value) {
    var el = document.querySelector(selector);
    if (el && value) {
      el.setAttribute('src', value);
    }
  }

  function escapeAttr(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function renderVideoMarkup(url, title) {
    var safeUrl = String(url || '').trim();
    var safeTitle = escapeAttr(title || 'Video');
    if (!safeUrl) return '';
    var lower = safeUrl.toLowerCase();
    var isFile = /\.(mp4|webm|ogg)(\?.*)?$/.test(lower);
    if (isFile) {
      return '<video controls preload="metadata" title="' + safeTitle + '">' +
        '<source src="' + escapeAttr(safeUrl) + '">' +
        '</video>';
    }
    return '<iframe src="' + escapeAttr(safeUrl) + '" title="' + safeTitle + '"' +
      ' allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>';
  }

  function renderFooter(settings) {
    setText('.footer__copyright', settings.footer_copyright || '');
    var links = document.querySelectorAll('.footer__socials a.footer__social');
    if (links[0]) links[0].setAttribute('href', settings.social_youtube_url || '#');
    if (links[1]) links[1].setAttribute('href', settings.social_twitter_url || '#');
    if (links[2]) links[2].setAttribute('href', settings.social_instagram_url || '#');
    if (links[3]) links[3].setAttribute('href', settings.social_facebook_url || '#');
  }

  function renderAbout(about) {
    setText('.about__sticker-text', about.sticker_text || '');
    setHtml('#about-modal .modal__content', about.modal_body || '');
    var tabsWrap = document.querySelector('#about .about__left .tabs');
    var playerWrap = document.querySelector('#about .about__left .about__player');
    var videos = Array.isArray(about.videos) ? about.videos : [];
    if (!tabsWrap || !playerWrap || !videos.length) return;
    tabsWrap.innerHTML = '';
    playerWrap.innerHTML = '';
    videos.forEach(function (video, idx) {
      var lang = (video.language_code || '').toUpperCase() || ('V' + (idx + 1));
      var tab = document.createElement('button');
      tab.className = 'tab' + (idx === 0 ? ' tab--active' : '');
      tab.setAttribute('data-tab', String(idx));
      tab.textContent = lang;
      tabsWrap.appendChild(tab);

      var panel = document.createElement('div');
      panel.className = 'about__player-panel' + (idx === 0 ? ' is-active' : '');
      panel.setAttribute('data-panel', String(idx));
      panel.innerHTML = renderVideoMarkup(video.video_url, video.video_alt || ('About video ' + lang));
      playerWrap.appendChild(panel);

      tab.addEventListener('click', function () {
        tabsWrap.querySelectorAll('.tab').forEach(function (x) { x.classList.remove('tab--active'); });
        playerWrap.querySelectorAll('.about__player-panel').forEach(function (x) { x.classList.remove('is-active'); });
        tab.classList.add('tab--active');
        panel.classList.add('is-active');
      });
    });
  }

  function renderModulesList(modules, limit) {
    var list = document.querySelector('.modules__cards');
    if (!list) return;
    list.innerHTML = '';
    modules.slice(0, limit).forEach(function (m) {
      var href = 'module.html?slug=' + encodeURIComponent(m.slug);
      var item = document.createElement('a');
      item.className = 'card-link perforated_row';
      item.setAttribute('href', href);
      item.innerHTML =
        '<p class="card-link__number text-card-number">' + (m.module_number || '') + '</p>' +
        '<div class="card-link__body"><div class="card-link__content">' +
        '<h3 class="card-link__title text-h3">' + (m.title || '') + '</h3>' +
        '<p class="card-link__description text-paragraph">' + (m.short_description || '') + '</p>' +
        '<div class="card-link__meta-action"><p class="card-link__meta text-paragraph">' +
        (m.formats || '') + '<br>' +
        (m.list_duration_display || '') + '<br><strong>' + (m.languages || '') + '</strong></p>' +
        '<div class="card-link__action"><div class="card-link__action-label"><p>Learn<br>the module</p></div>' +
        '<span class="icon icon--md"><img src="assets/icons/arrow-right.svg" alt=""></span></div>' +
        '</div></div></div>';
      list.appendChild(item);
    });
  }

  function renderPublications(items, selector) {
    var list = document.querySelector(selector || '.publications.publications--grid');
    if (!list) return;
    list.innerHTML = '';
    items.forEach(function (p) {
      var link = p.file_path || p.external_url || '#';
      var category = p.publication_type_slug || 'all';
      var card = document.createElement('a');
      card.className = 'publication-item';
      card.setAttribute('href', link);
      card.setAttribute('data-category', category);
      card.innerHTML =
        '<img class="publication-item__image" src="' + (p.cover_image_path || 'assets/images/publication-3.svg') + '" alt="Publication cover">' +
        '<p class="publication-item__title text-paragraph-caps">' + (p.title || '') + '</p>';
      list.appendChild(card);
    });
  }

  function pickLatestPublications(items, limit) {
    return (items || []).slice().sort(function (a, b) {
      var aTime = Date.parse(a && a.published_at ? a.published_at : '') || 0;
      var bTime = Date.parse(b && b.published_at ? b.published_at : '') || 0;
      if (bTime !== aTime) return bTime - aTime;
      return (Number(b && b.id) || 0) - (Number(a && a.id) || 0);
    }).slice(0, limit);
  }

  function renderPublicationTabs(types, locale) {
    var tabsRoot = document.querySelector('.publications-tabs');
    if (!tabsRoot) return;
    tabsRoot.innerHTML = '';
    var localeUpper = String(locale || DEFAULT_LOCALE).toUpperCase();
    var ui = (window.KANT_LOCALES && window.KANT_LOCALES.ui) ? window.KANT_LOCALES.ui : {};
    var allLabel = (ui.publicationsAllTab && ui.publicationsAllTab[localeUpper]) ? ui.publicationsAllTab[localeUpper] : 'All';
    var allTab = document.createElement('button');
    allTab.className = 'tab tab--active';
    allTab.type = 'button';
    allTab.setAttribute('data-tab', 'all');
    allTab.textContent = allLabel;
    tabsRoot.appendChild(allTab);

    types.forEach(function (t) {
      var btn = document.createElement('button');
      btn.className = 'tab';
      btn.type = 'button';
      btn.setAttribute('data-tab', t.slug);
      btn.textContent = t.name || t.slug;
      tabsRoot.appendChild(btn);
    });

    function applyFilter(kind) {
      var items = document.querySelectorAll('.publications.publications--grid .publication-item[data-category]');
      items.forEach(function (item) {
        var cat = item.getAttribute('data-category') || 'all';
        item.style.display = kind === 'all' || cat === kind ? '' : 'none';
      });
    }

    tabsRoot.querySelectorAll('.tab[data-tab]').forEach(function (tab) {
      tab.addEventListener('click', function () {
        var target = tab.getAttribute('data-tab');
        tabsRoot.querySelectorAll('.tab[data-tab]').forEach(function (x) { x.classList.remove('tab--active'); });
        tab.classList.add('tab--active');
        applyFilter(target || 'all');
      });
    });
    applyFilter('all');
  }

  function renderAuthors(items) {
    var slider = document.querySelector('.authors-slider__track');
    if (!slider) return;
    slider.innerHTML = '';
    items.forEach(function (a) {
      var card = document.createElement('article');
      card.className = 'author-card';
      var name = ((a.first_name || '') + ' ' + (a.last_name || '')).trim() || a.full_name || '';
      card.innerHTML =
        '<img class="author-card__photo" src="' + (a.photo_path || 'assets/images/author-paper.svg') + '" alt="">' +
        '<h3 class="author-card__name text-h3">' + name + '</h3>' +
        '<p class="author-card__role text-paragraph">' + (a.affiliation || '') + '</p>';
      slider.appendChild(card);
    });
  }

  function renderModuleDetail(moduleItem, transcripts, readings) {
    if (!moduleItem) return;
    setText('.module-hero__kicker', 'Module ' + (moduleItem.module_number || ''));
    setText('.module-hero__headline', moduleItem.title || '');
    setText('.module-hero__subtitle', moduleItem.short_description || '');
    setImg('.module-hero .hero__bg', moduleItem.hero_background_image_path || '');
    setText('.module-main .module-block:nth-of-type(1) .module-title', moduleItem.lecture_title || '');
    setText('.module-main .module-block:nth-of-type(2) .module-title', moduleItem.presentation_title || '');

    var lectureTabsWrap = document.querySelector('.module-main .module-block:nth-of-type(1) .tabs');
    var lecturePlayerWrap = document.querySelector('.module-main .module-block:nth-of-type(1) .about__player.module-player');
    var lectureVideos = Array.isArray(moduleItem.lecture_videos) ? moduleItem.lecture_videos : [];
    if (lectureTabsWrap && lecturePlayerWrap && lectureVideos.length) {
      lectureTabsWrap.innerHTML = '';
      lecturePlayerWrap.innerHTML = '';
      lectureVideos.forEach(function (video, idx) {
        var lang = (video.language_code || '').toUpperCase() || ('V' + (idx + 1));
        var tab = document.createElement('button');
        tab.className = 'tab' + (idx === 0 ? ' tab--active' : '');
        tab.type = 'button';
        tab.setAttribute('data-tab', String(idx));
        tab.textContent = lang;
        lectureTabsWrap.appendChild(tab);

        var panel = document.createElement('div');
        panel.className = 'about__player-panel' + (idx === 0 ? ' is-active' : '');
        panel.setAttribute('data-panel', String(idx));
        panel.innerHTML = renderVideoMarkup(video.video_url, video.video_alt || ('Lecture video ' + lang));
        lecturePlayerWrap.appendChild(panel);

        tab.addEventListener('click', function () {
          lectureTabsWrap.querySelectorAll('.tab').forEach(function (x) { x.classList.remove('tab--active'); });
          lecturePlayerWrap.querySelectorAll('.about__player-panel').forEach(function (x) { x.classList.remove('is-active'); });
          tab.classList.add('tab--active');
          panel.classList.add('is-active');
        });
      });
    }
    var presentationFrame = document.querySelector('.module-main .module-block:nth-of-type(2) iframe');
    var presentationVideos = Array.isArray(moduleItem.presentation_videos) ? moduleItem.presentation_videos : [];
    var presentationTabsWrap = document.querySelector('.module-main .module-block:nth-of-type(2) .tabs');
    var presentationPlayerWrap = document.querySelector('.module-main .module-block:nth-of-type(2) .about__player.module-player');
    if (!presentationTabsWrap && presentationPlayerWrap) {
      presentationTabsWrap = document.createElement('div');
      presentationTabsWrap.className = 'tabs';
      presentationPlayerWrap.parentNode.insertBefore(presentationTabsWrap, presentationPlayerWrap);
    }
    if (presentationTabsWrap && presentationPlayerWrap && presentationVideos.length) {
      presentationTabsWrap.innerHTML = '';
      presentationPlayerWrap.innerHTML = '';
      presentationVideos.forEach(function (video, idx) {
        var lang = (video.language_code || '').toUpperCase() || ('V' + (idx + 1));
        var tab = document.createElement('button');
        tab.className = 'tab' + (idx === 0 ? ' tab--active' : '');
        tab.type = 'button';
        tab.setAttribute('data-tab', String(idx));
        tab.textContent = lang;
        presentationTabsWrap.appendChild(tab);

        var panel = document.createElement('div');
        panel.className = 'about__player-panel' + (idx === 0 ? ' is-active' : '');
        panel.setAttribute('data-panel', String(idx));
        panel.innerHTML = renderVideoMarkup(video.video_url, video.video_alt || ('Presentation video ' + lang));
        presentationPlayerWrap.appendChild(panel);

        tab.addEventListener('click', function () {
          presentationTabsWrap.querySelectorAll('.tab').forEach(function (x) { x.classList.remove('tab--active'); });
          presentationPlayerWrap.querySelectorAll('.about__player-panel').forEach(function (x) { x.classList.remove('is-active'); });
          tab.classList.add('tab--active');
          panel.classList.add('is-active');
        });
      });
    } else if (presentationFrame) {
      presentationFrame.src = '';
      presentationFrame.setAttribute('title', 'Presentation video');
    }
    setHref('.module-main .module-block:nth-of-type(2) .module-links a[download]', moduleItem.presentation_file_path || '#');

    var transcriptsList = document.querySelector('#transcript-modal .modal__downloads');
    if (transcriptsList) {
      transcriptsList.innerHTML = '';
      var downloadTranscriptLabel = locale === 'ru' ? 'Скачать транскрипцию' : 'Download transcript';
      transcripts.forEach(function (t) {
        var languageCode = String(t.display_name || '').trim().toUpperCase();
        var li = document.createElement('li');
        li.innerHTML = '<a href="' + (t.file_path || '#') + '" download>' + downloadTranscriptLabel + (languageCode ? ' (' + languageCode + ')' : '') + '</a>';
        transcriptsList.appendChild(li);
      });
    }

    var readingsGrid = document.querySelector('.module-publications');
    if (readingsGrid) {
      readingsGrid.innerHTML = '';
      readings.forEach(function (r) {
        var link = r.custom_file_path || r.custom_url || (r.linked_publication ? (r.linked_publication.file_path || r.linked_publication.external_url) : '#');
        var title = r.custom_title || (r.linked_publication ? r.linked_publication.title : '');
        var cover = r.custom_cover_image_path || (r.linked_publication ? r.linked_publication.cover_image_path : '') || 'assets/images/publication-3.svg';
        var card = document.createElement('a');
        card.className = 'publication-item';
        card.href = link || '#';
        card.innerHTML =
          '<img class="publication-item__image" src="' + cover + '" alt="Publication cover">' +
          '<p class="publication-item__title text-paragraph-caps">' + (title || '') + '</p>';
        readingsGrid.appendChild(card);
      });
    }

    if (moduleItem.literature_html) {
      var literatureList = document.querySelector('#literature-modal .modal__references');
      if (literatureList) {
        literatureList.innerHTML = moduleItem.literature_html;
      }
    }
  }

  function renderOurPosition(position) {
    var section = document.querySelector('.position');
    if (!section || !position) return;

    var titles = section.querySelectorAll('.position__block-title');
    if (titles[0] && position.concept_title) titles[0].textContent = position.concept_title;
    if (titles[1] && position.principles_title) titles[1].textContent = position.principles_title;
    if (titles[2] && position.objectives_title) titles[2].textContent = position.objectives_title;

    var bodies = section.querySelectorAll('.position__block-body');
    if (bodies[0] && position.concept_body) bodies[0].textContent = position.concept_body;
    if (bodies[1] && position.principles_body) bodies[1].textContent = position.principles_body;

    var objectives = Array.isArray(position.objectives) ? position.objectives : [];
    var list = section.querySelector('.position__objectives-list');
    if (list && objectives.length) {
      list.innerHTML = '';
      objectives.forEach(function (item) {
        var li = document.createElement('li');
        li.textContent = item;
        list.appendChild(li);
      });
    }

    var images = section.querySelectorAll('.position__block-image');
    if (images[0] && position.image_primary_path) images[0].setAttribute('src', position.image_primary_path);
    if (images[1] && position.image_secondary_path) images[1].setAttribute('src', position.image_secondary_path);
  }

  async function hydratePage() {
    var locale = currentLocale();
    try {
      var site = (await apiGet('site-settings', locale)).data || {};
      renderFooter(site);

      var path = window.location.pathname.toLowerCase();
      if (path.endsWith('/index.html') || path === '/' || path.endsWith('/kant/')) {
        var about = (await apiGet('about-project', locale)).data || {};
        var position = (await apiGet('our-position', locale)).data || {};
        var modules = (await apiGet('modules', locale)).data || [];
        var publications = (await apiGet('publications', locale)).data || [];
        var authors = (await apiGet('authors', locale)).data || [];
        renderAbout(about);
        renderOurPosition(position);
        renderModulesList(modules, 5);
        renderPublications(pickLatestPublications(publications, 3), '#publications .publications');
        renderAuthors(authors);
        return;
      }

      if (path.endsWith('/modules.html')) {
        var heroModules = (await apiGet('hero-sections?page_key=modules', locale)).data || {};
        var aboutModules = (await apiGet('about-project', locale)).data || {};
        var listModules = (await apiGet('modules', locale)).data || [];
        setText('.hero__headline', heroModules.title || (locale === 'ru' ? 'Модули' : 'Modules'));
        setText('.hero__subtitle', heroModules.subtitle || '');
        setImg('.hero .hero__bg', heroModules.background_image_path || '');
        renderAbout(aboutModules);
        renderModulesList(listModules, listModules.length);
        return;
      }

      if (path.endsWith('/publications.html')) {
        var heroPublications = (await apiGet('hero-sections?page_key=publications', locale)).data || {};
        var publicationTypes = (await apiGet('publication-types', locale)).data || [];
        var pubs = (await apiGet('publications', locale)).data || [];
        setText('.hero__headline', heroPublications.title || (locale === 'ru' ? 'Публикации' : 'Publications'));
        setText('.hero__subtitle', heroPublications.subtitle || '');
        setImg('.hero .hero__bg', heroPublications.background_image_path || '');
        renderPublicationTabs(publicationTypes, locale);
        renderPublications(pubs, '.publications.publications--grid');
        return;
      }

      if (path.endsWith('/module.html') || path.endsWith('/module-1.html')) {
        var params = new URLSearchParams(window.location.search);
        var slug = params.get('slug');
        var moduleItem = null;
        if (slug) {
          moduleItem = (await apiGet('modules/' + encodeURIComponent(slug), locale)).data || null;
        } else {
          var moduleList = (await apiGet('modules', locale)).data || [];
          moduleItem = moduleList[0] || null;
        }
        if (moduleItem && moduleItem.id) {
          var transcripts = (await apiGet('modules/' + moduleItem.id + '/transcripts', locale)).data || [];
          var readings = (await apiGet('modules/' + moduleItem.id + '/readings', locale)).data || [];
          renderModuleDetail(moduleItem, transcripts, readings);
        }
      }
    } catch (err) {
      console.error('KANT CMS error:', err);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    hydratePage();
    var langButtons = document.querySelectorAll('.lang-switcher__option[data-lang]');
    langButtons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        setTimeout(hydratePage, 0);
      });
    });
  });
})();
