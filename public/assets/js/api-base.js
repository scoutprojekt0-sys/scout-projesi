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

  function resolve() {
    var configured = normalize(global.NEXTSCOUT_API_BASE);
    if (configured) return persist(configured);

    var stored = readStored();
    if (stored) return stored;

    if (global.location.protocol === 'http:' || global.location.protocol === 'https:') {
      return persist(global.location.origin + '/api');
    }

    try {
      var params = new URLSearchParams(global.location.search);
      var fromQuery = normalize(params.get('api_base'));
      if (fromQuery) return persist(fromQuery);
    } catch (error) {}

    return persist('http://127.0.0.1:8000/api');
  }

  global.NextScoutApi = global.NextScoutApi || {};
  global.NextScoutApi.base = resolve;
  global.NextScoutApi.setBase = persist;
})(window);
