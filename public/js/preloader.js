'use strict';
(function() {
  function hidePreloader() {
    var el = document.querySelector('.preloader');
    if (!el) return;
    // fade out
    el.style.opacity = '0';
    // remove from flow after transition
    setTimeout(function(){
      if (el && el.parentNode) {
        el.parentNode.removeChild(el);
      }
    }, 400);
  }

  // Primary: hide after full load
  window.addEventListener('load', function() {
    hidePreloader();
  });

  // Fallback: ensure it hides even if load doesn't fire (e.g., cached resources)
  document.addEventListener('DOMContentLoaded', function(){
    setTimeout(hidePreloader, 2000);
  });

  // Extra: in case of SPA-like navigation hooks (optional)
  document.addEventListener('duka:hide-preloader', hidePreloader);
})();

// Initialize Bootstrap tooltips globally (requires jQuery + Bootstrap JS loaded)
// Runs after DOM ready
$(function () {
  if (typeof $.fn.tooltip === 'function') {
    $('[data-toggle="tooltip"]').tooltip();
  }
});
