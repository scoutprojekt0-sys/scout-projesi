from __future__ import annotations

import argparse
import csv
import math
import random
import re
import shutil
import unicodedata
from pathlib import Path

import cv2


SPORTS = {"football", "basketball", "volleyball"}
VIDEO_EXTENSIONS = {".mp4", ".mov", ".avi", ".mkv", ".webm"}


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Prepare dataset frames from source videos")
    parser.add_argument("--sport", required=True, choices=sorted(SPORTS), help="Sport dataset to prepare")
    parser.add_argument("--source-dir", required=True, help="Directory containing raw source videos")
    parser.add_argument("--output-dir", default=None, help="Dataset root directory")
    parser.add_argument("--sample-every-seconds", type=float, default=1.0, help="Frame sample interval")
    parser.add_argument("--max-seconds", type=int, default=180, help="Max seconds to sample per video")
    parser.add_argument("--val-ratio", type=float, default=0.15, help="Validation split ratio")
    parser.add_argument("--test-ratio", type=float, default=0.10, help="Test split ratio")
    parser.add_argument("--seed", type=int, default=42, help="Shuffle seed")
    return parser.parse_args()


def ensure_dirs(dataset_root: Path) -> None:
    for split in ("train", "val", "test"):
        (dataset_root / "images" / split).mkdir(parents=True, exist_ok=True)
        (dataset_root / "labels" / split).mkdir(parents=True, exist_ok=True)


def safe_stem(value: str) -> str:
    normalized = unicodedata.normalize("NFKD", value)
    ascii_value = normalized.encode("ascii", errors="ignore").decode("ascii")
    slug = re.sub(r"[^A-Za-z0-9._-]+", "_", ascii_value).strip("._-")
    return slug or "video"


def extract_frames(video_path: Path, staging_dir: Path, sample_every_seconds: float, max_seconds: int) -> list[Path]:
    capture = cv2.VideoCapture(str(video_path))
    if not capture.isOpened():
        raise RuntimeError(f"video acilamadi: {video_path}")

    fps = float(capture.get(cv2.CAP_PROP_FPS) or 25.0)
    frame_count = int(capture.get(cv2.CAP_PROP_FRAME_COUNT) or 0)
    total_seconds = min(int(frame_count / fps) if fps > 0 else 0, max_seconds)
    sample_step = max(sample_every_seconds, 0.2)
    sampled_paths: list[Path] = []
    step_count = int(math.floor(total_seconds / sample_step)) + 1

    for index in range(step_count):
        second = round(index * sample_step, 2)
        frame_index = int(second * fps)
        capture.set(cv2.CAP_PROP_POS_FRAMES, frame_index)
        ok, frame = capture.read()
        if not ok or frame is None:
            continue
        frame_name = f"{safe_stem(video_path.stem)}_s{str(second).replace('.', '_')}.jpg"
        target = staging_dir / frame_name
        cv2.imwrite(str(target), frame)
        sampled_paths.append(target)

    capture.release()
    return sampled_paths


def assign_split(index: int, total: int, val_ratio: float, test_ratio: float) -> str:
    if total <= 1:
        return "train"
    test_cutoff = int(total * test_ratio)
    val_cutoff = int(total * (test_ratio + val_ratio))
    if index < test_cutoff:
        return "test"
    if index < val_cutoff:
        return "val"
    return "train"


def load_existing_rows(manifest_path: Path) -> list[dict[str, str]]:
    if not manifest_path.exists():
        return []
    with manifest_path.open("r", newline="", encoding="utf-8") as handle:
        return list(csv.DictReader(handle))


def processed_video_keys(rows: list[dict[str, str]]) -> set[str]:
    keys: set[str] = set()
    for row in rows:
        source_video = (row.get("source_video") or "").strip()
        if source_video:
            keys.add(safe_stem(Path(source_video).stem))
    return keys


def existing_frame_paths(rows: list[dict[str, str]]) -> set[str]:
    return {str(Path((row.get("frame_path") or "")).resolve()) for row in rows if row.get("frame_path")}


def default_output_dir(sport: str) -> Path:
    return Path(__file__).resolve().parents[1] / "datasets" / sport


def main() -> None:
    args = parse_args()
    source_dir = Path(args.source_dir).resolve()
    if not source_dir.exists():
        raise FileNotFoundError(f"source dir bulunamadi: {source_dir}")

    dataset_root = Path(args.output_dir).resolve() if args.output_dir else default_output_dir(args.sport)
    staging_dir = dataset_root / "_staging"
    manifest_path = dataset_root / "manifest.csv"

    ensure_dirs(dataset_root)
    staging_dir.mkdir(parents=True, exist_ok=True)

    existing_rows = load_existing_rows(manifest_path)
    processed_videos = processed_video_keys(existing_rows)
    existing_frames = existing_frame_paths(existing_rows)
    extracted: list[Path] = []
    frame_sources: dict[str, str] = {}

    video_files = sorted(path for path in source_dir.rglob("*") if path.suffix.lower() in VIDEO_EXTENSIONS)
    if not video_files:
        raise RuntimeError(f"video bulunamadi: {source_dir}")

    for video_path in video_files:
        normalized_video_path = str(video_path.resolve())
        video_key = safe_stem(video_path.stem)
        if video_key in processed_videos:
            print(f"skip: already processed: {video_key}")
            continue
        try:
            frames = extract_frames(video_path, staging_dir, args.sample_every_seconds, args.max_seconds)
        except RuntimeError as exc:
            safe_message = str(exc).encode("ascii", errors="replace").decode("ascii")
            print(f"skip: {safe_message}")
            continue
        for frame_path in frames:
            extracted.append(frame_path)
            frame_sources[frame_path.name] = normalized_video_path

    rng = random.Random(args.seed)
    indices = list(range(len(extracted)))
    rng.shuffle(indices)
    ordered = [extracted[idx] for idx in indices]

    final_rows = existing_rows.copy()
    for ordered_index, frame_path in enumerate(ordered):
        split = assign_split(ordered_index, len(ordered), args.val_ratio, args.test_ratio)
        image_target = dataset_root / "images" / split / frame_path.name
        label_target = dataset_root / "labels" / split / f"{frame_path.stem}.txt"
        if not frame_path.exists():
            print(f"skip: missing staging frame: {frame_path.name}")
            continue
        if str(image_target.resolve()) in existing_frames or image_target.exists():
            frame_path.unlink(missing_ok=True)
            print(f"skip: duplicate frame: {frame_path.name}")
            continue
        shutil.move(str(frame_path), str(image_target))
        label_target.touch(exist_ok=True)
        final_rows.append(
            {
                "source_video": frame_sources.get(frame_path.name, ""),
                "frame_path": str(image_target),
                "split": split,
                "labels_path": str(label_target),
                "needs_labeling": "yes",
                "notes": "",
            }
        )

    with manifest_path.open("w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(
            handle,
            fieldnames=["source_video", "frame_path", "split", "labels_path", "needs_labeling", "notes"],
        )
        writer.writeheader()
        writer.writerows(final_rows)

    if staging_dir.exists():
        shutil.rmtree(staging_dir, ignore_errors=True)

    print(f"sport={args.sport}")
    print(f"dataset_root={dataset_root}")
    print(f"new_frames={len(ordered)}")
    print(f"frames={len(final_rows)}")
    print(f"manifest={manifest_path}")


if __name__ == "__main__":
    main()
