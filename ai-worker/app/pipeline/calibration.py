from __future__ import annotations

from dataclasses import dataclass

import cv2
import numpy as np

from app.pipeline.types import FrameSample


SPORT_FIELD_LENGTH_METERS = {
    "football": 105.0,
    "basketball": 28.0,
    "volleyball": 18.0,
}


@dataclass(slots=True)
class FieldCalibration:
    pixels_per_meter_x: float
    pixels_per_meter_y: float
    field_bbox: tuple[int, int, int, int]
    confidence: float

    def px_to_meters(self, pixel_distance: float) -> float:
        scale = max(1e-6, (self.pixels_per_meter_x + self.pixels_per_meter_y) / 2.0)
        return pixel_distance / scale


class FieldCalibrator:
    def estimate(self, sport: str, frames: list[FrameSample]) -> FieldCalibration | None:
        for frame in frames:
            if frame.image is None:
                continue
            calibration = self._estimate_from_frame(sport, frame)
            if calibration is not None:
                return calibration
        return None

    def _estimate_from_frame(self, sport: str, frame: FrameSample) -> FieldCalibration | None:
        image = frame.image
        hsv = cv2.cvtColor(image, cv2.COLOR_BGR2HSV)

        lower_green = np.array([25, 25, 25], dtype=np.uint8)
        upper_green = np.array([95, 255, 255], dtype=np.uint8)
        mask = cv2.inRange(hsv, lower_green, upper_green)
        kernel = np.ones((7, 7), np.uint8)
        mask = cv2.morphologyEx(mask, cv2.MORPH_OPEN, kernel)
        mask = cv2.morphologyEx(mask, cv2.MORPH_CLOSE, kernel)

        contours, _ = cv2.findContours(mask, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
        if not contours:
            return None

        contour = max(contours, key=cv2.contourArea)
        x, y, w, h = cv2.boundingRect(contour)
        area = float(w * h)
        frame_area = float(image.shape[0] * image.shape[1])
        if area < frame_area * 0.18:
            return None

        sport_length = SPORT_FIELD_LENGTH_METERS.get(sport, SPORT_FIELD_LENGTH_METERS["football"])
        sport_width = {
            "football": 68.0,
            "basketball": 15.0,
            "volleyball": 9.0,
        }.get(sport, 68.0)

        confidence = min(0.95, max(0.15, area / frame_area))
        return FieldCalibration(
            pixels_per_meter_x=w / sport_length,
            pixels_per_meter_y=h / sport_width,
            field_bbox=(x, y, x + w, y + h),
            confidence=round(confidence, 3),
        )
