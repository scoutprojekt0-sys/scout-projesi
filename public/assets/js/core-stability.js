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

    function getErrorBuffer() {
        try {
            var raw = localStorage.getItem('nextscout_client_errors');
            var parsed = raw ? JSON.parse(raw) : [];
            return Array.isArray(parsed) ? parsed : [];
        } catch (_e) {
            return [];
        }
    }

    function pushClientError(payload) {
        try {
            var list = getErrorBuffer();
            list.push(payload);
            if (list.length > 100) {
                list = list.slice(list.length - 100);
            }
            localStorage.setItem('nextscout_client_errors', JSON.stringify(list));
        } catch (_e) {
            // no-op: telemetry must not break runtime
        }
    }

    function setupClientErrorTelemetry() {
        if (window.__nextScoutClientTelemetryReady) return;
        window.__nextScoutClientTelemetryReady = true;

        window.addEventListener('error', function (event) {
            pushClientError({
                type: 'error',
                message: String((event && event.message) || 'Unknown JS error'),
                source: String((event && event.filename) || ''),
                line: Number((event && event.lineno) || 0),
                col: Number((event && event.colno) || 0),
                stack: String((event && event.error && event.error.stack) || ''),
                path: window.location.pathname,
                created_at: new Date().toISOString()
            });
        });

        window.addEventListener('unhandledrejection', function (event) {
            var reason = event ? event.reason : null;
            pushClientError({
                type: 'unhandledrejection',
                message: String((reason && reason.message) || reason || 'Unhandled promise rejection'),
                source: '',
                line: 0,
                col: 0,
                stack: String((reason && reason.stack) || ''),
                path: window.location.pathname,
                created_at: new Date().toISOString()
            });
        });
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
        safeFetchJson: safeFetchJson,
        getClientErrors: getErrorBuffer,
        clearClientErrors: function () {
            localStorage.removeItem('nextscout_client_errors');
        }
    };

    setupClientErrorTelemetry();

    document.documentElement.setAttribute('data-runtime', IS_FILE_MODE ? 'file' : 'web');
})();
