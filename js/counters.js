// Animated count-up for stat numbers, 0 -> target, triggered on scroll into view
(function () {
  const els = document.querySelectorAll('[data-count-to]');
  if (!els.length) return;

  const fmtEn = new Intl.NumberFormat('en-IN');
  const fmtMr = new Intl.NumberFormat('mr-IN-u-nu-deva');

  function format(value) {
    const lang = document.documentElement.getAttribute('data-lang');
    return lang === 'en' ? fmtEn.format(value) : fmtMr.format(value);
  }

  function animate(el) {
    const target = parseInt(el.dataset.countTo, 10);
    const suffix = el.dataset.suffix || '';
    const duration = 1400;
    const start = performance.now();

    function tick(now) {
      const progress = Math.min((now - start) / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      const value = Math.round(target * eased);
      el.textContent = format(value) + suffix;
      if (progress < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        animate(entry.target);
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.5 });

  els.forEach((el) => observer.observe(el));
})();
