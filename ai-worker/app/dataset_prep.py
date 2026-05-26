from __future__ import annotations

import subprocess
import sys
from pathlib import Path

from app.schemas import PrepareDatasetJobRequest


def _root() -> Path:
    return Path(__file__).resolve().parents[1]


def _script_path() -> Path:
    return _root() / 'scripts' / 'prepare_dataset.py'


def _source_dir(sport: str) -> Path:
    return _root() / 'raw_videos' / sport


def _output_dir(sport: str) -> Path:
    return _root() / 'datasets' / sport


def run_prepare_dataset_job(job: PrepareDatasetJobRequest) -> dict:
    script_path = _script_path()
    source_dir = _source_dir(job.sport)
    output_dir = _output_dir(job.sport)

    if not script_path.exists():
        raise RuntimeError(f'dataset prep script bulunamadi: {script_path}')
    if not source_dir.exists():
        raise RuntimeError(f'raw video klasoru bulunamadi: {source_dir}')

    output_dir.mkdir(parents=True, exist_ok=True)

    command = [
        sys.executable,
        str(script_path),
        '--sport',
        job.sport,
        '--source-dir',
        str(source_dir),
        '--output-dir',
        str(output_dir),
        f'--sample-every-seconds={job.sample_every_seconds}',
        f'--max-seconds={job.max_seconds}',
    ]

    completed = subprocess.run(
        command,
        cwd=str(_root()),
        capture_output=True,
        text=True,
        check=False,
    )
    output = (completed.stdout or '') + ('\n' + completed.stderr if completed.stderr else '')

    if completed.returncode != 0:
        raise RuntimeError(output.strip() or 'dataset prep basarisiz oldu')

    return {
        'sport': job.sport,
        'source_dir': str(source_dir),
        'output_dir': str(output_dir),
        'output': output.strip(),
    }

