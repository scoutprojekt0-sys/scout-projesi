(function (global) {
  'use strict';

  function normalize(value) {
    return String(value || '').trim().replace(/\/$/, '');
  }

  function readStored() {
    try {
      return normalize(global.localStorage.getItem('nextscout_api_base'));
    } catch (error) {
      return '';
    }
  }

  function persist(value) {
    var normalized = normalize(value);
    if (!normalized) return '';
    try {
      global.localStorage.setItem('nextscout_api_base', normalized);
    } catch (error) {}
    return normalized;
  }

  function localBackendBase() {
    return 'http://127.0.0.1:8000/api';
  }

  function resolve() {
    var configured = normalize(global.NEXTSCOUT_API_BASE);
    if (configured) return persist(configured);

    var stored = readStored();
    if (stored) return stored;

    if (global.location.protocol === 'http:' || global.location.protocol === 'https:') {
      var host = normalize(global.location.hostname).toLowerCase();
      var port = normalize(global.location.port);
      if ((host === '127.0.0.1' || host === 'localhost') && port && port !== '8000') {
        return persist(localBackendBase());
      }
      return persist(global.location.origin + '/api');
    }

    try {
      var params = new URLSearchParams(global.location.search);
      var fromQuery = normalize(params.get('api_base'));
      if (fromQuery) return persist(fromQuery);
    } catch (error) {}

    return persist(localBackendBase());
  }

  global.NextScoutApi = global.NextScoutApi || {};
  global.NextScoutApi.base = resolve;
  global.NextScoutApi.setBase = persist;
})(window);
