from pydantic import Field
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    ai_worker_port: int = Field(default=8010, alias="AI_WORKER_PORT")
    ai_worker_mode: str = Field(default="mock", alias="AI_WORKER_MODE")
    ai_worker_callback_timeout_seconds: int = Field(
        default=20, alias="AI_WORKER_CALLBACK_TIMEOUT_SECONDS"
    )
    ai_worker_log_level: str = Field(default="info", alias="AI_WORKER_LOG_LEVEL")
    ai_worker_detector: str = Field(default="auto", alias="AI_WORKER_DETECTOR")
    ai_worker_yolo_model_path: str = Field(default="models/player_ball.pt", alias="AI_WORKER_YOLO_MODEL_PATH")
    ai_worker_football_model_path: str = Field(default="models/football_player_ball.pt", alias="AI_WORKER_FOOTBALL_MODEL_PATH")
    ai_worker_basketball_model_path: str = Field(default="models/basketball_player_ball.pt", alias="AI_WORKER_BASKETBALL_MODEL_PATH")
    ai_worker_volleyball_model_path: str = Field(default="models/volleyball_player_ball.pt", alias="AI_WORKER_VOLLEYBALL_MODEL_PATH")
    ai_worker_sample_every_seconds: int = Field(default=1, alias="AI_WORKER_SAMPLE_EVERY_SECONDS")
    ai_worker_max_sample_seconds: int = Field(default=180, alias="AI_WORKER_MAX_SAMPLE_SECONDS")
    ai_worker_download_timeout_seconds: int = Field(default=60, alias="AI_WORKER_DOWNLOAD_TIMEOUT_SECONDS")

    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        extra="ignore",
    )


settings = Settings()
