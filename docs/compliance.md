# COMPLIANCE CHECKLIST (Transport & Mobility Management / TMM)

This document describes what is currently implemented in the TMM system codebase (Admin + Operator + Commuter portals). It avoids claiming features that are not present in the repository.

## 1) Core Functionalities (ISO/IEC 25010 + TAM)

### 1.1 User Management & Role-Based Access Control (RBAC)
- Role/permission model with per-page and per-API permission checks.
- Admin UI for managing users, roles, password resets, and exports.
- Implemented roles include: SuperAdmin, Admin / Transport Officer, Franchise Officer, Encoder, Inspector, Traffic Enforcer, Treasurer / Cashier, Terminal Manager.

### 1.2 Module 1: PUV Database
- Operator encoding and review workflows (including “submissions” review from the Operator Portal).
- Vehicle encoding, linking vehicles to operators, and link request review.
- Operator document verification and vehicle document verification workflows.
- Routes/corridors management (route definitions, origins/destinations, vehicle type fields).
- Ownership transfer review/approval workflow (admin-side processing of transfer requests).

### 1.3 Module 2: Franchise Management
- Franchise application submission (assisted/admin encoding + operator portal submissions).
- Application lifecycle tracking (list views + filtering + detail view by application).
- Endorsement workflow (LGU endorsement) and LTFRB issuance entry (PA/CPC issuance details).
- Franchise route assignment views and vehicle assignment under routes/franchise.

### 1.4 Module 3: Violation & Ticketing Management
- Violation recording with evidence attachment and workflow statuses (Pending / Verified / Closed).
- STS tickets tracking (ticket details, payment/status updates) and linking violations to STS tickets.
- Reports/monitoring page for ticketing metrics and summaries.

### 1.5 Module 4: Vehicle Registration & Inspection
- Vehicle registration listing and registration data management.
- Inspection scheduling (schedule/reschedule/cancel/overdue/no-show handling).
- Inspection checklist execution with stored results, document enforcement, and report generation (HTML/PDF endpoints).

### 1.6 Module 5: Parking & Terminal Management
- Terminal list management, including route mapping per terminal.
- Terminal assignment for vehicles and terminal operations views.
- Parking/terminal slots management, queue tracking, and payment recording.
- Occupancy/operations dashboards for monitoring.

### 1.7 Dashboards, Settings, and Admin Utilities
- Admin dashboards (system overview + module rollups).
- Settings pages for general and security-related configuration.

## 2) AI / “Intelligence” Features (as currently implemented)

The repository includes analytics endpoints and dashboards that combine internal operational data with external context sources. There is no IoT device ingestion code in this codebase.

- Demand/operations insights endpoints that can incorporate:
  - Weather context
  - Traffic context
  - Events/holidays context
- Decision support/ops insights pages in the admin portal that consume these analytics endpoints.

## 3) API Integration / Portals

### 3.1 Internal APIs (Admin)
- Most modules have dedicated JSON APIs for list/create/update/export operations.
- Import/export APIs exist for multiple datasets (operators, vehicles, routes, terminals, registrations, etc.).

### 3.2 Public Portals (Citizen/Operator)
- Operator portal APIs for operator-side workflows (submissions, vehicle linkage requests, etc.).
- Commuter portal APIs for route and fare consumption (read-only data surfaces).

### 3.3 Integrations
- Integration endpoints exist for Treasury and Permits interoperability (server-to-server style endpoints and callbacks).

## 4) Physical Server Setup & Configuration

Current runtime assumptions (based on repository structure):
- PHP-based web application running under an Apache/PHP stack (commonly XAMPP in local deployments).
- MySQL/MariaDB database accessed via project DB helper.
- File uploads are used for documents, evidence, inspection uploads, and exports (paths/handlers are implemented in multiple admin APIs).

## 5) Advanced Security Features (Data Privacy Act / ISO 27001 alignment)

### 5.1 Authentication & Credential Security
- Password hashing and verification are implemented for user accounts (password_hash/password_verify).
- Email OTP functions are implemented (table-backed OTP storage + email delivery through the mailer).
- reCAPTCHA verification helpers exist and are used in portal login flows.

### 5.2 Authorization
- RBAC permissions are enforced across admin pages and APIs using centralized auth helpers.
- Sidebar/navigation visibility is permission-aware.

### 5.3 Session Security
- Session inactivity timeout enforcement exists in the admin auth layer.
- Session-based identity is used across admin pages/APIs.

### 5.4 Audit & Accountability
- Login/audit activity is available via admin pages and export endpoints (login audit export exists).

### 5.5 Input Validation & Injection Safety
- Many endpoints use prepared statements for database interactions.
- Some endpoints also use dynamic SQL; these should be reviewed as part of hardening, but are part of the current implementation.

### 5.6 Known Gaps / Security Notes (for accuracy)
- Some parts of the authorization layer include compatibility/alias logic and special-casing for legacy roles/permissions; deployments should standardize on one RBAC schema.
- Development CORS helpers exist; production deployments should ensure CORS is locked down appropriately.

## 6) Analytics (Operational)
- Dashboard KPIs: revenue totals (parking + ticket payments), counts for vehicles/operators/tickets, occupancy and hotspot summaries.
- Module reports:
  - Violation/ticket reporting views
  - Parking/terminal occupancy, queue, and payment dashboards
- Decision support pages that call analytics endpoints for insights.

## 7) Import/Export & Reports
- CSV export endpoints across modules (operators, vehicles, routes, terminals, registrations, users, login audit, etc.).
- “Excel export” is implemented as HTML table output that can be opened in spreadsheet tools.
- PDF/print outputs exist for certain reports (e.g., inspection report endpoints; select module PDFs).

## 8) UI Look and Feel
- Responsive Tailwind-based UI with dark mode classes across admin pages.
- Modern interaction patterns:
  - Modal dialogs for create/edit/import flows
  - Toast notifications for feedback
  - Iconography via Lucide (data-lucide)
