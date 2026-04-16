(function () {
  'use strict';

  if (!window.gsap || !window.ScrollTrigger) return;
  window.gsap.registerPlugin(window.ScrollTrigger);

  var sections = document.querySelectorAll('.main .section, .main .module-block');
  if (!sections.length) return;

  sections.forEach(function (section) {
    var title = section.querySelector('.section__title, .module-label, .module-title');
    var blocks = Array.prototype.slice.call(section.children).filter(function (child) {
      return !child.classList.contains('section__title') &&
        !child.classList.contains('module-label') &&
        !child.classList.contains('module-title');
    });

    var targets = [];
    if (title) targets.push(title);
    targets = targets.concat(blocks);
    if (!targets.length) return;

    window.gsap.from(targets, {
      opacity: 0,
      y: 40,
      duration: 1,
      ease: 'power2.out',
      stagger: 0.12,
      scrollTrigger: {
        trigger: section,
        start: 'top 80%',
        toggleActions: 'play none none none',
        once: true
      }
    });
  });
})();

