from __future__ import annotations

from typing import Iterable

import cv2

from app.pipeline.types import FrameSample


class FrameExtractionError(RuntimeError):
    pass


class FrameExtractor:
    def __init__(self, sample_every_seconds: int = 1, max_seconds: int = 300) -> None:
        self.sample_every_seconds = max(1, sample_every_seconds)
        self.max_seconds = max_seconds if max_seconds > 0 else None

    def extract(self, video_path: str) -> Iterable[FrameSample]:
        capture = cv2.VideoCapture(video_path)
        if not capture.isOpened():
            raise FrameExtractionError(f"video acilamadi: {video_path}")

        fps = float(capture.get(cv2.CAP_PROP_FPS) or 25.0)
        frame_count = int(capture.get(cv2.CAP_PROP_FRAME_COUNT) or 0)
        video_seconds = int(frame_count / fps) if fps > 0 else 0
        total_seconds = (
            video_seconds
            if self.max_seconds is None
            else min(video_seconds, self.max_seconds)
        )

        for second in range(0, total_seconds + 1, self.sample_every_seconds):
            frame_index = int(second * fps)
            capture.set(cv2.CAP_PROP_POS_FRAMES, frame_index)
            ok, frame = capture.read()
            if not ok:
                continue
            yield FrameSample(second=second, frame_index=frame_index, fps=fps, image=frame)

        capture.release()
