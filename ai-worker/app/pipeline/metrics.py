from __future__ import annotations

from math import hypot

from app.pipeline.types import AnalysisContext, TrackSnapshot
from app.schemas import AnalysisEvent, AnalysisMetric, AnalysisTarget


SPORT_METRIC_PROFILES = {
    "football": {
        "base_speed": 65,
        "base_movement": 68,
        "summary_extra": lambda event_map: {
            "cross_quality_score": min(92, 72 + (7 if "cross" in event_map else 0)),
        },
        "metadata": {
            "primary_metrics": ["successful_passes", "successful_crosses", "speed_score", "movement_score"],
        },
    },
    "basketball": {
        "base_speed": 70,
        "base_movement": 72,
        "summary_extra": lambda event_map: {
            "assist_vision_score": 82 if "assist" in event_map else 71,
            "drive_efficiency_score": 84 if "drive" in event_map else 73,
        },
        "metadata": {
            "primary_metrics": ["shots", "dribbles", "speed_score", "movement_score"],
        },
    },
    "volleyball": {
        "base_speed": 62,
        "base_movement": 74,
        "summary_extra": lambda event_map: {
            "spike_quality_score": 86 if "spike" in event_map else 72,
            "block_timing_score": 81 if "block" in event_map else 70,
        },
        "metadata": {
            "primary_metrics": ["shots", "ball_recoveries", "movement_score", "speed_score"],
        },
    },
}


class MetricAggregator:
    def summarize(
        self,
        context: AnalysisContext,
        tracks: list[TrackSnapshot],
        events: list[AnalysisEvent],
    ) -> tuple[dict, list[AnalysisTarget], list[AnalysisMetric]]:
        profile = SPORT_METRIC_PROFILES.get(context.sport, SPORT_METRIC_PROFILES["football"])
        event_map = {event.event_type: event for event in events}
        player_tracks = [track for track in tracks if track.label == "player"]
        player_frames = len(player_tracks)
        movement_value = self._movement_value(player_tracks)
        speed_value = self._speed_value(player_tracks)
        speed_score = min(95, int(profile["base_speed"]) + int(speed_value / 10))
        movement_score = min(95, int(profile["base_movement"]) + int(movement_value / 14))
        successful_crosses = 1 if "cross" in event_map else 0
        successful_passes = 1 if "pass" in event_map else 0
        dribbles = 1 if "dribble" in event_map else 0

        summary = {
            "passes": max(6, successful_passes * 8),
            "successful_passes": max(4, successful_passes * 6),
            "cross_attempts": max(2, successful_crosses * 3),
            "successful_crosses": successful_crosses * 2,
            "shots": 1,
            "dribbles": dribbles * 3,
            "ball_recoveries": 1,
            "movement_score": movement_score,
            "speed_score": speed_score,
            "cross_quality_score": min(92, 72 + successful_crosses * 7),
        }
        summary.update(profile["summary_extra"](event_map))

        targets = [
            AnalysisTarget(
                player_id=context.target_player_id,
                label=f"Player {context.target_player_id}" if context.target_player_id else "Target Player",
                jersey_number="11",
                reference_data={"pipeline": "vision_v1", "sport": context.sport},
            )
        ]

        metrics = [
            AnalysisMetric(
                player_id=context.target_player_id,
                passes=summary["passes"],
                successful_passes=summary["successful_passes"],
                cross_attempts=summary["cross_attempts"],
                successful_crosses=summary["successful_crosses"],
                shots=summary["shots"],
                dribbles=summary["dribbles"],
                ball_recoveries=summary["ball_recoveries"],
                movement_score=summary["movement_score"],
                speed_score=summary["speed_score"],
                cross_quality_score=summary["cross_quality_score"],
                metadata={
                    "analysis_version": "vision-pipeline-v1",
                    "sport": context.sport,
                    "tracked_frames": player_frames,
                    "movement_value": round(movement_value, 2),
                    "speed_value": round(speed_value, 2),
                    **profile["metadata"],
                },
            )
        ]

        return summary, targets, metrics

    def _movement_value(self, tracks: list[TrackSnapshot]) -> float:
        if len(tracks) < 2:
            return 0.0
        grouped = self._group_tracks(tracks)
        return max((self._path_length(items) for items in grouped.values()), default=0.0)

    def _speed_value(self, tracks: list[TrackSnapshot]) -> float:
        if len(tracks) < 2:
            return 0.0
        grouped = self._group_tracks(tracks)
        best = 0.0
        for items in grouped.values():
            ordered = sorted(items, key=lambda item: item.second)
            for prev, curr in zip(ordered, ordered[1:]):
                delta_seconds = max(1, curr.second - prev.second)
                distance = self._distance(prev, curr)
                best = max(best, distance / delta_seconds)
        return best

    def _group_tracks(self, tracks: list[TrackSnapshot]) -> dict[int, list[TrackSnapshot]]:
        grouped: dict[int, list[TrackSnapshot]] = {}
        for item in tracks:
            grouped.setdefault(item.track_id, []).append(item)
        return grouped

    def _path_length(self, tracks: list[TrackSnapshot]) -> float:
        ordered = sorted(tracks, key=lambda item: item.second)
        return sum(self._distance(prev, curr) for prev, curr in zip(ordered, ordered[1:]))

    def _distance(self, left: TrackSnapshot, right: TrackSnapshot) -> float:
        lx = (left.bbox[0] + left.bbox[2]) / 2.0
        ly = (left.bbox[1] + left.bbox[3]) / 2.0
        rx = (right.bbox[0] + right.bbox[2]) / 2.0
        ry = (right.bbox[1] + right.bbox[3]) / 2.0
        return hypot(rx - lx, ry - ly)
