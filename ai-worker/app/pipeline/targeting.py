from __future__ import annotations

from collections import defaultdict

from app.pipeline.types import AnalysisContext, TrackSnapshot


class TargetLocker:
    def choose_track(self, context: AnalysisContext, tracks: list[TrackSnapshot]) -> int | None:
        player_tracks = [track for track in tracks if track.label == "player"]
        if not player_tracks:
            return None

        grouped: dict[int, list[TrackSnapshot]] = defaultdict(list)
        for track in player_tracks:
            grouped[track.track_id].append(track)

        preferred_position = ((context.target_profile or {}).get("position") or "").strip().lower()
        expected_height_cm = self._as_number((context.target_profile or {}).get("height_cm"))

        scored: list[tuple[float, int]] = []
        for track_id, items in grouped.items():
            duration_score = min(25.0, float(len(items)) * 1.8)
            avg_height_px = sum(item.bbox[3] - item.bbox[1] for item in items) / max(1, len(items))
            height_score = 0.0
            if expected_height_cm is not None:
                estimated_cm = 150.0 + ((avg_height_px - 80.0) * 0.35)
                delta = abs(estimated_cm - expected_height_cm)
                height_score = max(0.0, 20.0 - delta / 2.5)

            role_score = 0.0
            if preferred_position.startswith("kaleci") and self._looks_like_goalkeeper(items):
                role_score = 12.0
            elif preferred_position and not preferred_position.startswith("kaleci"):
                role_score = 4.0

            centrality_score = self._centrality_score(items)
            total = duration_score + height_score + role_score + centrality_score
            scored.append((total, track_id))

        scored.sort(reverse=True)
        return scored[0][1] if scored else None

    def _centrality_score(self, items: list[TrackSnapshot]) -> float:
        centers = [((item.bbox[0] + item.bbox[2]) / 2.0) for item in items]
        if not centers:
            return 0.0
        avg_center = sum(centers) / len(centers)
        # Wide edge tracks get a slight penalty; central repeated track is preferred.
        return max(0.0, 15.0 - abs(avg_center - 640.0) / 50.0)

    def _looks_like_goalkeeper(self, items: list[TrackSnapshot]) -> bool:
        centers = [((item.bbox[0] + item.bbox[2]) / 2.0) for item in items]
        if not centers:
            return False
        avg_center = sum(centers) / len(centers)
        return avg_center < 220.0 or avg_center > 1060.0

    def _as_number(self, value: object) -> float | None:
        if value is None:
            return None
        try:
            return float(value)
        except (TypeError, ValueError):
            return None
