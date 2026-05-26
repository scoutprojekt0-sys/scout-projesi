from __future__ import annotations

import shutil
import subprocess
import sys
from pathlib import Path

from app.schemas import TrainingJobRequest


def _root() -> Path:
    return Path(__file__).resolve().parents[1]


def _script_path(sport: str) -> Path:
    return _root() / 'scripts' / f'train_{sport}_model.py'


def _data_path(sport: str) -> Path:
    return _root() / 'datasets' / f'{sport}_detection.yaml'


def _project_dir(sport: str) -> Path:
    return _root().parent / 'runs' / sport


def _published_model_path(sport: str, model_version: str) -> Path:
    return _root() / 'storage' / 'app' / 'ai-models' / sport / f'{model_version}.pt'


def _find_latest_best(project_dir: Path) -> Path | None:
    if not project_dir.exists():
        return None

    best_paths = []
    for run_dir in project_dir.iterdir():
        if not run_dir.is_dir():
            continue
        best = run_dir / 'weights' / 'best.pt'
        if best.exists():
            best_paths.append(best)

    if not best_paths:
        return None

    best_paths.sort(key=lambda path: path.stat().st_mtime, reverse=True)
    return best_paths[0]


def run_training_job(job: TrainingJobRequest) -> dict:
    script_path = _script_path(job.sport)
    data_path = _data_path(job.sport)
    project_dir = _project_dir(job.sport)

    if not script_path.exists():
        raise RuntimeError(f'train script bulunamadi: {script_path}')
    if not data_path.exists():
        raise RuntimeError(f'dataset yaml bulunamadi: {data_path}')

    command = [
        sys.executable,
        str(script_path),
        '--data',
        str(data_path),
        '--device',
        str(job.device),
        '--epochs',
        str(job.epochs),
        '--imgsz',
        str(job.imgsz),
        '--batch',
        str(job.batch),
        '--project',
        str(project_dir),
    ]

    completed = subprocess.run(
        command,
        cwd=str(_root().parent),
        capture_output=True,
        text=True,
        check=False,
    )
    output = (completed.stdout or '') + ('\n' + completed.stderr if completed.stderr else '')

    if completed.returncode != 0:
        raise RuntimeError(output.strip() or 'training basarisiz oldu')

    best_path = _find_latest_best(project_dir)
    if best_path is None:
        raise RuntimeError('training tamamlandi ama best.pt bulunamadi')

    published_path = _published_model_path(job.sport, job.model_version)
    published_path.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(best_path, published_path)

    return {
        'sport': job.sport,
        'model_version': job.model_version,
        'best_path': str(best_path),
        'published_model_path': str(published_path).replace('\\', '/').split('/app/', 1)[-1],
        'output': output.strip(),
    }
