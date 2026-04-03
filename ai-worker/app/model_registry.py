from __future__ import annotations

from pathlib import Path

from app.config import settings


EXPECTED_LABEL_ALIASES = {
    "player": {
        "player",
        "players",
        "person",
        "athlete",
        "team player",
        "opponent player",
        "players-ball",
        "teamplayer",
        "opponent_player",
    },
    "ball": {
        "ball",
        "football",
        "soccer_ball",
        "soccer ball",
        "basketball",
        "basketball_ball",
        "basketball ball",
        "volleyball",
        "volleyball_ball",
        "volleyball ball",
        "sports ball",
    },
    "goalkeeper": {"goalkeeper", "keeper"},
    "referee": {"referee", "official"},
    "hoop": {"hoop", "rim", "basket"},
    "net": {"net", "volleyball_net", "court_net"},
}

SPORT_MODEL_PATHS = {
    "football": lambda: settings.ai_worker_football_model_path,
    "basketball": lambda: settings.ai_worker_basketball_model_path,
    "volleyball": lambda: settings.ai_worker_volleyball_model_path,
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


def resolve_model_path_for_sport(sport: str, fallback_path: str) -> Path:
    configured = SPORT_MODEL_PATHS.get(sport, lambda: fallback_path)()
    candidate = resolve_model_path(configured)
    if candidate.exists():
        return candidate
    return resolve_model_path(fallback_path)


def has_model_for_sport(sport: str, fallback_path: str) -> bool:
    target = resolve_model_path_for_sport(sport, fallback_path)
    return target.exists()


def available_models(fallback_path: str) -> dict[str, str]:
    result: dict[str, str] = {}
    for sport in ("football", "basketball", "volleyball"):
        target = resolve_model_path_for_sport(sport, fallback_path)
        result[sport] = str(target) if target.exists() else ""
    fallback = resolve_model_path(fallback_path)
    result["fallback"] = str(fallback) if fallback.exists() else ""
    return result
