(function () {
  'use strict';

  var tabsRoot = document.querySelector('.publications-tabs');
  var list = document.querySelector('.publications.publications--grid');
  if (!tabsRoot || !list) return;

  var tabs = tabsRoot.querySelectorAll('.tab[data-tab]');
  var items = list.querySelectorAll('.publication-item[data-category]');

  function applyFilter(kind) {
    items.forEach(function (item) {
      var cat = item.getAttribute('data-category') || 'all';
      var show = kind === 'all' || cat === kind;
      item.style.display = show ? '' : 'none';
    });
  }

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      var target = tab.getAttribute('data-tab');
      if (!target) return;

      tabs.forEach(function (t) { t.classList.remove('tab--active'); });
      tab.classList.add('tab--active');

      applyFilter(target);
    });
  });

  applyFilter('all');
})();

