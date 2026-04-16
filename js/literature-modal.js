(function () {
  'use strict';

  var trigger = document.querySelector('[data-literature-open]');
  var modal = document.getElementById('literature-modal');
  if (!trigger || !modal) return;

  var dialog = modal.querySelector('.modal__dialog');
  var closeBtn = modal.querySelector('.modal__close');
  if (!dialog || !closeBtn) return;

  function setOpen(isOpen) {
    modal.classList.toggle('modal--open', isOpen);
    modal.setAttribute('aria-hidden', String(!isOpen));
    document.body.style.overflow = isOpen ? 'hidden' : '';
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
