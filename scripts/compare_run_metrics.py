from __future__ import annotations

import json
import sys
from pathlib import Path


def load_summary(path: Path) -> dict:
    return json.loads(path.read_text(encoding="utf-8"))


def eval_map(summary: dict) -> dict[str, dict]:
    return {item["split"]: item for item in summary.get("evaluations", [])}


def fmt_delta(current: float, previous: float) -> str:
    delta = current - previous
    sign = "+" if delta >= 0 else ""
    return f"{current:.3f} ({sign}{delta:.3f})"


def main() -> int:
    if len(sys.argv) < 2 or len(sys.argv) > 3:
      print("Usage: python scripts/compare_run_metrics.py <current_summary.json> [previous_summary.json]")
      return 1

    current_path = Path(sys.argv[1]).resolve()
    if not current_path.is_file():
        print(f"Current summary not found: {current_path}")
        return 1

    if len(sys.argv) == 3:
        previous_path = Path(sys.argv[2]).resolve()
    else:
        base_dir = current_path.parent.parent
        candidates = sorted(
            p for p in base_dir.glob("*/validation_summary.json") if p.resolve() != current_path
        )
        if not candidates:
            print("No previous validation_summary.json found.")
            return 1
        previous_path = candidates[-1].resolve()

    if not previous_path.is_file():
        print(f"Previous summary not found: {previous_path}")
        return 1

    current = load_summary(current_path)
    previous = load_summary(previous_path)

    current_eval = eval_map(current)
    previous_eval = eval_map(previous)

    print(f"Current : {current_path}")
    print(f"Previous: {previous_path}")
    print()

    for split in ("val", "test"):
        if split not in current_eval or split not in previous_eval:
            continue

        cur = current_eval[split]
        prev = previous_eval[split]
        print(split.upper())
        print(f"  map50      : {fmt_delta(cur['map50'], prev['map50'])}")
        print(f"  map50_95   : {fmt_delta(cur['map50_95'], prev['map50_95'])}")
        print(f"  precision  : {fmt_delta(cur['precision'], prev['precision'])}")
        print(f"  recall     : {fmt_delta(cur['recall'], prev['recall'])}")
        print()

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
