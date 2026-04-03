from dataclasses import dataclass, field
from typing import Any


@dataclass(slots=True)
class FrameSample:
    second: int
    frame_index: int
    fps: float
    image: Any | None = None


@dataclass(slots=True)
class Detection:
    label: str
    confidence: float
    bbox: tuple[float, float, float, float]
    track_id: int | None = None
    metadata: dict[str, Any] = field(default_factory=dict)


@dataclass(slots=True)
class FrameDetections:
    frame: FrameSample
    detections: list[Detection]


@dataclass(slots=True)
class TrackSnapshot:
    track_id: int
    label: str
    second: int
    bbox: tuple[float, float, float, float]
    confidence: float


@dataclass(slots=True)
class AnalysisContext:
    analysis_id: int
    video_clip_id: int
    sport: str
    target_player_id: int | None
    video_url: str | None
    thumbnail_url: str | None
    analysis_type: str
