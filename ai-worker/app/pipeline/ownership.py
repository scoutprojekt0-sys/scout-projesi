from __future__ import annotations

from dataclasses import dataclass
from math import hypot

from app.pipeline.types import AnalysisContext, TrackSnapshot


@dataclass(slots=True)
class PossessionMoment:
    second: int
    holder_track_id: int | None
    distance: float | None
    ball: TrackSnapshot | None
    inferred: bool = False


class BallOwnershipChain:
    def infer(self, context: AnalysisContext, tracks: list[TrackSnapshot]) -> list[PossessionMoment]:
        player_tracks = [track for track in tracks if track.label == "player"]
        ball_tracks = [track for track in tracks if track.label == "ball"]
        if not player_tracks:
            return []

        grouped_players = self._group_by_second(player_tracks)
        grouped_balls = self._group_by_second(ball_tracks)
        seconds = sorted(set(grouped_players) | set(grouped_balls))
        if not seconds:
            return []

        moments = [
            self._moment_for_second(context, second, grouped_players, grouped_balls)
            for second in seconds
        ]
        self._fill_ball_gaps(moments)
        self._fill_soft_holders(context, moments, grouped_players)
        self._fill_holder_gaps(moments)
        self._stabilize_holder_runs(moments, grouped_players)
        context.ownership_chain = [
            {
                "second": moment.second,
                "holder_track_id": moment.holder_track_id,
                "distance": round(moment.distance, 1) if moment.distance is not None else None,
                "inferred": moment.inferred,
            }
            for moment in moments
        ]
        return moments

    def _moment_for_second(
        self,
        context: AnalysisContext,
        second: int,
        grouped_players: dict[int, list[TrackSnapshot]],
        grouped_balls: dict[int, list[TrackSnapshot]],
    ) -> PossessionMoment:
        players = grouped_players.get(second, [])
        balls = grouped_balls.get(second, [])
        ball = balls[0] if balls else None
        if ball is None or not players:
            return PossessionMoment(second=second, holder_track_id=None, distance=None, ball=ball)

        nearest = min(players, key=lambda player: self._distance(player, ball))
        distance = self._distance(nearest, ball)
        holder = nearest.track_id if self._holder_distance_threshold(context, nearest.track_id) >= distance else None
        return PossessionMoment(second=second, holder_track_id=holder, distance=distance, ball=ball)

    def _fill_ball_gaps(self, moments: list[PossessionMoment], max_gap_seconds: int = 2) -> None:
        if len(moments) < 3:
            return
        for index, moment in enumerate(moments):
            if moment.ball is not None:
                continue
            previous = self._previous_ball(moments, index)
            following = self._next_ball(moments, index)
            if previous is None or following is None:
                continue
            if previous.second == following.second:
                continue
            if (following.second - previous.second) > (max_gap_seconds + 1):
                continue

            ratio = (moment.second - previous.second) / max(1, following.second - previous.second)
            moment.ball = TrackSnapshot(
                track_id=-1,
                label="ball",
                second=moment.second,
                bbox=self._interpolate_bbox(previous.ball.bbox, following.ball.bbox, ratio),
                confidence=min(previous.ball.confidence, following.ball.confidence),
            )
            moment.inferred = True

    def _fill_holder_gaps(self, moments: list[PossessionMoment], max_gap_seconds: int = 2) -> None:
        if len(moments) < 3:
            return
        for index, moment in enumerate(moments):
            if moment.holder_track_id is not None:
                continue
            previous = self._previous_holder(moments, index)
            following = self._next_holder(moments, index)
            if previous is None or following is None:
                continue
            if previous.holder_track_id != following.holder_track_id:
                continue
            if (following.second - previous.second) > (max_gap_seconds + 1):
                continue

            moment.holder_track_id = previous.holder_track_id
            if previous.distance is not None and following.distance is not None:
                moment.distance = min(previous.distance, following.distance)
            else:
                moment.distance = previous.distance or following.distance
            moment.inferred = True

    def _fill_soft_holders(
        self,
        context: AnalysisContext,
        moments: list[PossessionMoment],
        grouped_players: dict[int, list[TrackSnapshot]],
    ) -> None:
        for index, moment in enumerate(moments):
            if moment.ball is None or moment.holder_track_id is not None:
                continue
            players = grouped_players.get(moment.second, [])
            if not players:
                continue

            nearest = min(players, key=lambda player: self._distance(player, moment.ball))
            distance = self._distance(nearest, moment.ball)
            threshold = self._holder_distance_threshold(context, nearest.track_id) + 25.0
            if distance > threshold:
                continue

            previous_holder = self._previous_holder(moments, index)
            next_holder = self._next_holder(moments, index)
            if previous_holder and previous_holder.holder_track_id == nearest.track_id:
                moment.holder_track_id = nearest.track_id
                moment.distance = distance
                moment.inferred = True
                continue
            if next_holder and next_holder.holder_track_id == nearest.track_id:
                moment.holder_track_id = nearest.track_id
                moment.distance = distance
                moment.inferred = True
                continue
            if context.target_track_id is not None and nearest.track_id == context.target_track_id:
                moment.holder_track_id = nearest.track_id
                moment.distance = distance
                moment.inferred = True

    def _stabilize_holder_runs(
        self,
        moments: list[PossessionMoment],
        grouped_players: dict[int, list[TrackSnapshot]],
    ) -> None:
        for index, moment in enumerate(moments):
            if moment.holder_track_id is not None:
                continue
            previous_holder = self._previous_holder(moments, index)
            if previous_holder is None:
                continue
            if (moment.second - previous_holder.second) > 2:
                continue

            players = grouped_players.get(moment.second, [])
            if not any(player.track_id == previous_holder.holder_track_id for player in players):
                continue

            moment.holder_track_id = previous_holder.holder_track_id
            moment.distance = previous_holder.distance
            moment.inferred = True

    def _previous_ball(self, moments: list[PossessionMoment], index: int) -> PossessionMoment | None:
        for candidate in reversed(moments[:index]):
            if candidate.ball is not None:
                return candidate
        return None

    def _next_ball(self, moments: list[PossessionMoment], index: int) -> PossessionMoment | None:
        for candidate in moments[index + 1 :]:
            if candidate.ball is not None:
                return candidate
        return None

    def _previous_holder(self, moments: list[PossessionMoment], index: int) -> PossessionMoment | None:
        for candidate in reversed(moments[:index]):
            if candidate.holder_track_id is not None:
                return candidate
        return None

    def _next_holder(self, moments: list[PossessionMoment], index: int) -> PossessionMoment | None:
        for candidate in moments[index + 1 :]:
            if candidate.holder_track_id is not None:
                return candidate
        return None

    def _group_by_second(self, tracks: list[TrackSnapshot]) -> dict[int, list[TrackSnapshot]]:
        grouped: dict[int, list[TrackSnapshot]] = {}
        for track in tracks:
            grouped.setdefault(track.second, []).append(track)
        return grouped

    def _interpolate_bbox(
        self,
        left: tuple[float, float, float, float],
        right: tuple[float, float, float, float],
        ratio: float,
    ) -> tuple[float, float, float, float]:
        return tuple(
            left_value + ((right_value - left_value) * ratio)
            for left_value, right_value in zip(left, right)
        )

    def _distance(self, left: TrackSnapshot, right: TrackSnapshot) -> float:
        lx = (left.bbox[0] + left.bbox[2]) / 2.0
        ly = (left.bbox[1] + left.bbox[3]) / 2.0
        rx = (right.bbox[0] + right.bbox[2]) / 2.0
        ry = (right.bbox[1] + right.bbox[3]) / 2.0
        return hypot(rx - lx, ry - ly)

    def _holder_distance_threshold(self, context: AnalysisContext, track_id: int | None) -> float:
        base = 130.0
        if context.target_track_id is not None and track_id == context.target_track_id:
            return 150.0
        return base
