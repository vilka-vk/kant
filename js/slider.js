(function () {
  'use strict';

  var track = document.querySelector('.authors-slider');
  if (!track) return;

  var arrows = track.closest('.section').querySelector('.slider-arrows');
  var prevBtn = arrows && arrows.children[0];
  var nextBtn = arrows && arrows.children[1];

  function getCardStep() {
    var card = track.querySelector('.card-author');
    if (!card) return 0;
    var style = getComputedStyle(track);
    return card.offsetWidth + parseFloat(style.gap || 12);
  }

  function maxScroll() {
    return track.scrollWidth - track.clientWidth;
  }

  function scrollBy(dir) {
    var step = getCardStep();
    var target = track.scrollLeft + step * dir;
    target = Math.max(0, Math.min(target, maxScroll()));
    track.scrollTo({ left: target, behavior: 'smooth' });
  }

  if (prevBtn) prevBtn.addEventListener('click', function () { scrollBy(-1); });
  if (nextBtn) nextBtn.addEventListener('click', function () { scrollBy(1); });

  // Pointer drag (mouse + pen + touch)
  var dragStartX = 0;
  var dragScrollLeft = 0;
  var isDragging = false;
  var hasMoved = false;
  var DRAG_THRESHOLD = 5;

  track.addEventListener('pointerdown', function (e) {
    if (e.button !== 0) return;
    isDragging = true;
    hasMoved = false;
    dragStartX = e.clientX;
    dragScrollLeft = track.scrollLeft;
    track.setPointerCapture(e.pointerId);
    track.classList.add('is-dragging');
  });

  track.addEventListener('pointermove', function (e) {
    if (!isDragging) return;
    var dx = e.clientX - dragStartX;
    if (!hasMoved && Math.abs(dx) < DRAG_THRESHOLD) return;
    hasMoved = true;
    track.scrollLeft = dragScrollLeft - dx;
  });

  function endDrag(e) {
    if (!isDragging) return;
    isDragging = false;
    track.classList.remove('is-dragging');
    if (hasMoved) snapToNearest();
  }

  track.addEventListener('pointerup', endDrag);
  track.addEventListener('pointercancel', endDrag);

  // Prevent click on links/cards after drag
  track.addEventListener('click', function (e) {
    if (hasMoved) { e.preventDefault(); e.stopPropagation(); }
  }, true);

  // Horizontal wheel / trackpad
  var wheelTimer;
  track.addEventListener('wheel', function (e) {
    if (Math.abs(e.deltaX) < Math.abs(e.deltaY)) return;
    e.preventDefault();
    track.scrollLeft += e.deltaX;
    clearTimeout(wheelTimer);
    wheelTimer = setTimeout(snapToNearest, 120);
  }, { passive: false });

  function snapToNearest() {
    var step = getCardStep();
    if (!step) return;
    var idx = Math.round(track.scrollLeft / step);
    var target = Math.max(0, Math.min(idx * step, maxScroll()));
    track.scrollTo({ left: target, behavior: 'smooth' });
  }

  // Keyboard navigation when section is focused/hovered
  track.setAttribute('tabindex', '0');
  track.setAttribute('role', 'region');
  track.setAttribute('aria-label', 'Authors slider');
  track.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowRight') { scrollBy(1); e.preventDefault(); }
    if (e.key === 'ArrowLeft')  { scrollBy(-1); e.preventDefault(); }
  });
})();
