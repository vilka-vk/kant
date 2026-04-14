(function () {
  'use strict';

  var hamburger = document.querySelector('.hamburger');
  var desktopNav = document.querySelector('.header .nav');
  var mobileMenu = document.querySelector('.mobile-menu');
  var mobileNav = mobileMenu && mobileMenu.querySelector('.mobile-menu__nav');
  var closeBtn = mobileMenu && mobileMenu.querySelector('.mobile-menu__close');

  if (!hamburger || !desktopNav || !mobileMenu || !mobileNav || !closeBtn) return;

  mobileNav.innerHTML = desktopNav.innerHTML;

  function setMenuState(isOpen) {
    mobileMenu.classList.toggle('is-open', isOpen);
    mobileMenu.setAttribute('aria-hidden', String(!isOpen));
    hamburger.setAttribute('aria-expanded', String(isOpen));
    document.body.style.overflow = isOpen ? 'hidden' : '';
  }

  hamburger.addEventListener('click', function () {
    setMenuState(!mobileMenu.classList.contains('is-open'));
  });

  closeBtn.addEventListener('click', function () {
    setMenuState(false);
  });

  mobileNav.addEventListener('click', function (event) {
    if (event.target.closest('.nav__link')) {
      setMenuState(false);
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') setMenuState(false);
  });
})();
