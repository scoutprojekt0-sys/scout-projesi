from app.schemas import (
    AnalysisClip,
    AnalysisEvent,
    AnalysisMetric,
    AnalysisTarget,
    VideoAnalysisJobRequest,
    VideoAnalysisResult,
)
from app.sports import normalize_sport


def run_mock_analysis(job: VideoAnalysisJobRequest) -> VideoAnalysisResult:
    sport = normalize_sport(job.sport)
    summary_map = {
        "football": {
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
        },
        "basketball": {
            "passes": 22,
            "successful_passes": 19,
            "cross_attempts": 0,
            "successful_crosses": 0,
            "shots": 9,
            "dribbles": 11,
            "ball_recoveries": 4,
            "movement_score": 85,
            "speed_score": 82,
            "cross_quality_score": 0,
            "assist_vision_score": 88,
            "drive_efficiency_score": 84,
        },
        "volleyball": {
            "passes": 0,
            "successful_passes": 0,
            "cross_attempts": 0,
            "successful_crosses": 0,
            "shots": 6,
            "dribbles": 0,
            "ball_recoveries": 5,
            "movement_score": 87,
            "speed_score": 73,
            "cross_quality_score": 0,
            "spike_quality_score": 89,
            "block_timing_score": 83,
        },
    }
    summary = summary_map.get(sport, summary_map["football"])

    event_map = {
        "football": [
            ("pass", 12, 16, 90.4, {"successful": True, "distance_m": 17}),
            ("cross", 41, 46, 88.1, {"successful": True, "target_zone": "back_post"}),
            ("dribble", 69, 73, 81.2, {"successful": True, "opponents_beaten": 2}),
        ],
        "basketball": [
            ("assist", 8, 11, 89.0, {"successful": True, "target_zone": "paint"}),
            ("drive", 24, 27, 84.5, {"successful": True, "direction": "left_lane"}),
            ("shot", 39, 41, 80.2, {"made": True, "shot_type": "catch_and_shoot"}),
        ],
        "volleyball": [
            ("serve", 5, 8, 91.4, {"successful": True, "serve_type": "jump"}),
            ("spike", 17, 19, 87.6, {"successful": True, "target_zone": "line"}),
            ("block", 28, 30, 79.3, {"successful": True, "hands": "double"}),
        ],
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
            "sport": sport,
        },
        targets=[
            AnalysisTarget(
                player_id=job.target_player_id,
                label=f"Player {job.target_player_id}" if job.target_player_id else "Target Player",
                jersey_number="11",
                reference_data={
                    "selection_mode": "linked_player" if job.target_player_id else "manual",
                    "sport": sport,
                },
            )
        ],
        events=[
            AnalysisEvent(
                target_player_id=job.target_player_id,
                event_type=event_type,
                start_second=start,
                end_second=end,
                confidence=confidence,
                payload=payload,
                clips=[clip(start, end, event_type)],
            )
            for event_type, start, end, confidence, payload in event_map.get(sport, event_map["football"])
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
                assist_vision_score=summary.get("assist_vision_score", 0),
                drive_efficiency_score=summary.get("drive_efficiency_score", 0),
                spike_quality_score=summary.get("spike_quality_score", 0),
                block_timing_score=summary.get("block_timing_score", 0),
                metadata={"analysis_version": "external-mock-v1", "sport": sport},
            )
        ],
    )
