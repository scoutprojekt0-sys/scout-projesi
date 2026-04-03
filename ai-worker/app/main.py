import logging

from fastapi import FastAPI

from app.config import settings
from app.model_registry import available_models
from app.schemas import VideoAnalysisJobRequest
from app.services import enqueue_video_analysis
from app.sports import SUPPORTED_SPORTS


logging.basicConfig(level=getattr(logging, settings.ai_worker_log_level.upper(), logging.INFO))

app = FastAPI(title="NextScout AI Worker", version="1.0.0")


@app.get("/health")
async def health() -> dict:
    return {
        "ok": "true",
        "mode": settings.ai_worker_mode,
        "detector": settings.ai_worker_detector,
        "sports": ",".join(sorted(SUPPORTED_SPORTS)),
        "models": available_models(settings.ai_worker_yolo_model_path),
    }


@app.post("/jobs/video-analysis")
async def create_video_analysis_job(payload: VideoAnalysisJobRequest) -> dict:
    enqueue_video_analysis(payload)
    return {
        "ok": True,
        "job_id": f"analysis-{payload.analysis_id}",
        "status": "submitted",
        "analysis_version": "vision-pipeline-v1" if settings.ai_worker_mode == "pipeline" else "external-mock-v1",
    }
