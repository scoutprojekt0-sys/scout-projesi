<?php

return [
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),

    'cors' => [
        'allowed_origins' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://127.0.0.1:3000'))
        ))),
    ],

    'auth' => [
        'token_expiration' => (int) env('SANCTUM_TOKEN_EXPIRATION', 10080),
    ],

    'performance' => [
        'opportunities_cache_enabled' => (bool) env('OPPORTUNITIES_CACHE_ENABLED', true),
        'opportunities_cache_ttl_seconds' => (int) env('OPPORTUNITIES_CACHE_TTL_SECONDS', 60),
    ],

    'rate_limits' => [
        'auth_per_minute' => (int) env('RATE_LIMIT_AUTH_PER_MINUTE', 5),
        'api_read_per_minute' => (int) env('RATE_LIMIT_API_READ_PER_MINUTE', 120),
        'api_write_per_minute' => (int) env('RATE_LIMIT_API_WRITE_PER_MINUTE', 40),
        'auth_failed_attempts_before_lock' => (int) env('AUTH_FAILED_ATTEMPTS_BEFORE_LOCK', 5),
        'auth_lock_seconds' => (int) env('AUTH_LOCK_SECONDS', 900),
    ],

    'logging' => [
        'security_level' => env('LOG_SECURITY_LEVEL', 'info'),
        'security_days' => (int) env('LOG_SECURITY_DAYS', 30),
        'ops_level' => env('LOG_OPS_LEVEL', 'info'),
        'ops_days' => (int) env('LOG_OPS_DAYS', 14),
    ],

    'monitoring' => [
        'slow_request_ms' => (int) env('MONITOR_SLOW_REQUEST_MS', 800),
    ],

    'ai_analysis' => [
        'mode' => env('AI_ANALYSIS_MODE', 'external'),
        'allow_mock_fallback' => env('AI_ANALYSIS_ALLOW_MOCK_FALLBACK', false),
        'worker_base_url' => env('AI_ANALYSIS_WORKER_BASE_URL', ''),
        'worker_timeout_seconds' => (int) env('AI_ANALYSIS_WORKER_TIMEOUT_SECONDS', 20),
        'callback_secret' => env('AI_ANALYSIS_CALLBACK_SECRET', ''),
    ],

    'ai_training' => [
        'continuous_learning' => [
            'auto_analyze_uploads' => filter_var(env('AI_CONTINUOUS_LEARNING_AUTO_ANALYZE_UPLOADS', true), FILTER_VALIDATE_BOOL),
            'auto_promote_confident_analysis' => filter_var(env('AI_CONTINUOUS_LEARNING_AUTO_PROMOTE_CONFIDENT_ANALYSIS', true), FILTER_VALIDATE_BOOL),
            'allow_mock_promotion' => filter_var(env('AI_CONTINUOUS_LEARNING_ALLOW_MOCK_PROMOTION', false), FILTER_VALIDATE_BOOL),
            'min_events' => (int) env('AI_CONTINUOUS_LEARNING_MIN_EVENTS', 3),
            'min_avg_event_confidence' => (float) env('AI_CONTINUOUS_LEARNING_MIN_AVG_EVENT_CONFIDENCE', env('AI_PSEUDO_LABEL_MIN_CONFIDENCE', 0.65)),
            'dataset_max_seconds' => (int) env('AI_CONTINUOUS_LEARNING_DATASET_MAX_SECONDS', 60),
            'dataset_sample_every_seconds' => (int) env('AI_CONTINUOUS_LEARNING_DATASET_SAMPLE_EVERY_SECONDS', 2),
        ],
        'auto_train' => [
            'enabled' => filter_var(env('AI_AUTO_TRAIN_ENABLED', true), FILTER_VALIDATE_BOOL),
            'daily_at' => env('AI_AUTO_TRAIN_DAILY_AT', '02:00'),
            'night_window_minutes' => (int) env('AI_AUTO_TRAIN_NIGHT_WINDOW_MINUTES', 90),
            'min_annotated_frames' => (int) env('AI_AUTO_TRAIN_MIN_ANNOTATED_FRAMES', 100),
            'min_images' => (int) env('AI_AUTO_TRAIN_MIN_IMAGES', 100),
            'min_completion' => (float) env('AI_AUTO_TRAIN_MIN_COMPLETION', 60),
            'epochs' => (int) env('AI_AUTO_TRAIN_EPOCHS', 25),
            'imgsz' => (int) env('AI_AUTO_TRAIN_IMGSZ', 960),
            'batch' => (int) env('AI_AUTO_TRAIN_BATCH', 8),
            'device' => env('AI_AUTO_TRAIN_DEVICE', 'cpu'),
        ],
        'pseudo_label' => [
            'min_confidence' => (float) env('AI_PSEUDO_LABEL_MIN_CONFIDENCE', 0.65),
        ],
        'validation' => [
            'default_thresholds' => [
                'map50' => (float) env('AI_VALIDATION_MIN_MAP50', 0.45),
                'map50_95' => (float) env('AI_VALIDATION_MIN_MAP50_95', 0.25),
                'precision' => (float) env('AI_VALIDATION_MIN_PRECISION', 0.45),
                'recall' => (float) env('AI_VALIDATION_MIN_RECALL', 0.40),
            ],
            'default_class_thresholds' => [
                'map50' => (float) env('AI_VALIDATION_CLASS_MIN_MAP50', 0.20),
                'map50_95' => (float) env('AI_VALIDATION_CLASS_MIN_MAP50_95', 0.10),
                'precision' => (float) env('AI_VALIDATION_CLASS_MIN_PRECISION', 0.20),
                'recall' => (float) env('AI_VALIDATION_CLASS_MIN_RECALL', 0.20),
            ],
            'default_event_thresholds' => [
                'precision' => (float) env('AI_VALIDATION_EVENT_MIN_PRECISION', 0.35),
                'recall' => (float) env('AI_VALIDATION_EVENT_MIN_RECALL', 0.30),
                'f1' => (float) env('AI_VALIDATION_EVENT_MIN_F1', 0.30),
            ],
            'default_event_type_thresholds' => [
                'precision' => (float) env('AI_VALIDATION_EVENT_TYPE_MIN_PRECISION', 0.20),
                'recall' => (float) env('AI_VALIDATION_EVENT_TYPE_MIN_RECALL', 0.20),
                'f1' => (float) env('AI_VALIDATION_EVENT_TYPE_MIN_F1', 0.20),
            ],
            'sport_thresholds' => [
                'football' => [],
                'basketball' => [],
                'volleyball' => [],
            ],
            'sport_class_thresholds' => [
                'football' => [],
                'basketball' => [],
                'volleyball' => [],
            ],
            'sport_event_thresholds' => [
                'football' => [],
                'basketball' => [],
                'volleyball' => [],
            ],
            'sport_event_type_thresholds' => [
                'football' => [],
                'basketball' => [],
                'volleyball' => [],
            ],
        ],
        'rollout' => [
            'default_tolerance' => [
                'map50' => (float) env('AI_ROLLOUT_MAX_MAP50_DROP', 0.02),
                'map50_95' => (float) env('AI_ROLLOUT_MAX_MAP50_95_DROP', 0.02),
                'precision' => (float) env('AI_ROLLOUT_MAX_PRECISION_DROP', 0.03),
                'recall' => (float) env('AI_ROLLOUT_MAX_RECALL_DROP', 0.03),
            ],
            'default_class_tolerance' => [
                'map50' => (float) env('AI_ROLLOUT_CLASS_MAX_MAP50_DROP', 0.05),
                'map50_95' => (float) env('AI_ROLLOUT_CLASS_MAX_MAP50_95_DROP', 0.05),
                'precision' => (float) env('AI_ROLLOUT_CLASS_MAX_PRECISION_DROP', 0.07),
                'recall' => (float) env('AI_ROLLOUT_CLASS_MAX_RECALL_DROP', 0.07),
            ],
            'default_event_tolerance' => [
                'precision' => (float) env('AI_ROLLOUT_EVENT_MAX_PRECISION_DROP', 0.08),
                'recall' => (float) env('AI_ROLLOUT_EVENT_MAX_RECALL_DROP', 0.08),
                'f1' => (float) env('AI_ROLLOUT_EVENT_MAX_F1_DROP', 0.08),
            ],
            'default_event_type_tolerance' => [
                'precision' => (float) env('AI_ROLLOUT_EVENT_TYPE_MAX_PRECISION_DROP', 0.12),
                'recall' => (float) env('AI_ROLLOUT_EVENT_TYPE_MAX_RECALL_DROP', 0.12),
                'f1' => (float) env('AI_ROLLOUT_EVENT_TYPE_MAX_F1_DROP', 0.12),
            ],
            'sport_tolerance' => [
                'football' => [],
                'basketball' => [],
                'volleyball' => [],
            ],
            'sport_class_tolerance' => [
                'football' => [],
                'basketball' => [],
                'volleyball' => [],
            ],
            'sport_event_tolerance' => [
                'football' => [],
                'basketball' => [],
                'volleyball' => [],
            ],
            'sport_event_type_tolerance' => [
                'football' => [],
                'basketball' => [],
                'volleyball' => [],
            ],
        ],
        'monitoring' => [
            'lookback_hours' => (int) env('AI_MONITORING_LOOKBACK_HOURS', 24),
            'min_sample_size' => (int) env('AI_MONITORING_MIN_SAMPLE_SIZE', 5),
            'consecutive_windows_for_rollback' => (int) env('AI_MONITORING_CONSECUTIVE_WINDOWS_FOR_ROLLBACK', 2),
            'max_failure_rate' => (float) env('AI_MONITORING_MAX_FAILURE_RATE', 0.35),
            'max_no_event_rate' => (float) env('AI_MONITORING_MAX_NO_EVENT_RATE', 0.45),
            'min_avg_event_confidence' => (float) env('AI_MONITORING_MIN_AVG_EVENT_CONFIDENCE', 0.55),
            'min_avg_events_per_analysis' => (float) env('AI_MONITORING_MIN_AVG_EVENTS_PER_ANALYSIS', 1.0),
            'max_failure_rate_increase' => (float) env('AI_MONITORING_MAX_FAILURE_RATE_INCREASE', 0.20),
            'max_no_event_rate_increase' => (float) env('AI_MONITORING_MAX_NO_EVENT_RATE_INCREASE', 0.25),
            'max_confidence_drop' => (float) env('AI_MONITORING_MAX_CONFIDENCE_DROP', 0.15),
            'max_events_per_analysis_drop' => (float) env('AI_MONITORING_MAX_EVENTS_PER_ANALYSIS_DROP', 0.80),
            'sport_thresholds' => [
                'football' => [
                    'min_sample_size' => (int) env('AI_MONITORING_FOOTBALL_MIN_SAMPLE_SIZE', 5),
                    'consecutive_windows_for_rollback' => (int) env('AI_MONITORING_FOOTBALL_CONSECUTIVE_WINDOWS_FOR_ROLLBACK', 2),
                    'max_failure_rate' => (float) env('AI_MONITORING_FOOTBALL_MAX_FAILURE_RATE', 0.35),
                    'max_no_event_rate' => (float) env('AI_MONITORING_FOOTBALL_MAX_NO_EVENT_RATE', 0.45),
                    'min_avg_event_confidence' => (float) env('AI_MONITORING_FOOTBALL_MIN_AVG_EVENT_CONFIDENCE', 0.55),
                    'min_avg_events_per_analysis' => (float) env('AI_MONITORING_FOOTBALL_MIN_AVG_EVENTS_PER_ANALYSIS', 1.00),
                    'max_failure_rate_increase' => (float) env('AI_MONITORING_FOOTBALL_MAX_FAILURE_RATE_INCREASE', 0.20),
                    'max_no_event_rate_increase' => (float) env('AI_MONITORING_FOOTBALL_MAX_NO_EVENT_RATE_INCREASE', 0.25),
                    'max_confidence_drop' => (float) env('AI_MONITORING_FOOTBALL_MAX_CONFIDENCE_DROP', 0.15),
                    'max_events_per_analysis_drop' => (float) env('AI_MONITORING_FOOTBALL_MAX_EVENTS_PER_ANALYSIS_DROP', 0.80),
                ],
                'basketball' => [
                    'min_sample_size' => (int) env('AI_MONITORING_BASKETBALL_MIN_SAMPLE_SIZE', 4),
                    'consecutive_windows_for_rollback' => (int) env('AI_MONITORING_BASKETBALL_CONSECUTIVE_WINDOWS_FOR_ROLLBACK', 2),
                    'max_failure_rate' => (float) env('AI_MONITORING_BASKETBALL_MAX_FAILURE_RATE', 0.30),
                    'max_no_event_rate' => (float) env('AI_MONITORING_BASKETBALL_MAX_NO_EVENT_RATE', 0.35),
                    'min_avg_event_confidence' => (float) env('AI_MONITORING_BASKETBALL_MIN_AVG_EVENT_CONFIDENCE', 0.60),
                    'min_avg_events_per_analysis' => (float) env('AI_MONITORING_BASKETBALL_MIN_AVG_EVENTS_PER_ANALYSIS', 1.80),
                    'max_failure_rate_increase' => (float) env('AI_MONITORING_BASKETBALL_MAX_FAILURE_RATE_INCREASE', 0.15),
                    'max_no_event_rate_increase' => (float) env('AI_MONITORING_BASKETBALL_MAX_NO_EVENT_RATE_INCREASE', 0.20),
                    'max_confidence_drop' => (float) env('AI_MONITORING_BASKETBALL_MAX_CONFIDENCE_DROP', 0.12),
                    'max_events_per_analysis_drop' => (float) env('AI_MONITORING_BASKETBALL_MAX_EVENTS_PER_ANALYSIS_DROP', 0.70),
                ],
                'volleyball' => [
                    'min_sample_size' => (int) env('AI_MONITORING_VOLLEYBALL_MIN_SAMPLE_SIZE', 4),
                    'consecutive_windows_for_rollback' => (int) env('AI_MONITORING_VOLLEYBALL_CONSECUTIVE_WINDOWS_FOR_ROLLBACK', 2),
                    'max_failure_rate' => (float) env('AI_MONITORING_VOLLEYBALL_MAX_FAILURE_RATE', 0.28),
                    'max_no_event_rate' => (float) env('AI_MONITORING_VOLLEYBALL_MAX_NO_EVENT_RATE', 0.32),
                    'min_avg_event_confidence' => (float) env('AI_MONITORING_VOLLEYBALL_MIN_AVG_EVENT_CONFIDENCE', 0.62),
                    'min_avg_events_per_analysis' => (float) env('AI_MONITORING_VOLLEYBALL_MIN_AVG_EVENTS_PER_ANALYSIS', 2.20),
                    'max_failure_rate_increase' => (float) env('AI_MONITORING_VOLLEYBALL_MAX_FAILURE_RATE_INCREASE', 0.14),
                    'max_no_event_rate_increase' => (float) env('AI_MONITORING_VOLLEYBALL_MAX_NO_EVENT_RATE_INCREASE', 0.18),
                    'max_confidence_drop' => (float) env('AI_MONITORING_VOLLEYBALL_MAX_CONFIDENCE_DROP', 0.10),
                    'max_events_per_analysis_drop' => (float) env('AI_MONITORING_VOLLEYBALL_MAX_EVENTS_PER_ANALYSIS_DROP', 0.60),
                ],
            ],
        ],
    ],
];
