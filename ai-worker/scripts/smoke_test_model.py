from __future__ import annotations

import argparse
from pathlib import Path

import cv2


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Smoke test a YOLO detector model on one image")
    parser.add_argument("--model", required=True, help="Path to .pt model")
    parser.add_argument("--image", required=True, help="Path to test image")
    parser.add_argument("--conf", type=float, default=0.25, help="Confidence threshold")
    return parser.parse_args()


def main() -> None:
    try:
        from ultralytics import YOLO  # type: ignore
    except Exception as exc:  # pragma: no cover
        raise RuntimeError("ultralytics yuklu degil") from exc

    args = parse_args()
    model_path = Path(args.model).resolve()
    image_path = Path(args.image).resolve()
    if not model_path.exists():
        raise FileNotFoundError(f"model bulunamadi: {model_path}")
    if not image_path.exists():
        raise FileNotFoundError(f"image bulunamadi: {image_path}")

    image = cv2.imread(str(image_path))
    if image is None:
        raise RuntimeError(f"image okunamadi: {image_path}")

    model = YOLO(str(model_path))
    result = model.predict(image, conf=args.conf, verbose=False)[0]
    names = getattr(result, "names", {})

    print(f"model={model_path}")
    print(f"image={image_path}")
    print(f"detections={len(getattr(result, 'boxes', []))}")

    for idx, box in enumerate(getattr(result, "boxes", []), start=1):
        cls_id = int(box.cls.item())
        label = str(names.get(cls_id, cls_id))
        conf = float(box.conf.item())
        x1, y1, x2, y2 = [round(float(v), 2) for v in box.xyxy[0].tolist()]
        print(f"{idx}. label={label} conf={conf:.3f} bbox=({x1},{y1},{x2},{y2})")


if __name__ == "__main__":
    main()
