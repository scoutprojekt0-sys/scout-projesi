(function () {
  const ANALYSIS_CACHE_KEY = 'nextscout_ai_video_lab_cache_v1';

  function getApiBaseUrl() {
    if (typeof window.API_BASE_URL === 'string' && window.API_BASE_URL.trim()) {
      return window.API_BASE_URL.replace(/\/$/, '');
    }
    if (typeof window.getApiBaseUrl === 'function') {
      return String(window.getApiBaseUrl()).replace(/\/$/, '');
    }
    if (window.NextScoutApi && typeof window.NextScoutApi.base === 'function') {
      return String(window.NextScoutApi.base()).replace(/\/$/, '');
    }
    const configured = (window.NEXTSCOUT_API_BASE || '').toString().trim();
    if (configured) return configured.replace(/\/$/, '');
    const host = (window.location.hostname || '').toString().trim().toLowerCase();
    const port = (window.location.port || '').toString().trim();
    if (window.location.protocol === 'file:') return 'http://127.0.0.1:8000/api';
    if ((host === '127.0.0.1' || host === 'localhost') && port && port !== '8000') {
      return 'http://127.0.0.1:8000/api';
    }
    return window.location.origin.replace(/\/$/, '') + '/api';
  }

  function getToken() {
    return (localStorage.getItem('nextscout_token') || '').trim();
  }

  function isAuthenticated() {
    return getToken() !== '';
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function formatDate(value) {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat('tr-TR', { day: '2-digit', month: 'short', year: 'numeric' }).format(date);
  }

  function getAnalysisCache() {
    try {
      const parsed = JSON.parse(localStorage.getItem(ANALYSIS_CACHE_KEY) || '{}');
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (error) {
      return {};
    }
  }

  function setAnalysisCache(cache) {
    localStorage.setItem(ANALYSIS_CACHE_KEY, JSON.stringify(cache && typeof cache === 'object' ? cache : {}));
  }

  function getCacheKey(playerId, videoId) {
    return String(playerId || '0') + ':' + String(videoId || '0');
  }

  async function apiGet(path, withAuth) {
    const headers = { Accept: 'application/json' };
    if (withAuth && getToken()) {
      headers.Authorization = 'Bearer ' + getToken();
    }

    const response = await fetch(getApiBaseUrl() + path, { headers });
    const data = await response.json().catch(function () { return {}; });
    if (!response.ok || data.ok === false) {
      throw new Error(data.message || 'Veri alinmadi.');
    }
    return data.data ?? data;
  }

  async function apiPost(path, payload) {
    const token = getToken();
    if (!token) {
      throw new Error('Analiz baslatmak icin once giris yapmalisin.');
    }

    const response = await fetch(getApiBaseUrl() + path, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        Authorization: 'Bearer ' + token
      },
      body: JSON.stringify(payload)
    });

    const data = await response.json().catch(function () { return {}; });
    if (!response.ok || data.ok === false) {
      const firstError = data && data.errors ? Object.values(data.errors)[0]?.[0] : '';
      throw new Error(firstError || data.message || 'Islem basarisiz.');
    }
    return data.data ?? data;
  }

  async function apiPostEnvelope(path, payload) {
    const token = getToken();
    if (!token) {
      throw new Error('Analiz baslatmak icin once giris yapmalisin.');
    }

    const response = await fetch(getApiBaseUrl() + path, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        Authorization: 'Bearer ' + token
      },
      body: JSON.stringify(payload)
    });

    const data = await response.json().catch(function () { return {}; });
    if (!response.ok || data.ok === false) {
      const firstError = data && data.errors ? Object.values(data.errors)[0]?.[0] : '';
      throw new Error(firstError || data.message || 'Islem basarisiz.');
    }
    return data;
  }

  function createSummaryMarkup(summary) {
    if (!summary) {
      return [
        '<div class="ai-video-lab-summary-item"><strong>-</strong><span>Pass</span></div>',
        '<div class="ai-video-lab-summary-item"><strong>-</strong><span>Cross</span></div>',
        '<div class="ai-video-lab-summary-item"><strong>-</strong><span>Speed</span></div>'
      ].join('');
    }

    return [
      ['successful_passes', 'Basarili Pass'],
      ['successful_crosses', 'Basarili Cross'],
      ['speed_score', 'Speed Score'],
      ['dribbles', 'Dribble'],
      ['shots', 'Shot'],
      ['movement_score', 'Movement']
    ].map(function (item) {
      return '<div class="ai-video-lab-summary-item"><strong>' +
        escapeHtml(Number(summary[item[0]] || 0)) +
        '</strong><span>' + escapeHtml(item[1]) + '</span></div>';
    }).join('');
  }

  function createEventMarkup(events) {
    const rows = Array.isArray(events) ? events.slice(0, 8) : [];
    if (!rows.length) {
      return '<div class="ai-video-lab-empty">Henuz analiz eventi yok.</div>';
    }

    return rows.map(function (event) {
      const clip = Array.isArray(event.clips) && event.clips.length ? event.clips[0] : null;
      return '' +
        '<article class="ai-video-lab-event">' +
          '<div class="ai-video-lab-event-top">' +
            '<strong>' + escapeHtml(String(event.event_type || '').toUpperCase()) + '</strong>' +
            '<span class="ai-video-lab-badge">%' + escapeHtml(Number(event.confidence || 0)) + '</span>' +
          '</div>' +
          '<div class="ai-video-lab-note">' +
            escapeHtml(event.payload?.successful ? 'Basarili aksiyon' : 'Tespit edilen aksiyon') +
            ' - ' + escapeHtml(Number(event.start_second || 0)) + 's - ' + escapeHtml(Number(event.end_second || 0)) + 's' +
          '</div>' +
          (clip?.clip_url ? '<div class="ai-video-lab-actions" style="margin-top:8px;"><a class="nav-btn" href="' + escapeHtml(clip.clip_url) + '" target="_blank" rel="noopener noreferrer">Klibi Ac</a></div>' : '') +
        '</article>';
    }).join('');
  }

  function normalizeDiscoveryPlayer(row) {
    if (!row || typeof row !== 'object') {
      return null;
    }

    const playerId = Number(row.player_id || row.id || 0);
    if (!playerId) {
      return null;
    }

    const videoClipId = Number(row.video_clip_id || 0);

    return {
      id: playerId,
      player_id: playerId,
      name: row.name || 'Oyuncu',
      city: row.city || '',
      position: row.position || '',
      age: row.age || '',
      photo_url: row.photo_url || '',
      successful_passes: Number(row.successful_passes || 0),
      successful_crosses: Number(row.successful_crosses || 0),
      speed_score: Number(row.speed_score || 0),
      movement_score: Number(row.movement_score || 0),
      cross_quality_score: Number(row.cross_quality_score || 0),
      analysis_status: row.analysis_status || '',
      analysis_provider: row.analysis_provider || '',
      has_video: Boolean(videoClipId),
      embedded_video: videoClipId ? {
        id: videoClipId,
        title: row.video_title || ('Video #' + videoClipId),
        video_url: row.video_url || '',
        thumbnail_url: row.thumbnail_url || '',
        platform: row.platform || '',
        duration_seconds: row.duration_seconds || null,
        match_date: row.match_date || null
      } : null
    };
    }

    function initLab(root) {
    const quickRoot = document.createElement('div');
    quickRoot.className = 'ai-video-lab-quick';
    quickRoot.innerHTML = '' +
      '<div class="ai-video-lab-quick-grid">' +
        '<div class="ai-video-lab-quick-card">' +
          '<strong>Haftanin Oyuncusu</strong>' +
          '<div class="ai-video-lab-chip-list" data-ai-player-week><div class="ai-video-lab-empty">Yukleniyor...</div></div>' +
        '</div>' +
        '<div class="ai-video-lab-quick-card">' +
          '<strong>Trend Oyuncular</strong>' +
          '<div class="ai-video-lab-chip-list" data-ai-trending-week><div class="ai-video-lab-empty">Yukleniyor...</div></div>' +
        '</div>' +
        '<div class="ai-video-lab-quick-card">' +
          '<strong>Yukselen Yildizlar</strong>' +
          '<div class="ai-video-lab-chip-list" data-ai-rising-stars><div class="ai-video-lab-empty">Yukleniyor...</div></div>' +
        '</div>' +
      '</div>';
    const searchBlock = root.querySelector('.ai-video-lab-search');
    if (searchBlock) {
      root.insertBefore(quickRoot, searchBlock);
    }

    const discoveryRoot = document.createElement('div');
    discoveryRoot.className = 'ai-video-lab-discovery';
    discoveryRoot.innerHTML = '' +
      '<div class="ai-video-lab-head" style="margin-bottom:0;">' +
        '<div>' +
          '<h2>AI Discovery</h2>' +
          '<p>Analiz metrikleriyle oyuncu kesfet: en iyi cross, speed, movement ve filtreli siralama.</p>' +
        '</div>' +
        '<div class="ai-video-lab-tag"><i class="fas fa-ranking-star"></i><span>Discovery</span></div>' +
      '</div>' +
      '<div class="ai-video-lab-discovery-grid">' +
        '<div class="ai-video-lab-discovery-card"><strong>En Iyi Cross</strong><div class="ai-video-lab-chip-list" data-ai-best-crosses><div class="ai-video-lab-empty">Yukleniyor...</div></div></div>' +
        '<div class="ai-video-lab-discovery-card"><strong>En Hizli Oyuncular</strong><div class="ai-video-lab-chip-list" data-ai-best-speed><div class="ai-video-lab-empty">Yukleniyor...</div></div></div>' +
        '<div class="ai-video-lab-discovery-card"><strong>En Yuksek Movement</strong><div class="ai-video-lab-chip-list" data-ai-best-movement><div class="ai-video-lab-empty">Yukleniyor...</div></div></div>' +
      '</div>' +
      '<div class="ai-video-lab-discovery-filters">' +
        '<div class="ai-video-lab-field"><label>Oyuncu Adi</label><input class="ai-video-lab-input" type="text" placeholder="Oyuncu ara" data-ai-discovery-search></div>' +
        '<div class="ai-video-lab-field"><label>Sehir</label><input class="ai-video-lab-input" type="text" placeholder="Istanbul" data-ai-discovery-city></div>' +
        '<div class="ai-video-lab-field"><label>Pozisyon</label><select class="ai-video-lab-select" data-ai-discovery-position><option value=\"\">Tumu</option><option value=\"Kaleci\">Kaleci</option><option value=\"Defans\">Defans</option><option value=\"Orta Saha\">Orta Saha</option><option value=\"Forvet\">Forvet</option></select></div>' +
        '<div class="ai-video-lab-field"><label>Sirala</label><select class="ai-video-lab-select" data-ai-discovery-sort><option value=\"speed_score_desc\">Speed</option><option value=\"successful_crosses_desc\">Cross</option><option value=\"movement_score_desc\">Movement</option><option value=\"successful_passes_desc\">Pass</option><option value=\"cross_quality_desc\">Cross Quality</option></select></div>' +
        '<button class="btn-small primary ai-video-lab-search-btn" type="button" data-ai-discovery-btn>Discovery Ara</button>' +
      '</div>' +
      '<div class="ai-video-lab-discovery-results" data-ai-discovery-results><div class="ai-video-lab-empty">AI discovery listesi yukleniyor...</div></div>';
    if (searchBlock) {
      root.insertBefore(discoveryRoot, searchBlock);
    }

    const searchInput = root.querySelector('[data-ai-player-search]');
    const cityInput = root.querySelector('[data-ai-city-search]');
    const positionSelect = root.querySelector('[data-ai-position-search]');
    const searchButton = root.querySelector('[data-ai-search-btn]');
    const resultsRoot = root.querySelector('[data-ai-results]');
    const selectedRoot = root.querySelector('[data-ai-selected-player]');
    const selectedName = root.querySelector('[data-ai-selected-name]');
    const selectedMeta = root.querySelector('[data-ai-selected-meta]');
    const videoSelect = root.querySelector('[data-ai-video-select]');
    const preview = root.querySelector('[data-ai-video-preview]');
    const previewThumb = root.querySelector('[data-ai-video-thumb]');
    const previewTitle = root.querySelector('[data-ai-video-title]');
    const previewDate = root.querySelector('[data-ai-video-date]');
    const previewDuration = root.querySelector('[data-ai-video-duration]');
    const previewPlatform = root.querySelector('[data-ai-video-platform]');
    const previewLink = root.querySelector('[data-ai-video-link]');
    const targetInput = root.querySelector('[data-ai-target-player-id]');
    const analyzeButton = root.querySelector('[data-ai-analyze-btn]');
    const summaryRoot = root.querySelector('[data-ai-summary]');
    const eventsRoot = root.querySelector('[data-ai-events]');
    const notice = root.querySelector('[data-ai-notice]');
    const playerWeekRoot = root.querySelector('[data-ai-player-week]');
    const trendingRoot = root.querySelector('[data-ai-trending-week]');
    const risingRoot = root.querySelector('[data-ai-rising-stars]');
    const resultCard = root.querySelectorAll('.ai-video-lab-card')[1];
    let sourceBadge = root.querySelector('[data-ai-analysis-source]');
    const discoverySearchInput = root.querySelector('[data-ai-discovery-search]');
    const discoveryCityInput = root.querySelector('[data-ai-discovery-city]');
    const discoveryPositionSelect = root.querySelector('[data-ai-discovery-position]');
    const discoverySortSelect = root.querySelector('[data-ai-discovery-sort]');
    const discoveryButton = root.querySelector('[data-ai-discovery-btn]');
    const discoveryResultsRoot = root.querySelector('[data-ai-discovery-results]');
    const bestCrossesRoot = root.querySelector('[data-ai-best-crosses]');
    const bestSpeedRoot = root.querySelector('[data-ai-best-speed]');
    const bestMovementRoot = root.querySelector('[data-ai-best-movement]');
    const workerModeBadge = document.getElementById('workerModeBadge');

    if (!sourceBadge && resultCard) {
      const heading = resultCard.querySelector('h3');
      if (heading) {
        const head = document.createElement('div');
        head.className = 'ai-video-lab-card-head';
        heading.parentNode.insertBefore(head, heading);
        head.appendChild(heading);
        sourceBadge = document.createElement('span');
        sourceBadge.className = 'ai-video-lab-source';
        sourceBadge.hidden = true;
        sourceBadge.setAttribute('data-ai-analysis-source', 'true');
        head.appendChild(sourceBadge);
      }
    }

    let players = [];
    let videos = [];
    let selectedPlayer = null;

    const head = root.querySelector('.ai-video-lab-head');
    if (head && !head.querySelector('[data-ai-open-page]')) {
      let actions = head.querySelector('.ai-video-lab-head-actions');
      if (!actions) {
        actions = document.createElement('div');
        actions.className = 'ai-video-lab-head-actions';
        head.appendChild(actions);
      }

      const link = document.createElement('a');
      link.className = 'ai-video-lab-link-btn';
      link.href = 'ai-discovery.html';
      link.setAttribute('data-ai-open-page', 'true');
      link.innerHTML = '<i class="fas fa-up-right-from-square"></i><span>Tam Ekran Discovery</span>';
      actions.prepend(link);
    }

    function setNotice(message, type) {
      if (!notice) return;
      notice.className = 'ai-video-lab-notice show ' + (type === 'ok' ? 'ok' : 'bad');
      notice.textContent = message;
    }

    function clearNotice() {
      if (!notice) return;
      notice.className = 'ai-video-lab-notice';
      notice.textContent = '';
    }

    function setWorkerMode(provider, fallbackMode) {
      if (!workerModeBadge) return;
      const normalizedProvider = String(provider || '').toLowerCase();
      const normalizedFallback = String(fallbackMode || '').toLowerCase();

      if (!normalizedProvider) {
        workerModeBadge.textContent = 'Hazir';
        return;
      }

      const labels = [
        normalizedProvider === 'mock' ? 'Mock' : normalizedProvider === 'external' ? 'External' : normalizedProvider
      ];
      if (normalizedFallback) {
        labels.push('fallback ' + normalizedFallback);
      }
      workerModeBadge.textContent = labels.join(' / ');
    }

    function setSourceBadge(source, meta) {
      if (!sourceBadge) return;
      const key = String(source || '').toLowerCase();
      const provider = String(meta?.provider || '').toLowerCase();
      const fallbackMode = String(meta?.fallback_mode || '').toLowerCase();
      if (!key) {
        sourceBadge.hidden = true;
        sourceBadge.className = 'ai-video-lab-source';
        sourceBadge.textContent = '';
        return;
      }
      sourceBadge.hidden = false;
      sourceBadge.className = 'ai-video-lab-source ' + key;
      const labels = [key === 'cached' ? 'Cached Analysis' : 'Fresh Analysis'];
      if (provider) labels.push(provider === 'mock' ? 'Mock' : provider === 'external' ? 'External' : provider);
      if (fallbackMode) labels.push('Fallback ' + fallbackMode.toUpperCase());
      sourceBadge.textContent = labels.join(' | ');
      setWorkerMode(provider, fallbackMode);
    }

    function renderCachedAnalysis(entry) {
      if (!entry) return false;
      if (summaryRoot) {
        summaryRoot.innerHTML = createSummaryMarkup(entry.summary || null);
      }
      if (eventsRoot) {
        eventsRoot.innerHTML = createEventMarkup(entry.events || []);
      }
      setSourceBadge('cached', {
        provider: entry.provider || '',
        fallback_mode: entry.fallback_mode || ''
      });
      return true;
    }

    function resetAnalysisPanel() {
      if (summaryRoot) {
        summaryRoot.innerHTML = createSummaryMarkup(null);
      }
      if (eventsRoot) {
        eventsRoot.innerHTML = '<div class="ai-video-lab-empty">Analiz baslatildiginda eventler burada gorunur.</div>';
      }
      if (sourceBadge) {
        sourceBadge.hidden = true;
        sourceBadge.className = 'ai-video-lab-source';
        sourceBadge.textContent = '';
      }
      setWorkerMode('', '');
    }

    function renderPlayerResults() {
      if (!resultsRoot) return;
      if (!players.length) {
        resultsRoot.innerHTML = '<div class="ai-video-lab-empty">Aramaya uygun oyuncu bulunamadi.</div>';
        return;
      }

      resultsRoot.innerHTML = players.map(function (player) {
        return '' +
          '<article class="ai-video-lab-player">' +
            '<div>' +
              '<strong>' + escapeHtml(player.name || 'Oyuncu') + '</strong>' +
              '<span>ID: ' + escapeHtml(player.id) + '</span>' +
              '<span>' + escapeHtml(player.position || '-') + '</span>' +
              '<span>' + escapeHtml(player.city || '-') + '</span>' +
              '<span>Yas: ' + escapeHtml(player.age || '-') + '</span>' +
              '<div class="ai-video-lab-player-metrics">' +
                '<span class="ai-video-lab-metric">Cross ' + escapeHtml(player.successful_crosses || 0) + '</span>' +
                '<span class="ai-video-lab-metric">Pass ' + escapeHtml(player.successful_passes || 0) + '</span>' +
                '<span class="ai-video-lab-metric">Speed ' + escapeHtml(player.speed_score || 0) + '</span>' +
                '<span class="ai-video-lab-metric">Move ' + escapeHtml(player.movement_score || 0) + '</span>' +
              '</div>' +
            '</div>' +
            '<button type="button" class="btn-small primary" data-ai-player-pick="' + escapeHtml(player.id) + '">Sec</button>' +
          '</article>';
      }).join('');

      resultsRoot.querySelectorAll('[data-ai-player-pick]').forEach(function (button) {
        button.addEventListener('click', function () {
          const playerId = Number(button.getAttribute('data-ai-player-pick') || 0);
          const found = players.find(function (item) { return Number(item.id) === playerId; });
          if (found) {
            selectPlayer(found);
          }
        });
      });
    }

    function createQuickChip(player) {
      return '<button type="button" class="ai-video-lab-chip" data-ai-quick-pick="' + escapeHtml(player.id) + '">' +
        escapeHtml(player.name || 'Oyuncu') +
        (player.position ? ' - ' + escapeHtml(player.position) : '') +
      '</button>';
    }

    function createDiscoveryChip(player, metricKey) {
      const value = Number(player[metricKey] || 0);
      return '<button type="button" class="ai-video-lab-chip" data-ai-quick-pick="' + escapeHtml(player.player_id || player.id) + '">' +
        escapeHtml(player.name || 'Oyuncu') + ' - ' + escapeHtml(value) +
      '</button>';
    }

    function bindQuickPickButtons(scope, sourceRows) {
      if (!scope) return;
      scope.querySelectorAll('[data-ai-quick-pick]').forEach(function (button) {
        button.addEventListener('click', function () {
          const playerId = Number(button.getAttribute('data-ai-quick-pick') || 0);
          const found = sourceRows.find(function (item) { return Number(item.id) === playerId; });
          if (found) {
            selectPlayer(found);
          }
        });
      });
    }

    async function loadQuickPicks() {
      const tasks = [
        apiGet('/player-of-week', false).catch(function () { return null; }),
        apiGet('/trending/week', false).catch(function () { return []; }),
        apiGet('/rising-stars', false).catch(function () { return []; })
      ];

      const results = await Promise.all(tasks);
      const playerOfWeek = results[0] && results[0].id ? [results[0]] : [];
      const trending = Array.isArray(results[1]) ? results[1].slice(0, 5) : [];
      const rising = Array.isArray(results[2]) ? results[2].slice(0, 5) : [];

      if (playerWeekRoot) {
        playerWeekRoot.innerHTML = playerOfWeek.length
          ? playerOfWeek.map(createQuickChip).join('')
          : '<div class="ai-video-lab-empty">Haftanin oyuncusu yok.</div>';
        bindQuickPickButtons(playerWeekRoot, playerOfWeek);
      }

      if (trendingRoot) {
        trendingRoot.innerHTML = trending.length
          ? trending.map(createQuickChip).join('')
          : '<div class="ai-video-lab-empty">Trend liste bos.</div>';
        bindQuickPickButtons(trendingRoot, trending);
      }

      if (risingRoot) {
        risingRoot.innerHTML = rising.length
          ? rising.map(createQuickChip).join('')
          : '<div class="ai-video-lab-empty">Yukselen yildiz bulunamadi.</div>';
        bindQuickPickButtons(risingRoot, rising);
      }
    }

    async function loadDiscoveryRankings() {
      try {
        const payload = await apiGet('/scouting-search/rankings?limit=5', false);
        const bestCrosses = Array.isArray(payload?.best_crosses) ? payload.best_crosses : [];
        const bestSpeed = Array.isArray(payload?.best_speed) ? payload.best_speed : [];
        const bestMovement = Array.isArray(payload?.best_movement) ? payload.best_movement : [];

        if (bestCrossesRoot) {
          bestCrossesRoot.innerHTML = bestCrosses.length
            ? bestCrosses.map(function (row) { return createDiscoveryChip(row, 'successful_crosses'); }).join('')
            : '<div class="ai-video-lab-empty">Liste bos.</div>';
          bindQuickPickButtons(bestCrossesRoot, bestCrosses.map(normalizeDiscoveryPlayer).filter(Boolean));
        }
        if (bestSpeedRoot) {
          bestSpeedRoot.innerHTML = bestSpeed.length
            ? bestSpeed.map(function (row) { return createDiscoveryChip(row, 'speed_score'); }).join('')
            : '<div class="ai-video-lab-empty">Liste bos.</div>';
          bindQuickPickButtons(bestSpeedRoot, bestSpeed.map(normalizeDiscoveryPlayer).filter(Boolean));
        }
        if (bestMovementRoot) {
          bestMovementRoot.innerHTML = bestMovement.length
            ? bestMovement.map(function (row) { return createDiscoveryChip(row, 'movement_score'); }).join('')
            : '<div class="ai-video-lab-empty">Liste bos.</div>';
          bindQuickPickButtons(bestMovementRoot, bestMovement.map(normalizeDiscoveryPlayer).filter(Boolean));
        }
      } catch (error) {
        if (bestCrossesRoot) bestCrossesRoot.innerHTML = '<div class="ai-video-lab-empty">Ranking verisi alinamadi.</div>';
        if (bestSpeedRoot) bestSpeedRoot.innerHTML = '<div class="ai-video-lab-empty">Ranking verisi alinamadi.</div>';
        if (bestMovementRoot) bestMovementRoot.innerHTML = '<div class="ai-video-lab-empty">Ranking verisi alinamadi.</div>';
      }
    }

    function renderDiscoveryResults(rows) {
      if (!discoveryResultsRoot) return;
      if (!Array.isArray(rows) || !rows.length) {
        discoveryResultsRoot.innerHTML = '<div class="ai-video-lab-empty">AI filtrelere uygun oyuncu bulunamadi.</div>';
        return;
      }

      discoveryResultsRoot.innerHTML = rows.map(function (row) {
        const provider = String(row.analysis_provider || '').toLowerCase();
        const status = String(row.analysis_status || '');
        const hasVideo = Boolean(row.video_clip_id);
        const providerLabel = provider ? (provider === 'mock' ? 'Mock' : provider === 'external' ? 'External' : provider) : 'Unknown';
        const statusPills = [
          '<span class="ai-video-lab-pill ' + (hasVideo ? 'good' : 'warn') + '">' + (hasVideo ? 'Video Hazir' : 'Video Yok') + '</span>',
          status ? '<span class="ai-video-lab-pill info">' + escapeHtml(status) + '</span>' : '',
          provider ? '<span class="ai-video-lab-pill info">' + escapeHtml(providerLabel) + '</span>' : ''
        ].filter(Boolean).join('');

        return '' +
          '<article class="ai-video-lab-discovery-player">' +
            '<div>' +
              '<div class="ai-video-lab-discovery-head">' +
                '<strong>' + escapeHtml(row.name || 'Oyuncu') + '</strong>' +
              '</div>' +
              '<div class="ai-video-lab-discovery-meta">' +
                '<span>ID: ' + escapeHtml(row.player_id) + '</span>' +
                '<span>' + escapeHtml(row.position || '-') + '</span>' +
                '<span>' + escapeHtml(row.city || '-') + '</span>' +
                '<span>Yas: ' + escapeHtml(row.age || '-') + '</span>' +
              '</div>' +
              '<div class="ai-video-lab-discovery-stats">' +
                '<span class="ai-video-lab-metric">Cross ' + escapeHtml(Number(row.successful_crosses || 0)) + '</span>' +
                '<span class="ai-video-lab-metric">Pass ' + escapeHtml(Number(row.successful_passes || 0)) + '</span>' +
                '<span class="ai-video-lab-metric">Speed ' + escapeHtml(Number(row.speed_score || 0)) + '</span>' +
                '<span class="ai-video-lab-metric">Move ' + escapeHtml(Number(row.movement_score || 0)) + '</span>' +
              '</div>' +
              '<div class="ai-video-lab-status-pills">' + statusPills + '</div>' +
            '</div>' +
            '<button type="button" class="btn-small primary" data-ai-discovery-pick="' + escapeHtml(row.player_id) + '">Analize Git</button>' +
          '</article>';
      }).join('');

      const mappedRows = rows.map(normalizeDiscoveryPlayer).filter(Boolean);
      bindQuickPickButtons(discoveryResultsRoot, mappedRows);
      discoveryResultsRoot.querySelectorAll('[data-ai-discovery-pick]').forEach(function (button) {
        button.addEventListener('click', function () {
          const playerId = Number(button.getAttribute('data-ai-discovery-pick') || 0);
          const found = mappedRows.find(function (item) { return Number(item.id) === playerId; });
          if (found) {
            selectPlayer(found);
          }
        });
      });
    }

    async function runDiscoverySearch() {
      if (discoveryResultsRoot) {
        discoveryResultsRoot.innerHTML = '<div class="ai-video-lab-empty">AI discovery araniyor...</div>';
      }
      const query = new URLSearchParams();
      if (discoverySearchInput?.value.trim()) query.set('search', discoverySearchInput.value.trim());
      if (discoveryCityInput?.value.trim()) query.set('city', discoveryCityInput.value.trim());
      if (discoveryPositionSelect?.value.trim()) query.set('position', discoveryPositionSelect.value.trim());
      if (discoverySortSelect?.value.trim()) query.set('sort', discoverySortSelect.value.trim());
      query.set('per_page', '8');

      try {
        const payload = await apiGet('/scouting-search/discovery?' + query.toString(), false);
        renderDiscoveryResults(Array.isArray(payload?.data) ? payload.data : payload);
      } catch (error) {
        if (discoveryResultsRoot) {
          discoveryResultsRoot.innerHTML = '<div class="ai-video-lab-empty">' + escapeHtml(error.message || 'Discovery verisi alinamadi.') + '</div>';
        }
      }
    }

    function renderVideos() {
      if (!videoSelect) return;
      if (!videos.length) {
        videoSelect.innerHTML = '<option value="">Oyuncuya bagli video bulunamadi</option>';
        updatePreview();
        return;
      }

      videoSelect.innerHTML = ['<option value="">Video sec</option>']
        .concat(videos.map(function (video) {
          const label = escapeHtml(video.title || ('Video #' + video.id));
          const duration = video.duration_seconds ? ' - ' + escapeHtml(video.duration_seconds) + 's' : '';
          return '<option value="' + escapeHtml(video.id) + '">' + label + duration + '</option>';
        }))
        .join('');
      if (videos.length === 1) {
        videoSelect.value = String(videos[0].id);
      }
      updatePreview();
    }

    function updatePreview() {
      const selectedId = Number(videoSelect?.value || 0);
      const selectedVideo = videos.find(function (video) { return Number(video.id) === selectedId; });

      if (!selectedVideo || !preview) {
        if (preview) preview.hidden = true;
        resetAnalysisPanel();
        return;
      }

      preview.hidden = false;
      if (previewTitle) previewTitle.textContent = selectedVideo.title || ('Video #' + selectedVideo.id);
      if (previewDate) previewDate.textContent = 'Tarih: ' + (selectedVideo.match_date ? formatDate(selectedVideo.match_date) : '-');
      if (previewDuration) previewDuration.textContent = 'Sure: ' + (selectedVideo.duration_seconds ? selectedVideo.duration_seconds + 's' : '-');
      if (previewPlatform) previewPlatform.textContent = 'Platform: ' + (selectedVideo.platform || '-');
      if (previewThumb) {
        previewThumb.src = selectedVideo.thumbnail_url || 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="120" height="72"%3E%3Crect width="120" height="72" fill="%230f172a"/%3E%3Ctext x="50%25" y="50%25" dominant-baseline="middle" text-anchor="middle" fill="%239bb5cb" font-size="12"%3EVideo%3C/text%3E%3C/svg%3E';
      }
      if (previewLink) {
        previewLink.href = selectedVideo.video_url || '#';
        previewLink.style.pointerEvents = selectedVideo.video_url ? 'auto' : 'none';
        previewLink.style.opacity = selectedVideo.video_url ? '1' : '0.5';
      }

      const cacheKey = getCacheKey(selectedPlayer?.id || 0, selectedVideo.id);
      const cachedEntry = getAnalysisCache()[cacheKey];
      if (!renderCachedAnalysis(cachedEntry)) {
        resetAnalysisPanel();
      }
    }

    function mergeEmbeddedVideo(currentVideos, embeddedVideo) {
      if (!embeddedVideo || !embeddedVideo.id) {
        return currentVideos;
      }

      const alreadyExists = currentVideos.some(function (video) {
        return Number(video.id) === Number(embeddedVideo.id);
      });
      if (alreadyExists) {
        return currentVideos;
      }

      return [embeddedVideo].concat(currentVideos);
    }

    async function loadVideos(playerId, player) {
      videos = [];
      if (player?.embedded_video) {
        videos = mergeEmbeddedVideo(videos, player.embedded_video);
      }
      renderVideos();
      try {
        const payload = await apiGet('/users/' + playerId + '/videos', Boolean(getToken()));
        const fetchedVideos = Array.isArray(payload?.data) ? payload.data : (Array.isArray(payload) ? payload : []);
        videos = mergeEmbeddedVideo(fetchedVideos, player?.embedded_video);
        renderVideos();
      } catch (error) {
        videos = mergeEmbeddedVideo([], player?.embedded_video);
        renderVideos();
        setNotice(error.message || 'Oyuncu videolari yuklenemedi.', 'bad');
      }
    }

    async function selectPlayer(player) {
      selectedPlayer = player;
      resetAnalysisPanel();
      if (selectedRoot) selectedRoot.hidden = false;
      if (selectedName) selectedName.textContent = player.name || 'Oyuncu';
      if (selectedMeta) {
        selectedMeta.textContent = [
          'ID: ' + (player.id || '-'),
          player.position || '-',
          player.city || '-',
          'Yas: ' + (player.age || '-'),
          'Cross: ' + (player.successful_crosses || 0),
          'Speed: ' + (player.speed_score || 0)
        ].join(' - ');
      }
      if (targetInput) targetInput.value = player.id || '';
      clearNotice();
      await loadVideos(player.id, player);
    }

    async function runSearch() {
      clearNotice();
      resultsRoot.innerHTML = '<div class="ai-video-lab-empty">Oyuncular araniyor...</div>';
      if (selectedRoot) selectedRoot.hidden = true;
      players = [];
      videos = [];
      renderVideos();

      const query = new URLSearchParams();
      if (searchInput?.value.trim()) query.set('search', searchInput.value.trim());
      if (cityInput?.value.trim()) query.set('city', cityInput.value.trim());
      if (positionSelect?.value.trim()) query.set('position', positionSelect.value.trim());

      try {
        query.set('per_page', '8');
        const payload = await apiGet('/scouting-search/discovery?' + query.toString(), false);
        const rows = Array.isArray(payload?.data) ? payload.data : (Array.isArray(payload) ? payload : []);
        players = rows.map(normalizeDiscoveryPlayer).filter(Boolean);
        renderPlayerResults();
      } catch (error) {
        resultsRoot.innerHTML = '<div class="ai-video-lab-empty">' + escapeHtml(error.message || 'Oyuncular getirilemedi.') + '</div>';
      }
    }

    async function startAnalysis() {
      clearNotice();
      if (!isAuthenticated()) {
        setNotice('Discovery acik. Analiz baslatmak icin once giris yapmalisin.', 'bad');
        return;
      }

      const videoClipId = Number(videoSelect?.value || 0);
      const targetPlayerId = Number(targetInput?.value || 0) || null;

      if (!selectedPlayer) {
        setNotice('Once bir oyuncu secmelisin.', 'bad');
        return;
      }

      if (!videoClipId) {
        setNotice('Analiz icin oyuncuya bagli bir video sec.', 'bad');
        return;
      }

      const cacheKey = getCacheKey(selectedPlayer.id, videoClipId);
      const cachedEntry = getAnalysisCache()[cacheKey];
      if (cachedEntry && renderCachedAnalysis(cachedEntry)) {
        setNotice('Bu video icin kayitli son analiz acildi.', 'ok');
        return;
      }

      try {
        const analysisResponse = await apiPostEnvelope('/video-analyses/start', {
          video_clip_id: videoClipId,
          target_player_id: targetPlayerId,
          analysis_type: 'scout_mvp'
        });
        const analysis = analysisResponse.data || {};
        const analysisMeta = analysisResponse.meta || {};
        const analysisSource = analysisMeta.analysis_source || 'fresh';
        if (analysis.status && analysis.status !== 'completed') {
          if (summaryRoot) {
            summaryRoot.innerHTML = createSummaryMarkup(null);
          }
          if (eventsRoot) {
            eventsRoot.innerHTML = '<div class="ai-video-lab-empty">Analiz worker kuyruguna gonderildi. Sonuc bekleniyor...</div>';
          }
          setSourceBadge(analysisSource, analysisMeta);
          setNotice('Analiz worker kuyruguna gonderildi. Sonuc bekleniyor.', 'ok');
          pollAnalysisUntilComplete(analysis.id, analysisSource, analysisMeta, cacheKey, videoClipId);
          return;
        }
        if (summaryRoot) {
          summaryRoot.innerHTML = createSummaryMarkup(analysis.summary || {});
        }
        setSourceBadge(analysisSource, analysisMeta);
        const events = await apiGet('/video-analyses/' + analysis.id + '/events', true);
        if (eventsRoot) {
          eventsRoot.innerHTML = createEventMarkup(events);
        }
        const cache = getAnalysisCache();
        cache[cacheKey] = {
          analysis_id: analysis.id,
          player_id: selectedPlayer.id,
          video_clip_id: videoClipId,
          summary: analysis.summary || null,
          events: Array.isArray(events) ? events : [],
          provider: analysisMeta.provider || analysis.provider || '',
          fallback_mode: analysisMeta.fallback_mode || '',
          cached_at: new Date().toISOString()
        };
        setAnalysisCache(cache);
        setNotice(
          analysisSource === 'cached'
            ? 'Bu video daha once analiz edilmisti. Kayitli sonuc acildi.'
            : 'Yeni video analizi tamamlandi. Ozet ve eventler guncellendi.',
          'ok'
        );
      } catch (error) {
        if (summaryRoot) {
          summaryRoot.innerHTML = createSummaryMarkup(null);
        }
        if (eventsRoot) {
          eventsRoot.innerHTML = createEventMarkup([]);
        }
        setSourceBadge('');
        setWorkerMode('', '');
        setNotice(error.message || 'Video analizi baslatilamadi.', 'bad');
      }
    }

    async function pollAnalysisUntilComplete(analysisId, analysisSource, initialMeta, cacheKey, videoClipId) {
      let attempts = 0;
      const maxAttempts = 6;
      const intervalMs = 2000;

      async function tick() {
        attempts += 1;
        try {
          const response = await fetch(getApiBaseUrl() + '/video-analyses/' + analysisId, {
            headers: {
              Accept: 'application/json',
              Authorization: 'Bearer ' + getToken()
            }
          });
          const payload = await response.json().catch(function () { return {}; });
          if (!response.ok || payload.ok === false) {
            throw new Error(payload.message || 'Analiz durumu alinamadi.');
          }
          const analysis = payload.data ?? payload;
          const analysisMeta = payload.meta || initialMeta || {};
          if (analysis.status === 'completed') {
            if (summaryRoot) {
              summaryRoot.innerHTML = createSummaryMarkup(analysis.summary || {});
            }
            const events = await apiGet('/video-analyses/' + analysis.id + '/events', true);
            if (eventsRoot) {
              eventsRoot.innerHTML = createEventMarkup(events);
            }
            const cache = getAnalysisCache();
            cache[cacheKey] = {
              analysis_id: analysis.id,
              player_id: selectedPlayer.id,
              video_clip_id: videoClipId,
              summary: analysis.summary || null,
              events: Array.isArray(events) ? events : [],
              provider: analysisMeta.provider || analysis.provider || '',
              fallback_mode: analysisMeta.fallback_mode || '',
              cached_at: new Date().toISOString()
            };
            setAnalysisCache(cache);
            setSourceBadge(analysisSource, analysisMeta);
            setNotice('Analiz tamamlandi. Sonuc worker uzerinden geldi.', 'ok');
            return;
          }

          if (analysis.status === 'failed') {
            setSourceBadge('');
            setNotice(analysis.failure_reason || 'Analiz worker tarafinda basarisiz oldu.', 'bad');
            return;
          }
        } catch (error) {
          setNotice(error.message || 'Analiz durumu alinamadi.', 'bad');
          return;
        }

        if (attempts < maxAttempts) {
          window.setTimeout(tick, intervalMs);
        } else {
          setNotice('Analiz suruyor. Birazdan yeniden kontrol et.', 'ok');
        }
      }

      window.setTimeout(tick, intervalMs);
    }

    if (searchButton) searchButton.addEventListener('click', runSearch);
    if (searchInput) {
      searchInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          runSearch();
        }
      });
    }
    if (cityInput) {
      cityInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          runSearch();
        }
      });
    }
    if (videoSelect) videoSelect.addEventListener('change', updatePreview);
    if (analyzeButton) analyzeButton.addEventListener('click', startAnalysis);
    if (discoveryButton) discoveryButton.addEventListener('click', runDiscoverySearch);
    if (discoverySearchInput) {
      discoverySearchInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          runDiscoverySearch();
        }
      });
    }
      loadQuickPicks();
    loadDiscoveryRankings();
    runDiscoverySearch();
    setWorkerMode('', '');
    resetAnalysisPanel();
    syncAnalyzeButtonState();
    if (!isAuthenticated()) {
      setNotice('AI discovery acik. Oyuncu kesfi yapabilirsin; analiz baslatmak icin giris gerekiyor.', 'ok');
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-ai-video-lab]').forEach(initLab);
  });
})();

    function syncAnalyzeButtonState() {
      if (!analyzeButton) return;
      if (isAuthenticated()) {
        analyzeButton.disabled = false;
        analyzeButton.textContent = 'Analizi Baslat';
        analyzeButton.title = '';
        return;
      }

      analyzeButton.disabled = false;
      analyzeButton.textContent = 'Giris Yap ve Analiz Et';
      analyzeButton.title = 'Analiz baslatmak icin giris gerekli';
    }
