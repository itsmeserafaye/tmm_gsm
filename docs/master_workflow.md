# Comprehensive Master Workflow & System Implementation Guide

This document serves as the authoritative "Blue Book" for the City Transport Management System (TMM). It provides an exhaustive detailed breakdown of every implemented function, logic flow, database interaction, and user journey across all modules.

---

## I. System Architecture & Technical Implementation

### 1. Core Technology Stack
*   **Backend Runtime:** PHP 8.x (Native/Vanilla)
    *   **Architecture:** Page-Controller pattern with centralized `auth.php` and `db.php` includes.
    *   **Database Abstraction:** `mysqli` with prepared statements for SQL injection prevention.
*   **Database:** MySQL / MariaDB
    *   **Storage Engine:** InnoDB (Supports Foreign Key constraints and Transactions).
    *   **Charset:** `utf8mb4_unicode_ci` (Full Unicode support).
*   **Frontend:**
    *   **Structure:** HTML5 Semantic Markup.
    *   **Styling:** TailwindCSS v3.4 (via CDN) for utility-first responsive design.
    *   **Interactivity:** Vanilla JavaScript (ES6+) with DOM manipulation.
    *   **Icons:** Lucide Icons (SVG).
*   **Infrastructure:**
    *   **Web Server:** Apache (XAMPP environment).
    *   **File Storage:** Local filesystem (`/uploads/`) for documents and evidence images.

### 2. Authentication & Security Layer
*   **Session Management:**
    *   **Mechanism:** PHP Native Sessions (`session_start()`).
    *   **Security:** `session_regenerate_id(true)` upon login to prevent Session Fixation.
    *   **Timeout:** Auto-logout after inactivity (Configurable in Settings, default: 30 mins).
*   **Login Workflow:**
    1.  **User Entry:** Enters Email and Password.
        *   *Feature:* **Eye Icon** toggles password visibility (`type="password"` <-> `type="text"`).
    2.  **Input Sanitization:** Email is trimmed and validated using `FILTER_VALIDATE_EMAIL`.
    3.  **Authentication:**
        *   Query `rbac_users` by email.
        *   Check `status` column: Must be `'Active'`.
        *   Verify Hash: `password_verify($input, $hash)` (Bcrypt).
    4.  **Session Hydration:**
        *   `$_SESSION['user_id']`: Primary Key.
        *   `$_SESSION['role']`: Primary Role (e.g., 'Encoder').
        *   `$_SESSION['permissions']`: Array of granular permissions (e.g., `['module1.write', 'reports.view']`).
    5.  **Audit Logging:** Insert record into `rbac_login_audit` (User ID, IP Address, User Agent, Timestamp, Success/Fail).
*   **Access Control (RBAC):**
    *   **Middleware:** `require_role(['Admin', 'Encoder'])` checks session role before page load.
    *   **Granular Checks:** `has_permission('tickets.issue')` controls visibility of specific buttons.

---

## II. The Dashboard (Command Center)
**File:** `admin/pages/dashboard.php`

### 1. Real-time Status Widgets
*   **Total Vehicles:** `SELECT COUNT(*) FROM vehicles WHERE status = 'Active'`
*   **Pending Franchises:** `SELECT COUNT(*) FROM franchise_applications WHERE status = 'Pending'`
*   **Today's Income:** `SELECT SUM(amount) FROM transactions WHERE date(created_at) = CURRENT_DATE`
*   **Active Violations:** `SELECT COUNT(*) FROM tickets WHERE status = 'Unpaid'`

### 2. Visual Analytics (Chart.js)
*   **Revenue Trend:** Aggregates transaction data grouped by month for the last 6 months.
*   **Violation Distribution:** Aggregates ticket data grouped by `violation_type`.

---

## III. Module 1: Public Utility Vehicle (PUV) Database
**Objective:** Maintain a "Single Source of Truth" for the city's transport fleet.

### Submodule 1: Vehicle & Ownership Registry
**File:** `admin/pages/module1/submodule1.php`
*   **Database Table:** `vehicles`
*   **Workflow:**
    1.  **Input:** User clicks "Register Vehicle".
    2.  **Form Data:**
        *   `Plate Number` (Unique Index - duplicates rejected).
        *   `Engine Number`, `Chassis Number` (Hardware IDs).
        *   `Make`, `Model`, `Year`, `Fuel Type` (Metadata).
        *   `Vehicle Type` (e.g., Tricycle, Jeepney, UV Express).
    3.  **Document Upload:**
        *   File: LTO OR/CR (PDF/JPG).
        *   Storage: Saved to `uploads/documents/`.
        *   DB Record: Insert into `documents` table linked to `plate_number`.
    4.  **Status Initialization:** Default status set to `Active`.

### Submodule 2: Operator & Franchise Validation
**File:** `admin/pages/module1/submodule2.php`
*   **Database Tables:** `operators`, `cooperatives`, `vehicle_ownership`
*   **Workflow:**
    1.  **Operator Registration:**
        *   **Individual:** First Name, Last Name, Address, Contact.
        *   **Juridical (Coop):** Cooperative Name, Accreditation No., Chairman Name.
    2.  **Linkage Process (The "Link Vehicle" Modal):**
        *   Select `Vehicle` (Dropdown populated from `vehicles` table).
        *   Select `Operator` (Dropdown from `operators`).
        *   Select `Cooperative` (Dropdown from `cooperatives`).
        *   **Validation:** A vehicle can only belong to ONE operator at a time.
        *   **Action:** Updates `operator_id` and `coop_id` foreign keys in `vehicles` table.

### Submodule 3: Route Management (LPTRP)
**File:** `admin/pages/module1/submodule3.php`
*   **Database Table:** `routes`
*   **Workflow:**
    1.  **Route Definition:**
        *   `Route Code`: Unique Identifier (e.g., "R-01").
        *   `Origin` & `Destination`: Text description of endpoints.
        *   `Structure`: Loop / Point-to-Point.
        *   `Distance`: In Kilometers.
    2.  **Capacity Planning (Crucial Logic):**
        *   Input `Authorized Units` (e.g., 50).
        *   **Logic:** This number is the hard limit for franchises on this route.

---

## IV. Module 2: Franchise Management
**Objective:** Manage the lifecycle of the Motorized Tricycle Operator's Permit (MTOP) / City Franchise.

### Submodule 1: Application Review
**File:** `admin/pages/module2/submodule1.php`
*   **Database Table:** `franchise_applications`
*   **Workflow:**
    1.  **Application Entry:**
        *   Select `Vehicle` (Must be registered in Module 1).
        *   Select `Route` (Must be defined in Module 1).
        *   **System Check:** Checks if vehicle already has an active franchise.
    2.  **Case Generation:**
        *   System generates `Case No.` format: `CASE-{YYYY}-{SEQ}` (e.g., CASE-2024-005).
    3.  **Initial State:** Status set to `Received`.

### Submodule 2: Endorsement & Validation
**File:** `admin/pages/module2/submodule2.php`
*   **Logic:** The "Gatekeeper" Module.
*   **Workflow:**
    1.  **Review:** Franchise Officer opens the application.
    2.  **Automated Capacity Check:**
        *   Query: `SELECT COUNT(*) FROM franchises WHERE route_id = [Target Route]`.
        *   Compare: If `Count >= Authorized_Units` (from Module 1), the "Endorse" button is **DISABLED**.
        *   *Result:* Prevents route saturation.
    3.  **Endorsement Action:**
        *   User clicks "Endorse".
        *   **Integration Trigger:** System packages data (Applicant + Vehicle) and sends to **Permits & Licensing System** API.
        *   **State Transition:** Status changes `Received` -> `Endorsed`.

### Submodule 3: Renewal & History
**File:** `admin/pages/module2/submodule3.php`
*   **Database Table:** `franchise_history`
*   **Workflow:**
    1.  **Expiration Monitoring:** System flags franchises nearing expiration (e.g., < 30 days).
    2.  **Renewal Action:** Creates a new application entry while archiving the old record in `franchise_history`.

---

## V. Module 3: Traffic Violation & Ticketing
**Objective:** Apprehension and Penalty Collection.

### Submodule 1: Violation Logging
**File:** `admin/pages/module3/submodule1.php`
*   **Database Table:** `tickets`
*   **Workflow:**
    1.  **Ticket Issuance:**
        *   Input: `Plate No.` (Auto-searches Module 1 for vehicle details).
        *   Input: `Driver Name`, `License No.`.
        *   Select: `Violation Type` (e.g., "Obstruction", "Colorum").
        *   Input: `Location` (Text / Coordinates).
    2.  **Evidence:** Upload photo (saved to `uploads/evidence/`).
    3.  **Cost Calculation:** Auto-populates `Amount` based on Violation Type configuration.
    4.  **State:** Status set to `Unpaid`.

### Submodule 2: Settlement & Payment
**File:** `admin/pages/module3/submodule2.php`
*   **Workflow:**
    1.  **Search:** Treasurer searches by Ticket No. or Plate No.
    2.  **Payment Processing:**
        *   Input: `OR Number` (Official Receipt).
        *   Input: `Amount Paid`.
    3.  **Settlement Action:**
        *   Click "Mark as Paid".
        *   **Integration Trigger:** Sends payment details to **Treasury System**.
        *   **State Transition:** Status changes `Unpaid` -> `Settled`.
        *   **Inventory Update:** If violation involved impounding, releases vehicle.

### Submodule 3: Analytics
**File:** `admin/pages/module3/submodule3.php`
*   **Heatmaps:** Visualizes top violation locations.
*   **Recidivism:** Identifies repeat offenders (drivers/operators with > 3 violations).

---

## VI. Module 4: Vehicle Inspection & Registration
**Objective:** Technical compliance verification.

### Submodule 1: Verification & Scheduling
**File:** `admin/pages/module4/submodule1.php`
*   **Database Table:** `inspection_schedules`
*   **Workflow:**
    1.  **Eligibility Check:** System ensures vehicle exists in Module 1.
    2.  **Scheduling:** Assigns a date/time slot and an Inspector.

### Submodule 2: Inspection Execution
**File:** `admin/pages/module4/submodule2.php`
*   **Database Table:** `inspections`
*   **Workflow:**
    1.  **Digital Checklist (Form):**
        *   Brakes (Pass/Fail)
        *   Lights (Pass/Fail)
        *   Smoke Emission (Pass/Fail)
        *   Structural Integrity (Pass/Fail)
    2.  **Logic:**
        *   IF *any* item is "Fail" -> Overall Result: `Failed`.
        *   IF *all* items are "Pass" -> Overall Result: `Passed`.
    3.  **Photo Proof:** Inspector uploads photo of the vehicle on the testing ramp.

### Submodule 3: Compliance & Certification
**File:** `admin/pages/module4/submodule3.php`
*   **Output:** Inspection Certificate.
*   **Features:**
    *   Generates PDF on-the-fly.
    *   Embeds **QR Code** containing: Plate No, VIN, Expiry Date, and Inspection Hash for validity checking.

---

## VII. Module 5: Parking & Terminal Management
**Objective:** Management of static transport assets.

### Submodule 1: Terminal Management
**File:** `admin/pages/module5/submodule1.php`
*   **Database Table:** `terminals`, `terminal_assignments`
*   **Workflow:**
    1.  **Terminal Creation:** Define Name, Location (Lat/Long), Max Capacity.
    2.  **Roster Management:**
        *   **Action:** Add Vehicle to Terminal.
        *   **Gate:** System checks if Vehicle is `Franchised` and `Inspected`.
        *   **Result:** Links Vehicle to Terminal.

### Submodule 2: Parking Area Management
**File:** `admin/pages/module5/submodule2.php`
*   **Database Table:** `parking_areas`
*   **Workflow:**
    1.  **Slot Tracking:**
        *   Manual or Sensor-based toggle of `Occupied` vs `Free`.
        *   Calculates `% Utilization`.

### Submodule 3: Fees, Enforcement & Analytics
**File:** `admin/pages/module5/submodule3.php`
*   **Fee Collection:**
    *   **Terminal Fee:** Daily/Monthly fee for using the terminal.
    *   **Parking Fee:** Hourly rate.
*   **Integration:** All collected fees are pushed to the **Treasury System** integration endpoint.

---

## VIII. User Management (Settings)
**File:** `admin/pages/users/accounts.php`

### 1. Account Creation Flow
*   **Fields:** First Name, Middle Name, Last Name, Suffix, Email, Employee No., Department, Position, Role.
*   **Input Handling:**
    *   **Department/Position:** Dropdowns with "Other" option for custom entry.
    *   **Employee No:** Auto-formats to `XXX-XXXX-XXX` and auto-capitalizes.
    *   **Password:** Optional. If blank, auto-generates 14-char secure string.

### 2. Role-Based Access Control (RBAC) Logic
*   **SuperAdmin:** Full Access to all modules + User Management + Activity Logs.
*   **Admin:** Full Access to operational modules (1-5). No access to User Management.
*   **Encoder:** Write access to Module 1 & 2. Read-only for others.
*   **Inspector:** Write access to Module 4.
*   **Treasurer:** Write access to Payment modules (Module 3 Sub 2, Module 5 Sub 3).

---

## IX. External Integrations (Data Exchange)
**Specs:** `INTEGRATION_SPECIFICATIONS.md`

### 1. Permits & Licensing System
*   **Direction:** Outbound (Send).
*   **Trigger:** Franchise Officer clicks "Endorse" in Module 2.
*   **Payload:** Applicant Metadata, Vehicle Metadata, Route Info.

### 2. Revenue & Treasury System
*   **Direction:** Outbound (Send) & Inbound (Receive).
*   **Trigger:** Ticket Settlement or Fee Payment.
*   **Payload:** Transaction ID, Amount, Payer Details.

### 3. Urban Planning & Zoning
*   **Direction:** Outbound (Send).
*   **Trigger:** Creation of new LPTRP Route in Module 1.
*   **Payload:** Route Path (GeoJSON LineString).
