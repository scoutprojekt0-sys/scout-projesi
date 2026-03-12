/* NextScout mobile professional layer: UI polish + perf + API runtime */
(function (window, document) {
    'use strict';

    var MOBILE_WIDTH = 1024;
    var API_TIMEOUT_MS = 8000;

    function isMobileView() {
        return window.innerWidth <= MOBILE_WIDTH;
    }

    function applyViewportFlags() {
        var root = document.documentElement;
        if (isMobileView()) root.classList.add('is-mobile-view');
        else root.classList.remove('is-mobile-view');
    }

    function enhanceImages() {
        var images = document.querySelectorAll('img');
        images.forEach(function (img) {
            if (!img.hasAttribute('loading')) img.setAttribute('loading', 'lazy');
            if (!img.hasAttribute('decoding')) img.setAttribute('decoding', 'async');
        });
    }

    function enableContentVisibility() {
        var selectors = [
            '.players-grid',
            '.mobile-player-list',
            '.quick-stats',
            '.panel',
            '.sidebar',
            '.results-section',
            '.search-section'
        ];
        selectors.forEach(function (selector) {
            document.querySelectorAll(selector).forEach(function (el) {
                el.style.contentVisibility = 'auto';
                el.style.containIntrinsicSize = el.style.containIntrinsicSize || '720px';
            });
        });
    }

    function withTimeout(promise, timeoutMs) {
        var timer;
        return Promise.race([
            promise,
            new Promise(function (_, reject) {
                timer = setTimeout(function () {
                    reject(new Error('timeout'));
                }, timeoutMs);
            })
        ]).finally(function () {
            clearTimeout(timer);
        });
    }

    function resolveApiBase() {
        var fromStorage = '';
        try {
            fromStorage = localStorage.getItem('NEXTSCOUT_API_BASE') || '';
        } catch (error) {
            fromStorage = '';
        }

        var fromWindow = window.NEXTSCOUT_API_BASE || '';
        var defaultBase = (window.location.protocol === 'http:' || window.location.protocol === 'https:') ? (window.location.origin + '/api') : 'http://127.0.0.1:8000/api';
        var base = String(fromStorage || fromWindow || defaultBase).trim();
        return base.replace(/\/+$/, '');
    }

    function authHeaders() {
        var token = '';
        try {
            token = localStorage.getItem('token') || localStorage.getItem('authToken') || '';
        } catch (error) {
            token = '';
        }
        var headers = { Accept: 'application/json', 'Content-Type': 'application/json' };
        if (token) headers.Authorization = 'Bearer ' + token;
        return headers;
    }

    async function apiRequest(path, options) {
        var base = resolveApiBase();
        var url = String(path || '').startsWith('http') ? String(path) : (base + '/' + String(path || '').replace(/^\/+/, ''));
        var opts = options || {};
        var method = (opts.method || 'GET').toUpperCase();
        var payload = typeof opts.body === 'undefined' ? null : opts.body;

        var request = fetch(url, {
            method: method,
            headers: authHeaders(),
            body: payload == null ? null : JSON.stringify(payload)
        }).then(async function (res) {
            var json = null;
            try {
                json = await res.json();
            } catch (error) {
                json = null;
            }
            return { ok: res.ok, status: res.status, data: json };
        });

        return withTimeout(request, API_TIMEOUT_MS);
    }

    function mountApiStatusBadge() {
        if (!isMobileView()) return;
        if (document.getElementById('nsApiStatus')) return;

        var badge = document.createElement('div');
        badge.id = 'nsApiStatus';
        badge.style.cssText = [
            'position:fixed',
            'right:10px',
            'bottom:10px',
            'z-index:9999',
            'padding:6px 10px',
            'border-radius:999px',
            'font-size:11px',
            'font-weight:700',
            'background:#1f2937',
            'color:#e2e8f0',
            'border:1px solid #475569',
            'box-shadow:0 6px 16px rgba(2,6,23,.35)'
        ].join(';');
        badge.textContent = 'API: kontrol...';
        document.body.appendChild(badge);
    }

    function ensureToastRoot() {
        var root = document.getElementById('nsToastRoot');
        if (root) return root;
        root = document.createElement('div');
        root.id = 'nsToastRoot';
        root.className = 'ns-toast-root';
        document.body.appendChild(root);
        return root;
    }

    function notify(type, message) {
        var root = ensureToastRoot();
        var toast = document.createElement('div');
        toast.className = 'ns-toast ns-toast-' + (type || 'info');
        toast.textContent = String(message || '').trim() || 'Bilgi';
        root.appendChild(toast);
        requestAnimationFrame(function () {
            toast.classList.add('show');
        });
        setTimeout(function () {
            toast.classList.remove('show');
            setTimeout(function () {
                toast.remove();
            }, 220);
        }, 2600);
    }

    function inferNotifyType(message) {
        var text = String(message || '').toLowerCase();
        if (!text) return 'info';
        if (
            text.indexOf('hata') >= 0 ||
            text.indexOf('olamadi') >= 0 ||
            text.indexOf('gecersiz') >= 0 ||
            text.indexOf('zorunlu') >= 0 ||
            text.indexOf('bulunamadi') >= 0 ||
            text.indexOf('yanlis') >= 0 ||
            text.indexOf('offline') >= 0
        ) return 'error';
        if (
            text.indexOf('basar') >= 0 ||
            text.indexOf('kaydedildi') >= 0 ||
            text.indexOf('gönderildi') >= 0 ||
            text.indexOf('eklendi') >= 0 ||
            text.indexOf('onay') >= 0 ||
            text.indexOf('doğrulandı') >= 0
        ) return 'success';
        return 'info';
    }

    function patchAlert() {
        if (window.__nsAlertPatched) return;
        var originalAlert = window.alert;
        window.alert = function (msg) {
            notify(inferNotifyType(msg), msg);
            if (!isMobileView() && typeof originalAlert === 'function') {
                // Desktop fallback behavior remains native.
                return originalAlert.call(window, msg);
            }
        };
        window.__nsAlertPatched = true;
    }

    function installFormLoadingUX() {
        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!(form instanceof HTMLFormElement)) return;
            var submitter = event.submitter || form.querySelector('button[type="submit"], input[type="submit"]');
            if (!submitter || submitter.dataset.nsBusy === '1') return;

            submitter.dataset.nsBusy = '1';
            submitter.dataset.nsOldText = submitter.textContent || submitter.value || '';
            submitter.classList.add('ns-btn-loading');
            submitter.disabled = true;
            if (submitter.tagName === 'INPUT') {
                submitter.value = 'Yukleniyor...';
            } else {
                submitter.textContent = 'Yukleniyor...';
            }

            setTimeout(function () {
                submitter.classList.remove('ns-btn-loading');
                submitter.disabled = false;
                if (submitter.tagName === 'INPUT') {
                    submitter.value = submitter.dataset.nsOldText || 'Gönder';
                } else {
                    submitter.textContent = submitter.dataset.nsOldText || 'Gönder';
                }
                submitter.dataset.nsBusy = '0';
            }, 1800);
        }, true);
    }

    async function updateApiHealth() {
        var badge = document.getElementById('nsApiStatus');
        try {
            var res = await apiRequest('/ping');
            var healthy = Boolean(res && res.ok && res.data && res.data.ok !== false);
            document.documentElement.setAttribute('data-api-status', healthy ? 'online' : 'degraded');
            if (badge) {
                badge.textContent = healthy ? 'API: online' : 'API: sorun';
                badge.style.borderColor = healthy ? '#22c55e' : '#f59e0b';
                badge.style.color = healthy ? '#bbf7d0' : '#fde68a';
            }
        } catch (error) {
            document.documentElement.setAttribute('data-api-status', 'offline');
            if (badge) {
                badge.textContent = 'API: offline';
                badge.style.borderColor = '#ef4444';
                badge.style.color = '#fecaca';
            }
        }
    }

    function exposeApiClient() {
        window.NextScoutApi = {
            base: resolveApiBase,
            get: function (path) { return apiRequest(path, { method: 'GET' }); },
            post: function (path, body) { return apiRequest(path, { method: 'POST', body: body }); },
            put: function (path, body) { return apiRequest(path, { method: 'PUT', body: body }); },
            patch: function (path, body) { return apiRequest(path, { method: 'PATCH', body: body }); },
            del: function (path) { return apiRequest(path, { method: 'DELETE' }); },
            health: updateApiHealth
        };
        window.NextScoutUI = {
            notify: notify,
            success: function (msg) { notify('success', msg); },
            error: function (msg) { notify('error', msg); },
            info: function (msg) { notify('info', msg); }
        };
        window.nsNotify = window.NextScoutUI;
        window.nsAlert = function (msg) {
            notify(inferNotifyType(msg), msg);
        };
    }

    function boot() {
        applyViewportFlags();
        enhanceImages();
        enableContentVisibility();
        patchAlert();
        installFormLoadingUX();
        exposeApiClient();
        mountApiStatusBadge();
        updateApiHealth();
    }

    document.addEventListener('DOMContentLoaded', boot, { once: true });
    window.addEventListener('resize', applyViewportFlags, { passive: true });
})(window, document);
