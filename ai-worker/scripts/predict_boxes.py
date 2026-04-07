from __future__ import annotations

import argparse
import json
from pathlib import Path

import cv2


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Predict YOLO boxes for one image")
    parser.add_argument("--model", required=True, help="Path to .pt model")
    parser.add_argument("--image", required=True, help="Path to image")
    parser.add_argument("--conf", type=float, default=0.20, help="Confidence threshold")
    parser.add_argument("--iou", type=float, default=0.45, help="NMS IoU threshold")
    parser.add_argument("--max-det", type=int, default=50, help="Max detections")
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

    height, width = image.shape[:2]
    model = YOLO(str(model_path))
    result = model.predict(
        image,
        conf=args.conf,
        iou=args.iou,
        max_det=args.max_det,
        verbose=False,
    )[0]
    names = getattr(result, "names", {})

    boxes = []
    for box in getattr(result, "boxes", []):
        cls_id = int(box.cls.item())
        conf = float(box.conf.item())
        x1, y1, x2, y2 = [float(v) for v in box.xyxy[0].tolist()]
        x1 = max(0.0, min(x1, float(width)))
        y1 = max(0.0, min(y1, float(height)))
        x2 = max(0.0, min(x2, float(width)))
        y2 = max(0.0, min(y2, float(height)))
        boxes.append(
            {
                "class_id": cls_id,
                "label": str(names.get(cls_id, cls_id)),
                "confidence": round(conf, 4),
                "x": round(x1, 2),
                "y": round(y1, 2),
                "w": round(max(0.0, x2 - x1), 2),
                "h": round(max(0.0, y2 - y1), 2),
            }
        )

    print(
        json.dumps(
            {
                "ok": True,
                "image_width": width,
                "image_height": height,
                "boxes": boxes,
            },
            ensure_ascii=True,
        )
    )


if __name__ == "__main__":
    main()
