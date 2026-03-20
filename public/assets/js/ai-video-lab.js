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
    if (window.location.protocol === 'file:') return 'http://127.0.0.1:8000/api';
    return window.location.origin.replace(/\/$/, '') + '/api';
  }

  function getToken() {
    return (localStorage.getItem('nextscout_token') || '').trim();
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
      return '' +
        '<article class="ai-video-lab-event">' +
          '<div class="ai-video-lab-event-top">' +
            '<strong>' + escapeHtml(String(event.event_type || '').toUpperCase()) + '</strong>' +
            '<span class="ai-video-lab-badge">%' + escapeHtml(Number(event.confidence || 0)) + '</span>' +
          '</div>' +
          '<div class="ai-video-lab-note">' +
            escapeHtml(event.payload?.successful ? 'Basarili aksiyon' : 'Tespit edilen aksiyon') +
            ' · ' + escapeHtml(Number(event.start_second || 0)) + 's - ' + escapeHtml(Number(event.end_second || 0)) + 's' +
          '</div>' +
        '</article>';
    }).join('');
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

    function setSourceBadge(source) {
      if (!sourceBadge) return;
      const key = String(source || '').toLowerCase();
      if (!key) {
        sourceBadge.hidden = true;
        sourceBadge.className = 'ai-video-lab-source';
        sourceBadge.textContent = '';
        return;
      }
      sourceBadge.hidden = false;
      sourceBadge.className = 'ai-video-lab-source ' + key;
      sourceBadge.textContent = key === 'cached' ? 'Cached Analysis' : 'Fresh Analysis';
    }

    function renderCachedAnalysis(entry) {
      if (!entry) return false;
      if (summaryRoot) {
        summaryRoot.innerHTML = createSummaryMarkup(entry.summary || null);
      }
      if (eventsRoot) {
        eventsRoot.innerHTML = createEventMarkup(entry.events || []);
      }
      setSourceBadge('cached');
      return true;
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
        (player.position ? ' · ' + escapeHtml(player.position) : '') +
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
          const duration = video.duration_seconds ? ' · ' + escapeHtml(video.duration_seconds) + 's' : '';
          return '<option value="' + escapeHtml(video.id) + '">' + label + duration + '</option>';
        }))
        .join('');
      updatePreview();
    }

    function updatePreview() {
      const selectedId = Number(videoSelect?.value || 0);
      const selectedVideo = videos.find(function (video) { return Number(video.id) === selectedId; });

      if (!selectedVideo || !preview) {
        if (preview) preview.hidden = true;
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
    }

    async function loadVideos(playerId) {
      videos = [];
      renderVideos();
      try {
        const payload = await apiGet('/users/' + playerId + '/videos', false);
        videos = Array.isArray(payload?.data) ? payload.data : (Array.isArray(payload) ? payload : []);
        renderVideos();
      } catch (error) {
        videos = [];
        renderVideos();
        setNotice(error.message || 'Oyuncu videolari yuklenemedi.', 'bad');
      }
    }

    async function selectPlayer(player) {
      selectedPlayer = player;
      if (selectedRoot) selectedRoot.hidden = false;
      if (selectedName) selectedName.textContent = player.name || 'Oyuncu';
      if (selectedMeta) {
        selectedMeta.textContent = [
          'ID: ' + (player.id || '-'),
          player.position || '-',
          player.city || '-',
          'Yas: ' + (player.age || '-')
        ].join(' · ');
      }
      if (targetInput) targetInput.value = player.id || '';
      clearNotice();
      await loadVideos(player.id);
    }

    async function runSearch() {
      clearNotice();
      resultsRoot.innerHTML = '<div class="ai-video-lab-empty">Oyuncular aranıyor...</div>';
      if (selectedRoot) selectedRoot.hidden = true;
      players = [];
      videos = [];
      renderVideos();

      const query = new URLSearchParams();
      if (searchInput?.value.trim()) query.set('search', searchInput.value.trim());
      if (cityInput?.value.trim()) query.set('city', cityInput.value.trim());
      if (positionSelect?.value.trim()) query.set('position', positionSelect.value.trim());

      try {
        const payload = await apiGet('/public/players?' + query.toString(), false);
        players = Array.isArray(payload?.data) ? payload.data : (Array.isArray(payload) ? payload : []);
        renderPlayerResults();
      } catch (error) {
        resultsRoot.innerHTML = '<div class="ai-video-lab-empty">' + escapeHtml(error.message || 'Oyuncular getirilemedi.') + '</div>';
      }
    }

    async function startAnalysis() {
      clearNotice();
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
        const analysisSource = analysisResponse.meta?.analysis_source || 'fresh';
        if (summaryRoot) {
          summaryRoot.innerHTML = createSummaryMarkup(analysis.summary || {});
        }
        setSourceBadge(analysisSource);
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
        setNotice(error.message || 'Video analizi baslatilamadi.', 'bad');
      }
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
    loadQuickPicks();
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-ai-video-lab]').forEach(initLab);
  });
})();
