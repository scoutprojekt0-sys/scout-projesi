(function (global) {
  'use strict';

  function nowIso() {
    return new Date().toISOString();
  }

  function track(eventName, payload) {
    try {
      var key = 'nextscout_telemetry_events';
      var rows = JSON.parse(localStorage.getItem(key) || '[]');
      rows.unshift({
        event: String(eventName || 'event'),
        payload: payload || {},
        ts: nowIso()
      });
      localStorage.setItem(key, JSON.stringify(rows.slice(0, 500)));
    } catch (error) {}
  }

  function idle(fn, timeout) {
    if (typeof fn !== 'function') return;
    if ('requestIdleCallback' in global) {
      global.requestIdleCallback(fn, { timeout: timeout || 1200 });
      return;
    }
    setTimeout(fn, 120);
  }

  function bindAutoTelemetry(root) {
    var scope = root || document;
    scope.addEventListener('click', function (event) {
      var target = event.target && event.target.closest
        ? event.target.closest('button, a, [data-track]')
        : null;
      if (!target) return;
      var label = target.getAttribute('data-track') || target.textContent || target.id || target.className || 'click';
      track('ui_click', { label: String(label).trim().slice(0, 80) });
    }, { passive: true });
  }

  global.NextScoutGrowth = {
    track: track,
    idle: idle,
    bindAutoTelemetry: bindAutoTelemetry
  };
})(window);
