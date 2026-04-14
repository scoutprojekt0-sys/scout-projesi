# Model Comparison

Use this helper to compare the latest training summary with the previous saved run.

## Usage

```bash
python scripts/compare_run_metrics.py runs/basketball/player_ball_detector13/validation_summary.json
```

Or with an explicit previous run:

```bash
python scripts/compare_run_metrics.py \
  runs/volleyball/player_ball_detector2/validation_summary.json \
  runs/volleyball/player_ball_detector1/validation_summary.json
```

## Output

For both `val` and `test` splits:

- `map50`
- `map50_95`
- `precision`
- `recall`

Values are shown as:

```text
current_value (+delta)
```

Example:

```text
map50 : 0.680 (+0.301)
```

## Requirement

Both runs must have `validation_summary.json`.
