from app.schemas import (
    AnalysisClip,
    AnalysisEvent,
    AnalysisMetric,
    AnalysisTarget,
    VideoAnalysisJobRequest,
    VideoAnalysisResult,
)


def run_mock_analysis(job: VideoAnalysisJobRequest) -> VideoAnalysisResult:
    summary = {
        "passes": 18,
        "successful_passes": 14,
        "cross_attempts": 5,
        "successful_crosses": 3,
        "shots": 2,
        "dribbles": 4,
        "ball_recoveries": 2,
        "movement_score": 83,
        "speed_score": 79,
        "cross_quality_score": 86,
    }

    def clip(start: int, end: int, event_type: str) -> AnalysisClip:
        video_url = job.video_url or f"https://example.com/video/{job.video_clip_id}"
        return AnalysisClip(
            clip_url=f"{video_url}#t={start},{end}",
            thumbnail_url=job.thumbnail_url,
            clip_start_second=start,
            clip_end_second=end,
            metadata={"event_type": event_type, "generated_by": "ai-worker-mock"},
        )

    return VideoAnalysisResult(
        status="completed",
        analysis_version="external-mock-v1",
        summary=summary,
        raw_output={
            "engine": "external-ai-worker-mock",
            "analysis_id": job.analysis_id,
            "video_clip_id": job.video_clip_id,
        },
        targets=[
            AnalysisTarget(
                player_id=job.target_player_id,
                label=f"Player {job.target_player_id}" if job.target_player_id else "Target Player",
                jersey_number="11",
                reference_data={"selection_mode": "linked_player" if job.target_player_id else "manual"},
            )
        ],
        events=[
            AnalysisEvent(
                target_player_id=job.target_player_id,
                event_type="pass",
                start_second=12,
                end_second=16,
                confidence=90.4,
                payload={"successful": True, "distance_m": 17},
                clips=[clip(12, 16, "pass")],
            ),
            AnalysisEvent(
                target_player_id=job.target_player_id,
                event_type="cross",
                start_second=41,
                end_second=46,
                confidence=88.1,
                payload={"successful": True, "target_zone": "back_post"},
                clips=[clip(41, 46, "cross")],
            ),
            AnalysisEvent(
                target_player_id=job.target_player_id,
                event_type="dribble",
                start_second=69,
                end_second=73,
                confidence=81.2,
                payload={"successful": True, "opponents_beaten": 2},
                clips=[clip(69, 73, "dribble")],
            ),
        ],
        metrics=[
            AnalysisMetric(
                player_id=job.target_player_id,
                passes=summary["passes"],
                successful_passes=summary["successful_passes"],
                cross_attempts=summary["cross_attempts"],
                successful_crosses=summary["successful_crosses"],
                shots=summary["shots"],
                dribbles=summary["dribbles"],
                ball_recoveries=summary["ball_recoveries"],
                movement_score=summary["movement_score"],
                speed_score=summary["speed_score"],
                cross_quality_score=summary["cross_quality_score"],
                metadata={"analysis_version": "external-mock-v1"},
            )
        ],
    )
