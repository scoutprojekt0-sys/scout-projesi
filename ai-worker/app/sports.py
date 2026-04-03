from __future__ import annotations

from typing import Iterable


SUPPORTED_SPORTS = {"football", "basketball", "volleyball"}


def normalize_sport(value: str | None, *, fallback: str = "football") -> str:
    raw = (value or "").strip().lower()
    aliases = {
        "futbol": "football",
        "football": "football",
        "soccer": "football",
        "basketbol": "basketball",
        "basketball": "basketball",
        "voleybol": "volleyball",
        "volleyball": "volleyball",
        "voleibol": "volleyball",
    }
    normalized = aliases.get(raw, raw)
    return normalized if normalized in SUPPORTED_SPORTS else fallback


def infer_sport_from_tags(tags: Iterable[str] | None, *, fallback: str = "football") -> str:
    if not tags:
        return fallback
    for item in tags:
        normalized = normalize_sport(item, fallback="")
        if normalized:
            return normalized
    return fallback
