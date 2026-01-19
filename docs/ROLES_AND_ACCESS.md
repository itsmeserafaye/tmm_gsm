# Roles & Access Control (RBAC)

This document defines the real-world roles in the Transport & Mobility Management (TMM) system, what each role should be able to do, and what access (permissions) they need to perform their work with proper separation of duties.

## How Access Works

- The admin system uses role-based access control with granular permissions (RBAC).
- A user can have one or more roles, and the system derives an effective permission set per user.
- Pages, buttons, and API actions are guarded by permission checks (e.g., `module2.franchises.manage`).

## Permission Catalog

| Permission | What it allows |
|---|---|
| `dashboard.view` | View dashboard widgets and overview screens |
| `analytics.view` | View analytics and AI insights |
| `analytics.train` | Encode demand/observation logs for forecasting |
| `module1.view` | Read-only access to PUV Database screens |
| `module2.view` | Read-only access to Franchise Management screens |
| `module3.view` | Read-only access to Traffic Violation Monitoring screens |
| `module4.view` | Read-only access to Inspection & Registration screens |
| `module5.view` | Read-only access to Parking & Terminal screens |
| `module1.vehicles.write` | Create/update vehicles and upload/verify vehicle documents |
| `module1.routes.write` | Create/update routes and route metadata |
| `module1.coops.write` | Create/update cooperatives and their compliance metadata |
| `module2.franchises.manage` | Create applications, validate, endorse, and manage compliance cases |
| `module4.inspections.manage` | Schedule inspections, assign inspectors, record results, generate certificates |
| `tickets.issue` | Issue citations (traffic/parking) and attach evidence |
| `tickets.validate` | Validate citations, escalate, and enforce compliance workflow |
| `tickets.settle` | Record payments and settle citations |
| `parking.manage` | Manage terminals, bays/slots, parking areas, and parking enforcement workflow |
| `reports.export` | Export CSV/PDF and operational reports |
| `settings.manage` | Manage system settings and security policies |

## Real-World Process Map (End-to-End)

1. **Encode Master Records (PUV Database)**
   - Register/update vehicle details and documents (OR/CR).
   - Register operator and cooperative records.
2. **File Franchise Application**
   - Create application and attach required documents.
3. **Validate & Endorse Application**
   - Validate operator, cooperative compliance, and route capacity.
   - Issue endorsement decision and create any compliance case as needed.
4. **Schedule Inspection**
   - Schedule inspection for units that require inspection and are not yet scheduled.
5. **Conduct Inspection & Issue Certificate**
   - Perform checklist, upload photos, generate inspection certificate reference.
6. **Route & Terminal Assignment**
   - Assign unit to route and terminal once eligible.
7. **Operations & Enforcement**
   - Issue traffic/parking citations, validate tickets, collect payments, and close cases.
8. **Reporting & Executive Monitoring**
   - Review dashboards, analytics, compliance trends, and export reports.

## Roles (Admin / Back Office)

### 1) SuperAdmin (City ICTO / System Owner)

**Primary responsibility (real process):**
- Maintain users, roles, permissions, and system configuration.
- Full oversight of all modules and integrations.

**Allowed actions:**
- Create/disable user accounts; assign roles; reset passwords.
- Configure security policy and general settings.
- Perform all operational actions across Modules 1–5.

**Required access (minimum):**
- All permissions (full system).

**Separation-of-duty notes:**
- Use for administration and emergency intervention, not daily operations.

---

### 2) Admin (Transport Management Office Administrator)

**Primary responsibility (real process):**
- Own the business workflow across franchise, inspections coordination, compliance decisions, and operational oversight.

**Allowed actions:**
- Oversee vehicle/operator master records (approve corrections, resolve conflicts).
- Manage franchise applications: validate and endorse.
- Coordinate inspection scheduling and certification oversight.
- Validate citations when required (compliance enforcement).
- Manage parking/terminal operations policy-level items.
- Export operational reports; manage settings if assigned.

**Required access (minimum):**
- `dashboard.view`
- `analytics.view`, `analytics.train` (optional; if training/observation encoding is part of Admin scope)
- `module1.vehicles.write`, `module1.routes.write`, `module1.coops.write`
- `module2.franchises.manage`
- `module4.inspections.manage`
- `tickets.validate`
- `parking.manage`
- `reports.export`
- `settings.manage` (only if Admin is responsible for configuration)

**Separation-of-duty notes:**
- Admin should not be the same user as Treasurer in production.

---

### 3) Encoder (Frontline Data Encoder)

**Primary responsibility (real process):**
- Encode and maintain master records and documents accurately.

**Allowed actions:**
- Register vehicles and upload OR/CR and supporting documents.
- Encode/update cooperative and operator details.
- Encode route references and basic metadata if assigned.
- Print/export lists for verification and fieldwork support.

**Not allowed (recommended):**
- Endorse applications.
- Schedule/approve inspections.
- Settle payments or validate tickets.

**Required access (minimum):**
- `dashboard.view`
- `module1.vehicles.write`
- `module1.routes.write` (if the encoder encodes routes; otherwise keep disabled)
- `module1.coops.write`
- `reports.export` (optional; if they generate printed summaries)

---

### 4) Inspector (Field Inspector / Inspection Office / Traffic Enforcer)

**Primary responsibility (real process):**
- Ensure safety/compliance through inspection and/or field enforcement.

**Allowed actions (inspection):**
- Schedule inspections (or manage assigned schedules).
- Perform inspection checklist and upload inspection photos.
- Generate certificate references upon passing.

**Allowed actions (enforcement):**
- Issue citations and attach evidence.

**Required access (minimum):**
- `dashboard.view`
- `module4.inspections.manage`
- `tickets.issue`
- `analytics.view` (optional; if inspectors review trends/heatmaps)

**Separation-of-duty notes:**
- Inspectors should not validate or settle their own tickets.

---

### 5) Treasurer (City Treasurer / Cashier / Payment Verification)

**Primary responsibility (real process):**
- Record and verify payments; finalize settlement.

**Allowed actions:**
- View ticket/payment references and record settlement.
- Export payment reports as required.

**Payment control (recommended):**
- Payments should be processed/confirmed through Treasury and supported by an official receipt (OR). Non-Treasury roles should not be able to mark transactions as paid.

**Not allowed (recommended):**
- Issue or validate tickets.
- Endorse franchise applications.
- Manage inspections.

**Required access (minimum):**
- `dashboard.view`
- `tickets.settle`
- `reports.export` (optional; for end-of-day reports)

---

### 6) ParkingStaff (Parking Operations / Terminal Operations Staff)

**Primary responsibility (real process):**
- Operate terminals and parking areas: slot/bay management, parking enforcement workflow, and operational tracking.

**Allowed actions:**
- Manage terminal and bay/slot configuration.
- Manage parking areas and enforce parking policy within Module 5 scope.

**Not allowed (recommended):**
- Traffic ticket issuance outside parking workflow.
- Any franchise endorsement or inspection management.

**Required access (minimum):**
- `dashboard.view`
- `parking.manage`

---

### 7) Viewer (Executive / Monitoring / Read-Only)

**Primary responsibility (real process):**
- View-only monitoring for leadership and auditors.

**Allowed actions:**
- View dashboards, module screens, and analytics (read-only).

**Not allowed:**
- Any write action, endorsements, scheduling, ticket issuance/validation/settlement, exports (recommended to keep exports off for Viewer unless explicitly needed).

**Required access (minimum):**
- `dashboard.view`
- `analytics.view`
- `module1.view`, `module2.view`, `module3.view`, `module4.view`, `module5.view`

## Roles (Citizen Portals)

### 8) Operator (Operator Portal Account)

**Primary responsibility (real process):**
- Submit requirements, track status, and comply with inspection/permit steps.

**Allowed actions (typical):**
- Submit/track franchise-related requirements and upload documents.
- View vehicle/application status and inspection schedule status.

**Access model:**
- Operator portal access is separate from admin RBAC; operator users should not access the admin UI.

---

### 9) Commuter (Citizen / Commuter Portal Account)

**Primary responsibility (real process):**
- Submit commuter-side interactions (feedback/requests depending on enabled features).

**Access model:**
- Citizen portal access only; no admin permissions.

## Recommended Assignment Guide (Quick)

- **Encoder**: Module 1 write permissions only; no endorsement, no inspection, no payments.
- **Admin**: Endorse and coordinate across Modules 1–4; manage compliance decisions.
- **Inspector**: Module 4 inspections + ticket issuance; no validation/settlement.
- **Treasurer**: Settlement only; no issuance/validation.
- **ParkingStaff**: Parking/terminal management only.
- **Viewer**: Read-only across modules; no exports unless required by policy.
