<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NextScout</title>
    <meta name="description" content="NextScout oyuncular, scoutlar ve takimlar icin yeni nesil futbol kesif platformu.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #07111f;
            --panel: rgba(10, 19, 34, 0.82);
            --line: rgba(255, 255, 255, 0.14);
            --text: #f6f7fb;
            --muted: #bcc6d6;
            --accent: #ff8a1d;
            --accent-2: #ffb84d;
            --card: rgba(255, 255, 255, 0.08);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Manrope", sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 15% 20%, rgba(255, 138, 29, 0.28), transparent 24%),
                radial-gradient(circle at 82% 12%, rgba(88, 160, 255, 0.24), transparent 22%),
                linear-gradient(135deg, #06101c 0%, #0c1830 50%, #07111f 100%);
            overflow-x: hidden;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background:
                linear-gradient(rgba(7, 17, 31, 0.2), rgba(7, 17, 31, 0.86)),
                url('https://images.unsplash.com/photo-1517927033932-b3d18e61fb3a?auto=format&fit=crop&w=1600&q=80') center/cover no-repeat;
            opacity: 0.34;
            transform: scale(1.04);
            z-index: -2;
        }

        body::after {
            content: "";
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.05) 1px, transparent 1px);
            background-size: 56px 56px;
            mask-image: linear-gradient(to bottom, rgba(0,0,0,.45), transparent 78%);
            z-index: -1;
        }

        .page {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 32px 18px;
        }

        .hero {
            width: min(1120px, 100%);
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(320px, 430px);
            gap: 28px;
            align-items: stretch;
        }

        .story {
            padding: 38px;
            border: 1px solid var(--line);
            border-radius: 32px;
            background: linear-gradient(180deg, rgba(8, 15, 27, 0.76), rgba(8, 15, 27, 0.54));
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.38);
            backdrop-filter: blur(10px);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
            padding: 8px 14px;
            border: 1px solid rgba(255, 184, 77, 0.28);
            border-radius: 999px;
            background: rgba(255, 184, 77, 0.08);
            color: #ffd08f;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .eyebrow::before {
            content: "";
            width: 9px;
            height: 9px;
            border-radius: 999px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            box-shadow: 0 0 14px rgba(255, 138, 29, 0.7);
        }

        h1 {
            margin: 0;
            max-width: 11ch;
            font-size: clamp(3rem, 7vw, 5.8rem);
            line-height: 0.94;
            letter-spacing: -0.05em;
        }

        .lead {
            max-width: 600px;
            margin: 22px 0 0;
            color: var(--muted);
            font-size: clamp(1rem, 2vw, 1.18rem);
            line-height: 1.7;
        }

        .points {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 26px 0 0;
        }

        .point {
            padding: 12px 15px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.06);
            color: #eef3fb;
            font-size: 14px;
            font-weight: 700;
        }

        .cta {
            align-self: center;
            padding: 24px;
            border: 1px solid var(--line);
            border-radius: 28px;
            background: linear-gradient(180deg, rgba(12, 23, 41, 0.95), rgba(10, 18, 33, 0.88));
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.34);
            backdrop-filter: blur(14px);
        }

        .visual {
            min-height: 240px;
            margin-bottom: 22px;
            border-radius: 22px;
            background:
                linear-gradient(160deg, rgba(255, 138, 29, 0.2), transparent 50%),
                linear-gradient(180deg, rgba(255,255,255,0.1), rgba(255,255,255,0.02)),
                url('https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=1200&q=80') center/cover no-repeat;
            border: 1px solid rgba(255, 255, 255, 0.08);
            position: relative;
            overflow: hidden;
        }

        .visual::after {
            content: "Yetenek, veri ve firsat tek ekranda.";
            position: absolute;
            left: 18px;
            right: 18px;
            bottom: 18px;
            padding: 12px 14px;
            border-radius: 14px;
            background: rgba(5, 9, 18, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 13px;
            font-weight: 700;
            color: #eff4ff;
        }

        .panel-title {
            margin: 0 0 8px;
            font-size: 26px;
            line-height: 1.08;
        }

        .panel-copy {
            margin: 0 0 22px;
            color: var(--muted);
            line-height: 1.65;
            font-size: 15px;
        }

        .primary {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 18px;
            padding: 16px 20px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: #251102;
            font: inherit;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 18px 36px rgba(255, 138, 29, 0.26);
        }

        .secondary-links {
            display: grid;
            gap: 12px;
            margin-top: 16px;
        }

        .secondary-link {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 16px;
            border-radius: 16px;
            background: var(--card);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text);
            text-decoration: none;
            font-weight: 700;
        }

        .secondary-link small {
            color: var(--muted);
            font-size: 12px;
            font-weight: 600;
        }

        dialog {
            width: min(460px, calc(100vw - 24px));
            padding: 0;
            border: 0;
            border-radius: 26px;
            background: linear-gradient(180deg, rgba(10, 20, 38, 0.98), rgba(8, 14, 28, 0.98));
            color: var(--text);
            box-shadow: 0 32px 90px rgba(0, 0, 0, 0.45);
        }

        dialog::backdrop {
            background: rgba(3, 7, 15, 0.72);
            backdrop-filter: blur(8px);
        }

        .sheet {
            padding: 26px;
        }

        .sheet-head {
            display: flex;
            align-items: start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .sheet-head h2 {
            margin: 0 0 6px;
            font-size: 28px;
            line-height: 1;
        }

        .sheet-head p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .close {
            width: 42px;
            height: 42px;
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 999px;
            background: rgba(255,255,255,.06);
            color: var(--text);
            font-size: 18px;
            cursor: pointer;
        }

        .sheet-actions {
            display: grid;
            gap: 12px;
        }

        .sheet-card {
            display: block;
            padding: 16px 18px;
            border-radius: 18px;
            text-decoration: none;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255,255,255,.1);
            color: var(--text);
        }

        .sheet-card strong {
            display: block;
            margin-bottom: 6px;
            font-size: 16px;
        }

        .sheet-card span {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
        }

        @media (max-width: 940px) {
            .hero {
                grid-template-columns: 1fr;
            }

            .story,
            .cta {
                padding: 24px;
                border-radius: 24px;
            }

            .visual {
                min-height: 210px;
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <section class="hero">
            <div class="story">
                <div class="eyebrow">NextScout Live</div>
                <h1>Yeni nesil scout deneyimi.</h1>
                <p class="lead">
                    Oyuncular, scoutlar ve kulüpler için daha temiz bir başlangıç ekranı.
                    Karmaşık giriş yerine tek odak: hızlı kayıt, net giriş ve takım akışı.
                </p>

                <div class="points">
                    <div class="point">Oyuncu profilleri</div>
                    <div class="point">Scout keşif akışı</div>
                    <div class="point">Takım giriş alanı</div>
                </div>
            </div>

            <aside class="cta">
                <div class="visual"></div>
                <h2 class="panel-title">Sahaya çıkmaya hazır mısın?</h2>
                <p class="panel-copy">
                    Hesabını oluştur, platforma giriş yap veya takım hesabınla ayrı akıştan devam et.
                </p>

                <button class="primary" id="open-register" type="button">Kayıt Ol</button>

                <div class="secondary-links">
                    <a class="secondary-link" href="/giris.html">
                        <span>Zaten hesabım var <small>Hemen giriş yap</small></span>
                        <strong>Giriş</strong>
                    </a>
                    <a class="secondary-link" href="/takim-giris.html">
                        <span>Takımla giriş <small>Kulüp ve takım hesabı</small></span>
                        <strong>Takım</strong>
                    </a>
                </div>
            </aside>
        </section>
    </main>

    <dialog id="register-dialog">
        <div class="sheet">
            <div class="sheet-head">
                <div>
                    <h2>Kayıt Ol</h2>
                    <p>Hangi taraftan başlamak istediğini seç. Giriş ekranını sonra detaylandırırız.</p>
                </div>
                <button class="close" type="button" id="close-register" aria-label="Kapat">×</button>
            </div>

            <div class="sheet-actions">
                <a class="sheet-card" href="/giris.html">
                    <strong>Bireysel kayıt / giriş</strong>
                    <span>Oyuncu, scout veya kişisel hesap akışı için devam et.</span>
                </a>
                <a class="sheet-card" href="/takim-giris.html">
                    <strong>Takım hesabı oluştur</strong>
                    <span>Kulüp ve takım tarafı için ayrı kayıt ve giriş alanına git.</span>
                </a>
            </div>
        </div>
    </dialog>

    <script>
        const dialog = document.getElementById('register-dialog');
        const openButton = document.getElementById('open-register');
        const closeButton = document.getElementById('close-register');

        openButton.addEventListener('click', () => dialog.showModal());
        closeButton.addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', (event) => {
            const rect = dialog.getBoundingClientRect();
            const inside = rect.top <= event.clientY && event.clientY <= rect.bottom
                && rect.left <= event.clientX && event.clientX <= rect.right;

            if (!inside) {
                dialog.close();
            }
        });
    </script>
</body>
</html>
