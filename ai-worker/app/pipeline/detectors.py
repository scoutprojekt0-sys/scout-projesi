from __future__ import annotations

from abc import ABC, abstractmethod
from pathlib import Path

from app.model_registry import normalize_label, resolve_model_path
from app.pipeline.types import Detection, FrameDetections, FrameSample


class BaseDetector(ABC):
    @abstractmethod
    def detect(self, frame: FrameSample) -> FrameDetections:
        raise NotImplementedError


class HeuristicDetector(BaseDetector):
    def detect(self, frame: FrameSample) -> FrameDetections:
        detections = []
        base_x = 80 + (frame.second % 5) * 24
        detections.append(
            Detection(
                label="player",
                confidence=0.86,
                bbox=(base_x, 120, base_x + 60, 280),
                track_id=11,
                metadata={"source": "heuristic"},
            )
        )
        detections.append(
            Detection(
                label="ball",
                confidence=0.78,
                bbox=(base_x + 42, 210, base_x + 56, 224),
                track_id=1,
                metadata={"source": "heuristic"},
            )
        )
        return FrameDetections(frame=frame, detections=detections)


class YoloDetector(BaseDetector):
    def __init__(self, model_path: str) -> None:
        self.model_path = str(resolve_model_path(model_path))
        self._model = None

    def _load(self):
        if self._model is not None:
            return self._model

        if not Path(self.model_path).exists():
            raise FileNotFoundError(f"model bulunamadi: {self.model_path}")

        try:
            from ultralytics import YOLO  # type: ignore
        except Exception as exc:  # pragma: no cover
            raise RuntimeError("ultralytics paketi yuklu degil") from exc

        self._model = YOLO(self.model_path)
        return self._model

    def detect(self, frame: FrameSample) -> FrameDetections:
        model = self._load()
        result = model.predict(frame.image, verbose=False)[0]
        detections: list[Detection] = []

        names = getattr(result, "names", {})
        for box in getattr(result, "boxes", []):
            cls_id = int(box.cls.item())
            label = normalize_label(str(names.get(cls_id, cls_id)))
            conf = float(box.conf.item())
            x1, y1, x2, y2 = [float(v) for v in box.xyxy[0].tolist()]
            detections.append(
                Detection(
                    label=label,
                    confidence=conf,
                    bbox=(x1, y1, x2, y2),
                    metadata={"source": "yolo"},
                )
            )

        return FrameDetections(frame=frame, detections=detections)
