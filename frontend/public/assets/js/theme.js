(function () {
  // Vitrine: o tema vem da cor da loja (layout_store.php) e não tem botão.
  if (document.documentElement.hasAttribute('data-theme-locked')) return;
  var STORAGE_KEY = 'plataform_stores_theme';
  var SUN = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>';
  var MOON = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
  function getTheme() {
    try {
      var saved = localStorage.getItem(STORAGE_KEY);
      if (saved === 'dark' || saved === 'light') return saved;
    } catch (e) {}
    if (typeof window.matchMedia !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches) return 'dark';
    return 'light';
  }
  function setTheme(theme) {
    theme = theme === 'dark' ? 'dark' : 'light';
    var root = document.documentElement;
    if (root) root.setAttribute('data-theme', theme);
    try { localStorage.setItem(STORAGE_KEY, theme); } catch (e) {}
    var btn = document.getElementById('theme-toggle-btn');
    if (btn) {
      btn.setAttribute('aria-label', theme === 'dark' ? 'Ativar modo claro' : 'Ativar modo escuro');
      btn.title = theme === 'dark' ? 'Modo claro' : 'Modo escuro';
      btn.innerHTML = theme === 'dark' ? SUN : MOON;
    }
  }
  function toggleTheme() {
    setTheme(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
  }
  setTheme(getTheme());
  function addToggleButton() {
    if (document.getElementById('theme-toggle-btn')) return;
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.id = 'theme-toggle-btn';
    btn.className = 'theme-toggle';
    btn.setAttribute('aria-label', document.documentElement.getAttribute('data-theme') === 'dark' ? 'Ativar modo claro' : 'Ativar modo escuro');
    btn.title = document.documentElement.getAttribute('data-theme') === 'dark' ? 'Modo claro' : 'Modo escuro';
    btn.innerHTML = document.documentElement.getAttribute('data-theme') === 'dark' ? SUN : MOON;
    btn.addEventListener('click', toggleTheme);
    if (document.body) document.body.appendChild(btn);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', addToggleButton);
  } else {
    addToggleButton();
  }
})();
