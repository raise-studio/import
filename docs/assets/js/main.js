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

})();
