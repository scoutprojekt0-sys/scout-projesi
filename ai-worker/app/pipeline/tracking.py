from __future__ import annotations

from app.pipeline.types import FrameDetections, TrackSnapshot


class SimpleTracker:
    def track(self, frame_detections: list[FrameDetections]) -> list[TrackSnapshot]:
        snapshots: list[TrackSnapshot] = []
        synthetic_id = 100

        for row in frame_detections:
            for detection in row.detections:
                track_id = detection.track_id if detection.track_id is not None else synthetic_id
                synthetic_id += 1
                snapshots.append(
                    TrackSnapshot(
                        track_id=track_id,
                        label=detection.label,
                        second=row.frame.second,
                        bbox=detection.bbox,
                        confidence=detection.confidence,
                    )
                )

        return snapshots
