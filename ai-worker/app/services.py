import asyncio
import logging
import threading

import httpx

from app.analyzers.mock import run_mock_analysis
from app.analyzers.pipeline import PipelineAnalyzer
from app.config import settings
from app.schemas import VideoAnalysisJobRequest, VideoAnalysisResult


logger = logging.getLogger("ai-worker")
pipeline_analyzer = PipelineAnalyzer()


async def process_video_analysis(job: VideoAnalysisJobRequest) -> None:
    try:
        if settings.ai_worker_mode == "mock":
            result = run_mock_analysis(job)
        elif settings.ai_worker_mode == "pipeline":
            result = pipeline_analyzer.run(job)
        else:
            result = run_mock_analysis(job)

        await send_callback(job, result)
    except Exception as exc:  # pragma: no cover
        logger.exception("analysis job failed", extra={"analysis_id": job.analysis_id})
        failed = VideoAnalysisResult(
            status="failed",
            analysis_version="external-worker-failed",
            summary={},
            raw_output={"engine": "external-ai-worker", "stage": "process"},
            failure_reason=str(exc),
        )
        await send_callback(job, failed)


async def send_callback(job: VideoAnalysisJobRequest, result: VideoAnalysisResult) -> None:
    timeout = settings.ai_worker_callback_timeout_seconds
    headers = {
        "Accept": "application/json",
        "Content-Type": "application/json",
        "X-Analysis-Callback-Secret": job.callback_secret,
    }
    async with httpx.AsyncClient(timeout=timeout) as client:
        response = await client.post(
            str(job.callback_url),
            headers=headers,
            json=result.model_dump(mode="json"),
        )
        response.raise_for_status()


def enqueue_video_analysis(job: VideoAnalysisJobRequest) -> None:
    threading.Thread(
        target=lambda: asyncio.run(process_video_analysis(job)),
        daemon=True,
        name=f"video-analysis-{job.analysis_id}",
    ).start()
