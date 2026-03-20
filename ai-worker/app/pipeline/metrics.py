from __future__ import annotations

from app.pipeline.types import AnalysisContext, TrackSnapshot
from app.schemas import AnalysisEvent, AnalysisMetric, AnalysisTarget


class MetricAggregator:
    def summarize(
        self,
        context: AnalysisContext,
        tracks: list[TrackSnapshot],
        events: list[AnalysisEvent],
    ) -> tuple[dict, list[AnalysisTarget], list[AnalysisMetric]]:
        event_map = {event.event_type: event for event in events}
        player_frames = len([track for track in tracks if track.label == "player"])
        speed_score = min(95, 65 + player_frames)
        movement_score = min(95, 68 + int(player_frames / 2))
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

        targets = [
            AnalysisTarget(
                player_id=context.target_player_id,
                label=f"Player {context.target_player_id}" if context.target_player_id else "Target Player",
                jersey_number="11",
                reference_data={"pipeline": "vision_v1"},
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
                metadata={"analysis_version": "vision-pipeline-v1"},
            )
        ]

        return summary, targets, metrics
