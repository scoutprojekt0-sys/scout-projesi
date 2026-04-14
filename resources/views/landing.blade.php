<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NextScout</title>
    <meta name="description" content="NextScout oyuncular, scoutlar ve kulüpler için yeni nesil futbol keşif platformu.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #07111f;
            --panel: rgba(10, 19, 34, 0.84);
            --panel-strong: rgba(11, 22, 40, 0.96);
            --line: rgba(255, 255, 255, 0.12);
            --line-soft: rgba(255, 255, 255, 0.06);
            --text: #f6f7fb;
            --muted: #bcc6d6;
            --accent: #ff8a1d;
            --accent-2: #ffb84d;
            --card: rgba(255, 255, 255, 0.08);
            --edge: 8px;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Manrope", sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 14% 18%, rgba(255, 138, 29, 0.24), transparent 24%),
                radial-gradient(circle at 84% 12%, rgba(88, 160, 255, 0.18), transparent 20%),
                linear-gradient(135deg, #06101c 0%, #0c1830 50%, #07111f 100%);
            overflow-x: hidden;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background:
                linear-gradient(rgba(7, 17, 31, 0.26), rgba(7, 17, 31, 0.88)),
                url('https://images.unsplash.com/photo-1517927033932-b3d18e61fb3a?auto=format&fit=crop&w=1600&q=80') center/cover no-repeat;
            opacity: 0.3;
            z-index: -2;
            transform: scale(1.04);
        }

        body::after {
            content: "";
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,.045) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.045) 1px, transparent 1px);
            background-size: 56px 56px;
            mask-image: linear-gradient(to bottom, rgba(0,0,0,.44), transparent 74%);
            z-index: -1;
        }

        .page {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 32px 18px;
        }

        .hero {
            width: min(1160px, 100%);
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 28px;
            align-items: stretch;
        }

        .story,
        .cta {
            position: relative;
            min-height: 640px;
            height: 100%;
            border: 1px solid var(--line);
            border-radius: var(--edge);
            background: linear-gradient(180deg, rgba(8, 15, 27, 0.82), rgba(8, 15, 27, 0.56));
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.38);
            backdrop-filter: blur(10px);
            overflow: hidden;
        }

        .story::after,
        .cta::after {
            content: "";
            position: absolute;
            inset: 14px;
            border: 1px solid var(--line-soft);
            pointer-events: none;
        }

        .story {
            padding: 38px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
            padding: 8px 14px;
            border: 1px solid rgba(255, 184, 77, 0.24);
            border-radius: 2px;
            background: rgba(255, 184, 77, 0.06);
            color: #ffd08f;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .eyebrow::before {
            content: "";
            width: 8px;
            height: 8px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            box-shadow: 0 0 14px rgba(255, 138, 29, 0.7);
        }

        h1 {
            margin: 0;
            max-width: 11ch;
            font-size: clamp(2rem, 4vw, 3.5rem);
            line-height: 0.98;
            letter-spacing: -0.05em;
        }

        .lead {
            max-width: 520px;
            margin: 18px 0 0;
            color: var(--muted);
            font-size: clamp(1rem, 1.8vw, 1.08rem);
            line-height: 1.75;
        }

        .story-meta {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin: 30px 0 0;
            max-width: 620px;
        }

        .story-stat {
            padding: 14px 14px 16px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 2px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.06), rgba(255, 255, 255, 0.03));
        }

        .story-stat strong {
            display: block;
            margin-bottom: 6px;
            font-size: 24px;
            line-height: 1;
            letter-spacing: -0.04em;
        }

        .story-stat span {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.5;
            font-weight: 700;
        }

        .cta {
            display: flex;
            flex-direction: column;
            justify-content: center;
            height: 100%;
            padding: 38px;
            background: linear-gradient(180deg, rgba(12, 23, 41, 0.95), rgba(10, 18, 33, 0.88));
        }

        .visual {
            min-height: 280px;
            margin-bottom: 22px;
            border-radius: 3px;
            background:
                linear-gradient(160deg, rgba(255, 138, 29, 0.2), transparent 50%),
                linear-gradient(180deg, rgba(255,255,255,0.1), rgba(255,255,255,0.02)),
                url('https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=1200&q=80') center/cover no-repeat;
            border: 1px solid rgba(255, 255, 255, 0.08);
            position: relative;
            overflow: hidden;
        }

        .visual::after {
            content: "Yetenek, veri ve fırsat tek ekranda.";
            position: absolute;
            left: 18px;
            right: 18px;
            bottom: 18px;
            padding: 12px 14px;
            border-radius: 2px;
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
            border-radius: 2px;
            padding: 18px 20px;
            background: linear-gradient(135deg, #f3922b, var(--accent-2));
            color: #251102;
            font: inherit;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 18px 36px rgba(255, 138, 29, 0.26);
            text-transform: uppercase;
            letter-spacing: 0.08em;
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
            border-radius: 2px;
            background: var(--card);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text);
            text-decoration: none;
            font-weight: 700;
            transition: background .18s ease, border-color .18s ease, transform .18s ease;
        }

        .secondary-link:hover {
            background: rgba(255, 255, 255, 0.11);
            border-color: rgba(255, 184, 77, 0.28);
            transform: translateY(-1px);
        }

        .secondary-link small {
            color: var(--muted);
            font-size: 12px;
            font-weight: 600;
        }

        dialog {
            width: min(980px, calc(100vw - 24px));
            padding: 0;
            border: 0;
            border-radius: var(--edge);
            background: linear-gradient(180deg, rgba(10, 20, 38, 0.98), rgba(8, 14, 28, 0.98));
            color: var(--text);
            box-shadow: 0 32px 90px rgba(0, 0, 0, 0.45);
        }

        dialog::backdrop {
            background: rgba(3, 7, 15, 0.72);
            backdrop-filter: blur(8px);
        }

        .sheet {
            padding: 0;
        }

        .sheet-head {
            display: flex;
            align-items: start;
            justify-content: space-between;
            gap: 16px;
            padding: 26px 26px 18px;
            border-bottom: 1px solid rgba(255,255,255,.08);
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
            border-radius: 2px;
            background: rgba(255,255,255,.06);
            color: var(--text);
            font-size: 18px;
            cursor: pointer;
        }

        .sheet-actions {
            display: grid;
            grid-template-columns: minmax(240px, 300px) minmax(0, 1fr);
            gap: 0;
        }

        .sheet-side {
            padding: 24px 22px 26px 26px;
            border-right: 1px solid rgba(255,255,255,.08);
            background: linear-gradient(180deg, rgba(255,255,255,.035), rgba(255,255,255,.015));
        }

        .sheet-side h3 {
            margin: 0 0 10px;
            font-size: 22px;
            line-height: 1.05;
        }

        .sheet-side p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.7;
        }

        .sheet-body {
            padding: 24px 26px 26px 22px;
        }

        .role-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-top: 10px;
        }

        .sheet-card {
            display: block;
            padding: 16px 18px;
            border-radius: 2px;
            text-decoration: none;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255,255,255,.1);
            color: var(--text);
        }

        .sheet-card.role {
            min-height: 126px;
            background:
                linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.03)),
                rgba(255,255,255,.03);
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

        .sheet-kicker {
            margin: 14px 0 8px;
            color: #ffd08f;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        @media (max-width: 940px) {
            .hero {
                grid-template-columns: 1fr;
            }

            .story,
            .cta {
                padding: 24px;
                min-height: auto;
                height: auto;
            }

            .visual {
                min-height: 210px;
            }

            .story-meta {
                grid-template-columns: 1fr;
                max-width: 100%;
            }
        }

        @media (max-width: 860px) {
            dialog {
                width: min(680px, calc(100vw - 20px));
            }

            .sheet-actions {
                grid-template-columns: 1fr;
            }

            .sheet-side {
                border-right: 0;
                border-bottom: 1px solid rgba(255,255,255,.08);
                padding-right: 26px;
            }

            .sheet-body {
                padding-left: 26px;
            }

            .role-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 560px) {
            .role-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <section class="hero">
            <div class="story">
                <div class="eyebrow">NextScout Live</div>
                <h1>Oyuncu, scout ve kulüpler için tek giriş noktası.</h1>
                <p class="lead">
                    Hesabına giriş yap veya kayıt ol. Sonraki adımda rolüne uygun alan açılır.
                </p>

                <div class="story-meta">
                    <div class="story-stat">
                        <strong>01</strong>
                        <span>Doğru rolü seç, doğru akışa gir.</span>
                    </div>
                    <div class="story-stat">
                        <strong>02</strong>
                        <span>Oyuncu, scout ve kulüp tarafını ayır.</span>
                    </div>
                    <div class="story-stat">
                        <strong>03</strong>
                        <span>Canlıya çıkmadan önce yüzeyi tamamla.</span>
                    </div>
                </div>
            </div>

            <aside class="cta">
                <div class="visual"></div>
                <h2 class="panel-title">Girişi doğru kur, ürünü güçlü göster.</h2>
                <p class="panel-copy">
                    Hesabını oluştur, mevcut hesabınla devam et veya takım tarafına ayrı akıştan geç.
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
                    <p>Rolünü seç ve doğru kayıt akışına gir. Takım hesabı ayrı, diğer roller aynı giriş ailesinde.</p>
                </div>
                <button class="close" type="button" id="close-register" aria-label="Kapat">×</button>
            </div>

            <div class="sheet-actions">
                <aside class="sheet-side">
                    <h3>Rolünü seç.</h3>
                    <p>
                        Giriş ve kayıt akışını role göre ayırıyoruz. Bireysel roller tek ailede,
                        takım ve kulüp tarafı ise ayrı hatta ilerliyor.
                    </p>
                </aside>

                <div class="sheet-body">
                    <div class="sheet-kicker">Bireysel Roller</div>
                    <div class="role-grid">
                        <a class="sheet-card role" href="/giris.html?role=player">
                            <strong>Oyuncu</strong>
                            <span>Kendi profilini kur, videolarını ekle ve fırsatları takip et.</span>
                        </a>
                        <a class="sheet-card role" href="/giris.html?role=scout">
                            <strong>Scout</strong>
                            <span>Oyuncu izle, raporla ve keşif akışına doğrudan gir.</span>
                        </a>
                        <a class="sheet-card role" href="/giris.html?role=manager">
                            <strong>Menajer</strong>
                            <span>Oyuncularını temsil et, görüşmeleri ve fırsatları yönet.</span>
                        </a>
                        <a class="sheet-card role" href="/giris.html?role=coach">
                            <strong>Antrenör</strong>
                            <span>Gözlem, performans ve ekip akışlarını kendi panelinden yönet.</span>
                        </a>
                        <a class="sheet-card role" href="/giris.html?role=lawyer">
                            <strong>Avukat</strong>
                            <span>Sözleşme, hukuki süreç ve profesyonel destek akışına gir.</span>
                        </a>
                    </div>

                    <div class="sheet-kicker">Kulüp Tarafı</div>
                    <a class="sheet-card role" href="/takim-giris.html">
                        <strong>Takım / Kulüp</strong>
                        <span>Kulüp kaydı, takım girişi ve oyuncu ihtiyaçlarını yönetmek için devam et.</span>
                    </a>
                </div>
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
