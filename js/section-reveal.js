(function () {
  'use strict';

  if (!window.gsap || !window.ScrollTrigger) return;
  window.gsap.registerPlugin(window.ScrollTrigger);

  var sections = document.querySelectorAll('.main .section, .main .module-block');
  if (!sections.length) return;

  sections.forEach(function (section) {
    var headings = Array.prototype.slice.call(
      section.querySelectorAll(':scope > .section__title, :scope > .module-label, :scope > .module-title')
    );
    var blocks = Array.prototype.slice.call(section.children).filter(function (child) {
      return !child.classList.contains('section__title') &&
        !child.classList.contains('module-label') &&
        !child.classList.contains('module-title');
    });

    var targets = headings.concat(blocks);
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

  var moduleNavButtons = document.querySelectorAll('.module-nav-pair .btn-interlinking');
  if (moduleNavButtons.length) {
    window.gsap.from(moduleNavButtons, {
      opacity: 0,
      y: 30,
      duration: 0.9,
      ease: 'power2.out',
      stagger: 0.12,
      scrollTrigger: {
        trigger: moduleNavButtons[0].closest('.module-nav-pair'),
        start: 'top 85%',
        toggleActions: 'play none none none',
        once: true
      }
    });
  }
})();

