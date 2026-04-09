<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AI Labeler</title>
  <style>
    body { margin: 0; font-family: Arial, sans-serif; background: #0a1020; color: #fff; }
    .app { display: grid; grid-template-columns: 320px 1fr; min-height: 100vh; }
    .sidebar { border-right: 1px solid #26324d; padding: 16px; overflow: auto; }
    .main { padding: 16px; }
    .controls, .actions { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
    .toggle-row { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; color: #9fb0c7; font-size: 13px; }
    select, button { background: #14213d; color: #fff; border: 1px solid #32486d; padding: 10px 12px; border-radius: 8px; }
    input[type="checkbox"] { accent-color: #d4a94d; }
    button.primary { background: #d4a94d; color: #111; border-color: #d4a94d; font-weight: 700; }
    button.warn { background: #b45309; border-color: #b45309; color: #fff; }
    button.secondary { background: #173c73; border-color: #173c73; color: #fff; }
    .queue-item { padding: 10px; border: 1px solid #26324d; border-radius: 8px; margin-bottom: 8px; cursor: pointer; }
    .queue-item.active { border-color: #d4a94d; background: #121a2c; }
    .queue-item small { color: #9fb0c7; display: block; margin-top: 4px; }
    .canvas-wrap { position: relative; display: inline-block; border: 1px solid #26324d; background: #000; min-width: 320px; min-height: 220px; }
    canvas { display: block; max-width: 100%; cursor: crosshair; }
    .legend { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
    .legend button.active { outline: 2px solid #d4a94d; }
    .status { color: #9fb0c7; margin-top: 8px; white-space: pre-wrap; }
  </style>
</head>
<body>
  <div class="app">
    <aside class="sidebar">
      <div class="controls">
        <select id="sport">
          <option value="football">Football</option>
          <option value="basketball">Basketball</option>
          <option value="volleyball">Volleyball</option>
        </select>
        <select id="split">
          <option value="train">Train</option>
          <option value="val">Val</option>
          <option value="test">Test</option>
          <option value="all">All</option>
        </select>
        <button id="loadQueue">Queue Yukle</button>
      </div>
      <label class="toggle-row">
        <input type="checkbox" id="latestOnly">
        <span>Sadece son yuklenen video</span>
      </label>
      <div id="queue"></div>
    </aside>
    <main class="main">
      <div class="legend" id="classes"></div>
      <div class="actions">
        <button id="undo">Son Kutuyu Sil</button>
        <button class="warn" id="deleteSelected">Secili Kutuyu Sil</button>
        <button class="secondary" id="predict">AI Oner</button>
        <button class="warn" id="skip">Bos Kare / Gosterme</button>
        <button class="primary" id="save">Kaydet</button>
      </div>
      <div class="canvas-wrap">
        <canvas id="canvas"></canvas>
      </div>
      <div class="status" id="status">Queue yuklenmedi.</div>
    </main>
  </div>
  <script>
    const classDefs = [
      { id: 0, name: 'player', color: '#4ecdc4' },
      { id: 1, name: 'ball', color: '#ff6b6b' },
      { id: 2, name: 'goalkeeper', color: '#ffd166' },
      { id: 3, name: 'referee', color: '#a78bfa' },
    ];

    const canvas = document.getElementById('canvas');
    const ctx = canvas.getContext('2d');
    const queueEl = document.getElementById('queue');
    const statusEl = document.getElementById('status');
    const classesEl = document.getElementById('classes');
    const sportEl = document.getElementById('sport');
    const splitEl = document.getElementById('split');
    const latestOnlyEl = document.getElementById('latestOnly');

    let queue = [];
    let active = null;
    let image = new Image();
    let boxes = [];
    let currentClass = 0;
    let drawing = null;
    let selectedBoxIndex = -1;

    classDefs.forEach((item) => {
      const btn = document.createElement('button');
      btn.textContent = item.name;
      btn.style.borderColor = item.color;
      btn.onclick = () => {
        setActiveClass(item.id);
        if (selectedBoxIndex >= 0 && boxes[selectedBoxIndex]) {
          boxes[selectedBoxIndex].class_id = item.id;
          statusEl.textContent = `Secili kutu ${item.name} olarak degistirildi.`;
          render();
        }
      };
      if (item.id === 0) btn.classList.add('active');
      classesEl.appendChild(btn);
    });

    document.getElementById('loadQueue').onclick = loadQueue;
    document.getElementById('undo').onclick = () => {
      boxes.pop();
      selectedBoxIndex = -1;
      render();
    };
    document.getElementById('deleteSelected').onclick = deleteSelectedBox;
    document.getElementById('skip').onclick = skipItem;
    document.getElementById('save').onclick = saveLabels;
    document.getElementById('predict').onclick = predictLabels;

    canvas.addEventListener('mousedown', (e) => {
      if (!active || !image.width) return;
      const p = point(e);
      const hitIndex = findBoxAtPoint(p.x, p.y);
      if (hitIndex >= 0) {
        selectedBoxIndex = hitIndex;
        drawing = null;
        const cls = classDefs.find((x) => x.id === boxes[hitIndex].class_id);
        setActiveClass(boxes[hitIndex].class_id);
        statusEl.textContent = `Kutu secildi: ${cls ? cls.name : 'unknown'}. Sinif butonuna basarak degistirebilir veya Secili Kutuyu Sil diyebilirsin.`;
        render();
        return;
      }
      selectedBoxIndex = -1;
      drawing = { x: p.x, y: p.y, w: 0, h: 0, class_id: currentClass };
    });

    canvas.addEventListener('mousemove', (e) => {
      if (!drawing) return;
      const p = point(e);
      drawing.w = p.x - drawing.x;
      drawing.h = p.y - drawing.y;
      render();
    });

    canvas.addEventListener('mouseup', () => {
      if (!drawing) return;
      const fixed = normalizeBox(drawing);
      if (fixed.w > 4 && fixed.h > 4) {
        boxes.push(fixed);
        selectedBoxIndex = boxes.length - 1;
      }
      drawing = null;
      render();
    });

    async function loadQueue() {
      const sport = sportEl.value;
      const split = splitEl.value;
      const latestOnly = latestOnlyEl.checked;
      statusEl.textContent = 'Queue yukleniyor...';
      try {
        const params = new URLSearchParams({ split });
        if (latestOnly) params.set('latest_only', '1');
        const res = await fetch(`/api/ai-labeling/${sport}/queue?${params.toString()}`);
        const json = await res.json();
        if (!res.ok || !json.ok) {
          throw new Error(json.message || `Queue hatasi (${res.status})`);
        }
        queue = json.data || [];
        renderQueue();
        const latestMeta = json.meta && json.meta.latest_source_key ? `\nKaynak video: ${json.meta.latest_source_key}` : '';
        statusEl.textContent = `Queue yuklendi: ${queue.length} kayit${latestMeta}`;
        if (queue.length) {
          openItem(queue[0]);
        } else {
          clearCanvas();
        }
      } catch (error) {
        queue = [];
        active = null;
        renderQueue();
        clearCanvas();
        statusEl.textContent = `Queue yuklenemedi.\n${error.message}`;
      }
    }

    function renderQueue() {
      queueEl.innerHTML = '';
      queue.forEach((item) => {
        const div = document.createElement('div');
        div.className = 'queue-item' + (active && active.id === item.id ? ' active' : '');
        div.innerHTML = `<strong>${item.split}</strong><small>${item.status}</small><small>${item.source_key || '-'}</small><small>${basename(item.image_path)}</small>`;
        div.onclick = () => openItem(item);
        queueEl.appendChild(div);
      });
    }

    function openItem(item) {
      active = item;
      boxes = [];
      selectedBoxIndex = -1;
      renderQueue();
      clearCanvas();
      image = new Image();
      image.onload = () => {
        canvas.width = image.width;
        canvas.height = image.height;
        render();
        statusEl.textContent = `Gorsel yuklendi: ${basename(item.image_path)}`;
      };
      image.onerror = () => {
        clearCanvas();
        statusEl.textContent = `Gorsel yuklenemedi.\n${item.image_url}`;
      };
      statusEl.textContent = `Gorsel aciliyor...\n${item.image_path}`;
      image.src = `${item.image_url}&t=${Date.now()}`;
    }

    function render() {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      if (image.width) ctx.drawImage(image, 0, 0);

      [...boxes, ...(drawing ? [normalizeBox(drawing)] : [])].forEach((box, index) => {
        const cls = classDefs.find((x) => x.id === box.class_id) || classDefs[0];
        ctx.strokeStyle = cls.color;
        ctx.lineWidth = index === selectedBoxIndex ? 4 : 2;
        ctx.strokeRect(box.x, box.y, box.w, box.h);
        if (index === selectedBoxIndex) {
          ctx.strokeStyle = '#d4a94d';
          ctx.lineWidth = 2;
          ctx.strokeRect(box.x - 3, box.y - 3, box.w + 6, box.h + 6);
        }
        ctx.fillStyle = cls.color;
        ctx.font = '14px Arial';
        ctx.fillText(cls.name, box.x + 4, Math.max(14, box.y + 14));
      });
    }

    function point(evt) {
      const rect = canvas.getBoundingClientRect();
      return {
        x: (evt.clientX - rect.left) * (canvas.width / rect.width),
        y: (evt.clientY - rect.top) * (canvas.height / rect.height),
      };
    }

    function normalizeBox(box) {
      const x = box.w < 0 ? box.x + box.w : box.x;
      const y = box.h < 0 ? box.y + box.h : box.y;
      const w = Math.abs(box.w);
      const h = Math.abs(box.h);
      return { x, y, w, h, class_id: box.class_id };
    }

    function basename(path) {
      return path.split(/[\\\\/]/).pop();
    }

    function clearCanvas() {
      canvas.width = 0;
      canvas.height = 0;
      selectedBoxIndex = -1;
      ctx.clearRect(0, 0, canvas.width, canvas.height);
    }

    function setActiveClass(classId) {
      currentClass = classId;
      document.querySelectorAll('#classes button').forEach((button, index) => {
        button.classList.toggle('active', classDefs[index].id === classId);
      });
    }

    function findBoxAtPoint(x, y) {
      for (let index = boxes.length - 1; index >= 0; index--) {
        const box = boxes[index];
        if (x >= box.x && x <= box.x + box.w && y >= box.y && y <= box.y + box.h) {
          return index;
        }
      }

      return -1;
    }

    function deleteSelectedBox() {
      if (selectedBoxIndex < 0 || !boxes[selectedBoxIndex]) {
        statusEl.textContent = 'Silmek icin once bir kutuya tikla.';
        return;
      }

      boxes.splice(selectedBoxIndex, 1);
      selectedBoxIndex = -1;
      render();
      statusEl.textContent = 'Secili kutu silindi.';
    }

    async function predictLabels() {
      if (!active || !image.width || !image.height) {
        statusEl.textContent = 'Once bir gorsel sec ve yuklenmesini bekle.';
        return;
      }

      statusEl.textContent = 'AI onerileri aliniyor...';
      try {
        const res = await fetch(`/api/ai-labeling/${sportEl.value}/predict`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({
            image_path: active.image_path,
            conf: 0.20,
          }),
        });
        const json = await res.json();
        if (!res.ok || !json.ok) {
          throw new Error(json.message || `AI tahmin hatasi (${res.status})`);
        }

        const predicted = json.data && Array.isArray(json.data.boxes) ? json.data.boxes : [];
        boxes = predicted
          .map((box) => ({
            class_id: Number(box.class_id) || 0,
            x: Number(box.x) || 0,
            y: Number(box.y) || 0,
            w: Number(box.w) || 0,
            h: Number(box.h) || 0,
          }))
          .filter((box) => box.w > 4 && box.h > 4);
        selectedBoxIndex = -1;

        render();
        statusEl.textContent = `AI ${boxes.length} kutu onerdi. Yanlis kutulari sil, eksikleri ekle, sonra Kaydet.`;
      } catch (error) {
        statusEl.textContent = `AI onerisi alinamadi.\n${error.message}`;
      }
    }

    async function saveLabels() {
      if (!active || !image.width || !image.height) {
        statusEl.textContent = 'Once bir gorsel sec ve yuklenmesini bekle.';
        return;
      }

      const payload = {
        image_path: active.image_path,
        label_path: active.label_path,
        boxes: boxes.map((b) => ({
          class_id: b.class_id,
          x: (b.x + b.w / 2) / image.width,
          y: (b.y + b.h / 2) / image.height,
          w: b.w / image.width,
          h: b.h / image.height,
        })),
      };

      try {
        const res = await fetch(`/api/ai-labeling/${sportEl.value}/save`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify(payload),
        });
        const json = await res.json();
        if (!res.ok || !json.ok) {
          throw new Error(json.message || `Kayit hatasi (${res.status})`);
        }
        statusEl.textContent = json.message || 'Kaydedildi';
        queue = queue.filter((item) => item.id !== active.id);
        active = null;
        boxes = [];
        selectedBoxIndex = -1;
        renderQueue();
        clearCanvas();
        if (queue.length) {
          openItem(queue[0]);
        }
      } catch (error) {
        statusEl.textContent = `Kaydetme basarisiz.\n${error.message}`;
      }
    }

    async function skipItem() {
      if (!active) {
        statusEl.textContent = 'Once queue icinden bir gorsel sec.';
        return;
      }

      try {
        const res = await fetch(`/api/ai-labeling/${sportEl.value}/skip`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({
            image_path: active.image_path,
            label_path: active.label_path,
          }),
        });
        const json = await res.json();
        if (!res.ok || !json.ok) {
          throw new Error(json.message || `Atlama hatasi (${res.status})`);
        }
        statusEl.textContent = json.message || 'Gorsel atlandi';
        queue = queue.filter((item) => item.id !== active.id);
        active = null;
        boxes = [];
        selectedBoxIndex = -1;
        renderQueue();
        clearCanvas();
        if (queue.length) {
          openItem(queue[0]);
        }
      } catch (error) {
        statusEl.textContent = `Atlama basarisiz.\n${error.message}`;
      }
    }
  </script>
</body>
</html>
