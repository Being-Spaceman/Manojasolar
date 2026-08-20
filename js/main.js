// Language toggle + mobile nav
(function () {
  const root = document.documentElement;
  const stored = localStorage.getItem('lang') || 'mr';
  root.setAttribute('data-lang', stored);

  function setLang(lang) {
    root.setAttribute('data-lang', lang);
    localStorage.setItem('lang', lang);
    document.querySelectorAll('.lang-toggle button').forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.lang === lang);
    });
  }

  document.querySelectorAll('.lang-toggle button').forEach((btn) => {
    btn.addEventListener('click', () => setLang(btn.dataset.lang));
  });
  setLang(stored);

  const toggle = document.querySelector('.mobile-toggle');
  const nav = document.querySelector('.main-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', () => {
      nav.classList.toggle('open');
    });
  }
})();

// Product variant modal (click to enlarge)
(function () {
  const overlay = document.getElementById('productModal');
  if (!overlay) return;

  const modal = overlay.querySelector('.product-modal');
  const catEl = document.getElementById('productModalCat');
  const titleEl = document.getElementById('productModalTitle');
  const specEl = document.getElementById('productModalSpec');
  const photoEl = document.getElementById('productModalPhoto');

  function openModal(block) {
    const catHead = block.closest('.cat-block')?.querySelector('.cat-head h3');
    catEl.innerHTML = catHead ? catHead.innerHTML : '';

    const label = block.querySelector('.variant-label');
    titleEl.innerHTML = label ? label.innerHTML : '';

    const spec = block.querySelector('.variant-spec');
    specEl.innerHTML = spec ? spec.innerHTML : '';
    specEl.style.display = spec ? '' : 'none';

    const img = block.querySelector('.variant-image');
    const imagesAttr = block.dataset.images;
    const images = imagesAttr ? imagesAttr.split('|').filter(Boolean) : (img ? [img.src] : []);

    photoEl.innerHTML = '';
    if (images.length) {
      const mainImg = document.createElement('img');
      mainImg.className = 'product-modal-main-img';
      mainImg.src = images[0];
      mainImg.alt = img ? img.alt : '';
      photoEl.appendChild(mainImg);

      if (images.length > 1) {
        const thumbs = document.createElement('div');
        thumbs.className = 'product-modal-thumbs';
        images.forEach((src, i) => {
          const t = document.createElement('img');
          t.src = src;
          t.className = 'product-modal-thumb' + (i === 0 ? ' active' : '');
          t.addEventListener('click', () => {
            mainImg.src = src;
            thumbs.querySelectorAll('.product-modal-thumb').forEach((el) => el.classList.remove('active'));
            t.classList.add('active');
          });
          thumbs.appendChild(t);
        });
        photoEl.appendChild(thumbs);
      }
    } else {
      photoEl.innerHTML = '<div class="variant-placeholder"></div>';
    }

    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeModal() {
    overlay.classList.remove('open');
    document.body.style.overflow = '';
  }

  document.querySelectorAll('.variant-block').forEach((block) => {
    block.addEventListener('click', () => openModal(block));
  });

  document.getElementById('productModalClose').addEventListener('click', closeModal);

  overlay.addEventListener('click', (e) => {
    if (!modal.contains(e.target)) closeModal();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && overlay.classList.contains('open')) closeModal();
  });
})();
