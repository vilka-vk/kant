(function () {
  'use strict';

  var tabsGroups = document.querySelectorAll('.tabs');
  if (!tabsGroups.length) return;

  tabsGroups.forEach(function (tabsContainer) {
    var scope = tabsContainer.parentElement;
    if (!scope) return;

    var tabs = tabsContainer.querySelectorAll('.tab[data-tab]');
    var player = scope.querySelector('.about__player');
    if (!tabs.length || !player) return;

    var panels = player.querySelectorAll('.about__player-panel[data-panel]');
    if (!panels.length) return;

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var idx = tab.getAttribute('data-tab');

        tabs.forEach(function (t) { t.classList.remove('tab--active'); });
        tab.classList.add('tab--active');

        panels.forEach(function (p) { p.classList.remove('is-active'); });
        var target = player.querySelector('[data-panel="' + idx + '"]');
        if (target) target.classList.add('is-active');
      });
    });
  });
})();
