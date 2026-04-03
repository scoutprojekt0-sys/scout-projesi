from __future__ import annotations

from dataclasses import dataclass
from math import hypot

from app.pipeline.types import FrameDetections, TrackSnapshot


@dataclass(slots=True)
class _ActiveTrack:
    track_id: int
    label: str
    bbox: tuple[float, float, float, float]
    second: int


class SimpleTracker:
    def __init__(self, max_center_distance: float = 120.0) -> None:
        self.max_center_distance = max_center_distance

    def track(self, frame_detections: list[FrameDetections]) -> list[TrackSnapshot]:
        snapshots: list[TrackSnapshot] = []
        active_tracks: list[_ActiveTrack] = []
        synthetic_id = 100

        for row in frame_detections:
            for detection in row.detections:
                if detection.track_id is not None:
                    track_id = detection.track_id
                    self._update_active_track(
                        active_tracks,
                        _ActiveTrack(
                            track_id=track_id,
                            label=detection.label,
                            bbox=detection.bbox,
                            second=row.frame.second,
                        ),
                    )
                else:
                    matched = self._match_track(active_tracks, detection.label, detection.bbox, row.frame.second)
                    if matched is None:
                        track_id = synthetic_id
                        synthetic_id += 1
                        active_tracks.append(
                            _ActiveTrack(
                                track_id=track_id,
                                label=detection.label,
                                bbox=detection.bbox,
                                second=row.frame.second,
                            )
                        )
                    else:
                        track_id = matched.track_id
                        matched.bbox = detection.bbox
                        matched.second = row.frame.second

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

    def _match_track(
        self,
        active_tracks: list[_ActiveTrack],
        label: str,
        bbox: tuple[float, float, float, float],
        second: int,
    ) -> _ActiveTrack | None:
        candidates = [
            track for track in active_tracks if track.label == label and abs(track.second - second) <= 3
        ]
        if not candidates:
            return None

        target_center = self._center(bbox)
        ranked = sorted(
            candidates,
            key=lambda track: hypot(
                target_center[0] - self._center(track.bbox)[0],
                target_center[1] - self._center(track.bbox)[1],
            ),
        )
        best = ranked[0]
        distance = hypot(
            target_center[0] - self._center(best.bbox)[0],
            target_center[1] - self._center(best.bbox)[1],
        )
        return best if distance <= self.max_center_distance else None

    def _update_active_track(self, active_tracks: list[_ActiveTrack], updated: _ActiveTrack) -> None:
        for index, track in enumerate(active_tracks):
            if track.track_id == updated.track_id:
                active_tracks[index] = updated
                return
        active_tracks.append(updated)

    def _center(self, bbox: tuple[float, float, float, float]) -> tuple[float, float]:
        x1, y1, x2, y2 = bbox
        return ((x1 + x2) / 2.0, (y1 + y2) / 2.0)
