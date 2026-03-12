<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Messaging + Media</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <a class="ds-skip-link" href="#commMain">Skip to messaging content</a>
        <main id="commMain" class="ds-page ds-stack">
            <header class="ds-stack">
                <h1 class="ds-h1">Messaging + Media</h1>
                <p class="ds-caption">Manage inbox/sent, compose flow and media upload/list/delete in one screen.</p>
            </header>

            <section class="ds-card ds-stack" aria-labelledby="commQuickStartTitle">
                <h2 id="commQuickStartTitle" class="ds-h2">Quick Start</h2>
                <ol class="ds-stack">
                    <li>Paste a bearer token and click <strong>Load Messaging + Media</strong>.</li>
                    <li>Send one test message and confirm inbox/sent visibility.</li>
                    <li>Upload and then delete one media file to validate full lifecycle.</li>
                </ol>
            </section>

            <section class="ds-card ds-stack">
                <div class="ds-field">
                    <label class="ds-label" for="tokenInput">Bearer Token</label>
                    <input id="tokenInput" class="ds-input" type="text" placeholder="1|xxxxxxxx..." autocomplete="off">
                    <p class="ds-help">Token is used only for API calls in this page and is not stored.</p>
                </div>
                <div class="ds-button-row">
                    <button class="ds-btn ds-btn-primary" id="bootstrapBtn" type="button">Load Messaging + Media</button>
                </div>
                <div id="globalStatus" role="status" aria-live="polite"></div>
            </section>

            <section class="ds-grid lg:grid-cols-2">
                <article class="ds-card ds-stack">
                    <h2 class="ds-h2">Compose Message</h2>
                    <div class="ds-field">
                        <label class="ds-label" for="toUserId">To User ID</label>
                        <input id="toUserId" class="ds-input" type="number" min="1" placeholder="2">
                    </div>
                    <div class="ds-field">
                        <label class="ds-label" for="subject">Subject</label>
                        <input id="subject" class="ds-input" type="text" maxlength="160" placeholder="Transfer interest">
                    </div>
                    <div class="ds-field">
                        <label class="ds-label" for="message">Message</label>
                        <textarea id="message" class="ds-input" rows="4" placeholder="Mesajinizi yazin"></textarea>
                    </div>
                    <div class="ds-button-row">
                        <button class="ds-btn ds-btn-primary" id="sendMessageBtn" type="button">Send Message</button>
                    </div>
                </article>

                <article class="ds-card ds-stack">
                    <h2 class="ds-h2">Media Upload</h2>
                    <div class="ds-field">
                        <label class="ds-label" for="mediaTitle">Title</label>
                        <input id="mediaTitle" class="ds-input" type="text" maxlength="160" placeholder="Training clip">
                    </div>
                    <div class="ds-field">
                        <label class="ds-label" for="mediaFile">File</label>
                        <input id="mediaFile" class="ds-input" type="file" accept="image/*,video/*">
                        <p class="ds-help">Max 50MB. Destek: image/video.</p>
                    </div>
                    <div class="ds-button-row">
                        <button class="ds-btn ds-btn-primary" id="uploadMediaBtn" type="button">Upload Media</button>
                    </div>
                </article>
            </section>

            <section class="ds-grid lg:grid-cols-2">
                <article class="ds-card ds-stack">
                    <h2 class="ds-h2">Inbox</h2>
                    <div class="grid gap-3 md:grid-cols-4">
                        <div class="ds-field">
                            <label class="ds-label" for="inboxStatus">Status</label>
                            <select id="inboxStatus" class="ds-input">
                                <option value="">All</option>
                                <option value="new">New</option>
                                <option value="read">Read</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                        <div class="ds-field">
                            <label class="ds-label" for="inboxSortBy">Sort by</label>
                            <select id="inboxSortBy" class="ds-input">
                                <option value="created_at">Created</option>
                                <option value="status">Status</option>
                            </select>
                        </div>
                        <div class="ds-field">
                            <label class="ds-label" for="inboxSortDir">Direction</label>
                            <select id="inboxSortDir" class="ds-input">
                                <option value="desc">Desc</option>
                                <option value="asc">Asc</option>
                            </select>
                        </div>
                        <div class="ds-field">
                            <label class="ds-label" for="inboxPerPage">Per page</label>
                            <select id="inboxPerPage" class="ds-input">
                                <option value="5">5</option>
                                <option value="10" selected>10</option>
                                <option value="20">20</option>
                            </select>
                        </div>
                    </div>
                    <div class="ds-button-row">
                        <button class="ds-btn ds-btn-secondary" id="loadInboxBtn" type="button">Load Inbox</button>
                    </div>
                    <div id="inboxWrap" class="ds-stack" aria-live="polite"></div>
                </article>

                <article class="ds-card ds-stack">
                    <h2 class="ds-h2">Sent</h2>
                    <div class="grid gap-3 md:grid-cols-4">
                        <div class="ds-field">
                            <label class="ds-label" for="sentStatus">Status</label>
                            <select id="sentStatus" class="ds-input">
                                <option value="">All</option>
                                <option value="new">New</option>
                                <option value="read">Read</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                        <div class="ds-field">
                            <label class="ds-label" for="sentSortBy">Sort by</label>
                            <select id="sentSortBy" class="ds-input">
                                <option value="created_at">Created</option>
                                <option value="status">Status</option>
                            </select>
                        </div>
                        <div class="ds-field">
                            <label class="ds-label" for="sentSortDir">Direction</label>
                            <select id="sentSortDir" class="ds-input">
                                <option value="desc">Desc</option>
                                <option value="asc">Asc</option>
                            </select>
                        </div>
                        <div class="ds-field">
                            <label class="ds-label" for="sentPerPage">Per page</label>
                            <select id="sentPerPage" class="ds-input">
                                <option value="5">5</option>
                                <option value="10" selected>10</option>
                                <option value="20">20</option>
                            </select>
                        </div>
                    </div>
                    <div class="ds-button-row">
                        <button class="ds-btn ds-btn-secondary" id="loadSentBtn" type="button">Load Sent</button>
                    </div>
                    <div id="sentWrap" class="ds-stack" aria-live="polite"></div>
                </article>
            </section>

            <section class="ds-card ds-stack">
                <h2 class="ds-h2">My Media</h2>
                <div class="grid gap-3 md:grid-cols-4">
                    <div class="ds-field">
                        <label class="ds-label" for="mediaType">Type</label>
                        <select id="mediaType" class="ds-input">
                            <option value="">All</option>
                            <option value="image">Image</option>
                            <option value="video">Video</option>
                        </select>
                    </div>
                    <div class="ds-field">
                        <label class="ds-label" for="mediaSortBy">Sort by</label>
                        <select id="mediaSortBy" class="ds-input">
                            <option value="created_at">Created</option>
                            <option value="title">Title</option>
                            <option value="type">Type</option>
                        </select>
                    </div>
                    <div class="ds-field">
                        <label class="ds-label" for="mediaSortDir">Direction</label>
                        <select id="mediaSortDir" class="ds-input">
                            <option value="desc">Desc</option>
                            <option value="asc">Asc</option>
                        </select>
                    </div>
                    <div class="ds-field">
                        <label class="ds-label" for="mediaPerPage">Per page</label>
                        <select id="mediaPerPage" class="ds-input">
                            <option value="6">6</option>
                            <option value="12" selected>12</option>
                            <option value="24">24</option>
                        </select>
                    </div>
                </div>
                <div class="ds-button-row">
                    <button class="ds-btn ds-btn-secondary" id="loadMediaBtn" type="button">Load Media</button>
                </div>
                <div id="mediaWrap" class="grid gap-3 md:grid-cols-2 lg:grid-cols-3" aria-live="polite"></div>
            </section>
        </main>

        <script>
            const state = { me: null };

            const tokenInput = document.getElementById('tokenInput');
            const globalStatus = document.getElementById('globalStatus');
            const inboxWrap = document.getElementById('inboxWrap');
            const sentWrap = document.getElementById('sentWrap');
            const mediaWrap = document.getElementById('mediaWrap');

            function headers() {
                return {
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${tokenInput.value.trim()}`,
                };
            }

            function jsonHeaders() {
                return { ...headers(), 'Content-Type': 'application/json' };
            }

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function renderGlobal(type, message) {
                const klass = type === 'error' ? 'ds-alert ds-alert-danger'
                    : type === 'warn' ? 'ds-alert ds-alert-warning'
                    : 'ds-alert ds-alert-success';
                globalStatus.innerHTML = `<div class="${klass}">${escapeHtml(message)}</div>`;
            }

            function setState(node, kind, text) {
                const klass = kind === 'error' ? 'ds-state ds-state-error'
                    : kind === 'warn' ? 'ds-state ds-state-empty'
                    : kind === 'empty' ? 'ds-state ds-state-empty'
                    : 'ds-state ds-state-loading';
                node.setAttribute('aria-busy', kind === 'loading' ? 'true' : 'false');
                node.innerHTML = `<div class="${klass}">${escapeHtml(text)}</div>`;
            }

            async function api(path, method = 'GET', body = null, isForm = false) {
                const response = await fetch(path, {
                    method,
                    headers: isForm ? headers() : jsonHeaders(),
                    body: body,
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || data.ok === false) {
                    throw new Error(data.message || `API error (${response.status})`);
                }
                return data;
            }

            async function bootstrap() {
                if (!tokenInput.value.trim()) {
                    renderGlobal('warn', 'Enter a bearer token.');
                    return;
                }

                try {
                    const me = await api('/api/auth/me');
                    state.me = me.data;
                    await Promise.all([loadInbox(), loadSent(), loadMedia()]);
                    renderGlobal('success', 'Messaging and media data loaded.');
                } catch (error) {
                    renderGlobal('error', error.message || 'Messaging and media could not be loaded.');
                }
            }

            function contactsQuery(prefix) {
                const params = new URLSearchParams();
                const status = document.getElementById(`${prefix}Status`).value.trim();
                const sortBy = document.getElementById(`${prefix}SortBy`).value;
                const sortDir = document.getElementById(`${prefix}SortDir`).value;
                const perPage = document.getElementById(`${prefix}PerPage`).value;
                if (status) params.set('status', status);
                params.set('sort_by', sortBy || 'created_at');
                params.set('sort_dir', sortDir || 'desc');
                params.set('per_page', perPage || '10');
                return params.toString();
            }

            function renderContactCards(rows, mode) {
                if (!rows.length) {
                    return '<div class="ds-state ds-state-empty">Kayit bulunamadi.</div>';
                }

                return rows.map((row) => {
                    const identity = mode === 'inbox'
                        ? `From: ${escapeHtml(row.sender_name || '-')}`
                        : `To: ${escapeHtml(row.recipient_name || '-')}`;

                    const actionButtons = mode === 'inbox' && row.status !== 'archived'
                        ? `
                            <div class="ds-button-row">
                                ${row.status !== 'read' ? `<button class="ds-btn ds-btn-secondary contact-status-btn" data-id="${row.id}" data-status="read" type="button">Mark Read</button>` : ''}
                                <button class="ds-btn ds-btn-secondary contact-status-btn" data-id="${row.id}" data-status="archived" type="button">Archive</button>
                            </div>
                        `
                        : '';

                    return `
                        <article class="ds-card ds-stack">
                            <h3 class="ds-h2">${escapeHtml(row.subject || 'No subject')}</h3>
                            <p class="ds-caption">${identity} | Status: ${escapeHtml(row.status || '-')}</p>
                            <p>${escapeHtml(row.message || '-')}</p>
                            ${actionButtons}
                        </article>
                    `;
                }).join('');
            }

            async function loadInbox() {
                setState(inboxWrap, 'loading', 'Inbox yukleniyor...');
                try {
                    const data = await api(`/api/contacts/inbox?${contactsQuery('inbox')}`);
                    const rows = data?.data?.data || [];
                    inboxWrap.innerHTML = renderContactCards(rows, 'inbox');
                } catch (error) {
                    setState(inboxWrap, 'error', error.message || 'Inbox yuklenemedi.');
                }
            }

            async function loadSent() {
                setState(sentWrap, 'loading', 'Sent yukleniyor...');
                try {
                    const data = await api(`/api/contacts/sent?${contactsQuery('sent')}`);
                    const rows = data?.data?.data || [];
                    sentWrap.innerHTML = renderContactCards(rows, 'sent');
                } catch (error) {
                    setState(sentWrap, 'error', error.message || 'Sent yuklenemedi.');
                }
            }

            async function sendMessage() {
                const toUserId = Number.parseInt(document.getElementById('toUserId').value, 10);
                const subject = document.getElementById('subject').value.trim();
                const message = document.getElementById('message').value.trim();

                if (!Number.isInteger(toUserId) || toUserId <= 0) {
                    renderGlobal('warn', 'Gecerli to_user_id girin.');
                    return;
                }
                if (!message) {
                    renderGlobal('warn', 'Mesaj bos olamaz.');
                    return;
                }

                try {
                    await api('/api/contacts', 'POST', JSON.stringify({
                        to_user_id: toUserId,
                        subject: subject || null,
                        message: message,
                    }));
                    renderGlobal('success', 'Mesaj gonderildi.');
                    document.getElementById('subject').value = '';
                    document.getElementById('message').value = '';
                    await loadSent();
                } catch (error) {
                    renderGlobal('error', error.message || 'Mesaj gonderilemedi.');
                }
            }

            async function changeContactStatus(id, status) {
                try {
                    await api(`/api/contacts/${id}/status`, 'PATCH', JSON.stringify({ status }));
                    renderGlobal('success', 'Mesaj durumu guncellendi.');
                    await loadInbox();
                } catch (error) {
                    renderGlobal('error', error.message || 'Durum guncellenemedi.');
                }
            }

            function mediaQuery() {
                const params = new URLSearchParams();
                const type = document.getElementById('mediaType').value.trim();
                const sortBy = document.getElementById('mediaSortBy').value;
                const sortDir = document.getElementById('mediaSortDir').value;
                const perPage = document.getElementById('mediaPerPage').value;
                if (type) params.set('type', type);
                params.set('sort_by', sortBy || 'created_at');
                params.set('sort_dir', sortDir || 'desc');
                params.set('per_page', perPage || '12');
                return params.toString();
            }

            function renderMediaCards(rows) {
                if (!rows.length) {
                    return '<div class="ds-state ds-state-empty md:col-span-2 lg:col-span-3">Media bulunamadi.</div>';
                }

                return rows.map((item) => {
                    const mediaView = item.type === 'image'
                        ? `<img src="${escapeHtml(item.url)}" alt="${escapeHtml(item.title || 'media')}" class="w-full rounded-lg border border-slate-200" loading="lazy">`
                        : `<video src="${escapeHtml(item.url)}" controls class="w-full rounded-lg border border-slate-200"></video>`;

                    return `
                        <article class="ds-card ds-stack">
                            <div>${mediaView}</div>
                            <p class="ds-caption">${escapeHtml(item.type || '-')} | ${escapeHtml(item.title || 'Untitled')}</p>
                            <div class="ds-button-row">
                                <button class="ds-btn ds-btn-secondary media-delete-btn" data-id="${item.id}" type="button">Delete</button>
                            </div>
                        </article>
                    `;
                }).join('');
            }

            async function loadMedia() {
                if (!state.me?.id) {
                    setState(mediaWrap, 'warn', 'Once kullanici bilgisi yuklenmeli.');
                    return;
                }

                setState(mediaWrap, 'loading', 'Media yukleniyor...');
                try {
                    const data = await api(`/api/users/${state.me.id}/media?${mediaQuery()}`);
                    const rows = data?.data?.data || [];
                    mediaWrap.innerHTML = renderMediaCards(rows);
                } catch (error) {
                    setState(mediaWrap, 'error', error.message || 'Media yuklenemedi.');
                }
            }

            async function uploadMedia() {
                const fileInput = document.getElementById('mediaFile');
                const title = document.getElementById('mediaTitle').value.trim();
                const file = fileInput.files?.[0];
                if (!file) {
                    renderGlobal('warn', 'Lutfen bir dosya secin.');
                    return;
                }

                const form = new FormData();
                form.append('file', file);
                if (title) form.append('title', title);

                try {
                    await api('/api/media', 'POST', form, true);
                    renderGlobal('success', 'Media yuklendi.');
                    fileInput.value = '';
                    document.getElementById('mediaTitle').value = '';
                    await loadMedia();
                } catch (error) {
                    renderGlobal('error', error.message || 'Media yuklenemedi.');
                }
            }

            async function deleteMedia(id) {
                if (!confirm(`Media #${id} silinsin mi?`)) return;
                try {
                    await api(`/api/media/${id}`, 'DELETE');
                    renderGlobal('success', 'Media silindi.');
                    await loadMedia();
                } catch (error) {
                    renderGlobal('error', error.message || 'Media silinemedi.');
                }
            }

            document.getElementById('bootstrapBtn').addEventListener('click', bootstrap);
            document.getElementById('sendMessageBtn').addEventListener('click', sendMessage);
            document.getElementById('uploadMediaBtn').addEventListener('click', uploadMedia);
            document.getElementById('loadInboxBtn').addEventListener('click', loadInbox);
            document.getElementById('loadSentBtn').addEventListener('click', loadSent);
            document.getElementById('loadMediaBtn').addEventListener('click', loadMedia);

            inboxWrap.addEventListener('click', (event) => {
                const button = event.target.closest('.contact-status-btn');
                if (!button) return;
                const id = button.getAttribute('data-id');
                const status = button.getAttribute('data-status');
                if (!id || !status) return;
                changeContactStatus(id, status);
            });

            mediaWrap.addEventListener('click', (event) => {
                const button = event.target.closest('.media-delete-btn');
                if (!button) return;
                const id = button.getAttribute('data-id');
                if (!id) return;
                deleteMedia(id);
            });
        </script>
    </body>
</html>
