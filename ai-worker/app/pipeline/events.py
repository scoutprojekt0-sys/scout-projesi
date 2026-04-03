from __future__ import annotations

from math import hypot

from app.pipeline.types import AnalysisContext, TrackSnapshot
from app.schemas import AnalysisClip, AnalysisEvent


SPORT_EVENT_RULES = {
    "football": ("pass", "cross", "dribble"),
    "basketball": ("assist", "drive", "shot"),
    "volleyball": ("serve", "spike", "block"),
}


class EventDetector:
    def detect(self, context: AnalysisContext, tracks: list[TrackSnapshot]) -> list[AnalysisEvent]:
        player_tracks = [track for track in tracks if track.label == "player"]
        ball_tracks = [track for track in tracks if track.label == "ball"]
        if not player_tracks:
            return []

        video_url = context.video_url or f"https://example.com/video/{context.video_clip_id}"
        ordered_players = sorted(player_tracks, key=lambda item: (item.second, item.track_id))
        primary_track_id = self._primary_track_id(ordered_players)
        primary_player = [item for item in ordered_players if item.track_id == primary_track_id]
        if len(primary_player) < 2:
            primary_player = ordered_players

        movement = self._path_length(primary_player)
        ball_control = self._ball_control_ratio(primary_player, ball_tracks)
        start_second = primary_player[0].second
        end_second = primary_player[-1].second

        sport_events = SPORT_EVENT_RULES.get(context.sport, SPORT_EVENT_RULES["football"])
        confidence_seed = min(96.0, 72.0 + movement / 18.0)

        return [
            self._build_event(
                context=context,
                video_url=video_url,
                event_type=sport_events[0],
                start_second=start_second,
                end_second=min(end_second, start_second + 4),
                confidence=confidence_seed,
                payload=self._payload(context.sport, sport_events[0], movement, ball_control),
            ),
            self._build_event(
                context=context,
                video_url=video_url,
                event_type=sport_events[1],
                start_second=start_second + max(2, int(len(primary_player) / 3)),
                end_second=min(end_second, start_second + max(6, int(len(primary_player) / 2))),
                confidence=max(70.0, confidence_seed - 4.0),
                payload=self._payload(context.sport, sport_events[1], movement, ball_control),
            ),
            self._build_event(
                context=context,
                video_url=video_url,
                event_type=sport_events[2],
                start_second=max(start_second + 4, end_second - 3),
                end_second=end_second,
                confidence=max(68.0, confidence_seed - 7.0),
                payload=self._payload(context.sport, sport_events[2], movement, ball_control),
            ),
        ]

    def _build_event(
        self,
        *,
        context: AnalysisContext,
        video_url: str,
        event_type: str,
        start_second: int,
        end_second: int,
        confidence: float,
        payload: dict,
    ) -> AnalysisEvent:
        return AnalysisEvent(
            target_player_id=context.target_player_id,
            event_type=event_type,
            start_second=start_second,
            end_second=max(start_second + 1, end_second),
            confidence=round(confidence, 1),
            payload=payload,
            clips=[
                AnalysisClip(
                    clip_url=f"{video_url}#t={start_second},{max(start_second + 1, end_second)}",
                    thumbnail_url=context.thumbnail_url,
                    clip_start_second=start_second,
                    clip_end_second=max(start_second + 1, end_second),
                    metadata={
                        "generated_by": "pipeline-event-detector",
                        "event_type": event_type,
                        "sport": context.sport,
                    },
                )
            ],
        )

    def _primary_track_id(self, tracks: list[TrackSnapshot]) -> int:
        counts: dict[int, int] = {}
        for item in tracks:
            counts[item.track_id] = counts.get(item.track_id, 0) + 1
        return max(counts, key=counts.get)

    def _ball_control_ratio(self, players: list[TrackSnapshot], balls: list[TrackSnapshot]) -> float:
        if not players or not balls:
            return 0.0
        grouped_balls: dict[int, list[TrackSnapshot]] = {}
        for item in balls:
            grouped_balls.setdefault(item.second, []).append(item)

        controlled = 0
        total = 0
        for player in players:
            second_balls = grouped_balls.get(player.second, [])
            if not second_balls:
                continue
            total += 1
            nearest = min(second_balls, key=lambda ball: self._distance(player, ball))
            if self._distance(player, nearest) <= 90:
                controlled += 1
        return controlled / total if total else 0.0

    def _path_length(self, tracks: list[TrackSnapshot]) -> float:
        ordered = sorted(tracks, key=lambda item: item.second)
        return sum(self._distance(prev, curr) for prev, curr in zip(ordered, ordered[1:]))

    def _distance(self, left: TrackSnapshot, right: TrackSnapshot) -> float:
        lx = (left.bbox[0] + left.bbox[2]) / 2.0
        ly = (left.bbox[1] + left.bbox[3]) / 2.0
        rx = (right.bbox[0] + right.bbox[2]) / 2.0
        ry = (right.bbox[1] + right.bbox[3]) / 2.0
        return hypot(rx - lx, ry - ly)

    def _payload(self, sport: str, event_type: str, movement: float, ball_control: float) -> dict:
        if sport == "basketball":
            return {
                "successful": True,
                "ball_control_ratio": round(ball_control, 2),
                "lane_pressure": "high" if movement > 180 else "medium",
                "event_family": event_type,
            }
        if sport == "volleyball":
            return {
                "successful": True,
                "ball_control_ratio": round(ball_control, 2),
                "timing_score": min(95, int(64 + movement / 12)),
                "event_family": event_type,
            }
        return {
            "successful": True,
            "ball_control_ratio": round(ball_control, 2),
            "distance_m": round(movement / 18, 1),
            "event_family": event_type,
        }
