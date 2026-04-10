from __future__ import annotations

import argparse
import json
import tempfile
from pathlib import Path

import yaml


SPORTS = {"football", "basketball", "volleyball"}


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Validate a trained YOLO model for a supported sport")
    parser.add_argument("--sport", required=True, choices=sorted(SPORTS), help="Sport to validate")
    parser.add_argument("--weights", required=True, help="Path to model weights")
    parser.add_argument("--data", default=None, help="Path to YOLO dataset yaml")
    parser.add_argument("--split", default="val", choices=("val", "test"), help="Dataset split to evaluate")
    parser.add_argument("--imgsz", type=int, default=960, help="Image size")
    parser.add_argument("--batch", type=int, default=8, help="Batch size")
    parser.add_argument("--device", default="cpu", help="Validation device")
    parser.add_argument("--output-dir", default=None, help="Where validation outputs should be stored")
    return parser.parse_args()


def default_data_path(sport: str) -> Path:
    return Path(__file__).resolve().parents[1] / "datasets" / f"{sport}_detection.yaml"


def resolved_dataset_yaml(sport: str, dataset_path: Path) -> str:
    dataset_config = yaml.safe_load(dataset_path.read_text(encoding="utf-8")) or {}
    dataset_root = dataset_path.parent / sport
    dataset_config["path"] = dataset_root.as_posix()
    with tempfile.NamedTemporaryFile("w", suffix=".yaml", delete=False, encoding="utf-8") as temp_file:
        yaml.safe_dump(dataset_config, temp_file, sort_keys=False, allow_unicode=True)
        return temp_file.name


def main() -> None:
    args = parse_args()
    weights_path = Path(args.weights).resolve()
    if not weights_path.exists():
        raise FileNotFoundError(f"weights bulunamadi: {weights_path}")

    dataset_path = Path(args.data).resolve() if args.data else default_data_path(args.sport)
    if not dataset_path.exists():
        raise FileNotFoundError(f"dataset yaml bulunamadi: {dataset_path}")

    save_dir = Path(args.output_dir).resolve() if args.output_dir else weights_path.parent.parent / f"{args.split}_metrics"
    resolved_yaml = resolved_dataset_yaml(args.sport, dataset_path)

    from ultralytics import YOLO  # type: ignore

    model = YOLO(str(weights_path))
    metrics = model.val(
        data=resolved_yaml,
        split=args.split,
        imgsz=args.imgsz,
        batch=args.batch,
        device=args.device,
        workers=0,
        plots=True,
        save_json=False,
        verbose=False,
        project=str(save_dir.parent),
        name=save_dir.name,
        exist_ok=True,
    )
    box = metrics.box
    summary = {
        "sport": args.sport,
        "split": args.split,
        "weights": str(weights_path),
        "map50": float(box.map50),
        "map50_95": float(box.map),
        "precision": float(box.mp),
        "recall": float(box.mr),
        "output_dir": str(save_dir),
    }
    summary_path = save_dir / "summary.json"
    summary_path.write_text(json.dumps(summary, indent=2), encoding="utf-8")
    print(json.dumps(summary, indent=2))


if __name__ == "__main__":
    main()
