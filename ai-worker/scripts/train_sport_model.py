from __future__ import annotations

import argparse
import csv
import json
import os
import tempfile
import time
from copy import deepcopy
from pathlib import Path

import torch
import yaml


SPORTS = {"football", "basketball", "volleyball"}


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Train YOLO model for a supported sport")
    parser.add_argument("--sport", required=True, choices=sorted(SPORTS), help="Sport to train")
    parser.add_argument("--data", default=None, help="Path to YOLO dataset yaml")
    parser.add_argument("--model", default="yolov8n.pt", help="Base YOLO checkpoint")
    parser.add_argument("--epochs", type=int, default=60, help="Training epochs")
    parser.add_argument("--imgsz", type=int, default=960, help="Image size")
    parser.add_argument("--batch", type=int, default=8, help="Batch size")
    parser.add_argument("--device", default="cpu", help="Training device, e.g. cpu, 0")
    parser.add_argument("--project", default=None, help="YOLO project directory")
    parser.add_argument("--name", default="player_ball_detector", help="YOLO run name")
    parser.add_argument("--ball-boost", type=int, default=3, help="Duplicate train images containing ball labels")
    parser.add_argument("--exist-ok", action="store_true", help="Allow writing into an existing YOLO run directory")
    parser.add_argument(
        "--eval-splits",
        nargs="+",
        default=("val", "test"),
        choices=("val", "test"),
        help="Dataset splits to validate after training",
    )
    return parser.parse_args()


def default_data_path(sport: str) -> Path:
    return Path(__file__).resolve().parents[1] / "datasets" / f"{sport}_detection.yaml"


def default_project_path(sport: str) -> Path:
    return Path(__file__).resolve().parents[2] / "runs" / sport


def _patch_ultralytics_polars_usage() -> None:
    """Avoid importing polars on older CPUs where it can crash the process."""
    import numpy as np
    import ultralytics.engine.trainer as trainer_module  # type: ignore
    import ultralytics.models.yolo.detect.train as detect_train_module  # type: ignore
    import ultralytics.utils.plotting as plotting_module  # type: ignore

    def _read_results_csv_without_polars(self) -> dict[str, list[float | str]]:
        csv_path = Path(self.csv)
        if not csv_path.exists():
            return {}

        rows: dict[str, list[float | str]] = {}
        with csv_path.open("r", encoding="utf-8", newline="") as handle:
            reader = csv.DictReader(handle)
            for row in reader:
                for key, raw_value in row.items():
                    if key is None:
                        continue
                    rows.setdefault(key, [])
                    value = (raw_value or "").strip()
                    if value == "":
                        rows[key].append(value)
                        continue
                    try:
                        rows[key].append(float(value))
                    except ValueError:
                        rows[key].append(value)
        return rows

    def _plot_results_without_polars(file: str = "path/to/results.csv", dir: str = "", on_plot=None):
        import matplotlib.pyplot as plt  # type: ignore

        save_dir = Path(file).parent if file and file != "path/to/results.csv" else Path(dir)
        files = list(save_dir.glob("results*.csv"))
        if not files:
            return

        for csv_file in files:
            with csv_file.open("r", encoding="utf-8", newline="") as handle:
                reader = csv.DictReader(handle)
                rows = list(reader)
            if not rows:
                continue

            columns = [column for column in (reader.fieldnames or []) if column]
            if len(columns) < 2:
                continue

            x_values: list[float] = []
            series: dict[str, list[float | None]] = {column: [] for column in columns[1:]}
            for row in rows:
                try:
                    x_values.append(float((row.get(columns[0]) or "").strip()))
                except ValueError:
                    x_values.append(float(len(x_values)))
                for column in columns[1:]:
                    raw_value = (row.get(column) or "").strip()
                    try:
                        series[column].append(float(raw_value))
                    except ValueError:
                        series[column].append(None)

            numeric_columns = [column for column, values in series.items() if any(value is not None for value in values)]
            if not numeric_columns:
                continue

            fig, axes = plt.subplots(len(numeric_columns), 1, figsize=(10, max(4, len(numeric_columns) * 2.2)), tight_layout=True)
            if hasattr(axes, "ravel"):
                axes = axes.ravel().tolist()
            elif not isinstance(axes, list):
                axes = [axes]

            for axis, column in zip(axes, numeric_columns):
                y_values = [value if value is not None else float("nan") for value in series[column]]
                axis.plot(x_values, y_values, marker=".", linewidth=1.5, markersize=5)
                axis.set_title(column, fontsize=10)
                axis.grid(True, alpha=0.25)

            results_plot = csv_file.with_suffix(".png")
            fig.savefig(results_plot, dpi=200)
            plt.close(fig)
            if on_plot:
                on_plot(results_plot)

    def _plot_labels_without_polars(boxes, cls, names=(), save_dir=Path(""), on_plot=None):
        import matplotlib.pyplot as plt  # type: ignore
        from matplotlib.colors import LinearSegmentedColormap
        from PIL import Image, ImageDraw
        from ultralytics.utils.plotting import colors  # type: ignore

        boxes_array = np.asarray(boxes)
        cls_array = np.asarray(cls).reshape(-1)
        if boxes_array.size == 0 or cls_array.size == 0:
            return

        save_dir = Path(save_dir)
        save_dir.mkdir(parents=True, exist_ok=True)
        nc = int(cls_array.max() + 1) if cls_array.size else 0
        if nc <= 0:
            return

        subplot_color = LinearSegmentedColormap.from_list("white_blue", ["white", "blue"])
        figure, axes = plt.subplots(2, 2, figsize=(8, 8), tight_layout=True)
        axes = axes.ravel()

        histogram = axes[0].hist(cls_array, bins=np.linspace(0, nc, nc + 1) - 0.5, rwidth=0.8)
        for index in range(min(nc, len(histogram[2]))):
            histogram[2][index].set_color([channel / 255 for channel in colors(index)])
        axes[0].set_ylabel("instances")
        if 0 < len(names) < 30:
            axes[0].set_xticks(range(len(names)))
            axes[0].set_xticklabels(list(names.values()), rotation=90, fontsize=10)
            axes[0].bar_label(histogram[2])
        else:
            axes[0].set_xlabel("classes")

        draw_boxes = np.column_stack([0.5 - boxes_array[:, 2:4] / 2, 0.5 + boxes_array[:, 2:4] / 2]) * 1000
        image = Image.fromarray(np.ones((1000, 1000, 3), dtype=np.uint8) * 255)
        for class_id, box in zip(cls_array[:500], draw_boxes[:500]):
            ImageDraw.Draw(image).rectangle(box.tolist(), width=1, outline=colors(int(class_id)))
        axes[1].imshow(image)
        axes[1].axis("off")

        axes[2].hist2d(boxes_array[:, 0], boxes_array[:, 1], bins=50, cmap=subplot_color)
        axes[2].set_xlabel("x")
        axes[2].set_ylabel("y")
        axes[3].hist2d(boxes_array[:, 2], boxes_array[:, 3], bins=50, cmap=subplot_color)
        axes[3].set_xlabel("width")
        axes[3].set_ylabel("height")
        for axis in axes:
            for side in ("top", "right", "left", "bottom"):
                axis.spines[side].set_visible(False)

        labels_plot = save_dir / "labels.jpg"
        figure.savefig(labels_plot, dpi=200)
        plt.close(figure)
        if on_plot:
            on_plot(labels_plot)

    trainer_module.BaseTrainer.read_results_csv = _read_results_csv_without_polars
    trainer_module.plot_results = _plot_results_without_polars
    plotting_module.plot_results = _plot_results_without_polars
    plotting_module.plot_labels = _plot_labels_without_polars
    detect_train_module.plot_labels = _plot_labels_without_polars


def _build_ball_boost_train_list(dataset_root: Path, multiplier: int) -> str | None:
    if multiplier <= 1:
        return None

    images_dir = dataset_root / "images" / "train"
    labels_dir = dataset_root / "labels" / "train"
    if not images_dir.exists() or not labels_dir.exists():
        return None

    image_paths = sorted(path for path in images_dir.glob("*") if path.is_file())
    boosted_paths: list[str] = []
    ball_positive = 0
    for image_path in image_paths:
        label_path = labels_dir / f"{image_path.stem}.txt"
        boosted_paths.append(image_path.as_posix())
        if not label_path.exists():
            continue
        label_lines = [line.strip() for line in label_path.read_text(encoding="utf-8").splitlines() if line.strip()]
        if not any(line.split()[0] == "1" for line in label_lines):
            continue
        ball_positive += 1
        for _ in range(multiplier - 1):
            boosted_paths.append(image_path.as_posix())

    if ball_positive == 0:
        return None

    with tempfile.NamedTemporaryFile("w", suffix=".txt", delete=False, encoding="utf-8") as temp_file:
        temp_file.write("\n".join(boosted_paths))
        temp_file.write("\n")
        return temp_file.name


def _resolved_dataset_yaml(sport: str, dataset_path: Path, ball_boost: int) -> str:
    dataset_config = yaml.safe_load(dataset_path.read_text(encoding="utf-8")) or {}
    dataset_root = dataset_path.parent / sport
    dataset_config["path"] = dataset_root.as_posix()
    boosted_train_list = _build_ball_boost_train_list(dataset_root, ball_boost)
    if boosted_train_list is not None:
        dataset_config["train"] = boosted_train_list

    val_labels_dir = dataset_root / "labels" / "val"
    has_val_annotations = any(
        label_path.read_text(encoding="utf-8").strip() for label_path in val_labels_dir.glob("*.txt")
    ) if val_labels_dir.exists() else False
    if not has_val_annotations:
        dataset_config["val"] = dataset_config.get("train", "images/train")

    with tempfile.NamedTemporaryFile("w", suffix=".yaml", delete=False, encoding="utf-8") as temp_file:
        yaml.safe_dump(dataset_config, temp_file, sort_keys=False, allow_unicode=True)
        return temp_file.name


def _save_checkpoint(trainer, target_path: Path) -> None:
    target_path.parent.mkdir(parents=True, exist_ok=True)
    model_to_save = trainer.ema.ema if trainer.ema else trainer.model
    checkpoint = {
        "epoch": trainer.epoch,
        "best_fitness": trainer.best_fitness,
        "model": None,
        "ema": deepcopy(model_to_save).half(),
        "updates": trainer.ema.updates if trainer.ema else 0,
        "optimizer": None,
        "scaler": None,
        "train_args": vars(trainer.args),
        "train_metrics": trainer.metrics or {},
        "train_results": {},
        "date": None,
        "__version__": None,
    }
    torch.save(checkpoint, target_path)


def _run_validation(model_path: Path, data_yaml: str, split: str, args: argparse.Namespace, save_dir: Path) -> dict:
    from ultralytics import YOLO  # type: ignore

    output_dir = save_dir / f"{split}_metrics"
    model = YOLO(str(model_path))
    metrics = model.val(
        data=data_yaml,
        split=split,
        imgsz=args.imgsz,
        batch=args.batch,
        device=args.device,
        workers=0,
        plots=True,
        save_json=False,
        verbose=False,
        project=str(save_dir),
        name=f"{split}_metrics",
        exist_ok=True,
    )
    box = metrics.box
    return {
        "split": split,
        "map50": float(box.map50),
        "map50_95": float(box.map),
        "precision": float(box.mp),
        "recall": float(box.mr),
        "output_dir": str(output_dir),
    }


def main() -> None:
    os.environ.setdefault("POLARS_SKIP_CPU_CHECK", "1")
    args = parse_args()

    dataset_path = Path(args.data).resolve() if args.data else default_data_path(args.sport)
    if not dataset_path.exists():
        raise FileNotFoundError(f"dataset yaml bulunamadi: {dataset_path}")

    project_path = Path(args.project).resolve() if args.project else default_project_path(args.sport)
    resolved_dataset_yaml = _resolved_dataset_yaml(args.sport, dataset_path, args.ball_boost)
    _patch_ultralytics_polars_usage()

    from ultralytics import YOLO  # type: ignore

    print(f"sport={args.sport}")
    print(f"ball_boost={args.ball_boost}")
    print("train_script_version=shared_train_v1")

    model = YOLO(args.model)
    start_time = time.time()
    results = model.train(
        data=resolved_dataset_yaml,
        epochs=args.epochs,
        imgsz=args.imgsz,
        batch=args.batch,
        device=args.device,
        project=str(project_path),
        name=args.name,
        pretrained=True,
        exist_ok=args.exist_ok,
        workers=0,
        amp=False,
        plots=True,
        save=True,
        save_period=-1,
        val=True,
        verbose=True,
    )
    elapsed_hours = (time.time() - start_time) / 3600

    save_dir = Path(results.save_dir)
    weights_dir = save_dir / "weights"
    last_path = weights_dir / "last.pt"
    best_path = weights_dir / "best.pt"

    if not last_path.exists() or not best_path.exists():
        trainer = model.trainer
        _save_checkpoint(trainer, last_path)
        _save_checkpoint(trainer, best_path)

    evaluation_rows: list[dict] = []
    for split in args.eval_splits:
        if split == "test":
            test_dir = dataset_path.parent / args.sport / "labels" / "test"
            has_test_annotations = test_dir.exists() and any(
                label_path.read_text(encoding="utf-8").strip() for label_path in test_dir.glob("*.txt")
            )
            if not has_test_annotations:
                continue
        evaluation_rows.append(_run_validation(best_path, resolved_dataset_yaml, split, args, save_dir))

    summary_payload = {
        "sport": args.sport,
        "epochs": args.epochs,
        "elapsed_hours": round(elapsed_hours, 3),
        "weights_dir": str(weights_dir),
        "dataset_yaml": resolved_dataset_yaml,
        "evaluations": evaluation_rows,
    }
    summary_path = save_dir / "validation_summary.json"
    summary_path.write_text(json.dumps(summary_payload, indent=2), encoding="utf-8")

    print(f"\n{args.epochs} epochs completed in {elapsed_hours:.3f} hours.")
    print(f"weights_saved={weights_dir}")
    print(f"validation_summary={summary_path}")


if __name__ == "__main__":
    main()
