(function () {
  'use strict';

  var switcher = document.querySelector('.lang-switcher');
  if (!switcher) return;

  var trigger = switcher.querySelector('.lang-switcher__trigger');
  var label = switcher.querySelector('.lang-switcher__label');
  var options = switcher.querySelectorAll('.lang-switcher__option');

  if (!trigger || !label || !options.length) return;

  function setOpenState(isOpen) {
    switcher.classList.toggle('is-open', isOpen);
    trigger.setAttribute('aria-expanded', String(isOpen));
  }

  function selectLanguage(option) {
    var lang = option.getAttribute('data-lang');
    if (!lang) return;

    label.textContent = lang;
    options.forEach(function (item) {
      var isActive = item === option;
      item.classList.toggle('is-active', isActive);
      item.setAttribute('aria-selected', String(isActive));
    });
  }

  trigger.addEventListener('click', function (event) {
    event.stopPropagation();
    setOpenState(!switcher.classList.contains('is-open'));
  });

  options.forEach(function (option) {
    option.addEventListener('click', function (event) {
      event.stopPropagation();
      selectLanguage(option);
      setOpenState(false);
    });
  });

  document.addEventListener('click', function (event) {
    if (!switcher.contains(event.target)) {
      setOpenState(false);
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      setOpenState(false);
    }
  });
})();
