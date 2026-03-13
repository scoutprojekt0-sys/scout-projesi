(function () {
    'use strict';

    const isLocal = window.location.hostname === 'localhost' || window.location.protocol === 'file:';
    const isDebugQuery = new URLSearchParams(window.location.search).get('debug') === '1';
    const debug = isLocal || isDebugQuery;

    const runtime = {
        debug,
        log: function () {
            if (!runtime.debug) return;
            console.log.apply(console, arguments);
        },
        warn: function () {
            if (!runtime.debug) return;
            console.warn.apply(console, arguments);
        },
        error: function () {
            console.error.apply(console, arguments);
        }
    };

    window.NextScoutRuntime = runtime;
})();
