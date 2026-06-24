// Raise Import — Site Interactions
(function(){

  // --- Theme ---
  const theme = localStorage.getItem('raise-import-theme') || 'light';
  document.documentElement.setAttribute('data-theme', theme);

  document.querySelectorAll('.theme-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
      const current = document.documentElement.getAttribute('data-theme');
      const next = current === 'light' ? 'dark' : 'light';
      document.documentElement.setAttribute('data-theme', next);
      localStorage.setItem('raise-import-theme', next);
    });
  });

  // --- Language ---
  const lang = localStorage.getItem('raise-import-lang') || 'en';
  document.documentElement.setAttribute('data-lang', lang);

  document.querySelectorAll('.lang-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const current = document.documentElement.getAttribute('data-lang');
      const next = current === 'en' ? 'zh' : 'en';
      document.documentElement.setAttribute('data-lang', next);
      localStorage.setItem('raise-import-lang', next);
      btn.textContent = next === 'en' ? '中' : 'EN';
    });
    const currentLang = document.documentElement.getAttribute('data-lang');
    btn.textContent = currentLang === 'en' ? '中' : 'EN';
  });

  // --- Mobile Menu ---
  const menuBtn = document.querySelector('.mobile-menu-btn');
  const mobileMenu = document.querySelector('.mobile-menu');
  if (menuBtn && mobileMenu) {
    menuBtn.addEventListener('click', () => {
      mobileMenu.classList.toggle('open');
    });
    mobileMenu.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => mobileMenu.classList.remove('open'));
    });
    document.addEventListener('click', (e) => {
      if (!menuBtn.contains(e.target) && !mobileMenu.contains(e.target)) {
        mobileMenu.classList.remove('open');
      }
    });
  }

  // --- Scroll-triggered fade-in ---
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.style.animationPlayState = 'running';
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });

  document.querySelectorAll('.fade-in').forEach(el => {
    el.style.animationPlayState = 'paused';
    observer.observe(el);
  });

  // --- Lightbox ---
  const lightboxHTML = `
    <div class="lightbox-overlay" role="dialog" aria-label="Image preview">
      <button class="lightbox-close" aria-label="Close">&times;</button>
      <button class="lightbox-nav lightbox-prev" aria-label="Previous">&#8249;</button>
      <button class="lightbox-nav lightbox-next" aria-label="Next">&#8250;</button>
      <div class="lightbox-image-wrap">
        <img src="" alt="">
        <div class="lightbox-counter"></div>
        <div class="lightbox-caption"></div>
      </div>
    </div>`;

  const body = document.body;
  body.insertAdjacentHTML('beforeend', lightboxHTML);

  const overlay = body.querySelector('.lightbox-overlay');
  const lightboxImg = overlay.querySelector('img');
  const lightboxCounter = overlay.querySelector('.lightbox-counter');
  const lightboxCaption = overlay.querySelector('.lightbox-caption');
  const closeBtn = overlay.querySelector('.lightbox-close');
  const prevBtn = overlay.querySelector('.lightbox-prev');
  const nextBtn = overlay.querySelector('.lightbox-next');

  let currentIndex = 0;
  let images = [];

  function getCaption(img) {
    // Find the first <p> sibling inside .screenshot-inner that has a matching lang
    const inner = img.closest('.screenshot-inner');
    if (!inner) return '';
    const currentLang = document.documentElement.getAttribute('data-lang') || 'en';
    const p = inner.querySelector(`p[lang="${currentLang}"]`);
    return p ? p.textContent.trim() : '';
  }

  function openLightbox(index) {
    if (!images.length || index < 0 || index >= images.length) return;
    currentIndex = index;
    const item = images[currentIndex];
    lightboxImg.classList.remove('loaded');
    lightboxImg.src = item.src;
    lightboxImg.alt = item.alt || '';
    lightboxCounter.textContent = `${currentIndex + 1} / ${images.length}`;
    lightboxCaption.textContent = item.caption;
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
    // Trigger load animation
    lightboxImg.onload = () => lightboxImg.classList.add('loaded');
    if (lightboxImg.complete) lightboxImg.classList.add('loaded');
  }

  function closeLightbox() {
    overlay.classList.remove('open');
    document.body.style.overflow = '';
  }

  function navigate(dir) {
    const next = currentIndex + dir;
    if (next >= 0 && next < images.length) openLightbox(next);
  }

  // Collect screenshot images — click on image or card opens lightbox
  document.querySelectorAll('.screenshot').forEach((card) => {
    const img = card.querySelector('img');
    if (!img) return;

    const idx = images.length;
    images.push({
      src: img.src,
      alt: img.alt,
      caption: getCaption(img),
      element: img,
    });

    card.addEventListener('click', (e) => {
      e.stopPropagation();
      // Re-collect images in case the DOM changed
      images = [];
      document.querySelectorAll('.screenshot img').forEach((im) => {
        images.push({
          src: im.src,
          alt: im.alt,
          caption: getCaption(im),
          element: im,
        });
      });
      const foundIndex = images.findIndex(item => item.src === img.src);
      openLightbox(foundIndex >= 0 ? foundIndex : idx);
    });
  });

  // Close on overlay background click
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) closeLightbox();
  });
  closeBtn.addEventListener('click', closeLightbox);
  prevBtn.addEventListener('click', () => navigate(-1));
  nextBtn.addEventListener('click', () => navigate(1));

  // Keyboard navigation
  document.addEventListener('keydown', (e) => {
    if (!overlay.classList.contains('open')) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') navigate(-1);
    if (e.key === 'ArrowRight') navigate(1);
  });

  // Update captions when language changes
  document.querySelectorAll('.lang-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      if (overlay.classList.contains('open')) {
        lightboxCaption.textContent = getCaption(images[currentIndex]?.element);
      }
    });
  });

})();
