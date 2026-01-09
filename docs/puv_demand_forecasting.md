# PUV Demand Forecasting — Implementation Guide

## Objective
- Forecast near-term PUV trip demand per terminal and route using dispatch logs.
- Provide actionable outputs: trips per time-slot, headway suggestions, and capacity planning.
- Integrate forecasts into existing Module 5 analytics and operational gates.

## Simple AI Implementation (Planned)
- Core promise: simple, transparent forecasts and alerts; no deep ML required.
- Tools: Excel or basic Python (pandas + scikit-learn) for simple regression; current PHP fallback is available.
- Outputs: 2-hour peak alerts, per-slot headway guidance, daily recommendations to TODAs.
- Governance: no auto-reassignment; gates and admin overrides remain in place for fairness and compliance.

### What Panelists Emphasized
- Keep models simple and explainable; focus on clean historical data.
- Deliver concrete operational actions (alerts, headways, vehicles to add) rather than opaque scores.
- Respect LGU process: admin approvals, audit trails, minimum service floors, and transparency.

## Target Outputs
- Time-based forecasts per terminal_id and route_id at 15–60 minute granularity.
- Fields: ts (timestamp), horizon_min, forecast_trips, lower_ci, upper_ci, model_version.
- Derived metrics: recommended headway, required vehicles to meet demand, confidence flag.

## Data Pipeline
- Source: terminal_logs with activity_type='Dispatch' joined to vehicles for route_id.
- Aggregation resolution: 30 min or 60 min to start; configurable.
- Example SQL to build training series:

```sql
SELECT
  l.terminal_id,
  v.route_id,
  DATE_FORMAT(l.time_in, '%Y-%m-%d %H:00:00') AS ts_hour,
  COUNT(*) AS trips
FROM terminal_logs l
JOIN vehicles v ON v.plate_number = l.vehicle_plate
WHERE l.activity_type = 'Dispatch'
GROUP BY l.terminal_id, v.route_id, ts_hour
ORDER BY ts_hour;
```

## Schema Additions
- demand_forecasts: store forecasts for serving and UI overlay.
- demand_forecast_jobs: track training/forecasting runs for observability.
- Optional features mart: denormalized calendar and external signals.

```sql
CREATE TABLE IF NOT EXISTS demand_forecasts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  terminal_id INT NOT NULL,
  route_id VARCHAR(64) NOT NULL,
  ts DATETIME NOT NULL,
  horizon_min INT NOT NULL,
  forecast_trips DOUBLE NOT NULL,
  lower_ci DOUBLE NULL,
  upper_ci DOUBLE NULL,
  model_version VARCHAR(50) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (terminal_id, route_id, ts)
);

CREATE TABLE IF NOT EXISTS demand_forecast_jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_type ENUM('train','forecast') NOT NULL,
  status ENUM('queued','running','succeeded','failed') NOT NULL DEFAULT 'queued',
  params_json TEXT,
  started_at DATETIME NULL,
  finished_at DATETIME NULL,
  message TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Modeling Strategy
- Baseline: last-week same slot blended with hour-of-day mean and recent daily averages.
- Simple Regression: Linear Regression or Random Forest on features like hour, day-of-week, event flags, and optional weather.
- Confidence Bands: simple ±20% around prediction or based on historical variability.
- Accuracy Target: iterate toward ≥80% MAPE accuracy by cleaning data and tuning simple features.

### Features
- Calendar: hour, day-of-week, weekend, holiday, month, term weeks.
- Terminal/route attributes: capacity, authorized roster count.
- Compliance and permit status as binary flags.
- Weather or event signals when accessible; otherwise omit.

## Service Design
- Option A (Excel): Build per-route/per-terminal hourly tables; fit simple linear trends and seasonal adjustments; export daily recommendations and peak alert windows.
- Option B (Python microservice): Minimal FastAPI with scikit-learn Linear Regression; returns forecasts; optional Random Forest for nonlinearity.
- Option C (PHP fallback): Use historical dispatch series to produce baseline forecasts and 2-hour alerts directly in PHP when Python is unavailable.
- Existing integration: Module 5 analytics calls a forecast endpoint and persists results; see [run_forecast.php](file:///c:/xampp/htdocs/tmm/admin/api/analytics/run_forecast.php).

### Minimal FastAPI Skeleton

```python
from fastapi import FastAPI
from pydantic import BaseModel
import pandas as pd

app = FastAPI()

class ForecastRequest(BaseModel):
    terminal_id: int
    route_id: str
    horizon_min: int
    granularity_min: int

@app.get("/health")
def health():
    return {"ok": True}

@app.post("/forecast")
def forecast(req: ForecastRequest):
    horizon_steps = max(1, req.horizon_min // req.granularity_min)
    ts = pd.date_range(pd.Timestamp.utcnow().floor(f"{req.granularity_min}min"),
                       periods=horizon_steps, freq=f"{req.granularity_min}min")
    yhat = [0.0 for _ in range(horizon_steps)]
    return {"ok": True, "terminal_id": req.terminal_id, "route_id": req.route_id,
            "granularity_min": req.granularity_min,
            "forecasts": [{"ts": t.isoformat(), "forecast_trips": float(v)} for t, v in zip(ts, yhat)]}
```

## Integration
- API wrapper: call microservice or use PHP fallback, then persist to demand_forecasts.
- UI: overlay forecasts in Module 5 analytics; display headway recommendations and 2-hour peak alerts.
- Operations: show forecast-driven deployment alerts; suggest vehicles to add per window.

### PHP Fetch Example

```php
$payload = [
  'terminal_id' => $terminalId,
  'route_id' => $routeId,
  'horizon_min' => 240,
  'granularity_min' => 60
];
$ch = curl_init('http://127.0.0.1:8001/forecast');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
curl_close($ch);
$data = json_decode($res, true);
```

## Training and Scheduling
- Nightly refresh: recompute simple regression fits (Excel or Python) or rely on baseline PHP fallback.
- Hourly refresh: run forecasts and update alerts for upcoming windows.
- Task Scheduler: schedule scripts and write metadata to demand_forecast_jobs; keep model_version tags simple (e.g., simple_reg_v1).

## Evaluation and Monitoring
- Backtesting: rolling-origin with 4–8 weeks of history.
- Metrics: MAPE target ≥80% accuracy; track by terminal×route and time-of-day.
- Monitoring UI: actual vs forecast overlay; error bands; drift flags.
- Fallback: baseline PHP forecast when service unavailable or data sparse.

## UI Enhancements
- Module 5 submodule3: Forecasts panel with headway guidance and “Upcoming Alerts (2h)” list.
- Show recommended vehicles per window and confidence indicator.
- Compare actual dispatches against forecasts to improve accuracy.

## Oversupply Management & Permit Gating
- Problem: Some routes can be over-supplied (too many PUVs relative to demand), causing congestion and low utilization.
- Approach: Detect oversupply using a demand-capacity ratio (DCR) and enforce dynamic caps through existing gates.

### Oversupply Detection
- Define DCR = forecast_trips / served_capacity over each time window.
- served_capacity ≈ authorized_vehicles × expected_trips_per_vehicle (from recommended headway).
- Oversupply when DCR < θ (e.g., 0.7) for sustained windows and confidence ≥ c (e.g., 0.6).
- Secondary signals: unusually low dwell-to-dispatch ratios, very short headways, persistent idle times.

### Dynamic Caps & Gates
- Dynamic Route Cap: compute cap_t using simple demand-capacity ratios (DCR) and store in route_cap_schedule.
- Assignment Gate: assign_route blocks when current_authorized ≥ cap_t.
- Permit Gate: permit activation blocks when oversupply flagged; officer override required.
- Roster Approval Gate: approve_roster rejects additions that exceed cap_t.

### Policy & Fairness
- Minimum Service Floor: do not reduce below a basic accessibility threshold per area/time-of-day.
- Progressive Controls: soft cap (warnings), hard cap (block), time-bounded caps (peak/off-peak).
- Overrides: Admin-level override logged with reason; audit trail for governance.
- Rebalancing: recommend reassignment to under-served routes; show “where-to-add” vs “where-to-hold” suggestions.

### Implementation Hooks
- Use forecasts in demand_forecasts to compute DCR each hour; write cap decisions to route_cap_schedule.
- Extend analytics UI (submodule3.php) with “Oversupply” tab: DCR charts, cap status, override controls.
- Update gates:
  - assign_route.php consults cap schedule before linking vehicle to route.
  - validate_roster / approve_roster consults cap schedule and shows operator guidance.
  - permit issuance keeps Pending Activation when oversupply is flagged, requiring explicit approval.

### Suggested Tables
```sql
CREATE TABLE IF NOT EXISTS route_cap_schedule (
  id INT AUTO_INCREMENT PRIMARY KEY,
  route_id VARCHAR(64) NOT NULL,
  ts DATETIME NOT NULL,
  cap INT NOT NULL,
  reason VARCHAR(255) NULL, -- e.g., "oversupply DCR=0.62"
  confidence DOUBLE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (route_id, ts)
);
```

### Operator UX
- Show “Route is temporarily capped” on assignment attempts; provide alternative routes needing capacity.
- Provide schedule of caps (peak/off-peak) for planning; notify when caps lift.
- Display headway guidance and expected trips to align supply with forecast demand.

## Regulatory Alignment & Route Assignment
- Real-world practice: PUVs operate on fixed, permitted routes (e.g., LPTRP). Reassignment typically requires formal approval (route modification, special order, or temporary augmentation permit).
- System default: No automatic reassignment. The AI produces advisory signals (under/over-supply), while gates control additions or permit activation.
- Temporary augmentation: Allowed via time-bounded permits for events or peak periods; enforce via permit status and terminal logging.
- Permanent changes: Require administrative review and update of franchise/endorsement records; processed through Module 2 workflows.
- UI and process:
  - “Request Reassignment” workflow: operators/coops submit proposals to rebalance specific vehicles for defined windows; reviewed and approved by officers.
  - “Augmentation Permit” workflow: limited vehicles and time ranges; visible in Module 5 and enforced in log_entry_exit.
- Governance:
  - Maintain minimum service floors for coverage; use soft/hard caps based on severity and confidence.
  - Audit overrides and publish cap schedules to ensure fairness and transparency.

## Security and Governance
- RBAC: Admin/Encoder/Inspector access to training and forecast APIs.
- Audit: log train/forecast requests with params_json and job metadata.
- Data handling: anonymize personal info; limit external signals to public sources.

## Implementation Steps
1. Create demand_forecasts and demand_forecast_jobs tables.
2. Export or query hourly dispatch series from terminal_logs.
3. Choose implementation path:
   - Excel: build sheets and formulas per route; publish daily guidance.
   - Python: small FastAPI using Linear Regression; optional Random Forest.
   - PHP: use fallback baseline already wired in [run_forecast.php](file:///c:/xampp/htdocs/tmm/admin/api/analytics/run_forecast.php).
4. Persist forecasts and show headways and 2-hour alerts in Analytics UI.
5. Schedule nightly/Hourly updates; track jobs and accuracy.
6. Tune simple features and thresholds to hit ≥80% accuracy.

## References in Codebase
- Dispatch source events: [log_entry_exit.php](file:///c:/xampp/htdocs/tmm/admin/api/module5/log_entry_exit.php)
- Analytics panel to extend: [submodule3.php](file:///c:/xampp/htdocs/tmm/admin/pages/module5/submodule3.php)
- Workflow alignment: [master_workflow.md](file:///c:/xampp/htdocs/tmm/docs/master_workflow.md)

## Step-by-Step Implementation (Checklist You Can Trigger)
1) Prepare Data & Schema
- Confirm Dispatch events exist and are consistent: see [log_entry_exit.php](file:///c:/xampp/htdocs/tmm/admin/api/module5/log_entry_exit.php).
- Add demand tables to DB initializer: demand_forecasts and demand_forecast_jobs (see “Schema Additions” above). If not present, request “Add demand_forecasts and demand_forecast_jobs to admin/includes/db.php”.
- Ensure dynamic caps table route_cap_schedule exists; already added in DB initializer. If missing, request “Add route_cap_schedule to admin/includes/db.php”.

2) Build Aggregated Series
- Create a PHP API to export dispatch counts per terminal×route×time window from terminal_logs. Suggested path: admin/api/analytics/export_dispatch_series.php.
- Start with hourly counts; include terminal_id, route_id, ts_hour, trips.
- You can prompt: “Create export_dispatch_series.php to return hourly dispatch counts”.

3) Set Up Forecast Service (Local)
- Install Python 3.11 and create a venv:
  - Windows: open PowerShell in project root and run:
    - py -3.11 -m venv venv
    - .\venv\Scripts\Activate.ps1
    - pip install fastapi uvicorn pandas numpy scikit-learn statsmodels
    - Optional: pip install xgboost prophet
- Create a minimal FastAPI app (app.py) with /health and /forecast endpoints (see “Minimal FastAPI Skeleton”).
- Run the service locally: uvicorn app:app --host 127.0.0.1 --port 8001.
- Prompt: “Generate app.py FastAPI skeleton and requirements.txt for forecasting”.

4) Baseline Forecast
- Implement baseline method: last week same hour and moving average fallback.
- Return forecasts array with ts and forecast_trips; include lower/upper_ci as simple ±10–20% bands initially.
- Prompt: “Add baseline forecast logic to FastAPI /forecast using moving average + last-week”.

5) PHP Wrapper & Persistence
- Create a PHP endpoint to call the microservice and persist results into demand_forecasts.
- Suggested path: admin/api/analytics/run_forecast.php (params: terminal_id, route_id, horizon_min, granularity_min).
- After calling the service, insert rows into demand_forecasts; set model_version to “baseline_v1”.
- Prompt: “Create run_forecast.php to call FastAPI and save to demand_forecasts”.

6) UI Overlay in Module 5
- Extend [submodule3.php](file:///c:/xampp/htdocs/tmm/admin/pages/module5/submodule3.php) with a ‘Forecasts’ panel:
  - Show next N time slots’ forecast_trips.
  - Compute recommended headway = slot_minutes / max(1, forecast_trips). Display suggested vehicles.
- Prompt: “Add Forecasts panel to submodule3.php showing forecasted trips and headway”.

7) Dynamic Caps from Forecasts
- Implement a PHP job to compute demand-capacity ratio (DCR) per route from demand_forecasts and authorized vehicles, then write route_cap_schedule.
- Suggested path: admin/api/analytics/compute_caps.php (thresholds: θ=0.7, confidence≥0.6 initially).
- Prompt: “Create compute_caps.php to write route caps based on DCR”.

8) Enforce Gates (Already Partly Done)
- Assignment: [assign_route.php](file:///c:/xampp/htdocs/tmm/admin/api/module1/assign_route.php) consults latest cap and static max_vehicle_limit; blocks on capacity.
- Roster Approval: [approve_roster.php](file:///c:/xampp/htdocs/tmm/admin/api/module5/approve_roster.php) enforces same cap logic in batch.
- Permit Activation: verify payment before Active; optionally keep Pending Activation when oversupply flagged (extend update_permit.php if required).
- Prompt: “Verify assignment and roster gates use route_cap_schedule; add permit gating if needed”.

9) Scheduling & Automation
- Use Windows Task Scheduler to:
  - Nightly: export series → train models (optional) → run forecasts → compute caps.
  - Hourly: refresh near-term forecasts and caps for peak periods.
- Prompt: “Provide Task Scheduler steps and scripts to automate nightly training and caps”.

10) Evaluation & Calibration
- Create an admin page or API to compute RMSE/MAPE per route and time-of-day; store in demand_forecast_jobs or a metrics table.
- Review thresholds (θ, confidence) monthly; adjust caps toward fairness and minimum service floors.
- Prompt: “Add evaluation endpoint to compute RMSE/MAPE and report by route”.

11) Governance & Overrides
- Implement a simple admin UI to set manual caps and time windows; log reason and user.
- Show override state in analytics with audit trail.
- Prompt: “Add admin UI to edit route_cap_schedule; record overrides with reason”.

12) Gradual Model Upgrades
- After baselines stabilize, add SARIMA/Prophet for seasonality; later consider GBM or LSTM.
312→- Maintain model_version and backtesting reports; prefer ensemble only when it consistently beats baseline.
313→- Prompt: “Upgrade forecast service to SARIMA and add model_version=‘sarima_v1’”.
314→
315→**Status: COMPLETED** (Baseline Python Service Deployed & Integrated)
316→
317→## Recommended Prompt Sequence
- Step 1: “Export hourly dispatch counts for simple modeling.”
- Step 2: “Implement Excel sheets or Python Linear Regression for forecasts.”
- Step 3: “Use PHP fallback if service unavailable; add 2-hour alerts.”
- Step 4: “Persist to demand_forecasts and show headways and alerts in UI.”
- Step 5: “Compute simple DCR-based caps and enforce through gates.”
- Step 6: “Schedule nightly/hourly updates; track accuracy and jobs.”
- Step 7: “Optionally add Random Forest and event flags for improvement.”

## Phased Delivery
- Phase 0 — Data Cleanup: ensure dispatch logs and route mapping are consistent; fix missing or duplicate entries.
- Phase 1 — Baseline Forecast & Alerts: implement PHP fallback baseline; display headways; show 2-hour alerts in Analytics.
- Phase 2 — Simple Regression: add Linear Regression (Excel or Python) using hour/day features and event flags; evaluate and log accuracy (MAPE).
- Phase 3 — Recommendations: generate “vehicles to add” per window; publish simple guidance to TODAs.
- Phase 4 — Caps & Governance: compute DCR-based caps; enforce via existing gates; maintain overrides and minimum service floors.
- Phase 5 — Scheduling & Monitoring: nightly refresh; hourly alerts; add evaluation dashboard; iterate toward ≥80% accuracy.
