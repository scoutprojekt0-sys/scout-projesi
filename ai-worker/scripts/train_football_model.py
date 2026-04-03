from __future__ import annotations

import argparse
import os
from pathlib import Path
import tempfile

import yaml


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Train football player/ball YOLO model")
    parser.add_argument(
        "--data",
        default=str(Path(__file__).resolve().parents[1] / "datasets" / "football_detection.yaml"),
        help="Path to YOLO dataset yaml",
    )
    parser.add_argument("--model", default="yolov8n.pt", help="Base YOLO checkpoint")
    parser.add_argument("--epochs", type=int, default=60, help="Training epochs")
    parser.add_argument("--imgsz", type=int, default=960, help="Image size")
    parser.add_argument("--batch", type=int, default=8, help="Batch size")
    parser.add_argument("--device", default="cpu", help="Training device, e.g. cpu, 0")
    parser.add_argument("--project", default="runs/football", help="YOLO project directory")
    parser.add_argument("--name", default="player_ball_detector", help="YOLO run name")
    return parser.parse_args()


def main() -> None:
    os.environ.setdefault("POLARS_SKIP_CPU_CHECK", "1")

    try:
        from ultralytics import YOLO  # type: ignore
    except Exception as exc:  # pragma: no cover
        raise RuntimeError("ultralytics yuklu degil") from exc

    args = parse_args()
    dataset_path = Path(args.data).resolve()
    if not dataset_path.exists():
        raise FileNotFoundError(f"dataset yaml bulunamadi: {dataset_path}")

    dataset_config = yaml.safe_load(dataset_path.read_text(encoding="utf-8")) or {}
    dataset_root = dataset_path.parent / "football"
    dataset_config["path"] = dataset_root.as_posix()
    val_labels_dir = dataset_root / "labels" / "val"
    has_val_annotations = any(
        label_path.read_text(encoding="utf-8").strip()
        for label_path in val_labels_dir.glob("*.txt")
    ) if val_labels_dir.exists() else False
    if not has_val_annotations:
        dataset_config["val"] = dataset_config.get("train", "images/train")

    with tempfile.NamedTemporaryFile("w", suffix=".yaml", delete=False, encoding="utf-8") as temp_file:
        yaml.safe_dump(dataset_config, temp_file, sort_keys=False, allow_unicode=True)
        resolved_dataset_yaml = temp_file.name

    model = YOLO(args.model)
    model.train(
        data=resolved_dataset_yaml,
        epochs=args.epochs,
        imgsz=args.imgsz,
        batch=args.batch,
        device=args.device,
        project=args.project,
        name=args.name,
        pretrained=True,
        exist_ok=True,
        workers=0,
        amp=False,
        plots=False,
        val=True,
    )


if __name__ == "__main__":
    main()
