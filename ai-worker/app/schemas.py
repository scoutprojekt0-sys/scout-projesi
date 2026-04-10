from typing import Any

from pydantic import BaseModel, Field, HttpUrl


class VideoAnalysisJobRequest(BaseModel):
    analysis_id: int
    video_clip_id: int
    sport: str = "football"
    video_url: str | None = None
    thumbnail_url: str | None = None
    target_player_id: int | None = None
    requested_by: int
    analysis_type: str = "scout_mvp"
    target_profile: dict[str, Any] | None = None
    callback_url: HttpUrl
    callback_secret: str


class AnalysisClip(BaseModel):
    clip_url: str
    thumbnail_url: str | None = None
    clip_start_second: int | None = None
    clip_end_second: int | None = None
    metadata: dict[str, Any] | None = None


class AnalysisEvent(BaseModel):
    target_player_id: int | None = None
    event_type: str
    start_second: int
    end_second: int
    confidence: float = Field(default=0)
    payload: dict[str, Any] | None = None
    clips: list[AnalysisClip] = Field(default_factory=list)


class AnalysisMetric(BaseModel):
    player_id: int | None = None
    passes: int = 0
    successful_passes: int = 0
    cross_attempts: int = 0
    successful_crosses: int = 0
    shots: int = 0
    dribbles: int = 0
    ball_recoveries: int = 0
    movement_score: int = 0
    speed_score: int = 0
    cross_quality_score: int = 0
    assist_vision_score: int = 0
    drive_efficiency_score: int = 0
    spike_quality_score: int = 0
    block_timing_score: int = 0
    metadata: dict[str, Any] | None = None


class AnalysisTarget(BaseModel):
    player_id: int | None = None
    label: str | None = None
    jersey_number: str | None = None
    reference_data: dict[str, Any] | None = None


class VideoAnalysisResult(BaseModel):
    status: str = "completed"
    analysis_version: str = "external-mock-v1"
    summary: dict[str, Any]
    raw_output: dict[str, Any] | None = None
    targets: list[AnalysisTarget] = Field(default_factory=list)
    events: list[AnalysisEvent] = Field(default_factory=list)
    metrics: list[AnalysisMetric] = Field(default_factory=list)
    failure_reason: str | None = None
