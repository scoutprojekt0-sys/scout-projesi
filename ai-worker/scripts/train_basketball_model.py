from __future__ import annotations

import argparse
from pathlib import Path


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Train basketball player/ball YOLO model")
    parser.add_argument(
        "--data",
        default=str(Path(__file__).resolve().parents[1] / "datasets" / "basketball_detection.yaml"),
        help="Path to YOLO dataset yaml",
    )
    parser.add_argument("--model", default="yolov8n.pt", help="Base YOLO checkpoint")
    parser.add_argument("--epochs", type=int, default=60, help="Training epochs")
    parser.add_argument("--imgsz", type=int, default=960, help="Image size")
    parser.add_argument("--batch", type=int, default=8, help="Batch size")
    parser.add_argument("--device", default="cpu", help="Training device, e.g. cpu, 0")
    parser.add_argument("--project", default="runs/basketball", help="YOLO project directory")
    parser.add_argument("--name", default="player_ball_detector", help="YOLO run name")
    return parser.parse_args()


def main() -> None:
    try:
        from ultralytics import YOLO  # type: ignore
    except Exception as exc:  # pragma: no cover
        raise RuntimeError("ultralytics yuklu degil") from exc

    args = parse_args()
    dataset_path = Path(args.data).resolve()
    if not dataset_path.exists():
        raise FileNotFoundError(f"dataset yaml bulunamadi: {dataset_path}")

    model = YOLO(args.model)
    model.train(
        data=str(dataset_path),
        epochs=args.epochs,
        imgsz=args.imgsz,
        batch=args.batch,
        device=args.device,
        project=args.project,
        name=args.name,
        pretrained=True,
        exist_ok=True,
    )


if __name__ == "__main__":
    main()
