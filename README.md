# Transport Management Module (TMM)

A comprehensive Public Utility Vehicle (PUV) management system designed for Local Government Units (LGUs). This system handles vehicle registration, franchise management, law enforcement, terminal operations, and AI-powered demand forecasting.

## Modules

1.  **Module 1: PUV Database** - Vehicle registration, operator management, and route assignment.
2.  **Module 2: Franchise Management** - LPTRP compliance, franchise applications, and renewals.
3.  **Module 3: Law Enforcement** - Violation ticketing, settlement, and franchise suspension enforcement.
4.  **Module 4: Inspection & Compliance** - Digital inspection checklists, certificate issuance, and document verification.
5.  **Module 5: Terminal Management** - Terminal operations, fee collection, dispatching, and analytics.

## New Feature: AI-Powered Demand Forecasting

The system now includes a hybrid PHP-Python forecasting engine to predict passenger demand and optimize dispatching.

### Key Capabilities
*   **Demand Forecasting:** Predicts passenger traffic for the next 4-24 hours using historical data (Weighted Average Model).
*   **Dynamic Caps:** Automatically adjusts route capacity limits based on forecasted demand to prevent oversupply.
*   **Dispatch Recommendations:** Provides real-time suggestions to dispatchers on when to add more units.
*   **Exogenous Factors:** Ingests weather, traffic, and event data to refine predictions.

### System Architecture
*   **Backend:** PHP (Core Logic) + MySQL (Data Store)
*   **Analytics Service:** Python FastAPI (Time-series forecasting)
*   **Automation:** Windows Task Scheduler (Hourly/Nightly jobs)

## Setup & Installation

### Prerequisites
*   XAMPP (PHP 8.x, MySQL/MariaDB)
*   Python 3.10+
*   Composer (optional, for PHP dependencies)

### Installation Steps
1.  **Clone the repository** to `C:\xampp\htdocs\tmm`.
2.  **Database Setup:**
    *   Import the schema by visiting the setup pages or running the SQL scripts in `admin/includes/db.php`.
    *   Ensure `demand_forecasts`, `route_cap_schedule`, and `terminal_logs` tables are created.
3.  **Python Service Setup:**
    *   Navigate to `services/forecasting`.
    *   Run `run_service.bat` to create the virtual environment, install dependencies, and start the service on Port 8000.
    *   *Note: Keep this console window open or configure as a system service.*
4.  **Scheduler Setup:**
    *   Use Windows Task Scheduler to run `scripts/run_hourly.bat` every hour.
    *   Run `scripts/run_nightly.bat` every night at 00:00.

## Usage
*   **Admin Panel:** Access at `http://localhost/tmm/admin`.
*   **Terminal Analytics:** Go to **Module 5 > Analytics** to view forecasts and recommendations.
*   **API Docs:** See `docs/` folder for detailed API specifications.
