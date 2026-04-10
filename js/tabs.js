(function () {
  'use strict';

  var tabsContainer = document.querySelector('.tabs');
  if (!tabsContainer) return;

  var tabs = tabsContainer.querySelectorAll('.tab');
  var player = tabsContainer.closest('.about__left').querySelector('.about__player');
  var panels = player.querySelectorAll('.about__player-panel');

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
})();
