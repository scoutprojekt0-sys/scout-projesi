from __future__ import annotations

from math import hypot

from app.pipeline.types import AnalysisContext, TrackSnapshot
from app.schemas import AnalysisEvent, AnalysisMetric, AnalysisTarget


SPORT_METRIC_PROFILES = {
    "football": {
        "base_speed": 65,
        "base_movement": 68,
        "summary_extra": lambda counts: {
            "cross_quality_score": min(92, 70 + counts.get("cross", 0) * 8),
        },
        "metadata": {
            "primary_metrics": ["successful_passes", "successful_crosses", "speed_score", "movement_score"],
        },
    },
    "basketball": {
        "base_speed": 70,
        "base_movement": 72,
        "summary_extra": lambda counts: {
            "assist_vision_score": min(92, 71 + counts.get("assist", 0) * 7),
            "drive_efficiency_score": min(92, 73 + counts.get("drive", 0) * 6),
        },
        "metadata": {
            "primary_metrics": ["shots", "dribbles", "speed_score", "movement_score"],
        },
    },
    "volleyball": {
        "base_speed": 62,
        "base_movement": 74,
        "summary_extra": lambda counts: {
            "spike_quality_score": min(92, 72 + counts.get("spike", 0) * 7),
            "block_timing_score": min(92, 70 + counts.get("block", 0) * 7),
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
        player_tracks = [track for track in tracks if track.label == "player"]
        primary_track_id = context.target_track_id or (self._primary_track_id(player_tracks) if player_tracks else None)
        primary_tracks = [track for track in player_tracks if track.track_id == primary_track_id] if primary_track_id is not None else []
        event_counts = self._event_counts(events)
        movement_value_px = self._movement_value(primary_tracks)
        speed_value_px = self._speed_value(primary_tracks)
        movement_value = self._to_meters(movement_value_px, context)
        speed_value = self._to_meters(speed_value_px, context)
        speed_score = min(95, int(profile["base_speed"]) + int(speed_value / 8))
        movement_score = min(95, int(profile["base_movement"]) + int(movement_value / 12))

        summary = {
            "passes": event_counts.get("pass", 0),
            "successful_passes": event_counts.get("pass", 0),
            "cross_attempts": event_counts.get("cross", 0),
            "successful_crosses": event_counts.get("cross", 0),
            "shots": event_counts.get("shot", 0),
            "dribbles": event_counts.get("dribble", 0),
            "ball_recoveries": event_counts.get("ball_recovery", 0),
            "movement_score": movement_score,
            "speed_score": speed_score,
            "cross_quality_score": min(92, 70 + event_counts.get("cross", 0) * 8),
        }
        summary.update(profile["summary_extra"](event_counts))

        targets = [
            AnalysisTarget(
                player_id=context.target_player_id,
                label=f"Player {context.target_player_id}" if context.target_player_id else "Target Player",
                jersey_number=None,
                reference_data={
                    "pipeline": "vision_v1",
                    "sport": context.sport,
                    "source_track_id": primary_track_id,
                    "target_team_id": context.target_team_id,
                    "target_profile": context.target_profile,
                },
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
                assist_vision_score=summary.get("assist_vision_score", 0),
                drive_efficiency_score=summary.get("drive_efficiency_score", 0),
                spike_quality_score=summary.get("spike_quality_score", 0),
                block_timing_score=summary.get("block_timing_score", 0),
                metadata={
                    "analysis_version": "vision-pipeline-v1",
                    "sport": context.sport,
                    "tracked_frames": len(primary_tracks),
                    "movement_value_m": round(movement_value, 2),
                    "speed_value_m_per_sample": round(speed_value, 2),
                    "movement_value_px": round(movement_value_px, 2),
                    "speed_value_px_per_sample": round(speed_value_px, 2),
                    "source_track_id": primary_track_id,
                    "target_team_id": context.target_team_id,
                    "team_count": len(set(context.track_team_map.values())) if context.track_team_map else 0,
                    "event_counts": event_counts,
                    "meters_per_pixel": context.meters_per_pixel,
                    "calibration_confidence": context.calibration_confidence,
                    **profile["metadata"],
                },
            )
        ]

        return summary, targets, metrics

    def _event_counts(self, events: list[AnalysisEvent]) -> dict[str, int]:
        counts: dict[str, int] = {}
        for event in events:
            counts[event.event_type] = counts.get(event.event_type, 0) + 1
        return counts

    def _primary_track_id(self, tracks: list[TrackSnapshot]) -> int | None:
        if not tracks:
            return None
        counts: dict[int, int] = {}
        for item in tracks:
            counts[item.track_id] = counts.get(item.track_id, 0) + 1
        return max(counts, key=counts.get)

    def _movement_value(self, tracks: list[TrackSnapshot]) -> float:
        if len(tracks) < 2:
            return 0.0
        ordered = sorted(tracks, key=lambda item: item.second)
        return sum(self._distance(prev, curr) for prev, curr in zip(ordered, ordered[1:]))

    def _speed_value(self, tracks: list[TrackSnapshot]) -> float:
        if len(tracks) < 2:
            return 0.0
        ordered = sorted(tracks, key=lambda item: item.second)
        best = 0.0
        for prev, curr in zip(ordered, ordered[1:]):
            delta_seconds = max(1, curr.second - prev.second)
            distance = self._distance(prev, curr)
            best = max(best, distance / delta_seconds)
        return best

    def _distance(self, left: TrackSnapshot, right: TrackSnapshot) -> float:
        lx = (left.bbox[0] + left.bbox[2]) / 2.0
        ly = (left.bbox[1] + left.bbox[3]) / 2.0
        rx = (right.bbox[0] + right.bbox[2]) / 2.0
        ry = (right.bbox[1] + right.bbox[3]) / 2.0
        return hypot(rx - lx, ry - ly)

    def _to_meters(self, pixel_distance: float, context: AnalysisContext) -> float:
        if context.meters_per_pixel is not None:
            return pixel_distance * context.meters_per_pixel
        return pixel_distance / 18.0
