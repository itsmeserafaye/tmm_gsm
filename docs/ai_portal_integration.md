# AI Integration Guide for Citizen & Operator Portals

This guide outlines how to expose the TMM's AI-powered demand forecasting and analytics to external portals for Citizens (Commuters) and Operators (Drivers/Coops).

## 1. Overview

Integrating AI insights into public-facing portals enhances transparency and efficiency:
*   **For Citizens:** Provides predictability (wait times, crowding levels), improving the commute experience.
*   **For Operators:** Maximizes earnings by guiding them to high-demand times/routes and ensures compliance with dynamic caps.

## 2. Feature Specification

### A. Citizen Portal (Public)
Focus: Convenience & Predictability.

| Feature | Description | Data Source |
| :--- | :--- | :--- |
| **Crowding Indicator** | Real-time status (Low, Moderate, High) based on current supply vs. forecasted demand. | `terminal_logs` (supply) vs `demand_forecasts` (demand) |
| **Estimated Wait Time** | Approx. minutes until next departure based on current headway and queue. | `terminal_logs` (avg headway) |
| **Best Time to Travel** | Suggestion for off-peak hours based on historical trends. | `demand_forecasts` (24h view) |

### B. Operator Portal (Restricted)
Focus: Efficiency & Compliance.

| Feature | Description | Data Source |
| :--- | :--- | :--- |
| **Demand Heatmap** | Visual chart showing expected passenger volume for the next 4-8 hours. | `demand_forecasts` |
| **Cap Status** | Live view of Route Capacity (e.g., "45/50 slots filled"). Alerts when near cap. | `route_cap_schedule` |
| **Dispatch Advice** | Actionable tips (e.g., "Dispatch now to meet 5 PM rush"). | `demand_forecasts` (recommendations) |

## 3. Technical Implementation

### A. API Layer
Create a new set of lightweight, read-only APIs.

#### 1. Public API (Citizen)
*   **Endpoint:** `GET /api/public/status?terminal_id=X&route_id=Y`
*   **Response:**
    ```json
    {
      "status": "Moderate",
      "wait_time_min": 15,
      "next_best_slot": "10:00 AM"
    }
    ```
*   **Security:** Public access, Rate-limited (e.g., 60 req/min).

#### 2. Operator API (Secure)
*   **Endpoint:** `GET /api/operator/dashboard?operator_id=Z`
*   **Response:**
    ```json
    {
      "current_cap": 50,
      "active_units": 42,
      "remaining_slots": 8,
      "forecast": [
        {"time": "17:00", "demand": "High", "trips": 25},
        {"time": "18:00", "demand": "High", "trips": 30}
      ]
    }
    ```
*   **Security:** Token-based Authentication (Bearer Token).

### B. Frontend Integration

#### Citizen Portal (Mobile/Web)
*   **UI Components:** Simple "Traffic Light" cards (Green=Low, Yellow=Mod, Red=High).
*   **Library:** No heavy charting needed; use simple CSS badges.

#### Operator Portal (Dashboard)
*   **UI Components:**
    *   **Bar Chart:** Forecasted Demand vs. Current Supply (using Chart.js).
    *   **Gauge:** Capacity utilization (Green -> Red).
*   **Notifications:** Push notifications (or SMS) when "High Demand" is detected.

## 4. Implementation Steps

1.  **Backend (API):**
    *   Create `admin/api/public/` directory.
    *   Implement `get_terminal_status.php` (Public).
    *   Implement `get_operator_insights.php` (Secure).
2.  **Security:**
    *   Implement API Key validation for the Operator endpoint.
    *   Add CORS headers to allow requests from the Portal domain.
3.  **Frontend (Portal):**
    *   Fetch data via AJAX/Fetch API.
    *   Render widgets.

## 5. Sample Code (PHP API)

**`admin/api/public/get_terminal_status.php`**

```php
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow public access
require_once '../../includes/db.php';

$tid = $_GET['terminal_id'] ?? 0;
$rid = $_GET['route_id'] ?? '';

// 1. Get Forecast
$forecast = 0;
// ... query demand_forecasts ...

// 2. Get Active Supply
$supply = 0;
// ... query terminal_logs ...

// 3. Determine Status
$ratio = $supply > 0 ? $forecast / $supply : 0;
$status = "Low";
if ($ratio > 1.2) $status = "High";
elseif ($ratio > 0.8) $status = "Moderate";

echo json_encode(['status' => $status, 'forecast' => $forecast]);
?>
```
