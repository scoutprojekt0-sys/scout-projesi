from __future__ import annotations

from pathlib import Path


EXPECTED_LABEL_ALIASES = {
    "player": {"player", "person", "athlete"},
    "ball": {"ball", "football", "soccer_ball"},
    "goalkeeper": {"goalkeeper", "keeper"},
    "referee": {"referee", "official"},
}


def resolve_model_path(model_path: str) -> Path:
    candidate = Path(model_path)
    if candidate.is_absolute():
        return candidate

    return Path(__file__).resolve().parents[1] / candidate


def normalize_label(raw_label: str) -> str:
    label = str(raw_label).strip().lower()
    for normalized, aliases in EXPECTED_LABEL_ALIASES.items():
        if label in aliases:
            return normalized
    return label
