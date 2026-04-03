from __future__ import annotations

import tempfile
from pathlib import Path

import httpx

from app.config import settings
from app.model_registry import has_model_for_sport
from app.pipeline.detectors import HeuristicDetector, YoloDetector
from app.pipeline.events import EventDetector
from app.pipeline.extractor import FrameExtractor
from app.pipeline.metrics import MetricAggregator
from app.pipeline.tracking import SimpleTracker
from app.pipeline.types import AnalysisContext
from app.schemas import VideoAnalysisJobRequest, VideoAnalysisResult
from app.sports import normalize_sport


class PipelineAnalyzer:
    def __init__(self) -> None:
        self.extractor = FrameExtractor(
            sample_every_seconds=settings.ai_worker_sample_every_seconds,
            max_seconds=settings.ai_worker_max_sample_seconds,
        )
        self.tracker = SimpleTracker()
        self.event_detector = EventDetector()
        self.metric_aggregator = MetricAggregator()

    def _build_detector(self, sport: str):
        detector_mode = settings.ai_worker_detector.strip().lower()
        if detector_mode == "yolo":
            return YoloDetector(settings.ai_worker_yolo_model_path)
        if detector_mode == "auto" and has_model_for_sport(sport, settings.ai_worker_yolo_model_path):
            return YoloDetector(settings.ai_worker_yolo_model_path)
        return HeuristicDetector()

    def run(self, job: VideoAnalysisJobRequest) -> VideoAnalysisResult:
        if not job.video_url:
            raise RuntimeError("pipeline analysis icin video_url zorunlu")

        context = AnalysisContext(
            analysis_id=job.analysis_id,
            video_clip_id=job.video_clip_id,
            sport=normalize_sport(job.sport),
            target_player_id=job.target_player_id,
            video_url=job.video_url,
            thumbnail_url=job.thumbnail_url,
            analysis_type=job.analysis_type,
        )

        detector = self._build_detector(context.sport)

        with tempfile.TemporaryDirectory(prefix="nextscout-ai-") as tmp_dir:
            local_video = self._download_video(job.video_url, Path(tmp_dir))
            frames = list(self.extractor.extract(str(local_video)))
            frame_detections = [detector.detect(context, frame) for frame in frames]
            tracks = self.tracker.track(frame_detections)
            events = self.event_detector.detect(context, tracks)
            summary, targets, metrics = self.metric_aggregator.summarize(context, tracks, events)

        return VideoAnalysisResult(
            status="completed",
            analysis_version="vision-pipeline-v1",
            summary=summary,
            raw_output={
                "engine": "vision-pipeline",
                "sport": context.sport,
                "detector": "yolo" if isinstance(detector, YoloDetector) else "heuristic",
                "detector_mode": settings.ai_worker_detector,
                "detector_model_path": getattr(detector, "model_path", None),
                "sampled_frames": len(frames),
                "track_count": len(tracks),
            },
            targets=targets,
            events=events,
            metrics=metrics,
        )

    def _download_video(self, video_url: str, tmp_dir: Path) -> Path:
        target = tmp_dir / "video.mp4"
        with httpx.stream("GET", video_url, timeout=settings.ai_worker_download_timeout_seconds) as response:
            response.raise_for_status()
            with target.open("wb") as file_handle:
                for chunk in response.iter_bytes():
                    file_handle.write(chunk)
        return target
