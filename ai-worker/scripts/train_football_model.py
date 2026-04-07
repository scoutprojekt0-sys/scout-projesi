from __future__ import annotations

import argparse
import os
from copy import deepcopy
from pathlib import Path
import tempfile
import time
import warnings

import numpy as np
import torch
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
    parser.add_argument(
        "--project",
        default=str(Path(__file__).resolve().parents[2] / "runs" / "football"),
        help="YOLO project directory",
    )
    parser.add_argument("--name", default="player_ball_detector", help="YOLO run name")
    return parser.parse_args()


def _resolved_dataset_yaml(dataset_name: str, dataset_path: Path) -> str:
    dataset_config = yaml.safe_load(dataset_path.read_text(encoding="utf-8")) or {}
    dataset_root = dataset_path.parent / dataset_name
    dataset_config["path"] = dataset_root.as_posix()
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


def main() -> None:
    os.environ.setdefault("POLARS_SKIP_CPU_CHECK", "1")
    print("train_script_version=debug_v3")

    try:
        from ultralytics.engine.trainer import BaseTrainer, LOGGER, RANK, TQDM, autocast, unwrap_model  # type: ignore
        from ultralytics.models.yolo.detect import DetectionTrainer  # type: ignore
    except Exception as exc:  # pragma: no cover
        raise RuntimeError("ultralytics yuklu degil") from exc

    if not getattr(BaseTrainer, "_nextscout_patched", False):
        original_run_callbacks = BaseTrainer.run_callbacks

        def safe_run_callbacks(self, event: str):
            if event in {"on_train_end", "teardown"}:
                return
            return original_run_callbacks(self, event)

        BaseTrainer.final_eval = lambda self: None
        BaseTrainer.run_callbacks = safe_run_callbacks
        BaseTrainer._nextscout_patched = True

    args = parse_args()
    dataset_path = Path(args.data).resolve()
    if not dataset_path.exists():
        raise FileNotFoundError(f"dataset yaml bulunamadi: {dataset_path}")

    resolved_dataset_yaml = _resolved_dataset_yaml("football", dataset_path)
    overrides = {
        "model": args.model,
        "data": resolved_dataset_yaml,
        "epochs": args.epochs,
        "imgsz": args.imgsz,
        "batch": args.batch,
        "device": args.device,
        "project": args.project,
        "name": args.name,
        "pretrained": True,
        "exist_ok": True,
        "workers": 0,
        "amp": False,
        "plots": False,
        "val": False,
        "save": False,
    }

    class NextScoutDetectionTrainer(DetectionTrainer):
        def validate(self):
            self.metrics = {}
            if self.best_fitness is None:
                self.best_fitness = 0.0
            return {}, 0.0

        def _do_train(self):
            self._setup_train()
            nb = len(self.train_loader)
            nw = max(round(self.args.warmup_epochs * nb), 100) if self.args.warmup_epochs > 0 else -1
            last_opt_step = -1
            self.epoch_time_start = time.time()
            self.train_time_start = time.time()
            self.run_callbacks("on_train_start")
            LOGGER.info(
                f"Image sizes {self.args.imgsz} train, {self.args.imgsz} val\n"
                f"Using {self.train_loader.num_workers} dataloader workers\n"
                f"Logging results to {self.save_dir}\n"
                f"Starting training for {self.epochs} epochs..."
            )
            self.optimizer.zero_grad()

            for epoch in range(self.start_epoch, self.epochs):
                self.epoch = epoch
                self.run_callbacks("on_train_epoch_start")
                with warnings.catch_warnings():
                    warnings.simplefilter("ignore")
                    self.scheduler.step()

                self._model_train()
                pbar = TQDM(enumerate(self.train_loader), total=nb) if RANK in {-1, 0} else enumerate(self.train_loader)
                if RANK in {-1, 0}:
                    LOGGER.info(self.progress_string())
                self.tloss = None

                for i, batch in pbar:
                    self.run_callbacks("on_train_batch_start")
                    ni = i + nb * epoch
                    if ni <= nw:
                        xi = [0, nw]
                        self.accumulate = max(1, int(np.interp(ni, xi, [1, self.args.nbs / self.batch_size]).round()))
                        for x in self.optimizer.param_groups:
                            x["lr"] = np.interp(
                                ni,
                                xi,
                                [
                                    self.args.warmup_bias_lr if x.get("param_group") == "bias" else 0.0,
                                    x["initial_lr"] * self.lf(epoch),
                                ],
                            )
                            if "momentum" in x:
                                x["momentum"] = np.interp(ni, xi, [self.args.warmup_momentum, self.args.momentum])

                    with autocast(self.amp):
                        batch = self.preprocess_batch(batch)
                        loss, self.loss_items = self.model(batch)
                        self.loss = loss.sum()
                        self.tloss = self.loss_items if self.tloss is None else (self.tloss * i + self.loss_items) / (i + 1)

                    self.scaler.scale(self.loss).backward()
                    if ni - last_opt_step >= self.accumulate:
                        self.optimizer_step()
                        last_opt_step = ni

                    if RANK in {-1, 0}:
                        loss_length = self.tloss.shape[0] if len(self.tloss.shape) else 1
                        pbar.set_description(
                            ("%11s" * 2 + "%11.4g" * (2 + loss_length))
                            % (
                                f"{epoch + 1}/{self.epochs}",
                                f"{self._get_memory():.3g}G",
                                *(self.tloss if loss_length > 1 else torch.unsqueeze(self.tloss, 0)),
                                batch["cls"].shape[0],
                                batch["img"].shape[-1],
                            )
                        )
                    self.run_callbacks("on_train_batch_end")

                if hasattr(unwrap_model(self.model).criterion, "update"):
                    unwrap_model(self.model).criterion.update()
                self.lr = {f"lr/pg{ir}": x["lr"] for ir, x in enumerate(self.optimizer.param_groups)}
                self.run_callbacks("on_train_epoch_end")
                if RANK in {-1, 0} and self.ema:
                    self.ema.update_attr(self.model, include=["yaml", "nc", "args", "names", "stride", "class_weights"])
                self.metrics = {}
                self.fitness = 0.0
                self.best_fitness = 0.0 if self.best_fitness is None else max(self.best_fitness, 0.0)

            seconds = time.time() - self.train_time_start
            LOGGER.info(f"\n{self.epochs - self.start_epoch} epochs completed in {seconds / 3600:.3f} hours.")

    trainer = NextScoutDetectionTrainer(overrides=overrides)
    trainer.train()
    print("train_complete")

    save_dir = Path(trainer.save_dir)
    weights_dir = save_dir / "weights"
    weights_dir.mkdir(parents=True, exist_ok=True)
    print(f"weights_dir_ready={weights_dir}")
    last_path = weights_dir / "last.pt"
    best_path = weights_dir / "best.pt"

    print(f"saving_last={last_path}")
    _save_checkpoint(trainer, last_path)
    print("last_saved")

    print(f"saving_best={best_path}")
    _save_checkpoint(trainer, best_path)
    print("best_saved")
    print(f"weights_saved={weights_dir}")


if __name__ == "__main__":
    main()
