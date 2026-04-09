from __future__ import annotations

from dataclasses import dataclass
from math import hypot

from app.pipeline.ownership import BallOwnershipChain, PossessionMoment
from app.pipeline.types import AnalysisContext, TrackSnapshot
from app.schemas import AnalysisClip, AnalysisEvent


SPORT_EVENT_RULES = {
    "football": ("pass", "cross", "dribble", "shot", "ball_recovery"),
    "basketball": ("assist", "drive", "shot"),
    "volleyball": ("serve", "spike", "block"),
}


class EventDetector:
    def __init__(self) -> None:
        self.ownership_chain = BallOwnershipChain()

    def detect(self, context: AnalysisContext, tracks: list[TrackSnapshot]) -> list[AnalysisEvent]:
        player_tracks = [track for track in tracks if track.label == "player"]
        if not player_tracks:
            return []

        if context.sport == "football":
            return self._detect_football(context, tracks)
        if context.sport == "basketball":
            return self._detect_basketball(context, tracks)
        if context.sport == "volleyball":
            return self._detect_volleyball(context, tracks)

        return self._detect_generic(context, tracks)

    def _detect_football(self, context: AnalysisContext, tracks: list[TrackSnapshot]) -> list[AnalysisEvent]:
        video_url = context.video_url or f"https://example.com/video/{context.video_clip_id}"
        player_tracks = [track for track in tracks if track.label == "player"]
        ball_tracks = [track for track in tracks if track.label == "ball"]
        primary_track_id = context.target_track_id or self._primary_track_id(player_tracks)
        primary_player = self._track_group(player_tracks).get(primary_track_id, [])
        width_hint = max((track.bbox[2] for track in player_tracks), default=0.0)
        possessions = self.ownership_chain.infer(context, tracks)
        events: list[AnalysisEvent] = []

        events.extend(self._detect_recoveries(context, video_url, primary_track_id, possessions))
        events.extend(self._detect_passes_and_crosses(context, video_url, primary_track_id, possessions, width_hint))
        events.extend(self._detect_dribbles(context, video_url, primary_track_id, primary_player, possessions))
        events.extend(self._detect_shots(context, video_url, primary_track_id, primary_player, possessions))
        if not events:
            events.extend(self._detect_fallback_events(context, video_url, primary_track_id, primary_player, ball_tracks))

        deduped: list[AnalysisEvent] = []
        seen: set[tuple[str, int, int]] = set()
        for event in sorted(events, key=lambda item: (item.start_second, item.event_type)):
            key = (event.event_type, event.start_second, event.end_second)
            if key in seen:
                continue
            seen.add(key)
            deduped.append(event)

        return deduped

    def _detect_basketball(self, context: AnalysisContext, tracks: list[TrackSnapshot]) -> list[AnalysisEvent]:
        video_url = context.video_url or f"https://example.com/video/{context.video_clip_id}"
        player_tracks = [track for track in tracks if track.label == "player"]
        ball_tracks = [track for track in tracks if track.label == "ball"]
        primary_track_id = context.target_track_id or self._primary_track_id(player_tracks)
        primary_player = self._track_group(player_tracks).get(primary_track_id, [])
        possessions = self.ownership_chain.infer(context, tracks)
        events: list[AnalysisEvent] = []

        events.extend(self._detect_assists(context, video_url, primary_track_id, possessions))
        events.extend(self._detect_drives(context, video_url, primary_track_id, primary_player, possessions))
        events.extend(self._detect_shots(context, video_url, primary_track_id, primary_player, possessions))
        if not events:
            events.extend(self._detect_fallback_events(context, video_url, primary_track_id, primary_player, ball_tracks))
        return self._dedupe_events(events)

    def _detect_volleyball(self, context: AnalysisContext, tracks: list[TrackSnapshot]) -> list[AnalysisEvent]:
        video_url = context.video_url or f"https://example.com/video/{context.video_clip_id}"
        player_tracks = [track for track in tracks if track.label == "player"]
        ball_tracks = [track for track in tracks if track.label == "ball"]
        primary_track_id = context.target_track_id or self._primary_track_id(player_tracks)
        primary_player = self._track_group(player_tracks).get(primary_track_id, [])
        net_tracks = [track for track in tracks if track.label == "net"]
        possessions = self.ownership_chain.infer(context, tracks)
        events: list[AnalysisEvent] = []

        events.extend(self._detect_serves(context, video_url, primary_track_id, possessions))
        events.extend(self._detect_spikes(context, video_url, primary_track_id, primary_player, possessions))
        events.extend(self._detect_blocks(context, video_url, primary_track_id, net_tracks, player_tracks, ball_tracks))
        if not events:
            events.extend(self._detect_fallback_events(context, video_url, primary_track_id, primary_player, ball_tracks))
        return self._dedupe_events(events)

    def _detect_generic(self, context: AnalysisContext, tracks: list[TrackSnapshot]) -> list[AnalysisEvent]:
        player_tracks = [track for track in tracks if track.label == "player"]
        ball_tracks = [track for track in tracks if track.label == "ball"]
        video_url = context.video_url or f"https://example.com/video/{context.video_clip_id}"
        ordered_players = sorted(player_tracks, key=lambda item: (item.second, item.track_id))
        primary_track_id = context.target_track_id or self._primary_track_id(ordered_players)
        primary_player = [item for item in ordered_players if item.track_id == primary_track_id] or ordered_players
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
                payload=self._payload(context, sport_events[0], movement, ball_control, {"source_track_id": primary_track_id}),
            ),
            self._build_event(
                context=context,
                video_url=video_url,
                event_type=sport_events[1],
                start_second=start_second + max(2, int(len(primary_player) / 3)),
                end_second=min(end_second, start_second + max(6, int(len(primary_player) / 2))),
                confidence=max(70.0, confidence_seed - 4.0),
                payload=self._payload(context, sport_events[1], movement, ball_control, {"source_track_id": primary_track_id}),
            ),
            self._build_event(
                context=context,
                video_url=video_url,
                event_type=sport_events[2],
                start_second=max(start_second + 4, end_second - 3),
                end_second=end_second,
                confidence=max(68.0, confidence_seed - 7.0),
                payload=self._payload(context, sport_events[2], movement, ball_control, {"source_track_id": primary_track_id}),
            ),
        ]

    def _detect_assists(
        self,
        context: AnalysisContext,
        video_url: str,
        primary_track_id: int,
        possessions: list[PossessionMoment],
    ) -> list[AnalysisEvent]:
        events: list[AnalysisEvent] = []
        for prev, curr in zip(possessions, possessions[1:]):
            if prev.holder_track_id != primary_track_id:
                continue
            if curr.holder_track_id in {None, primary_track_id}:
                continue
            if not self._is_same_team(context, primary_track_id, curr.holder_track_id):
                continue
            if prev.ball is None or curr.ball is None:
                continue
            travel = self._distance(prev.ball, curr.ball)
            if travel < 35:
                continue
            events.append(
                self._build_event(
                    context=context,
                    video_url=video_url,
                    event_type="assist",
                    start_second=prev.second,
                    end_second=max(prev.second + 1, curr.second),
                    confidence=min(94.0, 72.0 + travel / 7.0),
                    payload={
                        "successful": True,
                        "source_track_id": primary_track_id,
                        "target_track_id": curr.holder_track_id,
                        "ball_travel_px": round(travel, 1),
                    },
                )
            )
        return events

    def _detect_drives(
        self,
        context: AnalysisContext,
        video_url: str,
        primary_track_id: int,
        primary_player: list[TrackSnapshot],
        possessions: list[PossessionMoment],
    ) -> list[AnalysisEvent]:
        events: list[AnalysisEvent] = []
        grouped_primary = {track.second: track for track in primary_player}
        segment_start: int | None = None
        for moment in possessions + [PossessionMoment(second=-1, holder_track_id=None, distance=None, ball=None)]:
            if moment.holder_track_id == primary_track_id:
                if segment_start is None:
                    segment_start = moment.second
                continue
            if segment_start is None:
                continue
            segment_end = moment.second - 1
            segment_tracks = [
                grouped_primary[second]
                for second in range(segment_start, segment_end + 1)
                if second in grouped_primary
            ]
            if len(segment_tracks) >= 2:
                movement = self._path_length(segment_tracks)
                if movement >= 28:
                    events.append(
                        self._build_event(
                            context=context,
                            video_url=video_url,
                            event_type="drive",
                            start_second=segment_start,
                            end_second=max(segment_start + 1, segment_end),
                            confidence=min(92.0, 70.0 + movement / 5.0),
                            payload={
                                "successful": True,
                                "source_track_id": primary_track_id,
                                "movement_px": round(movement, 1),
                            },
                        )
                    )
            segment_start = None
        return events

    def _detect_serves(
        self,
        context: AnalysisContext,
        video_url: str,
        primary_track_id: int,
        possessions: list[PossessionMoment],
    ) -> list[AnalysisEvent]:
        for prev, curr in zip(possessions, possessions[1:]):
            if prev.holder_track_id != primary_track_id or prev.ball is None or curr.ball is None:
                continue
            travel = self._distance(prev.ball, curr.ball)
            if travel < 60:
                continue
            return [
                self._build_event(
                    context=context,
                    video_url=video_url,
                    event_type="serve",
                    start_second=prev.second,
                    end_second=max(prev.second + 1, curr.second),
                    confidence=min(93.0, 73.0 + travel / 8.0),
                    payload={
                        "successful": True,
                        "source_track_id": primary_track_id,
                        "ball_travel_px": round(travel, 1),
                    },
                )
            ]
        return []

    def _detect_spikes(
        self,
        context: AnalysisContext,
        video_url: str,
        primary_track_id: int,
        primary_player: list[TrackSnapshot],
        possessions: list[PossessionMoment],
    ) -> list[AnalysisEvent]:
        events: list[AnalysisEvent] = []
        grouped_primary = {track.second: track for track in primary_player}
        by_second = {moment.second: moment for moment in possessions}
        for second, moment in by_second.items():
            if moment.holder_track_id != primary_track_id or moment.ball is None:
                continue
            next_moment = by_second.get(second + 1)
            if next_moment is None or next_moment.ball is None:
                continue
            primary_track = grouped_primary.get(second)
            if primary_track is None:
                continue
            release_distance = self._distance(moment.ball, next_moment.ball)
            player_ball_gap = self._distance(primary_track, next_moment.ball)
            if release_distance < 70 or player_ball_gap < 90:
                continue
            events.append(
                self._build_event(
                    context=context,
                    video_url=video_url,
                    event_type="spike",
                    start_second=second,
                    end_second=second + 1,
                    confidence=min(95.0, 74.0 + release_distance / 7.0),
                    payload={
                        "successful": True,
                        "source_track_id": primary_track_id,
                        "ball_speed_px_per_sample": round(release_distance, 1),
                    },
                )
            )
            break
        return events

    def _detect_blocks(
        self,
        context: AnalysisContext,
        video_url: str,
        primary_track_id: int,
        net_tracks: list[TrackSnapshot],
        player_tracks: list[TrackSnapshot],
        ball_tracks: list[TrackSnapshot],
    ) -> list[AnalysisEvent]:
        if not net_tracks or not ball_tracks:
            return []
        grouped_players = self._group_by_second(player_tracks)
        grouped_balls = self._group_by_second(ball_tracks)
        grouped_nets = self._group_by_second(net_tracks)
        for second, balls in grouped_balls.items():
            players = grouped_players.get(second, [])
            nets = grouped_nets.get(second, [])
            if not players or not nets:
                continue
            primary_player = next((item for item in players if item.track_id == primary_track_id), None)
            if primary_player is None:
                continue
            net = nets[0]
            ball = balls[0]
            net_gap = self._distance(net, ball)
            player_gap = self._distance(primary_player, ball)
            if net_gap > 70 or player_gap > 110:
                continue
            return [
                self._build_event(
                    context=context,
                    video_url=video_url,
                    event_type="block",
                    start_second=second,
                    end_second=second + 1,
                    confidence=min(91.0, 72.0 + max(0.0, 100.0 - player_gap) / 6.0),
                    payload={
                        "successful": True,
                        "source_track_id": primary_track_id,
                        "ball_net_gap_px": round(net_gap, 1),
                    },
                )
            ]
        return []

    def _detect_recoveries(
        self,
        context: AnalysisContext,
        video_url: str,
        primary_track_id: int,
        possessions: list[_PossessionMoment],
    ) -> list[AnalysisEvent]:
        events: list[AnalysisEvent] = []
        for prev, curr in zip(possessions, possessions[1:]):
            if curr.holder_track_id != primary_track_id:
                continue
            if prev.holder_track_id in {None, primary_track_id}:
                continue
            if not self._is_opponent_switch(context, prev.holder_track_id, primary_track_id):
                continue
            confidence = min(94.0, 72.0 + (100.0 - (curr.distance or 100.0)) / 3.0)
            events.append(
                self._build_event(
                    context=context,
                    video_url=video_url,
                    event_type="ball_recovery",
                    start_second=curr.second,
                    end_second=curr.second + 1,
                    confidence=confidence,
                    payload={
                        "successful": True,
                        "source_track_id": prev.holder_track_id,
                        "target_track_id": primary_track_id,
                    },
                )
            )
        return events

    def _detect_passes_and_crosses(
        self,
        context: AnalysisContext,
        video_url: str,
        primary_track_id: int,
        possessions: list[_PossessionMoment],
        width_hint: float,
    ) -> list[AnalysisEvent]:
        events: list[AnalysisEvent] = []
        for prev, curr in zip(possessions, possessions[1:]):
            if prev.holder_track_id != primary_track_id:
                continue
            if curr.holder_track_id in {None, primary_track_id}:
                continue
            if not self._is_same_team(context, primary_track_id, curr.holder_track_id):
                continue
            if prev.ball is None or curr.ball is None:
                continue

            ball_travel = self._distance(prev.ball, curr.ball)
            if ball_travel < 25:
                continue

            start_second = prev.second
            end_second = max(curr.second, prev.second + 1)
            pass_confidence = min(96.0, 74.0 + min(ball_travel, 180.0) / 8.0)
            pass_payload = {
                "successful": True,
                "source_track_id": primary_track_id,
                "target_track_id": curr.holder_track_id,
                "ball_travel_px": round(ball_travel, 1),
            }
            events.append(
                self._build_event(
                    context=context,
                    video_url=video_url,
                    event_type="pass",
                    start_second=start_second,
                    end_second=end_second,
                    confidence=pass_confidence,
                    payload=pass_payload,
                )
            )

            if self._is_wide_play(prev.ball, width_hint) and ball_travel >= 80:
                events.append(
                    self._build_event(
                        context=context,
                        video_url=video_url,
                        event_type="cross",
                        start_second=start_second,
                        end_second=end_second,
                        confidence=max(70.0, pass_confidence - 3.5),
                        payload={
                            **pass_payload,
                            "target_zone": "box",
                        },
                    )
                )
        return events

    def _detect_dribbles(
        self,
        context: AnalysisContext,
        video_url: str,
        primary_track_id: int,
        primary_player: list[TrackSnapshot],
        possessions: list[_PossessionMoment],
    ) -> list[AnalysisEvent]:
        events: list[AnalysisEvent] = []
        grouped_primary = {track.second: track for track in primary_player}
        segment_start: int | None = None

        for moment in possessions + [PossessionMoment(second=-1, holder_track_id=None, distance=None, ball=None)]:
            if moment.holder_track_id == primary_track_id:
                if segment_start is None:
                    segment_start = moment.second
                continue

            if segment_start is None:
                continue

            segment_end = moment.second - 1
            segment_tracks = [
                grouped_primary[second]
                for second in range(segment_start, segment_end + 1)
                if second in grouped_primary
            ]
            if len(segment_tracks) >= 2:
                movement = self._path_length(segment_tracks)
                if movement >= 35:
                    events.append(
                        self._build_event(
                            context=context,
                            video_url=video_url,
                            event_type="dribble",
                            start_second=segment_start,
                            end_second=max(segment_end, segment_start + 1),
                            confidence=min(93.0, 71.0 + movement / 6.0),
                            payload={
                                "successful": True,
                                "source_track_id": primary_track_id,
                                "movement_px": round(movement, 1),
                            },
                        )
                    )
            segment_start = None

        return events

    def _detect_shots(
        self,
        context: AnalysisContext,
        video_url: str,
        primary_track_id: int,
        primary_player: list[TrackSnapshot],
        possessions: list[_PossessionMoment],
    ) -> list[AnalysisEvent]:
        events: list[AnalysisEvent] = []
        grouped_primary = {track.second: track for track in primary_player}
        by_second = {moment.second: moment for moment in possessions}

        for second, moment in by_second.items():
            if moment.holder_track_id != primary_track_id or moment.ball is None:
                continue
            next_moment = by_second.get(second + 1)
            if next_moment is None or next_moment.ball is None:
                continue

            primary_track = grouped_primary.get(second)
            if primary_track is None:
                continue

            release_distance = self._distance(moment.ball, next_moment.ball)
            player_ball_gap = self._distance(primary_track, next_moment.ball)
            if release_distance < 80 or player_ball_gap < 120:
                continue

            events.append(
                self._build_event(
                    context=context,
                    video_url=video_url,
                    event_type="shot",
                    start_second=second,
                    end_second=second + 1,
                    confidence=min(95.0, 74.0 + release_distance / 7.0),
                    payload={
                        "successful": True,
                        "source_track_id": primary_track_id,
                        "ball_speed_px_per_sample": round(release_distance, 1),
                    },
                )
            )
            break

        return events

    def _is_same_team(self, context: AnalysisContext, left_track_id: int, right_track_id: int) -> bool:
        if not context.track_team_map:
            return True
        left_team = context.track_team_map.get(left_track_id)
        right_team = context.track_team_map.get(right_track_id)
        if left_team is None or right_team is None:
            return True
        return left_team == right_team

    def _is_opponent_switch(self, context: AnalysisContext, from_track_id: int, to_track_id: int) -> bool:
        if not context.track_team_map:
            return True
        from_team = context.track_team_map.get(from_track_id)
        to_team = context.track_team_map.get(to_track_id)
        if from_team is None or to_team is None:
            return True
        return from_team != to_team

    def _detect_fallback_events(
        self,
        context: AnalysisContext,
        video_url: str,
        primary_track_id: int,
        primary_player: list[TrackSnapshot],
        ball_tracks: list[TrackSnapshot],
    ) -> list[AnalysisEvent]:
        events: list[AnalysisEvent] = []
        if len(primary_player) >= 2:
            movement = self._path_length(primary_player)
            if movement >= 22:
                start_second = primary_player[0].second
                end_second = primary_player[-1].second
                events.append(
                    self._build_event(
                        context=context,
                        video_url=video_url,
                        event_type="dribble",
                        start_second=start_second,
                        end_second=max(start_second + 1, end_second),
                        confidence=min(82.0, 66.0 + movement / 7.0),
                        payload={
                            "successful": True,
                            "source_track_id": primary_track_id,
                            "movement_px": round(movement, 1),
                            "fallback": True,
                        },
                    )
                )

        if len(ball_tracks) >= 2:
            ordered_balls = sorted(ball_tracks, key=lambda item: item.second)
            for prev, curr in zip(ordered_balls, ordered_balls[1:]):
                travel = self._distance(prev, curr)
                if travel < 45:
                    continue
                events.append(
                    self._build_event(
                        context=context,
                        video_url=video_url,
                        event_type="shot" if travel >= 100 else "pass",
                        start_second=prev.second,
                        end_second=max(prev.second + 1, curr.second),
                        confidence=min(80.0, 64.0 + travel / 8.0),
                        payload={
                            "successful": True,
                            "source_track_id": primary_track_id,
                            "ball_travel_px": round(travel, 1),
                            "fallback": True,
                        },
                    )
                )
                break

        return events

    def _group_by_second(self, tracks: list[TrackSnapshot]) -> dict[int, list[TrackSnapshot]]:
        grouped: dict[int, list[TrackSnapshot]] = {}
        for track in tracks:
            grouped.setdefault(track.second, []).append(track)
        return grouped

    def _track_group(self, tracks: list[TrackSnapshot]) -> dict[int, list[TrackSnapshot]]:
        grouped: dict[int, list[TrackSnapshot]] = {}
        for track in tracks:
            grouped.setdefault(track.track_id, []).append(track)
        return grouped

    def _is_wide_play(self, ball: TrackSnapshot, width_hint: float) -> bool:
        if width_hint <= 0:
            return False
        center_x = (ball.bbox[0] + ball.bbox[2]) / 2.0
        ratio = center_x / width_hint
        return ratio <= 0.28 or ratio >= 0.72

    def _dedupe_events(self, events: list[AnalysisEvent]) -> list[AnalysisEvent]:
        deduped: list[AnalysisEvent] = []
        seen: set[tuple[str, int, int]] = set()
        for event in sorted(events, key=lambda item: (item.start_second, item.event_type)):
            key = (event.event_type, event.start_second, event.end_second)
            if key in seen:
                continue
            seen.add(key)
            deduped.append(event)
        return deduped

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
        normalized_end = max(start_second + 1, end_second)
        return AnalysisEvent(
            target_player_id=context.target_player_id,
            event_type=event_type,
            start_second=start_second,
            end_second=normalized_end,
            confidence=round(confidence, 1),
            payload=payload,
            clips=[
                AnalysisClip(
                    clip_url=f"{video_url}#t={start_second},{normalized_end}",
                    thumbnail_url=context.thumbnail_url,
                    clip_start_second=start_second,
                    clip_end_second=normalized_end,
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
        grouped_balls = self._group_by_second(balls)

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

    def _payload(self, context: AnalysisContext, event_type: str, movement: float, ball_control: float, extra: dict) -> dict:
        sport = context.sport
        if sport == "basketball":
            return {
                "successful": True,
                "ball_control_ratio": round(ball_control, 2),
                "lane_pressure": "high" if movement > 180 else "medium",
                "event_family": event_type,
                **extra,
            }
        if sport == "volleyball":
            return {
                "successful": True,
                "ball_control_ratio": round(ball_control, 2),
                "timing_score": min(95, int(64 + movement / 12)),
                "event_family": event_type,
                **extra,
            }
        return {
            "successful": True,
            "ball_control_ratio": round(ball_control, 2),
            "distance_m": round(self._to_meters(movement, context), 1),
            "event_family": event_type,
            **extra,
        }

    def _to_meters(self, pixel_distance: float, context: AnalysisContext | None) -> float:
        if context is not None and context.meters_per_pixel is not None:
            return pixel_distance * context.meters_per_pixel
        return pixel_distance / 18.0
