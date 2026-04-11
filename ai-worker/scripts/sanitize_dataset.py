from __future__ import annotations

import argparse
import csv
from pathlib import Path


SPORTS = {"football", "basketball", "volleyball"}
SPORT_KEYWORDS = {
    "football": {"football", "futbol", "soccer"},
    "basketball": {"basketball", "basketbol"},
    "volleyball": {"volleyball", "voleybol", "voleyball"},
}


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Remove obvious cross-sport contamination from a dataset")
    parser.add_argument("--sport", required=True, choices=sorted(SPORTS), help="Dataset sport to sanitize")
    parser.add_argument("--dataset-dir", default=None, help="Dataset root directory")
    return parser.parse_args()


def default_dataset_dir(sport: str) -> Path:
    return Path(__file__).resolve().parents[1] / "datasets" / sport


def is_mismatched_for_sport(sport: str, text: str) -> bool:
    normalized = text.lower()
    forbidden_keywords = set().union(
        *(keywords for current_sport, keywords in SPORT_KEYWORDS.items() if current_sport != sport)
    )
    return any(keyword in normalized for keyword in forbidden_keywords)


def sanitize_manifest(dataset_root: Path, sport: str) -> int:
    manifest_path = dataset_root / "manifest.csv"
    if not manifest_path.exists():
        return 0

    with manifest_path.open("r", newline="", encoding="utf-8") as handle:
        rows = list(csv.DictReader(handle))
        fieldnames = list(rows[0].keys()) if rows else [
            "source_video",
            "frame_path",
            "split",
            "labels_path",
            "needs_labeling",
            "notes",
        ]

    kept_rows = []
    removed = 0
    for row in rows:
        haystack = " ".join(
            [
                row.get("source_video", ""),
                row.get("frame_path", ""),
                row.get("labels_path", ""),
            ]
        )
        if is_mismatched_for_sport(sport, haystack):
            removed += 1
            continue
        kept_rows.append(row)

    with manifest_path.open("w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(handle, fieldnames=fieldnames)
        writer.writeheader()
        writer.writerows(kept_rows)

    return removed


def sanitize_queue_file(queue_path: Path, sport: str) -> int:
    if not queue_path.exists():
        return 0

    with queue_path.open("r", newline="", encoding="utf-8") as handle:
        rows = list(csv.reader(handle))

    if not rows:
        return 0

    kept_rows = [rows[0]]
    removed = 0
    for row in rows[1:]:
        haystack = " ".join(row)
        if is_mismatched_for_sport(sport, haystack):
            removed += 1
            continue
        kept_rows.append(row)

    with queue_path.open("w", newline="", encoding="utf-8") as handle:
        writer = csv.writer(handle)
        writer.writerows(kept_rows)

    return removed


def sanitize_skipped_file(skipped_path: Path, sport: str) -> int:
    if not skipped_path.exists():
        return 0

    lines = skipped_path.read_text(encoding="utf-8").splitlines()
    kept_lines = [line for line in lines if not is_mismatched_for_sport(sport, line)]
    removed = len(lines) - len(kept_lines)
    if removed:
        skipped_path.write_text(("\n".join(kept_lines).strip() + "\n") if kept_lines else "", encoding="utf-8")
    return removed


def remove_mismatched_files(dataset_root: Path, sport: str) -> int:
    removed = 0
    for path in dataset_root.rglob("*"):
        if not path.is_file():
            continue
        if path.name in {"manifest.csv"} or path.parent.name == "queues":
            continue
        if is_mismatched_for_sport(sport, str(path)):
            path.unlink(missing_ok=True)
            removed += 1
    return removed


def main() -> None:
    args = parse_args()
    dataset_root = Path(args.dataset_dir).resolve() if args.dataset_dir else default_dataset_dir(args.sport)
    if not dataset_root.exists():
        raise FileNotFoundError(f"dataset bulunamadi: {dataset_root}")

    removed_files = remove_mismatched_files(dataset_root, args.sport)
    removed_manifest = sanitize_manifest(dataset_root, args.sport)

    removed_queue_rows = 0
    removed_skipped_rows = 0
    queues_dir = dataset_root / "queues"
    if queues_dir.exists():
        for queue_path in queues_dir.glob("label_queue_*.csv"):
            removed_queue_rows += sanitize_queue_file(queue_path, args.sport)
        for skipped_path in queues_dir.glob("skipped_*.txt"):
            removed_skipped_rows += sanitize_skipped_file(skipped_path, args.sport)

    print(f"sport={args.sport}")
    print(f"dataset_root={dataset_root}")
    print(f"removed_files={removed_files}")
    print(f"removed_manifest_rows={removed_manifest}")
    print(f"removed_queue_rows={removed_queue_rows}")
    print(f"removed_skipped_rows={removed_skipped_rows}")


if __name__ == "__main__":
    main()
