/* NextScout Core Stability Layer */
(function () {
    'use strict';

    var IS_FILE_MODE = window.location.protocol === 'file:';

    function byId(id) {
        return document.getElementById(id);
    }

    function safeBind(selector, eventName, handler, options) {
        var el = document.querySelector(selector);
        if (!el || typeof handler !== 'function') return false;
        el.addEventListener(eventName, handler, options || false);
        return true;
    }

    function safeBindById(id, eventName, handler, options) {
        var el = byId(id);
        if (!el || typeof handler !== 'function') return false;
        el.addEventListener(eventName, handler, options || false);
        return true;
    }

    function safeNavigate(target, fallback) {
        var raw = String(target || '').trim();
        var next = raw || String(fallback || 'index.html');
        if (/^(https?:|mailto:|tel:)/i.test(next)) {
            window.location.href = next;
            return;
        }
        window.location.href = next.replace(/^\.\//, '');
    }

    async function safeFetchJson(url, fallback) {
        try {
            var response = await fetch(url);
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return await response.json();
        } catch (error) {
            return typeof fallback === 'undefined' ? null : fallback;
        }
    }

    window.NextScoutCore = {
        isFileMode: IS_FILE_MODE,
        byId: byId,
        safeBind: safeBind,
        safeBindById: safeBindById,
        safeNavigate: safeNavigate,
        safeFetchJson: safeFetchJson
    };

    document.documentElement.setAttribute('data-runtime', IS_FILE_MODE ? 'file' : 'web');
})();
