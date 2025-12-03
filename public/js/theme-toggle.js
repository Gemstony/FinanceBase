'use strict';
(function(){
  var shopKey = (typeof window !== 'undefined' && window.DUKABASE_SHOP_KEY) ? window.DUKABASE_SHOP_KEY : '';
  var STORAGE_KEY = 'dukabase_theme' + shopKey; // 'light' | 'dark', scoped per shop
  var CLASS_DARK = 'dark-mode';

  function applyTheme(theme){
    var body = document.body;
    if (!body) return;
    if (theme === 'dark') {
      body.classList.add(CLASS_DARK);
    } else {
      body.classList.remove(CLASS_DARK);
    }
    // Swap icon and tooltip title
    var btn = document.querySelector('[data-role="theme-toggle"]');
    if (btn) {
      var icon = btn.querySelector('i');
      if (icon) {
        icon.classList.toggle('fa-moon', theme !== 'dark');
        icon.classList.toggle('fa-sun', theme === 'dark');
      }
      var title = theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';
      btn.setAttribute('title', title);
      btn.dataset.originalTitle = title; // for Bootstrap 4 stored title
      if (typeof $ === 'function' && typeof $.fn.tooltip === 'function') {
        try { $(btn).tooltip('dispose').tooltip(); } catch(e) {}
      }
    }
    // Persist
    try { localStorage.setItem(STORAGE_KEY, theme); } catch(e) {}
  }

  function currentTheme(){
    try { return localStorage.getItem(STORAGE_KEY) || 'light'; } catch(e) { return 'light'; }
  }

  function toggleTheme(){
    var theme = currentTheme() === 'dark' ? 'light' : 'dark';
    applyTheme(theme);
  }

  // Init on DOM ready
  document.addEventListener('DOMContentLoaded', function(){
    // default light
    applyTheme(currentTheme());
    // Click binding
    var btn = document.querySelector('[data-role="theme-toggle"]');
    if (btn) {
      btn.addEventListener('click', function(e){
        e.preventDefault();
        toggleTheme();
      });
    }
  });
})();
