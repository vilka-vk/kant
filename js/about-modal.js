(function () {
  'use strict';

  var trigger = document.querySelector('.about__read-more');
  var modal = document.getElementById('about-modal');
  if (!trigger || !modal) return;

  var dialog = modal.querySelector('.modal__dialog');
  var closeBtn = modal.querySelector('.modal__close');
  var body = document.body;

  function getScrollbarCompensation() {
    return Math.max(0, window.innerWidth - document.documentElement.clientWidth);
  }

  function lockBodyScroll() {
    var compensation = getScrollbarCompensation();
    body.style.setProperty('--scrollbar-compensation', compensation ? compensation + 'px' : '0px');
    body.classList.add('modal-open');
    body.style.overflow = 'hidden';
    body.style.paddingRight = compensation ? compensation + 'px' : '';
  }

  function unlockBodyScroll() {
    body.classList.remove('modal-open');
    body.style.removeProperty('--scrollbar-compensation');
    body.style.overflow = '';
    body.style.paddingRight = '';
  }

  function setOpen(isOpen) {
    modal.classList.toggle('modal--open', isOpen);
    modal.setAttribute('aria-hidden', String(!isOpen));
    if (isOpen) {
      lockBodyScroll();
    } else {
      var onCloseComplete = function (event) {
        if (event && event.target !== modal) return;
        if (!modal.classList.contains('modal--open')) {
          unlockBodyScroll();
        }
      };
      modal.addEventListener('transitionend', onCloseComplete, { once: true });
      window.setTimeout(onCloseComplete, 260);
    }
  }

  trigger.addEventListener('click', function (event) {
    event.preventDefault();
    setOpen(true);
  });

  closeBtn.addEventListener('click', function () {
    setOpen(false);
  });

  modal.addEventListener('click', function (event) {
    if (!dialog.contains(event.target)) {
      setOpen(false);
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      setOpen(false);
    }
  });
})();

