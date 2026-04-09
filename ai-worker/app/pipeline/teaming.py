from __future__ import annotations

from collections import defaultdict

import cv2
import numpy as np

from app.pipeline.types import FrameSample, TrackSnapshot


class TeamSeparator:
    def assign(self, frames: list[FrameSample], tracks: list[TrackSnapshot]) -> dict[int, int]:
        player_tracks = [track for track in tracks if track.label == "player"]
        if len(player_tracks) < 2:
            return {}

        frame_map = {frame.second: frame for frame in frames if frame.image is not None}
        samples: dict[int, list[np.ndarray]] = defaultdict(list)

        for track in player_tracks:
            frame = frame_map.get(track.second)
            if frame is None or frame.image is None:
                continue
            feature = self._sample_jersey_color(frame.image, track.bbox)
            if feature is not None:
                samples[track.track_id].append(feature)

        if len(samples) < 2:
            return {}

        track_ids: list[int] = []
        vectors: list[np.ndarray] = []
        for track_id, items in samples.items():
            if not items:
                continue
            track_ids.append(track_id)
            vectors.append(np.mean(np.stack(items), axis=0))

        if len(vectors) < 2:
            return {}

        data = np.float32(np.stack(vectors))
        _compactness, labels, _centers = cv2.kmeans(
            data,
            2,
            None,
            (cv2.TERM_CRITERIA_EPS + cv2.TERM_CRITERIA_MAX_ITER, 12, 1.0),
            4,
            cv2.KMEANS_PP_CENTERS,
        )

        result: dict[int, int] = {}
        for index, track_id in enumerate(track_ids):
            result[track_id] = int(labels[index][0])
        return result

    def _sample_jersey_color(
        self,
        image: np.ndarray,
        bbox: tuple[float, float, float, float],
    ) -> np.ndarray | None:
        x1, y1, x2, y2 = [int(round(v)) for v in bbox]
        h, w = image.shape[:2]
        x1 = max(0, min(x1, w - 1))
        x2 = max(x1 + 1, min(x2, w))
        y1 = max(0, min(y1, h - 1))
        y2 = max(y1 + 1, min(y2, h))

        torso_top = y1 + int((y2 - y1) * 0.18)
        torso_bottom = y1 + int((y2 - y1) * 0.55)
        torso_left = x1 + int((x2 - x1) * 0.2)
        torso_right = x1 + int((x2 - x1) * 0.8)
        crop = image[torso_top:torso_bottom, torso_left:torso_right]
        if crop.size == 0:
            return None

        hsv = cv2.cvtColor(crop, cv2.COLOR_BGR2HSV)
        # ignore pitch-like green spill in crop
        lower_green = np.array([25, 25, 25], dtype=np.uint8)
        upper_green = np.array([95, 255, 255], dtype=np.uint8)
        mask = cv2.inRange(hsv, lower_green, upper_green)
        usable = hsv[mask == 0]
        if usable.size == 0:
            usable = hsv.reshape(-1, 3)
        return np.mean(usable, axis=0)
