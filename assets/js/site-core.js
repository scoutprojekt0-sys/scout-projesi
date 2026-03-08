(function (global) {
  'use strict';

  function toArray(input) {
    if (Array.isArray(input)) return input;
    if (input && Array.isArray(input.data)) return input.data;
    return [];
  }

  function toNumber(value, fallback) {
    var n = Number(value);
    return Number.isFinite(n) ? n : (fallback || 0);
  }

  function formatDateTR(value) {
    try {
      return new Date(value).toLocaleString('tr-TR');
    } catch (error) {
      return '';
    }
  }

  function normalizeText(value, maxLen) {
    var text = String(value == null ? '' : value)
      .replace(/\s+/g, ' ')
      .trim();
    if (maxLen && text.length > maxLen) return text.slice(0, maxLen).trim();
    return text;
  }

  // Lightweight editorial guard for admin/content forms.
  function editorialChecklist(payload) {
    var title = normalizeText(payload && payload.title, 120);
    var body = normalizeText(payload && payload.body, 1200);
    return {
      ok: Boolean(title && body && body.length >= 30),
      title: title,
      body: body,
      warnings: [
        title ? '' : 'Baslik bos',
        body.length < 30 ? 'Icerik cok kisa' : ''
      ].filter(Boolean)
    };
  }

  global.NextScoutData = {
    toArray: toArray,
    toNumber: toNumber,
    formatDateTR: formatDateTR,
    normalizeText: normalizeText,
    editorialChecklist: editorialChecklist
  };
})(window);
