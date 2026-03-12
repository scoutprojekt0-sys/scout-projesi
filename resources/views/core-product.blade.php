<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Core Product UX</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <a class="ds-skip-link" href="#coreMain">Skip to core content</a>
        <main id="coreMain" class="ds-page ds-stack">
            <header class="ds-stack">
                <h1 class="ds-h1">Core Product UX</h1>
                <p class="ds-caption">Opportunities, applications and profile updates in one operational flow.</p>
            </header>

            <section class="ds-card ds-stack" aria-labelledby="coreQuickStartTitle">
                <h2 id="coreQuickStartTitle" class="ds-h2">Quick Start</h2>
                <ol class="ds-stack">
                    <li>Paste a valid bearer token.</li>
                    <li>Click <strong>Load Core Flow</strong> to load profile, opportunities and applications.</li>
                    <li>Use filters, then share URL query state for fast role-based UAT review.</li>
                </ol>
            </section>

            <section class="ds-card ds-stack">
                <div class="ds-field">
                    <label class="ds-label" for="tokenInput">Bearer Token</label>
                    <input id="tokenInput" class="ds-input" type="text" placeholder="1|xxxxxxxx..." autocomplete="off">
                    <p class="ds-help">Token is used only for requests on this page and is never persisted.</p>
                </div>
                <div class="ds-button-row">
                    <button class="ds-btn ds-btn-primary" id="bootstrapBtn" type="button">Load Core Flow</button>
                </div>
                <div id="globalStatus" role="status" aria-live="polite"></div>
            </section>

            <section class="ds-grid lg:grid-cols-3">
                <article class="ds-card ds-stack lg:col-span-1">
                    <h2 class="ds-h2">Profile</h2>
                    <p class="ds-caption" id="profileRole">Role: -</p>
                    <div class="ds-field">
                        <label class="ds-label" for="profileName">Name</label>
                        <input id="profileName" class="ds-input" type="text">
                    </div>
                    <div class="ds-field">
                        <label class="ds-label" for="profileEmail">Email</label>
                        <input id="profileEmail" class="ds-input" type="email">
                    </div>
                    <div class="ds-field">
                        <label class="ds-label" for="profileCity">City</label>
                        <input id="profileCity" class="ds-input" type="text">
                    </div>
                    <div class="ds-field">
                        <label class="ds-label" for="profilePhone">Phone</label>
                        <input id="profilePhone" class="ds-input" type="text">
                    </div>
                    <div class="ds-field">
                        <label class="ds-label" for="profilePassword">New Password (optional)</label>
                        <input id="profilePassword" class="ds-input" type="password">
                    </div>
                    <div class="ds-button-row">
                        <button class="ds-btn ds-btn-secondary" id="reloadProfileBtn" type="button">Reload Profile</button>
                        <button class="ds-btn ds-btn-primary" id="saveProfileBtn" type="button">Save Profile</button>
                    </div>
                </article>

                <article class="ds-card ds-stack lg:col-span-2">
                    <h2 class="ds-h2">Opportunities</h2>
                    <div class="grid gap-3 md:grid-cols-6">
                        <div class="ds-field">
                            <label class="ds-label" for="fltStatus">Status</label>
                            <select id="fltStatus" class="ds-input">
                                <option value="">All</option>
                                <option value="open">Open</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                        <div class="ds-field">
                            <label class="ds-label" for="fltPosition">Position</label>
                            <input id="fltPosition" class="ds-input" type="text" placeholder="Forward">
                        </div>
                        <div class="ds-field">
                            <label class="ds-label" for="fltCity">City</label>
                            <input id="fltCity" class="ds-input" type="text" placeholder="Istanbul">
                        </div>
                        <div class="ds-field">
                            <label class="ds-label" for="fltPerPage">Per page</label>
                            <select id="fltPerPage" class="ds-input">
                                <option value="5">5</option>
                                <option value="10" selected>10</option>
                                <option value="20">20</option>
                            </select>
                        </div>
                        <div class="ds-field">
                            <label class="ds-label" for="fltSortBy">Sort by</label>
                            <select id="fltSortBy" class="ds-input">
                                <option value="created_at">Created</option>
                                <option value="title">Title</option>
                                <option value="status">Status</option>
                                <option value="city">City</option>
                            </select>
                        </div>
                        <div class="ds-field">
                            <label class="ds-label" for="fltSortDir">Direction</label>
                            <select id="fltSortDir" class="ds-input">
                                <option value="desc">Desc</option>
                                <option value="asc">Asc</option>
                            </select>
                        </div>
                    </div>
                    <div class="ds-button-row">
                        <button class="ds-btn ds-btn-primary" id="applyFiltersBtn" type="button">Apply Filters</button>
                        <button class="ds-btn ds-btn-secondary" id="resetFiltersBtn" type="button">Reset</button>
                    </div>
                    <div id="opportunitiesWrap" class="ds-stack" aria-live="polite"></div>
                    <div class="ds-button-row ds-sticky-actions">
                        <button class="ds-btn ds-btn-secondary" id="oppPrevBtn" type="button">Prev</button>
                        <button class="ds-btn ds-btn-secondary" id="oppNextBtn" type="button">Next</button>
                        <span class="ds-caption" id="oppPageInfo">Page -</span>
                        <span class="ds-caption" id="oppTotalInfo">Total -</span>
                    </div>
                </article>
            </section>

            <section class="ds-card ds-stack">
                <h2 class="ds-h2">Applications</h2>
                <p class="ds-caption" id="applicationsHint">Role-based stream</p>
                <div class="grid gap-3 md:grid-cols-4">
                    <div class="ds-field">
                    <label class="ds-label" for="appStatus">Status</label>
                    <select id="appStatus" class="ds-input">
                        <option value="">All</option>
                        <option value="pending">Pending</option>
                        <option value="accepted">Accepted</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    </div>
                    <div class="ds-field">
                        <label class="ds-label" for="appSortBy">Sort by</label>
                        <select id="appSortBy" class="ds-input">
                            <option value="created_at">Created</option>
                            <option value="status">Status</option>
                            <option value="opportunity_title">Opportunity</option>
                        </select>
                    </div>
                    <div class="ds-field">
                        <label class="ds-label" for="appSortDir">Direction</label>
                        <select id="appSortDir" class="ds-input">
                            <option value="desc">Desc</option>
                            <option value="asc">Asc</option>
                        </select>
                    </div>
                    <div class="ds-field">
                        <label class="ds-label" for="appPerPage">Per page</label>
                        <select id="appPerPage" class="ds-input">
                            <option value="5">5</option>
                            <option value="10" selected>10</option>
                            <option value="20">20</option>
                        </select>
                    </div>
                </div>
                <div class="ds-button-row">
                    <button class="ds-btn ds-btn-primary" id="loadApplicationsBtn" type="button">Load Applications</button>
                </div>
                <div id="applicationsWrap" class="ds-stack" aria-live="polite"></div>
                <div class="ds-button-row ds-sticky-actions">
                    <button class="ds-btn ds-btn-secondary" id="appPrevBtn" type="button">Prev</button>
                    <button class="ds-btn ds-btn-secondary" id="appNextBtn" type="button">Next</button>
                    <span class="ds-caption" id="appPageInfo">Page -</span>
                    <span class="ds-caption" id="appTotalInfo">Total -</span>
                </div>
            </section>
        </main>

        <script>
            const state = {
                me: null,
                opp: {
                    page: 1,
                    status: '',
                    position: '',
                    city: '',
                    perPage: '10',
                    sortBy: 'created_at',
                    sortDir: 'desc',
                },
                app: {
                    page: 1,
                    status: '',
                    perPage: '10',
                    sortBy: 'created_at',
                    sortDir: 'desc',
                },
            };

            const tokenInput = document.getElementById('tokenInput');
            const globalStatus = document.getElementById('globalStatus');
            const profileRole = document.getElementById('profileRole');
            const profileName = document.getElementById('profileName');
            const profileEmail = document.getElementById('profileEmail');
            const profileCity = document.getElementById('profileCity');
            const profilePhone = document.getElementById('profilePhone');
            const profilePassword = document.getElementById('profilePassword');
            const opportunitiesWrap = document.getElementById('opportunitiesWrap');
            const oppPageInfo = document.getElementById('oppPageInfo');
            const oppTotalInfo = document.getElementById('oppTotalInfo');
            const applicationsWrap = document.getElementById('applicationsWrap');
            const applicationsHint = document.getElementById('applicationsHint');
            const appPageInfo = document.getElementById('appPageInfo');
            const appTotalInfo = document.getElementById('appTotalInfo');

            function intOrDefault(value, fallback = 1) {
                const parsed = Number.parseInt(String(value || ''), 10);
                return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback;
            }

            function loadStateFromUrl() {
                const params = new URLSearchParams(window.location.search);
                state.opp.page = intOrDefault(params.get('opp_page'), 1);
                state.opp.status = params.get('opp_status') || '';
                state.opp.position = params.get('opp_position') || '';
                state.opp.city = params.get('opp_city') || '';
                state.opp.perPage = params.get('opp_per_page') || '10';
                state.opp.sortBy = params.get('opp_sort_by') || 'created_at';
                state.opp.sortDir = params.get('opp_sort_dir') || 'desc';

                state.app.page = intOrDefault(params.get('app_page'), 1);
                state.app.status = params.get('app_status') || '';
                state.app.perPage = params.get('app_per_page') || '10';
                state.app.sortBy = params.get('app_sort_by') || 'created_at';
                state.app.sortDir = params.get('app_sort_dir') || 'desc';
            }

            function writeStateToUrl() {
                const params = new URLSearchParams();
                params.set('opp_page', String(state.opp.page));
                if (state.opp.status) params.set('opp_status', state.opp.status);
                if (state.opp.position) params.set('opp_position', state.opp.position);
                if (state.opp.city) params.set('opp_city', state.opp.city);
                params.set('opp_per_page', state.opp.perPage);
                params.set('opp_sort_by', state.opp.sortBy);
                params.set('opp_sort_dir', state.opp.sortDir);

                params.set('app_page', String(state.app.page));
                if (state.app.status) params.set('app_status', state.app.status);
                params.set('app_per_page', state.app.perPage);
                params.set('app_sort_by', state.app.sortBy);
                params.set('app_sort_dir', state.app.sortDir);

                const query = params.toString();
                const nextUrl = query ? `${window.location.pathname}?${query}` : window.location.pathname;
                window.history.replaceState({}, '', nextUrl);
            }

            function hydrateControlsFromState() {
                document.getElementById('fltStatus').value = state.opp.status;
                document.getElementById('fltPosition').value = state.opp.position;
                document.getElementById('fltCity').value = state.opp.city;
                document.getElementById('fltPerPage').value = state.opp.perPage;
                document.getElementById('fltSortBy').value = state.opp.sortBy;
                document.getElementById('fltSortDir').value = state.opp.sortDir;

                document.getElementById('appStatus').value = state.app.status;
                document.getElementById('appPerPage').value = state.app.perPage;
                document.getElementById('appSortBy').value = state.app.sortBy;
                document.getElementById('appSortDir').value = state.app.sortDir;
            }

            function headers() {
                return {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${tokenInput.value.trim()}`,
                };
            }

            function notify(type, message) {
                const klass = type === 'error' ? 'ds-alert ds-alert-danger'
                    : type === 'warn' ? 'ds-alert ds-alert-warning'
                    : 'ds-alert ds-alert-success';
                globalStatus.innerHTML = `<div class="${klass}">${escapeHtml(message)}</div>`;
            }

            function setState(node, kind, text) {
                const klass = kind === 'error' ? 'ds-state ds-state-error'
                    : kind === 'empty' ? 'ds-state ds-state-empty'
                    : 'ds-state ds-state-loading';
                node.setAttribute('aria-busy', kind === 'loading' ? 'true' : 'false');
                node.innerHTML = `<div class="${klass}">${escapeHtml(text)}</div>`;
            }

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            async function api(path, method = 'GET', body = null) {
                const response = await fetch(path, {
                    method,
                    headers: headers(),
                    body: body ? JSON.stringify(body) : null,
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || data.ok === false) {
                    const message = data.message || `API error (${response.status})`;
                    throw new Error(message);
                }
                return data;
            }

            function fmtDate(value) {
                if (!value) return '-';
                const d = new Date(value);
                return Number.isNaN(d.getTime()) ? value : d.toLocaleString();
            }

            async function loadProfile() {
                const data = await api('/api/auth/me');
                state.me = data.data;
                profileRole.textContent = `Role: ${state.me.role || '-'}`;
                profileName.value = state.me.name || '';
                profileEmail.value = state.me.email || '';
                profileCity.value = state.me.city || '';
                profilePhone.value = state.me.phone || '';
                profilePassword.value = '';
            }

            async function saveProfile() {
                const payload = {
                    name: profileName.value.trim(),
                    email: profileEmail.value.trim(),
                    city: profileCity.value.trim() || null,
                    phone: profilePhone.value.trim() || null,
                };
                const password = profilePassword.value.trim();
                if (password !== '') {
                    payload.password = password;
                    payload.password_confirmation = password;
                }

                await api('/api/auth/me', 'PUT', payload);
                notify('success', 'Profile updated successfully.');
                await loadProfile();
            }

            function opportunityQuery() {
                const query = new URLSearchParams();
                if (state.opp.status) query.set('status', state.opp.status);
                if (state.opp.position) query.set('position', state.opp.position);
                if (state.opp.city) query.set('city', state.opp.city);
                query.set('page', String(state.opp.page));
                query.set('per_page', state.opp.perPage || '10');
                query.set('sort_by', state.opp.sortBy || 'created_at');
                query.set('sort_dir', state.opp.sortDir || 'desc');
                return query.toString();
            }

            async function loadOpportunities() {
                setState(opportunitiesWrap, 'loading', 'Firsatlar yukleniyor...');
                try {
                    const data = await api(`/api/opportunities?${opportunityQuery()}`);
                    const page = data.data;
                    const rows = page.data || [];

                    if (!rows.length) {
                        setState(opportunitiesWrap, 'empty', 'Filtreye uygun firsat bulunamadi.');
                    } else {
                        opportunitiesWrap.innerHTML = rows.map((row) => {
                            const applyArea = state.me?.role === 'player' && row.status === 'open'
                                ? `
                                    <div class="ds-field">
                                        <label class="ds-label" for="msg-${row.id}">Application Message</label>
                                        <textarea id="msg-${row.id}" class="ds-input" rows="2" placeholder="Kisa bir mesaj ekle"></textarea>
                                    </div>
                                    <button class="ds-btn ds-btn-primary apply-btn" data-id="${row.id}" type="button">Apply</button>
                                `
                                : '';

                            return `
                                <article class="ds-card ds-stack">
                                    <h3 class="ds-h2">${escapeHtml(row.title || '-')}</h3>
                                    <p class="ds-caption">
                                        Team: ${escapeHtml(row.team_name || '-')} | City: ${escapeHtml(row.city || '-')} | Status: ${escapeHtml(row.status || '-')}
                                    </p>
                                    <p>${escapeHtml(row.details || 'Detay yok.')}</p>
                                    ${applyArea}
                                </article>
                            `;
                        }).join('');
                    }

                    oppPageInfo.textContent = `Page ${page.current_page || 1} / ${page.last_page || 1}`;
                    oppTotalInfo.textContent = `Total ${page.total ?? 0}`;
                    state.opp.page = intOrDefault(page.current_page, 1);
                    document.getElementById('oppPrevBtn').disabled = !(state.opp.page > 1);
                    document.getElementById('oppNextBtn').disabled = !(page.current_page < page.last_page);
                    writeStateToUrl();
                } catch (error) {
                    setState(opportunitiesWrap, 'error', error.message || 'Firsatlar yuklenemedi.');
                    throw error;
                }
            }

            async function applyToOpportunity(opportunityId) {
                const msgEl = document.getElementById(`msg-${opportunityId}`);
                const message = msgEl ? msgEl.value.trim() : '';
                await api(`/api/opportunities/${opportunityId}/apply`, 'POST', { message: message || null });
                notify('success', 'Basvuru gonderildi.');
                await loadApplications();
            }

            function applicationsQuery() {
                const query = new URLSearchParams();
                if (state.app.status) query.set('status', state.app.status);
                query.set('page', String(state.app.page));
                query.set('per_page', state.app.perPage || '10');
                query.set('sort_by', state.app.sortBy || 'created_at');
                query.set('sort_dir', state.app.sortDir || 'desc');
                return query.toString();
            }

            async function loadApplications() {
                if (!state.me) return;
                setState(applicationsWrap, 'loading', 'Basvurular yukleniyor...');

                let path = null;
                if (state.me.role === 'player') {
                    path = `/api/applications/outgoing?${applicationsQuery()}`;
                    applicationsHint.textContent = 'Outgoing applications (player view)';
                } else if (state.me.role === 'team') {
                    path = `/api/applications/incoming?${applicationsQuery()}`;
                    applicationsHint.textContent = 'Incoming applications (team view)';
                } else {
                    applicationsHint.textContent = 'Bu rol icin basvuru listesi yok.';
                    setState(applicationsWrap, 'empty', 'Bu rol icin uygulama akis ekrani bulunmuyor.');
                    appPageInfo.textContent = 'Page -';
                    appTotalInfo.textContent = 'Total -';
                    return;
                }

                try {
                    const data = await api(path);
                    const page = data.data;
                    const rows = page.data || [];

                    if (!rows.length) {
                        setState(applicationsWrap, 'empty', 'Basvuru kaydi bulunamadi.');
                    } else {
                        applicationsWrap.innerHTML = rows.map((row) => {
                            const teamAction = state.me.role === 'team' && row.status === 'pending'
                                ? `
                                    <div class="ds-button-row">
                                        <button class="ds-btn ds-btn-primary app-status-btn" data-id="${row.id}" data-status="accepted" type="button">Accept</button>
                                        <button class="ds-btn ds-btn-secondary app-status-btn" data-id="${row.id}" data-status="rejected" type="button">Reject</button>
                                    </div>
                                `
                                : '';
                            return `
                                <article class="ds-card ds-stack">
                                    <h3 class="ds-h2">${escapeHtml(row.opportunity_title || '-')}</h3>
                                    <p class="ds-caption">
                                        Status: ${escapeHtml(row.status || '-')} | Created: ${escapeHtml(fmtDate(row.created_at))}
                                    </p>
                                    <p>${escapeHtml(row.message || 'Mesaj yok.')}</p>
                                    ${teamAction}
                                </article>
                            `;
                        }).join('');
                    }

                    appPageInfo.textContent = `Page ${page.current_page || 1} / ${page.last_page || 1}`;
                    appTotalInfo.textContent = `Total ${page.total ?? 0}`;
                    state.app.page = intOrDefault(page.current_page, 1);
                    document.getElementById('appPrevBtn').disabled = !(state.app.page > 1);
                    document.getElementById('appNextBtn').disabled = !(page.current_page < page.last_page);
                    writeStateToUrl();
                } catch (error) {
                    setState(applicationsWrap, 'error', error.message || 'Basvurular yuklenemedi.');
                    throw error;
                }
            }

            async function changeApplicationStatus(applicationId, status) {
                await api(`/api/applications/${applicationId}/status`, 'PATCH', { status });
                notify('success', 'Basvuru durumu guncellendi.');
                await loadApplications();
            }

            async function bootstrap() {
                if (!tokenInput.value.trim()) {
                    notify('warn', 'Enter a bearer token to continue.');
                    return;
                }

                try {
                    await loadProfile();
                    await loadOpportunities();
                    await loadApplications();
                    notify('success', 'Core flow loaded.');
                } catch (error) {
                    notify('error', error.message || 'Core flow could not be loaded.');
                }
            }

            document.getElementById('bootstrapBtn').addEventListener('click', bootstrap);
            document.getElementById('reloadProfileBtn').addEventListener('click', () => loadProfile().catch((e) => notify('error', e.message)));
            document.getElementById('saveProfileBtn').addEventListener('click', () => saveProfile().catch((e) => notify('error', e.message)));
            document.getElementById('applyFiltersBtn').addEventListener('click', () => {
                state.opp.page = 1;
                state.opp.status = document.getElementById('fltStatus').value.trim();
                state.opp.position = document.getElementById('fltPosition').value.trim();
                state.opp.city = document.getElementById('fltCity').value.trim();
                state.opp.perPage = document.getElementById('fltPerPage').value || '10';
                state.opp.sortBy = document.getElementById('fltSortBy').value || 'created_at';
                state.opp.sortDir = document.getElementById('fltSortDir').value || 'desc';
                loadOpportunities().catch((e) => notify('error', e.message));
            });
            document.getElementById('resetFiltersBtn').addEventListener('click', () => {
                state.opp = {
                    page: 1,
                    status: '',
                    position: '',
                    city: '',
                    perPage: '10',
                    sortBy: 'created_at',
                    sortDir: 'desc',
                };
                hydrateControlsFromState();
                loadOpportunities().catch((e) => notify('error', e.message));
            });
            document.getElementById('oppPrevBtn').addEventListener('click', () => {
                state.opp.page = Math.max(1, state.opp.page - 1);
                loadOpportunities().catch((e) => notify('error', e.message));
            });
            document.getElementById('oppNextBtn').addEventListener('click', () => {
                state.opp.page += 1;
                loadOpportunities().catch((e) => notify('error', e.message));
            });
            document.getElementById('loadApplicationsBtn').addEventListener('click', () => {
                state.app.page = 1;
                state.app.status = document.getElementById('appStatus').value.trim();
                state.app.perPage = document.getElementById('appPerPage').value || '10';
                state.app.sortBy = document.getElementById('appSortBy').value || 'created_at';
                state.app.sortDir = document.getElementById('appSortDir').value || 'desc';
                loadApplications().catch((e) => notify('error', e.message));
            });
            document.getElementById('appPrevBtn').addEventListener('click', () => {
                state.app.page = Math.max(1, state.app.page - 1);
                loadApplications().catch((e) => notify('error', e.message));
            });
            document.getElementById('appNextBtn').addEventListener('click', () => {
                state.app.page += 1;
                loadApplications().catch((e) => notify('error', e.message));
            });

            opportunitiesWrap.addEventListener('click', (event) => {
                const button = event.target.closest('.apply-btn');
                if (!button) return;
                const id = button.getAttribute('data-id');
                if (!id) return;
                applyToOpportunity(id).catch((e) => notify('error', e.message));
            });

            applicationsWrap.addEventListener('click', (event) => {
                const button = event.target.closest('.app-status-btn');
                if (!button) return;
                const id = button.getAttribute('data-id');
                const status = button.getAttribute('data-status');
                if (!id || !status) return;
                if (!confirm(`Status ${status} olarak guncellensin mi?`)) return;
                changeApplicationStatus(id, status).catch((e) => notify('error', e.message));
            });

            loadStateFromUrl();
            hydrateControlsFromState();
        </script>
    </body>
</html>
