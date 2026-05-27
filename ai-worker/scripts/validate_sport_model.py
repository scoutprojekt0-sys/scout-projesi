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


def default_event_manifest_path(sport: str) -> Path:
    return Path(__file__).resolve().parents[1] / "datasets" / sport / "event_validation.json"


def resolved_dataset_yaml(sport: str, dataset_path: Path) -> str:
    dataset_config = yaml.safe_load(dataset_path.read_text(encoding="utf-8")) or {}
    dataset_root = dataset_path.parent / sport
    dataset_config["path"] = dataset_root.as_posix()
    with tempfile.NamedTemporaryFile("w", suffix=".yaml", delete=False, encoding="utf-8") as temp_file:
        yaml.safe_dump(dataset_config, temp_file, sort_keys=False, allow_unicode=True)
        return temp_file.name


def dataset_names(dataset_path: Path) -> dict[int, str]:
    dataset_config = yaml.safe_load(dataset_path.read_text(encoding="utf-8")) or {}
    names = dataset_config.get("names") or {}
    if isinstance(names, dict):
        return {int(key): str(value) for key, value in names.items()}
    if isinstance(names, list):
        return {index: str(value) for index, value in enumerate(names)}
    return {}


def compute_ratio(numerator: int, denominator: int) -> float:
    if denominator <= 0:
        return 0.0
    return float(numerator / denominator)


def compute_f1(precision: float, recall: float) -> float:
    if precision <= 0 or recall <= 0:
        return 0.0
    return float((2 * precision * recall) / (precision + recall))


def intervals_match(expected: dict, predicted: dict, tolerance_seconds: int) -> bool:
    expected_type = str(expected.get("event_type", "")).strip().lower()
    predicted_type = str(predicted.get("event_type", "")).strip().lower()
    if expected_type == "" or predicted_type == "" or expected_type != predicted_type:
        return False

    expected_start = int(expected.get("start_second", 0))
    expected_end = int(expected.get("end_second", expected_start))
    predicted_start = int(predicted.get("start_second", 0))
    predicted_end = int(predicted.get("end_second", predicted_start))

    overlap_start = max(expected_start, predicted_start)
    overlap_end = min(expected_end, predicted_end)
    if overlap_start <= overlap_end:
        return True

    return abs(expected_start - predicted_start) <= tolerance_seconds or abs(expected_end - predicted_end) <= tolerance_seconds


def evaluate_case(case: dict, analyzer, weights_path: Path) -> dict:
    from app.schemas import VideoAnalysisJobRequest

    video_path = Path(str(case.get("video_path", ""))).expanduser().resolve()
    if not video_path.exists():
        raise FileNotFoundError(f"event validation video bulunamadi: {video_path}")

    tolerance_seconds = int(case.get("tolerance_seconds", 2))
    expected_events = []
    for row in case.get("expected_events", []):
        if not isinstance(row, dict) or "event_type" not in row:
            continue
        expected_events.append(
            {
                "event_type": str(row["event_type"]),
                "start_second": int(row.get("start_second", 0)),
                "end_second": int(row.get("end_second", row.get("start_second", 0))),
            }
        )

    payload = VideoAnalysisJobRequest(
        analysis_id=int(case.get("analysis_id", 0) or 0),
        video_clip_id=int(case.get("video_clip_id", 0) or 0),
        sport=str(case.get("sport") or "football"),
        video_url=str(video_path),
        thumbnail_url=None,
        target_player_id=case.get("target_player_id"),
        requested_by=int(case.get("requested_by", 1) or 1),
        analysis_type=str(case.get("analysis_type") or "scout_mvp"),
        target_profile=case.get("target_profile"),
        model_version=str(case.get("model_version") or weights_path.stem),
        model_path=str(weights_path),
        callback_url="http://127.0.0.1/unused-callback",
        callback_secret="event-validation",
    )
    result = analyzer.run(payload)
    predicted_events = [
        {
            "event_type": item.event_type,
            "start_second": int(item.start_second),
            "end_second": int(item.end_second),
        }
        for item in result.events
    ]

    used_predictions: set[int] = set()
    matched_pairs: list[tuple[dict, dict]] = []
    for expected in expected_events:
        for index, predicted in enumerate(predicted_events):
            if index in used_predictions:
                continue
            if intervals_match(expected, predicted, tolerance_seconds):
                used_predictions.add(index)
                matched_pairs.append((expected, predicted))
                break

    return {
        "case_id": str(case.get("case_id") or video_path.stem),
        "video_path": str(video_path),
        "expected_events": expected_events,
        "predicted_events": predicted_events,
        "matched_count": len(matched_pairs),
        "expected_count": len(expected_events),
        "predicted_count": len(predicted_events),
    }


def evaluate_event_manifest(sport: str, manifest_path: Path, weights_path: Path) -> dict:
    if not manifest_path.exists():
        return {
            "available": False,
            "reason": "manifest_missing",
            "manifest_path": str(manifest_path),
        }

    payload = json.loads(manifest_path.read_text(encoding="utf-8"))
    cases = payload.get("cases", []) if isinstance(payload, dict) else []
    if not isinstance(cases, list) or not cases:
        return {
            "available": False,
            "reason": "manifest_empty",
            "manifest_path": str(manifest_path),
        }

    from app.analyzers.pipeline import PipelineAnalyzer

    analyzer = PipelineAnalyzer()
    per_type: dict[str, dict[str, int]] = {}
    evaluated_cases = []
    total_expected = 0
    total_predicted = 0
    total_matched = 0

    for case in cases:
        if not isinstance(case, dict):
            continue
        merged_case = dict(case)
        merged_case.setdefault("sport", sport)
        case_result = evaluate_case(merged_case, analyzer, weights_path)
        evaluated_cases.append(
            {
                "case_id": case_result["case_id"],
                "video_path": case_result["video_path"],
                "expected_count": case_result["expected_count"],
                "predicted_count": case_result["predicted_count"],
                "matched_count": case_result["matched_count"],
            }
        )
        total_expected += case_result["expected_count"]
        total_predicted += case_result["predicted_count"]
        total_matched += case_result["matched_count"]

        for expected in case_result["expected_events"]:
            event_type = str(expected["event_type"])
            bucket = per_type.setdefault(event_type, {"expected": 0, "predicted": 0, "matched": 0})
            bucket["expected"] += 1
        for predicted in case_result["predicted_events"]:
            event_type = str(predicted["event_type"])
            bucket = per_type.setdefault(event_type, {"expected": 0, "predicted": 0, "matched": 0})
            bucket["predicted"] += 1

        matched_by_type: dict[str, int] = {}
        remaining_predictions = list(case_result["predicted_events"])
        for expected in case_result["expected_events"]:
            for index, predicted in enumerate(remaining_predictions):
                if predicted is None:
                    continue
                if intervals_match(expected, predicted, int(case.get("tolerance_seconds", 2))):
                    event_type = str(expected["event_type"])
                    matched_by_type[event_type] = matched_by_type.get(event_type, 0) + 1
                    remaining_predictions[index] = None
                    break
        for event_type, matched_count in matched_by_type.items():
            per_type.setdefault(event_type, {"expected": 0, "predicted": 0, "matched": 0})["matched"] += matched_count

    precision = compute_ratio(total_matched, total_predicted)
    recall = compute_ratio(total_matched, total_expected)
    by_event_type = []
    for event_type in sorted(per_type.keys()):
        counts = per_type[event_type]
        event_precision = compute_ratio(counts["matched"], counts["predicted"])
        event_recall = compute_ratio(counts["matched"], counts["expected"])
        by_event_type.append(
            {
                "event_type": event_type,
                "expected": counts["expected"],
                "predicted": counts["predicted"],
                "matched": counts["matched"],
                "precision": event_precision,
                "recall": event_recall,
                "f1": compute_f1(event_precision, event_recall),
            }
        )

    return {
        "available": True,
        "reason": "evaluated",
        "manifest_path": str(manifest_path),
        "case_count": len(evaluated_cases),
        "expected": total_expected,
        "predicted": total_predicted,
        "matched": total_matched,
        "precision": precision,
        "recall": recall,
        "f1": compute_f1(precision, recall),
        "cases": evaluated_cases,
        "by_event_type": by_event_type,
    }


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
    class_names = dataset_names(dataset_path)
    per_class = []
    for metric_index, class_index in enumerate(list(box.ap_class_index) if len(box.ap_class_index) else []):
        precision, recall, map50, map50_95 = box.class_result(metric_index)
        class_id = int(class_index)
        per_class.append(
            {
                "class_id": class_id,
                "class_name": class_names.get(class_id, str(class_id)),
                "precision": float(precision),
                "recall": float(recall),
                "map50": float(map50),
                "map50_95": float(map50_95),
            }
        )
    summary = {
        "sport": args.sport,
        "split": args.split,
        "weights": str(weights_path),
        "map50": float(box.map50),
        "map50_95": float(box.map),
        "precision": float(box.mp),
        "recall": float(box.mr),
        "per_class": per_class,
        "event_validation": evaluate_event_manifest(args.sport, default_event_manifest_path(args.sport), weights_path),
        "output_dir": str(save_dir),
    }
    summary_path = save_dir / "summary.json"
    summary_path.write_text(json.dumps(summary, indent=2), encoding="utf-8")
    print(json.dumps(summary, indent=2))


if __name__ == "__main__":
    main()
