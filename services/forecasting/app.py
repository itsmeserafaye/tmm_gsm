from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
import requests
import logging
import os
from datetime import datetime, timedelta, timezone

# Configure Logging
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    handlers=[logging.FileHandler("service.log"), logging.StreamHandler()]
)
logger = logging.getLogger(__name__)

app = FastAPI()

PHP_API_URL = os.getenv("PHP_API_URL", "http://127.0.0.1/tmm/admin/api/analytics/export_dispatch_series.php")

class ForecastRequest(BaseModel):
    terminal_id: int
    route_id: str
    horizon_min: int
    granularity_min: int
    start: str | None = None
    end: str | None = None

@app.get("/health")
def health():
    return {"ok": True, "status": "healthy"}

def fetch_series(route_id: str, terminal_id: int | None, start: str | None, end: str | None):
    params = {"route_id": route_id}
    if terminal_id and terminal_id > 0:
        params["terminal_id"] = terminal_id
    if start and end:
        params["start"] = start
        params["end"] = end
    
    try:
        logger.info(f"Fetching series from {PHP_API_URL} with params {params}")
        r = requests.get(PHP_API_URL, params=params, timeout=10)
        r.raise_for_status()
        j = r.json()
        
        if not j.get("ok"):
            logger.warning(f"PHP API returned error: {j.get('error')}")
            return {}

        rows = j.get("data", [])
        series = {}
        
        # Parse bounds
        try:
            start_dt = datetime.fromisoformat(start.replace("Z", "+00:00")) if start else None
            end_dt = datetime.fromisoformat(end.replace("Z", "+00:00")) if end else None
        except Exception as e:
            logger.warning(f"Date parsing error for bounds: {e}")
            start_dt = None
            end_dt = None

        for row in rows:
            ts_raw = row.get("ts") or row.get("ts_hour")
            trips = row.get("trips")
            try:
                # Assume PHP sends 'Y-m-d H:i:s' in local time (or server time). 
                # For simplicity in baseline, we treat it as naive or UTC.
                # Ideally, we'd know the server timezone.
                ts_dt = datetime.strptime(ts_raw, "%Y-%m-%d %H:%M:%S").replace(tzinfo=timezone.utc)
            except Exception as e:
                logger.debug(f"Skipping row due to date parse error: {ts_raw} - {e}")
                continue
            
            y = int(trips) if trips is not None else 0
            series[ts_dt] = y

        # Fill gaps if range is known
        if start_dt and end_dt:
            # Ensure start_dt/end_dt are UTC for comparison
            if start_dt.tzinfo is None: start_dt = start_dt.replace(tzinfo=timezone.utc)
            if end_dt.tzinfo is None: end_dt = end_dt.replace(tzinfo=timezone.utc)
            
            start_dt = start_dt.replace(minute=0, second=0, microsecond=0)
            end_dt = end_dt.replace(minute=0, second=0, microsecond=0)
            
            cur = start_dt
            while cur <= end_dt:
                if cur not in series:
                    series[cur] = 0
                cur += timedelta(hours=1)
        
        return dict(sorted(series.items()))
    
    except requests.RequestException as e:
        logger.error(f"Network error fetching series: {e}")
        return {}
    except Exception as e:
        logger.error(f"Unexpected error in fetch_series: {e}")
        return {}

def baseline_forecast(series_map: dict, horizon_steps: int, granularity_min: int) -> list[dict]:
    # Align 'now' to granularity
    now = datetime.now(timezone.utc)
    minute_aligned = (now.minute // granularity_min) * granularity_min
    now = now.replace(minute=minute_aligned, second=0, microsecond=0)
    
    vals = [series_map[t] for t in sorted(series_map.keys())]
    tail = vals[-24:] if vals else [] # Last 24 hours of data
    
    # Simple Moving Average of last 24h
    ma_val = sum(tail) / len(tail) if tail else 0.0
    
    out = []
    for i in range(horizon_steps):
        ts = now + timedelta(minutes=granularity_min * i)
        
        # Look back 1 week (same hour)
        # Note: This simple lookback assumes exact 7 days ago data exists in series_map.
        # If series_map is sparse or missing 7 days ago, we fallback to MA.
        lw_ts = ts - timedelta(days=7)
        
        # Find closest key in series_map (since we might have gaps or slight offsets)
        # But our fill_gaps logic above ensures hourly keys exist if range was requested correctly.
        # However, granularity might be < 60min. The series is hourly.
        # We need to map minutes to the hour bucket.
        lw_hour = lw_ts.replace(minute=0, second=0, microsecond=0)
        
        lw_val = series_map.get(lw_hour)
        
        if lw_val is None:
             # Fallback: try to find any data point? Or just use MA.
             y = ma_val
        else:
             # Weighted average: 40% last week, 60% recent trend (MA)
             y = 0.6 * ma_val + 0.4 * float(lw_val)
        
        y = max(0.0, float(y))
        
        # Confidence Intervals (Static for baseline)
        lower = max(0.0, y * 0.8)
        upper = y * 1.2
        
        out.append({
            "ts": ts.isoformat(), 
            "forecast_trips": round(y, 2), 
            "lower_ci": round(lower, 2), 
            "upper_ci": round(upper, 2),
            "model": "baseline_v1_python"
        })
    return out

@app.post("/forecast")
def forecast(req: ForecastRequest):
    logger.info(f"Forecast request: {req}")
    try:
        horizon_steps = max(1, int(req.horizon_min) // int(req.granularity_min))
        
        now = datetime.now(timezone.utc)
        # Request 30 days of history + future buffer? No, just history.
        # To get "last week", we need at least 8 days. 30 days is safe.
        start = (now - timedelta(days=30)).isoformat()
        end = now.isoformat()
        
        series_map = fetch_series(req.route_id, req.terminal_id, start, end)
        
        if not series_map:
            logger.warning("No data series returned, returning zero forecast")
            # Return zero forecast instead of empty list?
            # Or still generate based on 0 MA.
        
        forecasts = baseline_forecast(series_map, horizon_steps, req.granularity_min)
        
        return {
            "ok": True, 
            "terminal_id": req.terminal_id, 
            "route_id": req.route_id, 
            "granularity_min": req.granularity_min, 
            "forecasts": forecasts
        }
    except Exception as e:
        logger.error(f"Forecast generation failed: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail="Internal server error")
