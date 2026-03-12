from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path
from typing import List, Tuple

from PIL import Image, ImageDraw, ImageFont


WIDTH = 1080
HEIGHT = 1080
FRAMES = 36
FPS_MS = 70


@dataclass
class GifSpec:
    filename: str
    lines: List[str]
    subtitle: str
    cta: str
    colors: Tuple[Tuple[int, int, int], Tuple[int, int, int]]


def load_font(size: int) -> ImageFont.FreeTypeFont | ImageFont.ImageFont:
    candidates = [
        "C:/Windows/Fonts/arialbd.ttf",
        "C:/Windows/Fonts/arial.ttf",
        "arial.ttf",
    ]

    for path in candidates:
        try:
            return ImageFont.truetype(path, size)
        except OSError:
            continue

    return ImageFont.load_default()


def gradient_background(top: Tuple[int, int, int], bottom: Tuple[int, int, int]) -> Image.Image:
    img = Image.new("RGB", (WIDTH, HEIGHT), top)
    draw = ImageDraw.Draw(img)

    for y in range(HEIGHT):
        ratio = y / max(HEIGHT - 1, 1)
        r = int(top[0] * (1 - ratio) + bottom[0] * ratio)
        g = int(top[1] * (1 - ratio) + bottom[1] * ratio)
        b = int(top[2] * (1 - ratio) + bottom[2] * ratio)
        draw.line([(0, y), (WIDTH, y)], fill=(r, g, b))

    return img


def draw_center_text(draw: ImageDraw.ImageDraw, text: str, y: int, font: ImageFont.ImageFont, fill: Tuple[int, int, int]) -> int:
    bbox = draw.textbbox((0, 0), text, font=font)
    text_w = bbox[2] - bbox[0]
    text_h = bbox[3] - bbox[1]
    x = (WIDTH - text_w) // 2
    draw.text((x + 2, y + 2), text, font=font, fill=(0, 0, 0, 120))
    draw.text((x, y), text, font=font, fill=fill)
    return text_h


def render_gif(spec: GifSpec, out_dir: Path) -> None:
    font_title = load_font(72)
    font_sub = load_font(40)
    font_cta = load_font(44)

    frames: List[Image.Image] = []

    for i in range(FRAMES):
        base = gradient_background(spec.colors[0], spec.colors[1]).convert("RGBA")
        draw = ImageDraw.Draw(base)

        # Subtle animated glow bar
        glow_x = int((i / FRAMES) * (WIDTH + 250)) - 250
        draw.rounded_rectangle([(glow_x, 90), (glow_x + 250, HEIGHT - 90)], radius=40, fill=(255, 255, 255, 24))

        y = 210
        for line in spec.lines:
            line_h = draw_center_text(draw, line, y, font_title, (255, 255, 255))
            y += line_h + 18

        y += 20
        draw_center_text(draw, spec.subtitle, y, font_sub, (229, 231, 235))

        cta_wobble = int(6 * ((i % 12) - 6) / 6)
        cta_text = f"{spec.cta}"
        cta_bbox = draw.textbbox((0, 0), cta_text, font=font_cta)
        cta_w = cta_bbox[2] - cta_bbox[0]
        cta_h = cta_bbox[3] - cta_bbox[1]
        cta_x = (WIDTH - cta_w) // 2
        cta_y = HEIGHT - 190 + cta_wobble

        draw.rounded_rectangle(
            [(cta_x - 24, cta_y - 18), (cta_x + cta_w + 24, cta_y + cta_h + 18)],
            radius=28,
            fill=(15, 23, 42, 210),
            outline=(255, 255, 255, 120),
            width=2,
        )
        draw.text((cta_x, cta_y), cta_text, font=font_cta, fill=(255, 255, 255))

        frames.append(base.convert("P", palette=Image.ADAPTIVE))

    out_path = out_dir / spec.filename
    frames[0].save(
        out_path,
        save_all=True,
        append_images=frames[1:],
        optimize=False,
        duration=FPS_MS,
        loop=0,
    )


def main() -> None:
    root = Path(__file__).resolve().parent
    out_dir = root / "output"
    out_dir.mkdir(parents=True, exist_ok=True)

    specs = [
        GifSpec(
            filename="2026-03-09-facebook-scout-api-hizli-kesif-v1.gif",
            lines=["Scout API ile", "Yetenek Kesfi HIZLANDI"],
            subtitle="Daha hizli eslesme, daha net karar.",
            cta="Hemen Kesfet",
            colors=((37, 99, 235), (30, 58, 138)),
        ),
        GifSpec(
            filename="2026-03-09-instagram-kulup-icin-dogru-oyuncu-v1.gif",
            lines=["Kulubun Icin", "Dogru Oyuncu 24/7"],
            subtitle="Filtrele, izle, teklif ver.",
            cta="Simdi Basla",
            colors=((225, 29, 72), (91, 33, 182)),
        ),
        GifSpec(
            filename="2026-03-09-x-tek-platform-v1.gif",
            lines=["Tek Platform", "Oyuncu + Menajer + Kulup"],
            subtitle="Transfer surecinde hiz ve guven.",
            cta="Demo Al",
            colors=((16, 185, 129), (20, 83, 45)),
        ),
        GifSpec(
            filename="2026-03-09-facebook-canli-firsatlar-v1.gif",
            lines=["Canli Firsatlar", "Hizli Karar"],
            subtitle="Firsati kacirmadan hamle yap.",
            cta="Firsatlari Gor",
            colors=((249, 115, 22), (154, 52, 18)),
        ),
    ]

    for spec in specs:
        render_gif(spec, out_dir)

    print(f"{len(specs)} GIF generated under: {out_dir}")


if __name__ == "__main__":
    main()
