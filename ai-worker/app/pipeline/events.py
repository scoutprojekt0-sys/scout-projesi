from __future__ import annotations

from app.pipeline.types import AnalysisContext, TrackSnapshot
from app.schemas import AnalysisClip, AnalysisEvent


class EventDetector:
    def detect(self, context: AnalysisContext, tracks: list[TrackSnapshot]) -> list[AnalysisEvent]:
        player_tracks = [track for track in tracks if track.label == "player"]
        if not player_tracks:
            return []

        video_url = context.video_url or f"https://example.com/video/{context.video_clip_id}"
        seeds = [
            ("pass", 12, 16, 0.88, {"successful": True, "distance_m": 15}),
            ("cross", 38, 43, 0.84, {"successful": True, "target_zone": "back_post"}),
            ("dribble", 57, 61, 0.79, {"successful": True, "opponents_beaten": 1}),
        ]

        return [
            AnalysisEvent(
                target_player_id=context.target_player_id,
                event_type=event_type,
                start_second=start_second,
                end_second=end_second,
                confidence=confidence * 100,
                payload=payload,
                clips=[
                    AnalysisClip(
                        clip_url=f"{video_url}#t={start_second},{end_second}",
                        thumbnail_url=context.thumbnail_url,
                        clip_start_second=start_second,
                        clip_end_second=end_second,
                        metadata={"generated_by": "pipeline-event-detector", "event_type": event_type},
                    )
                ],
            )
            for event_type, start_second, end_second, confidence, payload in seeds
        ]
