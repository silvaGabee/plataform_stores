(function () {
  var STORAGE_KEY = 'plataform_stores_theme';
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
      btn.innerHTML = theme === 'dark' ? '&#9728;' : '&#9790;';
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
    btn.innerHTML = document.documentElement.getAttribute('data-theme') === 'dark' ? '&#9728;' : '&#9790;';
    btn.addEventListener('click', toggleTheme);
    if (document.body) document.body.appendChild(btn);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', addToggleButton);
  } else {
    addToggleButton();
  }
})();
