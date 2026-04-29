from __future__ import annotations

import logging
import tempfile
from pathlib import Path

import httpx

from app.config import settings
from app.model_registry import has_model_for_sport
from app.pipeline.calibration import FieldCalibrator
from app.pipeline.detectors import HeuristicDetector, YoloDetector
from app.pipeline.events import EventDetector
from app.pipeline.extractor import FrameExtractor
from app.pipeline.metrics import MetricAggregator
from app.pipeline.ownership import BallOwnershipChain
from app.pipeline.targeting import TargetLocker
from app.pipeline.teaming import TeamSeparator
from app.pipeline.tracking import SimpleTracker
from app.pipeline.types import AnalysisContext
from app.schemas import VideoAnalysisJobRequest, VideoAnalysisResult
from app.sports import normalize_sport


logger = logging.getLogger("ai-worker")


class PipelineAnalyzer:
    def __init__(self) -> None:
        self.extractor = FrameExtractor(
            sample_every_seconds=settings.ai_worker_sample_every_seconds,
            max_seconds=settings.ai_worker_max_sample_seconds,
        )
        self.calibrator = FieldCalibrator()
        self.tracker = SimpleTracker()
        self.target_locker = TargetLocker()
        self.team_separator = TeamSeparator()
        self.ownership_chain = BallOwnershipChain()
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
            target_profile=job.target_profile,
        )

        detector = self._build_detector(context.sport)

        with tempfile.TemporaryDirectory(prefix="nextscout-ai-") as tmp_dir:
            local_video = self._download_video(job.video_url, Path(tmp_dir))
            frames = list(self.extractor.extract(str(local_video)))
            calibration = self.calibrator.estimate(context.sport, frames)
            if calibration is not None:
                context.meters_per_pixel = calibration.px_to_meters(1.0)
                context.calibration_confidence = calibration.confidence
            frame_detections = [detector.detect(context, frame) for frame in frames]
            tracks = self.tracker.track(frame_detections)
            player_tracks = [track for track in tracks if track.label == "player"]
            ball_tracks = [track for track in tracks if track.label == "ball"]
            if len(frames) < 2 or len(player_tracks) < 2:
                raise RuntimeError("Analiz icin yeterli oyuncu takibi cikmadi.")
            context.target_track_id = self.target_locker.choose_track(context, tracks)
            context.track_team_map = self.team_separator.assign(frames, tracks)
            if context.target_track_id is not None:
                context.target_team_id = context.track_team_map.get(context.target_track_id)
            possessions = self.ownership_chain.infer(context, tracks)
            events = self.event_detector.detect(context, tracks)
            summary, targets, metrics = self.metric_aggregator.summarize(context, tracks, events)
            if context.target_track_id is None or (not events and len(ball_tracks) == 0):
                raise RuntimeError("Analiz anlamli event veya top takibi uretemedi.")

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
                "target_track_id": context.target_track_id,
                "team_count": len(set(context.track_team_map.values())) if context.track_team_map else 0,
                "target_team_id": context.target_team_id,
                "ownership_moments": len(possessions),
                "resolved_ownerships": sum(1 for item in possessions if item.holder_track_id is not None),
                "meters_per_pixel": context.meters_per_pixel,
                "calibration_confidence": context.calibration_confidence,
                "field_bbox": calibration.field_bbox if calibration is not None else None,
            },
            targets=targets,
            events=events,
            metrics=metrics,
        )

    def _download_video(self, video_url: str, tmp_dir: Path) -> Path:
        target = tmp_dir / "video.mp4"
        logger.info("downloading video for analysis", extra={"video_url": video_url})
        with httpx.stream(
            "GET",
            video_url,
            timeout=settings.ai_worker_download_timeout_seconds,
            follow_redirects=True,
        ) as response:
            response.raise_for_status()
            with target.open("wb") as file_handle:
                for chunk in response.iter_bytes():
                    file_handle.write(chunk)
        return target
