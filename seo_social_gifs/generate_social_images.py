from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path
from typing import Iterable


DATE_PREFIX = "2026-03-09"
BRAND = "nextscout.pro"


@dataclass(frozen=True)
class PlatformSpec:
    key: str
    width: int
    height: int
    folder: str
    cta: str


PLATFORMS = {
    "instagram": PlatformSpec("instagram", 1080, 1350, "instagram", "SIMDI BASLA"),
    "facebook": PlatformSpec("facebook", 1200, 630, "facebook", "DEMO AL"),
    "x": PlatformSpec("x", 1600, 900, "x", "SIMDI INCELE"),
}


INSTAGRAM_LINES = [
    "Hizli transfer, net karar",
    "Kulubune dogru oyuncu",
    "Yetenek kesfinde avantaj",
    "Canli firsatlari kacirma",
    "Transfer surecini hizlandir",
    "Rakiplerinden once gor",
    "Ayni anda daha cok aday",
    "Daha az kaos, daha net surec",
    "Scouting artik daha kolay",
    "Dogru profil, hizli iletisim",
    "Kariyer firsatlari tek ekranda",
    "Menajer + Oyuncu + Kulup",
    "Veriyle guclu karar al",
    "Sadece ihtiyacin olan adaylar",
    "Firsatlari tek merkezde topla",
    "Transferde hiz kazan",
    "Filtrele, karsilastir, sec",
    "Iletisim surecini kisalt",
    "Sureci profesyonellestir",
    "Kesfet ve hemen aksiyon al",
]


FACEBOOK_LINES = [
    "Transferde hizli karar zamani",
    "Kulubun icin en uygun oyuncu",
    "Canli scouting firsatlari",
    "Daha guclu kadro planlamasi",
    "Rakiplerinden once kesfet",
    "Yetenek havuzuna aninda eris",
    "Scouting surecini dijitallestir",
    "Adaylari hizli karsilastir",
    "Tek platformda tum surec",
    "Menajer ve kulup tek hatta",
    "Oyuncu kesfinde yeni seviye",
    "Veri odakli transfer yonetimi",
    "Hedefe uygun aday secimi",
    "Sahaya uygun profil bul",
    "Transfer surecinde netlik",
    "Zaman kaybetmeden ilerle",
    "Daha etkili scouting",
    "Daha hizli iletisim",
    "Dogru zamanda dogru hamle",
    "Kulubun icin next level scouting",
]


X_LINES = [
    "Hizli eslesme, daha cok kazanc",
    "Transferde hiz = avantaj",
    "Dogru oyuncu, dogru zaman",
    "Canli firsatlari aninda gor",
    "Scouting surecini kisalt",
    "Rakiplerinden once hamle yap",
    "Tek platform, net akis",
    "Kulup stratejine uygun kesif",
    "Adaylari veriyle sec",
    "Oyuncu havuzu cebinde",
    "Transferde belirsizligi azalt",
    "Hedef odakli scouting",
    "Iletisimde hiz kazan",
    "Saha ihtiyacina uygun adaylar",
    "Firsati kacirma, hemen bak",
    "Yetenek kesfinde profesyonel adim",
    "Scoutingde yeni standart",
    "Takimina dogru profili bul",
    "Daha az efor, daha cok sonuc",
    "Transfer surecinde one gec",
]


BACKGROUNDS = [
    "football-pitch.svg",
    "footballer-action.svg",
    "stadium-lights.svg",
    "basketball-court.svg",
    "basketball-hoop.svg",
    "mixed-sports.svg",
]


def chunk_lines(text: str, max_len: int = 22) -> list[str]:
    words = text.split()
    lines: list[str] = []
    current: list[str] = []

    for w in words:
        candidate = " ".join(current + [w])
        if len(candidate) <= max_len:
            current.append(w)
        else:
            if current:
                lines.append(" ".join(current))
            current = [w]

    if current:
        lines.append(" ".join(current))

    return lines[:3]


def escape_xml(text: str) -> str:
    return (
        text.replace("&", "&amp;")
        .replace("<", "&lt;")
        .replace(">", "&gt;")
        .replace('"', "&quot;")
        .replace("'", "&apos;")
    )


def make_svg(spec: PlatformSpec, line: str, idx: int, bg_file: str) -> str:
    lines = chunk_lines(line)
    title_font = 92 if spec.key == "instagram" else 74 if spec.key == "facebook" else 98
    sub_font = 36 if spec.key == "instagram" else 30 if spec.key == "facebook" else 40
    cta_font = 46 if spec.key == "instagram" else 38 if spec.key == "facebook" else 44

    center_x = spec.width // 2
    start_y = int(spec.height * (0.33 if spec.key == "instagram" else 0.38))

    title_parts: list[str] = []
    y = start_y
    for t in lines:
        title_parts.append(
            f'<text x="{center_x}" y="{y}" text-anchor="middle" fill="#ffffff" '
            f'font-family="Arial, Helvetica, sans-serif" font-size="{title_font}" font-weight="700">{escape_xml(t.upper())}</text>'
        )
        y += int(title_font * 1.08)

    subtitle = "Transfer surecinde hiz ve netlik"
    subtitle_y = y + int(sub_font * 0.9)

    cta_w = 560 if spec.key == "instagram" else 360 if spec.key == "facebook" else 420
    cta_h = 98 if spec.key == "instagram" else 82 if spec.key == "facebook" else 88
    cta_x = (spec.width - cta_w) // 2
    cta_y = int(spec.height * 0.72)

    tag_y = int(spec.height * 0.92)

    return f'''<svg xmlns="http://www.w3.org/2000/svg" width="{spec.width}" height="{spec.height}" viewBox="0 0 {spec.width} {spec.height}">
  <defs>
    <linearGradient id="overlay" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#111827" stop-opacity="0.78"/>
      <stop offset="100%" stop-color="#0f172a" stop-opacity="0.62"/>
    </linearGradient>
  </defs>

  <image href="../backgrounds/{bg_file}" x="0" y="0" width="{spec.width}" height="{spec.height}" preserveAspectRatio="xMidYMid slice"/>
  <rect x="0" y="0" width="{spec.width}" height="{spec.height}" fill="url(#overlay)"/>
  <rect x="40" y="40" width="{spec.width - 80}" height="{spec.height - 80}" rx="34" ry="34" fill="#000000" opacity="0.16"/>

  {''.join(title_parts)}

  <text x="{center_x}" y="{subtitle_y}" text-anchor="middle" fill="#d1d5db" font-family="Arial, Helvetica, sans-serif" font-size="{sub_font}" font-weight="500">{escape_xml(subtitle)}</text>

  <rect x="{cta_x}" y="{cta_y}" width="{cta_w}" height="{cta_h}" rx="22" ry="22" fill="#111827" opacity="0.9"/>
  <text x="{center_x}" y="{cta_y + int(cta_h * 0.64)}" text-anchor="middle" fill="#ffffff" font-family="Arial, Helvetica, sans-serif" font-size="{cta_font}" font-weight="700">{escape_xml(spec.cta)}</text>

  <text x="{center_x}" y="{tag_y}" text-anchor="middle" fill="#94a3b8" font-family="Arial, Helvetica, sans-serif" font-size="{max(24, int(sub_font * 0.75))}">{BRAND}</text>
</svg>
'''


def write_series(base_dir: Path, spec: PlatformSpec, lines: Iterable[str]) -> int:
    out_dir = base_dir / spec.folder
    out_dir.mkdir(parents=True, exist_ok=True)

    total = 0
    for i, line in enumerate(lines, start=1):
        bg = BACKGROUNDS[(i - 1) % len(BACKGROUNDS)]
        slug = f"{i:02d}"
        filename = f"{DATE_PREFIX}-{spec.key}-post-{slug}-v1.svg"
        svg = make_svg(spec, line, i, bg)
        (out_dir / filename).write_text(svg, encoding="utf-8")
        total += 1

    return total


def main() -> None:
    root = Path(__file__).resolve().parents[1]
    out_base = root / "public" / "assets" / "social" / "2026-03"

    created = 0
    created += write_series(out_base, PLATFORMS["instagram"], INSTAGRAM_LINES)
    created += write_series(out_base, PLATFORMS["facebook"], FACEBOOK_LINES)
    created += write_series(out_base, PLATFORMS["x"], X_LINES)

    print(f"Generated {created} SVG social visuals under: {out_base}")


if __name__ == "__main__":
    main()
