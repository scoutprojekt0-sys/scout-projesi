from __future__ import annotations

from abc import ABC, abstractmethod
from pathlib import Path

from app.model_registry import normalize_label, resolve_model_path, resolve_model_path_for_sport
from app.pipeline.types import AnalysisContext, Detection, FrameDetections, FrameSample


class BaseDetector(ABC):
    @abstractmethod
    def detect(self, context: AnalysisContext, frame: FrameSample) -> FrameDetections:
        raise NotImplementedError


class HeuristicDetector(BaseDetector):
    def detect(self, context: AnalysisContext, frame: FrameSample) -> FrameDetections:
        detections = []
        base_x = 80 + (frame.second % 5) * 24
        sport_bias = {
            "football": (60, 14),
            "basketball": (52, 18),
            "volleyball": (58, 12),
        }.get(context.sport, (60, 14))
        detections.append(
            Detection(
                label="player",
                confidence=0.86,
                bbox=(base_x, 120, base_x + sport_bias[0], 280),
                track_id=11,
                metadata={"source": "heuristic", "sport": context.sport},
            )
        )
        detections.append(
            Detection(
                label="ball",
                confidence=0.78,
                bbox=(base_x + 42, 210, base_x + 42 + sport_bias[1], 224),
                track_id=1,
                metadata={"source": "heuristic", "sport": context.sport},
            )
        )
        return FrameDetections(frame=frame, detections=detections)


class YoloDetector(BaseDetector):
    def __init__(self, model_path: str) -> None:
        self.default_model_path = model_path
        self.model_path = str(resolve_model_path(model_path))
        self._model = None
        self._loaded_path: str | None = None

    def _load(self, sport: str):
        target_model_path = str(resolve_model_path_for_sport(sport, self.default_model_path))
        if self._model is not None and self._loaded_path == target_model_path:
            return self._model

        self.model_path = target_model_path
        if self._model is not None:
            self._model = None

        if not Path(self.model_path).exists():
            raise FileNotFoundError(f"model bulunamadi: {self.model_path}")

        try:
            from ultralytics import YOLO  # type: ignore
        except Exception as exc:  # pragma: no cover
            raise RuntimeError("ultralytics paketi yuklu degil") from exc

        self._model = YOLO(self.model_path)
        self._loaded_path = self.model_path
        return self._model

    def detect(self, context: AnalysisContext, frame: FrameSample) -> FrameDetections:
        model = self._load(context.sport)
        result = model.track(frame.image, persist=True, verbose=False, tracker="bytetrack.yaml")[0]
        detections: list[Detection] = []

        names = getattr(result, "names", {})
        for box in getattr(result, "boxes", []):
            cls_id = int(box.cls.item())
            label = normalize_label(str(names.get(cls_id, cls_id)))
            conf = float(box.conf.item())
            x1, y1, x2, y2 = [float(v) for v in box.xyxy[0].tolist()]
            track_id = None
            if getattr(box, "id", None) is not None:
                track_id = int(box.id.item())
            detections.append(
                Detection(
                    label=label,
                    confidence=conf,
                    bbox=(x1, y1, x2, y2),
                    track_id=track_id,
                    metadata={
                        "source": "yolo",
                        "sport": context.sport,
                        "model_path": self.model_path,
                        "tracker": "bytetrack",
                    },
                )
            )

        return FrameDetections(frame=frame, detections=detections)
